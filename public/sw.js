const FLOOR_CACHE = 'sentria-session-floor-v1';
const DOCUMENT_CACHE = 'sentria-session-documents-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('message', (event) => {
    const data = event.data;
    if (!data || data.type !== 'CACHE_SESSION_FLOOR') {
        return;
    }

    const payload = data.payload;
    if (!payload || !payload.cacheUrl) {
        return;
    }

    event.waitUntil(cacheSessionFloor(payload.cacheUrl, payload.documentUrls ?? []));
});

async function cacheSessionFloor(cacheUrl, documentUrls) {
    const floorCache = await caches.open(FLOOR_CACHE);
    await floorCache.add(cacheUrl);

    if (documentUrls.length === 0) {
        return;
    }

    const docCache = await caches.open(DOCUMENT_CACHE);
    await Promise.all(
        documentUrls.map(async (url) => {
            try {
                await docCache.add(url);
            } catch {
                // Document download may require fresh auth; best-effort cache only.
            }
        }),
    );
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET') {
        return;
    }

    if (url.pathname.includes('/floor/cache')) {
        event.respondWith(cacheFirst(request, FLOOR_CACHE));

        return;
    }

    if (url.pathname.includes('/versions/') && url.pathname.endsWith('/download')) {
        event.respondWith(cacheFirst(request, DOCUMENT_CACHE));
    }
});

async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            await cache.put(request, response.clone());
        }

        return response;
    } catch {
        const fallback = await cache.match(request);
        if (fallback) {
            return fallback;
        }

        throw new Error('Offline and no cached response available.');
    }
}
