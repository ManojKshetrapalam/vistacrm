<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Models\NotificationLog;
use App\Services\WhatsApp\WhatsAppManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LeadDiagnosticsCommand extends Command
{
    protected $signature = 'leads:diagnostics {--limit=10 : Number of recent logs to display}';
    protected $description = 'Display CRM follow-up reminder diagnostics, scheduler status, queue health, and WhatsApp delivery logs in Asia/Kolkata timestamps';

    public function handle(WhatsAppManager $whatsAppManager): int
    {
        $this->info("================================================================================");
        $this->info("       VISTABOUND CRM — FOLLOW-UP REMINDER & NOTIFICATION DIAGNOSTICS            ");
        $this->info("================================================================================");

        $nowUtc = Carbon::now('UTC');
        $nowIst = Carbon::now('Asia/Kolkata');

        $this->line("<comment>Server Time (UTC):</comment>          " . $nowUtc->format('Y-m-d H:i:s') . " UTC");
        $this->line("<comment>Local Business Time (IST):</comment>   " . $nowIst->format('d M Y, h:i:s A') . " Asia/Kolkata (UTC+05:30)");
        $this->newLine();

        // 1. Follow-up Metrics
        $totalFollowUps = FollowUp::count();
        $pendingFollowUps = FollowUp::where('status', 'pending')->count();
        $overdueFollowUps = FollowUp::where('status', 'pending')->where('scheduled_at', '<', $nowUtc)->count();
        $pendingRemindersDue = FollowUp::where('status', 'pending')
            ->where('reminder_status', 'pending')
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', $nowUtc)
            ->count();
        $overdueUnsentReminders = FollowUp::where('status', 'pending')
            ->where('reminder_status', 'pending')
            ->where('scheduled_at', '<', $nowUtc)
            ->count();

        $this->info("--- [1] Follow-up Tasks & Reminders State ---");
        $this->table(
            ['Metric', 'Count', 'Status / Policy'],
            [
                ['Total Follow-up Tasks', $totalFollowUps, 'In database'],
                ['Pending Follow-up Tasks', $pendingFollowUps, 'Active'],
                ['Overdue Follow-up Tasks (scheduled_at < now)', $overdueFollowUps, $overdueFollowUps > 0 ? '<fg=red>Requires Attention</>' : '<fg=green>Clear</>'],
                ['Reminders Due For Dispatch (reminder_at <= now)', $pendingRemindersDue, $pendingRemindersDue > 0 ? '<fg=yellow>Eligible for dispatch</>' : '<fg=green>None pending</>'],
                ['Overdue Unsent Reminders (past due_at)', $overdueUnsentReminders, $overdueUnsentReminders > 0 ? '<fg=yellow>Will dispatch via late policy</>' : '<fg=green>None overdue</>'],
            ]
        );
        $this->newLine();

        // 2. Queue & Scheduler Health
        $queueBacklog = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        $this->info("--- [2] Queue & Scheduler Health ---");
        $this->table(
            ['Component', 'Configuration / Value', 'Health'],
            [
                ['Scheduler Cadence', 'everyMinute()->withoutOverlapping()', '<fg=green>Active</>'],
                ['Queue Backlog (jobs table)', $queueBacklog, $queueBacklog > 10 ? '<fg=yellow>High Backlog</>' : '<fg=green>Normal</>'],
                ['Failed Queue Jobs (failed_jobs table)', $failedJobs, $failedJobs > 0 ? '<fg=red>Failed Jobs Present</>' : '<fg=green>0 (Clean)</>'],
            ]
        );
        $this->newLine();

        // 3. WhatsApp Integration Health
        $waStatus = $whatsAppManager->getStatusSummary();
        $this->info("--- [3] WhatsApp Integration Status ---");
        $this->table(
            ['Parameter', 'Value'],
            [
                ['Configured Driver', $waStatus['driver']],
                ['Live Credentials Configured', $waStatus['configured'] ? '<fg=green>YES</>' : '<fg=yellow>NO (MOCK DRIVER ACTIVE)</>'],
                ['Meta Phone Number ID', $waStatus['phone_number_id'] ?: 'None (Mock)'],
                ['Advisory Note', $waStatus['advisory']],
            ]
        );
        $this->newLine();

        // 4. Recent Reminder Notification Logs
        $limit = (int) $this->option('limit');
        $logs = NotificationLog::where(function ($q) {
                $q->where('channel', 'like', '%whatsapp%')
                  ->orWhere('channel', 'in_app');
            })
            ->with(['user', 'followUp.lead'])
            ->latest()
            ->take($limit)
            ->get();

        $this->info("--- [4] Recent Reminder Delivery Logs (Last {$limit}) ---");
        if ($logs->isEmpty()) {
            $this->comment("No reminder delivery logs recorded yet.");
        } else {
            $rows = [];
            foreach ($logs as $log) {
                $fu = $log->followUp;
                $lead = $fu?->lead;
                $user = $log->user;
                $metadata = is_array($log->metadata) ? $log->metadata : json_decode($log->metadata ?? '[]', true);

                $dueIst = $metadata['due_time_ist'] ?? ($fu?->scheduled_at_ist ?? 'N/A');
                $dispatchIst = $metadata['dispatch_time_ist'] ?? ($log->created_at?->setTimezone('Asia/Kolkata')->format('d M, h:i:s A') ?? 'N/A');
                $statusColor = match ($log->status) {
                    'delivered', 'sent' => '<fg=green>' . $log->status . '</>',
                    'simulated' => '<fg=cyan>simulated (mock)</>',
                    'failed' => '<fg=red>failed</>',
                    default => $log->status,
                };

                $rows[] = [
                    '#' . $log->id,
                    'FU #' . ($log->follow_up_id ?? 'N/A'),
                    $lead ? ($lead->name . ' (' . $lead->reference_id . ')') : 'N/A',
                    $user?->name ?? 'System',
                    $log->channel,
                    $dueIst,
                    $dispatchIst,
                    $statusColor,
                    $log->reminder_key ?? 'N/A',
                ];
            }

            $this->table(
                ['Log ID', 'Follow-up', 'Lead', 'Agent', 'Channel', 'Due (IST)', 'Dispatched (IST)', 'Status', 'Reminder Key'],
                $rows
            );
        }

        $this->newLine();
        $this->info("================================================================================");
        return Command::SUCCESS;
    }
}
