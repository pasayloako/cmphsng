// PWA Service Worker (No offline.html)

const CACHE = "memory-game-cache-v1";

importScripts("https://storage.googleapis.com/workbox-cdn/releases/5.1.2/workbox-sw.js");

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

// Activate immediately
self.addEventListener("activate", (event) => {
  event.waitUntil(self.clients.claim());
});

// Enable navigation preload if supported
if (workbox.navigationPreload.isSupported()) {
  workbox.navigationPreload.enable();
}

// Cache all GET requests using StaleWhileRevalidate
workbox.routing.registerRoute(
  ({ request }) => request.method === "GET",
  new workbox.strategies.StaleWhileRevalidate({
    cacheName: CACHE
  })
);

// Navigation requests
self.addEventListener("fetch", (event) => {
  if (event.request.mode === "navigate") {
    event.respondWith((async () => {
      try {
        const preloadResp = await event.preloadResponse;

        if (preloadResp) {
          return preloadResp;
        }

        return await fetch(event.request);
      } catch (err) {
        // No offline fallback page.
        return new Response(
          "No internet connection. Please reconnect and try again.",
          {
            status: 503,
            headers: {
              "Content-Type": "text/plain"
            }
          }
        );
      }
    })());
  }
});
