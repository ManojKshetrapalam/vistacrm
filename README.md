# Vistabound Temporary Lead Management CRM

## Project Overview
The **Vistabound Temporary Lead Management CRM** is a production-ready, standalone internal CRM built for **Vistabound Integrated Solutions LLP** (official website: [vistaboundintegrated.com](https://vistaboundintegrated.com/)). The system empowers a 1–5 person client solutions team to manage inbound leads across multiple business verticals (IT Infrastructure & Hardware AMC, Cloud & DevOps, Enterprise Software, Incubation, Hospitality, and Commerce), execute bulk Excel/CSV file imports with duplicate protection, schedule and complete follow-ups, launch 1-click WhatsApp outreach with templated messaging, track activity timelines, and inspect operational metrics.

## Current Status
- **Core Functionality**: Complete & Verified (Authentication, Role-Based Access Control, Pipeline Data Table, Lead Detail View & Chronological Timeline, Call Logging, Follow-up Scheduler with 5-minute pre-due reminder engine & late-scheduling policy, Login Briefing Popup Modal, Real-time In-app Reminder Alert, Excel/CSV Ingestion Wizard, WhatsApp Multi-Provider Engine with Mock & Meta Cloud API support, and Notification Delivery Logs).
- **Automated Tests**: 17 tests / 121 assertions passing cleanly (100% test pass rate across `FollowUpReminderTest`, `CrmWorkflowTest`, and `LeadImportWorkflowTest`).
- **Brand Identity**: Complete fidelity with official Vistabound assets (original logo, Sora & Source Sans 3 typography, CSS variable design tokens, pill buttons, glassmorphic cards, spectrum accent gradients).
- **PWA & Mobile**: Responsive layout, Web App Manifest (`public/manifest.json`), Service Worker (`public/sw.js`).
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
│   ├── migrations/                        # 10 clean database migration files
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
│   │   ├── components/                    # AppLayout, StatusBadge, PriorityBadge, Modals
│   │   ├── router/index.ts                # Route definitions & navigation guards
│   │   ├── stores/auth.ts                 # Pinia reactive auth store
│   │   ├── views/                         # Dashboard, Leads, Detail, Import, etc.
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
# Run all feature and unit tests (17 tests / 121 assertions)
php artisan test

# Run follow-up reminder test suite (Tests A through I)
php artisan test --filter=FollowUpReminderTest

# Run general workflows & import tests
php artisan test --filter=CrmWorkflowTest
php artisan test --filter=LeadImportWorkflowTest
```

---

## Diagnostics & Operations Commands
```bash
# Display system diagnostics, follow-up metrics, queue health & delivery logs in Asia/Kolkata timestamps
php artisan leads:diagnostics

# Scan eligible follow-ups and trigger 5-minute pre-due reminders (executed minutely by scheduler)
php artisan leads:check-followups
```

---

## Production Deployment (Linux VPS)
1. **Server Prerequisites**: Ubuntu 22.04/24.04, Nginx, PHP 8.3/8.5 with extensions (`pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`), MySQL 8.0, Node.js 20+.
2. **Deploy Nginx Configuration**:
   ```bash
   sudo cp deploy/nginx/vistabound-crm.conf /etc/nginx/sites-available/vistabound-crm
   sudo ln -s /etc/nginx/sites-available/vistabound-crm /etc/nginx/sites-enabled/
   sudo nginx -t && sudo systemctl reload nginx
   ```
3. **Queue Worker with Supervisor**:
   ```bash
   sudo cp deploy/supervisor/vistabound-worker.conf /etc/supervisor/conf.d/
   sudo supervisorctl reread && sudo supervisorctl update
   ```
4. **Automated Backups**:
   Schedule daily backups via crontab:
   ```cron
   0 2 * * * /var/www/vistabound-crm/scripts/backup-db.sh >> /var/log/crm-backup.log 2>&1
   ```
