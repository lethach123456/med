'use strict';

// Deterministic mock DOM/Image/observer tests. No browser, PHP, DB or network.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname, '../assets/js/doctor-profile.js'), 'utf8');
let checks = 0;
const check = (ok, label) => { checks++; assert.ok(ok, label); };
const equal = (actual, expected, label) => { checks++; assert.equal(actual, expected, label); };

class ClassList {
  constructor() { this.values = new Set(); }
  add(name) { this.values.add(name); }
  remove(name) { this.values.delete(name); }
  contains(name) { return this.values.has(name); }
  toggle(name, force) {
    const enable = force ?? !this.contains(name);
    if (enable) this.add(name); else this.remove(name);
    return enable;
  }
}
class Element {
  constructor(tag = 'div', attributes = {}) {
    this.tagName = tag.toUpperCase(); this.attributes = new Map(); this.dataset = {};
    this.children = []; this.parentElement = null; this.listeners = new Map();
    this.classList = new ClassList(); this.hidden = false; this.open = false; this.disabled = false;
    this.complete = false; this.naturalWidth = 0; this.textContent = ''; this.focuses = [];
    this.queries = new Map(); this.lists = new Map(); this.scrolls = [];
    this.style = {overflow: '', values: new Map(), setProperty(name, value) { this.values.set(name, value); }};
    Object.entries(attributes).forEach(([name, value]) => this.setAttribute(name, value));
  }
  get id() { return this.getAttribute('id') || ''; }
  get hash() { const href = this.getAttribute('href') || ''; return href.startsWith('#') ? href : ''; }
  get src() { return this.getAttribute('src') || ''; }
  set src(value) { this.setAttribute('src', value); }
  get alt() { return this.getAttribute('alt') || ''; }
  set alt(value) { this.setAttribute('alt', value); }
  setAttribute(name, value) {
    this.attributes.set(name, String(value));
    if (name.startsWith('data-')) this.dataset[name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] = String(value);
  }
  getAttribute(name) { return this.attributes.get(name) ?? null; }
  hasAttribute(name) { return this.attributes.has(name); }
  removeAttribute(name) { this.attributes.delete(name); }
  append(child) { child.parentElement = this; this.children.push(child); }
  contains(node) { return this === node || this.children.some(child => child.contains(node)); }
  querySelector(selector) { return this.queries.get(selector) ?? null; }
  querySelectorAll(selector) { return this.lists.get(selector) ?? []; }
  closest(selector) {
    for (let current = this; current; current = current.parentElement) {
      if (selector === 'a[href^="#"]' && current.tagName === 'A' && current.hash) return current;
      if (selector === 'button' && current.tagName === 'BUTTON') return current;
      if (selector === 'figure' && current.tagName === 'FIGURE') return current;
      if (selector === '.dp-nav' && current.className === 'dp-nav') return current;
    }
    return null;
  }
  matches() { return Boolean(this.interacting); }
  focus(options) { this.focuses.push(options); }
  scrollIntoView(options) { this.scrolls.push(options); }
  addEventListener(name, callback, options = {}) {
    if (!this.listeners.has(name)) this.listeners.set(name, []);
    this.listeners.get(name).push({callback, once: Boolean(options.once)});
  }
  dispatch(name, values = {}) {
    const event = {target: this, button: 0, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...values};
    const listeners = [...(this.listeners.get(name) || [])];
    for (const listener of listeners) {
      listener.callback(event);
      if (listener.once) this.listeners.set(name, this.listeners.get(name).filter(item => item !== listener));
    }
    return event;
  }
}

