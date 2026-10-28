import { defineStore } from 'pinia';
import api from '../api/axios';
import { useAuthStore } from './auth';

export interface ActiveAlert {
  id: number;
  event_type: 'follow_up_reminder' | 'new_lead' | 'lead_reassigned' | 'unassigned_lead' | 'general';
  title: string;
  message: string;
  lead_id?: number;
  follow_up_id?: number;
  lead?: {
    id: number;
    reference_id: string;
    name: string;
    phone: string;
    company_name?: string;
    service_interest?: string;
    status: string;
  };
  follow_up?: {
    id: number;
    scheduled_at: string;
    scheduled_at_ist: string;
    notes?: string;
    status: string;
  };
  metadata?: Record<string, any>;
  created_at_ist?: string;
}

export const useNotificationStore = defineStore('notification', {
  state: () => {
    // Load previously dismissed alert IDs from sessionStorage to prevent spamming
    let savedDismissed: number[] = [];
    try {
      const raw = sessionStorage.getItem('vb_crm_dismissed_alerts');
      if (raw) savedDismissed = JSON.parse(raw);
    } catch (e) {
      // quiet
    }

    return {
      unreadCount: 0,
      activeAlerts: [] as ActiveAlert[],
      followUpCounts: {
        overdue: 0,
        today: 0,
        upcoming: 0,
        total_pending: 0,
      },
      latestLeadId: 0,
      dismissedAlertIds: new Set<number>(savedDismissed),
      isPolling: false,
      isTabVisible: !document.hidden,
      pollTimer: null as any,
      pollIntervalMs: 15000, // 15 seconds
      consecutiveErrors: 0,
    };
  },

  getters: {
    currentTopAlert: (state): ActiveAlert | null => {
      // Return the first active alert that has not been dismissed
      return state.activeAlerts.find(a => !state.dismissedAlertIds.has(a.id)) || null;
    },
    hasActiveAlerts: (state): boolean => {
      return state.activeAlerts.some(a => !state.dismissedAlertIds.has(a.id));
    },
  },

  actions: {
    async pollNow() {
      const authStore = useAuthStore();
      if (!authStore.isAuthenticated) {
        this.stopPolling();
        return;
      }

      try {
        const response = await api.get('/notifications/poll');
        const data = response.data;

        this.unreadCount = data.unread_count ?? 0;
        authStore.unreadCount = this.unreadCount;

        if (data.follow_up_counts) {
          this.followUpCounts = data.follow_up_counts;
        }

        // Detect if new leads were added while using app
        if (this.latestLeadId > 0 && data.latest_lead_id > this.latestLeadId) {
          window.dispatchEvent(new CustomEvent('vb_crm_leads_updated', { detail: { newLeadId: data.latest_lead_id } }));
        }
        this.latestLeadId = data.latest_lead_id ?? this.latestLeadId;

        // Filter alerts: only keep those not yet dismissed by the user in this session
        const rawAlerts: ActiveAlert[] = data.active_alerts ?? [];
        this.activeAlerts = rawAlerts.filter(a => !this.dismissedAlertIds.has(a.id));

        this.consecutiveErrors = 0;
      } catch (err: any) {
        this.consecutiveErrors++;
        // Don't log expected 401s (handled by auth interceptor)
        if (err.response?.status !== 401) {
          console.warn('[Notification Poll] Transient poll failure, will retry automatically:', err.message);
        }
      }
    },

    startPolling() {
      if (this.isPolling) return;
      this.isPolling = true;

      // Initial fetch immediately
      this.pollNow();

      // Setup Page Visibility listener to throttle polling when tab is inactive
      const handleVisibilityChange = () => {
        this.isTabVisible = !document.hidden;
        if (this.isTabVisible) {
          // Tab became active again: immediately poll to catch up on any missed alerts
          this.pollNow();
          this.resetTimer(15000);
        } else {
          // Tab hidden: slow down polling to 60s to save CPU and battery
          this.resetTimer(60000);
        }
      };

      document.removeEventListener('visibilitychange', handleVisibilityChange);
      document.addEventListener('visibilitychange', handleVisibilityChange);

      // Setup window online/offline listeners to recover gracefully
      const handleOnline = () => {
        console.info('[Notification Poll] Network connection re-established. Resuming immediate poll.');
        this.pollNow();
        this.resetTimer(15000);
      };
      window.removeEventListener('online', handleOnline);
      window.addEventListener('online', handleOnline);

      this.resetTimer(15000);
    },

    resetTimer(intervalMs: number) {
      if (this.pollTimer) {
        clearInterval(this.pollTimer);
      }
      this.pollIntervalMs = intervalMs;
      this.pollTimer = setInterval(() => {
        // If there were consecutive errors, back off proportionally (max 60s)
        if (this.consecutiveErrors > 3) {
          if (this.consecutiveErrors % 2 !== 0) return; // skip every second cycle
        }
        this.pollNow();
      }, intervalMs);
    },

    stopPolling() {
      if (this.pollTimer) {
        clearInterval(this.pollTimer);
        this.pollTimer = null;
      }
      this.isPolling = false;
    },

    async dismissAlert(alertId: number) {
      // 1. Optimistically add to dismissed set and persist to sessionStorage
      this.dismissedAlertIds.add(alertId);
      try {
        sessionStorage.setItem('vb_crm_dismissed_alerts', JSON.stringify(Array.from(this.dismissedAlertIds)));
      } catch (e) {
        // ignore
      }

      // 2. Remove from active alerts list
      this.activeAlerts = this.activeAlerts.filter(a => a.id !== alertId);
      if (this.unreadCount > 0) {
        this.unreadCount--;
        const authStore = useAuthStore();
        authStore.unreadCount = this.unreadCount;
      }

      // 3. Mark read on backend
      try {
        await api.post(`/notifications/${alertId}/dismiss`);
      } catch (err) {
        console.error('Failed to mark notification dismissed on backend:', err);
      }
    },

    clearSessionDismissals() {
      this.dismissedAlertIds.clear();
      try {
        sessionStorage.removeItem('vb_crm_dismissed_alerts');
        sessionStorage.removeItem('vb_crm_login_popup_seen');
      } catch (e) {
        // ignore
      }
    },
  },
});
