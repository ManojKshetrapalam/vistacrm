# Vistabound CRM — Phase G.2.2 Web-Only RBAC Authentication Boundary & Engine Specification

**Document Version:** 1.0.0 (Phase G.2.2 Verified Baseline)  
**Security Status:** STRICT BOUNDARY ENFORCED  
**Architecture:** Web-Only RBAC Administration, Stateful Session Desk, Scoped Mobile Personal Access Tokens, Dynamic Server-Side Authorization Engine  

---

## 1. Executive Summary & Architectural Overview

Vistabound CRM enforces a strict, unforgeable architectural boundary separating administrative Role-Based Access Control (RBAC) governance from day-to-day CRM operational flows across Web and Android:

1. **Web Desk Administration**:
   - RBAC governance (role authoring, permission grants/denials, visibility scope configuration, category filtering, user overrides, and security audit logs) is **strictly restricted to verified, stateful web desk browser sessions**.
   - Authenticated via Laravel Sanctum stateful session cookies (`laravel_session`, XSRF-TOKEN) backed by `Auth::guard('web')`.
2. **Android Mobile Client (Capacitor 8)**:
   - Authenticated via Sanctum Personal Access Tokens (PATs) stored securely on the mobile device.
   - Mobile tokens are issued with **strictly scoped operational CRM abilities** (`mobile:access`, `leads:view`, `leads:create`, `leads:edit`, `follow_ups:manage`, `whatsapp:send`, `mobile:sync`, `notifications:view`, `profile:manage`).
   - Wildcard (`*`) and RBAC management (`rbac:manage`) abilities are permanently forbidden and omitted from mobile tokens.
3. **Unforgeable Server-Side Enforcement**:
   - The server enforces the boundary in `EnsureWebDeskRbacSession` middleware.
   - Any request authenticated via a `PersonalAccessToken` (mobile token) targeting an RBAC route is immediately rejected with **HTTP 403 Forbidden**, regardless of user role (even system administrators), wildcard abilities, or client-declared platform headers/parameters (`X-Platform`, `client_channel`, `User-Agent`).

---

## 2. Authentication Architecture: Web Desk vs. Android Mobile

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                   AUTHENTICATION FLOWS                                 │
└────────────────────────────────────────────────────────────────────────────────────────┘

    [Web Browser Desk (Vue 3 / Vite)]
          │
          │ 1. POST /sanctum/csrf-cookie
          │ 2. POST /api/auth/web-login { email, password }
          ▼
    [Laravel Auth::guard('web')]
          │
          ├─► Generates secure HttpOnly session cookie (laravel_session)
          ├─► Regenerates session ID
          ├─► Returns user JSON (NO Personal Access Token issued)
          ▼
    [Subsequent Web API Requests]
          │ (Sent with credentials: true, cookies attached, Origin/Referer header)
          ▼
    [Sanctum Stateful Guard] ──► TransientToken attached ──► EnsureWebDeskRbacSession (PASS)
          │
          ├─► Normal CRM Endpoints (/api/leads, /api/follow-ups, etc.) ──► 200 OK
          └─► RBAC Admin Endpoints (/api/roles, /api/permissions, etc.) ──► 200 OK


    [Android Mobile App (Capacitor 8 / Native Android)]
          │
          │ 1. POST /api/auth/mobile-login { email, password, device_name }
          ▼
    [Laravel Sanctum Token Engine]
          │
          ├─► Validates credentials & active status
          ├─► Generates scoped Personal Access Token ('crm-mobile-session')
          ├─► Abilities: ['mobile:access', 'leads:view', 'leads:create', 'leads:edit', ...]
          ├─► NEVER assigns '*' or 'rbac:manage'
          └─► Returns bearer token JSON
          ▼
    [Subsequent Mobile API Requests]
          │ (Sent with Authorization: Bearer <token>)
          ▼
    [Sanctum Bearer Guard] ──► PersonalAccessToken attached
          │
          ├─► Normal CRM Endpoints (/api/leads, /api/mobile/sync/pull, etc.) ──► 200 OK
          └─► RBAC Admin Endpoints (/api/roles, /api/permissions, etc.)
                    │
                    ▼
          [EnsureWebDeskRbacSession Middleware]
                    │
                    ├─► Token is PersonalAccessToken? TRUE!
                    └─► REJECT IMMEDIATELY with HTTP 403 Forbidden!
