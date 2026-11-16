<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $now = Carbon::now();
        $employees = User::query()
            ->orderBy('role', 'asc')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($emp) use ($now) {
                $assignedLeadsCount = Lead::where('assigned_to', $emp->id)->count();
                $convertedCount = Lead::where('assigned_to', $emp->id)->where('status', 'converted')->count();
                $dueTodayCount = FollowUp::where('user_id', $emp->id)
                    ->where('status', 'pending')
                    ->whereDate('scheduled_at', Carbon::today())
                    ->count();
                $overdueCount = FollowUp::where('user_id', $emp->id)
                    ->where('status', 'pending')
                    ->where('scheduled_at', '<', $now)
                    ->count();

                return [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'email' => $emp->email,
                    'phone' => $emp->phone,
                    'role' => $emp->role,
                    'role_name' => $emp->role,
                    'role_display_name' => $emp->role === 'admin' ? 'Administrator' : ($emp->role === 'team_lead' ? 'Team Lead' : 'Employee'),
                    'is_active' => (bool)$emp->is_active,
                    'allowed_sources' => $emp->allowed_sources ?? [],
                    'allowed_services' => $emp->allowed_services ?? [],
                    'team_members' => $emp->team_members ?? [],
                    'assignable_users' => $emp->assignable_users ?? [],
                    'created_at' => $emp->created_at ? $emp->created_at->format('Y-m-d') : null,
                    'stats' => [
                        'assigned_leads' => $assignedLeadsCount,
                        'converted_leads' => $convertedCount,
                        'due_today' => $dueTodayCount,
                        'overdue' => $overdueCount,
                    ],
                ];
            });

        return response()->json($employees);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,team_lead,employee',
            'allowed_sources' => 'nullable|array',
            'allowed_services' => 'nullable|array',
            'team_members' => 'nullable|array',
            'assignable_users' => 'nullable|array',
        ]);

        if (in_array($validated['role'], ['employee', 'team_lead'], true)) {
            if (empty($validated['allowed_sources']) || !is_array($validated['allowed_sources']) || count($validated['allowed_sources']) === 0) {
                return response()->json(['message' => 'Allowed Lead Sources is mandatory. Please select at least one source.'], 422);
            }
        }

        if ($validated['role'] === 'team_lead') {
            if (empty($validated['team_members']) || !is_array($validated['team_members']) || count($validated['team_members']) === 0) {
                return response()->json(['message' => 'Supervised Team Members is mandatory for Team Leads.'], 422);
            }
        }

        $employee = User::create([
            'name' => $validated['name'],
            'username' => explode('@', $validated['email'])[0],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
            'must_change_password' => false,
            'allowed_sources' => $validated['allowed_sources'] ?? [],
            'allowed_services' => $validated['allowed_services'] ?? [],
            'team_members' => $validated['team_members'] ?? [],
            'assignable_users' => !empty($validated['assignable_users']) ? $validated['assignable_users'] : ($validated['team_members'] ?? []),
        ]);

        return response()->json([
            'message' => 'Employee account created successfully.',
            'employee' => $employee,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'role' => 'nullable|in:admin,team_lead,employee',
            'is_active' => 'sometimes|required|boolean',
            'allowed_sources' => 'nullable|array',
            'allowed_services' => 'nullable|array',
            'team_members' => 'nullable|array',
            'assignable_users' => 'nullable|array',
        ]);

        // Invariant: cannot deactivate self
        if (isset($validated['is_active']) && $user->id === $currentUser->id && !$validated['is_active']) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        // Invariant: cannot deactivate last admin
        if (isset($validated['is_active']) && !$validated['is_active'] && $user->isAdmin()) {
            $activeAdmins = User::where('role', 'admin')->where('is_active', true)->count();
            if ($activeAdmins <= 1) {
                return response()->json(['message' => 'Cannot deactivate the last active administrator.'], 422);
            }
        }

        // If deactivating, purge personal access tokens & device tokens
        if (isset($validated['is_active']) && !$validated['is_active']) {
            $user->tokens()->delete();
            $user->deviceTokens()->delete();
        }

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }
        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }
        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        if (array_key_exists('role', $validated) && !empty($validated['role'])) {
            $user->role = $validated['role'];
        }
        if (array_key_exists('is_active', $validated)) {
            $user->is_active = (bool)$validated['is_active'];
        }
        if (array_key_exists('allowed_sources', $validated)) {
            $user->allowed_sources = $validated['allowed_sources'];
        }
        if (array_key_exists('allowed_services', $validated)) {
            $user->allowed_services = $validated['allowed_services'];
        }
        if (array_key_exists('team_members', $validated)) {
            $user->team_members = $validated['team_members'];
        }
        if (array_key_exists('assignable_users', $validated)) {
            $user->assignable_users = $validated['assignable_users'];
        }

        $user->save();

        return response()->json([
            'message' => 'Employee account updated successfully.',
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'role_name' => $user->role,
                'role_display_name' => $user->role === 'admin' ? 'Administrator' : ($user->role === 'team_lead' ? 'Team Lead' : 'Employee'),
                'is_active' => (bool)$user->is_active,
                'allowed_sources' => $user->allowed_sources ?? [],
                'allowed_services' => $user->allowed_services ?? [],
                'team_members' => $user->team_members ?? [],
                'assignable_users' => $user->assignable_users ?? [],
            ],
        ]);
    }

    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        if ($user->is_active && $user->isAdmin()) {
            $activeAdmins = User::where('role', 'admin')->where('is_active', true)->count();
            if ($activeAdmins <= 1) {
                return response()->json(['message' => 'Cannot deactivate the last active administrator.'], 422);
            }
        }

        $user->is_active = !$user->is_active;

        if (!$user->is_active) {
            $user->tokens()->delete();
            $user->deviceTokens()->delete();
        }

        $user->save();

        return response()->json([
            'message' => $user->is_active ? 'Account activated successfully.' : 'Account deactivated successfully.',
            'is_active' => (bool)$user->is_active,
        ]);
    }

    public function assignedLeads(User $user): JsonResponse
    {
        $leads = Lead::where('assigned_to', $user->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'reference_id', 'name', 'phone', 'email', 'company_name', 'status', 'priority', 'source', 'service_interest', 'created_at']);

        return response()->json($leads);
    }

    public function reassignLeads(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'target_user_id' => 'required|exists:users,id',
            'lead_ids' => 'nullable|array',
            'lead_ids.*' => 'exists:leads,id',
        ]);

        $targetUser = User::findOrFail($validated['target_user_id']);
        if (!$targetUser->is_active) {
            return response()->json(['message' => 'Target assignee must be an active employee.'], 422);
        }

        $query = Lead::where('assigned_to', $user->id);
        if (!empty($validated['lead_ids'])) {
            $query->whereIn('id', $validated['lead_ids']);
        }

        $leadsToReassign = $query->get();
        $count = $leadsToReassign->count();

        if ($count === 0) {
            return response()->json(['message' => 'No matching leads found to reassign.', 'reassigned_count' => 0]);
        }

        DB::transaction(function () use ($leadsToReassign, $targetUser, $currentUser, $user) {
            $now = Carbon::now();
            foreach ($leadsToReassign as $lead) {
                $lead->assigned_to = $targetUser->id;
                $lead->version = ($lead->version ?? 1) + 1;
                $lead->save();

                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $currentUser->id,
                    'type' => 'assignment_changed',
                    'description' => "Lead reassigned from {$user->name} to {$targetUser->name} by {$currentUser->name}.",
                    'metadata' => [
                        'from_user_id' => $user->id,
                        'from_user_name' => $user->name,
                        'to_user_id' => $targetUser->id,
                        'to_user_name' => $targetUser->name,
                        'reassigned_by' => $currentUser->name,
                    ],
                    'created_at' => $now,
                ]);
            }
        });

        return response()->json([
            'message' => "Successfully reassigned {$count} lead(s) to {$targetUser->name}.",
            'reassigned_count' => $count,
        ]);
    }
}
