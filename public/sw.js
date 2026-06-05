/**
 * Service Worker for Traktor PWA
 * Handles offline caching and asset management
 * Follows best-practices-rulebook.md for PWA implementation
 */

const CACHE_NAME = 'traktor';
const RUNTIME_CACHE = 'traktor-runtime';

// Assets to cache on install (static assets only - no dynamic pages)
const STATIC_ASSETS = [
  '/favicon.ico',
  '/favicon.svg',
  '/apple-touch-icon.png',
  '/web-app-manifest-192x192.png',
  '/web-app-manifest-512x512.png'
];

// Routes that should never be cached (dynamic, cookie-dependent, or authentication-dependent)
const NO_CACHE_ROUTES = [
  '/',
  '/welcome',
  '/register-device',
  '/admin'
];

// API routes that should be cached (gallery API responses)
const CACHEABLE_API_ROUTES = [
  '/api/user/',
  '/api/playlist/'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    (async () => {
      try {
        const cache = await caches.open(CACHE_NAME);
        await cache.addAll(STATIC_ASSETS);
        await self.skipWaiting();
      } catch (error) {
        console.error('Service Worker install failed:', error);
      }
    })()
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      try {
        const cacheNames = await caches.keys();
        await Promise.all(
          cacheNames
            .filter((cacheName) => {
              return cacheName !== CACHE_NAME && cacheName !== RUNTIME_CACHE;
            })
            .map((cacheName) => {
              return caches.delete(cacheName);
            })
        );
        await self.clients.claim();
      } catch (error) {
        console.error('Service Worker activate failed:', error);
      }
    })()
  );
});

// Check if a URL should be cached
function shouldCache(url) {
  // Never cache routes that depend on cookies/authentication
  // PS4 supports URL constructor natively
  let urlPath;
  try {
    const urlObj = new URL(url);
    urlPath = urlObj.pathname;
  } catch (e) {
    // Fallback for edge cases - extract pathname manually
    const match = url.match(/^https?:\/\/[^\/]+(\/.*)?$/);
    urlPath = match ? (match[1] || '/') : '/';
  }
  
  for (const route of NO_CACHE_ROUTES) {
    if (urlPath === route || urlPath.startsWith(route + '/')) {
      return false;
    }
  }
  
  // Cache gallery API routes (they handle their own authentication)
  for (const route of CACHEABLE_API_ROUTES) {
    if (urlPath.startsWith(route)) {
      return true;
    }
  }
  
  return true;
}

// Fetch event - network-first for dynamic pages, cache-first for static assets
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests (POST, PUT, DELETE, etc. should always go to network)
  if (event.request.method !== 'GET') {
    return;
  }

  // Skip cross-origin requests
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  const url = event.request.url;
  const shouldCacheThis = shouldCache(url);

  // For dynamic/cookie-dependent routes, always use network-first strategy
  if (!shouldCacheThis) {
    event.respondWith(
      (async () => {
        try {
          return await fetch(event.request);
        } catch (error) {
          // Network failed - return offline response
          return new Response('Offline', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: new Headers({
              'Content-Type': 'text/plain'
            })
          });
        }
      })()
    );
    return;
  }

  // For gallery API routes, use network-first strategy
  // This ensures fresh content is always fetched first, with cache as fallback
  const isGalleryApi = CACHEABLE_API_ROUTES.some(route => url.startsWith(self.location.origin + route));
  if (isGalleryApi) {
    event.respondWith(
      (async () => {
        try {
          const response = await fetch(event.request);
          // Network succeeded - cache successful responses for offline fallback
          if (response && response.status === 200) {
            const responseToCache = response.clone();
            const cache = await caches.open(RUNTIME_CACHE);
            await cache.put(event.request, responseToCache);
          }
          return response;
        } catch (error) {
          // Network failed - try cache as fallback
          try {
            const cache = await caches.open(RUNTIME_CACHE);
            const cachedResponse = await cache.match(event.request);
            return cachedResponse || new Response('Offline', {
              status: 503,
              statusText: 'Service Unavailable',
              headers: new Headers({
                'Content-Type': 'text/plain'
              })
            });
          } catch (cacheError) {
            return new Response('Offline', {
              status: 503,
              statusText: 'Service Unavailable',
              headers: new Headers({
                'Content-Type': 'text/plain'
              })
            });
          }
        }
      })()
    );
    return;
  }

  // For static assets, use cache-first strategy
  event.respondWith(
    (async () => {
      try {
        const cachedResponse = await caches.match(event.request);
        // Return cached version if available
        if (cachedResponse) {
          return cachedResponse;
        }

        // Otherwise fetch from network
        const response = await fetch(event.request);
        // Don't cache non-successful responses
        if (!response || response.status !== 200 || response.type !== 'basic') {
          return response;
        }

        // Clone the response
        const responseToCache = response.clone();

        // Cache successful responses
        const cache = await caches.open(RUNTIME_CACHE);
        await cache.put(event.request, responseToCache);

        return response;
      } catch (error) {
        // Network failed - could return offline page here if needed
        return new Response('Offline', {
          status: 503,
          statusText: 'Service Unavailable',
          headers: new Headers({
            'Content-Type': 'text/plain'
          })
        });
      }
    })()
  );
});
