# Vistabound CRM — Android Mobile Application & Offline Synchronization Guide

**Application ID:** `com.vistabound.crm`  
**Application Name:** `Vistabound CRM`  
**Target Organization:** Vistabound Integrated Solutions LLP  
**Official Portal:** `https://vistaboundintegrated.com/CRM`  
**Package Version:** `1.0.0` (Build `1`)

---

## 1. Architectural Overview

The Vistabound CRM Android mobile application is an offline-first native packaging of the Vue 3 / Vite enterprise interface using Capacitor, backed by Laravel RESTful synchronization endpoints.

```
┌─────────────────────────────────────────────────────────────┐
│                   Vistabound CRM Android App                │
│                     (com.vistabound.crm)                    │
├──────────────────────────────┬──────────────────────────────┤
│  Vue 3 Single-Page Bundle    │  Capacitor Native Plugins    │
│  - Pinia Session Store       │  - @capacitor/network        │
│  - Offline Outbox Engine     │  - @capacitor/preferences    │
│  - Optimistic UI Cache       │  - @capacitor/local-notif    │
│  - Conflict Resolution       │  - @capacitor/push-notif     │
└──────────────┬───────────────┴──────────────┬───────────────┘
               │                              │
         REST / JSON                    Native Alarms
     Two-Way Delta Sync             5-Min Pre-Due Alarms
               │                              │
┌──────────────▼──────────────────────────────▼───────────────┐
│               Hostinger VPS Production Backend              │
│       https://vistaboundintegrated.com/CRM/api              │
│  - /api/mobile/sync/pull (accessible leads & follow-ups)    │
│  - /api/mobile/sync/push (idempotent outbox transactions)   │
│  - /api/mobile/push-token (FCM device registration)         │
│  - Immutable LeadActivity History Logging                   │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Offline-First Architecture & Storage

### 2.1 Persistent Storage
All client data is persisted locally using `@capacitor/preferences` (backed by Android `SharedPreferences` with fallback to browser storage):
- **`vb_offline_leads`**: Array of complete lead records accessible by the user, including cached activity history and scheduled follow-ups.
- **`vb_offline_follow_ups`**: Outstanding and scheduled follow-up tasks.
- **`vb_offline_outbox`**: Mutation queue for all actions performed while disconnected.
- **`vb_offline_sync_watermark`**: High-watermark timestamp (`server_time`) ensuring only deltas are fetched.
- **`vb_offline_settings`**: Dropdown dictionaries (pipeline stages, lead sources, priorities).

### 2.2 Offline Mutation Outbox
When the device is disconnected, the following actions can be performed seamlessly with zero lag:
1. **Add Internal Notes**: Appended optimistically to the local timeline; queued as `create_note`.
2. **Update Pipeline Status**: Lead status changes immediately in local views; queued as `update_status`.
3. **Log Phone Call Outcomes**: Records call outcome, notes, and duration; queued as `log_call`.
4. **Create / Reschedule Follow-up**: Schedules task and sets a local Android alarm; queued as `create_followup` / `reschedule_followup`.
5. **Create Lead**: Generates lead with local client UUID; queued as `create_lead`.

Each queued mutation includes:
- `uuid`: Universally unique identifier (UUID v4) generated on client.
- `operation`: Action identifier.
- `lead_id`: Target lead ID (or null for new leads).
- `payload`: Action data.
- `client_timestamp`: ISO 8601 client timestamp.
- `version`: Lead version at the moment of mutation.

### 2.3 Two-Way Synchronization & Conflict Resolution
- **Automatic Sync on Reconnect**: Listens to `@capacitor/network`. As soon as internet connectivity returns, the sync engine fires automatically.
- **Idempotency Guarantee**: If network drops mid-request and the app retries, the backend checks `client_uuid` across `lead_activities`, `follow_ups`, and `leads`. Duplicate operations are acknowledged without re-executing or corrupting state.
- **Conflict Handling**: If another user updated a lead's status concurrently while the current user was offline, the backend flags a `conflict` status and returns the authoritative server state.

---

## 3. Five-Minute Pre-Due Reminders & Local Notifications

### 3.1 Timing Calculation
For any follow-up task scheduled at time $T$, the local reminder alarm is set to:
$$T_{\text{reminder}} = T_{\text{scheduled}} - 5\text{ minutes}$$

### 3.2 Native Android Alarms (`@capacitor/local-notifications`)
- Configured with a dedicated high-priority channel:
  - **Channel ID:** `follow_up_reminders`
  - **Name:** `Follow-up Reminders`
  - **Importance:** `High` (Heads-up notification banners with sound and vibration)
  - **Color:** `#7B2D8E` (Vistabound Violet)
