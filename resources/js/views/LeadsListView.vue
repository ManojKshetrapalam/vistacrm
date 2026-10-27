<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-display font-bold text-vb-plum">Lead Management Pipeline</h2>
          <p class="text-xs text-vb-muted mt-0.5">Filter, engage, and update prospective client accounts</p>
        </div>

        <div class="flex items-center gap-2.5">
          <button
            type="button"
            @click="exportCsv"
            :disabled="exporting"
            class="px-4 py-2 bg-white border border-vb-plum/20 hover:border-vb-plum text-vb-plum rounded-full text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-sm"
          >
            <Download class="w-3.5 h-3.5" />
            <span>{{ exporting ? 'Exporting...' : 'Export CSV' }}</span>
          </button>

          <router-link
            to="/leads/new"
            class="btn-primary text-xs font-semibold px-4 py-2 rounded-full flex items-center gap-1.5 shadow-md"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Add Lead</span>
          </router-link>
        </div>
      </div>

      <!-- Filters Card -->
      <div class="card-glass p-5 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
          <!-- Search -->
          <div class="lg:col-span-2 relative">
            <Search class="w-4 h-4 text-vb-muted absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="filters.search"
              @input="debounceSearch"
              type="text"
              placeholder="Search by name, phone, company, reference..."
              class="field pl-9 text-xs"
            />
          </div>

          <!-- Status Filter -->
          <div>
            <select v-model="filters.status" @change="fetchLeads" class="field text-xs">
              <option value="">All Statuses</option>
              <option value="new">New</option>
              <option value="assigned">Assigned</option>
              <option value="contacted">Contacted</option>
              <option value="follow_up">Follow-up</option>
              <option value="interested">Interested</option>
              <option value="converted">Converted</option>
              <option value="lost">Lost</option>
              <option value="not_reachable">Not Reachable</option>
            </select>
          </div>

          <!-- Priority Filter -->
          <div>
            <select v-model="filters.priority" @change="fetchLeads" class="field text-xs">
              <option value="">All Priorities</option>
              <option value="urgent">Urgent</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
          </div>

          <!-- Follow-up Timing Filter -->
          <div>
            <select v-model="filters.followup_filter" @change="fetchLeads" class="field text-xs">
              <option value="">All Follow-up Dates</option>
              <option value="overdue">Overdue Follow-ups</option>
              <option value="today">Due Today</option>
              <option value="upcoming">Upcoming</option>
            </select>
          </div>
        </div>

        <!-- Secondary Filters Row (Source & Assignee) -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-vb-plum/5 text-xs">
          <div class="flex flex-wrap items-center gap-3">
            <!-- Source Filter -->
            <select v-model="filters.source" @change="fetchLeads" class="bg-white border border-vb-plum/15 rounded-lg px-2.5 py-1 text-xs text-vb-ink">
              <option value="">All Lead Sources</option>
              <option v-for="src in leadSources" :key="src" :value="src">{{ src }}</option>
            </select>

            <!-- Employee Filter (Admin Only) -->
            <select
              v-if="authStore.isAdmin"
              v-model="filters.assigned_to"
              @change="fetchLeads"
              class="bg-white border border-vb-plum/15 rounded-lg px-2.5 py-1 text-xs text-vb-ink"
            >
              <option value="">All Employees</option>
              <option value="unassigned">Unassigned Only</option>
              <option v-for="emp in employeesList" :key="emp.id" :value="emp.id">
                {{ emp.name }}
              </option>
            </select>
          </div>

          <button
            v-if="hasActiveFilters"
            @click="resetFilters"
            class="text-xs text-rose-600 hover:underline font-semibold"
          >
            Clear all filters
          </button>
        </div>
      </div>

      <!-- Leads Data Table Card -->
      <div class="card-glass overflow-hidden">
        <div v-if="loading" class="py-16 flex flex-col items-center justify-center gap-3">
          <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
          <span class="text-xs text-vb-muted font-medium">Loading pipeline accounts...</span>
        </div>

        <div v-else-if="leads.length === 0" class="py-16 text-center px-4">
          <div class="w-12 h-12 rounded-2xl bg-vb-cream text-vb-plum mx-auto flex items-center justify-center mb-3">
            <Users class="w-6 h-6" />
          </div>
          <h3 class="font-display font-bold text-base text-vb-plum">No leads match your filter</h3>
          <p class="text-xs text-vb-muted mt-1 max-w-sm mx-auto">
            Try adjusting your search criteria or create a new prospective lead account.
          </p>
          <div class="mt-4 flex justify-center gap-3">
            <button @click="resetFilters" class="px-4 py-1.5 text-xs border border-vb-plum/20 rounded-full font-semibold">
              Reset Filters
            </button>
            <router-link to="/leads/new" class="btn-primary px-4 py-1.5 text-xs rounded-full font-semibold">
              Create Lead
            </router-link>
          </div>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-vb-cream/60 border-b border-vb-plum/10 text-vb-plum font-semibold">
              <tr>
                <th class="py-3.5 px-4">Reference</th>
                <th class="py-3.5 px-4">Client / Contact</th>
                <th class="py-3.5 px-4">Company &amp; Service</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 px-4">Priority</th>
                <th class="py-3.5 px-4">Representative</th>
                <th class="py-3.5 px-4">Next Follow-up</th>
                <th class="py-3.5 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-vb-plum/5">
              <tr
                v-for="lead in leads"
                :key="lead.id"
                class="hover:bg-white/80 transition-colors group"
              >
                <!-- Reference ID -->
                <td class="py-3 px-4 font-mono font-bold text-vb-plum">
                  <router-link :to="`/leads/${lead.id}`" class="hover:underline">
                    {{ lead.reference_id }}
                  </router-link>
                </td>

                <!-- Name & Phone -->
                <td class="py-3 px-4">
                  <router-link :to="`/leads/${lead.id}`" class="font-bold text-vb-plum text-sm hover:underline block">
                    {{ lead.name }}
                  </router-link>
                  <div class="flex items-center gap-2 mt-0.5 text-[11px] text-vb-muted">
                    <span>{{ lead.phone }}</span>
                    <span v-if="lead.email">· {{ lead.email }}</span>
                  </div>
                </td>

                <!-- Company & Service -->
                <td class="py-3 px-4">
                  <span class="font-semibold text-vb-ink block">{{ lead.company_name || 'Individual Client' }}</span>
                  <span class="text-[11px] text-vb-muted block truncate max-w-[180px]">{{ lead.service_interest || 'General Inquiry' }}</span>
                </td>

                <!-- Status -->
                <td class="py-3 px-4">
                  <StatusBadge :status="lead.status" />
                </td>

                <!-- Priority -->
                <td class="py-3 px-4">
                  <PriorityBadge :priority="lead.priority" />
                </td>

                <!-- Assigned Employee -->
                <td class="py-3 px-4">
                  <div v-if="lead.assigned_employee" class="flex items-center gap-1.5">
                    <div class="w-5 h-5 rounded-full bg-vb-plum/10 text-vb-plum text-[10px] font-bold flex items-center justify-center">
                      {{ lead.assigned_employee.name[0] }}
                    </div>
                    <span class="font-medium text-vb-ink">{{ lead.assigned_employee.name }}</span>
                  </div>
                  <span v-else class="text-[11px] text-vb-muted/70 italic">Unassigned</span>
                </td>

                <!-- Next Follow-up -->
                <td class="py-3 px-4">
                  <div v-if="lead.next_follow_up_date" class="space-y-0.5">
                    <span
                      class="text-xs font-semibold block"
                      :class="isOverdue(lead.next_follow_up_date) ? 'text-rose-600 font-bold' : 'text-vb-ink'"
                    >
                      {{ formatDateTime(lead.next_follow_up_date) }}
                    </span>
                    <span v-if="isOverdue(lead.next_follow_up_date)" class="text-[10px] text-rose-600 bg-rose-50 px-1.5 py-0.2 rounded font-bold uppercase tracking-wider inline-block">
                      Overdue
                    </span>
                  </div>
                  <span v-else class="text-[11px] text-vb-muted italic">None scheduled</span>
                </td>

                <!-- Row Actions -->
                <td class="py-3 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <!-- WhatsApp Button -->
                    <button
                      title="WhatsApp Direct Connect"
                      @click="openWhatsAppModal(lead)"
                      class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 transition-colors"
                    >
                      <MessageSquare class="w-4 h-4" />
                    </button>

                    <!-- Call Log Button -->
                    <button
                      title="Log Call Outcome"
                      @click="openCallModal(lead)"
                      class="p-1.5 rounded-lg text-vb-plum hover:bg-vb-plum/10 transition-colors"
                    >
                      <PhoneCall class="w-4 h-4" />
                    </button>

                    <!-- View Details -->
                    <router-link
                      :to="`/leads/${lead.id}`"
                      title="View Lead Details & Timeline"
                      class="p-1.5 rounded-lg text-vb-muted hover:text-vb-plum hover:bg-vb-cream transition-colors"
                    >
                      <ChevronRight class="w-4 h-4" />
                    </router-link>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Bar -->
        <div class="p-4 border-t border-vb-plum/10 bg-vb-cream/30 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
          <div class="text-vb-muted">
            Showing <strong class="text-vb-plum">{{ leads.length }}</strong> of <strong class="text-vb-plum">{{ pagination.total }}</strong> leads
          </div>

          <div class="flex items-center gap-2">
            <button
              :disabled="pagination.current_page <= 1"
              @click="goToPage(pagination.current_page - 1)"
              class="px-3 py-1.5 rounded-lg bg-white border border-vb-plum/15 text-vb-plum disabled:opacity-40 disabled:cursor-not-allowed font-medium hover:bg-vb-cream"
            >
              Previous
            </button>
            <span class="text-xs font-semibold px-2 text-vb-plum">
              Page {{ pagination.current_page }} of {{ pagination.last_page }}
            </span>
            <button
              :disabled="pagination.current_page >= pagination.last_page"
              @click="goToPage(pagination.current_page + 1)"
              class="px-3 py-1.5 rounded-lg bg-white border border-vb-plum/15 text-vb-plum disabled:opacity-40 disabled:cursor-not-allowed font-medium hover:bg-vb-cream"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <WhatsAppModal
      :is-open="whatsAppModalOpen"
      :lead="activeLead"
      @close="whatsAppModalOpen = false"
      @dispatched="fetchLeads"
    />

    <LogCallModal
      :is-open="callModalOpen"
      :lead="activeLead"
      @close="callModalOpen = false"
      @saved="fetchLeads"
    />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import StatusBadge from '../components/StatusBadge.vue';
