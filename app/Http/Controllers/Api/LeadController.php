<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\NotificationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $isArchived = $request->boolean('archived') || $request->input('status') === 'archived';
        $query = ($isArchived && $user->isAdmin())
            ? Lead::onlyTrashed()->accessibleBy($user)
            : Lead::accessibleBy($user);

        $query->with(['assignedEmployee:id,name,email,phone', 'creator:id,name']);

        // Search term
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        // Filters
        if ($request->filled('status') && $request->input('status') !== 'archived') {
            $statuses = is_array($request->input('status')) ? $request->input('status') : explode(',', $request->input('status'));
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('priority')) {
            $priorities = is_array($request->input('priority')) ? $request->input('priority') : explode(',', $request->input('priority'));
            $query->whereIn('priority', $priorities);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        if ($request->filled('service_interest')) {
            $query->where('service_interest', $request->input('service_interest'));
        }

        if ($user->isAdmin() && $request->filled('assigned_to')) {
            if ($request->input('assigned_to') === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->input('assigned_to'));
            }
        }

        if ($request->filled('followup_filter')) {
            $fFilter = $request->input('followup_filter');
            $now = Carbon::now();
            if ($fFilter === 'overdue') {
                $query->whereNotNull('next_follow_up_date')
                      ->where('next_follow_up_date', '<', $now);
            } elseif ($fFilter === 'today') {
                $query->whereDate('next_follow_up_date', Carbon::today());
            } elseif ($fFilter === 'upcoming') {
                $query->where('next_follow_up_date', '>', Carbon::today()->endOfDay());
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['created_at', 'next_follow_up_date', 'last_contact_date', 'name', 'status', 'priority'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(100, max(1, (int)$request->input('per_page', 15)));
        $leads = $query->paginate($perPage);

        return response()->json($leads);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $query = $user->isAdmin() ? Lead::withTrashed() : Lead::query();
        $lead = $query->findOrFail($id);

        if (!$user->isAdmin() && $lead->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized access to this lead.'], 403);
        }

        $lead->load([
            'assignedEmployee:id,name,email,phone',
            'creator:id,name',
            'activities.user:id,name',
            'followUps.user:id,name',
            'notificationLogs' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(20);
            },
        ]);

        $data = $lead->toArray();
        $data['is_archived'] = $lead->trashed();

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:100',
            'service_interest' => 'nullable|string|max:255',
            'additional_details' => 'nullable|string',
            'status' => 'nullable|in:new,assigned,contacted,follow_up,interested,converted,lost,not_reachable',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'next_follow_up_date' => 'nullable|date',
            'confirm_duplicate' => 'nullable|boolean',
        ]);

        $normalizedPhone = Lead::normalizePhone($validated['phone']);
        if (empty($normalizedPhone) || strlen($normalizedPhone) < 7) {
            return response()->json([
                'message' => 'The phone number provided is invalid. Please enter at least 7 digits.',
                'errors' => ['phone' => ['Invalid phone number format.']],
            ], 422);
        }

        // Duplicate check
        $existingLead = Lead::where('phone_normalized', $normalizedPhone)->first();
        if ($existingLead && empty($validated['confirm_duplicate'])) {
            return response()->json([
                'message' => 'A lead with this phone number already exists in the system.',
                'duplicate' => true,
                'existing_lead' => [
                    'id' => $existingLead->id,
                    'reference_id' => $existingLead->reference_id,
                    'name' => $existingLead->name,
                    'company_name' => $existingLead->company_name,
                    'status' => $existingLead->status,
                    'assigned_to_name' => $existingLead->assignedEmployee?->name ?? 'Unassigned',
                    'created_at' => $existingLead->created_at->toIso8601String(),
                ],
            ], 409);
        }

        // Assignee logic: Employees default to themselves if creating, Admin can specify
        $assigneeId = null;
        if ($user->isAdmin()) {
            $assigneeId = $validated['assigned_to'] ?? null;
        } else {
            $assigneeId = $user->id;
        }

        $status = $validated['status'] ?? ($assigneeId ? 'assigned' : 'new');

        $lead = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'phone_normalized' => $normalizedPhone,
            'email' => $validated['email'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'source' => $validated['source'] ?? 'Website',
            'service_interest' => $validated['service_interest'] ?? null,
            'additional_details' => $validated['additional_details'] ?? null,
            'status' => $status,
            'priority' => $validated['priority'] ?? 'medium',
            'assigned_to' => $assigneeId,
            'created_by' => $user->id,
            'next_follow_up_date' => $validated['next_follow_up_date'] ?? null,
        ]);

        // Log creation activity
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'activity_type' => 'lead_created',
            'title' => 'Lead created manually',
            'description' => "Created by {$user->name}. Source: {$lead->source}",
            'metadata' => ['source' => $lead->source],
        ]);

        if ($assigneeId) {
            $assignedUser = User::find($assigneeId);
            if ($assignedUser) {
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $user->id,
                    'activity_type' => 'assignment_change',
                    'title' => "Assigned to {$assignedUser->name}",
                    'description' => "Lead assigned upon creation.",
                    'metadata' => ['assigned_to' => $assignedUser->id, 'assignee_name' => $assignedUser->name],
                ]);

                if ($assignedUser->id !== $user->id) {
                    NotificationLog::create([
                        'lead_id' => $lead->id,
                        'user_id' => $assignedUser->id,
                        'event_type' => 'new_lead',
                        'channel' => 'in_app',
                        'recipient' => $assignedUser->email,
                        'title' => 'New Lead Assigned',
                        'message' => "You have been assigned lead {$lead->name} ({$lead->reference_id}) for {$lead->service_interest}.",
                        'status' => 'delivered',
                        'metadata' => [
                            'lead_id' => $lead->id,
                            'lead_reference_id' => $lead->reference_id,
                            'lead_name' => $lead->name,
                            'company_name' => $lead->company_name,
                            'phone' => $lead->phone,
                            'service_interest' => $lead->service_interest,
                            'popup_alert' => true,
                        ],
                        'is_read' => false,
                    ]);
                }
            }
        } else {
            // Unassigned lead created: Notify all active administrators
            $admins = User::where('role', 'admin')->where('is_active', true)->get();
            foreach ($admins as $admin) {
                NotificationLog::create([
                    'lead_id' => $lead->id,
                    'user_id' => $admin->id,
                    'event_type' => 'unassigned_lead',
                    'channel' => 'in_app',
                    'recipient' => $admin->email,
                    'title' => 'New Unassigned Lead Received',
                    'message' => "New unassigned lead {$lead->name} ({$lead->reference_id}) requires staff assignment.",
                    'status' => 'delivered',
                    'metadata' => [
                        'lead_id' => $lead->id,
                        'lead_reference_id' => $lead->reference_id,
                        'lead_name' => $lead->name,
                        'company_name' => $lead->company_name,
                        'phone' => $lead->phone,
                        'service_interest' => $lead->service_interest,
                        'popup_alert' => true,
                    ],
                    'is_read' => false,
                ]);
            }
        }

        // If follow-up date specified, create follow-up
        if (!empty($validated['next_follow_up_date'])) {
            $dueUtc = FollowUp::parseToUtc($validated['next_follow_up_date']);
            $lead->next_follow_up_date = $dueUtc;
            $lead->save();

            $followUp = FollowUp::create([
                'lead_id' => $lead->id,
                'user_id' => $assigneeId ?: $user->id,
                'scheduled_at' => $dueUtc,
                'status' => 'pending',
                'notes' => 'Initial follow-up scheduled on lead creation.',
            ]);

            $formattedIst = $dueUtc->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A');

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'activity_type' => 'follow_up_scheduled',
                'title' => 'Follow-up scheduled',
                'description' => "Scheduled for {$formattedIst} IST",
                'metadata' => [
                    'follow_up_id' => $followUp->id,
                    'scheduled_at' => $dueUtc->toIso8601String(),
                    'scheduled_at_ist' => $formattedIst,
                ],
            ]);
        }

        $lead->load(['assignedEmployee:id,name,email,phone', 'creator:id,name']);

        return response()->json([
            'message' => 'Lead created successfully',
            'lead' => $lead,
        ], 201);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && $lead->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized to update this lead.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:30',
            'email' => 'nullable|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:100',
            'service_interest' => 'nullable|string|max:255',
            'additional_details' => 'nullable|string',
            'status' => 'nullable|in:new,assigned,contacted,follow_up,interested,converted,lost,not_reachable',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'next_follow_up_date' => 'nullable|date',
        ]);

        $oldStatus = $lead->status;
        $oldAssignee = $lead->assigned_to;

        if (isset($validated['phone']) && $validated['phone'] !== $lead->phone) {
            $normalized = Lead::normalizePhone($validated['phone']);
            if (empty($normalized) || strlen($normalized) < 7) {
                return response()->json([
                    'message' => 'The phone number provided is invalid.',
                    'errors' => ['phone' => ['Invalid phone number format.']],
                ], 422);
            }
            $lead->phone = $validated['phone'];
            $lead->phone_normalized = $normalized;
        }

        if (isset($validated['name'])) {
            $lead->name = $validated['name'];
        }
        if (array_key_exists('email', $validated)) {
            $lead->email = $validated['email'];
        }
        if (array_key_exists('company_name', $validated)) {
            $lead->company_name = $validated['company_name'];
        }
        if (array_key_exists('source', $validated)) {
            $lead->source = $validated['source'];
        }
        if (array_key_exists('service_interest', $validated)) {
            $lead->service_interest = $validated['service_interest'];
        }
        if (array_key_exists('additional_details', $validated)) {
            $lead->additional_details = $validated['additional_details'];
        }
        if (isset($validated['priority'])) {
            $lead->priority = $validated['priority'];
        }
        if (array_key_exists('next_follow_up_date', $validated)) {
            $lead->next_follow_up_date = $validated['next_follow_up_date'];
        }

        // Status change tracking
        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            $lead->status = $validated['status'];
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'activity_type' => 'status_change',
                'title' => "Status changed to " . ucfirst(str_replace('_', ' ', $lead->status)),
                'description' => "Status updated from " . ucfirst(str_replace('_', ' ', $oldStatus)) . " to " . ucfirst(str_replace('_', ' ', $lead->status)),
                'metadata' => ['old_status' => $oldStatus, 'new_status' => $lead->status],
            ]);
        }

        // Assignment change (Admin only)
        if ($user->isAdmin() && array_key_exists('assigned_to', $validated) && $validated['assigned_to'] != $oldAssignee) {
            $lead->assigned_to = $validated['assigned_to'];
            $newAssignee = $lead->assigned_to ? User::find($lead->assigned_to) : null;
            $assigneeName = $newAssignee ? $newAssignee->name : 'Unassigned';

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'activity_type' => 'assignment_change',
                'title' => "Reassigned to {$assigneeName}",
                'description' => "Lead assignment changed by {$user->name}.",
                'metadata' => ['old_assigned_to' => $oldAssignee, 'new_assigned_to' => $lead->assigned_to],
            ]);

            if ($newAssignee && $newAssignee->id !== $user->id) {
                NotificationLog::create([
                    'lead_id' => $lead->id,
                    'user_id' => $newAssignee->id,
                    'event_type' => 'lead_reassigned',
                    'channel' => 'in_app',
                    'recipient' => $newAssignee->email,
                    'title' => 'Lead Reassigned to You',
                    'message' => "Lead {$lead->name} ({$lead->reference_id}) has been assigned to you.",
                    'status' => 'delivered',
                    'metadata' => [
                        'lead_id' => $lead->id,
                        'lead_reference_id' => $lead->reference_id,
                        'lead_name' => $lead->name,
                        'company_name' => $lead->company_name,
                        'phone' => $lead->phone,
                        'service_interest' => $lead->service_interest,
                        'popup_alert' => true,
                    ],
                    'is_read' => false,
                ]);
            }
        }

        $lead->save();

        $lead->load(['assignedEmployee:id,name,email,phone', 'creator:id,name', 'activities.user:id,name']);

        return response()->json([
            'message' => 'Lead updated successfully',
            'lead' => $lead,
        ]);
    }

    public function assign(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Only administrators can reassign leads.'], 403);
        }

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $oldAssignee = $lead->assigned_to;
        $lead->assigned_to = $validated['assigned_to'];
        if ($lead->status === 'new' && $lead->assigned_to) {
            $lead->status = 'assigned';
        }
        $lead->save();

        $newAssignee = $lead->assigned_to ? User::find($lead->assigned_to) : null;
        $assigneeName = $newAssignee ? $newAssignee->name : 'Unassigned';

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'activity_type' => 'assignment_change',
            'title' => "Assigned to {$assigneeName}",
            'description' => "Reassigned by {$user->name}",
            'metadata' => ['old_assigned_to' => $oldAssignee, 'new_assigned_to' => $lead->assigned_to],
        ]);

        if ($newAssignee) {
            NotificationLog::create([
                'lead_id' => $lead->id,
                'user_id' => $newAssignee->id,
                'event_type' => 'lead_reassigned',
                'channel' => 'in_app',
                'recipient' => $newAssignee->email,
                'title' => 'Lead Assigned',
                'message' => "Lead {$lead->name} ({$lead->reference_id}) has been assigned to you.",
                'status' => 'delivered',
                'metadata' => [
                    'lead_id' => $lead->id,
                    'lead_reference_id' => $lead->reference_id,
                    'lead_name' => $lead->name,
                    'company_name' => $lead->company_name,
                    'phone' => $lead->phone,
                    'service_interest' => $lead->service_interest,
                    'popup_alert' => true,
                ],
                'is_read' => false,
            ]);
        }

        $lead->load(['assignedEmployee:id,name,email,phone']);

        return response()->json([
            'message' => 'Lead reassigned successfully',
            'lead' => $lead,
        ]);
    }

    public function logCall(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && $lead->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized to log calls for this lead.'], 403);
        }

        $validated = $request->validate([
            'outcome' => 'required|string|in:Connected,No Answer,Busy,Callback Requested,Wrong Number,Interested,Not Interested,Meeting Scheduled',
            'notes' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
            'next_follow_up_date' => 'nullable|date',
            'update_status' => 'nullable|in:new,assigned,contacted,follow_up,interested,converted,lost,not_reachable',
        ]);

        $now = Carbon::now();
        $lead->last_contact_date = $now;

        if (!empty($validated['update_status'])) {
            $lead->status = $validated['update_status'];
        } elseif ($lead->status === 'new' || $lead->status === 'assigned') {
            $lead->status = 'contacted';
        }

        if (!empty($validated['next_follow_up_date'])) {
            $dueUtc = FollowUp::parseToUtc($validated['next_follow_up_date']);
            $lead->next_follow_up_date = $dueUtc;
            // Create follow-up
            FollowUp::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'scheduled_at' => $dueUtc,
                'status' => 'pending',
                'notes' => "Scheduled following call outcome: {$validated['outcome']}. " . ($validated['notes'] ?? ''),
            ]);
        }

        $lead->save();

        // Create Activity
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'activity_type' => 'call_outcome',
            'title' => "Call Logged: {$validated['outcome']}",
            'description' => $validated['notes'] ?? 'No additional call notes provided.',
            'metadata' => [
                'outcome' => $validated['outcome'],
                'duration_minutes' => $validated['duration_minutes'] ?? 0,
                'next_follow_up' => $validated['next_follow_up_date'] ?? null,
            ],
        ]);

        $lead->load(['activities.user:id,name', 'followUps.user:id,name']);

        return response()->json([
            'message' => 'Call outcome logged successfully',
            'lead' => $lead,
        ]);
    }

    public function addNote(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && $lead->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized to add notes for this lead.'], 403);
        }

        $validated = $request->validate([
            'note' => 'required|string|min:2',
        ]);

        $activity = LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'activity_type' => 'note',
            'title' => 'Internal Note Added',
            'description' => $validated['note'],
            'metadata' => [],
        ]);

        $activity->load('user:id,name');

        return response()->json([
            'message' => 'Note added successfully',
            'activity' => $activity,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        $query = Lead::accessibleBy($user)
            ->with(['assignedEmployee:id,name', 'creator:id,name'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }
        if ($user->isAdmin() && $request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        $filename = "vistabound_leads_" . date('Y_m_d_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Reference ID',
                'Lead Name',
                'Phone',
                'Normalized Phone',
                'Email',
                'Company Name',
                'Source',
                'Service Interest',
                'Status',
                'Priority',
                'Assigned Employee',
                'Created By',
                'Created Date',
                'Last Contact Date',
                'Next Follow-up Date',
                'Additional Details',
            ]);

            $query->chunk(200, function ($leads) use ($handle) {
                foreach ($leads as $lead) {
                    fputcsv($handle, [
                        $lead->reference_id,
                        $lead->name,
                        $lead->phone,
                        $lead->phone_normalized,
                        $lead->email ?? '',
                        $lead->company_name ?? '',
                        $lead->source,
                        $lead->service_interest ?? '',
                        ucfirst(str_replace('_', ' ', $lead->status)),
                        ucfirst($lead->priority),
                        $lead->assignedEmployee?->name ?? 'Unassigned',
                        $lead->creator?->name ?? 'System',
                        $lead->created_at->format('Y-m-d H:i:s'),
                        $lead->last_contact_date ? $lead->last_contact_date->format('Y-m-d H:i:s') : '',
                        $lead->next_follow_up_date ? $lead->next_follow_up_date->format('Y-m-d H:i:s') : '',
                        $lead->additional_details ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    public function destroy(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Only administrators can archive leads.'], 403);
        }

        // Cancel any pending follow-up reminders
        FollowUp::where('lead_id', $lead->id)
            ->where('status', 'pending')
            ->update(['reminder_status' => 'cancelled']);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'activity_type' => 'lead_archived',
            'title' => 'Lead Archived',
            'description' => "Lead {$lead->reference_id} ({$lead->name}) was archived by {$user->name}.",
            'metadata' => [
                'archived_at' => Carbon::now()->toIso8601String(),
                'archived_by' => $user->name,
            ],
        ]);

        $lead->delete(); // Soft delete

        return response()->json([
            'message' => "Lead {$lead->reference_id} ({$lead->name}) has been archived.",
            'lead_id' => $lead->id,
        ]);
    }

    public function restore(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Only administrators can restore leads.'], 403);
        }

        $lead = Lead::onlyTrashed()->findOrFail($id);
        $lead->restore();

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'activity_type' => 'lead_restored',
            'title' => 'Lead Restored',
            'description' => "Lead {$lead->reference_id} ({$lead->name}) was restored from archive by {$user->name}.",
            'metadata' => [
                'restored_at' => Carbon::now()->toIso8601String(),
                'restored_by' => $user->name,
            ],
        ]);

        $lead->load(['assignedEmployee:id,name,email,phone', 'creator:id,name']);

        return response()->json([
            'message' => "Lead {$lead->reference_id} ({$lead->name}) restored successfully.",
            'lead' => $lead,
        ]);
    }

    public function forceDelete(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Only administrators can permanently delete leads.'], 403);
        }

        $lead = Lead::onlyTrashed()->findOrFail($id);

        $refId = $lead->reference_id;
        $name = $lead->name;

        DB::transaction(function () use ($lead) {
            $lead->notificationLogs()->delete();
            $lead->followUps()->delete();
            $lead->activities()->delete();
            $lead->forceDelete();
        });

        return response()->json([
            'message' => "Lead {$refId} ({$name}) has been permanently deleted.",
            'deleted_id' => $id,
        ]);
    }
}
