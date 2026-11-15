// Popups (<x-popup>): auto-open with a frequency limit, open from buttons, close on backdrop.
const store = (kind) => { try { return kind === 'session' ? sessionStorage : localStorage; } catch { return null; } };
const today = () => new Date().toISOString().slice(0, 10);

function due(d) {
  const key = 'uh-popup:' + d.dataset.popup;
  const f = d.dataset.frequency;
  if (f === 'always') return true;
  const s = store(f === 'session' ? 'session' : 'local');
  const v = s?.getItem(key);
  if (f === 'daily') return v !== today();
  return !v; // session / once
}

function remember(d) {
  const f = d.dataset.frequency;
  if (f === 'always') return;
  store(f === 'session' ? 'session' : 'local')?.setItem('uh-popup:' + d.dataset.popup, f === 'daily' ? today() : '1');
}

// Show auto popups one after another (home popup, then service popup…)
const queue = [...document.querySelectorAll('dialog[data-popup][data-popup-auto="1"]')].filter(due);
function next() {
  const d = queue.shift();
  if (!d) return;
  setTimeout(() => {
    if (document.querySelector('dialog[open]')) { queue.unshift(d); return; }
    d.showModal();
    remember(d);
    d.addEventListener('close', next, { once: true });
  }, Number(d.dataset.delay || 0) * 1000);
}
if (queue.length) next();

document.addEventListener('click', (e) => {
  const opener = e.target.closest('[data-open-popup]');
  if (opener) {
    document.getElementById(opener.dataset.openPopup)?.showModal();
    return;
  }
  const closer = e.target.closest('[data-close-dialog]');
  if (closer) {
    closer.closest('dialog')?.close();
    return;
  }
  // Click on the dark backdrop closes
  if (e.target instanceof HTMLDialogElement && e.target.matches('dialog[data-popup], dialog#popup-preview-dialog')) {
    e.target.close();
    return;
  }
  // "#order" buttons inside a popup: close it and jump to the order form
  const order = e.target.closest('[data-popup-order]');
  if (order) {
    order.closest('dialog')?.close();
    const form = document.getElementById('order');
    form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    form?.querySelector('input, textarea, select')?.focus({ preventScroll: true });
  }
});
