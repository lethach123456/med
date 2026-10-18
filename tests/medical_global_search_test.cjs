'use strict';

// Search behavior only: controlled clock, DOM fixture and deferred network responses.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/medical-global-search.js'), 'utf8');
let checks = 0;
const check = (condition, label) => { checks++; assert.ok(condition, label); };
const same = (actual, expected, label) => { checks++; assert.deepEqual(actual, expected, label); };

class Element {
  constructor(tag = 'div', properties = {}) {
    this.tagName = tag.toUpperCase(); this.dataset = {}; this.attributes = {}; this.listeners = {};
    this.className = ''; this.children = []; this.parentElement = null; this.hidden = false;
    this.value = ''; this.id = ''; this._html = ''; this.scrollHeight = 100; this.clientHeight = 100; this.scrollTop = 0;
    Object.assign(this, properties);
  }
  get classList() {
    const values = () => new Set(this.className.split(/\s+/).filter(Boolean));
    return {
      contains: value => values().has(value),
      add: (...names) => { const next = values(); names.forEach(name => next.add(name)); this.className = [...next].join(' '); },
      remove: (...names) => { const next = values(); names.forEach(name => next.delete(name)); this.className = [...next].join(' '); },
      toggle: (name, force) => { const next = values(); const enabled = force === undefined ? !next.has(name) : force; enabled ? next.add(name) : next.delete(name); this.className = [...next].join(' '); return enabled; }
    };
  }
  set innerHTML(value) {
    this._html = String(value); this.children.forEach(child => { child.parentElement = null; }); this.children = [];
    // Only search option anchors need DOM identities for keyboard navigation.
    for (const match of this._html.matchAll(/<a\b([^>]*)>([\s\S]*?)<\/a>/g)) {
      const className = /class="([^"]*)"/.exec(match[1])?.[1] || '';
      if (!className.split(/\s+/).includes('medical-search-item')) continue;
      const href = /href="([^"]*)"/.exec(match[1])?.[1] || '';
      const item = new Element('a', { className, href, textContent: match[2].replace(/<[^>]*>/g, '') });
      this.append(item);
    }
  }
  get innerHTML() { return this._html; }
  get childElementCount() { return this.children.length || (this._html ? 1 : 0); }
  append(...elements) { for (const element of elements) { element.parentElement = this; this.children.push(element); } }
  remove() { if (this.parentElement) this.parentElement.children = this.parentElement.children.filter(child => child !== this); this.parentElement = null; }
  setAttribute(key, value) { this.attributes[key] = String(value); if (key === 'id') this.id = String(value); }
  getAttribute(key) { return key === 'id' ? this.id : this.attributes[key] ?? null; }
  removeAttribute(key) { delete this.attributes[key]; }
  hasAttribute(key) { return Object.hasOwn(this.attributes, key); }
  addEventListener(type, callback) { (this.listeners[type] ||= []).push(callback); }
  emit(type, properties = {}) {
    const event = { type, target: this, defaultPrevented: false, button: 0, preventDefault() { this.defaultPrevented = true; }, ...properties };
    for (const listener of this.listeners[type] || []) listener(event);
    return event;
  }
  dispatchEvent(event) { this.emit(event.type, event); return true; }
  matches(selector) {
    return selector.split(',').some(part => {
      part = part.trim();
      if (part.startsWith('.')) return part.split('.').filter(Boolean).every(name => this.classList.contains(name));
      if (part.startsWith('[')) {
        const attribute = part.slice(1, -1).split('=')[0];
        return attribute.startsWith('data-') ? Object.hasOwn(this.dataset, attribute.slice(5).replace(/-([a-z])/g, (_, char) => char.toUpperCase())) : this.hasAttribute(attribute);
      }
      const tag = /^[a-z]+/i.exec(part)?.[0];
      if (tag && this.tagName.toLowerCase() !== tag.toLowerCase()) return false;
      const attribute = /\[([^=\]]+)(?:=["']?([^\]"']+)["']?)?\]/.exec(part);
      if (attribute) return attribute[2] === undefined ? this.hasAttribute(attribute[1]) : this.getAttribute(attribute[1]) === attribute[2];
      return Boolean(tag);
    });
  }
  closest(selector) { for (let node = this; node; node = node.parentElement) if (node.matches(selector)) return node; return null; }
  contains(target) { for (let node = target; node; node = node.parentElement) if (node === this) return true; return false; }
  querySelectorAll(selector) {
    return this.children.flatMap(child => [...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)]);
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  scrollIntoView() { this.scrolled = true; }
  focus() { this.ownerDocument.activeElement = this; this.emit('focus'); }
  getBoundingClientRect() { return { top: 120, height: 70 }; }
}

