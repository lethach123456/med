'use strict';

// Deterministic DOM/Image/observer fixtures only. No browser, PHP, DB or network.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname, '../assets/js/toplist-doctor-detail.js'), 'utf8');
let checks = 0;
const check = (value, label) => { checks++; assert.ok(value, label); };
const equal = (actual, expected, label) => { checks++; assert.equal(actual, expected, label); };

class ClassList {
  constructor() { this.values = new Set(); }
  add(value) { this.values.add(value); }
  remove(value) { this.values.delete(value); }
  contains(value) { return this.values.has(value); }
}
class Element {
  constructor(tag = 'div', attributes = {}) {
    this.tagName = tag.toUpperCase(); this.attributes = new Map(); this.dataset = {};
    this.children = []; this.parentElement = null; this.listeners = new Map();
    this.classList = new ClassList(); this.hidden = false; this.disabled = false; this.open = false;
    this.textContent = ''; this.focuses = []; this.complete = false; this.naturalWidth = 0;
    this.style = {values: new Map(), setProperty(name, value) { this.values.set(name, value); }};
    Object.entries(attributes).forEach(([key, value]) => this.setAttribute(key, value));
  }
  get id() { return this.getAttribute('id') || ''; }
  get hash() { const href = this.getAttribute('href') || ''; return href.startsWith('#') ? href : ''; }
  get src() { return this.getAttribute('src') || ''; }
  set src(value) { this.setAttribute('src', value); }
  append(child) { child.parentElement = this; this.children.push(child); }
  contains(node) { return node === this || this.children.some(child => child.contains(node)); }
  hasAttribute(name) { return this.attributes.has(name); }
  getAttribute(name) { return this.attributes.get(name) ?? null; }
  setAttribute(name, value) {
    this.attributes.set(name, String(value));
    if (name.startsWith('data-')) this.dataset[name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] = String(value);
  }
  removeAttribute(name) { this.attributes.delete(name); }
  focus(options) { this.focuses.push(options); }
  cloneNode() { return new Element(this.tagName.toLowerCase()); }
  querySelector(selector) {
    if (selector === '.td-photo-fallback') return this.children.find(child => child.className === 'td-photo-fallback') || null;
    return this.queries?.get(selector) ?? null;
  }
  querySelectorAll(selector) { return this.lists?.get(selector) ?? []; }
  closest(selector) {
    for (let current = this; current; current = current.parentElement) {
      if (selector === 'a[href^="#rank-"]' && current.tagName === 'A' && /^#rank-/.test(current.hash)) return current;
      if (selector === 'a[href^="#"]' && current.tagName === 'A' && current.hash.startsWith('#')) return current;
      if (selector === '[data-td-photo]' && current.hasAttribute('data-td-photo')) return current;
      if (selector === '[data-td-member]' && current.hasAttribute('data-td-member')) return current;
      if (selector === '.td-summary-avatars' && current.className === 'td-summary-avatars') return current;
    }
    return null;
  }
  addEventListener(name, callback) {
    if (!this.listeners.has(name)) this.listeners.set(name, []);
    this.listeners.get(name).push(callback);
  }
  dispatch(name, values = {}) {
    const event = {target: this, button: 0, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...values};
    (this.listeners.get(name) || []).forEach(callback => callback(event));
    return event;
  }
}

function fixture(options = {}) {
  const root = new Element('main', {'data-locale': options.locale || 'vi'});
  const header = new Element('header');
  let headerHeight = options.headerHeight ?? 84;
  header.getBoundingClientRect = () => ({height: headerHeight});
  const menu = new Element('details'); menu.open = true; root.append(menu);
  const sidebar = new Element('nav'); root.append(sidebar);
  const members = [], mobileLinks = [], desktopLinks = [], photoLinks = [], managedImages = [];
  for (let rank = 1; rank <= 3; rank++) {
    const member = new Element('article', {id: 'rank-' + rank, 'data-td-member': rank});
    member.top = [160, 800, 1400][rank - 1];
    member.getBoundingClientRect = () => ({top: member.top});
    const heading = new Element('h2'); member.append(heading); member.queries = new Map([['h2', heading]]);
    const cover = new Element('a', {href: 'https://images.example.org/' + rank + '-0.jpg', 'data-td-photo': 0});
    const coverImage = new Element('img', {'data-td-image': ''}); cover.append(coverImage); member.append(cover);
    cover.queries = new Map(); managedImages.push(coverImage); photoLinks.push(cover);
    for (let photo = 1; photo <= 4; photo++) {
      const link = new Element('a', {href: 'https://images.example.org/' + rank + '-' + photo + '.jpg', 'data-td-photo': photo});
      member.append(link); photoLinks.push(link);
    }
    root.append(member); members.push(member);
    const mobile = new Element('a', {href: '#rank-' + rank}), desktop = new Element('a', {href: '#rank-' + rank});
    menu.append(mobile); sidebar.append(desktop); mobileLinks.push(mobile); desktopLinks.push(desktop);
  }
  const comparisonLink = new Element('a', {href: '#rank-2'}); root.append(comparisonLink);
  const sectionTargets = new Map(), sectionLinks = [];
  for (const id of ['toplist-intro', 'toplist-comparison']) {
    const section = new Element('section', {id}); const heading = new Element('h2'); section.append(heading);
    section.queries = new Map([['h2', heading]]); root.append(section); sectionTargets.set(id, section);
    const link = new Element('a', {href: '#' + id}); menu.append(link); sectionLinks.push(link);
  }
  const article = new Element('p'), articleImage = new Element('img'); article.append(articleImage); root.append(article);
  managedImages.push(articleImage);
  const avatars = new Element('div'); avatars.className = 'td-summary-avatars';
  const avatarImage = new Element('img', {'data-td-image': ''}); avatars.append(avatarImage); root.append(avatars); managedImages.push(avatarImage);
  const icon = new Element('svg');
  const dialog = new Element('dialog'); root.append(dialog);
  const names = ['stage', 'image', 'caption', 'count', 'error', 'prev', 'next', 'close'];
  const viewer = Object.fromEntries(names.map(name => [name, new Element(name === 'image' ? 'img' : 'div')]));
  Object.values(viewer).forEach(element => dialog.append(element));
  dialog.queries = new Map(names.map(name => ['.td-viewer-' + name, options.missingPart === name ? null : viewer[name]]));
  dialog.showModal = options.noDialog ? undefined : () => { if (options.modalThrows) throw new Error('Unavailable'); dialog.open = true; };
  dialog.close = () => { dialog.open = false; dialog.dispatch('close'); };
  let animations = 0, cancelled = 0;
  if (!options.noAnimation) viewer.image.animate = () => { animations++; };
  if (!options.noGetAnimations) viewer.image.getAnimations = () => [{cancel() { cancelled++; }}];
  root.queries = new Map([['.td-cover-placeholder svg', options.coverPlaceholderIcon ? icon : null], ['.td-eyebrow svg', icon], ['.td-mobile-toc', menu], ['.td-lightbox', options.missingDialog ? null : dialog]]);
  root.lists = new Map([
    ['[data-td-member]', members], ['nav.td-toc a[href^="#rank-"]', [...mobileLinks, ...desktopLinks]],
    ['[data-td-image],.td-article-content img,.td-intro img', managedImages],
  ]);
  const galleries = Object.fromEntries(members.map((member, rank) => [String(rank + 1), {name: 'Provider ' + (rank + 1),
    images: Array.from({length: 7}, (_, index) => ({url: 'https://images.example.org/' + (rank + 1) + '-' + index + '.jpg', caption: 'Room ' + index, alt: 'Actual photo ' + index}))}]));
  if (options.singleImage) galleries['1'].images = [galleries['1'].images[0]];
  if (options.invalidImages) galleries['1'].images = [{caption: 'Missing URL'}];
  if (options.noLabels) delete galleries['1'].images[0].caption;
  const data = new Element('script'); data.textContent = options.badJSON ? '{broken' : JSON.stringify({galleries, ...(options.noLabels ? {} : {labels: {photo: 'Ảnh'}})});
  const body = new Element('body'); body.append(root);
  const document = {
    body,
    querySelector: selector => selector === '.doctor-toplist' ? options.noRoot ? null : root : selector === '.medical-header' && !options.noHeader ? header : null,
    getElementById: id => id === 'tdGalleryData' ? options.missingData ? null : data : members.find(member => member.id === id) || sectionTargets.get(id) || null,
    createElement: tag => new Element(tag),
  };
  const frames = new Map(), timers = new Map(); let nextFrame = 0, nextTimer = 0;
  const window = new Element('window'); window.location = {hash: options.hash || ''};
  window.setTimeout = callback => { timers.set(++nextTimer, callback); return nextTimer; };
  if (!options.noRAF) window.requestAnimationFrame = callback => { frames.set(++nextFrame, callback); return nextFrame; };
  const motion = {matches: Boolean(options.reduced)};
  if (!options.noMatchMedia) window.matchMedia = () => motion;
  const observers = [], resizers = [];
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
  const requests = [];
  const MockImage = class extends Element {
    constructor() {
      super('img'); requests.push(this);
      this.complete = Boolean(options.cachedImage); this.naturalWidth = options.cachedImage ? 700 : 0;
      if (!options.noDecode) this.decode = () => options.decodeReject ? Promise.reject(new Error('Decode unavailable')) : Promise.resolve();
    }
  };
  const context = {window, document, Image: options.noImage ? undefined : MockImage, console};
  vm.runInNewContext(source, context, {filename: 'toplist-doctor-detail.js'});
  const flush = () => {
    for (let iteration = 0; iteration < 20 && (frames.size || timers.size); iteration++) {
      const callbacks = [...frames.values(), ...timers.values()]; frames.clear(); timers.clear(); callbacks.forEach(callback => callback());
    }
    assert.equal(frames.size + timers.size, 0, 'All scheduled fixture frames settle');
  };
  const click = (target, values = {}) => root.dispatch('click', {target, ...values});
  return {root, header, setHeaderHeight: height => { headerHeight = height; }, menu, members, mobileLinks, desktopLinks,
    comparisonLink, sectionTargets, sectionLinks, photoLinks, managedImages, article, articleImage, avatars, avatarImage, dialog, viewer, body, window, motion, observers, resizers,
    requests, frames, timers, flush, click, animations: () => animations, cancelled: () => cancelled};
}
const load = async (request, name = 'load') => {
  request.complete = true; request.naturalWidth = name === 'load' ? 700 : 0; request.dispatch(name);
  await Promise.resolve(); await Promise.resolve();
};

(async () => {
  const navigation = fixture({hash: '#rank-2'});
  equal(navigation.root.style.values.get('--td-header-offset'), '100px', 'Offset fallback exists before measured frame');
  equal(navigation.frames.size, 1, 'Startup measurement is coalesced into one animation frame');
  navigation.flush();
  equal(navigation.root.style.values.get('--td-header-offset'), '96px', 'Shared header height plus clearance is measured');
  equal(navigation.resizers[0].target, navigation.header, 'ResizeObserver watches the shared site header');
  equal(navigation.observers[0].configuration.rootMargin, '-96px 0px -55% 0px', 'Observer clearance matches measured sticky offset');
  equal(navigation.observers[0].targets.length, 3, 'Every ranked member is observed');
  equal(navigation.requests.length, 0, 'Navigation startup does not preload gallery photos');
  for (const links of [navigation.mobileLinks, navigation.desktopLinks]) equal(links[1].getAttribute('aria-current'), 'location', 'Initial hash synchronizes both native TOCs');
  const observer = navigation.observers[0];
  observer.emit([[navigation.members[0], true]]);
  observer.emit([[navigation.members[1], true]]);
  for (const links of [navigation.mobileLinks, navigation.desktopLinks]) equal(links[0].getAttribute('aria-current'), 'location', 'Unchanged earlier visible card remains selected');
  observer.emit([[navigation.members[0], false]]);
  for (const links of [navigation.mobileLinks, navigation.desktopLinks]) equal(links[1].getAttribute('aria-current'), 'location', 'Exiting card selects another retained visible card');
  equal(navigation.comparisonLink.getAttribute('aria-current'), null, 'Comparison table/card link never gains navigation current state');
  navigation.members[1].top = 600; navigation.members[2].top = 420;
  observer.emit([[navigation.members[2], true]]);
  equal(navigation.desktopLinks[2].getAttribute('aria-current'), 'location', 'Selection measures current geometry across all visible members');
  navigation.setHeaderHeight(120);
  navigation.resizers[0].callback(); navigation.window.dispatch('resize'); navigation.window.dispatch('resize');
  equal(navigation.frames.size, 1, 'Header/viewport resize callbacks share one scheduled measurement');
  navigation.flush();
  equal(navigation.root.style.values.get('--td-header-offset'), '132px', 'Updated header height changes root CSS variable');
  check(observer.disconnected, 'Changing header clearance replaces the old observer');
  observer.emit([[navigation.members[0], true]]);
  equal(navigation.desktopLinks[2].getAttribute('aria-current'), 'location', 'Late disconnected observer cannot overwrite current state');
  for (const [height, expected] of [[NaN, '100px'], [0, '100px'], [Infinity, '100px'], [10000, '252px']]) {
    navigation.setHeaderHeight(height); navigation.window.dispatch('resize'); navigation.flush();
    equal(navigation.root.style.values.get('--td-header-offset'), expected, 'Nonfinite/extreme measured height is bounded: ' + height);
  }
  const click = navigation.click(navigation.mobileLinks[2]);
  check(!click.defaultPrevented, 'Rank anchors retain browser hash/history/scroll behavior');
  check(!navigation.menu.open, 'Mobile native details closes before default anchor navigation');
  equal(navigation.members[2].querySelector('h2').focuses.length, 0, 'Heading focus waits until after native hash navigation');
  navigation.flush();
  equal(navigation.members[2].querySelector('h2').focuses[0].preventScroll, true, 'Focused target heading does not cause another scroll');
  equal(navigation.members[2].querySelector('h2').getAttribute('tabindex'), '-1', 'Rank heading is programmatically focusable');
  for (const modified of [{ctrlKey:true}, {metaKey:true}, {shiftKey:true}, {altKey:true}, {button:1}, {defaultPrevented:true}]) {
    navigation.menu.open = true;
    const before = navigation.members[0].querySelector('h2').focuses.length;
    navigation.click(navigation.mobileLinks[0], modified); navigation.flush();
    check(navigation.menu.open, 'Modified rank click does not close menu');
    equal(navigation.members[0].querySelector('h2').focuses.length, before, 'Modified rank click does not steal focus');
  }
  navigation.window.location.hash = '#rank-1'; navigation.window.dispatch('hashchange');
  equal(navigation.desktopLinks[0].getAttribute('aria-current'), 'location', 'Native history/hash changes synchronize navigation');
  for (const link of navigation.sectionLinks) {
    navigation.menu.open = true;
    const event = navigation.click(link);
    check(!event.defaultPrevented && !navigation.menu.open, 'Intro/comparison anchor closes sticky mobile menu without overriding native hash behavior');
    const heading = navigation.sectionTargets.get(link.hash.slice(1)).querySelector('h2');
    equal(heading.focuses.length, 0, 'Intro/comparison heading focus waits for native anchor navigation');
    navigation.flush();
    equal(heading.focuses.at(-1).preventScroll, true, 'Intro/comparison destination heading gains focus without a second scroll');
    navigation.menu.open = true;
    navigation.click(link, {ctrlKey: true}); navigation.flush();
    check(navigation.menu.open && heading.focuses.length === 1, 'Modified intro/comparison click keeps native new-tab behavior and does not steal focus');
  }

  const broken = fixture(); broken.flush();
  const managed = broken.managedImages[0]; managed.dispatch('error');
  check(managed.hidden && managed.parentElement.children.some(child => child.className === 'td-photo-fallback'), 'Managed cover failure gets a decorative local placeholder');
  check(managed.parentElement.querySelector('.td-photo-fallback').children[0]?.tagName === 'SVG', 'Cover placeholder uses an available eyebrow SVG when kind labels no longer contain an icon');
  const fallbackCount = managed.parentElement.children.length; managed.dispatch('error');
  // Browser once-listener prevents this; the holder guard also prevents duplicates.
  equal(managed.parentElement.children.length, fallbackCount, 'Repeated image errors do not stack placeholders');
  broken.articleImage.dispatch('error');
  check(broken.articleImage.hidden, 'Broken article photo is hidden');
  equal(broken.article.children.length, 1, 'Inline article paragraph never receives an overlay cover fallback');
  broken.avatarImage.dispatch('error');
  check(broken.avatarImage.hidden, 'Broken summary avatar is hidden');
  equal(broken.avatars.children.length, 1, 'Avatar stack does not get an absolute fallback covering neighboring avatars');
  const coverFallback = fixture({coverPlaceholderIcon: true}); coverFallback.managedImages[0].dispatch('error');
  check(coverFallback.managedImages[0].parentElement.querySelector('.td-photo-fallback').children[0]?.tagName === 'SVG', 'Available cover placeholder SVG also supports managed image recovery');

  const viewer = fixture(); viewer.flush();
  const photoClick = viewer.click(viewer.photoLinks[4]);
  check(photoClick.defaultPrevented && viewer.dialog.open, 'Supported viewer enhances an ordinary photo link');
  check(viewer.body.classList.contains('td-lightbox-open'), 'Open lightbox locks background through its existing class');
  equal(viewer.viewer.count.textContent, '5 / 7', 'Visible thumbnail retains original full-gallery index');
  equal(viewer.viewer.caption.textContent, 'Room 4', 'Stored caption is rendered as text');
  await load(viewer.requests.at(-1));
  equal(viewer.viewer.image.src, 'https://images.example.org/1-4.jpg', 'Requested real photo is displayed');
  equal(viewer.viewer.image.alt, 'Actual photo 4', 'Stored image alt is retained');
  check(!viewer.viewer.image.hidden && viewer.viewer.error.hidden, 'Successful load reveals photo and clears error');
  equal(viewer.viewer.stage.getAttribute('aria-busy'), 'false', 'Successful load clears busy state');
  equal(viewer.animations(), 1, 'One restrained transition follows a loaded photo');
  viewer.viewer.next.dispatch('click'); await load(viewer.requests.at(-1));
  equal(viewer.viewer.count.textContent, '6 / 7', 'Images beyond visible thumbnails remain reachable');
  viewer.viewer.next.dispatch('click'); await load(viewer.requests.at(-1));
  viewer.viewer.next.dispatch('click'); await load(viewer.requests.at(-1));
  equal(viewer.viewer.count.textContent, '1 / 7', 'Full gallery wraps forward');
  viewer.viewer.prev.dispatch('click'); await load(viewer.requests.at(-1));
  equal(viewer.viewer.count.textContent, '7 / 7', 'Full gallery wraps backward');
  const key = viewer.dialog.dispatch('keydown', {key:'ArrowRight'}); await load(viewer.requests.at(-1));
  check(key.defaultPrevented && viewer.viewer.count.textContent === '1 / 7', 'Lightbox arrow key navigates without page scroll');
  viewer.viewer.stage.dispatch('pointerdown', {pointerType:'touch',clientX:180,clientY:50});
  viewer.viewer.stage.dispatch('pointerup', {clientX:100,clientY:55}); await load(viewer.requests.at(-1));
  equal(viewer.viewer.count.textContent, '2 / 7', 'Horizontal touch swipe navigates photos');
  const beforeVertical = viewer.requests.length;
  viewer.viewer.stage.dispatch('pointerdown', {pointerType:'touch',clientX:100,clientY:10});
  viewer.viewer.stage.dispatch('pointerup', {clientX:105,clientY:150});
  equal(viewer.requests.length, beforeVertical, 'Vertical gesture never changes photos');
  viewer.viewer.next.dispatch('click'); const stale = viewer.requests.at(-1);
  viewer.viewer.next.dispatch('click'); const current = viewer.requests.at(-1);
  await load(stale); check(viewer.viewer.image.hidden, 'Late old request cannot reveal the wrong photo');
  await load(current); equal(viewer.viewer.image.src, current.src, 'Latest request alone commits its photo');
  viewer.viewer.next.dispatch('click'); await load(viewer.requests.at(-1), 'error');
  check(viewer.viewer.image.hidden && !viewer.viewer.error.hidden, 'Failed load exposes existing translated error status, not stale bitmap');
  equal(viewer.viewer.stage.getAttribute('aria-busy'), 'false', 'Failure clears busy state');
  viewer.viewer.next.dispatch('click'); const closingRequest = viewer.requests.at(-1);
  viewer.viewer.close.dispatch('click');
  check(!viewer.dialog.open && !viewer.body.classList.contains('td-lightbox-open'), 'Native close restores background state');
  equal(viewer.photoLinks[4].focuses.at(-1).preventScroll, true, 'Viewer restores focus to original thumbnail without scrolling');
  equal(viewer.viewer.image.src, '', 'Closed viewer releases its photo source');
  await load(closingRequest); check(viewer.viewer.image.hidden, 'Image arriving after close cannot reopen or reveal viewer');
  const focusedBefore = viewer.photoLinks[0].focuses.length;
  viewer.click(viewer.photoLinks[0], {ctrlKey:true});
  check(!viewer.dialog.open && viewer.photoLinks[0].focuses.length === focusedBefore, 'Modified photo link remains a normal native link');
  viewer.motion.matches = true; viewer.click(viewer.photoLinks[0]); await load(viewer.requests.at(-1));
  const animationCount = viewer.animations();
  viewer.viewer.next.dispatch('click'); await load(viewer.requests.at(-1));
  equal(viewer.animations(), animationCount, 'Reduced-motion preference is respected even after it changes');

  const single = fixture({singleImage:true, cachedImage:true, noDecode:true, noGetAnimations:true}); single.flush();
  single.click(single.photoLinks[0]); await Promise.resolve(); await Promise.resolve();
  check(single.viewer.prev.disabled && single.viewer.next.disabled, 'Single-photo viewer disables redundant controls');
  check(!single.viewer.image.hidden, 'Cached photo and missing decode/getAnimations still work');
  const noLabels = fixture({noLabels:true, locale:'en'}); noLabels.flush(); noLabels.click(noLabels.photoLinks[0]);
  equal(noLabels.viewer.caption.textContent, 'Provider 1 · Photo 1', 'Missing labels safely fall back in actual page language');

  for (const options of [{noDialog:true}, {missingDialog:true}, {noImage:true}, {missingData:true}, {badJSON:true}, {missingPart:'caption'}, {invalidImages:true}, {modalThrows:true}]) {
    const basic = fixture(options); basic.flush(); const event = basic.click(basic.photoLinks[0]);
    check(!event.defaultPrevented && !basic.dialog.open, 'Unavailable viewer leaves a working native image link: ' + JSON.stringify(options));
    const navEvent = basic.click(basic.mobileLinks[1]); basic.flush();
    check(!navEvent.defaultPrevented && !basic.menu.open, 'Navigation enhancement survives unavailable viewer: ' + JSON.stringify(options));
    equal(basic.members[1].querySelector('h2').focuses.at(-1).preventScroll, true, 'Heading focus remains independent of lightbox support');
  }
  for (const options of [{noHeader:true}, {noIO:true,noRO:true}, {noRAF:true,noMatchMedia:true,noDialog:true}]) {
    const basic = fixture(options); basic.flush();
    check(basic.root.style.values.has('--td-header-offset'), 'Safe sticky clearance survives optional API absence');
    basic.click(basic.mobileLinks[0]); basic.flush();
    check(!basic.menu.open && basic.members[0].querySelector('h2').focuses.length === 1, 'Progressive navigation works without optional observers/RAF/motion APIs');
  }
  const absent = fixture({noRoot:true}); equal(absent.frames.size, 0, 'Pages without the detail root are untouched');
  check(!/\b(?:fetch|XMLHttpRequest|scrollIntoView|scrollTo)\s*\(/.test(source), 'Enhancement never fetches data or forces scroll positions');
  console.log('Doctor Toplist JavaScript: ' + checks + ' checks passed. Mock DOM only; no browser, DB or network.');
})().catch(error => { console.error(error); process.exitCode = 1; });
