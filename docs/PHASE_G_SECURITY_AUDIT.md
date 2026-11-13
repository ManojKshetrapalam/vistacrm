# VISTABOUND CRM — PHASE G POST-IMPLEMENTATION SECURITY & PRODUCTION READINESS AUDIT

**Date:** 2026-11-13  
**Repository:** `E:\Projects\vistacrm`  
**Current Baseline Commit:** `a7797fb`  
**Audit Purpose:** Independent security, authorization, database migration, and mobile compatibility evaluation of the Granular Role-Based Access Control (RBAC) implementation prior to staging or production deployment.  
**Classification:** Defensive Audit & Production Verification  

---

## Executive Summary

Phase G introduced a centralized Granular RBAC and User Permission Management System across the Vistabound CRM web portal (Laravel 11 / Vue 3) and mobile application (Capacitor 8 Android). 

This audit evaluated:
1. Backward compatibility for existing production administrators and sales representatives.
2. Authorization boundaries, tenant isolation, and direct object-level access controls across all API endpoints.
3. Category (vertical) access restrictions and edge-case behaviors (null, empty, case-sensitivity).
4. Immutability and coverage of administrative permission audit logging.
5. Android Capacitor native buildability, application identifier integrity, and configuration isolation.
6. Production database schema safety, indexing, foreign keys, and rollback stability.
7. Automated test verification covering positive and negative security scenarios.

### Audit Verdict: **NOT PRODUCTION READY PENDING REMEDIATION (NO-GO)**

While the architecture cleanly satisfies the required Inherit / Allow / Deny resolution model and all 51 automated regression tests pass, the audit identified **2 High-Severity** and **3 Medium-Severity** findings that must be addressed prior to deploying to the production server (`myworks.sbs` / Hostinger VPS):
- **Finding SEC-01 [HIGH]:** Broken Object-Level Authorization (BOLA) in `LeadController@destroy`, `restore`, and `assign` allows users with permissions to archive, restore, or reassign leads outside their assigned visibility scope.
- **Finding SEC-02 [HIGH]:** Production database migration does not seed baseline roles and permissions, creating an empty access control catalog if standard `php artisan migrate` is executed without explicit seed intervention.
- **Finding SEC-03 [MEDIUM]:** Missing vertical category restriction validation on lead creation (`POST /api/leads`), permitting users to submit leads under disallowed verticals which subsequently become inaccessible "orphaned" records to the creator.
- **Finding SEC-04 [MEDIUM]:** Discrepancy between query scope `accessibleBy()` (which excludes `NULL` service interests) and model check `canAccessLead()` (which permits `NULL` service interests for restricted users).
- **Finding SEC-05 [MEDIUM]:** Role template and team assignments executed via `EmployeeController@update` bypass `permission_audit_logs`.

---

## 1. Architecture Audit

### 1.1 Resolution Hierarchy
The backend (`app/Models/User.php`) implements an Inherit / Allow / Deny authorization hierarchy:
1. **Administrator Override (`isAdmin()`):** Bypasses all domain permission and visibility checks, granting universal access.
2. **User Explicit Override (`user_has_permissions`):**
   - `effect = 'allow'`: Explicitly grants permission even if omitted from role template.
   - `effect = 'deny'`: Explicitly revokes permission even if granted by role template.
3. **Role Template Default (`role_has_permissions`):** Inherits permissions associated with `role_id`.
4. **Hardcoded Standard Fallback:** If `role_id` is null and `role === 'employee'`, automatically grants default baseline (`leads.view`, `leads.create`, `leads.edit`, `follow_ups.manage`, `whatsapp.send`).
5. **Default Fallback:** Revoked / Denied.

### 1.2 Architectural Findings
- **Strengths:**
  - Complete backend single-source-of-truth. Web and mobile consume identical effective permission profiles.
  - Zero external dependencies introduced, maintaining strict standalone VPS architecture.
  - Clean separation of concern between functional permissions (`hasPermission`), visibility scopes (`getEffectiveVisibilityScope`), and domain category filters (`getEffectiveAllowedCategories`).
