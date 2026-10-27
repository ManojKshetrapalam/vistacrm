<template>
  <AppLayout>
    <div class="space-y-6 max-w-5xl mx-auto">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-widest text-vb-violet font-display">Communication &amp; Audit</span>
          <h2 class="text-2xl font-display font-bold text-vb-plum mt-1">Notification &amp; Delivery Center</h2>
          <p class="text-xs text-vb-muted mt-0.5">
            Real-time internal assignment alerts and external communication delivery logs.
          </p>
        </div>

        <button
          v-if="activeTab === 'in_app' && unreadCount > 0"
          @click="markAllAsRead"
          class="px-4 py-2 bg-white border border-vb-plum/20 hover:border-vb-plum text-vb-plum rounded-full text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-sm self-start"
        >
          <CheckCheck class="w-3.5 h-3.5 text-vb-violet" />
          <span>Mark All Read</span>
        </button>
      </div>

      <!-- Tabs Bar -->
      <div class="flex items-center gap-3 border-b border-vb-plum/10 pb-3 text-xs font-semibold">
        <button
          @click="activeTab = 'in_app'"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'in_app' ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <span>My Alerts</span>
          <span v-if="unreadCount > 0" class="px-1.5 py-0.2 rounded-full text-[10px] bg-vb-violet text-white">
            {{ unreadCount }}
          </span>
        </button>

        <button
          v-if="authStore.isAdmin"
          @click="activeTab = 'delivery_logs'"
          class="px-4 py-2 rounded-full transition-all flex items-center gap-2"
          :class="activeTab === 'delivery_logs' ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-muted hover:text-vb-plum hover:bg-white'"
        >
          <Radio class="w-3.5 h-3.5" />
          <span>Full Delivery Audit Logs</span>
        </button>
      </div>

      <!-- TAB 1: In-App Alerts -->
      <div v-if="activeTab === 'in_app'" class="card-glass overflow-hidden">
        <div v-if="loading" class="py-16 flex justify-center">
          <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
        </div>

        <div v-else-if="notifications.length === 0" class="py-16 text-center">
          <Bell class="w-10 h-10 text-vb-muted mx-auto mb-2 opacity-50" />
          <p class="text-xs text-vb-muted">No notifications right now.</p>
        </div>

        <div v-else class="divide-y divide-vb-plum/5">
          <div
            v-for="item in notifications"
            :key="item.id"
            class="p-4 sm:p-5 flex items-start justify-between gap-4 transition-colors"
            :class="item.is_read ? 'bg-white/40' : 'bg-vb-cream/80 border-l-4 border-vb-violet'"
          >
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="font-bold text-sm text-vb-plum font-display">{{ item.title }}</span>
                <span v-if="!item.is_read" class="px-1.5 py-0.2 text-[10px] bg-vb-violet text-white rounded font-bold uppercase tracking-wider">
                  New
                </span>
              </div>
              <p class="text-xs text-vb-ink/90 leading-relaxed">{{ item.message }}</p>

              <div class="flex items-center gap-3 pt-1 text-[11px]">
                <span class="text-vb-muted">{{ formatDateTime(item.created_at) }}</span>
                <router-link
                  v-if="item.lead"
                  :to="`/leads/${item.lead.id}`"
                  class="font-semibold text-vb-violet hover:underline"
                >
                  View Lead ({{ item.lead.reference_id }}) →
                </router-link>
              </div>
            </div>

            <button
              v-if="!item.is_read"
              @click="markOneAsRead(item)"
              class="p-1.5 text-vb-muted hover:text-vb-plum rounded-lg"
              title="Mark as read"
            >
              <Check class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- TAB 2: System Delivery Audit Logs (Admin Only) -->
      <div v-if="activeTab === 'delivery_logs' && authStore.isAdmin" class="space-y-4">
        <!-- Filter Bar -->
        <div class="card-glass p-4 flex flex-wrap items-center justify-between gap-3 text-xs">
          <div class="flex flex-wrap items-center gap-3">
            <select v-model="logFilters.channel" @change="fetchLogs" class="field text-xs w-auto">
              <option value="">All Channels (WhatsApp / In-app)</option>
              <option value="whatsapp">WhatsApp Outreach</option>
              <option value="in_app">Internal In-App Alerts</option>
            </select>

            <select v-model="logFilters.status" @change="fetchLogs" class="field text-xs w-auto">
              <option value="">All Statuses</option>
              <option value="sent">Sent / Dispatched</option>
              <option value="delivered">Delivered</option>
              <option value="failed">Failed</option>
            </select>
          </div>

          <div class="relative min-w-[200px]">
            <Search class="w-3.5 h-3.5 text-vb-muted absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="logFilters.search"
              @input="fetchLogs"
              placeholder="Search recipient..."
              class="field pl-8 text-xs"
            />
          </div>
        </div>

        <div class="card-glass overflow-hidden">
          <div v-if="logsLoading" class="py-16 flex justify-center">
            <div class="w-8 h-8 border-3 border-vb-plum/20 border-t-vb-plum rounded-full animate-spin"></div>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-vb-cream/80 border-b border-vb-plum/10 text-vb-plum font-semibold">
                <tr>
                  <th class="py-3 px-4">Channel</th>
                  <th class="py-3 px-4">Recipient</th>
                  <th class="py-3 px-4">Title &amp; Message Snippet</th>
                  <th class="py-3 px-4">Delivery Status</th>
                  <th class="py-3 px-4">Dispatched At</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-vb-plum/5">
                <tr v-for="log in deliveryLogs" :key="log.id" class="hover:bg-white/70">
                  <td class="py-3 px-4">
                    <span
                      class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                      :class="log.channel === 'whatsapp' ? 'bg-emerald-100 text-emerald-800' : 'bg-vb-plum/10 text-vb-plum'"
                    >
                      {{ log.channel }}
                    </span>
                  </td>
                  <td class="py-3 px-4 font-mono font-bold text-vb-ink">{{ log.recipient }}</td>
                  <td class="py-3 px-4 max-w-xs">
                    <span class="font-semibold text-vb-plum block">{{ log.title }}</span>
                    <span class="text-[11px] text-vb-muted truncate block">{{ log.message }}</span>
                  </td>
                  <td class="py-3 px-4">
                    <span
                      class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase"
                      :class="log.status === 'sent' || log.status === 'delivered' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                    >
                      {{ log.status }}
                    </span>
                  </td>
                  <td class="py-3 px-4 text-vb-muted text-[11px]">{{ formatDateTime(log.created_at) }}</td>
                </tr>
                <tr v-if="deliveryLogs.length === 0">
                  <td colspan="5" class="py-6 text-center text-vb-muted italic">No logs found matching filters.</td>
                </tr>
              </tbody>
            </table>
          </div>
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
import {
  CheckCheck,
  Check,
  Bell,
  Radio,
  Search,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const activeTab = ref('in_app');

const notifications = ref<any[]>([]);
const unreadCount = ref(0);
const loading = ref(true);

const deliveryLogs = ref<any[]>([]);
const logsLoading = ref(false);

const logFilters = reactive({
  channel: '',
  status: '',
  search: '',
});

const fetchNotifications = async () => {
  loading.value = true;
  try {
    const res = await api.get('/notifications');
    notifications.value = res.data.notifications.data;
    unreadCount.value = res.data.unread_count;
    authStore.unreadCount = unreadCount.value;
  } catch (err) {
    console.error('Failed to load notifications', err);
  } finally {
    loading.value = false;
  }
};

const markOneAsRead = async (item: any) => {
  try {
    await api.post(`/notifications/${item.id}/read`);
    item.is_read = true;
    if (unreadCount.value > 0) unreadCount.value--;
    authStore.unreadCount = unreadCount.value;
  } catch (err) {
    // quiet
  }
};

const markAllAsRead = async () => {
  try {
    await api.post('/notifications/read-all');
    notifications.value.forEach((n) => (n.is_read = true));
    unreadCount.value = 0;
    authStore.unreadCount = 0;
  } catch (err) {
    // quiet
  }
};

const fetchLogs = async () => {
  logsLoading.value = true;
  try {
    const res = await api.get('/notifications/delivery-logs', {
      params: logFilters,
    });
    deliveryLogs.value = res.data.data;
  } catch (err) {
    console.error('Failed to load delivery logs', err);
  } finally {
    logsLoading.value = false;
  }
};

const formatDateTime = (dateStr: string) => {
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
  fetchNotifications();
  if (authStore.isAdmin) {
    fetchLogs();
  }
});
</script>
