const CACHE_NAME = 'lms-jb-static-v1';
const OFFLINE_URL = '/lms/offline.html';
const PRECACHE = [
  OFFLINE_URL,
  '/lms/assets/img/jb-mobile.png',
  '/lms/assets/img/pwa-icon-192.png',
  '/lms/assets/img/pwa-icon-512.png'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(PRECACHE)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  if (url.origin === self.location.origin &&
      url.pathname.startsWith('/lms/assets/') &&
      !url.pathname.startsWith('/lms/assets/upload/')) {
    event.respondWith(
      caches.match(request).then(cached => cached || fetch(request).then(response => {
        if (response.ok) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
        }
        return response;
      }))
    );
  }
});
