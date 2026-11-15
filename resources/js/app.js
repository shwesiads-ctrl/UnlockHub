import './theme';
import './admin';
import './popup';
import './popup-editor';
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
