<template>
  <div class="min-h-screen bg-vb-cream flex flex-col font-sans">
    <!-- Top Brand Spectrum Bar -->
    <div class="spectrum h-1 w-full fixed top-0 left-0 z-50"></div>

    <div class="flex-1 flex pt-1">
      <!-- Mobile Sidebar Overlay -->
      <div
        v-if="mobileSidebarOpen"
        class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
        @click="mobileSidebarOpen = false"
      ></div>

      <!-- Sidebar -->
      <aside
        class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-vb-plum/10 flex flex-col transition-transform duration-300 ease-in-out lg:static lg:translate-x-0"
        :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
      >
        <!-- Logo Area -->
        <div class="p-5 border-b border-vb-plum/10 flex items-center justify-between">
          <router-link to="/dashboard" class="flex items-center gap-3">
            <img
              src="/favicon.jpg"
              alt="Vistabound Logo"
              class="h-10 w-10 rounded-xl object-contain shadow-sm border border-vb-plum/10"
            />
            <div class="leading-tight">
              <span class="block font-display font-bold text-base tracking-tight text-vb-plum">VISTABOUND</span>
              <span class="block text-[10px] tracking-[0.18em] uppercase text-vb-muted font-medium">Solutions LLP</span>
            </div>
          </router-link>
          <button
            class="lg:hidden p-1.5 rounded-lg text-vb-muted hover:text-vb-plum"
            @click="mobileSidebarOpen = false"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Role Pill in Sidebar -->
        <div class="px-5 py-3 bg-vb-cream/50 border-b border-vb-plum/5 flex items-center justify-between">
          <div class="text-xs text-vb-muted">Role:</div>
          <span
            class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
            :class="authStore.isAdmin ? 'bg-vb-plum text-white' : 'bg-vb-teal/10 text-vb-teal'"
          >
            {{ authStore.isAdmin ? 'Administrator' : 'Solutions Desk' }}
          </span>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          <router-link
            to="/dashboard"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/dashboard') ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-ink/80 hover:bg-vb-plum/5 hover:text-vb-plum'"
            @click="mobileSidebarOpen = false"
          >
            <LayoutDashboard class="w-4 h-4" />
            <span>Dashboard</span>
          </router-link>

          <router-link
            to="/leads"
            class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/leads') ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-ink/80 hover:bg-vb-plum/5 hover:text-vb-plum'"
            @click="mobileSidebarOpen = false"
          >
            <div class="flex items-center gap-3">
              <Users class="w-4 h-4" />
              <span>Lead Pipeline</span>
            </div>
          </router-link>

          <router-link
            to="/follow-ups"
            class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/follow-ups') ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-ink/80 hover:bg-vb-plum/5 hover:text-vb-plum'"
            @click="mobileSidebarOpen = false"
          >
            <div class="flex items-center gap-3">
              <CalendarClock class="w-4 h-4" />
              <span>Follow-ups</span>
            </div>
            <span
              v-if="dueFollowupsCount > 0"
              class="px-1.5 py-0.5 text-[10px] font-bold rounded-full"
              :class="isActive('/follow-ups') ? 'bg-white/20 text-white' : 'bg-vb-amber text-white'"
            >
              {{ dueFollowupsCount }}
            </span>
          </router-link>

          <div v-if="authStore.isAdmin" class="pt-4 pb-1 px-3">
            <span class="text-[10px] font-bold uppercase tracking-widest text-vb-muted/70">Administration</span>
          </div>

          <router-link
            v-if="authStore.isAdmin"
            to="/import"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/import') ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-ink/80 hover:bg-vb-plum/5 hover:text-vb-plum'"
            @click="mobileSidebarOpen = false"
          >
            <FileSpreadsheet class="w-4 h-4" />
            <span>Import Excel / CSV</span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin"
            to="/employees"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/employees') ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-ink/80 hover:bg-vb-plum/5 hover:text-vb-plum'"
            @click="mobileSidebarOpen = false"
          >
            <UserCheck class="w-4 h-4" />
            <span>Employee Accounts</span>
          </router-link>

          <div class="pt-4 pb-1 px-3">
            <span class="text-[10px] font-bold uppercase tracking-widest text-vb-muted/70">System</span>
          </div>

          <router-link
            to="/notifications"
            class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/notifications') ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-ink/80 hover:bg-vb-plum/5 hover:text-vb-plum'"
            @click="mobileSidebarOpen = false"
          >
            <div class="flex items-center gap-3">
              <BellRing class="w-4 h-4" />
              <span>Notifications & Logs</span>
            </div>
            <span
              v-if="authStore.unreadCount > 0"
              class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-vb-violet text-white"
            >
              {{ authStore.unreadCount }}
            </span>
          </router-link>

          <router-link
            v-if="authStore.isAdmin"
            to="/settings"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium text-sm transition-all"
            :class="isActive('/settings') ? 'bg-vb-plum text-white shadow-sm' : 'text-vb-ink/80 hover:bg-vb-plum/5 hover:text-vb-plum'"
            @click="mobileSidebarOpen = false"
          >
            <Settings class="w-4 h-4" />
            <span>CRM & Templates</span>
          </router-link>
        </nav>

        <!-- User Profile Card in Sidebar Footer -->
        <div class="p-4 border-t border-vb-plum/10 bg-vb-cream/30">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-9 h-9 rounded-full bg-vb-plum text-white flex items-center justify-center font-display font-bold text-sm shrink-0">
                {{ userInitials }}
              </div>
              <div class="truncate">
                <p class="text-sm font-bold text-vb-plum truncate">{{ authStore.user?.name }}</p>
                <p class="text-xs text-vb-muted truncate">{{ authStore.user?.email }}</p>
              </div>
            </div>
            <button
              title="Logout"
              class="p-1.5 text-vb-muted hover:text-rose-600 rounded-lg transition-colors"
              @click="handleLogout"
            >
              <LogOut class="w-4 h-4" />
            </button>
          </div>
        </div>
      </aside>

      <!-- Main Content Container -->
      <div class="flex-1 flex flex-col min-w-0">
        <!-- Topbar -->
        <header class="h-16 bg-white/80 backdrop-blur border-b border-vb-plum/10 sticky top-1 z-30 px-4 sm:px-8 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <button
              class="lg:hidden p-2 rounded-xl text-vb-plum hover:bg-vb-cream"
              @click="mobileSidebarOpen = true"
            >
              <Menu class="w-5 h-5" />
            </button>
            <h1 class="font-display font-bold text-lg text-vb-plum hidden sm:block">
              {{ currentTitle }}
            </h1>
          </div>

          <!-- Topbar Actions -->
          <div class="flex items-center gap-3">
            <router-link
              to="/leads/new"
              class="btn-primary text-xs font-semibold px-4 py-2 rounded-full flex items-center gap-1.5"
            >
              <Plus class="w-3.5 h-3.5" />
              <span>Add Lead</span>
            </router-link>

            <!-- Notifications Drawer Trigger -->
            <router-link
              to="/notifications"
              class="relative p-2 text-vb-plum/80 hover:text-vb-plum hover:bg-vb-cream rounded-xl transition-colors"
              title="Notifications"
            >
              <Bell class="w-5 h-5" />
              <span
                v-if="authStore.unreadCount > 0"
                class="absolute top-1 right-1 w-2.5 h-2.5 bg-vb-violet rounded-full ring-2 ring-white"
              ></span>
            </router-link>
          </div>
        </header>

        <!-- Dynamic Page View -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
          <slot />
        </main>
      </div>
    </div>

    <!-- Outstanding Follow-up Briefing (on login/session load) -->
    <LoginFollowUpModal />

    <!-- Active Real-time Reminder Alert Modal -->
    <ActiveReminderModal />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../api/axios';
