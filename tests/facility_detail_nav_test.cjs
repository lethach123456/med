'use strict';

// Deterministic DOM fixtures only: no browser, PHP bootstrap, network or DB.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const filename = path.resolve(__dirname, '../assets/js/facility-detail-nav.js');
const source = fs.readFileSync(filename, 'utf8');
let checks = 0;
const equal = (actual, expected, message) => { checks++; assert.equal(actual, expected, message); };
const check = (condition, message) => { checks++; assert.ok(condition, message); };

class ClassList {
  constructor(...values) { this.values = new Set(values); }
  contains(value) { return this.values.has(value); }
  add(value) { this.values.add(value); }
  remove(value) { this.values.delete(value); }
  toggle(value, force = !this.contains(value)) { force ? this.add(value) : this.remove(value); return force; }
}
class Element {
  constructor(attributes = {}) {
    this.attributes = new Map(Object.entries(attributes));
    this.classList = new ClassList();
    this.hidden = false;
    this.children = [];
    this.parentNode = null;
    this.listeners = new Map();
    this.style = {
      values: new Map(), priorities: new Map(),
      getPropertyValue(name) { return this.values.get(name) || ''; },
      getPropertyPriority(name) { return this.priorities.get(name) || ''; },
      setProperty(name, value, priority = '') { this.values.set(name, value); this.priorities.set(name, priority); },
      removeProperty(name) { this.values.delete(name); this.priorities.delete(name); },
    };
  }
  insertBefore(child, reference) {
    if (child.parentNode) child.parentNode.removeChild(child);
    const index = reference === null ? this.children.length : this.children.indexOf(reference);
    assert.ok(index >= 0, 'insertBefore reference is a direct child');
    this.children.splice(index, 0, child);
    child.parentNode = this;
    return child;
  }
  removeChild(child) {
    const index = this.children.indexOf(child);
    assert.ok(index >= 0, 'removeChild target is a direct child');
    this.children.splice(index, 1);
    child.parentNode = null;
  }
  append(child) { this.insertBefore(child, null); }
  contains(target) { return this === target || this.children.some(child => child.contains?.(target)); }
  getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
  setAttribute(name, value) { this.attributes.set(name, String(value)); }
  removeAttribute(name) { this.attributes.delete(name); }
  addEventListener(name, listener, options) {
    if (!this.listeners.has(name)) this.listeners.set(name, []);
    this.listeners.get(name).push({listener, options});
  }
  dispatch(name, values = {}) {
    const event = {type: name, target: this, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...values};
    (this.listeners.get(name) || []).forEach(({listener}) => listener(event));
    return event;
  }
}

