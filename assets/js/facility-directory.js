(() => {
  'use strict';
  const root = document.querySelector('.fd-directory');
  const form = document.getElementById('facilityDirectoryFilter');
  if (!root || !form) return;
  root.classList.add('fd-enhanced');
  const en = root.dataset.locale === 'en';
  const locale = en ? 'en' : 'vi';
  const words = en ? {
    matches: 'matching facilities', reviews: 'reviews', profile: 'View profile', verified: 'Verified profile',
    more: 'Show more services', less: 'Show fewer services', unrated: 'Not rated yet', price: 'Reference price: ',
    empty: 'No matching facilities', hint: 'Try a different keyword or remove some filters.', all: 'View all facilities',
    loading: 'Updating results…', error: 'Unable to load results', errorHint: 'Please try again. Your search has been kept.',
    retry: 'Try again', clear: 'Clear all', remove: 'Remove filter: ', page: 'Page ', previous: 'Previous page', next: 'Next page',
    city: 'Location', category: 'Facility type', service: 'Service', min_rating: 'Rating', q: 'Search',
  } : {
    matches: 'cơ sở phù hợp', reviews: 'đánh giá', profile: 'Xem hồ sơ', verified: 'Hồ sơ đã xác thực',
    more: 'Xem thêm dịch vụ', less: 'Thu gọn dịch vụ', unrated: 'Chưa có đánh giá', price: 'Giá tham khảo: ',
    empty: 'Chưa tìm thấy cơ sở phù hợp', hint: 'Thử từ khóa khác hoặc bỏ bớt bộ lọc nhé.', all: 'Xem tất cả cơ sở',
    loading: 'Đang cập nhật kết quả…', error: 'Chưa tải được danh sách', errorHint: 'Thử lại nhé. Điều kiện tìm kiếm vẫn được giữ.',
    retry: 'Thử lại', clear: 'Xóa tất cả', remove: 'Bỏ bộ lọc: ', page: 'Trang ', previous: 'Trang trước', next: 'Trang sau',
    city: 'Khu vực', category: 'Nhóm cơ sở', service: 'Dịch vụ', min_rating: 'Điểm', q: 'Từ khóa',
  };
  const list = document.getElementById('facilityList');
  const pagination = document.getElementById('facilityPagination');
  const count = document.getElementById('facilityResultCount');
  const status = document.getElementById('facilityDirectoryStatus');
  const active = document.getElementById('facilityActiveFilters');
  const toggle = document.getElementById('facilityFilterToggle');
  const disclosure = document.getElementById('facilityFilterDisclosure');
  const panel = document.getElementById('facilityFilterOptions');
  const filterCount = document.getElementById('facilityFilterCount');
  const search = document.getElementById('facilitySearch');
  const controls = [...root.querySelectorAll('[data-facility-filter-control]')];
  const initial = JSON.parse(document.getElementById('facilityDirectoryInitial').textContent);
  const number = new Intl.NumberFormat(en ? 'en-US' : 'vi-VN');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const compact = window.matchMedia('(max-width: 800px)');
  const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const safeURL = (value, fallback = '') => {
    const url = String(value ?? '').trim();
    return !/[\x00-\x20\\]/.test(url) && (/^\/(?!\/)/.test(url) || /^https?:\/\/[^/]+/i.test(url)) ? url : fallback;
  };
  let currentPage = initial.page;
  let controller;
  let requestId = 0;
  let debounce;
  let revealObserver;
  const revealedCards = new WeakSet();

  function revealCards() {
    revealObserver?.disconnect();
    if (reduced.matches || !('IntersectionObserver' in window)) return;
    // Content is always readable without JS. Animate only cards entering the
    // viewport, once each, rather than keeping a long list moving off-screen.
    revealObserver = new IntersectionObserver((entries) => {
      let stagger = 0;
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        revealObserver.unobserve(entry.target);
        if (reduced.matches || revealedCards.has(entry.target)) return;
        revealedCards.add(entry.target);
        entry.target.animate(
          [{opacity: .35, transform: 'translateY(12px)'}, {opacity: 1, transform: 'translateY(0)'}],
          {duration: 420, delay: Math.min(stagger++ * 55, 110), easing: 'cubic-bezier(.16,1,.3,1)'}
        );
      });
    }, {threshold: .08});
    list.querySelectorAll('.fd-card').forEach((item) => revealObserver.observe(item));
  }

  function parameters(page = 1) {
    const params = new URLSearchParams(new FormData(form));
    params.set('q', search.value.trim());
    params.set('page', String(page));
    params.set('locale', locale);
    params.set('limit', '12');
    return params;
  }
  function pageURL(page) {
    const params = parameters(page);
    for (const [key, value] of [...params]) {
      if (!value || key === 'limit' || key === 'locale' || (key === 'page' && value === '1') || (key === 'sort' && value === 'recommended')) params.delete(key);
    }
    return form.getAttribute('action') + (params.size ? '?' + params : '');
  }
  function syncFilters() {
    const params = parameters();
    const filters = ['q', 'city', 'category', 'service', 'min_rating'].filter((key) => params.get(key));
    const selectedCount = filters.filter((key) => key !== 'q').length;
    filterCount.textContent = selectedCount;
    filterCount.hidden = !selectedCount;
    active.hidden = !filters.length;
    active.innerHTML = filters.map((key) => {
      const value = key === 'min_rating' ? '≥ ' + params.get(key) + '/5' : params.get(key);
      return `<button type="button" data-remove-filter="${key}" aria-label="${escape(words.remove + words[key] + ': ' + value)}"><span>${escape(words[key] + ': ' + value)}</span><i class="ph ph-x" aria-hidden="true"></i></button>`;
    }).join('') + (filters.length ? `<button type="button" class="fd-clear-all" data-clear-filters>${words.clear}</button>` : '');
    root.querySelectorAll('[data-category]').forEach((link) => {
      if (link.dataset.category === params.get('category')) link.setAttribute('aria-current', 'true');
      else link.removeAttribute('aria-current');
    });
    root.querySelectorAll('[data-city]').forEach((link) => {
      if (link.dataset.city === params.get('city')) link.setAttribute('aria-current', 'true');
      else link.removeAttribute('aria-current');
    });
  }
  function setPanel(open, {restoreFocus = true} = {}) {
    if (!open && restoreFocus && panel.contains(document.activeElement)) toggle.focus({preventScroll: true});
    disclosure.open = open;
  }
  disclosure.addEventListener('toggle', () => {
    toggle.setAttribute('aria-expanded', String(disclosure.open));
    panel.getAnimations().forEach((animation) => animation.cancel());
    if (disclosure.open && !reduced.matches) {
      panel.animate([{opacity: 0, transform: 'translateY(-8px) scale(.985)'}, {opacity: 1, transform: 'translateY(0) scale(1)'}], {duration: 300, easing: 'cubic-bezier(.16,1,.3,1)'});
    }
    if (disclosure.open && compact.matches && panel.getBoundingClientRect().bottom > window.innerHeight - 12) {
      const heading = document.getElementById('fdResultsHeading');
      const header = document.querySelector('.medical-header');
      const offset = (header?.getBoundingClientRect().height || 72) + 16;
      window.scrollTo({top: Math.max(0, heading.getBoundingClientRect().top + window.scrollY - offset), behavior: reduced.matches ? 'auto' : 'smooth'});
    }
  });
  document.addEventListener('pointerdown', (event) => {
    if (disclosure.open && !disclosure.contains(event.target)) setPanel(false);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && disclosure.open) {
      event.preventDefault();
      setPanel(false);
      toggle.focus({preventScroll: true});
    }
  });
  disclosure.addEventListener('focusout', (event) => {
    if (event.relatedTarget && !disclosure.contains(event.relatedTarget)) setPanel(false, {restoreFocus: false});
  });
  document.getElementById('facilityFilterClose').addEventListener('click', () => setPanel(false));
  function card(item) {
    const url = safeURL(item.url, (en ? '/en' : '') + '/co-so-y-te/' + encodeURIComponent(item.slug || ''));
    const image = safeURL(item.image);
    const rating = Math.max(0, Math.min(5, Number(item.rating) || 0));
    const services = (Array.isArray(item.services) ? item.services : []).map(String).filter((s) => s.trim());
    const media = `<span class="fd-media-fallback" aria-hidden="true"><i class="ph ph-hospital"></i><span>MedReview</span></span>${image ? `<img src="${escape(image)}" alt="${escape(item.name)}" width="360" height="300" loading="lazy" decoding="async">` : ''}`;
    return `<article class="fd-card"><a class="fd-media" href="${escape(url)}" aria-label="${escape((en ? 'View ' : 'Xem ') + item.name)}">${media}</a><div class="fd-card-content">
      <div class="fd-card-main">
      <div class="fd-card-category">${escape(item.category || (en ? 'Healthcare facility' : 'Cơ sở y tế'))}${item.city ? `<span aria-hidden="true">·</span>${escape(item.city)}` : ''}</div>
      <h2><a href="${escape(url)}">${escape(item.name)}</a>${item.verified ? `<span class="fd-verified" role="img" aria-label="${words.verified}" title="${words.verified}"><i class="ph-fill ph-seal-check" aria-hidden="true"></i></span>` : ''}</h2>
      </div>
      ${item.address ? `<p class="fd-address"><i class="ph ph-map-pin" aria-hidden="true"></i><span>${escape(item.address)}</span></p>` : ''}
      ${item.subtitle ? `<p class="fd-summary">${escape(item.subtitle)}</p>` : ''}
      ${services.length ? `<div class="fd-services" data-service-tags>${services.map((s, i) => `<span class="fd-service${i > 2 ? ' fd-service-extra' : ''}"${i > 2 ? ' hidden' : ''} title="${escape(s)}"><i class="ph ph-stethoscope" aria-hidden="true"></i><span>${escape(s)}</span></span>`).join('')}${services.length > 3 || services.some(s => Array.from(s).length > 14) ? `<button class="fd-service-more" type="button" data-service-tags-more data-extra-count="${Math.max(0, services.length - 3)}" aria-expanded="false" aria-label="${words.more}">${services.length > 3 ? '+' + (services.length - 3) : (en ? 'Details' : 'Xem đủ')}<i class="ph ph-caret-down" aria-hidden="true"></i></button>` : ''}</div>` : ''}
      <div class="fd-card-footer"><div class="fd-rating">${rating ? `<i class="ph-fill ph-star" aria-hidden="true"></i><strong>${rating.toFixed(1)}<span>/5</span></strong><span class="fd-review-count">(${number.format(Math.max(0, Number(item.reviews_count) || 0))} ${words.reviews})</span>` : `<span class="fd-unrated">${words.unrated}</span>`}</div><a class="fd-profile-link" href="${escape(url)}">${words.profile}<i class="ph ph-arrow-right" aria-hidden="true"></i></a></div>
      ${item.price ? `<p class="fd-price">${words.price}${escape(item.price)}</p>` : ''}</div></article>`;
  }
  function renderPagination(page, pages) {
    const link = (target, label, content) => `<a href="${escape(pageURL(target))}" data-page="${target}" aria-label="${escape(label)}"${target === page ? ' aria-current="page"' : ''}>${content}</a>`;
    let html = page > 1 ? link(page - 1, words.previous, '<i class="ph ph-arrow-left" aria-hidden="true"></i>') : '';
    let last = 0;
    if (pages > 1) {
      for (let i = 1; i <= pages; i++) {
        if (i !== 1 && i !== pages && Math.abs(i - page) > 1) continue;
        if (last && i - last > 1) html += '<span class="fd-page-gap" aria-hidden="true">…</span>';
        html += link(i, words.page + i, i);
        last = i;
      }
      if (page < pages) html += link(page + 1, words.next, '<i class="ph ph-arrow-right" aria-hidden="true"></i>');
    }
    pagination.innerHTML = html;
  }
  function emptyState(error = false) {
    return `<div class="fd-empty"><span aria-hidden="true"><i class="ph ph-${error ? 'wifi-slash' : 'magnifying-glass'}"></i></span><h3>${error ? words.error : words.empty}</h3><p>${error ? words.errorHint : words.hint}</p>${error ? `<button type="button" data-retry>${words.retry}</button>` : `<a href="${escape(form.getAttribute('action'))}" data-clear-filters>${words.all}</a>`}</div>`;
  }
  function imageFallbacks() {
    list.querySelectorAll('.fd-media img').forEach((img) => {
      if (img.complete && !img.naturalWidth) img.remove();
    });
  }
  function scrollResults() {
    const heading = document.getElementById('fdResultsHeading');
    const header = document.querySelector('.medical-header');
    const offset = (header?.getBoundingClientRect().height || 80) + 18;
    window.scrollTo({top: Math.max(0, heading.getBoundingClientRect().top + window.scrollY - offset), behavior: reduced.matches ? 'auto' : 'smooth'});
    heading.focus({preventScroll: true});
  }
  async function load(page = 1, {historyMode = 'push', scroll = false} = {}) {
    clearTimeout(debounce);
    controller?.abort();
    controller = new AbortController();
    const id = ++requestId;
    currentPage = page;
    syncFilters();
    list.setAttribute('aria-busy', 'true');
    status.textContent = words.loading;
    try {
      const response = await fetch('/api/medical/facilities.php?' + parameters(page), {signal: controller.signal, headers: {'Accept': 'application/json'}});
      const data = await response.json();
      if (!response.ok || !data.ok || !Array.isArray(data.items)) throw new Error('Invalid directory response');
      if (id !== requestId) return;
      currentPage = data.paging.page;
      list.innerHTML = data.items.length ? data.items.map(card).join('') : emptyState();
      imageFallbacks();
      count.innerHTML = `<strong>${number.format(data.paging.total)}</strong> ${words.matches}`;
      status.textContent = number.format(data.paging.total) + ' ' + words.matches;
      renderPagination(currentPage, data.paging.total_pages);
      if (historyMode) {
        const url = pageURL(currentPage);
        if (url !== location.pathname + location.search) history[historyMode === 'replace' ? 'replaceState' : 'pushState']({}, '', url);
      }
      revealCards();
      if (scroll) scrollResults();
    } catch (error) {
      if (error.name === 'AbortError' || id !== requestId) return;
      list.innerHTML = emptyState(true);
      pagination.innerHTML = '';
      count.textContent = words.error;
      status.textContent = words.error + '. ' + words.errorHint;
    } finally {
      if (id === requestId) list.setAttribute('aria-busy', 'false');
    }
  }
  function clearFilters() {
    search.value = '';
    controls.forEach((control) => {control.value = control.name === 'sort' ? 'recommended' : '';});
    return load(1);
  }
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    setPanel(false, {restoreFocus: false});
    load(1, {scroll: true});
  });
  document.getElementById('facilityFilterReset').addEventListener('click', clearFilters);
  controls.forEach((control) => control.addEventListener('change', () => load(1)));
  search.addEventListener('input', () => {
    clearTimeout(debounce);
    // Invalidate pending responses immediately, not only when the debounce fires.
    controller?.abort();
    requestId++;
    list.setAttribute('aria-busy', 'false');
    debounce = setTimeout(() => load(1, {historyMode: 'replace'}), 380);
  });
  root.addEventListener('click', (event) => {
    const target = event.target.closest('button,a');
    if (!target) return;
    if (target.matches('[data-service-tags-more]')) {
      const tags = target.closest('[data-service-tags]');
      const open = target.getAttribute('aria-expanded') !== 'true';
      tags.classList.toggle('is-expanded', open);
      tags.querySelectorAll('.fd-service-extra').forEach((tag) => {tag.hidden = !open;});
      target.setAttribute('aria-expanded', String(open));
      target.setAttribute('aria-label', open ? words.less : words.more);
      target.innerHTML = (open ? (en ? 'Less' : 'Thu gọn') : (Number(target.dataset.extraCount) > 0 ? '+' + target.dataset.extraCount : (en ? 'Details' : 'Xem đủ'))) + '<i class="ph ph-caret-down" aria-hidden="true"></i>';
    } else if (target.matches('[data-retry]')) load(currentPage);
    else if (target.matches('[data-open-filters]')) {
      setPanel(true);
      document.getElementById('filterCity').focus({preventScroll: true});
      toggle.scrollIntoView({block: 'center', behavior: reduced.matches ? 'auto' : 'smooth'});
    }
    else if (target.matches('[data-clear-filters]')) {event.preventDefault(); clearFilters();}
    else if (target.matches('[data-remove-filter]')) {
      const key = target.dataset.removeFilter;
      form.elements.namedItem(key).value = '';
      // Restore focus before the removed chip is replaced.
      (key === 'q' ? search : toggle.offsetParent ? toggle : document.getElementById('facilityFilterReset')).focus({preventScroll: true});
      load(1);
    } else if (target.matches('[data-category]') && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
      event.preventDefault();
      document.getElementById('filterCategory').value = target.dataset.category;
      load(1);
    } else if (target.matches('[data-city]') && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
      event.preventDefault();
      document.getElementById('filterCity').value = target.dataset.city;
      load(1, {scroll: true});
    }
  });
  pagination.addEventListener('click', (event) => {
    const link = event.target.closest('[data-page]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey) return;
    event.preventDefault();
    if (Number(link.dataset.page) !== currentPage) load(Number(link.dataset.page), {scroll: true});
  });
  list.addEventListener('error', (event) => {if (event.target.matches('.fd-media img')) event.target.remove();}, true);
  list.addEventListener('pointerdown', (event) => {
    // Tapping a profile should never wait for its entrance animation.
    event.target.closest('.fd-card')?.getAnimations().forEach((animation) => animation.cancel());
  });
  reduced.addEventListener('change', () => {
    if (reduced.matches) {
      revealObserver?.disconnect();
      root.getAnimations({subtree: true}).forEach((animation) => animation.cancel());
    } else revealCards();
  });
  window.addEventListener('popstate', () => {
    const params = new URLSearchParams(location.search);
    search.value = params.get('q') || '';
    controls.forEach((control) => {control.value = params.get(control.name) || (control.name === 'sort' ? 'recommended' : '');});
    load(Math.max(1, Number(params.get('page')) || 1), {historyMode: null});
  });
  syncFilters();
  imageFallbacks();
  revealCards();
})();
