# Vista CRM — Phase G.3.4 Hostinger Staging Deployment Report

**Execution Date:** 2026-11-14  
**Target Environment:** Hostinger Staging (`https://myworks.sbs/vistacrm1`)  
**Repository Branch:** `fix/rbac-security-remediation`  
**Security Baseline Commits:** `885aeee` (Phase G.2.2), `4c92b87` (Phase G.2.3), `6cc035e` (Production Invariant 9), `f368819` (Phase G.3.2)  
**Execution Status:** **PASS (Fully Verified & Operational)**  

---

## 1. Executive Summary

Phase G.3.4 successfully provisioned, hardened, and deployed Vistabound CRM into a completely isolated staging environment hosted on Hostinger at `https://myworks.sbs/vistacrm1`.

All core objectives were met:
1. **Frontend Subfolder Portability**: Centralized environment-aware routing utility implemented in `resources/js/utils/basePath.ts`. Both web and native Android builds compile cleanly without regressions.
2. **Permanent Production Database Protection (AGENTS.md Invariant 9)**: Zero production databases or systems were accessed, queried, or modified. Production application (`/home/u996219523/domains/vistaboundintegrated.com/crm-app/`) and production database (`database/database.sqlite`) remained 100% untouched and out of scope.
3. **Staging Environment Isolation**: Provisioned application code in `/home/u996219523/domains/myworks.sbs/vistacrm1-app/` with dedicated public entry in `/home/u996219523/domains/myworks.sbs/public_html/vistacrm1/`.
4. **Dedicated Staging Database**: Deployed and migrated isolated SQLite database at `storage/database/vista_crm_staging.sqlite`.
5. **Seeder Integrity**: Executed `StagingSeeder` seeding baseline roles, administrative accounts, CRM settings, and WhatsApp templates with zero synthetic dummy leads.
6. **Live Staging Verification**: Validated HTTP 200 responses for the SPA entry, CSS/JS asset bundles, `/login`, `/sanctum/csrf-cookie` (HTTP 204), and authenticated API operations.

---

## 2. Infrastructure & Environment Topology

| Component | Staging Configuration | Production Isolation Reference |
|---|---|---|
| **Hosting Platform** | Hostinger Shared / Cloud Hosting | Shared server `31.97.225.172` (SSH alias `myworks`) |
| **System User** | `u996219523` | Isolated system account |
| **Staging FQDN / Path** | `https://myworks.sbs/vistacrm1` | Production is `https://vistaboundintegrated.com/CRM` |
| **App Root Directory** | `/home/u996219523/domains/myworks.sbs/vistacrm1-app/` | Production app root is `/home/u996219523/domains/vistaboundintegrated.com/crm-app/` |
| **Public Document Root** | `/home/u996219523/domains/myworks.sbs/public_html/vistacrm1/` | Production document root is `/domains/vistaboundintegrated.com/public_html/CRM/` |
| **PHP Runtime** | PHP 8.4.27 (`/opt/alt/php84/usr/bin/php` with LiteSpeed LSPHP) | PHP 8.2+ compatibility |
| **Composer** | Composer 2.9.8 | Native Linux autoloader optimization |
| **Web Server** | LiteSpeed Web Server with Cloudflare SSL & proxy | `.htaccess` scoped with `RewriteBase /vistacrm1/` |
| **Staging Database** | SQLite (`storage/database/vista_crm_staging.sqlite`) | Completely separate from production `database.sqlite` |
| **Session Isolation** | Cookie: `vistacrm1_session`, Path: `/vistacrm1` | No cookie collision with root or other domain apps |

---

## 3. Implementation Steps Executed

### Step 3.1: Frontend Subfolder Path Architecture
- Created `resources/js/utils/basePath.ts` supporting root `/`, `/CRM`, and `/vistacrm1` dynamically.
- Refactored frontend consumers:
  - `resources/js/api/axios.ts`: Dynamic API baseURL and 401 redirect via `getLoginRoute()`.
  - `resources/js/router/index.ts`: Vue Router history initialized with `getRouterBase()`.
  - `resources/js/stores/auth.ts`: Dynamic CSRF cookie endpoint via `getCsrfCookieUrl()`.
  - `resources/js/views/LoginView.vue`: Asset URL resolution and dynamic staging indicator.
  - `resources/js/components/AppLayout.vue`: Cleaned asset URL resolution and imports.
- Rebuilt production assets:
  - `npm run build`: Exit code 0, 1864 modules transformed.
  - `npm run build:mobile`: Exit code 0, 1865 modules transformed.

### Step 3.2: Staging Preflight Hardening
- Updated `app/Console/Commands/DeployPreflightCommand.php` to accept `vista_crm_staging.sqlite` and `u996219523_vista_crm_staging` in `APPROVED_STAGING_DATABASES`.
- Preserved strict blocking of production databases (`database.sqlite`, `vistabound_crm`, `crm_prod`, `production`) and production hosts (`31.97.225.172`, `vistaboundintegrated.com`).
- Updated `scripts/deploy.sh` and `scripts/deploy-remote.sh`.
- Added automated unit test `test_scenario_07b_passes_with_valid_sqlite_staging_configuration` in `tests/Feature/DeploymentSafetyTest.php`.
- Verified local test suite: 107 tests, 517 assertions, 100% PASS.

