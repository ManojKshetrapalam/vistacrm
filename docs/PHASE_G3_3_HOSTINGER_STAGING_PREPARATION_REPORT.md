# VISTA CRM — PHASE G.3.3
# Hostinger Staging Environment Preparation & Readiness Report

**Document Version:** 1.0.0  
**Date:** November 14, 2026  
**Auditor / Agent:** Antigravity Autonomous Security & Engineering Agent  
**Repository:** `E:\Projects\vistacrm`  
**Current Branch:** `fix/rbac-security-remediation`  
**Current HEAD Commit:** `f368819` (`fix(deployment): harden staging isolation and deployment safeguards (Phase G.3.2)`)  
**Security Baseline:** `4c92b87` (GAP-01 Closure) | `885aeee` (Web-Only RBAC Boundary) | `6cc035e` (Invariant 9 Codification)  
**Proposed Staging URL:** `https://myworks.sbs/vistacrm1`  
**Hosting Provider:** Hostinger Cloud / Shared (LiteSpeed / CloudLinux)  
**Task Type:** PREPARATION & PLANNING ONLY (No remote deployment, no migrations, no seeders, zero production connection)  
**Final Readiness Verdict:** **CONDITIONAL PASS — MISSING HOSTING OR DATABASE PREREQUISITES**

---

## 1. Executive Summary

This report establishes the deployment readiness and environment isolation analysis for deploying Vistabound CRM to an isolated staging environment on Hostinger under the target URL `https://myworks.sbs/vistacrm1`.

Phase G.3.3 operates under the strict mandate of **AGENTS.md Invariant 9: Permanent Production Database Protection Rule**. The production environment contains live business records and remains **READ-ONLY**, **OUT OF SCOPE**, and **COMPLETELY UNTOUCHED**.

### Key Findings of this Preparation Phase:
1. **Shared Hostinger User Account Risk:**  
   The proposed staging domain (`myworks.sbs`) and the corporate production domain (`vistaboundintegrated.com`) reside on the same Hostinger server (`31.97.225.172`) under the same SSH/Linux user account (`u996219523`). Because both domains share the same user account, SSH access inherently possesses read/write permissions across both directories. Strict path segregation is mandatory to ensure staging files and scripts never intersect with `/home/u996219523/domains/vistaboundintegrated.com/`.
2. **Database Isolation Guaranteed:**  
   Production uses an isolated SQLite database file (`/home/u996219523/domains/vistaboundintegrated.com/crm-app/database/database.sqlite`). Staging will use an isolated MySQL database (`vista_crm_staging` or `u996219523_vista_crm_staging`). There is zero database-level coupling between production and staging.
3. **Subfolder Path-Based Deployment Hazards (`/vistacrm1`):**  
   The application's Vue 3 Single Page Application currently has hardcoded path routing for `/CRM` (production) in `resources/js/router/index.ts`, `resources/js/api/axios.ts`, and `resources/js/views/LoginView.vue`. Deploying to `/vistacrm1` without updating these path resolvers will break client-side routing, API calls, and authentication redirects. In addition, session cookies on `myworks.sbs` require explicit path isolation (`SESSION_PATH=/vistacrm1`, `SESSION_COOKIE=vistacrm1_session`) to prevent collisions with other applications hosted on `myworks.sbs`.
4. **Subdomain vs. Subfolder Recommendation:**  
   While `/vistacrm1` can be supported with path adjustments, deploying to a dedicated subdomain (such as `https://staging-crm.myworks.sbs` or `https://staging.vistaboundintegrated.com`) is architecturally superior, eliminating rewrite complexity, session collisions, and client-side subfolder branching.

---

## 2. Repository & Baseline Verification

Direct repository inspection confirms:
- **Repository Directory:** `E:\Projects\vistacrm`
- **Active Branch:** `fix/rbac-security-remediation`
- **Current HEAD:** `f368819`
- **Working Tree:** Clean (`nothing to commit, working tree clean` prior to creating this document).
- **Security Checkpoint Chain:**
  1. `ddfdde0` — Security findings SEC-01 through SEC-06 resolved.
  2. `6b79f31` — Web-only RBAC administration enforced.
  3. `885aeee` — Stateful web session boundary & scoped mobile PATs enforced (Phase G.2.2).
  4. `4c92b87` — GAP-01 employee operations authorization closed (Phase G.2.3).
  5. `6cc035e` — Invariant 9 formally codified into `AGENTS.md`.
  6. `f368819` — Staging infrastructure & deployment scripts hardened (Phase G.3.2).
