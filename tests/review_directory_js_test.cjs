'use strict';

// Legacy feedback-component behavioral tests; /review now serves editorial
// articles with native GET navigation. No browser or network is used here.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const base = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(base, 'assets/js/review-directory.js'), 'utf8');
const template = fs.readFileSync(path.join(base, 'Tem/review-directory.php'), 'utf8');
const resultsTemplate = fs.readFileSync(path.join(base, 'Tem/review-results.php'), 'utf8');
const controllerSource = fs.readFileSync(path.join(base, 'review.php'), 'utf8');
let checks = 0;
const check = (condition, label) => { checks++; assert.ok(condition, label); };
const same = (actual, expected, label) => { checks++; assert.deepEqual(actual, expected, label); };

class Element {
  constructor(tag = 'div', options = {}) {
    this.tagName = tag.toUpperCase(); this.dataset = {}; this.attributes = {};
    this.children = []; this.parentElement = null; this.listeners = {}; this.hidden = false;
    this.className = ''; this.textContent = ''; this.value = ''; this.options = [];
    this.focusCalls = 0; this.animationCalls = 0; this.innerHTML = '';
    Object.assign(this, options);
  }
  addEventListener(type, callback) { (this.listeners[type] ||= []).push(callback); }
  emit(type, properties = {}) {
    const event = { type, target: this, defaultPrevented: false, button: 0,
      preventDefault() { this.defaultPrevented = true; }, ...properties };
    for (const callback of this.listeners[type] || []) callback(event);
    return event;
  }
  setAttribute(key, value) { this.attributes[key] = String(value); }
  append(...elements) {
    for (const element of elements) { element.remove(); element.parentElement = this; this.children.push(element); }
  }
  insertBefore(element, reference) {
    element.remove(); const index = this.children.indexOf(reference);
    element.parentElement = this; this.children.splice(index < 0 ? this.children.length : index, 0, element);
  }
  before(element) { this.parentElement.insertBefore(element, this); }
  remove() {
    if (!this.parentElement) return;
    const index = this.parentElement.children.indexOf(this);
    if (index >= 0) this.parentElement.children.splice(index, 1);
    this.parentElement = null;
  }
  replaceChildren(...elements) {
    for (const child of this.children) child.parentElement = null;
    this.children = []; this.append(...elements);
  }
  get childElementCount() { return this.children.length; }
  contains(target) { for (let node = target; node; node = node.parentElement) if (node === this) return true; return false; }
  matches(selector) {
    return selector.split(',').some(part => {
      part = part.trim();
      if (part.startsWith('.')) return this.className.split(/\s+/).includes(part.slice(1));
      if (part.startsWith('[')) {
        const attribute = part.slice(1, -1);
        if (attribute.startsWith('data-')) return Object.hasOwn(this.dataset, attribute.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase()));
        return Object.hasOwn(this.attributes, attribute);
      }
      return this.tagName.toLowerCase() === part.toLowerCase();
    });
  }
  closest(selector) { for (let node = this; node; node = node.parentElement) if (node.matches(selector)) return node; return null; }
  querySelector(selector) {
    for (const child of this.children) { if (child.matches(selector)) return child; const found = child.querySelector(selector); if (found) return found; }
    return null;
  }
  querySelectorAll(selector) {
    const nodes = [];
    for (const child of this.children) { if (child.matches(selector)) nodes.push(child); nodes.push(...child.querySelectorAll(selector)); }
    return nodes;
  }
  focus(options) { this.focusCalls++; this.focusOptions = options; this.ownerDocument.activeElement = this; }
  animate() { this.animationCalls++; return { cancel() {} }; }
  getBoundingClientRect() { return { top: 180, bottom: 430, height: 84 }; }
}

