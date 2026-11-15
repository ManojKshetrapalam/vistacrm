<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-vb-magenta/20 text-purple-200 border border-vb-magenta/30">
              Administration
            </span>
          </div>
          <h2 class="text-2xl font-display font-bold text-white mt-1">Solutions Team Desk</h2>
          <p class="text-xs text-purple-200/70 mt-0.5">
            Manage employee access, role authorizations, pipeline workloads, and category permissions.
          </p>
        </div>

        <button
          @click="openCreateModal"
          class="btn-primary text-xs font-semibold px-5 py-2.5 rounded-full flex items-center gap-2 shadow-md shadow-vb-magenta/30 self-start sm:self-auto"
        >
          <UserPlus class="w-4 h-4" />
          <span>Add Employee Account</span>
        </button>
      </div>

      <!-- Segmented Tabs Bar: All, Active, Deactivated -->
      <div class="flex items-center gap-2 border-b border-white/10 pb-3 text-xs font-semibold overflow-x-auto">
        <button
          type="button"
          @click="statusFilter = 'all'"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="statusFilter === 'all' ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-md shadow-vb-magenta/30 border border-white/20' : 'btn-ghost text-purple-200/70 hover:text-white'"
        >
          <span>All Team Members</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="statusFilter === 'all' ? 'bg-white/20 text-white' : 'bg-white/10 text-purple-200'">
            {{ employees.length }}
          </span>
        </button>

        <button
          type="button"
          @click="statusFilter = 'active'"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="statusFilter === 'active' ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-md shadow-vb-magenta/30 border border-white/20' : 'btn-ghost text-purple-200/70 hover:text-white'"
        >
          <span>Active Accounts</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="statusFilter === 'active' ? 'bg-white/20 text-white' : 'bg-white/10 text-purple-200'">
            {{ activeCount }}
          </span>
        </button>

        <button
          type="button"
          @click="statusFilter = 'deactivated'"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="statusFilter === 'deactivated' ? 'bg-gradient-to-r from-rose-600 to-rose-700 text-white shadow-md shadow-rose-600/30 border border-rose-500/40' : 'btn-ghost text-purple-200/70 hover:text-rose-400'"
        >
          <span>Deactivated</span>
          <span
            v-if="deactivatedCount > 0"
            class="px-2 py-0.5 rounded-full text-[10px] font-bold"
            :class="statusFilter === 'deactivated' ? 'bg-white/20 text-white' : 'bg-rose-500/20 text-rose-300'"
          >
            {{ deactivatedCount }}
          </span>
        </button>
      </div>

      <!-- Employees Table -->
      <div class="surface-solid overflow-hidden shadow-lg border border-white/10">
        <div v-if="loading" class="py-16 flex justify-center">
          <div class="w-8 h-8 border-3 border-vb-magenta/30 border-t-vb-magenta rounded-full animate-spin"></div>
        </div>

        <div v-else-if="filteredEmployees.length === 0" class="py-16 text-center">
          <Users class="w-12 h-12 text-purple-300/40 mx-auto mb-3" />
          <h3 class="font-display font-bold text-base text-white">No accounts found</h3>
          <p class="text-xs text-purple-200/70 mt-1 max-w-sm mx-auto">
            {{ statusFilter === 'deactivated' ? 'No deactivated team members. All accounts are currently active.' : 'No employee accounts matching this filter.' }}
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-white/[0.04] border-b border-white/10 text-purple-200 font-semibold uppercase tracking-wider text-[11px]">
              <tr>
                <th class="py-3 px-4">Employee</th>
                <th class="py-3 px-4">Role &amp; Visibility</th>
                <th class="py-3 px-4">Account Status</th>
                <th class="py-3 px-4 text-center">Assigned Leads</th>
                <th class="py-3 px-4 text-center">Due Today</th>
                <th class="py-3 px-4 text-center">Converted</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr
                v-for="emp in filteredEmployees"
                :key="emp.id"
                class="hover:bg-white/[0.04] transition-colors"
                :class="!emp.is_active ? 'opacity-70 bg-white/[0.02]' : ''"
              >
                <!-- Employee Name & Email -->
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <div
                      class="w-8 h-8 rounded-full font-bold flex items-center justify-center text-xs border border-white/20"
                      :class="emp.is_active ? 'bg-gradient-to-br from-vb-magenta to-vb-plum text-white shadow-xs' : 'bg-white/10 text-slate-300'"
                    >
                      {{ emp.name[0] }}
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <span class="font-bold text-white text-sm block">{{ emp.name }}</span>
                        <span v-if="emp.id === authStore.user?.id" class="px-1.5 py-0.2 rounded-md bg-vb-magenta/20 text-purple-200 border border-vb-magenta/30 text-[10px] font-bold">
                          You
                        </span>
                      </div>
                      <div class="text-[11px] text-purple-200/60 flex items-center gap-2 mt-0.5">
                        <span>{{ emp.email }}</span>
                        <span v-if="emp.phone">· {{ emp.phone }}</span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Role & Permissions -->
                <td class="py-3 px-4">
                  <div class="space-y-1">
                    <span
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider inline-block border"
                      :class="emp.role === 'admin' ? 'bg-vb-magenta/20 text-purple-200 border-vb-magenta/40' : (emp.role === 'team_lead' ? 'bg-teal-500/20 text-teal-300 border-teal-500/40' : 'bg-white/10 text-purple-200 border-white/15')"
                    >
                      {{ emp.role === 'admin' ? 'Administrator' : (emp.role === 'team_lead' ? 'Team Lead' : 'Employee') }}
                    </span>
                    <div v-if="emp.role === 'team_lead'" class="text-[10px] space-y-0.5 max-w-[220px]">
                      <div class="flex items-center gap-1">
                        <span class="font-bold text-purple-300">Supervises:</span>
                        <span class="text-white font-medium truncate" :title="getTeamMemberNames(emp)">
                          {{ getTeamMemberNames(emp) || 'None' }}
                        </span>
                      </div>
                      <div class="flex items-center gap-1">
                        <span class="font-bold text-purple-300">Sources:</span>
                        <span v-if="emp.allowed_sources && emp.allowed_sources.length > 0" class="text-white font-medium truncate" :title="emp.allowed_sources.join(', ')">
                          {{ emp.allowed_sources.slice(0, 2).join(', ') }}{{ emp.allowed_sources.length > 2 ? ` (+${emp.allowed_sources.length - 2})` : '' }}
                        </span>
                        <span v-else class="text-purple-200/50 italic">All Sources</span>
                      </div>
                      <div class="flex items-center gap-1">
                        <span class="font-bold text-purple-300">Verticals:</span>
                        <span v-if="emp.allowed_services && emp.allowed_services.length > 0" class="text-white font-medium truncate" :title="emp.allowed_services.join(', ')">
                          {{ emp.allowed_services.slice(0, 2).join(', ') }}{{ emp.allowed_services.length > 2 ? ` (+${emp.allowed_services.length - 2})` : '' }}
                        </span>
                        <span v-else class="text-emerald-300 font-semibold">All Verticals</span>
                      </div>
                    </div>
                    <div v-else-if="emp.role === 'employee'" class="text-[10px] space-y-0.5 max-w-[200px]">
                      <div class="flex items-center gap-1">
                        <span class="font-bold text-purple-300">Sources:</span>
                        <span v-if="emp.allowed_sources && emp.allowed_sources.length > 0" class="text-white font-medium truncate" :title="emp.allowed_sources.join(', ')">
                          {{ emp.allowed_sources.slice(0, 2).join(', ') }}{{ emp.allowed_sources.length > 2 ? ` (+${emp.allowed_sources.length - 2})` : '' }}
                        </span>
                        <span v-else class="text-purple-200/50 italic">All Sources</span>
                      </div>
                      <div class="flex items-center gap-1">
                        <span class="font-bold text-purple-300">Verticals:</span>
                        <span v-if="emp.allowed_services && emp.allowed_services.length > 0" class="text-white font-medium truncate" :title="emp.allowed_services.join(', ')">
                          {{ emp.allowed_services.slice(0, 2).join(', ') }}{{ emp.allowed_services.length > 2 ? ` (+${emp.allowed_services.length - 2})` : '' }}
                        </span>
                        <span v-else class="text-emerald-300 font-semibold">All Verticals</span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Account Status -->
                <td class="py-3 px-4">
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="emp.is_active ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-white/10 text-slate-300 border border-white/20'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="emp.is_active ? 'bg-emerald-400' : 'bg-slate-400'"></span>
                    {{ emp.is_active ? 'Active' : 'Deactivated' }}
                  </span>
                </td>

                <!-- Stats -->
                <td class="py-3 px-4 text-center font-bold text-white">{{ emp.stats?.assigned_leads || 0 }}</td>
                <td class="py-3 px-4 text-center font-semibold text-amber-300">{{ emp.stats?.due_today || 0 }}</td>
                <td class="py-3 px-4 text-center font-bold text-emerald-300">{{ emp.stats?.converted_leads || 0 }}</td>

                <!-- Actions Menu -->
                <td class="py-3 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      @click="openEditModal(emp)"
                      class="btn-ghost px-2.5 py-1 rounded-lg text-white font-semibold text-[11px]"
                    >
                      Edit
                    </button>

                    <!-- Dropdown Actions Menu -->
                    <ActionMenu
                      :items="getEmployeeActions(emp)"
                      title="Employee Account Actions"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create Employee Modal -->
    <div v-if="createModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-md flex items-center justify-center p-4">
      <div class="glass-elevated max-w-xl w-full max-h-[90vh] flex flex-col overflow-hidden relative border border-white/15 shadow-2xl animate-in fade-in zoom-in-95 duration-200">
        <div class="h-1.5 w-full spectrum absolute top-0 left-0"></div>
        <div class="p-5 pt-6 border-b border-white/10 flex items-center justify-between bg-white/[0.04] backdrop-blur-md flex-shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-vb-magenta to-vb-plum text-white flex items-center justify-center shadow-md border border-white/20">
              <UserPlus class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-display font-bold text-base text-white">Add Team Member</h3>
              <p class="text-xs text-purple-200/70">Provision credentials and lead visibility rules for a new representative.</p>
            </div>
          </div>
          <button @click="createModalOpen = false" class="p-1 rounded-lg text-purple-300 hover:text-white hover:bg-white/10 transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="handleCreateEmployee" class="p-6 space-y-4 overflow-y-auto flex-1">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">Full Name *</label>
            <input v-model="createForm.name" type="text" required class="field text-sm" placeholder="e.g. Ramesh Hegde" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">Email Address *</label>
            <input v-model="createForm.email" type="email" required class="field text-sm" placeholder="employee@vistaboundintegrated.com" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">Mobile Phone (WhatsApp Notifications)</label>
            <input v-model="createForm.phone" type="tel" class="field text-sm" placeholder="e.g. +91 98450 12345" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">Initial Password *</label>
            <div class="relative flex items-center">
              <input v-model="createForm.password" :type="showCreatePassword ? 'text' : 'password'" required minlength="8" class="field text-sm pr-10" placeholder="Minimum 8 characters" />
              <button type="button" @click="showCreatePassword = !showCreatePassword" class="absolute right-3 text-purple-300 hover:text-white p-1" :title="showCreatePassword ? 'Hide password' : 'Show password'" tabindex="-1">
                <EyeOff v-if="showCreatePassword" class="w-4 h-4 text-vb-magenta" />
                <Eye v-else class="w-4 h-4 text-purple-300 hover:text-white" />
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">Role *</label>
            <select v-model="createForm.role" class="field text-sm">
              <option value="employee">Solutions Representative (Employee)</option>
              <option value="team_lead">Team Lead (Supervised Delegation)</option>
              <option value="admin">Administrator (Unrestricted Full Access)</option>
            </select>
          </div>

          <!-- Supervised Team Members (Mandatory for Team Lead) -->
          <div v-if="createForm.role === 'team_lead'" class="p-4 bg-white/[0.04] rounded-xl border border-white/10 space-y-2.5">
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200">
                  Supervised Team Members <span class="text-rose-400 font-bold">* (Mandatory)</span>
                </label>
                <p class="text-[11px] text-purple-200/60 mt-0.5">Select whose leads this Team Lead can view, monitor, and manage.</p>
              </div>
              <button
                type="button"
                @click="toggleAllTeamMembers(createForm)"
                class="text-[11px] font-semibold text-vb-cyan hover:underline"
              >
                {{ createForm.team_members.length === candidateMembers().length ? 'Clear All' : 'Select All' }}
              </button>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
              <label
                v-for="member in candidateMembers()"
                :key="member.id"
                class="flex items-center gap-2 p-2 rounded-lg border text-xs cursor-pointer transition-all select-none"
                :class="createForm.team_members.includes(member.id) ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white border-vb-magenta font-semibold shadow-xs' : 'bg-white/[0.06] text-purple-100 border-white/15 hover:border-white/30'"
              >
                <input
                  type="checkbox"
                  :value="member.id"
                  v-model="createForm.team_members"
                  class="hidden"
                />
                <span class="w-3.5 h-3.5 rounded border flex items-center justify-center text-[10px]"
                  :class="createForm.team_members.includes(member.id) ? 'border-white bg-white/20' : 'border-white/30 bg-white/10'"
                >
                  <span v-if="createForm.team_members.includes(member.id)">✓</span>
                </span>
                <span class="truncate">{{ member.name }}</span>
              </label>
            </div>
            <p v-if="createForm.team_members.length === 0" class="text-[11px] text-amber-300 font-medium pt-1">
              ⚠️ Please select at least one team member to supervise.
            </p>
          </div>

          <!-- Allowed Assignees (To whom can this Team Lead assign leads?) -->
          <div v-if="createForm.role === 'team_lead'" class="p-4 bg-white/[0.04] rounded-xl border border-white/10 space-y-2.5">
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200">
                  Lead Assignment Delegation <span class="text-purple-200/50 font-normal">(Assignable Staff)</span>
                </label>
                <p class="text-[11px] text-purple-200/60 mt-0.5">Select who this Team Lead can assign leads to (defaults to supervised team + self).</p>
              </div>
              <button
                type="button"
                @click="createForm.assignable_users = [...createForm.team_members]"
                class="text-[11px] font-semibold text-vb-cyan hover:underline"
              >
                Mirror Team Members
              </button>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
              <label
                v-for="member in candidateMembers()"
                :key="member.id"
                class="flex items-center gap-2 p-2 rounded-lg border text-xs cursor-pointer transition-all select-none"
                :class="createForm.assignable_users.includes(member.id) ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white border-vb-magenta font-semibold shadow-xs' : 'bg-white/[0.06] text-purple-100 border-white/15 hover:border-white/30'"
              >
                <input
                  type="checkbox"
                  :value="member.id"
                  v-model="createForm.assignable_users"
                  class="hidden"
                />
                <span class="w-3.5 h-3.5 rounded border flex items-center justify-center text-[10px]"
                  :class="createForm.assignable_users.includes(member.id) ? 'border-white bg-white/20' : 'border-white/30 bg-white/10'"
                >
                  <span v-if="createForm.assignable_users.includes(member.id)">✓</span>
                </span>
                <span class="truncate">{{ member.name }}</span>
              </label>
            </div>
            <p class="text-[10px] text-purple-200/50 italic">Note: Team Leads can always assign leads to themselves.</p>
          </div>

          <!-- Allowed Lead Sources (Mandatory for Employees & Team Leads) -->
          <div v-if="['employee', 'team_lead'].includes(createForm.role)" class="p-4 bg-white/[0.04] rounded-xl border border-white/10 space-y-2.5">
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200">
                  Allowed Lead Sources <span class="text-rose-400 font-bold">* (Mandatory)</span>
                </label>
                <p class="text-[11px] text-purple-200/60 mt-0.5">Select all inbound sources this employee is permitted to view and manage.</p>
              </div>
              <button
                type="button"
                @click="toggleAllSources(createForm)"
                class="text-[11px] font-semibold text-vb-cyan hover:underline"
              >
                {{ createForm.allowed_sources.length === availableSources.length ? 'Clear All' : 'Select All' }}
              </button>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
              <label
                v-for="src in availableSources"
                :key="src"
                class="flex items-center gap-2 p-2 rounded-lg border text-xs cursor-pointer transition-all select-none"
                :class="createForm.allowed_sources.includes(src) ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white border-vb-magenta font-semibold shadow-xs' : 'bg-white/[0.06] text-purple-100 border-white/15 hover:border-white/30'"
              >
                <input
                  type="checkbox"
                  :value="src"
                  v-model="createForm.allowed_sources"
                  class="hidden"
                />
                <span class="w-3.5 h-3.5 rounded border flex items-center justify-center text-[10px]"
                  :class="createForm.allowed_sources.includes(src) ? 'border-white bg-white/20' : 'border-white/30 bg-white/10'"
                >
                  <span v-if="createForm.allowed_sources.includes(src)">✓</span>
                </span>
                <span class="truncate">{{ src }}</span>
              </label>
            </div>
            <p v-if="createForm.allowed_sources.length === 0" class="text-[11px] text-amber-300 font-medium pt-1">
              ⚠️ Please select at least one lead source.
            </p>
          </div>

          <!-- Allowed Business Verticals (Optional for Employees & Team Leads) -->
          <div v-if="['employee', 'team_lead'].includes(createForm.role)" class="p-4 bg-white/[0.04] rounded-xl border border-white/10 space-y-2.5">
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200">
                  Allowed Business Verticals <span class="text-purple-200/50 font-normal">(Optional)</span>
                </label>
                <p class="text-[11px] text-purple-200/60 mt-0.5">Leave unselected to grant access to all business verticals under the allowed sources.</p>
              </div>
              <button
                type="button"
                @click="toggleAllServices(createForm)"
                class="text-[11px] font-semibold text-vb-cyan hover:underline"
              >
                {{ createForm.allowed_services.length === availableServices.length ? 'Clear All' : 'Select All' }}
              </button>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
              <label
                v-for="srv in availableServices"
                :key="srv"
                class="flex items-center gap-2 p-2 rounded-lg border text-xs cursor-pointer transition-all select-none"
                :class="createForm.allowed_services.includes(srv) ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white border-vb-magenta font-semibold shadow-xs' : 'bg-white/[0.06] text-purple-100 border-white/15 hover:border-white/30'"
              >
                <input
                  type="checkbox"
                  :value="srv"
                  v-model="createForm.allowed_services"
                  class="hidden"
                />
                <span class="w-3.5 h-3.5 rounded border flex items-center justify-center text-[10px]"
                  :class="createForm.allowed_services.includes(srv) ? 'border-white bg-white/20' : 'border-white/30 bg-white/10'"
                >
                  <span v-if="createForm.allowed_services.includes(srv)">✓</span>
                </span>
                <span class="truncate">{{ srv }}</span>
              </label>
            </div>
          </div>

          <p v-if="modalError" class="text-xs text-rose-300 bg-rose-500/20 p-2.5 rounded-lg border border-rose-500/40">
            {{ modalError }}
          </p>

          <div class="pt-4 border-t border-white/10 flex items-center justify-end gap-2.5 flex-shrink-0">
            <button type="button" @click="createModalOpen = false" class="btn-ghost px-4 py-2 text-xs rounded-full font-semibold">
              Cancel
            </button>
            <button type="submit" :disabled="saving" class="btn-primary text-xs font-semibold px-5 py-2.5 rounded-full flex items-center gap-1.5 shadow-sm">
              <span>{{ saving ? 'Creating...' : 'Create Account' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Employee Modal -->
    <div v-if="editModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-md flex items-center justify-center p-4">
      <div class="glass-elevated max-w-xl w-full max-h-[90vh] flex flex-col overflow-hidden relative border border-white/15 shadow-2xl animate-in fade-in zoom-in-95 duration-200">
        <div class="h-1.5 w-full spectrum absolute top-0 left-0"></div>
        <div class="p-5 pt-6 border-b border-white/10 flex items-center justify-between bg-white/[0.04] backdrop-blur-md flex-shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-vb-magenta to-vb-plum text-white flex items-center justify-center shadow-md border border-white/20">
              <UserCog class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-display font-bold text-base text-white">Edit Team Member</h3>
              <p class="text-xs text-purple-200/70">{{ activeEditUser?.email }}</p>
            </div>
          </div>
          <button @click="editModalOpen = false" class="p-1 rounded-lg text-purple-300 hover:text-white hover:bg-white/10 transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="handleEditEmployee" class="p-6 space-y-4 overflow-y-auto flex-1">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">Full Name *</label>
            <input v-model="editForm.name" type="text" required class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">Email Address *</label>
            <input v-model="editForm.email" type="email" required class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">Mobile Phone</label>
            <input v-model="editForm.phone" type="tel" class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">New Password (leave blank to keep current)</label>
            <div class="relative flex items-center">
              <input v-model="editForm.password" :type="showEditPassword ? 'text' : 'password'" minlength="8" class="field text-sm pr-10" placeholder="••••••••" />
              <button type="button" @click="showEditPassword = !showEditPassword" class="absolute right-3 text-purple-300 hover:text-white p-1" :title="showEditPassword ? 'Hide password' : 'Show password'" tabindex="-1">
                <EyeOff v-if="showEditPassword" class="w-4 h-4 text-vb-magenta" />
                <Eye v-else class="w-4 h-4 text-purple-300 hover:text-white" />
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">Role *</label>
            <select v-model="editForm.role" class="field text-sm">
              <option value="employee">Solutions Representative (Employee)</option>
              <option value="team_lead">Team Lead (Supervised Delegation)</option>
              <option value="admin">Administrator (Unrestricted Full Access)</option>
            </select>
          </div>

          <!-- Supervised Team Members (Mandatory for Team Lead) -->
          <div v-if="editForm.role === 'team_lead'" class="p-4 bg-white/[0.04] rounded-xl border border-white/10 space-y-2.5">
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200">
                  Supervised Team Members <span class="text-rose-400 font-bold">* (Mandatory)</span>
                </label>
                <p class="text-[11px] text-purple-200/60 mt-0.5">Select whose leads this Team Lead can view, monitor, and manage.</p>
              </div>
              <button
                type="button"
                @click="toggleAllTeamMembers(editForm, activeEditUser?.id)"
                class="text-[11px] font-semibold text-vb-cyan hover:underline"
              >
                {{ editForm.team_members.length === candidateMembers(activeEditUser?.id).length ? 'Clear All' : 'Select All' }}
              </button>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
              <label
                v-for="member in candidateMembers(activeEditUser?.id)"
                :key="member.id"
                class="flex items-center gap-2 p-2 rounded-lg border text-xs cursor-pointer transition-all select-none"
                :class="editForm.team_members.includes(member.id) ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white border-vb-magenta font-semibold shadow-xs' : 'bg-white/[0.06] text-purple-100 border-white/15 hover:border-white/30'"
              >
                <input
                  type="checkbox"
                  :value="member.id"
                  v-model="editForm.team_members"
                  class="hidden"
                />
                <span class="w-3.5 h-3.5 rounded border flex items-center justify-center text-[10px]"
                  :class="editForm.team_members.includes(member.id) ? 'border-white bg-white/20' : 'border-white/30 bg-white/10'"
                >
                  <span v-if="editForm.team_members.includes(member.id)">✓</span>
                </span>
                <span class="truncate">{{ member.name }}</span>
              </label>
            </div>
            <p v-if="editForm.team_members.length === 0" class="text-[11px] text-amber-300 font-medium pt-1">
              ⚠️ Please select at least one team member to supervise.
            </p>
          </div>

          <!-- Allowed Assignees (To whom can this Team Lead assign leads?) -->
          <div v-if="editForm.role === 'team_lead'" class="p-4 bg-white/[0.04] rounded-xl border border-white/10 space-y-2.5">
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200">
                  Lead Assignment Delegation <span class="text-purple-200/50 font-normal">(Assignable Staff)</span>
                </label>
                <p class="text-[11px] text-purple-200/60 mt-0.5">Select who this Team Lead can assign leads to (defaults to supervised team + self).</p>
              </div>
              <button
                type="button"
                @click="editForm.assignable_users = [...editForm.team_members]"
                class="text-[11px] font-semibold text-vb-cyan hover:underline"
              >
                Mirror Team Members
              </button>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
              <label
                v-for="member in candidateMembers(activeEditUser?.id)"
                :key="member.id"
                class="flex items-center gap-2 p-2 rounded-lg border text-xs cursor-pointer transition-all select-none"
                :class="editForm.assignable_users.includes(member.id) ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white border-vb-magenta font-semibold shadow-xs' : 'bg-white/[0.06] text-purple-100 border-white/15 hover:border-white/30'"
              >
                <input
                  type="checkbox"
                  :value="member.id"
                  v-model="editForm.assignable_users"
                  class="hidden"
                />
                <span class="w-3.5 h-3.5 rounded border flex items-center justify-center text-[10px]"
                  :class="editForm.assignable_users.includes(member.id) ? 'border-white bg-white/20' : 'border-white/30 bg-white/10'"
                >
                  <span v-if="editForm.assignable_users.includes(member.id)">✓</span>
                </span>
                <span class="truncate">{{ member.name }}</span>
              </label>
            </div>
            <p class="text-[10px] text-purple-200/50 italic">Note: Team Leads can always assign leads to themselves.</p>
          </div>

          <!-- Allowed Lead Sources (Mandatory for Employees & Team Leads) -->
          <div v-if="['employee', 'team_lead'].includes(editForm.role)" class="p-4 bg-white/[0.04] rounded-xl border border-white/10 space-y-2.5">
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200">
                  Allowed Lead Sources <span class="text-rose-400 font-bold">* (Mandatory)</span>
                </label>
                <p class="text-[11px] text-purple-200/60 mt-0.5">Select all inbound sources this employee is permitted to view and manage.</p>
              </div>
              <button
                type="button"
                @click="toggleAllSources(editForm)"
                class="text-[11px] font-semibold text-vb-cyan hover:underline"
              >
                {{ editForm.allowed_sources.length === availableSources.length ? 'Clear All' : 'Select All' }}
              </button>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
              <label
                v-for="src in availableSources"
                :key="src"
                class="flex items-center gap-2 p-2 rounded-lg border text-xs cursor-pointer transition-all select-none"
                :class="editForm.allowed_sources.includes(src) ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white border-vb-magenta font-semibold shadow-xs' : 'bg-white/[0.06] text-purple-100 border-white/15 hover:border-white/30'"
              >
                <input
                  type="checkbox"
                  :value="src"
                  v-model="editForm.allowed_sources"
                  class="hidden"
                />
                <span class="w-3.5 h-3.5 rounded border flex items-center justify-center text-[10px]"
                  :class="editForm.allowed_sources.includes(src) ? 'border-white bg-white/20' : 'border-white/30 bg-white/10'"
                >
                  <span v-if="editForm.allowed_sources.includes(src)">✓</span>
                </span>
                <span class="truncate">{{ src }}</span>
              </label>
            </div>
            <p v-if="editForm.allowed_sources.length === 0" class="text-[11px] text-amber-300 font-medium pt-1">
              ⚠️ Please select at least one lead source.
            </p>
          </div>

          <!-- Allowed Business Verticals (Optional for Employees & Team Leads) -->
          <div v-if="['employee', 'team_lead'].includes(editForm.role)" class="p-4 bg-white/[0.04] rounded-xl border border-white/10 space-y-2.5">
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200">
                  Allowed Business Verticals <span class="text-purple-200/50 font-normal">(Optional)</span>
                </label>
                <p class="text-[11px] text-purple-200/60 mt-0.5">Leave unselected to grant access to all business verticals under the allowed sources.</p>
              </div>
              <button
                type="button"
                @click="toggleAllServices(editForm)"
                class="text-[11px] font-semibold text-vb-cyan hover:underline"
              >
                {{ editForm.allowed_services.length === availableServices.length ? 'Clear All' : 'Select All' }}
              </button>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
              <label
                v-for="srv in availableServices"
                :key="srv"
                class="flex items-center gap-2 p-2 rounded-lg border text-xs cursor-pointer transition-all select-none"
                :class="editForm.allowed_services.includes(srv) ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white border-vb-magenta font-semibold shadow-xs' : 'bg-white/[0.06] text-purple-100 border-white/15 hover:border-white/30'"
              >
                <input
                  type="checkbox"
                  :value="srv"
                  v-model="editForm.allowed_services"
                  class="hidden"
                />
                <span class="w-3.5 h-3.5 rounded border flex items-center justify-center text-[10px]"
                  :class="editForm.allowed_services.includes(srv) ? 'border-white bg-white/20' : 'border-white/30 bg-white/10'"
                >
                  <span v-if="editForm.allowed_services.includes(srv)">✓</span>
                </span>
                <span class="truncate">{{ srv }}</span>
              </label>
            </div>
          </div>

          <p v-if="modalError" class="text-xs text-rose-300 bg-rose-500/20 p-2.5 rounded-lg border border-rose-500/40">
            {{ modalError }}
          </p>

          <div class="pt-4 border-t border-white/10 flex items-center justify-end gap-2.5 flex-shrink-0">
            <button type="button" @click="editModalOpen = false" class="btn-ghost px-4 py-2 text-xs rounded-full font-semibold">
              Cancel
            </button>
            <button type="submit" :disabled="saving" class="btn-primary text-xs font-semibold px-5 py-2.5 rounded-full flex items-center gap-1.5 shadow-sm">
              <span>{{ saving ? 'Saving...' : 'Save Changes' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Deactivate Confirmation Modal -->
    <ConfirmModal
      :is-open="deactivateModalOpen"
      title="Deactivate Employee Account"
      :subtitle="selectedEmp?.name"
      :message="`Are you sure you want to deactivate the account for ${selectedEmp?.name} (${selectedEmp?.email})?`"
      consequences="This immediately revokes all active API sessions and halts background reminder delivery. Existing leads remain safely assigned to this employee until reassigned."
      confirm-text="Deactivate Account"
      variant="danger"
      :is-submitting="statusSubmitting"
      :error="statusError"
      @confirm="executeDeactivate"
      @cancel="deactivateModalOpen = false"
    />

    <!-- Reactivate Confirmation Modal -->
    <ConfirmModal
      :is-open="reactivateModalOpen"
      title="Reactivate Employee Account"
      :subtitle="selectedEmp?.name"
      :message="`Restore active status for ${selectedEmp?.name} (${selectedEmp?.email})?`"
      consequences="The employee will regain access to log in and manage their designated pipeline leads and scheduled follow-ups."
      confirm-text="Reactivate Account"
      variant="warning"
      :is-submitting="statusSubmitting"
      :error="statusError"
      @confirm="executeReactivate"
      @cancel="reactivateModalOpen = false"
    />

    <!-- Assigned Leads Workload Modal -->
    <div v-if="assignedLeadsModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-md flex items-center justify-center p-4">
      <div class="glass-elevated max-w-lg w-full overflow-hidden border border-white/15 shadow-2xl animate-in fade-in zoom-in-95 duration-200">
        <div class="p-5 border-b border-white/10 flex items-center justify-between bg-white/[0.04] backdrop-blur-md">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-vb-magenta to-vb-plum text-white flex items-center justify-center shadow-md border border-white/20">
              <Briefcase class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-display font-bold text-base text-white">Assigned Leads Workload</h3>
              <p class="text-xs text-purple-200/70">{{ selectedEmp?.name }} · {{ assignedLeadsList.length }} total leads</p>
            </div>
          </div>
          <button @click="assignedLeadsModalOpen = false" class="p-1 rounded-lg text-purple-300 hover:text-white hover:bg-white/10">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
          <!-- Bulk Reassignment Panel -->
          <div v-if="assignedLeadsList.length > 0" class="p-3.5 bg-white/[0.04] rounded-xl border border-white/10 space-y-2">
            <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200">
              Bulk Reassign All Leads to Another Member
            </label>
            <div class="flex gap-2">
              <select v-model="targetAssigneeId" class="field text-xs flex-1">
                <option value="">Select target team member...</option>
                <option
                  v-for="target in activeEmployeesList.filter((e) => e.id !== selectedEmp?.id)"
                  :key="target.id"
                  :value="target.id"
                >
                  {{ target.name }} ({{ target.email }})
                </option>
              </select>
              <button
                type="button"
                :disabled="!targetAssigneeId || reassigningLeads"
                @click="executeReassignLeads"
                class="btn-primary text-xs font-semibold px-4 py-2 rounded-xl"
              >
                {{ reassigningLeads ? 'Reassigning...' : 'Reassign All' }}
              </button>
            </div>
          </div>

          <!-- Leads List Table -->
          <div v-if="assignedLeadsList.length === 0" class="text-center py-8 text-purple-200/60 text-xs">
            No leads currently assigned to this representative.
          </div>
          <div v-else class="divide-y divide-white/5">
            <div v-for="ld in assignedLeadsList" :key="ld.id" class="py-2.5 flex items-center justify-between text-xs">
              <div>
                <span class="font-mono font-bold text-vb-magenta">{{ ld.reference_id }}</span>
                <span class="font-semibold text-white ml-2">{{ ld.name }}</span>
                <span v-if="ld.company_name" class="text-purple-200/60 text-[11px] block">{{ ld.company_name }}</span>
              </div>
              <div class="flex items-center gap-2">
                <StatusBadge :status="ld.status" />
                <PriorityBadge :priority="ld.priority" />
              </div>
            </div>
          </div>
        </div>

        <div class="p-4 border-t border-white/10 flex justify-end bg-white/[0.02]">
          <button @click="assignedLeadsModalOpen = false" class="btn-ghost px-4 py-2 text-xs font-semibold rounded-full">
            Close
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import StatusBadge from '../components/StatusBadge.vue';
import PriorityBadge from '../components/PriorityBadge.vue';
import ActionMenu, { ActionMenuItem } from '../components/ActionMenu.vue';
import ConfirmModal from '../components/ConfirmModal.vue';
import {
  Users,
  UserPlus,
  UserCheck,
  UserX,
  UserCog,
  Briefcase,
  Edit,
  X,
  Eye,
  EyeOff,
} from 'lucide-vue-next';

const authStore = useAuthStore();

const showCreatePassword = ref(false);
const showEditPassword = ref(false);

const loading = ref(true);
const saving = ref(false);
const employees = ref<any[]>([]);
const statusFilter = ref<'all' | 'active' | 'deactivated'>('active');

const availableSources = ref<string[]>([]);
const availableServices = ref<string[]>([]);

const createModalOpen = ref(false);
const editModalOpen = ref(false);
const deactivateModalOpen = ref(false);
const reactivateModalOpen = ref(false);
const assignedLeadsModalOpen = ref(false);

const selectedEmp = ref<any>(null);
const assignedLeadsList = ref<any[]>([]);
const targetAssigneeId = ref<any>('');
const reassigningLeads = ref(false);

const statusSubmitting = ref(false);
const statusError = ref('');
const modalError = ref('');

const activeEditUser = ref<any>(null);

const createForm = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  role: 'employee',
  allowed_sources: [] as string[],
  allowed_services: [] as string[],
  team_members: [] as number[],
  assignable_users: [] as number[],
});

const editForm = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  role: 'employee',
  allowed_sources: [] as string[],
  allowed_services: [] as string[],
  team_members: [] as number[],
  assignable_users: [] as number[],
});