- **Areas for Improvement:**
  - Route model binding in controllers resolves model instances before checking visibility scope, requiring explicit `$user->canAccessLead($lead)` guards on every method.

---

## 2. API Authorization Coverage Matrix

Every protected API endpoint was audited for authentication, authorization gates, record-level scoping, and direct ID manipulation (BOLA/IDOR):

| Route | HTTP | Controller Action | Gate Check | Record Scoping | Security Verdict |
|---|---|---|---|---|---|
| `/api/dashboard/metrics` | GET | `DashboardController@metrics` | Authenticated | `Lead::accessibleBy($user)` | **PASS** |
| `/api/leads` | GET | `LeadController@index` | `leads.view` / `isAdmin` | `Lead::accessibleBy($user)` | **PASS** |
| `/api/leads/{id}` | GET | `LeadController@show` | Authenticated | `$user->canAccessLead($lead)` | **PASS** |
| `/api/leads` | POST | `LeadController@store` | `leads.create` | Assigned to creator or target | **PARTIAL** (See SEC-03) |
| `/api/leads/{id}` | PUT | `LeadController@update` | `leads.edit` | `$user->canAccessLead($lead)` | **PASS** |
| `/api/leads/{id}` | DELETE | `LeadController@destroy` | `leads.archive` | **NONE** (Direct Model Binding) | **FAIL** (See SEC-01) |
| `/api/leads/{id}/restore` | POST | `LeadController@restore` | `leads.restore` | **NONE** (`onlyTrashed()->findOrFail`) | **FAIL** (See SEC-01) |
| `/api/leads/{id}/force` | DELETE | `LeadController@forceDelete` | `leads.force_delete` | Admin / Permission gated | **PASS** (Admin only) |
| `/api/leads/{id}/assign` | POST | `LeadController@assign` | `leads.assign` | **NONE** (Direct Model Binding) | **FAIL** (See SEC-01) |
| `/api/leads/{id}/call` | POST | `LeadController@logCall` | `leads.edit` | `$user->canAccessLead($lead)` | **PASS** |
| `/api/leads/{id}/note` | POST | `LeadController@addNote` | `leads.edit` | `$user->canAccessLead($lead)` | **PASS** |
| `/api/leads/export` | GET | `LeadController@export` | `leads.export` | `Lead::accessibleBy($user)` | **PASS** |
| `/api/follow-ups` | GET | `FollowUpController@index` | Authenticated | Scoped by User / Team / All | **PASS** |
| `/api/leads/{id}/follow-ups` | POST | `FollowUpController@store` | `follow_ups.manage` | `$user->canAccessLead($lead)` | **PASS** |
| `/api/follow-ups/{id}` | PUT | `FollowUpController@update` | Ownership or Admin | `$followUp->user_id === $user->id` | **PASS** |
| `/api/follow-ups/{id}/complete`| POST | `FollowUpController@complete` | Ownership or Admin | `$followUp->user_id === $user->id` | **PASS** |
| `/api/follow-ups/{id}/cancel` | POST | `FollowUpController@cancel` | Ownership or Admin | `$followUp->user_id === $user->id` | **PASS** |
| `/api/follow-ups/{id}` | DELETE | `FollowUpController@destroy` | Admin Only | Admin Only | **PASS** |
| `/api/import/*` | ALL | `ImportController` | `leads.import` | N/A | **PASS** |
| `/api/whatsapp/*` | ALL | `WhatsAppController` | `whatsapp.send` | `$user->canAccessLead($lead)` | **PASS** |
| `/api/employees` | GET | `EmployeeController@index` | `employees.manage` | Admin / Authorized | **PASS** |
| `/api/employees` | POST | `EmployeeController@store` | `employees.manage` | Admin / Authorized | **PASS** |
| `/api/employees/{id}` | PUT | `EmployeeController@update` | `employees.manage` | Admin / Authorized | **PARTIAL** (See SEC-05) |
| `/api/employees/{id}/toggle-status` | POST | `EmployeeController@toggleStatus`| `employees.manage` | Admin protection guards | **PASS** |
| `/api/roles` | ALL | `RolePermissionController` | `roles.manage` | Admin / Authorized | **PASS** |
| `/api/users/{id}/*` | ALL | `RolePermissionController` | `roles.manage` | Admin / Authorized | **PASS** |
| `/api/audit-logs` | GET | `RolePermissionController@auditLogs` | `roles.manage` | Admin / Authorized | **PASS** |
| `/api/mobile/sync/pull` | GET | `MobileSyncController@pull` | Authenticated | `Lead::accessibleBy($user)` | **PASS** |
| `/api/mobile/sync/push` | POST | `MobileSyncController@push` | Permission + Scope | `Lead::accessibleBy($user)->find()` | **PASS** |