function harness({ locale = 'vi', start, compact = false, reduced = true, supportsFetch = true } = {}) {
  const origin = 'https://medreview.test';
  const location = { origin, href: origin + (start || (locale === 'en' ? '/en/review' : '/review')) };
  const root = new Element('main', { className: 'rv-directory', dataset: { locale } });
  const form = new Element('form', { id: 'reviewDirectoryFilter', action: origin + (locale === 'en' ? '/en/review' : '/review') });
  const input = new Element('input', { id: 'review-search', name: 'q', value: '' });
  const heading = new Element('div', { id: 'reviewResultsHeading' });
  const tools = new Element('div', { dataset: { reviewTools: '' } });
  const disclosure = new Element('details', { dataset: { reviewFilters: '' }, open: false });
  const toggle = new Element('summary', { id: 'reviewFilterToggle' });
  const panel = new Element('section', { id: 'reviewFilterOptions' });
  const close = new Element('button', { dataset: { reviewFilterClose: '' }, hidden: true });
  const slot = new Element('div', { dataset: { reviewSortSlot: '' } });
  const group = new Element('div', { dataset: { reviewSortGroup: '' } });
  const control = (name, options, value = '') => new Element('select', { id: 'review-' + name, name, options: options.map(value => ({ value })), value, dataset: { reviewControl: '' } });
  const sort = control('sort', ['newest', 'rating'], 'newest');
  const rating = control('rating', ['', '4', '5']);
  const media = control('media', ['', 'photos']);
  const controls = [sort, rating, media];
  const results = new Element('div', { id: 'reviewResults', innerHTML: '<p>Original published reviews</p>' });
  const count = new Element('span', { id: 'reviewResultCount', textContent: '9 bài viết phù hợp' });
  const status = new Element('p', { id: 'reviewDirectoryStatus' });
  const active = new Element('div', { id: 'reviewActiveFilters', hidden: true });
  const badge = new Element('b', { dataset: { reviewFilterCount: '' }, hidden: true });
  const loading = new Element('span', { dataset: { reviewLoading: '' }, hidden: true });
  const canonical = new Element('link', { href: 'https://medreview.vn' + (locale === 'en' ? '/en/review' : '/review') });
  form.append(input); group.append(sort); slot.append(group); panel.append(close, slot, rating, media);
  toggle.append(badge); disclosure.append(toggle, panel); tools.append(disclosure); heading.append(count, loading, tools);
  root.append(form, heading, active, status, results);
  const document = new Element('document');
  document.append(root); document.title = locale === 'en' ? 'Healthcare Reviews | MedReview' : 'Review | MedReview'; document.activeElement = null;
  const ids = [form, input, heading, toggle, panel, sort, rating, media, results, count, status, active];
  document.getElementById = id => ids.find(element => element.id === id) || null;
  const defaultQuery = document.querySelector.bind(document);
  document.querySelector = selector => selector === 'link[rel=canonical]' ? canonical : selector === '.medical-header' ? { getBoundingClientRect: () => ({ height: 84 }) } : defaultQuery(selector);
  document.createElement = tag => { const element = new Element(tag); element.ownerDocument = document; return element; };
  document.dispatched = []; document.dispatchEvent = event => { document.dispatched.push(event); document.emit(event.type, event); };
  const assignDocument = element => { element.ownerDocument = document; element.children.forEach(assignDocument); };
  assignDocument(document);
  const timers = new Map(); let nextTimer = 1, now = 0;
  const requests = [];
  const fetch = (url, options) => new Promise((resolve, reject) => requests.push({ url: new URL(url), options, resolve, reject }));
  const changes = []; const history = {};
  for (const method of ['pushState', 'replaceState']) history[method] = (state, title, url) => { changes.push({ method, url: new URL(url).href }); location.href = new URL(url).href; };
  const compactMedia = new Element('media', { matches: compact });
  const reducedMedia = new Element('media', { matches: reduced });
  const window = new Element('window', { fetch: supportsFetch ? fetch : undefined, AbortController, matchMedia: query => query.includes('max-width') ? compactMedia : reducedMedia, scrolls: [] });
  window.scrollTo = options => window.scrolls.push(options);
  class TestFormData {
    constructor(candidate) { assert.equal(candidate, form); this.values = [input, ...controls].map(element => [element.name, element.value]); }
    *[Symbol.iterator]() { yield* this.values; }
  }
  const sandbox = {
    document, window, location, history, fetch, FormData: TestFormData, URL, URLSearchParams, Intl, AbortController, innerHeight: 800, scrollY: 0,
    CustomEvent: class { constructor(type) { this.type = type; } },
    setTimeout(callback, delay) { const id = nextTimer++; timers.set(id, { callback, time: now + delay }); return id; },
    clearTimeout(id) { timers.delete(id); },
  };
  vm.runInNewContext(source, sandbox, { filename: 'review-directory.js' });
  const tick = milliseconds => {
    now += milliseconds;
    for (const [id, timer] of [...timers]) if (timer.time <= now) { timers.delete(id); timer.callback(); }
  };
  const settle = async () => { for (let pass = 0; pass < 12; pass++) await Promise.resolve(); };
  const respond = async (request, data = {}, extra = {}) => {
    request.resolve({ ok: true, headers: { get: () => 'application/json; charset=utf-8' }, json: async () => ({ ok: true, locale, html: '<p>Updated reviews</p>', paging: { total: 8, page: 1 }, ...data }), ...extra });
    await settle();
  };
  const pager = href => { const parent = new Element('nav', { className: 'rv-dir-pagination' }); const link = new Element('a', { href: origin + href }); parent.append(link); results.append(parent); return link; };
  return { root, form, input, controls, rating, media, sort, sortGroup: group, sortSlot: slot, tools, disclosure, toggle, panel, close, heading, results, count, status, active, badge, loading, canonical,
    document, window, location, history, changes, requests, tick, settle, respond, pager, compactMedia, reducedMedia };
}

