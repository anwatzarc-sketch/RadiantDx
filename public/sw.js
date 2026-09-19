/*
 * Service worker for the Harme Laboratory System.
 *
 * ---------------------------------------------------------------------------
 * What this deliberately does NOT do
 * ---------------------------------------------------------------------------
 * It never stores an HTML page in the cache.
 *
 * Every meaningful page in this application is an authenticated, server
 * rendered response containing patient identifiers, clinical indications and
 * results. Putting those in Cache Storage would leave protected health
 * information sitting in the browser profile on a shared laboratory
 * workstation, readable after sign out and surviving a session expiry. So
 * navigations are always network-first, and when the network is unavailable
 * the user gets a generic offline page rather than a stale copy of somebody's
 * results.
 *
 * Only things that are identical for every user are cached: the compiled
 * stylesheet and JavaScript, fonts, and the app icons.
 *
 * Offline *data entry* is out of scope here on purpose. Queueing result entry
 * for later replay is a clinical-safety design decision (a result replayed
 * after the specimen's validation state has moved on is a patient-safety
 * hazard, not a sync bug), not something a service worker should decide on its
 * own.
 */

const VERSION = 'v1';
const ASSET_CACHE = `harme-assets-${VERSION}`;
const OFFLINE_URL = '/offline';
const OFFLINE_CACHE = `harme-offline-${VERSION}`;

/** Same-origin paths whose responses are identical for every user. */
const CACHEABLE_PATHS = [/^\/build\//, /^\/images\//, /^\/favicon\.ico$/];

const isCacheableAsset = (url) =>
    url.origin === self.location.origin && CACHEABLE_PATHS.some((re) => re.test(url.pathname));

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(OFFLINE_CACHE)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            .then(() => self.skipWaiting())
            .catch(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key.startsWith('harme-') && key !== ASSET_CACHE && key !== OFFLINE_CACHE)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

/** Sign-out clears everything we hold, so nothing outlives the session. */
self.addEventListener('message', (event) => {
    if (event.data === 'clear-caches') {
        event.waitUntil(caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key)))));
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Navigations: network only, with a generic offline page as the fallback.
    // Nothing authenticated is ever written to a cache.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => {
                const cached = await caches.match(OFFLINE_URL);
                return (
                    cached ??
                    new Response('<h1>Offline</h1><p>This page needs a network connection.</p>', {
                        status: 503,
                        headers: { 'Content-Type': 'text/html; charset=utf-8' },
                    })
                );
            }),
        );

        return;
    }

    // Build output is content-hashed, and icons change rarely: serve from cache
    // and refresh in the background.
    if (isCacheableAsset(url)) {
        event.respondWith(
            caches.open(ASSET_CACHE).then(async (cache) => {
                const cached = await cache.match(request);

                const network = fetch(request)
                    .then((response) => {
                        if (response.ok && response.type === 'basic') {
                            cache.put(request, response.clone());
                        }

                        return response;
                    })
                    .catch(() => cached);

                return cached ?? network;
            }),
        );
    }
});
