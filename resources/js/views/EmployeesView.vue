<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-display font-bold text-vb-plum">Solutions Team Desk</h2>
          <p class="text-xs text-vb-muted mt-0.5">
            Manage employee access, role authorizations, pipeline workloads, and account lifecycle.
          </p>
        </div>

        <button
          @click="createModalOpen = true"
          class="btn-primary text-xs font-semibold px-4 py-2 rounded-full flex items-center gap-1.5 shadow-md self-start sm:self-auto"
        >
          <UserPlus class="w-3.5 h-3.5" />
          <span>Add Employee Account</span>
        </button>
      </div>

      <!-- Segmented Tabs Bar: All, Active, Deactivated -->
      <div class="flex items-center gap-2 border-b border-vb-plum/10 pb-3 text-xs font-semibold">
        <button
          type="button"
          @click="statusFilter = 'all'"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="statusFilter === 'all' ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <span>All Team Members</span>
          <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="statusFilter === 'all' ? 'bg-white/20 text-white' : 'bg-vb-plum/10 text-vb-plum'">
            {{ employees.length }}
          </span>
        </button>

        <button
          type="button"
          @click="statusFilter = 'active'"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="statusFilter === 'active' ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <span>Active Accounts</span>
          <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="statusFilter === 'active' ? 'bg-white/20 text-white' : 'bg-vb-plum/10 text-vb-plum'">
            {{ activeCount }}
          </span>
        </button>

        <button
          type="button"
          @click="statusFilter = 'deactivated'"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="statusFilter === 'deactivated' ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <span>Deactivated</span>
          <span
            v-if="deactivatedCount > 0"
            class="px-1.5 py-0.2 rounded-full text-[10px]"
            :class="statusFilter === 'deactivated' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-700 font-bold'"
          >
            {{ deactivatedCount }}
          </span>
        </button>
      </div>

      <!-- Employees Table -->
      <div class="card-glass overflow-hidden shadow-sm">
        <div v-if="loading" class="py-16 flex justify-center">
          <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
        </div>

        <div v-else-if="filteredEmployees.length === 0" class="py-16 text-center">
          <Users class="w-12 h-12 text-vb-muted/40 mx-auto mb-3" />
          <h3 class="font-display font-bold text-base text-vb-plum">No accounts found</h3>
          <p class="text-xs text-vb-muted mt-1 max-w-sm mx-auto">
            {{ statusFilter === 'deactivated' ? 'No deactivated team members. All accounts are currently active.' : 'No employee accounts matching this filter.' }}
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-vb-cream/80 border-b border-vb-plum/10 text-vb-plum font-semibold">
              <tr>
                <th class="py-3 px-4">Employee</th>
                <th class="py-3 px-4">Role</th>
                <th class="py-3 px-4">Account Status</th>
                <th class="py-3 px-4 text-center">Assigned Leads</th>
                <th class="py-3 px-4 text-center">Due Today</th>
                <th class="py-3 px-4 text-center">Converted</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-vb-plum/5">
              <tr
                v-for="emp in filteredEmployees"
                :key="emp.id"
                class="hover:bg-white/80 transition-colors"
                :class="!emp.is_active ? 'opacity-70 bg-zinc-50/50' : ''"
              >
                <!-- Employee Name & Email -->
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <div
                      class="w-8 h-8 rounded-full font-bold flex items-center justify-center text-xs"
                      :class="emp.is_active ? 'bg-vb-plum text-white' : 'bg-zinc-400 text-white'"
                    >
                      {{ emp.name[0] }}
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <span class="font-bold text-vb-plum text-sm block">{{ emp.name }}</span>
                        <span v-if="emp.id === authStore.user?.id" class="px-1.5 py-0.2 rounded-md bg-vb-violet/10 text-vb-violet text-[10px] font-bold">
                          You
                        </span>
                      </div>
                      <div class="text-[11px] text-vb-muted flex items-center gap-2 mt-0.5">
                        <span>{{ emp.email }}</span>
                        <span v-if="emp.phone">· {{ emp.phone }}</span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Role -->
                <td class="py-3 px-4">
                  <span
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="emp.role === 'admin' ? 'bg-vb-plum text-white' : 'bg-vb-teal/10 text-vb-teal'"
                  >
                    {{ emp.role === 'admin' ? 'Administrator' : 'Employee' }}
                  </span>
                </td>

                <!-- Account Status -->
                <td class="py-3 px-4">
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="emp.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600 border border-gray-300'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="emp.is_active ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                    {{ emp.is_active ? 'Active' : 'Deactivated' }}
                  </span>
                </td>

                <!-- Stats -->
                <td class="py-3 px-4 text-center font-bold text-vb-plum">{{ emp.stats?.assigned_leads || 0 }}</td>
                <td class="py-3 px-4 text-center font-semibold text-vb-amber">{{ emp.stats?.due_today || 0 }}</td>
                <td class="py-3 px-4 text-center font-bold text-vb-emerald">{{ emp.stats?.converted_leads || 0 }}</td>

                <!-- Actions Menu -->
                <td class="py-3 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      @click="openEditModal(emp)"
                      class="px-2.5 py-1 rounded-lg border border-vb-plum/20 hover:bg-vb-plum/5 text-vb-plum font-semibold text-[11px]"
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
    <div v-if="createModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-vb-plum/10 animate-in fade-in zoom-in-95 duration-200">
        <div class="p-5 border-b border-vb-plum/10 flex items-center justify-between bg-vb-cream/50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-vb-plum text-white flex items-center justify-center shadow-md">
              <UserPlus class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-display font-bold text-base text-vb-plum">Add Team Member</h3>
              <p class="text-xs text-vb-muted">Provision desk credentials for a new representative.</p>
            </div>
          </div>
          <button @click="createModalOpen = false" class="p-1 rounded-lg text-vb-muted hover:text-vb-plum">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="handleCreateEmployee" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">Full Name *</label>
            <input v-model="createForm.name" type="text" required class="field text-sm" placeholder="e.g. Ramesh Hegde" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">Email Address *</label>
            <input v-model="createForm.email" type="email" required class="field text-sm" placeholder="employee@vistaboundintegrated.com" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">Mobile Phone (WhatsApp Notifications)</label>
            <input v-model="createForm.phone" type="tel" class="field text-sm" placeholder="e.g. +91 98450 12345" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">Initial Password *</label>
            <input v-model="createForm.password" type="password" required minlength="8" class="field text-sm" placeholder="Minimum 8 characters" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">Role *</label>
            <select v-model="createForm.role" class="field text-sm">
              <option value="employee">Solutions Representative (Employee)</option>
              <option value="admin">Administrator</option>
            </select>
          </div>

          <p v-if="modalError" class="text-xs text-rose-600 bg-rose-50 p-2.5 rounded-lg border border-rose-200">
            {{ modalError }}
          </p>

          <div class="pt-3 border-t border-vb-plum/10 flex items-center justify-end gap-2.5">
            <button type="button" @click="createModalOpen = false" class="px-4 py-2 text-xs font-semibold text-vb-plum">
              Cancel
            </button>
            <button type="submit" :disabled="saving" class="btn-primary text-xs font-semibold px-5 py-2.5 rounded-full flex items-center gap-1.5">
              <span>{{ saving ? 'Creating...' : 'Create Account' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Employee Modal -->
    <div v-if="editModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-vb-plum/10 animate-in fade-in zoom-in-95 duration-200">
        <div class="p-5 border-b border-vb-plum/10 flex items-center justify-between bg-vb-cream/50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-vb-plum text-white flex items-center justify-center shadow-md">
              <UserCog class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-display font-bold text-base text-vb-plum">Edit Team Member</h3>
              <p class="text-xs text-vb-muted">{{ activeEditUser?.email }}</p>
            </div>
          </div>
          <button @click="editModalOpen = false" class="p-1 rounded-lg text-vb-muted hover:text-vb-plum">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="handleEditEmployee" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">Full Name *</label>
            <input v-model="editForm.name" type="text" required class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">Email Address *</label>
            <input v-model="editForm.email" type="email" required class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">Mobile Phone</label>
            <input v-model="editForm.phone" type="tel" class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">New Password (leave blank to keep current)</label>
            <input v-model="editForm.password" type="password" minlength="8" class="field text-sm" placeholder="••••••••" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">Role *</label>
            <select v-model="editForm.role" class="field text-sm">
              <option value="employee">Solutions Representative (Employee)</option>
              <option value="admin">Administrator</option>
            </select>
          </div>

          <p v-if="modalError" class="text-xs text-rose-600 bg-rose-50 p-2.5 rounded-lg border border-rose-200">
            {{ modalError }}
          </p>

          <div class="pt-3 border-t border-vb-plum/10 flex items-center justify-end gap-2.5">
            <button type="button" @click="editModalOpen = false" class="px-4 py-2 text-xs font-semibold text-vb-plum">
              Cancel
            </button>
            <button type="submit" :disabled="saving" class="btn-primary text-xs font-semibold px-5 py-2.5 rounded-full flex items-center gap-1.5">
              <span>{{ saving ? 'Saving...' : 'Save Changes' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- View Assigned Leads Modal -->
    <div v-if="assignedLeadsModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-vb-plum/10 animate-in fade-in zoom-in-95 duration-200">
        <div class="p-5 border-b border-vb-plum/10 flex items-center justify-between bg-vb-cream/50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-vb-violet text-white flex items-center justify-center shadow-md">
              <Briefcase class="w-5 h-5" />
            </div>
            <div>
              <h3 class="font-display font-bold text-base text-vb-plum">Assigned Leads</h3>
              <p class="text-xs text-vb-muted">{{ selectedEmp?.name }} · {{ assignedLeadsList.length }} active accounts</p>
            </div>
          </div>
          <button @click="assignedLeadsModalOpen = false" class="p-1 rounded-lg text-vb-muted hover:text-vb-plum">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
          <div v-if="assignedLeadsList.length === 0" class="py-8 text-center text-xs text-vb-muted">
            No active leads currently assigned to this representative.
          </div>

          <div v-else class="space-y-2">
            <div
              v-for="l in assignedLeadsList"
              :key="l.id"
              class="p-3 bg-vb-cream/40 rounded-xl border border-vb-plum/10 flex items-center justify-between text-xs"
            >
              <div>
                <span class="font-mono font-bold text-vb-violet block">{{ l.reference_id }}</span>
                <span class="font-semibold text-vb-plum">{{ l.name }}</span>
                <span v-if="l.company_name" class="text-vb-muted block text-[11px]">{{ l.company_name }}</span>
              </div>
              <router-link
                :to="`/leads/${l.id}`"
                target="_blank"
                class="px-2.5 py-1 rounded-lg border border-vb-plum/20 text-vb-plum hover:bg-white text-[11px] font-semibold"
              >
                Inspect
              </router-link>
            </div>
          </div>

          <!-- Reassign All Leads Section -->
          <div v-if="assignedLeadsList.length > 0" class="pt-4 border-t border-vb-plum/10 space-y-2.5">
            <label class="block text-xs font-semibold text-vb-plum uppercase tracking-wider">
              Reassign All Leads To Another Active Representative:
            </label>
            <div class="flex gap-2">
              <select v-model="targetAssigneeId" class="field text-xs flex-1">
                <option value="">Select target representative...</option>
                <option
                  v-for="other in activeEmployeesList"
                  :key="other.id"
                  :value="other.id"
                  :disabled="other.id === selectedEmp?.id"
                >
                  {{ other.name }} ({{ other.role }})
                </option>
              </select>
              <button
                type="button"
                @click="executeReassignLeads"
                :disabled="!targetAssigneeId || reassigningLeads"
                class="btn-primary text-xs font-semibold px-4 py-2 rounded-full disabled:opacity-40"
              >
                {{ reassigningLeads ? 'Reassigning...' : 'Reassign All' }}
              </button>
            </div>
          </div>
        </div>

        <div class="p-4 bg-vb-cream/30 border-t border-vb-plum/10 flex justify-end">
          <button @click="assignedLeadsModalOpen = false" class="px-4 py-2 text-xs font-semibold text-vb-plum">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Deactivate Account Confirmation Modal -->
    <ConfirmModal
      :is-open="deactivateModalOpen"
      title="Deactivate Employee Account"
      :subtitle="`${selectedEmp?.name} · ${selectedEmp?.email}`"
      :message="`Are you sure you want to deactivate ${selectedEmp?.name}'s account?`"
      consequences="Deactivation immediately blocks new logins, terminates active web sessions, and invalidates mobile push notification tokens. All historical leads, calls, notes, and activity history created by this employee will remain completely preserved with proper attribution."
      confirm-text="Deactivate Account"
      variant="danger"
      :is-submitting="statusSubmitting"
      :error="statusError"
      @confirm="executeDeactivate"
      @cancel="deactivateModalOpen = false"
    />

    <!-- Reactivate Account Confirmation Modal -->
    <ConfirmModal
      :is-open="reactivateModalOpen"
      title="Reactivate Employee Account"
      :subtitle="`${selectedEmp?.name} · ${selectedEmp?.email}`"
      :message="`Restore CRM access for ${selectedEmp?.name}?`"
      consequences="The employee will be able to log in to the CRM desk and resume managing their assigned pipeline."
      confirm-text="Reactivate Account"
      variant="primary"
      :is-submitting="statusSubmitting"
      :error="statusError"
      @confirm="executeReactivate"
      @cancel="reactivateModalOpen = false"
    />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import ActionMenu, { ActionMenuItem } from '../components/ActionMenu.vue';
import ConfirmModal from '../components/ConfirmModal.vue';
import {
  UserPlus,
  UserCog,
  X,
  Users,
  UserCheck,
  UserX,
  Briefcase,
  Edit,
} from 'lucide-vue-next';

const authStore = useAuthStore();

const loading = ref(true);
const saving = ref(false);
const employees = ref<any[]>([]);
const statusFilter = ref<'all' | 'active' | 'deactivated'>('all');

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
});

const editForm = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  role: 'employee',
});

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

const handleCreateEmployee = async () => {
  saving.value = true;
  modalError.value = '';

  try {
    await api.post('/employees', createForm);
    createModalOpen.value = false;
    createForm.name = '';
    createForm.email = '';
    createForm.phone = '';
    createForm.password = '';
    createForm.role = 'employee';
    await fetchEmployees();
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Failed to create employee account.';
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
  editForm.role = emp.role;
  modalError.value = '';
  editModalOpen.value = true;
};

const handleEditEmployee = async () => {
  if (!activeEditUser.value) return;
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

    await api.put(`/employees/${activeEditUser.value.id}`, payload);
    editModalOpen.value = false;
    await fetchEmployees();
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Failed to update employee.';
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

onMounted(() => {
  fetchEmployees();
});
</script>
