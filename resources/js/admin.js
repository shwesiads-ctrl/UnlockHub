// Small helpers for admin forms (no framework needed).

// Repeater rows: <button data-add-row="#container" data-template="#tpl">, rows have [data-row] and [data-remove-row]
document.addEventListener('click', (e) => {
  const add = e.target.closest('[data-add-row]');
  if (add) {
    const box = document.querySelector(add.dataset.addRow);
    const tpl = document.querySelector(add.dataset.template);
    if (!box || !tpl) return;
    const i = Number(box.dataset.nextIndex || box.children.length);
    box.dataset.nextIndex = String(i + 1);
    box.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__INDEX__', String(i)));
    box.lastElementChild?.querySelector('input')?.focus();
    return;
  }

  const remove = e.target.closest('[data-remove-row]');
  if (remove) {
    remove.closest('[data-row]')?.remove();
    return;
  }

  // Fill selling prices from cost + profit %
  if (e.target.closest('[data-autofill-prices]')) {
    const form = e.target.closest('form');
    const cost = Number(form.querySelector('[data-cost]')?.value || 0);
    const pct = Number(form.querySelector('[data-profit]')?.value || 0);
    if (!cost) return;
    const pricing = form.querySelector('[data-service-pricing]');
    const isPoints = pricing?.dataset.priceCurrency === 'points';
    const pointRate = Math.max(1, Number(pricing?.dataset.pointRate || 1));
    const round = (n) => isPoints ? Math.ceil(n / pointRate) : Math.ceil(n / 100) * 100;
    form.querySelector('[data-price]').value = round(cost * (1 + pct / 100));
    // Lower groups get a smaller margin: Customer full %, next groups step down
    const groups = [...form.querySelectorAll('[data-group-price]')];
    groups.forEach((input, idx) => {
      const p = Math.max(pct - idx * (pct / (groups.length + 1)), 0);
      input.value = round(cost * (1 + p / 100));
    });
  }
});

// Supplier import: "select group" checkbox + live selected count
function updateImportCount(form) {
  const counter = form?.querySelector('[data-selected-count]');
  if (counter) counter.textContent = form.querySelectorAll('input[name="ids[]"]:checked').length;
}
document.addEventListener('change', (e) => {
  const form = e.target.closest('[data-import-form]');
  if (!form) return;
  const group = e.target.dataset.checkGroup;
  if (group) {
    form.querySelectorAll(`[data-group="${group}"]`).forEach((cb) => { cb.checked = e.target.checked; });
  }
  updateImportCount(form);
});

// Processing mode radio → show API settings
document.addEventListener('change', (e) => {
  if (e.target.name !== 'processing_mode') return;
  const section = e.target.closest('[data-mode-section]');
  section?.querySelector('[data-api-only]')?.classList.toggle('hidden', e.target.value !== 'api');
});

// Top-up approval in a foreign currency: credited Ks = received × rate.
document.querySelectorAll('[data-approve-calc]').forEach((box) => {
  const form = box.closest('form');
  const amount = box.querySelector('[data-calc-amount]');
  const rate = box.querySelector('[data-calc-rate]');
  const result = form && form.querySelector('[data-calc-result]');
  if (!amount || !rate || !result) return;
  const calc = () => {
    const ks = Number(amount.value || 0) * Number(rate.value || 0);
    if (ks > 0) result.value = Math.round(ks);
  };
  amount.addEventListener('input', calc);
  rate.addEventListener('input', calc);
});
