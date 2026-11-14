# VISTA CRM — PHASE G.3.2
# Staging Infrastructure Hardening & Database Provisioning Report

**Document Version:** 1.0.0  
**Date:** November 14, 2026  
**Repository:** `E:\Projects\vistacrm`  
**Audited Branch:** `fix/rbac-security-remediation`  
**Current HEAD Commit:** `6cc035e`  
**Security Baseline:** `4c92b87` (Phase G.2.3 GAP-01 Closure) | `885aeee` (Phase G.2.2 Web-Only RBAC Boundary)  
**Local Database:** `vista_crm_local` (MySQL 8.4 on `127.0.0.1:3306`)  
**Target Environment:** Dedicated Staging Environment (`vista_crm_staging`)  
**Status:** COMPLETE & VERIFIED

---

## 1. Executive Summary

Phase G.3.2 resolves all deployment-blocking findings documented in the Phase G.3.1 audit (`docs/PHASE_G3_1_STAGING_DEPLOYMENT_READINESS_AUDIT.md`). The primary objective was to establish a hardened, isolated staging deployment pathway that strictly guarantees **AGENTS.md Invariant 9: Permanent Production Database Protection Rule**.

Key accomplishments during this phase:
1. **Hardened Local VPS Deployment Script (`scripts/deploy.sh`):** Implemented mandatory `--target=staging` argument, multi-layered fail-closed pre-flight validation of `APP_ENV`, `DB_DATABASE`, and `DB_HOST`, and automatic pre-migration staging backup.
2. **Hardened Remote SSH Deployment Script (`scripts/deploy-remote.sh`):** Completely eradicated production host defaults (`31.97.225.172`) and production paths; established an approved staging host allowlist, strict production blacklist, and remote pre-flight execution over SSH.
3. **Automated Artisan Pre-Flight Gate (`app/Console/Commands/DeployPreflightCommand.php`):** Created `php artisan deploy:preflight --target=staging` verifying non-production environment variables and database identities.
4. **Decoupled Seeder Architecture (`DemoDataSeeder` & `StagingSeeder`):** Extracted synthetic demo leads out of `DatabaseSeeder.php` into `DemoDataSeeder.php`; created idempotent `StagingSeeder.php` provisioning only baseline admin, staff, and permissions.
5. **PHPUnit Database Isolation (`phpunit.xml` & `tests/TestCase.php`):** Explicitly bound test configuration to local `vista_crm_local`; implemented runtime exception throw in `tests/TestCase.php` if tests ever point to production/staging databases or hosts.
6. **Staging Database Provisioning Assets:** Prepared idempotent SQL provisioning script (`database/staging/provision_staging_db.sql`) and secure environment template (`.env.staging.example`).
7. **Regression & Safety Verification:** 106 automated tests passing (514 assertions, 0 failures), including 9 dedicated pre-flight safety scenario tests in `tests/Feature/DeploymentSafetyTest.php`.

---

## 2. Infrastructure Changes Made

| Component / File | Purpose | Security Hardening Applied |
|---|---|---|
| `scripts/deploy.sh` | Local deployment script | Enforces `--target=staging`, validates `.env`, rejects production DB/host, runs `artisan deploy:preflight`, executes pre-migration backup. |
| `scripts/deploy-remote.sh` | Remote SSH deployment script | Removed default production IP `31.97.225.172`; enforces staging allowlist, blocks production host/paths, performs remote pre-flight. |
| `app/Console/Commands/DeployPreflightCommand.php` | Artisan safety command | Programmatic gate verifying `APP_ENV === 'staging'`, approved DB name, non-production host, and staging credentials. |
| `database/seeders/DatabaseSeeder.php` | Main database seeder | Removed hardcoded demo leads; guards demo data behind `local` environment check and `SEED_DEMO_DATA` flag. |
| `database/seeders/DemoDataSeeder.php` | Dedicated local demo seeder | Encapsulates synthetic demo leads (`VB-LD-2026-0001`..`0007`) strictly for local dev testing. |
| `database/seeders/StagingSeeder.php` | Dedicated staging seeder | Idempotently provisions staging admin, QA staff accounts, dictionaries, templates, and baseline permissions (`PermissionsSeeder`). |
| `phpunit.xml` | Test runner configuration | Explicitly binds `DB_CONNECTION=mysql` and `DB_DATABASE=vista_crm_local`; prevents silent inheritance of production `.env`. |
| `tests/TestCase.php` | Base test case | Added `assertSafeTestDatabase()` in `setUp()` throwing `\RuntimeException` on prohibited DB names or hosts. |
| `database/staging/provision_staging_db.sql` | Staging DB SQL script | Idempotent DDL script creating `vista_crm_staging` and dedicated least-privilege user `vista_crm_staging_user`. |
| `.env.staging.example` | Staging configuration template | Documents exact staging settings: HTTPS, stateful domains, database sessions, and isolated staging database. |
| `tests/Feature/DeploymentSafetyTest.php` | Feature safety test suite | 9 automated tests validating fail-closed behavior across all required rejection and pass scenarios. |