### Step 3.3: Remote Host Preparation & Deployment
- Connected to remote server `myworks` (`u996219523`).
- Created application directory tree: `/home/u996219523/domains/myworks.sbs/vistacrm1-app/`.
- Created dedicated database directory and file: `/storage/database/vista_crm_staging.sqlite` with permissions `664`.
- Packaged clean release archive excluding `.git`, `node_modules`, local databases, and temporary caches.
- Uploaded release archive and extracted into `vistacrm1-app`.
- Executed `composer install --no-dev --optimize-autoloader` using PHP 8.4.
- Generated new staging application key (`php artisan key:generate --force`).
- Executed remote preflight validation: `php artisan deploy:preflight --target=staging` (PASSED).
- Executed database migrations: `php artisan migrate --force` (All 18 migrations applied).
- Executed staging seeder: `php artisan db:seed --class=StagingSeeder --force` (Baseline permissions, roles, QA accounts, CRM settings).
- Configured public document root `/home/u996219523/domains/myworks.sbs/public_html/vistacrm1`:
  - `index.php` front-controller configured to bootstrap `vistacrm1-app`.
  - `.htaccess` configured with LiteSpeed PHP 8.4 handler (`application/x-httpd-alt-php84___lsphp`) and `RewriteBase /vistacrm1/`.
  - Asset symlinks established for `build`, `storage`, `favicon.jpg`, `manifest.json`, `robots.txt`, `sw.js`, and `images`.
- Optimized caches: `config:cache`, `route:cache`, `view:cache`.

---

## 4. Verification Evidence & Test Results

### 4.1 Deployment Preflight Gate
```text
===============================================================
  Vistabound CRM - Invariant 9 Deployment Pre-Flight Gate      
===============================================================
  ✓ APP_ENV is verified as "staging"
  ✓ Database host '' is verified non-production
  ✓ Database name '/home/u996219523/domains/myworks.sbs/vistacrm1-app/storage/database/vista_crm_staging.sqlite' is verified as an approved isolated staging database
  ✓ Staging SQLite database driver verified
---------------------------------------------------------------
  PRE-FLIGHT STATUS: PASS — Safe for staging migration/deployment
---------------------------------------------------------------
```

### 4.2 Web Entry Point & Asset Bundle Delivery
- `GET https://myworks.sbs/vistacrm1` -> HTTP 301 redirect to `/vistacrm1/` (canonical directory).
- `GET https://myworks.sbs/vistacrm1/` -> **HTTP/2 200 OK**
  - Serves Vue SPA shell (`app.blade.php`).
  - Sets cookies: `XSRF-TOKEN` and `vistacrm1_session` with `path=/vistacrm1; secure; samesite=lax`.
- `GET https://myworks.sbs/vistacrm1/build/assets/app-CzRXEBk2.css` -> **HTTP/2 200 OK** (Content-Type: `text/css`).
- `GET https://myworks.sbs/vistacrm1/build/assets/app-D8zc1fJQ.js` -> **HTTP/2 200 OK** (Content-Type: `application/x-javascript`).

### 4.3 Web Desk Session & CSRF Endpoint
- `GET https://myworks.sbs/vistacrm1/sanctum/csrf-cookie` -> **HTTP/2 204 No Content**
  - Set-Cookie: `XSRF-TOKEN=...; path=/vistacrm1; secure; samesite=lax`
  - Set-Cookie: `vistacrm1_session=...; path=/vistacrm1; secure; httponly; samesite=lax`

### 4.4 Mobile Authentication & Scoped Token Validation
- `POST https://myworks.sbs/vistacrm1/api/auth/mobile-login` with valid credentials:
  - Response: **HTTP 200 OK**
  - Body: `{"message":"Mobile session authenticated successfully","token":"...","user":{"id":1,"name":"Staging Administrator","role":"admin"}}`
- Normal CRM endpoint access with Bearer token:
  - `GET https://myworks.sbs/vistacrm1/api/leads` -> **HTTP 200 OK** (Returns paginated leads collection).

---

## 5. Rollback & Disaster Recovery Procedure

In the event of an issue on staging:
1. **Disable Public Access**: Replace `/home/u996219523/domains/myworks.sbs/public_html/vistacrm1/index.php` with a maintenance message.
2. **Database Reset**: If staging database state must be reset:
   ```bash
   ssh myworks "rm -f /home/u996219523/domains/myworks.sbs/vistacrm1-app/storage/database/vista_crm_staging.sqlite && touch /home/u996219523/domains/myworks.sbs/vistacrm1-app/storage/database/vista_crm_staging.sqlite && chmod 664 /home/u996219523/domains/myworks.sbs/vistacrm1-app/storage/database/vista_crm_staging.sqlite"
   ssh myworks "cd /home/u996219523/domains/myworks.sbs/vistacrm1-app && /opt/alt/php84/usr/bin/php artisan migrate --force && /opt/alt/php84/usr/bin/php artisan db:seed --class=StagingSeeder --force"
   ```
3. **Application Tear-Down**: To completely remove the staging deployment:
   ```bash
   ssh myworks "rm -rf /home/u996219523/domains/myworks.sbs/vistacrm1-app /home/u996219523/domains/myworks.sbs/public_html/vistacrm1"
   ```
   *Note: This does not affect any other project or production in any way.*

---

## 6. Verification Status

| Gate | Check Item | Result |
|---|---|---|
| **Gate 1** | Local Git working tree and safety tests | **PASS (107/107 tests passing)** |
| **Gate 2** | Invariant 9 Production Protection Compliance | **PASS (Zero production exposure)** |
| **Gate 3** | Remote staging filesystem and database isolation | **PASS (Self-contained in myworks.sbs)** |
| **Gate 4** | Subfolder path resolution (`/vistacrm1`) | **PASS (Assets, routes, cookies resolved)** |
| **Gate 5** | Staging migrations and clean seeding | **PASS (Clean baseline, zero demo leads)** |
| **Gate 6** | Remote API and SPA verification | **PASS (HTTP 200 across endpoints)** |

**Final Phase G.3.4 Verdict: PASS**
