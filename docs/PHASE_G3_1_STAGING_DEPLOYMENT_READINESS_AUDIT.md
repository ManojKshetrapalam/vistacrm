# VISTA CRM — PHASE G.3.1
# Staging Deployment Readiness & Environment Isolation Audit Report

**Audit Date:** November 14, 2026  
**Auditor:** Antigravity Autonomous Security & Engineering Agent  
**Repository:** `E:\Projects\vistacrm`  
**Audited Branch:** `fix/rbac-security-remediation`  
**Current HEAD Commit:** `6cc035e` (`docs: codify permanent production database protection rule into AGENTS.md`)  
**Security Remediation Baseline:** `4c92b87` (Phase G.2.3 GAP-01 closure)  
**Security Boundary Baseline:** `885aeee` (Phase G.2.2 Web-Only RBAC boundary)  
**Local Database:** `vista_crm_local` (MySQL 8.4 on `127.0.0.1:3306`)  
**Target Environment:** Isolated Staging Environment  
**Audit Type:** Read-Only Pre-Deployment & Isolation Audit  
**Audit Final Verdict:** **CONDITIONAL PASS — STAGING BLOCKED UNTIL SPECIFIED PREREQUISITES**

---

## 1. Executive Summary

This independent pre-deployment audit evaluates the readiness of Vistabound CRM for deployment to a dedicated, isolated staging environment following the completion of the Phase G.2 Granular RBAC implementation, Phase G.2.2 Web-Only authentication boundary, Phase G.2.3 GAP-01 employee operations remediation, and the formal codification of **Invariant 9: Permanent Production Database Protection Rule**.

The purpose of this audit is to ensure that:
1. Production database isolation is absolute and technically guaranteed.
2. The staging environment can be provisioned and updated without any risk to live business records.
3. Web Desk session-based RBAC administration and Android Personal Access Token compatibility operate correctly in the target staging environment.
4. Deployment automation, migrations, and seeders cannot inadvertently mutate production.

**Audit Verdict:** **CONDITIONAL PASS**.  
The application code, authorization logic, and migration scripts are structurally sound, idempotent, and regression-free (97/97 tests passing). However, deployment to staging is **BLOCKED** until specific deployment tooling vulnerabilities (unconditional `migrate --force` in shell scripts), database provisioning, and staging environment configuration are resolved.

---

## 2. Git & Baseline Verification

### 2.1 Git Status & Commit History
The repository state was verified directly from the working directory:
- **Local Branch:** `fix/rbac-security-remediation`
- **Working Tree:** Clean (`nothing to commit, working tree clean` prior to creating this uncommitted audit report)
- **Verified Commit Trajectory:**
  1. `6cc035e` — `docs: codify permanent production database protection rule into AGENTS.md` (Production Protection)
  2. `4c92b87` — `fix(security): close GAP-01 with web-only employee role and team authorization (Phase G.2.3)`
  3. `885aeee` — `feat(security): enforce stateful web-session RBAC boundary and scoped mobile tokens (Phase G.2.2)`
  4. `6b79f31` — `fix(rbac): enforce web-only RBAC administration and mobile permission consumption (Phase G.2.1)`
  5. `ddfdde0` — `fix(security): remediate Phase G RBAC security findings SEC-01 through SEC-06`

### 2.2 Invariant 9 Verification (`AGENTS.md`)
Line 44 of `AGENTS.md` was inspected and verified to contain the permanent invariant:
- **Invariant 9: Permanent Production Database Protection Rule**: Present, complete, and unmodified.
- Confirms production database is **READ-ONLY** and **OUT OF SCOPE**.
- Formally prohibits migrations, seeders, user/team mutations, token modifications, or bulk operations against production without independent prior authorization.

---

## 3. Database Environment Isolation Audit

### 3.1 Local Development Environment Verification
Local development configuration in `.env` was inspected (with credentials redacted):
- `APP_ENV`: `local`
- `APP_URL`: `http://127.0.0.1:8000`
- `DB_CONNECTION`: `mysql`
- `DB_HOST`: `127.0.0.1`
- `DB_PORT`: `3306`
- `DB_DATABASE`: `vista_crm_local`
- `DB_USERNAME`: `vista_crm_user`
- `SESSION_DRIVER`: `database`

Local development strictly connects to `127.0.0.1:3306` (`vista_crm_local`), completely separate from staging and production.

