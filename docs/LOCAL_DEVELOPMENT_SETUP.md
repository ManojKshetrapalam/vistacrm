# Vistabound CRM — Windows Local Development Setup & Operations Guide

**Application:** Vistabound CRM (`com.vistabound.crm`)  
**Repository:** `https://github.com/ManojKshetrapalam/vistacrm.git`  
**Platform:** Windows 11 (x64) Local Development Environment  
**Last Verified:** 2026-11-12  

---

## 1. Verified Native Toolchain Requirements

The Windows development environment has been actively tested and verified with the following native runtimes:

| Component | Verified Version | Installation Path / Notes |
|---|---|---|
| **PHP** | `8.4.22` (CLI, ZTS Visual C++ 2022 x64) | WinGet package path (`pdo_mysql`, `mysqli`, `pdo_sqlite`, `curl`, `mbstring`, `openssl`, `zip`, `intl`, `gd`, `fileinfo`, `bcmath` active) |
| **Composer** | `2.10.1` (2026-06-04) | Global Composer CLI |
| **Laravel Framework** | `11.57.0` | Locked in `composer.lock` |
| **MySQL Community Server** | `8.4.9` (Win64 on x86_64) | `C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe`, Port `3306` active |
| **Node.js** | `v24.16.0` | Global Node runtime |
| **npm** | `11.13.0` | Node Package Manager |
| **Android / Capacitor** | `@capacitor/core: ^8.5.3`, `@capacitor/android: ^8.5.3` | Configured in `capacitor.config.ts` |

---

## 2. Automated Bootstrap Script

To initialize a clean local development environment in one step without manual intervention, run from PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup-local.ps1
```

### Safety Features of the Bootstrap Script:
- **No Overwriting of `.env`**: Preserves existing `.env` credentials unless `-ForceEnv` is explicitly passed.
- **No Database Erasure**: Preserves existing database data unless `-ResetDatabase` is explicitly passed.
- **Idempotent**: Can be run repeatedly without duplicating seed records or corrupting schema.

---

## 3. Manual Step-by-Step Setup

### Step 3.1: MySQL Database Provisioning
Connect to local MySQL as root and create the isolated development database and user:

```sql
CREATE DATABASE IF NOT EXISTS `vista_crm_local` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'vista_crm_user'@'localhost' IDENTIFIED BY 'VbLocal#Db2026_x89q';
CREATE USER IF NOT EXISTS 'vista_crm_user'@'127.0.0.1' IDENTIFIED BY 'VbLocal#Db2026_x89q';
ALTER USER 'vista_crm_user'@'localhost' IDENTIFIED BY 'VbLocal#Db2026_x89q';
ALTER USER 'vista_crm_user'@'127.0.0.1' IDENTIFIED BY 'VbLocal#Db2026_x89q';
GRANT ALL PRIVILEGES ON `vista_crm_local`.* TO 'vista_crm_user'@'localhost';
GRANT ALL PRIVILEGES ON `vista_crm_local`.* TO 'vista_crm_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

### Step 3.2: Configure `.env`
Create `.env` at the project root with the following local configuration:

```ini
APP_NAME="Vistabound CRM"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://127.0.0.1:8000

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# MySQL Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vista_crm_local
DB_USERNAME=vista_crm_user
DB_PASSWORD="VbLocal#Db2026_x89q"

# Sessions & Queues
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

QUEUE_CONNECTION=database
CACHE_STORE=database

FILESYSTEM_DISK=local

# Mail Notification Driver
MAIL_MAILER=log
MAIL_FROM_ADDRESS="local@vistaboundintegrated.com"
MAIL_FROM_NAME="${APP_NAME}"

# Frontend Vite Variables
VITE_APP_NAME="${APP_NAME}"
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

> [!IMPORTANT]
> If your database password contains characters like `#` or quotes, always wrap the password in double quotes (e.g. `DB_PASSWORD="your#password"`). Unquoted `#` characters are parsed by `phpdotenv` as comment delimiters, truncating the password.

