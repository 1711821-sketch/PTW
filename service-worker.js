/**
 * PTW System Service Worker
 * Implements intelligent caching strategies for optimal performance
 */

const CACHE_VERSION = 'v4';
const STATIC_CACHE = `ptw-static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `ptw-dynamic-${CACHE_VERSION}`;
const IMAGE_CACHE = `ptw-images-${CACHE_VERSION}`;

// Static assets to cache on install
const STATIC_ASSETS = [
    '/style.css',
    '/navigation.js',
    '/js/theme.js',
    '/js/swipe.js',
    '/js/pull-refresh.js',
    '/js/infinite-scroll.js',
    '/icon-192.png',
    '/icon-512.png',
    '/manifest.json'
];

// Maximum items in dynamic cache
const MAX_DYNAMIC_CACHE = 50;
const MAX_IMAGE_CACHE = 100;

// Cache expiration times (in milliseconds)
const STATIC_EXPIRY = 7 * 24 * 60 * 60 * 1000; // 7 days
const DYNAMIC_EXPIRY = 24 * 60 * 60 * 1000; // 24 hours
const IMAGE_EXPIRY = 30 * 24 * 60 * 60 * 1000; // 30 days

/**
 * Install event - cache static assets
 */
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker...');
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => {
                console.log('[SW] Pre-caching static assets');
                return cache.addAll(STATIC_ASSETS).catch((error) => {
                    console.log('[SW] Some assets failed to cache:', error);
                    return Promise.resolve();
                });
            })
            .then(() => self.skipWaiting())
    );
});

/**
 * Activate event - clean up old caches
 */
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker...');
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => {
                            return name.startsWith('ptw-') &&
                                   name !== STATIC_CACHE &&
                                   name !== DYNAMIC_CACHE &&
                                   name !== IMAGE_CACHE;
                        })
                        .map((name) => {
                            console.log('[SW] Deleting old cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => self.clients.claim())
    );
});

/**
 * Fetch event - implement caching strategies
 */
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle same-origin requests
    if (url.origin !== location.origin) {
        return;
    }

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip requests that should never be cached
    const skipPatterns = [
        'login.php',
        'logout.php',
        'handler.php',
        'approve_',
        'delete_',
        'update_',
        'ajax=1',
        'csrf_token'
    ];

    if (skipPatterns.some(pattern => url.href.includes(pattern))) {
        return;
    }

    // Choose strategy based on resource type
    if (isStaticAsset(url)) {
        // Cache-first for static assets
        event.respondWith(cacheFirst(request, STATIC_CACHE));
    } else if (isImage(url)) {
        // Cache-first with fallback for images
        event.respondWith(cacheFirstWithFallback(request, IMAGE_CACHE));
    } else if (isPage(url)) {
        // Network-first for pages (stale-while-revalidate)
        event.respondWith(networkFirst(request, DYNAMIC_CACHE));
    } else {
        // Default: network-first
        event.respondWith(networkFirst(request, DYNAMIC_CACHE));
    }
});

/**
 * Check if URL is a static asset
 */
function isStaticAsset(url) {
    const staticExtensions = ['.css', '.js', '.woff', '.woff2', '.ttf', '.eot'];
    return staticExtensions.some(ext => url.pathname.endsWith(ext));
}

/**
 * Check if URL is an image
 */
function isImage(url) {
    const imageExtensions = ['.png', '.jpg', '.jpeg', '.gif', '.webp', '.avif', '.svg', '.ico'];
    return imageExtensions.some(ext => url.pathname.endsWith(ext)) ||
           url.pathname.includes('/uploads/');
}

/**
 * Check if URL is a page
 */
function isPage(url) {
    return url.pathname.endsWith('.php') || url.pathname === '/';
}

/**
 * Cache-first strategy
 */
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    if (cached) {
        // Return cached and update in background
        updateCache(request, cacheName);
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.log('[SW] Fetch failed:', error);
        throw error;
    }
}

/**
 * Cache-first with placeholder fallback for images
 */
async function cacheFirstWithFallback(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            // Limit cache size
            await trimCache(cacheName, MAX_IMAGE_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.log('[SW] Image fetch failed:', error);
        // Return empty response for failed images
        return new Response('', { status: 404, statusText: 'Not found' });
    }
}

/**
 * Network-first strategy (stale-while-revalidate)
 */
async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);

    try {
        const response = await fetch(request);
        if (response.ok) {
            // Limit cache size
            await trimCache(cacheName, MAX_DYNAMIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.log('[SW] Network failed, trying cache');
        const cached = await cache.match(request);
        if (cached) {
            return cached;
        }

        // Return offline page for documents
        if (request.destination === 'document') {
            return createOfflineResponse();
        }

        throw error;
    }
}

/**
 * Update cache in background
 */
async function updateCache(request, cacheName) {
    try {
        const cache = await caches.open(cacheName);
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response);
        }
    } catch (error) {
        // Silently fail background updates
    }
}

/**
 * Trim cache to maximum size
 */
async function trimCache(cacheName, maxItems) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();

    if (keys.length > maxItems) {
        const deleteCount = keys.length - maxItems;
        for (let i = 0; i < deleteCount; i++) {
            await cache.delete(keys[i]);
        }
    }
}

/**
 * Create offline response
 */
function createOfflineResponse() {
    const offlineHtml = `
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - PTW System</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f3f4f6;
            color: #374151;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 400px;
        }
        .icon { font-size: 4rem; margin-bottom: 1rem; }
        h1 { margin: 0 0 0.5rem; font-size: 1.5rem; }
        p { color: #6b7280; margin: 0 0 1.5rem; }
        button {
            background: #1e40af;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
        }
        button:hover { background: #1e3a8a; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">📡</div>
        <h1>Du er offline</h1>
        <p>Tjek din internetforbindelse og prov igen.</p>
        <button onclick="location.reload()">Prov igen</button>
    </div>
</body>
</html>
`;
    return new Response(offlineHtml, {
        headers: { 'Content-Type': 'text/html; charset=utf-8' }
    });
}

/**
 * Handle messages from clients
 */
self.addEventListener('message', (event) => {
    if (event.data === 'skipWaiting') {
        self.skipWaiting();
    }
    if (event.data === 'clearCache') {
        caches.keys().then((names) => {
            names.forEach((name) => {
                if (name.startsWith('ptw-')) {
                    caches.delete(name);
                }
            });
        });
    }
});
