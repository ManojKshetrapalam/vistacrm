# Vistabound Temporary Lead Management CRM

## Project Overview
The **Vistabound Temporary Lead Management CRM** is a production-ready, standalone internal CRM built for **Vistabound Integrated Solutions LLP** (official website: [vistaboundintegrated.com](https://vistaboundintegrated.com/)). The system empowers a 1–5 person client solutions team to manage inbound leads across multiple business verticals (IT Infrastructure & Hardware AMC, Cloud & DevOps, Enterprise Software, Incubation, Hospitality, and Commerce), execute bulk Excel/CSV file imports with duplicate protection, schedule and complete follow-ups, launch 1-click WhatsApp outreach with templated messaging, track activity timelines, and inspect operational metrics.

## Current Status
- **Core Functionality**: Complete & Verified (Authentication, Role-Based Access Control, Pipeline Data Table, Lead Detail View & Chronological Timeline, Unified Action Menus, Safe Lead Archiving & Two-Stage Permanent Deletion, Employee Deactivation Safeguards with Token Revocation & Bulk Lead Reassignment, Call Logging, Follow-up Scheduler with 5-minute pre-due reminder engine & late-scheduling policy, Login Briefing Popup Modal, Real-time In-app Reminder Alert & New-Lead Alerts via Lightweight Background Polling, Excel/CSV Ingestion Wizard with Batch Audit History & Failed Rows CSV Download, WhatsApp Multi-Provider Engine with Mock & Meta Cloud API support, and Notification Delivery Logs).
- **Automated Tests**: 44 tests / 223 assertions passing cleanly (100% test pass rate across `AdminActionsWorkflowTest`, `NotificationPollingWorkflowTest`, `FollowUpReminderTest`, `MobileSyncWorkflowTest`, `CrmWorkflowTest`, and `LeadImportWorkflowTest`).
- **Brand Identity**: Complete fidelity with official Vistabound assets (original uncropped logo, Sora & Source Sans 3 typography, CSS variable design tokens, pill buttons, glassmorphic cards, spectrum accent gradients).
- **Real-Time Notification Polling**: Global Pinia store engine (`useNotificationStore`) polling `GET /api/notifications/poll` at 15s (active) / 60s (inactive via Page Visibility API), providing live topbar bell counts, sidebar task badges, and automatic popup triggers without manual page refresh.
- **PWA & Mobile**: Responsive layout, Capacitor 8 Android app with offline storage and background alarm triggers, Web App Manifest (`public/manifest.json`), Service Worker (`public/sw.js`).
- **Diagnostics & Monitoring**: Full diagnostic command `php artisan leads:diagnostics` and admin-visible Diagnostics panel in Settings.
- **Deployment**: Production Nginx configuration, Supervisor queue supervisor configuration, cron runner (1-minute cadence), and automated database backup script prepared in `deploy/` and `scripts/`.

---

## AI Quick Start
> [!IMPORTANT]
> **Read this in 30–60 seconds to navigate and modify this codebase efficiently.**

- **Architecture / Runtime**: Laravel 11/12 (PHP 8.5/8.3 compatible) REST API backend + Vue 3 (Vite + TypeScript + Tailwind CSS) Single Page Application served via `resources/views/app.blade.php`.
- **Entry Points**:
  - Backend API: [routes/api.php](file:///Users/manojkumar/27octvicrm/routes/api.php)
  - Web SPA Catch-all: [routes/web.php](file:///Users/manojkumar/27octvicrm/routes/web.php)
  - Frontend Vue App: [resources/js/app.ts](file:///Users/manojkumar/27octvicrm/resources/js/app.ts) & [resources/js/router/index.ts](file:///Users/manojkumar/27octvicrm/resources/js/router/index.ts)
- **Database & Models**: MySQL (`vistabound_crm`), tables: `users`, `leads`, `lead_activities`, `follow_ups`, `import_histories`, `notification_logs`, `whatsapp_templates`, `crm_settings`.
- **Tenant & Security Boundary**: Two roles: `admin` and `employee`. Employees are strictly scoped via `Lead::scopeAccessibleBy($user)` and `LeadPolicy`. Employees can only view/update their assigned leads and cannot access unauthorized records or admin endpoints (`/api/employees`, `/api/import/*`, `/api/settings/*`).
- **Key Business Rules**:
  - Mandatory lead fields: `name` and valid `phone` (min 7 digits).
  - Phone normalization: E.164 digits cleaned (`Lead::normalizePhone($phone)`).
  - Duplicate detection: Prevents duplicate phone entries unless explicitly confirmed by admin.
  - Activity stream: Any call, note, status transition, reassignment, or WhatsApp dispatch records an immutable row in `lead_activities`.
- **Common Change Locations**:
  - Brand styles & colors: [tailwind.config.js](file:///Users/manojkumar/27octvicrm/tailwind.config.js) & [resources/css/app.css](file:///Users/manojkumar/27octvicrm/resources/css/app.css)
  - Lead workflows & API: [app/Http/Controllers/Api/LeadController.php](file:///Users/manojkumar/27octvicrm/app/Http/Controllers/Api/LeadController.php)
  - Excel/CSV Parser: [app/Services/LeadImportService.php](file:///Users/manojkumar/27octvicrm/app/Services/LeadImportService.php)
  - WhatsApp deep-links: [app/Http/Controllers/Api/WhatsAppController.php](file:///Users/manojkumar/27octvicrm/app/Http/Controllers/Api/WhatsAppController.php)
  - Views: [resources/js/views/](file:///Users/manojkumar/27octvicrm/resources/js/views/)

---

## Technology Stack
- **Backend**: Laravel 11/12, PHP 8.5/8.3, Laravel Sanctum, PhpSpreadsheet, Eloquent ORM.
- **Frontend**: Vue 3 (Composition API `<script setup>`), Vite 6, TypeScript, Pinia, Vue Router 4, Tailwind CSS, Lucide Icons.
- **Database**: MySQL 8.0 with transactional batch ingestion and indexed normalization lookups.
- **Infrastructure**: Linux VPS, Nginx with HTTP/2 and SSL, Supervisor process manager, Crontab scheduler, automated gzip backup script.

---

## Architecture & Project Structure

```
27octvicrm/
├── app/
│   ├── Console/Commands/
│   │   └── CheckFollowUpsCommand.php      # Scans due follow-ups & triggers reminders
│   ├── Http/Controllers/Api/
│   │   ├── AuthController.php             # Login, profile, me, logout
│   │   ├── DashboardController.php        # Role-aware KPI summary & distributions
│   │   ├── EmployeeController.php         # Admin staff management & status toggling
│   │   ├── FollowUpController.php         # Today, overdue, upcoming task scheduling
│   │   ├── ImportController.php           # Excel/CSV upload, preview, processing
│   │   ├── LeadController.php             # Full lead CRUD, call logs, export, notes
│   │   ├── NotificationController.php     # In-app alerts & delivery logs
│   │   ├── SettingsController.php         # Lead sources, services, WhatsApp templates
│   │   └── WhatsAppController.php         # Deep-link generator, parameter interpolation
│   ├── Models/
│   │   ├── User.php                       # Admin & Employee models
│   │   ├── Lead.php                       # Core lead record with normalization & scopes
│   │   ├── LeadActivity.php               # Chronological audit timeline
│   │   ├── FollowUp.php                   # Scheduled tasks & outcomes
│   │   ├── ImportHistory.php              # Batch import audit logs & failure reports
│   │   ├── NotificationLog.php            # Multi-channel delivery records
│   │   ├── WhatsappTemplate.php           # Dynamic WhatsApp message templates
│   │   └── CrmSetting.php                 # Dynamic CRM dictionary settings
│   ├── Policies/
│   │   └── LeadPolicy.php                 # Strict employee/admin access control
│   └── Services/
│       └── LeadImportService.php          # Ingestion engine for CSV, XLSX, XLS
├── database/
│   ├── migrations/                        # Additive migrations including soft-deletes and mobile sync
│   └── seeders/
│       └── DatabaseSeeder.php             # Admin, 4 employees, templates, sample leads
├── deploy/
│   ├── cron/vistabound-cron               # Production cron definition
│   ├── nginx/vistabound-crm.conf          # Hardened Nginx server block with SSL
│   └── supervisor/vistabound-worker.conf  # Supervisor queue worker definition
├── public/
│   ├── favicon.jpg                        # Official Vistabound brand logo asset
│   ├── manifest.json                      # PWA web app manifest
│   └── sw.js                              # PWA service worker
├── resources/
│   ├── css/app.css                        # Vistabound CSS variables & custom utilities
│   ├── js/
│   │   ├── api/axios.ts                   # Configured Axios with Bearer token interceptor
│   │   ├── components/                    # ActionMenu, ConfirmModal, EditLeadModal, Modals
│   │   ├── router/index.ts                # Route definitions & navigation guards
│   │   ├── stores/auth.ts                 # Pinia reactive auth store
│   │   ├── views/                         # Dashboard, Leads (Active/Archived), Detail, Employees, Import History
│   │   ├── App.vue                        # Root Vue component
│   │   └── app.ts                         # Vite client entry point
│   └── views/
│       └── app.blade.php                  # Host HTML shell with fonts and metadata
├── routes/
│   ├── api.php                            # REST API route endpoints
│   ├── console.php                        # Scheduled task definitions
│   └── web.php                            # SPA web route catch-all
├── scripts/
│   ├── backup-db.sh                       # Automated MySQL backup & retention pruner
│   └── deploy.sh                          # Zero-downtime VPS deployment script
└── tests/
    └── Feature/
        ├── AdminActionsWorkflowTest.php   # Lead archive/restore/purge, employee deactivation safeguards, follow-up actions
        ├── NotificationPollingWorkflowTest.php # In-app notification polling engine
        ├── FollowUpReminderTest.php       # Pre-due 5-min alarm engine and scheduling
        ├── MobileSyncWorkflowTest.php     # Bidirectional delta sync, tombstone pruning, device tokens
        ├── CrmWorkflowTest.php            # Auth, isolation, normalization, logging tests
        └── LeadImportWorkflowTest.php     # Batch file upload, mapping, deduplication
```

---

## Where To Change What
- **Change Brand Colors / Design Tokens**: Modify `:root` in [resources/css/app.css](file:///Users/manojkumar/27octvicrm/resources/css/app.css) or `colors.vb` in [tailwind.config.js](file:///Users/manojkumar/27octvicrm/tailwind.config.js).
- **Add or Modify Lead Statuses**: Update the `status` enum migration in [database/migrations/2026_10_27_110000_create_leads_table.php](file:///Users/manojkumar/27octvicrm/database/migrations/2026_10_27_110000_create_leads_table.php), [app/Http/Controllers/Api/LeadController.php](file:///Users/manojkumar/27octvicrm/app/Http/Controllers/Api/LeadController.php), and [resources/js/components/StatusBadge.vue](file:///Users/manojkumar/27octvicrm/resources/js/components/StatusBadge.vue).
- **Add or Modify WhatsApp Message Templates**: Use the CRM Settings UI (`/settings`) or update [database/seeders/DatabaseSeeder.php](file:///Users/manojkumar/27octvicrm/database/seeders/DatabaseSeeder.php).
- **Change Import Column Matching Rules**: Modify `$synonyms` in [app/Services/LeadImportService.php](file:///Users/manojkumar/27octvicrm/app/Services/LeadImportService.php).
- **Change Follow-up Notification Threshold**: Modify `$dueSoon` window in [app/Console/Commands/CheckFollowUpsCommand.php](file:///Users/manojkumar/27octvicrm/app/Console/Commands/CheckFollowUpsCommand.php).

---

## Authentication & Default Credentials
The database seeder provisions initial accounts with secure password `Vistabound@2026`:

| Role | Name | Email | Initial Password | Access Level |
|---|---|---|---|---|
| **Admin** | CRM Administrator | `admin@vistaboundintegrated.com` | `Vistabound@2026` | Full pipeline, imports, employees, settings |
| **Employee** | Ananya Sharma | `ananya@vistaboundintegrated.com` | `Vistabound@2026` | Assigned leads & follow-ups |
| **Employee** | Rohit Verma | `rohit@vistaboundintegrated.com` | `Vistabound@2026` | Assigned leads & follow-ups |
| **Employee** | Priya Nair | `priya@vistaboundintegrated.com` | `Vistabound@2026` | Assigned leads & follow-ups |
| **Employee** | Karthik Rao | `karthik@vistaboundintegrated.com` | `Vistabound@2026` | Assigned leads & follow-ups |

---

## Local Development Setup

1. **Clone & Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

2. **Configure Database**:
   Ensure MySQL is running and `.env` has:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=vistabound_crm
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Run Migrations & Seeders**:
   ```bash
   php artisan migrate --seed
   ```

4. **Install Dependencies & Build Assets**:
   ```bash
   composer install
   npm install
   npm run build
   ```

5. **Start Local Development Server**:
   ```bash
   php artisan serve --port=8000
   ```
   Open `http://127.0.0.1:8000` in your browser.

---

## Running Automated Tests
```bash
# Run all feature and unit tests (44 tests / 223 assertions)
php artisan test

# Run Admin Actions & Safe Lifecycle test suite (14 tests)
php artisan test --filter=AdminActionsWorkflowTest

# Run mobile sync delta & device token test suite
php artisan test --filter=MobileSyncWorkflowTest

# Run background polling workflow test suite (Tests A through H)
php artisan test --filter=NotificationPollingWorkflowTest

# Run follow-up reminder test suite (Tests A through I)
php artisan test --filter=FollowUpReminderTest

# Run general workflows & import tests
php artisan test --filter=CrmWorkflowTest
php artisan test --filter=LeadImportWorkflowTest
```

---

## Admin Actions Menu & Safe Record Lifecycle

The CRM enforces non-destructive data integrity patterns across all administrative screens:

1. **Two-Stage Lead Archiving**:
   - Leads cannot be permanently deleted from active pipelines.
   - Stage 1: **Archive Lead** applies `SoftDeletes` (`deleted_at`), moving the lead to the **Archived Leads** tab. All customer notes, calls, follow-ups, and timeline events remain fully preserved.
   - Stage 2: **Restore** recovers the lead back to active status, or **Permanent Delete** completely purges the record.
   - **Typed Confirmation**: Permanent deletion requires the administrator to explicitly type the lead's unique reference ID (e.g. `VB-LD-2026-0001`) into a guarded confirmation modal before the action button unlocks.
   - Sequential reference ID generation (`Lead::generateReferenceId()`) queries `withTrashed()`, preventing ID collision when archived leads exist.

2. **Employee Deactivation Safeguards**:
   - Prevents self-deactivation of logged-in administrator.
   - Prevents deactivation of the last active administrator (`activeAdminsCount <= 1`).
   - On deactivation, revokes all Sanctum personal access tokens and purges mobile FCM push device tokens immediately.
   - Provides a **Bulk Lead Reassignment** modal allowing reassignment of all assigned leads to another active team member before or after deactivation.

3. **Follow-Up Task Management**:
   - Actions: **View Lead**, **Reschedule Task**, **Complete Task**, **Cancel Task**, and **Delete Task** (admin only).
   - Cancelling or deleting a follow-up logs an immutable activity in `lead_activities` and synchronizes with `@capacitor/local-notifications` to cancel pending mobile alarms.

4. **Import Batches & History**:
   - The Import Wizard features an **Import Batches & History** tab.
   - Administrators can review all past uploads (filename, row count, status, created timestamp, import summary).
   - For batches with invalid rows, a failed rows CSV report can be downloaded directly via `GET /api/import/history/{id}/failed-report`.

---

## Diagnostics & Operations Commands
```bash
# Display system diagnostics, follow-up metrics, queue health & delivery logs in Asia/Kolkata timestamps
php artisan leads:diagnostics

# Scan eligible follow-ups and trigger 5-minute pre-due reminders (executed minutely by scheduler)
php artisan leads:check-followups
```

---

## Production Deployment (Hostinger Cloud / VPS)

- **Production Live URL**: [https://vistaboundintegrated.com/CRM](https://vistaboundintegrated.com/CRM)
- **Document Root Link**: `/home/u996219523/domains/vistaboundintegrated.com/public_html/CRM` -> `/home/u996219523/domains/vistaboundintegrated.com/crm-app/public`
- **Application Root**: `/home/u996219523/domains/vistaboundintegrated.com/crm-app`
- **Active PHP Runtime**: CloudLinux / cPanel PHP 8.4 (`/opt/alt/php84/usr/bin/php` CLI, `ea-php84` Apache/LiteSpeed handler)
- **Database**: Isolated SQLite database at `/home/u996219523/domains/vistaboundintegrated.com/crm-app/database/database.sqlite` (isolated from corporate website tables)
- **Bootstrap Administrator**:
  - Username: `admin`
  - Email: `admin@vistaboundintegrated.com`
  - Temporary Password: `Admin123` (Must be changed immediately via profile / prompt)

### Production Cron Job (Hostinger hPanel)
In Hostinger hPanel under **Advanced → Cron Jobs**:
- **Schedule**: Every minute (`* * * * *`)
- **Command**:
  ```bash
  /opt/alt/php84/usr/bin/php /home/u996219523/domains/vistaboundintegrated.com/crm-app/artisan schedule:run >> /dev/null 2>&1
  ```

### Rollback Procedure
If rollback to the previous pre-Oct 27 installation is ever required:
```bash
ssh -p 65002 u996219523@31.97.225.172
cd /home/u996219523/domains/vistaboundintegrated.com
rm -rf crm-app
tar -xzf /home/u996219523/.dbdumps/crm-app-backup-20261027_065926.tar.gz
```

---

## Android Mobile Application & Offline Synchronization

- **Application ID:** `com.vistabound.crm`
- **Application Name:** `Vistabound CRM`
- **Native Platform:** Android (Capacitor 8 + Vue 3 / Vite)
- **Offline Architecture:**
  - Full local cache of accessible leads, notes, and follow-ups.
  - Persistent mutation outbox with client-generated UUIDs and idempotency keys.
  - Offline actions: Add notes, update pipeline status, log call attempts, and schedule/complete follow-ups.
  - Automatic two-way delta synchronization on reconnection via `@capacitor/network`.
- **5-Minute Pre-Due Alarms:**
  - Scheduled native Android alarms using `@capacitor/local-notifications`.
  - Notification channel: `follow_up_reminders` with heads-up display, vibration, and Vistabound Violet `#7B2D8E` accent.
  - Deep links directly to lead details on tap.
- **Firebase Push Notifications:**
  - Token registration via `POST /api/mobile/push-token` with graceful backend service fallback.
- **Deep Linking:**
  - Scheme: `vistabound://`
  - App Links: `https://vistaboundintegrated.com/CRM/*`
- **Build Commands:**
  ```bash
  # Compile mobile standalone SPA bundle
  npm run build:mobile

  # Copy assets into Android native project
  npx cap copy android

  # Build Signed Release APK & Signed Google Play Bundle (AAB)
  cd android && ./gradlew assembleRelease bundleRelease
  ```
- **Release Artifacts:**
  - Production Release APK (v1.0.1): [`release/Vistabound-CRM-v1.0.1-production.apk`](file:///Users/manojkumar/27octvicrm/release/Vistabound-CRM-v1.0.1-production.apk)
    - Version Code: `2` | Version Name: `1.0.1`
    - SHA-256: `e4fdf2c062d692d4fc2ab29949881050f95c1ead53f36b47cee4f99b55392c2e`
  - Release APK (v1.0.0): [`release/Vistabound-CRM-v1.0.0.apk`](file:///Users/manojkumar/27octvicrm/release/Vistabound-CRM-v1.0.0.apk)
    - SHA-256: `fc1da8f972f86c6060ccbcf9aaebf2834ad17163dfd517c3f38f758054e94d11`
  - Google Play AAB (v1.0.0): [`release/Vistabound-CRM-v1.0.0.aab`](file:///Users/manojkumar/27octvicrm/release/Vistabound-CRM-v1.0.0.aab)
    - SHA-256: `34cf18f199377699818f5ff5742150b5ff66f55fe0caeaf34114003de7c5ecce`
- **Verification Reports:**
  - End-to-End Mobile Test Report: [test-results/android-emulator/README.md](file:///Users/manojkumar/27octvicrm/test-results/android-emulator/README.md)
  - Production Acceptance Verification: [docs/PRODUCTION_DEPLOYMENT_VERIFICATION.md](file:///Users/manojkumar/27octvicrm/docs/PRODUCTION_DEPLOYMENT_VERIFICATION.md)
- **Full Mobile Guide:** See [docs/ANDROID_MOBILE_APP_GUIDE.md](file:///Users/manojkumar/27octvicrm/docs/ANDROID_MOBILE_APP_GUIDE.md)