### Step 3.3: Install Dependencies & Application Key
```bash
composer install --no-interaction --prefer-dist
php artisan key:generate
```

### Step 3.4: Execute Migrations
```bash
php artisan migrate
```

#### Verified Table Count (18 Total Tables: 17 Application/System + 1 Migration Table):
1. `cache`
2. `cache_locks`
3. `crm_settings`
4. `device_tokens`
5. `failed_jobs`
6. `follow_ups`
7. `import_histories`
8. `job_batches`
9. `jobs`
10. `lead_activities`
11. `leads`
12. `migrations`
13. `notification_logs`
14. `password_reset_tokens`
15. `personal_access_tokens`
16. `sessions`
17. `users`
18. `whatsapp_templates`

### Step 3.5: Execute Repeatable Demo Seeder
```bash
php artisan db:seed
```

All seeders in `DatabaseSeeder.php` are 100% idempotent and can be safely re-run without creating duplicate leads, activities, or follow-ups.

#### Seeded Local Development Accounts:
| Role | Username | Email | Development Password | Access Scope |
|---|---|---|---|---|
| **Administrator / Owner** | `admin` | `admin@vistaboundintegrated.com` | `Admin123` / `Vistabound@2026` | Full pipeline, settings, employees, raw logs |
| **Sales Manager** | `salesmanager` | `sales.manager@vistaboundintegrated.com` | `Vistabound@2026` | Team leads, assignments, pipelines |
| **Sales Executive** | `ananya` | `ananya@vistaboundintegrated.com` | `Vistabound@2026` | Assigned leads & follow-ups, mobile sync |
| **Sales Executive** | `rohit` | `rohit@vistaboundintegrated.com` | `Vistabound@2026` | Assigned leads & follow-ups, mobile sync |
| **Sales Executive** | `priya` | `priya@vistaboundintegrated.com` | `Vistabound@2026` | Assigned leads & follow-ups, mobile sync |
| **Sales Executive** | `karthik` | `karthik@vistaboundintegrated.com` | `Vistabound@2026` | Assigned leads & follow-ups, mobile sync |
| **Accounts Executive** | `accounts` | `accounts@vistaboundintegrated.com` | `Vistabound@2026` | Converted clients, finance accounts |

### Step 3.6: Install Frontend Dependencies & Build
```bash
npm install
npm run build
npm run build:mobile
```

### Step 3.7: Run Verification Tests
```bash
php artisan test
```
*Current test suite result: 44 passed (223 assertions).*

---

## 4. Starting the Local Web Application

Start the local Laravel development server:
```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Access the CRM in your browser at:  
👉 **`http://127.0.0.1:8000`**

For active frontend development with hot module reloading (HMR):
```bash
npm run dev
```

---

## 5. Android Mobile Application Architecture & Connectivity

### 5.1 Architecture Overview
- **Framework**: Vue 3 + Vite + Pinia single-page application packaged natively with **Capacitor 8** (`@capacitor/android: ^8.5.3`).
- **Mobile Assets**: Located in `android/` with build outputs directed to `dist/`.
- **Sync Engine**: REST-based two-way sync via `/api/mobile/sync/pull` and `/api/mobile/sync/push`.
- **Environment Isolation**: Production builds default strictly to `https://vistaboundintegrated.com/CRM/api` via `.env.production`. Development settings allow secure LAN/emulator fallbacks without hardcoding.

### 5.2 Android Emulator Access
The Android Emulator maps the Windows host loopback (`127.0.0.1`) to the alias `10.0.2.2`.
- Start Laravel server on host:
  ```bash
  php artisan serve --host=127.0.0.1 --port=8000
  ```
- Android Emulator API Base URL:
  `http://10.0.2.2:8000/api`

### 5.3 Physical Android Device (Same Local Wi-Fi Network)
1. Find your Windows PC's IPv4 address via `ipconfig` (e.g. `192.168.1.15`).
2. Start Laravel listening on all network interfaces:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
3. Set the mobile app API URL to:
   `http://192.168.1.15:8000/api`
   *(In-app: enter via server settings or set `localStorage.setItem('vb_crm_server_url', 'http://192.168.1.15:8000/api')`)*