(async () => {
  check(template.includes('id="reviewDirectoryFilter"') && template.includes('method="get"'), 'Search retains native GET form');
  same((template.match(/form="reviewDirectoryFilter" data-review-control/g) || []).length, 3, 'All three external selects are associated with the search form');
  check(template.includes('type="submit" form="reviewDirectoryFilter"') && template.includes('data-review-filter-close hidden'), 'Apply submits the same form and no-JS close control stays hidden');
  check(resultsTemplate.includes('review_view_url($reviewBasePath, $reviewFilters') && resultsTemplate.includes('review_view_escape($rvHref)'), 'SSR paging preserves filters and escapes detail URLs');
  check(controllerSource.includes('medical_review_articles_directory_page(') && !controllerSource.includes('review_view_directory('), 'The public review menu queries articles, not individual feedback');
  check(controllerSource.includes('review-articles.js') && !controllerSource.includes("$_GET['partial']"), 'Editorial directory uses native GET navigation instead of the legacy feedback partial-response protocol');

  const noFetch = harness({ supportsFetch: false });
  same(noFetch.form.listeners.submit, undefined, 'Unsupported clients keep native GET submission');
  same(noFetch.input.listeners.input, undefined, 'No live search interception without fetch support');
  const app = harness();
  same(app.sortGroup.parentElement, app.tools, 'Desktop places sort next to the filter control without duplicating it');
  same(app.close.hidden, false, 'Enhanced filter panel exposes an actual close button');
  app.input.value = ' mắt ';
  app.input.emit('input'); app.tick(379);
  same(app.requests.length, 0, 'Typing is debounced before any request');
  app.tick(1); same(app.requests.length, 1, 'One request starts after the 380 ms debounce');
  same(app.requests[0].url.searchParams.get('q'), 'mắt', 'Query is trimmed and remains UTF-8');
  same(app.requests[0].url.searchParams.get('partial'), '1', 'Live updates request only escaped SSR results');
  same(app.requests[0].options.credentials, 'same-origin', 'Public fetch uses same-origin credentials only');
  same(app.requests[0].options.headers.Accept, 'application/json', 'Partial response format negotiated explicitly');
  same(app.results.attributes['aria-busy'], 'true', 'Loading is announced on the result region');
  app.input.value = 'răng'; app.input.emit('input');
  check(app.requests[0].options.signal.aborted, 'New typing aborts the previous request immediately');
  app.tick(380); same(app.requests.length, 2, 'Latest input gets its own debounced request');
  await app.respond(app.requests[0], { html: '<p>Stale response</p>', paging: { total: 200, page: 1 } });
  same(app.results.innerHTML, '<p>Original published reviews</p>', 'An aborted late response cannot replace current reviews');
  same(app.results.attributes['aria-busy'], 'true', 'A stale response cannot clear the newer loading state');
  await app.respond(app.requests[1], { html: '<p>Newest reviews</p>', paging: { total: 12, page: 1 } });
  same(app.results.innerHTML, '<p>Newest reviews</p>', 'Latest valid response replaces only the result region');
  same(app.results.attributes['aria-busy'], 'false', 'Latest completion clears loading');
  check(app.count.textContent.startsWith('12 ') && app.status.textContent === app.count.textContent, 'New total is visible and announced');
  same(app.changes.at(-1).method, 'replaceState', 'Debounced query updates do not add one history entry per keystroke');
  check(!app.location.href.includes('partial=') && app.location.href.includes('q='), 'Address bar is shareable without private partial parameter');
  same(app.canonical.href, 'https://medreview.vn/review?q=r%C4%83ng', 'Canonical origin retained with updated public filters');
  same(app.document.dispatched.at(-1).type, 'review:results-updated', 'Replaced cards notify image fallback setup');

  app.rating.value = '4'; app.rating.emit('change'); const ratingRequest = app.requests.at(-1);
  same(ratingRequest.url.searchParams.get('rating'), '4', 'Changing rating starts a real filtered request');
  app.sort.value = 'rating'; app.sort.emit('change'); check(ratingRequest.options.signal.aborted, 'New sort aborts obsolete filter request');
  app.media.value = 'photos'; app.media.emit('change'); const filterRequest = app.requests.at(-1);
  same(filterRequest.url.searchParams.get('sort'), 'rating', 'Sort is included despite being outside the form DOM');
  same(filterRequest.url.searchParams.get('media'), 'photos', 'Media select submits with all current controls');
  await app.respond(filterRequest, { html: '<p>Filtered reviews</p>', paging: { total: 3, page: 1 } });
  same(app.badge.textContent, '2', 'Filter badge counts active rating and photo filters');
  same(app.active.children.filter(child => child.dataset.reviewRemove !== undefined).length, 3, 'Keyword and both filters have removable chips');
  same(app.changes.at(-1).method, 'pushState', 'Explicit filter change creates navigable history');
  app.disclosure.open = true;
  const submit = app.form.emit('submit');
  check(submit.defaultPrevented && !app.disclosure.open, 'Enhanced submit closes the filter and prevents full navigation');
  check(!app.requests.at(-1).url.searchParams.has('page'), 'A changed search starts at page one');
  await app.respond(app.requests.at(-1));
  check(app.heading.focusCalls > 0 && app.window.scrolls.at(-1).behavior === 'auto', 'Explicit submission focuses results and respects reduced motion');

  const link = app.pager('/review?q=r%C4%83ng&rating=4&media=photos&sort=rating&page=2');
  const requestCount = app.requests.length;
  const modifiedClick = app.root.emit('click', { target: link, metaKey: true });
  check(!modifiedClick.defaultPrevented && app.requests.length === requestCount, 'Modified pager click remains native for a new tab');
  const pageClick = app.root.emit('click', { target: link });
  check(pageClick.defaultPrevented && app.requests.at(-1).url.searchParams.get('page') === '2', 'Plain pager click fetches the requested page');
  await app.respond(app.requests.at(-1), { html: '<p>Page two</p>', paging: { total: 20, page: 2 } });
  check(app.document.title.endsWith(' — Trang 2') && app.location.href.includes('page=2'), 'Page response updates title and URL');
  app.location.href = 'https://medreview.test/review.php?q=old&page=999';
  app.window.emit('popstate');
  const aliasRequest = app.requests.at(-1);
  check(aliasRequest.url.pathname === '/review' && aliasRequest.url.searchParams.get('q') === 'old', 'Back to legacy review.php loads the canonical matching route');
  await app.respond(aliasRequest, { html: '<p>Old history restored</p>', paging: { total: 2, page: 1 } });
  check(app.results.innerHTML.includes('Old history') && !app.location.href.includes('page=') && !app.document.title.includes(' — Trang'), 'Server-clamped history restores results, canonical page and title');
  same(app.changes.at(-1).method, 'replaceState', 'Back restoration normalizes legacy entry without creating a new history step');

  const english = harness({ locale: 'en' });
  english.location.href = 'https://medreview.test/review.php?lang=en&q=eye&page=2';
  english.window.emit('popstate');
  check(english.requests.length === 1 && english.requests[0].url.pathname === '/en/review' && !english.requests[0].url.searchParams.has('lang'), 'English legacy Back preserves English data via canonical English path');
  await english.respond(english.requests[0], { html: '<p>English data</p>', paging: { total: 20, page: 2 } });
  check(english.document.title.endsWith(' — Page 2') && english.location.href.includes('/en/review'), 'English page labels and route remain English');
  english.location.href = 'https://medreview.test/review?lang=en&q=clinic'; english.window.emit('popstate');
  check(english.requests.at(-1).url.pathname === '/en/review' && !english.requests.at(-1).url.searchParams.has('lang'), 'Pretty legacy locale-query alias also canonicalizes safely');

  app.input.value = 'network'; app.input.emit('input'); app.tick(380);
  const oldHtml = app.results.innerHTML;
  app.requests.at(-1).reject(new Error('Simulated offline')); await app.settle();
  same(app.results.innerHTML, oldHtml, 'Fetch errors retain the last successful review list');
  same(app.results.attributes['aria-busy'], 'false', 'Fetch failure clears the spinner and busy state');
  const error = app.root.querySelector('.rv-dir-fetch-error');
  check(error && error.attributes.role === 'alert', 'Load error is accessible and visible');
  const native = error.children.find(child => child.tagName === 'A');
  check(native.href.includes('q=network') && !native.href.includes('partial='), 'Error has a working native navigation fallback');
  const retry = error.children.find(child => child.tagName === 'BUTTON');
  retry.emit('click'); check(app.requests.at(-1).url.searchParams.get('q') === 'network', 'Retry preserves the failed request filters');
  await app.respond(app.requests.at(-1), { html: '<p>Retry recovered</p>' });
  check(app.results.innerHTML.includes('Retry recovered') && !app.root.querySelector('.rv-dir-fetch-error'), 'Successful retry replaces results and removes error');

  app.input.value = 'locale'; app.input.emit('input'); app.tick(380);
  await app.respond(app.requests.at(-1), { locale: 'en', html: '<p>Wrong language</p>' });
  check(!app.results.innerHTML.includes('Wrong language') && app.root.querySelector('.rv-dir-fetch-error'), 'Wrong-language partial cannot overwrite reviews');
  app.input.value = 'bad response'; app.input.emit('input'); app.tick(380);
  await app.respond(app.requests.at(-1), {}, { headers: { get: () => 'text/html' } });
  check(app.root.querySelector('.rv-dir-fetch-error') && app.results.innerHTML.includes('Retry recovered'), 'HTML challenge or invalid partial keeps existing data');

  const composing = harness();
  composing.input.emit('compositionstart'); composing.input.value = 'nhã'; composing.input.emit('input'); composing.tick(1000);
  same(composing.requests.length, 0, 'Vietnamese IME composition does not send incomplete input');
  composing.input.emit('compositionend'); composing.tick(380);
  same(composing.requests.length, 1, 'Completed composition schedules one search');
  const unsafeText = '<script>alert(1)</script>';
  composing.input.value = unsafeText; composing.input.emit('input'); composing.tick(380);
  await composing.respond(composing.requests.at(-1));
  const queryChip = composing.active.children.find(child => child.dataset.reviewRemove === 'q');
  check(queryChip.children[0].textContent.includes(unsafeText) && queryChip.innerHTML === '', 'Filter chips use textContent rather than unescaped HTML');
  composing.root.emit('click', { target: queryChip });
  same(composing.input.value, '', 'Removing the keyword chip clears only the input');
  check(!composing.requests.at(-1).url.searchParams.has('q'), 'Removing keyword sends cleared query');
  same(composing.document.activeElement, composing.heading, 'Removing a disappearing chip moves keyboard focus to a stable heading');
  const clear = app.active.children.find(child => child.dataset.reviewClear !== undefined);
  app.root.emit('click', { target: clear });
  check(app.input.value === '' && app.rating.value === '' && app.media.value === '' && app.sort.value === 'newest', 'Clear all restores every native control default');
  check(app.document.activeElement === app.input && app.requests.at(-1).url.searchParams.size === 1, 'Reset focuses search and requests only partial, no stale filters');

  const phone = harness({ compact: true });
  same(phone.sortGroup.parentElement, phone.sortSlot, 'Mobile moves sort into the same filter panel');
  phone.disclosure.open = true; phone.document.activeElement = phone.rating; phone.close.emit('click');
  check(!phone.disclosure.open && phone.document.activeElement === phone.toggle, 'Close restores keyboard focus to the filter trigger');
  phone.disclosure.open = true; phone.document.emit('keydown', { key: 'Escape' });
  check(!phone.disclosure.open && phone.document.activeElement === phone.toggle, 'Escape closes the disclosure accessibly');
  phone.disclosure.open = true; phone.document.emit('pointerdown', { target: phone.input });
  same(phone.disclosure.open, false, 'Pointer outside closes the filter panel');
  const resized = harness(); resized.sort.focus(); resized.compactMedia.matches = true; resized.compactMedia.emit('change');
  check(resized.sortGroup.parentElement === resized.sortSlot && resized.disclosure.open && resized.document.activeElement === resized.sort, 'Responsive relocation keeps an active sort control visible and focused');

  const guarded = harness();
  guarded.location.href = 'https://outside.example/review'; guarded.window.emit('popstate');
  same(guarded.requests.length, 0, 'AJAX refuses another origin');
  guarded.location.href = 'https://medreview.test/co-so-y-te'; guarded.window.emit('popstate');
  same(guarded.requests.length, 0, 'AJAX refuses another directory route');
  guarded.location.href = 'https://medreview.test/review.php?lang=en'; guarded.window.emit('popstate');
  same(guarded.requests.length, 0, 'Legacy alias cannot request another locale from the Vietnamese directory');
  console.log(`Review directory JS: ${checks} checks passed. No browser, network or database used.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
