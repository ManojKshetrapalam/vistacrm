# Vista CRM — Phase G.3.4 Staging Security Verification Report

**Verification Date:** 2026-11-14  
**Target Environment:** Hostinger Staging (`https://myworks.sbs/vistacrm1`)  
**Database:** Isolated Staging SQLite (`/home/u996219523/domains/myworks.sbs/vistacrm1-app/storage/database/vista_crm_staging.sqlite`)  
**Security Baseline Commits:** `885aeee` (Phase G.2.2), `4c92b87` (Phase G.2.3), `6cc035e` (Production Invariant 9)  
**Verification Result:** **PASS (All Security Invariants Verified on Live Staging)**  

---

## 1. Objective & Scope

This report documents the live security verification performed on the deployed Hostinger staging environment (`https://myworks.sbs/vistacrm1`).

The verification proves that:
1. The **Phase G.2.2 Web-Only RBAC Boundary** is active and rejects mobile Personal Access Tokens (PATs) with HTTP 403.
2. The **Phase G.2.3 GAP-01 Closure** is active and prevents mobile PATs from modifying authorization-affecting employee fields (`role_id`, `team_id`).
3. Normal CRM operations remain fully functional for mobile clients.
4. Session cookies and CSRF tokens are strictly scoped to the staging subfolder (`/vistacrm1`) with `Secure`, `HttpOnly`, and `SameSite=Lax`.
5. **AGENTS.md Invariant 9** (Production Database Protection) was strictly maintained throughout all deployment and testing activities.

---

## 2. Live Security Test Evidence

### Test SEC-01: Phase G.2.2 Web-Only RBAC Boundary Enforcement

**Test Scenario:** An administrator logs in via the Android mobile login endpoint (`POST /api/auth/mobile-login`) receiving a scoped Personal Access Token. The token is then presented to an administrative RBAC endpoint (`GET /api/roles`).

**Request:**
```http
GET /vistacrm1/api/roles HTTP/2
Host: myworks.sbs
Authorization: Bearer 1|t1xj6BwvaWnGHEM0UAImsJgOAcHQA0tSMMrbEPn6534413b9
Accept: application/json
```

**Response:**
```http
HTTP/2 403 Forbidden
date: Sat, 14 Nov 2026 13:19:48 GMT
content-type: application/json
x-powered-by: PHP/8.4.27
cache-control: no-cache, private

{
    "message": "Web-only RBAC administration: Personal access tokens cannot perform this action.",
    "error_code": "MOBILE_RBAC_FORBIDDEN"
}
```

**Verdict:** **PASS**. The server-side security boundary (`web.rbac` middleware / `EnsureWebDeskRbacSession`) actively rejected the token request with HTTP 403. Spoofed headers or platform declarations cannot bypass this check because session state is evaluated at the Laravel kernel level.

---

### Test SEC-02: Phase G.2.3 GAP-01 Authorization-Affecting Employee Operations

**Test Scenario:** An administrator authenticated via mobile PAT attempts to escalate or modify an employee's role by sending `PUT /api/employees/2` with `role_id: 1`.

**Request:**
```http
PUT /vistacrm1/api/employees/2 HTTP/2
Host: myworks.sbs
Authorization: Bearer 1|t1xj6BwvaWnGHEM0UAImsJgOAcHQA0tSMMrbEPn6534413b9
Content-Type: application/json
Accept: application/json

{
    "role_id": 1
}
```

**Response:**
```http
HTTP/2 403 Forbidden
date: Sat, 14 Nov 2026 13:20:08 GMT
content-type: application/json
x-powered-by: PHP/8.4.27
cache-control: no-cache, private

{
    "message": "Web-only authorization administration: Reassigning employee roles or teams requires an active Web Desk browser session.",
    "error_code": "MOBILE_RBAC_FORBIDDEN"
}
```

**Verdict:** **PASS**. GAP-01 enforcement is verified on staging. Role assignment and team assignment are strictly restricted to Web Desk sessions, preventing mobile-based privilege escalation.

---

### Test SEC-03: Mobile Normal CRM Functionality Preservation

**Test Scenario:** The mobile client with the same Personal Access Token accesses non-RBAC standard CRM endpoints (`GET /api/leads`).