function fixture(options = {}) {
  const root = new Element();
  const container = new Element();
  const layout = new Element();
  const main = new Element();
  const nav = new Element();
  const previous = new Element();
  const following = new Element();
  const header = new Element();
  root.append(container);
  container.append(layout);
  layout.append(main);
  main.append(previous);
  main.append(nav);
  main.append(following);
  const ids = ['gioi-thieu', 'dich-vu', 'bang-gia', 'danh-gia', 'dat-lich'];
  const links = ids.map((id, index) => {
    const link = new Element({href: `#${id}`});
    link.index = index;
    link.closest = () => link;
    nav.append(link);
    return link;
  });
  nav.querySelectorAll = () => links;
  root.querySelector = selector => selector === '.detail-nav' ? options.missingNav ? null : nav
    : selector === '.facility-reading-layout' ? options.missingLayout ? null : layout : null;
  const targets = new Map();
  const geometry = {headerHeight: options.headerHeight ?? 64, navHeight: options.navHeight ?? 50, pagePadding: options.pagePadding ?? '64px', scroll: options.scroll ?? 0, navLeft: 10, navWidth: 280};
  ids.forEach((id, index) => {
    if (options.missingPrice && id === 'bang-gia') return;
    const target = new Element({id});
    target.top = [200, 700, 1050, 1600, 2100][index];
    target.getBoundingClientRect = () => ({top: target.top - geometry.scroll});
    targets.set(id, target);
    if (index === 0) main.append(target);
    else if (index === 1 || index === 2) targets.get('gioi-thieu').append(target);
    else container.append(target);
  });
  if (options.outsideTarget) {
    const outside = targets.get(options.outsideTarget);
    outside.parentNode.removeChild(outside);
  }
  nav.scrollLeft = 0;
  nav.clientWidth = geometry.navWidth;
  nav.scrollWidth = 590;
  nav.scrollCalls = [];
  nav.scrollTo = settings => { nav.scrollCalls.push(settings); nav.scrollLeft = settings.left; };
  nav.getBoundingClientRect = () => ({left: geometry.navLeft, right: geometry.navLeft + geometry.navWidth, height: geometry.navHeight});
  links.forEach((link, index) => {
    link.getBoundingClientRect = () => ({left: geometry.navLeft + 8 + index * 118 - nav.scrollLeft, right: geometry.navLeft + 8 + index * 118 + 110 - nav.scrollLeft, width: 110});
  });
  header.getBoundingClientRect = () => ({height: geometry.headerHeight});
  if (options.initialActive !== undefined) {
    links[options.initialActive].classList.add('is-active');
    links[options.initialActive].setAttribute('aria-current', 'page');
  }
  if (options.initialHidden !== undefined) links[options.initialHidden].hidden = true;
  if (options.originalVariable) {
    root.style.setProperty('--facility-nav-header-height', '18px', 'important');
    root.style.setProperty('--facility-nav-page-padding', '20px', 'important');
  }
  const mobile = new Element();
  mobile.matches = options.mobile !== false;
  const motion = new Element();
  motion.matches = Boolean(options.reducedMotion);
  if (options.legacyMedia) {
    mobile.addListener = listener => mobile.listeners.set('change', [{listener}]);
    mobile.addEventListener = undefined;
  }
  const frames = new Map();
  let nextFrame = 1;
  const window = new Element();
  window.location = {hash: options.hash || ''};
  window.matchMedia = query => query === '(max-width:767px)' ? mobile : motion;
  window.getComputedStyle = element => {
    assert.equal(element, document.documentElement, 'Page padding measurement uses the html element');
    return {scrollPaddingTop: geometry.pagePadding};
  };
  window.requestAnimationFrame = callback => { const id = nextFrame++; frames.set(id, callback); return id; };
  window.cancelAnimationFrame = id => frames.delete(id);
  const observers = [];
  if (!options.missingResizeObserver) window.ResizeObserver = class {
    constructor(callback) { this.callback = callback; this.observed = new Set(); this.disconnected = false; observers.push(this); }
    observe(target) { this.observed.add(target); this.disconnected = false; }
    disconnect() { this.observed.clear(); this.disconnected = true; }
  };
  const fontCallbacks = [];
  const document = {
    documentElement: new Element(),
    querySelector: selector => selector === '.facility-detail.site-typo' ? options.missingRoot ? null : root
      : selector === '.medical-header' ? options.missingHeader ? null : header : null,
    createComment: () => new Element(),
    getElementById: id => targets.get(id) || null,
  };
  if (!options.missingFonts) document.fonts = {ready: {then: callback => fontCallbacks.push(callback)}};
  if (options.missingMatchMedia) window.matchMedia = undefined;
  if (options.missingScrollTo) nav.scrollTo = undefined;
  vm.runInNewContext(source, {document, window}, {filename});
  return {
    root, container, layout, main, nav, links, targets, previous, following, header, geometry, mobile, motion, window, observers, frames, fontCallbacks,
    flush() { const pending = [...frames.values()]; frames.clear(); pending.forEach(callback => callback()); },
    scroll(position) { geometry.scroll = position; window.dispatch('scroll'); },
    setMobile(matches) { mobile.matches = matches; mobile.dispatch('change', {matches}); },
    hash(value) { window.location.hash = value; window.dispatch('hashchange'); },
    click(index, values = {}) { return nav.dispatch('click', {target: links[index], button: 0, ...values}); },
    active() { return links.findIndex(link => link.classList.contains('is-active')); },
  };
}

