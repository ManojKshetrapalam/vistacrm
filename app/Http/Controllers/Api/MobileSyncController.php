<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendFollowUpReminderJob;
use App\Models\CrmSetting;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\NotificationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MobileSyncController extends Controller
{
    /**
     * Pull delta updates for offline synchronization.
     */
    public function pull(Request $request): JsonResponse
    {
        $user = $request->user();
        $since = $request->filled('since') ? Carbon::parse($request->query('since'))->setTimezone('UTC') : null;

        // Accessible leads delta
        $leadsQuery = Lead::accessibleBy($user)
            ->with([
                'assignedEmployee:id,name,email,phone',
                'creator:id,name',
                'followUps.user:id,name',
                'activities.user:id,name',
            ]);

        if ($since) {
            $leadsQuery->where('updated_at', '>=', $since);
        }

        $leads = $leadsQuery->orderBy('updated_at', 'desc')->limit(500)->get();

        // Accessible follow-ups delta
        $followUpsQuery = FollowUp::query()->with('lead:id,reference_id,name,phone,company_name,status');
        if (!$user->isAdmin()) {
            $followUpsQuery->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('lead', function ($lq) use ($user) {
                      $lq->where('assigned_to', $user->id);
                  });
            });
        }

        if ($since) {
            $followUpsQuery->where('updated_at', '>=', $since);
        }

        $followUps = $followUpsQuery->orderBy('scheduled_at', 'asc')->limit(500)->get();

        // CRM settings & reference dictionaries
        $pipelineStages = CrmSetting::getSetting('pipeline_stages', [
            ['id' => 'new', 'label' => 'New Lead', 'color' => '#3B82F6'],
            ['id' => 'contacted', 'label' => 'Contacted', 'color' => '#8B5CF6'],
            ['id' => 'follow_up', 'label' => 'Follow Up', 'color' => '#F59E0B'],
            ['id' => 'interested', 'label' => 'Interested', 'color' => '#10B981'],
            ['id' => 'converted', 'label' => 'Converted / Won', 'color' => '#059669'],
            ['id' => 'lost', 'label' => 'Lost / Closed', 'color' => '#EF4444'],
            ['id' => 'not_reachable', 'label' => 'Not Reachable', 'color' => '#6B7280'],
        ]);

        $leadSources = CrmSetting::getSetting('lead_sources', [
            ['id' => 'website', 'label' => 'Website Form'],
            ['id' => 'whatsapp', 'label' => 'Direct WhatsApp'],
            ['id' => 'referral', 'label' => 'Client Referral'],
            ['id' => 'social_media', 'label' => 'Social Media Campaign'],
            ['id' => 'cold_call', 'label' => 'Cold Outreach'],
            ['id' => 'event', 'label' => 'Event / Exhibition'],
            ['id' => 'other', 'label' => 'Other Channel'],
        ]);

        $deletedLeadIds = [];
        if ($since) {
            $deletedLeadIds = Lead::onlyTrashed()
                ->where('deleted_at', '>=', $since)
                ->pluck('id')
                ->toArray();
        } else {
            $deletedLeadIds = Lead::onlyTrashed()->pluck('id')->toArray();
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'leads' => $leads,
            'deleted_lead_ids' => $deletedLeadIds,
            'follow_ups' => $followUps,
            'settings' => [
                'pipeline_stages' => $pipelineStages,
                'lead_sources' => $leadSources,
            ],
        ]);
    }

    /**
     * Push queued mutations from offline outbox.
     */
    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mutations' => 'required|array',
            'mutations.*.uuid' => 'required|string|max:64',
            'mutations.*.operation' => 'required|string|in:create_note,update_status,log_call,create_followup,reschedule_followup,complete_followup,create_lead',
            'mutations.*.lead_id' => 'nullable|integer',
            'mutations.*.payload' => 'required|array',
            'mutations.*.client_timestamp' => 'nullable|string',
            'mutations.*.version' => 'nullable|integer',
        ]);

        $user = $request->user();
        $results = [];
        $leadInitialVersions = [];

        foreach ($validated['mutations'] as $mutation) {
            $uuid = $mutation['uuid'];
            $operation = $mutation['operation'];
            $leadId = $mutation['lead_id'] ?? null;
            $payload = $mutation['payload'];
            $clientVersion = $mutation['version'] ?? null;

            // Idempotency check: Has this client_uuid already been processed?
            $existingActivity = LeadActivity::where('client_uuid', $uuid)->first();
            $existingFollowUp = FollowUp::where('client_uuid', $uuid)->first();
            $existingLead = Lead::where('client_uuid', $uuid)->first();

            if ($existingActivity || $existingFollowUp || $existingLead) {
                $results[] = [
                    'uuid' => $uuid,
                    'status' => 'synced',
                    'message' => 'Already synchronized',
                    'operation' => $operation,
                    'server_id' => $existingActivity?->id ?? $existingFollowUp?->id ?? $existingLead?->id,
                ];
                continue;
            }

            try {
                DB::beginTransaction();

                if ($operation === 'create_lead') {
                    $phoneNormalized = Lead::normalizePhone($payload['phone'] ?? '');
                    if (empty($phoneNormalized) || strlen($phoneNormalized) < 7) {
                        DB::rollBack();
                        $results[] = [
                            'uuid' => $uuid,
                            'status' => 'rejected',
                            'message' => 'Invalid phone number format',
                            'operation' => $operation,
                        ];
                        continue;
                    }

                    $newLead = Lead::create([
                        'client_uuid' => $uuid,
                        'name' => $payload['name'] ?? 'Unnamed Lead',
                        'phone' => $payload['phone'],
                        'phone_normalized' => $phoneNormalized,
                        'email' => $payload['email'] ?? null,
                        'company_name' => $payload['company_name'] ?? null,
                        'source' => $payload['source'] ?? 'mobile_app',
                        'service_interest' => $payload['service_interest'] ?? null,
                        'additional_details' => $payload['additional_details'] ?? null,
                        'status' => $payload['status'] ?? 'new',
                        'priority' => $payload['priority'] ?? 'medium',
                        'assigned_to' => $user->isAdmin() ? ($payload['assigned_to'] ?? $user->id) : $user->id,
                        'created_by' => $user->id,
                        'version' => 1,
                    ]);

                    LeadActivity::create([
                        'lead_id' => $newLead->id,
                        'user_id' => $user->id,
                        'activity_type' => 'lead_created',
                        'title' => 'Lead created via Mobile App',
                        'description' => "Lead created while offline and synchronized.",
                        'metadata' => ['client_uuid' => $uuid],
                    ]);

                    DB::commit();

                    $results[] = [
                        'uuid' => $uuid,
                        'status' => 'synced',
                        'operation' => $operation,
                        'server_id' => $newLead->id,
                        'lead' => $newLead,
                    ];
                    continue;
                }

                // For existing lead operations: Lead must exist and be accessible
                $lead = Lead::accessibleBy($user)->find($leadId);
                if (!$lead) {
                    DB::rollBack();
                    $results[] = [
                        'uuid' => $uuid,
                        'status' => 'forbidden',
                        'message' => 'Lead not found or access denied.',
                        'operation' => $operation,
                        'lead_id' => $leadId,
                    ];
                    continue;
                }

                if (!isset($leadInitialVersions[$lead->id])) {
                    $leadInitialVersions[$lead->id] = $lead->version;
                }

                if ($operation === 'create_note') {
                    $activity = LeadActivity::create([
                        'client_uuid' => $uuid,
                        'lead_id' => $lead->id,
                        'user_id' => $user->id,
                        'activity_type' => 'note',
                        'title' => 'Note added via Mobile',
                        'description' => $payload['note'] ?? $payload['content'] ?? 'Note',
                        'metadata' => [
                            'client_timestamp' => $mutation['client_timestamp'] ?? null,
                        ],
                    ]);

                    $lead->touch();
                    $lead->increment('version');
                    DB::commit();

                    $results[] = [
                        'uuid' => $uuid,
                        'status' => 'synced',
                        'operation' => $operation,
                        'server_id' => $activity->id,
                        'lead_version' => $lead->version,
                    ];
                } elseif ($operation === 'update_status') {
                    // Check version conflict against initial version before batch
                    $initialVer = $leadInitialVersions[$lead->id] ?? $lead->version;
                    if ($clientVersion !== null && $initialVer > $clientVersion && $lead->status !== ($payload['status'] ?? $lead->status)) {
                        DB::rollBack();
                        $results[] = [
                            'uuid' => $uuid,
                            'status' => 'conflict',
                            'message' => "Lead status was concurrently modified on server to '{$lead->status}'.",
                            'operation' => $operation,
                            'server_lead' => $lead,
                            'server_version' => $lead->version,
                        ];
                        continue;
                    }

                    $oldStatus = $lead->status;
                    $newStatus = $payload['status'] ?? $oldStatus;
                    $lead->status = $newStatus;
                    $lead->increment('version');
                    $lead->save();

                    LeadActivity::create([
                        'client_uuid' => $uuid,
                        'lead_id' => $lead->id,
                        'user_id' => $user->id,
                        'activity_type' => 'status_change',
                        'title' => "Status changed to " . ucfirst(str_replace('_', ' ', $newStatus)),
                        'description' => "Status updated from " . ucfirst(str_replace('_', ' ', $oldStatus)) . " to " . ucfirst(str_replace('_', ' ', $newStatus)) . " (via mobile sync)",
                        'metadata' => ['old_status' => $oldStatus, 'new_status' => $newStatus],
                    ]);

                    DB::commit();

                    $results[] = [
                        'uuid' => $uuid,
                        'status' => 'synced',
                        'operation' => $operation,
                        'lead_version' => $lead->version,
                        'new_status' => $newStatus,
                    ];
                } elseif ($operation === 'log_call') {
                    $outcome = $payload['outcome'] ?? 'connected';
                    $notes = $payload['notes'] ?? '';
                    $lead->last_contact_date = now();
                    $lead->increment('version');
                    $lead->save();

                    $activity = LeadActivity::create([
                        'client_uuid' => $uuid,
                        'lead_id' => $lead->id,
                        'user_id' => $user->id,
                        'activity_type' => 'call_logged',
                        'title' => "Call logged: " . ucfirst(str_replace('_', ' ', $outcome)),
                        'description' => $notes ?: "Call outcome: {$outcome}",
                        'metadata' => [
                            'outcome' => $outcome,
                            'duration' => $payload['duration'] ?? null,
                            'client_timestamp' => $mutation['client_timestamp'] ?? null,
                        ],
                    ]);

                    DB::commit();

                    $results[] = [
                        'uuid' => $uuid,
                        'status' => 'synced',
                        'operation' => $operation,
                        'server_id' => $activity->id,
                        'lead_version' => $lead->version,
                    ];
                } elseif ($operation === 'create_followup') {
                    $scheduledAtUtc = FollowUp::parseToUtc($payload['scheduled_at'] ?? now()->addDay());
                    $reminderAtUtc = $scheduledAtUtc->copy()->subMinutes(5);

                    $followUp = FollowUp::create([
                        'client_uuid' => $uuid,
                        'lead_id' => $lead->id,
                        'user_id' => $user->id,
                        'scheduled_at' => $scheduledAtUtc,
                        'reminder_at' => $reminderAtUtc,
                        'status' => 'pending',
                        'notes' => $payload['notes'] ?? 'Scheduled via mobile app',
                        'reminder_status' => 'pending',
                    ]);

                    $lead->next_follow_up_date = $scheduledAtUtc;
                    $lead->increment('version');
                    $lead->save();

                    $formattedIst = $scheduledAtUtc->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A');

                    LeadActivity::create([
                        'lead_id' => $lead->id,
                        'user_id' => $user->id,
                        'activity_type' => 'follow_up_scheduled',
                        'title' => 'Follow-up scheduled',
                        'description' => "Scheduled for {$formattedIst} IST (via mobile)",
                        'metadata' => [
                            'follow_up_id' => $followUp->id,
                            'scheduled_at' => $scheduledAtUtc->toIso8601String(),
                        ],
                    ]);

                    DB::commit();

                    // If reminder is within next 10 minutes, queue background job
                    $delaySeconds = max(0, now()->diffInSeconds($reminderAtUtc, false));
                    if ($delaySeconds >= 0 && $delaySeconds <= 600) {
                        try {
                            SendFollowUpReminderJob::dispatch($followUp->id)->delay($reminderAtUtc);
                        } catch (\Throwable $e) {
                            Log::warning("Could not dispatch SendFollowUpReminderJob: " . $e->getMessage());
                        }
                    }

                    $results[] = [
                        'uuid' => $uuid,
                        'status' => 'synced',
                        'operation' => $operation,
                        'server_id' => $followUp->id,
                        'follow_up' => $followUp,
                        'lead_version' => $lead->version,
                    ];
                } elseif ($operation === 'reschedule_followup') {
                    $followUpId = $payload['follow_up_id'] ?? null;
                    $followUp = FollowUp::where('id', $followUpId)->where('lead_id', $lead->id)->first();

                    if (!$followUp) {
                        DB::rollBack();
                        $results[] = [
                            'uuid' => $uuid,
                            'status' => 'rejected',
                            'message' => 'Follow-up task not found.',
                            'operation' => $operation,
                        ];
                        continue;
                    }

                    $newScheduledUtc = FollowUp::parseToUtc($payload['scheduled_at']);
                    $followUp->scheduled_at = $newScheduledUtc;
                    $followUp->reminder_at = $newScheduledUtc->copy()->subMinutes(5);
                    $followUp->reminder_status = 'pending';
                    $followUp->reminder_sent = false;
                    $followUp->reminder_sent_at = null;
                    if (isset($payload['notes'])) {
                        $followUp->notes = $payload['notes'];
                    }
                    $followUp->increment('version');
                    $followUp->save();

                    $lead->next_follow_up_date = $newScheduledUtc;
                    $lead->increment('version');
                    $lead->save();

                    $formattedIst = $newScheduledUtc->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A');

                    LeadActivity::create([
                        'client_uuid' => $uuid,
                        'lead_id' => $lead->id,
                        'user_id' => $user->id,
                        'activity_type' => 'follow_up_rescheduled',
                        'title' => 'Follow-up rescheduled',
                        'description' => "Rescheduled to {$formattedIst} IST (via mobile)",
                        'metadata' => [
                            'follow_up_id' => $followUp->id,
                            'scheduled_at' => $newScheduledUtc->toIso8601String(),
                        ],
                    ]);

                    DB::commit();

                    $results[] = [
                        'uuid' => $uuid,
                        'status' => 'synced',
                        'operation' => $operation,
                        'server_id' => $followUp->id,
                        'lead_version' => $lead->version,
                    ];
                } elseif ($operation === 'complete_followup') {
                    $followUpId = $payload['follow_up_id'] ?? null;
                    $followUp = FollowUp::where('id', $followUpId)->where('lead_id', $lead->id)->first();

                    if ($followUp) {
                        $followUp->status = 'completed';
                        $followUp->completed_at = now();
                        $followUp->outcome = $payload['outcome'] ?? 'Completed via mobile';
                        $followUp->increment('version');
                        $followUp->save();

                        LeadActivity::create([
                            'client_uuid' => $uuid,
                            'lead_id' => $lead->id,
                            'user_id' => $user->id,
                            'activity_type' => 'follow_up_completed',
                            'title' => 'Follow-up marked completed',
                            'description' => $followUp->outcome,
                            'metadata' => ['follow_up_id' => $followUp->id],
                        ]);
                    }

                    DB::commit();

                    $results[] = [
                        'uuid' => $uuid,
                        'status' => 'synced',
                        'operation' => $operation,
                        'server_id' => $followUp?->id,
                    ];
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error("Mobile sync mutation error for UUID {$uuid}: " . $e->getMessage());
                $results[] = [
                    'uuid' => $uuid,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'operation' => $operation,
                ];
            }
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'results' => $results,
        ]);
    }
}