---

## 3. Existing User Migration Assessment

### 3.1 Schema Compatibility
The database migration adds two nullable foreign keys to `users`:
- `role_id` (nullable, references `roles.id`)
- `team_id` (nullable, references `teams.id`)

The legacy string column `users.role` (`'admin'` or `'employee'`) is completely preserved and remains untouched.

### 3.2 Access Continuity for Existing Production Users
1. **Existing Administrators (`role = 'admin'`, `role_id = NULL`):**
   - Evaluated by `User::isAdmin()`, which explicitly checks `$this->role === 'admin'`.
   - Access to all modules, administrative APIs, and pipeline visibility (`all`) is 100% maintained with zero downtime or permission loss.
2. **Existing Sales Representatives (`role = 'employee'`, `role_id = NULL`):**
   - Evaluated by `User::hasPermission()`, falling back to `$defaultEmployeePermissions` (`leads.view`, `leads.create`, `leads.edit`, `follow_ups.manage`, `whatsapp.send`).
   - Pipeline visibility defaults to `'assigned_only'`.
   - Category restrictions default to `null` (unrestricted).
   - All assigned leads and follow-ups remain accessible.
3. **Session & Token Preservation:**
   - Existing Sanctum personal access tokens (`personal_access_tokens`) and mobile push tokens (`device_tokens`) continue to authenticate existing users without forcing re-login.

### 3.3 Migration Mapping Plan
Upon production deployment, existing users should be mapped to the standardized role templates:

| Existing `users.role` | Recommended Target Role | Assigned `role_id` | Visibility Scope | Category Scope |
|---|---|---|---|---|
| `admin` | Administrator (`admin`) | 1 | `all` | Unrestricted (`null`) |
| `employee` (Management) | Sales Manager (`sales_manager`) | 2 | `team_only` | Unrestricted (`null`) |
| `employee` (Standard Rep) | Sales Executive (`sales_executive`) | 3 | `assigned_only` | Unrestricted or Verticals |
| `employee` (Finance/Ops) | Accounts Executive (`accounts_executive`) | 4 | `all` | Unrestricted |

---

## 4. Category (Vertical) Access Assessment

### 4.1 Storage & Structure
- Verticals are defined in `crm_settings.service_categories`:
  - `IT Infrastructure & Hardware AMC`
  - `Cloud & DevOps Solutions`
  - `Enterprise Software Engineering`
  - `Incubation & Strategic Investment`
  - `Hospitality Management`
  - `Retail & Commerce Solutions`
- Assigned to roles via `role_category_assignments` (`category_name`).
- Assigned to users via `user_category_assignments` (`category_name`, `is_allowed`).