const candidateMembers = (excludeId?: number) => {
  return employees.value.filter((e) => e.is_active && e.role !== 'admin' && (!excludeId || e.id !== excludeId));
};

const getTeamMemberNames = (emp: any): string => {
  if (!emp.team_members || !Array.isArray(emp.team_members)) return '';
  return emp.team_members
    .map((id: number) => employees.value.find((e) => e.id === id)?.name || `#${id}`)
    .join(', ');
};

const toggleAllTeamMembers = (form: any, excludeId?: number) => {
  const candidates = candidateMembers(excludeId).map((c) => c.id);
  if (form.team_members.length === candidates.length) {
    form.team_members = [];
  } else {
    form.team_members = [...candidates];
  }
};

const activeCount = computed(() => employees.value.filter((e) => e.is_active).length);
const deactivatedCount = computed(() => employees.value.filter((e) => !e.is_active).length);
const activeAdminsCount = computed(() => employees.value.filter((e) => e.role === 'admin' && e.is_active).length);

const activeEmployeesList = computed(() => employees.value.filter((e) => e.is_active));

const filteredEmployees = computed(() => {
  if (statusFilter.value === 'active') {
    return employees.value.filter((e) => e.is_active);
  }
  if (statusFilter.value === 'deactivated') {
    return employees.value.filter((e) => !e.is_active);
  }
  return employees.value;
});

