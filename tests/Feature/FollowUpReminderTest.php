<?php

namespace Tests\Feature;

use App\Jobs\SendFollowUpReminderJob;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\WhatsApp\MockWhatsAppService;
use App\Services\WhatsApp\WhatsAppManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FollowUpReminderTest extends TestCase
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

        // Standardize test clock to 10:50 AM IST on 27 Oct 2026 (exact observed scenario)
        Carbon::setTestNow(Carbon::create(2026, 10, 27, 10, 50, 0, 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        MockWhatsAppService::resetSimulation();
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    /**
     * Test A: Follow-up due at 10:57 AM Asia/Kolkata; reminder scheduled for 10:52 AM Asia/Kolkata.
     */
    public function test_a_follow_up_due_at_10_57_schedules_reminder_at_10_52(): void
    {
        // Follow-up scheduled for 10:57 AM IST
        $dueAtIst = Carbon::create(2026, 10, 27, 10, 57, 0, 'Asia/Kolkata');

        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Rajesh Sharma Test A',
            'phone' => '+91 98860 ' . rand(10000, 99999),
            'status' => 'new',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->admin->id,
        ]);

        $token = $this->employee1->createToken('test_a')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/leads/{$lead->id}/follow-ups", [
                'scheduled_at' => $dueAtIst->format('Y-m-d H:i:s'),
                'notes' => 'Review IT Infrastructure proposal at 10:57 AM',
            ]);

        $response->assertStatus(201);
        $followUpId = $response->json('follow_up.id');

        $followUp = FollowUp::find($followUpId);
        $this->assertNotNull($followUp);

        // Due time: 10:57 AM IST = 05:27 AM UTC
        $this->assertEquals('2026-10-27 05:27:00', $followUp->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertEquals('27 Oct 2026, 10:57 AM', $followUp->scheduled_at_ist);

        // Reminder time: 10:52 AM IST = 05:22 AM UTC (5 minutes prior)
        $this->assertNotNull($followUp->reminder_at);
        $this->assertEquals('2026-10-27 05:22:00', $followUp->reminder_at->format('Y-m-d H:i:s'));
        $this->assertEquals('27 Oct 2026, 10:52 AM', $followUp->reminder_at_ist);

        // Status & Key
        $this->assertEquals('pending', $followUp->reminder_status);
        $this->assertNotEmpty($followUp->reminder_key);
    }

    /**
     * Test B: Scheduler runs after reminder time. Overdue unsent reminder is detected and processed.
     */
    public function test_b_scheduler_runs_after_reminder_time_detects_and_processes_late_reminder(): void
    {
        $dueAtIst = Carbon::create(2026, 10, 27, 10, 57, 0, 'Asia/Kolkata');

        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Test B Overdue Reminder Lead',
            'phone' => '+91 98860 ' . rand(10000, 99999),
            'status' => 'assigned',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->admin->id,
        ]);

        $followUp = FollowUp::create([
            'lead_id' => $lead->id,
            'user_id' => $this->employee1->id,
            'scheduled_at' => $dueAtIst,
            'status' => 'pending',
            'notes' => 'Late policy test follow-up',
        ]);

        $this->assertEquals('27 Oct 2026, 10:52 AM', $followUp->reminder_at_ist);

        // Advance clock to 10:54 AM IST (after reminder time 10:52 AM, but before due time 10:57 AM)
        Carbon::setTestNow(Carbon::create(2026, 10, 27, 10, 54, 0, 'Asia/Kolkata'));

        // Run scheduler command
        $exitCode = Artisan::call('leads:check-followups');
        $this->assertEquals(0, $exitCode);

        // Verify reminder job executed and updated status
        $followUp->refresh();
        $this->assertEquals('sent', $followUp->reminder_status);
        $this->assertTrue((bool)$followUp->reminder_sent);
        $this->assertNotNull($followUp->reminder_sent_at);

        // Verify notification logs created for whatsapp and in_app
        $waLog = NotificationLog::where('follow_up_id', $followUp->id)
            ->where('channel', 'whatsapp')
            ->first();
        $this->assertNotNull($waLog);
        $this->assertEquals($followUp->reminder_key, $waLog->reminder_key);

        $inAppLog = NotificationLog::where('follow_up_id', $followUp->id)
            ->where('channel', 'in_app')
            ->first();
        $this->assertNotNull($inAppLog);
        $this->assertFalse((bool)$inAppLog->is_read);
    }

    /**
     * Test C: Employee logs in with overdue and upcoming follow-ups. The popup endpoint returns the correct records.
     */
    public function test_c_employee_logs_in_with_overdue_and_upcoming_followups_popup_returns_correct_records(): void
    {
        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Test C Briefing Lead',
            'phone' => '+91 98860 ' . rand(10000, 99999),
            'status' => 'contacted',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->admin->id,
        ]);

        // 1 Overdue follow-up (scheduled 2 hours ago at 08:50 AM)
        FollowUp::create([
            'lead_id' => $lead->id,
            'user_id' => $this->employee1->id,
            'scheduled_at' => Carbon::now('UTC')->subHours(2),
            'status' => 'pending',
            'notes' => 'Overdue discussion',
        ]);

        // 1 Upcoming follow-up (next week)
        FollowUp::create([
            'lead_id' => $lead->id,
            'user_id' => $this->employee1->id,
            'scheduled_at' => Carbon::now('UTC')->addDays(3),
            'status' => 'pending',
            'notes' => 'Upcoming review',
        ]);

        $token = $this->employee1->createToken('test_c')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/follow-ups/outstanding');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total',
                'counts' => ['overdue', 'due_today', 'upcoming'],
                'overdue',
                'due_today',
                'upcoming',
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('counts.overdue'));
        $this->assertGreaterThanOrEqual(1, $response->json('counts.upcoming'));
    }

    /**
     * Test D: Dismissing the popup does not complete or cancel follow-ups.
     */
    public function test_d_dismissing_popup_does_not_complete_or_cancel_followups(): void
    {
        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Test D Non-destructive Lead',
            'phone' => '+91 98860 ' . rand(10000, 99999),
            'status' => 'contacted',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->admin->id,
        ]);

        $followUp = FollowUp::create([
            'lead_id' => $lead->id,
            'user_id' => $this->employee1->id,
            'scheduled_at' => Carbon::now('UTC')->subMinutes(30),
            'status' => 'pending',
            'notes' => 'Dismissal test task',
        ]);

        $notificationLog = NotificationLog::create([
            'lead_id' => $lead->id,
            'follow_up_id' => $followUp->id,
            'user_id' => $this->employee1->id,
            'reminder_key' => $followUp->reminder_key,
            'channel' => 'in_app',
            'recipient' => $this->employee1->email,
            'title' => 'Follow-up Reminder',
            'message' => 'Test reminder message',
            'status' => 'delivered',
            'is_read' => false,
        ]);

        $token = $this->employee1->createToken('test_d')->plainTextToken;

        // Dismissing / acknowledging the reminder notification alert
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/follow-ups/reminders/{$notificationLog->id}/acknowledge");

        $response->assertStatus(200);

        // Verify the notification alert was marked read
        $notificationLog->refresh();
        $this->assertTrue((bool)$notificationLog->is_read);

        // Crucial invariant: The follow-up itself MUST remain pending!
        $followUp->refresh();
        $this->assertEquals('pending', $followUp->status);
        $this->assertNull($followUp->completed_at);
    }

    /**
     * Test E: Rescheduling a follow-up invalidates its previous reminder.
     */
    public function test_e_rescheduling_a_followup_invalidates_its_previous_reminder(): void
    {
        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Test E Rescheduling Lead',
            'phone' => '+91 98860 ' . rand(10000, 99999),
            'status' => 'assigned',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->admin->id,
        ]);

        $initialDueIst = Carbon::create(2026, 10, 27, 10, 57, 0, 'Asia/Kolkata');

        $followUp = FollowUp::create([
            'lead_id' => $lead->id,
            'user_id' => $this->employee1->id,
            'scheduled_at' => $initialDueIst,
            'status' => 'pending',
            'notes' => 'Original schedule',
        ]);

        $originalKey = $followUp->reminder_key;
        $originalReminderAt = $followUp->reminder_at;
        $this->assertEquals('27 Oct 2026, 10:52 AM', $followUp->reminder_at_ist);

        // Reschedule to 11:30 AM IST
        $token = $this->employee1->createToken('test_e')->plainTextToken;
        $newDueIst = Carbon::create(2026, 10, 27, 11, 30, 0, 'Asia/Kolkata');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/follow-ups/{$followUp->id}", [
                'scheduled_at' => $newDueIst->format('Y-m-d H:i:s'),
                'notes' => 'Client requested rescheduling to 11:30 AM',
            ]);

        $response->assertStatus(200);

        $followUp->refresh();

        // 1. New reminder time is 11:25 AM IST (5 mins before 11:30 AM)
        $this->assertEquals('27 Oct 2026, 11:25 AM', $followUp->reminder_at_ist);
        $this->assertNotEquals($originalReminderAt->timestamp, $followUp->reminder_at->timestamp);

        // 2. Reminder key was regenerated
        $this->assertNotEquals($originalKey, $followUp->reminder_key);
        $this->assertEquals('pending', $followUp->reminder_status);

        // 3. Stale job attempting to run with the old key is aborted immediately without sending
        $staleJob = new SendFollowUpReminderJob($followUp->id, $originalKey);
        $manager = app(WhatsAppManager::class);
        $staleJob->handle($manager);

        // Status is still pending for the new reminder, not marked sent by stale job
        $followUp->refresh();
        $this->assertEquals('pending', $followUp->reminder_status);
    }

    /**
     * Test F: Repeated scheduler executions do not send duplicate notifications.
     */
    public function test_f_repeated_scheduler_executions_do_not_send_duplicate_notifications(): void
    {
        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Test F Deduplication Lead',
            'phone' => '+91 98860 ' . rand(10000, 99999),
            'status' => 'assigned',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->admin->id,
        ]);

        $dueAtIst = Carbon::create(2026, 10, 27, 10, 57, 0, 'Asia/Kolkata');

        $followUp = FollowUp::create([
            'lead_id' => $lead->id,
            'user_id' => $this->employee1->id,
            'scheduled_at' => $dueAtIst,
            'status' => 'pending',
            'notes' => 'Dedup test',
        ]);

        $reminderKey = $followUp->reminder_key;

        // Advance clock past reminder time (10:53 AM)
        Carbon::setTestNow(Carbon::create(2026, 10, 27, 10, 53, 0, 'Asia/Kolkata'));

        // First scheduler run
        Artisan::call('leads:check-followups');

        $initialLogsCount = NotificationLog::where('reminder_key', $reminderKey)->count();
        $this->assertEquals(1, $initialLogsCount);

        // Second scheduler run immediately after
        Artisan::call('leads:check-followups');

        $afterSecondRunCount = NotificationLog::where('reminder_key', $reminderKey)->count();
        // Count must remain EXACTLY identical
        $this->assertEquals($initialLogsCount, $afterSecondRunCount);

        // Third scheduler run
        Artisan::call('leads:check-followups');
        $afterThirdRunCount = NotificationLog::where('reminder_key', $reminderKey)->count();
        $this->assertEquals($initialLogsCount, $afterThirdRunCount);
    }

    /**
     * Test G: An employee cannot retrieve another employee's private leads or follow-ups.
     */
    public function test_g_employee_cannot_retrieve_another_employees_private_leads_or_followups(): void
    {
        // Lead and follow-up owned by Employee 1
        $lead1 = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Confidential Lead of Employee 1',
            'phone' => '+91 98860 ' . rand(10000, 99999),
            'status' => 'assigned',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->admin->id,
        ]);

        $followUp1 = FollowUp::create([
            'lead_id' => $lead1->id,
            'user_id' => $this->employee1->id,
            'scheduled_at' => Carbon::now('UTC')->addDay(),
            'status' => 'pending',
            'notes' => 'Confidential notes',
        ]);

        // Employee 2 authenticates
        $token2 = $this->employee2->createToken('test_g_emp2')->plainTextToken;

        // 1. Employee 2 tries to access Employee 1's lead
        $resLead = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson("/api/leads/{$lead1->id}");
        $resLead->assertStatus(403);

        // 2. Employee 2 tries to update Employee 1's follow-up
        $resFu = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->putJson("/api/follow-ups/{$followUp1->id}", [
                'scheduled_at' => Carbon::now('UTC')->addDays(2)->format('Y-m-d H:i:s'),
            ]);
        $resFu->assertStatus(403);

        // 3. Employee 2's outstanding briefing does not contain Employee 1's follow-up
        $resBriefing = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson('/api/follow-ups/outstanding');
        $resBriefing->assertStatus(200);

        $allReturnedFuIds = collect($resBriefing->json('overdue'))
            ->concat($resBriefing->json('due_today'))
            ->concat($resBriefing->json('upcoming'))
            ->pluck('id')
            ->toArray();

        $this->assertNotContains($followUp1->id, $allReturnedFuIds);
    }

    /**
     * Test H: WhatsApp API failure is recorded and handled without duplicates.
     */
    public function test_h_whatsapp_api_failure_is_recorded_and_handled_without_duplicates(): void
    {
        $dueAtIst = Carbon::create(2026, 10, 27, 10, 57, 0, 'Asia/Kolkata');

        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Test H WhatsApp Failure Lead',
            'phone' => '+91 98860 ' . rand(10000, 99999),
            'status' => 'assigned',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->admin->id,
        ]);

        $followUp = FollowUp::create([
            'lead_id' => $lead->id,
            'user_id' => $this->employee1->id,
            'scheduled_at' => $dueAtIst,
            'status' => 'pending',
            'notes' => 'Simulate failure',
        ]);

        // Inject simulated failure into MockWhatsAppService
        $mockService = new MockWhatsAppService();
        $mockService->setSimulateFailure(true);

        $manager = new WhatsAppManager();
        $manager->setProvider($mockService);

        $job = new SendFollowUpReminderJob($followUp->id, $followUp->reminder_key);

        try {
            $job->handle($manager);
        } catch (\RuntimeException $e) {
            // Expected exception thrown by queue job for supervisor retry
            $this->assertStringContainsString('Simulated WhatsApp Gateway Connection Timeout', $e->getMessage());
        }

        // Verify failure was recorded in notification logs
        $failedLog = NotificationLog::where('follow_up_id', $followUp->id)
            ->where('channel', 'like', '%whatsapp%')
            ->where('status', 'failed')
            ->first();

        $this->assertNotNull($failedLog);
        $this->assertStringContainsString('Simulated WhatsApp Gateway Connection Timeout', (string)$failedLog->error_message);

        // Running job again does not create duplicate entries
        try {
            $job->handle($manager);
        } catch (\RuntimeException $e) {
            // caught
        }

        $count = NotificationLog::where('follow_up_id', $followUp->id)
            ->where('channel', 'like', '%whatsapp%')
            ->count();
        $this->assertEquals(1, $count);

        MockWhatsAppService::resetSimulation();
    }

    /**
     * Test I: Timezone conversion works correctly between UTC and Asia/Kolkata.
     */
    public function test_i_timezone_conversion_works_correctly_between_utc_and_asia_kolkata(): void
    {
        // 10:57 AM IST on 27 Oct 2026
        $istString = '2026-10-27 10:57:00';
        $utc = FollowUp::parseToUtc($istString);

        // 10:57 AM IST is exactly 05:27 AM UTC (5 hours 30 mins difference)
        $this->assertEquals('2026-10-27 05:27:00', $utc->format('Y-m-d H:i:s'));

        // 5-minute reminder calculation
        $reminderUtc = FollowUp::calculateReminderAt($utc, Carbon::create(2026, 10, 27, 5, 0, 0, 'UTC'));
        // 05:27 UTC - 5 mins = 05:22 UTC
        $this->assertEquals('2026-10-27 05:22:00', $reminderUtc->format('Y-m-d H:i:s'));

        // Converted back to IST: 05:22 UTC = 10:52 AM IST
        $reminderIst = $reminderUtc->copy()->setTimezone('Asia/Kolkata');
        $this->assertEquals('2026-10-27 10:52:00', $reminderIst->format('Y-m-d H:i:s'));
        $this->assertEquals('10:52 AM', $reminderIst->format('h:i A'));
    }
}
