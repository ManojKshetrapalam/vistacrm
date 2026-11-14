# AGENTS.md — Vistabound CRM Invariants & AI Operating Rules

## Critical Architectural Invariants
1. **Strict Standalone Architecture**:
   - This CRM is a standalone temporary solution for Vistabound Integrated Solutions LLP.
   - Do NOT introduce external dependencies on the unfinished main Vistabound enterprise CRM.
   - Do NOT add distributed microservices, Redis brokers, or separate Node backend processes. The system is designed for a team of 1–5 users on a single VPS.

2. **Tenant & Role-Based Access Isolation**:
   - The system strictly enforces two roles: `admin` and `employee`.
   - **Never bypass `Lead::accessibleBy($user)`** in any lead querying endpoint or query builder.
   - Employees must never be granted access to other employees' leads or administrative endpoints (`/api/employees`, `/api/import/*`, `/api/settings/*`).
   - All authorization must be checked both on the frontend UI and enforced in backend controllers and `LeadPolicy`.

3. **Data Integrity & Duplicate Rules**:
   - Phone numbers must always be normalized using `Lead::normalizePhone($phone)` prior to querying or storing in `phone_normalized`.
   - Duplicate prevention must NEVER silently overwrite or merge client records. If a duplicate exists, return HTTP 409 with existing lead metadata unless explicitly confirmed by an administrator.
   - `reference_id` must follow the company standard sequence (`VB-LD-YYYY-XXXX`) generated via `Lead::generateReferenceId()`.

4. **Activity History Immutability**:
   - Every state transition, call outcome, manual note, assignment change, follow-up scheduling/completion, and WhatsApp message dispatch must create an immutable row in `lead_activities`.
   - Never perform destructive bulk updates without recording the corresponding activity.

5. **Brand Asset Integrity**:
   - The official Vistabound logo (`/favicon.jpg`) and brand palette (Plum `#3B0A32`, Plum 2 `#5B1450`, Violet `#7B2D8E`, Spectrum gradient, Sora and Source Sans 3 typography) must be preserved.
   - Do not replace the logo with generic icon placeholders or distort its proportions.

6. **Testing & Verification Standard**:
   - Any modification to database schemas, lead workflows, authorization policies, or import services must be accompanied by updated feature tests in `tests/Feature/`.
   - Always run `php artisan test` and `npm run build` before claiming completion.

7. **Safe Archiving & Soft-Delete Invariant**:
   - Leads must NEVER be permanently deleted directly from active pipelines. Deletion must strictly follow the two-stage lifecycle: Soft Delete (`deleted_at` via `SoftDeletes`) -> Archived status.
   - Reference ID generation (`Lead::generateReferenceId()`) must ALWAYS query `static::withTrashed()` so sequential numbering remains monotonic and never collides with archived records.
   - Permanent deletion (`forceDelete()`) is restricted to administrators, only permitted on records already soft-deleted, executed inside a database transaction cascading to activities, follow-ups, and notifications, and must be guarded by strict typed confirmation on the frontend.
   - Mobile synchronization must propagate lead deletions (`deleted_lead_ids` via `/api/mobile/sync/pull`) so archived records are purged from local caches and pending alarms cancelled.

8. **Employee Deactivation Safeguards**:
   - The system must never permit deactivation of the last active administrator (`activeAdminsCount <= 1`).
   - Self-deactivation is strictly forbidden.
   - Deactivation must automatically revoke all active personal access tokens (`tokens()->delete()`) and purge all push device tokens (`deviceTokens()->delete()`).
   - Deactivated employees retain their historic lead attribution unless explicitly reassigned via the bulk reassignment endpoint (`/api/employees/{id}/reassign-leads`).

9. **Permanent Production Database Protection Rule**:
   - **Strict Protection**: The production database contains real business data and must NEVER be modified during development, testing, security verification, or staging preparation.
   - **Default Posture**: Production database is **READ-ONLY** and **OUT OF SCOPE** for all development and staging activities.
   - **Strictly Prohibited Without Separate Explicit Authorization**:
     - `php artisan migrate` against production.
     - `php artisan migrate:fresh`, `migrate:refresh`, or `migrate:rollback` against production.
     - Running database seeders (`db:seed`) against production.
     - Modifying users, employees, roles, permissions, or teams.
     - Revoking or regenerating production Personal Access Tokens.
     - Performing bulk updates, bulk deletes, cleanups, or table truncations.
     - Automated scripts, deployment hooks, or CI/CD pipelines writing to production.
     - Any application or database operation that modifies live business records.
   - **Mandatory Environment Separation**:
     1. Local development must exclusively use `vista_crm_local`.
     2. Staging must use a dedicated, isolated staging database.
     3. Production remains separate, isolated, and untouched.
     4. Never copy staging configuration or `.env` into production.
     5. Never point local tests, seeders, or staging scripts at production.
     6. Verify database host, database name, port, and `APP_ENV` before executing any database operation.
   - **Staging Deployment Safeguards**:
     - Confirm staging database identity prior to executing commands.
     - Confirm production credentials are NOT in use.
     - Audit deployment scripts and hooks for implicit migrations or seeders.
     - Verify zero production database connections are established.
   - **Final Release Condition**:
     - No release can be approved if it requires unapproved production database migrations or modifications.
     - Any future production database change requires an independent migration plan, backup and recovery strategy, impact assessment, and explicit authorization.

