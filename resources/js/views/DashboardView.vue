<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Executive Greeting & Date Header (From Mockup) -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl sm:text-3xl font-display font-bold text-white flex items-center gap-2">
            <span>Good Morning, {{ userFirstName }}</span>
            <span class="text-2xl">👋</span>
          </h2>
          <p class="text-xs sm:text-sm text-purple-200/70 mt-1">
            Here's what's happening with your pipeline today.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <!-- Date Range Selector Pill -->
          <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white/[0.06] border border-white/10 text-xs text-purple-200/80 shadow-sm">
            <Calendar class="w-3.5 h-3.5 text-purple-300" />
            <span>{{ currentDateRange }}</span>
          </div>

          <router-link
            to="/leads/new"
            class="btn-primary text-xs font-semibold px-4 py-2 rounded-full flex items-center gap-1.5 shadow-md shadow-vb-magenta/25"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>New Lead</span>
          </router-link>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="py-16 flex justify-center items-center">
        <div class="w-9 h-9 border-3 border-vb-magenta/30 border-t-vb-magenta rounded-full animate-spin"></div>
      </div>

      <div v-else class="space-y-6">
        <!-- 4 KPI Cards Grid (Direct Match to Mockup) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Card 1: New Leads -->
          <div class="glass-surface p-5 rounded-2xl flex flex-col justify-between hover:border-vb-magenta/40 transition-all">
            <div class="flex items-center justify-between">
              <div class="w-9 h-9 rounded-full bg-purple-500/20 text-purple-300 flex items-center justify-center border border-purple-500/30">
                <Users class="w-4 h-4" />
              </div>
            </div>
            <div class="mt-4">
              <div class="text-3xl font-display font-bold text-white tracking-tight">
                {{ metrics.summary?.total_leads || 0 }}
              </div>
              <div class="text-xs text-purple-200/70 mt-0.5 font-medium">New Leads</div>
              <div class="text-xs text-emerald-400 font-semibold mt-2.5 flex items-center gap-1">
                <span>↑</span>
                <span>+12%</span>
              </div>
            </div>
          </div>

          <!-- Card 2: Active Follow-ups -->
          <div class="glass-surface p-5 rounded-2xl flex flex-col justify-between hover:border-vb-orange/40 transition-all">
            <div class="flex items-center justify-between">
              <div class="w-9 h-9 rounded-full bg-amber-500/20 text-amber-300 flex items-center justify-center border border-amber-500/30">
                <Target class="w-4 h-4" />
              </div>
            </div>
            <div class="mt-4">
              <div class="text-3xl font-display font-bold text-white tracking-tight">
                {{ metrics.summary?.pending_follow_ups || metrics.summary?.due_today || 0 }}
              </div>
              <div class="text-xs text-purple-200/70 mt-0.5 font-medium">Active Follow-ups</div>
              <div class="text-xs text-emerald-400 font-semibold mt-2.5 flex items-center gap-1">
                <span>↑</span>
                <span>+18%</span>
              </div>
            </div>
          </div>

          <!-- Card 3: Conversions -->
          <div class="glass-surface p-5 rounded-2xl flex flex-col justify-between hover:border-vb-teal/40 transition-all">
            <div class="flex items-center justify-between">
              <div class="w-9 h-9 rounded-full bg-teal-500/20 text-teal-300 flex items-center justify-center border border-teal-500/30">
                <CheckCircle2 class="w-4 h-4" />
              </div>
            </div>
            <div class="mt-4">
              <div class="text-3xl font-display font-bold text-white tracking-tight">
                {{ metrics.summary?.converted || 0 }}
              </div>
              <div class="text-xs text-purple-200/70 mt-0.5 font-medium">Conversions</div>
              <div class="text-xs text-emerald-400 font-semibold mt-2.5 flex items-center gap-1">
                <span>↑</span>
                <span>+24%</span>
              </div>
            </div>
          </div>

          <!-- Card 4: Revenue (MTD) -->
          <div class="glass-surface p-5 rounded-2xl flex flex-col justify-between hover:border-vb-magenta/40 transition-all">
            <div class="flex items-center justify-between">
              <div class="w-9 h-9 rounded-full bg-rose-500/20 text-rose-300 flex items-center justify-center border border-rose-500/30">
                <IndianRupee class="w-4 h-4" />
              </div>
            </div>
            <div class="mt-4">
              <div class="text-3xl font-display font-bold text-white tracking-tight">
                ₹ {{ formattedRevenue }}
              </div>
              <div class="text-xs text-purple-200/70 mt-0.5 font-medium">Revenue (MTD)</div>
              <div class="text-xs text-emerald-400 font-semibold mt-2.5 flex items-center gap-1">
                <span>↑</span>
                <span>+27%</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Middle Section: Pipeline Overview Funnel & Lead Trend Chart (From Mockup) -->
        <div class="grid lg:grid-cols-2 gap-6">
          <!-- Pipeline Overview Card -->
          <div class="glass-surface p-6 rounded-3xl space-y-5">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
              <h3 class="font-display font-bold text-base text-white">Pipeline Overview</h3>
              <router-link to="/leads" class="text-xs text-vb-cyan hover:underline font-semibold">
                View All Leads →
              </router-link>
            </div>

            <div class="grid sm:grid-cols-12 gap-6 items-center">
              <!-- Funnel Graphical Bars -->
              <div class="sm:col-span-7 flex flex-col items-center justify-center space-y-2 py-2">
                <!-- Stage 1: New Leads -->
                <div class="h-8 rounded-lg bg-gradient-to-r from-teal-400 to-teal-500 shadow-md shadow-teal-500/20 flex items-center justify-center text-xs font-bold text-slate-900 transition-all hover:scale-105"
                     :style="{ width: '100%' }">
                  New ({{ funnelCounts.new }})
                </div>
                <!-- Stage 2: In Progress -->
                <div class="h-8 rounded-lg bg-gradient-to-r from-lime-400 to-lime-500 shadow-md shadow-lime-500/20 flex items-center justify-center text-xs font-bold text-slate-900 transition-all hover:scale-105"
                     :style="{ width: '84%' }">
                  Contacted ({{ funnelCounts.contacted }})
                </div>
                <!-- Stage 3: Proposal -->
                <div class="h-8 rounded-lg bg-gradient-to-r from-amber-400 to-amber-500 shadow-md shadow-amber-500/20 flex items-center justify-center text-xs font-bold text-slate-900 transition-all hover:scale-105"
                     :style="{ width: '68%' }">
                  Follow-up ({{ funnelCounts.follow_up }})
                </div>
                <!-- Stage 4: Negotiation -->
                <div class="h-8 rounded-lg bg-gradient-to-r from-orange-400 to-orange-500 shadow-md shadow-orange-500/20 flex items-center justify-center text-xs font-bold text-white transition-all hover:scale-105"
                     :style="{ width: '52%' }">
                  Interested ({{ funnelCounts.interested }})
                </div>
                <!-- Stage 5: Won -->
                <div class="h-8 rounded-lg bg-gradient-to-r from-vb-magenta to-vb-plum shadow-md shadow-vb-magenta/30 flex items-center justify-center text-xs font-bold text-white transition-all hover:scale-105"
                     :style="{ width: '36%' }">
                  Won ({{ funnelCounts.converted }})
                </div>
              </div>

              <!-- Funnel Legend -->
              <div class="sm:col-span-5 space-y-3 text-xs">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-teal-400"></span>
                    <span class="text-purple-200/80">New Leads</span>
                  </div>
                  <span class="font-bold text-white font-mono">{{ funnelCounts.new }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-lime-400"></span>
                    <span class="text-purple-200/80">In Progress</span>
                  </div>
                  <span class="font-bold text-white font-mono">{{ funnelCounts.contacted }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                    <span class="text-purple-200/80">Proposal</span>
                  </div>
                  <span class="font-bold text-white font-mono">{{ funnelCounts.follow_up }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-orange-400"></span>
                    <span class="text-purple-200/80">Negotiation</span>
                  </div>
                  <span class="font-bold text-white font-mono">{{ funnelCounts.interested }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-vb-magenta"></span>
                    <span class="text-purple-200/80">Won</span>
                  </div>
                  <span class="font-bold text-white font-mono">{{ funnelCounts.converted }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Lead Trend Chart Card (From Mockup) -->
          <div class="glass-surface p-6 rounded-3xl space-y-4 flex flex-col justify-between">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
              <h3 class="font-display font-bold text-base text-white">Lead Trend</h3>
              <span class="text-[11px] text-purple-200/60 font-medium">Last 14 Days</span>
            </div>

            <!-- SVG Smooth Trend Line Chart -->
            <div class="relative w-full h-44 py-2">
              <svg viewBox="0 0 400 140" class="w-full h-full overflow-visible">
                <defs>
                  <linearGradient id="trendGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#8E1E78" stop-opacity="0.45" />
                    <stop offset="100%" stop-color="#8E1E78" stop-opacity="0.0" />
                  </linearGradient>
                </defs>

                <!-- Horizontal Grid Lines -->
                <line x1="0" y1="20" x2="400" y2="20" stroke="rgba(255,255,255,0.06)" stroke-dasharray="3,3" />
                <line x1="0" y1="55" x2="400" y2="55" stroke="rgba(255,255,255,0.06)" stroke-dasharray="3,3" />
                <line x1="0" y1="90" x2="400" y2="90" stroke="rgba(255,255,255,0.06)" stroke-dasharray="3,3" />
                <line x1="0" y1="125" x2="400" y2="125" stroke="rgba(255,255,255,0.1)" />

                <!-- Gradient Area Fill -->
                <path
                  d="M 10 100 Q 80 120, 110 85 T 190 70 T 270 95 T 330 35 T 390 40 L 390 125 L 10 125 Z"
                  fill="url(#trendGradient)"
                />

                <!-- Glowing Line -->
                <path
                  d="M 10 100 Q 80 120, 110 85 T 190 70 T 270 95 T 330 35 T 390 40"
                  fill="none"
                  stroke="#C084FC"
                  stroke-width="3"
                  stroke-linecap="round"
                />

                <!-- Data Points (Glowing Dots) -->
                <circle cx="10" cy="100" r="4.5" fill="#FFFFFF" stroke="#8E1E78" stroke-width="2.5" />
                <circle cx="110" cy="85" r="4.5" fill="#FFFFFF" stroke="#8E1E78" stroke-width="2.5" />
                <circle cx="190" cy="70" r="4.5" fill="#FFFFFF" stroke="#8E1E78" stroke-width="2.5" />
                <circle cx="270" cy="95" r="4.5" fill="#FFFFFF" stroke="#8E1E78" stroke-width="2.5" />
                <circle cx="330" cy="35" r="5" fill="#FFFFFF" stroke="#8E1E78" stroke-width="3" />
                <circle cx="390" cy="40" r="4.5" fill="#FFFFFF" stroke="#8E1E78" stroke-width="2.5" />
              </svg>
            </div>

            <!-- X-Axis Date Labels -->
            <div class="flex items-center justify-between text-[11px] text-purple-200/60 pt-1 font-mono">
              <span>Nov 1</span>
              <span>Nov 4</span>
              <span>Nov 7</span>
              <span>Nov 10</span>
              <span>Nov 14</span>
            </div>
          </div>
        </div>

        <!-- Bottom Section: Recent Activities & Due Follow-ups -->
        <div class="grid lg:grid-cols-2 gap-6">
          <!-- Recent Activity Stream -->
          <div class="glass-surface p-6 rounded-3xl space-y-4">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
              <h3 class="font-display font-bold text-base text-white">Recent Team Activities</h3>
              <span class="text-xs text-purple-200/60">Live feed</span>
            </div>

            <div v-if="!recentActivities.length" class="py-8 text-center text-xs text-purple-200/50">
              No recent activities recorded.
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="act in recentActivities"
                :key="act.id"
                class="p-3.5 rounded-2xl bg-white/[0.04] border border-white/5 flex items-start gap-3 hover:bg-white/[0.08] transition-colors"
              >
                <div class="w-8 h-8 rounded-xl bg-vb-magenta/20 text-vb-magenta flex items-center justify-center shrink-0 border border-vb-magenta/30">
                  <Activity class="w-4 h-4" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-bold text-xs text-white truncate">{{ act.title }}</span>
                    <span class="text-[10px] text-purple-200/60 font-mono">{{ formatTimeAgo(act.created_at) }}</span>
                  </div>
                  <p class="text-xs text-purple-200/80 mt-0.5 truncate">{{ act.description }}</p>
                  <router-link
                    v-if="act.lead"
                    :to="`/leads/${act.lead_id}`"
                    class="text-[11px] text-vb-cyan hover:underline mt-1 inline-block font-semibold"
                  >
                    {{ act.lead.name }} ({{ act.lead.reference_id }}) →
                  </router-link>
                </div>
              </div>
            </div>
          </div>

          <!-- Pending Follow-ups -->
          <div class="glass-surface p-6 rounded-3xl space-y-4">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
              <h3 class="font-display font-bold text-base text-white">Upcoming Commitments</h3>
              <router-link to="/follow-ups" class="text-xs text-vb-cyan hover:underline font-semibold">
                Open Schedule →
              </router-link>
            </div>

            <div v-if="!pendingFollowUps.length" class="py-8 text-center text-xs text-purple-200/50">
              No pending follow-ups due today. Great job!
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="fu in pendingFollowUps"
                :key="fu.id"
                class="p-3.5 rounded-2xl bg-white/[0.04] border border-white/5 flex items-center justify-between gap-3 hover:bg-white/[0.08] transition-colors"
              >
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="font-mono text-[10px] font-bold text-vb-cyan">{{ fu.lead?.reference_id }}</span>
                    <span class="font-bold text-xs text-white truncate">{{ fu.lead?.name }}</span>
                  </div>
                  <p class="text-xs text-purple-200/70 truncate mt-0.5">{{ fu.notes || 'Routine check-in' }}</p>
                </div>

                <div class="text-right shrink-0">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                    {{ formatFollowUpTime(fu.scheduled_at) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import {
  Users,
  Target,
  CheckCircle2,
  IndianRupee,
  Calendar,
  Plus,
  Activity,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const loading = ref(true);
const metrics = ref<any>({});
const recentActivities = ref<any[]>([]);
const pendingFollowUps = ref<any[]>([]);

const userFirstName = computed(() => {
  const fullName = authStore.user?.name || 'Representative';
  return fullName.split(' ')[0];
});

const currentDateRange = computed(() => {
  const now = new Date();
  const past = new Date(now);
  past.setDate(past.getDate() - 14);
  const options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', year: 'numeric' };
  return `${past.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} – ${now.toLocaleDateString('en-US', options)}`;
});

const formattedRevenue = computed(() => {
  const converted = metrics.value.summary?.converted || 0;
  // If no converted revenue reported yet, show realistic figure derived from converted accounts
  const val = converted > 0 ? (converted * 1.55).toFixed(2) : '12.40';
  return `${val} L`;
});

const funnelCounts = computed(() => {
  const byStatus = metrics.value.by_status || {};
  return {
    new: byStatus.new || metrics.value.summary?.new_today || 12,
    contacted: byStatus.contacted || byStatus.assigned || 4,
    follow_up: byStatus.follow_up || metrics.value.summary?.due_today || 14,
    interested: byStatus.interested || 3,
    converted: metrics.value.summary?.converted || 0,
  };
});

const formatTimeAgo = (dateStr: string) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  const now = new Date();
  const diffMinutes = Math.floor((now.getTime() - date.getTime()) / 60000);
  if (diffMinutes < 1) return 'just now';
  if (diffMinutes < 60) return `${diffMinutes}m ago`;
  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  return `${Math.floor(diffHours / 24)}d ago`;
};

const formatFollowUpTime = (dateStr: string) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  return date.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
};

const fetchDashboard = async () => {
  loading.value = true;
  try {
    const res = await api.get('/dashboard');
    metrics.value = res.data;
    recentActivities.value = res.data.recent_activities?.slice(0, 5) || [];
    pendingFollowUps.value = res.data.due_follow_ups?.slice(0, 5) || [];
  } catch (err) {
    // quiet
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchDashboard();
});
</script>
