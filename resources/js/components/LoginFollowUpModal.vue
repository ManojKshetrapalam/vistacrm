<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-md animate-fade-in"
    role="dialog"
    aria-modal="true"
    aria-labelledby="outstanding-briefing-title"
  >
    <div
      class="glass-elevated border border-white/15 shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col max-h-[85vh] animate-scale-up"
    >
      <!-- Top Spectrum Bar -->
      <div class="spectrum h-1.5 w-full"></div>

      <!-- Modal Header -->
      <div class="p-6 border-b border-white/10 bg-white/[0.04] backdrop-blur-md flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-vb-magenta to-vb-plum text-white flex items-center justify-center shadow-sm border border-white/20">
            <CalendarClock class="w-5 h-5 text-white" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-[10px] font-bold uppercase tracking-widest text-vb-magenta font-display">Daily Briefing</span>
              <span
                v-if="counts.overdue > 0"
                class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40"
              >
                {{ counts.overdue }} Overdue
              </span>
            </div>
            <h2 id="outstanding-briefing-title" class="font-display font-bold text-lg text-white">
              Outstanding Follow-up Commitments
            </h2>
          </div>
        </div>

        <button
          @click="dismissModal"
          class="p-2 rounded-xl text-purple-300 hover:text-white hover:bg-white/10 transition-colors"
          title="Dismiss"
          aria-label="Close outstanding follow-ups briefing"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Tab Navigation -->
      <div class="flex border-b border-white/10 bg-white/[0.03] px-6 pt-2 gap-2 text-xs font-semibold backdrop-blur-xs">
        <button
          @click="activeTab = 'overdue'"
          class="pb-3 px-3 relative transition-colors flex items-center gap-1.5"
          :class="activeTab === 'overdue' ? 'text-rose-400 border-b-2 border-rose-400 font-bold' : 'text-purple-200/70 hover:text-white'"
        >
          <span>Overdue Tasks</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
            :class="activeTab === 'overdue' ? 'bg-rose-500/20 text-rose-300' : 'bg-white/10 text-purple-200'"
          >
            {{ counts.overdue }}
          </span>
        </button>

        <button
          @click="activeTab = 'due_today'"
          class="pb-3 px-3 relative transition-colors flex items-center gap-1.5"
          :class="activeTab === 'due_today' ? 'text-amber-400 border-b-2 border-amber-400 font-bold' : 'text-purple-200/70 hover:text-white'"
        >
          <span>Due Today</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
            :class="activeTab === 'due_today' ? 'bg-amber-500/20 text-amber-300' : 'bg-white/10 text-purple-200'"
          >
            {{ counts.due_today }}
          </span>
        </button>

        <button
          @click="activeTab = 'upcoming'"
          class="pb-3 px-3 relative transition-colors flex items-center gap-1.5"
          :class="activeTab === 'upcoming' ? 'text-vb-cyan border-b-2 border-vb-cyan font-bold' : 'text-purple-200/70 hover:text-white'"
        >
          <span>Upcoming Days</span>
          <span
            class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
            :class="activeTab === 'upcoming' ? 'bg-vb-cyan/20 text-cyan-300' : 'bg-white/10 text-purple-200'"
          >
            {{ counts.upcoming }}
          </span>
        </button>
      </div>

      <!-- Follow-up Items Content -->
      <div class="p-6 overflow-y-auto flex-1 space-y-3 bg-white/[0.02] backdrop-blur-sm">
        <div v-if="loading" class="py-12 text-center text-purple-200/60">
          <Loader2 class="w-7 h-7 animate-spin mx-auto text-vb-magenta mb-2" />
          <p class="text-xs">Checking follow-up schedules...</p>
        </div>

        <div v-else-if="currentList.length === 0" class="py-12 text-center text-purple-200/60">
          <CheckCircle2 class="w-9 h-9 text-emerald-400 mx-auto mb-2" />
          <p class="text-xs font-semibold text-white">No tasks in this category.</p>
          <p class="text-[11px]">All clear for this selection.</p>
        </div>

        <div
          v-else
          v-for="item in currentList"
          :key="item.id"
          class="p-4 rounded-2xl bg-white/[0.06] border border-white/10 shadow-sm hover:border-vb-magenta/40 backdrop-blur-sm transition-all flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
        >
          <div class="space-y-1.5 min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-mono font-bold text-vb-magenta bg-vb-magenta/20 px-2 py-0.5 rounded-md border border-vb-magenta/30">{{ item.lead?.reference_id }}</span>
              <span class="text-sm font-bold text-white">{{ item.lead?.name }}</span>
              <span
                v-if="item.lead?.company_name"
                class="text-[11px] text-purple-200/70 bg-white/10 border border-white/10 px-2.5 py-0.5 rounded-full font-medium"
              >
                {{ item.lead.company_name }}
              </span>
            </div>

            <div class="flex items-center gap-3 text-xs text-purple-200/80 flex-wrap">
              <span class="flex items-center gap-1 font-semibold text-white">
                <Clock class="w-3.5 h-3.5 text-vb-magenta" />
                {{ item.scheduled_at_ist || item.scheduled_at }}
              </span>
              <span v-if="item.lead?.service_interest" class="text-purple-200/70">
                Interest: <strong class="text-white font-semibold">{{ item.lead.service_interest }}</strong>
              </span>
            </div>

            <p v-if="item.notes" class="text-xs text-purple-200/70 italic line-clamp-2 mt-1 leading-relaxed">
              "{{ item.notes }}"
            </p>
          </div>

          <button
            @click="goToLead(item.lead_id)"
            class="btn-primary text-xs font-semibold px-4 py-2 rounded-full shrink-0 flex items-center gap-1.5 shadow-sm"
          >
            <span>Open Lead</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="p-4 bg-white/[0.03] backdrop-blur-sm border-t border-white/10 flex items-center justify-between text-xs">
        <span class="text-purple-200/70 font-medium">
          Total outstanding: <strong class="text-white font-bold">{{ total }} tasks</strong>
        </span>
        <button
          @click="dismissModal"
          class="px-5 py-2 rounded-full font-semibold text-white bg-white/10 hover:bg-white/15 border border-white/15 transition-all shadow-sm"
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

    if (total.value > 0) {
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
    console.error('Failed to load outstanding follow-ups briefing:', err);
  } finally {
    loading.value = false;
  }
};

const dismissModal = () => {
  isOpen.value = false;
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
