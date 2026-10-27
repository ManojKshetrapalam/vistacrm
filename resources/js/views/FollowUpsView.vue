<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl font-display font-bold text-vb-plum">Daily Follow-up Schedule</h2>
          <p class="text-xs text-vb-muted mt-0.5">
            {{ authStore.isAdmin ? 'Monitor follow-ups across all employees or focus on your tasks.' : 'Your scheduled calls, meetings, and check-in commitments.' }}
          </p>
        </div>
      </div>

      <!-- Tabs Bar -->
      <div class="flex items-center gap-2 border-b border-vb-plum/10 pb-3 overflow-x-auto text-xs font-semibold">
        <button
          @click="changeTab('today')"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'today' ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <span>Today's Commitments</span>
          <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'today' ? 'bg-white/20 text-white' : 'bg-vb-plum/10 text-vb-plum'">
            {{ counts.today || 0 }}
          </span>
        </button>

        <button
          @click="changeTab('overdue')"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'overdue' ? 'bg-rose-600 text-white shadow-sm' : 'text-vb-muted hover:text-rose-600 hover:bg-white'"
        >
          <span>Overdue Tasks</span>
          <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'overdue' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-700 font-bold'">
            {{ counts.overdue || 0 }}
          </span>
        </button>

        <button
          @click="changeTab('upcoming')"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'upcoming' ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <span>Upcoming Days</span>
          <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'upcoming' ? 'bg-white/20 text-white' : 'bg-vb-plum/10 text-vb-plum'">
            {{ counts.upcoming || 0 }}
          </span>
        </button>

        <button
          @click="changeTab('completed')"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'completed' ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <span>Completed History</span>
          <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'completed' ? 'bg-white/20 text-white' : 'bg-vb-plum/10 text-vb-plum'">
            {{ counts.completed || 0 }}
          </span>
        </button>
      </div>

      <!-- Follow-up Items List -->
      <div v-if="loading" class="py-16 flex justify-center">
        <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
      </div>

      <div v-else-if="followUps.length === 0" class="card-glass p-12 text-center">
        <CalendarCheck class="w-12 h-12 text-vb-emerald mx-auto mb-3 opacity-80" />
        <h3 class="font-display font-bold text-base text-vb-plum">All clear in this section!</h3>
        <p class="text-xs text-vb-muted mt-1 max-w-sm mx-auto">
          {{ activeTab === 'overdue' ? 'No overdue tasks. Great job keeping the pipeline up to date!' : 'No follow-up tasks scheduled for this category.' }}
        </p>
      </div>

      <div v-else class="grid md:grid-cols-2 gap-4">
        <div
          v-for="item in followUps"
          :key="item.id"
          class="card-glass p-5 space-y-4 hover:translate-y-[-1px] transition-transform flex flex-col justify-between"
          :class="activeTab === 'overdue' ? 'border-rose-200 bg-rose-50/20' : ''"
        >
          <div class="space-y-3">
            <!-- Header Row -->
            <div class="flex items-start justify-between gap-3">
              <div>
                <span class="font-mono text-[11px] font-bold text-vb-violet">
                  {{ item.lead?.reference_id }}
                </span>
                <router-link
                  :to="`/leads/${item.lead?.id}`"
                  class="font-display font-bold text-base text-vb-plum block hover:underline"
                >
                  {{ item.lead?.name }}
                </router-link>
                <span class="text-xs text-vb-muted block">
                  {{ item.lead?.company_name || 'Individual' }} · {{ item.lead?.service_interest || 'General Inquiry' }}
                </span>
              </div>

              <div class="text-right">
                <StatusBadge v-if="item.lead" :status="item.lead.status" />
                <div class="mt-1 text-[11px] font-bold" :class="isOverdue(item.scheduled_at) ? 'text-rose-600' : 'text-vb-plum'">
                  {{ formatDateTime(item.scheduled_at) }}
                </div>
              </div>
            </div>

            <!-- Notes & Objective -->
            <div v-if="item.notes" class="p-3 bg-white/70 rounded-xl border border-vb-plum/10 text-xs text-vb-ink">
              <span class="font-semibold text-vb-plum block mb-0.5">Objective:</span>
              <p class="text-xs text-vb-ink/90 whitespace-pre-line">{{ item.notes }}</p>
            </div>

            <!-- Completed Info -->
            <div v-if="item.status === 'completed'" class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-900 space-y-1">
              <div class="flex items-center gap-1.5 font-bold">
                <Check class="w-4 h-4 text-emerald-600" />
                <span>Outcome: {{ item.outcome }}</span>
              </div>
              <span class="text-[11px] text-emerald-700 block">
                Completed on {{ formatDateTime(item.completed_at) }}
              </span>
            </div>

            <!-- Representative Pill (Admin view) -->
            <div v-if="authStore.isAdmin && item.user" class="text-[11px] text-vb-muted flex items-center gap-1.5">
              <span>Handled by:</span>
              <strong class="text-vb-plum">{{ item.user.name }}</strong>
            </div>
          </div>

          <!-- Bottom Action Buttons -->
          <div v-if="item.status === 'pending'" class="pt-3 border-t border-vb-plum/10 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <button
                @click="openWhatsApp(item.lead)"
                title="WhatsApp Direct"
                class="p-2 rounded-full text-emerald-600 hover:bg-emerald-50 border border-emerald-200"
              >
                <MessageSquare class="w-4 h-4" />
              </button>
              <a
                :href="'tel:' + (item.lead?.phone || '')"
                title="Call"
                class="p-2 rounded-full text-vb-plum hover:bg-vb-plum/10 border border-vb-plum/20"
              >
                <Phone class="w-4 h-4" />
              </a>
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="openRescheduleModal(item)"
                class="px-3 py-1.5 rounded-full text-xs font-semibold text-vb-plum hover:bg-vb-cream border border-vb-plum/20"
              >
                Reschedule
              </button>
              <button
                @click="openCompleteModal(item)"
                class="btn-primary px-4 py-1.5 rounded-full text-xs font-semibold flex items-center gap-1.5 shadow-sm"
              >
                <Check class="w-3.5 h-3.5" />
                <span>Complete Task</span>
              </button>
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
    <div v-if="completeModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4 border border-vb-plum/10">
        <h3 class="font-display font-bold text-base text-vb-plum">Mark Follow-up as Completed</h3>
        <p class="text-xs text-vb-muted">Record the client response and update pipeline status.</p>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Engagement Outcome <span class="text-rose-500">*</span>
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
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
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
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Schedule Next Follow-up (Optional)
          </label>
          <input
            v-model="completeForm.next_follow_up_date"
            type="datetime-local"
            class="field text-sm"
          />
        </div>

        <div class="flex justify-end gap-2.5 pt-3 border-t border-vb-plum/10">
          <button @click="completeModalOpen = false" class="px-4 py-2 text-xs font-semibold text-vb-plum">
            Cancel
          </button>
          <button @click="handleCompleteSubmit" :disabled="completing" class="btn-primary px-5 py-2 text-xs rounded-full font-semibold">
            {{ completing ? 'Saving...' : 'Mark Completed' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Reschedule Modal -->
    <div v-if="rescheduleModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 space-y-4 border border-vb-plum/10">
        <h3 class="font-display font-bold text-base text-vb-plum">Reschedule Task</h3>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
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
          <label class="block text-xs font-semibold uppercase tracking-wider text-vb-plum mb-1.5">
            Reason / Adjustment
          </label>
          <textarea
            v-model="rescheduleForm.notes"
            rows="2"
            class="field text-xs font-sans resize-none"
            placeholder="e.g. Client requested postponement..."
          ></textarea>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-vb-plum/10">
          <button @click="rescheduleModalOpen = false" class="px-4 py-2 text-xs font-semibold text-vb-plum">
            Cancel
          </button>
          <button @click="handleRescheduleSubmit" :disabled="rescheduling" class="btn-primary px-4 py-2 text-xs rounded-full font-semibold">
            {{ rescheduling ? 'Updating...' : 'Save New Time' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import StatusBadge from '../components/StatusBadge.vue';
import WhatsAppModal from '../components/WhatsAppModal.vue';
import {
  CalendarCheck,
  Check,
  Phone,
  MessageSquare,
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
const activeFollowUp = ref<any>(null);
const completing = ref(false);
const completeForm = reactive({
  outcome: 'Spoke & Follow-up Scheduled',
  notes: '',
  next_follow_up_date: '',
});

const rescheduleModalOpen = ref(false);
const rescheduling = ref(false);
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
    await api.put(`/follow-ups/${activeFollowUp.value.id}`, {
      scheduled_at: rescheduleForm.scheduled_at,
      notes: rescheduleForm.notes,
    });
    rescheduleModalOpen.value = false;
    await fetchFollowUps();
  } catch (err) {
    console.error('Failed to reschedule', err);
  } finally {
    rescheduling.value = false;
  }
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
