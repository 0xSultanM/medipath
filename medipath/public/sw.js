/* MediPath emergency offline cache — registered only from /emergency.
   Network-first for the emergency document; cache-first for build assets. */
const CACHE = 'medipath-emergency-v1';
const PRECACHE = ['/emergency'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE).catch(() => undefined)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    const isAsset = url.pathname.startsWith('/build/') || url.pathname.startsWith('/favicon');
    const isEmergency = url.pathname === '/emergency';

    if (isAsset) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(CACHE).then((cache) => cache.put(request, copy)).catch(() => undefined);
                return response;
            })),
        );
        return;
    }

    if (isEmergency) {
        event.respondWith(
            fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(CACHE).then((cache) => cache.put(request, copy)).catch(() => undefined);
                return response;
            }).catch(() => caches.match(request).then((cached) => cached || caches.match('/emergency'))),
        );
    }
});