const fetchSettings = async () => {
  try {
    const res = await api.get('/settings');
    availableSources.value = res.data.lead_sources || [];
    availableServices.value = res.data.service_categories || [];
  } catch (err) {
    console.error('Failed to fetch settings for permissions', err);
  }
};

const fetchEmployees = async () => {
  loading.value = true;
  try {
    const res = await api.get('/employees');
    employees.value = res.data;
  } catch (err) {
    console.error('Failed to fetch employees', err);
  } finally {
    loading.value = false;
  }
};

const toggleAllSources = (form: { allowed_sources: string[] }) => {
  if (form.allowed_sources.length === availableSources.value.length) {
    form.allowed_sources = [];
  } else {
    form.allowed_sources = [...availableSources.value];
  }
};

const toggleAllServices = (form: { allowed_services: string[] }) => {
  if (form.allowed_services.length === availableServices.value.length) {
    form.allowed_services = [];
  } else {
    form.allowed_services = [...availableServices.value];
  }
};

const openCreateModal = () => {
  createForm.name = '';
  createForm.email = '';
  createForm.phone = '';
  createForm.password = '';
  createForm.role = 'employee';
  showCreatePassword.value = false;
  // Pre-select all available sources by default for ease of creation
  createForm.allowed_sources = [...availableSources.value];
  createForm.allowed_services = [];
  createForm.team_members = [];
  createForm.assignable_users = [];
  modalError.value = '';
  createModalOpen.value = true;
};

