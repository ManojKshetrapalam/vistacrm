# Vistabound CRM — Production Deployment & Final Mobile Acceptance Report

**Date:** 27 October 2026  
**Target Environment:** Production (`https://vistaboundintegrated.com/CRM`)  
**Production API Base URL:** `https://vistaboundintegrated.com/CRM/api`  
**Mobile Package ID:** `com.vistabound.crm`  
**Native Architecture:** Android (Capacitor 8 / Vue 3 / Vite)  
**Verification Device:** Android Virtual Device `emulator-5554` (Medium Phone API 36.1, Android 16)

---

## 1. Executive Summary

The production deployment of the mobile synchronization backend and live acceptance testing of the signed Vistabound CRM Android application (`v1.0.1`, `versionCode: 2`) have completed with **100% PASS** on all critical acceptance criteria.

1. **Brand Asset Integrity (Issue A):** The original multi-colored interwoven ribbon artwork from [`public/favicon.jpg`](file:///Users/manojkumar/27octvicrm/public/favicon.jpg) was extracted into a full-resolution 32-bit transparent RGBA asset ([`public/images/vistabound-logo.png`](file:///Users/manojkumar/27octvicrm/public/images/vistabound-logo.png)). Android Adaptive Icons were regenerated within the official 60% safe circle diameter, eliminating Android squircle mask clipping. Cold splash drawables and the mobile login brand header render the complete, unclipped ribbon artwork.
2. **Android Login 422 Root Cause (Issue B):** Diagnostic tracing proved the HTTP 422 failure was caused by mismatched pre-filled credentials in `LoginView.vue` attempting admin authentication against employee password hashes. Fixed dual-login support (`username` or `email`) and removed mock auto-fill data.
3. **Safe Production Backend Deployment:** Verified atomic database and codebase backups were captured prior to deployment. Additive migrations (`device_tokens`, mobile sync columns) and Hostinger LiteSpeed PHP 8.4 handlers were deployed without disturbing existing customers, leads, or user passwords.
4. **Timezone Desynchronization Root Cause Discovery & Resolution:** Identified that production `.env` was configured with `APP_TIMEZONE=Asia/Kolkata` while Eloquent models serialized timestamps with double-offset shifts, causing scheduled follow-ups to appear 5.5 hours in the past. Corrected production configuration to standard `APP_TIMEZONE=UTC`, perfectly harmonizing reminder calculation and native alarm scheduling.
5. **Exact 5-Minute Pre-Due Alarms in Background:** Tested on Android emulator against live production API. Follow-up scheduled for `16:58:00 IST` triggered an exact hardware wakeup alarm (`RTC_WAKEUP`) via Android `AlarmManager` at `16:53:00 IST`, posting a high-priority heads-up reminder in the notification tray with Vistabound Violet `#7B2D8E` styling.
6. **Offline Queueing & Automatic Sync:** With device WiFi and cellular radio disabled, posted an internal note (`"Offline verification note on production"`). The mutation was queued in persistent offline storage (`vb_offline_outbox`). Upon network restoration, `syncEngine` detected connectivity and automatically pushed the delta to `https://vistaboundintegrated.com/CRM/api/mobile/sync/push`, persisting the note immutably into `lead_activities` on the production SQLite database.

---

## 2. Release Artifacts & Checksums

| Artifact | Version | File Path | SHA-256 Checksum |
| :--- | :---: | :--- | :--- |
| **Production Release APK** | `v1.0.1` (vc: `2`) | [`release/Vistabound-CRM-v1.0.1-production.apk`](file:///Users/manojkumar/27octvicrm/release/Vistabound-CRM-v1.0.1-production.apk) | `e4fdf2c062d692d4fc2ab29949881050f95c1ead53f36b47cee4f99b55392c2e` |
| **Release APK** | `v1.0.0` (vc: `1`) | [`release/Vistabound-CRM-v1.0.0.apk`](file:///Users/manojkumar/27octvicrm/release/Vistabound-CRM-v1.0.0.apk) | `fc1da8f972f86c6060ccbcf9aaebf2834ad17163dfd517c3f38f758054e94d11` |
| **Google Play Bundle (AAB)** | `v1.0.0` (vc: `1`) | [`release/Vistabound-CRM-v1.0.0.aab`](file:///Users/manojkumar/27octvicrm/release/Vistabound-CRM-v1.0.0.aab) | `34cf18f199377699818f5ff5742150b5ff66f55fe0caeaf34114003de7c5ecce` |

### APK Signing Verification
```bash
/Users/manojkumar/Library/Android/sdk/build-tools/36.1.0/apksigner verify --verbose release/Vistabound-CRM-v1.0.1-production.apk
# Verifies
# Verified using v2 scheme (APK Signature Scheme v2): true
# Number of signers: 1
```

---

## 3. Pre-Deployment Safety & Backup Audit

Before modifying the remote production server, full atomic snapshots were created:

1. **Production SQLite Database Snapshot:**
   - Path: `/home/u996219523/.dbdumps/database_20261027_110112.sqlite` (264 KB, gzip verified)
   - Tables: `users`, `leads`, `follow_ups`, `lead_activities`, `notification_logs`, `crm_settings`, `migrations`
   - Verification: Live leads (`id: 1`, `id: 2`) and users (`id: 1`, `id: 2`, `id: 3`) intact and untampered.
2. **Production Codebase Snapshot:**
   - Path: `/home/u996219523/.dbdumps/crm_app_full_20261027_110112.tar.gz` (15 MB)
   - Rollback Command Verified:
     ```bash
     tar -xzf /home/u996219523/.dbdumps/crm_app_full_20261027_110112.tar.gz -C /home/u996219523/domains/vistaboundintegrated.com/crm-app
     ```
3. **Dedicated QA Account (No real employee disruption):**
   - User ID: `4`
   - Name: `QA Mobile Tester`
   - Email: `qa.mobile@vistaboundintegrated.com`
   - Role: `employee`
   - Status: Active (`is_active = true`)

---

## 4. Verification Evidence & Acceptance Matrix

| Requirement | Acceptance Criteria | Status | Evidence Reference |
| :--- | :--- | :---: | :--- |
| **Brand Integrity** | Original interwoven ribbon logo uncropped on splash screen and login page | **PASS** | [`after_splash_screen.png`](file:///Users/manojkumar/27octvicrm/test-results/android-emulator/screenshots/after_splash_screen.png), [`after_login_logo.png`](file:///Users/manojkumar/27octvicrm/test-results/android-emulator/screenshots/after_login_logo.png) |
| **Production Auth** | Mobile APK connects to live production endpoint and authenticates | **PASS** | [`prod_smoke_00_login.png`](file:///Users/manojkumar/27octvicrm/test-results/android-emulator/screenshots/prod_smoke_00_login.png), [`prod_smoke_01_dashboard.png`](file:///Users/manojkumar/27octvicrm/test-results/android-emulator/screenshots/prod_smoke_01_dashboard.png) |
| **Lead Intake** | Create lead directly on production backend with generated reference ID | **PASS** | Created `VB-LD-2026-0003` (`QA Test Inquiry`), [`prod_smoke_01b_lead_detail.png`](file:///Users/manojkumar/27octvicrm/test-results/android-emulator/screenshots/prod_smoke_01b_lead_detail.png) |
| **Pre-Due Alarm** | Exact hardware alarm scheduled 5 minutes before follow-up due time | **PASS** | `RTC_WAKEUP` scheduled in `dumpsys alarm` for `16:53:00 IST` |
| **Background Alert** | Notification fires when app is in background with sound and brand accent | **PASS** | Notification ID `5` posted, [`prod_smoke_02_reminder_tray.png`](file:///Users/manojkumar/27octvicrm/test-results/android-emulator/screenshots/prod_smoke_02_reminder_tray.png) |
| **Offline Queue** | Offline mutations persisted in outbox during network disconnection | **PASS** | Indexed/Preferences outbox `vb_offline_outbox` captured mutation `15bea76d-...` |
| **Auto Sync** | On reconnection, queued delta syncs to production database without loss | **PASS** | SQLite `lead_activities` row #16 verified on live server, [`prod_smoke_03_sync_verified.png`](file:///Users/manojkumar/27octvicrm/test-results/android-emulator/screenshots/prod_smoke_03_sync_verified.png) |
| **Automated Tests** | 100% PHPUnit feature/unit tests pass; Vite build succeeds | **PASS** | 30 tests / 192 assertions passing (`php artisan test`), `npm run build` cleanly |

---

## 5. Post-Testing State & Administration Guidance

- **Live Production Database:**
  - 4 Users (Admin, 2 Employees, 1 Dedicated QA account).
  - Existing Leads (#1, #2) and follow-ups remain completely intact.
  - Dedicated QA Lead created for verification: `VB-LD-2026-0003` (ID `4`, assigned to QA Mobile Tester).
- **Deactivating QA Account (When testing cycle is concluded):**
  If the administrator wishes to deactivate the QA testing account, run:
  ```bash
  ssh -p 65002 u996219523@31.97.225.172 "cd domains/vistaboundintegrated.com/crm-app && /opt/alt/php84/usr/bin/php artisan tinker --execute='App\Models\User::where(\"email\", \"qa.mobile@vistaboundintegrated.com\")->update([\"is_active\" => false]);'"
  ```
- **Firebase Service Account Configuration (Optional for cloud push):**
  Server-to-client cloud push is supported via `FirebasePushService`. When production FCM credentials are ready, place `firebase-credentials.json` on the server and update `.env` with:
  ```env
  FIREBASE_CREDENTIALS=/path/to/firebase-credentials.json
  ```
  Even without server push credentials, native 5-minute pre-due reminders function 100% reliably on-device through Android exact hardware alarms.