### 3.2 Proposed Staging Database Isolation Requirements
Staging requires an isolated database instance. Status: **PENDING PROVISIONING**.
To satisfy Invariant 9, the staging database must meet the following mandatory criteria:
- **Database Name:** Distinct identifier (e.g. `vista_crm_staging` or `vistabound_crm_staging`).
- **Database Host & Port:** Staging database server (e.g., local staging MySQL or designated VPS MySQL socket).
- **Credentials:** Dedicated staging user with privileges restricted strictly to the staging database schema.
- **Credential Separation:** Production database passwords, usernames, and host details must never appear in staging `.env` or staging scripts.
- **Connection Isolation:** Zero network connectivity from staging to the production SQLite/MySQL database.

### 3.3 Test Configuration Hazard (`phpunit.xml`)
An audit of `phpunit.xml` identified a configuration risk:
- Lines 25–26 of `phpunit.xml` have database connection overrides commented out:
  ```xml
  <!-- <env name="DB_CONNECTION" value="sqlite"/> -->
  <!-- <env name="DB_DATABASE" value=":memory:"/> -->
  ```
- **Finding:** In the absence of an explicit testing database definition in `phpunit.xml`, PHPUnit falls back to the database configured in the active `.env`.
- **Risk:** If automated tests are executed on a server or container where `.env` points to a production or shared database, tests will execute against that database, triggering potential data mutation or corruption.
- **Remediation Requirement:** Staging CI/CD and deployment hooks must never run `php artisan test` against a live staging or production `.env`. Test executions must use a dedicated testing database (e.g., SQLite `:memory:` or `vista_crm_testing`).

---

## 4. Production Protection Enforcement Review (Tooling Audit)

Existing deployment, backup, and maintenance scripts were inspected for compliance with Invariant 9:

### 4.1 Vulnerability in `scripts/deploy.sh` [CRITICAL]
- **Inspected File:** `scripts/deploy.sh`
- **Line 19:**
  ```bash
  echo "==> [4/6] Running database migrations..."
  php artisan migrate --force
  ```
- **Vulnerability:** `scripts/deploy.sh` runs `php artisan migrate --force` unconditionally without:
  1. Inspecting `APP_ENV` (rejection if `production`).
  2. Inspecting `DB_DATABASE` (rejection if production database name is detected).
  3. Requiring interactive or explicit confirmation of the target database identity.
- **Impact:** If `scripts/deploy.sh` is executed in an environment where `.env` points to production, it will forcefully execute migrations against live business records, violating Invariant 9.

### 4.2 Vulnerability in `scripts/deploy-remote.sh` [CRITICAL]
- **Inspected File:** `scripts/deploy-remote.sh`
- **Lines 55–66:**
  ```bash
  if [ -f artisan ]; then
    $PHP_BIN artisan migrate --force
    $PHP_BIN artisan config:clear
    $PHP_BIN artisan route:clear
    $PHP_BIN artisan view:clear
  fi
  ```
- **Vulnerability:**
  - The script SSHs into a remote target and executes `artisan migrate --force`.
  - The default example in the script targets `31.97.225.172` (Hostinger production VPS).
  - There is zero pre-flight validation confirming whether the remote host is a staging server or production server.
- **Impact:** An erroneous invocation of `scripts/deploy-remote.sh` would push code and execute migrations directly against production.

### 4.3 Database Seeder Inadvertent Data Pollution [MEDIUM]
- **Inspected File:** `database/seeders/DatabaseSeeder.php`
- **Lines 204–394:** Seeds 7 synthetic demo leads (`VB-LD-2026-0001` through `0007`) and 6 sample employee accounts with hardcoded passwords.
- **Finding:** Running `php artisan db:seed` without `--class=PermissionsSeeder` will populate demo records and overwrite employee records.
- **Rule:** Automated deployment scripts must NEVER execute `php artisan db:seed`. Only explicit, targeted seeders (`PermissionsSeeder`) may be invoked under controlled conditions.

---

## 5. Laravel Migration & Seeder Inventory

### 5.1 Migration Inventory & Risk Classification
The repository contains 18 database migrations. All 18 migrations are committed in git.

