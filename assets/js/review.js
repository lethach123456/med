(() => {
  'use strict';
  const english = document.documentElement.lang === 'en';
  const filters = document.querySelector('[data-review-filters]');
  if (filters) {
    const narrow = window.matchMedia('(max-width: 999px)');
    const syncFilters = () => { filters.open = !narrow.matches || filters.dataset.active === 'true'; };
    syncFilters();
    narrow.addEventListener('change', syncFilters);
  }
  const photoUnavailable = english ? 'Photo unavailable' : 'Ảnh hiện chưa tải được';
  const photos = [...document.querySelectorAll('[data-review-image]')];
  const failedPhotos = new Set();
  document.querySelectorAll('.rv-directory img, .rv-detail img:not([data-lightbox-image])').forEach(image => {
    const fail = () => {
      const button = image.closest('[data-review-image]');
      if (button) {
        failedPhotos.add(button);
        button.disabled = true;
        button.classList.add('rv-media-failed');
        button.replaceChildren(document.createTextNode(photoUnavailable));
        button.setAttribute('aria-label', photoUnavailable);
      } else {
        const shot = image.closest('.rv-dir-shot');
        if (shot) {
          shot.hidden = true;
          const collection = shot.parentElement;
          const remaining = [...collection.children].filter(child => !child.hidden);
          if (remaining.length) collection.classList.add('rv-dir-single');
          else {
            image.closest('[data-rv-image-wrap]').hidden = true;
            image.closest('.rv-dir-card').classList.add('rv-dir-card-text');
          }
          return;
        }
        const wrap = image.closest('[data-rv-image-wrap], .rv-detail-related-image');
        if (wrap) {
          wrap.classList.add('rv-media-failed');
          wrap.replaceChildren(document.createTextNode(photoUnavailable));
        } else image.hidden = true;
      }
    };
    image.addEventListener('error', fail, { once: true });
    if (image.complete && !image.naturalWidth) fail();
  });

  const dialog = document.querySelector('[data-review-lightbox]');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  const image = dialog.querySelector('[data-lightbox-image]');
  const caption = dialog.querySelector('[data-lightbox-caption]');
  const counter = dialog.querySelector('[data-lightbox-counter]');
  const prev = dialog.querySelector('[data-lightbox-prev]');
  const next = dialog.querySelector('[data-lightbox-next]');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0;
  let trigger = null;
  let previousOverflow = '';
  let activeAnimation = null;
  const available = () => photos.filter(photo => !failedPhotos.has(photo));
  const render = (index, animate = true) => {
    const collection = available();
    if (!collection.length) { if (dialog.open) dialog.close(); return; }
    current = (index + collection.length) % collection.length;
    const photo = collection[current];
    activeAnimation?.cancel();
    image.src = photo.dataset.imageSrc;
    image.alt = photo.dataset.imageAlt || photo.dataset.imageLabel || '';
    caption.textContent = photo.dataset.imageLabel || '';
    counter.textContent = `${current + 1} / ${collection.length}`;
    prev.hidden = next.hidden = collection.length < 2;
    if (animate && !reducedMotion.matches) {
      activeAnimation = image.animate([{ opacity: .25, transform: 'translateY(4px)' }, { opacity: 1, transform: 'translateY(0)' }], {
        duration: 220, easing: 'cubic-bezier(.2,.8,.2,1)'
      });
    }
  };
  photos.forEach(photo => photo.addEventListener('click', () => {
    if (failedPhotos.has(photo)) return;
    trigger = photo;
    previousOverflow = document.documentElement.style.overflow;
    render(available().indexOf(photo), false);
    dialog.showModal();
    document.documentElement.style.overflow = 'hidden';
    dialog.querySelector('[data-lightbox-close]').focus({ preventScroll: true });
  }));
  dialog.querySelector('[data-lightbox-close]').addEventListener('click', () => dialog.close());
  prev.addEventListener('click', () => render(current - 1));
  next.addEventListener('click', () => render(current + 1));
  dialog.addEventListener('keydown', event => {
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
      event.preventDefault(); render(current + (event.key === 'ArrowRight' ? 1 : -1));
    }
  });
  dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
  dialog.addEventListener('close', () => {
    activeAnimation?.cancel();
    document.documentElement.style.overflow = previousOverflow;
    trigger?.focus({ preventScroll: true });
  });
  image.addEventListener('error', () => { caption.textContent = photoUnavailable; });
})();
