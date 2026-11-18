<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use App\Services\LeadImportService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LeadImportWorkflowTest extends TestCase
{
    public function test_csv_upload_inspection_and_process(): void
    {
        $admin = User::where('role', 'admin')->first();
        $token = $admin->createToken('test')->plainTextToken;

        // Generate synthetic CSV with unique test leads
        $uniqueNum = rand(100000, 999999);
        $csvContent = "Full Name,Mobile Number,Email,Company,Channel,Requirement,Notes\n" .
            "Import Client A,+91 9100{$uniqueNum},clientA@test.com,Alpha Corp,Website,IT Infrastructure & Hardware AMC,Test notes A\n" .
            "Import Client B,9200{$uniqueNum},clientB@test.com,Beta Ltd,Referral,Enterprise Software Engineering,Test notes B\n" .
            "Invalid Client,,missingphone@test.com,Gamma Ltd,Website,Cloud Solutions,Missing phone\n" .
            "Duplicate Client,+91 9100{$uniqueNum},dupe@test.com,Alpha Corp,Website,IT AMC,Duplicate in file\n";

        $tempPath = tempnam(sys_get_temp_dir(), 'test_import_') . '.csv';
        file_put_contents($tempPath, $csvContent);

        $uploadedFile = new UploadedFile($tempPath, 'leads_batch_test.csv', 'text/csv', null, true);

        // 1. Test upload & preview endpoint (multipart upload)
        $previewResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/import/upload-preview', [
                'file' => $uploadedFile,
            ]);

        $previewResponse->assertStatus(200)
            ->assertJsonStructure([
                'file_token',
                'filename',
                'headers',
                'preview',
                'total_rows',
                'detected_mapping',
            ]);

        $fileToken = $previewResponse->json('file_token');
        $detectedMapping = $previewResponse->json('detected_mapping');

        $this->assertEquals('Full Name', $detectedMapping['name']);
        $this->assertEquals('Mobile Number', $detectedMapping['phone']);

        // 2. Test batch process endpoint
        $processResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/import/process', [
                'file_token' => $fileToken,
                'mapping' => $detectedMapping,
                'skip_duplicates' => true,
                'assignment_mode' => 'round_robin',
            ]);

        $processResponse->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'report' => [
                    'total_rows',
                    'imported_count',
                    'duplicate_count',
                    'invalid_count',
                    'skipped_count',
                    'failed_rows',
                ],
            ]);

        $report = $processResponse->json('report');
        $this->assertEquals(2, $report['imported_count']); // Client A and Client B
        $this->assertEquals(1, $report['invalid_count']);  // Missing phone
        $this->assertEquals(1, $report['duplicate_count']); // In-file duplicate

        // Clean up temp file
        @unlink($tempPath);
    }

    public function test_csv_import_enforces_user_category_restrictions(): void
    {
        $employee = User::where('role', 'employee')->firstOrFail();
        $originalServices = $employee->allowed_services;

        // Restrict employee to only 'Cloud & DevOps Solutions'
        $employee->allowed_services = ['Cloud & DevOps Solutions'];
        $employee->save();

        $token = $employee->createToken('emp-import-test')->plainTextToken;

        $uniqueNum = rand(100000, 999999);
        $csvContent = "Full Name,Mobile Number,Requirement\n" .
            "Allowed Lead,+91 9150{$uniqueNum},Cloud & DevOps Solutions\n" .
            "Forbidden Lead,+91 9250{$uniqueNum},Enterprise Software Engineering\n";

        $tempPath = tempnam(sys_get_temp_dir(), 'test_cat_import_') . '.csv';
        file_put_contents($tempPath, $csvContent);

        $uploadedFile = new UploadedFile($tempPath, 'cat_test.csv', 'text/csv', null, true);

        $previewResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/import/upload-preview', ['file' => $uploadedFile]);
        $previewResponse->assertStatus(200);

        $fileToken = $previewResponse->json('file_token');
        $mapping = [
            'name' => 'Full Name',
            'phone' => 'Mobile Number',
            'service_interest' => 'Requirement',
        ];

        $processResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/import/process', [
                'file_token' => $fileToken,
                'mapping' => $mapping,
                'skip_duplicates' => true,
                'assignment_mode' => 'unassigned',
            ]);

        $processResponse->assertStatus(200);
        $report = $processResponse->json('report');

        // Allowed row succeeds (1), forbidden row fails (1)
        $this->assertEquals(1, $report['imported_count']);
        $this->assertEquals(1, $report['invalid_count']);
        $this->assertStringContainsString('Unauthorized category', $report['failed_rows'][0]['reason']);

        // Clean up
        $employee->allowed_services = $originalServices;
        $employee->save();
        @unlink($tempPath);
    }

    public function test_preview_duplicates_and_round_robin_assignment(): void
    {
        $admin = User::where('role', 'admin')->first();
        $token = $admin->createToken('admin-rr-test')->plainTextToken;

        // Create an existing lead in the CRM to trigger a duplicate match
        $uniqueNum = rand(100000, 999999);
        $existingPhone = "+91 9900{$uniqueNum}";
        $existingLead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Existing Customer',
            'phone' => $existingPhone,
            'phone_normalized' => Lead::normalizePhone($existingPhone),
            'source' => 'Website',
            'status' => 'new',
            'created_by' => $admin->id,
        ]);

        // Create CSV with 1 existing CRM dupe, 1 clean lead, and 1 file duplicate
        $cleanPhone1 = "+91 9911{$uniqueNum}";
        $cleanPhone2 = "+91 9922{$uniqueNum}";
        $csvContent = "Full Name,Mobile Number,Email,Company,Channel,Requirement,Notes\n" .
            "Duplicate In CRM,{$existingPhone},dupe@crm.com,CRM Old Corp,Website,Cloud,CRM Dupe\n" .
            "Clean Alpha,{$cleanPhone1},alpha@test.com,Alpha Corp,Referral,IT Hardware,Clean Alpha\n" .
            "Clean Beta,{$cleanPhone2},beta@test.com,Beta Corp,LinkedIn,Consulting,Clean Beta\n" .
            "Clean Alpha Dupe In File,{$cleanPhone1},alpha2@test.com,Alpha 2,Website,Cloud,File Dupe\n";

        $tempPath = tempnam(sys_get_temp_dir(), 'test_rr_import_') . '.csv';
        file_put_contents($tempPath, $csvContent);
        $uploadedFile = new UploadedFile($tempPath, 'rr_test.csv', 'text/csv', null, true);

        // 1. Upload & Preview
        $previewRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/import/upload-preview', ['file' => $uploadedFile]);
        $previewRes->assertStatus(200);
        $fileToken = $previewRes->json('file_token');
        $mapping = [
            'name' => 'Full Name',
            'phone' => 'Mobile Number',
            'email' => 'Email',
            'company_name' => 'Company',
            'source' => 'Channel',
            'service_interest' => 'Requirement',
        ];

        // 2. Duplicate Pre-Scan Validation
        $dupeRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/import/preview-duplicates', [
                'file_token' => $fileToken,
                'mapping' => $mapping,
            ]);
        $dupeRes->assertStatus(200)
            ->assertJsonStructure([
                'total_rows',
                'clean_count',
                'duplicate_count',
                'invalid_count',
                'duplicates',
                'sample_clean_leads',
            ]);

        $this->assertEquals(4, $dupeRes->json('total_rows'));
        $this->assertEquals(2, $dupeRes->json('clean_count')); // Clean Alpha & Clean Beta
        $this->assertEquals(2, $dupeRes->json('duplicate_count')); // 1 CRM dupe + 1 in-file dupe

        // 3. Assignable Users Endpoint
        $usersRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/import/assignable-users');
        $usersRes->assertStatus(200)
            ->assertJsonStructure(['users']);
        $assignableUsers = $usersRes->json('users');
        $this->assertNotEmpty($assignableUsers);

        // 4. Batch Process with Custom Selected Assignees (Round-Robin)
        $selectedUserIds = array_slice(array_column($assignableUsers, 'id'), 0, 2);
        if (count($selectedUserIds) < 2) {
            $selectedUserIds = [$admin->id];
        }

        $processRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/import/process', [
                'file_token' => $fileToken,
                'mapping' => $mapping,
                'skip_duplicates' => true,
                'assignment_mode' => 'round_robin',
                'selected_assignee_ids' => $selectedUserIds,
            ]);

        $processRes->assertStatus(200);
        $report = $processRes->json('report');
        $this->assertEquals(2, $report['imported_count']);
        $this->assertEquals(2, $report['duplicate_count']);
        $this->assertNotEmpty($report['assignment_breakdown']);

        // Clean up
        $existingLead->forceDelete();
        Lead::whereIn('phone_normalized', [Lead::normalizePhone($cleanPhone1), Lead::normalizePhone($cleanPhone2)])->forceDelete();
        @unlink($tempPath);
    }
}
