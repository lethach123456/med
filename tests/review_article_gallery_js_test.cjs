'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/review-articles.js'), 'utf8');
let checks = 0;
const check = (actual, expected, message) => { assert.deepEqual(actual, expected, message); checks += 1; };

// A small event/DOM fixture exercises the real script without loading a browser,
// external images, credentials, production data, or third-party dependencies.
function fixture({ count = 3, pointer = true, locale = 'vi', nativeDialog = true } = {}) {
  let focused = null;
  class Element {
    constructor(tagName, attributes = {}, dataset = {}) {
      this.tagName = tagName.toUpperCase();
      this.attributes = { ...attributes };
      this.dataset = { ...dataset };
      this.children = [];
      this.parentNode = null;
      this.listeners = {};
      this.hidden = false;
      this.complete = false;
      this.naturalWidth = 0;
      this.textContent = '';
      this.isConnected = true;
      this.open = false;
      this.classList = { toggle() {} };
    }
    append(...nodes) { nodes.forEach((node) => { node.parentNode = this; this.children.push(node); }); return this; }
    matches(selector) {
      const attribute = selector.match(/^\[([^=\]]+)\]$/);
      return attribute ? Object.hasOwn(this.attributes, attribute[1]) : this.tagName.toLowerCase() === selector;
    }
    querySelectorAll(selector) {
      return this.children.flatMap((child) => [...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)]);
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
    addEventListener(type, callback, options) { (this.listeners[type] ||= []).push({ callback, options }); }
    dispatch(type, values = {}) {
      const event = { target: this, button: 0, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...values };
      (this.listeners[type] || []).forEach(({ callback }) => callback(event));
      return event;
    }
    setAttribute(key, value) { this.attributes[key] = String(value); }
    getAttribute(key) { return this.attributes[key] ?? null; }
    removeAttribute(key) { delete this.attributes[key]; }
    cloneNode() { return new Element(this.tagName, this.attributes, this.dataset); }
    replaceWith(node) {
      const index = this.parentNode.children.indexOf(this);
      this.parentNode.children[index] = node;
      node.parentNode = this.parentNode;
      this.parentNode = null;
      this.isConnected = false;
    }
    focus(options) { focused = { node: this, options }; }
    get src() { return this.attributes.src || ''; }
    set src(value) { this.attributes.src = value; this.complete = false; this.naturalWidth = 0; }
    get href() { return this.attributes.href; }
    get alt() { return this.attributes.alt || ''; }
    set alt(value) { this.attributes.alt = value; }
  }
  const hook = (name, tag = 'div') => new Element(tag, { [name]: '' });
  const page = new Element('main', { 'data-review-articles': '' }, { locale });
  const photos = Array.from({ length: count }, (_, index) => {
    const photo = new Element('a', { 'data-review-photo': '', href: `https://example.test/photo-${index}.webp` }, { caption: `Caption ${index}` });
    return photo.append(new Element('img', { alt: `Photo ${index}` }));
  });
  const dialog = hook('data-review-photo-dialog', 'dialog');
  if (nativeDialog) dialog.showModal = () => { if (dialog.failOpen) throw new Error('Unavailable'); dialog.open = true; };
  dialog.close = () => { dialog.open = false; dialog.dispatch('close'); };
  const close = hook('data-review-photo-close', 'button');
  const previous = hook('data-review-photo-prev', 'button');
  const next = hook('data-review-photo-next', 'button');
  const status = hook('data-review-photo-status');
  const retry = hook('data-review-photo-retry', 'button');
  const stage = hook('data-review-photo-stage');
  stage.append(new Element('img', { 'data-review-photo-image': '', referrerpolicy: 'no-referrer' }), status, retry);
  const caption = hook('data-review-photo-caption', 'figcaption');
  const counter = hook('data-review-photo-counter', 'span');
  dialog.append(close, previous, next, stage, caption, counter);
  page.append(...photos, dialog);
  const body = { style: { overflow: 'clip' } };
  const document = { body, querySelector: (selector) => selector === '[data-review-articles]' ? page : null, addEventListener() {} };
  const window = { visualViewport: { scale: 1 } };
  if (pointer) window.PointerEvent = function PointerEvent() {};
  vm.runInNewContext(source, { document, window, navigator: {}, Date, Map, Array, Number });
  return {
    page, photos, dialog, close, previous, next, status, retry, stage, caption, counter, body, window,
    image: () => dialog.querySelector('[data-review-photo-image]'),
    focused: () => focused,
    loaded: () => { const image = dialog.querySelector('[data-review-photo-image]'); image.naturalWidth = 800; image.complete = true; image.onload(); },
  };
}

