import { ref } from 'vue';
import { Network } from '@capacitor/network';
import api from '../api/axios';
import { offlineStorage, Mutation } from './offlineStorage';
import { localNotificationService } from './localNotifications';

export const isOnline = ref<boolean>(navigator.onLine);
export const isSyncing = ref<boolean>(false);
export const pendingCount = ref<number>(0);
export const lastSyncedAt = ref<string | null>(null);
export const lastSyncError = ref<string | null>(null);

function generateUUID(): string {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return 'uuid-' + Date.now() + '-' + Math.random().toString(36).substring(2, 9);
}

export const syncEngine = {
  initialized: false,

  async init(): Promise<void> {
    if (this.initialized) return;

    // Load initial outbox count
    const outbox = await offlineStorage.getOutbox();
    pendingCount.value = outbox.length;

    // Load watermark
    lastSyncedAt.value = await offlineStorage.getWatermark();

    // Check current network status
    try {
      const status = await Network.getStatus();
      isOnline.value = status.connected;
    } catch {
      isOnline.value = navigator.onLine;
    }

    // Network status change listener
    Network.addListener('networkStatusChange', (status) => {
      const wasOffline = !isOnline.value;
      isOnline.value = status.connected;

      if (status.connected && wasOffline) {
        console.log('[syncEngine] Connection restored. Triggering automatic background sync.');
        this.sync();
      }
    });

    window.addEventListener('online', () => {
      isOnline.value = true;
      this.sync();
    });

    window.addEventListener('offline', () => {
      isOnline.value = false;
    });

    this.initialized = true;

    // Initial sync if online
    if (isOnline.value && localStorage.getItem('vb_crm_token')) {
      this.sync();
    }
  },

  /**
   * Queue a mutation while offline or online
   */
  async enqueueMutation(
    operation: Mutation['operation'],
    leadId?: number,
    payload: Record<string, any> = {},
    version: number = 1
  ): Promise<Mutation> {
    const uuid = generateUUID();
    const mutation: Mutation = {
      uuid,
      operation,
      lead_id: leadId,
      payload,
      client_timestamp: new Date().toISOString(),
      version,
      status: 'pending',
    };

    // Optimistic local update
    await this.applyOptimisticUpdate(mutation);

    // Save to persistent outbox
    await offlineStorage.enqueueMutation(mutation);
    const outbox = await offlineStorage.getOutbox();
    pendingCount.value = outbox.length;

    // Attempt immediate sync if connected
    if (isOnline.value) {
      this.sync();
    }

    return mutation;
  },

  /**
   * Optimistically update local cache so user experiences zero delay
   */
  async applyOptimisticUpdate(mutation: Mutation): Promise<void> {
    const { operation, lead_id, payload } = mutation;

    if (!lead_id && operation !== 'create_lead') return;

    if (lead_id) {
      const lead = await offlineStorage.getLead(lead_id);
      if (lead) {
        if (operation === 'update_status') {
          lead.status = payload.status;
          lead.version = (lead.version || 1) + 1;
        } else if (operation === 'create_note') {
          if (!lead.activities) lead.activities = [];
          lead.activities.unshift({
            id: 'temp-' + Date.now(),
            client_uuid: mutation.uuid,
            activity_type: 'note',
            title: 'Note added (offline)',
            description: payload.note || payload.content,
            created_at: new Date().toISOString(),
            user: { name: 'You' },
          });
        } else if (operation === 'log_call') {
          lead.last_contact_date = new Date().toISOString();
          if (!lead.activities) lead.activities = [];
          lead.activities.unshift({
            id: 'temp-' + Date.now(),
            client_uuid: mutation.uuid,
            activity_type: 'call_logged',
            title: `Call logged: ${payload.outcome}`,
            description: payload.notes || `Outcome: ${payload.outcome}`,
            created_at: new Date().toISOString(),
            user: { name: 'You' },
          });
        }
        await offlineStorage.saveLead(lead);
      }
    }
  },

  /**
   * Two-way delta synchronization
   */
  async sync(): Promise<{ success: boolean; pushed: number; pulled: number }> {
    if (isSyncing.value || !isOnline.value) {
      return { success: false, pushed: 0, pulled: 0 };
    }

    const token = localStorage.getItem('vb_crm_token');
    if (!token) {
      return { success: false, pushed: 0, pulled: 0 };
    }

    isSyncing.value = true;
    lastSyncError.value = null;

    let pushedCount = 0;
    let pulledCount = 0;

    try {
      // PHASE 1: Push outbox mutations
      const outbox = await offlineStorage.getOutbox();
      if (outbox.length > 0) {
        const payloadMutations = outbox.map(m => ({
          uuid: m.uuid,
          operation: m.operation,
          lead_id: m.lead_id,
          payload: m.payload,
          client_timestamp: m.client_timestamp,
          version: m.version,
        }));

        const pushRes = await api.post('/mobile/sync/push', { mutations: payloadMutations });
        const results = pushRes.data?.results || [];

        // Remove synced mutations
        for (const res of results) {
          if (res.status === 'synced' || res.status === 'rejected') {
            await offlineStorage.removeMutation(res.uuid);
            pushedCount++;
          }
        }

        const remaining = await offlineStorage.getOutbox();
        pendingCount.value = remaining.length;
      }

      // PHASE 2: Pull delta updates from server
      const watermark = await offlineStorage.getWatermark();
      const pullUrl = watermark ? `/mobile/sync/pull?since=${encodeURIComponent(watermark)}` : '/mobile/sync/pull';
      const pullRes = await api.get(pullUrl);

      if (pullRes.data) {
        const { leads, follow_ups, settings, server_time } = pullRes.data;

        if (leads && leads.length > 0) {
          const cachedLeads = await offlineStorage.getLeads();
          const leadMap = new Map(cachedLeads.map(l => [l.id, l]));

          for (const serverLead of leads) {
            leadMap.set(serverLead.id, serverLead);
          }

          await offlineStorage.setLeads(Array.from(leadMap.values()));
          pulledCount += leads.length;
        }

        if (follow_ups && follow_ups.length > 0) {
          await offlineStorage.setFollowUps(follow_ups);
          // Sync native 5-minute pre-due alarms for all incoming follow-ups!
          await localNotificationService.syncAllReminders(follow_ups);
        }

        if (settings) {
          await offlineStorage.setSettings(settings);
        }

        if (server_time) {
          await offlineStorage.setWatermark(server_time);
          lastSyncedAt.value = server_time;
        }
      }

      return { success: true, pushed: pushedCount, pulled: pulledCount };
    } catch (e: any) {
      console.warn('[syncEngine] Sync error:', e);
      lastSyncError.value = e?.message || 'Sync failed';
      return { success: false, pushed: pushedCount, pulled: pulledCount };
    } finally {
      isSyncing.value = false;
      const finalOutbox = await offlineStorage.getOutbox();
      pendingCount.value = finalOutbox.length;
    }
  },
};
