<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Permission;
use App\Models\PermissionAuditLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
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

        // Reset base roles and clean any lingering overrides from previous runs
        $salesRole = Role::where('name', 'sales_executive')->first();
        $this->employee1->role_id = $salesRole?->id;
        $this->employee1->role = 'employee';
        $this->employee1->team_id = $this->teamSales->id;
        $this->employee1->save();

        $this->employee2->role_id = $salesRole?->id;
        $this->employee2->role = 'employee';
        $this->employee2->team_id = $this->teamSales->id;
        $this->employee2->save();

        DB::table('user_has_permissions')->whereIn('user_id', [$this->employee1->id, $this->employee2->id])->delete();
        DB::table('user_visibility_overrides')->whereIn('user_id', [$this->employee1->id, $this->employee2->id])->delete();
        DB::table('user_category_assignments')->whereIn('user_id', [$this->employee1->id, $this->employee2->id])->delete();
    }

    protected function tearDown(): void
    {
        DB::table('user_has_permissions')->whereIn('user_id', [$this->employee1->id, $this->employee2->id])->delete();
        DB::table('user_visibility_overrides')->whereIn('user_id', [$this->employee1->id, $this->employee2->id])->delete();
        DB::table('user_category_assignments')->whereIn('user_id', [$this->employee1->id, $this->employee2->id])->delete();

        $salesRole = Role::where('name', 'sales_executive')->first();
        if ($salesRole) {
            $this->employee1->role_id = $salesRole->id;
            $this->employee1->role = 'employee';
            $this->employee1->save();

            $this->employee2->role_id = $salesRole->id;
            $this->employee2->role = 'employee';
            $this->employee2->save();
        }

        parent::tearDown();
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
        $salesRole = Role::where('name', 'sales_executive')->first();
        $this->employee1->role_id = $salesRole?->id;
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

        // Admin grants explicit 'allow' override via web desk session
        $response = $this->actingAs($this->admin, 'web')
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

        // Admin grants explicit 'deny' override via web desk session
        $response = $this->actingAs($this->admin, 'web')
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
        // Set overrides
        DB::table('user_has_permissions')->updateOrInsert(
            ['user_id' => $this->employee1->id, 'permission_id' => Permission::first()->id],
            ['effect' => 'deny', 'granted_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('user_visibility_overrides')->updateOrInsert(
            ['user_id' => $this->employee1->id],
            ['scope' => 'all', 'updated_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
        );

        // Call reset endpoint via web desk session
        $response = $this->actingAs($this->admin, 'web')
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

    public function test_direct_id_manipulation_prevented_on_lead_show_and_update(): void
    {
        // Lead assigned to employee2 (private lead)
        $privateLead = $this->createLead('Cloud & DevOps Solutions', $this->employee2->id);

        // Ensure employee1 is assigned_only
        DB::table('user_visibility_overrides')->updateOrInsert(
            ['user_id' => $this->employee1->id],
            ['scope' => 'assigned_only', 'created_at' => now(), 'updated_at' => now()]
        );

        $token1 = $this->employee1->createToken('emp1-test')->plainTextToken;

        // 1. Employee 1 attempts to view Employee 2's lead directly
        $showRes = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->getJson("/api/leads/{$privateLead->id}");

        $showRes->assertStatus(403);

        // 2. Employee 1 attempts to update Employee 2's lead directly
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->putJson("/api/leads/{$privateLead->id}", [
                'name' => 'Tampered Lead Name',
            ]);

        $updateRes->assertStatus(403);

        // 3. Employee 1 attempts to log call on Employee 2's lead directly
        $callRes = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson("/api/leads/{$privateLead->id}/call", [
                'outcome' => 'Interested',
                'notes' => 'Tampered call log',
            ]);

        $callRes->assertStatus(403);

        // Clean up
        $privateLead->forceDelete();
    }

    public function test_category_restrictions_enforced_during_mobile_sync_push(): void
    {
        // Lead assigned to employee1, but in 'Retail & Commerce Solutions'
        $retailLead = $this->createLead('Retail & Commerce Solutions', $this->employee1->id);

        // Restrict employee1 to only 'Cloud & DevOps Solutions'
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_category_assignments')->insert([
            'user_id' => $this->employee1->id,
            'category_name' => 'Cloud & DevOps Solutions',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token1 = $this->employee1->createToken('emp1-mobile-test')->plainTextToken;

        // Push offline mutation on forbidden category lead
        $pushRes = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/mobile/sync/push', [
                'mutations' => [
                    [
                        'uuid' => 'test-mobile-uuid-' . rand(10000, 99999),
                        'operation' => 'create_note',
                        'lead_id' => $retailLead->id,
                        'payload' => ['note' => 'Attempted note on restricted category lead'],
                    ],
                ],
            ]);

        $pushRes->assertStatus(200);
        $results = $pushRes->json('results');
        $this->assertNotEmpty($results);
        $this->assertEquals('forbidden', $results[0]['status']);
        $this->assertStringContainsString('access denied', strtolower($results[0]['message']));

        // Clean up
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        $retailLead->forceDelete();
    }

    /**
     * SEC-01 Tests: Broken Object Level Authorization (BOLA)
     */
    public function test_sec01_unauthorized_lead_archive_blocked_by_visibility(): void
    {
        $privateLead = $this->createLead('Cloud & DevOps Solutions', $this->employee2->id);

        // Grant employee1 the functional permission 'leads.archive'
        $perm = Permission::where('name', 'leads.archive')->firstOrFail();
        DB::table('user_has_permissions')->updateOrInsert(
            ['user_id' => $this->employee1->id, 'permission_id' => $perm->id],
            ['effect' => 'allow', 'granted_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
        );

        // Ensure visibility is assigned_only
        DB::table('user_visibility_overrides')->updateOrInsert(
            ['user_id' => $this->employee1->id],
            ['scope' => 'assigned_only', 'created_at' => now(), 'updated_at' => now()]
        );

        $token1 = $this->employee1->createToken('emp1-test')->plainTextToken;

        // Attempt to archive employee2's lead
        $res = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->deleteJson("/api/leads/{$privateLead->id}");

        $res->assertStatus(403);
        $this->assertNull($privateLead->fresh()->deleted_at);

        // Clean up
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->where('permission_id', $perm->id)->delete();
        DB::table('user_visibility_overrides')->where('user_id', $this->employee1->id)->delete();
        $privateLead->forceDelete();
    }

    public function test_sec01_unauthorized_lead_restore_blocked_by_visibility(): void
    {
        $privateLead = $this->createLead('Cloud & DevOps Solutions', $this->employee2->id);
        $privateLead->delete(); // Soft delete

        // Grant employee1 the functional permission 'leads.restore'
        $perm = Permission::where('name', 'leads.restore')->firstOrFail();
        DB::table('user_has_permissions')->updateOrInsert(
            ['user_id' => $this->employee1->id, 'permission_id' => $perm->id],
            ['effect' => 'allow', 'granted_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
        );

        // Ensure visibility is assigned_only
        DB::table('user_visibility_overrides')->updateOrInsert(
            ['user_id' => $this->employee1->id],
            ['scope' => 'assigned_only', 'created_at' => now(), 'updated_at' => now()]
        );

        $token1 = $this->employee1->createToken('emp1-test')->plainTextToken;

        // Attempt to restore employee2's archived lead
        $res = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson("/api/leads/{$privateLead->id}/restore");

        $res->assertStatus(403);
        $this->assertTrue($privateLead->fresh()->trashed());

        // Clean up
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->where('permission_id', $perm->id)->delete();
        DB::table('user_visibility_overrides')->where('user_id', $this->employee1->id)->delete();
        $privateLead->forceDelete();
    }

    public function test_sec01_unauthorized_lead_reassignment_blocked_by_visibility(): void
    {
        $privateLead = $this->createLead('Cloud & DevOps Solutions', $this->employee2->id);

        // Grant employee1 the functional permission 'leads.assign'
        $perm = Permission::where('name', 'leads.assign')->firstOrFail();
        DB::table('user_has_permissions')->updateOrInsert(
            ['user_id' => $this->employee1->id, 'permission_id' => $perm->id],
            ['effect' => 'allow', 'granted_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
        );

        // Ensure visibility is assigned_only
        DB::table('user_visibility_overrides')->updateOrInsert(
            ['user_id' => $this->employee1->id],
            ['scope' => 'assigned_only', 'created_at' => now(), 'updated_at' => now()]
        );

        $token1 = $this->employee1->createToken('emp1-test')->plainTextToken;

        // Attempt to reassign employee2's lead to employee1
        $res = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson("/api/leads/{$privateLead->id}/assign", [
                'assigned_to' => $this->employee1->id,
            ]);

        $res->assertStatus(403);
        $this->assertEquals($this->employee2->id, $privateLead->fresh()->assigned_to);

        // Clean up
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->where('permission_id', $perm->id)->delete();
        DB::table('user_visibility_overrides')->where('user_id', $this->employee1->id)->delete();
        $privateLead->forceDelete();
    }

    public function test_sec01_lead_assignment_to_inactive_user_rejected(): void
    {
        $lead = $this->createLead('Cloud & DevOps Solutions', $this->employee1->id);

        // Deactivate employee2 temporarily
        $this->employee2->is_active = false;
        $this->employee2->save();

        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/leads/{$lead->id}/assign", [
                'assigned_to' => $this->employee2->id,
            ]);

        $res->assertStatus(422);
        $res->assertJsonFragment(['message' => 'Cannot assign leads to an inactive user.']);

        // Reactivate employee2 and clean up
        $this->employee2->is_active = true;
        $this->employee2->save();
        $lead->forceDelete();
    }

    public function test_sec01_lead_assignment_to_category_ineligible_user_rejected(): void
    {
        $leadRetail = $this->createLead('Retail & Commerce Solutions', null);

        // Restrict employee1 to only 'Cloud & DevOps Solutions'
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_category_assignments')->insert([
            'user_id' => $this->employee1->id,
            'category_name' => 'Cloud & DevOps Solutions',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminToken = $this->admin->createToken('admin-test')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson("/api/leads/{$leadRetail->id}/assign", [
                'assigned_to' => $this->employee1->id,
            ]);

        $res->assertStatus(422);
        $res->assertJsonFragment(['message' => "Destination user is not authorized to handle this lead's service category."]);

        // Clean up
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        $leadRetail->forceDelete();
    }

    public function test_sec01_authorized_user_can_archive_restore_and_assign_permitted_lead(): void
    {
        $lead = $this->createLead('Cloud & DevOps Solutions', $this->employee1->id);

        // Grant employee1 permissions
        foreach (['leads.archive', 'leads.restore', 'leads.assign'] as $permName) {
            $perm = Permission::where('name', $permName)->firstOrFail();
            DB::table('user_has_permissions')->updateOrInsert(
                ['user_id' => $this->employee1->id, 'permission_id' => $perm->id],
                ['effect' => 'allow', 'granted_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $token1 = $this->employee1->createToken('emp1-test')->plainTextToken;

        // Archive own lead
        $delRes = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->deleteJson("/api/leads/{$lead->id}");
        $delRes->assertStatus(200);
        $this->assertTrue($lead->fresh()->trashed());

        // Restore own lead
        $restRes = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson("/api/leads/{$lead->id}/restore");
        $restRes->assertStatus(200);
        $this->assertFalse($lead->fresh()->trashed());

        // Assign to self
        $assignRes = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson("/api/leads/{$lead->id}/assign", ['assigned_to' => $this->employee1->id]);
        $assignRes->assertStatus(200);

        // Clean up
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->delete();
        $lead->forceDelete();
    }

    /**
     * SEC-02 Tests: Permission Catalog Initialization & User Mapping
     */
    public function test_sec02_baseline_permissions_initialize_and_custom_roles_survive(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => 'leads.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'leads.archive']);
        $this->assertDatabaseHas('permissions', ['name' => 'leads.restore']);
        $this->assertDatabaseHas('permissions', ['name' => 'leads.assign']);

        // Create a custom role with custom permissions
        $customRole = Role::create([
            'name' => 'custom_support_agent_' . rand(1000, 9999),
            'display_name' => 'Support Desk Agent',
            'description' => 'Custom role',
            'is_system' => false,
        ]);
        $customRole->setVisibilityScope('assigned_only');
        $customPerm = Permission::where('name', 'leads.view')->firstOrFail();
        $customRole->permissions()->attach([$customPerm->id]);

        // Re-run seeder to verify idempotency and preservation
        $seeder = new \Database\Seeders\PermissionsSeeder();
        $seeder->run();

        // Custom role is preserved and permissions remain intact
        $freshRole = Role::find($customRole->id);
        $this->assertNotNull($freshRole);
        $this->assertEquals(1, $freshRole->permissions()->count());

        // Clean up
        $customRole->delete();
    }

    public function test_sec02_ambiguous_user_responsibility_preserved_unmapped(): void
    {
        $ambiguousUser = User::create([
            'name' => 'Ambiguous Employee',
            'email' => 'ambiguous_' . rand(1000, 9999) . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'employee',
            'role_id' => null,
            'is_active' => true,
        ]);

        $seeder = new \Database\Seeders\PermissionsSeeder();
        $seeder->run();

        // Must remain null (never guessed or force-mapped)
        $this->assertNull($ambiguousUser->fresh()->role_id);

        $ambiguousUser->forceDelete();
    }

    /**
     * SEC-03 Tests: Lead Category Validation on Creation Paths
     */
    public function test_sec03_restricted_user_cannot_create_lead_in_unauthorized_category(): void
    {
        // Restrict employee1 to only 'Cloud & DevOps Solutions'
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_category_assignments')->insert([
            'user_id' => $this->employee1->id,
            'category_name' => 'Cloud & DevOps Solutions',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Grant employee1 leads.create
        $perm = Permission::where('name', 'leads.create')->firstOrFail();
        DB::table('user_has_permissions')->updateOrInsert(
            ['user_id' => $this->employee1->id, 'permission_id' => $perm->id],
            ['effect' => 'allow', 'granted_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
        );

        $token1 = $this->employee1->createToken('emp1-test')->plainTextToken;

        // Post lead under forbidden category 'Retail & Commerce Solutions'
        $res = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/leads', [
                'name' => 'Forbidden Cat Lead',
                'phone' => '+91 9988776655',
                'service_interest' => 'Retail & Commerce Solutions',
            ]);

        $res->assertStatus(403);
        $res->assertJsonFragment(['message' => 'You are not authorized to create leads under this service vertical.']);

        // Clean up
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->where('permission_id', $perm->id)->delete();
    }

    public function test_sec03_restricted_user_cannot_create_lead_without_category(): void
    {
        // Restrict employee1 to 'Cloud & DevOps Solutions'
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_category_assignments')->insert([
            'user_id' => $this->employee1->id,
            'category_name' => 'Cloud & DevOps Solutions',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $perm = Permission::where('name', 'leads.create')->firstOrFail();
        DB::table('user_has_permissions')->updateOrInsert(
            ['user_id' => $this->employee1->id, 'permission_id' => $perm->id],
            ['effect' => 'allow', 'granted_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
        );

        $token1 = $this->employee1->createToken('emp1-test')->plainTextToken;

        // Post lead without category
        $res = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/leads', [
                'name' => 'Missing Cat Lead',
                'phone' => '+91 9988776644',
                'service_interest' => null,
            ]);

        $res->assertStatus(422);

        // Clean up
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->where('permission_id', $perm->id)->delete();
    }

    public function test_sec03_restricted_user_can_create_lead_in_authorized_category(): void
    {
        // Restrict employee1 to 'Cloud & DevOps Solutions'
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_category_assignments')->insert([
            'user_id' => $this->employee1->id,
            'category_name' => 'Cloud & DevOps Solutions',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $perm = Permission::where('name', 'leads.create')->firstOrFail();
        DB::table('user_has_permissions')->updateOrInsert(
            ['user_id' => $this->employee1->id, 'permission_id' => $perm->id],
            ['effect' => 'allow', 'granted_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
        );

        $token1 = $this->employee1->createToken('emp1-test')->plainTextToken;

        $phone = '+91 91234' . rand(10000, 99999);
        $res = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/leads', [
                'name' => 'Authorized Cat Lead',
                'phone' => $phone,
                'service_interest' => 'Cloud & DevOps Solutions',
            ]);

        $res->assertStatus(201);
        $createdId = $res->json('lead.id');

        // Clean up
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->where('permission_id', $perm->id)->delete();
        if ($createdId) {
            Lead::find($createdId)?->forceDelete();
        }
    }

    public function test_sec03_mobile_offline_sync_push_rejects_unauthorized_category_lead(): void
    {
        // Restrict employee1 to 'Cloud & DevOps Solutions'
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_category_assignments')->insert([
            'user_id' => $this->employee1->id,
            'category_name' => 'Cloud & DevOps Solutions',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token1 = $this->employee1->createToken('emp1-mobile-test')->plainTextToken;

        $pushRes = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/mobile/sync/push', [
                'mutations' => [
                    [
                        'uuid' => 'test-create-lead-forbidden-' . rand(10000, 99999),
                        'operation' => 'create_lead',
                        'payload' => [
                            'name' => 'Offline Lead Forbidden Vertical',
                            'phone' => '+91 98765' . rand(10000, 99999),
                            'service_interest' => 'Retail & Commerce Solutions',
                        ],
                    ],
                ],
            ]);

        $pushRes->assertStatus(200);
        $results = $pushRes->json('results');
        $this->assertNotEmpty($results);
        $this->assertEquals('forbidden', $results[0]['status']);
        $this->assertStringContainsString('unauthorized category', strtolower($results[0]['message']));

        // Clean up
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
    }

    /**
     * SEC-04 Tests: NULL Category Access Consistency
     */
    public function test_sec04_null_and_empty_category_leads_uniformly_inaccessible_to_restricted_user(): void
    {
        // Create lead with null service_interest
        $leadNull = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Lead Null Category',
            'phone' => '+91 94444' . rand(10000, 99999),
            'phone_normalized' => '94444' . rand(10000, 99999),
            'service_interest' => null,
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->admin->id,
        ]);

        // Create lead with whitespace service_interest
        $leadBlank = Lead::create([
            'reference_id' => Lead::generateReferenceId(),
            'name' => 'Lead Blank Category',
            'phone' => '+91 93333' . rand(10000, 99999),
            'phone_normalized' => '93333' . rand(10000, 99999),
            'service_interest' => '   ',
            'assigned_to' => $this->employee1->id,
            'created_by' => $this->admin->id,
        ]);

        // Restrict employee1 to only 'Cloud & DevOps Solutions'
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_category_assignments')->insert([
            'user_id' => $this->employee1->id,
            'category_name' => 'Cloud & DevOps Solutions',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->employee1->refresh();

        // 1. Check canAccessLead returns false for both
        $this->assertFalse($this->employee1->canAccessLead($leadNull));
        $this->assertFalse($this->employee1->canAccessLead($leadBlank));

        // 2. Check query scope excludes both
        $accessibleIds = Lead::accessibleBy($this->employee1)->pluck('id')->toArray();
        $this->assertNotContains($leadNull->id, $accessibleIds);
        $this->assertNotContains($leadBlank->id, $accessibleIds);

        // 3. Unrestricted admin can access both
        $this->assertTrue($this->admin->canAccessLead($leadNull));
        $this->assertTrue($this->admin->canAccessLead($leadBlank));

        // Clean up
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        $leadNull->forceDelete();
        $leadBlank->forceDelete();
    }

    /**
     * SEC-05 Tests: Employee Permission Audit Trail
     */
    public function test_sec05_employee_creation_and_updates_generate_immutable_audit_logs(): void
    {
        $this->actingAsWeb($this->admin);

        // 1. Create Employee
        $empEmail = 'test_audit_emp_' . rand(1000, 9999) . '@example.com';
        $createRes = $this->postJson('/api/employees', [
            'name' => 'Audit Test User',
            'email' => $empEmail,
            'password' => 'SecurePass123!',
            'role' => 'employee',
            'team_id' => $this->teamSales->id,
        ]);

        $createRes->assertStatus(201);
        $empId = $createRes->json('employee.id');

        $this->assertDatabaseHas('permission_audit_logs', [
            'actor_id' => $this->admin->id,
            'target_type' => 'user',
            'target_id' => $empId,
            'action' => 'employee_created',
        ]);

        // 2. Update Role
        $managerRole = Role::where('name', 'sales_manager')->firstOrFail();
        $updateRoleRes = $this->putJson("/api/employees/{$empId}", [
            'role_id' => $managerRole->id,
        ]);

        $updateRoleRes->assertStatus(200);

        $this->assertDatabaseHas('permission_audit_logs', [
            'actor_id' => $this->admin->id,
            'target_type' => 'user',
            'target_id' => $empId,
            'action' => 'employee_role_changed',
        ]);

        // 3. Update Team
        $updateTeamRes = $this->putJson("/api/employees/{$empId}", [
            'team_id' => $this->teamRetail->id,
        ]);

        $updateTeamRes->assertStatus(200);

        $this->assertDatabaseHas('permission_audit_logs', [
            'actor_id' => $this->admin->id,
            'target_type' => 'user',
            'target_id' => $empId,
            'action' => 'employee_team_changed',
        ]);

        // Clean up
        User::find($empId)?->forceDelete();
    }

    /**
     * SEC-06 Tests: Case and Whitespace Category Normalization
     */
    public function test_sec06_case_insensitive_and_whitespace_category_normalization(): void
    {
        // Allowed category with mixed case
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_category_assignments')->insert([
            'user_id' => $this->employee1->id,
            'category_name' => 'Cloud & DevOps Solutions',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->employee1->refresh();

        // 1. Test isCategoryAllowed with leading/trailing spaces and lowercase
        $this->assertTrue($this->employee1->isCategoryAllowed('  cloud & devops solutions  '));
        $this->assertTrue($this->employee1->isCategoryAllowed('CLOUD & DEVOPS SOLUTIONS'));
        $this->assertFalse($this->employee1->isCategoryAllowed('  retail & commerce solutions  '));

        // 2. Create lead with lowercase and whitespace
        $leadNorm = $this->createLead('  cloud & devops solutions  ', $this->employee1->id);

        $this->assertTrue($this->employee1->canAccessLead($leadNorm));

        $accessibleIds = Lead::accessibleBy($this->employee1)->pluck('id')->toArray();
        $this->assertContains($leadNorm->id, $accessibleIds);

        // Clean up
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        $leadNorm->forceDelete();
    }

    /**
     * Phase G.2.1 Architecture Verification Tests: Web-Only RBAC & Mobile Authority
     */
    public function test_phase_g21_web_only_rbac_endpoints_strictly_forbid_non_admin(): void
    {
        $employeeToken = $this->employee1->createToken('test_mobile_session')->plainTextToken;

        // 1. Role listing and management
        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->getJson('/api/roles')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->postJson('/api/roles', [
                'name' => 'malicious_role',
                'display_name' => 'Malicious Role',
            ])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->putJson('/api/roles/1', [
                'display_name' => 'Tampered Role',
            ])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->deleteJson('/api/roles/1')
            ->assertStatus(403);

        // 2. Permission catalog listing
        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->getJson('/api/permissions')
            ->assertStatus(403);

        // 3. User-level overrides on other users
        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->getJson("/api/users/{$this->employee2->id}/permissions")
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->putJson("/api/users/{$this->employee2->id}/permissions", [
                'overrides' => [['permission_id' => 1, 'state' => 'allow']],
            ])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->putJson("/api/users/{$this->employee2->id}/visibility", [
                'visibility_scope' => 'all',
            ])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->putJson("/api/users/{$this->employee2->id}/categories", [
                'categories' => ['All Categories'],
            ])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->postJson("/api/users/{$this->employee2->id}/permissions/reset")
            ->assertStatus(403);

        // 4. Permission Audit Logs
        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->getJson('/api/audit-logs')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $employeeToken)
            ->getJson('/api/permissions/audit-logs')
            ->assertStatus(403);
    }

    protected function actingAsWeb(User $user): static
    {
        auth()->forgetGuards();
        auth()->shouldUse('web');
        return $this->actingAs($user, 'web');
    }

    protected function actingAsMobile(User $user, array $abilities = ['*']): static
    {
        auth()->forgetGuards();
        Sanctum::actingAs($user, $abilities);
        return $this;
    }

    public function test_phase_g21_mobile_sync_pull_returns_authoritative_effective_permissions_and_refreshes(): void
    {
        // 1. Initial mobile pull - should NOT have leads.export by default
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->delete();
        $this->employee1->refresh();

        $this->actingAsMobile($this->employee1);
        $initialPull = $this->getJson('/api/mobile/sync/pull');

        $initialPull->assertStatus(200);
        $initialPull->assertJsonStructure([
            'server_time',
            'user' => [
                'id',
                'name',
                'email',
                'role',
                'permissions',
                'visibility_scope',
                'allowed_categories',
            ],
            'leads',
            'follow_ups',
            'settings',
        ]);

        $initialPerms = $initialPull->json('user.permissions');
        $this->assertNotContains('leads.export', $initialPerms);

        // 2. Admin grants explicit allow override on web
        $updateRes = $this->actingAsWeb($this->admin)
            ->putJson("/api/users/{$this->employee1->id}/permissions", [
                'overrides' => [
                    ['permission' => 'leads.export', 'effect' => 'allow'],
                ],
            ]);
        $updateRes->assertStatus(200);

        // 3. Mobile pulls sync again - effective permissions immediately include leads.export
        $this->actingAsMobile($this->employee1);
        $refreshedPull = $this->getJson('/api/mobile/sync/pull');

        $refreshedPull->assertStatus(200);
        $refreshedPerms = $refreshedPull->json('user.permissions');
        $this->assertContains('leads.export', $refreshedPerms);

        // 4. Admin revokes with explicit deny on web
        $denyRes = $this->actingAsWeb($this->admin)
            ->putJson("/api/users/{$this->employee1->id}/permissions", [
                'overrides' => [
                    ['permission' => 'leads.export', 'effect' => 'deny'],
                ],
            ]);
        $denyRes->assertStatus(200);

        // 5. Mobile pulls sync again - effective permissions immediately exclude leads.export
        $this->actingAsMobile($this->employee1);
        $deniedPull = $this->getJson('/api/mobile/sync/pull');

        $deniedPull->assertStatus(200);
        $deniedPerms = $deniedPull->json('user.permissions');
        $this->assertNotContains('leads.export', $deniedPerms);

        // Clean up
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->delete();
    }

    public function test_phase_g21_backend_enforces_live_permission_dynamically_against_stale_token(): void
    {
        // 1. Admin grants explicit allow for leads.archive on web
        $this->actingAsWeb($this->admin)
            ->putJson("/api/users/{$this->employee1->id}/permissions", [
                'overrides' => [
                    ['permission' => 'leads.archive', 'effect' => 'allow'],
                ],
            ])->assertStatus(200);

        $lead = $this->createLead('Enterprise Software Engineering', $this->employee1->id);

        // 2. Employee archives lead -> succeeds
        $this->actingAsMobile($this->employee1);
        $archiveRes = $this->deleteJson("/api/leads/{$lead->id}");
        $archiveRes->assertStatus(200);
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);

        // Restore lead via admin
        $lead->restore();

        // 3. Admin revokes leads.archive with DENY override on web
        $this->actingAsWeb($this->admin)
            ->putJson("/api/users/{$this->employee1->id}/permissions", [
                'overrides' => [
                    ['permission' => 'leads.archive', 'effect' => 'deny'],
                ],
            ])->assertStatus(200);

        // 4. Employee immediately re-attempts archive (stale client state)
        $this->actingAsMobile($this->employee1);
        $staleAttempt = $this->deleteJson("/api/leads/{$lead->id}");

        // Backend must dynamically reject with 403 Forbidden!
        $staleAttempt->assertStatus(403);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'deleted_at' => null]);

        // Clean up
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->delete();
        $lead->forceDelete();
    }

    public function test_phase_g21_category_restricted_lead_creation_enforcement(): void
    {
        // Assign only 'Cloud Infrastructure'
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        DB::table('user_category_assignments')->insert([
            'user_id' => $this->employee1->id,
            'category_name' => 'Cloud Infrastructure',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->employee1->refresh();
        Sanctum::actingAs($this->employee1);

        // 1. Prohibited category lead creation -> 403 Forbidden
        $disallowedRes = $this->postJson('/api/leads', [
            'name' => 'Disallowed Category Lead',
            'phone' => '+91 99999 88888',
            'service_interest' => 'Retail Banking Platform',
            'status' => 'new',
        ]);

        $disallowedRes->assertStatus(403);

        // 2. Allowed category lead creation -> 201 Created
        $allowedRes = $this->postJson('/api/leads', [
            'name' => 'Allowed Category Lead',
            'phone' => '+91 99999 77777',
            'service_interest' => 'Cloud Infrastructure',
            'status' => 'new',
        ]);

        $allowedRes->assertStatus(201);
        $leadId = $allowedRes->json('lead.id');

        // Clean up
        DB::table('user_category_assignments')->where('user_id', $this->employee1->id)->delete();
        if ($leadId) {
            Lead::withTrashed()->find($leadId)?->forceDelete();
        }
    }

    /**
     * Phase G.2.2 Web-Only RBAC Security Matrix Tests
     */
    public function test_g22_01_web_admin_with_valid_session_can_access_rbac(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->withHeaders(['Referer' => 'http://localhost/'])
            ->postJson('/api/auth/web-login', [
                'email' => $this->admin->email,
                'password' => 'Admin123',
            ]);

        $loginRes->assertStatus(200);
        $this->assertNull($loginRes->json('token'));
        $this->assertEquals('admin', $loginRes->json('user.role'));

        $rolesRes = $this->withHeaders(['Referer' => 'http://localhost/'])
            ->getJson('/api/roles');

        $rolesRes->assertStatus(200);
        $this->assertNotEmpty($rolesRes->json());
        $rolesRes->assertJsonStructure([
            '*' => ['id', 'name', 'display_name', 'permission_names'],
        ]);
    }

    public function test_g22_02_android_admin_with_mobile_token_receives_403(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Samsung SM-S918B',
        ]);

        $loginRes->assertStatus(200);
        $token = $loginRes->json('token');
        $this->assertNotEmpty($token);

        $rolesRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/roles');

        $rolesRes->assertStatus(403);
        $rolesRes->assertJsonFragment([
            'message' => 'Web-only RBAC administration: Personal access tokens cannot perform this action.',
        ]);
    }

    public function test_g22_03_android_employee_with_mobile_token_receives_403(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->employee1->email,
            'password' => 'Vistabound@2026',
            'device_name' => 'Pixel 7',
        ]);

        $loginRes->assertStatus(200);
        $token = $loginRes->json('token');
        $this->assertNotEmpty($token);

        $rolesRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/roles');

        $rolesRes->assertStatus(403);
    }

    public function test_g22_04_unauthorized_web_employee_receives_403(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->withHeaders(['Referer' => 'http://localhost/'])
            ->postJson('/api/auth/web-login', [
                'email' => $this->employee1->email,
                'password' => 'Vistabound@2026',
            ]);

        $loginRes->assertStatus(200);

        $rolesRes = $this->withHeaders(['Referer' => 'http://localhost/'])
            ->getJson('/api/roles');

        $rolesRes->assertStatus(403);
        $rolesRes->assertJsonFragment([
            'message' => 'Unauthorized access.',
        ]);
    }

    public function test_g22_05_android_admin_retains_normal_crm_functionality(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Samsung SM-S918B',
        ]);
        $token = $loginRes->json('token');

        // Admin via mobile token can access normal CRM endpoints
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/leads')
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/follow-ups')
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/mobile/sync/pull')
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard/metrics')
            ->assertStatus(200);
    }

    public function test_g22_06_web_admin_retains_normal_crm_functionality(): void
    {
        auth()->forgetGuards();

        $this->withHeaders(['Referer' => 'http://localhost/'])
            ->postJson('/api/auth/web-login', [
                'email' => $this->admin->email,
                'password' => 'Admin123',
            ])->assertStatus(200);

        // Web session admin can access the same CRM endpoints
        $this->withHeaders(['Referer' => 'http://localhost/'])
            ->getJson('/api/leads')
            ->assertStatus(200);

        $this->withHeaders(['Referer' => 'http://localhost/'])
            ->getJson('/api/follow-ups')
            ->assertStatus(200);

        $this->withHeaders(['Referer' => 'http://localhost/'])
            ->getJson('/api/mobile/sync/pull')
            ->assertStatus(200);

        $this->withHeaders(['Referer' => 'http://localhost/'])
            ->getJson('/api/dashboard/metrics')
            ->assertStatus(200);
    }

    public function test_g22_07_wildcard_personal_access_token_rejected_from_rbac(): void
    {
        auth()->forgetGuards();

        // Create PAT with wildcard abilities for admin
        $pat = $this->admin->createToken('admin-wildcard', ['*'])->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $pat)
            ->getJson('/api/roles');

        $res->assertStatus(403);
        $res->assertJsonFragment([
            'message' => 'Web-only RBAC administration: Personal access tokens cannot perform this action.',
        ]);
    }

    public function test_g22_08_spoofed_client_channel_web_rejected(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Pixel 7',
        ]);
        $token = $loginRes->json('token');

        // Mobile token attempting to spoof ?client_channel=web
        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/roles?client_channel=web');

        $res->assertStatus(403);
    }

    public function test_g22_09_spoofed_x_platform_web_rejected(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Pixel 7',
        ]);
        $token = $loginRes->json('token');

        // Mobile token attempting to spoof X-Platform: web header
        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Platform' => 'web',
            'X-Client' => 'web-desk',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ])->getJson('/api/roles');

        $res->assertStatus(403);
    }

    public function test_g22_10_rbac_mutations_protected_against_tokens(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Pixel 7',
        ]);
        $token = $loginRes->json('token');

        // 1. Mobile token attempt -> 403 Forbidden
        $tokenRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/users/{$this->employee1->id}/permissions", [
                'overrides' => [
                    ['permission' => 'leads.export', 'effect' => 'allow'],
                ],
            ]);
        $tokenRes->assertStatus(403);

        // 2. Web session attempt -> 200 OK
        $webRes = $this->actingAsWeb($this->admin)
            ->putJson("/api/users/{$this->employee1->id}/permissions", [
                'overrides' => [
                    ['permission' => 'leads.export', 'effect' => 'allow'],
                ],
            ]);
        $webRes->assertStatus(200);

        // Clean up
        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->delete();
    }

    public function test_g22_11_web_session_without_functional_permission_rejected(): void
    {
        auth()->forgetGuards();

        $role = Role::first();

        // Web session user with ordinary employee role (lacking roles.manage)
        $res = $this->actingAsWeb($this->employee1)
            ->putJson("/api/roles/{$role->id}", [
                'display_name' => 'Unauthorized Role Rename',
            ]);

        $res->assertStatus(403);
        $res->assertJsonFragment([
            'message' => 'Unauthorized access.',
        ]);
    }

    public function test_g22_12_web_and_android_login_logout_flows(): void
    {
        auth()->forgetGuards();

        // 1. Web Desk Flow: login sets session, logout clears session
        $webLogin = $this->withHeaders(['Referer' => 'http://localhost/'])
            ->postJson('/api/auth/web-login', [
                'email' => $this->admin->email,
                'password' => 'Admin123',
            ]);
        $webLogin->assertStatus(200);
        $this->assertNull($webLogin->json('token'));
        $this->assertAuthenticatedAs($this->admin, 'web');

        $webLogout = $this->withHeaders(['Referer' => 'http://localhost/'])
            ->postJson('/api/auth/web-logout');
        $webLogout->assertStatus(200);
        $this->assertGuest('web');

        // Reset test client state to simulate an isolated native mobile app
        $this->flushSession();
        $this->defaultCookies = [];
        auth()->forgetGuards();

        // 2. Android Mobile Flow: login issues scoped PAT, logout revokes token
        $mobileLogin = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->employee1->email,
            'password' => 'Vistabound@2026',
            'device_name' => 'OnePlus 11',
        ]);
        $mobileLogin->assertStatus(200);
        $token = $mobileLogin->json('token');
        $this->assertNotEmpty($token);

        // Verify token in database has scoped abilities (and no wildcard or rbac:manage)
        $patModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        $this->assertNotNull($patModel);
        $this->assertNotContains('*', $patModel->abilities);
        $this->assertNotContains('rbac:manage', $patModel->abilities);
        $this->assertContains('mobile:access', $patModel->abilities);
        $this->assertContains('leads:view', $patModel->abilities);

        // Mobile logout revokes the token
        $mobileLogout = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');
        $mobileLogout->assertStatus(200);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $patModel->id]);
    }

    /**
     * Phase G.2.3 Tests: Web-Only Authorization-Affecting Employee Operations (GAP-01 Remediation)
     */
    public function test_g23_01_android_admin_pat_cannot_assign_or_change_employee_role(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Pixel 7',
        ]);
        $token = $loginRes->json('token');

        $customRole = Role::create([
            'name' => 'custom_rep_' . rand(1000, 9999),
            'display_name' => 'Custom Rep',
            'description' => 'Test role',
            'is_system' => false,
        ]);

        $originalRoleId = $this->employee1->role_id;

        // 1. Mobile PAT attempts to change employee's role_id -> 403 Forbidden
        $resUpdate = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'role_id' => $customRole->id,
            ]);

        $resUpdate->assertStatus(403);
        $resUpdate->assertJsonFragment([
            'error_code' => 'MOBILE_RBAC_FORBIDDEN',
        ]);
        $this->assertEquals($originalRoleId, $this->employee1->fresh()->role_id);

        // 2. Mobile PAT attempts to create an employee with privileged role -> 403 Forbidden
        $resCreate = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/employees', [
                'name' => 'PAT Created Rep',
                'email' => 'pat_rep_' . rand(1000, 9999) . '@example.com',
                'password' => 'Vistabound@2026',
                'role_id' => $customRole->id,
            ]);

        $resCreate->assertStatus(403);
        $resCreate->assertJsonFragment([
            'error_code' => 'MOBILE_RBAC_FORBIDDEN',
        ]);

        $customRole->delete();
    }

    public function test_g23_02_android_admin_pat_cannot_reassign_or_remove_employee_team(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Pixel 7',
        ]);
        $token = $loginRes->json('token');

        $originalTeamId = $this->employee1->team_id;
        $targetTeam = $this->teamRetail;

        // 1. Mobile PAT attempts team reassignment -> 403 Forbidden
        $resReassign = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'team_id' => $targetTeam->id,
            ]);

        $resReassign->assertStatus(403);
        $resReassign->assertJsonFragment([
            'error_code' => 'MOBILE_RBAC_FORBIDDEN',
        ]);
        $this->assertEquals($originalTeamId, $this->employee1->fresh()->team_id);

        // 2. Mobile PAT attempts team removal (null) -> 403 Forbidden
        $resRemove = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'team_id' => null,
            ]);

        $resRemove->assertStatus(403);
        $resRemove->assertJsonFragment([
            'error_code' => 'MOBILE_RBAC_FORBIDDEN',
        ]);
        $this->assertEquals($originalTeamId, $this->employee1->fresh()->team_id);
    }

    public function test_g23_03_web_admin_can_perform_authorized_role_assignment_and_removal(): void
    {
        auth()->forgetGuards();

        $customRole = Role::create([
            'name' => 'custom_desk_rep_' . rand(1000, 9999),
            'display_name' => 'Custom Desk Rep',
            'description' => 'Test role',
            'is_system' => false,
        ]);

        // 1. Web Desk Admin assigns new role -> 200 OK
        $resAssign = $this->actingAsWeb($this->admin)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'role_id' => $customRole->id,
            ]);

        $resAssign->assertStatus(200);
        $this->assertEquals($customRole->id, $this->employee1->fresh()->role_id);

        // 2. Web Desk Admin explicitly removes role (null) -> 200 OK
        $resRemove = $this->actingAsWeb($this->admin)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'role_id' => null,
            ]);

        $resRemove->assertStatus(200);
        $this->assertNull($this->employee1->fresh()->role_id);
        $this->assertEquals('employee', $this->employee1->fresh()->role);

        $customRole->delete();
    }

    public function test_g23_04_web_admin_can_perform_authorized_team_assignment_and_removal(): void
    {
        auth()->forgetGuards();

        // 1. Web Desk Admin assigns employee to teamRetail -> 200 OK
        $resAssign = $this->actingAsWeb($this->admin)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'team_id' => $this->teamRetail->id,
            ]);

        $resAssign->assertStatus(200);
        $this->assertEquals($this->teamRetail->id, $this->employee1->fresh()->team_id);

        // 2. Web Desk Admin explicitly removes employee from team (null) -> 200 OK
        $resRemove = $this->actingAsWeb($this->admin)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'team_id' => null,
            ]);

        $resRemove->assertStatus(200);
        $this->assertNull($this->employee1->fresh()->team_id);
    }

    public function test_g23_05_ordinary_permitted_employee_profile_updates_continue_to_work_via_pat_and_web(): void
    {
        auth()->forgetGuards();

        // 1. Mobile PAT updates profile fields (with role_id and team_id matching current or omitted)
        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Pixel 7',
        ]);
        $token = $loginRes->json('token');

        $newPhone = '+919988776655';
        $resMobile = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'phone' => $newPhone,
                'role_id' => $this->employee1->role_id, // Unchanged
                'team_id' => $this->employee1->team_id, // Unchanged
            ]);

        $resMobile->assertStatus(200);
        $this->assertEquals($newPhone, $this->employee1->fresh()->phone);

        // 2. Web Desk updates profile fields
        $newName = 'Ananya Sharma Updated';
        $resWeb = $this->actingAsWeb($this->admin)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'name' => $newName,
            ]);

        $resWeb->assertStatus(200);
        $this->assertEquals($newName, $this->employee1->fresh()->name);
    }

    public function test_g23_06_unauthorized_web_employee_cannot_change_authorization_affecting_fields(): void
    {
        auth()->forgetGuards();

        // Grant employee1 the functional permission 'employees.manage' ONLY, without 'roles.manage'
        $empManagePerm = Permission::where('name', 'employees.manage')->firstOrFail();
        DB::table('user_has_permissions')->updateOrInsert(
            ['user_id' => $this->employee1->id, 'permission_id' => $empManagePerm->id],
            ['effect' => 'allow', 'granted_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]
        );
        $this->employee1->refresh();

        // Verify user has employees.manage but NOT roles.manage
        $this->assertTrue($this->employee1->hasPermission('employees.manage'));
        $this->assertFalse($this->employee1->hasPermission('roles.manage'));

        // 1. Ordinary permitted employee attempts to change employee2's role_id on Web Desk -> 403 Forbidden
        $resRole = $this->actingAsWeb($this->employee1)
            ->putJson("/api/employees/{$this->employee2->id}", [
                'role_id' => 1,
            ]);

        $resRole->assertStatus(403);
        $resRole->assertJsonFragment([
            'error_code' => 'INSUFFICIENT_PERMISSIONS',
        ]);

        // 2. Ordinary permitted employee attempts to change employee2's team_id on Web Desk -> 403 Forbidden
        $resTeam = $this->actingAsWeb($this->employee1)
            ->putJson("/api/employees/{$this->employee2->id}", [
                'team_id' => $this->teamRetail->id,
            ]);

        $resTeam->assertStatus(403);
        $resTeam->assertJsonFragment([
            'error_code' => 'INSUFFICIENT_PERMISSIONS',
        ]);

        // 3. But permitted employee CAN update profile-only fields (name/phone) on Web Desk -> 200 OK
        $resProfile = $this->actingAsWeb($this->employee1)
            ->putJson("/api/employees/{$this->employee2->id}", [
                'name' => 'Rohit Verma Directory Edit',
            ]);

        $resProfile->assertStatus(200);
        $this->assertEquals('Rohit Verma Directory Edit', $this->employee2->fresh()->name);

        DB::table('user_has_permissions')->where('user_id', $this->employee1->id)->delete();
    }

    public function test_g23_07_tenant_isolation_remains_enforced(): void
    {
        auth()->forgetGuards();

        // Ordinary employee without employees.manage is rejected from directory
        $this->assertFalse($this->employee2->hasPermission('employees.manage'));

        $this->actingAsWeb($this->employee2)
            ->getJson('/api/employees')
            ->assertStatus(403);

        $this->actingAsWeb($this->employee2)
            ->putJson("/api/employees/{$this->employee1->id}", ['name' => 'Unauthorized'])
            ->assertStatus(403);
    }

    public function test_g23_08_audit_entries_generated_for_successful_authorization_changes(): void
    {
        auth()->forgetGuards();

        $customRole = Role::create([
            'name' => 'audit_test_role_' . rand(1000, 9999),
            'display_name' => 'Audit Test Role',
            'is_system' => false,
        ]);

        // Web admin changes role and team
        $res = $this->actingAsWeb($this->admin)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'role_id' => $customRole->id,
                'team_id' => $this->teamRetail->id,
            ]);

        $res->assertStatus(200);

        // Verify audit logs exist
        $this->assertDatabaseHas('permission_audit_logs', [
            'actor_id' => $this->admin->id,
            'target_type' => 'user',
            'target_id' => $this->employee1->id,
            'action' => 'employee_role_changed',
        ]);

        $this->assertDatabaseHas('permission_audit_logs', [
            'actor_id' => $this->admin->id,
            'target_type' => 'user',
            'target_id' => $this->employee1->id,
            'action' => 'employee_team_changed',
        ]);

        $customRole->delete();
    }

    public function test_g23_09_failed_operations_do_not_leave_partial_database_changes(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Pixel 7',
        ]);
        $token = $loginRes->json('token');

        $originalName = $this->employee1->name;
        $originalRoleId = $this->employee1->role_id;

        // Attacker attempts mixed request: change name AND change role via mobile PAT
        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'name' => 'Tampered Hacker Name',
                'role_id' => 1, // Admin role
            ]);

        $res->assertStatus(403);

        // Assert database is 100% UNCHANGED (neither name nor role was altered)
        $freshUser = $this->employee1->fresh();
        $this->assertEquals($originalName, $freshUser->name);
        $this->assertEquals($originalRoleId, $freshUser->role_id);
    }

    public function test_g23_10_numeric_string_vs_integer_id_comparison_does_not_trigger_false_authorization_check(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Pixel 7',
        ]);
        $token = $loginRes->json('token');

        // Employee currently has integer IDs
        $currentRoleId = (string) $this->employee1->role_id;
        $currentTeamId = (string) $this->employee1->team_id;

        // Form submits identical values as numeric strings along with updated phone number
        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'phone' => '+919911223344',
                'role_id' => $currentRoleId, // Numeric string e.g. "2"
                'team_id' => $currentTeamId, // Numeric string e.g. "1"
            ]);

        $res->assertStatus(200);
        $this->assertEquals('+919911223344', $this->employee1->fresh()->phone);
    }

    public function test_g23_11_role_escalation_through_legacy_role_field_is_blocked(): void
    {
        auth()->forgetGuards();

        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Pixel 7',
        ]);
        $token = $loginRes->json('token');

        $this->assertEquals('employee', $this->employee1->role);

        // Attempt privilege escalation via legacy 'role' slug
        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson("/api/employees/{$this->employee1->id}", [
                'role' => 'admin',
            ]);

        $res->assertStatus(403);
        $this->assertEquals('employee', $this->employee1->fresh()->role);
    }

    public function test_g23_12_employee_creation_without_privileged_role_succeeds_under_employees_manage(): void
    {
        auth()->forgetGuards();

        // Mobile PAT creates unprivileged employee without role/team assignment -> 201 Created
        $loginRes = $this->postJson('/api/auth/mobile-login', [
            'email' => $this->admin->email,
            'password' => 'Admin123',
            'device_name' => 'Pixel 7',
        ]);
        $token = $loginRes->json('token');

        $email = 'unprivileged_' . rand(1000, 9999) . '@example.com';
        $resMobile = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/employees', [
                'name' => 'Standard Rep Unprivileged',
                'email' => $email,
                'password' => 'Vistabound@2026',
            ]);

        $resMobile->assertStatus(201);
        $newId = $resMobile->json('employee.id');
        $this->assertNotNull($newId);

        $createdEmp = User::find($newId);
        $this->assertEquals('employee', $createdEmp->role);
        $this->assertNull($createdEmp->role_id);
        $this->assertNull($createdEmp->team_id);

        $createdEmp->delete();
    }
}
