'use strict';

// Deterministic DOM fixtures only: no browser, PHP bootstrap, network or DB.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const project = path.resolve(__dirname, '..');
const filename = path.join(project, 'assets/js/facility-profile-disclosure.js');
const source = fs.readFileSync(filename, 'utf8');
const page = fs.readFileSync(path.join(project, 'co-so-y-te-chi-tiet.php'), 'utf8');
const css = fs.readFileSync(path.join(project, 'assets/css/pages/facility-profile-disclosure.css'), 'utf8');
let checks = 0;
const equal = (actual, expected, message) => {
  checks++;
  assert.equal(actual, expected, message);
};
const check = (condition, message) => {
  checks++;
  assert.ok(condition, message);
};

class ClassList {
  constructor() { this.values = new Set(); }
  contains(value) { return this.values.has(value); }
  add(...values) { values.forEach(value => this.values.add(value)); }
  remove(...values) { values.forEach(value => this.values.delete(value)); }
  toggle(value, force = !this.contains(value)) {
    if (force) this.add(value);
    else this.remove(value);
    return force;
  }
}

class Element {
  constructor(attributes = {}) {
    this.attributes = new Map(Object.entries(attributes));
    this.classList = new ClassList();
    this.listeners = new Map();
    this.hidden = false;
    this.scrollCalls = [];
    this.animationCalls = [];
    this.style = {};
  }
  getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
  setAttribute(name, value) { this.attributes.set(name, String(value)); }
  removeAttribute(name) { this.attributes.delete(name); }
  addEventListener(name, listener) {
    if (!this.listeners.has(name)) this.listeners.set(name, []);
    this.listeners.get(name).push(listener);
  }
  dispatch(name, event = {}) { (this.listeners.get(name) || []).forEach(listener => listener({type: name, target: this, ...event})); }
  click() { this.dispatch('click'); }
  scrollIntoView(options) { this.scrollCalls.push(options); }
  animate(keyframes, options) {
    const animation = {cancelled: false, cancel() { this.cancelled = true; }};
    this.animationCalls.push({keyframes, options, animation});
    return animation;
  }
}

function fixture(options = {}) {
  const root = new Element();
  const content = new Element();
  const button = new Element({'aria-expanded': 'false', 'aria-controls': 'facility-profile-content'});
  const label = {textContent: 'Read full facility profile'};
  button.dataset = {more: 'Read full facility profile', less: 'Show less'};
  button.hidden = true;
  button.querySelector = selector => selector === '[data-facility-read-label]' ? label : null;
  root.querySelector = selector => {
    if (selector === '[data-facility-profile-content]') return options.missingContent ? null : content;
    if (selector === '[data-facility-read-more]') return options.missingButton ? null : button;
    return null;
  };

  const viewportTop = 100;
  const preview = {height: options.height === undefined ? '430px' : options.height};
  content.scrollHeight = options.scrollHeight === undefined ? 1000 : options.scrollHeight;
  content.getBoundingClientRect = () => ({
    top: viewportTop,
    bottom: viewportTop + (content.classList.contains('is-collapsed') ? parseFloat(preview.height) || 460 : content.scrollHeight),
  });
  const link = (bottom, tabindex) => {
    const element = new Element(tabindex === undefined ? {} : {tabindex});
    element.getBoundingClientRect = () => ({bottom: viewportTop + bottom});
    return element;
  };
  const links = [link(180), link(800), link(850, '3'), link(900, '-1'), link(350, '0')];
  content.querySelectorAll = () => links;
  const anchor = new Element();
  const outside = new Element();
  content.contains = element => element === anchor || links.includes(element);
  const targets = {'profile-section': anchor, outside};
  const location = {hash: options.hash || ''};
  const motion = new Element();
  motion.matches = Boolean(options.reducedMotion);
  const window = new Element();
  const fontCallbacks = [];
  const resizeObservers = [];
  const intersectionObservers = [];
  const document = {
    querySelector: () => options.missingRoot ? null : root,
    getElementById: id => targets[id] || null,
  };
  if (!options.missingFonts) document.fonts = {ready: {then: callback => fontCallbacks.push(callback)}};
  class ResizeObserver {
    constructor(callback) { this.callback = callback; resizeObservers.push(this); }
    observe(element) { this.element = element; }
  }
  class IntersectionObserver {
    constructor(callback, options) {
      this.callback = callback;
      this.options = options;
      this.disconnected = false;
      intersectionObservers.push(this);
    }
    observe(element) { this.element = element; }
    disconnect() { this.disconnected = true; }
  }
  const sandbox = {
    document, window, location,
    matchMedia: () => motion,
    getComputedStyle: () => ({getPropertyValue: () => preview.height}),
  };
  if (!options.missingResizeObserver) {
    window.ResizeObserver = ResizeObserver;
    sandbox.ResizeObserver = ResizeObserver;
  }
  if (options.withIntersectionObserver) {
    window.IntersectionObserver = IntersectionObserver;
    sandbox.IntersectionObserver = IntersectionObserver;
  }
  if (options.missingAnimate) content.animate = undefined;
  vm.runInNewContext(source, sandbox, {filename});
  return {
    root, content, button, label, links, motion, location, preview, window,
    anchor, outside, fontCallbacks, resizeObservers, intersectionObservers,
    resize() { resizeObservers.forEach(observer => observer.callback()); },
    setMotion(matches) { motion.matches = matches; motion.dispatch('change', {matches}); },
  };
}

