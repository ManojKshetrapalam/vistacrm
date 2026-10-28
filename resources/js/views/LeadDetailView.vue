<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Loading State -->
      <div v-if="loading" class="py-20 flex justify-center items-center">
        <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
      </div>

      <div v-else-if="!lead" class="card-glass p-12 text-center">
        <h3 class="font-display font-bold text-lg text-vb-plum">Lead Not Found</h3>
        <p class="text-xs text-vb-muted mt-1">This lead may have been deleted or you do not have permission to view it.</p>
        <router-link to="/leads" class="btn-primary inline-flex px-5 py-2 text-xs rounded-full mt-4 font-semibold">
          Return to Leads
        </router-link>
      </div>

      <div v-else class="space-y-6">
        <!-- Breadcrumb & Back -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2 text-xs text-vb-muted">
            <router-link to="/leads" class="hover:text-vb-plum">Leads</router-link>
            <span>/</span>
            <span class="text-vb-plum font-semibold">{{ lead.reference_id }}</span>
          </div>

          <router-link to="/leads" class="text-xs font-semibold text-vb-violet hover:underline flex items-center gap-1">
            <ArrowLeft class="w-3.5 h-3.5" />
            <span>Back to pipeline</span>
          </router-link>
        </div>

        <!-- Archived Banner -->
        <div v-if="lead.is_archived" class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
              <Archive class="w-5 h-5" />
            </div>
            <div>
              <h4 class="font-bold text-xs">This lead is currently archived</h4>
              <p class="text-[11px] text-amber-800">
                Hidden from active pipeline views and daily follow-up queues. All past timeline activities, notes, and records are preserved.
              </p>
            </div>
          </div>
          <button
            v-if="authStore.isAdmin"
            type="button"
            @click="restoreModalOpen = true"
            class="px-4 py-1.5 rounded-full bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs flex items-center gap-1.5 shrink-0 self-start sm:self-auto shadow-sm"
          >
            <RotateCcw class="w-3.5 h-3.5" />
            <span>Restore to Pipeline</span>
          </button>
        </div>

        <!-- Lead Banner Card -->
        <div class="card-glass p-6 sm:p-8 bg-gradient-to-r from-white via-white to-vb-cream/50 flex flex-col lg:flex-row lg:items-center justify-between gap-6" :class="lead.is_archived ? 'opacity-85' : ''">
          <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2.5">
              <span class="font-mono text-xs font-bold text-vb-violet bg-vb-violet/10 px-2.5 py-0.5 rounded-md">
                {{ lead.reference_id }}
              </span>
              <span v-if="lead.is_archived" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-zinc-200 text-zinc-700">
                Archived
              </span>
              <StatusBadge v-else :status="lead.status" />
              <PriorityBadge :priority="lead.priority" />
              <span v-if="lead.source" class="text-[11px] text-vb-muted bg-white border border-vb-plum/10 px-2 py-0.5 rounded-full font-medium">
                {{ lead.source }}
              </span>
            </div>

            <h2 class="text-2xl sm:text-3xl font-display font-bold text-vb-plum">
              {{ lead.name }}
            </h2>
            <p class="text-sm text-vb-muted flex flex-wrap items-center gap-3">
              <span v-if="lead.company_name" class="font-semibold text-vb-ink">{{ lead.company_name }}</span>
              <span v-if="lead.service_interest" class="text-vb-plum">· {{ lead.service_interest }}</span>
            </p>
          </div>

          <!-- Quick Action Buttons & Actions Menu -->
          <div class="flex flex-wrap items-center gap-2.5">
            <!-- WhatsApp Action -->
            <button
              v-if="!lead.is_archived"
              type="button"
              @click="whatsAppModalOpen = true"
              class="px-4 py-2.5 rounded-full text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-md flex items-center gap-2 transition-all"
            >
              <MessageSquare class="w-4 h-4" />
              <span>WhatsApp Connect</span>
            </button>

            <!-- Call Log Action -->
            <button
              v-if="!lead.is_archived"
              type="button"
              @click="callModalOpen = true"
              class="btn-primary px-4 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2 shadow-md"
            >
              <PhoneCall class="w-4 h-4" />
              <span>Log Call</span>
            </button>

            <!-- Schedule Follow-up -->
            <button
              v-if="!lead.is_archived"
              type="button"
              @click="followUpModalOpen = true"
              class="btn-ghost px-4 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2"
            >
              <CalendarClock class="w-4 h-4" />
              <span>Schedule Follow-up</span>
            </button>

            <!-- Actions Dropdown Menu -->
            <ActionMenu
              :items="getDetailActions()"
              title="More Lead Options"
              trigger-class="px-3.5 py-2.5 rounded-full bg-white border border-vb-plum/20 hover:border-vb-plum text-vb-plum text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-colors"
            >
              <template #trigger>
                <MoreVertical class="w-4 h-4" />
                <span class="hidden sm:inline">Options</span>
              </template>
            </ActionMenu>
          </div>
        </div>

        <!-- Main Details Grid -->
        <div class="grid lg:grid-cols-3 gap-6">
          <!-- Left Column: Lead Information & Next Follow-up -->
          <div class="space-y-6">
            <!-- Contact Details Card -->
            <div class="card-glass p-6 space-y-4">
              <h3 class="font-display font-bold text-sm uppercase tracking-wider text-vb-plum border-b border-vb-plum/10 pb-2">
                Client Contact Information
              </h3>

              <div class="space-y-3 text-xs">
                <div>
                  <span class="text-vb-muted text-[11px] block uppercase tracking-wider font-semibold">Phone Number</span>
                  <div class="flex items-center justify-between mt-0.5">
                    <span class="font-bold text-vb-ink text-sm">{{ lead.phone }}</span>
                    <a :href="'tel:' + lead.phone" class="text-vb-violet hover:underline text-[11px] font-semibold">Call Now</a>
                  </div>
                </div>

                <div v-if="lead.email">
                  <span class="text-vb-muted text-[11px] block uppercase tracking-wider font-semibold">Email Address</span>
                  <div class="flex items-center justify-between mt-0.5">
                    <span class="font-medium text-vb-ink">{{ lead.email }}</span>
                    <a :href="'mailto:' + lead.email" class="text-vb-violet hover:underline text-[11px] font-semibold">Send Mail</a>
                  </div>
                </div>

                <div>
                  <span class="text-vb-muted text-[11px] block uppercase tracking-wider font-semibold">Company / Entity</span>
                  <span class="font-medium text-vb-ink mt-0.5 block">{{ lead.company_name || 'Individual / Not specified' }}</span>
                </div>

                <div>
                  <span class="text-vb-muted text-[11px] block uppercase tracking-wider font-semibold">Assigned Solutions Representative</span>
                  <div class="flex items-center justify-between mt-1">
                    <div class="flex items-center gap-2">
                      <div class="w-6 h-6 rounded-full bg-vb-plum text-white text-[10px] font-bold flex items-center justify-center">
                        {{ lead.assigned_employee ? lead.assigned_employee.name[0] : '?' }}
                      </div>
                      <span class="font-semibold text-vb-plum">{{ lead.assigned_employee?.name || 'Unassigned' }}</span>
                    </div>

                    <!-- Admin Reassign Trigger -->
                    <button
                      v-if="authStore.isAdmin"
                      @click="reassignModalOpen = true"
                      class="text-[11px] text-vb-violet hover:underline font-semibold"
                    >
                      Reassign
                    </button>
                  </div>
                </div>

                <div class="pt-2 border-t border-vb-plum/5 grid grid-cols-2 gap-2 text-[11px]">
                  <div>
                    <span class="text-vb-muted block">Created Date</span>
                    <span class="font-medium text-vb-ink">{{ formatDate(lead.created_at) }}</span>
                  </div>
                  <div>
                    <span class="text-vb-muted block">Last Contact</span>
                    <span class="font-medium text-vb-ink">{{ lead.last_contact_date ? formatDate(lead.last_contact_date) : 'Never' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Next Follow-up Card -->
            <div class="card-glass p-6">
              <div class="flex items-center justify-between border-b border-vb-plum/10 pb-2 mb-3">
                <h3 class="font-display font-bold text-sm uppercase tracking-wider text-vb-plum">
                  Next Scheduled Action
                </h3>
                <span
                  v-if="lead.next_follow_up_date && isOverdue(lead.next_follow_up_date)"
                  class="text-[10px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full uppercase"
                >
                  Overdue
                </span>
              </div>

              <div v-if="lead.next_follow_up_date" class="space-y-3">
                <div class="p-3.5 bg-vb-cream/60 rounded-xl border border-vb-plum/10 space-y-1 text-xs">
                  <div class="flex items-center gap-2 text-vb-plum font-bold">
                    <CalendarClock class="w-4 h-4 text-vb-amber" />
                    <span>{{ formatDateTime(lead.next_follow_up_date) }}</span>
                  </div>
                  <p class="text-vb-muted text-[11px]">
                    Ensure timely check-in and log the client outcome.
                  </p>
                </div>

                <div class="flex items-center gap-2">
                  <button
                    @click="callModalOpen = true"
                    class="w-full btn-primary py-2 text-xs font-semibold rounded-full flex items-center justify-center gap-1.5"
                  >
                    <Check class="w-3.5 h-3.5" />
                    <span>Complete / Log Outcome</span>
                  </button>
                  <button
                    @click="followUpModalOpen = true"
                    class="px-3 py-2 text-xs font-semibold text-vb-plum hover:bg-vb-cream rounded-full border border-vb-plum/20"
                  >
                    Reschedule
                  </button>
                </div>
              </div>

              <div v-else class="text-center py-4 space-y-2">
                <p class="text-xs text-vb-muted italic">No upcoming follow-up scheduled.</p>
                <button
                  @click="followUpModalOpen = true"
                  class="btn-ghost text-xs font-semibold px-4 py-1.5 rounded-full inline-flex items-center gap-1.5"
                >
                  <Plus class="w-3.5 h-3.5" />
                  <span>Set Follow-up Date</span>
                </button>
              </div>
            </div>

            <!-- Additional Details / Scope Card -->
            <div class="card-glass p-6 space-y-3">
              <h3 class="font-display font-bold text-sm uppercase tracking-wider text-vb-plum border-b border-vb-plum/10 pb-2">
                Scope &amp; Inquiry Details
              </h3>
              <p class="text-xs text-vb-ink leading-relaxed whitespace-pre-line bg-white/70 p-3 rounded-xl border border-vb-plum/10">
                {{ lead.additional_details || 'No additional scope notes entered for this inquiry.' }}
              </p>
            </div>
          </div>

          <!-- Right Column: Activity History & Timeline -->
          <div class="lg:col-span-2 space-y-6">
            <div class="card-glass p-6">
              <div class="flex items-center justify-between mb-4 pb-2 border-b border-vb-plum/10">
                <div>
                  <h3 class="font-display font-bold text-base text-vb-plum">Activity Stream &amp; Engagement History</h3>
                  <p class="text-xs text-vb-muted">Chronological timeline of all calls, messages, notes, and status changes</p>
                </div>
              </div>

              <!-- Quick Add Note Input -->
              <div class="p-3.5 bg-vb-cream/50 rounded-2xl border border-vb-plum/10 mb-6">
                <textarea
                  v-model="newNoteText"
                  rows="2"
                  class="field text-xs resize-none bg-white font-sans"
                  placeholder="Add internal note or update for this account..."
                ></textarea>
                <div class="mt-2 flex justify-end">
                  <button
                    type="button"
                    @click="handleAddNote"
                    :disabled="savingNote || !newNoteText.trim()"
                    class="btn-primary text-xs font-semibold px-4 py-1.5 rounded-full flex items-center gap-1.5"
                  >
                    <span>{{ savingNote ? 'Saving...' : 'Post Internal Note' }}</span>
                  </button>
                </div>
              </div>

              <!-- Timeline Feed -->
              <div class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-vb-plum/10">
                <div
                  v-for="act in lead.activities"
                  :key="act.id"
                  class="relative flex items-start gap-4 text-xs group"
                >
                  <!-- Timeline Icon Bullet -->
                  <div
                    class="absolute -left-6 mt-1 w-5 h-5 rounded-full flex items-center justify-center text-white ring-4 ring-white shadow-sm"
                    :class="getActivityIconBg(act.activity_type)"
                  >
                    <component :is="getActivityIcon(act.activity_type)" class="w-2.5 h-2.5" />
                  </div>

                  <!-- Activity Box -->
                  <div class="flex-1 bg-white p-4 rounded-2xl border border-vb-plum/10 shadow-sm space-y-1.5">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <span class="font-bold text-vb-plum font-display">{{ act.title }}</span>
                        <span class="text-[10px] text-vb-muted font-medium">by {{ act.user?.name || 'System' }}</span>
                      </div>
                      <span class="text-[10px] text-vb-muted font-medium">{{ formatDateTime(act.created_at) }}</span>
                    </div>

                    <p v-if="act.description" class="text-vb-ink text-xs whitespace-pre-line leading-relaxed">
                      {{ act.description }}
                    </p>

                    <!-- Call Outcome Tags -->
                    <div v-if="act.activity_type === 'call_outcome' && act.metadata?.outcome" class="pt-1 flex items-center gap-2 text-[11px]">
                      <span class="font-semibold text-vb-plum bg-vb-plum/5 px-2 py-0.5 rounded-md">
                        Outcome: {{ act.metadata.outcome }}
                      </span>
                      <span v-if="act.metadata.duration_minutes" class="text-vb-muted">
                        Duration: {{ act.metadata.duration_minutes }}m
                      </span>
                    </div>
                  </div>
                </div>

                <div v-if="!lead.activities || lead.activities.length === 0" class="text-center py-6 text-vb-muted text-xs italic">
                  No activity history logged yet.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <WhatsAppModal
      :is-open="whatsAppModalOpen"
      :lead="lead"
      @close="whatsAppModalOpen = false"
      @dispatched="fetchLead"
    />

    <LogCallModal
      :is-open="callModalOpen"
      :lead="lead"
      @close="callModalOpen = false"
      @saved="fetchLead"
    />

    <ScheduleFollowUpModal
      :is-open="followUpModalOpen"
      :lead="lead"
      @close="followUpModalOpen = false"
      @saved="fetchLead"
    />

    <EditLeadModal
      :is-open="editModalOpen"
      :lead="lead"
      @close="editModalOpen = false"
      @saved="fetchLead"
    />

    <!-- Archive Confirmation Modal -->
    <ConfirmModal
      :is-open="archiveModalOpen"
      title="Archive Lead"
      :subtitle="`${lead?.reference_id} · ${lead?.name}`"
      :message="`Are you sure you want to archive lead ${lead?.reference_id}?`"
      consequences="Archiving will remove this lead from active pipeline views, assignment lists, and daily follow-up queues. All past timeline activities, notes, and records are preserved. You can restore this lead at any time."
      confirm-text="Archive Lead"
      variant="danger"
      :is-submitting="actionSubmitting"
      :error="actionError"
      @confirm="executeArchiveLead"
      @cancel="archiveModalOpen = false"
    />

    <!-- Restore Confirmation Modal -->
    <ConfirmModal
      :is-open="restoreModalOpen"
      title="Restore Lead to Pipeline"
      :subtitle="`${lead?.reference_id} · ${lead?.name}`"
      :message="`Restore lead ${lead?.reference_id} (${lead?.name}) to the active pipeline?`"
      consequences="The lead will reappear in active pipeline lists and remain assigned to its designated solutions representative without duplicating past reminders or activities."
      confirm-text="Restore Lead"
      variant="warning"
      :is-submitting="actionSubmitting"
      :error="actionError"
      @confirm="executeRestoreLead"
      @cancel="restoreModalOpen = false"
    />

    <!-- Permanent Delete Modal -->
    <ConfirmModal
      :is-open="forceDeleteModalOpen"
      title="Permanently Delete Lead Record"
      :subtitle="`${lead?.reference_id} · ${lead?.name}`"
      :message="`This action will permanently delete ${lead?.reference_id} and all related activity history from the database.`"
      consequences="This action cannot be undone. All audit history, follow-up logs, and engagement records for this lead will be permanently deleted from the database."
      confirm-text="Permanently Delete Record"
      variant="danger"
      :required-confirmation-text="lead?.reference_id"
      :is-submitting="actionSubmitting"
      :error="actionError"
      @confirm="executeForceDeleteLead"
      @cancel="forceDeleteModalOpen = false"
    />

    <!-- Admin Reassign Modal -->
    <div v-if="reassignModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 space-y-4 border border-vb-plum/10">
        <h3 class="font-display font-bold text-base text-vb-plum">Reassign Lead</h3>
        <p class="text-xs text-vb-muted">Select an employee from the solutions desk to manage this account.</p>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Select Representative
          </label>
          <select v-model="selectedAssigneeId" class="field text-sm">
            <option value="">Unassigned</option>
            <option v-for="emp in employeesList" :key="emp.id" :value="emp.id">
              {{ emp.name }} ({{ emp.email }})
            </option>
          </select>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button @click="reassignModalOpen = false" class="px-4 py-2 text-xs font-semibold text-vb-plum">
            Cancel
          </button>
          <button @click="handleReassign" :disabled="reassigning" class="btn-primary px-5 py-2 text-xs rounded-full font-semibold">
            {{ reassigning ? 'Assigning...' : 'Confirm Assignment' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import { syncEngine, isOnline } from '../services/syncEngine';
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
  MessageSquare,
  PhoneCall,
  CalendarClock,
  ArrowLeft,
  Check,
  Plus,
  FileText,
  UserCheck,
  RefreshCw,
  Send,
  Sparkles,
  Archive,
  RotateCcw,
  Trash2,
  Edit,
  MoreVertical,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const lead = ref<any>(null);
const loading = ref(true);
const employeesList = ref<any[]>([]);

const whatsAppModalOpen = ref(false);
const callModalOpen = ref(false);
const followUpModalOpen = ref(false);
const reassignModalOpen = ref(false);
const editModalOpen = ref(false);
const archiveModalOpen = ref(false);
const restoreModalOpen = ref(false);
const forceDeleteModalOpen = ref(false);

const actionSubmitting = ref(false);
const actionError = ref('');
const selectedAssigneeId = ref<any>('');
const reassigning = ref(false);

const newNoteText = ref('');
const savingNote = ref(false);

const fetchLead = async () => {
  loading.value = true;
  try {
    if (isOnline.value) {
      const res = await api.get(`/leads/${route.params.id}`);
      lead.value = res.data;
      selectedAssigneeId.value = lead.value.assigned_to || '';
      // Cache lead for offline access
      await offlineStorage.saveLead(res.data);
    } else {
      // Load from offline persistent storage
      const cached = await offlineStorage.getLead(Number(route.params.id));
      if (cached) {
        lead.value = cached;
        selectedAssigneeId.value = cached.assigned_to || '';
      }
    }
  } catch (err) {
    console.warn('Network fetch failed, attempting to read from offline cache', err);
    const cached = await offlineStorage.getLead(Number(route.params.id));
    if (cached) {
      lead.value = cached;
      selectedAssigneeId.value = cached.assigned_to || '';
    }
  } finally {
    loading.value = false;
  }
};

const fetchSettings = async () => {
  try {
    const res = await api.get('/settings');
    employeesList.value = res.data.employees || [];
  } catch (err) {
    // quiet
  }
};

const handleAddNote = async () => {
  if (!newNoteText.value.trim() || !lead.value) return;
  savingNote.value = true;
  const noteContent = newNoteText.value.trim();
  try {
    if (isOnline.value) {
      await api.post(`/leads/${lead.value.id}/note`, {
        note: noteContent,
      });
      newNoteText.value = '';
      await fetchLead();
    } else {
      await syncEngine.enqueueMutation('create_note', lead.value.id, {
        note: noteContent,
      }, lead.value.version || 1);
      newNoteText.value = '';
      const updated = await offlineStorage.getLead(lead.value.id);
      if (updated) lead.value = updated;
    }
  } catch (err) {
    // If online request fails, fallback to queueing offline
    try {
      await syncEngine.enqueueMutation('create_note', lead.value.id, {
        note: noteContent,
      }, lead.value.version || 1);
      newNoteText.value = '';
      const updated = await offlineStorage.getLead(lead.value.id);
      if (updated) lead.value = updated;
    } catch (e) {
      console.error('Failed to post note', e);
    }
  } finally {
    savingNote.value = false;
  }
};

const getDetailActions = (): ActionMenuItem[] => {
  if (lead.value?.is_archived) {
    return [
      {
        label: 'Restore to Active Pipeline',
        icon: RotateCcw,
        warning: true,
        show: authStore.isAdmin,
        action: () => {
          actionError.value = '';
          restoreModalOpen.value = true;
        },
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
        action: () => {
          actionError.value = '';
          forceDeleteModalOpen.value = true;
        },
      },
    ];
  }

  return [
    {
      label: 'Edit Lead Details',
      icon: Edit,
      action: () => {
        editModalOpen.value = true;
      },
    },
    {
      label: 'Reassign Representative',
      icon: UserCheck,
      show: authStore.isAdmin,
      action: () => {
        reassignModalOpen.value = true;
      },
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
      action: () => {
        actionError.value = '';
        archiveModalOpen.value = true;
      },
    },
  ];
};

const executeArchiveLead = async () => {
  if (!lead.value) return;
  actionSubmitting.value = true;
  actionError.value = '';

  try {
    await api.delete(`/leads/${lead.value.id}`);
    archiveModalOpen.value = false;
    await fetchLead();
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Failed to archive lead.';
  } finally {
    actionSubmitting.value = false;
  }
};

const executeRestoreLead = async () => {
  if (!lead.value) return;
  actionSubmitting.value = true;
  actionError.value = '';

  try {
    await api.post(`/leads/${lead.value.id}/restore`);
    restoreModalOpen.value = false;
    await fetchLead();
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Failed to restore lead.';
  } finally {
    actionSubmitting.value = false;
  }
};

const executeForceDeleteLead = async () => {
  if (!lead.value) return;
  actionSubmitting.value = true;
  actionError.value = '';

  try {
    await api.delete(`/leads/${lead.value.id}/force`);
    forceDeleteModalOpen.value = false;
    router.push('/leads');
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Failed to permanently delete lead record.';
  } finally {
    actionSubmitting.value = false;
  }
};

const handleReassign = async () => {
  if (!lead.value) return;
  reassigning.value = true;
  try {
    await api.post(`/leads/${lead.value.id}/assign`, {
      assigned_to: selectedAssigneeId.value || null,
    });
    reassignModalOpen.value = false;
    await fetchLead();
  } catch (err) {
    console.error('Failed to reassign lead', err);
  } finally {
    reassigning.value = false;
  }
};

const getActivityIcon = (type: string) => {
  switch (type) {
    case 'call_outcome': return PhoneCall;
    case 'whatsapp_sent': return Send;
    case 'follow_up_scheduled': return CalendarClock;
    case 'follow_up_completed': return Check;
    case 'assignment_change': return UserCheck;
    case 'status_change': return RefreshCw;
    case 'note': return FileText;
    default: return Sparkles;
  }
};

const getActivityIconBg = (type: string) => {
  switch (type) {
    case 'call_outcome': return 'bg-vb-plum';
    case 'whatsapp_sent': return 'bg-emerald-600';
    case 'follow_up_scheduled': return 'bg-vb-amber';
    case 'follow_up_completed': return 'bg-vb-teal';
    case 'assignment_change': return 'bg-vb-violet';
    case 'status_change': return 'bg-vb-cyan';
    case 'note': return 'bg-slate-600';
    default: return 'bg-vb-plum';
  }
};

const isOverdue = (dateStr: string) => {
  if (!dateStr) return false;
  return new Date(dateStr) < new Date();
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
};

const formatDateTime = (dateStr: string) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

onMounted(() => {
  fetchLead();
  fetchSettings();
});
</script>
