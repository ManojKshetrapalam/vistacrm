<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Top Title & Primary Actions Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-display font-bold text-vb-plum">Lead Pipeline</h2>
          <p class="text-xs text-vb-muted mt-0.5">
            {{ viewArchived ? 'Archived leads repository. Records can be restored or inspected with full historical audit trails.' : 'Track client engagement, assignment statuses, and scheduled consultation deadlines.' }}
          </p>
        </div>

        <div class="flex items-center gap-2.5">
          <button
            @click="exportCsv"
            :disabled="exporting || leads.length === 0"
            class="px-4 py-2 bg-white border border-vb-plum/20 hover:border-vb-plum text-vb-plum rounded-full text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-sm disabled:opacity-40"
          >
            <Download class="w-3.5 h-3.5" />
            <span>{{ exporting ? 'Exporting...' : 'Export Pipeline CSV' }}</span>
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

      <!-- Admin Tabs: Active Pipeline vs Archived Leads -->
      <div v-if="authStore.isAdmin" class="flex items-center gap-2 border-b border-vb-plum/10 pb-3 text-xs font-semibold">
        <button
          type="button"
          @click="setArchivedMode(false)"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="!viewArchived ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <span>Active Pipeline</span>
        </button>

        <button
          type="button"
          @click="setArchivedMode(true)"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="viewArchived ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <Archive class="w-3.5 h-3.5" />
          <span>Archived Leads</span>
          <span
            v-if="archivedCount > 0"
            class="px-1.5 py-0.2 rounded-full text-[10px]"
            :class="viewArchived ? 'bg-white/20 text-white' : 'bg-vb-plum/10 text-vb-plum'"
          >
            {{ archivedCount }}
          </span>
        </button>
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

          <!-- Status Filter (only relevant for Active Pipeline) -->
          <div v-if="!viewArchived">
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
          <div v-if="!viewArchived">
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
            class="text-xs text-vb-muted hover:text-vb-plum underline font-medium"
          >
            Clear all filters
          </button>
        </div>
      </div>

      <!-- Leads Table Container -->
      <div class="card-glass overflow-hidden shadow-sm">
        <div v-if="loading" class="py-20 flex justify-center items-center">
          <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
        </div>

        <div v-else-if="leads.length === 0" class="py-16 text-center">
          <Users class="w-12 h-12 text-vb-muted/40 mx-auto mb-3" />
          <h3 class="font-display font-bold text-base text-vb-plum">
            {{ viewArchived ? 'No archived leads found' : 'No matching leads found' }}
          </h3>
          <p class="text-xs text-vb-muted mt-1 max-w-sm mx-auto">
            {{ viewArchived ? 'No records have been archived. Archived leads will appear here for safe restoration or review.' : 'Try adjusting your search criteria or create a new lead.' }}
          </p>
          <div v-if="hasActiveFilters" class="mt-4 flex items-center justify-center gap-2">
            <button @click="resetFilters" class="px-4 py-1.5 text-xs border border-vb-plum/20 rounded-full font-semibold text-vb-plum hover:bg-vb-cream">
              Reset Filters
            </button>
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
                <th class="py-3.5 px-4">{{ viewArchived ? 'Archived On' : 'Next Follow-up' }}</th>
                <th class="py-3.5 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-vb-plum/5">
              <tr
                v-for="lead in leads"
                :key="lead.id"
                class="hover:bg-white/60 transition-colors group"
                :class="viewArchived ? 'opacity-85' : ''"
              >
                <!-- Reference ID -->
                <td class="py-3 px-4 font-mono font-bold text-vb-violet">
                  <router-link :to="`/leads/${lead.id}`" class="hover:underline">
                    {{ lead.reference_id }}
                  </router-link>
                </td>

                <!-- Contact Info -->
                <td class="py-3 px-4">
                  <router-link :to="`/leads/${lead.id}`" class="font-bold text-vb-plum block hover:underline">
                    {{ lead.name }}
                  </router-link>
                  <span class="text-vb-muted font-medium text-[11px] block mt-0.5">
                    {{ lead.phone }}
                  </span>
                </td>

                <!-- Company & Service Interest -->
                <td class="py-3 px-4">
                  <span class="font-semibold text-vb-ink block truncate max-w-[180px]">
                    {{ lead.company_name || 'Individual / Not specified' }}
                  </span>
                  <span class="text-[11px] text-vb-muted block mt-0.5 truncate max-w-[180px]">
                    {{ lead.service_interest || 'General Consultation' }}
                  </span>
                </td>

                <!-- Status Badge -->
                <td class="py-3 px-4">
                  <span v-if="viewArchived" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-zinc-200 text-zinc-700">
                    Archived
                  </span>
                  <StatusBadge v-else :status="lead.status" />
                </td>

                <!-- Priority Badge -->
                <td class="py-3 px-4">
                  <PriorityBadge :priority="lead.priority" />
                </td>

                <!-- Assigned Representative -->
                <td class="py-3 px-4">
                  <div v-if="lead.assigned_employee" class="flex items-center gap-1.5">
                    <div class="w-5 h-5 rounded-full bg-vb-plum text-white text-[9px] font-bold flex items-center justify-center">
                      {{ lead.assigned_employee.name[0] }}
                    </div>
                    <span class="text-vb-plum font-medium truncate max-w-[120px]">
                      {{ lead.assigned_employee.name }}
                    </span>
                  </div>
                  <span v-else class="text-vb-muted text-[11px] italic">Unassigned</span>
                </td>

                <!-- Next Follow-up or Archived Date -->
                <td class="py-3 px-4">
                  <span v-if="viewArchived" class="text-[11px] text-vb-muted">
                    {{ formatDateTime(lead.deleted_at) }}
                  </span>
                  <div v-else-if="lead.next_follow_up_date">
                    <span
                      class="font-semibold text-[11px] block"
                      :class="isOverdue(lead.next_follow_up_date) ? 'text-rose-600 font-bold' : 'text-vb-plum'"
                    >
                      {{ formatDateTime(lead.next_follow_up_date) }}
                    </span>
                  </div>
                  <span v-else class="text-[11px] text-vb-muted italic">None scheduled</span>
                </td>

                <!-- Row Actions -->
                <td class="py-3 px-4 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <!-- WhatsApp Button (Active leads only) -->
                    <button
                      v-if="!viewArchived"
                      title="WhatsApp Direct Connect"
                      @click="openWhatsAppModal(lead)"
                      class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 transition-colors"
                    >
                      <MessageSquare class="w-4 h-4" />
                    </button>

                    <!-- Call Log Button (Active leads only) -->
                    <button
                      v-if="!viewArchived"
                      title="Log Call Outcome"
                      @click="openCallModal(lead)"
                      class="p-1.5 rounded-lg text-vb-plum hover:bg-vb-plum/10 transition-colors"
                    >
                      <PhoneCall class="w-4 h-4" />
                    </button>

                    <!-- View Details link -->
                    <router-link
                      :to="`/leads/${lead.id}`"
                      title="View Lead Details & Timeline"
                      class="p-1.5 rounded-lg text-vb-muted hover:text-vb-plum hover:bg-vb-cream transition-colors"
                    >
                      <ChevronRight class="w-4 h-4" />
                    </router-link>

                    <!-- Unified Actions Menu Dropdown -->
                    <ActionMenu
                      :items="getLeadActions(lead)"
                      title="Lead Management Options"
                    />
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

          <div v-if="pagination.last_page > 1" class="flex items-center gap-1.5">
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

    <ScheduleFollowUpModal
      :is-open="followUpModalOpen"
      :lead="activeLead"
      @close="followUpModalOpen = false"
      @saved="fetchLeads"
    />

    <EditLeadModal
      :is-open="editModalOpen"
      :lead="activeLead"
      @close="editModalOpen = false"
      @saved="handleLeadEdited"
    />

    <!-- Archive Lead Confirmation Modal -->
    <ConfirmModal
      :is-open="archiveModalOpen"
      title="Archive Lead"
      :subtitle="`${activeLead?.reference_id} · ${activeLead?.name}`"
      :message="`Are you sure you want to archive lead ${activeLead?.reference_id}?`"
      consequences="Archiving will remove this lead from active pipeline views, assignment lists, and follow-up queues. All previous call logs, client notes, and activity history will be preserved. You can restore this lead at any time from the Archived Leads tab."
      confirm-text="Archive Lead"
      variant="danger"
      :is-submitting="actionSubmitting"
      :error="actionError"
      @confirm="executeArchiveLead"
      @cancel="archiveModalOpen = false"
    />

    <!-- Restore Lead Confirmation Modal -->
    <ConfirmModal
      :is-open="restoreModalOpen"
      title="Restore Lead to Pipeline"
      :subtitle="`${activeLead?.reference_id} · ${activeLead?.name}`"
      :message="`Restore lead ${activeLead?.reference_id} (${activeLead?.name}) to the active pipeline?`"
      consequences="The lead will reappear in active pipeline lists and remain assigned to its designated solutions representative without duplicating past reminders or activities."
      confirm-text="Restore Lead"
      variant="warning"
      :is-submitting="actionSubmitting"
      :error="actionError"
      @confirm="executeRestoreLead"
      @cancel="restoreModalOpen = false"
    />

    <!-- Permanent Delete Lead Modal (Requires Typing Reference ID) -->
    <ConfirmModal
      :is-open="forceDeleteModalOpen"
      title="Permanently Delete Lead Record"
      :subtitle="`${activeLead?.reference_id} · ${activeLead?.name}`"
      :message="`This action will permanently delete ${activeLead?.reference_id} and all related activity history from the database.`"
      consequences="This action cannot be undone. All audit history, follow-up logs, and engagement records for this lead will be permanently deleted from the database."
      confirm-text="Permanently Delete Record"
      variant="danger"
      :required-confirmation-text="activeLead?.reference_id"
      :is-submitting="actionSubmitting"
      :error="actionError"
      @confirm="executeForceDeleteLead"
      @cancel="forceDeleteModalOpen = false"
    />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import { isOnline } from '../services/syncEngine';