### 4.2 Behavior on Edge Cases
- **Unrestricted State:** When neither the role nor the user has category assignments, `getEffectiveAllowedCategories()` returns `null`, granting pipeline access across all verticals.
- **Empty Array Handling (`[]`):** If all categories are denied (`$effective = []`), `scopeAccessibleBy` restricts the query to zero records (`whereIn('service_interest', [])`), preventing accidental elevation to unrestricted access.
- **Case Sensitivity Discrepancy (Finding SEC-06):** In PHP, `in_array()` is strictly case-sensitive, while MySQL queries under `utf8mb4_unicode_ci` are case-insensitive. A category saved as `"cloud & devops solutions"` would be returned by MySQL `whereIn()`, but rejected by PHP `canAccessLead()`. String trimming and case normalization should be enforced.
- **Renamed Categories:** If an admin updates category names in CRM Settings, existing lead records retain the historical string. Because category mapping uses raw string keys rather than a relational ID, renaming a category in settings leaves existing assignments orphaned unless updated via a database script.

---

## 5. Audit Log Security

### 5.1 Immutability
- Table: `permission_audit_logs`.
- **API Protection:** The API routes expose strictly `GET /api/audit-logs`. No `POST`, `PUT`, `PATCH`, or `DELETE` endpoints exist for audit logs.
- **Foreign Key Safety:** Foreign key `actor_id` uses `nullOnDelete()`. If an administrator account is deleted, all past audit logs authored by that administrator are preserved with `actor_id = NULL`.
- **Target Independence:** `target_id` is an unconstrained `unsignedBigInteger` alongside `target_type` (`'user'` or `'role'`). Deleting a role or employee does not cascade to or erase audit trail entries.

### 5.2 Audit Trigger Coverage
Audit logging is fully integrated into `RolePermissionController.php`:
- `storeRole` -> `role_created`
- `updateRole` -> `role_updated`
- `deleteRole` -> `role_deleted`
- `updateUserPermissions` -> `user_permissions_updated`
- `updateUserVisibility` -> `visibility_scope_updated`
- `updateUserCategories` -> `category_assignments_updated`
- `resetUserPermissions` -> `permissions_reset_to_role_default`

**Audit Gap (Finding SEC-05):** Role template and team assignment changes executed via `EmployeeController@update` do not invoke `PermissionAuditLog::record()`.

---

## 6. Android Native Build Verification

### 6.1 Native Configuration
- **Application ID:** `com.vistabound.crm` (Confirmed preserved in `android/app/build.gradle` and `capacitor.config.ts`).
- **Version Code:** `2`
- **Version Name:** `1.0.1`
- **Namespace:** `com.vistabound.crm`
- **Compile SDK:** 36 | **Target SDK:** 36 | **Min SDK:** 24

### 6.2 Production API Configuration & URL Leaks
- Inspected `resources/js/api/axios.ts`:
  - `Capacitor.isNativePlatform()` dynamically defaults to `https://vistaboundintegrated.com/CRM/api`.
  - Development URLs (`http://10.0.2.2:8000`, private LAN IPs) are only activated if an explicit development override is stored in `localStorage.getItem('vb_crm_server_url')`.
  - Production release builds contain zero hardcoded local development IPs.

### 6.3 Native Build Output & Verification
- **Web Compilation:** `npm run build:mobile` compiled 1,864 modules cleanly to `dist/` in 7.98s.
- **Capacitor Sync:** `npx cap sync android` updated web assets in `android/app/src/main/assets/public/` and registered 7 native Capacitor plugins.
- **Gradle Native Compilation:** Executed `cd android; .\gradlew.bat assembleDebug`.
  - **Gradle Version:** 8.14 (Downloaded and configured via Gradle wrapper).
  - **Android SDK Tools:** Build-Tools 35.0.0 installed into `C:\Users\Manoj\AppData\Local\Android\Sdk`.
  - **Execution Outcome:** `BUILD SUCCESSFUL in 25m 59s` (307 actionable tasks executed).
  - **Generated APK:** `android/app/build/outputs/apk/debug/app-debug.apk` (11,316,295 bytes / 10.79 MB).
  - **Output Metadata (`output-metadata.json`):** Verified `applicationId: "com.vistabound.crm"`, `variantName: "debug"`, `versionCode: 2`, `versionName: "1.0.1"`, `minSdkVersionForDexing: 24`.
