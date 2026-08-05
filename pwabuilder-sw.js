// ============================================
// PWA SERVICE WORKER - Memory Grid
// ============================================

const CACHE_NAME = "memory-grid-cache-v1";
const OFFLINE_URL = "/offline.html";

// Assets to cache on install
const PRECACHE_URLS = [
  "/",
  "/index.html",
  "/post.php",
  "/manifest.json",
  "/web-app-manifest-192x192.png",
  "/web-app-manifest-512x512.png"
];

// Import Workbox
importScripts("https://storage.googleapis.com/workbox-cdn/releases/5.1.2/workbox-sw.js");

// ============================================
// INSTALL EVENT
// ============================================
self.addEventListener("install", (event) => {
  console.log("📦 Service Worker installing...");
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log("📦 Caching app shell");
        return cache.addAll(PRECACHE_URLS);
      })
      .then(() => {
        console.log("✅ Installation complete");
        self.skipWaiting();
      })
      .catch((err) => {
        console.log("❌ Installation failed:", err);
      })
  );
});

// ============================================
// ACTIVATE EVENT
// ============================================
self.addEventListener("activate", (event) => {
  console.log("🔄 Service Worker activating...");
  
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((cacheName) => {
              return cacheName !== CACHE_NAME;
            })
            .map((cacheName) => {
              console.log("🗑️ Removing old cache:", cacheName);
              return caches.delete(cacheName);
            })
        );
      })
      .then(() => {
        console.log("✅ Activation complete");
        return self.clients.claim();
      })
  );
});

// ============================================
// MESSAGE HANDLER
// ============================================
self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    console.log("⏭️ Skipping waiting");
    self.skipWaiting();
  }
});

// ============================================
// ENABLE NAVIGATION PRELOAD
// ============================================
if (workbox.navigationPreload.isSupported()) {
  console.log("📡 Enabling navigation preload");
  workbox.navigationPreload.enable();
}

// ============================================
// CACHE STRATEGIES
// ============================================

// 1. HTML & Navigation - Network First
workbox.routing.registerRoute(
  ({ request }) => request.mode === "navigate",
  new workbox.strategies.NetworkFirst({
    cacheName: CACHE_NAME,
    plugins: [
      new workbox.expiration.ExpirationPlugin({
        maxEntries: 50,
        maxAgeSeconds: 30 * 24 * 60 * 60, // 30 days
      }),
    ],
  })
);

// 2. Static Assets (JS, CSS, Images) - Cache First
workbox.routing.registerRoute(
  ({ request }) => {
    return request.destination === "script" ||
           request.destination === "style" ||
           request.destination === "image";
  },
  new workbox.strategies.StaleWhileRevalidate({
    cacheName: CACHE_NAME,
    plugins: [
      new workbox.expiration.ExpirationPlugin({
        maxEntries: 60,
        maxAgeSeconds: 30 * 24 * 60 * 60, // 30 days
      }),
    ],
  })
);

// 3. API Requests (post.php) - Network Only (never cache credentials)
workbox.routing.registerRoute(
  ({ url }) => url.pathname.includes("/post.php"),
  new workbox.strategies.NetworkOnly({
    plugins: [
      new workbox.backgroundSync.BackgroundSyncPlugin("postQueue", {
        maxRetentionTime: 24 * 60, // Retry for up to 24 hours
        onSync: async ({ queue }) => {
          console.log("🔄 Syncing queued requests");
          await queue.replayRequests();
        },
      }),
    ],
  })
);

// 4. All other GET requests - StaleWhileRevalidate
workbox.routing.registerRoute(
  ({ request }) => request.method === "GET",
  new workbox.strategies.StaleWhileRevalidate({
    cacheName: CACHE_NAME,
  })
);

// ============================================
// FETCH HANDLER - Fallback for offline
// ============================================
self.addEventListener("fetch", (event) => {
  // Skip cross-origin requests
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  // Handle navigation requests
  if (event.request.mode === "navigate") {
    event.respondWith(
      (async () => {
        try {
          // Try network first
          const preloadResponse = await event.preloadResponse;
          if (preloadResponse) {
            return preloadResponse;
          }

          const networkResponse = await fetch(event.request);
          return networkResponse;
        } catch (error) {
          console.log("📴 Offline - serving cached page");
          
          // Try to serve cached index.html
          const cachedResponse = await caches.match("/index.html");
          if (cachedResponse) {
            return cachedResponse;
          }

          // If no cache, show offline message
          return new Response(
            `<!DOCTYPE html>
            <html>
            <head>
              <meta charset="UTF-8">
              <meta name="viewport" content="width=device-width, initial-scale=1.0">
              <title>Memory Grid - Offline</title>
              <style>
                body {
                  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                  background: #1a1a2e;
                  color: #fff;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  min-height: 100vh;
                  margin: 0;
                  padding: 20px;
                  text-align: center;
                }
                .container {
                  max-width: 400px;
                }
                .icon { font-size: 64px; margin-bottom: 20px; }
                h2 { margin-bottom: 12px; }
                p { color: rgba(255,255,255,0.6); line-height: 1.6; }
                .btn {
                  display: inline-block;
                  margin-top: 20px;
                  padding: 12px 32px;
                  background: #667eea;
                  color: #fff;
                  border: none;
                  border-radius: 12px;
                  font-size: 16px;
                  cursor: pointer;
                  text-decoration: none;
                }
                .btn:hover { background: #764ba2; }
              </style>
            </head>
            <body>
              <div class="container">
                <div class="icon">📶</div>
                <h2>No Internet Connection</h2>
                <p>Please reconnect to play Memory Grid and continue the game.</p>
                <button class="btn" onclick="location.reload()">Retry</button>
              </div>
            </body>
            </html>`,
            {
              status: 503,
              headers: { "Content-Type": "text/html" },
            }
          );
        }
      })()
    );
  }
});

// ============================================
// BACKGROUND SYNC - Retry failed POST requests
// ============================================
const bgSyncPlugin = new workbox.backgroundSync.BackgroundSyncPlugin("postQueue", {
  maxRetentionTime: 24 * 60, // 24 hours
  onSync: async ({ queue }) => {
    console.log("🔄 Syncing queued POST requests");
    await queue.replayRequests();
  },
});

// Register route for post.php with background sync
workbox.routing.registerRoute(
  ({ url }) => url.pathname.includes("/post.php"),
  new workbox.strategies.NetworkOnly({
    plugins: [bgSyncPlugin],
  }),
  "POST"
);

// ============================================
// LOG SERVICE WORKER STATUS
// ============================================
console.log("✅ Service Worker loaded successfully");
console.log(`📦 Cache: ${CACHE_NAME}`);
console.log("📡 Background sync enabled for post.php");
