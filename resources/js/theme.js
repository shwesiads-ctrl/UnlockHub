// Day / night toggle. The initial class is set inline in <head> to avoid a flash.
const KEY = 'uh-theme';

function apply(theme) {
  document.documentElement.classList.toggle('dark', theme === 'dark');
  document.querySelectorAll('[data-theme-toggle]').forEach((b) => {
    b.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
  });
}

document.addEventListener('click', (e) => {
  if (!e.target.closest('[data-theme-toggle]')) return;
  const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
  try { localStorage.setItem(KEY, next); } catch (_) {}
  apply(next);
});

apply(document.documentElement.classList.contains('dark') ? 'dark' : 'light');
