<?php

namespace App\Jobs;

use App\Models\FollowUp;
use App\Models\NotificationLog;
use App\Services\WhatsApp\WhatsAppManager;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendFollowUpReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $followUpId,
        public string $reminderKey,
        public ?string $detectionTimeIso = null
    ) {
        $this->detectionTimeIso = $detectionTimeIso ?? Carbon::now('UTC')->toIso8601String();
    }

    public function handle(WhatsAppManager $whatsAppManager): void
    {
        // 1. Recheck follow-up status immediately before sending
        $followUp = FollowUp::with(['lead', 'user'])->find($this->followUpId);

        if (!$followUp) {
            Log::warning("[REMINDER ABORTED] Follow-up #{$this->followUpId} no longer exists.");
            return;
        }

        if ($followUp->status !== 'pending') {
            Log::info("[REMINDER ABORTED] Follow-up #{$this->followUpId} is not pending (status: {$followUp->status}). Invalidation confirmed.");
            return;
        }

        if ($followUp->reminder_status === 'sent') {
            Log::info("[REMINDER ABORTED] Reminder for Follow-up #{$this->followUpId} was already sent (key: {$this->reminderKey}).");
            return;
        }

        if ($followUp->reminder_key !== $this->reminderKey) {
            Log::info("[REMINDER ABORTED] Reminder key mismatch for Follow-up #{$this->followUpId}. Stored: '{$followUp->reminder_key}', Job: '{$this->reminderKey}'. Invalidated by reschedule.");
            return;
        }

        $lead = $followUp->lead;
        $user = $followUp->user;
        if (!$lead || !$user) {
            Log::warning("[REMINDER ABORTED] Missing lead or assignee relationship for Follow-up #{$this->followUpId}.");
            return;
        }

        // 2. Prevent duplicate execution using atomic state transition
        $affected = FollowUp::where('id', $followUp->id)
            ->where('reminder_status', 'pending')
            ->where('reminder_key', $this->reminderKey)
            ->update(['reminder_status' => 'processing']);

        if ($affected === 0) {
            Log::info("[REMINDER SKIPPED] Follow-up #{$this->followUpId} is already being processed by another worker.");
            return;
        }

        $nowUtc = Carbon::now('UTC');
        $dueTimeIst = $followUp->scheduled_at->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A');
        $reminderTimeIst = $followUp->reminder_at ? $followUp->reminder_at->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A') : 'Immediately';
        $detectionTimeIst = Carbon::parse($this->detectionTimeIso)->setTimezone('Asia/Kolkata')->format('d M Y, h:i:s A');
        $dispatchTimeIst = $nowUtc->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i:s A');

        $recipientPhone = $user->phone ?: '+916361232225';
        $title = "Follow-up Reminder: {$lead->name} due at {$dueTimeIst}";
        $message = "REMINDER [Vistabound CRM]: Scheduled follow-up with {$lead->name} ({$lead->phone}) is due at {$dueTimeIst} IST for {$lead->service_interest}. Notes: " . ($followUp->notes ?: 'None');

        $diagnosticMetadata = [
            'follow_up_id' => $followUp->id,
            'employee_id' => $user->id,
            'employee_name' => $user->name,
            'lead_id' => $lead->id,
            'lead_name' => $lead->name,
            'due_time_utc' => $followUp->scheduled_at->toIso8601String(),
            'due_time_ist' => $dueTimeIst,
            'reminder_time_utc' => $followUp->reminder_at?->toIso8601String(),
            'reminder_time_ist' => $reminderTimeIst,
            'detection_time_ist' => $detectionTimeIst,
            'dispatch_time_ist' => $dispatchTimeIst,
            'reminder_key' => $this->reminderKey,
            'channel' => 'whatsapp,in_app',
        ];

        // 3. Dispatch through WhatsApp channel
        $waResult = $whatsAppManager->sendMessage($recipientPhone, $message, $diagnosticMetadata);

        if ($waResult['success']) {
            DB::transaction(function () use ($followUp, $lead, $user, $recipientPhone, $title, $message, $diagnosticMetadata, $waResult, $nowUtc) {
                // Record WhatsApp notification log with unique reminder key
                NotificationLog::updateOrCreate(
                    ['reminder_key' => $this->reminderKey],
                    [
                        'lead_id' => $lead->id,
                        'follow_up_id' => $followUp->id,
                        'user_id' => $user->id,
                        'channel' => 'whatsapp',
                        'recipient' => $recipientPhone,
                        'title' => $title,
                        'message' => $message,
                        'status' => 'delivered',
                        'metadata' => array_merge($diagnosticMetadata, ['provider_response' => $waResult]),
                        'is_read' => true,
                    ]
                );

                // Record in-app alert log for real-time popup & notifications center
                NotificationLog::create([
                    'lead_id' => $lead->id,
                    'follow_up_id' => $followUp->id,
                    'user_id' => $user->id,
                    'channel' => 'in_app',
                    'recipient' => $user->email,
                    'title' => $title,
                    'message' => $message,
                    'status' => 'delivered',
                    'metadata' => array_merge($diagnosticMetadata, ['popup_alert' => true]),
                    'is_read' => false,
                ]);

                // Update follow-up record
                $followUp->update([
                    'reminder_status' => 'sent',
                    'reminder_sent' => true,
                    'reminder_sent_at' => $nowUtc,
                    'reminder_channel' => 'whatsapp,in_app',
                ]);
            });

            Log::info("[REMINDER SUCCESS] FollowUp #{$followUp->id} | Due: {$dueTimeIst} IST | Sent to {$recipientPhone} via {$waResult['provider']}");
        } else {
            // Failed delivery: Record error details
            $errorMessage = $waResult['error'] ?? 'Unknown WhatsApp provider error';

            NotificationLog::create([
                'lead_id' => $lead->id,
                'follow_up_id' => $followUp->id,
                'user_id' => $user->id,
                'channel' => 'whatsapp',
                'recipient' => $recipientPhone,
                'title' => "FAILED: " . $title,
                'message' => $message,
                'status' => 'failed',
                'error_message' => $errorMessage,
                'metadata' => array_merge($diagnosticMetadata, ['error' => $errorMessage]),
                'is_read' => false,
            ]);

            $followUp->update([
                'reminder_status' => 'pending', // allow retry
            ]);

            Log::error("[REMINDER FAILURE] FollowUp #{$followUp->id} | Error: {$errorMessage}");

            throw new \RuntimeException($errorMessage);
        }
    }
}
