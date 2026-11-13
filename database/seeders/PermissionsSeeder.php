<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Permissions
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

        // 2. Seed Teams
        $salesTeam = Team::firstOrCreate(
            ['name' => 'Enterprise Solutions Team'],
            ['description' => 'Direct sales and client consultation desk.']
        );
        $accountsTeam = Team::firstOrCreate(
            ['name' => 'Accounts & Operations Team'],
            ['description' => 'Client onboarding, invoicing, and service delivery.']
        );

        // 3. Seed Roles
        // A. Admin Role
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator / Tenant Owner',
                'description' => 'Full administrative access across all pipeline records, settings, and users.',
                'is_system' => true,
            ]
        );
        $adminRole->setVisibilityScope('all');
        $allPermIds = Permission::pluck('id')->toArray();
        $adminRole->permissions()->sync($allPermIds);

        // B. Sales Manager Role
        $managerRole = Role::firstOrCreate(
            ['name' => 'sales_manager'],
            [
                'display_name' => 'Sales Manager',
                'description' => 'Team lead oversight with team visibility and reassignment capabilities.',
                'is_system' => true,
            ]
        );
        $managerRole->setVisibilityScope('team_only');
        $managerPerms = Permission::whereIn('name', [
            'leads.view',
            'leads.create',
            'leads.edit',
            'leads.assign',
            'leads.export',
            'follow_ups.manage',
            'whatsapp.send',
        ])->pluck('id')->toArray();
        $managerRole->permissions()->sync($managerPerms);

        // C. Sales Executive Role
        $executiveRole = Role::firstOrCreate(
            ['name' => 'sales_executive'],
            [
                'display_name' => 'Sales Executive',
                'description' => 'Standard representative handling assigned leads and consultations.',
                'is_system' => true,
            ]
        );
        $executiveRole->setVisibilityScope('assigned_only');
        $execPerms = Permission::whereIn('name', [
            'leads.view',
            'leads.create',
            'leads.edit',
            'follow_ups.manage',
            'whatsapp.send',
        ])->pluck('id')->toArray();
        $executiveRole->permissions()->sync($execPerms);

        // D. Accounts Executive Role
        $accountsRole = Role::firstOrCreate(
            ['name' => 'accounts_executive'],
            [
                'display_name' => 'Accounts Executive',
                'description' => 'Operations executive managing converted clients and agreements.',
                'is_system' => true,
            ]
        );
        $accountsRole->setVisibilityScope('assigned_only');
        $accountsPerms = Permission::whereIn('name', [
            'leads.view',
            'leads.edit',
            'follow_ups.manage',
        ])->pluck('id')->toArray();
        $accountsRole->permissions()->sync($accountsPerms);

        // 4. Attach Roles and Teams to Seeded Users
        $adminUser = User::where('email', 'admin@vistaboundintegrated.com')->first();
        if ($adminUser) {
            $adminUser->role = 'admin';
            $adminUser->role_id = $adminRole->id;
            $adminUser->team_id = $salesTeam->id;
            $adminUser->save();
        }

        $managerUser = User::where('email', 'sales.manager@vistaboundintegrated.com')->first();
        if ($managerUser) {
            $managerUser->role = 'employee';
            $managerUser->role_id = $managerRole->id;
            $managerUser->team_id = $salesTeam->id;
            $managerUser->save();
        }

        $execEmails = [
            'ananya@vistaboundintegrated.com',
            'rohit@vistaboundintegrated.com',
            'priya@vistaboundintegrated.com',
            'karthik@vistaboundintegrated.com',
        ];
        foreach ($execEmails as $email) {
            $execUser = User::where('email', $email)->first();
            if ($execUser) {
                $execUser->role = 'employee';
                $execUser->role_id = $executiveRole->id;
                $execUser->team_id = $salesTeam->id;
                $execUser->save();
            }
        }

        $accountsUser = User::where('email', 'accounts@vistaboundintegrated.com')->first();
        if ($accountsUser) {
            $accountsUser->role = 'employee';
            $accountsUser->role_id = $accountsRole->id;
            $accountsUser->team_id = $accountsTeam->id;
            $accountsUser->save();
        }
    }
}
