<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-vb-plum/60 backdrop-blur-sm animate-fade-in"
    role="dialog"
    aria-modal="true"
    aria-labelledby="outstanding-briefing-title"
  >
    <div
      class="bg-white rounded-3xl border border-vb-plum/20 shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col max-h-[85vh] animate-scale-up"
    >
      <!-- Top Spectrum Bar -->
      <div class="spectrum h-1.5 w-full"></div>

      <!-- Modal Header -->
      <div class="p-6 border-b border-vb-plum/10 bg-vb-cream/30 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-vb-plum text-white flex items-center justify-center shadow-md">
            <CalendarClock class="w-5 h-5 text-vb-amber" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-[10px] font-bold uppercase tracking-widest text-vb-violet font-display">Daily Briefing</span>
              <span
                v-if="counts.overdue > 0"
                class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700"
              >
                {{ counts.overdue }} Overdue
              </span>
            </div>
            <h2 id="outstanding-briefing-title" class="font-display font-bold text-lg text-vb-plum">
              Outstanding Follow-up Tasks
            </h2>
          </div>
        </div>

        <button
          @click="dismissModal"
          class="p-2 rounded-xl text-vb-muted hover:text-vb-plum hover:bg-vb-plum/5 transition-colors"
          title="Dismiss"
          aria-label="Close outstanding follow-ups briefing"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Tab Navigation -->
      <div class="flex border-b border-vb-plum/10 bg-white px-6 pt-2 gap-2 text-xs font-semibold">
        <button
          @click="activeTab = 'overdue'"
          class="pb-3 px-3 relative transition-colors flex items-center gap-1.5"
          :class="activeTab === 'overdue' ? 'text-rose-600 border-b-2 border-rose-600' : 'text-vb-muted hover:text-vb-ink'"
        >
          <span>Overdue</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px]"
            :class="activeTab === 'overdue' ? 'bg-rose-100 text-rose-700' : 'bg-gray-100 text-gray-600'"
          >
            {{ counts.overdue }}
          </span>
        </button>

        <button
          @click="activeTab = 'due_today'"
          class="pb-3 px-3 relative transition-colors flex items-center gap-1.5"
          :class="activeTab === 'due_today' ? 'text-vb-plum border-b-2 border-vb-plum' : 'text-vb-muted hover:text-vb-ink'"
        >
          <span>Due Today</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px]"
            :class="activeTab === 'due_today' ? 'bg-vb-plum/10 text-vb-plum' : 'bg-gray-100 text-gray-600'"
          >
            {{ counts.due_today }}
          </span>
        </button>

        <button
          @click="activeTab = 'upcoming'"
          class="pb-3 px-3 relative transition-colors flex items-center gap-1.5"
          :class="activeTab === 'upcoming' ? 'text-vb-teal border-b-2 border-vb-teal' : 'text-vb-muted hover:text-vb-ink'"
        >
          <span>Upcoming</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px]"
            :class="activeTab === 'upcoming' ? 'bg-vb-teal/10 text-vb-teal' : 'bg-gray-100 text-gray-600'"
          >
            {{ counts.upcoming }}
          </span>
        </button>
      </div>

      <!-- Follow-up Items Content -->
      <div class="p-6 overflow-y-auto flex-1 space-y-3 bg-vb-cream/10">
        <div v-if="loading" class="py-12 text-center text-vb-muted">
          <Loader2 class="w-6 h-6 animate-spin mx-auto text-vb-violet mb-2" />
          <p class="text-xs">Checking follow-up schedules...</p>
        </div>

        <div v-else-if="currentList.length === 0" class="py-12 text-center text-vb-muted">
          <CheckCircle2 class="w-8 h-8 text-emerald-500 mx-auto mb-2" />
          <p class="text-xs font-semibold text-vb-ink">No tasks in this category.</p>
          <p class="text-[11px]">All clear for this selection.</p>
        </div>

        <div
          v-else
          v-for="item in currentList"
          :key="item.id"
          class="p-4 rounded-2xl bg-white border border-vb-plum/10 shadow-sm hover:border-vb-violet/40 transition-all flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
        >
          <div class="space-y-1 min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-mono font-bold text-vb-violet">{{ item.lead?.reference_id }}</span>
              <span class="text-sm font-bold text-vb-plum">{{ item.lead?.name }}</span>
              <span
                v-if="item.lead?.company_name"
                class="text-[11px] text-vb-muted bg-vb-cream px-2 py-0.5 rounded-full"
              >
                {{ item.lead.company_name }}
              </span>
            </div>

            <div class="flex items-center gap-3 text-xs text-vb-ink/80 flex-wrap">
              <span class="flex items-center gap-1 font-medium text-vb-plum">
                <Clock class="w-3.5 h-3.5 text-vb-violet" />
                {{ item.scheduled_at_ist || item.scheduled_at }}
              </span>
              <span v-if="item.lead?.service_interest" class="text-vb-muted">
                Interest: <strong class="text-vb-ink">{{ item.lead.service_interest }}</strong>
              </span>
            </div>

            <p v-if="item.notes" class="text-xs text-vb-muted italic line-clamp-2 mt-1">
              "{{ item.notes }}"
            </p>
          </div>

          <button
            @click="goToLead(item.lead_id)"
            class="btn-primary text-xs font-semibold px-4 py-2 rounded-xl shrink-0 flex items-center gap-1.5"
          >
            <span>Open Lead</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="p-4 bg-white border-t border-vb-plum/10 flex items-center justify-between text-xs">
        <span class="text-vb-muted">
          Total outstanding: <strong class="text-vb-plum">{{ total }} tasks</strong>
        </span>
        <button
          @click="dismissModal"
          class="px-5 py-2.5 rounded-full font-semibold text-vb-plum bg-vb-cream/80 hover:bg-vb-plum/10 transition-colors"
        >
          Dismiss &amp; Continue
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api/axios';
import {
  CalendarClock,
  X,
  Clock,
  ArrowRight,
  CheckCircle2,
  Loader2,
} from 'lucide-vue-next';

