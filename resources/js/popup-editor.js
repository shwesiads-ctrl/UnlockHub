// Admin popup builder: <div data-popup-editor> … (see resources/views/admin/popups/_editor.blade.php)
// Keeps the popup as JSON in a hidden input and shows a live server-rendered preview.

const TONES = { info: 'Blue (info)', success: 'Green (success)', warning: 'Orange (warning)', danger: 'Red (danger)', amber: 'Yellow', plain: 'Grey', dark: 'Black' };

// [key, label, kind, options/columns]
const BLOCKS = {
  heading: { label: 'Section heading', fields: [['text', 'Heading', 'input']] },
  text: { label: 'Paragraph', fields: [['text', 'Text', 'textarea']] },
  list: {
    label: 'List',
    fields: [
      ['style', 'Style', 'select', { bullet: '• Bullets', check: '✅ Check marks', cross: '❌ Crosses (red)', number: '1. Numbers' }],
      ['items', 'Items — one per line', 'lines'],
    ],
  },
  box: {
    label: 'Coloured box',
    fields: [
      ['tone', 'Colour', 'select', TONES],
      ['title', 'Title', 'input'],
      ['text', 'Text', 'textarea'],
      ['items', 'Bullet items — one per line (optional)', 'lines'],
      ['link_label', 'Link text (optional)', 'input'],
      ['link_url', 'Link URL', 'input'],
      ['half', 'Half width — two boxes side by side', 'check'],
    ],
  },
  steps: {
    label: 'Steps',
    fields: [
      ['title', 'Label (black pill)', 'input'],
      ['items', 'One step per line:  Title | Text | Link text | Link URL', 'pipes', ['title', 'text', 'link_label', 'link_url']],
    ],
  },
  chips: { label: 'Chips grid (countries, models…)', fields: [['title', 'Title', 'input'], ['items', 'Chips — one per line', 'lines']] },
  table: {
    label: 'Info table (checker sample)',
    fields: [
      ['title', 'Title', 'input'],
      ['rows', 'One row per line:  Key | Value | colour (green, red, amber, gray — optional)', 'pipes', ['key', 'value', 'tone']],
    ],
  },
  buttons: {
    label: 'Buttons',
    fields: [['items', 'One button per line (max 4):  Label | URL | colour\nColours: green, blue, dark, orange, red, purple, whatsapp, telegram. URL "#order" jumps to the order form.', 'pipes', ['label', 'url', 'color']]],
  },
};

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

function toText(kind, value, cols) {
  if (kind === 'lines') return (value || []).join('\n');
  if (kind === 'pipes') {
    return (value || []).map((r) => {
      const parts = cols.map((c) => r[c] ?? '');
      while (parts.length > 1 && parts[parts.length - 1] === '') parts.pop();
      return parts.join(' | ');
    }).join('\n');
  }
  return value ?? '';
}

function fromText(kind, text, cols) {
  const lines = text.split('\n').map((l) => l.trim()).filter(Boolean);
  if (kind === 'lines') return lines;
  return lines.map((l) => {
    const parts = l.split('|').map((p) => p.trim());
    const row = {};
    cols.forEach((c, i) => { row[c] = parts[i] ?? ''; });
    // Let the last column swallow extra "|" (e.g. URLs with pipes are rare, text more likely)
    if (parts.length > cols.length) row[cols[cols.length - 1]] = parts.slice(cols.length - 1).join(' | ');
    return row;
  });
}

