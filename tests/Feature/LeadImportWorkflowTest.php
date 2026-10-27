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
}