const f = fixture();
['ctrlKey', 'metaKey', 'shiftKey', 'altKey'].forEach((modifier) => {
  check(f.photos[0].dispatch('click', { [modifier]: true }).defaultPrevented, false, 'Modified image links keep native behavior.');
});
check(f.photos[0].dispatch('click', { button: 1 }).defaultPrevented, false, 'Middle-click opens the image normally.');
f.photos[0].dispatch('click', { defaultPrevented: true });
check(f.dialog.open, false, 'Modified clicks do not open a modal.');
check(f.photos[0].dispatch('click').defaultPrevented, true, 'Normal click is progressively enhanced.');
check(f.dialog.open, true, 'The native dialog opens.');
check(f.body.style.overflow, 'hidden', 'The document is locked only while the modal is open.');
check(f.dialog.dataset.photoState, 'loading', 'Loading state is visible immediately.');
check(f.status.textContent, 'Đang tải ảnh…', 'Loading feedback is localized.');
check(f.image().getAttribute('aria-busy'), 'true', 'Image loading is announced.');
check(f.image().getAttribute('referrerpolicy'), 'no-referrer', 'Source privacy policy survives image replacement.');
check(f.image().alt, 'Photo 0', 'Full-size images preserve descriptive alternative text.');
check(f.caption.textContent, 'Caption 0', 'Captions are preserved.');
check(f.counter.textContent, '1 / 3', 'Photo counter matches selection.');
f.loaded();
check(f.dialog.dataset.photoState, 'ready', 'Successfully loaded images become visible.');
check(f.status.hidden, true, 'Loading feedback disappears after success.');
check(f.image().getAttribute('aria-busy'), 'false', 'Loading announcement is cleared.');

const oldImage = f.image();
const oldLoad = oldImage.onload;
const oldError = oldImage.onerror;
f.next.dispatch('click');
check(oldImage === f.image(), false, 'Switching images uses a separate node.');
check(oldImage.src, '', 'Old image requests are detached.');
check(oldImage.onload, null, 'Old handlers are removed.');
oldLoad();
oldError();
check(f.dialog.dataset.photoState, 'loading', 'Late load/error events cannot change the next image state.');
f.image().onerror();
check(f.dialog.dataset.photoState, 'error', 'Image failures show an explicit state.');
check(f.retry.hidden, false, 'A failed image can be retried.');
check(f.status.textContent.includes('thử lại'), true, 'Error feedback explains the next action.');
f.retry.dispatch('click');
check(f.image().src, 'https://example.test/photo-1.webp', 'Retry uses the same verified image URL.');
check(f.dialog.dataset.photoState, 'loading', 'Retry re-enters loading state.');
check(f.retry.hidden, true, 'Retry is unavailable during a request.');
check(f.focused().node === f.close, true, 'Retry transfers focus to a visible viewer control.');
f.loaded();
check(f.dialog.dataset.photoState, 'ready', 'A retried image can recover.');

f.dialog.dispatch('keydown', { key: 'ArrowRight' });
check(f.counter.textContent, '3 / 3', 'Arrow keys advance photos.');
f.dialog.dispatch('keydown', { key: 'ArrowRight' });
check(f.counter.textContent, '1 / 3', 'Arrow navigation wraps.');
f.dialog.dispatch('keydown', { key: 'ArrowLeft', metaKey: true });
check(f.counter.textContent, '1 / 3', 'Modified keyboard shortcuts are not intercepted.');
f.dialog.dispatch('keydown', { key: 'ArrowLeft', target: { tagName: 'INPUT' } });
check(f.counter.textContent, '1 / 3', 'Text-field cursor movement is not intercepted.');
f.dialog.dispatch('keydown', { key: 'ArrowLeft', target: { isContentEditable: true } });
check(f.counter.textContent, '1 / 3', 'Editable content retains cursor keys.');