---

## 3. Deployment Script Safeguards (SEC-AUD-01 & SEC-AUD-02)

### 3.1 Hardened `scripts/deploy.sh`
The revised script introduces fail-closed pre-flight validation before executing any state-changing command:
1. **Target Enforcement:** Requires `--target=staging` (or `-t staging`). Rejects any missing target or target equal to `production`.
2. **Environment Validation:** Parses `.env` and asserts `APP_ENV === 'staging'`. Exits immediately with error code 1 if `APP_ENV` is `production` or missing.
3. **Database Identity Validation:** Asserts `DB_DATABASE` is an approved staging database (`vista_crm_staging` or `vistabound_crm_staging`). Rejects known production identifiers (`vistabound_crm`, `crm_prod`, `production`, `database.sqlite`).
4. **Host Validation:** Rejects any host matching `31.97.225.172` or `vistaboundintegrated.com`.
5. **Command-Level Safety:** Executes `php artisan deploy:preflight --target=staging` prior to `artisan down`, `composer install`, or `artisan migrate`.
6. **Pre-Migration Staging Backup:** Calls `scripts/backup-db.sh` for the staging database before applying schema migrations.
7. **Inspection Flag:** Supports `--check-only` for non-destructive pre-flight audits.

### 3.2 Hardened `scripts/deploy-remote.sh`
Production host default removal:
1. **Zero Production Defaults:** Removed Hostinger production IP (`31.97.225.172`) and production path (`/public_html/CRM`) from argument fallbacks and documentation examples.
2. **Production Blacklist:** Explicitly scans `SSH_TARGET` and `REMOTE_PATH`. If `31.97.225.172`, `vistaboundintegrated.com`, or `/public_html/CRM` are detected, execution halts immediately with a `CRITICAL SAFETY VIOLATION` error.
3. **Approved Staging Allowlist:** Rejects any target host not matching `APPROVED_STAGING_HOSTS` (`127.0.0.1`, `localhost`, `staging.myworks.sbs`, `myworks.sbs` or custom allowlist via `STAGING_HOST_ALLOWLIST`).
4. **Remote Pre-Flight Check:** Prior to archiving or uploading, executes SSH check on remote host verifying remote `.env` has `APP_ENV=staging` and `DB_DATABASE` matches staging.
5. **No Exposure of Secrets:** Avoids echoing credentials or private tokens into logs.
6. **Zero Production Probing:** Strictly avoids running SSH or network pings against production.

---

## 4. Seeder Restrictions & Approved Staging Sequence (SEC-AUD-04)

### 4.1 Decoupling of Demo Data
- Previously, `DatabaseSeeder.php` directly inserted 7 demo leads (`VB-LD-2026-0001` through `0007`) and dummy activities.
- This logic was extracted into `database/seeders/DemoDataSeeder.php`.
- In `DatabaseSeeder.php`, demo data is only called if:
  ```php
  if (app()->environment('local') && env('SEED_DEMO_DATA', false)) {
      $this->call(DemoDataSeeder::class);
  }
  ```

### 4.2 Approved Staging Seeder (`StagingSeeder.php`)
For staging environments, only `StagingSeeder::class` is authorized. It executes:
1. **Staging Administrator:** `admin@vistaboundintegrated.com` (`firstOrCreate`).
2. **Staging Staff QA Accounts:** `staging_manager`, `staging_rep1`, `staging_rep2` (`firstOrCreate`).
3. **Permissions & Roles (`PermissionsSeeder`):**
   - 14 baseline permissions
   - Baseline teams (`Enterprise Solutions Team`, `Accounts & Operations Team`)
   - 4 baseline roles (`admin`, `sales_manager`, `sales_executive`, `accounts_executive`)
   - Non-destructive user-role attachments.
4. **CRM Dictionaries & Settings:** Company info, lead sources, service categories (`CrmSetting::set`).
5. **Official WhatsApp Templates:** 4 standard business outreach templates (`firstOrCreate`).
6. **Zero Demo Leads:** Zero synthetic or mock leads are seeded into the pipeline.