const markup = page.replace(/<\?(?:php|=)[\s\S]*?\?>/g, '');
const previewMarkup = markup.match(/<div\b[^>]*data-facility-profile-content\b[^>]*>/)?.[0];
check(previewMarkup, 'The page provides the profile preview hook');
check(!/\bhidden(?:\s|=|>)|\baria-hidden\s*=/.test(previewMarkup), 'Without JavaScript, the page does not hide the profile content');
check(page.includes('aria-controls="facility-profile-content"'), 'The control names its profile content region');
check(/<script[^>]+\/assets\/js\/facility-profile-disclosure\.js[^>]+\bdefer\b/.test(markup), 'Disclosure behavior loads after the document is parsed');
const cssRules = [...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)];
const previewRules = cssRules.filter(rule => /\.facility-info-preview\s*$/.test(rule[1].trim()));
check(previewRules.some(rule => /--facility-preview-height\s*:\s*[1-9]\d*px/.test(rule[2])), 'CSS defines a readable, nonzero preview height');
const collapsedRule = cssRules.find(rule => /\.facility-info-preview\.is-collapsed\s*$/.test(rule[1].trim()));
check(collapsedRule && /max-height\s*:\s*var\(--facility-preview-height\)/.test(collapsedRule[2]) && /overflow\s*:\s*hidden/.test(collapsedRule[2]), 'The collapsed state clips only the excess height');
check([...previewRules, collapsedRule].every(rule => !/display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0\b/.test(rule[2])), 'Preview CSS never hides the whole profile');
const fadeRule = cssRules.find(rule => /\.facility-info-preview\.is-collapsed::after\s*$/.test(rule[1].trim()));
check(fadeRule && /linear-gradient\s*\(/.test(fadeRule[2]) && /pointer-events\s*:\s*none/.test(fadeRule[2]), 'The preview ends in a fade that cannot block interaction');
const hintRules = cssRules.filter(rule => /\.is-hinting/.test(rule[1]) && /animation\s*:/.test(rule[2]));
check(hintRules.length > 0 && hintRules.every(rule => {
  const value = rule[2].match(/animation\s*:\s*([^;]+)/)[1].trim();
  const iterations = Number(value.split(/\s+/).at(-1));
  return Number.isFinite(iterations) && iterations > 0 && !/\binfinite\b/.test(value);
}), 'The attention hint runs a finite number of times');
check(/@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)[\s\S]*animation\s*:\s*none!important/.test(css), 'CSS decorations also honor reduced motion');

const long = fixture();
equal(long.button.hidden, false, 'Long profiles expose the expansion control');
equal(long.content.classList.contains('is-collapsed'), true, 'Long profiles start with a clipped preview');
equal(long.content.hidden, false, 'The readable preview is not entirely hidden');
equal(long.content.getAttribute('hidden'), null, 'The content never receives the hidden attribute');
equal(long.content.getAttribute('aria-hidden'), null, 'Preview text stays exposed to assistive technology');
check(long.content.getBoundingClientRect().bottom > long.content.getBoundingClientRect().top, 'A nonzero preview remains visible');
equal(long.button.getAttribute('aria-expanded'), 'false', 'Initial accessibility state is collapsed');
equal(long.label.textContent, long.button.dataset.more, 'Initial control label invites expansion');
equal(long.button.classList.contains('is-hinting'), true, 'A finite hint has a fallback when visibility observation is unavailable');
equal(long.links[0].getAttribute('tabindex'), null, 'A visible natural link keeps its tab order');
equal(long.links[4].getAttribute('tabindex'), '0', 'A visible explicit tabindex is unchanged');
long.links.slice(1, 4).forEach((link, index) => equal(link.getAttribute('tabindex'), '-1', `Clipped link ${index + 1} cannot receive invisible keyboard focus`));

