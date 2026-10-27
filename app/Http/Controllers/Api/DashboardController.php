<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $now = Carbon::now();
        $today = Carbon::today();

        $leadsBase = Lead::accessibleBy($user);
        $totalLeads = (clone $leadsBase)->count();
        $newToday = (clone $leadsBase)->whereDate('created_at', $today)->count();
        $convertedLeads = (clone $leadsBase)->where('status', 'converted')->count();
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

        $followUpsBase = FollowUp::query();
        if (!$user->isAdmin()) {
            $followUpsBase->where('user_id', $user->id);
        }

        $dueToday = (clone $followUpsBase)
            ->where('status', 'pending')
            ->whereDate('scheduled_at', $today)
            ->count();

        $overdue = (clone $followUpsBase)
            ->where('status', 'pending')
            ->where('scheduled_at', '<', $now)
            ->count();

        // Status breakdown
        $statusBreakdown = (clone $leadsBase)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Source breakdown
        $sourceBreakdown = (clone $leadsBase)
            ->select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();

        // Recent activities
        $activitiesQuery = LeadActivity::with(['lead:id,reference_id,name', 'user:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(10);

        if (!$user->isAdmin()) {
            $activitiesQuery->whereHas('lead', function ($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }
        $recentActivities = $activitiesQuery->get();

        // Employee performance breakdown (Admin only)
        $employeeStats = [];
        if ($user->isAdmin()) {
            $employees = User::where('role', 'employee')->where('is_active', true)->get();
            foreach ($employees as $emp) {
                $empLeads = Lead::where('assigned_to', $emp->id)->count();
                $empConverted = Lead::where('assigned_to', $emp->id)->where('status', 'converted')->count();
                $empDue = FollowUp::where('user_id', $emp->id)->where('status', 'pending')->whereDate('scheduled_at', $today)->count();
                $empOverdue = FollowUp::where('user_id', $emp->id)->where('status', 'pending')->where('scheduled_at', '<', $now)->count();

                $employeeStats[] = [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'email' => $emp->email,
                    'assigned_leads' => $empLeads,
                    'converted_leads' => $empConverted,
                    'conversion_rate' => $empLeads > 0 ? round(($empConverted / $empLeads) * 100, 1) : 0,
                    'due_today' => $empDue,
                    'overdue' => $empOverdue,
                ];
            }
        }

        return response()->json([
            'summary' => [
                'total_leads' => $totalLeads,
                'new_today' => $newToday,
                'converted_leads' => $convertedLeads,
                'conversion_rate' => $conversionRate,
                'due_today' => $dueToday,
                'overdue' => $overdue,
            ],
            'status_breakdown' => $statusBreakdown,
            'source_breakdown' => $sourceBreakdown,
            'recent_activities' => $recentActivities,
            'employee_stats' => $employeeStats,
        ]);
    }
}
