import './theme';
import './admin';
import './popup';
import './popup-editor';
import './pwa';
import './receipt-preview';

// Quick-fill buttons: <button data-fill="#input" data-value="10000">
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-fill]');
  if (!btn) return;
  const input = document.querySelector(btn.dataset.fill);
  if (input) { input.value = btn.dataset.value; input.focus(); }
});

// Forms with [data-auto-submit] submit as soon as a file is chosen (avatar upload)
document.addEventListener('change', (e) => {
  const form = e.target.closest('form[data-auto-submit]');
  if (form && e.target.type === 'file' && e.target.files.length) form.submit();
});

// File inputs inside [data-dropzone]: show the chosen file name
document.addEventListener('change', (e) => {
  const zone = e.target.closest('[data-dropzone]');
  if (!zone || e.target.type !== 'file') return;
  const label = zone.querySelector('[data-file-name]');
  if (label && e.target.files[0]) label.textContent = e.target.files[0].name;
});

// Points redeem: live "You get" value
document.addEventListener('input', (e) => {
  const form = e.target.closest('[data-redeem]');
  if (!form || e.target.name !== 'points') return;
  const out = form.querySelector('[data-redeem-value]');
  const ks = Math.max(0, Number(e.target.value || 0)) * Number(form.dataset.rate || 0);
  if (out) out.textContent = ks.toLocaleString('en-US', { maximumFractionDigits: 0 }) + ' Ks';
});

// Copy-to-clipboard buttons: <button data-copy="text">
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-copy]');
  if (!btn) return;
  navigator.clipboard?.writeText(btn.dataset.copy).then(() => {
    const old = btn.textContent;
    btn.textContent = 'Copied';
    setTimeout(() => (btn.textContent = old), 1200);
  });
});

// Forms with [data-submit-once]: disable the submit button after the first click (no double orders)
document.addEventListener('submit', (e) => {
  const form = e.target;
  if (!form.matches('form[data-submit-once]')) return;
  if (form.dataset.submitting === '1') { e.preventDefault(); return; }
  form.dataset.submitting = '1';
  form.querySelectorAll('button:not([type="button"]), input[type="submit"]').forEach((b) => {
    b.disabled = true;
    if (b.tagName === 'BUTTON') { b.dataset.label = b.textContent; b.textContent = 'Please wait…'; }
  });
});
// Back/forward cache: re-enable when the page is shown again
window.addEventListener('pageshow', () => {
  document.querySelectorAll('form[data-submit-once][data-submitting="1"]').forEach((form) => {
    form.dataset.submitting = '';
    form.querySelectorAll('button[disabled]').forEach((b) => { b.disabled = false; if (b.dataset.label) b.textContent = b.dataset.label; });
  });
});

// Ticket reply: insert a saved reply at the cursor
document.addEventListener('change', (e) => {
  const sel = e.target.closest('select[data-canned]');
  if (!sel || !sel.value) return;
  const ta = sel.closest('form').querySelector('textarea[name="message"]');
  const at = ta.selectionStart ?? ta.value.length;
  ta.value = ta.value.slice(0, at) + sel.value + ta.value.slice(at);
  ta.focus();
  sel.value = '';
});

// Favourite star: toggle without leaving the page (falls back to a normal post)
document.addEventListener('submit', async (e) => {
  const form = e.target.closest('form[data-favorite]');
  if (!form) return;
  e.preventDefault();
  const btn = form.querySelector('button');
  try {
    const res = await fetch(form.action, {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value },
    });
    if (!res.ok) throw new Error();
    const { favorite } = await res.json();
    btn.setAttribute('aria-pressed', favorite ? 'true' : 'false');
    btn.classList.toggle('text-point', favorite);
    btn.classList.toggle('text-muted', !favorite && btn.classList.contains('text-lg'));
    const star = btn.querySelector('[aria-hidden]') || btn;
    if (star === btn && !btn.querySelector('[data-fav-label]')) btn.textContent = favorite ? '★' : '☆';
    else star.textContent = favorite ? '★' : '☆';
    const label = btn.querySelector('[data-fav-label]');
    if (label) label.textContent = favorite ? 'Favourite' : 'Add to favourites';
  } catch {
    form.submit();
  }
});

