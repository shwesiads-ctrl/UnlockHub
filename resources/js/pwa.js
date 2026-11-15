// PWA: service worker, update notice, offline guard, install button, push opt-in.
const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.content || '';
const canSW = 'serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname));

function toast(html, actions = []) {
  document.getElementById('uh-toast')?.remove();
  const el = document.createElement('div');
  el.id = 'uh-toast';
  el.setAttribute('role', 'status');
  el.className = 'fixed left-1/2 -translate-x-1/2 bottom-24 lg:bottom-6 z-[60] max-w-[calc(100vw-24px)] card shadow-lg px-4 py-3 flex items-center gap-3 text-sm';
  el.innerHTML = `<span>${html}</span>`;
  actions.forEach(([label, fn, primary]) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = `btn h-9 text-[13px] ${primary ? 'btn-primary' : ''}`;
    b.textContent = label;
    b.addEventListener('click', () => { fn(); el.remove(); });
    el.append(b);
  });
  document.body.append(el);
  return el;
}

// ---- Service worker + "new version" notice
let reloading = false;
if (canSW) {
  window.addEventListener('load', async () => {
    try {
      const reg = await navigator.serviceWorker.register('/sw.js?v=' + encodeURIComponent(meta('sw-version') || '1'), { scope: '/' });
      const offer = (worker) => toast('A new version is available.', [
        ['Update', () => { worker.postMessage('SKIP_WAITING'); }, true],
        ['Later', () => {}],
      ]);
      if (reg.waiting && navigator.serviceWorker.controller) offer(reg.waiting);
      reg.addEventListener('updatefound', () => {
        const w = reg.installing;
        w?.addEventListener('statechange', () => {
          if (w.state === 'installed' && navigator.serviceWorker.controller) offer(w);
        });
      });
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloading) return;
        reloading = true;
        location.reload();
      });
    } catch { /* SW not available: site works normally */ }
  });
}

// ---- Offline: banner + never pretend an order went through
function offlineBanner() {
  let bar = document.getElementById('uh-offline');
  if (navigator.onLine) { bar?.remove(); return; }
  if (bar) return;
  bar = document.createElement('div');
  bar.id = 'uh-offline';
  bar.setAttribute('role', 'alert');
  bar.className = 'fixed top-0 inset-x-0 z-[70] bg-[#B91C1C] text-white text-center text-[13px] font-semibold py-1.5 px-3';
  bar.textContent = "You're offline — orders and payments can't be sent until you reconnect.";
  document.body.append(bar);
}
window.addEventListener('online', offlineBanner);
window.addEventListener('offline', offlineBanner);
offlineBanner();

document.addEventListener('submit', (e) => {
  if (navigator.onLine) return;
  const form = e.target;
  if ((form.method || '').toLowerCase() !== 'post') return;
  e.preventDefault();
  e.stopImmediatePropagation();
  toast("You're offline. Nothing was sent and nothing was charged — try again when you're back online.");
}, true);

// ---- Install button (Android / desktop Chrome)
let installEvent = null;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  installEvent = e;
  document.querySelectorAll('[data-install-app]').forEach((b) => { b.hidden = false; });
});
document.addEventListener('click', async (e) => {
  if (!e.target.closest('[data-install-app]') || !installEvent) return;
  installEvent.prompt();
  await installEvent.userChoice;
  installEvent = null;
  document.querySelectorAll('[data-install-app]').forEach((b) => { b.hidden = true; });
});

// ---- Push notifications opt-in: <div data-push-panel> with [data-push-status], [data-push-on], [data-push-off]
const b64ToBytes = (s) => {
  const pad = '='.repeat((4 - (s.length % 4)) % 4);
  const raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
  return Uint8Array.from(raw, (c) => c.charCodeAt(0));
};
const csrf = () => meta('csrf-token');

async function pushState(panel) {
  const status = panel.querySelector('[data-push-status]');
  const on = panel.querySelector('[data-push-on]');
  const off = panel.querySelector('[data-push-off]');
  const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;

  if (!meta('push-key')) { panel.hidden = true; return; }
  panel.hidden = false;
  if (!canSW || !('PushManager' in window) || !('Notification' in window)) {
    status.textContent = isIOS && !standalone
      ? 'On iPhone: tap Share › Add to Home Screen, open the app from the icon, then turn this on.'
      : 'This browser does not support push notifications.';
    on.hidden = off.hidden = true;
    return;
  }
  const reg = await navigator.serviceWorker.ready;
  const sub = await reg.pushManager.getSubscription();
  const denied = Notification.permission === 'denied';
  status.textContent = denied ? 'Notifications are blocked in your browser settings for this site.'
    : sub ? 'On for this device.' : 'Off for this device.';
  on.hidden = !!sub || denied;
  off.hidden = !sub;
}

document.querySelectorAll('[data-push-panel]').forEach((panel) => {
  pushState(panel);
  panel.addEventListener('click', async (e) => {
    const reg = canSW ? await navigator.serviceWorker.ready : null;
    if (!reg) return;
    try {
      if (e.target.closest('[data-push-on]')) {
        if (await Notification.requestPermission() !== 'granted') return pushState(panel);
        const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(meta('push-key')) });
        await fetch('/account/push', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ endpoint: sub.endpoint }) });
      } else if (e.target.closest('[data-push-off]')) {
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
          await fetch('/account/push', { method: 'DELETE', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ endpoint: sub.endpoint }) });
          await sub.unsubscribe();
        }
      }
    } catch { /* permission dismissed or push service unavailable */ }
    pushState(panel);
  });
});

// ---- Mobile navigation: [data-nav-toggle] opens the side menu, [data-toggle-target="#id"] shows/hides a block
document.addEventListener('click', (e) => {
  if (e.target.closest('[data-nav-toggle]')) {
    document.body.classList.toggle('nav-open');
    return;
  }
  if (e.target.closest('.nav-backdrop') || (document.body.classList.contains('nav-open') && e.target.closest('.side-nav a'))) {
    document.body.classList.remove('nav-open');
    return;
  }
  const t = e.target.closest('[data-toggle-target]');
  if (t) {
    const el = document.querySelector(t.dataset.toggleTarget);
    el?.classList.toggle('hidden');
    t.setAttribute('aria-expanded', el && !el.classList.contains('hidden') ? 'true' : 'false');
  }
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.body.classList.remove('nav-open'); });