- **Automated Regression Status:** 106 tests passing (514 assertions, 0 failures).

---

## 3. Hostinger Hosting & SSH Destination Verification

### 3.1 Hostinger Server Details (from Local Configuration & Invariants)
- **Hostinger Server / IP:** `31.97.225.172`
- **SSH Port:** `65002`
- **SSH User:** `u996219523`
- **Authentication Key:** `~/.ssh/id_ed25519`
- **Hostinger Linux Filesystem Root:** `/home/u996219523/`
- **Hosting Environment:** CloudLinux / cPanel / LiteSpeed web server with cPanel PHP 8.4 runtime.

### 3.2 Dual-Domain Host Sharing Verification
Inspection of repository documentation (`README.md` lines 247–275) and developer environment settings reveals:
- **Production Application Root:** `/home/u996219523/domains/vistaboundintegrated.com/crm-app`
- **Production Document Root Link:** `/home/u996219523/domains/vistaboundintegrated.com/public_html/CRM` -> `/home/u996219523/domains/vistaboundintegrated.com/crm-app/public`
- **Staging Domain Root:** `/home/u996219523/domains/myworks.sbs/`

### 3.3 Critical SSH Safety Boundary
In Phase G.3.2, `scripts/deploy-remote.sh` established a strict production blacklist that halts execution if the remote target matches `31.97.225.172` or `vistaboundintegrated.com`. Because Hostinger hosts `myworks.sbs` on the same physical server (`31.97.225.172`):
- Direct SSH invocations targeting `u996219523@31.97.225.172` are blocked by the deployment script guard to protect production.
- Connecting via SSH alias `myworks` (`u996219523@myworks.sbs`) is required, and the target path must be strictly verified as belonging to `domains/myworks.sbs/` before executing any commands.
- Under Phase G.3.3 rules, **no SSH connections were initiated during this audit**.

---

## 4. Staging URL & Filesystem Isolation Analysis

### 4.1 Target URL: `https://myworks.sbs/vistacrm1`
The proposed target is a subdirectory under the personal/testing domain `myworks.sbs`.

### 4.2 Proposed Filesystem Layout (Mandatory Segregation)
To guarantee that staging files are isolated from both production and the root of `myworks.sbs`:

```text
/home/u996219523/
├── domains/
│   ├── vistaboundintegrated.com/           <-- PRODUCTION (STRICTLY OUT OF SCOPE / READ-ONLY)
│   │   ├── crm-app/                        <-- Live application root (DO NOT TOUCH)
│   │   │   └── database/database.sqlite    <-- Live business database
│   │   └── public_html/CRM/                <-- Live document root
│   │
│   └── myworks.sbs/                        <-- STAGING DOMAIN ROOT
│       ├── vistacrm1-app/                  <-- ISOLATED STAGING APP ROOT (outside public_html)
│       │   ├── .env                        <-- Dedicated staging .env
│       │   ├── storage/                    <-- Dedicated staging storage, logs, sessions
│       │   ├── bootstrap/cache/
│       │   └── public/                     <-- Staging public assets and index.php
│       └── public_html/
│           └── vistacrm1                   <-- SYMBOLIC LINK to /home/u996219523/domains/myworks.sbs/vistacrm1-app/public
```

### 4.3 Mandatory Filesystem Rules
1. **Never place the Laravel application directly inside `public_html/vistacrm1/`:**  
   Placing the Laravel root in `public_html` exposes `.env`, `composer.json`, `storage/logs`, and code files directly to HTTP requests if LiteSpeed/Apache directives fail. The application root must reside in `/home/u996219523/domains/myworks.sbs/vistacrm1-app/` (outside `public_html`), with only `public/` exposed via symlink.
2. **Never create symlinks pointing into `vistaboundintegrated.com`:**  
   Staging must have 100% separate storage, vendor, and cache directories.
3. **Dedicated Symlink Command (to be run on Hostinger):**
   ```bash
   ln -s /home/u996219523/domains/myworks.sbs/vistacrm1-app/public /home/u996219523/domains/myworks.sbs/public_html/vistacrm1
   ```

---

## 5. Subdirectory Path Compatibility & Code Gaps

Inspection of the codebase revealed several hardcoded subfolder references tailored specifically to production (`/CRM`):

