import './theme';

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
