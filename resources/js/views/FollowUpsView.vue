<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-vb-magenta/20 text-purple-200 border border-vb-magenta/30">
              Operations
            </span>
          </div>
          <h2 class="text-2xl font-display font-bold text-white mt-1">Daily Follow-up Schedule</h2>
          <p class="text-xs text-purple-200/70 mt-0.5">
            {{ authStore.isAdmin ? 'Monitor follow-ups across all employees or focus on your tasks.' : 'Your scheduled calls, meetings, and check-in commitments.' }}
          </p>
        </div>
      </div>

      <!-- Tabs Bar -->
      <div class="flex items-center gap-2 border-b border-white/10 pb-3 overflow-x-auto text-xs font-semibold">
        <button
          @click="changeTab('today')"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'today' ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-md shadow-vb-magenta/30 border border-white/20' : 'btn-ghost text-purple-200/70 hover:text-white'"
        >
          <span>Today's Commitments</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="activeTab === 'today' ? 'bg-white/20 text-white' : 'bg-white/10 text-purple-200'">
            {{ counts.today || 0 }}
          </span>
        </button>

        <button
          @click="changeTab('overdue')"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'overdue' ? 'bg-gradient-to-r from-rose-600 to-rose-700 text-white shadow-md shadow-rose-600/30 border border-rose-500/40' : 'btn-ghost text-purple-200/70 hover:text-rose-400'"
        >
          <span>Overdue Tasks</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="activeTab === 'overdue' ? 'bg-white/25 text-white' : 'bg-rose-500/20 text-rose-300'">
            {{ counts.overdue || 0 }}
          </span>
        </button>

        <button
          @click="changeTab('upcoming')"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'upcoming' ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-md shadow-vb-magenta/30 border border-white/20' : 'btn-ghost text-purple-200/70 hover:text-white'"
        >
          <span>Upcoming Days</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="activeTab === 'upcoming' ? 'bg-white/20 text-white' : 'bg-white/10 text-purple-200'">
            {{ counts.upcoming || 0 }}
          </span>
        </button>

        <button
          @click="changeTab('completed')"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'completed' ? 'bg-gradient-to-r from-vb-magenta to-vb-plum text-white shadow-md shadow-vb-magenta/30 border border-white/20' : 'btn-ghost text-purple-200/70 hover:text-white'"
        >
          <span>Completed History</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="activeTab === 'completed' ? 'bg-white/20 text-white' : 'bg-white/10 text-purple-200'">
            {{ counts.completed || 0 }}
          </span>
        </button>
      </div>

      <!-- Follow-up Items List -->
      <div v-if="loading" class="py-16 flex justify-center">
        <div class="w-8 h-8 border-3 border-vb-magenta/30 border-t-vb-magenta rounded-full animate-spin"></div>
      </div>

      <div v-else-if="followUps.length === 0" class="glass-surface p-12 text-center border border-white/10">
        <CalendarCheck class="w-12 h-12 text-emerald-400 mx-auto mb-3 opacity-90" />
        <h3 class="font-display font-bold text-base text-white">All clear in this section!</h3>
        <p class="text-xs text-purple-200/70 mt-1 max-w-sm mx-auto">
          {{ activeTab === 'overdue' ? 'No overdue tasks. Great job keeping the pipeline up to date!' : 'No follow-up tasks scheduled for this category.' }}
        </p>
      </div>

      <div v-else class="grid md:grid-cols-2 gap-4">
        <div
          v-for="item in followUps"
          :key="item.id"
          class="glass-surface p-5 space-y-4 hover:translate-y-[-2px] transition-all flex flex-col justify-between border border-white/10 shadow-lg"
          :class="activeTab === 'overdue' ? 'border-rose-500/40 bg-rose-500/[0.06]' : ''"
        >
          <div class="space-y-3">
            <!-- Header Row -->
            <div class="flex items-start justify-between gap-3">
              <div>
                <span class="font-mono text-[11px] font-bold text-vb-magenta">
                  {{ item.lead?.reference_id }}
                </span>
                <router-link
                  :to="`/leads/${item.lead?.id}`"
                  class="font-display font-bold text-base text-white block hover:text-vb-cyan transition-colors"
                >
                  {{ item.lead?.name }}
                </router-link>
                <span class="text-xs text-purple-200/70 block">
                  {{ item.lead?.company_name || 'Individual' }} · {{ item.lead?.service_interest || 'General Inquiry' }}
                </span>
              </div>

              <div class="text-right">
                <StatusBadge v-if="item.lead" :status="item.lead.status" />
                <div class="mt-1 text-[11px] font-bold" :class="isOverdue(item.scheduled_at) ? 'text-rose-400' : 'text-purple-100'">
                  {{ formatDateTime(item.scheduled_at) }}
                </div>
              </div>
            </div>

            <!-- Notes & Objective -->
            <div v-if="item.notes" class="p-3 bg-white/[0.04] rounded-xl border border-white/10 text-xs text-purple-100">
              <span class="font-semibold text-purple-200 block mb-0.5">Objective:</span>
              <p class="text-xs text-white/90 whitespace-pre-line">{{ item.notes }}</p>
            </div>

            <!-- Completed Info -->
            <div v-if="item.status === 'completed'" class="p-3 bg-emerald-500/15 rounded-xl border border-emerald-500/30 text-xs text-emerald-200 space-y-1">
              <div class="flex items-center gap-1.5 font-bold">
                <Check class="w-4 h-4 text-emerald-400" />
                <span>Outcome: {{ item.outcome }}</span>
              </div>
              <span class="text-[11px] text-emerald-300/80 block">
                Completed on {{ formatDateTime(item.completed_at) }}
              </span>
            </div>

            <!-- Representative Pill (Admin view) -->
            <div v-if="authStore.isAdmin && item.user" class="text-[11px] text-purple-200/60 flex items-center gap-1.5">
              <span>Handled by:</span>
              <strong class="text-white">{{ item.user.name }}</strong>
            </div>
          </div>

          <!-- Bottom Action Buttons -->
          <div class="pt-3 border-t border-white/10 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <button
                v-if="item.lead?.phone"
                @click="openWhatsApp(item.lead)"
                title="WhatsApp Direct"
                class="p-2 rounded-full text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/30 transition-colors"
              >
                <MessageSquare class="w-4 h-4" />
              </button>
              <a
                v-if="item.lead?.phone"
                :href="'tel:' + (item.lead?.phone || '')"
                title="Call"
                class="p-2 rounded-full text-purple-200 hover:text-white hover:bg-white/10 border border-white/15 transition-colors"
              >
                <Phone class="w-4 h-4" />
              </a>
            </div>

            <div class="flex items-center gap-2">
              <button
                v-if="item.status === 'pending'"
                @click="openRescheduleModal(item)"
                class="px-3 py-1.5 rounded-full text-xs font-semibold text-white hover:bg-white/10 border border-white/20 transition-colors"
              >
                Reschedule
              </button>
              <button
                v-if="item.status === 'pending'"
                @click="openCompleteModal(item)"
                class="btn-primary px-4 py-1.5 rounded-full text-xs font-semibold flex items-center gap-1.5 shadow-sm"
              >
                <Check class="w-3.5 h-3.5" />
                <span>Complete Task</span>
              </button>

              <!-- Actions Dropdown -->
              <ActionMenu
                :items="getFollowUpActions(item)"
                title="Task Actions"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <WhatsAppModal
      :is-open="whatsAppModalOpen"
      :lead="activeLead"
      @close="whatsAppModalOpen = false"
      @dispatched="fetchFollowUps"
    />

    <!-- Complete Follow-up Modal -->
    <div v-if="completeModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-md flex items-center justify-center p-4">
      <div class="glass-elevated max-w-md w-full p-6 sm:p-7 space-y-4 relative overflow-hidden border border-white/15 shadow-2xl">
        <div class="h-1.5 w-full spectrum absolute top-0 left-0"></div>
        <div class="pt-1">
          <h3 class="font-display font-bold text-lg text-white">Mark Follow-up as Completed</h3>
          <p class="text-xs text-purple-200/70 mt-0.5">Record the client response and update pipeline status.</p>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
            Engagement Outcome <span class="text-rose-400">*</span>
          </label>
          <select v-model="completeForm.outcome" required class="field text-sm">
            <option value="Spoke & Follow-up Scheduled">Spoke & Next Follow-up Scheduled</option>
            <option value="Proposal Accepted / Converted">Proposal Accepted / Converted</option>
            <option value="Proposal In Review">Proposal Sent / In Client Review</option>
            <option value="Callback Requested">Callback Requested</option>
            <option value="No Answer / Will Retry">No Answer / Left Voicemail</option>
            <option value="Not Interested / Closed">Not Interested / Closed Account</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
            Resolution Notes
          </label>
          <textarea
            v-model="completeForm.notes"
            rows="3"
            class="field text-sm font-sans resize-none"
            placeholder="Details of client reaction, questions asked, next agreed steps..."
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
            Schedule Next Follow-up (Optional)
          </label>
          <input
            v-model="completeForm.next_follow_up_date"
            type="datetime-local"
            class="field text-sm"
          />
        </div>

        <div class="flex justify-end gap-2.5 pt-3 border-t border-white/10">
          <button @click="completeModalOpen = false" class="btn-ghost px-4 py-2 text-xs rounded-full font-semibold">
            Cancel
          </button>
          <button @click="handleCompleteSubmit" :disabled="completing" class="btn-primary px-5 py-2 text-xs rounded-full font-semibold shadow-sm">
            {{ completing ? 'Saving...' : 'Mark Completed' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Reschedule Modal -->
    <div v-if="rescheduleModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-md flex items-center justify-center p-4">
      <div class="glass-elevated max-w-sm w-full p-6 sm:p-7 space-y-4 relative overflow-hidden border border-white/15 shadow-2xl">
        <div class="h-1.5 w-full spectrum absolute top-0 left-0"></div>
        <div class="pt-1">
          <h3 class="font-display font-bold text-lg text-white">Reschedule Task</h3>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
            New Date &amp; Time
          </label>
          <input
            v-model="rescheduleForm.scheduled_at"
            type="datetime-local"
            required
            class="field text-sm"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-purple-200 mb-1.5">
            Reason / Adjustment
          </label>
          <textarea
            v-model="rescheduleForm.notes"
            rows="2"
            class="field text-xs font-sans resize-none"
            placeholder="e.g. Client requested postponement..."
          ></textarea>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-white/10">
          <button @click="rescheduleModalOpen = false" class="btn-ghost px-4 py-2 text-xs rounded-full font-semibold">
            Cancel
          </button>
          <button @click="handleRescheduleSubmit" :disabled="rescheduling" class="btn-primary px-4 py-2 text-xs rounded-full font-semibold shadow-sm">
            {{ rescheduling ? 'Updating...' : 'Save New Time' }}
          </button>
        </div>
      </div>
    </div>
    <!-- Cancel Follow-up Confirmation Modal -->
    <ConfirmModal
      :is-open="cancelModalOpen"
      title="Cancel Follow-up Task"
      :subtitle="`${activeFollowUp?.lead?.reference_id} · ${activeFollowUp?.lead?.name}`"
      :message="`Are you sure you want to cancel the follow-up task scheduled for ${activeFollowUp?.scheduled_at_ist || 'this time'}?`"
      consequences="Cancelling this task will deactivate any pending 5-minute Android reminders and record a cancellation activity on the client's timeline. You can schedule a new follow-up at any time."
      confirm-text="Cancel Task"
      variant="warning"
      :is-submitting="actionSubmitting"
      :error="actionError"
      @confirm="executeCancelFollowUp"
      @cancel="cancelModalOpen = false"
    />

    <!-- Delete Follow-up Record Confirmation Modal (Admin Only) -->
    <ConfirmModal
      :is-open="deleteModalOpen"
      title="Delete Follow-up Record"
      :subtitle="`Task #${activeFollowUp?.id} · ${activeFollowUp?.lead?.name}`"
      :message="`Permanently delete follow-up task #${activeFollowUp?.id} from the system?`"
      consequences="This follow-up record and its pending reminders will be permanently removed. The lead's next follow-up date will be recalculated from remaining active tasks."
      confirm-text="Delete Record"
      variant="danger"
      :is-submitting="actionSubmitting"
      :error="actionError"
      @confirm="executeDeleteFollowUp"
      @cancel="deleteModalOpen = false"
    />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import { localNotificationService } from '../services/localNotifications';
import AppLayout from '../components/AppLayout.vue';
import StatusBadge from '../components/StatusBadge.vue';
import WhatsAppModal from '../components/WhatsAppModal.vue';
import ActionMenu, { ActionMenuItem } from '../components/ActionMenu.vue';
import ConfirmModal from '../components/ConfirmModal.vue';
import {
  CalendarCheck,
  Check,
  Phone,
  MessageSquare,
  Eye,
  CalendarClock,
  Ban,
  Trash2,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const loading = ref(true);
const followUps = ref<any[]>([]);
const activeTab = ref('today');
const counts = reactive({
  today: 0,
  overdue: 0,
  upcoming: 0,
  completed: 0,
});

const activeLead = ref<any>(null);
const whatsAppModalOpen = ref(false);

const completeModalOpen = ref(false);
const rescheduleModalOpen = ref(false);
const cancelModalOpen = ref(false);
const deleteModalOpen = ref(false);

const activeFollowUp = ref<any>(null);
const completing = ref(false);
const rescheduling = ref(false);
const actionSubmitting = ref(false);
const actionError = ref('');

const completeForm = reactive({
  outcome: 'Spoke & Follow-up Scheduled',
  notes: '',
  next_follow_up_date: '',
});

const rescheduleForm = reactive({
  scheduled_at: '',
  notes: '',
});

const changeTab = (tab: string) => {
  activeTab.value = tab;
  fetchFollowUps();
};

const fetchFollowUps = async () => {
  loading.value = true;
  try {
    const res = await api.get('/follow-ups', {
      params: { tab: activeTab.value, per_page: 50 },
    });
    followUps.value = res.data.data;
    Object.assign(counts, res.data.counts || {});
  } catch (err) {
    console.error('Failed to fetch follow-ups', err);
  } finally {
    loading.value = false;
  }
};

const openWhatsApp = (lead: any) => {
  activeLead.value = lead;
  whatsAppModalOpen.value = true;
};

const openCompleteModal = (item: any) => {
  activeFollowUp.value = item;
  completeForm.outcome = 'Spoke & Follow-up Scheduled';
  completeForm.notes = '';
  completeForm.next_follow_up_date = '';
  completeModalOpen.value = true;
};

const handleCompleteSubmit = async () => {
  if (!activeFollowUp.value) return;
  completing.value = true;
  try {
    await api.post(`/follow-ups/${activeFollowUp.value.id}/complete`, {
      outcome: completeForm.outcome,
      notes: completeForm.notes,
      next_follow_up_date: completeForm.next_follow_up_date || null,
    });
    // Cancel native Android hardware alarm
    await localNotificationService.cancelReminder(activeFollowUp.value.id);
    completeModalOpen.value = false;
    await fetchFollowUps();
  } catch (err) {
    console.error('Failed to complete follow up', err);
  } finally {
    completing.value = false;
  }
};

const openRescheduleModal = (item: any) => {
  activeFollowUp.value = item;
  rescheduleForm.scheduled_at = item.scheduled_at ? item.scheduled_at.slice(0, 16) : '';
  rescheduleForm.notes = item.notes || '';
  rescheduleModalOpen.value = true;
};

const handleRescheduleSubmit = async () => {
  if (!activeFollowUp.value) return;
  rescheduling.value = true;
  try {
    const res = await api.put(`/follow-ups/${activeFollowUp.value.id}`, {
      scheduled_at: rescheduleForm.scheduled_at,
      notes: rescheduleForm.notes,
    });
    if (res.data?.follow_up) {
      await localNotificationService.scheduleReminder(res.data.follow_up);
    }
    rescheduleModalOpen.value = false;
    await fetchFollowUps();
  } catch (err) {
    console.error('Failed to reschedule', err);
  } finally {
    rescheduling.value = false;
  }
};

const openCancelModal = (item: any) => {
  activeFollowUp.value = item;
  actionError.value = '';
  cancelModalOpen.value = true;
};

const executeCancelFollowUp = async () => {
  if (!activeFollowUp.value) return;
  actionSubmitting.value = true;
  actionError.value = '';

  try {
    await api.post(`/follow-ups/${activeFollowUp.value.id}/cancel`);
    await localNotificationService.cancelReminder(activeFollowUp.value.id);
    cancelModalOpen.value = false;
    await fetchFollowUps();
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Failed to cancel follow-up task.';
  } finally {
    actionSubmitting.value = false;
  }
};

const openDeleteModal = (item: any) => {
  activeFollowUp.value = item;
  actionError.value = '';
  deleteModalOpen.value = true;
};

const executeDeleteFollowUp = async () => {
  if (!activeFollowUp.value) return;
  actionSubmitting.value = true;
  actionError.value = '';

  try {
    await api.delete(`/follow-ups/${activeFollowUp.value.id}`);
    await localNotificationService.cancelReminder(activeFollowUp.value.id);
    deleteModalOpen.value = false;
    await fetchFollowUps();
  } catch (err: any) {
    actionError.value = err.response?.data?.message || 'Failed to delete follow-up record.';
  } finally {
    actionSubmitting.value = false;
  }
};

const getFollowUpActions = (item: any): ActionMenuItem[] => {
  const actions: ActionMenuItem[] = [
    {
      label: 'View Lead Profile',
      icon: Eye,
      to: `/leads/${item.lead?.id}`,
    },
  ];

  if (item.status === 'pending') {
    actions.push(
      {
        label: 'Reschedule Task',
        icon: CalendarClock,
        action: () => openRescheduleModal(item),
      },
      {
        label: 'Complete Task',
        icon: Check,
        action: () => openCompleteModal(item),
      },
      {
        divider: true,
      },
      {
        label: 'Cancel Task',
        icon: Ban,
        warning: true,
        action: () => openCancelModal(item),
      }
    );
  }

  if (authStore.isAdmin) {
    actions.push(
      {
        divider: true,
      },
      {
        label: 'Delete Follow-up Record',
        icon: Trash2,
        danger: true,
        action: () => openDeleteModal(item),
      }
    );
  }

  return actions;
};

const isOverdue = (dateStr: string) => {
  if (!dateStr) return false;
  return new Date(dateStr) < new Date();
};

const formatDateTime = (dateStr: string) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
};

onMounted(() => {
  fetchFollowUps();
});
</script>