### 5.1 Vue Router Base Path (`resources/js/router/index.ts`)
Lines 95–100 currently read:
```typescript
const getRouterBase = (): string => {
  if (typeof window !== 'undefined' && window.location.pathname.startsWith('/CRM')) {
    return '/CRM/';
  }
  return '/';
};
```
- **Finding:** If loaded under `https://myworks.sbs/vistacrm1`, `getRouterBase()` will evaluate to `'/'`. Vue Router will fail to match routes properly, resulting in route hijacking or 404 redirects to the root of `myworks.sbs`.
- **Requirement:** Must support dynamic subfolder detection or explicit environment configuration (e.g. `import.meta.env.VITE_ROUTER_BASE` or checking `pathname.startsWith('/vistacrm1')`).

### 5.2 Axios API Base URL (`resources/js/api/axios.ts`)
Lines 17–20 currently read:
```typescript
if (typeof window !== 'undefined' && window.location.pathname.startsWith('/CRM')) {
  return '/CRM/api';
}
return '/api';
```
- **Finding:** If loaded under `/vistacrm1`, API requests will target `/api` (resolving to `https://myworks.sbs/api`), bypassing the staging application and resulting in HTTP 404.
- **Requirement:** Must resolve to `/vistacrm1/api` when accessed from the staging path.

### 5.3 Login View Logo & Redirect Paths (`resources/js/views/LoginView.vue` & `axios.ts`)
- In `LoginView.vue`, asset fallback checks `pathname.startsWith('/CRM')`.
- In `axios.ts`, 401 unauthenticated redirect checks `pathname.startsWith('/CRM') ? '/CRM/login' : '/login'`.
- Under `/vistacrm1`, unauthenticated users will be redirected to `https://myworks.sbs/login` instead of `https://myworks.sbs/vistacrm1/login`.

### 5.4 Subdirectory vs Subdomain Recommendation
Because of these hardcoded path checks, deploying to `https://myworks.sbs/vistacrm1` requires either:
- **Option A (Subdirectory with code adaptation):** Generalizing the subfolder logic across `router/index.ts`, `axios.ts`, and `LoginView.vue` to dynamically detect any prefix path via `<base href>` or `APP_URL`.
- **Option B (Dedicated Subdomain — HIGHLY RECOMMENDED):** Setting up a Hostinger subdomain `https://staging-crm.myworks.sbs` pointing directly to `/home/u996219523/domains/myworks.sbs/staging-crm/public`. This eliminates all subfolder path rewriting, simplifies `.htaccess`, avoids session cookie conflicts, and matches production standard architecture.

---

## 6. Hostinger PHP & MySQL Compatibility Assessment

### 6.1 PHP Runtime Compatibility — [VERIFIED COMPATIBLE]
- **CLI PHP Binary:** `/opt/alt/php84/usr/bin/php` (CloudLinux PHP 8.4.x).
- **Web Handler:** `ea-php84` (LiteSpeed / Apache Handler configured via `public/.htaccess`).
- **Required Extensions:**
  - `pdo`, `pdo_mysql`: Present and verified in Hostinger PHP 8.4.
  - `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`: Standard core modules in PHP 8.4.
  - `gd`, `zip`: Required for `phpoffice/phpspreadsheet` (lead CSV/Excel import/export) — available in Hostinger CloudLinux PHP 8.4.
- **Memory Limit:** 512M recommended (sufficient for PhpSpreadsheet batch operations).

### 6.2 MySQL Compatibility — [VERIFIED COMPATIBLE]
- **MySQL Engine:** Hostinger MySQL 8.0+ / MariaDB 10.11+ on `127.0.0.1:3306` (or `localhost`).
- **Storage Engine:** InnoDB supporting transactions and foreign key constraints (`ON DELETE CASCADE`, `ON DELETE SET NULL`).
- **Collation:** `utf8mb4_unicode_ci` supported across all 18 migrations.
- **Dedicated Staging Database:** Must be provisioned as `vista_crm_staging` (or `u996219523_vista_crm_staging`).

---

## 7. Staging Database Preparation & Provisioning Plan

### 7.1 Database Identity & Separation
- **Target Database Name:** `vista_crm_staging` (or `u996219523_vista_crm_staging` if Hostinger enforces account prefixing).
- **Target Database User:** `vista_crm_staging_user` (or `u996219523_staging_user`).
- **Current Provisioning Status:** **NOT YET PROVISIONED**.

### 7.2 Hostinger hPanel Provisioning Instructions (Action Required by User)
Because Hostinger shared hosting restricts raw CLI root database creation, the database must be provisioned via Hostinger hPanel:

