const CACHE_NAME = 'arbejdstilladelse-v2';
const urlsToCache = [
  '/',
  '/index.php',
  '/view_wo.php',
  '/view_sja.php',
  '/dashboard.php',
  '/style.css',
  '/navigation.js',
  '/icon-192.png',
  '/icon-512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(urlsToCache).catch((error) => {
          console.log('Cache addAll error:', error);
          return Promise.resolve();
        });
      })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Only handle GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);
  
  // Don't intercept PHP files that do redirects or handle forms
  if (url.pathname.includes('login.php') || 
      url.pathname.includes('logout.php') ||
      url.pathname.includes('handler.php') ||
      url.pathname.includes('approve_') ||
      url.pathname.includes('delete_') ||
      url.pathname.includes('update_')) {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        
        return fetch(event.request).then((response) => {
          // Only cache successful responses
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }
          
          const responseToCache = response.clone();
          caches.open(CACHE_NAME)
            .then((cache) => {
              cache.put(event.request, responseToCache);
            });
          
          return response;
        }).catch((error) => {
          console.log('Fetch failed:', error);
          // Return cached fallback for documents
          if (event.request.destination === 'document') {
            return caches.match('/index.php');
          }
          throw error;
        });
      })
  );
});
