import { PushNotifications, Token, ActionPerformed } from '@capacitor/push-notifications';
import { Capacitor } from '@capacitor/core';
import api from '../api/axios';
import router from '../router';

export const pushNotificationService = {
  registeredToken: null as string | null,

  async init(): Promise<void> {
    if (!Capacitor.isNativePlatform()) return;

    try {
      let perm = await PushNotifications.checkPermissions();

      if (perm.receive === 'prompt') {
        perm = await PushNotifications.requestPermissions();
      }

      if (perm.receive !== 'granted') {
        console.warn('[PushNotifications] Permission not granted for push notifications');
        return;
      }

      try {
        await PushNotifications.register();
      } catch (regError) {
        console.warn('[PushNotifications] Push registration deferred:', regError);
      }

      // On successful token registration with FCM
      PushNotifications.addListener('registration', async (token: Token) => {
        console.log('[PushNotifications] FCM Device Token received:', token.value);
        this.registeredToken = token.value;
        try {
          await api.post('/mobile/push-token', {
            token: token.value,
            platform: Capacitor.getPlatform(),
            device_id: navigator.userAgent,
          });
        } catch (err) {
          console.warn('[PushNotifications] Failed to upload push token to server:', err);
        }
      });

      // On registration error
      PushNotifications.addListener('registrationError', (error: any) => {
        console.warn('[PushNotifications] Registration error:', error);
      });

      // Foreground notification received
      PushNotifications.addListener('pushNotificationReceived', (notification) => {
        console.log('[PushNotifications] Foreground push received:', notification);
      });

      // User clicked on push notification
      PushNotifications.addListener('pushNotificationActionPerformed', (action: ActionPerformed) => {
        const data = action.notification.data;
        if (data && data.lead_id) {
          router.push(`/leads/${data.lead_id}`);
        } else if (data && data.follow_up_id) {
          router.push('/follow-ups');
        } else {
          router.push('/notifications');
        }
      });
    } catch (e) {
      console.warn('[PushNotifications] Error initializing push notifications:', e);
    }
  },

  async unregister(): Promise<void> {
    if (!Capacitor.isNativePlatform() || !this.registeredToken) return;

    try {
      await api.delete('/mobile/push-token', {
        data: { token: this.registeredToken },
      });
      this.registeredToken = null;
    } catch (e) {
      console.warn('[PushNotifications] Error unregistering token:', e);
    }
  },
};