- Operates entirely offline without needing an active internet connection at notification time.
- Tapping the notification triggers deep link navigation directly to `/leads/:id` or `/follow-ups`.

---

## 4. Firebase Cloud Messaging (FCM) Integration

### 4.1 Device Token Registration
- On app launch, `@capacitor/push-notifications` requests notification permission and registers with Google FCM servers.
- The resulting FCM token is transmitted to `POST /api/mobile/push-token` and stored in `device_tokens`.
- On user logout, the token is revoked via `DELETE /api/mobile/push-token`.

### 4.2 Backend Push Service (`App\Services\FirebasePushService`)
- Push notifications are dispatched on:
  1. New lead assigned to an employee.
  2. Follow-up reminder alerts.
  3. Broadcast administrative notices.
- **Graceful Fallback**: If `FCM_SERVER_KEY` is not yet configured in production `.env`, the service logs delivery intentions cleanly without throwing exceptions.

---

## 5. Deep Linking & App Links

### 5.1 Custom Scheme
- Scheme: `vistabound://`
- Examples:
  - `vistabound://leads/42`
  - `vistabound://follow-ups`

### 5.2 Android App Links
- Configured in `AndroidManifest.xml` with `autoVerify="true"`:
  - Host: `https://vistaboundintegrated.com`
  - Path Prefix: `/CRM`
- When a user taps a link such as `https://vistaboundintegrated.com/CRM/leads/42` in an email, chat, or WhatsApp message, Android opens the native app directly.

---

## 6. Build Instructions & Release Artifacts

### 6.1 Prerequisites
- **Java:** OpenJDK 21
- **Android SDK:** API 34+ (compileSdk: 36, targetSdk: 36, minSdk: 24)
- **Node.js:** v20+ / v25+

### 6.2 Build Commands
```bash
# 1. Build mobile standalone web bundle
npm run build:mobile

# 2. Sync web assets into Android project
npx cap copy android

# 3. Build Signed Release APK (Direct Sideloading)
cd android && ./gradlew assembleRelease

# 4. Build Signed Android App Bundle (Google Play Release)
cd android && ./gradlew bundleRelease
```

### 6.3 Artifact Locations
- **Signed Release APK:** `android/app/build/outputs/apk/release/app-release.apk`
- **Signed Android App Bundle (AAB):** `android/app/build/outputs/bundle/release/app-release.aab`

---

## 7. Direct APK Installation Guide

1. Transfer `app-release.apk` to any Android phone via USB, Google Drive, or direct link.
2. Open the `.apk` file in the device's Files or Downloads app.
3. If prompted, enable "Install unknown apps" for the browser or file manager.
4. Tap **Install**.
5. Launch **Vistabound CRM** and log in with your company credentials.

---

## 8. Google Play Store Submission & Data Safety Checklist

### 8.1 Google Play Console Setup
- **App Name:** `Vistabound CRM`
- **Default Language:** `English (India) — en-IN`
- **App Category:** `Business / Productivity`
- **Store Listing Icon:** 512x512 PNG (from `/public/favicon.jpg`)
- **Feature Graphic:** 1024x500 PNG with Plum `#3B0A32` branding and Vistabound logo.

### 8.2 Data Safety Disclosures
- **Location:** None collected.
- **Personal Info:** Name, email address, phone number (used solely for account identification and lead CRM contact).
- **Security Practices:**
  - Data encrypted in transit via HTTPS (TLS 1.3).
  - Users can request account and data deletion through their administrator.
