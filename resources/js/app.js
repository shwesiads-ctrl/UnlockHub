import './theme';
import './admin';

// Quick-fill buttons: <button data-fill="#input" data-value="10000">
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-fill]');
  if (!btn) return;
  const input = document.querySelector(btn.dataset.fill);
  if (input) { input.value = btn.dataset.value; input.focus(); }
});

// File inputs inside [data-dropzone]: show the chosen file name
document.addEventListener('change', (e) => {
  const zone = e.target.closest('[data-dropzone]');
  if (!zone || e.target.type !== 'file') return;
  const label = zone.querySelector('[data-file-name]');
  if (label && e.target.files[0]) label.textContent = e.target.files[0].name;
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
