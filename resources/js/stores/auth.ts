import { defineStore } from 'pinia';
import api from '../api/axios';

export interface UserProfile {
  id: number;
  name: string;
  username?: string;
  email: string;
  phone?: string;
  role: string;
  role_id?: number | null;
  role_name?: string;
  role_display_name?: string;
  team_id?: number | null;
  team_name?: string | null;
  is_active: boolean;
  must_change_password?: boolean;
  permissions?: string[];
  visibility_scope?: 'assigned_only' | 'team_only' | 'all';
  allowed_categories?: string[] | null;
  notification_preferences?: {
    whatsapp_enabled?: boolean;
    email_enabled?: boolean;
    in_app_enabled?: boolean;
    phone_for_notifications?: string;
  };
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('vb_crm_token') || '',
    user: (JSON.parse(localStorage.getItem('vb_crm_user') || 'null') as UserProfile | null),
    unreadCount: 0,
    loading: false,
    error: null as string | null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token && !!state.user,
    isAdmin: (state) => state.user?.role === 'admin' || state.user?.role_name === 'admin',
    isEmployee: (state) => state.user?.role !== 'admin' && state.user?.role_name !== 'admin',
    can: (state) => (permission: string): boolean => {
      if (state.user?.role === 'admin' || state.user?.role_name === 'admin') return true;
      return (state.user?.permissions || []).includes(permission);
    },
    canAny: (state) => (permissions: string[]): boolean => {
      if (state.user?.role === 'admin' || state.user?.role_name === 'admin') return true;
      return permissions.some(p => (state.user?.permissions || []).includes(p));
    },
    canAll: (state) => (permissions: string[]): boolean => {
      if (state.user?.role === 'admin' || state.user?.role_name === 'admin') return true;
      return permissions.every(p => (state.user?.permissions || []).includes(p));
    },
  },

  actions: {
    async login(credentials: { email?: string; login?: string; password: string }) {
      this.loading = true;
      this.error = null;
      try {
        const payload = {
          login: credentials.login || credentials.email,
          email: credentials.email || credentials.login,
          password: credentials.password,
        };
        const response = await api.post('/auth/login', payload);
        this.token = response.data.token;
        this.user = response.data.user;

        localStorage.setItem('vb_crm_token', this.token);
        localStorage.setItem('vb_crm_user', JSON.stringify(this.user));

        // Reset session flags on fresh login so briefing and active alerts appear
        try {
          sessionStorage.removeItem('vb_crm_login_popup_seen');
          sessionStorage.removeItem('vb_crm_dismissed_alerts');
        } catch (e) {
          // ignore
        }

        await this.fetchMe();
        return response.data;
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Login failed. Please check credentials.';
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async fetchMe() {
      if (!this.token) return;
      try {
        const response = await api.get('/auth/me');
        this.user = response.data.user;
        this.unreadCount = response.data.unread_notifications_count || 0;
        localStorage.setItem('vb_crm_user', JSON.stringify(this.user));
      } catch (err) {
        // Interceptor handles 401
      }
    },

    async updateProfile(payload: Partial<UserProfile & { password?: string; password_confirmation?: string }>) {
      const response = await api.put('/auth/profile', payload);
      this.user = response.data.user;
      localStorage.setItem('vb_crm_user', JSON.stringify(this.user));
      return response.data;
    },

    async logout() {
      try {
        await api.post('/auth/logout');
      } catch (err) {
        // ignore
      } finally {
        this.token = '';
        this.user = null;
        localStorage.removeItem('vb_crm_token');
        localStorage.removeItem('vb_crm_user');
        try {
          sessionStorage.removeItem('vb_crm_login_popup_seen');
          sessionStorage.removeItem('vb_crm_dismissed_alerts');
        } catch (e) {
          // ignore
        }
      }
    },
  },
});
