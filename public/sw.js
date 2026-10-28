const CACHE_NAME = 'vistabound-crm-v2';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      // Purge all stale caches
      return Promise.all(keys.map((key) => caches.delete(key)));
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // NEVER intercept API calls, assets, images, scripts, styles, or build files
  const url = event.request.url;
  if (
    url.includes('/api/') ||
    event.request.destination === 'image' ||
    event.request.destination === 'script' ||
    event.request.destination === 'style' ||
    url.includes('/build/') ||
    url.includes('/images/') ||
    url.includes('.png') ||
    url.includes('.jpg') ||
    url.includes('.jpeg') ||
    url.includes('.svg') ||
    url.includes('.ico')
  ) {
    return;
  }

  // Network-first for navigation requests
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});