new vm.Script(source, {filename});
checks++;
const mobile = fixture();
equal(mobile.nav.parentNode, mobile.container, 'Mobile nav shares the full reading/reviews/booking containing block');
equal(mobile.container.children.indexOf(mobile.nav) + 1, mobile.container.children.indexOf(mobile.layout), 'Mobile nav is immediately before the reading layout');
equal(mobile.main.children[0], mobile.previous, 'Portalling preserves earlier reading-main content');
equal(mobile.main.children[2], mobile.following, 'A marker retains the exact original navigation position');
equal(mobile.root.classList.contains('facility-nav-mobile'), true, 'Enhanced mobile layout gets a scoped state hook');
equal(mobile.root.style.getPropertyValue('--facility-nav-header-height'), '64px', 'Header offset uses the actual height');
equal(mobile.root.style.getPropertyValue('--facility-nav-height'), '50px', 'Anchor clearance uses the actual nav height');
equal(mobile.root.style.getPropertyValue('--facility-nav-page-padding'), '64px', 'Native html scroll padding is measured for anchor-offset compensation');
equal(mobile.active(), 0, 'Introduction is current before the first section reaches the strip');
equal(mobile.links[0].getAttribute('aria-current'), 'location', 'Current section is announced accessibly');
check(mobile.observers[0].observed.has(mobile.header) && mobile.observers[0].observed.has(mobile.nav), 'Both header and strip size changes are observed');
equal(mobile.window.listeners.get('scroll')[0].options.passive, true, 'Scroll observation never blocks document scrolling');

mobile.scroll(580);
equal(mobile.frames.size, 1, 'Scrolling schedules one animation-frame update');
mobile.window.dispatch('scroll');
mobile.window.dispatch('scroll');
equal(mobile.frames.size, 1, 'Repeated scroll events are throttled to a single frame');
mobile.flush();
equal(mobile.active(), 1, 'Nested service heading becomes current once its start passes the offset');
equal(mobile.links[0].getAttribute('aria-current'), null, 'Former current section has no stale aria-current');
mobile.scroll(930);
mobile.flush();
equal(mobile.active(), 2, 'Nested price heading wins over its introduction parent');
mobile.scroll(1480);
mobile.flush();
equal(mobile.active(), 3, 'Reviews remain reachable and current outside the reading grid');
mobile.scroll(1980);
mobile.flush();
equal(mobile.active(), 4, 'Booking remains reachable and current outside the reading grid');
mobile.scroll(580);
mobile.flush();
equal(mobile.active(), 1, 'Scrolling upward restores the earlier current section');
mobile.scroll(0);
mobile.flush();
equal(mobile.active(), 0, 'Scrolling above the content returns to introduction');

const resize = fixture();
resize.scroll(560);
resize.flush();
equal(resize.active(), 0, 'Service heading below the old threshold is not current');
resize.geometry.headerHeight = 90;
resize.geometry.navHeight = 60;
resize.observers[0].callback();
equal(resize.frames.size, 1, 'Resize observation batches measurement with scrollspy');
resize.flush();
equal(resize.root.style.getPropertyValue('--facility-nav-header-height'), '90px', 'Header resizing updates the sticky offset');
equal(resize.root.style.getPropertyValue('--facility-nav-height'), '60px', 'Strip resizing updates anchor clearance');
equal(resize.active(), 1, 'Scrollspy follows the updated header and strip threshold');
resize.geometry.pagePadding = '48.5px';
resize.geometry.headerHeight = 72;
resize.window.dispatch('resize');
resize.flush();
equal(resize.root.style.getPropertyValue('--facility-nav-header-height'), '72px', 'Window resizing also updates dimensions');
equal(resize.root.style.getPropertyValue('--facility-nav-page-padding'), '48.5px', 'Window resizing remeasures fractional html scroll padding');
resize.geometry.navHeight = 55;
resize.fontCallbacks.forEach(callback => callback());
resize.flush();
equal(resize.root.style.getPropertyValue('--facility-nav-height'), '55px', 'Font completion rechecks actual strip dimensions');
const fractional = fixture({headerHeight: 74, navHeight: 50});
fractional.scroll(700 - 136.05);
fractional.flush();
equal(fractional.active(), 1, 'Fractional native-anchor positions within 2px still activate their section');
for (const value of ['auto', '', 'NaN', 'Infinity']) {
  equal(fixture({pagePadding: value}).root.style.getPropertyValue('--facility-nav-page-padding'), '0px', `Unresolved html padding ${JSON.stringify(value)} uses a finite zero fallback`);
}

