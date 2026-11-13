# Vistabound CRM — Role-Based Access Control (RBAC) & Permission Engine Specification

## 1. Overview & Architecture

Vistabound CRM implements a centralized, granular Role-Based Access Control (RBAC) architecture with individual user overrides and cross-platform synchronization (Web and Capacitor Android).

The backend (Laravel 11 / PHP 8.4) serves as the **single source of truth**. Both the Vue 3 desktop web application and Capacitor Android application consume identical effective permissions and visibility scopes.

---

## 2. Inherit / Allow / Deny Resolution Model

Permissions are evaluated dynamically on the `User` model using a three-tier hierarchy:

```
[Administrator (role = 'admin')] -> Unrestricted access (Bypasses all checks)
      │
      ▼
[User-Level Explicit Override] (user_has_permissions: 'allow' | 'deny')
      │  ├─ 'allow' -> Granted (Overrides role omission)
      │  └─ 'deny'  -> Denied  (Overrides role grant)
      ▼
[Role Template Default] (role_has_permissions)
      │  ├─ Granted in role -> Granted
      │  └─ Omitted in role -> Denied
      ▼
[Default Fallback] -> Denied
```

### Effective Permission Matrix Resolution

```php
$override = DB::table('user_has_permissions')
    ->where('user_id', $user->id)
    ->where('permission_id', $perm->id)
    ->value('effect'); // 'allow' | 'deny' | null

if ($override === 'allow') return true;
if ($override === 'deny') return false;

// Inherit from role template
return $user->roleRelation?->hasPermission($permissionName) ?? false;
```

---

## 3. Database Foundations

The following 10 relational tables govern access control:

| Table | Purpose |
|---|---|
| `roles` | Role templates (`admin`, `sales_manager`, `sales_executive`, `accounts_executive`, custom roles) |
| `permissions` | Granular capability slugs (`leads.view`, `leads.create`, `leads.edit`, `leads.assign`, `leads.archive`, `leads.restore`, `leads.force_delete`, `leads.export`, `leads.import`, `follow_ups.manage`, `whatsapp.send`, `employees.manage`, `roles.manage`, `settings.manage`) |
| `teams` | Organizational units for team-scoped lead collaboration (`Corporate Sales Unit`, `Retail & SMB Unit`) |
| `role_has_permissions` | Permissions granted to each role template |
| `user_has_permissions` | Explicit user overrides (`effect`: `allow` or `deny`, with `granted_by` audit trail) |
| `role_visibility_scopes` | Default visibility scope per role (`assigned_only`, `team_only`, `all`) |
| `user_visibility_overrides`| Explicit user pipeline visibility override (`scope`: `assigned_only`, `team_only`, `all`) |
| `role_category_assignments`| Service vertical restrictions per role template |
| `user_category_assignments`| Explicit vertical restrictions per employee |
| `permission_audit_logs` | Immutable chronological trail of all permission/role changes |

---

## 4. Visibility Scopes & Category Restrictions

### Visibility Scopes
1. **`assigned_only`** (Default): Representative only has access to leads where `assigned_to == user.id`.
2. **`team_only`**: Access extended to all team members belonging to the same `team_id`.
3. **`all`**: Unrestricted pipeline access across the entire organization.

### Category (Vertical) Restrictions
- Leads feature a `service_interest` vertical (e.g., *Cloud & DevOps Solutions*, *Enterprise Software Engineering*, *IT Infrastructure & Hardware AMC*, *Retail & Commerce Solutions*, *Hospitality Management*).
- Roles or individual users can be restricted to specific service categories.
- If restricted, `Lead::accessibleBy($user)` and `User::canAccessLead($lead)` reject leads outside the permitted verticals.

---

## 5. API Endpoints

All endpoints require Sanctum bearer token authentication.

### Role Templates
- `GET /api/roles`: List all system and custom role templates with scopes and user counts.
- `POST /api/roles`: Create a new custom role template (`roles.manage` required).
- `PUT /api/roles/{id}`: Update an existing role template.
- `DELETE /api/roles/{id}`: Delete a non-system role template.

### User Overrides
- `GET /api/users/{id}/permissions`: Fetch user permission matrix, visibility overrides, and category restrictions.
- `POST|PUT /api/users/{id}/permissions`: Save individual `allow` or `deny` overrides.
- `POST|PUT /api/users/{id}/visibility`: Set visibility override (`inherit`, `assigned_only`, `team_only`, `all`).
- `POST|PUT /api/users/{id}/categories`: Restrict user to specific service verticals.
- `POST /api/users/{id}/permissions/reset`: Purge all user-level overrides and revert to clean role defaults.

### Audit Trail
- `GET /api/audit-logs`: Paginated log of all access control modifications with actor, target, diff, IP, and timestamp.

---

## 6. Frontend & Mobile Integration

### Web Application (`resources/js`)
- **`RolesPermissionsView.vue`**: Three-tab management dashboard for Role Templates, User Overrides (3-way `Inherit` / `Allow` / `Deny` segmented controls), and the Audit Trail.
- **`AppLayout.vue`**: Navigation and action buttons guarded via `authStore.can(permission)`.
- **`EmployeesView.vue`**: Role Template and Team assignment selectors, plus direct "Configure Permissions" action.
- **`LeadsListView.vue` & `LeadDetailView.vue`**: Granular button protection for export, creation, reassignment, archiving, and permanent deletion.

### Capacitor Mobile (`android`)
- Backend `MobileSyncController@pull` delivers `permissions`, `visibility_scope`, and `allowed_categories` in the sync payload.
- Sync push mutations enforce `canAccessLead()` and functional permissions before processing offline entries.

---

## 7. Verification & Automated Test Coverage

Full test suite verified via `php artisan test`:
- `tests/Feature/PermissionManagementTest.php`:
  1. `test_role_template_inheritance` (Verified)
  2. `test_user_explicit_allow_overrides_role_omission` (Verified)
  3. `test_user_explicit_deny_overrides_role_grant` (Verified)
  4. `test_reset_user_permissions_reverts_to_role_default` (Verified)
  5. `test_lead_visibility_scopes_assigned_team_and_all` (Verified)
  6. `test_category_based_vertical_restrictions` (Verified)
  7. `test_unauthorized_user_cannot_access_roles_endpoints` (Verified)
- Full regression suite: **51 tests passed, 254 assertions, 0 failures**.