const handleCreateEmployee = async () => {
  if (['employee', 'team_lead'].includes(createForm.role) && createForm.allowed_sources.length === 0) {
    modalError.value = 'Allowed Lead Sources is mandatory. Please select at least one source.';
    return;
  }

  if (createForm.role === 'team_lead' && createForm.team_members.length === 0) {
    modalError.value = 'Please select at least one team member under this Team Lead.';
    return;
  }

  saving.value = true;
  modalError.value = '';

  try {
    const payload: any = {
      name: createForm.name,
      email: createForm.email,
      phone: createForm.phone || null,
      password: createForm.password,
      role: createForm.role,
    };

    if (['employee', 'team_lead'].includes(createForm.role)) {
      payload.allowed_sources = createForm.allowed_sources;
      payload.allowed_services = createForm.allowed_services;
    }

    if (createForm.role === 'team_lead') {
      payload.team_members = createForm.team_members;
      payload.assignable_users = createForm.assignable_users.length > 0 ? createForm.assignable_users : createForm.team_members;
    }

    await api.post('/employees', payload);
    createModalOpen.value = false;
    await fetchEmployees();
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Failed to create account.';
  } finally {
    saving.value = false;
  }
};

const openEditModal = (emp: any) => {
  activeEditUser.value = emp;
  editForm.name = emp.name;
  editForm.email = emp.email;
  editForm.phone = emp.phone || '';
  editForm.password = '';
  showEditPassword.value = false;
  editForm.role = emp.role;
  editForm.allowed_sources = Array.isArray(emp.allowed_sources) && emp.allowed_sources.length > 0
    ? [...emp.allowed_sources]
    : (['employee', 'team_lead'].includes(emp.role) ? [...availableSources.value] : []);
  editForm.allowed_services = Array.isArray(emp.allowed_services) ? [...emp.allowed_services] : [];
  editForm.team_members = Array.isArray(emp.team_members) ? [...emp.team_members] : [];
  editForm.assignable_users = Array.isArray(emp.assignable_users) ? [...emp.assignable_users] : [];
  modalError.value = '';
  editModalOpen.value = true;
};