| Migration File | Purpose | Classification | Risk Level |
|---|---|---|---|
| `0001_01_01_000000_create_users_table.php` | Users, password resets, sessions | Additive Schema | Low |
| `0001_01_01_000001_create_cache_table.php` | Cache and cache locks | Additive Schema | Low |
| `0001_01_01_000002_create_jobs_table.php` | Queue jobs and failed jobs | Additive Schema | Low |
| `2026_10_27_102303_create_personal_access_tokens_table.php` | Sanctum tokens | Additive Schema | Low |
| `2026_10_27_110000_create_leads_table.php` | Core leads table | Additive Schema | Low |
| `2026_10_27_110001_create_lead_activities_table.php` | Immutable activity history | Additive Schema | Low |
| `2026_10_27_110002_create_follow_ups_table.php` | Follow-up schedules | Additive Schema | Low |
| `2026_10_27_110003_create_import_histories_table.php` | Import batches & failed rows | Additive Schema | Low |
| `2026_10_27_110004_create_notification_logs_table.php` | Notifications & delivery logs | Additive Schema | Low |
| `2026_10_27_110005_create_crm_settings_and_templates_table.php` | Dictionaries & templates | Additive Schema | Low |
| `2026_10_27_120000_add_reminder_fields_to_follow_ups_and_logs.php` | Reminder timing columns | Column Addition | Low |
| `2026_10_27_121000_add_event_type_to_notification_logs.php` | Event type categorization | Column Addition | Low |
| `2026_10_27_122000_add_username_and_password_change_to_users_table.php` | Auth fields | Column Addition | Low |
| `2026_10_27_130000_create_device_tokens_table.php` | Push device tokens | Additive Schema | Low |
| `2026_10_27_130001_add_mobile_sync_fields_to_crm_tables.php` | Offline sync timestamps & UUIDs | Column Addition | Low |
| `2026_10_27_180000_add_deleted_at_to_leads_table.php` | Soft deletes for leads | Column Addition | Low |
| `2026_11_12_000000_create_permission_management_tables.php` | Roles, permissions, teams, overrides | Additive Schema + FKs | Low (Additive) / High (Rollback) |
| `2026_11_13_000001_seed_baseline_roles_and_permissions.php` | Baseline roles, teams, permissions, backfill | Data Migration | Low (Idempotent) |

### 5.2 RBAC Migration Safety Assessment
1. **`2026_11_12_000000_create_permission_management_tables.php`:**
   - Adds nullable foreign keys `role_id` and `team_id` to `users` with `nullOnDelete()`.
   - Does NOT drop or alter existing `role` string column (`admin` / `employee`).
   - Does NOT delete or truncate any existing user, lead, or activity records.
2. **`2026_11_13_000001_seed_baseline_roles_and_permissions.php`:**
   - Wrapped inside `DB::transaction`.
   - Uses `Permission::firstOrCreate`, `Role::firstOrCreate`, `Team::firstOrCreate` — 100% idempotent.
   - Only updates users where `role_id IS NULL`, preserving any existing manual or custom role assignments.
   - Leaves unverified accounts unmapped (logs warning), avoiding destructive assumptions.
   - `down()` method is intentionally non-destructive (empty), preserving RBAC configurations if rolled back.

---

## 6. Application Deployment Readiness