function fixture(options = {}) {
  const root = new Element('main'), header = new Element('header'), body = new Element('body');
  body.style.overflow = options.overflow || 'auto'; body.append(root);
  let headerHeight = options.headerHeight ?? 83.2;
  header.getBoundingClientRect = () => ({height: headerHeight});
  const mobile = new Element('nav'), desktop = new Element('nav');
  mobile.className = 'dp-nav'; mobile.clientWidth = options.navViewport ? 160 : 0; mobile.scrollLeft = 0;
  mobile.scrollTo = values => { mobile.scrolls.push(values); mobile.scrollLeft = values.left; };
  root.append(mobile); root.append(desktop);
  const sections = [], mobileLinks = [], desktopLinks = [];
  for (const [index, id] of ['gioi-thieu', 'chuyen-mon', 'nguon'].entries()) {
    const section = new Element('section', {id}); section.top = [200, 800, 1400][index];
    section.getBoundingClientRect = () => ({top: section.top});
    const heading = new Element('h2', index === 1 ? {tabindex: '0'} : {});
    section.append(heading); section.queries.set('h2,h3', heading); root.append(section); sections.push(section);
    const left = new Element('a', {href: '#' + id}), right = new Element('a', {href: '#' + id});
    left.offsetLeft = index * 120; left.offsetWidth = 100;
    mobile.append(left); desktop.append(right); mobileLinks.push(left); desktopLinks.push(right);
  }
  const heroLink = new Element('a', {href: '#nguon'}); root.append(heroLink);
  const deadLink = new Element('a', {href: '#missing'}); root.append(deadLink);
  const malformed = new Element('a', {href: '#%E0%A4%A'}); root.append(malformed);
  const article = new Element('div'); article.scrollHeight = options.shortArticle ? 240 : 900;
  const articleLinks = [new Element('a'), new Element('a', {tabindex: '0'}), new Element('a', {tabindex: '-1'})];
  articleLinks.forEach(link => article.append(link)); article.lists.set('a', articleLinks); sections[0].append(article);
  const more = new Element('button', {'aria-expanded': 'false', 'data-more': 'Read full profile', 'data-less': 'Read less'});
  more.hidden = true; more.firstChild = {textContent: 'Read full profile'};
  const label = new Element('span'); label.textContent = 'Read full profile';
  if (options.labelSpan) more.queries.set('[data-dp-read-more-label]', label);
  root.append(more);
  let articleAnimations = 0, cancelled = 0, imageAnimations = 0;
  if (!options.noAnimation) article.animate = () => { articleAnimations++; return {cancel() { cancelled++; }}; };
  const portraitHolder = new Element('div'), portrait = new Element('img');
  portraitHolder.append(portrait); root.append(portraitHolder);
  const buttons = [], figures = [], photos = [];
  const photoCount = options.emptyGallery ? 0 : options.singleImage ? 1 : 4;
  for (let index = 0; index < photoCount; index++) {
    const figure = new Element('figure');
    const button = new Element('button', {'data-dp-gallery': index * 3, 'data-dp-gallery-caption': 'Room ' + index, 'data-dp-gallery-alt': 'Actual room ' + index});
    const photo = new Element('img', {src: 'https://images.example.org/doctor-' + index + '.webp', alt: 'Legacy alt ' + index});
    if (options.missingCaption) { button.removeAttribute('data-dp-gallery-caption'); delete button.dataset.dpGalleryCaption; }
    if (options.missingAlt) { button.removeAttribute('data-dp-gallery-alt'); delete button.dataset.dpGalleryAlt; }
    if (options.emptyCaption) {
      button.setAttribute('data-dp-gallery-caption', '');
      const citations = new Element('figcaption'); citations.textContent = '[1] [2]'; figure.queries.set('figcaption', citations); figure.append(citations);
    }
    button.append(photo); button.queries.set('img', options.missingPhoto && index === 0 ? null : photo);
    figure.append(button); root.append(figure); buttons.push(button); figures.push(figure); photos.push(photo);
  }
  const orphan = new Element('img');
  const dialog = new Element('dialog'); root.append(dialog);
  const names = ['viewer-image', 'count', 'caption', 'error', 'prev', 'next', 'close'];
  const viewer = Object.fromEntries(names.map(name => [name, new Element(name === 'viewer-image' ? 'img' : name === 'prev' || name === 'next' || name === 'close' ? 'button' : 'p')]));
  Object.values(viewer).forEach(element => dialog.append(element));
  viewer.error.hidden = true; viewer.error.textContent = 'Không tải được ảnh này.';
  dialog.queries = new Map(names.map(name => ['[data-dp-' + name + ']', options.missingPart === name ? null : viewer[name]]));
  dialog.showModal = options.noDialog ? undefined : () => { if (options.modalThrows) throw new Error('Unavailable'); dialog.open = true; };
  dialog.close = () => { dialog.open = false; dialog.dispatch('close'); };
  if (!options.noAnimation) viewer['viewer-image'].animate = () => { imageAnimations++; };
  if (!options.noGetAnimations) viewer['viewer-image'].getAnimations = () => [{cancel() { cancelled++; }}];
  root.queries = new Map([
    ['[data-dp-article]', options.noArticle ? null : article],
    ['[data-dp-read-more]', options.noReadMore ? null : more],
    ['[data-dp-lightbox]', options.missingDialog ? null : dialog],
  ]);
  root.lists = new Map([
    ['.dp-nav a[href^="#"],nav.dp-toc a[href^="#"]', [...mobileLinks, ...desktopLinks, deadLink, malformed]],
    ['[data-dp-photo]', [portrait]],
    ['[data-dp-gallery-photo]', [...photos, orphan]],
    ['[data-dp-gallery]', buttons],
  ]);
  const fontCallbacks = [];
  const document = {body,
    querySelector: selector => selector === '.doctor-profile' ? options.noRoot ? null : root : selector === '.medical-header' && !options.noHeader ? header : null,
    getElementById: id => sections.find(section => section.id === id) || null,
    ...(options.noFonts ? {} : {fonts: {ready: {then(callback) { fontCallbacks.push(callback); }}}}),
  };
  const frames = new Map(), timers = new Map(); let nextFrame = 0, nextTimer = 0;
  const window = new Element('window'); window.location = {hash: options.hash || ''};
  window.setTimeout = callback => { timers.set(++nextTimer, callback); return nextTimer; };
  if (!options.noRAF) window.requestAnimationFrame = callback => { frames.set(++nextFrame, callback); return nextFrame; };
  const motion = new Element(); motion.matches = Boolean(options.reduced);
  if (!options.noMatchMedia) window.matchMedia = () => motion;
  const observers = [], resizers = [], requests = [];
  if (!options.noIO) window.IntersectionObserver = class {
    constructor(callback, configuration) { this.callback = callback; this.configuration = configuration; this.targets = []; observers.push(this); }
    observe(target) { this.targets.push(target); }
    disconnect() { this.disconnected = true; }
    emit(entries) { this.callback(entries.map(([target, isIntersecting]) => ({target, isIntersecting}))); }
  };
  if (!options.noRO) window.ResizeObserver = class {
    constructor(callback) { this.callback = callback; resizers.push(this); }
    observe(target) { this.target = target; }
  };
  const MockImage = class extends Element {
    constructor() {
      super('img'); requests.push(this);
      this.complete = Boolean(options.cachedImage); this.naturalWidth = options.cachedImage ? 640 : 0;
      if (!options.noDecode) this.decode = () => options.decodeReject ? Promise.reject(new Error('No decode')) : Promise.resolve();
    }
  };
  vm.runInNewContext(source, {window, document, Image: options.noImage ? undefined : MockImage, console}, {filename: 'doctor-profile.js'});
  const flush = () => {
    for (let pass = 0; pass < 20 && (frames.size || timers.size); pass++) {
      const callbacks = [...frames.values(), ...timers.values()]; frames.clear(); timers.clear();
      callbacks.forEach(callback => callback());
    }
    assert.equal(frames.size + timers.size, 0, 'All scheduled fixture frames settle');
  };
  return {root, header, body, mobile, desktop, setHeaderHeight: height => { headerHeight = height; }, sections, mobileLinks, desktopLinks,
    heroLink, deadLink, malformed, article, articleLinks, more, label, portrait, portraitHolder,
    buttons, photos, figures, orphan, dialog, viewer, frames, timers, fontCallbacks, flush, window, motion, observers, resizers, requests,
    click: (target, values = {}) => root.dispatch('click', {target, ...values}),
    articleAnimations: () => articleAnimations, imageAnimations: () => imageAnimations, cancelled: () => cancelled};
}
const settle = async (request, name = 'load') => {
  request.complete = true; request.naturalWidth = name === 'load' ? 640 : 0; request.dispatch(name);
  await Promise.resolve(); await Promise.resolve();
};