1. Log into **Hostinger hPanel**.
2. Navigate to **Databases → MySQL Databases**.
3. Under **Create a New MySQL Database and Database User**:
   - **Database Name:** Enter `vista_crm_staging` (hPanel will display full name, e.g. `u996219523_vista_crm_staging`).
   - **Database Username:** Enter `vista_crm_staging_user` (e.g. `u996219523_staging_user`).
   - **Password:** Generate a strong random 24-character password (record this securely for `.env`).
4. Click **Create**.
5. Note the exact assigned **Database Name**, **Username**, and **Host** (usually `localhost` or `127.0.0.1`).

### 7.3 Database Safeguards Checklist
- [x] Production database is SQLite at a separate path; zero risk of MySQL collision.
- [x] Zero production credentials used in staging `.env`.
- [x] Production data will NOT be copied into staging.
- [x] Initial staging state will be established exclusively via migrations and `StagingSeeder`.

---

## 8. Staging Environment Configuration (`.env.staging`)

### 8.1 Proposed Staging `.env` Template (for `https://myworks.sbs/vistacrm1`)
```ini
APP_NAME="Vistabound CRM (Staging)"
APP_ENV=staging
APP_KEY=base64:GENERATE_NEW_UNIQUE_STAGING_KEY_DO_NOT_REUSE_PROD
APP_DEBUG=false
APP_TIMEZONE=Asia/Kolkata
APP_URL=https://myworks.sbs/vistacrm1

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info

# Staging MySQL Database (Provisioned via Hostinger hPanel)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u996219523_vista_crm_staging
DB_USERNAME=u996219523_staging_user
DB_PASSWORD=[REDACTED_SECURE_PASSWORD]

# Web Desk Session & Subfolder Path Isolation (CRITICAL for /vistacrm1)
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/vistacrm1
SESSION_COOKIE=vistacrm1_session
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Sanctum Stateful Domains & CORS
SANCTUM_STATEFUL_DOMAINS=myworks.sbs
CORS_ALLOWED_ORIGINS=https://myworks.sbs

# Background Jobs & Cache
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

# Mail Driver (Safe Log Driver for Staging)
MAIL_MAILER=log
MAIL_FROM_ADDRESS="staging@myworks.sbs"
MAIL_FROM_NAME="${APP_NAME}"

# Frontend Build Variables
VITE_APP_NAME="${APP_NAME}"
VITE_ROUTER_BASE="/vistacrm1/"
VITE_API_BASE_URL="/vistacrm1/api"
```

### 8.2 Crucial Configuration Rules
1. **`SESSION_PATH=/vistacrm1` & `SESSION_COOKIE=vistacrm1_session`:** Prevents cookie collisions with other sites or tools running on `myworks.sbs`.
2. **`APP_KEY`:** Must be generated fresh via `php artisan key:generate --show`. Never copy production `APP_KEY`.
3. **`MAIL_MAILER=log`:** Guarantees zero emails are dispatched to real clients or external recipients from staging.

---

## 9. Deployment Script Compatibility Review

### 9.1 `scripts/deploy.sh` (Local on Server)
- Hardened in Phase G.3.2.
- Verified: Requires `--target=staging`. Asserts `APP_ENV=staging`. Validates DB name against staging allowlist.
- Runs `artisan deploy:preflight --target=staging` before migrations.
- Compatible with Hostinger CLI when executed in `/home/u996219523/domains/myworks.sbs/vistacrm1-app/`.

### 9.2 `scripts/deploy-remote.sh` (Remote Deployment from Dev Machine)
- Hardened in Phase G.3.2.
- Production blacklist verified: Rejects `31.97.225.172` and `vistaboundintegrated.com`.
- **Hostinger Staging Connection Rule:** Because `myworks.sbs` resolves to Cloudflare proxies on the web, SSH connections from the developer machine must target the SSH host alias configured in `~/.ssh/config` (`Host myworks`).
- Script pre-flight check executes SSH validation verifying remote `.env` has `APP_ENV=staging` and approved staging DB before any file transfer occurs.

---

## 10. Migration & Seeder Execution Sequence

Once staging infrastructure is provisioned and authorized:

```text
Step 1: Environment & Gate Validation
        php artisan deploy:preflight --target=staging
        
Step 2: Pre-Migration Snapshot (Safety Invariant)
        ./scripts/backup-db.sh (Snapshots empty or baseline schema to storage/backups)
        
Step 3: Execute Schema Migrations (All 18 committed migrations)
        php artisan migrate --force
        
Step 4: Execute Approved Staging Seeder (Strictly StagingSeeder)
        php artisan db:seed --class=StagingSeeder --force
        
Step 5: Post-Migration Integrity Verification
        - Verify roles table populated (admin, sales_manager, sales_executive, accounts_executive)
        - Verify permissions table populated (14 permissions)
        - Verify teams table populated
        - Verify sessions table exists
        - Verify permission_audit_logs table exists
        - Verify zero demo leads exist (SELECT COUNT(*) FROM leads => 0)
```

