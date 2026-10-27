<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckFollowUpsCommand extends Command
{
    protected $signature = 'leads:check-followups';
    protected $description = 'Scan pending follow-ups due soon and dispatch reminder notifications';

    public function handle(): int
    {
        $now = Carbon::now();
        $dueSoon = $now->copy()->addMinutes(60);

        $pendingFollowUps = FollowUp::with(['lead', 'user'])
            ->where('status', 'pending')
            ->where('reminder_sent', false)
            ->where('scheduled_at', '<=', $dueSoon)
            ->get();

        $count = 0;
        foreach ($pendingFollowUps as $followUp) {
            $lead = $followUp->lead;
            $user = $followUp->user;

            if ($lead && $user) {
                $isOverdue = $followUp->scheduled_at < $now;
                $title = $isOverdue
                    ? "OVERDUE: Follow-up with {$lead->name}"
                    : "Upcoming Follow-up with {$lead->name}";

                $message = $isOverdue
                    ? "Follow-up was scheduled for {$followUp->scheduled_at->format('d M, h:i A')}. Please contact {$lead->name} ({$lead->phone})."
                    : "Scheduled today at {$followUp->scheduled_at->format('h:i A')}. Service: {$lead->service_interest}.";

                NotificationLog::create([
                    'lead_id' => $lead->id,
                    'user_id' => $user->id,
                    'channel' => 'in_app',
                    'recipient' => $user->email,
                    'title' => $title,
                    'message' => $message,
                    'status' => 'delivered',
                    'is_read' => false,
                ]);

                $followUp->reminder_sent = true;
                $followUp->save();
                $count++;
            }
        }

        $this->info("Dispatched {$count} follow-up reminder notifications.");
        return Command::SUCCESS;
    }
}
