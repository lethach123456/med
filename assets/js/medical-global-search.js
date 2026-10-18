(() => {
  const endpoint = '/api/medical/search.php';
  const minimumLength = 2;
  const language = document.documentElement.lang?.toLowerCase().startsWith('en') ? 'en' : 'vi';
  const labels = language === 'en' ? {
    facilities: 'Healthcare facilities', doctors: 'Doctors', toplists: 'Toplists', reviews: 'reviews',
    facilitiesCount: 'facilities', doctorsCount: 'doctors', updated: 'Updated', verified: 'Verified', noResults: 'No results found for',
    unavailable: 'Search is temporarily unavailable. Please try again.', loading: 'Searching...',
    scrollMore: 'Scroll for more', swipeMore: 'Swipe down for more',
    curated: 'Curated healthcare list',
    noResultsHint: 'Try a shorter name, specialty, service or city.'
  } : {
    facilities: 'Cơ sở y tế', doctors: 'Bác sĩ', toplists: 'Toplist', reviews: 'đánh giá',
    facilitiesCount: 'cơ sở', doctorsCount: 'bác sĩ', updated: 'Cập nhật', verified: 'Đã xác thực', noResults: 'Không tìm thấy kết quả cho',
    unavailable: 'Không thể tìm kiếm lúc này. Vui lòng thử lại.', loading: 'Đang tìm kiếm...',
    scrollMore: 'Cuộn xuống để xem thêm', swipeMore: 'Vuốt xuống để xem thêm',
    curated: 'Danh sách cơ sở được chọn lọc',
    noResultsHint: 'Thử tên ngắn hơn, chuyên khoa, dịch vụ hoặc thành phố.'
  };

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
  })[char]);
  const numberFormat = new Intl.NumberFormat(language === 'en' ? 'en-US' : 'vi-VN');
  const isVerified = (value) => value === true || value === 1 || value === '1' || value === 'true';
  const compactText = (values) => [...new Set(values.map((value) => String(value || '').trim()).filter(Boolean))].join(' · ');
  const resultsCloseTimers = new WeakMap();
  // Keep repeated focus/typing requests local to this page for a short time.
  // Search queries are never persisted in browser storage.
  const responseCache = new Map();
  const cacheKey = (query) => `${language}:${query.toLocaleLowerCase().normalize('NFC')}`;
  const cachedResponse = (query) => {
    const entry = responseCache.get(cacheKey(query));
    if (!entry) return null;
    if (Date.now() - entry.at >= 30000) {
      responseCache.delete(cacheKey(query));
      return null;
    }
    return entry.data;
  };
  const rememberResponse = (query, data) => {
    const key = cacheKey(query);
    responseCache.delete(key);
    responseCache.set(key, {at: Date.now(), data});
    if (responseCache.size > 40) responseCache.delete(responseCache.keys().next().value);
  };

  const cancelResultsClose = (results) => {
    const timer = resultsCloseTimers.get(results);
    if (timer) window.clearTimeout(timer);
    resultsCloseTimers.delete(results);
    results.classList.remove('is-closing');
    results.removeAttribute('inert');
  };

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
      const doctorList = item.entity_type === 'doctor';
      const mixedList = item.entity_type === 'mixed';
      const memberCount = mixedList ? Math.max(0, Number(item.facility_count) || 0) + Math.max(0, Number(item.doctor_count) || 0) : Math.max(0, Number.parseInt((doctorList ? item.doctor_count : item.facility_count) || 0, 10) || 0);
      const countLabel = mixedList ? (document.documentElement.lang === 'en' ? 'providers' : 'hồ sơ') : (doctorList ? labels.doctorsCount : labels.facilitiesCount);
      const updated = dateText(item.updated_at);
      facts = `<span class="medical-search-facts">
        ${memberCount > 0 ? `<span class="medical-search-toplist-count"><i class="ph ${mixedList ? 'ph-users' : doctorList ? 'ph-stethoscope' : 'ph-buildings'}" aria-hidden="true"></i>${numberFormat.format(memberCount)} ${escapeHtml(countLabel)}</span>` : ''}
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
    cancelResultsClose(results);
    const groups = data && data.groups ? data.groups : {};
    const definitions = [
      ['facilities', labels.facilities, 'ph ph-hospital'],
      ['doctors', labels.doctors, 'ph ph-user-doctor'],
      ['toplists', labels.toplists, 'ph ph-list-numbers']
    ];
    const requestedOrder = Array.isArray(data?.meta?.group_order) ? data.meta.group_order : [];
    const groupKeys = definitions.map(([key]) => key);
    const order = [...new Set([...requestedOrder.filter((key) => groupKeys.includes(key)), ...groupKeys])];
    const sections = order.map((key) => {
      const [, label, icon] = definitions.find(([candidate]) => candidate === key);
      const items = Array.isArray(groups[key]) ? groups[key] : [];
      const markup = items.map((item) => itemMarkup(key, item)).filter(Boolean);
      if (!markup.length) return '';
      return `<section class="medical-search-group" aria-label="${escapeHtml(label)}"><div class="medical-search-group-title"><i class="${icon}" aria-hidden="true"></i>${escapeHtml(label)}<span>${numberFormat.format(markup.length)}</span></div>${markup.join('')}</section>`;
    }).filter(Boolean);

    if (!sections.length) {
      results.innerHTML = `<div class="medical-search-empty" role="status"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><span>${escapeHtml(labels.noResults)} “${escapeHtml(query)}”.<br><small>${escapeHtml(labels.noResultsHint)}</small></span></div>`;
    } else {
      results.innerHTML = sections.join('');
      results.querySelectorAll('.medical-search-item').forEach((item, index) => {
        item.id = `${results.id}-option-${index}`;
        item.setAttribute('aria-selected', 'false');
      });
    }
    results.hidden = false;
    results.scrollTop = 0;
    syncScrollCue(shell, results);
    shell.classList.remove('is-searching');
    shell.classList.add('is-open');
    shell.closest('.hero-wrap')?.classList.add('medical-search-active');
    setExpanded(input, true);
  };

  const hide = (shell, input, results) => {
    const closingTimer = resultsCloseTimers.get(results);
    if (closingTimer) window.clearTimeout(closingTimer);
    if (results.hidden || !results.childElementCount) {
      results.hidden = true;
      results.innerHTML = '';
      results.classList.remove('is-closing', 'has-scroll-cue', 'at-scroll-end');
      results.removeAttribute('inert');
      resultsCloseTimers.delete(results);
    } else {
      results.classList.add('is-closing');
      results.setAttribute('inert', '');
      resultsCloseTimers.set(results, window.setTimeout(() => {
        if (!results.classList.contains('is-closing')) return;
        results.hidden = true;
        results.innerHTML = '';
        results.classList.remove('is-closing', 'has-scroll-cue', 'at-scroll-end');
        resultsCloseTimers.delete(results);
      }, 170));
    }
    shell.classList.remove('is-open');
    shell.classList.remove('is-searching');
    shell.closest('.hero-wrap')?.classList.remove('medical-search-active');
    input.removeAttribute('aria-activedescendant');
    setExpanded(input, false);
  };

  const init = (shell, shellIndex) => {
    const input = shell.querySelector('[data-medical-search-input]');
    const results = shell.querySelector('[data-medical-search-results]');
    if (!input || !results || shell.dataset.medicalSearchReady === 'true') return;
    shell.dataset.medicalSearchReady = 'true';

    results.id ||= `medical-search-results-${shellIndex + 1}`;
    results.setAttribute('role', 'listbox');
    results.setAttribute('aria-label', language === 'en' ? 'Search suggestions' : 'Gợi ý tìm kiếm');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', results.id);
    input.setAttribute('maxlength', '120');
    input.setAttribute('enterkeyhint', 'search');
    setExpanded(input, false);
    window.addEventListener('resize', () => syncScrollCue(shell, results), {passive: true});
    results.addEventListener('scroll', () => {
      results.classList.toggle('at-scroll-end', results.scrollTop + results.clientHeight >= results.scrollHeight - 2);
    }, {passive: true});

    let timer = 0;
    let controller = null;
    let requestNumber = 0;
    let activeIndex = -1;
    let composing = false;
    let renderedQuery = '';
    const readQuery = () => Array.from(input.value.trim().replace(/\s+/g, ' ')).slice(0, 120).join('');
    const canSearch = (query) => Array.from(query).length >= minimumLength;
    const stopPending = () => {
      clearTimeout(timer);
      timer = 0;
      requestNumber++;
      controller?.abort();
      controller = null;
      input.setAttribute('aria-busy', 'false');
    };
    const dismiss = () => {
      stopPending();
      activeIndex = -1;
      renderedQuery = '';
      hide(shell, input, results);
    };
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
    const options = () => renderedQuery === readQuery() && shell.classList.contains('is-open') && !results.hidden && !results.classList.contains('is-closing')
      ? [...results.querySelectorAll('.medical-search-item')] : [];
    const setActive = (nextIndex, scroll = true) => {
      const items = options();
      if (!items.length) return;
      activeIndex = (nextIndex + items.length) % items.length;
      items.forEach((item, index) => {
        item.classList.toggle('is-active', index === activeIndex);
        item.setAttribute('aria-selected', String(index === activeIndex));
      });
      const active = items[activeIndex];
      input.setAttribute('aria-activedescendant', active.id);
      if (scroll) active.scrollIntoView({block: 'nearest'});
    };

    const search = (immediate = false) => {
      const query = readQuery();
      stopPending();
      if (composing) return;
      if (renderedQuery === query && shell.classList.contains('is-open') && !results.classList.contains('is-closing')) return;
      activeIndex = -1;
      renderedQuery = '';
      input.removeAttribute('aria-activedescendant');
      hide(shell, input, results);
      if (!canSearch(query)) return;
      const currentRequest = requestNumber;
      const isCurrent = () => currentRequest === requestNumber && query === readQuery() && !composing && shell.contains(document.activeElement);
      const present = (data) => {
        if (!isCurrent()) return;
        renderedQuery = query;
        input.setAttribute('aria-busy', 'false');
        render(shell, input, results, data, query);
      };
      const cached = cachedResponse(query);
      if (cached) {
        present(cached);
        return;
      }
      const execute = async () => {
        if (!isCurrent()) return;
        const requestController = new AbortController();
        controller = requestController;
        cancelResultsClose(results);
        results.innerHTML = `<div class="medical-search-loading" role="status"><span class="medical-search-spinner" aria-hidden="true"></span>${escapeHtml(labels.loading)}</div>`;
        results.hidden = false;
        shell.classList.add('is-searching');
        input.setAttribute('aria-busy', 'true');
        scrollHomeSearchNearHeader();
        try {
          const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}&limit=4&locale=${language}`, {signal: requestController.signal, headers: {Accept: 'application/json'}});
          const data = await response.json();
          if (!isCurrent()) return;
          if (!response.ok || !data.ok) throw new Error('Search failed');
          rememberResponse(query, data);
          present(data);
        } catch (error) {
          if (error.name === 'AbortError' || !isCurrent()) return;
          results.innerHTML = `<div class="medical-search-empty" role="status">${escapeHtml(labels.unavailable)}</div>`;
          results.hidden = false;
          shell.classList.remove('is-searching');
          input.setAttribute('aria-busy', 'false');
          setExpanded(input, false);
        } finally {
          if (controller === requestController) controller = null;
        }
      };
      if (immediate) execute();
      else timer = window.setTimeout(execute, 220);
    };

    input.addEventListener('input', () => search());
    input.addEventListener('compositionstart', () => {
      composing = true;
      dismiss();
    });
    input.addEventListener('compositionend', () => {
      composing = false;
      search();
    });
    input.addEventListener('focus', () => {
      if (canSearch(readQuery())) search();
    });
    input.addEventListener('keydown', (event) => {
      if (composing || event.isComposing || event.keyCode === 229) return;
      const items = options();
      if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && items.length) {
        event.preventDefault();
        setActive(activeIndex < 0 ? (event.key === 'ArrowDown' ? 0 : items.length - 1) : activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
      } else if (event.key === 'Escape') {
        event.preventDefault();
        dismiss();
      } else if (event.key === 'Enter') {
        event.preventDefault();
        if (items.length && activeIndex >= 0) window.location.assign(items[activeIndex].href);
        else search(true);
      }
    });
    const submit = (event) => {
      event.preventDefault();
      input.focus({preventScroll: true});
      search(true);
    };
    shell.querySelector('form')?.addEventListener('submit', submit);
    shell.querySelector('.send-button')?.addEventListener('click', submit);
    shell.addEventListener('medical-search:close', dismiss);
    shell.addEventListener('focusout', () => {
      window.setTimeout(() => {
        if (!shell.contains(document.activeElement)) dismiss();
      }, 0);
    });
    results.addEventListener('mousemove', (event) => {
      const item = event.target.closest('.medical-search-item');
      if (!item) return;
      const itemIndex = options().indexOf(item);
      if (itemIndex >= 0 && itemIndex !== activeIndex) setActive(itemIndex, false);
    });
    document.addEventListener('click', (event) => {
      if (shell.contains(event.target)) return;
      // Homepage quick queries live beside the search box. Their click first
      // focuses this input and schedules a search, then bubbles here.
      const quickQuery = event.target.closest?.('[data-home-query]');
      if (quickQuery && shell.classList.contains('hero-search-shell') && shell.closest('.med-home')?.contains(quickQuery) && document.activeElement === input) return;
      dismiss();
    });
  };

  const boot = () => document.querySelectorAll('[data-medical-search]').forEach(init);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
