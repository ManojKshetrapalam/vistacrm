<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = FollowUp::with([
            'lead:id,reference_id,name,phone,phone_normalized,email,company_name,service_interest,status,priority,assigned_to',
            'user:id,name,email',
        ]);

        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $tab = $request->input('tab', 'today');
        $now = Carbon::now();

        if ($tab === 'today') {
            $query->where('status', 'pending')
                  ->whereDate('scheduled_at', Carbon::today())
                  ->orderBy('scheduled_at', 'asc');
        } elseif ($tab === 'overdue') {
            $query->where('status', 'pending')
                  ->where('scheduled_at', '<', $now)
                  ->orderBy('scheduled_at', 'asc');
        } elseif ($tab === 'upcoming') {
            $query->where('status', 'pending')
                  ->where('scheduled_at', '>', Carbon::today()->endOfDay())
                  ->orderBy('scheduled_at', 'asc');
        } elseif ($tab === 'completed') {
            $query->where('status', 'completed')
                  ->orderBy('completed_at', 'desc');
        } else {
            $query->orderBy('scheduled_at', 'desc');
        }

        $perPage = min(100, max(1, (int)$request->input('per_page', 25)));
        $followUps = $query->paginate($perPage);

        // Also return counts for the tabs
        $countsQuery = FollowUp::query();
        if (!$user->isAdmin()) {
            $countsQuery->where('user_id', $user->id);
        }

        $counts = [
            'today' => (clone $countsQuery)->where('status', 'pending')->whereDate('scheduled_at', Carbon::today())->count(),
            'overdue' => (clone $countsQuery)->where('status', 'pending')->where('scheduled_at', '<', $now)->count(),
            'upcoming' => (clone $countsQuery)->where('status', 'pending')->where('scheduled_at', '>', Carbon::today()->endOfDay())->count(),
            'completed' => (clone $countsQuery)->where('status', 'completed')->count(),
        ];

        return response()->json([
            'data' => $followUps->items(),
            'pagination' => [
                'current_page' => $followUps->currentPage(),
                'last_page' => $followUps->lastPage(),
                'per_page' => $followUps->perPage(),
                'total' => $followUps->total(),
            ],
            'counts' => $counts,
        ]);
    }

    public function store(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && $lead->assigned_to !== $user->id) {
            return response()->json(['message' => 'Unauthorized to schedule follow-ups for this lead.'], 403);
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $assigneeId = $user->isAdmin() ? ($validated['user_id'] ?? $lead->assigned_to ?? $user->id) : $user->id;

        $followUp = FollowUp::create([
            'lead_id' => $lead->id,
            'user_id' => $assigneeId,
            'scheduled_at' => $validated['scheduled_at'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        $lead->next_follow_up_date = $validated['scheduled_at'];
        if ($lead->status === 'new' || $lead->status === 'assigned') {
            $lead->status = 'follow_up';
        }
        $lead->save();

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'activity_type' => 'follow_up_scheduled',
            'title' => 'Follow-up Scheduled',
            'description' => "Scheduled for " . Carbon::parse($validated['scheduled_at'])->format('d M Y, h:i A') . ". " . ($validated['notes'] ?? ''),
            'metadata' => [
                'follow_up_id' => $followUp->id,
                'scheduled_at' => $validated['scheduled_at'],
            ],
        ]);

        $followUp->load(['lead:id,reference_id,name,phone,company_name', 'user:id,name']);

        return response()->json([
            'message' => 'Follow-up scheduled successfully',
            'follow_up' => $followUp,
        ], 201);
    }

    public function update(Request $request, FollowUp $followUp): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && $followUp->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized to modify this follow-up.'], 403);
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:pending,snoozed,cancelled',
        ]);

        $followUp->scheduled_at = $validated['scheduled_at'];
        if (array_key_exists('notes', $validated)) {
            $followUp->notes = $validated['notes'];
        }
        if (!empty($validated['status'])) {
            $followUp->status = $validated['status'];
        }
        $followUp->save();

        // Update lead's next follow up date
        $lead = $followUp->lead;
        if ($lead) {
            $lead->next_follow_up_date = $validated['scheduled_at'];
            $lead->save();

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'activity_type' => 'follow_up_scheduled',
                'title' => 'Follow-up Rescheduled',
                'description' => "Rescheduled to " . Carbon::parse($validated['scheduled_at'])->format('d M Y, h:i A'),
                'metadata' => [
                    'follow_up_id' => $followUp->id,
                    'rescheduled_at' => $validated['scheduled_at'],
                ],
            ]);
        }

        $followUp->load(['lead:id,reference_id,name,phone,company_name', 'user:id,name']);

        return response()->json([
            'message' => 'Follow-up updated successfully',
            'follow_up' => $followUp,
        ]);
    }

    public function complete(Request $request, FollowUp $followUp): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && $followUp->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized to complete this follow-up.'], 403);
        }

        $validated = $request->validate([
            'outcome' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'next_follow_up_date' => 'nullable|date',
            'lead_status' => 'nullable|in:new,assigned,contacted,follow_up,interested,converted,lost,not_reachable',
        ]);

        $now = Carbon::now();
        $followUp->status = 'completed';
        $followUp->completed_at = $now;
        $followUp->outcome = $validated['outcome'];
        if (!empty($validated['notes'])) {
            $followUp->notes = ($followUp->notes ? $followUp->notes . "\n\nResolution: " : "") . $validated['notes'];
        }
        $followUp->save();

        $lead = $followUp->lead;
        if ($lead) {
            $lead->last_contact_date = $now;
            if (!empty($validated['lead_status'])) {
                $lead->status = $validated['lead_status'];
            }

            if (!empty($validated['next_follow_up_date'])) {
                $lead->next_follow_up_date = $validated['next_follow_up_date'];
                // Schedule the next follow-up
                FollowUp::create([
                    'lead_id' => $lead->id,
                    'user_id' => $followUp->user_id,
                    'scheduled_at' => $validated['next_follow_up_date'],
                    'status' => 'pending',
                    'notes' => "Next follow-up following: {$validated['outcome']}",
                ]);
            } else {
                $lead->next_follow_up_date = null;
            }
            $lead->save();

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'activity_type' => 'follow_up_completed',
                'title' => "Follow-up Completed: {$validated['outcome']}",
                'description' => $validated['notes'] ?? 'Follow-up marked as completed.',
                'metadata' => [
                    'follow_up_id' => $followUp->id,
                    'outcome' => $validated['outcome'],
                ],
            ]);
        }

        return response()->json([
            'message' => 'Follow-up marked as completed',
            'follow_up' => $followUp,
        ]);
    }
}
