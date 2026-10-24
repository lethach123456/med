(() => {
  'use strict';
  const root = document.querySelector('.tl-directory');
  const form = document.getElementById('toplistSearchForm');
  if (!root || !form) return;
  const input = document.getElementById('toplistSearch');
  const type = document.getElementById('toplistType');
  const en = root.dataset.locale === 'en';
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const getSort = () => document.getElementById('toplistSort');
  const results = () => root.querySelector('.tl-results');
  let timer, controller, requestId = 0, composing = false;

  function imageFallbacks() {
    root.querySelectorAll('#toplistList img').forEach(image => {
      const recover = () => {
        const collage = image.closest('.tl-collage');
        image.closest('.tl-collage-cell')?.remove();
        if (!collage) return;
        const remaining = collage.querySelectorAll('.tl-collage-cell').length;
        if (remaining) collage.dataset.count = String(remaining);
        else collage.remove();
      };
      image.addEventListener('error', recover, {once: true});
      if (image.complete && !image.naturalWidth) recover();
    });
  }
  imageFallbacks();
  // Search forms, page links and type links remain normal native navigation.
  if (!window.fetch || !window.AbortController || !window.DOMParser) return;

  function controlsUrl(page = 1) {
    const url = new URL(form.action, location.origin);
    url.search = ''; url.hash = '';
    const query = input.value.trim();
    if (query) url.searchParams.set('q', query);
    if (type.value) url.searchParams.set('type', type.value);
    if (getSort().value !== 'updated') url.searchParams.set('sort', getSort().value);
    if (page > 1) url.searchParams.set('page', String(page));
    return url;
  }
  function setBusy(busy) {
    const section = results();
    section.classList.toggle('is-loading', busy);
    section.setAttribute('aria-busy', String(busy));
    if (busy) section.querySelector('#toplistStatus').textContent = en ? 'Loading lists…' : 'Đang tải danh sách…';
  }
  function cancelRequest() {
    clearTimeout(timer);
    requestId++;
    controller?.abort();
    setBusy(false);
  }
  function syncControls(url) {
    input.value = (url.searchParams.get('q') || '').slice(0, 120);
    type.value = ['facility', 'doctor', 'mixed'].includes(url.searchParams.get('type')) ? url.searchParams.get('type') : '';
    getSort().value = ['members', 'title'].includes(url.searchParams.get('sort')) ? url.searchParams.get('sort') : 'updated';
  }
  function showError(url, options) {
    results().querySelector('.tl-fetch-error')?.remove();
    const alert = document.createElement('div');
    alert.className = 'tl-fetch-error'; alert.setAttribute('role', 'alert');
    const message = document.createElement('p');
    message.textContent = en ? 'Could not load this page. Your current lists are still here.' : 'Chưa tải được trang này. Danh sách hiện tại vẫn được giữ lại.';
    const retry = document.createElement('button');
    retry.type = 'button'; retry.className = 'tl-button';
    retry.textContent = en ? 'Try again' : 'Thử lại';
    retry.addEventListener('click', () => navigate(url, options));
    const native = document.createElement('a');
    native.className = 'tl-button'; native.href = url.href;
    native.textContent = en ? 'Open page' : 'Mở trang';
    alert.append(message, retry, native);
    results().querySelector('.tl-results-top').after(alert);
  }
  async function navigate(target, options = {}) {
    clearTimeout(timer);
    const url = new URL(target, location.origin);
    if (url.origin !== location.origin || url.pathname !== new URL(form.action, location.origin).pathname) return;
    const id = ++requestId;
    controller?.abort();
    controller = new AbortController();
    const signal = controller.signal;
    const focusedId = document.activeElement?.id;
    results().querySelector('.tl-fetch-error')?.remove();
    setBusy(true);
    try {
      const response = await fetch(url.href, {signal, credentials: 'same-origin', headers: {'Accept': 'text/html'}});
      if (!response.ok || !response.headers.get('content-type')?.includes('text/html')) throw new Error('Invalid page response');
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (id !== requestId || signal.aborted) return;
      const nextRoot = doc.querySelector('.tl-directory');
      const nextResults = nextRoot?.querySelector('.tl-results');
      const nextTypes = nextRoot?.querySelector('.tl-types');
      if (nextRoot?.dataset.locale !== root.dataset.locale || !nextResults?.querySelector('#toplistList') || !nextResults.querySelector('#toplistPagination') || !nextTypes) throw new Error('Incomplete page response');
      results().replaceWith(document.importNode(nextResults, true));
      root.querySelector('.tl-types').replaceWith(document.importNode(nextTypes, true));
      root.dataset.page = nextRoot.dataset.page;
      root.dataset.pages = nextRoot.dataset.pages;
      input.value = doc.getElementById('toplistSearch').value;
      type.value = doc.getElementById('toplistType').value;
      const normalizedUrl = controlsUrl(Number(root.dataset.page));
      if (options.history === 'replace') history.replaceState({}, '', normalizedUrl);
      else if (options.history !== 'none' && normalizedUrl.href !== location.href) history.pushState({}, '', normalizedUrl);
      else if (options.history === 'none' && normalizedUrl.href !== location.href) history.replaceState({}, '', normalizedUrl);
      if (doc.title) document.title = doc.title;
      const canonical = document.querySelector('link[rel="canonical"]');
      const nextCanonical = doc.querySelector('link[rel="canonical"]');
      if (canonical && nextCanonical) canonical.href = nextCanonical.href;
      document.querySelectorAll('meta[name="robots"]').forEach(meta => meta.remove());
      const nextRobots = doc.querySelector('meta[name="robots"]');
      if (nextRobots) document.head.append(document.importNode(nextRobots, true));
      document.querySelectorAll('link[rel="alternate"][hreflang]').forEach(link => link.remove());
      doc.querySelectorAll('link[rel="alternate"][hreflang]').forEach(link => document.head.append(document.importNode(link, true)));
      const range = results().querySelector('.tl-page-range');
      results().querySelector('#toplistStatus').textContent = range?.textContent || results().querySelector('#toplistResultCount').textContent;
      imageFallbacks();
      if (!reduced) results().querySelectorAll('[data-toplist-card]').forEach((card, index) => {
        card.style.setProperty('--tl-order', String(index)); card.classList.add('is-entering');
      });
      if (options.scroll) {
        const heading = document.getElementById('toplistResultsHeading');
        heading.setAttribute('tabindex', '-1'); heading.focus({preventScroll: true});
        heading.closest('.tl-results-top').scrollIntoView({behavior: reduced ? 'auto' : 'smooth', block: 'start'});
      } else if (focusedId === 'toplistSort') getSort().focus({preventScroll: true});
    } catch (error) {
      if (id === requestId && !signal.aborted) showError(url, options);
    } finally {
      if (id === requestId) setBusy(false);
    }
  }
  function scheduleSearch() {
    cancelRequest();
    if (!composing) timer = setTimeout(() => navigate(controlsUrl(), {history: 'replace'}), 350);
  }
  input.addEventListener('compositionstart', () => {composing = true; cancelRequest();});
  input.addEventListener('compositionend', () => {composing = false; scheduleSearch();});
  input.addEventListener('input', event => {if (!event.isComposing) scheduleSearch();});
  form.addEventListener('submit', event => {
    event.preventDefault(); composing = false;
    navigate(controlsUrl(), {scroll: true});
  });
  root.addEventListener('change', event => {
    if (event.target.id === 'toplistSort') navigate(controlsUrl());
  });
  root.addEventListener('click', event => {
    const link = event.target.closest?.('[data-toplist-page],[data-toplist-type],[data-toplist-clear]');
    if (!link || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault(); clearTimeout(timer);
    if (link.hasAttribute('data-toplist-clear')) {input.value = ''; type.value = ''; getSort().value = 'updated';}
    else if (link.hasAttribute('data-toplist-type')) type.value = link.dataset.toplistType;
    const page = Number(link.dataset.toplistPage) || 1;
    navigate(controlsUrl(page), {scroll: true});
  });
  window.addEventListener('popstate', () => {
    clearTimeout(timer);
    syncControls(new URL(location.href));
    navigate(location.href, {history: 'none'});
  });
})();
