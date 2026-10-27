<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Welcome Banner -->
      <div class="card-glass p-6 sm:p-8 bg-gradient-to-r from-white via-white to-vb-violet/5 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <span class="text-xs uppercase font-bold tracking-widest text-vb-violet font-display">
            {{ authStore.isAdmin ? 'Executive CRM Console' : 'Solutions Representative Desk' }}
          </span>
          <h2 class="text-2xl sm:text-3xl font-display font-bold text-vb-plum mt-1">
            Welcome back, {{ authStore.user?.name }}
          </h2>
          <p class="text-sm text-vb-muted mt-1">
            {{ authStore.isAdmin ? 'Overall enterprise lead pipeline and employee activity status.' : 'Your assigned accounts and daily follow-up commitments.' }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <router-link
            to="/leads/new"
            class="btn-primary text-xs font-semibold px-4 py-2.5 rounded-full flex items-center gap-2"
          >
            <Plus class="w-4 h-4" />
            <span>Create Lead</span>
          </router-link>
          <router-link
            v-if="authStore.isAdmin"
            to="/import"
            class="btn-ghost text-xs font-semibold px-4 py-2 rounded-full flex items-center gap-2"
          >
            <FileSpreadsheet class="w-4 h-4" />
            <span>Import Excel/CSV</span>
          </router-link>
          <router-link
            to="/follow-ups"
            class="px-4 py-2 rounded-full text-xs font-semibold bg-white border border-vb-plum/15 text-vb-plum hover:bg-vb-cream flex items-center gap-2"
          >
            <CalendarClock class="w-4 h-4 text-vb-amber" />
            <span>Today's Schedule</span>
          </router-link>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="py-12 flex justify-center items-center">
        <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
      </div>

      <div v-else class="space-y-6">
        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
          <!-- Total Leads -->
          <div class="card-glass p-5 hover:translate-y-[-2px] transition-transform">
            <div class="flex items-center justify-between text-vb-muted text-xs font-medium">
              <span>{{ authStore.isAdmin ? 'Total Leads' : 'My Leads' }}</span>
              <Users class="w-4 h-4 text-vb-plum" />
            </div>
            <div class="mt-2 text-2xl font-display font-bold text-vb-plum">
              {{ metrics.summary?.total_leads || 0 }}
            </div>
            <div class="mt-1 text-[11px] text-vb-muted">
              Active in CRM pipeline
            </div>
          </div>

          <!-- New Today -->
          <div class="card-glass p-5 hover:translate-y-[-2px] transition-transform">
            <div class="flex items-center justify-between text-vb-muted text-xs font-medium">
              <span>New Today</span>
              <Sparkles class="w-4 h-4 text-vb-cyan" />
            </div>
            <div class="mt-2 text-2xl font-display font-bold text-vb-cyan">
              {{ metrics.summary?.new_today || 0 }}
            </div>
            <div class="mt-1 text-[11px] text-vb-muted">
              Intake past 24 hours
            </div>
          </div>

          <!-- Follow-ups Due Today -->
          <div class="card-glass p-5 hover:translate-y-[-2px] transition-transform">
            <div class="flex items-center justify-between text-vb-muted text-xs font-medium">
              <span>Due Today</span>
              <CalendarClock class="w-4 h-4 text-vb-amber" />
            </div>
            <div class="mt-2 text-2xl font-display font-bold text-vb-amber">
              {{ metrics.summary?.due_today || 0 }}
            </div>
            <div class="mt-1 text-[11px] text-vb-muted">
              Requires contact today
            </div>
          </div>

          <!-- Overdue Follow-ups -->
          <div
            class="card-glass p-5 hover:translate-y-[-2px] transition-transform"
            :class="(metrics.summary?.overdue || 0) > 0 ? 'bg-rose-50/50 border-rose-200' : ''"
          >
            <div class="flex items-center justify-between text-xs font-medium" :class="(metrics.summary?.overdue || 0) > 0 ? 'text-rose-700' : 'text-vb-muted'">
              <span>Overdue</span>
              <AlertTriangle class="w-4 h-4" :class="(metrics.summary?.overdue || 0) > 0 ? 'text-rose-600' : 'text-vb-muted'" />
            </div>
            <div class="mt-2 text-2xl font-display font-bold" :class="(metrics.summary?.overdue || 0) > 0 ? 'text-rose-600' : 'text-vb-plum'">
              {{ metrics.summary?.overdue || 0 }}
            </div>
            <div class="mt-1 text-[11px]" :class="(metrics.summary?.overdue || 0) > 0 ? 'text-rose-600' : 'text-vb-muted'">
              Past scheduled time
            </div>
          </div>

          <!-- Converted & Rate -->
          <div class="card-glass p-5 hover:translate-y-[-2px] transition-transform col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between text-vb-muted text-xs font-medium">
              <span>Converted</span>
              <Award class="w-4 h-4 text-vb-emerald" />
            </div>
            <div class="mt-2 text-2xl font-display font-bold text-vb-emerald">
              {{ metrics.summary?.converted_leads || 0 }}
            </div>
            <div class="mt-1 text-[11px] text-vb-muted">
              {{ metrics.summary?.conversion_rate || 0 }}% conversion rate
            </div>
          </div>
        </div>

        <!-- Pipeline Status Breakdown -->
        <div class="card-glass p-6">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="font-display font-bold text-base text-vb-plum">Pipeline Stage Distribution</h3>
              <p class="text-xs text-vb-muted">Real-time status breakdown across active leads</p>
            </div>
            <router-link to="/leads" class="text-xs font-semibold text-vb-violet hover:underline">
              View all leads →
            </router-link>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
            <div
              v-for="status in statusList"
              :key="status.key"
              class="p-3 rounded-xl border bg-white/60 flex flex-col items-center text-center"
              :class="status.border"
            >
              <span class="text-xs font-bold text-vb-plum font-display">{{ metrics.status_breakdown?.[status.key] || 0 }}</span>
              <span class="text-[10px] font-semibold uppercase tracking-wider text-vb-muted mt-0.5">{{ status.label }}</span>
            </div>
          </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
          <!-- Lead Sources Breakdown -->
          <div class="card-glass p-6">
            <h3 class="font-display font-bold text-base text-vb-plum mb-1">Lead Inflow by Source</h3>
            <p class="text-xs text-vb-muted mb-4">Where Vistabound clients originate</p>

            <div class="space-y-3">
              <div
                v-for="(count, src) in metrics.source_breakdown"
                :key="src"
                class="flex items-center justify-between text-xs"
              >
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-vb-violet"></span>
                  <span class="font-medium text-vb-ink">{{ src }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="font-bold text-vb-plum">{{ count }}</span>
                  <span class="text-vb-muted text-[11px]">({{ getPercentage(count, metrics.summary?.total_leads) }}%)</span>
                </div>
              </div>
              <div v-if="!metrics.source_breakdown || Object.keys(metrics.source_breakdown).length === 0" class="text-xs text-vb-muted italic py-3 text-center">
                No lead source data yet.
              </div>
            </div>
          </div>

          <!-- Employee Performance (Admin Only) or Quick Schedule (Employee) -->
          <div class="card-glass p-6 lg:col-span-2">
            <div v-if="authStore.isAdmin">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h3 class="font-display font-bold text-base text-vb-plum">Solutions Desk Team Performance</h3>
                  <p class="text-xs text-vb-muted">Account allocation and pipeline outcomes by employee</p>
                </div>
                <router-link to="/employees" class="text-xs font-semibold text-vb-violet hover:underline">
                  Manage team →
                </router-link>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                  <thead class="bg-vb-cream/60 border-b border-vb-plum/10 text-vb-plum font-semibold">
                    <tr>
                      <th class="py-2.5 px-3">Employee</th>
                      <th class="py-2.5 px-3 text-center">Assigned</th>
                      <th class="py-2.5 px-3 text-center">Due Today</th>
                      <th class="py-2.5 px-3 text-center">Overdue</th>
                      <th class="py-2.5 px-3 text-center">Converted</th>
                      <th class="py-2.5 px-3 text-right">Win Rate</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-vb-plum/5">
                    <tr v-for="emp in metrics.employee_stats" :key="emp.id" class="hover:bg-white/50">
                      <td class="py-2.5 px-3">
                        <span class="font-semibold text-vb-plum block">{{ emp.name }}</span>
                        <span class="text-[10px] text-vb-muted">{{ emp.email }}</span>
                      </td>
                      <td class="py-2.5 px-3 text-center font-bold">{{ emp.assigned_leads }}</td>
                      <td class="py-2.5 px-3 text-center font-semibold text-vb-amber">{{ emp.due_today }}</td>
                      <td class="py-2.5 px-3 text-center font-bold" :class="emp.overdue > 0 ? 'text-rose-600' : 'text-vb-muted'">
                        {{ emp.overdue }}
                      </td>
                      <td class="py-2.5 px-3 text-center font-bold text-vb-emerald">{{ emp.converted_leads }}</td>
                      <td class="py-2.5 px-3 text-right font-bold text-vb-violet">{{ emp.conversion_rate }}%</td>
                    </tr>
                    <tr v-if="!metrics.employee_stats || metrics.employee_stats.length === 0">
                      <td colspan="6" class="py-4 text-center text-vb-muted italic">No employee data.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Employee View: Activity Stream -->
            <div v-else>
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h3 class="font-display font-bold text-base text-vb-plum">Recent Activity Timeline</h3>
                  <p class="text-xs text-vb-muted">Latest calls, WhatsApp dispatches, and status updates</p>
                </div>
              </div>

              <div class="space-y-3">
                <div
                  v-for="act in metrics.recent_activities"
                  :key="act.id"
                  class="p-3 bg-white/70 rounded-xl border border-vb-plum/10 flex items-start justify-between gap-3 text-xs"
                >
                  <div class="space-y-0.5">
                    <div class="flex items-center gap-2">
                      <span class="font-bold text-vb-plum">{{ act.title }}</span>
                      <router-link
                        v-if="act.lead"
                        :to="`/leads/${act.lead.id}`"
                        class="text-[11px] text-vb-violet hover:underline font-semibold"
                      >
                        {{ act.lead.name }} ({{ act.lead.reference_id }})
                      </router-link>
                    </div>
                    <p class="text-vb-muted text-[11px]">{{ act.description }}</p>
                  </div>
                  <span class="text-[10px] text-vb-muted shrink-0">{{ formatDate(act.created_at) }}</span>
                </div>
                <div v-if="!metrics.recent_activities || metrics.recent_activities.length === 0" class="text-center py-4 text-vb-muted text-xs italic">
                  No recent activities recorded.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Global Recent Activity Stream (For Admin) -->
        <div v-if="authStore.isAdmin" class="card-glass p-6">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="font-display font-bold text-base text-vb-plum">System Activity Stream</h3>
              <p class="text-xs text-vb-muted">Live trail of lead engagements across all representatives</p>
            </div>
          </div>

          <div class="grid md:grid-cols-2 gap-3">
            <div
              v-for="act in metrics.recent_activities"
              :key="act.id"
              class="p-3.5 bg-white/70 rounded-xl border border-vb-plum/10 flex items-start justify-between gap-3 text-xs"
            >
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="font-semibold text-vb-plum">{{ act.title }}</span>
                  <router-link
                    v-if="act.lead"
                    :to="`/leads/${act.lead.id}`"
                    class="text-[11px] text-vb-violet hover:underline font-bold"
                  >
                    {{ act.lead.name }}
                  </router-link>
                </div>
                <p class="text-vb-muted text-[11px]">{{ act.description }}</p>
                <div class="text-[10px] text-vb-plum/70 flex items-center gap-2">
                  <span>By: {{ act.user?.name || 'System' }}</span>
                  <span>·</span>
                  <span>{{ formatDate(act.created_at) }}</span>
                </div>
              </div>
            </div>
            <div v-if="!metrics.recent_activities || metrics.recent_activities.length === 0" class="col-span-2 text-center py-4 text-vb-muted text-xs italic">
              No recent activity logs available.
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import AppLayout from '../components/AppLayout.vue';
import {
  Users,
  Sparkles,
  CalendarClock,
  AlertTriangle,
  Award,
  Plus,
  FileSpreadsheet,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const loading = ref(true);
const metrics = ref<any>({});

const statusList = [
  { key: 'new', label: 'New', border: 'border-cyan-200' },
  { key: 'assigned', label: 'Assigned', border: 'border-purple-200' },
  { key: 'contacted', label: 'Contacted', border: 'border-amber-200' },
  { key: 'follow_up', label: 'Follow-up', border: 'border-indigo-200' },
  { key: 'interested', label: 'Interested', border: 'border-teal-200' },
  { key: 'converted', label: 'Converted', border: 'border-emerald-200' },
  { key: 'lost', label: 'Lost', border: 'border-rose-200' },
  { key: 'not_reachable', label: 'Unreachable', border: 'border-gray-200' },
];

const fetchMetrics = async () => {
  loading.value = true;
  try {
    const res = await api.get('/dashboard/metrics');
    metrics.value = res.data;
  } catch (err) {
    console.error('Failed to load metrics', err);
  } finally {
    loading.value = false;
  }
};

const getPercentage = (count: number, total: number) => {
  if (!total || total === 0) return 0;
  return Math.round((count / total) * 100);
};

const formatDate = (dateStr: string) => {
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
  fetchMetrics();
});
</script>
