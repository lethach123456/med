(() => {
  'use strict';
  const root = document.querySelector('.doctor-toplist');
  if (!root) return;
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const recover = image => {
    image.hidden = true;
    // Inline article photos may live in an ordinary text paragraph. Do not put
    // an absolutely positioned cover placeholder over the surrounding text.
    if (!image.hasAttribute('data-td-image')) return;
    const holder = image.parentElement;
    if (!holder || holder.querySelector('.td-photo-fallback')) return;
    const fallback = document.createElement('span');
    fallback.className = 'td-photo-fallback'; fallback.setAttribute('aria-hidden', 'true');
    const icon = root.querySelector('.td-kind svg');
    if (icon) fallback.append(icon.cloneNode(true));
    holder.append(fallback);
  };
  root.querySelectorAll('[data-td-image],.td-article-content img,.td-intro img').forEach(image => {
    image.addEventListener('error', () => recover(image), {once:true});
    if (image.complete && !image.naturalWidth) recover(image);
  });
  root.addEventListener('click', event => {
    const link = event.target.closest('a[href^="#rank-"]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const menu = root.querySelector('.td-mobile-toc');
    if (menu?.contains(link)) menu.open = false;
    const heading = document.getElementById(link.hash.slice(1))?.querySelector('h2');
    if (heading) {
      heading.setAttribute('tabindex', '-1');
      requestAnimationFrame(() => heading.focus({preventScroll:true}));
    }
  });
  if ('IntersectionObserver' in window) {
    const toc = [...root.querySelectorAll('.td-toc a[href^="#rank-"]')];
    const observer = new IntersectionObserver(entries => {
      const visible = entries.filter(entry => entry.isIntersecting).sort((a,b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
      if (!visible) return;
      toc.forEach(link => {
        if (link.hash === '#' + visible.target.id) link.setAttribute('aria-current', 'location');
        else link.removeAttribute('aria-current');
      });
    }, {rootMargin:'-110px 0px -55% 0px', threshold:0});
    root.querySelectorAll('[data-td-member]').forEach(card => observer.observe(card));
  }
  const dialog = root.querySelector('.td-lightbox');
  if (!dialog?.showModal) return;
  let data;
  try { data = JSON.parse(document.getElementById('tdGalleryData').textContent); } catch (_) { return; }
  const stage = dialog.querySelector('.td-viewer-stage');
  const photo = dialog.querySelector('.td-viewer-image');
  const caption = dialog.querySelector('.td-viewer-caption');
  const counter = dialog.querySelector('.td-viewer-count');
  const error = dialog.querySelector('.td-viewer-error');
  const previous = dialog.querySelector('.td-viewer-prev');
  const next = dialog.querySelector('.td-viewer-next');
  const close = dialog.querySelector('.td-viewer-close');
  let gallery, index = 0, trigger, token = 0, swipe;
  function show(target, direction = 1) {
    if (!gallery?.images.length) return;
    index = (target + gallery.images.length) % gallery.images.length;
    const entry = gallery.images[index];
    const current = ++token;
    stage.setAttribute('aria-busy', 'true');
    photo.hidden = true; error.hidden = true;
    counter.textContent = (index + 1) + ' / ' + gallery.images.length;
    caption.textContent = entry.caption || gallery.name + ' · ' + data.labels.photo + ' ' + (index + 1);
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
      if (!reduced && photo.animate) {
        photo.getAnimations().forEach(animation => animation.cancel());
        photo.animate([{opacity:.35,transform:'translateX(' + (direction * 14) + 'px) scale(.99)'},{opacity:1,transform:'none'}], {duration:280,easing:'cubic-bezier(.22,1,.36,1)'});
      }
    };
    const failed = () => {
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
    if (!candidate?.images.length) return;
    event.preventDefault(); gallery = candidate; trigger = link;
    dialog.showModal(); document.body.classList.add('td-lightbox-open');
    show(Number(link.dataset.tdPhoto) || 0);
    close.focus({preventScroll:true});
  });
  close.addEventListener('click', () => dialog.close());
  previous.addEventListener('click', () => show(index - 1, -1));
  next.addEventListener('click', () => show(index + 1));
  dialog.addEventListener('click', event => {if (event.target === dialog) dialog.close();});
  dialog.addEventListener('close', () => {
    token++; photo.removeAttribute('src');
    document.body.classList.remove('td-lightbox-open');
    trigger?.focus({preventScroll:true});
  });
  dialog.addEventListener('keydown', event => {
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