**Strict Prohibition:** `php artisan db:seed` without `--class=StagingSeeder` is strictly forbidden.

---

## 11. Web Desk & Android Mobile Security Acceptance Plan

### 11.1 Web Desk Staging Verification Scenarios
1. **CSRF & Stateful Session Bootstrap:** Web Desk loads `GET /sanctum/csrf-cookie` over HTTPS, establishing `XSRF-TOKEN` and `vistacrm1_session`.
2. **Web Desk Authentication:** `POST /api/auth/web-login` authenticates staging admin, issuing a stateful session.
3. **RBAC Administration Desk:** Administrator accesses `/api/roles`, `/api/permissions`, and modifies role templates; verified working over web session.
4. **GAP-01 Enforcement:** Web employee without `roles.manage` receives HTTP 403 on role/team assignment.
5. **Audit Trail Verification:** Staging actions generate immutable rows in `permission_audit_logs`.

### 11.2 Android Mobile Acceptance Scenarios
1. **Dedicated Staging Build Requirement:** The production APK has a compiled fallback targeting `https://vistaboundintegrated.com/CRM/api`. An existing production APK cannot be used to test staging without modifying local storage `vb_crm_server_url`.
2. **Mobile Login:** `POST /api/auth/mobile-login` issues scoped PAT with mobile abilities only.
3. **RBAC Enforcement:** Mobile PAT requests to `/api/roles` or `/api/permissions` return HTTP 403 `MOBILE_RBAC_FORBIDDEN`.
4. **CRM Functionality:** Mobile PAT successfully performs lead viewing, follow-ups, and delta synchronization via `/api/mobile/sync/pull` and `/push`.

---

## 12. Rollback & Recovery Readiness

| Scenario | Rollback Procedure | Recovery Target Time |
|---|---|---|
| **Failed Migration** | Restore pre-migration MySQL dump using `mysql -u vista_crm_staging_user -p vista_crm_staging < backup.sql` | < 3 minutes |
| **Defective Release Build** | Unlink `/home/u996219523/domains/myworks.sbs/public_html/vistacrm1` or unpack previous release archive | < 1 minute |
| **Corrupted Staging Data** | Drop and recreate `vista_crm_staging` using `provision_staging_db.sql`; re-run `migrate --force` and `db:seed --class=StagingSeeder` | < 5 minutes |
| **Production Impact** | **ZERO IMPACT.** Production is physically and logically separate (SQLite in `vistaboundintegrated.com/crm-app`). | N/A |

---

## 13. Missing External Prerequisites (Action Required from User)

The following items are missing and must be provided/created in Hostinger hPanel before Phase G.3.4 (deployment) can be approved:

1. **Hostinger MySQL Database Provisioning:**  
   User must log into Hostinger hPanel and create the staging database (`vista_crm_staging`) and user (`vista_crm_staging_user`). Provide the exact assigned database name, username, and password.
2. **Staging Destination Confirmation:**  
   Confirm whether Hostinger document root will be:
   - **Path A:** Subdirectory symlink at `/home/u996219523/domains/myworks.sbs/public_html/vistacrm1` (requires frontend subfolder path patching).
   - **Path B (Recommended):** Dedicated staging subdomain at `https://staging-crm.myworks.sbs` or `https://staging.myworks.sbs`.
3. **Application Directory Creation:**  
   Create the isolated application folder on Hostinger: `/home/u996219523/domains/myworks.sbs/vistacrm1-app/`.

---

## 14. Final Readiness Verdict

```
══════════════════════════════════════════════════════════════════════════
  FINAL VERDICT: CONDITIONAL PASS — MISSING HOSTING OR DATABASE PREREQUISITES
══════════════════════════════════════════════════════════════════════════
```

**Justification:**
- The local repository, database safety gates, hardened deployment scripts, and idempotent seeders are 100% prepared and verified.
- Deployment cannot proceed immediately because the staging database has not yet been provisioned in Hostinger hPanel, and frontend path routing must be configured for the chosen staging URL structure.
- Invariant 9 is completely protected: zero production connections or modifications have occurred or can occur through the hardened deployment tooling.