import LoginFollowUpModal from './LoginFollowUpModal.vue';
import ActiveReminderModal from './ActiveReminderModal.vue';
import {
  LayoutDashboard,
  Users,
  CalendarClock,
  FileSpreadsheet,
  UserCheck,
  BellRing,
  Settings,
  LogOut,
  Menu,
  X,
  Plus,
  Bell,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const mobileSidebarOpen = ref(false);
const dueFollowupsCount = ref(0);

const isActive = (path: string) => {
  return route.path === path || (path !== '/dashboard' && route.path.startsWith(path));
};

const currentTitle = computed(() => {
  if (route.path.startsWith('/leads/new')) return 'Create New Lead';
  if (route.path.startsWith('/leads/')) return 'Lead Details & Timeline';
  if (route.path.startsWith('/leads')) return 'Lead Management Pipeline';
  if (route.path.startsWith('/follow-ups')) return 'Follow-up Schedule & Tasks';
  if (route.path.startsWith('/import')) return 'Batch Lead Import';
  if (route.path.startsWith('/employees')) return 'Employee Directory';
  if (route.path.startsWith('/notifications')) return 'Notification Delivery Center';
  if (route.path.startsWith('/settings')) return 'CRM Configuration & Templates';
  return 'Operational Overview';
});

const userInitials = computed(() => {
  if (!authStore.user?.name) return 'VB';
  const parts = authStore.user.name.split(' ');
  return parts.length > 1
    ? (parts[0][0] + parts[1][0]).toUpperCase()
    : parts[0].slice(0, 2).toUpperCase();
});

const fetchQuickStats = async () => {
  try {
    const res = await api.get('/follow-ups?tab=today&per_page=1');
    dueFollowupsCount.value = (res.data.counts?.today || 0) + (res.data.counts?.overdue || 0);
  } catch (err) {
    // quiet
  }
};

const handleLogout = async () => {
  await authStore.logout();
  router.push('/login');
};

onMounted(() => {
  fetchQuickStats();
  authStore.fetchMe();
});
</script>