(async () => {
  const nav = fixture({hash: '#chuyen-mon'}); nav.flush();
  equal(nav.root.style.values.get('--dp-header-height'), '84px', 'Measured shared header uses finite rounded height');
  equal(nav.resizers[0].target, nav.header, 'ResizeObserver watches shared header');
  const observer = nav.observers.find(item => item.targets.includes(nav.sections[0]));
  equal(observer.targets.length, 3, 'Duplicate desktop/mobile links observe real sections only once');
  equal(observer.configuration.rootMargin, '-84px 0px -50% 0px', 'Observer tracks measured header clearance');
  equal(nav.requests.length, 0, 'Startup does not preload gallery images');
  for (const links of [nav.mobileLinks, nav.desktopLinks]) equal(links[1].getAttribute('aria-current'), 'location', 'Initial hash synchronizes both navigation surfaces');
  observer.emit([[nav.sections[0], true]]); observer.emit([[nav.sections[1], true]]);
  for (const links of [nav.mobileLinks, nav.desktopLinks]) check(links[0].classList.contains('is-active'), 'Unchanged earlier section remains active');
  observer.emit([[nav.sections[0], false]]);
  for (const links of [nav.mobileLinks, nav.desktopLinks]) equal(links[1].getAttribute('aria-current'), 'location', 'Leaving section selects retained visible section');
  nav.sections[1].top = 900; nav.sections[2].top = 400; observer.emit([[nav.sections[2], true]]);
  equal(nav.desktopLinks[2].getAttribute('aria-current'), 'location', 'Current geometry determines first visible section');
  nav.setHeaderHeight(104); nav.resizers[0].callback(); nav.window.dispatch('resize'); nav.window.dispatch('resize');
  equal(nav.frames.size, 1, 'Header resize and viewport changes coalesce');
  nav.flush(); equal(nav.root.style.values.get('--dp-header-height'), '104px', 'Resizing updates root CSS height');
  check(observer.disconnected, 'New measurement disconnects stale section observer');
  observer.emit([[nav.sections[0], true]]);
  equal(nav.desktopLinks[2].getAttribute('aria-current'), 'location', 'Disconnected observer cannot overwrite navigation state');
  for (const [height, value] of [[NaN, '72px'], [Infinity, '72px'], [0, '72px'], [-1, '72px'], [2, '24px'], [99999, '240px']]) {
    nav.setHeaderHeight(height); nav.window.dispatch('resize'); nav.flush();
    equal(nav.root.style.values.get('--dp-header-height'), value, 'Invalid/extreme header height stays bounded: ' + height);
  }
  const event = nav.click(nav.mobileLinks[1]);
  check(!event.defaultPrevented, 'Navigation keeps native anchor hash/history/scroll');
  equal(nav.sections[1].querySelector('h2,h3').focuses.length, 0, 'Heading focus waits for native navigation');
  nav.flush();
  equal(nav.sections[1].querySelector('h2,h3').focuses.at(-1).preventScroll, true, 'Heading focus causes no second page scroll');
  equal(nav.sections[1].querySelector('h2,h3').getAttribute('tabindex'), '0', 'Existing heading tabindex is preserved');
  nav.click(nav.heroLink); nav.flush();
  equal(nav.sections[2].querySelector('h2,h3').getAttribute('tabindex'), '-1', 'Hero section anchors get programmatically focusable headings');
  for (const modified of [{ctrlKey:true}, {metaKey:true}, {shiftKey:true}, {altKey:true}, {button:1}, {defaultPrevented:true}]) {
    const before = nav.sections[0].querySelector('h2,h3').focuses.length;
    nav.click(nav.desktopLinks[0], modified); nav.flush();
    equal(nav.sections[0].querySelector('h2,h3').focuses.length, before, 'Modified click preserves native behavior without stealing focus');
  }
  check(!nav.click(nav.deadLink).defaultPrevented && !nav.click(nav.malformed).defaultPrevented, 'Unknown/malformed hashes are harmless native links');
  nav.window.location.hash = '#gioi-thieu'; nav.window.dispatch('hashchange');
  equal(nav.desktopLinks[0].getAttribute('aria-current'), 'location', 'History hash synchronizes both navigation menus');
  equal(nav.mobile.scrolls.length, 0, 'Hidden horizontal nav is never scrolled');
  const chips = fixture({navViewport:true,hash:'#nguon'}); chips.flush();
  equal(chips.mobile.scrolls.length, 1, 'Active off-screen mobile chip is revealed horizontally');
  equal(chips.mobile.scrolls[0].left, 228, 'Horizontal chip reveal preserves leading clearance');
  check(!('top' in chips.mobile.scrolls[0]), 'Chip reveal never changes vertical scroll');
  chips.window.dispatch('hashchange'); equal(chips.mobile.scrolls.length, 1, 'Already-visible active chip is not repeatedly scrolled');
  chips.motion.matches = true; chips.window.location.hash = '#gioi-thieu'; chips.window.dispatch('hashchange');
  equal(chips.mobile.scrolls.at(-1).behavior, 'auto', 'Horizontal chip reveal respects reduced motion');

  const article = fixture(); article.flush();
  check(article.article.classList.contains('is-collapsed') && !article.more.hidden, 'Only long articles receive progressive preview');
  for (const link of article.articleLinks) equal(link.getAttribute('tabindex'), '-1', 'Visually concealed article links leave tab order');
  const hint = article.observers.find(item => item.targets.includes(article.more));
  check(!article.more.classList.contains('is-hinting'), 'Read-more cue waits until control enters viewport');
  hint.emit([[article.more, true]]);
  check(article.more.classList.contains('is-hinting') && hint.disconnected, 'Read-more cue starts once then disconnects observer');
  article.more.dispatch('pointerenter'); check(!article.more.classList.contains('is-hinting'), 'Pointer interaction stops finite cue');
  article.more.dispatch('click');
  check(!article.article.classList.contains('is-collapsed') && article.more.getAttribute('aria-expanded') === 'true', 'Read-more reveals full article');
  equal(article.more.firstChild.textContent, 'Read less', 'Read-more label reflects expanded state');
  for (const [index, expected] of [null, '0', '-1'].entries()) equal(article.articleLinks[index].getAttribute('tabindex'), expected, 'Exact original article tabindex restored');
  equal(article.articleAnimations(), 1, 'Article reveal uses one restrained transition');
  article.fontCallbacks.forEach(callback => callback());
  check(!article.article.classList.contains('is-collapsed'), 'Late fonts ready never unexpectedly re-collapse expanded text');
  article.more.dispatch('click');
  equal(article.more.firstChild.textContent, 'Read full profile', 'Collapse restores original label');
  equal(article.article.scrolls.length, 1, 'Only explicit user collapse returns to shortened article');
  equal(article.article.scrolls[0].behavior, 'smooth', 'User collapse respects normal motion preference');
  check(article.cancelled() > 0, 'Read-more cancels a previous reveal animation');
  const short = fixture({shortArticle:true}); short.flush();
  check(short.more.hidden && !short.article.classList.contains('is-collapsed'), 'Short article stays entirely readable with no redundant button');
  equal(short.articleLinks[1].getAttribute('tabindex'), '0', 'Short article tab order is not changed');
  short.article.scrollHeight = 900; short.fontCallbacks.forEach(callback => callback());
  check(!short.more.hidden && short.article.classList.contains('is-collapsed'), 'Fonts-ready can detect late article overflow');
  const labeled = fixture({labelSpan:true}); labeled.more.dispatch('click');
  equal(labeled.label.textContent, 'Read less', 'Optional dedicated label span leaves icons intact');
  const reduce = fixture({reduced:true}); reduce.flush(); reduce.more.dispatch('click'); reduce.more.dispatch('click');
  equal(reduce.articleAnimations(), 0, 'Reduced-motion skips article reveal transition');
  equal(reduce.article.scrolls[0].behavior, 'auto', 'Reduced-motion collapse never smooth-scrolls');
  check(!reduce.more.classList.contains('is-hinting'), 'Reduced-motion disables read-more cue');
  const changing = fixture(); changing.flush(); changing.more.dispatch('click'); changing.motion.matches = true; changing.motion.dispatch('change', {matches:true});
  check(changing.cancelled() > 0, 'Changing motion preference cancels active reveal animation');
  const waiting = fixture(); waiting.motion.matches = true;
  waiting.observers.find(item => item.targets.includes(waiting.more)).emit([[waiting.more, true]]);
  check(!waiting.more.classList.contains('is-hinting'), 'Late IO hint does not ignore changed motion preference');

  const broken = fixture(); broken.portrait.dispatch('error');
  check(broken.portrait.hidden && broken.portraitHolder.classList.contains('is-placeholder'), 'Broken portrait reveals local placeholder');
  broken.photos[1].dispatch('error');
  check(broken.buttons[1].hidden && broken.figures[1].hidden, 'Broken gallery thumbnail hides only its own visual item');
  broken.orphan.dispatch('error'); check(!broken.orphan.hidden, 'Orphan thumbnail error cannot throw on absent button/figure');

  const photos = fixture(); photos.flush(); photos.buttons[2].dispatch('click');
  check(photos.dialog.open && photos.body.style.overflow === 'hidden', 'Gallery opens native modal and locks background');
  equal(photos.viewer.count.textContent, '3 / 4', 'Clicked gallery button keeps actual array position despite nonconsecutive data indices');
  equal(photos.viewer.caption.textContent, 'Room 2', 'Stored caption is visible as plain text');
  equal(photos.viewer.close.focuses[0].preventScroll, true, 'Opening modal moves focus to close without scrolling');
  await settle(photos.requests.at(-1));
  equal(photos.viewer['viewer-image'].src, 'https://images.example.org/doctor-2.webp', 'Real clicked photo source is shown');
  equal(photos.viewer['viewer-image'].alt, 'Actual room 2', 'Stored alt is retained separately from caption');
  check(!photos.viewer['viewer-image'].hidden && photos.viewer.error.hidden, 'Successful load reveals photo and clears failure status');
  equal(photos.dialog.getAttribute('aria-busy'), 'false', 'Successful load clears busy state');
  equal(photos.imageAnimations(), 1, 'Loaded photo receives a restrained transition');
  photos.viewer.next.dispatch('click'); await settle(photos.requests.at(-1));
  photos.viewer.next.dispatch('click'); await settle(photos.requests.at(-1));
  equal(photos.viewer.count.textContent, '1 / 4', 'Forward navigation wraps');
  photos.viewer.prev.dispatch('click'); await settle(photos.requests.at(-1));
  equal(photos.viewer.count.textContent, '4 / 4', 'Negative navigation wraps correctly');
  const key = photos.dialog.dispatch('keydown', {key:'ArrowRight'}); await settle(photos.requests.at(-1));
  check(key.defaultPrevented && photos.viewer.count.textContent === '1 / 4', 'Modal arrow keys navigate without page scrolling');
  const img = photos.viewer['viewer-image'];
  img.dispatch('touchstart', {touches:[{clientX:180,clientY:40}]});
  img.dispatch('touchend', {changedTouches:[{clientX:90,clientY:45}]}); await settle(photos.requests.at(-1));
  equal(photos.viewer.count.textContent, '2 / 4', 'Horizontal touch swipe navigates');
  const beforeVertical = photos.requests.length;
  img.dispatch('touchstart', {touches:[{clientX:100,clientY:30}]});
  img.dispatch('touchend', {changedTouches:[{clientX:110,clientY:180}]});
  equal(photos.requests.length, beforeVertical, 'Vertical touch gesture does not change photos');
  img.dispatch('touchstart', {touches:[{clientX:180,clientY:40}]}); img.dispatch('touchcancel');
  img.dispatch('touchend', {changedTouches:[{clientX:90,clientY:45}]});
  equal(photos.requests.length, beforeVertical, 'Cancelled touch does not later navigate');
  photos.viewer.next.dispatch('click'); const stale = photos.requests.at(-1);
  photos.viewer.next.dispatch('click'); const newest = photos.requests.at(-1);
  await settle(stale, 'error');
  check(photos.viewer.error.hidden, 'Late stale error cannot replace a newer photo with an error');
  await settle(newest); equal(img.src, newest.src, 'Newest request alone commits the viewer image');
  photos.viewer.next.dispatch('click'); await settle(photos.requests.at(-1), 'error');
  check(img.hidden && !photos.viewer.error.hidden, 'Real photo failure shows translated inline status without stale bitmap');
  equal(photos.dialog.getAttribute('aria-busy'), 'false', 'Photo failure clears busy state');
  check(photos.buttons.every(button => !button.hidden), 'Viewer failure never deletes or renumbers gallery buttons');
  photos.viewer.next.dispatch('click'); const pending = photos.requests.at(-1);
  photos.viewer.close.dispatch('click');
  check(!photos.dialog.open && photos.body.style.overflow === 'auto', 'Native close restores exact prior body overflow');
  equal(photos.buttons[2].focuses.at(-1).preventScroll, true, 'Close restores focus to original gallery trigger without scrolling');
  equal(img.src, '', 'Close releases viewer image source');
  await settle(pending); check(img.hidden, 'Load after close cannot reveal a stale viewer image');
  const closedRequestCount = photos.requests.length;
  photos.dialog.dispatch('keydown', {key:'ArrowRight'});
  equal(photos.requests.length, closedRequestCount, 'Closed modal ignores arrow keys');
  photos.buttons[0].dispatch('click'); await settle(photos.requests.at(-1));
  photos.motion.matches = true; const animations = photos.imageAnimations();
  photos.viewer.next.dispatch('click'); await settle(photos.requests.at(-1));
  equal(photos.imageAnimations(), animations, 'Photo transition respects updated reduced-motion preference');
  photos.dialog.close(); check(photos.body.style.overflow === 'auto', 'Repeated modal sessions do not retain overflow lock');

  const sparse = fixture({singleImage:true,cachedImage:true,noDecode:true,noGetAnimations:true,noAnimation:true}); sparse.flush(); sparse.buttons[0].dispatch('click');
  await Promise.resolve(); await Promise.resolve();
  check(sparse.viewer.prev.hidden && sparse.viewer.next.hidden && sparse.viewer.prev.disabled, 'Single-photo viewer hides/disables redundant navigation');
  check(!sparse.viewer['viewer-image'].hidden, 'Cached image works without decode/animation APIs');
  const excluded = fixture(); excluded.photos[0].dispatch('error'); excluded.buttons[2].dispatch('click');
  equal(excluded.viewer.count.textContent, '2 / 3', 'Known failed thumbnail is excluded without using obsolete numeric data indices');
  const fallback = fixture({missingCaption:true,missingAlt:true}); fallback.buttons[0].dispatch('click'); await settle(fallback.requests.at(-1));
  equal(fallback.viewer.caption.textContent, 'Legacy alt 0', 'Legacy gallery without metadata has meaningful alt caption fallback');
  equal(fallback.viewer['viewer-image'].alt, 'Legacy alt 0', 'Legacy gallery alt is retained');
  const citationOnly = fixture({emptyCaption:true}); citationOnly.buttons[0].dispatch('click');
  equal(citationOnly.viewer.caption.textContent, 'Actual room 0', 'Explicit empty caption falls back to meaningful alt, not figure citation links');

  for (const options of [{noDialog:true},{missingDialog:true},{noImage:true},{missingPart:'caption'},{missingPart:'error'},{modalThrows:true},{emptyGallery:true}]) {
    const basic = fixture(options); basic.flush(); basic.buttons[0]?.dispatch('click');
    check(!basic.dialog.open && basic.body.style.overflow === 'auto', 'Unsupported/sparse viewer never locks page: ' + JSON.stringify(options));
    basic.click(basic.mobileLinks[1]); basic.flush();
    equal(basic.sections[1].querySelector('h2,h3').focuses.at(-1).preventScroll, true, 'Navigation still works without viewer support');
    basic.more.dispatch('click'); check(!basic.article.classList.contains('is-collapsed'), 'Read-more still works without viewer support');
  }
  for (const options of [{noHeader:true},{noIO:true,noRO:true},{noRAF:true,noMatchMedia:true,noFonts:true},{noArticle:true,noReadMore:true},{missingPhoto:true}]) {
    const basic = fixture(options); basic.flush();
    check(basic.root.style.values.has('--dp-header-height'), 'Optional browser API absence keeps safe header CSS variable');
    basic.click(basic.desktopLinks[0]); basic.flush();
    equal(basic.sections[0].querySelector('h2,h3').focuses.at(-1).preventScroll, true, 'Progressive navigation survives optional API absence');
  }
  const absent = fixture({noRoot:true}); equal(absent.frames.size, 0, 'Other pages are untouched');
  check(!/\b(?:fetch|XMLHttpRequest)\s*\(/.test(source) && !/(?:window|document|body)\.scroll(?:To|IntoView)\s*\(/.test(source), 'Enhancement never fetches service data or forces document scroll');
  console.log('Doctor profile JavaScript: ' + checks + ' checks passed. Mock DOM only; no browser, DB or network.');
})().catch(error => { console.error(error); process.exitCode = 1; });
