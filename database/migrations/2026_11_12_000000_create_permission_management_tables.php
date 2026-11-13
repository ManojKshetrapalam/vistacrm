<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for the Granular Role-Based Access Control and Permission Management System.
     */
    public function up(): void
    {
        // 1. Roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('display_name', 128);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // 2. Permissions table
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('display_name', 128);
            $table->string('group', 64)->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 3. Teams table (for team visibility scope)
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 4. Attach role_id and team_id to users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->after('role_id')->constrained('teams')->nullOnDelete();
        });

        // 5. Role permissions pivot (Role Templates)
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // 6. User permission overrides (Inherit / Allow / Deny Model)
        Schema::create('user_has_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->enum('effect', ['allow', 'deny'])->default('allow');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'permission_id']);
        });

        // 7. Role visibility scopes (Default: assigned_only)
        Schema::create('role_visibility_scopes', function (Blueprint $table) {
            $table->foreignId('role_id')->primary()->constrained('roles')->cascadeOnDelete();
            $table->enum('scope', ['assigned_only', 'team_only', 'all'])->default('assigned_only');
            $table->timestamps();
        });

        // 8. User visibility overrides (Inherit / Assigned / Team / All)
        Schema::create('user_visibility_overrides', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->enum('scope', ['inherit', 'assigned_only', 'team_only', 'all'])->default('inherit');
            $table->string('reason')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 9. Role category assignments
        Schema::create('role_category_assignments', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('category_name', 128);
            $table->timestamps();
            $table->primary(['role_id', 'category_name']);
        });

        // 10. User category assignments (Allow / Deny specific categories)
        Schema::create('user_category_assignments', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category_name', 128);
            $table->boolean('is_allowed')->default(true);
            $table->timestamps();
            $table->primary(['user_id', 'category_name']);
        });

        // 11. Centralized Permission Audit Logs
        Schema::create('permission_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('target_type', ['user', 'role'])->index();
            $table->unsignedBigInteger('target_id')->index();
            $table->string('action', 64)->index();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_audit_logs');
        Schema::dropIfExists('user_category_assignments');
        Schema::dropIfExists('role_category_assignments');
        Schema::dropIfExists('user_visibility_overrides');
        Schema::dropIfExists('role_visibility_scopes');
        Schema::dropIfExists('user_has_permissions');
        Schema::dropIfExists('role_has_permissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('teams');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
