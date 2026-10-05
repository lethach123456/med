(() => {
  'use strict';
  const root = document.querySelector('.doctor-profile');
  if (!root) return;
  const header = document.querySelector('.medical-header');
  const setHeaderHeight = () => root.style.setProperty('--dp-header-height', `${Math.ceil(header?.getBoundingClientRect().height || 72)}px`);
  setHeaderHeight();
  if (header && 'ResizeObserver' in window) new ResizeObserver(setHeaderHeight).observe(header);

  // The full article remains readable with JavaScript disabled.
  const article = root.querySelector('[data-dp-article]');
  const more = root.querySelector('[data-dp-read-more]');
  const collapseArticle = () => {
    if (!article || !more || article.scrollHeight <= 570 || !more.hidden) return;
    article.classList.add('is-collapsed');
    more.hidden = false;
    // Prevent keyboard focus moving into text visually concealed by the clamp.
    article.querySelectorAll('a').forEach(link => link.setAttribute('tabindex', '-1'));
  };
  collapseArticle();
  document.fonts?.ready.then(collapseArticle);
  more?.addEventListener('click', () => {
    const expand = more.getAttribute('aria-expanded') !== 'true';
    article.classList.toggle('is-collapsed', !expand);
    article.querySelectorAll('a').forEach(link => expand ? link.removeAttribute('tabindex') : link.setAttribute('tabindex', '-1'));
    more.setAttribute('aria-expanded', String(expand));
    more.firstChild.textContent = expand ? more.dataset.less : more.dataset.more;
    if (!expand) article.scrollIntoView({block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
  });

  const navLinks = [...root.querySelectorAll('.dp-nav a')];
  const nav = root.querySelector('.dp-nav');
  if ('IntersectionObserver' in window) {
    const sections = navLinks.map(link => root.querySelector(link.hash)).filter(Boolean);
    const observer = new IntersectionObserver(entries => {
      const visible = entries.filter(entry => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
      if (!visible.length) return;
      navLinks.forEach(link => {
        const active = link.hash === `#${visible[0].target.id}`;
        link.classList.toggle('is-active', active);
        active ? link.setAttribute('aria-current', 'location') : link.removeAttribute('aria-current');
        if (active && nav && (link.offsetLeft < nav.scrollLeft || link.offsetLeft + link.offsetWidth > nav.scrollLeft + nav.clientWidth)) {
          nav.scrollTo({left: Math.max(0, link.offsetLeft - 12), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
        }
      });
    }, {rootMargin: '-150px 0px -50% 0px', threshold: 0});
    sections.forEach(section => observer.observe(section));
  }

  root.querySelectorAll('[data-dp-photo]').forEach(photo => {
    const fallback = () => {photo.hidden = true; photo.parentElement.classList.add('is-placeholder');};
    photo.addEventListener('error', fallback);
    if (photo.complete && !photo.naturalWidth) fallback();
  });
  root.querySelectorAll('[data-dp-gallery-photo]').forEach(photo => {
    const remove = () => {photo.closest('button').hidden = true; photo.closest('figure').hidden = true;};
    photo.addEventListener('error', remove);
    if (photo.complete && !photo.naturalWidth) remove();
  });

  const viewer = root.querySelector('[data-dp-lightbox]');
  if (!viewer || !viewer.showModal) return;
  const buttons = [...root.querySelectorAll('[data-dp-gallery]')];
  const image = viewer.querySelector('[data-dp-viewer-image]');
  const count = viewer.querySelector('[data-dp-count]');
  let current = 0;
  let galleryButtons = [];
  let trigger = null;
  let previousOverflow = '';
  const show = index => {
    if (!galleryButtons.length) {if (viewer.open) viewer.close(); return;}
    current = (index + galleryButtons.length) % galleryButtons.length;
    image.src = galleryButtons[current].querySelector('img').src;
    image.alt = galleryButtons[current].querySelector('img').alt;
    count.textContent = `${current + 1} / ${galleryButtons.length}`;
    viewer.querySelector('[data-dp-prev]').hidden = galleryButtons.length < 2;
    viewer.querySelector('[data-dp-next]').hidden = galleryButtons.length < 2;
  };
  buttons.forEach(button => button.addEventListener('click', () => {
    trigger = button;
    galleryButtons = buttons.filter(item => !item.hidden);
    show(galleryButtons.indexOf(button));
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    viewer.showModal();
  }));
  viewer.querySelector('[data-dp-close]').addEventListener('click', () => viewer.close());
  viewer.querySelector('[data-dp-prev]').addEventListener('click', () => show(current - 1));
  viewer.querySelector('[data-dp-next]').addEventListener('click', () => show(current + 1));
  viewer.addEventListener('click', event => {if (event.target === viewer) viewer.close();});
  viewer.addEventListener('close', () => {document.body.style.overflow = previousOverflow; trigger?.focus({preventScroll: true});});
  image.addEventListener('error', () => {
    if (!galleryButtons[current]) return;
    galleryButtons[current].hidden = true;
    galleryButtons[current].closest('figure').hidden = true;
    galleryButtons = galleryButtons.filter(button => !button.hidden);
    show(current);
  });
  viewer.addEventListener('keydown', event => {
    if (event.key === 'ArrowRight') {event.preventDefault(); show(current + 1);}
    if (event.key === 'ArrowLeft') {event.preventDefault(); show(current - 1);}
  });
  let touchStart = null;
  image.addEventListener('touchstart', event => {touchStart = event.touches.length === 1 ? event.touches[0].clientX : null;}, {passive: true});
  image.addEventListener('touchend', event => {
    if (touchStart === null || event.changedTouches.length !== 1) return;
    const distance = event.changedTouches[0].clientX - touchStart;
    if (Math.abs(distance) > 55) show(current + (distance < 0 ? 1 : -1));
    touchStart = null;
  }, {passive: true});
})();