- **Publication Guard:** The APK was compiled strictly for local native verification. No release APK was published or distributed.

---

## 7. Production Database Migration Risks

### 7.1 Schema Integrity Review
- **Foreign Key Indexing:** Foreign keys on `users` (`role_id`, `team_id`), `role_has_permissions`, `user_has_permissions`, `role_visibility_scopes`, `user_visibility_overrides`, and `permission_audit_logs` are indexed and correctly constrained.
- **Rollback Safety:** `down()` method drops foreign keys from `users` prior to dropping `teams` and `roles`, guaranteeing clean reversibility without constraint violations.
- **Zero Destructive Alterations:** No existing columns are renamed, modified, or dropped on the `users` table or any other table.

### 7.2 The Baseline Data Risk (SEC-02)
Running `php artisan migrate` on production will create the tables but will leave `roles`, `permissions`, and `teams` empty.
- Because permissions are currently seeded via `PermissionsSeeder.php`, running `migrate` alone will not populate the initial permission catalog or system roles.
- **Mandatory Action:** Production deployment must execute `php artisan db:seed --class=PermissionsSeeder --force` immediately following migration.

---

## 8. Automated Test Results

The full test suite was executed, including new security test scenarios:

```
   PASS  Tests\Unit\ExampleTest (1 test)
   PASS  Tests\Feature\AdminActionsWorkflowTest (14 tests)
   PASS  Tests\Feature\CrmWorkflowTest (5 tests)
   PASS  Tests\Feature\ExampleTest (1 test)
   PASS  Tests\Feature\FollowUpReminderTest (9 tests)
   PASS  Tests\Feature\LeadImportWorkflowTest (2 tests)
   PASS  Tests\Feature\MobileSyncWorkflowTest (5 tests)
   PASS  Tests\Feature\NotificationPollingWorkflowTest (8 tests)
   PASS  Tests\Feature\PermissionManagementTest (24 tests)
   ----------------------------------------------------------------------
   Total: 69 tests passed (313 assertions) | Duration: 6.99s
```

All security verification and regression tests passed:
- `test_role_template_inheritance` (Verified)
- `test_user_explicit_allow_overrides_role_omission` (Verified)
- `test_user_explicit_deny_overrides_role_grant` (Verified)
- `test_reset_user_permissions_reverts_to_role_default` (Verified)
- `test_lead_visibility_scopes_assigned_team_and_all` (Verified)
- `test_category_based_vertical_restrictions` (Verified)
- `test_unauthorized_user_cannot_access_roles_endpoints` (Verified)
- `test_direct_id_manipulation_prevented_on_lead_show_and_update` (Verified)
- `test_category_restrictions_enforced_during_mobile_sync_push` (Verified)
- `test_sec01_unauthorized_lead_archive_blocked_by_visibility` (Verified)
- `test_sec01_unauthorized_lead_restore_blocked_by_visibility` (Verified)
- `test_sec01_unauthorized_lead_reassignment_blocked_by_visibility` (Verified)
- `test_sec01_lead_assignment_to_inactive_user_rejected` (Verified)
- `test_sec01_lead_assignment_to_category_ineligible_user_rejected` (Verified)
- `test_sec01_authorized_user_can_archive_restore_and_assign_permitted_lead` (Verified)
- `test_sec02_baseline_permissions_initialize_and_custom_roles_survive` (Verified)
- `test_sec02_ambiguous_user_responsibility_preserved_unmapped` (Verified)
- `test_sec03_restricted_user_cannot_create_lead_in_unauthorized_category` (Verified)
- `test_sec03_restricted_user_cannot_create_lead_without_category` (Verified)
- `test_sec03_restricted_user_can_create_lead_in_authorized_category` (Verified)
- `test_sec03_mobile_offline_sync_push_rejects_unauthorized_category_lead` (Verified)
- `test_sec03_csv_import_enforces_user_category_restrictions` (Verified)
- `test_sec04_null_and_empty_category_leads_uniformly_inaccessible_to_restricted_user` (Verified)
- `test_sec05_employee_creation_and_updates_generate_immutable_audit_logs` (Verified)
- `test_sec06_case_insensitive_and_whitespace_category_normalization` (Verified)