const router = useRouter();

const isOpen = ref(false);
const loading = ref(false);
const total = ref(0);
const counts = ref({ overdue: 0, due_today: 0, upcoming: 0 });
const overdue = ref<any[]>([]);
const dueToday = ref<any[]>([]);
const upcoming = ref<any[]>([]);
const activeTab = ref<'overdue' | 'due_today' | 'upcoming'>('overdue');

const currentList = computed(() => {
  if (activeTab.value === 'overdue') return overdue.value;
  if (activeTab.value === 'due_today') return dueToday.value;
  return upcoming.value;
});

const fetchOutstanding = async () => {
  // If already seen in this session, do not auto-open
  if (sessionStorage.getItem('vb_crm_login_popup_seen')) {
    return;
  }

  loading.value = true;
  try {
    const res = await api.get('/follow-ups/outstanding');
    total.value = res.data.total || 0;
    counts.value = res.data.counts || { overdue: 0, due_today: 0, upcoming: 0 };
    overdue.value = res.data.overdue || [];
    dueToday.value = res.data.due_today || [];
    upcoming.value = res.data.upcoming || [];

    // If there are outstanding tasks, show the popup
    if (total.value > 0) {
      // Default to overdue if any exist, otherwise due_today, otherwise upcoming
      if (counts.value.overdue > 0) {
        activeTab.value = 'overdue';
      } else if (counts.value.due_today > 0) {
        activeTab.value = 'due_today';
      } else {
        activeTab.value = 'upcoming';
      }
      isOpen.value = true;
    }
  } catch (err) {
    // Fail gracefully without breaking dashboard
    console.error('Failed to load outstanding follow-ups briefing:', err);
  } finally {
    loading.value = false;
  }
};

const dismissModal = () => {
  isOpen.value = false;
  // Mark as seen for this login session so page navigations won't re-trigger it
  sessionStorage.setItem('vb_crm_login_popup_seen', 'true');
};

const goToLead = (leadId: number | string) => {
  dismissModal();
  router.push(`/leads/${leadId}`);
};

onMounted(() => {
  fetchOutstanding();
});
</script>
