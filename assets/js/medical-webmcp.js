(() => {
  'use strict';

  // WebMCP is experimental. Unsupported browsers retain the normal search UI.
  if (!window.isSecureContext) return;
  let context;
  try {
    context = [document.modelContext, typeof navigator === 'undefined' ? null : navigator.modelContext]
      .find(candidate => typeof candidate?.registerTool === 'function');
  } catch { return; }
  if (!context) return;
  const registrationKey = Symbol.for('medreview.webmcp.directory-search');
  if (document[registrationKey]) return;

  const language = document.documentElement.lang?.toLowerCase().startsWith('en') ? 'en' : 'vi';
  const categories = ['facilities', 'doctors', 'toplists'];
  const routes = { facilities: 'co-so-y-te', doctors: 'bac-si', toplists: 'toplist' };
  const result = (payload, isError = false) => ({
    content: [{ type: 'text', text: JSON.stringify(payload) }], isError
  });
  const failure = (code, message) => result({ ok: false, error: { code, message } }, true);
  const text = (value, length = 500) => typeof value === 'string' ? Array.from(value).slice(0, length).join('') : '';
  const count = value => Number.isSafeInteger(value) && value >= 0 ? value : 0;
  const publicItem = (item, category) => {
    if (!item || typeof item !== 'object' || Array.isArray(item) || typeof item.url !== 'string') return null;
    let url;
    try { url = new URL(item.url, window.location.origin); } catch { return null; }
    const route = new RegExp(`^/(?:en/)?${routes[category]}/[^/]+/?$`);
    if (url.origin !== window.location.origin || url.username || url.password
      || !route.test(url.pathname) || /%2f|%5c|%00/i.test(url.pathname) || url.search || url.hash) return null;
    const label = text(item.name || item.title, 240);
    if (!label) return null;
    const output = { label, url: url.href };
    // Only public search-card fields; never return arbitrary server objects/HTML.
    for (const key of ['city', 'address', 'category', 'subtitle', 'specialty_text', 'facility_name', 'service_text', 'matched_service', 'price', 'hours', 'rating_basis', 'entity_type']) {
      if (typeof item[key] === 'string' && item[key]) output[key] = text(item[key]);
    }
    if (Number.isFinite(item.rating) && item.rating >= 0 && item.rating <= 5) output.rating = item.rating;
    if (Number.isSafeInteger(item.reviews_count) && item.reviews_count >= 0) output.reviews_count = item.reviews_count;
    if (typeof item.verified === 'boolean') output.verified = item.verified;
    return output;
  };

  const tool = {
    name: 'search_medreview_directory',
    description: 'Search published MedReview healthcare facility profiles, doctor profiles and Toplists in Vietnam by name, specialty, service or city. Returns reference information and public profile links, not medical advice, treatment recommendations, appointment bookings or a clinical quality guarantee. Do not include patient identifiers or private medical records in the query.',
    inputSchema: {
      type: 'object',
      properties: {
        q: {
          type: 'string', minLength: 2, maxLength: 120,
          description: 'Name, specialty, service or city to search; 2–120 characters. No patient identifiers or private medical records.'
        },
        category: {
          type: 'string', enum: ['all', ...categories], default: 'all',
          description: 'Search result group to return: all, facilities, doctors or toplists.'
        },
        locale: {
          type: 'string', enum: ['vi', 'en'], default: language,
          description: 'Language of published profiles; defaults to the current page language. A translated profile may not exist.'
        },
        limit: {
          type: 'integer', minimum: 1, maximum: 6, default: 4,
          description: 'Maximum number of results per group, from 1 to 6.'
        }
      },
      required: ['q'],
      additionalProperties: false
    },
    annotations: { readOnlyHint: true, untrustedContentHint: true, consequentialHint: false },
    async execute(input, options = {}) {
      if (!input || typeof input !== 'object' || Array.isArray(input)
        || Object.keys(input).some(key => !['q', 'category', 'locale', 'limit'].includes(key))
        || typeof input.q !== 'string' || Array.from(input.q).length < 2 || Array.from(input.q).length > 120) {
        return failure('INVALID_INPUT', 'Provide q as a string of 2–120 characters and only the declared fields.');
      }
      const query = input.q.normalize('NFC').replace(/\s+/gu, ' ').trim();
      const category = input.category === undefined ? 'all' : input.category;
      const locale = input.locale === undefined ? language : input.locale;
      const limit = input.limit === undefined ? 4 : input.limit;
      if (Array.from(query).length < 2 || Array.from(query).length > 120
        || !['all', ...categories].includes(category) || !['vi', 'en'].includes(locale)
        || !Number.isInteger(limit) || limit < 1 || limit > 6) {
        return failure('INVALID_INPUT', 'Check q, category, locale and limit against the input schema.');
      }

      const signal = options?.signal;
      if (signal?.aborted) return failure('ABORTED', 'Search was cancelled.');
      const controller = new AbortController();
      const cancel = () => controller.abort();
      let timedOut = false;
      const timer = window.setTimeout(() => { timedOut = true; controller.abort(); }, 10000);
      signal?.addEventListener('abort', cancel, { once: true });
      let phase = 'fetch';
      try {
        const url = new URL('/api/medical/search.php', window.location.origin);
        url.searchParams.set('q', query);
        url.searchParams.set('locale', locale);
        url.searchParams.set('limit', String(limit));
        const response = await fetch(url.href, {
          method: 'GET', credentials: 'omit', cache: 'no-store', redirect: 'error',
          headers: { Accept: 'application/json' }, signal: controller.signal
        });
        if (!response.ok) return failure('UNAVAILABLE', 'Public directory search is temporarily unavailable.');
        if (!/^application\/(?:[\w.-]+\+)?json(?:\s*;|$)/i.test(response.headers.get('content-type') || '')) {
          return failure('INVALID_RESPONSE', 'Search did not return a JSON response.');
        }
        phase = 'json';
        const payload = await response.json();
        if (controller.signal.aborted) return failure(timedOut ? 'TIMEOUT' : 'ABORTED', timedOut ? 'Search timed out. Please try again.' : 'Search was cancelled.');
        if (!payload || payload.ok !== true || !payload.groups
          || categories.some(key => !Array.isArray(payload.groups[key]))) {
          return failure('INVALID_RESPONSE', 'Search returned an invalid directory response.');
        }
        const selected = category === 'all' ? categories : [category];
        const groups = {}, totals = {};
        for (const key of selected) {
          groups[key] = payload.groups[key].slice(0, limit).map(item => publicItem(item, key)).filter(Boolean);
          totals[key] = count(payload.meta?.totals?.[key]);
        }
        const order = Array.isArray(payload.meta?.group_order) ? payload.meta.group_order : selected;
        return result({
          ok: true, query, locale, category, groups,
          meta: { totals, group_order: [...new Set([...order.filter(key => selected.includes(key)), ...selected])], city: text(payload.meta?.city, 120) },
          notice: locale === 'en'
            ? 'Reference information only, not medical advice. Confirm current details with the provider. A verified badge is a profile status, not a clinical quality or treatment guarantee.'
            : 'Thông tin tham khảo, không thay thế tư vấn y tế. Xác nhận thông tin hiện tại với cơ sở hoặc bác sĩ. Nhãn xác thực là trạng thái hồ sơ, không bảo đảm chất lượng hay kết quả điều trị.'
        });
      } catch {
        if (controller.signal.aborted) return failure(timedOut ? 'TIMEOUT' : 'ABORTED', timedOut ? 'Search timed out. Please try again.' : 'Search was cancelled.');
        return failure(phase === 'json' ? 'INVALID_RESPONSE' : 'UNAVAILABLE', 'Public directory search could not be completed. Please try again.');
      } finally {
        window.clearTimeout(timer);
        signal?.removeEventListener('abort', cancel);
      }
    }
  };

  // Never replace another library's tools using provideContext().
  document[registrationKey] = true;
  try {
    Promise.resolve(context.registerTool(tool)).catch(() => { delete document[registrationKey]; });
  } catch { delete document[registrationKey]; }
})();
