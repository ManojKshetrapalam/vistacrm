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
   PASS  Tests\Feature\LeadImportWorkflowTest (1 test)
   PASS  Tests\Feature\MobileSyncWorkflowTest (5 tests)
   PASS  Tests\Feature\NotificationPollingWorkflowTest (8 tests)
   PASS  Tests\Feature\PermissionManagementTest (9 tests)
   ----------------------------------------------------------------------
   Total: 53 tests passed (261 assertions) | Duration: 15.22s
```

All security verification tests passed:
- `test_role_template_inheritance` (Verified)
- `test_user_explicit_allow_overrides_role_omission` (Verified)
- `test_user_explicit_deny_overrides_role_grant` (Verified)
- `test_reset_user_permissions_reverts_to_role_default` (Verified)
- `test_lead_visibility_scopes_assigned_team_and_all` (Verified)
- `test_category_based_vertical_restrictions` (Verified)
- `test_unauthorized_user_cannot_access_roles_endpoints` (Verified)
- `test_direct_id_manipulation_prevented_on_lead_show_and_update` (Verified)
- `test_category_restrictions_enforced_during_mobile_sync_push` (Verified)

---

## 9. Comprehensive Findings Classification

| ID | Title | Severity | Component | Remediated in Branch | Status |
|---|---|---|---|---|---|
| **SEC-01** | BOLA / Scope bypass in Lead archive, restore, and assign | **HIGH** | `LeadController.php` | Pending Fix | **BLOCKER** |
| **SEC-02** | Missing baseline role/permission seeding in production migration | **HIGH** | `Database/Deploy` | Pending Fix | **BLOCKER** |
| **SEC-03** | Lead creation ignores user vertical category restrictions | **MEDIUM** | `LeadController@store` | Pending Fix | Action Needed |
| **SEC-04** | Category discrepancy on leads with `service_interest = NULL` | **MEDIUM** | `User.php` / `Lead.php` | Pending Fix | Action Needed |
| **SEC-05** | Employee role/team updates bypass permission audit trail | **MEDIUM** | `EmployeeController.php` | Pending Fix | Action Needed |
| **SEC-06** | Case-sensitivity mismatch between PHP and MySQL collation | **LOW** | `User.php` | Pending Fix | Recommended |

---

## 10. Production Deployment Recommendation

### Recommendation: **DEFER DEPLOYMENT UNTIL HIGH-SEVERITY FINDINGS ARE REMEDIATED**

1. **Remediation Steps Required Before Production Deployment:**
   - [ ] Patch `LeadController@destroy`: Add `$user->canAccessLead($lead)` check.
   - [ ] Patch `LeadController@restore`: Add `$user->canAccessLead($lead)` check.
   - [ ] Patch `LeadController@assign`: Add `$user->canAccessLead($lead)` check.
   - [ ] Update `LeadController@store`: Validate `service_interest` against `$user->getEffectiveAllowedCategories()`.
   - [ ] Add `PermissionAuditLog::record()` in `EmployeeController@update` for role/team modifications.
   - [ ] Create a migration or add a deployment runbook step ensuring `PermissionsSeeder` is executed automatically during `php artisan migrate`.
2. **Post-Remediation Verification:**
   - Rerun `php artisan test`.
   - Once all high-severity findings are resolved, the system will achieve full Production-Ready certification.
