<?php

namespace App\Console\Commands;

use App\Jobs\SendFollowUpReminderJob;
use App\Models\FollowUp;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckFollowUpsCommand extends Command
{
    protected $signature = 'leads:check-followups {--sync : Execute reminder dispatches synchronously rather than background queue}';
    protected $description = 'Scan pending follow-ups due soon and dispatch 5-minute pre-due reminders';

    public function handle(): int
    {
        $nowUtc = Carbon::now('UTC');
        $nowIst = $nowUtc->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i:s A');

        $this->info("[SCHEDULER RUN] {$nowIst} IST — Scanning pending follow-up reminders...");

        // 1. Heal any legacy follow-ups that were created without reminder_at or reminder_key
        $unkeyedFollowUps = FollowUp::where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('reminder_at')->orWhereNull('reminder_key');
            })
            ->get();

        foreach ($unkeyedFollowUps as $unkeyed) {
            $dueAtUtc = $unkeyed->scheduled_at->copy()->utc();
            $createdAtUtc = $unkeyed->created_at ? $unkeyed->created_at->copy()->utc() : $nowUtc;

            $reminderAtUtc = FollowUp::calculateReminderAt($dueAtUtc, $createdAtUtc);
            $key = $unkeyed->generateReminderKey();

            $unkeyed->update([
                'reminder_at' => $reminderAtUtc,
                'reminder_key' => $key,
                'reminder_status' => $unkeyed->reminder_sent ? 'sent' : 'pending',
            ]);

            $this->line("  → Initialized reminder attributes for Follow-up #{$unkeyed->id}: Reminder at {$unkeyed->reminder_at_ist} IST");
        }

        // 2. Query all pending follow-ups where reminder_at <= now()
        $eligibleFollowUps = FollowUp::pendingReminders()
            ->with(['lead', 'user'])
            ->get();

        $count = $eligibleFollowUps->count();

        if ($count === 0) {
            $this->info("No reminders currently eligible for dispatch.");
            return Command::SUCCESS;
        }

        $this->info("Found {$count} reminder(s) eligible for dispatch.");

        $dispatchedCount = 0;
        foreach ($eligibleFollowUps as $followUp) {
            $dueIst = $followUp->scheduled_at_ist;
            $reminderIst = $followUp->reminder_at_ist;
            $leadName = $followUp->lead?->name ?? 'Unknown Lead';
            $assigneeName = $followUp->user?->name ?? 'Unassigned';

            // Diagnostic log
            $logMsg = "[REMINDER ELIGIBLE] FollowUp #{$followUp->id} | Client: {$leadName} | Assignee: {$assigneeName} | Due: {$dueIst} IST | Reminder Scheduled: {$reminderIst} IST | Detected: {$nowIst} IST";
            $this->line("  ★ " . $logMsg);
            Log::info($logMsg);

            if ($this->option('sync')) {
                // Execute synchronously (useful for tests and CLI debugging)
                app()->call([new SendFollowUpReminderJob($followUp->id, $followUp->reminder_key, $nowUtc->toIso8601String()), 'handle']);
            } else {
                // Queue for background delivery
                SendFollowUpReminderJob::dispatch($followUp->id, $followUp->reminder_key, $nowUtc->toIso8601String());
            }

            $dispatchedCount++;
        }

        $this->info("Dispatched {$dispatchedCount} reminder job(s).");
        return Command::SUCCESS;
    }
}