```

---

## 3. Dedicated Authentication Endpoints

| Endpoint | Method | Guard / Mechanism | Purpose |
|---|---|---|---|
| `/api/auth/web-login` | `POST` | Stateful Web Session | Authenticates Web Desk sessions (`Auth::guard('web')->attempt()`), regenerates session, sets HttpOnly cookies, returns user JSON without personal access token. |
| `/api/auth/web-logout` | `POST` | Stateful Web Session | Invalidates web session (`Auth::guard('web')->logout()`, `$request->session()->invalidate()`). |
| `/api/auth/mobile-login` | `POST` | Personal Access Token | Authenticates native Android devices, issues scoped Personal Access Token (`crm-mobile-session`). |
| `/api/auth/logout` | `POST` | Universal / Bearer | Revokes the current `PersonalAccessToken` if authenticated via token, and clears web session if present. |
| `/api/auth/me` | `GET` | Universal | Returns current user profile, effective permissions, visibility scope, and unread notifications. |

---

## 4. Mobile Personal Access Token Scoping & Abilities

Personal Access Tokens created via `POST /api/auth/mobile-login` receive strictly scoped abilities defined by `User::MOBILE_CRM_ABILITIES`:

```php
public const MOBILE_CRM_ABILITIES = [
    'mobile:access',
    'leads:view',
    'leads:create',
    'leads:edit',
    'follow_ups:manage',
    'whatsapp:send',
    'mobile:sync',
    'notifications:view',
    'profile:manage',
];
```

### Security Invariants:
1. **No Wildcards**: The token never receives `*`.
2. **No RBAC Ability**: The token never receives `rbac:manage`, `roles.manage`, or any administrative grant.
3. **Defense in Depth**: Even if an administrator creates a manual token with `['*']` or database manipulation attaches extra abilities, `EnsureWebDeskRbacSession` inspects token type before token abilities: because it is an instance of `PersonalAccessToken`, it is unconditionally blocked from RBAC routes.

---

## 5. Middleware Specification: `EnsureWebDeskRbacSession`

**File:** `app/Http/Middleware/EnsureWebDeskRbacSession.php`  
**Route Middleware Alias:** `'web.rbac'`  
**Scope of Protection:**
- `/api/roles` (GET, POST)
- `/api/roles/{id}` (PUT, DELETE)
- `/api/permissions` (GET)
- `/api/teams` (GET, POST)
- `/api/users/{id}/permissions` (GET, PUT)
- `/api/users/{id}/visibility` (PUT)
- `/api/users/{id}/categories` (PUT)
- `/api/users/{id}/permissions/reset` (POST)
- `/api/audit-logs` (GET)
- `/api/permissions/audit-logs` (GET)

### Execution Sequence:
```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    $currentToken = $user->currentAccessToken();

    // STEP 1: Unconditional rejection of Personal Access Tokens (mobile tokens)
    if ($currentToken instanceof PersonalAccessToken) {
        return response()->json([
            'message' => 'Web-only RBAC administration: Personal access tokens cannot perform this action.',
            'error_code' => 'MOBILE_RBAC_FORBIDDEN',
        ], 403);
    }

    // STEP 2: Strict verification of genuine stateful web desk browser session
    $hasWebSession = Auth::guard('web')->check() || ($request->hasSession() && $currentToken instanceof \Laravel\Sanctum\TransientToken);
    if (!$hasWebSession) {
        return response()->json([
            'message' => 'Web-only RBAC administration: An active web desk browser session is required.',
            'error_code' => 'WEB_SESSION_REQUIRED',
        ], 403);
    }

    // STEP 3: Functional authorization check
    if (!$user->isAdmin() && !$user->hasPermission('roles.manage')) {
        return response()->json([
            'message' => 'Unauthorized access.',
            'error_code' => 'INSUFFICIENT_PERMISSIONS',
        ], 403);
    }

    return $next($request);
}
```

### Spoofing Immunity:
- **`?client_channel=web`**: Ignored. Rejection occurs on token instance type.
- **`X-Platform: web`**: Ignored. Rejection occurs on token instance type.
- **`User-Agent`**: Ignored. Client headers are completely untrusted at trust boundaries.

---

## 6. Client Handling & Error Codes

| HTTP Status | Error Code / Message | Client Behavior (Web Desk) | Client Behavior (Android Mobile) |
|---|---|---|---|
| `401 Unauthorized` | `Unauthenticated.` | Redirect to `/login` | Redirect to mobile login screen |
| `403 Forbidden` | `MOBILE_RBAC_FORBIDDEN` | N/A (Web sessions never receive this) | Log security alert; RBAC UI is not mounted in mobile build |
| `403 Forbidden` | `WEB_SESSION_REQUIRED` | Invalidate local state, redirect to web login | N/A |
| `403 Forbidden` | `INSUFFICIENT_PERMISSIONS` / `Unauthorized access.` | Display error toast notification "You lack permission to manage roles." | Display standard permission error |

---

## 7. Audit Logging Implications

All RBAC modifications made via Web Desk sessions are recorded in `permission_audit_logs`:
- **Actor Attribution**: `actor_id` records the authenticated administrator's `users.id`.
- **Target Tracking**: `target_type` (`role`, `user`, `team`), `target_id`.
- **Context Preservation**: Action slug (`role_created`, `user_permissions_updated`, `permissions_reset_to_role_default`), full JSON diff (`old_values`, `new_values`), client IP address, and browser User-Agent.
- **Immutability**: Audit logs cannot be modified or deleted via API.

---

## 8. Verification Test Matrix & Evidence

The implementation is verified by **40 automated test cases** in `tests/Feature/PermissionManagementTest.php` and **85 total tests** across the entire application test suite.

### Dedicated Phase G.2.2 Security Matrix (12 Tests):

| Test ID | Test Name | Target Behavior | Outcome |
|---|---|---|---|
| G22-01 | `test_g22_01_web_admin_with_valid_session_can_access_rbac` | Web admin with valid session accesses `GET /api/roles` | **PASS (200 OK)** |
| G22-02 | `test_g22_02_android_admin_with_mobile_token_receives_403` | Admin using mobile Bearer token requests `GET /api/roles` | **PASS (403 Forbidden)** |
| G22-03 | `test_g22_03_android_employee_with_mobile_token_receives_403` | Employee using mobile Bearer token requests `GET /api/roles` | **PASS (403 Forbidden)** |
| G22-04 | `test_g22_04_unauthorized_web_employee_receives_403` | Employee using web session requests `GET /api/roles` | **PASS (403 Forbidden)** |
| G22-05 | `test_g22_05_android_admin_retains_normal_crm_functionality` | Admin mobile token accesses leads, follow-ups, sync, metrics | **PASS (200 OK)** |
| G22-06 | `test_g22_06_web_admin_retains_normal_crm_functionality` | Web admin session accesses leads, follow-ups, sync, metrics | **PASS (200 OK)** |
| G22-07 | `test_g22_07_wildcard_personal_access_token_rejected_from_rbac` | Admin PAT with `['*']` wildcard abilities rejected | **PASS (403 Forbidden)** |
| G22-08 | `test_g22_08_spoofed_client_channel_web_rejected` | Mobile token spoofing `?client_channel=web` rejected | **PASS (403 Forbidden)** |
| G22-09 | `test_g22_09_spoofed_x_platform_web_rejected` | Mobile token spoofing `X-Platform: web` rejected | **PASS (403 Forbidden)** |
| G22-10 | `test_g22_10_rbac_mutations_protected_against_tokens` | Mobile token fails mutation (403); Web session succeeds (200) | **PASS** |
| G22-11 | `test_g22_11_web_session_without_functional_permission_rejected` | Web employee lacking `roles.manage` rejected on role update | **PASS (403 Forbidden)** |
| G22-12 | `test_g22_12_web_and_android_login_logout_flows` | Full web session lifecycle & mobile scoped PAT lifecycle | **PASS (200 OK)** |

### Full Test Suite Execution Summary:
- **Total Test Cases**: 85
- **Total Assertions**: 418
- **Passed**: 85 (100%)
- **Failed**: 0
- **Frontend Web Build (`npm run build`)**: PASS (0 errors, 25.16s)
- **Frontend Mobile Build (`npm run build:mobile`)**: PASS (0 errors, 24.74s)
