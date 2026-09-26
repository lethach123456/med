(() => {
  const endpoint = '/api/medical/search.php';
  const minimumLength = 2;
  const language = document.documentElement.lang?.toLowerCase().startsWith('en') ? 'en' : 'vi';
  const labels = language === 'en' ? {
    facilities: 'Healthcare facilities', doctors: 'Doctors', toplists: 'Toplists', reviews: 'reviews',
    facilitiesCount: 'facilities', updated: 'Updated', verified: 'Verified', noResults: 'No results found for',
    unavailable: 'Search is temporarily unavailable. Please try again.', loading: 'Searching...',
    scrollMore: 'Scroll for more', swipeMore: 'Swipe down for more',
    curated: 'Curated healthcare list'
  } : {
    facilities: 'Cơ sở y tế', doctors: 'Bác sĩ', toplists: 'Toplist', reviews: 'đánh giá',
    facilitiesCount: 'cơ sở', updated: 'Cập nhật', verified: 'Đã xác thực', noResults: 'Không tìm thấy kết quả cho',
    unavailable: 'Không thể tìm kiếm lúc này. Vui lòng thử lại.', loading: 'Đang tìm kiếm...',
    scrollMore: 'Cuộn xuống để xem thêm', swipeMore: 'Vuốt xuống để xem thêm',
    curated: 'Danh sách cơ sở được chọn lọc'
  };

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
  })[char]);
  const numberFormat = new Intl.NumberFormat(language === 'en' ? 'en-US' : 'vi-VN');
  const isVerified = (value) => value === true || value === 1 || value === '1' || value === 'true';
  const compactText = (values) => [...new Set(values.map((value) => String(value || '').trim()).filter(Boolean))].join(' · ');

  const dateText = (value) => {
    if (!value) return '';
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return '';
    return new Intl.DateTimeFormat(language === 'en' ? 'en-GB' : 'vi-VN', {
      day: '2-digit', month: '2-digit', year: 'numeric'
    }).format(parsed);
  };

  const imageMarkup = (url, icon) => {
    if (String(url || '').trim() !== '') return `<img src="${escapeHtml(url)}" alt="" loading="lazy">`;
    return `<span class="medical-search-placeholder"><i class="${icon}" aria-hidden="true"></i></span>`;
  };

  const verifiedMarkup = (verified) => isVerified(verified)
    ? `<span class="medical-search-verified" title="${escapeHtml(labels.verified)}"><i class="ph-fill ph-seal-check" aria-hidden="true"></i><span>${escapeHtml(labels.verified)}</span></span>`
    : '';

  const ratingMarkup = (item) => {
    const rating = Number(item.rating || 0);
    const reviews = Math.max(0, Number.parseInt(item.reviews_count || 0, 10) || 0);
    if (rating <= 0 && reviews <= 0) return '';
    return `<span class="medical-search-facts">
      ${rating > 0 ? `<span class="medical-search-rating"><i class="ph-fill ph-star" aria-hidden="true"></i>${rating.toFixed(1)}</span>` : ''}
      <span class="medical-search-review-count">${numberFormat.format(reviews)} ${escapeHtml(labels.reviews)}</span>
    </span>`;
  };

  const itemMarkup = (type, item) => {
    const isFacility = type === 'facilities';
    const isDoctor = type === 'doctors';
    const title = isFacility || isDoctor ? item.name : item.title;
    const image = isFacility || isDoctor ? item.image_url : item.featured_image_url;
    const icon = isFacility ? 'ph ph-hospital' : (isDoctor ? 'ph ph-user-doctor' : 'ph ph-list-numbers');
    if (!item.url || !title) return '';

    let context = '';
    let detail = '';
    let facts = '';
    if (isFacility) {
      context = compactText([item.category, item.city]);
      detail = item.matched_service || item.service_text || item.featured_service || item.service || '';
      facts = ratingMarkup(item);
    } else if (isDoctor) {
      context = compactText([item.specialty_text || item.title_text, item.facility_name, item.city]);
      detail = item.matched_service || item.service_text || '';
      facts = ratingMarkup(item);
    } else {
      context = item.excerpt || labels.curated;
      const facilityCount = Math.max(0, Number.parseInt(item.facility_count || 0, 10) || 0);
      const updated = dateText(item.updated_at);
      facts = `<span class="medical-search-facts">
        ${facilityCount > 0 ? `<span class="medical-search-toplist-count"><i class="ph ph-buildings" aria-hidden="true"></i>${numberFormat.format(facilityCount)} ${escapeHtml(labels.facilitiesCount)}</span>` : ''}
        ${updated ? `<span class="medical-search-updated"><i class="ph ph-calendar-blank" aria-hidden="true"></i>${escapeHtml(labels.updated)} ${escapeHtml(updated)}</span>` : ''}
      </span>`;
    }

    return `<a class="medical-search-item medical-search-item-${escapeHtml(type)}" href="${escapeHtml(item.url)}" role="option" tabindex="-1">
      <span class="medical-search-thumb">${imageMarkup(image, icon)}</span>
      <span class="medical-search-copy">
        <span class="medical-search-title-row"><strong>${escapeHtml(title)}</strong>${verifiedMarkup(item.verified)}</span>
        ${context ? `<small class="medical-search-meta">${escapeHtml(context)}</small>` : ''}
        ${(detail || facts) ? `<span class="medical-search-summary">${detail ? `<small class="medical-search-service"><i class="ph ph-sparkle" aria-hidden="true"></i>${escapeHtml(detail)}</small>` : ''}${facts}</span>` : ''}
      </span>
      <i class="ph ph-arrow-up-right medical-search-arrow" aria-hidden="true"></i>
    </a>`;
  };

  const setExpanded = (input, expanded) => input.setAttribute('aria-expanded', expanded ? 'true' : 'false');

  const syncScrollCue = (shell, results) => {
    if (!shell.classList.contains('hero-search-shell')) return;
    const hasOverflow = results.scrollHeight > results.clientHeight + 2;
    let cue = results.querySelector('.medical-search-scroll-cue');
    if (!hasOverflow) {
      cue?.remove();
      results.classList.remove('has-scroll-cue');
      results.classList.remove('at-scroll-end');
      return;
    }
    if (!cue) {
      cue = document.createElement('div');
      cue.className = 'medical-search-scroll-cue';
      cue.setAttribute('aria-hidden', 'true');
      cue.innerHTML = '<span></span><i class="ph ph-caret-down"></i>';
      results.append(cue);
    }
    cue.querySelector('span').textContent = window.matchMedia('(pointer: coarse)').matches
      ? labels.swipeMore
      : labels.scrollMore;
    results.classList.add('has-scroll-cue');
    results.classList.toggle('at-scroll-end', results.scrollTop + results.clientHeight >= results.scrollHeight - 2);
  };

  const render = (shell, input, results, data, query) => {
    const groups = data && data.groups ? data.groups : {};
    const definitions = [
      ['facilities', labels.facilities, 'ph ph-hospital'],
      ['doctors', labels.doctors, 'ph ph-user-doctor'],
      ['toplists', labels.toplists, 'ph ph-list-numbers']
    ];
    const sections = definitions.map(([key, label, icon]) => {
      const items = Array.isArray(groups[key]) ? groups[key] : [];
      const markup = items.map((item) => itemMarkup(key, item)).filter(Boolean);
      if (!markup.length) return '';
      return `<section class="medical-search-group" aria-label="${escapeHtml(label)}"><div class="medical-search-group-title"><i class="${icon}" aria-hidden="true"></i>${escapeHtml(label)}<span>${numberFormat.format(markup.length)}</span></div>${markup.join('')}</section>`;
    }).filter(Boolean);

    if (!sections.length) {
      results.innerHTML = `<div class="medical-search-empty"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>${escapeHtml(labels.noResults)} “${escapeHtml(query)}”.</div>`;
    } else {
      results.innerHTML = sections.join('');
      results.querySelectorAll('.medical-search-item').forEach((item, index) => {
        item.id = `${results.id}-option-${index}`;
      });
    }
    results.hidden = false;
    results.scrollTop = 0;
    syncScrollCue(shell, results);
    shell.classList.add('is-open');
    shell.closest('.hero-wrap')?.classList.add('medical-search-active');
    setExpanded(input, true);
  };

  const hide = (shell, input, results) => {
    results.hidden = true;
    results.innerHTML = '';
    results.classList.remove('has-scroll-cue');
    shell.classList.remove('is-open');
    shell.closest('.hero-wrap')?.classList.remove('medical-search-active');
    shell.dataset.homeSearchAutoScrolled = 'false';
    input.removeAttribute('aria-activedescendant');
    setExpanded(input, false);
  };

  const init = (shell, shellIndex) => {
    const input = shell.querySelector('[data-medical-search-input]');
    const results = shell.querySelector('[data-medical-search-results]');
    if (!input || !results) return;

    results.id ||= `medical-search-results-${shellIndex + 1}`;
    results.setAttribute('role', 'listbox');
    results.setAttribute('aria-label', language === 'en' ? 'Search suggestions' : 'Gợi ý tìm kiếm');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', results.id);
    setExpanded(input, false);
    window.addEventListener('resize', () => syncScrollCue(shell, results), {passive: true});
    results.addEventListener('scroll', () => {
      results.classList.toggle('at-scroll-end', results.scrollTop + results.clientHeight >= results.scrollHeight - 2);
    }, {passive: true});

    let timer = 0;
    let controller = null;
    let requestNumber = 0;
    let activeIndex = -1;
    const scrollHomeSearchNearHeader = () => {
      if (!shell.classList.contains('hero-search-shell') || !window.matchMedia('(max-width: 767px)').matches || shell.dataset.homeSearchAutoScrolled === 'true') return;
      shell.dataset.homeSearchAutoScrolled = 'true';
      window.requestAnimationFrame(() => {
        const header = document.querySelector('.medical-header');
        const headerHeight = header ? header.getBoundingClientRect().height : 0;
        const target = window.scrollY + shell.getBoundingClientRect().top - headerHeight - 10;
        window.scrollTo({
          top: Math.max(0, target),
          behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
        });
      });
    };
    const options = () => [...results.querySelectorAll('.medical-search-item')];
    const setActive = (nextIndex) => {
      const items = options();
      if (!items.length) return;
      activeIndex = (nextIndex + items.length) % items.length;
      items.forEach((item, index) => item.classList.toggle('is-active', index === activeIndex));
      const active = items[activeIndex];
      input.setAttribute('aria-activedescendant', active.id);
      active.scrollIntoView({block: 'nearest'});
    };

    const search = () => {
      const query = input.value.trim();
      clearTimeout(timer);
      activeIndex = -1;
      input.removeAttribute('aria-activedescendant');
      if (query.length < minimumLength) {
        if (controller) controller.abort();
        hide(shell, input, results);
        return;
      }
      timer = window.setTimeout(async () => {
        const currentRequest = ++requestNumber;
        if (controller) controller.abort();
        controller = new AbortController();
        results.innerHTML = `<div class="medical-search-loading" role="status"><span class="medical-search-spinner" aria-hidden="true"></span>${escapeHtml(labels.loading)}</div>`;
        results.hidden = false;
        shell.classList.add('is-open');
        shell.closest('.hero-wrap')?.classList.add('medical-search-active');
        setExpanded(input, true);
        scrollHomeSearchNearHeader();
        try {
          const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}&limit=3&locale=${language}`, {signal: controller.signal, headers: {Accept: 'application/json'}});
          const data = await response.json();
          if (!response.ok || !data.ok || currentRequest !== requestNumber) throw new Error('Search failed');
          render(shell, input, results, data, query);
        } catch (error) {
          if (error.name === 'AbortError' || currentRequest !== requestNumber) return;
          results.innerHTML = `<div class="medical-search-empty" role="status">${escapeHtml(labels.unavailable)}</div>`;
          results.hidden = false;
          shell.classList.add('is-open');
          shell.closest('.hero-wrap')?.classList.add('medical-search-active');
          setExpanded(input, true);
        }
      }, 220);
    };

    input.addEventListener('input', search);
    input.addEventListener('focus', () => {
      if (input.value.trim().length >= minimumLength) search();
    });
    input.addEventListener('keydown', (event) => {
      const items = options();
      if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && !results.hidden && items.length) {
        event.preventDefault();
        setActive(event.key === 'ArrowDown' ? activeIndex + 1 : activeIndex - 1);
      } else if (event.key === 'Escape') {
        hide(shell, input, results);
      } else if (event.key === 'Enter') {
        if (!results.hidden && items.length) {
          event.preventDefault();
          const selected = items[activeIndex >= 0 ? activeIndex : 0];
          if (selected) window.location.assign(selected.href);
        } else if (!input.closest('form') && input.value.trim().length >= minimumLength) {
          event.preventDefault();
          window.location.assign(`/co-so-y-te?q=${encodeURIComponent(input.value.trim())}`);
        }
      }
    });
    results.addEventListener('mousemove', (event) => {
      const item = event.target.closest('.medical-search-item');
      if (!item) return;
      const itemIndex = options().indexOf(item);
      if (itemIndex >= 0 && itemIndex !== activeIndex) setActive(itemIndex);
    });
    document.addEventListener('click', (event) => {
      if (!shell.contains(event.target)) hide(shell, input, results);
    });
  };

  const boot = () => document.querySelectorAll('[data-medical-search]').forEach(init);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
