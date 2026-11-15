import { Capacitor } from '@capacitor/core';
import { CapacitorUpdater } from '@capgo/capacitor-updater';

export const OTA_CONFIG = {
  versionEndpoint: 'https://vistaboundintegrated.com/CRM/api/mobile/ota-version',
};

let updateChecking = false;

export async function initOta(): Promise<void> {
  if (!Capacitor.isNativePlatform()) {
    return;
  }

  try {
    // Notify CapacitorUpdater that the app started cleanly (prevents auto-rollback)
    await CapacitorUpdater.notifyAppReady();
    console.log('[OTA] App ready acknowledged.');

    // Run background check for new self-hosted bundle
    await checkForOtaUpdate();
  } catch (err) {
    console.warn('[OTA] Init failed:', err);
  }
}

export async function checkForOtaUpdate(): Promise<boolean> {
  if (!Capacitor.isNativePlatform() || updateChecking) {
    return false;
  }

  updateChecking = true;
  try {
    const res = await fetch(OTA_CONFIG.versionEndpoint, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Cache-Control': 'no-cache',
      },
    });

    if (!res.ok) {
      console.log('[OTA] Version check returned status:', res.status);
      return false;
    }

    const data = await res.json();
    if (!data.version || !data.url) {
      return false;
    }

    const current = await CapacitorUpdater.current();
    const currentVersion = current.bundle?.version || '1.0.0';

    console.log(`[OTA] Current version: ${currentVersion}, Remote version: ${data.version}`);

    if (data.version !== currentVersion) {
      console.log(`[OTA] Starting download of update ${data.version} from ${data.url}...`);
      
      const bundle = await CapacitorUpdater.download({
        url: data.url,
        version: data.version,
      });

      // Set bundle as active for next app launch or reload
      await CapacitorUpdater.set(bundle);
      console.log(`[OTA] Update ${data.version} downloaded and installed successfully for next restart.`);
      return true;
    }

    return false;
  } catch (err) {
    console.warn('[OTA] Check/Download failed:', err);
    return false;
  } finally {
    updateChecking = false;
  }
}
