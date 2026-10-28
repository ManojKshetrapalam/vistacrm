import { Preferences } from '@capacitor/preferences';

export interface Mutation {
  uuid: string;
  operation: 'create_note' | 'update_status' | 'log_call' | 'create_followup' | 'reschedule_followup' | 'complete_followup' | 'create_lead';
  lead_id?: number;
  payload: Record<string, any>;
  client_timestamp: string;
  version?: number;
  retry_count?: number;
  error?: string;
  status?: 'pending' | 'syncing' | 'failed' | 'conflict';
}

const KEYS = {
  LEADS: 'vb_offline_leads',
  FOLLOW_UPS: 'vb_offline_follow_ups',
  OUTBOX: 'vb_offline_outbox',
  WATERMARK: 'vb_offline_sync_watermark',
  SETTINGS: 'vb_offline_settings',
};

async function getItem<T>(key: string, fallback: T): Promise<T> {
  try {
    const { value } = await Preferences.get({ key });
    if (!value) return fallback;
    return JSON.parse(value) as T;
  } catch (e) {
    console.warn(`[offlineStorage] Error reading key ${key}:`, e);
    return fallback;
  }
}

async function setItem<T>(key: string, value: T): Promise<void> {
  try {
    await Preferences.set({
      key,
      value: JSON.stringify(value),
    });
  } catch (e) {
    console.warn(`[offlineStorage] Error saving key ${key}:`, e);
  }
}

export const offlineStorage = {
  // Leads
  async getLeads(): Promise<any[]> {
    return getItem<any[]>(KEYS.LEADS, []);
  },

  async setLeads(leads: any[]): Promise<void> {
    await setItem(KEYS.LEADS, leads);
  },

  async getLead(id: number): Promise<any | null> {
    const leads = await this.getLeads();
    return leads.find(l => l.id === id) || null;
  },

  async saveLead(lead: any): Promise<void> {
    const leads = await this.getLeads();
    const idx = leads.findIndex(l => l.id === lead.id);
    if (idx >= 0) {
      leads[idx] = { ...leads[idx], ...lead };
    } else {
      leads.unshift(lead);
    }
    await this.setLeads(leads);
  },

  // Follow-ups
  async getFollowUps(): Promise<any[]> {
    return getItem<any[]>(KEYS.FOLLOW_UPS, []);
  },

  async setFollowUps(followUps: any[]): Promise<void> {
    await setItem(KEYS.FOLLOW_UPS, followUps);
  },

  async saveFollowUp(followUp: any): Promise<void> {
    const followUps = await this.getFollowUps();
    const idx = followUps.findIndex(f => f.id === followUp.id);
    if (idx >= 0) {
      followUps[idx] = { ...followUps[idx], ...followUp };
    } else {
      followUps.push(followUp);
    }
    await this.setFollowUps(followUps);
  },

  // Outbox Mutations
  async getOutbox(): Promise<Mutation[]> {
    return getItem<Mutation[]>(KEYS.OUTBOX, []);
  },

  async setOutbox(mutations: Mutation[]): Promise<void> {
    await setItem(KEYS.OUTBOX, mutations);
  },

  async enqueueMutation(mutation: Mutation): Promise<void> {
    const outbox = await this.getOutbox();
    outbox.push(mutation);
    await this.setOutbox(outbox);
  },

  async removeMutation(uuid: string): Promise<void> {
    const outbox = await this.getOutbox();
    const filtered = outbox.filter(m => m.uuid !== uuid);
    await this.setOutbox(filtered);
  },

  // Sync Watermark
  async getWatermark(): Promise<string | null> {
    return getItem<string | null>(KEYS.WATERMARK, null);
  },

  async setWatermark(timestamp: string): Promise<void> {
    await setItem(KEYS.WATERMARK, timestamp);
  },

  // Settings
  async getSettings(): Promise<any> {
    return getItem<any>(KEYS.SETTINGS, null);
  },

  async setSettings(settings: any): Promise<void> {
    await setItem(KEYS.SETTINGS, settings);
  },

  // Clear all
  async clearAll(): Promise<void> {
    await Preferences.remove({ key: KEYS.LEADS });
    await Preferences.remove({ key: KEYS.FOLLOW_UPS });
    await Preferences.remove({ key: KEYS.OUTBOX });
    await Preferences.remove({ key: KEYS.WATERMARK });
    await Preferences.remove({ key: KEYS.SETTINGS });
  },
};
