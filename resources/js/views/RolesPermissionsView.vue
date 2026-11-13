<template>
  <AppLayout>
    <div class="space-y-6 max-w-6xl mx-auto">
      <!-- Page Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-widest text-vb-violet font-display">Access Control</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-vb-plum/10 text-vb-plum">RBAC Engine</span>
          </div>
          <h2 class="text-2xl font-display font-bold text-vb-plum mt-1">Role & Permission Management</h2>
          <p class="text-xs text-vb-muted mt-0.5">
            Configure system role templates, fine-tune individual employee permission overrides (Inherit / Allow / Deny), and audit access policy transitions.
          </p>
        </div>

        <button
          v-if="activeTab === 'roles'"
          @click="openCreateRoleModal"
          class="btn-primary text-xs font-semibold px-4 py-2 rounded-full flex items-center gap-1.5 shadow-md self-start sm:self-auto"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>Create Role Template</span>
        </button>
      </div>

      <!-- Navigation Tabs: Role Templates | User Overrides | Audit Trail -->
      <div class="flex items-center gap-2 border-b border-vb-plum/10 pb-3 text-xs font-semibold">
        <button
          type="button"
          @click="activeTab = 'roles'"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'roles' ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <Shield class="w-4 h-4" />
          <span>Role Templates</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px]"
            :class="activeTab === 'roles' ? 'bg-white/20 text-white' : 'bg-vb-plum/10 text-vb-plum'"
          >
            {{ roles.length }}
          </span>
        </button>

        <button
          type="button"
          @click="activeTab = 'users'"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'users' ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <Users class="w-4 h-4" />
          <span>User Permission Overrides</span>
        </button>

        <button
          type="button"
          @click="activeTab = 'audit'"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'audit' ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <History class="w-4 h-4" />
          <span>Audit Trail</span>
        </button>
      </div>

      <!-- ================= TAB 1: ROLE TEMPLATES ================= -->
      <div v-if="activeTab === 'roles'" class="space-y-6">
        <div class="card-glass overflow-hidden shadow-sm">
          <div v-if="loadingRoles" class="py-16 flex justify-center">
            <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
          </div>

          <div v-else-if="roles.length === 0" class="py-16 text-center">
            <Shield class="w-12 h-12 text-vb-muted/40 mx-auto mb-3" />
            <h3 class="font-display font-bold text-base text-vb-plum">No roles configured</h3>
            <p class="text-xs text-vb-muted mt-1">Initialize default roles or create a new template.</p>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-vb-cream/80 border-b border-vb-plum/10 text-vb-plum font-semibold">
                <tr>
                  <th class="py-3 px-4">Role Template</th>
                  <th class="py-3 px-4">Visibility Scope</th>
                  <th class="py-3 px-4">Category Restrictions</th>
                  <th class="py-3 px-4 text-center">Permissions</th>
                  <th class="py-3 px-4 text-center">Assigned Users</th>
                  <th class="py-3 px-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-vb-plum/5">
                <tr v-for="role in roles" :key="role.id" class="hover:bg-white/80 transition-colors">
                  <!-- Name & Slug -->
                  <td class="py-3.5 px-4">
                    <div class="flex items-center gap-2.5">
                      <div class="w-8 h-8 rounded-lg bg-vb-plum/10 text-vb-plum flex items-center justify-center font-bold">
                        <ShieldCheck v-if="role.is_system" class="w-4 h-4" />
                        <Shield v-else class="w-4 h-4" />
                      </div>
                      <div>
                        <div class="flex items-center gap-2">
                          <span class="font-bold text-vb-plum text-sm">{{ role.display_name }}</span>
                          <span
                            v-if="role.is_system"
                            class="px-1.5 py-0.2 rounded-md bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-semibold"
                          >
                            System Role
                          </span>
                        </div>
                        <div class="text-[11px] text-vb-muted font-mono mt-0.5">
                          slug: {{ role.name }}
                        </div>
                        <p v-if="role.description" class="text-[11px] text-vb-muted mt-0.5 max-w-xs truncate">
                          {{ role.description }}
                        </p>
                      </div>
                    </div>
                  </td>

                  <!-- Visibility Scope -->
                  <td class="py-3.5 px-4">
                    <span
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                      :class="getVisibilityBadgeClass(role.visibility_scope)"
                    >
                      <span class="w-1.5 h-1.5 rounded-full" :class="getVisibilityDotClass(role.visibility_scope)"></span>
                      {{ formatScopeLabel(role.visibility_scope) }}
                    </span>
                  </td>

                  <!-- Category Restrictions -->
                  <td class="py-3.5 px-4">
                    <div v-if="!role.allowed_categories || role.allowed_categories.length === 0" class="text-emerald-700 font-semibold text-[11px] flex items-center gap-1">
                      <CheckCircle2 class="w-3.5 h-3.5" />
                      <span>All Verticals (Unrestricted)</span>
                    </div>
                    <div v-else class="space-y-1">
                      <span class="text-[10px] font-bold text-vb-violet">
                        {{ role.allowed_categories.length }} Vertical{{ role.allowed_categories.length > 1 ? 's' : '' }}
                      </span>
                      <div class="flex flex-wrap gap-1 max-w-xs">
                        <span
                          v-for="cat in role.allowed_categories.slice(0, 2)"
                          :key="cat"
                          class="px-1.5 py-0.5 rounded bg-vb-plum/5 text-vb-plum text-[10px] truncate max-w-[120px]"
                          :title="cat"
                        >
                          {{ cat }}
                        </span>
                        <span
                          v-if="role.allowed_categories.length > 2"
                          class="px-1.5 py-0.5 rounded bg-vb-plum/10 text-vb-plum text-[10px] font-bold"
                        >
                          +{{ role.allowed_categories.length - 2 }} more
                        </span>
                      </div>
                    </div>
                  </td>

                  <!-- Permissions Count -->
                  <td class="py-3.5 px-4 text-center">
                    <span class="px-2 py-0.5 rounded-full bg-vb-violet/10 text-vb-violet font-bold text-xs">
                      {{ role.permissions_count }} granted
                    </span>
                  </td>

                  <!-- Users Count -->
                  <td class="py-3.5 px-4 text-center">
                    <span class="font-bold text-vb-plum">{{ role.users_count }}</span>
                  </td>

                  <!-- Actions -->
                  <td class="py-3.5 px-4 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                      <button
                        @click="openEditRoleModal(role)"
                        class="px-3 py-1 rounded-lg border border-vb-plum/20 hover:bg-vb-plum/5 text-vb-plum font-semibold text-xs transition-colors"
                      >
                        Edit
                      </button>
                      <button
                        v-if="!role.is_system"
                        @click="deleteRolePrompt(role)"
                        class="p-1 rounded-lg text-vb-muted hover:text-rose-600 hover:bg-rose-50 transition-colors"
                        title="Delete Role"
                      >
                        <Trash2 class="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ================= TAB 2: USER PERMISSION OVERRIDES ================= -->
      <div v-if="activeTab === 'users'" class="space-y-6">
        <!-- User Selection Bar -->
        <div class="card-glass p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex-1 max-w-md">
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
              Select Employee Account
            </label>
            <select
              v-model="selectedUserId"
              @change="fetchUserPermissions"
              class="field text-sm font-semibold"
            >
              <option :value="null" disabled>Choose an employee to manage...</option>
              <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                {{ emp.name }} ({{ emp.role_relation?.display_name || emp.role }}) - {{ emp.email }}
              </option>
            </select>
          </div>

          <div v-if="selectedUser" class="flex flex-wrap items-center gap-3">
            <div class="text-right hidden sm:block">
              <div class="text-xs font-bold text-vb-plum">{{ selectedUser.user.name }}</div>
              <div class="text-[11px] text-vb-muted">Base Role: {{ selectedUser.user.role_display_name }}</div>
            </div>
            <button
              @click="resetUserOverridesPrompt"
              :disabled="savingUser"
              class="px-3.5 py-2 rounded-xl border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-semibold flex items-center gap-1.5 transition-colors"
              title="Clears all individual Allow/Deny overrides and reverts to base role template"
            >
              <RotateCcw class="w-3.5 h-3.5" />
              <span>Reset to Role Defaults</span>
            </button>
            <button
              @click="saveUserOverrides"
              :disabled="savingUser"
              class="btn-primary text-xs font-semibold px-4 py-2 rounded-xl flex items-center gap-1.5 shadow-sm"
            >
              <Check class="w-4 h-4" />
              <span>{{ savingUser ? 'Saving...' : 'Save User Configuration' }}</span>
            </button>
          </div>
        </div>

        <div v-if="loadingUser" class="py-16 flex justify-center">
          <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
        </div>

        <div v-else-if="!selectedUser" class="card-glass py-16 text-center">
          <Users class="w-12 h-12 text-vb-muted/40 mx-auto mb-3" />
          <h3 class="font-display font-bold text-base text-vb-plum">Select an employee account above</h3>
          <p class="text-xs text-vb-muted mt-1 max-w-sm mx-auto">
            Review effective access, configure explicit Allow or Deny overrides, and adjust visibility scopes.
          </p>
        </div>

        <div v-else class="space-y-6">
          <!-- Visibility Scope & Category Overrides Row -->
          <div class="grid lg:grid-cols-2 gap-6">
            <!-- Visibility Scope Override Box -->
            <div class="card-glass p-5 space-y-4">
              <div class="flex items-center justify-between border-b border-vb-plum/10 pb-3">
                <div>
                  <h3 class="font-display font-bold text-sm text-vb-plum">Lead Visibility Scope</h3>
                  <p class="text-[11px] text-vb-muted">Determines pipeline boundaries for this employee</p>
                </div>
                <div class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-vb-plum/5 text-vb-plum">
                  Role: {{ formatScopeLabel(selectedUser.visibility.role_scope) }}
                </div>
              </div>

              <div class="space-y-2">
                <label
                  v-for="opt in visibilityScopeOptions"
                  :key="opt.value"
                  class="flex items-start gap-3 p-2.5 rounded-xl border transition-all cursor-pointer"
                  :class="userVisibilityScope === opt.value ? 'bg-vb-plum/5 border-vb-plum text-vb-plum font-semibold' : 'border-vb-plum/10 hover:bg-white text-vb-ink'"
                >
                  <input
                    type="radio"
                    name="user_visibility_scope"
                    :value="opt.value"
                    v-model="userVisibilityScope"
                    class="mt-1 text-vb-plum focus:ring-vb-plum"
                  />
                  <div>
                    <div class="text-xs font-bold">{{ opt.label }}</div>
                    <div class="text-[11px] text-vb-muted mt-0.5">{{ opt.description }}</div>
                  </div>
                </label>
              </div>
            </div>

            <!-- Category / Vertical Restrictions Box -->
            <div class="card-glass p-5 space-y-4">
              <div class="flex items-center justify-between border-b border-vb-plum/10 pb-3">
                <div>
                  <h3 class="font-display font-bold text-sm text-vb-plum">Category / Vertical Restrictions</h3>
                  <p class="text-[11px] text-vb-muted">Limit lead pipeline visibility to specific business verticals</p>
                </div>
              </div>

              <div class="space-y-2">
                <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-vb-cream/50 cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="userCategoryModeAll"
                    class="rounded text-vb-plum focus:ring-vb-plum"
                  />
                  <span class="text-xs font-semibold text-vb-plum">
                    Unrestricted (Access all service verticals)
                  </span>
                </label>

                <div v-if="!userCategoryModeAll" class="pt-2 border-t border-vb-plum/5 space-y-1.5 max-h-48 overflow-y-auto">
                  <label
                    v-for="cat in availableCategories"
                    :key="cat"
                    class="flex items-center justify-between p-2 rounded-lg hover:bg-white border border-vb-plum/5 cursor-pointer text-xs"
                  >
                    <span>{{ cat }}</span>
                    <input
                      type="checkbox"
                      :value="cat"
                      v-model="userSelectedCategories"
                      class="rounded text-vb-plum focus:ring-vb-plum"
                    />
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Permission Matrix Table -->
          <div class="card-glass overflow-hidden shadow-sm">
            <div class="p-4 border-b border-vb-plum/10 bg-vb-cream/40 flex items-center justify-between">
              <div>
                <h3 class="font-display font-bold text-sm text-vb-plum">Functional Permission Matrix</h3>
                <p class="text-[11px] text-vb-muted">
                  Explicit overrides take precedence over role template defaults.
                </p>
              </div>
              <div class="flex items-center gap-3 text-xs">
                <span class="flex items-center gap-1.5 text-vb-muted">
                  <span class="w-2.5 h-2.5 rounded-full bg-zinc-300"></span>
                  <span>Inherit (Role Default)</span>
                </span>
                <span class="flex items-center gap-1.5 text-emerald-700 font-semibold">
                  <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                  <span>Allow (Explicit)</span>
                </span>
                <span class="flex items-center gap-1.5 text-rose-700 font-semibold">
                  <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                  <span>Deny (Explicit)</span>
                </span>
              </div>
            </div>

            <div class="divide-y divide-vb-plum/10">
              <div v-for="(groupPerms, groupName) in groupedUserPermissions" :key="groupName" class="p-4 space-y-3">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold uppercase tracking-wider text-vb-violet">
                    {{ formatGroupName(groupName) }} Module
                  </span>
                  <span class="text-[10px] text-vb-muted">({{ groupPerms.length }} permissions)</span>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                  <div
                    v-for="perm in groupPerms"
                    :key="perm.name"
                    class="p-3 rounded-xl bg-white border transition-all flex flex-col justify-between gap-3 shadow-2xs"
                    :class="getPermCardBorderClass(perm)"
                  >
                    <div>
                      <div class="flex items-start justify-between gap-2">
                        <span class="text-xs font-bold text-vb-plum">{{ perm.display_name }}</span>
                        <!-- Effective Result Badge -->
                        <span
                          class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider"
                          :class="perm.effective ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-500 border border-gray-200'"
                        >
                          {{ perm.effective ? 'Granted' : 'Denied' }}
                        </span>
                      </div>
                      <p class="text-[11px] text-vb-muted mt-1 leading-snug">{{ perm.description }}</p>
                      <div class="text-[10px] text-vb-muted mt-1.5 font-mono">
                        Role default:
                        <span :class="perm.role_default ? 'text-emerald-600 font-bold' : 'text-zinc-500'">
                          {{ perm.role_default ? 'Allow' : 'Omit' }}
                        </span>
                      </div>
                    </div>

                    <!-- 3-Way Segmented Control: Inherit | Allow | Deny -->
                    <div class="grid grid-cols-3 gap-1 bg-vb-cream/70 p-1 rounded-lg text-[10px] font-semibold text-center">
                      <button
                        type="button"
                        @click="setPermissionOverride(perm.name, 'inherit')"
                        class="py-1 rounded-md transition-all"
                        :class="perm.override === 'inherit' ? 'bg-white text-vb-ink font-bold shadow-xs' : 'text-vb-muted hover:text-vb-plum'"
                      >
                        Inherit
                      </button>
                      <button
                        type="button"
                        @click="setPermissionOverride(perm.name, 'allow')"
                        class="py-1 rounded-md transition-all"
                        :class="perm.override === 'allow' ? 'bg-emerald-600 text-white font-bold shadow-xs' : 'text-vb-muted hover:text-emerald-700'"
                      >
                        Allow
                      </button>
                      <button
                        type="button"
                        @click="setPermissionOverride(perm.name, 'deny')"
                        class="py-1 rounded-md transition-all"
                        :class="perm.override === 'deny' ? 'bg-rose-600 text-white font-bold shadow-xs' : 'text-vb-muted hover:text-rose-700'"
                      >
                        Deny
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ================= TAB 3: AUDIT TRAIL ================= -->
      <div v-if="activeTab === 'audit'" class="space-y-6">
        <div class="card-glass overflow-hidden shadow-sm">
          <div class="p-4 border-b border-vb-plum/10 bg-vb-cream/40 flex items-center justify-between">
            <div>
              <h3 class="font-display font-bold text-sm text-vb-plum">Permission & Access Audit Trail</h3>
              <p class="text-[11px] text-vb-muted">
                Immutable chronological log of all role creations, policy edits, and employee permission overrides.
              </p>
            </div>
            <button
              @click="fetchAuditLogs"
              :disabled="loadingAudit"
              class="px-3 py-1.5 rounded-lg border border-vb-plum/20 hover:bg-vb-plum/5 text-xs font-semibold text-vb-plum flex items-center gap-1.5"
            >
              <RefreshCw class="w-3.5 h-3.5" :class="{'animate-spin': loadingAudit}" />
              <span>Refresh</span>
            </button>
          </div>

          <div v-if="loadingAudit" class="py-16 flex justify-center">
            <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
          </div>

          <div v-else-if="auditLogs.length === 0" class="py-16 text-center">
            <History class="w-12 h-12 text-vb-muted/40 mx-auto mb-3" />
            <h3 class="font-display font-bold text-base text-vb-plum">No audit logs recorded yet</h3>
            <p class="text-xs text-vb-muted mt-1">Changes to roles and user overrides will appear here.</p>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-vb-cream/80 border-b border-vb-plum/10 text-vb-plum font-semibold">
                <tr>
                  <th class="py-3 px-4">Timestamp (IST)</th>
                  <th class="py-3 px-4">Actor</th>
                  <th class="py-3 px-4">Action</th>
                  <th class="py-3 px-4">Target</th>
                  <th class="py-3 px-4">Summary / Changes</th>
                  <th class="py-3 px-4 text-right">IP Address</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-vb-plum/5 font-sans">
                <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-white/80 transition-colors">
                  <td class="py-3 px-4 font-mono text-[11px] text-vb-muted whitespace-nowrap">
                    {{ formatTimestamp(log.created_at) }}
                  </td>
                  <td class="py-3 px-4">
                    <div class="font-bold text-vb-plum">{{ log.actor?.name || 'System' }}</div>
                    <div class="text-[10px] text-vb-muted">{{ log.actor?.email }}</div>
                  </td>
                  <td class="py-3 px-4">
                    <span
                      class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                      :class="getActionBadgeClass(log.action)"
                    >
                      {{ formatActionName(log.action) }}
                    </span>
                  </td>
                  <td class="py-3 px-4 font-semibold text-vb-ink">
                    <span class="capitalize">{{ log.target_type }}</span> #{{ log.target_id }}
                  </td>
                  <td class="py-3 px-4 max-w-sm">
                    <div class="text-[11px] text-vb-ink truncate font-mono bg-vb-cream/40 p-1.5 rounded border border-vb-plum/5">
                      {{ formatLogDetails(log.details) }}
                    </div>
                  </td>
                  <td class="py-3 px-4 text-right font-mono text-[11px] text-vb-muted">
                    {{ log.ip_address || '—' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ================= CREATE / EDIT ROLE MODAL ================= -->
      <div
        v-if="roleModalOpen"
        class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto"
      >
        <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-vb-plum/10 my-8 space-y-5">
          <div class="flex items-center justify-between border-b border-vb-plum/10 pb-3">
            <div>
              <h3 class="font-display font-bold text-lg text-vb-plum">
                {{ editingRoleId ? 'Edit Role Template' : 'Create Role Template' }}
              </h3>
              <p class="text-xs text-vb-muted">Configure template visibility, category boundaries, and granted permissions.</p>
            </div>
            <button @click="roleModalOpen = false" class="p-1 rounded-lg text-vb-muted hover:text-vb-plum">
              <X class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="submitRoleForm" class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">
                  Display Name *
                </label>
                <input
                  v-model="roleForm.display_name"
                  required
                  placeholder="e.g. Senior Client Executive"
                  class="field text-sm"
                />
              </div>

              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">
                  Slug / Identifier *
                </label>
                <input
                  v-model="roleForm.name"
                  required
                  :disabled="!!editingRoleId"
                  placeholder="e.g. senior_client_exec"
                  class="field text-sm font-mono"
                  :class="editingRoleId ? 'bg-zinc-100 text-zinc-500 cursor-not-allowed' : ''"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">
                Description
              </label>
              <textarea
                v-model="roleForm.description"
                rows="2"
                placeholder="Brief summary of duties and authority under this role..."
                class="field text-xs"
              ></textarea>
            </div>

            <!-- Role Visibility Scope -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Default Pipeline Visibility Scope *
              </label>
              <div class="grid sm:grid-cols-3 gap-2">
                <label
                  v-for="opt in roleScopeOptions"
                  :key="opt.value"
                  class="p-2.5 rounded-xl border text-center cursor-pointer transition-all"
                  :class="roleForm.visibility_scope === opt.value ? 'bg-vb-plum text-white font-bold border-vb-plum shadow-sm' : 'border-vb-plum/10 hover:bg-vb-cream text-vb-ink'"
                >
                  <input
                    type="radio"
                    name="role_scope"
                    :value="opt.value"
                    v-model="roleForm.visibility_scope"
                    class="sr-only"
                  />
                  <div class="text-xs">{{ opt.label }}</div>
                  <div class="text-[10px] opacity-80 mt-0.5">{{ opt.sub }}</div>
                </label>
              </div>
            </div>

            <!-- Category Restrictions for Role -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="text-xs font-semibold uppercase tracking-wider text-vb-plum">
                  Service Category Scope
                </label>
                <button
                  type="button"
                  @click="toggleRoleCategoryAll"
                  class="text-[11px] text-vb-violet font-semibold hover:underline"
                >
                  {{ roleForm.allowed_categories.length === 0 ? 'Restrict to specific verticals' : 'Allow All Verticals' }}
                </button>
              </div>

              <div v-if="roleForm.allowed_categories.length > 0" class="p-3 rounded-xl border border-vb-plum/10 bg-vb-cream/30 space-y-1.5 max-h-36 overflow-y-auto">
                <label
                  v-for="cat in availableCategories"
                  :key="cat"
                  class="flex items-center justify-between p-1.5 rounded hover:bg-white text-xs cursor-pointer"
                >
                  <span>{{ cat }}</span>
                  <input
                    type="checkbox"
                    :value="cat"
                    v-model="roleForm.allowed_categories"
                    class="rounded text-vb-plum focus:ring-vb-plum"
                  />
                </label>
              </div>
              <div v-else class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2">
                <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                <span>All service categories permitted without restriction</span>
              </div>
            </div>

            <!-- Permissions Selection Matrix -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
                Granted Permissions ({{ roleForm.permissions.length }} selected)
              </label>
              <div class="max-h-56 overflow-y-auto space-y-3 p-3 rounded-xl border border-vb-plum/10 bg-vb-cream/30">
                <div v-for="(perms, group) in allPermissionsGrouped" :key="group" class="space-y-1.5">
                  <div class="text-[11px] font-bold uppercase tracking-wider text-vb-violet">
                    {{ formatGroupName(group) }}
                  </div>
                  <div class="grid sm:grid-cols-2 gap-1.5">
                    <label
                      v-for="p in perms"
                      :key="p.name"
                      class="flex items-start gap-2 p-1.5 rounded hover:bg-white text-xs cursor-pointer border border-transparent hover:border-vb-plum/10"
                    >
                      <input
                        type="checkbox"
                        :value="p.name"
                        v-model="roleForm.permissions"
                        class="mt-0.5 rounded text-vb-plum focus:ring-vb-plum"
                      />
                      <div>
                        <div class="font-bold text-vb-ink text-[11px]">{{ p.display_name }}</div>
                        <div class="text-[10px] text-vb-muted leading-tight">{{ p.description }}</div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Error Banner -->
            <div v-if="roleFormError" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
              <AlertCircle class="w-4 h-4 shrink-0" />
              <span>{{ roleFormError }}</span>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-vb-plum/10">
              <button
                type="button"
                @click="roleModalOpen = false"
                class="px-4 py-2 rounded-xl text-xs font-semibold text-vb-muted hover:text-vb-plum"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="savingRole"
                class="btn-primary text-xs font-semibold px-5 py-2.5 rounded-xl shadow-sm"
              >
                {{ savingRole ? 'Saving...' : (editingRoleId ? 'Update Role' : 'Create Role') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Role Confirmation Modal -->
      <ConfirmModal
        :open="confirmDeleteOpen"
        title="Delete Role Template"
        :message="`Are you sure you want to delete role '${roleToDelete?.display_name}'? Users must be reassigned before deletion.`"
        confirm-label="Delete Role"
        danger
        @confirm="confirmDeleteRole"
        @cancel="confirmDeleteOpen = false"
      />

      <!-- Reset User Overrides Confirmation Modal -->
      <ConfirmModal
        :open="confirmResetOpen"
        title="Reset User Overrides to Role Default"
        :message="`This will purge all custom Allow/Deny overrides, visibility overrides, and category restrictions for ${selectedUser?.user?.name}. The user will cleanly inherit from their '${selectedUser?.user?.role_display_name}' role template.`"
        confirm-label="Reset to Defaults"
        danger
        @confirm="confirmResetUserOverrides"
        @cancel="confirmResetOpen = false"
      />
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Capacitor } from '@capacitor/core';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import ConfirmModal from '../components/ConfirmModal.vue';
import {
  Shield,
  ShieldCheck,
  Users,
  History,
  Plus,
  Trash2,
  X,
  Check,
  RotateCcw,
  CheckCircle2,
  RefreshCw,
  AlertCircle,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();

// Active tab: 'roles' | 'users' | 'audit'
const activeTab = ref<'roles' | 'users' | 'audit'>('roles');

// Roles State
const roles = ref<any[]>([]);
const loadingRoles = ref<boolean>(false);
const availableCategories = ref<string[]>([]);
const allPermissionsGrouped = ref<Record<string, any[]>>({});

// Employees List (for user picker)
const employees = ref<any[]>([]);
const selectedUserId = ref<number | null>(null);
const selectedUser = ref<any | null>(null);
const loadingUser = ref<boolean>(false);
const savingUser = ref<boolean>(false);

// User Overrides State
const userVisibilityScope = ref<string>('inherit');
const userCategoryModeAll = ref<boolean>(true);
const userSelectedCategories = ref<string[]>([]);
const userPermissionOverrides = reactive<Record<string, 'inherit' | 'allow' | 'deny'>>({});

// Audit Trail State
const auditLogs = ref<any[]>([]);
const loadingAudit = ref<boolean>(false);

// Role Modal State
const roleModalOpen = ref<boolean>(false);
const editingRoleId = ref<number | null>(null);
const savingRole = ref<boolean>(false);
const roleFormError = ref<string>('');
const roleForm = reactive({
  name: '',
  display_name: '',
  description: '',
  visibility_scope: 'assigned_only',
  allowed_categories: [] as string[],
  permissions: [] as string[],
});

// Confirmation Modals
const confirmDeleteOpen = ref<boolean>(false);
const roleToDelete = ref<any | null>(null);
const confirmResetOpen = ref<boolean>(false);

const roleScopeOptions = [
  { value: 'assigned_only', label: 'Assigned Leads Only', sub: 'Standard sales representative' },
  { value: 'team_only', label: 'Team Leads Only', sub: 'Team leader or unit manager' },
  { value: 'all', label: 'All Organization Leads', sub: 'Executive / General Manager' },
];

const visibilityScopeOptions = [
  { value: 'inherit', label: 'Inherit from Role Template', description: 'Dynamically follow whichever visibility scope is set on the assigned role' },
  { value: 'assigned_only', label: 'Assigned Leads Only', description: 'Restrict user strictly to leads where assigned_to matches this user ID' },
  { value: 'team_only', label: 'Team Leads Only', description: 'Grant visibility to all leads assigned across this user\'s team unit' },
  { value: 'all', label: 'All Organization Leads', description: 'Grant unrestricted enterprise visibility across all active leads' },
];

const formatScopeLabel = (scope: string) => {
  switch (scope) {
    case 'assigned_only': return 'Assigned Only';
    case 'team_only': return 'Team Only';
    case 'all': return 'All Leads';
    case 'inherit': return 'Inherited from Role';
    default: return scope;
  }
};

const getVisibilityBadgeClass = (scope: string) => {
  switch (scope) {
    case 'assigned_only': return 'bg-zinc-100 text-zinc-700 border border-zinc-200';
    case 'team_only': return 'bg-sky-50 text-sky-700 border border-sky-200';
    case 'all': return 'bg-vb-violet/10 text-vb-violet border border-vb-violet/20';
    default: return 'bg-zinc-100 text-zinc-700';
  }
};

const getVisibilityDotClass = (scope: string) => {
  switch (scope) {
    case 'assigned_only': return 'bg-zinc-400';
    case 'team_only': return 'bg-sky-500';
    case 'all': return 'bg-vb-violet';
    default: return 'bg-zinc-400';
  }
};

const formatGroupName = (group: string) => {
  switch (group) {
    case 'leads': return 'Lead Pipeline';
    case 'follow_ups': return 'Follow-ups';
    case 'whatsapp': return 'WhatsApp Outreach';
    case 'employees': return 'Employee Accounts';
    case 'roles': return 'Role Management';
    case 'settings': return 'System Settings';
    default: return group;
  }
};

const formatTimestamp = (ts: string) => {
  if (!ts) return '—';
  try {
    const d = new Date(ts);
    return d.toLocaleString('en-IN', {
      timeZone: 'Asia/Kolkata',
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  } catch {
    return ts;
  }
};

const formatActionName = (action: string) => {
  return action.replace(/_/g, ' ');
};

const getActionBadgeClass = (action: string) => {
  if (action.includes('created')) return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
  if (action.includes('deleted') || action.includes('reset')) return 'bg-rose-50 text-rose-700 border border-rose-200';
  return 'bg-vb-plum/10 text-vb-plum border border-vb-plum/20';
};

const formatLogDetails = (details: any) => {
  if (!details) return '—';
  if (typeof details === 'string') return details;
  return JSON.stringify(details);
};

// Grouped permissions for current user
const groupedUserPermissions = computed(() => {
  if (!selectedUser.value?.permissions_matrix) return {};
  const map: Record<string, any[]> = {};
  for (const p of selectedUser.value.permissions_matrix) {
    const g = p.group || 'general';
    if (!map[g]) map[g] = [];
    map[g].push({
      ...p,
      override: userPermissionOverrides[p.name] ?? p.override,
      effective: computeEffective(p, userPermissionOverrides[p.name] ?? p.override),
    });
  }
  return map;
});

const computeEffective = (p: any, override: string) => {
  if (selectedUser.value?.user?.role === 'admin') return true;
  if (override === 'allow') return true;
  if (override === 'deny') return false;
  return !!p.role_default;
};

const getPermCardBorderClass = (perm: any) => {
  if (perm.override === 'allow') return 'border-emerald-300 ring-1 ring-emerald-200 bg-emerald-50/20';
  if (perm.override === 'deny') return 'border-rose-300 ring-1 ring-rose-200 bg-rose-50/20';
  return 'border-vb-plum/10';
};

const setPermissionOverride = (permName: string, effect: 'inherit' | 'allow' | 'deny') => {
  userPermissionOverrides[permName] = effect;
};

// Fetch initial data
const fetchRoles = async () => {
  loadingRoles.value = true;
  try {
    const res = await api.get('/roles');
    roles.value = res.data;
  } catch (e) {
    console.error('Failed to load roles:', e);
  } finally {
    loadingRoles.value = false;
  }
};

const fetchCategoriesAndPermissions = async () => {
  try {
    const [settingsRes, permRes, empRes] = await Promise.all([
      api.get('/settings'),
      api.get('/permissions'),
      api.get('/employees'),
    ]);
    availableCategories.value = settingsRes.data.service_categories || [];
    allPermissionsGrouped.value = permRes.data.grouped || {};
    employees.value = empRes.data || [];
  } catch (e) {
    console.error('Failed loading prerequisites:', e);
  }
};

const fetchAuditLogs = async () => {
  loadingAudit.value = true;
  try {
    const res = await api.get('/audit-logs');
    auditLogs.value = res.data.data || res.data;
  } catch (e) {
    console.error('Failed to load audit logs:', e);
  } finally {
    loadingAudit.value = false;
  }
};

const fetchUserPermissions = async () => {
  if (!selectedUserId.value) return;
  loadingUser.value = true;
  try {
    const res = await api.get(`/users/${selectedUserId.value}/permissions`);
    selectedUser.value = res.data;

    // Populate overrides state
    userVisibilityScope.value = res.data.visibility?.user_override || 'inherit';

    // Populate category state
    const catOverrides = res.data.categories?.user_overrides || [];
    if (catOverrides.length === 0) {
      userCategoryModeAll.value = true;
      userSelectedCategories.value = [];
    } else {
      userCategoryModeAll.value = false;
      userSelectedCategories.value = catOverrides.filter((c: any) => c.is_allowed).map((c: any) => c.category_name);
    }

    // Populate permission overrides map
    for (const key in userPermissionOverrides) {
      delete userPermissionOverrides[key];
    }
    for (const p of res.data.permissions_matrix || []) {
      userPermissionOverrides[p.name] = p.override || 'inherit';
    }
  } catch (e) {
    console.error('Failed to load user permissions:', e);
  } finally {
    loadingUser.value = false;
  }
};

const saveUserOverrides = async () => {
  if (!selectedUserId.value) return;
  savingUser.value = true;
  try {
    // 1. Save permission overrides
    const overridesPayload = Object.entries(userPermissionOverrides).map(([perm, effect]) => ({
      permission: perm,
      effect,
    }));
    await api.put(`/users/${selectedUserId.value}/permissions`, { overrides: overridesPayload });

    // 2. Save visibility scope override
    await api.put(`/users/${selectedUserId.value}/visibility`, {
      scope: userVisibilityScope.value,
      reason: 'Administrative override via Access Control panel',
    });

    // 3. Save category restrictions
    const allowedCategories = userCategoryModeAll.value ? [] : userSelectedCategories.value;
    await api.put(`/users/${selectedUserId.value}/categories`, { allowed_categories: allowedCategories });

    // Refresh user state
    await fetchUserPermissions();
    await fetchAuditLogs();
    alert('User permission configuration saved successfully.');
  } catch (e: any) {
    alert(e?.response?.data?.message || 'Failed to save user overrides.');
  } finally {
    savingUser.value = false;
  }
};

const resetUserOverridesPrompt = () => {
  confirmResetOpen.value = true;
};

const confirmResetUserOverrides = async () => {
  if (!selectedUserId.value) return;
  confirmResetOpen.value = false;
  savingUser.value = true;
  try {
    await api.post(`/users/${selectedUserId.value}/permissions/reset`);
    await fetchUserPermissions();
    await fetchAuditLogs();
    alert('User successfully reset to clean role defaults.');
  } catch (e: any) {
    alert(e?.response?.data?.message || 'Failed to reset user overrides.');
  } finally {
    savingUser.value = false;
  }
};

// Role Modal Methods
const openCreateRoleModal = () => {
  editingRoleId.value = null;
  roleFormError.value = '';
  roleForm.name = '';
  roleForm.display_name = '';
  roleForm.description = '';
  roleForm.visibility_scope = 'assigned_only';
  roleForm.allowed_categories = [];
  roleForm.permissions = ['leads.view', 'leads.create', 'leads.edit', 'follow_ups.manage', 'whatsapp.send'];
  roleModalOpen.value = true;
};

const openEditRoleModal = (role: any) => {
  editingRoleId.value = role.id;
  roleFormError.value = '';
  roleForm.name = role.name;
  roleForm.display_name = role.display_name;
  roleForm.description = role.description || '';
  roleForm.visibility_scope = role.visibility_scope || 'assigned_only';
  roleForm.allowed_categories = role.allowed_categories ? [...role.allowed_categories] : [];
  roleForm.permissions = role.permission_names ? [...role.permission_names] : [];
  roleModalOpen.value = true;
};

const toggleRoleCategoryAll = () => {
  if (roleForm.allowed_categories.length === 0) {
    roleForm.allowed_categories = [...availableCategories.value];
  } else {
    roleForm.allowed_categories = [];
  }
};

const submitRoleForm = async () => {
  savingRole.value = true;
  roleFormError.value = '';
  try {
    if (editingRoleId.value) {
      await api.put(`/roles/${editingRoleId.value}`, {
        display_name: roleForm.display_name,
        description: roleForm.description,
        visibility_scope: roleForm.visibility_scope,
        permissions: roleForm.permissions,
        allowed_categories: roleForm.allowed_categories,
      });
    } else {
      await api.post('/roles', {
        name: roleForm.name,
        display_name: roleForm.display_name,
        description: roleForm.description,
        visibility_scope: roleForm.visibility_scope,
        permissions: roleForm.permissions,
        allowed_categories: roleForm.allowed_categories,
      });
    }
    roleModalOpen.value = false;
    await fetchRoles();
    await fetchAuditLogs();
  } catch (e: any) {
    roleFormError.value = e?.response?.data?.message || 'Failed to save role template.';
  } finally {
    savingRole.value = false;
  }
};

const deleteRolePrompt = (role: any) => {
  roleToDelete.value = role;
  confirmDeleteOpen.value = true;
};

const confirmDeleteRole = async () => {
  if (!roleToDelete.value) return;
  try {
    await api.delete(`/roles/${roleToDelete.value.id}`);
    confirmDeleteOpen.value = false;
    await fetchRoles();
    await fetchAuditLogs();
  } catch (e: any) {
    alert(e?.response?.data?.message || 'Failed to delete role.');
  }
};

onMounted(async () => {
  if (Capacitor.isNativePlatform()) {
    router.replace({ name: 'dashboard' });
    return;
  }

  await Promise.all([fetchRoles(), fetchCategoriesAndPermissions()]);
  if (route.query.user) {
    activeTab.value = 'users';
    selectedUserId.value = Number(route.query.user);
    await fetchUserPermissions();
  }
});
</script>