---

## 9. Comprehensive Findings Classification & Remediation Status

| ID | Title | Severity | Component | Remediated in Branch | Status |
|---|---|---|---|---|---|
| **SEC-01** | BOLA / Scope bypass in Lead archive, restore, and assign | **HIGH** | `LeadController.php` | `fix/rbac-security-remediation` | **REMEDIATED** |
| **SEC-02** | Missing baseline role/permission seeding in production migration | **HIGH** | `database/migrations` | `fix/rbac-security-remediation` | **REMEDIATED** |
| **SEC-03** | Lead creation ignores user vertical category restrictions | **MEDIUM** | `LeadController`, `LeadImportService`, `MobileSyncController` | `fix/rbac-security-remediation` | **REMEDIATED** |
| **SEC-04** | Category discrepancy on leads with `service_interest = NULL` | **MEDIUM** | `User.php` / `Lead.php` | `fix/rbac-security-remediation` | **REMEDIATED** |
| **SEC-05** | Employee role/team updates bypass permission audit trail | **MEDIUM** | `EmployeeController.php` | `fix/rbac-security-remediation` | **REMEDIATED** |
| **SEC-06** | Case-sensitivity mismatch between PHP and MySQL collation | **LOW** | `User.php` / `Lead.php` | `fix/rbac-security-remediation` | **REMEDIATED** |

---

## 10. Detailed Remediation Audit & Evidence (Phase G.2)

### 10.1 SEC-01: Lead Broken Object Level Authorization (BOLA)
- **Original Finding:** `destroy()`, `restore()`, and `assign()` evaluated only functional permissions without enforcing record-level visibility (`$user->canAccessLead($lead)`). Furthermore, `assign()` accepted destination users without verifying activity status or category eligibility.
- **Root Cause:** Authorization check stopped at `$user->hasPermission(...)` and omitted record instance authorization.
- **Remediation:**
  - Added `$user->canAccessLead($lead)` check to `destroy()`, `restore()`, `forceDelete()`, and `assign()`. Unauthorized requests are rejected with HTTP 403 (`Unauthorized access to this lead.`) without disclosing lead metadata.
  - Implemented centralized destination user validation helper `validateAssignee(User $actor, ?int $targetUserId, ?string $serviceInterest)`.
  - Enforced that destination users must exist and be active (`is_active = true`), belong to the acting user's team if the actor's scope is `team_only`, and possess permission for the lead's service category (`$targetUser->isCategoryAllowed($serviceInterest)`).
- **Files Modified:** `app/Http/Controllers/Api/LeadController.php`
- **Regression Tests:**
  - `test_sec01_unauthorized_lead_archive_blocked_by_visibility`
  - `test_sec01_unauthorized_lead_restore_blocked_by_visibility`
  - `test_sec01_unauthorized_lead_reassignment_blocked_by_visibility`
  - `test_sec01_lead_assignment_to_inactive_user_rejected`
  - `test_sec01_lead_assignment_to_category_ineligible_user_rejected`
  - `test_sec01_authorized_user_can_archive_restore_and_assign_permitted_lead`
- **Evidence:** All 6 tests passing (Status: Code Remediation PASSED, Local Verification PASSED).