import { offlineStorage } from '../services/offlineStorage';
import AppLayout from '../components/AppLayout.vue';
import StatusBadge from '../components/StatusBadge.vue';
import PriorityBadge from '../components/PriorityBadge.vue';
import WhatsAppModal from '../components/WhatsAppModal.vue';
import LogCallModal from '../components/LogCallModal.vue';
import ScheduleFollowUpModal from '../components/ScheduleFollowUpModal.vue';
import EditLeadModal from '../components/EditLeadModal.vue';
import ConfirmModal from '../components/ConfirmModal.vue';
import ActionMenu, { ActionMenuItem } from '../components/ActionMenu.vue';
import {
  Plus,
  Download,
  Search,
  MessageSquare,
  PhoneCall,
  ChevronRight,
  Users,
  Archive,
  RotateCcw,
  Trash2,
  Edit,
  Eye,
  CalendarClock,
} from 'lucide-vue-next';

const authStore = useAuthStore();

const loading = ref(true);
const exporting = ref(false);
const viewArchived = ref(false);
const archivedCount = ref(0);

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
const followUpModalOpen = ref(false);
const editModalOpen = ref(false);
const archiveModalOpen = ref(false);
const restoreModalOpen = ref(false);
const forceDeleteModalOpen = ref(false);

const actionSubmitting = ref(false);
const actionError = ref('');

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

