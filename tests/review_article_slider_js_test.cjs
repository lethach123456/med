'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/review-articles.js'), 'utf8');
let checks = 0;
const check = (actual, expected, message) => { assert.deepEqual(actual, expected, message); checks += 1; };

// Execute the production script against an isolated, dependency-free DOM/event
// fixture. No browser, remote images, API calls, credentials, or DB is involved.
function fixture({ count = 8, width = 600, gap = 16, reducedMotion = false, observer = true, nativeScrollTo = true, controls = true, hooks = true } = {}) {
  const frames = [];
  const calls = [];
  const windowListeners = {};
  let resizeCallback;
  let observed;
  class Element {
    constructor(tagName = 'div', attributes = {}) {
      this.tagName = tagName.toUpperCase();
      this.attributes = { ...attributes };
      this.children = [];
      this.listeners = {};
      this.dataset = {};
      this.hidden = false;
      this.textContent = '';
      this.disabled = false;
      this.scrollLeft = 0;
    }
    append(...children) { this.children.push(...children); return this; }
    matches(selector) {
      const attribute = selector.match(/^\[([^=\]]+)\]$/);
      if (attribute) return Object.hasOwn(this.attributes, attribute[1]);
      if (selector.startsWith('.')) return (this.attributes.class || '').split(/\s+/).includes(selector.slice(1));
      return this.tagName.toLowerCase() === selector;
    }
    querySelectorAll(selector) {
      return this.children.flatMap((child) => [...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)]);
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
    addEventListener(type, callback, options) { (this.listeners[type] ||= []).push({ callback, options }); }
    dispatch(type, values = {}) {
      const event = { target: this, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...values };
      (this.listeners[type] || []).forEach(({ callback }) => callback(event));
      return event;
    }
  }
  const hook = (attribute, tag) => new Element(tag, { [attribute]: '' });
  const page = hook('data-review-articles', 'main');
  const slider = hook('data-review-photo-slider', 'section');
  const track = hook('data-review-photo-track');
  const cards = Array.from({ length: count }, () => new Element('figure', { class: 'ra-customer-card' }));
  track.append(...cards);
  const previous = hook('data-review-slider-prev', 'button');
  const next = hook('data-review-slider-next', 'button');
  const position = hook('data-review-slider-position', 'output');
  const navigation = hook('data-review-slider-controls');
  navigation.hidden = true;
  navigation.append(previous, position, next);
  slider.append(track);
  if (controls) slider.append(navigation);
  if (hooks) page.append(slider);
  const setSize = (newWidth, newGap = gap) => {
    const cardWidth = (newWidth - newGap) / 2;
    track.clientWidth = newWidth;
    track.scrollWidth = cards.length * cardWidth + Math.max(0, cards.length - 1) * newGap;
    cards.forEach((card, index) => { card.offsetLeft = index * (cardWidth + newGap); });
    track.scrollLeft = Math.max(0, Math.min(track.scrollLeft, track.scrollWidth - track.clientWidth));
  };
  setSize(width);
  if (nativeScrollTo) track.scrollTo = (options) => {
    calls.push({ ...options });
    track.scrollLeft = options.left;
    track.dispatch('scroll');
  };
  const window = {
    requestAnimationFrame: (callback) => { frames.push(callback); return frames.length; },
    matchMedia: (query) => { check(query, '(prefers-reduced-motion: reduce)', 'Motion preference uses the standard media query.'); return { matches: reducedMotion }; },
    addEventListener: (type, callback, options) => { (windowListeners[type] ||= []).push({ callback, options }); },
  };
  if (observer) window.ResizeObserver = class {
    constructor(callback) { resizeCallback = callback; }
    observe(element) { observed = element; }
  };
  const document = { querySelector: (selector) => selector === '[data-review-articles]' ? page : null, addEventListener() {} };
  vm.runInNewContext(source, { document, window, navigator: {} });
  return {
    page, slider, track, cards, previous, next, position, navigation, calls, frames, windowListeners,
    observed: () => observed,
    flush: () => { while (frames.length) frames.shift()(); },
    nativeScroll: (left) => { track.scrollLeft = left; track.dispatch('scroll'); },
    resize: (newWidth, newGap) => {
      setSize(newWidth, newGap);
      if (resizeCallback) resizeCallback();
      else (windowListeners.resize || []).forEach(({ callback }) => callback());
    },
  };
}

const f = fixture();
check(f.navigation.hidden, false, 'More than two photos progressively show navigation.');
check(f.position.textContent, '1–2 / 8', 'The initial range represents two visible photos.');
check(f.previous.disabled, true, 'Previous is disabled at the start.');
check(f.next.disabled, false, 'Next is enabled before the end.');
check(f.observed() === f.track, true, 'Resize observation is scoped to the photo track.');
check(f.calls.length, 0, 'Initializing the rail does not move it or autoplay.');
f.next.dispatch('click');
check(f.calls[0], { left: 616, behavior: 'smooth' }, 'Next moves exactly two cards, including the gap.');
check(f.frames.length, 1, 'Scroll feedback is animation-frame throttled.');
f.flush();
check(f.position.textContent, '3–4 / 8', 'The range follows the next pair.');
check(f.previous.disabled, false, 'Previous enables after moving.');
f.next.dispatch('click');
f.flush();
check(f.position.textContent, '5–6 / 8', 'The next pair remains in the same row.');
f.next.dispatch('click');
f.flush();
check(f.position.textContent, '7–8 / 8', 'The final complete pair is reported correctly.');
check(f.next.disabled, true, 'Next disables at the final pair.');
f.previous.dispatch('click');
f.flush();
check(f.position.textContent, '5–6 / 8', 'Previous goes back by two photos.');
check(f.next.disabled, false, 'Next re-enables after going back.');