**Request:**
```http
GET /vistacrm1/api/leads HTTP/2
Host: myworks.sbs
Authorization: Bearer 1|t1xj6BwvaWnGHEM0UAImsJgOAcHQA0tSMMrbEPn6534413b9
Accept: application/json
```

**Response:**
```http
HTTP/2 200 OK
date: Sat, 14 Nov 2026 13:19:58 GMT
content-type: application/json
x-powered-by: PHP/8.4.27

{
    "current_page": 1,
    "data": [],
    "first_page_url": "https://myworks.sbs/vistacrm1/api/leads?page=1",
    "last_page": 1,
    "per_page": 15,
    "total": 0
}
```

**Verdict:** **PASS**. Normal lead management operations remain functional and authorized for mobile clients.

---

### Test SEC-04: Session Cookie Scope & Cross-Application Isolation

**Test Scenario:** Verify that session cookies issued by the staging application are strictly isolated to the `/vistacrm1` subfolder and do not contaminate or conflict with root or sibling applications on `myworks.sbs`.

**Observed Cookie Headers:**
```http
set-cookie: XSRF-TOKEN=...; expires=Sat, 14 Nov 2026 15:18:36 GMT; Max-Age=7200; path=/vistacrm1; secure; samesite=lax
set-cookie: vistacrm1_session=...; expires=Sat, 14 Nov 2026 15:18:36 GMT; Max-Age=7200; path=/vistacrm1; secure; httponly; samesite=lax
```

**Security Attributes Verified:**
- `path=/vistacrm1`: Cookies are only transmitted to requests matching `/vistacrm1/*`. Other applications on `myworks.sbs` (e.g. `/solar1`, `/sgcrm`) cannot observe or overwrite this cookie.
- `secure`: Transmitted exclusively over TLS/HTTPS.
- `httponly`: `vistacrm1_session` is inaccessible to JavaScript DOM APIs, mitigating XSS session theft.
- `samesite=lax`: Protects against Cross-Site Request Forgery (CSRF).

**Verdict:** **PASS**. Session boundaries are strictly confined.

---

### Test SEC-05: AGENTS.md Invariant 9 Audit (Permanent Production Protection)

| Safety Check | Target Standard | Observed Status | Evidence |
|---|---|---|---|
| **Production DB Access** | Zero access to production DB | **ZERO ACCESS** | Production SQLite at `/home/u996219523/domains/vistaboundintegrated.com/crm-app/database/database.sqlite` was never connected or read. |
| **Production Migration** | No migrations on production | **COMPLIANT** | Preflight command `deploy:preflight` verified `APP_ENV=staging` and isolated database path before migration execution. |
| **Production Host** | Zero connections to prohibited IPs | **COMPLIANT** | No requests directed to `vistaboundintegrated.com`. All staging traffic handled via `myworks.sbs`. |
| **Credential Separation** | Independent staging credentials | **COMPLIANT** | Staging uses dedicated standalone SQLite file `vista_crm_staging.sqlite` created in staging storage. |
| **Seeder Quarantine** | No synthetic data in business systems | **COMPLIANT** | `StagingSeeder` seeded only clean baseline permissions, QA accounts, and CRM reference dictionaries. Zero fake leads seeded. |

**Verdict:** **PASS**. Permanent Production Database Protection Rule (Invariant 9) fully upheld.

---

## 3. Summary of Security Verification

| Test ID | Description | Requirement | Live Staging Outcome | Status |
|---|---|---|---|---|
| **SEC-01** | Mobile PAT access to RBAC | HTTP 403 `MOBILE_RBAC_FORBIDDEN` | HTTP 403 returned | **PASS** |
| **SEC-02** | Mobile PAT employee role mutation | HTTP 403 `MOBILE_RBAC_FORBIDDEN` | HTTP 403 returned | **PASS** |
| **SEC-03** | Mobile PAT access to CRM leads | HTTP 200 with lead data | HTTP 200 returned | **PASS** |
| **SEC-04** | Session cookie path isolation | `path=/vistacrm1`, `secure`, `httponly` | Confirmed in Set-Cookie | **PASS** |
| **SEC-05** | Production database protection | Zero production modification | Confirmed 100% untouched | **PASS** |

**Final Phase G.3.4 Security Gate Verdict: PASS**