### 10.2 SEC-02: Permission Catalog Initialization & User Mapping Safety
- **Original Finding:** `2026_11_12_000000_create_permission_management_tables.php` created empty tables, leaving production unusable after `migrate` unless `PermissionsSeeder` was manually executed. Additionally, rerun of seeder wiped out role customizations via `sync()`.
- **Root Cause:** Data seeding was decoupled from schema migrations, and user mapping risk existed if unmapped employees were blindly forced into `sales_executive`.
- **Remediation:**
  - Created version-controlled data migration `database/migrations/2026_11_13_000001_seed_baseline_roles_and_permissions.php`.
  - Seeded 14 baseline permissions, 2 baseline teams, and 4 baseline system roles idempotently using `firstOrCreate`.
  - System roles only attach baseline permissions and visibility scopes if no permissions/scopes are currently configured, preserving all custom administrator adjustments.
  - User mapping strictly updates users where `role_id IS NULL`:
    - Verified admin -> `admin`
    - Verified sales manager (`sales.manager@vistaboundintegrated.com`) -> `sales_manager`
    - Verified sales executives -> `sales_executive`
    - Verified accounts employee (`accounts@vistaboundintegrated.com`) -> `accounts_executive`
    - Ambiguous responsibility -> Left unmapped (`role_id = NULL`) and logged to prevent guessing.
  - Updated `database/seeders/PermissionsSeeder.php` with non-destructive preservation logic.
  - Migration `down()` is non-destructive to prevent data loss.
- **Files Created/Modified:**
  - `database/migrations/2026_11_13_000001_seed_baseline_roles_and_permissions.php` (New)
  - `database/seeders/PermissionsSeeder.php`
- **Regression Tests:**
  - `test_sec02_baseline_permissions_initialize_and_custom_roles_survive`
  - `test_sec02_ambiguous_user_responsibility_preserved_unmapped`
- **Evidence:** Migration executed idempotently in 70ms; repeated migrate/seed runs preserve data (Status: Code Remediation PASSED, Local Verification PASSED).

### 10.3 SEC-03: Category Validation on All Lead Creation Paths
- **Original Finding:** Lead creation endpoints accepted arbitrary `service_interest` without validating against the creator's `$user->getEffectiveAllowedCategories()`.
- **Root Cause:** Absence of vertical category validation at backend creation boundaries.
- **Remediation:**
  - `LeadController@store`: For category-restricted creators, missing/empty category returns HTTP 422; unauthorized category returns HTTP 403. Also validated destination `assigned_to` eligibility.
  - `LeadImportService@processImport` & `ImportController@process`: Validated row categories against creator's `$user->isCategoryAllowed($service)`. Rows with unauthorized categories fail with clear diagnostic feedback. Target assignee activity verified.
  - `MobileSyncController@push`: For offline `create_lead` mutations, unauthorized categories are rejected with `status: 'forbidden'`, message: `'Unauthorized category'`.
- **Files Modified:**
  - `app/Http/Controllers/Api/LeadController.php`
  - `app/Http/Controllers/Api/ImportController.php`
  - `app/Services/LeadImportService.php`
  - `app/Http/Controllers/Api/MobileSyncController.php`
- **Regression Tests:**
  - `test_sec03_restricted_user_cannot_create_lead_in_unauthorized_category`
  - `test_sec03_restricted_user_cannot_create_lead_without_category`
  - `test_sec03_restricted_user_can_create_lead_in_authorized_category`
  - `test_sec03_csv_import_enforces_user_category_restrictions`
  - `test_sec03_mobile_offline_sync_push_rejects_unauthorized_category_lead`
- **Evidence:** All 5 tests passing across API, CSV, and Mobile push (Status: Code Remediation PASSED, Local Verification PASSED).

### 10.4 SEC-04: NULL Category Access Consistency
- **Original Finding:** `Lead::scopeAccessibleBy` filtered out leads where `service_interest IS NULL` when restrictions existed, while `User::canAccessLead` bypassed category verification for NULL values via `!empty()`, allowing restricted users to view unclassified leads via direct ID (`show`).
- **Root Cause:** Logic mismatch between SQL query scope and in-memory PHP record check.
- **Remediation:**
  - Established consistent policy: Category-restricted users are restricted strictly to explicitly allowed verticals. Unclassified leads (`service_interest` is NULL, empty, or whitespace) are strictly inaccessible to restricted users.
  - `User::canAccessLead`: Calls `$this->isCategoryAllowed($lead->service_interest)`, which returns `false` if category is null/empty for restricted accounts.
  - `Lead::scopeAccessibleBy`: Enforces `whereNotNull('service_interest')->whereRaw("TRIM(service_interest) != ''")->whereRaw("LOWER(TRIM(service_interest)) IN (...)")`.
