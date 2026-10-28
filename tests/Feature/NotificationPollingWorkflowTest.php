<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\WhatsApp\MockWhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationPollingWorkflowTest extends TestCase
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

        // Ensure clean notification baseline for test users
        NotificationLog::whereIn('user_id', [$this->admin->id, $this->employee1->id, $this->employee2->id])->delete();
        auth()->forgetGuards();
        MockWhatsAppService::resetSimulation();

        // Baseline test clock to 11:40 AM IST on 27 Oct 2026
        Carbon::setTestNow(Carbon::create(2026, 10, 27, 11, 40, 0, 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        MockWhatsAppService::resetSimulation();
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    /**
     * Test A: Log in and leave the follow-up page open. Create a new notification. It appears without refreshing.
     */
    public function test_a_new_notification_appears_via_polling_without_refreshing(): void
    {
        $token = $this->employee1->createToken('test_poll_a')->plainTextToken;

        // Initial poll state: mark old notifications as read for clean baseline
        NotificationLog::where('user_id', $this->employee1->id)->update(['is_read' => true]);

        $initialPoll = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/notifications/poll');
        $initialPoll->assertStatus(200);
        $this->assertEquals(0, $initialPoll->json('unread_count'));

        // Admin assigns a new lead to Employee 1 in background
        auth()->forgetGuards();
        $adminToken = $this->admin->createToken('admin_create')->plainTextToken;
        $createLeadRes = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson('/api/leads', [
                'name' => 'Amitabh Bachchan Solutions Lead',
                'phone' => '+91 99887 ' . rand(10000, 99999),
                'assigned_to' => $this->employee1->id,
                'service_interest' => 'Enterprise Software Engineering',
            ]);
        $createLeadRes->assertStatus(201);
        $newLeadId = $createLeadRes->json('lead.id');

        // Reset auth guard between requests with different tokens
        auth()->forgetGuards();

        // Subsequent poll (simulate 15-second background polling cycle)
        $nextPoll = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/notifications/poll');

        $nextPoll->assertStatus(200);
        // Automatically detected new unread notification
        $this->assertGreaterThanOrEqual(1, $nextPoll->json('unread_count'));

        // Alert payload received without page refresh
        $alerts = $nextPoll->json('active_alerts');
        $this->assertNotEmpty($alerts);
        $leadAlert = collect($alerts)->firstWhere('lead_id', $newLeadId);
        $this->assertNotNull($leadAlert);
        $this->assertEquals('new_lead', $leadAlert['event_type']);
        $this->assertStringContainsString('Amitabh Bachchan', $leadAlert['title'] . $leadAlert['message']);
    }

    /**
     * Test B: Schedule a follow-up. At five minutes before its due time, the reminder popup appears automatically.
     */
    public function test_b_at_five_minutes_before_due_time_reminder_appears_via_polling(): void
    {
        // Follow-up scheduled for 11:48 AM IST (reminder time = 11:43 AM IST)
        $dueAtIst = Carbon::create(2026, 10, 27, 11, 48, 0, 'Asia/Kolkata');

        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Suresh Reddy Test B',
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
            'notes' => '5-minute notice verification',
        ]);

        $token = $this->employee1->createToken('test_poll_b')->plainTextToken;

        // Current time: 11:40 AM IST (3 minutes before 11:43 AM reminder)
        Artisan::call('leads:check-followups');
        $prePoll = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/notifications/poll');
        $this->assertEmpty(collect($prePoll->json('active_alerts'))->where('follow_up_id', $followUp->id));

        // Advance time to 11:43 AM IST (exact 5-minute reminder mark)
        Carbon::setTestNow(Carbon::create(2026, 10, 27, 11, 43, 0, 'Asia/Kolkata'));

        // Scheduler scans and detects reminder
        Artisan::call('leads:check-followups');

        // Next frontend poll automatically picks up the active reminder dialog payload
        $postPoll = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/notifications/poll');
        $postPoll->assertStatus(200);

        $reminderAlert = collect($postPoll->json('active_alerts'))->firstWhere('follow_up_id', $followUp->id);
        $this->assertNotNull($reminderAlert);
        $this->assertEquals('follow_up_reminder', $reminderAlert['event_type']);
        $this->assertNotNull($reminderAlert['lead']);
        $this->assertEquals($lead->name, $reminderAlert['lead']['name']);
    }

    /**
     * Test C: Keep the browser open while the reminder time passes. The popup appears without navigating away or refreshing.
     */
    public function test_c_browser_stays_on_page_reminder_appears_as_time_passes(): void
    {
        $dueAtIst = Carbon::create(2026, 10, 27, 11, 48, 0, 'Asia/Kolkata');

        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Continuous Session Client',
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
            'notes' => 'Review Cloud Architecture',
        ]);

        $token = $this->employee1->createToken('test_poll_c')->plainTextToken;

        // Poll cycle 1 at 11:40 AM
        $poll1 = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/notifications/poll');
        $this->assertNull(collect($poll1->json('active_alerts'))->firstWhere('follow_up_id', $followUp->id));

        // Time progresses while user stays on dashboard: 11:44 AM
        Carbon::setTestNow(Carbon::create(2026, 10, 27, 11, 44, 0, 'Asia/Kolkata'));

        // Scheduler processes due reminder
        Artisan::call('leads:check-followups');

        // Poll cycle 2 (15s later): Reminder alert is delivered
        $poll2 = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/notifications/poll');
        $this->assertNotNull(collect($poll2->json('active_alerts'))->firstWhere('follow_up_id', $followUp->id));
    }

    /**
     * Test D: Dismiss a popup. It does not immediately reappear on every polling cycle.
     */
    public function test_d_dismissed_popup_does_not_reappear_on_subsequent_polls(): void
    {
        $notification = NotificationLog::create([
            'user_id' => $this->employee1->id,
            'event_type' => 'follow_up_reminder',
            'channel' => 'in_app',
            'recipient' => $this->employee1->email,
            'title' => 'Dismissal Test Alert',
            'message' => 'Client follow-up alert',
            'status' => 'delivered',
            'is_read' => false,
        ]);

        $token = $this->employee1->createToken('test_poll_d')->plainTextToken;

        // First poll: Alert is present
        $poll1 = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/notifications/poll');
        $this->assertNotNull(collect($poll1->json('active_alerts'))->firstWhere('id', $notification->id));

        // Employee dismisses alert
        $dismissRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/notifications/{$notification->id}/dismiss");
        $dismissRes->assertStatus(200);

        // Subsequent poll cycles: Alert is no longer returned in active_alerts
        $poll2 = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/notifications/poll');
        $this->assertNull(collect($poll2->json('active_alerts'))->firstWhere('id', $notification->id));

        $poll3 = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/notifications/poll');
        $this->assertNull(collect($poll3->json('active_alerts'))->firstWhere('id', $notification->id));
    }

    /**
     * Test E: Refresh the page. Existing notifications are restored correctly without duplicate alerts.
     */
    public function test_e_page_refresh_restores_notifications_without_duplicates(): void
    {
        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Test E Persistence Lead',
            'phone' => '+91 98860 ' . rand(10000, 99999),
            'status' => 'assigned',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->admin->id,
        ]);

        $notification = NotificationLog::create([
            'lead_id' => $lead->id,
            'user_id' => $this->employee1->id,
            'event_type' => 'new_lead',
            'channel' => 'in_app',
            'recipient' => $this->employee1->email,
            'title' => 'New Lead Assigned',
            'message' => 'Assigned lead',
            'status' => 'delivered',
            'is_read' => false,
        ]);

        $token = $this->employee1->createToken('test_poll_e')->plainTextToken;

        // Simulate page refresh (fetching notifications list endpoint)
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/notifications');
        $listRes->assertStatus(200);
        $items = $listRes->json('notifications.data');

        // Matches record and has exactly 1 entry for this notification
        $matching = collect($items)->where('id', $notification->id);
        $this->assertEquals(1, $matching->count());

        // Polling still shows matching active alert count without multiplying
        $pollRes = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/notifications/poll');
        $pollMatching = collect($pollRes->json('active_alerts'))->where('id', $notification->id);
        $this->assertEquals(1, $pollMatching->count());
    }

    /**
     * Test F: Log in as an employee. Only notifications and follow-ups permitted for that employee are visible.
     */
    public function test_f_employee_only_sees_permitted_notifications_and_followups(): void
    {
        // Notification for Employee 1
        $notif1 = NotificationLog::create([
            'user_id' => $this->employee1->id,
            'event_type' => 'new_lead',
            'channel' => 'in_app',
            'recipient' => $this->employee1->email,
            'title' => 'For Ananya Only',
            'message' => 'Private message',
            'status' => 'delivered',
            'is_read' => false,
        ]);

        // Notification for Employee 2
        $notif2 = NotificationLog::create([
            'user_id' => $this->employee2->id,
            'event_type' => 'new_lead',
            'channel' => 'in_app',
            'recipient' => $this->employee2->email,
            'title' => 'For Rohit Only',
            'message' => 'Private message',
            'status' => 'delivered',
            'is_read' => false,
        ]);

        // Employee 1 polls
        auth()->forgetGuards();
        $token1 = $this->employee1->createToken('emp1_test_f')->plainTextToken;
        $poll1 = $this->withHeader('Authorization', 'Bearer ' . $token1)->getJson('/api/notifications/poll');
        $alerts1 = collect($poll1->json('active_alerts'))->pluck('id')->toArray();

        $this->assertContains($notif1->id, $alerts1);
        $this->assertNotContains($notif2->id, $alerts1);

        // Employee 2 polls
        auth()->forgetGuards();
        $token2 = $this->employee2->createToken('emp2_test_f')->plainTextToken;
        $poll2 = $this->withHeader('Authorization', 'Bearer ' . $token2)->getJson('/api/notifications/poll');
        $alerts2 = collect($poll2->json('active_alerts'))->pluck('id')->toArray();

        $this->assertContains($notif2->id, $alerts2);
        $this->assertNotContains($notif1->id, $alerts2);
    }

    /**
     * Test G: Simulate temporary network failure. Polling resumes after connectivity returns.
     */
    public function test_g_polling_resumes_cleanly_after_transient_failure(): void
    {
        auth()->forgetGuards();
        $token = $this->employee1->createToken('test_poll_g')->plainTextToken;

        // Valid poll
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/notifications/poll');
        $res1->assertStatus(200);

        // Simulate request without auth / invalid token (transient 401 failure)
        auth()->forgetGuards();
        $resFailed = $this->withHeader('Authorization', 'Bearer invalid_token')->getJson('/api/notifications/poll');
        $resFailed->assertStatus(401);

        // Reconnect with valid token: polling resumes seamlessly
        auth()->forgetGuards();
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/notifications/poll');
        $res2->assertStatus(200);
        $this->assertArrayHasKey('unread_count', $res2->json());
        $this->assertArrayHasKey('active_alerts', $res2->json());
    }

    /**
     * Test H: Confirm that backend scheduling, notification persistence and WhatsApp delivery are independent of frontend polling.
     */
    public function test_h_backend_scheduling_and_whatsapp_are_independent_of_frontend_polling(): void
    {
        $dueAtIst = Carbon::create(2026, 10, 27, 11, 48, 0, 'Asia/Kolkata');

        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Independent Backend Execution Client',
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
            'notes' => 'Headless background test',
        ]);

        // Advance to 11:44 AM IST
        Carbon::setTestNow(Carbon::create(2026, 10, 27, 11, 44, 0, 'Asia/Kolkata'));

        // ZERO frontend polling requests are made. The backend scheduler runs purely via cron / CLI
        $exitCode = Artisan::call('leads:check-followups');
        $this->assertEquals(0, $exitCode);

        // Verify that follow-up was updated by background engine
        $followUp->refresh();
        $this->assertEquals('sent', $followUp->reminder_status);
        $this->assertTrue((bool)$followUp->reminder_sent);

        // Verify WhatsApp notification was created and sent
        $waLog = NotificationLog::where('follow_up_id', $followUp->id)->where('channel', 'whatsapp')->first();
        $this->assertNotNull($waLog);
        $this->assertEquals($followUp->reminder_key, $waLog->reminder_key);
        $this->assertEquals('delivered', $waLog->status);

        // Verify in-app alert was persisted for future polling detection
        $inAppLog = NotificationLog::where('follow_up_id', $followUp->id)->where('channel', 'in_app')->first();
        $this->assertNotNull($inAppLog);
        $this->assertFalse((bool)$inAppLog->is_read);
    }
}