const swipe = (start, end, extra = {}) => {
  f.stage.dispatch('pointerdown', { pointerType: 'touch', pointerId: 5, isPrimary: true, clientX: start[0], clientY: start[1], ...extra });
  f.stage.dispatch('pointerup', { pointerType: 'touch', pointerId: 5, clientX: end[0], clientY: end[1] });
};
swipe([200, 100], [100, 105]);
check(f.counter.textContent, '2 / 3', 'A deliberate horizontal touch swipe advances photos.');
swipe([100, 100], [120, 210]);
check(f.counter.textContent, '2 / 3', 'Vertical scrolling is not interpreted as a swipe.');
swipe([100, 100], [125, 100]);
check(f.counter.textContent, '2 / 3', 'Small accidental movements do not navigate.');
swipe([100, 100], [200, 100], { pointerType: 'mouse' });
check(f.counter.textContent, '2 / 3', 'Mouse drags retain native behavior.');
f.window.visualViewport.scale = 2;
swipe([200, 100], [100, 100]);
check(f.counter.textContent, '2 / 3', 'Zoomed photos can be panned without navigating.');
f.window.visualViewport.scale = 1;
f.stage.dispatch('pointerdown', { pointerType: 'touch', pointerId: 5, isPrimary: true, button: 0, clientX: 200, clientY: 100 });
f.stage.dispatch('pointerdown', { pointerType: 'touch', pointerId: 6, isPrimary: false, button: 0, clientX: 100, clientY: 100 });
f.stage.dispatch('pointerup', { pointerType: 'touch', pointerId: 5, clientX: 100, clientY: 100 });
check(f.counter.textContent, '2 / 3', 'Multi-touch gestures cannot turn into photo navigation.');
check(Object.values(f.stage.listeners).flat().every(({ options }) => options.passive === true), true, 'Touch handlers never block native scroll or zoom.');

const lateLoad = f.image().onload;
f.close.dispatch('click');
check(f.body.style.overflow, 'clip', 'Closing restores the exact original document overflow.');
check(f.image().src, '', 'Closing clears the full-size image.');
check(f.focused().node === f.photos[0], true, 'Keyboard focus returns to the photo that opened the dialog.');
check(f.focused().options.preventScroll, true, 'Focus restoration does not unexpectedly scroll the page.');
lateLoad();
check(f.dialog.dataset.photoState, '', 'Late load after closing cannot reopen loading state.');
f.body.style.overflow = 'auto';
f.photos[2].dispatch('click');
f.photos[1].dispatch('click');
f.dialog.close();
check(f.body.style.overflow, 'auto', 'Changing selection while open does not overwrite the original scroll state.');
check(f.focused().node === f.photos[1], true, 'The most recent trigger receives focus after closing.');
f.dialog.failOpen = true;
check(f.photos[0].dispatch('click').defaultPrevented, false, 'A failed native dialog keeps the original image link usable.');
check(f.body.style.overflow, 'auto', 'Failed modal opening never locks document scrolling.');

const touch = fixture({ pointer: false, locale: 'en' });
touch.photos[0].dispatch('click');
check(touch.status.textContent, 'Loading photo…', 'English loading feedback is supported.');
touch.stage.dispatch('touchstart', { touches: [{ identifier: 1, clientX: 200, clientY: 100 }] });
touch.stage.dispatch('touchend', { changedTouches: [{ identifier: 1, clientX: 100, clientY: 100 }] });
check(touch.counter.textContent, '2 / 3', 'Touch events support browsers without PointerEvent.');
touch.stage.dispatch('touchstart', { touches: [{ identifier: 1, clientX: 200, clientY: 100 }] });
touch.stage.dispatch('touchmove', { touches: [{ identifier: 1 }, { identifier: 2 }] });
touch.stage.dispatch('touchend', { changedTouches: [{ identifier: 1, clientX: 100, clientY: 100 }] });
check(touch.counter.textContent, '2 / 3', 'Fallback touch handling also preserves pinch gestures.');
touch.image().onerror();
check(touch.status.textContent.includes('try again'), true, 'English errors explain retry.');

const single = fixture({ count: 1 });
single.photos[0].dispatch('click');
check(single.previous.hidden && single.next.hidden, true, 'A single-photo gallery does not show navigation controls.');
const singleImage = single.image();
check(single.dialog.dispatch('keydown', { key: 'ArrowRight' }).defaultPrevented, false, 'Single-photo galleries do not consume arrow keys.');
check(singleImage === single.image(), true, 'Single-photo keyboard input does not reload the image.');
const fallback = fixture({ nativeDialog: false });
check(fallback.photos[0].dispatch('click').defaultPrevented, false, 'Unsupported native dialogs preserve accessible image links.');
fixture({ count: 0 });
checks += 1;
console.log(`review_article_gallery_js_test: ${checks} checks passed`);
