<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Permission;
use App\Models\PermissionAuditLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionManagementTest extends TestCase
{
    protected User $admin;
    protected User $employee1;
    protected User $employee2;
    protected User $manager;
    protected Team $teamSales;
    protected Team $teamRetail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('email', 'admin@vistaboundintegrated.com')->firstOrFail();
        $this->employee1 = User::where('email', 'ananya@vistaboundintegrated.com')->firstOrFail();
        $this->employee2 = User::where('email', 'rohit@vistaboundintegrated.com')->firstOrFail();

        // Ensure teams exist
        $this->teamSales = Team::firstOrCreate(['name' => 'Corporate Sales Unit'], ['description' => 'Test Unit A']);
        $this->teamRetail = Team::firstOrCreate(['name' => 'Retail & SMB Unit'], ['description' => 'Test Unit B']);

        // Set employee1 and employee2 on teamSales
        $this->employee1->team_id = $this->teamSales->id;
        $this->employee1->save();

        $this->employee2->team_id = $this->teamSales->id;
        $this->employee2->save();
    }

    protected function createLead(string $service = 'Enterprise Software Engineering', ?int $assignedTo = null): Lead
    {
        return Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Test RBAC Lead ' . rand(1000, 9999),
            'phone' => '+91 ' . rand(60000, 99999) . ' ' . rand(10000, 99999),
            'phone_normalized' => '+91' . rand(6000000000, 9999999999),
            'email' => 'rbac' . rand(1000, 9999) . '@example.com',
            'company_name' => 'RBAC Test Enterprise',
            'status' => 'new',
            'priority' => 'medium',
            'source' => 'Website',
            'service_interest' => $service,
            'assigned_to' => $assignedTo,
            'assigned_at' => $assignedTo ? now() : null,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_role_template_inheritance(): void
    {
        // 1. Create a custom test role
        $role = Role::create([
            'name' => 'test_junior_rep',
            'display_name' => 'Junior Representative',
            'description' => 'Limited test role',
            'is_system' => false,
        ]);
        $role->setVisibilityScope('assigned_only');

        $perms = Permission::whereIn('name', ['leads.view', 'leads.create'])->pluck('id');
        $role->permissions()->sync($perms);

        // 2. Assign employee1 to this role
        $this->employee1->role_id = $role->id;
        $this->employee1->role = $role->name;
        $this->employee1->save();

        // Clear any previous overrides
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->delete();

        // 3. Verify inherited permissions
        $this->assertTrue($this->employee1->hasPermission('leads.view'));
        $this->assertTrue($this->employee1->hasPermission('leads.create'));
        $this->assertFalse($this->employee1->hasPermission('leads.archive'));
        $this->assertFalse($this->employee1->hasPermission('employees.manage'));

        // Clean up
        $this->employee1->role_id = null;
        $this->employee1->role = 'employee';
        $this->employee1->save();
        $role->delete();
    }

    public function test_user_explicit_allow_overrides_role_omission(): void
    {
        // Ensure employee1 has no base 'leads.archive' permission
        $role = Role::where('name', 'sales_executive')->first();
        if ($role) {
            $this->employee1->role_id = $role->id;
            $this->employee1->save();
        }

        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->delete();
        $this->assertFalse($this->employee1->hasPermission('leads.archive'));

        // Admin grants explicit 'allow' override
        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->putJson("/api/users/{$this->employee1->id}/permissions", [
                'overrides' => [
                    ['permission' => 'leads.archive', 'effect' => 'allow'],
                ],
            ]);

        $response->assertStatus(200);

        // Now user has permission
        $this->employee1->refresh();
        $this->assertTrue($this->employee1->hasPermission('leads.archive'));

        // Verify audit log
        $this->assertDatabaseHas('permission_audit_logs', [
            'actor_id' => $this->admin->id,
            'target_type' => 'user',
            'target_id' => $this->employee1->id,
            'action' => 'user_permissions_updated',
        ]);
    }

    public function test_user_explicit_deny_overrides_role_grant(): void
    {
        // Give employee1 a role that has 'leads.create'
        $role = Role::where('name', 'sales_executive')->first();
        if ($role) {
            $this->employee1->role_id = $role->id;
            $this->employee1->save();
        }

        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->delete();
        $this->assertTrue($this->employee1->hasPermission('leads.create'));

        // Admin grants explicit 'deny' override
        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->putJson("/api/users/{$this->employee1->id}/permissions", [
                'overrides' => [
                    ['permission' => 'leads.create', 'effect' => 'deny'],
                ],
            ]);

        $response->assertStatus(200);

        // User is now explicitly denied
        $this->employee1->refresh();
        $this->assertFalse($this->employee1->hasPermission('leads.create'));
    }

    public function test_reset_user_permissions_reverts_to_role_default(): void
    {
        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        // Set overrides
        DB::table('user_has_permissions')->updateOrInsert(
            ['user_id' => $this->employee1->id, 'permission_id' => Permission::first()->id],
            ['effect' => 'deny', 'granted_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('user_visibility_overrides')->updateOrInsert(
            ['user_id' => $this->employee1->id],
            ['scope' => 'all', 'updated_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
        );

        // Call reset endpoint
        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/users/{$this->employee1->id}/permissions/reset");

        $response->assertStatus(200);

        // Verify tables cleared
        $this->assertDatabaseMissing('user_has_permissions', ['user_id' => $this->employee1->id]);
        $this->assertDatabaseMissing('user_visibility_overrides', ['user_id' => $this->employee1->id]);
        $this->assertDatabaseMissing('user_category_assignments', ['user_id' => $this->employee1->id]);

        // Verify audit log
        $this->assertDatabaseHas('permission_audit_logs', [
            'actor_id' => $this->admin->id,
            'target_type' => 'user',
            'target_id' => $this->employee1->id,
            'action' => 'permissions_reset_to_role_default',
        ]);
    }

    public function test_lead_visibility_scopes_assigned_team_and_all(): void
    {
        // Create 3 distinct test leads
        $leadAssigned = $this->createLead('Cloud & DevOps Solutions', $this->employee1->id);
        $leadTeamMate = $this->createLead('Cloud & DevOps Solutions', $this->employee2->id); // same team
        $leadOther = $this->createLead('Cloud & DevOps Solutions', $this->admin->id); // different team

        // Case 1: Assigned Only
        DB::table('user_visibility_overrides')->updateOrInsert(
            ['user_id' => $this->employee1->id],
            ['scope' => 'assigned_only', 'created_at' => now(), 'updated_at' => now()]
        );
        $accessibleIds = Lead::accessibleBy($this->employee1)->pluck('id')->toArray();
        $this->assertContains($leadAssigned->id, $accessibleIds);
        $this->assertNotContains($leadTeamMate->id, $accessibleIds);
        $this->assertNotContains($leadOther->id, $accessibleIds);

        // Case 2: Team Only
        DB::table('user_visibility_overrides')->updateOrInsert(
            ['user_id' => $this->employee1->id],
            ['scope' => 'team_only', 'created_at' => now(), 'updated_at' => now()]
        );
        $accessibleIds = Lead::accessibleBy($this->employee1)->pluck('id')->toArray();
        $this->assertContains($leadAssigned->id, $accessibleIds);
        $this->assertContains($leadTeamMate->id, $accessibleIds);
        $this->assertNotContains($leadOther->id, $accessibleIds);

        // Case 3: All Leads
        DB::table('user_visibility_overrides')->updateOrInsert(
            ['user_id' => $this->employee1->id],
            ['scope' => 'all', 'created_at' => now(), 'updated_at' => now()]
        );
        $accessibleIds = Lead::accessibleBy($this->employee1)->pluck('id')->toArray();
        $this->assertContains($leadAssigned->id, $accessibleIds);
        $this->assertContains($leadTeamMate->id, $accessibleIds);
        $this->assertContains($leadOther->id, $accessibleIds);

        // Clean up
        DB::table('user_visibility_overrides')->where('user_id', $this->employee1->id)->delete();
        $leadAssigned->forceDelete();
        $leadTeamMate->forceDelete();
        $leadOther->forceDelete();
    }

    public function test_category_based_vertical_restrictions(): void
    {
        $leadCloud = $this->createLead('Cloud & DevOps Solutions', $this->employee1->id);
        $leadRetail = $this->createLead('Retail & Commerce Solutions', $this->employee1->id);

        // Set category restriction to only 'Cloud & DevOps Solutions'
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_category_assignments')->insert([
            'user_id' => $this->employee1->id,
            'category_name' => 'Cloud & DevOps Solutions',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->employee1->refresh();

        // Verify canAccessLead
        $this->assertTrue($this->employee1->canAccessLead($leadCloud));
        $this->assertFalse($this->employee1->canAccessLead($leadRetail));

        // Verify scope query
        $accessibleIds = Lead::accessibleBy($this->employee1)->pluck('id')->toArray();
        $this->assertContains($leadCloud->id, $accessibleIds);
        $this->assertNotContains($leadRetail->id, $accessibleIds);

        // Clean up
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        $leadCloud->forceDelete();
        $leadRetail->forceDelete();
    }

    public function test_unauthorized_user_cannot_access_roles_endpoints(): void
    {
        // employee2 has no roles.manage permission
        $token = $this->employee2->createToken('emp-test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/roles');

        $response->assertStatus(403);

        $responseAudit = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/audit-logs');

        $responseAudit->assertStatus(403);
    }
}