const handleEditEmployee = async () => {
  if (!activeEditUser.value) return;

  if (['employee', 'team_lead'].includes(editForm.role) && editForm.allowed_sources.length === 0) {
    modalError.value = 'Allowed Lead Sources is mandatory. Please select at least one source.';
    return;
  }

  if (editForm.role === 'team_lead' && editForm.team_members.length === 0) {
    modalError.value = 'Please select at least one team member under this Team Lead.';
    return;
  }

  saving.value = true;
  modalError.value = '';

  try {
    const payload: any = {
      name: editForm.name,
      email: editForm.email,
      phone: editForm.phone || null,
      role: editForm.role,
    };
    if (editForm.password) {
      payload.password = editForm.password;
    }
    if (['employee', 'team_lead'].includes(editForm.role)) {
      payload.allowed_sources = editForm.allowed_sources;
      payload.allowed_services = editForm.allowed_services;
    }
    if (editForm.role === 'team_lead') {
      payload.team_members = editForm.team_members;
      payload.assignable_users = editForm.assignable_users.length > 0 ? editForm.assignable_users : editForm.team_members;
    }

    await api.put(`/employees/${activeEditUser.value.id}`, payload);
    editModalOpen.value = false;
    await fetchEmployees();
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Failed to update user account.';
  } finally {
    saving.value = false;
  }
};

