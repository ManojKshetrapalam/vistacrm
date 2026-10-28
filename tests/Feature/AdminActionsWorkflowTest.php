<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class AdminActionsWorkflowTest extends TestCase
{
    protected User $admin;
    protected User $employee1;
    protected User $employee2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'admin@vistaboundintegrated.com')->first();
        $this->employee1 = User::where('email', 'ananya@vistaboundintegrated.com')->first();
        $this->employee2 = User::where('email', 'rohit@vistaboundintegrated.com')->first();
    }

    protected function createTestLead(string $phone = '+91 99999 11111', ?int $assignedTo = null): Lead
    {
        return Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Test Action Lead ' . rand(1000, 9999),
            'phone' => $phone,
            'phone_normalized' => Lead::normalizePhone($phone),
            'email' => 'actionlead' . rand(1000, 9999) . '@example.com',
            'company_name' => 'Vistabound Actions Test Corp',
            'status' => 'new',
            'priority' => 'high',
            'source' => 'Website',
            'service_interest' => 'Hospitality Solutions',
            'assigned_to' => $assignedTo ?? $this->employee1->id,
            'assigned_at' => now(),
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_archive_lead(): void
    {
        $lead = $this->createTestLead('+91 98888 22221');
        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->deleteJson("/api/leads/{$lead->id}");

        $response->assertStatus(200);
        $this->assertStringContainsString('has been archived', $response->json('message'));

        $this->assertSoftDeleted('leads', ['id' => $lead->id]);

        // Check activity log
        $activity = LeadActivity::where('lead_id', $lead->id)
            ->where('activity_type', 'lead_archived')
            ->first();
        $this->assertNotNull($activity);

        // Check it's excluded from active leads list
        $activeList = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/leads');
        $activeIds = collect($activeList->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($lead->id, $activeIds);

        // Check it's included in archived leads list
        $archivedList = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/leads?archived=1');
        $archivedIds = collect($archivedList->json('data'))->pluck('id')->toArray();
        $this->assertContains($lead->id, $archivedIds);

        // Clean up
        $lead->forceDelete();
    }

    public function test_employee_cannot_archive_lead(): void
    {
        $lead = $this->createTestLead('+91 98888 22222');
        $empToken = $this->employee1->createToken('emp-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $empToken)
            ->deleteJson("/api/leads/{$lead->id}");

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('leads', ['id' => $lead->id]);

        // Clean up
        $lead->forceDelete();
    }

    public function test_admin_can_restore_archived_lead(): void
    {
        $lead = $this->createTestLead('+91 98888 22223');
        $lead->delete(); // soft delete
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);

        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/leads/{$lead->id}/restore");

        $response->assertStatus(200);
        $this->assertStringContainsString('restored successfully', $response->json('message'));

        $this->assertNotSoftDeleted('leads', ['id' => $lead->id]);

        // Check activity log
        $activity = LeadActivity::where('lead_id', $lead->id)
            ->where('activity_type', 'lead_restored')
            ->first();
        $this->assertNotNull($activity);

        // Clean up
        $lead->forceDelete();
    }

    public function test_employee_cannot_restore_lead(): void
    {
        $lead = $this->createTestLead('+91 98888 22224');
        $lead->delete();

        $empToken = $this->employee1->createToken('emp-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $empToken)
            ->postJson("/api/leads/{$lead->id}/restore");

        $response->assertStatus(403);

        // Clean up
        $lead->forceDelete();
    }

    public function test_admin_can_force_delete_archived_lead(): void
    {
        $lead = $this->createTestLead('+91 98888 22225');
        $lead->delete();

        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->deleteJson("/api/leads/{$lead->id}/force");

        $response->assertStatus(200);
        $this->assertStringContainsString('permanently deleted', $response->json('message'));

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    public function test_employee_cannot_force_delete_lead(): void
    {
        $lead = $this->createTestLead('+91 98888 22226');
        $lead->delete();

        $empToken = $this->employee1->createToken('emp-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $empToken)
            ->deleteJson("/api/leads/{$lead->id}/force");

        $response->assertStatus(403);

        // Clean up
        $lead->forceDelete();
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $secondAdmin = User::create([
            'name' => 'Second Admin Self Test',
            'username' => 'secondadminself_' . rand(100, 999),
            'email' => 'secondadminself_' . rand(100, 999) . '@vistabound.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/employees/{$this->admin->id}/toggle-status");

        $response->assertStatus(422)
            ->assertJson(['message' => 'You cannot deactivate your own account.']);

        $secondAdmin->delete();
    }

    public function test_admin_cannot_deactivate_last_active_admin(): void
    {
        // Ensure only 1 active admin exists ($this->admin)
        $otherAdmins = User::where('role', 'admin')
            ->where('is_active', true)
            ->where('id', '!=', $this->admin->id)
            ->get();

        foreach ($otherAdmins as $oa) {
            $oa->is_active = false;
            $oa->save();
        }

        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/employees/{$this->admin->id}/toggle-status");

        $res->assertStatus(422)
            ->assertJson(['message' => 'Cannot deactivate the last active administrator.']);

        foreach ($otherAdmins as $oa) {
            $oa->is_active = true;
            $oa->save();
        }
    }

    public function test_deactivating_employee_revokes_tokens_and_device_tokens(): void
    {
        $testEmp = User::create([
            'name' => 'Test Employee Deactivate',
            'username' => 'testdeact_' . rand(100, 999),
            'email' => 'testdeact_' . rand(100, 999) . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'employee',
            'is_active' => true,
        ]);

        // Create Sanctum personal token
        $empToken = $testEmp->createToken('mobile-session');

        // Create DeviceToken
        DeviceToken::create([
            'user_id' => $testEmp->id,
            'token' => 'fcm_test_token_' . rand(1000, 9999),
            'platform' => 'android',
        ]);

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $testEmp->id]);
        $this->assertDatabaseHas('device_tokens', ['user_id' => $testEmp->id]);

        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        // Deactivate employee
        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/employees/{$testEmp->id}/toggle-status");

        $response->assertStatus(200);

        $testEmp->refresh();
        $this->assertFalse($testEmp->is_active);

        // Verify tokens are purged
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $testEmp->id]);
        $this->assertDatabaseMissing('device_tokens', ['user_id' => $testEmp->id]);

        // Cleanup
        $testEmp->delete();
    }

    public function test_admin_can_reassign_leads_from_employee(): void
    {
        $lead1 = $this->createTestLead('+91 97777 11111', $this->employee1->id);
        $lead2 = $this->createTestLead('+91 97777 22222', $this->employee1->id);

        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/employees/{$this->employee1->id}/reassign-leads", [
                'new_user_id' => $this->employee2->id,
            ]);

        $response->assertStatus(200);

        $lead1->refresh();
        $lead2->refresh();

        $this->assertEquals($this->employee2->id, $lead1->assigned_to);
        $this->assertEquals($this->employee2->id, $lead2->assigned_to);

        // Clean up
        $lead1->forceDelete();
        $lead2->forceDelete();
    }

    public function test_admin_can_cancel_follow_up(): void
    {
        $lead = $this->createTestLead('+91 96666 11111');
        $followUp = FollowUp::create([
            'lead_id' => $lead->id,
            'user_id' => $this->employee1->id,
            'scheduled_at' => Carbon::now()->addDays(2),
            'reminder_at' => Carbon::now()->addDays(2)->subMinutes(5),
            'status' => 'pending',
            'reminder_status' => 'pending',
            'type' => 'call',
            'notes' => 'Discuss enterprise contract',
            'created_by' => $this->admin->id,
        ]);

        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/follow-ups/{$followUp->id}/cancel");

        $response->assertStatus(200);
        $this->assertStringContainsString('cancelled successfully', $response->json('message'));

        $followUp->refresh();
        $this->assertEquals('cancelled', $followUp->status);
        $this->assertEquals('cancelled', $followUp->reminder_status);

        // Verify activity logged
        $activity = LeadActivity::where('lead_id', $lead->id)
            ->where('activity_type', 'follow_up_cancelled')
            ->first();
        $this->assertNotNull($activity);

        // Clean up
        $followUp->delete();
        $lead->forceDelete();
    }

    public function test_employee_cannot_delete_follow_up(): void
    {
        $lead = $this->createTestLead('+91 96666 22222');
        $followUp = FollowUp::create([
            'lead_id' => $lead->id,
            'user_id' => $this->employee1->id,
            'scheduled_at' => Carbon::now()->addDays(3),
            'reminder_at' => Carbon::now()->addDays(3)->subMinutes(5),
            'status' => 'pending',
            'reminder_status' => 'pending',
            'type' => 'call',
            'notes' => 'Follow up on AMC quotation',
            'created_by' => $this->admin->id,
        ]);

        $empToken = $this->employee1->createToken('emp-test')->plainTextToken;

        $empResponse = $this->withHeader('Authorization', 'Bearer ' . $empToken)
            ->deleteJson("/api/follow-ups/{$followUp->id}");

        $empResponse->assertStatus(403);
        $this->assertDatabaseHas('follow_ups', ['id' => $followUp->id]);

        // Clean up
        $followUp->delete();
        $lead->forceDelete();
    }

    public function test_admin_can_delete_follow_up(): void
    {
        $lead = $this->createTestLead('+91 96666 33333');
        $followUp = FollowUp::create([
            'lead_id' => $lead->id,
            'user_id' => $this->employee1->id,
            'scheduled_at' => Carbon::now()->addDays(3),
            'reminder_at' => Carbon::now()->addDays(3)->subMinutes(5),
            'status' => 'pending',
            'reminder_status' => 'pending',
            'type' => 'call',
            'notes' => 'Follow up on AMC quotation',
            'created_by' => $this->admin->id,
        ]);

        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        $adminResponse = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->deleteJson("/api/follow-ups/{$followUp->id}");

        $adminResponse->assertStatus(200);
        $this->assertDatabaseMissing('follow_ups', ['id' => $followUp->id]);

        // Clean up
        $lead->forceDelete();
    }

    public function test_mobile_sync_pull_returns_deleted_lead_ids(): void
    {
        $lead = $this->createTestLead('+91 95555 11111');
        $since = Carbon::now()->subMinute()->toIso8601String();

        // Archive lead
        $lead->delete();

        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/mobile/sync/pull?since=' . urlencode($since));

        $response->assertStatus(200)
            ->assertJsonStructure(['server_time', 'leads', 'follow_ups', 'deleted_lead_ids', 'settings']);

        $deletedLeadIds = $response->json('deleted_lead_ids');
        $this->assertContains($lead->id, $deletedLeadIds);

        // Clean up
        $lead->forceDelete();
    }
}