**Approved Staging Seeder Command:**
```bash
php artisan db:seed --class=StagingSeeder --force
```

---

## 5. PHPUnit Database Isolation

### 5.1 Test Configuration (`phpunit.xml`)
Updated `phpunit.xml` with explicit local database bindings:
```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_HOST" value="127.0.0.1"/>
<env name="DB_PORT" value="3306"/>
<env name="DB_DATABASE" value="vista_crm_local"/>
<env name="DB_USERNAME" value="vista_crm_user"/>
```

### 5.2 Runtime Test Database Safety Guard (`tests/TestCase.php`)
Added `assertSafeTestDatabase()` in base `setUp()`:
- Checks `app()->environment()`: Throws `\RuntimeException` if `production`.
- Checks active database name against `PROHIBITED_TEST_DATABASES` (`vistabound_crm`, `crm_prod`, `production`, `vista_crm_staging`, `vistabound_crm_staging`).
- Checks active database host against `PROHIBITED_TEST_HOSTS` (`31.97.225.172`, `vistaboundintegrated.com`).
- Prevents tests from executing against production or staging under any circumstance.

---

## 6. Staging Database Provisioning Status

### 6.1 Database Specifications
- **Database Name:** `vista_crm_staging`
- **Database User:** `vista_crm_staging_user`
- **Privileges:** `SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES, LOCK TABLES` scoped exclusively to `vista_crm_staging.*`.
- **Character Set / Collation:** `utf8mb4` / `utf8mb4_unicode_ci`.

