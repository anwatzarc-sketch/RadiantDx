/*
 * Service worker for RadiantDx.
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

const VERSION = 'v2';
const ASSET_CACHE = `radiantdx-assets-${VERSION}`;
const OFFLINE_URL = '/offline';
const OFFLINE_CACHE = `radiantdx-offline-${VERSION}`;

/*
 * Every cache prefix this worker is responsible for deleting.
 *
 * The pre-rename prefix is still listed on purpose. Activation only reaps keys
 * it recognises, so dropping `harme-` here would strand the asset and offline
 * caches already sitting in an installed client's browser profile with nothing
 * left that would ever clear them.
 */
const OWNED_CACHE_PREFIXES = ['radiantdx-', 'harme-'];

/** Same-origin paths whose responses are identical for every user. */
const CACHEABLE_PATHS = [/^\/build\//, /^\/images\//, /^\/favicon\.ico$/];

const isCacheableAsset = (url) =>
    url.origin === self.location.origin && CACHEABLE_PATHS.some((re) => re.test(url.pathname));

/**
 * Puts the offline page in the cache, and says whether it is there.
 *
 * Install is not a reliable moment to do this. The request races the page that
 * registered the worker, and a single-threaded origin — `php artisan serve` is
 * one, so this bites in development first — can simply refuse to answer it. The
 * old code swallowed that failure, which left the worker installed with an
 * empty offline cache and no way to recover: every later offline navigation
 * fell through to the bare fallback string at the bottom of this file.
 *
 * So the same function runs again after any navigation that succeeds. By the
 * time the network is actually needed, the page has been cached by whichever
 * attempt found the server willing.
 */
const cacheOfflinePage = async () => {
    const cache = await caches.open(OFFLINE_CACHE);

    if (await cache.match(OFFLINE_URL)) {
        return true;
    }

    try {
        await cache.add(new Request(OFFLINE_URL, { cache: 'reload' }));

        return true;
    } catch {
        // Offline, or the origin would not answer a second request yet.
        // A later navigation will try again.
        return false;
    }
};

self.addEventListener('install', (event) => {
    event.waitUntil(cacheOfflinePage().then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                OWNED_CACHE_PREFIXES.some((prefix) => key.startsWith(prefix)) &&
                                key !== ASSET_CACHE &&
                                key !== OFFLINE_CACHE,
                        )
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

/**
 * Last resort, for the window before the real offline page has ever been
 * cached — a first run that went offline immediately, or a browser that
 * evicted the cache under storage pressure.
 *
 * It is written out here rather than linking anything because at this point
 * nothing can be fetched, and it is kept to the few lines needed to not look
 * like a browser error. The designed page lives in resources/views/offline.blade.php.
 */
const offlineFallback = () =>
    new Response(
        `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Offline</title><style>
html,body{height:100%;margin:0}
body{display:flex;align-items:center;justify-content:center;padding:1.5rem;text-align:center;
background:#00303c;color:#8fb3bb;
font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
div{max-width:22rem}
span{display:flex;align-items:center;justify-content:center;width:3.5rem;height:3.5rem;margin:0 auto 1.5rem;
border-radius:1.125rem;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12)}
svg{width:1.75rem;height:1.75rem}
h1{margin:0;font-size:1.25rem;font-weight:700;color:#fff}
p{margin:.625rem 0 0;font-size:.875rem;line-height:1.6}
button{margin-top:1.5rem;min-height:2.75rem;padding:0 1.5rem;font:inherit;font-size:.875rem;font-weight:600;
color:#00303c;background:#fff;border:0;border-radius:.625rem;cursor:pointer}
</style></head><body><div>
<span><svg viewBox="0 0 24 24" fill="none" stroke="#2dd4bf" stroke-width="1.6" stroke-linecap="round"
stroke-linejoin="round"><path d="M8.6 15.7a6 6 0 0 1 6.8 0"/><path d="M5 12.1a11 11 0 0 1 3.2-2"/>
<path d="M15.8 10.1a11 11 0 0 1 3.2 2"/><path d="M12 19.5h.01"/>
<path d="M2.5 2.5l19 19" stroke="#f8fafc"/></svg></span>
<h1>No network connection</h1>
<p>This device cannot reach the laboratory server. Nothing you had already saved has been lost.</p>
<button onclick="location.reload()">Try again</button>
</div><script>addEventListener('online',function(){location.reload()})</scr`+`ipt></body></html>`,
        {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' },
        },
    );

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
            fetch(request)
                .then((response) => {
                    // The server answered, so this is a good moment to make
                    // sure the offline page is actually in the cache — see
                    // cacheOfflinePage above for why install alone is not
                    // enough. Deliberately not awaited: the navigation must
                    // not wait on it.
                    event.waitUntil(cacheOfflinePage());

                    return response;
                })
                .catch(async () => {
                    const cached = await caches.match(OFFLINE_URL);

                    return cached ?? offlineFallback();
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
