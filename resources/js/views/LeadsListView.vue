<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Top Title & Primary Actions Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-display font-bold text-white">Lead Pipeline</h2>
          <p class="text-xs text-purple-200/70 mt-0.5">
            {{ viewArchived ? 'Archived leads repository. Records can be restored or inspected with full historical audit trails.' : 'Track client engagement, assignment statuses, and scheduled consultation deadlines.' }}
          </p>
        </div>

        <div class="flex items-center gap-2.5">
          <button
            @click="exportCsv"
            :disabled="exporting || leads.length === 0"
            class="btn-ghost px-4 py-2 rounded-full text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs disabled:opacity-40"
          >
            <Download class="w-3.5 h-3.5 text-vb-cyan" />
            <span>{{ exporting ? 'Exporting...' : 'Export Pipeline CSV' }}</span>
          </button>

          <router-link
            to="/leads/new"
            class="btn-primary text-xs font-semibold px-4.5 py-2 rounded-full flex items-center gap-1.5 shadow-md shadow-vb-magenta/25"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Add Lead</span>
          </router-link>
        </div>
      </div>

      <!-- Admin Tabs: Active Pipeline vs Archived Leads -->
      <div v-if="authStore.isAdmin" class="flex items-center gap-2 border-b border-white/10 pb-3 text-xs font-semibold">
        <button
          type="button"
          @click="setArchivedMode(false)"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="!viewArchived ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-md shadow-vb-magenta/30 border border-white/20' : 'text-purple-200/70 hover:text-white hover:bg-white/10'"
        >
          <span>Active Pipeline</span>
        </button>

        <button
          type="button"
          @click="setArchivedMode(true)"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="viewArchived ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-md shadow-vb-magenta/30 border border-white/20' : 'text-purple-200/70 hover:text-white hover:bg-white/10'"
        >
          <Archive class="w-3.5 h-3.5" />
          <span>Archived Leads</span>
          <span
            v-if="archivedCount > 0"
            class="px-1.5 py-0.2 rounded-full text-[10px]"
            :class="viewArchived ? 'bg-white/20 text-white' : 'bg-white/10 text-purple-200'"
          >
            {{ archivedCount }}
          </span>
        </button>
      </div>

      <!-- Filters Card -->
      <div class="glass-surface p-5 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
          <!-- Search -->
          <div class="lg:col-span-2 relative">
            <Search class="w-4 h-4 text-purple-300 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="filters.search"
              @input="debounceSearch"
              type="text"
              placeholder="Search by name, phone, company, reference..."
              class="field pl-9 text-xs"
            />
          </div>

          <!-- Status Filter -->
          <div v-if="!viewArchived">
            <select v-model="filters.status" @change="fetchLeads" class="field text-xs">
              <option value="">All Statuses</option>
              <option value="new">New Inbound</option>
              <option value="assigned">Assigned</option>
              <option value="contacted">Contacted</option>
              <option value="follow_up">In Follow-up</option>
              <option value="interested">Qualified Interest</option>
              <option value="converted">Converted Deal</option>
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
        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-white/10 text-xs">
          <div class="flex flex-wrap items-center gap-3">
            <!-- Source Filter -->
            <select v-model="filters.source" @change="fetchLeads" class="field text-xs py-1.5 max-w-[200px]">
              <option value="">All Lead Sources</option>
              <option v-for="src in leadSources" :key="src" :value="src">{{ src }}</option>
            </select>

            <!-- Employee Filter (Admin & Team Lead) -->
            <select
              v-if="authStore.isAdmin || authStore.isTeamLead"
              v-model="filters.assigned_to"
              @change="fetchLeads"
              class="field text-xs py-1.5 max-w-[220px]"
            >
              <option value="">{{ authStore.isAdmin ? 'All Employees' : 'All Team Members' }}</option>
              <option v-if="authStore.isAdmin" value="unassigned">Unassigned Only</option>
              <option v-for="emp in employeesList" :key="emp.id" :value="emp.id">
                {{ emp.name }}
              </option>
            </select>
          </div>

          <button
            v-if="hasActiveFilters"
            @click="resetFilters"
            class="text-xs text-vb-cyan hover:underline font-semibold"
          >
            Clear all filters
          </button>
        </div>
      </div>

      <!-- Leads Table Container (High-readability solid surface) -->
      <div class="surface-solid overflow-hidden shadow-lg border border-white/10">
        <div v-if="loading" class="py-20 flex justify-center items-center">
          <div class="w-8 h-8 border-3 border-vb-magenta/30 border-t-vb-magenta rounded-full animate-spin"></div>
        </div>

        <div v-else-if="leads.length === 0" class="py-16 text-center">
          <Users class="w-12 h-12 text-purple-300/40 mx-auto mb-3" />
          <h3 class="font-display font-bold text-base text-white">
            {{ viewArchived ? 'No archived leads found' : 'No matching leads found' }}
          </h3>
          <p class="text-xs text-purple-200/70 mt-1 max-w-sm mx-auto">
            {{ viewArchived ? 'No records have been archived. Archived leads will appear here for safe restoration or review.' : 'Try adjusting your search criteria or create a new lead.' }}
          </p>
          <div v-if="hasActiveFilters" class="mt-4 flex items-center justify-center gap-2">
            <button @click="resetFilters" class="px-4 py-1.5 text-xs border border-white/20 rounded-full font-semibold text-white hover:bg-white/10 shadow-sm">
              Reset Filters
            </button>
          </div>
        </div>

        <div v-else>
          <!-- Desktop Table View (>= lg) -->
          <div class="hidden lg:block overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-white/[0.04] border-b border-white/10 text-purple-200 font-bold uppercase tracking-wider text-[11px]">
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
              <tbody class="divide-y divide-white/5">
                <tr
                  v-for="lead in leads"
                  :key="lead.id"
                  class="hover:bg-white/[0.04] transition-colors group"
                  :class="viewArchived ? 'opacity-85' : ''"
                >
                  <!-- Reference ID -->
                  <td class="py-3.5 px-4 font-mono font-bold text-vb-magenta">
                    <router-link :to="`/leads/${lead.id}`" class="hover:underline">
                      {{ lead.reference_id }}
                    </router-link>
                  </td>

                  <!-- Contact Info -->
                  <td class="py-3.5 px-4">
                    <router-link :to="`/leads/${lead.id}`" class="font-bold text-white block hover:text-vb-cyan transition-colors">
                      {{ lead.name }}
                    </router-link>
                    <span class="text-purple-200/60 font-medium text-[11px] block mt-0.5">
                      {{ lead.phone }}
                    </span>
                  </td>

                  <!-- Company & Service Interest -->
                  <td class="py-3.5 px-4">
                    <span class="font-semibold text-white/90 block truncate max-w-[180px]">
                      {{ lead.company_name || 'Individual / Direct' }}
                    </span>
                    <span class="text-[11px] text-purple-200/70 block mt-0.5 truncate max-w-[180px]">
                      {{ lead.service_interest || 'General Consultation' }}
                    </span>
                  </td>

                  <!-- Status Badge -->
                  <td class="py-3.5 px-4">
                    <span v-if="viewArchived" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/10 text-white border border-white/20">
                      Archived
                    </span>
                    <StatusBadge v-else :status="lead.status" />
                  </td>

                  <!-- Priority Badge -->
                  <td class="py-3.5 px-4">
                    <PriorityBadge :priority="lead.priority" />
                  </td>

                  <!-- Assigned Representative -->
                  <td class="py-3.5 px-4">
                    <div v-if="lead.assigned_employee" class="flex items-center gap-2">
                      <div class="w-6 h-6 rounded-full bg-gradient-to-br from-vb-magenta to-vb-plum text-white text-[10px] font-bold flex items-center justify-center shrink-0 border border-white/20 shadow-xs">
                        {{ lead.assigned_employee.name[0] }}
                      </div>
                      <span class="text-white/90 font-semibold truncate max-w-[120px]">
                        {{ lead.assigned_employee.name }}
                      </span>
                    </div>
                    <span v-else class="text-purple-200/50 text-[11px] italic">Unassigned</span>
                  </td>

                  <!-- Next Follow-up or Archived Date -->
                  <td class="py-3.5 px-4">
                    <span v-if="viewArchived" class="text-[11px] text-purple-200/70 font-mono">
                      {{ formatDateTime(lead.deleted_at) }}
                    </span>
                    <div v-else-if="lead.next_follow_up_date">
                      <span
                        class="font-semibold text-[11px] block"
                        :class="isOverdue(lead.next_follow_up_date) ? 'text-rose-400 font-bold' : 'text-purple-100'"
                      >
                        {{ formatDateTime(lead.next_follow_up_date) }}
                      </span>
                    </div>
                    <span v-else class="text-[11px] text-purple-200/50 italic">None scheduled</span>
                  </td>

                  <!-- Row Actions -->
                  <td class="py-3.5 px-4 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                      <ActionMenu :items="getLeadActions(lead)" />
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile Cards View (< lg) -->
          <div class="lg:hidden divide-y divide-white/10">
            <div
              v-for="lead in leads"
              :key="lead.id"
              class="p-4 hover:bg-white/[0.03] transition-colors space-y-3"
              :class="viewArchived ? 'opacity-85' : ''"
            >
              <!-- Card Top Row: Ref ID + Badges -->
              <div class="flex items-center justify-between gap-2">
                <router-link
                  :to="`/leads/${lead.id}`"
                  class="font-mono text-xs font-bold text-vb-magenta bg-vb-magenta/20 border border-vb-magenta/30 px-2 py-0.5 rounded-md hover:underline"
                >
                  {{ lead.reference_id }}
                </router-link>

                <div class="flex items-center gap-1.5">
                  <span v-if="viewArchived" class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-white/10 text-white border border-white/20">
                    Archived
                  </span>
                  <StatusBadge v-else :status="lead.status" />
                  <PriorityBadge :priority="lead.priority" />
                </div>
              </div>

              <!-- Card Middle: Name, Company, Service -->
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <router-link
                    :to="`/leads/${lead.id}`"
                    class="font-bold text-sm text-white hover:text-vb-cyan transition-colors block truncate"
                  >
                    {{ lead.name }}
                  </router-link>
                  <p class="text-xs text-purple-200/70 truncate mt-0.5">
                    {{ lead.company_name || 'Individual / Direct' }}
                    <span v-if="lead.service_interest" class="text-vb-cyan"> · {{ lead.service_interest }}</span>
                  </p>
                </div>

                <router-link
                  :to="`/leads/${lead.id}`"
                  class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-purple-200 hover:text-white transition-colors shrink-0"
                  title="View Lead Details"
                >
                  <ChevronRight class="w-4 h-4" />
                </router-link>
              </div>

              <!-- Card Bottom: Phone, Rep, Follow-up & Quick Actions -->
              <div class="pt-2 border-t border-white/5 flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-2">
                  <a
                    :href="'tel:' + lead.phone"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 font-semibold text-[11px] hover:bg-emerald-500/25 transition-colors"
                  >
                    <Phone class="w-3 h-3" />
                    <span>{{ lead.phone }}</span>
                  </a>

                  <div v-if="lead.assigned_employee" class="flex items-center gap-1.5 text-purple-200/80 text-[11px]">
                    <div class="w-5 h-5 rounded-full bg-gradient-to-br from-vb-magenta to-vb-plum text-white text-[9px] font-bold flex items-center justify-center shrink-0 border border-white/20">
                      {{ lead.assigned_employee.name[0] }}
                    </div>
                    <span class="truncate max-w-[100px]">{{ lead.assigned_employee.name }}</span>
                  </div>
                </div>

                <div class="flex items-center gap-2">
                  <div v-if="!viewArchived && lead.next_follow_up_date" class="text-[10px]">
                    <span
                      class="px-2 py-0.5 rounded-full font-semibold border"
                      :class="isOverdue(lead.next_follow_up_date) ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' : 'bg-white/10 text-purple-200 border-white/15'"
                    >
                      {{ formatDateTime(lead.next_follow_up_date) }}
                    </span>
                  </div>

                  <ActionMenu :items="getLeadActions(lead)" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination Bar -->
        <div v-if="pagination.total > pagination.per_page" class="p-4 border-t border-white/10 bg-white/[0.02] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
          <span class="text-purple-200/70 text-center sm:text-left">
            Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} to
            {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of
            <strong class="text-white">{{ pagination.total }}</strong> leads
          </span>

          <div class="flex items-center gap-1.5">
            <button
              :disabled="pagination.current_page <= 1"
              @click="changePage(pagination.current_page - 1)"
              class="px-3 py-1.5 rounded-full border border-white/15 bg-white/10 text-white hover:bg-white/20 disabled:opacity-40 transition-colors cursor-pointer"
            >
              Previous
            </button>
            <span class="px-2 font-mono font-bold text-white">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button
              :disabled="pagination.current_page >= pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
              class="px-3 py-1.5 rounded-full border border-white/15 bg-white/10 text-white hover:bg-white/20 disabled:opacity-40 transition-colors cursor-pointer"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <EditLeadModal
      :is-open="editModalOpen"
      :lead="activeLead"
      @close="editModalOpen = false"
      @saved="handleLeadEdited"
    />

    <ScheduleFollowUpModal
      :is-open="followUpModalOpen"
      :lead="activeLead"
      @close="followUpModalOpen = false"
      @saved="handleLeadEdited"
    />

    <ConfirmModal
      :is-open="archiveModalOpen"
      variant="warning"
      title="Archive Lead Record"
      :subtitle="`${activeLead?.reference_id} — ${activeLead?.name}`"
      message="Are you sure you want to archive this lead? The lead will be hidden from the active pipeline and scheduled queues. All historical call logs, notes, and activity timeline will remain safely preserved in the Archived Leads repository."
      confirm-text="Archive Lead"
      :is-submitting="actionSubmitting"
      :error="actionError"
      @confirm="executeArchiveLead"
      @cancel="archiveModalOpen = false"
    />

    <ConfirmModal
      :is-open="restoreModalOpen"
      variant="primary"
      title="Restore Lead to Pipeline"
      :subtitle="`${activeLead?.reference_id} — ${activeLead?.name}`"
      message="Restoring this lead will return it to active sales operations, enabling daily follow-up reminders and representative reassignment."
      confirm-text="Restore to Pipeline"
      :is-submitting="actionSubmitting"
      :error="actionError"
      @confirm="executeRestoreLead"
      @cancel="restoreModalOpen = false"
    />

    <ConfirmModal
      :is-open="forceDeleteModalOpen"
      variant="danger"
      title="Permanently Delete Lead Record"
      :subtitle="`${activeLead?.reference_id} — ${activeLead?.name}`"
      message="WARNING: This operation is permanent and irreversible. All associated activities, follow-ups, and notifications will be permanently purged from the database."
      :required-confirmation-text="activeLead?.reference_id"
      confirm-text="Permanently Delete"
      submitting-text="Purging..."
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
import AppLayout from '../components/AppLayout.vue';
import StatusBadge from '../components/StatusBadge.vue';
import PriorityBadge from '../components/PriorityBadge.vue';
import ActionMenu, { ActionMenuItem } from '../components/ActionMenu.vue';
import EditLeadModal from '../components/EditLeadModal.vue';
import ScheduleFollowUpModal from '../components/ScheduleFollowUpModal.vue';
import ConfirmModal from '../components/ConfirmModal.vue';
import {
  Users,
  Search,
  Plus,
  Download,
  Archive,
  Eye,
  Edit,
  CalendarClock,
  RotateCcw,
  Trash2,
  Phone,
  ChevronRight,
} from 'lucide-vue-next';

const authStore = useAuthStore();

const leads = ref<any[]>([]);
const loading = ref(true);
const exporting = ref(false);
const viewArchived = ref(false);
const archivedCount = ref(0);

const leadSources = ref<string[]>([]);
const employeesList = ref<any[]>([]);

const filters = reactive({
  search: '',
  status: '',
  priority: '',
  source: '',
  assigned_to: '',
  followup_filter: '',
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

const activeLead = ref<any>(null);
const editModalOpen = ref(false);
const followUpModalOpen = ref(false);
const archiveModalOpen = ref(false);
const restoreModalOpen = ref(false);
const forceDeleteModalOpen = ref(false);

const actionSubmitting = ref(false);
const actionError = ref('');

let searchTimer: any = null;
const debounceSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    pagination.current_page = 1;
    fetchLeads();
  }, 300);
};