import PriorityBadge from '../components/PriorityBadge.vue';
import WhatsAppModal from '../components/WhatsAppModal.vue';
import LogCallModal from '../components/LogCallModal.vue';
import {
  Plus,
  Download,
  Search,
  MessageSquare,
  PhoneCall,
  ChevronRight,
  Users,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const loading = ref(true);
const exporting = ref(false);
const leads = ref<any[]>([]);
const leadSources = ref<string[]>([]);
const employeesList = ref<any[]>([]);

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
});

const filters = reactive({
  search: '',
  status: '',
  priority: '',
  source: '',
  assigned_to: '',
  followup_filter: '',
});

const activeLead = ref<any>(null);
const whatsAppModalOpen = ref(false);
const callModalOpen = ref(false);

let searchTimeout: any = null;

const hasActiveFilters = computed(() => {
  return (
    !!filters.search ||
    !!filters.status ||
    !!filters.priority ||
    !!filters.source ||
    !!filters.assigned_to ||
    !!filters.followup_filter
  );
});

const debounceSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    pagination.current_page = 1;
    fetchLeads();
  }, 350);
};

const resetFilters = () => {
  filters.search = '';
  filters.status = '';
  filters.priority = '';
  filters.source = '';
  filters.assigned_to = '';
  filters.followup_filter = '';
  pagination.current_page = 1;
  fetchLeads();
};

