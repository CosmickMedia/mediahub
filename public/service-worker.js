// MediaHub Public Service Worker
const CACHE_NAME = 'mediahub-public-v8';
const urlsToCache = [
  // Don't cache PHP files - they're dynamic/authenticated
  '/assets/css/common.css',
  '/assets/images/mediahub-logo.png',
  '/icon-192.png',
  '/icon-192-maskable.png',
  '/icon-512.png',
  '/icon-512-maskable.png',
  '/apple-touch-icon.png',
  '/favicon-16x16.png',
  '/favicon-32x32.png'
];

// Install event - cache essential resources
self.addEventListener('install', (event) => {
  console.log('Service Worker: Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Service Worker: Caching files');
        // Cache files individually to prevent one failure from blocking installation
        return Promise.all(
          urlsToCache.map(url => {
            return cache.add(url).catch(err => {
              console.warn('Service Worker: Failed to cache', url, err);
              // Don't reject - allow installation to continue
            });
          })
        );
      })
      .then(() => {
        console.log('Service Worker: Install complete');
        return self.skipWaiting();
      })
      .catch(err => {
        console.error('Service Worker: Install failed', err);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('Service Worker: Clearing old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - network first, fallback to cache
self.addEventListener('fetch', (event) => {
  // .php files are dynamic/authenticated — skip SW interception entirely.
  // Calling event.respondWith() would force US to surface any fetch failure
  // back to the page; simply returning lets the browser handle the fetch
  // natively (including bfcache probes + SW update checks), producing no
  // extra console noise regardless of outcome.
  if (event.request.url.includes('.php')) return;

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        const req = event.request;
        // Only cache GET responses with status 200. Range requests
        // (e.g. for the 30 MB ffmpeg-core.wasm) return 206 Partial
        // Content, which the Cache API rejects — caching that throws
        // an unhandled rejection and poisons the cache entry.
        const canCache =
          req.method === 'GET' &&
          response &&
          response.ok &&
          response.status === 200 &&
          (req.url.startsWith('http:') || req.url.startsWith('https:'));

        if (canCache) {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(req, responseToCache).catch((err) => {
              console.warn('SW cache.put failed for', req.url, err);
            });
          });
        }

        return response;
      })
      .catch(() => {
        // If network fails, try to serve from cache
        return caches.match(event.request);
      })
  );
});
