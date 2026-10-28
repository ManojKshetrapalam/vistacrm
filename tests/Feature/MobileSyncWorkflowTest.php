<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileSyncWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $employee1;
    protected User $employee2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@vistabound.test',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->employee1 = User::factory()->create([
            'name' => 'Employee One',
            'email' => 'emp1@vistabound.test',
            'role' => 'employee',
            'is_active' => true,
        ]);

        $this->employee2 = User::factory()->create([
            'name' => 'Employee Two',
            'email' => 'emp2@vistabound.test',
            'role' => 'employee',
            'is_active' => true,
        ]);
    }

    public function test_device_token_registration_and_revocation(): void
    {
        Sanctum::actingAs($this->employee1);

        // Register token
        $response = $this->postJson('/api/mobile/push-token', [
            'token' => 'fcm_sample_token_xyz_123',
            'platform' => 'android',
            'device_id' => 'device_pixel_8',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $this->employee1->id,
            'token' => 'fcm_sample_token_xyz_123',
            'platform' => 'android',
        ]);

        // Revoke token
        $deleteResponse = $this->deleteJson('/api/mobile/push-token', [
            'token' => 'fcm_sample_token_xyz_123',
        ]);

        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('device_tokens', [
            'token' => 'fcm_sample_token_xyz_123',
        ]);
    }

    public function test_mobile_sync_pull_strictly_enforces_tenant_isolation(): void
    {
        // Lead for employee 1
        $lead1 = Lead::create([
            'name' => 'Emp1 Lead',
            'phone' => '9876543210',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->employee1->id,
        ]);

        // Lead for employee 2
        $lead2 = Lead::create([
            'name' => 'Emp2 Lead Private',
            'phone' => '9123456780',
            'assigned_to' => $this->employee2->id,
            'created_by' => $this->employee2->id,
        ]);

        Sanctum::actingAs($this->employee1);

        $response = $this->getJson('/api/mobile/sync/pull');
        $response->assertStatus(200);

        $leadNames = collect($response->json('leads'))->pluck('name');
        $this->assertTrue($leadNames->contains('Emp1 Lead'));
        $this->assertFalse($leadNames->contains('Emp2 Lead Private'));
    }

    public function test_offline_mutation_push_creates_notes_and_activities(): void
    {
        $lead = Lead::create([
            'name' => 'Offline Client',
            'phone' => '9888877777',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->employee1->id,
            'status' => 'new',
            'version' => 1,
        ]);

        Sanctum::actingAs($this->employee1);

        $uuidNote = 'uuid-note-1111-2222';
        $uuidStatus = 'uuid-status-3333-4444';
        $uuidCall = 'uuid-call-5555-6666';

        $payload = [
            'mutations' => [
                [
                    'uuid' => $uuidNote,
                    'operation' => 'create_note',
                    'lead_id' => $lead->id,
                    'payload' => ['note' => 'Spoke while traveling, requested quote'],
                    'client_timestamp' => now()->toISOString(),
                    'version' => 1,
                ],
                [
                    'uuid' => $uuidStatus,
                    'operation' => 'update_status',
                    'lead_id' => $lead->id,
                    'payload' => ['status' => 'interested'],
                    'client_timestamp' => now()->toISOString(),
                    'version' => 1,
                ],
                [
                    'uuid' => $uuidCall,
                    'operation' => 'log_call',
                    'lead_id' => $lead->id,
                    'payload' => [
                        'outcome' => 'connected',
                        'duration' => 120,
                        'notes' => 'Answered directly, agreed to proposal meeting',
                    ],
                    'client_timestamp' => now()->toISOString(),
                    'version' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/mobile/sync/push', $payload);
        $response->assertStatus(200);

        $results = $response->json('results');
        $this->assertCount(3, $results);
        $this->assertEquals('synced', $results[0]['status']);
        $this->assertEquals('synced', $results[1]['status']);
        $this->assertEquals('synced', $results[2]['status']);

        // Verify database state
        $lead->refresh();
        $this->assertEquals('interested', $lead->status);
        $this->assertNotNull($lead->last_contact_date);

        // Verify immutable activity history
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'client_uuid' => $uuidNote,
            'activity_type' => 'note',
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'client_uuid' => $uuidStatus,
            'activity_type' => 'status_change',
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'client_uuid' => $uuidCall,
            'activity_type' => 'call_logged',
        ]);
    }

    public function test_offline_follow_up_creation_calculates_5_minute_reminder(): void
    {
        $lead = Lead::create([
            'name' => 'Follow Up Client',
            'phone' => '9888812345',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->employee1->id,
            'status' => 'contacted',
        ]);

        Sanctum::actingAs($this->employee1);

        $uuidFollowUp = 'uuid-fup-7777-8888';
        $dueAt = Carbon::create(2026, 10, 27, 16, 30, 0, 'Asia/Kolkata'); // 4:30 PM IST

        $response = $this->postJson('/api/mobile/sync/push', [
            'mutations' => [
                [
                    'uuid' => $uuidFollowUp,
                    'operation' => 'create_followup',
                    'lead_id' => $lead->id,
                    'payload' => [
                        'scheduled_at' => $dueAt->toIso8601String(),
                        'notes' => 'Review commercial terms',
                    ],
                    'client_timestamp' => now()->toISOString(),
                ],
            ],
        ]);

        $response->assertStatus(200);

        $followUp = FollowUp::where('client_uuid', $uuidFollowUp)->first();
        $this->assertNotNull($followUp);

        // Reminder must be exactly 5 minutes before scheduled time
        $scheduledIst = $followUp->scheduled_at->setTimezone('Asia/Kolkata');
        $reminderIst = $followUp->reminder_at->setTimezone('Asia/Kolkata');

        $this->assertEquals('16:30', $scheduledIst->format('H:i'));
        $this->assertEquals('16:25', $reminderIst->format('H:i'));
        $this->assertEquals(5, $reminderIst->diffInMinutes($scheduledIst));
    }

    public function test_sync_push_idempotency_prevents_duplicate_mutations(): void
    {
        $lead = Lead::create([
            'name' => 'Idempotent Test',
            'phone' => '9999900000',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->employee1->id,
        ]);

        Sanctum::actingAs($this->employee1);

        $uuid = 'uuid-idempotent-repeat-999';
        $payload = [
            'mutations' => [
                [
                    'uuid' => $uuid,
                    'operation' => 'create_note',
                    'lead_id' => $lead->id,
                    'payload' => ['note' => 'Only one note should exist.'],
                ],
            ],
        ];

        // Push first time
        $res1 = $this->postJson('/api/mobile/sync/push', $payload);
        $res1->assertStatus(200);

        // Push second time (e.g. mobile app retried due to slow connection)
        $res2 = $this->postJson('/api/mobile/sync/push', $payload);
        $res2->assertStatus(200);
        $this->assertEquals('Already synchronized', $res2->json('results.0.message'));

        // Exactly 1 activity record must exist
        $count = LeadActivity::where('client_uuid', $uuid)->count();
        $this->assertEquals(1, $count);
    }
}
