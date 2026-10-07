(() => {
  'use strict';

  const root = document.querySelector('.facility-detail.site-typo');
  const nav = root?.querySelector('.detail-nav');
  const layout = root?.querySelector('.facility-reading-layout');
  if (!nav || !layout || !nav.parentNode || !layout.parentNode || typeof window.matchMedia !== 'function') return;

  const mobile = window.matchMedia('(max-width:767px)');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const header = document.querySelector('.medical-header');
  const marker = document.createComment('facility detail navigation position');
  const links = Array.from(nav.querySelectorAll('a[href^="#"]'));
  const originalLinks = links.map(link => ({
    link,
    active: link.classList.contains('is-active'),
    current: link.getAttribute('aria-current'),
    hidden: link.hidden,
  }));
  const variableNames = ['--facility-nav-header-height', '--facility-nav-height', '--facility-nav-page-padding'];
  const originalVariables = variableNames.map(name => ({
    name,
    value: root.style.getPropertyValue(name),
    priority: root.style.getPropertyPriority(name),
  }));
  const originallyEnhanced = root.classList.contains('facility-nav-mobile');
  let enabled = false;
  let entries = [];
  let activeLink = null;
  let frame = null;
  let dimensionsDirty = false;
  let headerHeight = 0;
  let navHeight = 50;

  const idFromHash = hash => {
    try { return decodeURIComponent(hash.slice(1)); }
    catch (_) { return ''; }
  };
  const setDimension = (name, value) => {
    const pixels = `${value}px`;
    if (root.style.getPropertyValue(name) !== pixels) root.style.setProperty(name, pixels);
  };
  const measure = () => {
    headerHeight = Math.max(0, header?.getBoundingClientRect().height || 0);
    navHeight = Math.max(0, nav.getBoundingClientRect().height || 50);
    const pagePadding = parseFloat(window.getComputedStyle?.(document.documentElement).scrollPaddingTop);
    setDimension(variableNames[0], headerHeight);
    setDimension(variableNames[1], navHeight);
    // Native anchor scrolling combines html scroll padding and target scroll
    // margin. CSS subtracts this padding so the two offsets are not doubled.
    setDimension(variableNames[2], Number.isFinite(pagePadding) ? Math.max(0, pagePadding) : 0);
  };
  // Scroll this strip only, never the document or its focused section.
  const keepVisible = link => {
    const strip = nav.getBoundingClientRect();
    const item = link.getBoundingClientRect();
    const left = strip.left + 8;
    const right = strip.right - 8;
    let delta = 0;
    if (item.left < left) delta = item.left - left;
    else if (item.right > right) delta = item.width > right - left ? item.left - left : item.right - right;
    if (!delta) return;
    const next = Math.max(0, Math.min(nav.scrollLeft + delta, Math.max(0, nav.scrollWidth - nav.clientWidth)));
    if (Math.abs(next - nav.scrollLeft) < 1) return;
    if (typeof nav.scrollTo === 'function') nav.scrollTo({left: next, behavior: reducedMotion.matches ? 'auto' : 'smooth'});
    else nav.scrollLeft = next;
  };
  const setActive = entry => {
    if (!entry || activeLink === entry.link) return;
    activeLink = entry.link;
    links.forEach(link => {
      const active = link === activeLink;
      link.classList.toggle('is-active', active);
      if (active) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
    keepVisible(activeLink);
  };
  const updateFromScroll = () => {
    if (!enabled || !entries.length) return;
    const threshold = headerHeight + navHeight + 12 + 2;
    // Section starts, rather than parent intersections, handle nested service
    // and price headings without keeping their introduction active forever.
    const positions = entries.map(entry => ({entry, top: entry.target.getBoundingClientRect().top}));
    positions.sort((first, second) => first.top - second.top);
    let current = positions[0].entry;
    positions.forEach(position => {
      if (position.top <= threshold) current = position.entry;
    });
    setActive(current);
  };
  const scheduleUpdate = (withMeasurement = false) => {
    if (!enabled) return;
    dimensionsDirty = dimensionsDirty || withMeasurement;
    if (frame !== null) return;
    frame = window.requestAnimationFrame(() => {
      frame = null;
      if (!enabled) return;
      if (dimensionsDirty) { measure(); dimensionsDirty = false; }
      updateFromScroll();
    });
  };
  const resizeObserver = typeof window.ResizeObserver === 'function'
    ? new window.ResizeObserver(() => scheduleUpdate(true))
    : null;
  const syncHash = () => {
    if (!enabled) return;
    const entry = entries.find(item => item.id === idFromHash(window.location.hash));
    if (entry) setActive(entry);
    else updateFromScroll();
  };
  const enable = () => {
    enabled = true;
    nav.parentNode.insertBefore(marker, nav);
    // The reading grid ends before reviews and booking. Sharing their parent
    // gives sticky positioning the full page-content containing block.
    layout.parentNode.insertBefore(nav, layout);
    root.classList.add('facility-nav-mobile');
    entries = [];
    links.forEach(link => {
      const id = idFromHash(link.getAttribute('href') || '');
      const target = id ? document.getElementById(id) : null;
      if (!target || !root.contains(target)) { link.hidden = true; return; }
      if (!link.hidden) entries.push({link, target, id});
    });
    measure();
    syncHash();
    if (header) resizeObserver?.observe(header);
    resizeObserver?.observe(nav);
  };
  const disable = () => {
    enabled = false;
    if (frame !== null) { window.cancelAnimationFrame(frame); frame = null; }
    dimensionsDirty = false;
    resizeObserver?.disconnect();
    if (marker.parentNode) {
      marker.parentNode.insertBefore(nav, marker);
      marker.parentNode.removeChild(marker);
    }
    originalLinks.forEach(({link, active, current, hidden}) => {
      link.classList.toggle('is-active', active);
      if (current === null) link.removeAttribute('aria-current');
      else link.setAttribute('aria-current', current);
      link.hidden = hidden;
    });
    originalVariables.forEach(({name, value, priority}) => {
      if (value) root.style.setProperty(name, value, priority);
      else root.style.removeProperty(name);
    });
    if (!originallyEnhanced) root.classList.remove('facility-nav-mobile');
    entries = [];
    activeLink = null;
  };
  const syncMode = () => {
    if (mobile.matches === enabled) return;
    if (mobile.matches) enable();
    else disable();
  };

  nav.addEventListener('click', event => {
    if (!enabled || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || (event.button != null && event.button !== 0)) return;
    const link = event.target.closest?.('a[href^="#"]');
    const entry = entries.find(item => item.link === link);
    if (entry) setActive(entry);
    // Native anchors preserve the URL, back button, focus and CSS scroll margin.
  });
  nav.addEventListener('focusin', event => {
    if (enabled && entries.some(entry => entry.link === event.target)) keepVisible(event.target);
  });
  window.addEventListener('scroll', () => scheduleUpdate(), {passive: true});
  window.addEventListener('resize', () => { syncMode(); scheduleUpdate(true); }, {passive: true});
  window.addEventListener('hashchange', syncHash);
  window.addEventListener('load', () => scheduleUpdate(true));
  if (typeof mobile.addEventListener === 'function') mobile.addEventListener('change', syncMode);
  else mobile.addListener?.(syncMode);
  document.fonts?.ready.then(() => scheduleUpdate(true));
  syncMode();
})();
