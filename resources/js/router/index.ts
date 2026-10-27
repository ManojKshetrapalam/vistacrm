import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '../stores/auth';

import LoginView from '../views/LoginView.vue';
import DashboardView from '../views/DashboardView.vue';
import LeadsListView from '../views/LeadsListView.vue';
import LeadCreateView from '../views/LeadCreateView.vue';
import LeadDetailView from '../views/LeadDetailView.vue';
import FollowUpsView from '../views/FollowUpsView.vue';
import ImportWizardView from '../views/ImportWizardView.vue';
import EmployeesView from '../views/EmployeesView.vue';
import NotificationsView from '../views/NotificationsView.vue';
import SettingsView from '../views/SettingsView.vue';

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { guestOnly: true },
  },
  {
    path: '/',
    redirect: '/dashboard',
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    meta: { requiresAuth: true },
  },
  {
    path: '/leads',
    name: 'leads',
    component: LeadsListView,
    meta: { requiresAuth: true },
  },
  {
    path: '/leads/new',
    name: 'leads-create',
    component: LeadCreateView,
    meta: { requiresAuth: true },
  },
  {
    path: '/leads/:id',
    name: 'lead-detail',
    component: LeadDetailView,
    meta: { requiresAuth: true },
  },
  {
    path: '/follow-ups',
    name: 'follow-ups',
    component: FollowUpsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/import',
    name: 'import',
    component: ImportWizardView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/employees',
    name: 'employees',
    component: EmployeesView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/notifications',
    name: 'notifications',
    component: NotificationsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/settings',
    name: 'settings',
    component: SettingsView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();

  if (authStore.token && !authStore.user) {
    await authStore.fetchMe();
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } });
  }

  if (to.meta.guestOnly && authStore.isAuthenticated) {
    return next({ name: 'dashboard' });
  }

  if (to.meta.requiresAdmin && !authStore.isAdmin) {
    return next({ name: 'dashboard' });
  }

  next();
});

export default router;
