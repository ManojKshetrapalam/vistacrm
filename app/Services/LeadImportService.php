<?php

namespace App\Services;

use App\Models\ImportHistory;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\NotificationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LeadImportService
{
    /**
     * Inspect uploaded file and return headers and preview rows.
     */
    public function inspectFile(string $filePath, string $extension): array
    {
        $rows = $this->readRows($filePath, $extension, 50);

        if (empty($rows)) {
            return [
                'headers' => [],
                'preview' => [],
                'total_rows' => 0,
                'detected_mapping' => [],
            ];
        }

        $headers = array_map('trim', array_map('strval', $rows[0]));
        $headers = array_filter($headers, fn($h) => $h !== '');

        $preview = [];
        for ($i = 1; $i < min(6, count($rows)); $i++) {
            $rowAssoc = [];
            foreach ($headers as $colIndex => $headerName) {
                $rowAssoc[$headerName] = isset($rows[$i][$colIndex]) ? trim((string)$rows[$i][$colIndex]) : '';
            }
            $preview[] = $rowAssoc;
        }

        // Count total rows
        $totalRows = $this->countTotalRows($filePath, $extension);

        return [
            'headers' => array_values($headers),
            'preview' => $preview,
            'total_rows' => max(0, $totalRows - 1), // exclude header
            'detected_mapping' => $this->detectColumnMapping(array_values($headers)),
        ];
    }

    /**
     * Intelligently auto-map column headers.
     */
    public function detectColumnMapping(array $headers): array
    {
        $synonyms = [
            'name' => ['name', 'full name', 'lead name', 'client name', 'contact name', 'customer name', 'person', 'contact'],
            'phone' => ['phone', 'mobile', 'cell', 'contact number', 'phone number', 'mobile number', 'whatsapp', 'tel', 'ph'],
            'email' => ['email', 'email address', 'mail', 'e-mail'],
            'company_name' => ['company', 'company name', 'organisation', 'organization', 'business', 'firm', 'agency', 'client company'],
            'source' => ['source', 'lead source', 'channel', 'platform', 'campaign'],
            'service_interest' => ['service', 'service interest', 'interest', 'product', 'requirement', 'vertical', 'offering'],
            'additional_details' => ['details', 'notes', 'remarks', 'comment', 'description', 'message', 'inquiry'],
            'priority' => ['priority', 'urgency', 'level'],
        ];

        $mapping = [];
        foreach ($headers as $header) {
            $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $header));
            foreach ($synonyms as $field => $synList) {
                if (isset($mapping[$field])) {
                    continue;
                }
                foreach ($synList as $syn) {
                    $normSyn = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $syn));
                    if ($normalized === $normSyn || str_contains($normalized, $normSyn)) {
                        $mapping[$field] = $header;
                        break;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Process full import with validation, duplicate detection, and batch insertion.
     */
    public function processImport(User $user, string $filePath, string $extension, array $columnMapping, array $options): array
    {
        $allRows = $this->readRows($filePath, $extension);
        if (empty($allRows) || count($allRows) < 2) {
            throw new \Exception("The uploaded file does not contain any data rows.");
        }

        $headers = array_map('trim', array_map('strval', $allRows[0]));
        $headerMap = array_flip($headers);

        $nameCol = $columnMapping['name'] ?? null;
        $phoneCol = $columnMapping['phone'] ?? null;
        $emailCol = $columnMapping['email'] ?? null;
        $companyCol = $columnMapping['company_name'] ?? null;
        $sourceCol = $columnMapping['source'] ?? null;
        $serviceCol = $columnMapping['service_interest'] ?? null;
        $detailsCol = $columnMapping['additional_details'] ?? null;
        $priorityCol = $columnMapping['priority'] ?? null;

        if (!$nameCol || !isset($headerMap[$nameCol])) {
            throw new \Exception("A column for 'Lead Name' must be mapped.");
        }
        if (!$phoneCol || !isset($headerMap[$phoneCol])) {
            throw new \Exception("A column for 'Phone Number' must be mapped.");
        }

        $skipDuplicates = !empty($options['skip_duplicates']);
        $assignmentMode = $options['assignment_mode'] ?? 'unassigned'; // 'unassigned', 'specific', 'round_robin'
        $assignedToUserId = $options['assigned_to'] ?? null;

        $employees = [];
        if ($assignmentMode === 'round_robin') {
            $employees = User::where('role', 'employee')->where('is_active', true)->pluck('id')->toArray();
        }
        $roundRobinIndex = 0;

        $existingPhones = Lead::pluck('phone_normalized')->filter()->flip()->toArray();
        $seenPhonesInFile = [];

        $totalRows = count($allRows) - 1;
        $importedCount = 0;
        $duplicateCount = 0;
        $invalidCount = 0;
        $skippedCount = 0;
        $failedRows = [];

        $leadsToInsert = [];
        $activitiesToCreate = [];
        $notificationsToCreate = [];

        for ($i = 1; $i < count($allRows); $i++) {
            $rowNum = $i + 1; // 1-based Excel row number
            $row = $allRows[$i];

            $name = isset($headerMap[$nameCol]) ? trim((string)($row[$headerMap[$nameCol]] ?? '')) : '';
            $phone = isset($headerMap[$phoneCol]) ? trim((string)($row[$headerMap[$phoneCol]] ?? '')) : '';
            $email = ($emailCol && isset($headerMap[$emailCol])) ? trim((string)($row[$headerMap[$emailCol]] ?? '')) : null;
            $company = ($companyCol && isset($headerMap[$companyCol])) ? trim((string)($row[$headerMap[$companyCol]] ?? '')) : null;
            $source = ($sourceCol && isset($headerMap[$sourceCol])) ? trim((string)($row[$headerMap[$sourceCol]] ?? '')) : 'CSV/Excel Import';
            $service = ($serviceCol && isset($headerMap[$serviceCol])) ? trim((string)($row[$headerMap[$serviceCol]] ?? '')) : null;
            $details = ($detailsCol && isset($headerMap[$detailsCol])) ? trim((string)($row[$headerMap[$detailsCol]] ?? '')) : null;
            $priority = ($priorityCol && isset($headerMap[$priorityCol])) ? strtolower(trim((string)($row[$headerMap[$priorityCol]] ?? 'medium'))) : 'medium';

            if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
                $priority = 'medium';
            }

            // Mandatory validation: Name & Phone
            if (empty($name)) {
                $invalidCount++;
                $failedRows[] = [
                    'row' => $rowNum,
                    'name' => $name,
                    'phone' => $phone,
                    'reason' => 'Lead Name is missing or empty.',
                ];
                continue;
            }

            $normalizedPhone = Lead::normalizePhone($phone);
            if (empty($normalizedPhone) || strlen($normalizedPhone) < 7) {
                $invalidCount++;
                $failedRows[] = [
                    'row' => $rowNum,
                    'name' => $name,
                    'phone' => $phone,
                    'reason' => "Invalid phone number ('{$phone}'). Must have at least 7 digits.",
                ];
                continue;
            }

            // Duplicate detection: In file
            if (isset($seenPhonesInFile[$normalizedPhone])) {
                $duplicateCount++;
                if ($skipDuplicates) {
                    $skippedCount++;
                    $failedRows[] = [
                        'row' => $rowNum,
                        'name' => $name,
                        'phone' => $phone,
                        'reason' => "Duplicate phone number within the imported file (matches row {$seenPhonesInFile[$normalizedPhone]}).",
                    ];
                    continue;
                }
            }
            $seenPhonesInFile[$normalizedPhone] = $rowNum;

            // Duplicate detection: In database
            if (isset($existingPhones[$normalizedPhone])) {
                $duplicateCount++;
                if ($skipDuplicates) {
                    $skippedCount++;
                    $failedRows[] = [
                        'row' => $rowNum,
                        'name' => $name,
                        'phone' => $phone,
                        'reason' => "Duplicate phone number already exists in CRM database.",
                    ];
                    continue;
                }
            }

            // Determine assignment
            $assigneeId = null;
            if ($assignmentMode === 'specific' && !empty($assignedToUserId)) {
                $assigneeId = $assignedToUserId;
            } elseif ($assignmentMode === 'round_robin' && !empty($employees)) {
                $assigneeId = $employees[$roundRobinIndex % count($employees)];
                $roundRobinIndex++;
            }

            $leadsToInsert[] = [
                'name' => $name,
                'phone' => $phone,
                'phone_normalized' => $normalizedPhone,
                'email' => !empty($email) ? $email : null,
                'company_name' => !empty($company) ? $company : null,
                'source' => !empty($source) ? $source : 'CSV/Excel Import',
                'service_interest' => !empty($service) ? $service : null,
                'additional_details' => !empty($details) ? $details : null,
                'status' => $assigneeId ? 'assigned' : 'new',
                'priority' => $priority,
                'assigned_to' => $assigneeId,
                'created_by' => $user->id,
            ];

            // Keep track of normalized phone so subsequent rows know it's now in DB
            $existingPhones[$normalizedPhone] = true;
        }

        // Execute batch insertion in database transaction
        DB::beginTransaction();
        try {
            foreach (array_chunk($leadsToInsert, 100) as $chunk) {
                foreach ($chunk as $item) {
                    $referenceId = Lead::generateReferenceId();
                    $lead = Lead::create(array_merge($item, [
                        'reference_id' => $referenceId,
                    ]));

                    $importedCount++;

                    // Activity
                    LeadActivity::create([
                        'lead_id' => $lead->id,
                        'user_id' => $user->id,
                        'activity_type' => 'lead_created',
                        'title' => 'Imported via Batch Import',
                        'description' => "Imported by {$user->name}. Source: {$lead->source}",
                        'metadata' => ['imported_by' => $user->id, 'filename' => basename($filePath)],
                    ]);

                    if ($lead->assigned_to) {
                        $assignee = User::find($lead->assigned_to);
                        if ($assignee) {
                            LeadActivity::create([
                                'lead_id' => $lead->id,
                                'user_id' => $user->id,
                                'activity_type' => 'assignment_change',
                                'title' => "Assigned to {$assignee->name}",
                                'description' => "Assigned during import.",
                                'metadata' => ['assigned_to' => $assignee->id, 'assignee_name' => $assignee->name],
                            ]);

                            // Create notification
                            NotificationLog::create([
                                'lead_id' => $lead->id,
                                'user_id' => $assignee->id,
                                'channel' => 'in_app',
                                'recipient' => $assignee->email,
                                'title' => 'New Lead Assigned from Import',
                                'message' => "You have been assigned lead {$lead->name} ({$lead->reference_id}) from recent import.",
                                'status' => 'delivered',
                                'is_read' => false,
                            ]);
                        }
                    }
                }
            }

            // Save Import History
            $history = ImportHistory::create([
                'user_id' => $user->id,
                'filename' => basename($filePath),
                'file_path' => $filePath,
                'total_rows' => $totalRows,
                'imported_count' => $importedCount,
                'duplicate_count' => $duplicateCount,
                'invalid_count' => $invalidCount,
                'skipped_count' => $skippedCount,
                'assigned_to' => $assignedToUserId,
                'summary' => [
                    'failed_rows' => array_slice($failedRows, 0, 200), // preserve sample of failures
                    'total_failures' => count($failedRows),
                ],
            ]);

            DB::commit();

            return [
                'history_id' => $history->id,
                'total_rows' => $totalRows,
                'imported_count' => $importedCount,
                'duplicate_count' => $duplicateCount,
                'invalid_count' => $invalidCount,
                'skipped_count' => $skippedCount,
                'failed_rows' => $failedRows,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reads all or limited rows from CSV/Excel.
     */
    private function readRows(string $filePath, string $extension, ?int $limit = null): array
    {
        $extension = strtolower($extension);

        if ($extension === 'csv' || $extension === 'txt') {
            $rows = [];
            if (($handle = fopen($filePath, 'r')) !== false) {
                $count = 0;
                while (($data = fgetcsv($handle, 4096, ',')) !== false) {
                    $rows[] = $data;
                    $count++;
                    if ($limit && $count >= $limit) {
                        break;
                    }
                }
                fclose($handle);
            }
            return $rows;
        }

        // PhpSpreadsheet for Excel .xlsx / .xls
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $rows = [];
        $count = 0;
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }
            $rows[] = $rowData;
            $count++;
            if ($limit && $count >= $limit) {
                break;
            }
        }

        return $rows;
    }

    private function countTotalRows(string $filePath, string $extension): int
    {
        $extension = strtolower($extension);
        if ($extension === 'csv' || $extension === 'txt') {
            $lineCount = 0;
            $handle = fopen($filePath, 'r');
            if ($handle) {
                while (!feof($handle)) {
                    $line = fgets($handle);
                    if ($line !== false && trim($line) !== '') {
                        $lineCount++;
                    }
                }
                fclose($handle);
            }
            return $lineCount;
        }

        $spreadsheet = IOFactory::load($filePath);
        return $spreadsheet->getActiveSheet()->getHighestRow();
    }
}