const horizontal = fixture();
equal(horizontal.nav.scrollCalls.length, 0, 'An already visible current link does not scroll the strip');
horizontal.click(4);
equal(horizontal.active(), 4, 'Native anchor clicks give immediate active feedback');
equal(horizontal.nav.scrollCalls.length, 1, 'A clipped current link is revealed horizontally');
equal(horizontal.nav.scrollCalls[0].behavior, 'smooth', 'Strip movement is gentle when motion is allowed');
equal(horizontal.nav.scrollCalls[0].left, 310, 'Strip scrolling is clamped to its scrollable range');
horizontal.click(4);
equal(horizontal.nav.scrollCalls.length, 1, 'Repeated current-link activation does not jitter the strip');
horizontal.click(3);
equal(horizontal.nav.scrollCalls.length, 1, 'Changing to an already visible link does not move the strip');
horizontal.motion.matches = true;
horizontal.click(0);
equal(horizontal.nav.scrollCalls.at(-1).behavior, 'auto', 'A live reduced-motion preference disables smooth strip movement');
const native = horizontal.click(2);
equal(native.defaultPrevented, false, 'Normal anchors retain their native URL, back, focus and scroll-margin behavior');
horizontal.click(1, {ctrlKey: true});
equal(horizontal.active(), 2, 'Modified clicks do not change current-section state');
horizontal.click(1, {button: 1});
equal(horizontal.active(), 2, 'Non-primary clicks do not change current-section state');
horizontal.click(1, {defaultPrevented: true});
equal(horizontal.active(), 2, 'A previously cancelled click has no active-state side effect');
horizontal.nav.dispatch('focusin', {target: horizontal.links[4]});
equal(horizontal.nav.scrollCalls.at(-1).behavior, 'auto', 'Keyboard focus can reveal an offscreen strip link without document scrolling');

const direct = fixture({hash: '#danh-gia'});
equal(direct.active(), 3, 'An initial hash selects its linked section');
direct.hash('#dich-vu');
equal(direct.active(), 1, 'Hash changes/back navigation synchronize the current link');
direct.hash('#dat-lich');
equal(direct.active(), 4, 'A booking hash selects the booking link');
direct.hash('#unknown');
equal(direct.active(), 0, 'Unrelated hashes fall back to actual section position');
direct.hash('#bad%ZZ');
equal(direct.active(), 0, 'Malformed hashes fail safely without breaking navigation');
const absent = fixture({missingPrice: true, hash: '#bang-gia'});
equal(absent.links[2].hidden, true, 'Unavailable price targets do not leave a dead mobile tab');
absent.scroll(1200);
absent.flush();
equal(absent.active(), 1, 'Missing targets are excluded from scrollspy');
absent.setMobile(false);
equal(absent.links[2].hidden, false, 'Desktop restores an originally visible missing-target link unchanged');
const outside = fixture({outsideTarget: 'bang-gia'});
equal(outside.links[2].hidden, true, 'Unrelated page targets do not become facility tabs');

