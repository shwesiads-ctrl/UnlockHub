/*
 * UnlockHub service worker.
 * - Caches ONLY static files (/build/*, /icons/*, /offline.html). Pages, wallet, receipts,
 *   API keys and anything personal are never stored: they always come from the network.
 * - Navigations that fail show /offline.html. POST requests are never queued or replayed.
 * - Push messages carry no data; the worker asks the server for the latest notification.
 */
const VERSION = new URL(self.location).searchParams.get('v') || '1';
const CACHE = 'uh-static-' + VERSION;
const PRECACHE = ['/offline.html', '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) {
      if (key.startsWith('uh-') && key !== CACHE) await caches.delete(key);
    }
    await self.clients.claim();
  })());
});

self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
});

const isStatic = (url) => url.origin === self.location.origin
  && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/offline.html');

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return; // never touch form posts / orders

  const url = new URL(req.url);

  if (req.mode === 'navigate') {
    // Network only (no copies of pages); offline → friendly page
    event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }

  if (isStatic(url)) {
    // Hashed build files never change: cache first
    event.respondWith((async () => {
      const cache = await caches.open(CACHE);
      const hit = await cache.match(req);
      if (hit) return hit;
      const res = await fetch(req);
      if (res.ok && res.type === 'basic') cache.put(req, res.clone());
      return res;
    })());
  }
  // Everything else: browser default (not cached by us)
});

self.addEventListener('push', (event) => {
  event.waitUntil((async () => {
    let n = { title: 'New notification', body: 'Open the app to see it.', url: '/account/notifications' };
    try {
      const res = await fetch('/account/push/latest', { credentials: 'include', headers: { Accept: 'application/json' } });
      if (res.ok) n = { ...n, ...(await res.json()) };
    } catch { /* offline: generic text */ }
    await self.registration.showNotification(n.title, {
      body: n.body,
      icon: '/icons/icon-192.png',
      badge: '/icons/icon-192.png',
      tag: n.tag || 'uh-notification',
      data: { url: n.url },
    });
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL(event.notification.data?.url || '/account', self.location.origin).href;
  event.waitUntil((async () => {
    for (const c of await self.clients.matchAll({ type: 'window', includeUncontrolled: true })) {
      if (c.url === target && 'focus' in c) return c.focus();
    }
    return self.clients.openWindow(target);
  })());
});