function harness({ kind = 'hero', locale = 'vi', both = false } = {}) {
  const document = new Element('document'); document.documentElement = { lang: locale }; document.readyState = 'complete'; document.activeElement = null;
  const fields = [];
  const add = candidate => {
    const wrap = new Element('section', { className: candidate === 'hero' ? 'hero-wrap' : 'medical-header' });
    const shell = new Element('div', { className: `medical-search-shell ${candidate}-search-shell`, dataset: { medicalSearch: '' } });
    const form = candidate === 'hero' ? new Element('form') : null;
    const input = new Element('input', { dataset: { medicalSearchInput: '' } });
    const button = candidate === 'hero' ? new Element('button', { className: 'send-button', dataset: { medicalSearchSubmit: '' }, attributes: { type: 'button' } }) : null;
    const results = new Element('div', { className: 'medical-search-results', dataset: { medicalSearchResults: '' }, hidden: true });
    if (form) { form.append(input, button); shell.append(form, results); } else shell.append(input, results);
    const home = candidate === 'hero' ? new Element('main', { className: 'med-home' }) : null;
    const quickButton = home ? new Element('button', { dataset: { homeQuery: 'nha khoa' } }) : null;
    const quickIcon = home ? new Element('span') : null;
    wrap.append(shell);
    if (home) { quickButton.append(quickIcon); home.append(wrap, quickButton); document.append(home); } else document.append(wrap);
    fields.push({ shell, form, input, button, results, home, quickButton, quickIcon });
  };
  add(kind); if (both) add(kind === 'hero' ? 'header' : 'hero');
  const own = element => { element.ownerDocument = document; element.children.forEach(own); }; own(document);
  document.createElement = tag => new Element(tag, { ownerDocument: document });
  const timers = new Map(); let now = 1_000_000, nextTimer = 1;
  const setTimeout = (callback, delay = 0) => { const id = nextTimer++; timers.set(id, { callback, due: now + delay }); return id; };
  const clearTimeout = id => timers.delete(id);
  const requests = [], navigations = [];
  const fetch = (url, options) => new Promise((resolve, reject) => requests.push({ url: new URL(url, 'https://medreview.test'), options, resolve, reject }));
  const location = { assign: href => navigations.push(href), origin: 'https://medreview.test' };
  const window = new Element('window', { setTimeout, clearTimeout, location, fetch, scrollY: 0, scrollTo() {}, requestAnimationFrame: callback => callback(), matchMedia: () => ({ matches: false }) });
  class ClockDate extends Date { constructor(...args) { super(...(args.length ? args : [now])); } static now() { return now; } }
  const localStorage = new Proxy({}, { get() { throw new Error('Global search must not persist patient queries in localStorage'); } });
  vm.runInNewContext(source, { document, window, fetch, location, AbortController, URL, URLSearchParams, Intl, Date: ClockDate, setTimeout, clearTimeout, localStorage, CustomEvent: class { constructor(type, properties = {}) { this.type = type; Object.assign(this, properties); } } }, { filename: 'medical-global-search.js' });
  const settle = async () => { for (let i = 0; i < 12; i++) await Promise.resolve(); };
  const tick = async milliseconds => {
    const end = now + milliseconds;
    for (;;) {
      const next = [...timers].filter(([, timer]) => timer.due <= end).sort((a, b) => a[1].due - b[1].due)[0];
      if (!next) break;
      now = next[1].due; timers.delete(next[0]); next[1].callback(); await settle();
    }
    now = end; await settle();
  };
  const respond = async (request, data = sample(), extra = {}) => {
    request.resolve({ ok: true, headers: { get: () => 'application/json' }, json: async () => data, ...extra }); await settle();
  };
  const type = (value, index = 0) => { document.activeElement = fields[index].input; fields[index].input.value = value; fields[index].input.emit('input'); };
  return { ...fields[0], fields, document, window, requests, navigations, tick, settle, respond, type };
}

