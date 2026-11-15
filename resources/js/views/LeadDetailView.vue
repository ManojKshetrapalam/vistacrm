<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Loading State -->
      <div v-if="loading" class="py-20 flex justify-center items-center">
        <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
      </div>

      <div v-else-if="!lead" class="glass-surface p-12 text-center">
        <h3 class="font-display font-bold text-lg text-white">Lead Not Found</h3>
        <p class="text-xs text-purple-200/70 mt-1">This lead may have been deleted or you do not have permission to view it.</p>
        <router-link to="/leads" class="btn-primary inline-flex px-5 py-2 text-xs rounded-full mt-4 font-semibold shadow-sm">
          Return to Leads
        </router-link>
      </div>

      <div v-else class="space-y-6">
        <!-- Breadcrumb & Back -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2 text-xs text-purple-200/60">
            <router-link to="/leads" class="hover:text-white font-medium">Leads</router-link>
            <span>/</span>
            <span class="text-white font-bold font-mono">{{ lead.reference_id }}</span>
          </div>

          <router-link to="/leads" class="text-xs font-semibold text-vb-cyan hover:underline flex items-center gap-1">
            <ArrowLeft class="w-3.5 h-3.5" />
            <span>Back to pipeline</span>
          </router-link>
        </div>

        <!-- Archived Banner -->
        <div v-if="lead.is_archived" class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm backdrop-blur-sm">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-300 flex items-center justify-center shrink-0 border border-amber-500/30">
              <Archive class="w-5 h-5" />
            </div>
            <div>
              <h4 class="font-bold text-xs text-white">This lead is currently archived</h4>
              <p class="text-[11px] text-amber-200/80">
                Hidden from active pipeline views and daily follow-up queues. All past timeline activities, notes, and records are preserved.
              </p>
            </div>
          </div>
          <button
            v-if="authStore.isAdmin"
            type="button"
            @click="restoreModalOpen = true"
            class="px-4 py-1.5 rounded-full bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs flex items-center gap-1.5 shrink-0 self-start sm:self-auto shadow-sm"
          >
            <RotateCcw class="w-3.5 h-3.5" />
            <span>Restore to Pipeline</span>
          </button>
        </div>

        <!-- Lead Banner Card -->
        <div class="glass-surface p-6 sm:p-8 border border-white/10 flex flex-col lg:flex-row lg:items-center justify-between gap-6 shadow-xl relative overflow-hidden" :class="lead.is_archived ? 'opacity-85' : ''">
          <div class="space-y-2 relative z-10">
            <div class="flex flex-wrap items-center gap-2.5">
              <span class="font-mono text-xs font-bold text-vb-magenta bg-vb-magenta/20 border border-vb-magenta/30 px-2.5 py-0.5 rounded-md shadow-xs">
                {{ lead.reference_id }}
              </span>
              <span v-if="lead.is_archived" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/10 text-white border border-white/20">
                Archived
              </span>
              <StatusBadge v-else :status="lead.status" />
              <PriorityBadge :priority="lead.priority" />
              <span v-if="lead.source" class="text-[11px] text-purple-200/80 bg-white/10 border border-white/10 px-2.5 py-0.5 rounded-full font-medium">
                {{ lead.source }}
              </span>
            </div>

            <h2 class="text-2xl sm:text-3xl font-display font-bold text-white">
              {{ lead.name }}
            </h2>
            <p class="text-sm text-purple-200/70 flex flex-wrap items-center gap-3">
              <span v-if="lead.company_name" class="font-semibold text-white/90">{{ lead.company_name }}</span>
              <span v-if="lead.service_interest" class="text-vb-cyan font-medium">· {{ lead.service_interest }}</span>
            </p>
          </div>

          <!-- Quick Action Buttons & Actions Menu -->
          <div class="flex flex-wrap items-center gap-2.5 relative z-10">
            <!-- WhatsApp Action -->
            <button
              v-if="!lead.is_archived"
              type="button"
              @click="whatsAppModalOpen = true"
              class="px-4.5 py-2.5 rounded-full text-xs font-semibold bg-gradient-to-r from-emerald-600 to-vb-green hover:opacity-95 text-white shadow-md shadow-emerald-600/30 flex items-center gap-2 transition-all"
            >
              <MessageSquare class="w-4 h-4" />
              <span>WhatsApp Connect</span>
            </button>

            <!-- Call Log Action -->
            <button
              v-if="!lead.is_archived"
              type="button"
              @click="callModalOpen = true"
              class="btn-primary px-4.5 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2 shadow-sm"
            >
              <PhoneCall class="w-4 h-4" />
              <span>Log Call</span>
            </button>

            <!-- Schedule Follow-up -->
            <button
              v-if="!lead.is_archived"
              type="button"
              @click="followUpModalOpen = true"
              class="btn-ghost px-4.5 py-2.5 rounded-full text-xs font-semibold flex items-center gap-2"
            >
              <CalendarClock class="w-4 h-4 text-vb-orange" />
              <span>Schedule Follow-up</span>
            </button>

            <!-- Actions Dropdown Menu -->
            <ActionMenu
              :items="getDetailActions()"
              title="More Lead Options"
              trigger-class="px-3.5 py-2.5 rounded-full bg-white/10 border border-white/15 hover:border-white/30 text-white text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-all"
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
            <div class="surface-solid p-6 space-y-4 border border-white/10 shadow-md">
              <h3 class="font-display font-bold text-xs uppercase tracking-wider text-purple-200 border-b border-white/10 pb-2.5 flex items-center justify-between">
                <span>Client Contact Information</span>
                <span class="text-[10px] font-mono text-purple-200/50 font-normal">{{ lead.reference_id }}</span>
              </h3>

              <div class="space-y-3.5 text-xs">
                <div>
                  <span class="text-purple-200/60 text-[11px] block uppercase tracking-wider font-semibold">Phone Number</span>
                  <div class="flex items-center justify-between mt-0.5">
                    <span class="font-bold text-white text-sm">{{ lead.phone }}</span>
                    <a :href="'tel:' + lead.phone" class="text-vb-cyan hover:underline text-[11px] font-semibold flex items-center gap-1">
                      <Phone class="w-3 h-3" />
                      <span>Call Now</span>
                    </a>
                  </div>
                </div>

                <div v-if="lead.email">
                  <span class="text-purple-200/60 text-[11px] block uppercase tracking-wider font-semibold">Email Address</span>
                  <div class="flex items-center justify-between mt-0.5">
                    <span class="font-medium text-white/90">{{ lead.email }}</span>
                    <a :href="'mailto:' + lead.email" class="text-vb-cyan hover:underline text-[11px] font-semibold">Send Mail</a>
                  </div>
                </div>

                <div>
                  <span class="text-purple-200/60 text-[11px] block uppercase tracking-wider font-semibold">Company / Entity</span>
                  <span class="font-medium text-white/90 mt-0.5 block">{{ lead.company_name || 'Individual / Direct' }}</span>
                </div>

                <div>
                  <span class="text-purple-200/60 text-[11px] block uppercase tracking-wider font-semibold">Assigned Representative</span>
                  <div class="flex items-center justify-between mt-1">
                    <div class="flex items-center gap-2">
                      <div class="w-6 h-6 rounded-full bg-gradient-to-br from-vb-magenta to-vb-plum text-white text-[10px] font-bold flex items-center justify-center shrink-0 border border-white/20 shadow-xs">
                        {{ lead.assigned_employee ? lead.assigned_employee.name[0] : '?' }}
                      </div>
                      <span class="font-semibold text-white">{{ lead.assigned_employee?.name || 'Unassigned' }}</span>
                    </div>

                    <!-- Admin or Team Lead Reassign Trigger -->
                    <button
                      v-if="authStore.isAdmin || authStore.isTeamLead"
                      @click="reassignModalOpen = true"
                      class="text-[11px] text-vb-cyan hover:underline font-semibold"
                    >
                      Reassign
                    </button>
                  </div>
                </div>

                <div class="pt-3 border-t border-white/10 grid grid-cols-2 gap-2 text-[11px]">
                  <div>
                    <span class="text-purple-200/60 block">Created Date</span>
                    <span class="font-medium text-white">{{ formatDate(lead.created_at) }}</span>
                  </div>
                  <div>
                    <span class="text-purple-200/60 block">Last Contact</span>
                    <span class="font-medium text-white">{{ lead.last_contact_date ? formatDate(lead.last_contact_date) : 'Never' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Next Follow-up Card -->
            <div class="glass-surface p-6 border border-white/10 shadow-md">
              <div class="flex items-center justify-between border-b border-white/10 pb-2.5 mb-3">
                <h3 class="font-display font-bold text-xs uppercase tracking-wider text-purple-200">
                  Next Scheduled Action
                </h3>
                <span
                  v-if="lead.next_follow_up_date && isOverdue(lead.next_follow_up_date)"
                  class="text-[10px] font-bold text-rose-300 bg-rose-500/20 border border-rose-500/40 px-2.5 py-0.5 rounded-full uppercase"
                >
                  Overdue
                </span>
              </div>

              <div v-if="lead.next_follow_up_date" class="space-y-3.5">
                <div class="p-3.5 bg-white/[0.06] rounded-2xl border border-white/10 space-y-1 text-xs backdrop-blur-sm shadow-sm">
                  <div class="flex items-center gap-2 text-white font-bold">
                    <CalendarClock class="w-4 h-4 text-vb-orange" />
                    <span>{{ formatDateTime(lead.next_follow_up_date) }}</span>
                  </div>
                  <p class="text-purple-200/70 text-[11px] leading-relaxed">
                    Ensure timely consultation check-in and log the client outcome.
                  </p>
                </div>

                <div class="flex items-center gap-2">
                  <button
                    @click="callModalOpen = true"
                    class="w-full btn-primary py-2 text-xs font-semibold rounded-full flex items-center justify-center gap-1.5 shadow-sm"
                  >
                    <Check class="w-3.5 h-3.5" />
                    <span>Complete / Log</span>
                  </button>
                  <button
                    @click="followUpModalOpen = true"
                    class="px-3.5 py-2 text-xs font-semibold text-white hover:bg-white/10 rounded-full border border-white/20 transition-colors shadow-sm"
                  >
                    Reschedule
                  </button>
                </div>
              </div>

              <div v-else class="text-center py-5 space-y-2.5">
                <p class="text-xs text-purple-200/60 italic">No upcoming follow-up scheduled.</p>
                <button
                  @click="followUpModalOpen = true"
                  class="btn-ghost text-xs font-semibold px-4 py-1.5 rounded-full inline-flex items-center gap-1.5"
                >
                  <Plus class="w-3.5 h-3.5 text-vb-cyan" />
                  <span>Set Follow-up Date</span>
                </button>
              </div>
            </div>

            <!-- Additional Details / Scope Card -->
            <div class="surface-solid p-6 space-y-3 border border-white/10 shadow-md">
              <h3 class="font-display font-bold text-xs uppercase tracking-wider text-purple-200 border-b border-white/10 pb-2.5">
                Scope &amp; Inquiry Details
              </h3>
              <p class="text-xs text-purple-100 leading-relaxed whitespace-pre-line bg-white/[0.04] p-3.5 rounded-2xl border border-white/10 font-sans">
                {{ lead.additional_details || 'No additional scope notes entered for this inquiry.' }}
              </p>
            </div>
          </div>

          <!-- Right Column: Activity History & Timeline -->
          <div class="lg:col-span-2 space-y-6">
            <div class="surface-solid p-6 sm:p-7 border border-white/10 shadow-md">
              <div class="flex items-center justify-between mb-4 pb-3 border-b border-white/10">
                <div>
                  <h3 class="font-display font-bold text-base text-white">Activity Stream &amp; Engagement History</h3>
                  <p class="text-xs text-purple-200/70">Chronological timeline of all calls, messages, notes, and status changes</p>
                </div>
              </div>

              <!-- Quick Add Note Input -->
              <div class="p-4 bg-white/[0.04] rounded-2xl border border-white/10 mb-6 backdrop-blur-xs">
                <textarea
                  v-model="newNoteText"
                  rows="2"
                  class="field text-xs resize-none font-sans"
                  placeholder="Add internal note or consultation update for this account..."
                ></textarea>
                <div class="mt-2.5 flex justify-end">
                  <button
                    type="button"
                    @click="handleAddNote"
                    :disabled="savingNote || !newNoteText.trim()"
                    class="btn-primary text-xs font-semibold px-4.5 py-1.5 rounded-full flex items-center gap-1.5 shadow-sm disabled:opacity-40"
                  >
                    <span>{{ savingNote ? 'Saving...' : 'Post Internal Note' }}</span>
                  </button>
                </div>
              </div>

              <!-- Timeline Feed -->
              <div class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-white/10">
                <div
                  v-for="act in lead.activities"
                  :key="act.id"
                  class="relative flex items-start gap-4 text-xs group"
                >
                  <!-- Timeline Icon Bullet -->
                  <div
                    class="absolute -left-6 mt-1 w-5 h-5 rounded-full flex items-center justify-center text-white ring-4 ring-[#18031E] shadow-sm shrink-0"
                    :class="getActivityIconBg(act.activity_type)"
                  >
                    <component :is="getActivityIcon(act.activity_type)" class="w-2.5 h-2.5" />
                  </div>

                  <!-- Activity Box -->
                  <div class="flex-1 bg-white/[0.05] p-4.5 rounded-2xl border border-white/10 shadow-sm hover:border-vb-magenta/40 transition-colors space-y-1.5">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <span class="font-bold text-white text-xs">{{ act.title }}</span>
                        <span class="text-[10px] text-purple-200/60">by {{ act.user?.name || 'System' }}</span>
                      </div>
                      <span class="text-[10px] text-purple-200/50 font-mono">{{ formatDateTime(act.created_at) }}</span>
                    </div>

                    <p v-if="act.description" class="text-purple-100 text-xs leading-relaxed whitespace-pre-line">
                      {{ act.description }}
                    </p>

                    <!-- Call Outcome Specific Meta -->
                    <div v-if="act.metadata && act.metadata.duration_minutes" class="pt-1 text-[11px] text-purple-200/70 flex items-center gap-3">
                      <span>Duration: <strong class="text-white">{{ act.metadata.duration_minutes }} min</strong></span>
                      <span v-if="act.metadata.outcome">Outcome: <strong class="text-white">{{ act.metadata.outcome }}</strong></span>
                    </div>
                  </div>
                </div>

                <div v-if="!lead.activities || lead.activities.length === 0" class="text-center py-8 text-purple-200/60 text-xs italic">
                  No activity history recorded for this lead yet.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
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

    <WhatsAppModal
      :is-open="whatsAppModalOpen"
      :lead="lead"
      @close="whatsAppModalOpen = false"
      @dispatched="fetchLead"
    />

    <EditLeadModal
      :is-open="editModalOpen"
      :lead="lead"
      @close="editModalOpen = false"
      @saved="fetchLead"
    />

    <!-- Reassign Modal (Admin & Team Lead) -->
    <div
      v-if="reassignModalOpen"
      class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-md flex items-center justify-center p-4 animate-in fade-in duration-150"
    >
      <div class="glass-elevated max-w-sm w-full overflow-hidden border border-white/15 shadow-2xl p-6 space-y-4 animate-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
          <h3 class="font-display font-bold text-base text-white">Reassign Lead</h3>
          <button @click="reassignModalOpen = false" class="p-1 rounded-lg text-purple-300 hover:text-white hover:bg-white/10">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
            Select Representative
          </label>
          <select v-model="selectedAssigneeId" class="field text-xs">
            <option value="">Unassign Lead</option>
            <option v-for="emp in employeesList" :key="emp.id" :value="emp.id">
              {{ emp.name }} ({{ emp.role }})
            </option>
          </select>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-white/10">
          <button
            @click="reassignModalOpen = false"
            class="px-4 py-2 text-xs font-semibold text-purple-200/70 hover:text-white rounded-full hover:bg-white/10"
          >
            Cancel
          </button>
          <button
            @click="handleReassign"
            :disabled="reassigning"
            class="btn-primary px-4 py-2 text-xs font-semibold rounded-full shadow-sm"
          >
            <span>{{ reassigning ? 'Reassigning...' : 'Confirm Assignment' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Confirm Modals -->
    <ConfirmModal
      :is-open="archiveModalOpen"
      variant="warning"
      title="Archive Lead Record"
      :subtitle="`${lead?.reference_id} — ${lead?.name}`"
      message="Are you sure you want to archive this lead? The lead will be hidden from the active pipeline and daily queues. Historical activities and notes will remain intact."
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
      :subtitle="`${lead?.reference_id} — ${lead?.name}`"
      message="Restoring this lead will return it to active sales operations, enabling daily follow-up reminders."
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
      :subtitle="`${lead?.reference_id} — ${lead?.name}`"
      message="WARNING: This operation is permanent and irreversible. All associated timeline activities, follow-ups, and notifications will be permanently purged from the database."
      :required-confirmation-text="lead?.reference_id"
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
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import { syncEngine, isOnline } from '../services/syncEngine';
import { offlineStorage } from '../services/offlineStorage';
import AppLayout from '../components/AppLayout.vue';
import StatusBadge from '../components/StatusBadge.vue';
import PriorityBadge from '../components/PriorityBadge.vue';
import ActionMenu, { ActionMenuItem } from '../components/ActionMenu.vue';
import LogCallModal from '../components/LogCallModal.vue';
import ScheduleFollowUpModal from '../components/ScheduleFollowUpModal.vue';
import WhatsAppModal from '../components/WhatsAppModal.vue';
import EditLeadModal from '../components/EditLeadModal.vue';
import ConfirmModal from '../components/ConfirmModal.vue';
import {
  ArrowLeft,
  Phone,
  PhoneCall,
  CalendarClock,
  MessageSquare,
  MoreVertical,
  Check,
  Plus,
  Send,
  UserCheck,
  RefreshCw,
  FileText,
  Sparkles,
  Archive,
  RotateCcw,
  Trash2,
  Edit,
  X,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const lead = ref<any>(null);
const loading = ref(true);
const employeesList = ref<any[]>([]);

const callModalOpen = ref(false);
const followUpModalOpen = ref(false);
const whatsAppModalOpen = ref(false);
const editModalOpen = ref(false);
const reassignModalOpen = ref(false);
const archiveModalOpen = ref(false);
const restoreModalOpen = ref(false);
const forceDeleteModalOpen = ref(false);

const actionSubmitting = ref(false);
const actionError = ref('');

const selectedAssigneeId = ref<string | number>('');
const reassigning = ref(false);

const newNoteText = ref('');
const savingNote = ref(false);

const fetchLead = async () => {
  const id = route.params.id;
  loading.value = true;
  try {
    if (isOnline.value) {
      const res = await api.get(`/leads/${id}`);
      lead.value = res.data;
      selectedAssigneeId.value = res.data.assigned_to || '';
      await offlineStorage.saveLead(res.data);
    } else {
      const cached = await offlineStorage.getLead(Number(id));
      if (cached) {
        lead.value = cached;
        selectedAssigneeId.value = cached.assigned_to || '';
      }
    }
  } catch (err: any) {
    const cached = await offlineStorage.getLead(Number(id));
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
    if (authStore.isAdmin) {
      const empRes = await api.get('/employees');
      employeesList.value = empRes.data;
    } else if (authStore.isTeamLead) {
      employeesList.value = res.data.employees || [];
    }
  } catch (err) {
    // quiet
  }
};

const handleAddNote = async () => {
  if (!lead.value || !newNoteText.value.trim()) return;
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
      show: authStore.isAdmin || authStore.isTeamLead,
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
    case 'follow_up_scheduled': return 'bg-vb-orange';
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