const openDeactivateModal = (emp: any) => {
  selectedEmp.value = emp;
  statusError.value = '';
  deactivateModalOpen.value = true;
};

const executeDeactivate = async () => {
  if (!selectedEmp.value) return;
  statusSubmitting.value = true;
  statusError.value = '';

  try {
    await api.post(`/employees/${selectedEmp.value.id}/toggle-status`);
    deactivateModalOpen.value = false;
    await fetchEmployees();
  } catch (err: any) {
    statusError.value = err.response?.data?.message || 'Failed to deactivate employee.';
  } finally {
    statusSubmitting.value = false;
  }
};

const openReactivateModal = (emp: any) => {
  selectedEmp.value = emp;
  statusError.value = '';
  reactivateModalOpen.value = true;
};

const executeReactivate = async () => {
  if (!selectedEmp.value) return;
  statusSubmitting.value = true;
  statusError.value = '';

  try {
    await api.post(`/employees/${selectedEmp.value.id}/toggle-status`);
    reactivateModalOpen.value = false;
    await fetchEmployees();
  } catch (err: any) {
    statusError.value = err.response?.data?.message || 'Failed to reactivate employee.';
  } finally {
    statusSubmitting.value = false;
  }
};

const openAssignedLeadsModal = async (emp: any) => {
  selectedEmp.value = emp;
  targetAssigneeId.value = '';
  assignedLeadsList.value = [];
  assignedLeadsModalOpen.value = true;

  try {
    const res = await api.get(`/employees/${emp.id}/leads`);
    assignedLeadsList.value = res.data.leads || [];
  } catch (err) {
    console.error('Failed to fetch assigned leads', err);
  }
};