long.button.click();
equal(long.button.getAttribute('aria-expanded'), 'true', 'Opening publishes the expanded accessibility state');
equal(long.label.textContent, long.button.dataset.less, 'Opening changes the control to Show less');
equal(long.button.classList.contains('is-hinting'), false, 'Opening stops the attention hint');
equal(long.content.classList.contains('is-collapsed'), false, 'Opening reveals the complete profile');
equal(long.content.hidden, false, 'The expanded content remains visible');
equal(long.links[1].getAttribute('tabindex'), null, 'Opening removes a temporary tabindex from a natural link');
equal(long.links[2].getAttribute('tabindex'), '3', 'Opening restores the original positive tabindex');
equal(long.links[3].getAttribute('tabindex'), '-1', 'Opening preserves an originally untabbable link');
equal(long.links[4].getAttribute('tabindex'), '0', 'Opening preserves an originally tabbable link');
equal(long.content.animationCalls.length, 1, 'Opening animates when motion is allowed');
check(long.content.animationCalls[0].options.duration > 0, 'The reveal animation has a positive, finite duration');
equal(long.root.scrollCalls.length, 0, 'Opening does not unexpectedly scroll the reading position');

long.button.click();
equal(long.button.getAttribute('aria-expanded'), 'false', 'Collapsing restores the accessibility state');
equal(long.label.textContent, long.button.dataset.more, 'Collapsing restores the expansion label');
equal(long.content.classList.contains('is-collapsed'), true, 'Collapsing returns to the readable preview');
equal(long.content.hidden, false, 'Collapsing does not hide the whole profile');
equal(long.content.animationCalls.length, 1, 'Collapsing does not start a reveal animation');
equal(long.content.animationCalls[0].animation.cancelled, true, 'A rapid collapse cancels the outstanding reveal animation');
equal(long.root.scrollCalls.at(-1).behavior, 'smooth', 'Collapsing gently returns to the profile heading');
equal(long.root.scrollCalls.at(-1).block, 'start', 'The profile heading is the collapse scroll target');
equal(long.links[2].getAttribute('tabindex'), '-1', 'Recollapsing disables links below the preview again');
long.button.click();
equal(long.links[1].getAttribute('tabindex'), null, 'Repeated toggles do not lose a natural tabindex');
equal(long.links[2].getAttribute('tabindex'), '3', 'Repeated toggles do not overwrite the original tabindex');

const short = fixture({scrollHeight: 260});
equal(short.button.hidden, true, 'Short profiles need no expansion control');
equal(short.content.classList.contains('is-collapsed'), false, 'Short profiles remain fully readable');
equal(short.links[1].getAttribute('tabindex'), null, 'Short profile links keep their natural tab order');
equal(short.links[2].getAttribute('tabindex'), '3', 'Short profile links keep their explicit tabindex');
equal(fixture({scrollHeight: 454}).button.hidden, true, 'The threshold allows a small height tolerance');
equal(fixture({scrollHeight: 455}).button.hidden, false, 'Content above the tolerance gets a control');
equal(fixture({height: '400px', scrollHeight: 425}).button.hidden, false, 'The current CSS preview height drives mobile clipping');
equal(fixture({height: '', scrollHeight: 485}).button.hidden, false, 'An unavailable CSS variable has a safe preview-height fallback');

const reduced = fixture({reducedMotion: true});
equal(reduced.button.classList.contains('is-hinting'), false, 'Reduced motion never starts the attention hint');
reduced.button.click();
equal(reduced.content.animationCalls.length, 0, 'Reduced motion opens without an animation');
equal(reduced.button.getAttribute('aria-expanded'), 'true', 'Reduced motion still fully expands the profile');
reduced.button.click();
equal(reduced.root.scrollCalls.at(-1).behavior, 'auto', 'Reduced motion collapses without smooth scrolling');
reduced.setMotion(false);
reduced.button.click();
equal(reduced.content.animationCalls.length, 1, 'Toggles respect the current, not cached, motion preference');
reduced.setMotion(true);
equal(reduced.content.animationCalls[0].animation.cancelled, true, 'A live reduced-motion change cancels an active reveal');
equal(reduced.button.getAttribute('aria-expanded'), 'true', 'Changing motion preference does not change the reading state');
const noAnimationAPI = fixture({missingAnimate: true});
noAnimationAPI.button.click();
equal(noAnimationAPI.button.getAttribute('aria-expanded'), 'true', 'Unsupported animation APIs do not prevent expansion');