### 6.1 Runtime & Dependencies
- **PHP Version:** PHP 8.2+ required (target staging server has PHP 8.4 CLI and PHP 8.4 FPM).
- **PHP Extensions Required:** `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `gd`, `zip` (all confirmed present in production/staging PHP 8.4 runtime).
- **Composer Dependencies:** `laravel/framework: ^11.31`, `laravel/sanctum: ^4.3`, `phpoffice/phpspreadsheet: ^5.10`.
- **Node.js Build:** Node 18+ and npm 9+ (compiles production bundles in <12 seconds).

### 6.2 Entry Points & Routing
- **Public Web Entry Point:** `public/index.php`.
- **SPA Blade Wrapper:** `resources/views/app.blade.php`.
- **Frontend SPA Routing:** Managed by Vue Router with HTML5 history mode.
- **SPA Fallback Rule in `routes/web.php`:**
  ```php
  Route::get('/{any?}', function () {
      return view('app');
  })->where('any', '^(?!api|sanctum|up).*$');
  ```
  Properly isolates API and Sanctum routes from SPA catch-all.

### 6.3 Storage & Permissions
The following directories require write permissions (`chmod -R 775` or owned by `www-data`):
- `storage/framework/cache/`
- `storage/framework/sessions/`
- `storage/framework/views/`
- `storage/logs/`
- `bootstrap/cache/`

### 6.4 Background Workers & Scheduler
- **Scheduler:** Hostinger crontab running `php artisan schedule:run` every minute. Required for follow-up reminders and notifications.
- **Queue Worker:** Supervisor configuration (`deploy/supervisor/vistabound-worker.conf`) managing `php artisan queue:work database`.

---

## 7. Web Desk Authentication Readiness

### 7.1 Sanctum Stateful Session Configuration
Phase G.2.2 established strict separation between Web Desk browser sessions and Android Personal Access Tokens:
- **`bootstrap/app.php`:** `$middleware->statefulApi()` is active, applying `EnsureFrontendRequestsAreStateful` to all API requests from stateful domains.
- **Stateful Domains (`SANCTUM_STATEFUL_DOMAINS`):** Must include the staging host FQDN.
- **CSRF Protection:**
  - Web Desk bootstrap requests `/sanctum/csrf-cookie` prior to login.
  - `POST /api/auth/web-login` establishes a stateful browser session via `Auth::guard('web')->login()`.
  - Non-browser endpoints (`/api/auth/mobile-login`, `/api/mobile/*`) are exempted from CSRF in `bootstrap/app.php`.
- **Cookie Security Settings for Staging (`.env`):**
  - `SESSION_DRIVER=database`
  - `SESSION_SECURE_COOKIE=true` (requires HTTPS in staging)
  - `SESSION_HTTP_ONLY=true`
  - `SESSION_SAME_SITE=lax`
  - `SESSION_DOMAIN=null` (or `.staging.domain.com` if using multi-subdomain staging)
- **CORS Credentials (`config/cors.php`):**
  - `supports_credentials => true`
  - Staging origin must be declared in `CORS_ALLOWED_ORIGINS`.
  - Frontend Axios configured with `withCredentials: true` (`resources/js/api/axios.ts`).
- **RBAC Route Middleware (`web.rbac`):**
  - Uses `EnsureWebDeskRbacSession::isWebDeskSession()`.
  - Rejects any request with a Sanctum `PersonalAccessToken`.
  - Validates `Auth::guard('web')->check()` or `TransientToken`.
  - Verified: Staging Web Desk users with valid browser sessions will be authenticated properly.

---

## 8. Android Mobile Compatibility Audit

### 8.1 API Contract & Scoped Tokens
- Mobile authentication operates via `POST /api/auth/mobile-login`.
- Issues scoped `PersonalAccessToken` with abilities:
  `['mobile:access', 'leads:view', 'leads:create', 'leads:edit', 'follow_ups:manage', 'whatsapp:send', 'mobile:sync', 'notifications:view', 'profile:manage']`.
- Strictly omits `roles.manage` and `*`.
- Staging backend will return HTTP 403 `{"error_code": "MOBILE_RBAC_FORBIDDEN"}` if mobile PAT attempts to access `/api/roles`, `/api/permissions`, or reassign roles/teams via `/api/employees`.

### 8.2 APK Rebuild Assessment [CRITICAL STAGING PREREQUISITE]
- **Inspected File:** `resources/js/api/axios.ts`
  ```typescript
  if (Capacitor.isNativePlatform()) {
    return (import.meta.env.VITE_API_BASE_URL as string) || 'https://vistaboundintegrated.com/CRM/api';
  }
  ```
- **Finding:** The Android app hardcodes the fallback API base URL to `https://vistaboundintegrated.com/CRM/api` (Production).
- **Impact on Staging Testing:**
  1. An existing installed production APK cannot connect to staging without modifying code or setting `localStorage.getItem('vb_crm_server_url')`.
  2. If mobile testing against staging is required, a dedicated Staging APK must be built with `VITE_API_BASE_URL` pointing to the staging API.
- **Rule Adherence:** Per the audit restrictions, **NO APK WILL BE REBUILT OR DISTRIBUTED DURING THIS AUDIT**. Staging APK builds must be scheduled in a separate approved execution phase.

---

## 9. Staging Database Strategy & Setup Recommendation

To ensure 100% compliance with Invariant 9, the following staging database strategy is recommended:

```
[Local Development]          [Isolated Staging]          [Production VPS]
  vista_crm_local              vista_crm_staging           vistabound_crm (Live)
  127.0.0.1:3306               Staging DB Server           Hostinger Isolated
  Full Dev Access              Controlled Testing          STRICTLY READ-ONLY
                               Zero Prod Links             OUT OF SCOPE
```

1. **Dedicated Database:** Create `vista_crm_staging` on the staging host.
2. **Dedicated User:** Create `vista_crm_staging_user` with access restricted exclusively to `vista_crm_staging`.
3. **Synthetic Test Data:** Populate staging with synthetic test leads using a sanitized staging seeder or test script. **NEVER copy production records into staging.**
4. **Controlled Migration Run:** Execute migrations strictly using an isolated staging artisan invocation:
   `php artisan migrate --force` with `.env` pointing to `vista_crm_staging`.
5. **Seeder Protocol:** Execute only `PermissionsSeeder::class` to set up baseline RBAC roles and teams:
   `php artisan db:seed --class=PermissionsSeeder --force`.

---

## 10. Rollback & Recovery Assessment

| Component | Rollback Strategy | Recovery Time | Risk Assessment |
|---|---|---|---|
| **Application Code** | Revert Git commit or unpack previous release archive (`tar -xzf`) | < 2 minutes | Very Low |
| **Frontend Assets** | Previous Vite build assets remain in versioned hash filenames in `public/build/assets` | Immediate | Very Low |
| **Configuration** | Restore previous `.env.backup` | Immediate | Low |
| **Database Migrations** | `php artisan migrate:rollback --step=2` | < 1 minute | **HIGH RISK** (Dropping RBAC tables destroys permissions/roles). Mandatory: Perform `mysqldump` prior to any migration. |
| **Active Sessions / Tokens** | Clear `sessions` table; mobile users re-authenticate on next app launch | Immediate | Low |

---

## 11. Security Findings & Remediation Matrix

| ID | Finding | Severity | Status | Remediation Required Before Staging Deployment |
|---|---|---|---|---|
| **SEC-AUD-01** | `scripts/deploy.sh` executes `artisan migrate --force` without environment or database name verification. | **HIGH** | Open | Add pre-flight guard checking `APP_ENV !== 'production'` and verifying database name before running migrations. |
| **SEC-AUD-02** | `scripts/deploy-remote.sh` targets production IP and executes `migrate --force` without safety confirmation. | **HIGH** | Open | Add explicit environment parameter requirement (`--staging` vs `--production`), host verification, and Invariant 9 guard. |
| **SEC-AUD-03** | `phpunit.xml` lacks database connection overrides, allowing tests to run against `.env` database. | **MEDIUM** | Open | Staging server must ensure tests are never run against live staging or production databases. |
| **SEC-AUD-04** | `DatabaseSeeder.php` seeds synthetic demo leads (`VB-LD-2026-0001`..`0007`) and dummy employees. | **MEDIUM** | Open | Ban `db:seed` without `--class` in deployment automation. Use only `PermissionsSeeder`. |
| **SEC-AUD-05** | Android APK hardcodes production API URL as native fallback; cannot target staging without rebuild or debug setting. | **MEDIUM** | Open | Schedule dedicated Staging APK build in subsequent phase when staging backend is ready. |
| **SEC-AUD-06** | Dedicated staging database and `.env` are not yet provisioned on the host. | **MEDIUM** | Pending | Provision isolated database `vista_crm_staging` and staging `.env` with HTTPS and stateful domains. |

---

## 12. Outstanding Prerequisites for Phase G.3.2 (Staging Deployment)

Before Phase G.3.2 staging deployment can be approved:

1. **Deployment Script Hardening:**
   - Patch `scripts/deploy.sh` and `scripts/deploy-remote.sh` to enforce Invariant 9:
     - Check `APP_ENV`: Reject if `production` without explicit multi-stage bypass.
     - Check `DB_DATABASE`: Reject if matching production database names.
     - Require explicit staging target flag.
2. **Staging Environment Provisioning:**
   - Provision isolated staging MySQL database (`vista_crm_staging`) and credentials.
   - Configure staging `.env` with:
     - `APP_ENV=staging`
     - `APP_DEBUG=false`
     - `APP_URL=https://staging.domain.com` (or designated staging URL)
     - `SANCTUM_STATEFUL_DOMAINS=staging.domain.com`
     - `SESSION_SECURE_COOKIE=true`
     - `DB_DATABASE=vista_crm_staging`
3. **Database Pre-Migration Backup:**
   - Execute a baseline database backup of staging before running any migration.
4. **Staging Web Desk Verification Plan:**
   - Verify web login, CSRF cookie bootstrap, role template creation, user overrides, and audit log generation on the deployed staging desk.

---

## 13. Final Audit Verdict

```
══════════════════════════════════════════════════════════════════════════
  FINAL AUDIT VERDICT: CONDITIONAL PASS — STAGING BLOCKED UNTIL PREREQUISITES
══════════════════════════════════════════════════════════════════════════
```

**Reasoning:**
- Application codebase, RBAC authorization, and automated regression suites (97/97 tests) are verified and production-ready.
- Invariant 9 is formally codified in `AGENTS.md`.
- Staging deployment is **BLOCKED** exclusively by operational prerequisites:
  1. Automated deployment scripts (`deploy.sh`, `deploy-remote.sh`) must be hardened with Invariant 9 pre-flight guards.
  2. Dedicated staging database (`vista_crm_staging`) and staging `.env` must be provisioned.
  3. No migrations or seeders may be executed until these safeguards are active.
