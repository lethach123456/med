(() => {
  'use strict';
  const root = document.querySelector('.doctor-toplist');
  if (!root) return;
  const motion = typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  const reduced = () => Boolean(motion?.matches);
  const frame = typeof window.requestAnimationFrame === 'function'
    ? callback => window.requestAnimationFrame(callback)
    : callback => window.setTimeout(callback, 16);
  const header = document.querySelector('.medical-header');
  const members = [...root.querySelectorAll('[data-td-member]')];
  const toc = [...root.querySelectorAll('nav.td-toc a[href^="#rank-"]')];
  const memberIds = new Set(members.map(member => member.id));
  let headerOffset = 100, pendingMeasure = false, observer, observationVersion = 0;
  const visibility = new Map(members.map(member => [member, false]));
  const setCurrent = id => {
    if (!memberIds.has(id)) return;
    toc.forEach(link => {
      if (link.hash === '#' + id) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
  };
  const observeMembers = () => {
    if (typeof window.IntersectionObserver !== 'function' || !members.length) return;
    observer?.disconnect();
    const version = ++observationVersion;
    visibility.forEach((_, member) => visibility.set(member, false));
    observer = new window.IntersectionObserver(entries => {
      if (version !== observationVersion) return;
      entries.forEach(entry => {
        if (visibility.has(entry.target)) visibility.set(entry.target, entry.isIntersecting);
      });
      // IO callbacks contain only changed targets. Keep every visible member
      // in the map so a later callback cannot select a lower unchanged card.
      const visible = members.filter(member => visibility.get(member));
      visible.sort((a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top);
      if (visible[0]) setCurrent(visible[0].id);
    }, {rootMargin:'-' + headerOffset + 'px 0px -55% 0px', threshold:0});
    members.forEach(member => observer.observe(member));
  };
  const measureHeader = () => {
    pendingMeasure = false;
    const height = header?.getBoundingClientRect().height;
    const next = Number.isFinite(height) && height > 0 ? Math.min(252, Math.max(12, Math.ceil(height) + 12)) : 100;
    root.style.setProperty('--td-header-offset', next + 'px');
    if (next !== headerOffset || !observer) {
      headerOffset = next;
      observeMembers();
    }
  };
  const queueMeasure = () => {
    if (pendingMeasure) return;
    pendingMeasure = true;
    frame(measureHeader);
  };
  root.style.setProperty('--td-header-offset', headerOffset + 'px');
  queueMeasure();
  window.addEventListener('resize', queueMeasure, {passive:true});
  if (header && typeof window.ResizeObserver === 'function') {
    const headerObserver = new window.ResizeObserver(queueMeasure);
    headerObserver.observe(header);
  }
  const syncHash = () => setCurrent((window.location.hash || '').slice(1));
  syncHash();
  window.addEventListener('hashchange', syncHash);
  const recover = image => {
    image.hidden = true;
    // Inline article photos may live in an ordinary text paragraph. Do not put
    // an absolutely positioned cover placeholder over the surrounding text.
    if (!image.hasAttribute('data-td-image') || image.closest('.td-summary-avatars')) return;
    const holder = image.parentElement;
    if (!holder || holder.querySelector('.td-photo-fallback')) return;
    const fallback = document.createElement('span');
    fallback.className = 'td-photo-fallback'; fallback.setAttribute('aria-hidden', 'true');
    const icon = root.querySelector('.td-cover-placeholder svg') || root.querySelector('.td-eyebrow svg');
    if (icon) fallback.append(icon.cloneNode(true));
    holder.append(fallback);
  };
  root.querySelectorAll('[data-td-image],.td-article-content img,.td-intro img').forEach(image => {
    image.addEventListener('error', () => recover(image), {once:true});
    if (image.complete && !image.naturalWidth) recover(image);
  });
  root.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#"]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const menu = root.querySelector('.td-mobile-toc');
    const inMenu = menu?.contains(link);
    let target = members.find(member => '#' + member.id === link.hash);
    if (!target && inMenu) {
      try { target = document.getElementById(decodeURIComponent(link.hash.slice(1))); } catch (_) {}
    }
    if (!target || !root.contains(target)) return;
    if (inMenu) menu.open = false;
    const heading = target?.querySelector('h2');
    if (heading) {
      if (memberIds.has(target.id)) setCurrent(target.id);
      if (!heading.hasAttribute('tabindex')) heading.setAttribute('tabindex', '-1');
      // Closing native details happens before the browser's default hash jump.
      // Focus after that jump without adding another scroll or history entry.
      frame(() => heading.focus({preventScroll:true}));
    }
  });
  const dialog = root.querySelector('.td-lightbox');
  if (typeof dialog?.showModal !== 'function' || typeof dialog.close !== 'function' || typeof Image !== 'function') return;
  let data;
  try { data = JSON.parse(document.getElementById('tdGalleryData')?.textContent || ''); } catch (_) { return; }
  if (!data || typeof data.galleries !== 'object' || data.galleries === null) return;
  const stage = dialog.querySelector('.td-viewer-stage');
  const photo = dialog.querySelector('.td-viewer-image');
  const caption = dialog.querySelector('.td-viewer-caption');
  const counter = dialog.querySelector('.td-viewer-count');
  const error = dialog.querySelector('.td-viewer-error');
  const previous = dialog.querySelector('.td-viewer-prev');
  const next = dialog.querySelector('.td-viewer-next');
  const close = dialog.querySelector('.td-viewer-close');
  if (![stage, photo, caption, counter, error, previous, next, close].every(Boolean)) return;
  let gallery, index = 0, trigger, token = 0, swipe;
  function show(target, direction = 1) {
    if (!gallery?.images.length) return;
    const position = Number.isFinite(target) ? Math.trunc(target) : 0;
    index = ((position % gallery.images.length) + gallery.images.length) % gallery.images.length;
    const entry = gallery.images[index];
    const current = ++token;
    stage.setAttribute('aria-busy', 'true');
    photo.hidden = true; error.hidden = true;
    counter.textContent = (index + 1) + ' / ' + gallery.images.length;
    caption.textContent = entry.caption || gallery.name + ' · ' + (data.labels?.photo || (root.dataset.locale === 'en' ? 'Photo' : 'Ảnh')) + ' ' + (index + 1);
    previous.disabled = next.disabled = gallery.images.length < 2;
    const preload = new Image();
    let settled = false;
    const ready = async () => {
      if (settled) return;
      settled = true;
      try { await preload.decode?.(); } catch (_) {}
      if (current !== token || !dialog.open) return;
      photo.src = entry.url; photo.alt = entry.alt || entry.caption || gallery.name;
      photo.hidden = false; stage.setAttribute('aria-busy', 'false');
      if (!reduced() && typeof photo.animate === 'function') {
        (photo.getAnimations?.() || []).forEach(animation => animation.cancel());
        photo.animate([{opacity:.35,transform:'translateX(' + (direction * 14) + 'px) scale(.99)'},{opacity:1,transform:'none'}], {duration:280,easing:'cubic-bezier(.22,1,.36,1)'});
      }
    };
    const failed = () => {
      if (settled) return;
      settled = true;
      if (current !== token || !dialog.open) return;
      photo.hidden = true; error.hidden = false; stage.setAttribute('aria-busy', 'false');
    };
    preload.addEventListener('load', ready, {once:true});
    preload.addEventListener('error', failed, {once:true});
    preload.src = entry.url;
    if (preload.complete) (preload.naturalWidth ? ready : failed)();
  }
  root.addEventListener('click', event => {
    const link = event.target.closest('[data-td-photo]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const candidate = data.galleries[link.closest('[data-td-member]')?.dataset.tdMember];
    if (!Array.isArray(candidate?.images) || !candidate.images.length
        || !candidate.images.every(image => image && typeof image.url === 'string' && image.url !== '')) return;
    try { if (!dialog.open) dialog.showModal(); } catch (_) { return; }
    event.preventDefault(); gallery = candidate; trigger = link;
    document.body.classList.add('td-lightbox-open');
    show(Number(link.dataset.tdPhoto) || 0);
    close.focus({preventScroll:true});
  });
  close.addEventListener('click', () => dialog.close());
  previous.addEventListener('click', () => show(index - 1, -1));
  next.addEventListener('click', () => show(index + 1));
  dialog.addEventListener('click', event => {if (event.target === dialog) dialog.close();});
  dialog.addEventListener('close', () => {
    token++; photo.removeAttribute('src');
    photo.hidden = true; stage.setAttribute('aria-busy', 'false');
    (photo.getAnimations?.() || []).forEach(animation => animation.cancel());
    document.body.classList.remove('td-lightbox-open');
    if (trigger && root.contains(trigger)) trigger.focus({preventScroll:true});
  });
  dialog.addEventListener('keydown', event => {
    if (!dialog.open) return;
    if (event.key === 'ArrowLeft') {event.preventDefault(); show(index - 1, -1);}
    if (event.key === 'ArrowRight') {event.preventDefault(); show(index + 1);}
  });
  stage.addEventListener('pointerdown', event => {if (event.pointerType === 'touch') swipe = {x:event.clientX,y:event.clientY};});
  stage.addEventListener('pointerup', event => {
    if (!swipe) return;
    const x = event.clientX - swipe.x, y = event.clientY - swipe.y;
    swipe = null;
    if (Math.abs(x) > 50 && Math.abs(x) > Math.abs(y)) show(index + (x > 0 ? -1 : 1), x > 0 ? -1 : 1);
  });
  stage.addEventListener('pointercancel', () => {swipe = null;});
})();