const responsive = fixture();
equal(responsive.resizeObservers[0].element, responsive.content, 'Resize tracking watches the profile content');
responsive.content.scrollHeight = 200;
responsive.resize();
equal(responsive.button.hidden, true, 'Resizing a short profile hides the unnecessary control');
equal(responsive.content.classList.contains('is-collapsed'), false, 'Resizing a short profile removes the clipping');
equal(responsive.button.classList.contains('is-hinting'), false, 'A hidden short-profile control has no attention hint');
equal(responsive.links[2].getAttribute('tabindex'), '3', 'Resizing restores tabindex values when clipping ends');
responsive.content.scrollHeight = 1000;
responsive.resize();
equal(responsive.button.hidden, false, 'Resizing back to long content restores the control');
equal(responsive.content.classList.contains('is-collapsed'), true, 'Long content respects the current collapsed state');
responsive.button.click();
responsive.preview.height = '400px';
responsive.resize();
equal(responsive.content.classList.contains('is-collapsed'), false, 'Resizing does not recollapse a profile the reader opened');
const fonts = fixture({scrollHeight: 200});
fonts.content.scrollHeight = 1000;
fonts.fontCallbacks.forEach(callback => callback());
equal(fonts.button.hidden, false, 'Font completion rechecks the actual content height');

const observed = fixture({withIntersectionObserver: true});
equal(observed.button.classList.contains('is-hinting'), false, 'Offscreen controls do not start an attention hint');
const visibility = observed.intersectionObservers[0];
equal(visibility.element, observed.button, 'Visibility tracking watches the expansion control');
check(visibility.options.threshold > 0, 'Visibility tracking waits for a meaningful visible fraction');
visibility.callback([{isIntersecting: false}]);
equal(observed.button.classList.contains('is-hinting'), false, 'A nonintersecting observation does not consume the hint');
visibility.callback([{isIntersecting: true}]);
equal(observed.button.classList.contains('is-hinting'), true, 'The hint begins when the control enters view');
equal(visibility.disconnected, true, 'A triggered hint disconnects its visibility observer');
observed.button.dispatch('pointerenter');
equal(observed.button.classList.contains('is-hinting'), false, 'Pointer interaction stops the attention hint');
for (const event of ['pointerdown', 'focus']) {
  const interacting = fixture({withIntersectionObserver: true});
  interacting.button.dispatch(event);
  equal(interacting.intersectionObservers[0].disconnected, true, `${event} cancels a pending attention hint`);
  equal(interacting.button.classList.contains('is-hinting'), false, `${event} leaves the control still`);
}
const motionHint = fixture();
motionHint.setMotion(true);
equal(motionHint.button.classList.contains('is-hinting'), false, 'A live reduced-motion change stops the attention hint');

const direct = fixture({hash: '#profile-section'});
equal(direct.content.classList.contains('is-collapsed'), false, 'A direct link to a profile section reveals its target');
equal(direct.button.getAttribute('aria-expanded'), 'true', 'Direct-link reveal keeps button state synchronized');
const hash = fixture();
hash.location.hash = '#outside';
hash.window.dispatch('hashchange');
equal(hash.content.classList.contains('is-collapsed'), true, 'A hash outside the profile does not change its state');
hash.location.hash = '#missing';
hash.window.dispatch('hashchange');
equal(hash.content.classList.contains('is-collapsed'), true, 'A missing hash target is harmless');
hash.location.hash = '#profile-section';
hash.window.dispatch('hashchange');
equal(hash.content.classList.contains('is-collapsed'), false, 'Changing to an in-profile hash reveals the section');

const print = fixture();
print.window.dispatch('beforeprint');
equal(print.content.classList.contains('is-collapsed'), false, 'Printing exposes the entire profile');
equal(print.links[2].getAttribute('tabindex'), '3', 'Printing restores clipped links');
print.window.dispatch('afterprint');
equal(print.content.classList.contains('is-collapsed'), true, 'After printing, the prior collapsed state returns');
equal(print.links[2].getAttribute('tabindex'), '-1', 'After printing, clipped links leave the tab order again');

for (const options of [
  {missingRoot: true}, {missingContent: true}, {missingButton: true},
  {missingFonts: true, missingResizeObserver: true},
]) {
  check(fixture(options), `Optional/missing DOM safely initializes: ${JSON.stringify(options)}`);
}

console.log(`Facility profile disclosure: ${checks} checks passed. Mocked DOM only; no browser, network or DB.`);
