<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWebDeskRbacSession;
use App\Models\DeviceToken;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\PermissionAuditLog;
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
        if (!$user->isAdmin() && !$user->hasPermission('employees.manage')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $now = Carbon::now();
        $employees = User::with(['roleRelation', 'team'])
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
                    'role_id' => $emp->role_id,
                    'role_name' => $emp->roleRelation?->name ?? $emp->role,
                    'role_display_name' => $emp->roleRelation?->display_name ?? ucfirst($emp->role),
                    'team_id' => $emp->team_id,
                    'team_name' => $emp->team?->name,
                    'is_active' => (bool)$emp->is_active,
                    'created_at' => $emp->created_at->format('Y-m-d'),
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
        if (!$user->isAdmin() && !$user->hasPermission('employees.manage')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role' => 'nullable|in:admin,employee',
            'role_id' => 'nullable|exists:roles,id',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        $submittedRoleId = (array_key_exists('role_id', $validated) && $validated['role_id'] !== null && $validated['role_id'] !== '')
            ? (int) $validated['role_id']
            : null;
        $submittedRole = (array_key_exists('role', $validated) && !empty($validated['role']))
            ? trim((string) $validated['role'])
            : null;
        $submittedTeamId = (array_key_exists('team_id', $validated) && $validated['team_id'] !== null && $validated['team_id'] !== '')
            ? (int) $validated['team_id']
            : null;

        // Resolve role template from DB
        $roleModel = null;
        if ($submittedRoleId !== null) {
            $roleModel = \App\Models\Role::find($submittedRoleId);
        } elseif ($submittedRole !== null) {
            $roleModel = \App\Models\Role::where('name', $submittedRole)->first();
        }

        $effectiveRoleString = 'employee';
        $effectiveRoleId = null;
        if ($roleModel) {
            $effectiveRoleId = $roleModel->id;
            $effectiveRoleString = $roleModel->name === 'admin' ? 'admin' : 'employee';
        } elseif ($submittedRole === 'admin') {
            $effectiveRoleString = 'admin';
        }

        // Determine if privileged role or team assignment is being attempted
        $isPrivilegedRole = ($effectiveRoleId !== null) || ($effectiveRoleString === 'admin');
        $isTeamAssigned = ($submittedTeamId !== null);

        if ($isPrivilegedRole || $isTeamAssigned) {
            // 1. Require authentic Web Desk session
            if (!EnsureWebDeskRbacSession::isWebDeskSession($request)) {
                return response()->json([
                    'message' => 'Web-only authorization administration: Assigning employee roles or teams requires an active Web Desk browser session.',
                    'error_code' => 'MOBILE_RBAC_FORBIDDEN',
                ], 403);
            }

            // 2. Require administrator or 'roles.manage' permission
            if (!$user->isAdmin() && !$user->hasPermission('roles.manage')) {
                return response()->json([
                    'message' => 'Unauthorized. You lack permission to assign employee roles or teams.',
                    'error_code' => 'INSUFFICIENT_PERMISSIONS',
                ], 403);
            }
        }

        $employee = DB::transaction(function () use ($validated, $user, $effectiveRoleString, $effectiveRoleId, $submittedTeamId, $request) {
            $emp = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => $effectiveRoleString,
                'role_id' => $effectiveRoleId,
                'team_id' => $submittedTeamId,
                'is_active' => true,
                'notification_preferences' => [
                    'whatsapp_enabled' => true,
                    'email_enabled' => true,
                    'in_app_enabled' => true,
                    'phone_for_notifications' => $validated['phone'] ?? null,
                ],
            ]);

            PermissionAuditLog::record(
                $user->id,
                'user',
                $emp->id,
                'employee_created',
                [
                    'role' => $emp->role,
                    'role_id' => $emp->role_id,
                    'team_id' => $emp->team_id,
                ],
                $request->ip()
            );

            return $emp;
        });

        return response()->json([
            'message' => 'Employee account created successfully',
            'employee' => $employee,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isAdmin() && !$currentUser->hasPermission('employees.manage')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'role' => 'nullable|in:admin,employee',
            'role_id' => 'nullable|exists:roles,id',
            'team_id' => 'nullable|exists:teams,id',
            'is_active' => 'sometimes|required|boolean',
        ]);

        if (isset($validated['is_active']) && $user->id === $currentUser->id && !$validated['is_active']) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $currentRoleId = $user->role_id !== null ? (int) $user->role_id : null;
        $currentRole = !empty($user->role) ? trim((string) $user->role) : 'employee';
        $currentTeamId = $user->team_id !== null ? (int) $user->team_id : null;

        // Normalize submitted values
        $newRoleId = $currentRoleId;
        $newRole = $currentRole;
        $roleIdExplicitlySubmitted = array_key_exists('role_id', $validated);
        $roleExplicitlySubmitted = array_key_exists('role', $validated);

        if ($roleIdExplicitlySubmitted) {
            $submittedRoleId = ($validated['role_id'] !== null && $validated['role_id'] !== '')
                ? (int) $validated['role_id']
                : null;
            $newRoleId = $submittedRoleId;

            if ($submittedRoleId !== null) {
                $roleModel = \App\Models\Role::find($submittedRoleId);
                if ($roleModel) {
                    $newRole = $roleModel->name === 'admin' ? 'admin' : 'employee';
                }
            } else {
                // Explicit role removal reverts to base employee role
                $newRole = 'employee';
            }
        } elseif ($roleExplicitlySubmitted) {
            $submittedRole = !empty($validated['role']) ? trim((string) $validated['role']) : 'employee';
            $newRole = $submittedRole;
            $roleModel = \App\Models\Role::where('name', $submittedRole)->first();
            $newRoleId = $roleModel?->id;
        }

        $teamExplicitlySubmitted = array_key_exists('team_id', $validated);
        $newTeamId = $currentTeamId;
        if ($teamExplicitlySubmitted) {
            $newTeamId = ($validated['team_id'] !== null && $validated['team_id'] !== '')
                ? (int) $validated['team_id']
                : null;
        }

        // Detect actual authorization-affecting changes
        $roleChanging = ($newRoleId !== $currentRoleId) || ($newRole !== $currentRole);
        $teamChanging = ($newTeamId !== $currentTeamId);

        if ($roleChanging || $teamChanging) {
            // 1. Require authentic Web Desk session
            if (!EnsureWebDeskRbacSession::isWebDeskSession($request)) {
                return response()->json([
                    'message' => 'Web-only authorization administration: Reassigning employee roles or teams requires an active Web Desk browser session.',
                    'error_code' => 'MOBILE_RBAC_FORBIDDEN',
                ], 403);
            }

            // 2. Require administrator or 'roles.manage' permission
            if (!$currentUser->isAdmin() && !$currentUser->hasPermission('roles.manage')) {
                return response()->json([
                    'message' => 'Unauthorized. You lack permission to reassign employee roles or teams.',
                    'error_code' => 'INSUFFICIENT_PERMISSIONS',
                ], 403);
            }
        }

        DB::transaction(function () use (
            $user, $validated, $currentUser, $request,
            $currentRoleId, $newRoleId, $currentRole, $newRole, $currentTeamId, $newTeamId,
            $roleChanging, $teamChanging
        ) {
            if (!empty($validated['name'])) {
                $user->name = $validated['name'];
            }
            if (!empty($validated['email'])) {
                $user->email = $validated['email'];
            }
            if (array_key_exists('phone', $validated)) {
                $user->phone = $validated['phone'];
            }
            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->role_id = $newRoleId;
            $user->role = $newRole;
            $user->team_id = $newTeamId;

            if (isset($validated['is_active'])) {
                $user->is_active = $validated['is_active'];
            }

            $user->save();

            // Record audit logs for actual changes in same transaction
            if ($roleChanging) {
                PermissionAuditLog::record(
                    $currentUser->id,
                    'user',
                    $user->id,
                    'employee_role_changed',
                    [
                        'old_role' => $currentRole,
                        'new_role' => $newRole,
                        'old_role_id' => $currentRoleId,
                        'new_role_id' => $newRoleId,
                    ],
                    $request->ip()
                );
            }

            if ($teamChanging) {
                PermissionAuditLog::record(
                    $currentUser->id,
                    'user',
                    $user->id,
                    'employee_team_changed',
                    [
                        'old_team_id' => $currentTeamId,
                        'new_team_id' => $newTeamId,
                    ],
                    $request->ip()
                );
            }
        });

        return response()->json([
            'message' => 'Employee updated successfully',
            'employee' => $user->fresh(['roleRelation', 'team']),
        ]);
    }

    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isAdmin() && !$currentUser->hasPermission('employees.manage')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Prevent deactivating the last active administrator
        if ($user->isAdmin() && $user->is_active) {
            $activeAdminsCount = User::where('role', 'admin')->where('is_active', true)->count();
            if ($activeAdminsCount <= 1) {
                return response()->json(['message' => 'Cannot deactivate the last active administrator.'], 422);
            }
        }

        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $willDeactivate = $user->is_active;

        if ($willDeactivate) {
            // Invalidate active personal access tokens
            $user->tokens()->delete();

            // Invalidate mobile push device tokens
            DeviceToken::where('user_id', $user->id)->delete();
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'message' => "Account for {$user->name} " . ($user->is_active ? "reactivated" : "deactivated") . " successfully.",
            'is_active' => $user->is_active,
            'user' => $user,
        ]);
    }

    public function assignedLeads(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isAdmin() && !$currentUser->hasPermission('employees.manage')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $leads = Lead::where('assigned_to', $user->id)
            ->select(['id', 'reference_id', 'name', 'company_name', 'status', 'priority', 'next_follow_up_date'])
            ->get();

        return response()->json([
            'count' => $leads->count(),
            'leads' => $leads,
        ]);
    }

    public function reassignLeads(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isAdmin() && !$currentUser->hasPermission('employees.manage') && !$currentUser->hasPermission('leads.assign')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'new_user_id' => 'required|exists:users,id',
        ]);

        $newAssignee = User::findOrFail($validated['new_user_id']);
        if (!$newAssignee->is_active) {
            return response()->json(['message' => 'Target assignee must be an active user.'], 422);
        }

        $affectedCount = Lead::where('assigned_to', $user->id)->count();

        DB::transaction(function () use ($user, $newAssignee, $currentUser) {
            $leads = Lead::where('assigned_to', $user->id)->get();
            foreach ($leads as $lead) {
                $lead->assigned_to = $newAssignee->id;
                $lead->save();

                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $currentUser->id,
                    'activity_type' => 'assignment_change',
                    'title' => "Reassigned to {$newAssignee->name}",
                    'description' => "Account reassigned from {$user->name} to {$newAssignee->name} by {$currentUser->name}.",
                ]);
            }
        });

        return response()->json([
            'message' => "Successfully reassigned {$affectedCount} leads to {$newAssignee->name}.",
            'reassigned_count' => $affectedCount,
        ]);
    }
}