const fetchLeads = async () => {
  loading.value = true;
  try {
    const params: any = {
      page: pagination.current_page,
      per_page: 15,
    };
    if (filters.search) params.search = filters.search;
    if (filters.status) params.status = filters.status;
    if (filters.priority) params.priority = filters.priority;
    if (filters.source) params.source = filters.source;
    if (filters.assigned_to) params.assigned_to = filters.assigned_to;
    if (filters.followup_filter) params.followup_filter = filters.followup_filter;

    const res = await api.get('/leads', { params });
    leads.value = res.data.data;
    pagination.current_page = res.data.current_page;
    pagination.last_page = res.data.last_page;
    pagination.total = res.data.total;
  } catch (err) {
    console.error('Failed to fetch leads', err);
  } finally {
    loading.value = false;
  }
};

const goToPage = (page: number) => {
  pagination.current_page = page;
  fetchLeads();
};

const openWhatsAppModal = (lead: any) => {
  activeLead.value = lead;
  whatsAppModalOpen.value = true;
};

const openCallModal = (lead: any) => {
  activeLead.value = lead;
  callModalOpen.value = true;
};

const exportCsv = async () => {
  exporting.value = true;
  try {
    const params: any = {};
    if (filters.status) params.status = filters.status;
    if (filters.source) params.source = filters.source;
    if (filters.assigned_to) params.assigned_to = filters.assigned_to;

    const response = await api.get('/leads/export', {
      params,
      responseType: 'blob',
    });

    const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `vistabound_leads_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  } catch (err) {
    console.error('Failed to export leads CSV', err);
  } finally {
    exporting.value = false;
  }
};

const fetchSettings = async () => {
  try {
    const res = await api.get('/settings');
    leadSources.value = res.data.lead_sources || [];
    employeesList.value = res.data.employees || [];
  } catch (err) {
    // quiet
  }
};

const isOverdue = (dateStr: string) => {
  if (!dateStr) return false;
  return new Date(dateStr) < new Date();
};

const formatDateTime = (dateStr: string) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
};

onMounted(() => {
  fetchLeads();
  fetchSettings();
});
</script>
