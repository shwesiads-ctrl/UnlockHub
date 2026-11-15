document.querySelectorAll('[data-support-conversation]').forEach((panel) => {
  if (panel.dataset.poll !== '1') return;
  const messages = panel.querySelector('[data-chat-messages]');
  const status = panel.querySelector('[data-chat-status]');
  const form = panel.querySelector('[data-chat-reply]');
  const feedback = panel.querySelector('[data-send-status]');
  const jump = panel.querySelector('[data-chat-jump]');
  const file = panel.querySelector('[data-chat-file]');
  const fileName = panel.querySelector('[data-chat-file-name]');
  const token = form?.querySelector('[name=client_token]');
  const viewerIsStaff = panel.dataset.viewer === 'staff';
  const labels = { open: 'Open', waiting: 'Waiting for customer', resolved: 'Resolved', closed: 'Closed' };
  let after = Number(panel.dataset.after);
  let stopped = false;
  let sending = false;
  let polling = false;
  let closed = panel.dataset.closed === '1';
  let timer;

  const scrollDown = () => { messages.scrollTop = messages.scrollHeight; jump.hidden = true; };
  const atBottom = () => messages.scrollHeight - messages.scrollTop - messages.clientHeight < 90;
  const newToken = () => {
    if (token) token.value = globalThis.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
      const random = Math.floor(Math.random() * 16);
      return (character === 'x' ? random : (random & 3) | 8).toString(16);
    });
  };
  const updateForm = () => {
    form?.querySelectorAll('button, textarea, input[type=file]').forEach((field) => { field.disabled = closed || sending; });
    const button = form?.querySelector('button[type=submit]');
    if (button) button.textContent = sending ? 'Sending…' : 'Send message';
  };

  function receive(data, forceScroll = false) {
    const shouldScroll = forceScroll || atBottom();
    let added = false;
    for (const message of data.messages) {
      if (Number(message.id) <= after) continue;
      const article = document.createElement('article');
      article.className = `support-message ${Boolean(message.is_staff) === viewerIsStaff ? 'support-message-outgoing' : ''}`;
      article.dataset.messageId = message.id;
      const author = document.createElement('strong');
      author.className = 'text-xs';
      author.textContent = message.is_staff ? (viewerIsStaff ? 'You' : 'Support team') : (viewerIsStaff ? 'Customer' : 'You');
      const body = document.createElement('p');
      body.className = 'm-0 mt-2 whitespace-pre-wrap break-words';
      body.textContent = message.body;
      const time = document.createElement('time');
      time.className = 'block mt-2 text-xs text-muted';
      time.textContent = new Date(message.created_at).toLocaleString();
      article.append(author, body, time);
      if (message.attachment_url) {
        const attachment = document.createElement('a');
        attachment.href = message.attachment_url;
        attachment.className = 'block mt-3 text-sm';
        attachment.textContent = `Attachment: ${message.attachment_name}`;
        article.append(attachment);
      }
      messages.append(article);
      after = Number(message.id);
      added = true;
    }
    closed = ['closed', 'resolved'].includes(data.status);
    panel.parentElement.querySelector('[data-conversation-status]').textContent = labels[data.status] || data.status;
    updateForm();
    status.textContent = closed ? 'Conversation closed. Support can reopen it if needed.' : 'Connected · Messages update automatically.';
    if (added && shouldScroll) scrollDown();
    else if (added) jump.hidden = false;
  }

  async function parse(response) {
    let data;
    try { data = await response.json(); } catch { throw new Error('Session expired or server unavailable. Refresh and sign in again.'); }
    if ([401, 403, 404, 419].includes(response.status)) {
      stopped = true;
      throw new Error('Conversation unavailable or session expired. Refresh and sign in again.');
    }
    if (!response.ok) {
      const errors = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
      throw new Error(errors || 'Unable to send. Please try again.');
    }
    return data;
  }

  async function poll() {
    if (stopped || polling || sending || document.hidden) return;
    polling = true;
    try {
      const url = new URL(panel.dataset.pollUrl, location.origin);
      url.searchParams.set('after', after);
      const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: AbortSignal.timeout(15000) });
      receive(await parse(response));
    } catch (error) {
      status.textContent = stopped ? error.message : 'Connection interrupted. Retrying automatically…';
    } finally { polling = false; }
  }

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (sending || closed || stopped) return;
    if (!form.reportValidity()) return;
    sending = true;
    feedback.textContent = '';
    const body = new FormData(form);
    body.set('after', after);
    updateForm();
    try {
      const response = await fetch(form.action, {
        method: 'POST', body, headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(30000),
      });
      receive(await parse(response), true);
      form.reset();
      newToken();
      if (fileName) fileName.textContent = 'Images or PDF · 5 MB max';
      feedback.textContent = 'Message sent.';
    } catch (error) {
      feedback.textContent = `${error.message} Your message has been kept; try sending again.`;
    } finally {
      sending = false;
      updateForm();
      if (!closed && !stopped) form.querySelector('textarea')?.focus();
    }
  });

  form?.querySelector('textarea[name="message"]')?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing && event.keyCode !== 229) {
      event.preventDefault();
      form.requestSubmit();
    }
  });
  file?.addEventListener('change', () => {
    if (fileName) fileName.textContent = file.files?.[0]?.name || 'Images or PDF · 5 MB max';
  });

  jump.addEventListener('click', scrollDown);
  messages.addEventListener('scroll', () => { if (atBottom()) jump.hidden = true; });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  window.addEventListener('online', poll);
  window.addEventListener('pagehide', () => { stopped = true; clearInterval(timer); });
  window.addEventListener('pageshow', (event) => { if (event.persisted) { stopped = false; timer = setInterval(poll, 5000); poll(); } });
  newToken();
  updateForm();
  scrollDown();
  timer = setInterval(poll, 5000);
});
