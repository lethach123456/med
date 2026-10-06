(() => {
  const form = document.getElementById('doctorDirectoryFilter');
  const list = document.getElementById('doctorList');
  const pager = document.getElementById('doctorPagination');
  const count = document.getElementById('doctorResultCount');
  const loading = document.getElementById('doctorLoading');
  const toggle = document.getElementById('doctorFilterToggle');
  const options = document.getElementById('doctorFilterOptions');
  const reset = document.getElementById('doctorFilterReset');
  const filterCount = document.getElementById('doctorFilterCount');
  const selectFilters = [...document.querySelectorAll('[data-doctor-filter]')];
  const directory = document.querySelector('.doctor-directory');
  const isEnglish = directory?.dataset.locale === 'en';
  const words = isEnglish ? {verified:'Verified', reviews:'reviews', noReviews:'Not rated yet', consultation:'Consultation fee', contact:'Contact for updates', profile:'View profile', outOf:'out of 5', emptyTitle:'No matching doctors found', emptyCopy:'Try another keyword or remove some filters.', previous:'Previous page', next:'Next page', count:'matching doctors', loadError:'Unable to load doctor data.', failedTitle:'Unable to load the directory', failedCopy:'Please try again in a few minutes.', collapse:'Show less'} : {verified:'Đã xác thực', reviews:'đánh giá', noReviews:'Chưa có đánh giá', consultation:'Chi phí khám', contact:'Liên hệ cập nhật', profile:'Xem hồ sơ', outOf:'trên 5', emptyTitle:'Chưa tìm thấy bác sĩ phù hợp', emptyCopy:'Thử đổi từ khóa hoặc bỏ bớt điều kiện lọc.', previous:'Trang trước', next:'Trang sau', count:'bác sĩ phù hợp', loadError:'Không thể tải dữ liệu.', failedTitle:'Không thể tải danh sách', failedCopy:'Vui lòng thử lại sau ít phút.', collapse:'Thu gọn'};
  if (!form || !list || !pager || !count) return;
  directory.classList.add('doctor-enhanced');
  const motionReduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const scrollToResults = () => {
    const top = count.getBoundingClientRect().top + window.scrollY - 100;
    window.scrollTo({top: Math.max(0, top), behavior: motionReduced ? 'auto' : 'smooth'});
  };
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
  const format = value => new Intl.NumberFormat(isEnglish ? 'en-US' : 'vi-VN').format(Number(value || 0));
  const prepareTags = () => list.querySelectorAll('[data-doctor-tags]').forEach(group => { if (group.querySelector('[data-doctor-tags-more]')) group.classList.add('is-collapsible'); });
  const card = item => {
    const detail = esc(item.url || ((isEnglish ? '/en' : '') + '/bac-si/' + encodeURIComponent(item.slug || '')));
    const image = String(item.image || '').trim();
    const services = Array.isArray(item.services) ? item.services : [];
    const tags = services.map((value, index) => `<span class="doctor-tag${index > 2 ? ' is-extra' : ''}">${esc(value)}</span>`).join('');
    const more = services.length > 3 ? `<button type="button" class="doctor-tags-more" data-doctor-tags-more data-extra-count="${services.length - 3}" aria-expanded="false">+${services.length - 3}</button>` : '';
    const verified = item.verified ? `<span class="doctor-verified" title="${words.verified}"><i class="ph-fill ph-seal-check"></i><span>${words.verified}</span></span>` : '';
    const media = image ? `<img src="${esc(image)}" alt="${esc(item.name)}" loading="lazy" decoding="async">` : '<span class="doctor-media-empty"><i class="ph ph-user-circle"></i></span>';
    const rating = Math.min(5, Math.max(0, Number(item.rating || 0)));
    const score = rating > 0 && Number(item.reviews_count) > 0 ? `<i class="ph-fill ph-star" aria-hidden="true"></i><strong>${rating.toFixed(1)}<small>/5</small></strong><span>${format(item.reviews_count)} ${words.reviews}</span>` : `<i class="ph ph-star" aria-hidden="true"></i><span>${words.noReviews}</span>`;
    const details = `${item.facility_name ? `<span><i class="ph ph-hospital"></i>${esc(item.facility_name)}</span>` : ''}${item.hours ? `<span><i class="ph ph-clock"></i>${esc(item.hours)}</span>` : ''}`;
    return `<article class="doctor-card"><a class="doctor-media" href="${detail}" aria-label="${isEnglish ? 'View profile of ' : 'Xem hồ sơ '}${esc(item.name)}">${media}</a><div class="doctor-profile"><div class="doctor-eyebrow">${item.specialty_text ? `<span>${esc(item.specialty_text)}</span>` : ''}${item.city ? `<i>·</i><span>${esc(item.city)}</span>` : ''}</div><div class="doctor-name-row"><h2><a href="${detail}">${esc(item.name)}</a></h2>${verified}</div>${item.title_text ? `<p class="doctor-subtitle">${esc(item.title_text)}</p>` : ''}<div class="doctor-details">${details}</div>${services.length ? `<div class="doctor-tags" data-doctor-tags>${tags}${more}</div>` : ''}</div><div class="doctor-card-footer"><div class="doctor-score">${score}</div><div class="doctor-price"><span>${words.consultation}</span><strong>${esc(item.price || words.contact)}</strong></div><a class="doctor-action" href="${detail}">${words.profile}<i class="ph ph-arrow-right" aria-hidden="true"></i></a></div></article>`;
  };
  const empty = () => `<div class="doctor-empty"><i class="ph ph-magnifying-glass"></i><h2>${words.emptyTitle}</h2><p>${words.emptyCopy}</p></div>`;
  const pageButton = (label, page, active = false, disabled = false, aria = '') => `<button type="button" data-page="${page}"${active ? ' class="is-current"' : ''}${disabled ? ' disabled' : ''}${aria ? ` aria-label="${aria}"` : ''}>${label}</button>`;
  const renderPager = paging => {
    const total = Number(paging.total_pages || 1), page = Number(paging.page || 1);
    if (total <= 1) { pager.innerHTML = ''; return; }
    const items = [pageButton('‹', page - 1, false, page <= 1, words.previous)];
    const from = Math.max(1, page - 2), to = Math.min(total, page + 2);
    if (from > 1) { items.push(pageButton('1', 1)); if (from > 2) items.push('<span class="pagination-gap">…</span>'); }
    for (let n = from; n <= to; n++) items.push(pageButton(String(n), n, n === page));
    if (to < total) { if (to < total - 1) items.push('<span class="pagination-gap">…</span>'); items.push(pageButton(String(total), total)); }
    items.push(pageButton('›', page + 1, false, page >= total, words.next));
    pager.innerHTML = items.join('');
  };
  const parameters = page => {
    const params = new URLSearchParams(new FormData(form));
    selectFilters.forEach(select => params.set(select.name, select.value));
    params.set('page', String(page));
    params.set('limit', '12');
    params.set('locale', isEnglish ? 'en' : 'vi');
    return params;
  };
  const syncFilterCount = () => {
    const active = selectFilters.filter(select => select.name !== 'sort' && select.value !== '').length;
    filterCount.hidden = active === 0;
    filterCount.textContent = String(active);
    directory?.querySelectorAll('[data-doctor-specialty]').forEach(link => {
      if (link.dataset.doctorSpecialty === document.getElementById('doctorSpecialty')?.value) link.setAttribute('aria-current', 'true');
      else link.removeAttribute('aria-current');
    });
    directory?.querySelectorAll('[data-doctor-city]').forEach(link => {
      if (link.dataset.doctorCity === document.getElementById('doctorCity')?.value) link.setAttribute('aria-current', 'true');
      else link.removeAttribute('aria-current');
    });
  };
  let requestId = 0;
  async function load(page = 1, updateUrl = true) {
    const id = ++requestId;
    loading?.classList.add('is-visible');
    list.setAttribute('aria-busy', 'true');
    try {
      const params = parameters(page);
      const response = await fetch(`/api/medical/doctors.php?${params.toString()}`, {headers:{Accept:'application/json'}});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || words.loadError);
      if (id !== requestId) return;
      const paging = data.paging || {};
      list.innerHTML = (data.items || []).length ? data.items.map(card).join('') : empty();
      prepareTags();
      count.innerHTML = `<strong>${format(paging.total || 0)}</strong> ${words.count}`;
      renderPager(paging);
      if (updateUrl) {
        const url = new URL(window.location.href);
        url.search = '';
        for (const [key, value] of parameters(Number(paging.page || 1))) if (value && key !== 'limit') url.searchParams.set(key, value);
        history.replaceState({}, '', url);
      }
    } catch (error) {
      if (id !== requestId) return;
      list.innerHTML = `<div class="doctor-empty"><i class="ph ph-warning-circle"></i><h2>${words.failedTitle}</h2><p>${words.failedCopy}</p></div>`;
      pager.innerHTML = '';
    } finally {
      if (id === requestId) { loading?.classList.remove('is-visible'); list.removeAttribute('aria-busy'); }
    }
  }
  prepareTags();
  renderPager({page: Number(directory?.dataset.page || 1), total_pages: Number(directory?.dataset.totalPages || 1)});
  toggle?.addEventListener('click', () => { const open = options.hidden; options.hidden = !open; toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  directory.querySelectorAll('[data-doctor-open-filters]').forEach(button => button.addEventListener('click', () => {
    options.hidden = false;
    toggle?.setAttribute('aria-expanded', 'true');
    scrollToResults();
    document.getElementById('doctorCity')?.focus({preventScroll:true});
  }));
  document.addEventListener('click', event => {
    if (options && !options.hidden && !event.target.closest('.doctor-filter-panel, [data-doctor-open-filters]')) {
      options.hidden = true;
      toggle?.setAttribute('aria-expanded', 'false');
    }
  });
  directory?.addEventListener('keydown', event => {
    if (event.key === 'Escape' && options && !options.hidden) {
      options.hidden = true;
      toggle?.setAttribute('aria-expanded', 'false');
      toggle?.focus();
    }
  });
  directory?.querySelectorAll('[data-doctor-specialty], [data-doctor-city]').forEach(link => link.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    const select = document.getElementById(link.hasAttribute('data-doctor-specialty') ? 'doctorSpecialty' : 'doctorCity');
    if (!select) return;
    event.preventDefault();
    select.value = link.dataset.doctorSpecialty || link.dataset.doctorCity || '';
    syncFilterCount();
    void load(1).then(scrollToResults);
  }));
  selectFilters.forEach(select => select.addEventListener('change', () => { syncFilterCount(); load(1); }));
  let searchTimer;
  form.querySelector('[name="q"]')?.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => load(1), 380); });
  form.addEventListener('submit', event => { event.preventDefault(); void load(1).then(scrollToResults); });
  reset?.addEventListener('click', () => {
    form.querySelector('[name="q"]').value = '';
    selectFilters.forEach(select => { select.value = select.name === 'sort' ? 'recommended' : ''; });
    syncFilterCount();
    options.hidden = true;
    toggle?.setAttribute('aria-expanded', 'false');
    load(1);
  });
  list.addEventListener('click', event => {
    const button = event.target.closest('[data-doctor-tags-more]');
    if (!button) return;
    const group = button.closest('[data-doctor-tags]');
    const expanded = group.classList.toggle('is-expanded');
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    button.textContent = expanded ? words.collapse : `+${button.dataset.extraCount || 0}`;
  });
  pager.addEventListener('click', event => { const button = event.target.closest('button[data-page]'); if (!button || button.disabled) return; load(Number(button.dataset.page)); scrollToResults(); });
  syncFilterCount();
})();
