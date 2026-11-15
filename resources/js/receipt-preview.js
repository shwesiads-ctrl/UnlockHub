const dialog = document.querySelector('#receipt-preview');

if (dialog && typeof dialog.showModal === 'function') {
  const image = dialog.querySelector('[data-receipt-image]');
  const loading = dialog.querySelector('[data-receipt-loading]');
  const error = dialog.querySelector('[data-receipt-error]');
  let opener;
  let previousOverflow;

  image.addEventListener('load', () => {
    loading.hidden = true;
    image.hidden = false;
  });
  image.addEventListener('error', () => {
    loading.hidden = true;
    image.hidden = true;
    error.hidden = false;
  });

  document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-receipt-preview]');
    if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    opener = link;
    loading.hidden = false;
    error.hidden = true;
    image.hidden = true;
    image.alt = link.dataset.title;
    dialog.querySelector('#receipt-preview-title').textContent = link.dataset.title;
    dialog.querySelector('[data-receipt-save]').href = link.dataset.download;
    image.src = link.href;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    dialog.showModal();
  });

  dialog.querySelector('[data-receipt-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (event) => {
    const bounds = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
  });
  dialog.addEventListener('close', () => {
    image.removeAttribute('src');
    document.body.style.overflow = previousOverflow;
    opener?.focus();
  });
}