const setArchivedMode = (archived: boolean) => {
  viewArchived.value = archived;
  pagination.current_page = 1;
  filters.status = '';
  fetchLeads();
};

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
    if (isOnline.value) {
      const params: any = {
        page: pagination.current_page,
      };

      if (viewArchived.value) {
        params.archived = 1;
      }

      if (filters.search) params.search = filters.search;
      if (filters.status && !viewArchived.value) params.status = filters.status;
      if (filters.priority) params.priority = filters.priority;
      if (filters.source) params.source = filters.source;
      if (filters.assigned_to) params.assigned_to = filters.assigned_to;
      if (filters.followup_filter && !viewArchived.value) params.followup_filter = filters.followup_filter;

      const res = await api.get('/leads', { params });
      leads.value = res.data.data;
      pagination.current_page = res.data.current_page;
      pagination.last_page = res.data.last_page;
      pagination.total = res.data.total;

      // Update cached leads for active views
      if (!viewArchived.value && leads.value.length > 0) {
        await offlineStorage.setLeads(leads.value);
      }

      // Fetch archived badge count if admin
      if (authStore.isAdmin && !viewArchived.value) {
        api.get('/leads', { params: { archived: 1, per_page: 1 } })
          .then((res) => {
            archivedCount.value = res.data.total || 0;
          })
          .catch(() => {});
      }
    } else {
      // Offline fallback
      let cached = await offlineStorage.getLeads();
      if (filters.search) {
        const s = filters.search.toLowerCase();
        cached = cached.filter((l: any) =>
          l.name?.toLowerCase().includes(s) ||
          l.phone?.includes(s) ||
          l.company_name?.toLowerCase().includes(s) ||
          l.reference_id?.toLowerCase().includes(s)
        );
      }
      if (filters.status) {
        cached = cached.filter((l: any) => l.status === filters.status);
      }
      if (filters.priority) {
        cached = cached.filter((l: any) => l.priority === filters.priority);
      }
      leads.value = cached;
      pagination.current_page = 1;
      pagination.last_page = 1;
      pagination.total = cached.length;
    }
  } catch (err) {
    console.warn('Failed to fetch leads online, using cached storage', err);
    const cached = await offlineStorage.getLeads();
    leads.value = cached;
    pagination.current_page = 1;
    pagination.last_page = 1;
    pagination.total = cached.length;
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

const openFollowUpModal = (lead: any) => {
  activeLead.value = lead;
  followUpModalOpen.value = true;
};

const openEditModal = (lead: any) => {
  activeLead.value = lead;
  editModalOpen.value = true;
};

const handleLeadEdited = () => {
  fetchLeads();
};

const openArchiveModal = (lead: any) => {
  activeLead.value = lead;
  actionError.value = '';
  archiveModalOpen.value = true;
};

const executeArchiveLead = async () => {
  if (!activeLead.value) return;
  actionSubmitting.value = true;
  actionError.value = '';

  try {
    await api.delete(`/leads/${activeLead.value.id}`);
    archiveModalOpen.value = false;
    await fetchLeads();
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Failed to archive lead.';
  } finally {
    actionSubmitting.value = false;
  }
};

const openRestoreModal = (lead: any) => {
  activeLead.value = lead;
  actionError.value = '';
  restoreModalOpen.value = true;
};

const executeRestoreLead = async () => {
  if (!activeLead.value) return;
  actionSubmitting.value = true;
  actionError.value = '';

  try {
    await api.post(`/leads/${activeLead.value.id}/restore`);
    restoreModalOpen.value = false;
    await fetchLeads();
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Failed to restore lead.';
  } finally {
    actionSubmitting.value = false;
  }
};

const openForceDeleteModal = (lead: any) => {
  activeLead.value = lead;
  actionError.value = '';
  forceDeleteModalOpen.value = true;
};

const executeForceDeleteLead = async () => {
  if (!activeLead.value) return;
  actionSubmitting.value = true;
  actionError.value = '';

  try {
    await api.delete(`/leads/${activeLead.value.id}/force`);
    forceDeleteModalOpen.value = false;
    await fetchLeads();
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Failed to permanently delete lead record.';
  } finally {
    actionSubmitting.value = false;
  }
};

const getLeadActions = (lead: any): ActionMenuItem[] => {
  if (viewArchived.value) {
    return [
      {
        label: 'View History & Audit',
        icon: Eye,
        to: `/leads/${lead.id}`,
      },
      {
        label: 'Restore Lead to Pipeline',
        icon: RotateCcw,
        warning: true,
        show: authStore.isAdmin,
        action: () => openRestoreModal(lead),
      },
      {
        divider: true,
        show: authStore.isAdmin,
      },
      {
        label: 'Permanently Delete Record',
        icon: Trash2,
        danger: true,
        show: authStore.isAdmin,
        action: () => openForceDeleteModal(lead),
      },
    ];
  }

  return [
    {
      label: 'View Full Timeline',
      icon: Eye,
      to: `/leads/${lead.id}`,
    },
    {
      label: 'Edit Lead Profile',
      icon: Edit,
      action: () => openEditModal(lead),
    },
    {
      label: 'Schedule Follow-up',
      icon: CalendarClock,
      action: () => openFollowUpModal(lead),
    },
    {
      divider: true,
      show: authStore.isAdmin,
    },
    {
      label: 'Archive Lead',
      icon: Archive,
      danger: true,
      show: authStore.isAdmin,
      action: () => openArchiveModal(lead),
    },
  ];
};

const exportCsv = async () => {
  exporting.value = true;
  try {
    const params: any = {};
    if (filters.status) params.status = filters.status;
    if (filters.source) params.source = filters.source;
    if (filters.assigned_to) params.assigned_to = filters.assigned_to;
    if (viewArchived.value) params.archived = 1;

    const response = await api.get('/leads/export', {
      params,
      responseType: 'blob',
    });

    const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `vistabound_${viewArchived.value ? 'archived_' : ''}leads_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  } catch (err) {
    console.error('Export failed', err);
  } finally {
    exporting.value = false;
  }
};

const formatDateTime = (dtStr: string) => {
  if (!dtStr) return '';
  const d = new Date(dtStr);
  return d.toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const isOverdue = (dtStr: string) => {
  if (!dtStr) return false;
  return new Date(dtStr).getTime() < Date.now();
};

const fetchInitialData = async () => {
  try {
    const settingsRes = await api.get('/settings');
    leadSources.value = (settingsRes.data.lead_sources || []).map((s: any) => s.label);
    if (authStore.isAdmin) {
      const empRes = await api.get('/employees');
      employeesList.value = empRes.data;
    }
  } catch {
    // quiet fallback
  }
};

onMounted(() => {
  fetchInitialData();
  fetchLeads();
});
</script>
