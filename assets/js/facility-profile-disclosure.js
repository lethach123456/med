(() => {
  'use strict';
  const root = document.querySelector('[data-facility-profile]');
  if (!root) return;
  const content = root.querySelector('[data-facility-profile-content]');
  const button = root.querySelector('[data-facility-read-more]');
  if (!content || !button) return;
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  const originalTabIndexes = new Map();
  let hintObserver = null;
  let revealAnimation = null;
  const stopHint = () => {
    button.classList.remove('is-hinting');
    hintObserver?.disconnect();
    hintObserver = null;
  };
  const cueHint = () => {
    if (button.hidden || reducedMotion.matches || button.getAttribute('aria-expanded') === 'true') return;
    if ('IntersectionObserver' in window) {
      hintObserver = new IntersectionObserver(entries => {
        if (!entries.some(entry => entry.isIntersecting)) return;
        button.classList.add('is-hinting');
        hintObserver?.disconnect();
        hintObserver = null;
      }, {threshold: .65});
      hintObserver.observe(button);
    } else button.classList.add('is-hinting');
  };
  const updateFocus = () => {
    originalTabIndexes.forEach((value, element) => {
      if (value === null) element.removeAttribute('tabindex');
      else element.setAttribute('tabindex', value);
    });
    originalTabIndexes.clear();
    if (!content.classList.contains('is-collapsed')) return;
    const bottom = content.getBoundingClientRect().bottom - 72;
    content.querySelectorAll('a,button,input,select,textarea,[tabindex]').forEach(element => {
      if (element.getBoundingClientRect().bottom > bottom) {
        originalTabIndexes.set(element, element.getAttribute('tabindex'));
        element.setAttribute('tabindex', '-1');
      }
    });
  };
  const syncPreview = () => {
    const limit = parseFloat(getComputedStyle(content).getPropertyValue('--facility-preview-height')) || 460;
    const expandable = content.scrollHeight > limit + 24;
    const wasHidden = button.hidden;
    button.hidden = !expandable;
    content.classList.toggle('is-collapsed', expandable && button.getAttribute('aria-expanded') !== 'true');
    updateFocus();
    if (!expandable) stopHint();
    else if (wasHidden) cueHint();
  };
  button.addEventListener('click', () => {
    stopHint();
    revealAnimation?.cancel();
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open));
    button.querySelector('[data-facility-read-label]').textContent = open ? button.dataset.less : button.dataset.more;
    content.classList.toggle('is-collapsed', !open);
    updateFocus();
    if (open && !reducedMotion.matches && content.animate) {
      revealAnimation = content.animate([{opacity: .76, transform: 'translateY(6px)'}, {opacity: 1, transform: 'none'}], {duration: 240, easing: 'cubic-bezier(.22,1,.36,1)'});
    }
    if (!open) {
      root.scrollIntoView({block: 'start', behavior: reducedMotion.matches ? 'auto' : 'smooth'});
      cueHint();
    }
  });
  ['pointerenter', 'pointerdown', 'focus'].forEach(event => button.addEventListener(event, stopHint));
  reducedMotion.addEventListener?.('change', event => {
    if (event.matches) {stopHint(); revealAnimation?.cancel();}
  });
  // Full content stays readable without JS; short profiles need no control.
  syncPreview();
  document.fonts?.ready.then(syncPreview);
  if ('ResizeObserver' in window) new ResizeObserver(syncPreview).observe(content);
  // Direct section links must not land inside a clipped area.
  const revealAnchor = () => {
    const target = document.getElementById(location.hash.slice(1));
    if (target && content.contains(target) && content.classList.contains('is-collapsed')) button.click();
  };
  revealAnchor();
  window.addEventListener('hashchange', revealAnchor);
  window.addEventListener('beforeprint', () => {
    content.classList.remove('is-collapsed');
    updateFocus();
  });
  window.addEventListener('afterprint', syncPreview);
})();