- **Files Modified:**
  - `app/Models/User.php`
  - `app/Models/Lead.php`
- **Regression Tests:**
  - `test_sec04_null_and_empty_category_leads_uniformly_inaccessible_to_restricted_user`
- **Evidence:** Verified identical exclusion across query scope and direct record lookup (Status: Code Remediation PASSED, Local Verification PASSED).

### 10.5 SEC-05: Employee Permission Audit Trail
- **Original Finding:** Role and team updates executed via `EmployeeController@update` bypassed `permission_audit_logs`.
- **Root Cause:** Audit calls were only present in `RolePermissionController`.
- **Remediation:**
  - `EmployeeController@store`: Wrapped in `DB::transaction()` with `PermissionAuditLog::record()` for `employee_created` capturing actor ID, target employee ID, initial role, team, and client IP.
  - `EmployeeController@update`: Wrapped in `DB::transaction()`. Compared before/after values to log `employee_role_changed` (with old/new role and role ID) and `employee_team_changed` (with old/new team ID) with client IP.
- **Files Modified:** `app/Http/Controllers/Api/EmployeeController.php`
- **Regression Tests:**
  - `test_sec05_employee_creation_and_updates_generate_immutable_audit_logs`
- **Evidence:** Verified immutable audit log records generated with before/after state, actor, target, client IP, and rollback integrity (Status: Code Remediation PASSED, Local Verification PASSED).

### 10.6 SEC-06: Category Normalization (Case & Whitespace)
- **Original Finding:** String casing and whitespace discrepancies between PHP (`in_array`) and MySQL collations (`utf8mb4_unicode_ci`) caused inconsistent access outcomes.
- **Root Cause:** Lack of standardized string normalization helper across data entry and validation.
- **Remediation:**
  - Added centralized helper `Lead::normalizeCategory(?string $category): ?string` using `mb_strtolower(trim($category), 'UTF-8')`.
  - Added `User::isCategoryAllowed(?string $category): bool` helper.
  - Enforced string trimming on category assignment storage in `RolePermissionController@updateUserCategories` and `Role@syncAllowedCategories`.
  - Applied `LOWER(TRIM(service_interest))` in SQL query scopes for exact collation and whitespace parity.
- **Files Modified:**
  - `app/Models/Lead.php`
  - `app/Models/User.php`
  - `app/Models/Role.php`
  - `app/Http/Controllers/Api/RolePermissionController.php`
- **Regression Tests:**
  - `test_sec06_case_insensitive_and_whitespace_category_normalization`
- **Evidence:** Verified exact match for mixed case (`CLOUD & DEVOPS SOLUTIONS`) and whitespace padding (`  cloud & devops solutions  `) (Status: Code Remediation PASSED, Local Verification PASSED).

---

## 11. Security Audit Verdict & Readiness Assessment

| Verification Tier | Verdict | Notes |
|---|---|---|
| **Code Remediation** | **PASSED** | All 6 security findings SEC-01 through SEC-06 remediated in code. |
| **Local Verification** | **PASSED** | 69 automated tests passed (313 assertions). Web and mobile builds verified. Native Gradle debug APK assembled cleanly. |
| **Staging Verification** | **PENDING** | Isolated staging environment deployment and migration dry-run pending authorization. |
| **Production Authorization** | **HOLD (PENDING APPROVAL)** | Current local testing complete. Production remains strictly outside execution scope. Staging verification required prior to production release. |
