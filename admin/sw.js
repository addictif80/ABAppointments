const CACHE_NAME = 'ab-admin-shell-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.add(new URL('offline.html', self.registration.scope)))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        ))
    );
    self.clients.claim();
});

// Network-first for page navigations, falling back to a static offline page.
// Everything else (API calls, assets) goes straight to the network untouched,
// since the back-office is data-driven and must never serve stale content.
self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(new URL('offline.html', self.registration.scope)))
        );
    }
});