const odd = fixture({ count: 5 });
odd.next.dispatch('click');
odd.flush();
check(odd.position.textContent, '3–4 / 5', 'Odd-sized galleries show the middle pair.');
odd.next.dispatch('click');
odd.flush();
check(odd.calls.at(-1).left, 924, 'The final move clamps to the native scroll boundary.');
check(odd.position.textContent, '4–5 / 5', 'An odd final pair uses actual visible cards instead of an out-of-range label.');
check(odd.next.disabled, true, 'An odd final pair disables Next.');
odd.next.dispatch('click');
odd.flush();
check(odd.position.textContent, '4–5 / 5', 'Attempts beyond the end never wrap or autoplay.');

const native = fixture({ count: 7 });
native.nativeScroll(308);
native.nativeScroll(616);
check(native.frames.length, 1, 'Consecutive native scroll events share one pending animation frame.');
native.flush();
check(native.position.textContent, '3–4 / 7', 'Native touch/trackpad scrolling updates the displayed range.');
check(native.calls.length, 0, 'Native scroll is not replaced with programmatic dragging.');
check(native.track.listeners.scroll[0].options.passive, true, 'The scroll listener is passive.');
check(Object.hasOwn(native.track.listeners, 'touchmove'), false, 'The rail never intercepts browser touch movement.');
native.nativeScroll(-20);
native.flush();
check(native.position.textContent, '1–2 / 7', 'Start overscroll cannot produce negative photo numbers.');
check(native.previous.disabled, true, 'Start overscroll still disables Previous.');
native.nativeScroll(100000);
native.flush();
check(native.position.textContent, '6–7 / 7', 'End overscroll cannot produce numbers beyond the last photo.');
check(native.next.disabled, true, 'End overscroll still disables Next.');

const motion = fixture({ reducedMotion: true });
motion.next.dispatch('click');
check(motion.calls[0].behavior, 'auto', 'Reduced-motion preferences disable scripted smooth movement.');
motion.flush();
check(motion.position.textContent, '3–4 / 8', 'Reduced-motion users keep identical navigation behavior.');

const keyboard = fixture({ count: 6 });
check(keyboard.track.dispatch('keydown', { key: 'ArrowRight' }).defaultPrevented, true, 'A focused rail supports the right arrow.');
keyboard.flush();
check(keyboard.position.textContent, '3–4 / 6', 'The keyboard advances a pair.');
check(keyboard.track.dispatch('keydown', { key: 'ArrowLeft' }).defaultPrevented, true, 'A focused rail supports the left arrow.');
keyboard.flush();
check(keyboard.position.textContent, '1–2 / 6', 'The keyboard goes back a pair.');
const callsBeforeGuards = keyboard.calls.length;
['altKey', 'ctrlKey', 'metaKey', 'shiftKey'].forEach((modifier) => {
  check(keyboard.track.dispatch('keydown', { key: 'ArrowRight', [modifier]: true }).defaultPrevented, false, 'Modified browser shortcuts remain native.');
});
check(keyboard.track.dispatch('keydown', { key: 'ArrowRight', target: { tagName: 'A' } }).defaultPrevented, false, 'Nested image links do not trigger rail movement.');
check(keyboard.track.dispatch('keydown', { key: 'ArrowRight', target: { tagName: 'DIALOG' } }).defaultPrevented, false, 'The photo viewer does not trigger rail movement.');
check(keyboard.track.dispatch('keydown', { key: 'Tab' }).defaultPrevented, false, 'Tab navigation is not trapped.');
check(keyboard.calls.length, callsBeforeGuards, 'Guarded keyboard events never issue scroll requests.');

const resized = fixture({ count: 5 });
resized.resize(340, 12);
resized.next.dispatch('click');
resized.flush();
check(resized.calls[0].left, 352, 'Responsive resizing recalculates the pair width and gap.');
check(resized.position.textContent, '3–4 / 5', 'Resizing preserves correct range reporting.');
resized.resize(600, 16);
check(resized.position.textContent, '2–3 / 5', 'Range feedback reflects the current offset after a resize.');
const fallbackResize = fixture({ observer: false });
check(fallbackResize.windowListeners.resize.length, 1, 'Browsers without ResizeObserver receive a resize fallback.');
fallbackResize.resize(340, 12);
fallbackResize.next.dispatch('click');
check(fallbackResize.calls[0].left, 352, 'The window resize fallback also recalculates movement.');

const noScrollTo = fixture({ nativeScrollTo: false });
noScrollTo.next.dispatch('click');
check(noScrollTo.track.scrollLeft, 616, 'Older browsers can move using scrollLeft.');
check(noScrollTo.position.textContent, '3–4 / 8', 'The fallback immediately updates feedback.');
[0, 1, 2].forEach((count) => {
  const small = fixture({ count });
  check(small.navigation.hidden, true, 'Two or fewer photos never show redundant controls.');
  check(Object.keys(small.track.listeners).length, 0, 'Short galleries retain fully native behavior.');
});
fixture({ hooks: false });
fixture({ controls: false });
checks += 2;
console.log(`review_article_slider_js_test: ${checks} checks passed`);
