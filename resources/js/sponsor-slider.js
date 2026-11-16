const sliders = document.querySelectorAll('[data-sponsor-slider]');

sliders.forEach((slider) => {
  const slides = Array.from(slider.querySelectorAll('[data-sponsor-slide]'));
  const dots = Array.from(slider.querySelectorAll('[data-sponsor-dot]'));
  const previous = document.querySelector('[data-sponsor-previous]');
  const next = document.querySelector('[data-sponsor-next]');

  if (slides.length < 2) return;

  let current = 0;
  let timer = null;
  let hovered = false;
  let focused = false;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const interval = Math.max(3000, Number(slider.dataset.interval) || 5000);

  const show = (index) => {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => { slide.hidden = i !== current; });
    dots.forEach((dot, i) => {
      if (i === current) dot.setAttribute('aria-current', 'true');
      else dot.removeAttribute('aria-current');
    });
  };

  const stop = () => {
    if (timer) window.clearInterval(timer);
    timer = null;
  };

  const start = () => {
    stop();
    if (!hovered && !focused && !reducedMotion.matches && document.visibilityState === 'visible') {
      timer = window.setInterval(() => show(current + 1), interval);
    }
  };

  previous?.addEventListener('click', () => { show(current - 1); start(); });
  next?.addEventListener('click', () => { show(current + 1); start(); });
  dots.forEach((dot) => dot.addEventListener('click', () => {
    show(Number(dot.dataset.sponsorDot));
    start();
  }));

  slider.addEventListener('pointerenter', () => { hovered = true; stop(); });
  slider.addEventListener('pointerleave', () => { hovered = false; start(); });
  slider.addEventListener('focusin', () => { focused = true; stop(); });
  slider.addEventListener('focusout', (event) => {
    if (!slider.contains(event.relatedTarget)) {
      focused = false;
      start();
    }
  });
  document.addEventListener('visibilitychange', start);
  reducedMotion.addEventListener?.('change', start);

  start();
});
