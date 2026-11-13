<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run idempotent data migration to safely initialize baseline permissions,
     * baseline teams, system roles, and backfill existing production users.
     */
    public function up(): void
    {
        DB::transaction(function () {
            // 1. Initialize Baseline Permissions (14 Permissions)
            $permissions = [
                // Leads
                ['name' => 'leads.view', 'display_name' => 'View Leads', 'group' => 'leads', 'description' => 'View leads within authorized visibility scope and categories.'],
                ['name' => 'leads.create', 'display_name' => 'Create Leads', 'group' => 'leads', 'description' => 'Create new leads in the pipeline.'],
                ['name' => 'leads.edit', 'display_name' => 'Edit Leads', 'group' => 'leads', 'description' => 'Modify lead details, add internal notes, and log call outcomes.'],
                ['name' => 'leads.assign', 'display_name' => 'Reassign Leads', 'group' => 'leads', 'description' => 'Reassign leads to other team members.'],
                ['name' => 'leads.archive', 'display_name' => 'Archive Leads', 'group' => 'leads', 'description' => 'Soft delete/archive leads from the active pipeline.'],
                ['name' => 'leads.restore', 'display_name' => 'Restore Leads', 'group' => 'leads', 'description' => 'Restore archived leads back into the active pipeline.'],
                ['name' => 'leads.force_delete', 'display_name' => 'Force Delete Leads', 'group' => 'leads', 'description' => 'Permanently delete archived leads and all history.'],
                ['name' => 'leads.export', 'display_name' => 'Export Leads', 'group' => 'leads', 'description' => 'Export lead data to CSV.'],
                ['name' => 'leads.import', 'display_name' => 'Import Leads', 'group' => 'leads', 'description' => 'Upload CSV and batch import new leads.'],

                // Follow-ups
                ['name' => 'follow_ups.manage', 'display_name' => 'Manage Follow-ups', 'group' => 'follow_ups', 'description' => 'Create, reschedule, complete, and cancel follow-ups.'],

                // WhatsApp
                ['name' => 'whatsapp.send', 'display_name' => 'Send WhatsApp', 'group' => 'whatsapp', 'description' => 'Preview and dispatch WhatsApp templates.'],

                // Employees
                ['name' => 'employees.manage', 'display_name' => 'Manage Employees', 'group' => 'employees', 'description' => 'Create, edit, and toggle employee accounts.'],

                // Roles & Permissions
                ['name' => 'roles.manage', 'display_name' => 'Manage Roles & Permissions', 'group' => 'roles', 'description' => 'Configure roles, visibility scopes, and user overrides.'],

                // Settings
                ['name' => 'settings.manage', 'display_name' => 'Manage CRM Settings', 'group' => 'settings', 'description' => 'Configure CRM dictionaries and WhatsApp templates.'],
            ];

            foreach ($permissions as $p) {
                Permission::firstOrCreate(['name' => $p['name']], $p);
            }

            // 2. Initialize Baseline Teams
            $salesTeam = Team::firstOrCreate(
                ['name' => 'Enterprise Solutions Team'],
                ['description' => 'Direct sales and client consultation desk.']
            );

            $accountsTeam = Team::firstOrCreate(
                ['name' => 'Accounts & Operations Team'],
                ['description' => 'Client onboarding, invoicing, and service delivery.']
            );

            // 3. Initialize Baseline System Roles & Attach Defaults (Only if not configured)
            // A. Admin Role
            $adminRole = Role::firstOrCreate(
                ['name' => 'admin'],
                [
                    'display_name' => 'Administrator / Tenant Owner',
                    'description' => 'Full administrative access across all pipeline records, settings, and users.',
                    'is_system' => true,
                ]
            );

            $adminPermCount = DB::table('role_has_permissions')->where('role_id', $adminRole->id)->count();
            if ($adminPermCount === 0) {
                $allPermIds = Permission::pluck('id')->toArray();
                $adminRole->permissions()->attach($allPermIds);
            }

            $adminScope = DB::table('role_visibility_scopes')->where('role_id', $adminRole->id)->value('scope');
            if (empty($adminScope)) {
                $adminRole->setVisibilityScope('all');
            }

            // B. Sales Manager Role
            $managerRole = Role::firstOrCreate(
                ['name' => 'sales_manager'],
                [
                    'display_name' => 'Sales Manager',
                    'description' => 'Team lead oversight with team visibility and reassignment capabilities.',
                    'is_system' => true,
                ]
            );

            $managerPermCount = DB::table('role_has_permissions')->where('role_id', $managerRole->id)->count();
            if ($managerPermCount === 0) {
                $managerPerms = Permission::whereIn('name', [
                    'leads.view',
                    'leads.create',
                    'leads.edit',
                    'leads.assign',
                    'leads.export',
                    'follow_ups.manage',
                    'whatsapp.send',
                ])->pluck('id')->toArray();
                $managerRole->permissions()->attach($managerPerms);
            }

            $managerScope = DB::table('role_visibility_scopes')->where('role_id', $managerRole->id)->value('scope');
            if (empty($managerScope)) {
                $managerRole->setVisibilityScope('team_only');
            }

            // C. Sales Executive Role
            $executiveRole = Role::firstOrCreate(
                ['name' => 'sales_executive'],
                [
                    'display_name' => 'Sales Executive',
                    'description' => 'Standard representative handling assigned leads and consultations.',
                    'is_system' => true,
                ]
            );

            $execPermCount = DB::table('role_has_permissions')->where('role_id', $executiveRole->id)->count();
            if ($execPermCount === 0) {
                $execPerms = Permission::whereIn('name', [
                    'leads.view',
                    'leads.create',
                    'leads.edit',
                    'follow_ups.manage',
                    'whatsapp.send',
                ])->pluck('id')->toArray();
                $executiveRole->permissions()->attach($execPerms);
            }

            $execScope = DB::table('role_visibility_scopes')->where('role_id', $executiveRole->id)->value('scope');
            if (empty($execScope)) {
                $executiveRole->setVisibilityScope('assigned_only');
            }

            // D. Accounts Executive Role
            $accountsRole = Role::firstOrCreate(
                ['name' => 'accounts_executive'],
                [
                    'display_name' => 'Accounts Executive',
                    'description' => 'Operations executive managing converted clients and agreements.',
                    'is_system' => true,
                ]
            );

            $accountsPermCount = DB::table('role_has_permissions')->where('role_id', $accountsRole->id)->count();
            if ($accountsPermCount === 0) {
                $accountsPerms = Permission::whereIn('name', [
                    'leads.view',
                    'leads.edit',
                    'follow_ups.manage',
                ])->pluck('id')->toArray();
                $accountsRole->permissions()->attach($accountsPerms);
            }

            $accountsScope = DB::table('role_visibility_scopes')->where('role_id', $accountsRole->id)->value('scope');
            if (empty($accountsScope)) {
                $accountsRole->setVisibilityScope('assigned_only');
            }

            // 4. Safe Existing User Mapping (Phase 5.4 CRITICAL)
            // Only update users where role_id IS NULL.
            $unmappedUsers = User::whereNull('role_id')->get();

            $verifiedSalesExecutives = [
                'ananya@vistaboundintegrated.com',
                'rohit@vistaboundintegrated.com',
                'priya@vistaboundintegrated.com',
                'karthik@vistaboundintegrated.com',
            ];

            foreach ($unmappedUsers as $user) {
                // Verified Administrator
                if ($user->role === 'admin' || $user->email === 'admin@vistaboundintegrated.com') {
                    $user->role = 'admin';
                    $user->role_id = $adminRole->id;
                    if (!$user->team_id) {
                        $user->team_id = $salesTeam->id;
                    }
                    $user->save();
                    continue;
                }

                // Verified Sales Manager
                if ($user->email === 'sales.manager@vistaboundintegrated.com') {
                    $user->role_id = $managerRole->id;
                    if (!$user->team_id) {
                        $user->team_id = $salesTeam->id;
                    }
                    $user->save();
                    continue;
                }

                // Verified Accounts Executive
                if ($user->email === 'accounts@vistaboundintegrated.com') {
                    $user->role_id = $accountsRole->id;
                    if (!$user->team_id) {
                        $user->team_id = $accountsTeam->id;
                    }
                    $user->save();
                    continue;
                }

                // Verified Sales Executives
                if (in_array(strtolower(trim($user->email)), $verifiedSalesExecutives, true)) {
                    $user->role_id = $executiveRole->id;
                    if (!$user->team_id) {
                        $user->team_id = $salesTeam->id;
                    }
                    $user->save();
                    continue;
                }

                // Ambiguous Responsibility: DO NOT GUESS. Preserve existing legacy behavior and leave unmapped.
                Log::warning("User ID {$user->id} ({$user->name} / {$user->email}) responsibility is ambiguous. Leaving role_id as NULL for manual administrator assignment.");
            }
        });
    }

    /**
     * Non-destructive rollback:
     * Preserve all roles, permissions, teams, and configurations created or modified.
     */
    public function down(): void
    {
        // Intentionally non-destructive to prevent data loss on rollback.
        // Full table teardown is handled by 2026_11_12_000000_create_permission_management_tables.
    }
};
