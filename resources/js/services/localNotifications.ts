import { LocalNotifications, Channel } from '@capacitor/local-notifications';
import { Capacitor } from '@capacitor/core';
import router from '../router';

const CHANNEL_ID = 'follow_up_reminders';

export const localNotificationService = {
  initialized: false,

  async init(): Promise<void> {
    if (this.initialized) return;

    if (Capacitor.isNativePlatform()) {
      try {
        const perm = await LocalNotifications.requestPermissions();
        if (perm.display !== 'granted') {
          console.warn('[LocalNotifications] Notification permission not granted:', perm.display);
        }

        const channel: Channel = {
          id: CHANNEL_ID,
          name: 'Follow-up Reminders',
          description: 'Timely 5-minute pre-due alerts for scheduled lead follow-ups',
          importance: 5, // High importance (heads-up notification)
          visibility: 1, // Public on lockscreen
          vibration: true,
          lights: true,
          lightColor: '#7B2D8E', // Vistabound Violet
          sound: 'beep.wav',
        };

        await LocalNotifications.createChannel(channel);

        // Listen for user tapping on notification to navigate directly to lead or task
        LocalNotifications.addListener('localNotificationActionPerformed', (action) => {
          const extra = action.notification.extra;
          if (extra && extra.leadId) {
            router.push(`/leads/${extra.leadId}`);
          } else {
            router.push('/follow-ups');
          }
        });

        this.initialized = true;
      } catch (e) {
        console.warn('[LocalNotifications] Error during initialization:', e);
      }
    }
  },

  /**
   * Schedule a native 5-minute reminder for a specific follow-up
   */
  async scheduleReminder(followUp: {
    id: number;
    scheduled_at: string;
    lead?: { id: number; name: string; company_name?: string; phone?: string };
    notes?: string;
  }): Promise<void> {
    if (!Capacitor.isNativePlatform()) return;

    try {
      await this.init();

      const scheduledDate = new Date(followUp.scheduled_at);
      // Exactly 5 minutes before due date
      const reminderDate = new Date(scheduledDate.getTime() - 5 * 60 * 1000);

      // Only schedule if reminder is in the future
      if (reminderDate.getTime() <= Date.now()) {
        return;
      }

      const leadName = followUp.lead?.name || 'Lead';
      const company = followUp.lead?.company_name ? ` (${followUp.lead.company_name})` : '';
      const phone = followUp.lead?.phone ? ` • Tel: ${followUp.lead.phone}` : '';

      await LocalNotifications.schedule({
        notifications: [
          {
            id: followUp.id % 2147483647, // Ensure 32-bit integer ID
            title: `Follow-up in 5 min: ${leadName}${company}`,
            body: followUp.notes ? `${followUp.notes}${phone}` : `Scheduled follow-up is due at ${scheduledDate.toLocaleTimeString()}${phone}`,
            schedule: { at: reminderDate, allowWhileIdle: true },
            channelId: CHANNEL_ID,
            smallIcon: 'ic_stat_notification',
            iconColor: '#7B2D8E',
            extra: {
              leadId: followUp.lead?.id,
              followUpId: followUp.id,
            },
          },
        ],
      });
    } catch (e) {
      console.warn('[LocalNotifications] Failed to schedule reminder:', e);
    }
  },

  /**
   * Sync all pending follow-up reminders into Android alarms
   */
  async syncAllReminders(followUps: any[]): Promise<void> {
    if (!Capacitor.isNativePlatform()) return;

    try {
      await this.init();

      for (const item of followUps) {
        if (item.status === 'pending' && item.scheduled_at) {
          await this.scheduleReminder(item);
        }
      }
    } catch (e) {
      console.warn('[LocalNotifications] Failed to sync reminders:', e);
    }
  },

  async cancelReminder(followUpId: number): Promise<void> {
    if (!Capacitor.isNativePlatform()) return;

    try {
      await LocalNotifications.cancel({
        notifications: [{ id: followUpId % 2147483647 }],
      });
    } catch (e) {
      console.warn('[LocalNotifications] Error cancelling reminder:', e);
    }
  },
};
