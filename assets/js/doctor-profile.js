(() => {
  'use strict';
  const root = document.querySelector('.doctor-profile');
  if (!root) return;
  const header = document.querySelector('.medical-header');
  const motion = typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  const reduced = () => Boolean(motion?.matches);
  const frame = typeof window.requestAnimationFrame === 'function'
    ? callback => window.requestAnimationFrame(callback)
    : callback => window.setTimeout(callback, 16);

  // IO returns only changed entries. Retain every real section's visibility,
  // and synchronize the desktop contents and horizontal mobile navigation.
  const navLinks = [...root.querySelectorAll('.dp-nav a[href^="#"],nav.dp-toc a[href^="#"]')];
  const targetFor = hash => {
    try {
      const target = document.getElementById(decodeURIComponent(hash.slice(1)));
      return target && root.contains(target) ? target : null;
    } catch (_) { return null; }
  };
  const sections = [...new Set(navLinks.map(link => targetFor(link.hash)).filter(Boolean))];
  const sectionIds = new Set(sections.map(section => section.id));
  const visibility = new Map(sections.map(section => [section, false]));
  let headerHeight = 72, pendingMeasure = false, sectionObserver, observationVersion = 0;
  const setCurrent = id => {
    if (!sectionIds.has(id)) return;
    navLinks.forEach(link => {
      const active = link.hash === '#' + id;
      link.classList.toggle('is-active', active);
      if (active) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
      const nav = active ? link.closest('.dp-nav') : null;
      // Reveal only an off-screen horizontal mobile chip. Hidden desktop
      // rails and the document itself must never be scrolled by observation.
      if (nav && typeof nav.scrollTo === 'function' && Number.isFinite(nav.clientWidth) && nav.clientWidth > 0
          && Number.isFinite(link.offsetLeft) && Number.isFinite(link.offsetWidth) && link.offsetWidth > 0
          && (link.offsetLeft < nav.scrollLeft || link.offsetLeft + link.offsetWidth > nav.scrollLeft + nav.clientWidth)) {
        nav.scrollTo({left: Math.max(0, link.offsetLeft - 12), behavior: reduced() ? 'auto' : 'smooth'});
      }
    });
  };
  const observeSections = () => {
    if (typeof window.IntersectionObserver !== 'function' || !sections.length) return;
    sectionObserver?.disconnect();
    const version = ++observationVersion;
    visibility.forEach((_, section) => visibility.set(section, false));
    sectionObserver = new window.IntersectionObserver(entries => {
      if (version !== observationVersion) return;
      entries.forEach(entry => {
        if (visibility.has(entry.target)) visibility.set(entry.target, entry.isIntersecting);
      });
      const visible = sections.filter(section => visibility.get(section));
      visible.sort((a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top);
      if (visible[0]) setCurrent(visible[0].id);
    }, {rootMargin: '-' + headerHeight + 'px 0px -50% 0px', threshold: 0});
    sections.forEach(section => sectionObserver.observe(section));
  };
  const measureHeader = () => {
    pendingMeasure = false;
    const height = header?.getBoundingClientRect().height;
    const next = Number.isFinite(height) && height > 0 ? Math.min(240, Math.max(24, Math.ceil(height))) : 72;
    root.style.setProperty('--dp-header-height', next + 'px');
    if (next !== headerHeight || !sectionObserver) {
      headerHeight = next;
      observeSections();
    }
  };
  const queueMeasure = () => {
    if (pendingMeasure) return;
    pendingMeasure = true;
    frame(measureHeader);
  };
  root.style.setProperty('--dp-header-height', headerHeight + 'px');
  queueMeasure();
  window.addEventListener('resize', queueMeasure, {passive: true});
  if (header && typeof window.ResizeObserver === 'function') new window.ResizeObserver(queueMeasure).observe(header);
  const syncHash = () => setCurrent((window.location.hash || '').slice(1));
  syncHash();
  window.addEventListener('hashchange', syncHash);
  root.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#"]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const target = targetFor(link.hash);
    if (!target || !sectionIds.has(target.id)) return;
    setCurrent(target.id);
    const heading = target.querySelector('h2,h3');
    if (heading) {
      if (!heading.hasAttribute('tabindex')) heading.setAttribute('tabindex', '-1');
      // Preserve native hash/history/scroll, then move reading focus without
      // a second page jump. Modified clicks are never intercepted.
      frame(() => heading.focus({preventScroll: true}));
    }
  });

  // The full article remains readable with JavaScript disabled.
  const article = root.querySelector('[data-dp-article]');
  const more = root.querySelector('[data-dp-read-more]');
  const articleLinkTabs = new Map();
  let hintObserver = null, revealAnimation = null;
  const stopHint = () => {
    more?.classList.remove('is-hinting');
    hintObserver?.disconnect(); hintObserver = null;
  };
  const cueReadMore = () => {
    stopHint();
    const canHint = () => more && !more.hidden && !reduced() && more.getAttribute('aria-expanded') !== 'true' && !more.matches(':hover, :focus');
    if (!canHint()) return;
    const playHint = () => { if (canHint()) more.classList.add('is-hinting'); };
    // CSS limits the cue to three iterations. Observe only until first in view.
    if (typeof window.IntersectionObserver === 'function') {
      hintObserver = new window.IntersectionObserver(entries => {
        if (!entries.some(entry => entry.isIntersecting)) return;
        playHint(); hintObserver?.disconnect(); hintObserver = null;
      }, {threshold: .65});
      hintObserver.observe(more);
    } else playHint();
  };
  const setArticleLinks = expand => article?.querySelectorAll('a').forEach(link => {
    if (expand) {
      const previousTab = articleLinkTabs.get(link);
      if (previousTab === null) link.removeAttribute('tabindex');
      else if (previousTab !== undefined) link.setAttribute('tabindex', previousTab);
    } else {
      if (!articleLinkTabs.has(link)) articleLinkTabs.set(link, link.getAttribute('tabindex'));
      link.setAttribute('tabindex', '-1');
    }
  });
  const collapseArticle = () => {
    if (!article || !more || article.scrollHeight <= 570 || !more.hidden) return;
    article.classList.add('is-collapsed'); more.hidden = false;
    setArticleLinks(false); cueReadMore();
  };
  collapseArticle();
  document.fonts?.ready?.then(collapseArticle);
  more?.addEventListener('click', () => {
    if (!article) return;
    stopHint(); revealAnimation?.cancel();
    const expand = more.getAttribute('aria-expanded') !== 'true';
    article.classList.toggle('is-collapsed', !expand); setArticleLinks(expand);
    more.setAttribute('aria-expanded', String(expand));
    const label = more.querySelector('[data-dp-read-more-label]') || more.firstChild;
    if (label) label.textContent = expand ? more.dataset.less : more.dataset.more;
    if (expand && !reduced() && typeof article.animate === 'function') {
      revealAnimation = article.animate([{opacity: .76, transform: 'translateY(6px)'}, {opacity: 1, transform: 'translateY(0)'}], {duration: 240, easing: 'cubic-bezier(.22,1,.36,1)'});
    }
    if (!expand) {
      // A user-initiated collapse returns to the newly shortened article.
      article.scrollIntoView({block: 'start', behavior: reduced() ? 'auto' : 'smooth'});
      cueReadMore();
    }
  });
  ['pointerenter', 'pointerdown', 'focus'].forEach(event => more?.addEventListener(event, stopHint));
  motion?.addEventListener?.('change', event => {
    if (event.matches) { stopHint(); revealAnimation?.cancel(); }
  });

  root.querySelectorAll('[data-dp-photo]').forEach(photo => {
    const fallback = () => { photo.hidden = true; photo.parentElement?.classList.add('is-placeholder'); };
    photo.addEventListener('error', fallback, {once: true});
    if (photo.complete && !photo.naturalWidth) fallback();
  });
  root.querySelectorAll('[data-dp-gallery-photo]').forEach(photo => {
    const remove = () => {
      const button = photo.closest('button'), figure = photo.closest('figure');
      if (button) button.hidden = true;
      if (figure) figure.hidden = true;
    };
    photo.addEventListener('error', remove, {once: true});
    if (photo.complete && !photo.naturalWidth) remove();
  });

  const viewer = root.querySelector('[data-dp-lightbox]');
  if (typeof viewer?.showModal !== 'function' || typeof viewer.close !== 'function' || typeof Image !== 'function') return;
  const buttons = [...root.querySelectorAll('[data-dp-gallery]')];
  const image = viewer.querySelector('[data-dp-viewer-image]');
  const count = viewer.querySelector('[data-dp-count]');
  const caption = viewer.querySelector('[data-dp-caption]');
  const error = viewer.querySelector('[data-dp-error]');
  const previous = viewer.querySelector('[data-dp-prev]');
  const next = viewer.querySelector('[data-dp-next]');
  const close = viewer.querySelector('[data-dp-close]');
  if (![image, count, caption, error, previous, next, close].every(Boolean)) return;
  const photoFor = button => {
    const photo = button.querySelector('img');
    const url = photo?.currentSrc || photo?.getAttribute('src') || '';
    if (!url.trim()) return null;
    const alt = button.dataset.dpGalleryAlt || photo.alt || '';
    const storedCaption = button.getAttribute('data-dp-gallery-caption');
    // An explicitly empty caption is meaningful: never replace it with the
    // figure's citation links. Legacy figures may still provide a real caption.
    const caption = storedCaption !== null ? storedCaption || alt
      : button.closest('figure')?.querySelector('figcaption')?.textContent.trim() || alt;
    return {button, url, alt, caption};
  };
  let current = 0, gallery = [], trigger = null, token = 0, previousOverflow = '', locked = false;
  const show = (index, direction = 1) => {
    if (!gallery.length) return;
    const position = Number.isFinite(index) ? Math.trunc(index) : 0;
    current = ((position % gallery.length) + gallery.length) % gallery.length;
    const entry = gallery[current], request = ++token;
    image.hidden = true; error.hidden = true; viewer.setAttribute('aria-busy', 'true');
    count.textContent = (current + 1) + ' / ' + gallery.length;
    caption.textContent = entry.caption;
    previous.hidden = next.hidden = gallery.length < 2;
    previous.disabled = next.disabled = gallery.length < 2;
    const preload = new Image(); let settled = false;
    const ready = async () => {
      if (settled) return;
      settled = true;
      try { await preload.decode?.(); } catch (_) {}
      if (request !== token || !viewer.open) return;
      image.src = entry.url; image.alt = entry.alt || entry.caption;
      image.hidden = false; viewer.setAttribute('aria-busy', 'false');
      if (!reduced() && typeof image.animate === 'function') {
        (image.getAnimations?.() || []).forEach(animation => animation.cancel());
        image.animate([{opacity: .4, transform: 'translateX(' + (direction * 12) + 'px)'}, {opacity: 1, transform: 'none'}], {duration: 240, easing: 'cubic-bezier(.22,1,.36,1)'});
      }
    };
    const failed = () => {
      if (settled) return;
      settled = true;
      if (request !== token || !viewer.open) return;
      image.hidden = true; error.hidden = false; viewer.setAttribute('aria-busy', 'false');
    };
    preload.addEventListener('load', ready, {once: true}); preload.addEventListener('error', failed, {once: true});
    preload.src = entry.url;
    if (preload.complete) (preload.naturalWidth ? ready : failed)();
  };
  buttons.forEach(button => button.addEventListener('click', () => {
    const candidates = buttons.filter(item => !item.hidden && !item.closest('figure')?.hidden).map(photoFor).filter(Boolean);
    const selected = candidates.findIndex(entry => entry.button === button);
    if (selected < 0) return;
    try { if (!viewer.open) viewer.showModal(); } catch (_) { return; }
    if (!locked) {
      previousOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden'; locked = true;
    }
    gallery = candidates; trigger = button; show(selected); close.focus({preventScroll: true});
  }));
  close.addEventListener('click', () => viewer.close());
  previous.addEventListener('click', () => show(current - 1, -1));
  next.addEventListener('click', () => show(current + 1));
  viewer.addEventListener('click', event => { if (event.target === viewer) viewer.close(); });
  viewer.addEventListener('close', () => {
    token++; image.removeAttribute('src'); image.hidden = true; error.hidden = true;
    viewer.setAttribute('aria-busy', 'false');
    (image.getAnimations?.() || []).forEach(animation => animation.cancel());
    if (locked) document.body.style.overflow = previousOverflow;
    locked = false;
    if (trigger && root.contains(trigger) && !trigger.hidden) trigger.focus({preventScroll: true});
  });
  viewer.addEventListener('keydown', event => {
    if (!viewer.open) return;
    if (event.key === 'ArrowRight') { event.preventDefault(); show(current + 1); }
    if (event.key === 'ArrowLeft') { event.preventDefault(); show(current - 1, -1); }
  });
  let touchStart = null;
  image.addEventListener('touchstart', event => {
    const point = event.touches.length === 1 ? event.touches[0] : null;
    touchStart = point ? {x: point.clientX, y: point.clientY} : null;
  }, {passive: true});
  image.addEventListener('touchend', event => {
    if (!touchStart || event.changedTouches.length !== 1) { touchStart = null; return; }
    const x = event.changedTouches[0].clientX - touchStart.x, y = event.changedTouches[0].clientY - touchStart.y;
    touchStart = null;
    if (Math.abs(x) > 55 && Math.abs(x) > Math.abs(y)) show(current + (x < 0 ? 1 : -1), x < 0 ? 1 : -1);
  }, {passive: true});
  image.addEventListener('touchcancel', () => { touchStart = null; }, {passive: true});
})();