const sample = (suffix = '') => ({ ok: true, groups: {
  facilities: [{ name: `Facility ${suffix}`, url: `/co-so-y-te/facility${suffix}` }],
  doctors: [{ name: `Doctor ${suffix}`, url: `/bac-si/doctor${suffix}` }],
  toplists: [{ title: `Toplist ${suffix}`, url: `/toplist/list${suffix}` }]
}, meta: { group_order: ['doctors', 'facilities', 'toplists'] } });

(async () => {
  for (const kind of ['hero', 'header']) {
    const app = harness({ kind });
    app.type(' a '); await app.tick(300); same(app.requests.length, 0, `${kind}: fewer than two trimmed characters does not search`);
    app.type('  mắt  '); await app.tick(219); same(app.requests.length, 0, `${kind}: input waits for the 220 ms debounce`);
    await app.tick(1); same(app.requests.length, 1, `${kind}: one complete word without trailing space starts a search`);
    same(app.requests[0].url.searchParams.get('q'), 'mắt', `${kind}: API receives the trimmed Vietnamese keyword`);
    await app.respond(app.requests[0]);
    check(app.results.innerHTML.indexOf('Doctor ') < app.results.innerHTML.indexOf('Facility '), `${kind}: server intent group order is respected`);
    const submit = app.input.emit('keydown', { key: 'Enter' }); await app.settle();
    check(submit.defaultPrevented && app.navigations.length === 0, `${kind}: Enter does not silently open the first suggestion`);
    app.input.emit('keydown', { key: 'ArrowUp' });
    same(app.input.getAttribute('aria-activedescendant'), `${app.results.id}-option-2`, `${kind}: ArrowUp from no selection chooses the last suggestion`);
    app.input.emit('keydown', { key: 'Enter' });
    same(app.navigations.at(-1), '/toplist/list', `${kind}: Enter follows only the deliberately selected current suggestion`);
  }

  const ime = harness();
  ime.input.emit('compositionstart'); ime.type('nhã'); await ime.tick(1000);
  same(ime.requests.length, 0, 'IME intermediate text is never sent to the API');
  const composingEnter = ime.input.emit('keydown', { key: 'Enter', isComposing: true });
  check(!composingEnter.defaultPrevented && ime.navigations.length === 0, 'IME confirmation Enter remains available to the input method');
  ime.input.emit('compositionend'); await ime.tick(219); same(ime.requests.length, 0, 'Completed IME input retains the debounce');
  await ime.tick(1); same(ime.requests.length, 1, 'Completed IME input performs one search');

  const race = harness(); race.type('răng'); await race.tick(220); const old = race.requests[0];
  race.type('mắt'); check(old.options.signal.aborted, 'New text aborts the obsolete request immediately');
  await race.tick(220); await race.respond(race.requests[1], sample('fresh')); await race.respond(old, sample('stale'));
  check(race.results.innerHTML.includes('fresh') && !race.results.innerHTML.includes('stale'), 'Late aborted responses cannot overwrite the current query');
  race.input.emit('keydown', { key: 'ArrowDown' }); race.type('tim');
  race.input.emit('keydown', { key: 'Enter' }); await race.settle();
  same(race.navigations.length, 0, 'Changing text invalidates the keyboard selection before Enter');
  same(race.requests.at(-1).url.searchParams.get('q'), 'tim', 'Enter on a new query starts that query immediately');

  const closes = [
    ['Escape', app => app.input.emit('keydown', { key: 'Escape' })],
    ['outside click', app => app.document.emit('click', { target: new Element('button') })],
    ['focus leaving search', app => { const outside = new Element('button'); app.document.activeElement = outside; app.shell.emit('focusout', { relatedTarget: outside }); }],
    ['header close event', app => app.shell.emit('medical-search:close')]
  ];
  for (const [label, close] of closes) {
    const app = harness({ kind: 'header' }); app.type('mắt'); await app.tick(220); const request = app.requests[0]; close(app); await app.tick(0);
    check(request.options.signal.aborted, `${label} aborts the active request`);
    await app.respond(request, sample('late')); await app.tick(200);
    check(app.input.getAttribute('aria-expanded') === 'false' && app.results.hidden && !app.results.innerHTML.includes('late'), `${label} prevents a delayed response reopening results`);
    app.type('răng'); close(app); await app.tick(250);
    same(app.requests.length, 1, `${label} cancels a pending debounce as well as active requests`);
  }

  const explicit = harness(); explicit.input.value = 'răng';
  const formSubmit = explicit.form.emit('submit'); await explicit.settle();
  check(formSubmit.defaultPrevented && explicit.requests.length === 1 && explicit.navigations.length === 0, 'Submitting the home form searches in place immediately');
  explicit.input.value = 'mắt'; const buttonClick = explicit.button.emit('click'); await explicit.settle();
  check(buttonClick.defaultPrevented && explicit.requests.length === 2 && explicit.navigations.length === 0, 'Clicking Send searches in place immediately');

  const quick = harness({ both: true });
  quick.type('mắt', 1); await quick.tick(220); const headerRequest = quick.requests[0];
  quick.input.value = 'nha khoa'; quick.input.focus(); quick.input.emit('input');
  quick.document.emit('click', { target: quick.quickIcon }); await quick.tick(220);
  check(headerRequest.options.signal.aborted, 'A homepage quick query still dismisses the other header search');
  same(quick.requests.length, 2, 'A quick query beside the hero survives its click bubbling to document');
  same(quick.requests[1].url.searchParams.get('q'), 'nha khoa', 'The quick query sends the keyword assigned by its click handler');
  await quick.respond(quick.requests[1], sample('quick'));
  check(quick.results.innerHTML.includes('quick') && quick.input.getAttribute('aria-expanded') === 'true', 'The homepage quick query renders its results in place');

  for (const outsideKind of ['another homepage', 'ordinary button', 'same homepage without focused input']) {
    const app = harness(); app.type('răng'); await app.tick(220); const request = app.requests[0];
    let target;
    if (outsideKind === 'another homepage') {
      const otherHome = new Element('main', { className: 'med-home' });
      target = new Element('button', { dataset: { homeQuery: 'mắt' } }); otherHome.append(target); app.document.append(otherHome);
    } else if (outsideKind === 'same homepage without focused input') {
      target = app.quickButton; app.document.activeElement = app.quickButton;
    } else target = new Element('button');
    app.document.emit('click', { target });
    check(request.options.signal.aborted, `A quick-query exception does not keep search open for ${outsideKind}`);
    await app.respond(request, sample('outside')); await app.tick(200);
    check(app.results.hidden && !app.results.innerHTML.includes('outside'), `Late results remain closed after clicking ${outsideKind}`);
  }

  const cached = harness({ both: true }); cached.type('mắt'); await cached.tick(220); await cached.respond(cached.requests[0], sample('cached'));
  cached.type('mắt', 1); await cached.tick(220);
  same(cached.requests.length, 1, 'Header and homepage reuse the same recent in-memory result');
  check(cached.fields[1].results.innerHTML.includes('cached'), 'Shared cached results render in the requesting search field');
  await cached.tick(30_001); cached.fields[1].shell.emit('medical-search:close'); cached.type('mắt', 1); await cached.tick(220);
  same(cached.requests.length, 2, 'Search cache expires after 30 seconds');

  const bounded = harness();
  for (let index = 0; index <= 40; index++) {
    bounded.type(`test${index}`); await bounded.tick(220); await bounded.respond(bounded.requests.at(-1), sample(String(index)));
  }
  const beforeEvicted = bounded.requests.length; bounded.type('test0'); await bounded.tick(220);
  same(bounded.requests.length, beforeEvicted + 1, 'The least recent query is evicted once the memory cache exceeds 40 entries');

  const english = harness({ locale: 'en' }); english.type('eye'); await english.tick(220);
  same(english.requests[0].url.searchParams.get('locale'), 'en', 'English header/home search requests only English results');
  await english.respond(english.requests[0], { ok: true, groups: { facilities: [{ name: '<script>bad()</script>', url: '/co-so-y-te/safe' }] } });
  check(!english.results.innerHTML.includes('<script>') && english.results.innerHTML.includes('&lt;script&gt;'), 'Result names are rendered as escaped text');

  process.stdout.write(`PASS medical global search: ${checks} checks\n`);
})().catch(error => { console.error(error); process.exitCode = 1; });
