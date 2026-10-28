<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = NotificationLog::where('user_id', $user->id)
            ->with(['lead:id,reference_id,name,company_name,phone', 'followUp:id,scheduled_at,notes'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $unreadCount = NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        $nowUtc = Carbon::now('UTC');
        $nowIst = Carbon::now('Asia/Kolkata');

        // 1. Unread count for topbar badge
        $unreadCount = NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        // 2. Active in-app alerts (unacknowledged/unread)
        $activeAlerts = NotificationLog::where('user_id', $user->id)
            ->where('channel', 'in_app')
            ->where('is_read', false)
            ->with([
                'lead:id,reference_id,name,phone,company_name,service_interest,status,assigned_to',
                'followUp:id,lead_id,scheduled_at,notes,status',
            ])
            ->latest()
            ->take(15)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'event_type' => $item->event_type ?? ($item->follow_up_id ? 'follow_up_reminder' : 'new_lead'),
                    'title' => $item->title,
                    'message' => $item->message,
                    'lead_id' => $item->lead_id,
                    'follow_up_id' => $item->follow_up_id,
                    'lead' => $item->lead,
                    'follow_up' => $item->followUp ? [
                        'id' => $item->followUp->id,
                        'scheduled_at' => $item->followUp->scheduled_at,
                        'scheduled_at_ist' => $item->followUp->scheduled_at_ist,
                        'notes' => $item->followUp->notes,
                        'status' => $item->followUp->status,
                    ] : null,
                    'metadata' => $item->metadata,
                    'created_at_ist' => $item->created_at?->setTimezone('Asia/Kolkata')->format('d M, h:i A'),
                ];
            });

        // 3. Follow-up summary counts for sidebar badge and live counter updates
        $fuQuery = FollowUp::query();
        if (!$user->isAdmin()) {
            $fuQuery->where('user_id', $user->id);
        }

        $endOfTodayUtc = Carbon::today('Asia/Kolkata')->endOfDay()->utc();

        $followUpCounts = [
            'overdue' => (clone $fuQuery)->where('status', 'pending')->where('scheduled_at', '<', $nowUtc)->count(),
            'today' => (clone $fuQuery)->where('status', 'pending')->whereBetween('scheduled_at', [$nowUtc, $endOfTodayUtc])->count(),
            'upcoming' => (clone $fuQuery)->where('status', 'pending')->where('scheduled_at', '>', $endOfTodayUtc)->count(),
            'total_pending' => (clone $fuQuery)->where('status', 'pending')->count(),
        ];

        // 4. Latest lead ID for live pipeline refresh
        $latestLeadId = Lead::accessibleBy($user)->max('id') ?? 0;

        return response()->json([
            'unread_count' => $unreadCount,
            'active_alerts' => $activeAlerts,
            'follow_up_counts' => $followUpCounts,
            'latest_lead_id' => $latestLeadId,
            'server_time' => [
                'utc' => $nowUtc->toIso8601String(),
                'ist' => $nowIst->format('d M Y, h:i:s A'),
            ],
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->with(['lead:id,reference_id,name,company_name,phone', 'followUp:id,scheduled_at,notes'])
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'count' => $notifications->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $unreadCount = NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json(['unread_count' => $unreadCount]);
    }

    public function markAsRead(Request $request, NotificationLog $notification): JsonResponse
    {
        $user = $request->user();
        if ($notification->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $notification->is_read = true;
        $notification->save();

        return response()->json(['message' => 'Notification marked as read.', 'notification' => $notification]);
    }

    public function dismiss(Request $request, NotificationLog $notification): JsonResponse
    {
        return $this->markAsRead($request, $notification);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        NotificationLog::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function deliveryLogs(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = NotificationLog::with([
            'lead:id,reference_id,name,phone',
            'user:id,name,email',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('recipient', 'like', "%{$term}%")
                  ->orWhere('title', 'like', "%{$term}%")
                  ->orWhere('message', 'like', "%{$term}%");
            });
        }

        $logs = $query->paginate(25);

        return response()->json($logs);
    }
}