// Bulk preview: filter rows by status
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-row-filter]');
  if (!btn) return;
  const status = btn.dataset.rowFilter;
  btn.parentElement.querySelectorAll('[data-row-filter]').forEach((b) => b.classList.toggle('btn-primary', b === btn));
  document.querySelectorAll('[data-rows] [data-status]').forEach((tr) => {
    tr.hidden = status !== 'all' && tr.dataset.status !== status;
  });
});

// Online top-up: live "You get … Ks"
document.addEventListener('input', (e) => {
  const form = e.target.closest('form[data-online-topup]');
  if (!form || e.target.name !== 'amount') return;
  const amount = Math.max(0, Number(e.target.value || 0));
  const ks = amount * Number(form.dataset.rate || 0);
  const total = amount * (1 + Number(form.dataset.fee || 0) / 100);
  form.querySelector('[data-online-ks]').textContent = ks ? Math.round(ks).toLocaleString('en-US') + ' Ks' : '—';
  const t = form.querySelector('[data-online-total]');
  if (t) t.textContent = amount ? total.toFixed(2) : '—';
});

// reCAPTCHA v3: get a fresh token right before the form is sent
document.addEventListener('submit', (e) => {
  const form = e.target;
  const box = form.querySelector('[data-captcha="recaptcha_v3"]');
  if (!box || form.dataset.captchaReady === '1' || !window.grecaptcha) return;
  e.preventDefault();
  e.stopImmediatePropagation();
  window.grecaptcha.ready(() => {
    window.grecaptcha.execute(box.dataset.sitekey, { action: box.dataset.action }).then((token) => {
      form.querySelector('input[name="g-recaptcha-response"]').value = token;
      form.dataset.captchaReady = '1';
      form.requestSubmit ? form.requestSubmit() : form.submit();
      setTimeout(() => { form.dataset.captchaReady = ''; }, 3000);
    });
  });
}, true);

// Language switcher: close the dropdown on outside click or Escape.
document.addEventListener('click', (e) => {
  document.querySelectorAll('details[data-locale-switcher][open]').forEach((d) => {
    if (!d.contains(e.target)) d.removeAttribute('open');
  });
});
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  document.querySelectorAll('details[data-locale-switcher][open]').forEach((d) => d.removeAttribute('open'));
});

// Add Funds in a foreign currency: show roughly how many Ks the wallet gets.
const payKs = (input) => {
  const out = input.form && input.form.querySelector('[data-pay-ks]');
  if (!out) return;
  const ks = Math.max(0, Number(input.value || 0)) * Number(input.dataset.payRate || 0);
  out.textContent = ks ? '≈ ' + Math.round(ks).toLocaleString('en-US') + ' Ks' : '—';
};
document.addEventListener('input', (e) => {
  if (e.target.matches && e.target.matches('[data-pay-rate]')) payKs(e.target);
});
document.addEventListener('click', (e) => {
  const btn = e.target.closest && e.target.closest('[data-fill]');
  if (!btn) return;
  setTimeout(() => {
    const input = document.querySelector(btn.dataset.fill);
    if (input && input.dataset.payRate) payKs(input);
  });
});
document.querySelectorAll('[data-pay-rate]').forEach(payKs);

// Login waiting time: live countdown, submit buttons stay disabled until it ends.
document.querySelectorAll('[data-login-wait]').forEach((box) => {
  const form = box.closest('form') || box.parentElement.querySelector('form') || document.querySelector('form[action$="/login"], form[action*="/login/verify"]');
  const buttons = form ? form.querySelectorAll('button:not([type="button"])') : [];
  const clock = box.querySelector('[data-login-wait-clock]');
  const end = Date.now() + Number(box.dataset.loginWait) * 1000;
  buttons.forEach((b) => { b.disabled = true; });
  const tick = () => {
    const s = Math.max(0, Math.round((end - Date.now()) / 1000));
    const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
    clock.textContent = (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(sec).padStart(2, '0');
    if (s <= 0) {
      buttons.forEach((b) => { b.disabled = false; });
      box.classList.remove('bg-warn-soft', 'text-warn');
      box.classList.add('bg-ok-soft', 'text-ok');
      box.querySelector('span').textContent = box.dataset.readyText || '✓';
      return;
    }
    setTimeout(tick, 1000);
  };
  tick();
});
