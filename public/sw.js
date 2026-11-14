const CACHE_NAME = 'ipma-shell-v3';

const SHELL_ASSETS = [
    '/offline.html',
    '/app-icons/icon-192.svg',
    '/app-icons/icon-512.svg',
    '/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
    '/vendor/bootstrap/bootstrap.min.css',
    '/vendor/bootstrap-icons/bootstrap-icons.min.css',
    '/vendor/bootstrap/bootstrap.bundle.min.js',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Cache-first for CDN assets and icons.
    if (
        url.pathname.startsWith('/vendor/') ||
        (url.hostname === self.location.hostname && url.pathname.startsWith('/app-icons/'))
    ) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request))
        );
        return;
    }

    // Network-first for same-origin navigation (live weather data).
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match('/offline.html')
            )
        );
        return;
    }
});