const hasActiveFilters = computed(() => {
  return !!(
    filters.search ||
    filters.status ||
    filters.priority ||
    filters.source ||
    filters.assigned_to ||
    filters.followup_filter
  );
});

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

const setArchivedMode = (archived: boolean) => {
  viewArchived.value = archived;
  pagination.current_page = 1;
  fetchLeads();
};

const fetchLeads = async () => {
  loading.value = true;
  try {
    const params: any = {
      page: pagination.current_page,
    };

    if (filters.search) params.search = filters.search;
    if (filters.status) params.status = filters.status;
    if (filters.priority) params.priority = filters.priority;
    if (filters.source) params.source = filters.source;
    if (filters.assigned_to) params.assigned_to = filters.assigned_to;
    if (filters.followup_filter) params.followup_filter = filters.followup_filter;
    if (viewArchived.value) params.archived = 1;

    const res = await api.get('/leads', { params });
    leads.value = res.data.data || [];
    pagination.current_page = res.data.current_page || 1;
    pagination.last_page = res.data.last_page || 1;
    pagination.per_page = res.data.per_page || 15;
    pagination.total = res.data.total || 0;
    archivedCount.value = res.data.archived_count || 0;
  } catch (err) {
    console.error('Failed to load leads', err);
  } finally {
    loading.value = false;
  }
};

const changePage = (page: number) => {
  pagination.current_page = page;
  fetchLeads();
};

const openEditModal = (lead: any) => {
  activeLead.value = lead;
  editModalOpen.value = true;
};

const openFollowUpModal = (lead: any) => {
  activeLead.value = lead;
  followUpModalOpen.value = true;
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
    leadSources.value = (settingsRes.data.lead_sources || []).map((s: any) => typeof s === 'string' ? s : (s.label || s));
    if (authStore.isAdmin) {
      const empRes = await api.get('/employees');
      employeesList.value = empRes.data;
    } else if (authStore.isTeamLead) {
      employeesList.value = settingsRes.data.employees || [];
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
