<template>
  <AppLayout>
    <div class="space-y-6 max-w-5xl mx-auto">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-widest text-vb-violet font-display">Personnel Management</span>
          <h2 class="text-2xl font-display font-bold text-vb-plum mt-1">Solutions Desk Employee Accounts</h2>
          <p class="text-xs text-vb-muted mt-0.5">
            Manage account access, contact details, and view individual pipeline allocations.
          </p>
        </div>

        <button
          @click="openAddModal"
          class="btn-primary text-xs font-semibold px-4 py-2.5 rounded-full flex items-center gap-2 shadow-md self-start"
        >
          <UserPlus class="w-4 h-4" />
          <span>Create Employee Account</span>
        </button>
      </div>

      <!-- Employees Table -->
      <div class="card-glass overflow-hidden">
        <div v-if="loading" class="py-16 flex justify-center">
          <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-vb-cream/80 border-b border-vb-plum/10 text-vb-plum font-semibold">
              <tr>
                <th class="py-3 px-4">Employee</th>
                <th class="py-3 px-4">Role</th>
                <th class="py-3 px-4">Account Status</th>
                <th class="py-3 px-4 text-center">Assigned</th>
                <th class="py-3 px-4 text-center">Due Today</th>
                <th class="py-3 px-4 text-center">Converted</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-vb-plum/5">
              <tr v-for="emp in employees" :key="emp.id" class="hover:bg-white/80 transition-colors">
                <!-- Employee Name & Email -->
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-vb-plum text-white font-bold flex items-center justify-center text-xs">
                      {{ emp.name[0] }}
                    </div>
                    <div>
                      <span class="font-bold text-vb-plum text-sm block">{{ emp.name }}</span>
                      <div class="text-[11px] text-vb-muted flex items-center gap-2">
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
                    :class="emp.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-500 border border-gray-200'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="emp.is_active ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                    {{ emp.is_active ? 'Active' : 'Deactivated' }}
                  </span>
                </td>

                <!-- Stats -->
                <td class="py-3 px-4 text-center font-bold text-vb-plum">{{ emp.stats?.assigned_leads || 0 }}</td>
                <td class="py-3 px-4 text-center font-semibold text-vb-amber">{{ emp.stats?.due_today || 0 }}</td>
                <td class="py-3 px-4 text-center font-bold text-vb-emerald">{{ emp.stats?.converted_leads || 0 }}</td>

                <!-- Actions -->
                <td class="py-3 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      @click="openEditModal(emp)"
                      class="px-3 py-1 rounded-lg border border-vb-plum/20 hover:bg-vb-plum/5 text-vb-plum font-semibold text-[11px]"
                    >
                      Edit
                    </button>
                    <button
                      v-if="emp.id !== authStore.user?.id"
                      @click="toggleActive(emp)"
                      class="px-2.5 py-1 rounded-lg font-semibold text-[11px] transition-colors"
                      :class="emp.is_active ? 'text-rose-600 hover:bg-rose-50 border border-rose-200' : 'text-emerald-700 hover:bg-emerald-50 border border-emerald-200'"
                    >
                      {{ emp.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Add Employee Modal -->
    <div v-if="addModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4 border border-vb-plum/10">
        <h3 class="font-display font-bold text-base text-vb-plum">Register Employee Account</h3>
        <p class="text-xs text-vb-muted">Create credentials for a new CRM user.</p>

        <form @submit.prevent="handleAddEmployee" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Full Name</label>
            <input v-model="addForm.name" type="text" required placeholder="e.g. Ramesh K" class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Email Address</label>
            <input v-model="addForm.email" type="email" required placeholder="name@vistaboundintegrated.com" class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Phone Number (WhatsApp)</label>
            <input v-model="addForm.phone" type="text" placeholder="+91 98800 11223" class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Initial Password</label>
            <input v-model="addForm.password" type="password" required minlength="8" placeholder="••••••••••••" class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Role Permission</label>
            <select v-model="addForm.role" class="field text-sm">
              <option value="employee">Employee (Assigned Leads Only)</option>
              <option value="admin">Administrator (Full Access)</option>
            </select>
          </div>

          <div v-if="modalError" class="p-2.5 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl">
            {{ modalError }}
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-vb-plum/10">
            <button type="button" @click="addModalOpen = false" class="px-4 py-2 text-xs font-semibold text-vb-plum">
              Cancel
            </button>
            <button type="submit" :disabled="saving" class="btn-primary px-5 py-2 text-xs rounded-full font-semibold">
              {{ saving ? 'Creating...' : 'Create Account' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Employee Modal -->
    <div v-if="editModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4 border border-vb-plum/10">
        <h3 class="font-display font-bold text-base text-vb-plum">Edit Employee Details</h3>

        <form @submit.prevent="handleEditEmployee" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Full Name</label>
            <input v-model="editForm.name" type="text" required class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Email Address</label>
            <input v-model="editForm.email" type="email" required class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Phone Number</label>
            <input v-model="editForm.phone" type="text" class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Reset Password (Leave blank to keep current)</label>
            <input v-model="editForm.password" type="password" minlength="8" placeholder="New password..." class="field text-sm" />
          </div>

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1">Role</label>
            <select v-model="editForm.role" class="field text-sm">
              <option value="employee">Employee</option>
              <option value="admin">Administrator</option>
            </select>
          </div>

          <div v-if="modalError" class="p-2.5 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl">
            {{ modalError }}
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-vb-plum/10">
            <button type="button" @click="editModalOpen = false" class="px-4 py-2 text-xs font-semibold text-vb-plum">
              Cancel
            </button>
            <button type="submit" :disabled="saving" class="btn-primary px-5 py-2 text-xs rounded-full font-semibold">
              {{ saving ? 'Saving...' : 'Update Employee' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import { UserPlus } from 'lucide-vue-next';

const authStore = useAuthStore();
const employees = ref<any[]>([]);
const loading = ref(true);

const addModalOpen = ref(false);
const editModalOpen = ref(false);
const saving = ref(false);
const modalError = ref('');

const activeEditUser = ref<any>(null);

const addForm = reactive({
  name: '',
  email: '',
  phone: '',
  password: 'Vistabound@2026',
  role: 'employee',
});

const editForm = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  role: 'employee',
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

const openAddModal = () => {
  addForm.name = '';
  addForm.email = '';
  addForm.phone = '';
  addForm.password = 'Vistabound@2026';
  addForm.role = 'employee';
  modalError.value = '';
  addModalOpen.value = true;
};

const handleAddEmployee = async () => {
  saving.value = true;
  modalError.value = '';
  try {
    await api.post('/employees', addForm);
    addModalOpen.value = false;
    await fetchEmployees();
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Failed to create employee.';
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

const toggleActive = async (emp: any) => {
  const confirmMsg = emp.is_active
    ? `Deactivate account for ${emp.name}? They will not be able to log in.`
    : `Activate account for ${emp.name}?`;

  if (!confirm(confirmMsg)) return;

  try {
    await api.post(`/employees/${emp.id}/toggle-status`);
    await fetchEmployees();
  } catch (err: any) {
    alert(err.response?.data?.message || 'Action failed.');
  }
};

onMounted(() => {
  fetchEmployees();
});
</script>
