const CACHE_NAME = 'arbejdstilladelse-v1';
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
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);
  
  // Don't cache login.php or any PHP files that might redirect
  if (url.pathname.includes('login.php') || 
      url.pathname.includes('logout.php') ||
      url.pathname.includes('handler.php')) {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        if (response) {
          return response;
        }
        
        return fetch(event.request).then((response) => {
          // Don't cache if:
          // - No response
          // - Not a 200 status (includes redirects 301/302)
          // - Response type is not 'basic' (opaqueredirect, etc)
          if (!response || 
              response.status !== 200 || 
              response.type !== 'basic') {
            return response;
          }
          
          const responseToCache = response.clone();
          caches.open(CACHE_NAME)
            .then((cache) => {
              cache.put(event.request, responseToCache);
            });
          
          return response;
        });
      })
      .catch(() => {
        if (event.request.destination === 'document') {
          return caches.match('/index.php');
        }
      })
  );
});