const responsive = fixture({initialActive: 1, initialHidden: 2, originalVariable: true});
responsive.scroll(1980);
equal(responsive.frames.size, 1, 'A mobile update may be pending during a breakpoint change');
responsive.setMobile(false);
equal(responsive.frames.size, 0, 'Desktop switching cancels a pending mobile update');
equal(responsive.nav.parentNode, responsive.main, 'Desktop restores the original navigation parent');
equal(responsive.main.children[1], responsive.nav, 'Desktop restores the exact original navigation position');
equal(responsive.main.children[2], responsive.following, 'Desktop restoration removes the temporary marker');
equal(responsive.root.classList.contains('facility-nav-mobile'), false, 'Desktop removes the enhancement-only layout state');
equal(responsive.root.style.getPropertyValue('--facility-nav-header-height'), '18px', 'Desktop preserves a pre-existing inline header variable');
equal(responsive.root.style.getPropertyPriority('--facility-nav-header-height'), 'important', 'Desktop preserves an original variable priority');
equal(responsive.root.style.getPropertyValue('--facility-nav-page-padding'), '20px', 'Desktop restores a pre-existing page-padding variable');
equal(responsive.root.style.getPropertyPriority('--facility-nav-page-padding'), 'important', 'Desktop preserves the page-padding variable priority');
equal(responsive.root.style.getPropertyValue('--facility-nav-height'), '', 'Desktop removes the JS-only strip variable');
equal(responsive.active(), 1, 'Desktop restores the original active classes');
equal(responsive.links[1].getAttribute('aria-current'), 'page', 'Desktop restores the original aria-current value');
equal(responsive.links[2].hidden, true, 'Desktop preserves an originally hidden link');
equal(responsive.observers[0].disconnected, true, 'Desktop disconnects mobile resize observations');
const desktopCalls = responsive.nav.scrollCalls.length;
responsive.click(4);
responsive.hash('#dat-lich');
responsive.window.dispatch('scroll');
responsive.window.dispatch('resize');
equal(responsive.active(), 1, 'Desktop clicks, hash changes and scrolling leave active state unchanged');
equal(responsive.nav.scrollCalls.length, desktopCalls, 'Desktop navigation never receives horizontal auto-scrolling');
equal(responsive.frames.size, 0, 'Desktop events do not schedule mobile updates');
responsive.setMobile(true);
equal(responsive.nav.parentNode, responsive.container, 'Switching back to mobile portals the nav again');
responsive.setMobile(false);
equal(responsive.main.children[1], responsive.nav, 'Repeated breakpoint switches do not lose the original position');
const clean = fixture();
clean.setMobile(false);
equal(clean.root.style.getPropertyValue('--facility-nav-header-height'), '', 'Desktop removes the JS-only header variable');
equal(clean.root.style.getPropertyValue('--facility-nav-page-padding'), '', 'Desktop removes the JS-only page-padding compensation variable');

const desktop = fixture({mobile: false});
equal(desktop.nav.parentNode, desktop.main, 'A desktop-only visit never moves the navigation');
equal(desktop.root.style.values.size, 0, 'A desktop-only visit never writes mobile offset variables');
equal(desktop.active(), -1, 'A desktop-only visit does not invent active styles');
equal(desktop.main.children.length, 4, 'A desktop-only visit creates no marker in the DOM');
equal(desktop.observers[0].observed.size, 0, 'A desktop-only visit starts no resize observation');
const fallback = fixture({missingResizeObserver: true, missingFonts: true, missingHeader: true, missingScrollTo: true});
equal(fallback.root.style.getPropertyValue('--facility-nav-header-height'), '0px', 'A missing site header has a safe zero offset');
fallback.click(4);
check(fallback.nav.scrollLeft > 0, 'Older strip APIs use horizontal scrollLeft as a safe fallback');
fallback.geometry.navHeight = 60;
fallback.window.dispatch('resize');
fallback.flush();
equal(fallback.root.style.getPropertyValue('--facility-nav-height'), '60px', 'Window resize remains available without ResizeObserver');
const legacy = fixture({legacyMedia: true});
legacy.setMobile(false);
equal(legacy.nav.parentNode, legacy.main, 'Legacy media-query listeners still restore desktop navigation');
for (const option of ['missingRoot', 'missingNav', 'missingLayout', 'missingMatchMedia']) {
  const unavailable = fixture({[option]: true});
  equal(unavailable.nav.parentNode, unavailable.main, `${option} leaves the existing navigation intact`);
  equal(unavailable.root.style.values.size, 0, `${option} writes no mobile styles`);
}
check(!/\b(?:fetch|XMLHttpRequest|IntersectionObserver|history\.|scrollIntoView|preventDefault)\b/.test(source), 'Enhancement requires no network, document scrolljacking, history replacement or intercepted anchors');
console.log(`Facility detail mobile navigation: ${checks} deterministic checks passed. No browser, network or DB access.`);