### 5.4 Building Mobile APK
```bash
# 1. Compile standalone mobile bundle
npm run build:mobile

# 2. Copy web assets to Android platform
npx cap copy android

# 3. Assemble Debug APK via Gradle
cd android
./gradlew assembleDebug
```
Debug APK generated at:  
`android/app/build/outputs/apk/debug/app-debug.apk`

---

## 6. Permission Architecture Design (Pre-Phase G Specification)

Ahead of implementing Phase G, the granular permission system has been designed with strict adherence to the **Inherit / Allow / Deny** hierarchy, default assigned-only lead visibility, and tenant role governance:

```
┌────────────────────────────────────────────────────────────────────────┐
│                      Central Authorization Engine                      │
├────────────────────────────────────────────────────────────────────────┤
│ 1. Individual User Override (Allow / Deny) ──> Highest Precedence      │
│ 2. Assigned Role Permissions ───────────────> Inherited Baseline       │
│ 3. Category Restrictions (Role + User) ─────> Domain Filtering         │
│ 4. Visibility Scope (Assigned / Team / All) ─> Default: Assigned-Only  │
└────────────────────────────────────────────────────────────────────────┘
```

### 6.1 Database Schema Plan
1. **`roles`**:
   - `id`, `name` (unique string, e.g. `admin`, `sales_manager`, `sales_executive`, `accounts_executive`), `display_name`, `description`, `is_system`, `timestamps`.
2. **`permissions`**:
   - `id`, `name` (unique string, e.g. `leads.view`, `leads.create`, `leads.edit`, `leads.delete`, `leads.export`, `leads.import`, `follow_ups.manage`, `employees.manage`, `settings.manage`, `whatsapp.send`), `display_name`, `group`, `timestamps`.
3. **`role_has_permissions`**:
   - `role_id` (FK), `permission_id` (FK) - Composite PK.
4. **`user_has_permissions`** *(Inherit / Allow / Deny Model)*:
   - `user_id` (FK), `permission_id` (FK), `effect` (`allow` / `deny`), `granted_by` (FK), `timestamps` - Composite PK (`user_id`, `permission_id`).
   - If row is absent: Inherits from role.
   - If `allow`: Explicit grant overrides role negation.
   - If `deny`: Explicit revocation overrides role grant.
5. **`role_visibility_scopes`**:
   - `role_id` (FK), `scope` (`assigned_only`, `team_only`, `all`). Default: `assigned_only`.
6. **`user_visibility_overrides`**:
   - `user_id` (FK), `scope` (`inherit`, `assigned_only`, `team_only`, `all`), `updated_by` (FK), `timestamps`.
7. **`role_category_assignments` & `user_category_assignments`**:
   - Filter access to specific service categories (e.g. `IT Infrastructure`, `Hospitality Management`).
8. **`permission_audit_logs`**:
   - Immutable audit trail (`id`, `actor_id`, `target_type`, `target_id`, `action`, `before_state`, `after_state`, `ip_address`, `created_at`).

---

## 7. Troubleshooting Common Issues

| Issue / Error | Root Cause | Solution |
|---|---|---|
| `SQLSTATE[HY000] [1045] Access denied` | Special characters (`#`) in `.env` treated as comments | Wrap password in double quotes in `.env`: `DB_PASSWORD="your#password"` |
| `Vite manifest not found at: public/build/manifest.json` | Web assets have not yet been built | Run `npm run build` |
| `Follow-up reminder 5h 30m offset discrepancy` | Application timezone set to `Asia/Kolkata` while database expects UTC | Set `APP_TIMEZONE=UTC` in `.env`. Display attributes like `scheduled_at_ist` format to IST automatically. |
| Android physical device cannot connect to API | Windows Firewall or server bound only to `127.0.0.1` | Run `php artisan serve --host=0.0.0.0 --port=8000` and allow port 8000 in Windows Firewall. |