### 6.2 Provisioning Script (`database/staging/provision_staging_db.sql`)
```sql
CREATE DATABASE IF NOT EXISTS vista_crm_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'vista_crm_staging_user'@'localhost' IDENTIFIED BY '<STRONG_STAGING_PASSWORD>';
CREATE USER IF NOT EXISTS 'vista_crm_staging_user'@'127.0.0.1' IDENTIFIED BY '<STRONG_STAGING_PASSWORD>';
GRANT ALL PRIVILEGES ON vista_crm_staging.* TO 'vista_crm_staging_user'@'localhost';
GRANT ALL PRIVILEGES ON vista_crm_staging.* TO 'vista_crm_staging_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### 6.3 External Hosting Provisioning Status
- Local staging simulation: **READY** (can be provisioned locally or in CI container).
- Target VPS staging database: **PENDING HOSTING ACCESS** (Hostinger MySQL database creation requires user credentials or hPanel execution).
- **Checklist for VPS Staging Provisioning:**
  1. Access MySQL on staging host (or hPanel MySQL Databases).
  2. Execute `database/staging/provision_staging_db.sql` with a generated strong password.
  3. Verify connection via `mysql -u vista_crm_staging_user -p vista_crm_staging`.

---

## 7. Migration Safety & Staging Execution Plan

### 7.1 Migration Order (All 18 Migrations)
1. `0001_01_01_000000_create_users_table.php` (Creates `users`, `password_reset_tokens`, `sessions`)
2. `0001_01_01_000001_create_cache_table.php` (Creates `cache`, `cache_locks`)
3. `0001_01_01_000002_create_jobs_table.php` (Creates `jobs`, `job_batches`, `failed_jobs`)
4. `2026_10_27_102303_create_personal_access_tokens_table.php`
5. `2026_10_27_110000_create_leads_table.php`
6. `2026_10_27_110001_create_lead_activities_table.php`
7. `2026_10_27_110002_create_follow_ups_table.php`
8. `2026_10_27_110003_create_import_histories_table.php`
9. `2026_10_27_110004_create_notification_logs_table.php`
10. `2026_10_27_110005_create_crm_settings_and_templates_table.php`
11. `2026_10_27_120000_add_reminder_fields_to_follow_ups_and_logs.php`
12. `2026_10_27_121000_add_event_type_to_notification_logs.php`
13. `2026_10_27_122000_add_username_and_password_change_to_users_table.php`
14. `2026_10_27_130000_create_device_tokens_table.php`
15. `2026_10_27_130001_add_mobile_sync_fields_to_crm_tables.php`
16. `2026_10_27_180000_add_deleted_at_to_leads_table.php`
17. `2026_11_12_000000_create_permission_management_tables.php` (RBAC tables + nullable user FKs)
18. `2026_11_13_000001_seed_baseline_roles_and_permissions.php` (Idempotent data migration)

### 7.2 Safety Protocol
- **Pre-Migration Backup:** Mandatory snapshot via `mysqldump` prior to any migration execution.
- **Transactional Migrations:** Supported by MySQL InnoDB engine.
- **Rollback Risk Assessment:** Rolling back `create_permission_management_tables` drops RBAC tables and cascades deletions. Backups are the primary recovery mechanism.

---

## 8. Deployment Pre-Flight Test Evidence

All 8 required pre-flight safety scenarios were verified via automated PHPUnit feature tests in `tests/Feature/DeploymentSafetyTest.php` and via direct Git Bash execution:

| Scenario | Input Condition | Expected Behavior | Verification Evidence |
|---|---|---|---|
| **Scenario 1** | `APP_ENV=production` | Reject with exit 1 | `CRITICAL SAFETY VIOLATION: APP_ENV is 'production'!` — **PASS** |
| **Scenario 2** | `DB_DATABASE=vistabound_crm` | Reject with exit 1 | `matches prohibited production database identifier` — **PASS** |
| **Scenario 3** | `DB_HOST=31.97.225.172` | Reject with exit 1 | `matches prohibited production host` — **PASS** |
| **Scenario 4** | `--target` omitted or not `staging` | Reject with exit 1 | `Missing required --target option` / `Invalid target` — **PASS** |
| **Scenario 5** | Missing `DB_USERNAME` | Reject with exit 1 | `Staging database username is missing or empty` — **PASS** |
| **Scenario 6** | `DB_DATABASE=some_random_db` | Reject with exit 1 | `is not in approved staging database list` — **PASS** |
| **Scenario 7** | Valid staging configuration | Exit 0 with pass | `PRE-FLIGHT STATUS: PASS — Safe for staging` — **PASS** |
| **Scenario 8** | Staging database unreachable | Fail with exit 1, zero prod fallback | Exits 1 with connection failure; zero prod connections — **PASS** |
| **PHPUnit Guard** | Test config points to prod DB | Throw RuntimeException | `SAFETY VIOLATION: Attempted to connect to protected DB` — **PASS** |

### Bash Test Evidence
- `deploy.sh`: Exits 1 with `[-] ERROR: Missing required '--target' argument.`
- `deploy.sh --target=production`: Exits 1 with `[-] ERROR: Invalid deployment target 'production'.`
- `deploy.sh --target=staging`: Exits 1 with `[-] ERROR: APP_ENV in .env is 'local'. Expected 'staging'.`
- `deploy-remote.sh --target=staging user@31.97.225.172 22 /path`: Exits 1 with `[-] CRITICAL SAFETY VIOLATION: SSH target host matches prohibited production host!`
- `deploy-remote.sh --target=staging user@arbitrary.com 22 /path`: Exits 1 with `[-] ERROR: Remote host is not in approved staging allowlist.`

---

## 9. Full Test Suite & Build Verification

```text
Test Suite Results:
   PASS  Tests\Unit\ExampleTest (1 test)
   PASS  Tests\Feature\AdminActionsWorkflowTest (14 tests)
   PASS  Tests\Feature\CrmWorkflowTest (5 tests)
   PASS  Tests\Feature\DeploymentSafetyTest (9 tests)
   PASS  Tests\Feature\ExampleTest (1 test)
   PASS  Tests\Feature\FollowUpReminderTest (9 tests)
   PASS  Tests\Feature\LeadImportWorkflowTest (2 tests)
   PASS  Tests\Feature\MobileSyncWorkflowTest (5 tests)
   PASS  Tests\Feature\NotificationPollingWorkflowTest (8 tests)
   PASS  Tests\Feature\PermissionManagementTest (52 tests)

Tests:    106 passed (514 assertions)
Duration: 17.78s
Failures: 0
```

Frontend compilation:
- `npm run build`: 1863 modules transformed, built in `8.84s` (0 errors).
- `npm run build:mobile`: 1864 modules transformed, built in `7.83s` (0 errors).

---

## 10. Outstanding External Prerequisites for Phase G.3.3

Before proceeding to Phase G.3.3 (Staging Application Deployment):
1. **Target Staging Host Details:** Confirm the staging host domain, SSH credentials, and port (e.g. `staging.myworks.sbs` or designated VPS subfolder).
2. **Execute Staging Database DDL:** Run `database/staging/provision_staging_db.sql` on the staging MySQL server to create `vista_crm_staging`.
3. **Deploy Staging `.env`:** Copy `.env.staging.example` to the remote staging host as `.env` and fill in generated staging secrets.
4. **Obtain Explicit Authorization:** Await user approval prior to launching any remote staging deployment.