const executeReassignLeads = async () => {
  if (!selectedEmp.value || !targetAssigneeId.value) return;
  reassigningLeads.value = true;

  try {
    await api.post(`/employees/${selectedEmp.value.id}/reassign-leads`, {
      new_user_id: targetAssigneeId.value,
    });
    assignedLeadsModalOpen.value = false;
    await fetchEmployees();
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to reassign leads.');
  } finally {
    reassigningLeads.value = false;
  }
};

const getEmployeeActions = (emp: any): ActionMenuItem[] => {
  const isSelf = emp.id === authStore.user?.id;
  const isLastActiveAdmin = emp.role === 'admin' && emp.is_active && activeAdminsCount.value <= 1;

  if (emp.is_active) {
    return [
      {
        label: 'Edit Account Details',
        icon: Edit,
        action: () => openEditModal(emp),
      },
      {
        label: `View Assigned Leads (${emp.stats?.assigned_leads || 0})`,
        icon: Briefcase,
        action: () => openAssignedLeadsModal(emp),
      },
      {
        divider: true,
      },
      {
        label: isSelf
          ? 'Cannot Deactivate Self'
          : isLastActiveAdmin
          ? 'Cannot Deactivate Last Admin'
          : 'Deactivate Account',
        icon: UserX,
        danger: true,
        disabled: isSelf || isLastActiveAdmin,
        action: () => openDeactivateModal(emp),
      },
    ];
  }

  return [
    {
      label: 'Edit Account Details',
      icon: Edit,
      action: () => openEditModal(emp),
    },
    {
      label: `View Assigned Leads (${emp.stats?.assigned_leads || 0})`,
      icon: Briefcase,
      action: () => openAssignedLeadsModal(emp),
    },
    {
      divider: true,
    },
    {
      label: 'Reactivate Account',
      icon: UserCheck,
      warning: true,
      action: () => openReactivateModal(emp),
    },
  ];
};

onMounted(async () => {
  await fetchSettings();
  await fetchEmployees();
});
</script>