function fieldHtml(block, [key, label, kind, extra]) {
  const id = `pb-${Math.random().toString(36).slice(2, 9)}`;
  const value = block[key];
  if (kind === 'check') {
    return `<label class="flex items-center gap-2 text-sm"><input type="checkbox" data-k="${key}" data-kind="check" class="w-4 h-4 accent-[var(--uh-brand)]" ${value ? 'checked' : ''}>${esc(label)}</label>`;
  }
  let input;
  if (kind === 'select') {
    input = `<select id="${id}" data-k="${key}" data-kind="select" class="input h-10">${Object.entries(extra).map(([v, l]) => `<option value="${v}" ${v === value ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>`;
  } else if (kind === 'input') {
    input = `<input id="${id}" data-k="${key}" data-kind="input" value="${esc(value)}" class="input h-10">`;
  } else {
    const rows = kind === 'textarea' ? 2 : Math.min(8, Math.max(3, (value || []).length + 1));
    const cols = kind === 'pipes' ? ` data-cols="${extra.join(',')}"` : '';
    input = `<textarea id="${id}" data-k="${key}" data-kind="${kind}"${cols} rows="${rows}" class="input font-[inherit] text-[13px]">${esc(toText(kind, value, extra))}</textarea>`;
  }
  return `<div class="flex flex-col gap-1"><label for="${id}" class="text-xs font-semibold text-muted whitespace-pre-line">${esc(label)}</label>${input}</div>`;
}

function blockHtml(block, i, total) {
  const def = BLOCKS[block.type];
  if (!def) return '';
  const wide = def.fields.length > 1 && block.type !== 'buttons';
  return `<div data-block="${i}" class="rounded-xl border border-line bg-bg p-3.5 flex flex-col gap-3">
    <div class="flex items-center gap-2">
      <span class="badge badge-info">${i + 1}</span>
      <span class="font-semibold text-sm">${esc(def.label)}</span>
      <span class="ml-auto flex gap-1">
        <button type="button" class="btn h-8 px-2 text-xs" data-move="-1" ${i === 0 ? 'disabled' : ''} aria-label="Move up">↑</button>
        <button type="button" class="btn h-8 px-2 text-xs" data-move="1" ${i === total - 1 ? 'disabled' : ''} aria-label="Move down">↓</button>
        <button type="button" class="btn h-8 px-2 text-xs" data-duplicate aria-label="Duplicate">⧉</button>
        <button type="button" class="btn btn-danger h-8 px-2 text-xs" data-remove aria-label="Remove">✕</button>
      </span>
    </div>
    <div class="grid gap-3 ${wide ? 'sm:grid-cols-2' : ''}">
      ${def.fields.map((f) => `<div class="${['textarea', 'lines', 'pipes'].includes(f[2]) ? 'sm:col-span-2' : ''}">${fieldHtml(block, f)}</div>`).join('')}
    </div>
  </div>`;
}

function newBlock(type) {
  const b = { type };
  BLOCKS[type].fields.forEach(([k, , kind]) => {
    b[k] = kind === 'lines' || kind === 'pipes' ? [] : kind === 'check' ? false : kind === 'select' ? undefined : '';
  });
  if (type === 'box') b.tone = 'info';
  if (type === 'list') b.style = 'bullet';
  if (type === 'buttons') b.items = [{ label: 'Contact us', url: '/contact', color: 'green' }];
  return b;
}

function init(root) {
  const hidden = root.querySelector('[data-popup-json]');
  const list = root.querySelector('[data-blocks]');
  const preview = root.querySelector('[data-preview]');
  const templates = JSON.parse(root.querySelector('[data-templates]')?.textContent || '{}');
  let state = JSON.parse(hidden.value || '{}');
  state.blocks ||= [];

  // Top-level settings: inputs with [data-top="key"]
  const tops = [...root.querySelectorAll('[data-top]')];
  const fillTops = () => tops.forEach((el) => {
    const v = state[el.dataset.top];
    if (el.type === 'checkbox') el.checked = !!v;
    else if (v !== undefined) el.value = v;
  });

  let timer;
  const sync = (now = false) => {
    hidden.value = JSON.stringify(state);
    clearTimeout(timer);
    timer = setTimeout(refreshPreview, now ? 0 : 350);
  };

  async function refreshPreview() {
    if (!preview) return;
    try {
      const res = await fetch(root.dataset.previewUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          Accept: 'text/html',
        },
        body: JSON.stringify({ popup: state, title: root.dataset.barTitle || '', delivery: root.querySelector('#delivery_time')?.value || '' }),
      });
      if (res.ok) preview.innerHTML = await res.text();
    } catch { /* offline: keep the old preview */ }
  }

  const render = () => {
    list.innerHTML = state.blocks.length
      ? state.blocks.map((b, i) => blockHtml(b, i, state.blocks.length)).join('')
      : '<p class="m-0 text-sm text-muted text-center py-6 rounded-xl border border-dashed border-line">No blocks yet — pick a template above or add a block below.</p>';
    sync();
  };

  root.addEventListener('input', (e) => onEdit(e));
  root.addEventListener('change', (e) => onEdit(e));
  function onEdit(e) {
    const el = e.target;
    if (el.dataset.top) {
      state[el.dataset.top] = el.type === 'checkbox' ? el.checked : el.value;
      sync();
      return;
    }
    const wrap = el.closest('[data-block]');
    if (!wrap || !el.dataset.k) return;
    const b = state.blocks[Number(wrap.dataset.block)];
    const kind = el.dataset.kind;
    b[el.dataset.k] = kind === 'check' ? el.checked
      : kind === 'lines' || kind === 'pipes' ? fromText(kind, el.value, (el.dataset.cols || '').split(','))
      : el.value;
    sync();
  }

  root.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn || !root.contains(btn)) return;
    const wrap = btn.closest('[data-block]');
    const i = wrap ? Number(wrap.dataset.block) : -1;

    if (btn.dataset.move) {
      const j = i + Number(btn.dataset.move);
      [state.blocks[i], state.blocks[j]] = [state.blocks[j], state.blocks[i]];
      render();
    } else if (btn.hasAttribute('data-remove')) {
      state.blocks.splice(i, 1);
      render();
    } else if (btn.hasAttribute('data-duplicate')) {
      state.blocks.splice(i + 1, 0, structuredClone(state.blocks[i]));
      render();
    } else if (btn.hasAttribute('data-add-block')) {
      const type = root.querySelector('[data-block-type]').value;
      state.blocks.push(newBlock(type));
      render();
      list.lastElementChild?.querySelector('input, textarea')?.focus();
    } else if (btn.hasAttribute('data-load-template')) {
      const key = root.querySelector('[data-template-select]').value;
      if (!templates[key]) return;
      if (state.blocks.length && !confirm('Replace the current popup with this template?')) return;
      load(templates[key].popup);
    } else if (btn.hasAttribute('data-copy-from')) {
      const sel = root.querySelector('[data-copy-select]');
      if (!sel.value) return;
      if (state.blocks.length && !confirm('Replace the current popup with the one from that service?')) return;
      fetch(sel.value, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((p) => p && load(p));
    } else if (btn.hasAttribute('data-preview-open')) {
      // Full-size preview in a real dialog
      const d = document.getElementById('popup-preview-dialog');
      d.querySelector('[data-preview-full]').innerHTML = preview.innerHTML;
      d.showModal();
    }
  });

  function load(p) {
    // Keep the "when/how often/who" settings, take content from the template
    const keep = ['trigger', 'frequency', 'audience', 'pages', 'delay'];
    const kept = Object.fromEntries(keep.filter((k) => k in state).map((k) => [k, state[k]]));
    state = { ...structuredClone(p), ...kept, enabled: true };
    state.blocks ||= [];
    fillTops();
    render();
  }

  fillTops();
  render();
  refreshPreview();
}

document.querySelectorAll('[data-popup-editor]').forEach(init);
