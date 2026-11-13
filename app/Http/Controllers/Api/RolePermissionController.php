<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PermissionAuditLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    /**
     * List all available system roles with member count and permissions summary.
     */
    public function roles(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('roles.manage')) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $roles = Role::with(['permissions'])->orderBy('id', 'asc')->get()->map(function ($r) {
            return [
                'id' => $r->id,
                'name' => $r->name,
                'display_name' => $r->display_name,
                'description' => $r->description,
                'is_system' => (bool)$r->is_system,
                'visibility_scope' => $r->getVisibilityScope(),
                'allowed_categories' => $r->getAllowedCategories(),
                'permissions_count' => $r->permissions->count(),
                'permission_names' => $r->permissions->pluck('name')->toArray(),
                'users_count' => User::where('role_id', $r->id)->orWhere('role', $r->name)->count(),
                'created_at' => $r->created_at->format('Y-m-d'),
            ];
        });

        return response()->json($roles);
    }

    /**
     * List all permissions grouped by functional module.
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->hasPermission('roles.manage')) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $permissions = Permission::orderBy('group', 'asc')->orderBy('id', 'asc')->get();
        $grouped = $permissions->groupBy('group');

        return response()->json([
            'all' => $permissions,
            'grouped' => $grouped,
        ]);
    }

    /**
     * List all teams.
     */
    public function teams(Request $request): JsonResponse
    {
        $teams = Team::withCount('members')->orderBy('name', 'asc')->get();
        return response()->json($teams);
    }

    /**
     * Create a new custom role template.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor->isAdmin() && !$actor->hasPermission('roles.manage')) {
            return response()->json(['message' => 'Unauthorized to create roles.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:64|unique:roles,name|regex:/^[a-z0-9_]+$/',
            'display_name' => 'required|string|max:128',
            'description' => 'nullable|string',
            'visibility_scope' => ['required', Rule::in(['assigned_only', 'team_only', 'all'])],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
            'allowed_categories' => 'nullable|array',
            'allowed_categories.*' => 'string',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        $role->setVisibilityScope($validated['visibility_scope']);

        if (!empty($validated['permissions'])) {
            $permIds = Permission::whereIn('name', $validated['permissions'])->pluck('id')->toArray();
            $role->permissions()->sync($permIds);
        }

        if (array_key_exists('allowed_categories', $validated)) {
            $role->syncAllowedCategories($validated['allowed_categories'] ?: []);
        }

        PermissionAuditLog::record(
            $actor->id,
            'role',
            $role->id,
            'role_created',
            ['name' => $role->name, 'display_name' => $role->display_name]
        );

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => $role,
        ], 201);
    }

    /**
     * Update an existing role.
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor->isAdmin() && !$actor->hasPermission('roles.manage')) {
            return response()->json(['message' => 'Unauthorized to update roles.'], 403);
        }

        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'display_name' => 'required|string|max:128',
            'description' => 'nullable|string',
            'visibility_scope' => ['required', Rule::in(['assigned_only', 'team_only', 'all'])],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
            'allowed_categories' => 'nullable|array',
            'allowed_categories.*' => 'string',
        ]);

        $beforeState = [
            'display_name' => $role->display_name,
            'visibility_scope' => $role->getVisibilityScope(),
            'permissions' => $role->permissions->pluck('name')->toArray(),
            'allowed_categories' => $role->getAllowedCategories(),
        ];

        $role->display_name = $validated['display_name'];
        if (array_key_exists('description', $validated)) {
            $role->description = $validated['description'];
        }
        $role->save();

        $role->setVisibilityScope($validated['visibility_scope']);

        if (array_key_exists('permissions', $validated)) {
            $permIds = Permission::whereIn('name', $validated['permissions'] ?: [])->pluck('id')->toArray();
            $role->permissions()->sync($permIds);
        }

        if (array_key_exists('allowed_categories', $validated)) {
            $role->syncAllowedCategories($validated['allowed_categories'] ?: []);
        }

        PermissionAuditLog::record(
            $actor->id,
            'role',
            $role->id,
            'role_updated',
            [
                'before' => $beforeState,
                'after' => [
                    'display_name' => $role->display_name,
                    'visibility_scope' => $role->getVisibilityScope(),
                    'permissions' => $validated['permissions'] ?? [],
                    'allowed_categories' => $validated['allowed_categories'] ?? [],
                ],
            ]
        );

        return response()->json([
            'message' => 'Role updated successfully.',
            'role' => $role,
        ]);
    }

    /**
     * Delete a non-system role.
     */
    public function deleteRole(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor->isAdmin() && !$actor->hasPermission('roles.manage')) {
            return response()->json(['message' => 'Unauthorized to delete roles.'], 403);
        }

        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }

        $assignedCount = User::where('role_id', $role->id)->count();
        if ($assignedCount > 0) {
            return response()->json([
                'message' => "Cannot delete role while {$assignedCount} users are assigned to it. Reassign users first.",
            ], 422);
        }

        $roleName = $role->name;
        $role->delete();

        PermissionAuditLog::record(
            $actor->id,
            'role',
            $id,
            'role_deleted',
            ['name' => $roleName]
        );

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    /**
     * Retrieve user effective permissions, explicit overrides, visibility scope, and category assignments.
     */
    public function getUserPermissions(Request $request, int $userId): JsonResponse
    {
        $actor = $request->user();
        if (!$actor->isAdmin() && !$actor->hasPermission('roles.manage') && $actor->id !== $userId) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $targetUser = User::with(['roleRelation', 'team'])->findOrFail($userId);

        // Fetch user explicit overrides
        $overrides = DB::table('user_has_permissions')
            ->join('permissions', 'user_has_permissions.permission_id', '=', 'permissions.id')
            ->where('user_has_permissions.user_id', $targetUser->id)
            ->select('permissions.name as permission_name', 'user_has_permissions.effect')
            ->get()
            ->pluck('effect', 'permission_name')
            ->toArray();

        // Fetch user visibility override
        $visibilityOverride = DB::table('user_visibility_overrides')
            ->where('user_id', $targetUser->id)
            ->value('scope') ?: 'inherit';

        // Fetch user category assignments
        $catOverrides = DB::table('user_category_assignments')
            ->where('user_id', $targetUser->id)
            ->select('category_name', 'is_allowed')
            ->get();

        // Role permissions
        $rolePermissions = [];
        $roleScope = 'assigned_only';
        $roleCategories = [];

        if ($targetUser->roleRelation) {
            $rolePermissions = $targetUser->roleRelation->permissions->pluck('name')->toArray();
            $roleScope = $targetUser->roleRelation->getVisibilityScope();
            $roleCategories = $targetUser->roleRelation->getAllowedCategories();
        } elseif ($targetUser->role === 'admin') {
            $rolePermissions = Permission::pluck('name')->toArray();
            $roleScope = 'all';
        }

        $allPermissions = Permission::all();
        $permissionMatrix = $allPermissions->map(function ($perm) use ($rolePermissions, $overrides, $targetUser) {
            $inherited = in_array($perm->name, $rolePermissions);
            $override = $overrides[$perm->name] ?? null; // 'allow', 'deny', or null (inherit)

            $effective = $override === 'allow'
                ? true
                : ($override === 'deny' ? false : $inherited);

            if ($targetUser->isAdmin()) {
                $effective = true;
            }

            return [
                'name' => $perm->name,
                'display_name' => $perm->display_name,
                'group' => $perm->group,
                'description' => $perm->description,
                'role_default' => $inherited,
                'override' => $override ?: 'inherit', // 'inherit', 'allow', 'deny'
                'effective' => $effective,
            ];
        });

        return response()->json([
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetUser->role,
                'role_id' => $targetUser->role_id,
                'role_name' => $targetUser->roleRelation?->name,
                'role_display_name' => $targetUser->roleRelation?->display_name ?? ucfirst($targetUser->role),
                'team_id' => $targetUser->team_id,
                'team_name' => $targetUser->team?->name,
            ],
            'permissions_matrix' => $permissionMatrix,
            'visibility' => [
                'role_scope' => $roleScope,
                'user_override' => $visibilityOverride,
                'effective_scope' => $targetUser->getEffectiveVisibilityScope(),
            ],
            'categories' => [
                'role_categories' => $roleCategories,
                'user_overrides' => $catOverrides,
                'effective_categories' => $targetUser->getEffectiveAllowedCategories(),
            ],
        ]);
    }

    /**
     * Update individual user permission overrides (Inherit / Allow / Deny).
     */
    public function updateUserPermissions(Request $request, int $userId): JsonResponse
    {
        $actor = $request->user();
        if (!$actor->isAdmin() && !$actor->hasPermission('roles.manage')) {
            return response()->json(['message' => 'Unauthorized to configure permissions.'], 403);
        }

        $targetUser = User::findOrFail($userId);

        $validated = $request->validate([
            'overrides' => 'required|array',
            'overrides.*.permission' => 'required|string|exists:permissions,name',
            'overrides.*.effect' => ['required', Rule::in(['inherit', 'allow', 'deny'])],
        ]);

        $beforeState = DB::table('user_has_permissions')->where('user_id', $targetUser->id)->get();

        foreach ($validated['overrides'] as $item) {
            $perm = Permission::where('name', $item['permission'])->first();
            if (!$perm) continue;

            if ($item['effect'] === 'inherit') {
                DB::table('user_has_permissions')
                    ->where('user_id', $targetUser->id)
                    ->where('permission_id', $perm->id)
                    ->delete();
            } else {
                DB::table('user_has_permissions')->updateOrInsert(
                    [
                        'user_id' => $targetUser->id,
                        'permission_id' => $perm->id,
                    ],
                    [
                        'effect' => $item['effect'],
                        'granted_by' => $actor->id,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        PermissionAuditLog::record(
            $actor->id,
            'user',
            $targetUser->id,
            'user_permissions_updated',
            [
                'updated_count' => count($validated['overrides']),
                'overrides' => $validated['overrides'],
            ]
        );

        return response()->json([
            'message' => 'User permissions updated successfully.',
            'effective_permissions' => $targetUser->getEffectivePermissions(),
        ]);
    }

    /**
     * Update user visibility override.
     */
    public function updateUserVisibility(Request $request, int $userId): JsonResponse
    {
        $actor = $request->user();
        if (!$actor->isAdmin() && !$actor->hasPermission('roles.manage')) {
            return response()->json(['message' => 'Unauthorized to configure visibility.'], 403);
        }

        $targetUser = User::findOrFail($userId);

        $validated = $request->validate([
            'scope' => ['required', Rule::in(['inherit', 'assigned_only', 'team_only', 'all'])],
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validated['scope'] === 'inherit') {
            DB::table('user_visibility_overrides')->where('user_id', $targetUser->id)->delete();
        } else {
            DB::table('user_visibility_overrides')->updateOrInsert(
                ['user_id' => $targetUser->id],
                [
                    'scope' => $validated['scope'],
                    'reason' => $validated['reason'] ?? null,
                    'updated_by' => $actor->id,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        PermissionAuditLog::record(
            $actor->id,
            'user',
            $targetUser->id,
            'visibility_scope_updated',
            ['scope' => $validated['scope'], 'reason' => $validated['reason'] ?? null]
        );

        return response()->json([
            'message' => 'User visibility scope updated successfully.',
            'effective_scope' => $targetUser->getEffectiveVisibilityScope(),
        ]);
    }

    /**
     * Update user category restrictions.
     */
    public function updateUserCategories(Request $request, int $userId): JsonResponse
    {
        $actor = $request->user();
        if (!$actor->isAdmin() && !$actor->hasPermission('roles.manage')) {
            return response()->json(['message' => 'Unauthorized to configure category assignments.'], 403);
        }

        $targetUser = User::findOrFail($userId);

        $validated = $request->validate([
            'allowed_categories' => 'nullable|array',
            'allowed_categories.*' => 'string',
        ]);

        DB::table('user_category_assignments')->where('user_id', $targetUser->id)->delete();

        if (!empty($validated['allowed_categories'])) {
            $rows = array_map(function ($cat) use ($targetUser) {
                return [
                    'user_id' => $targetUser->id,
                    'category_name' => $cat,
                    'is_allowed' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $validated['allowed_categories']);

            DB::table('user_category_assignments')->insert($rows);
        }

        PermissionAuditLog::record(
            $actor->id,
            'user',
            $targetUser->id,
            'category_assignments_updated',
            ['allowed_categories' => $validated['allowed_categories'] ?? []]
        );

        return response()->json([
            'message' => 'User category restrictions updated.',
            'effective_allowed_categories' => $targetUser->getEffectiveAllowedCategories(),
        ]);
    }

    /**
     * Reset user to clean role defaults (removes all individual overrides).
     */
    public function resetUserPermissions(Request $request, int $userId): JsonResponse
    {
        $actor = $request->user();
        if (!$actor->isAdmin() && !$actor->hasPermission('roles.manage')) {
            return response()->json(['message' => 'Unauthorized to reset permissions.'], 403);
        }

        $targetUser = User::findOrFail($userId);

        DB::table('user_has_permissions')->where('user_id', $targetUser->id)->delete();
        DB::table('user_visibility_overrides')->where('user_id', $targetUser->id)->delete();
        DB::table('user_category_assignments')->where('user_id', $targetUser->id)->delete();

        PermissionAuditLog::record(
            $actor->id,
            'user',
            $targetUser->id,
            'permissions_reset_to_role_default',
            ['role' => $targetUser->roleRelation?->name ?? $targetUser->role]
        );

        return response()->json([
            'message' => 'User permissions and visibility reset to role defaults.',
            'effective_permissions' => $targetUser->getEffectivePermissions(),
            'effective_scope' => $targetUser->getEffectiveVisibilityScope(),
        ]);
    }

    /**
     * List audit logs with pagination.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor->isAdmin() && !$actor->hasPermission('roles.manage')) {
            return response()->json(['message' => 'Unauthorized to view audit logs.'], 403);
        }

        $logs = PermissionAuditLog::with('actor:id,name,email')
            ->orderBy('id', 'desc')
            ->paginate(50);

        return response()->json($logs);
    }
}
