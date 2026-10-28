'use strict';

// Isolated inline hero behavior with controlled Image/timer/DOM fixtures only.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const page = fs.readFileSync(path.join(__dirname, '../co-so-y-te-chi-tiet.php'), 'utf8');
const between = (start, end) => {
  const first = page.indexOf(start), last = page.indexOf(end, first + start.length);
  assert.ok(first >= 0 && last > first, 'Inline source extraction boundaries exist');
  return page.slice(first + start.length, last);
};
const variables = between('const galleryTriggers =', '// Facility hero gallery functions.');
const functions = between('// Facility hero gallery functions.', '// End facility hero gallery functions.');
const navigation = between('// Facility hero gallery navigation.', '// End facility hero gallery navigation.');
const clicks = between('if (galleryReady) {\n          galleryTriggers.forEach', "previousImage.addEventListener('click'");
let checks = 0;
const check = (condition, label) => { checks++; assert.ok(condition, label); };
const same = (actual, expected, label) => { checks++; assert.deepEqual(JSON.parse(JSON.stringify(actual)), expected, label); };
const settle = async () => { for (let index = 0; index < 12; index++) await Promise.resolve(); };

class Element {
  constructor({ index, src = '' } = {}) {
    this.dataset = index === undefined ? {} : { galleryIndex: String(index) };
    this.attributes = new Map(); this.listeners = new Map(); this.classes = new Set();
    this.textContent = ''; this.src = src; this.alt = ''; this.disabled = false; this.scrolls = [];
    this.complete = true; this.naturalWidth = 600;
    this.classList = {
      contains: value => this.classes.has(value), add: (...values) => values.forEach(value => this.classes.add(value)),
      remove: (...values) => values.forEach(value => this.classes.delete(value)),
      toggle: (value, force = !this.classes.has(value)) => { force ? this.classes.add(value) : this.classes.delete(value); return force; }
    };
  }
  setAttribute(key, value) { this.attributes.set(key, String(value)); }
  getAttribute(key) { return this.attributes.get(key) ?? null; }
  addEventListener(type, callback, options) { if (!this.listeners.has(type)) this.listeners.set(type, []); this.listeners.get(type).push({ callback, options }); }
  emit(type, values = {}) {
    const event = { type, target: this, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...values };
    for (const { callback } of this.listeners.get(type) || []) callback(event);
    return event;
  }
  scrollIntoView(options) { this.scrolls.push(options); }
}

function harness({ count = 6, heroIndex = 0, reducedMotion = false, initialBroken = false, noControls = false, noCounter = false, noStatus = false, drag = null } = {}) {
  const images = Array.from({ length: count }, (_, index) => `/images/facility-${index + 1}.jpg`);
  const image = count ? new Element({ src: images[heroIndex] }) : null;
  if (initialBroken) image.naturalWidth = 0;
  const counter = noCounter || !count ? null : new Element();
  const status = noStatus || !count ? null : new Element();
  const hero = count ? new Element({ index: heroIndex }) : null;
  if (hero) hero.querySelector = selector => selector === 'img' ? image : selector === '[data-hero-gallery-counter]' ? counter : null;
  const previous = noControls ? null : new Element(), next = noControls ? null : new Element();
  const thumbIndexes = images.map((_, index) => index).filter(index => index !== heroIndex).slice(0, 4);
  const thumbs = thumbIndexes.map(index => new Element({ index }));
  const lightbox = new Element(); lightbox.querySelector = () => new Element();
  const lightboxNodes = new Map(['facilityGalleryImage', 'facilityGalleryCounter', 'facilityGalleryStatus'].map(id => [id, new Element()]));
  lightboxNodes.set('facilityGalleryLightbox', lightbox);
  const document = {
    querySelectorAll: selector => selector.startsWith('.thumb') ? thumbs : (hero ? [hero, ...thumbs] : []),
    querySelector: selector => ({ '[data-hero-gallery-carousel]': hero, '[data-hero-gallery-prev]': previous, '[data-hero-gallery-next]': next, '[data-hero-gallery-status]': status })[selector] ?? null,
    getElementById: id => lightboxNodes.get(id) ?? null,
  };
  const pending = [], timers = new Map(), opened = [];
  let now = 0, nextTimer = 1;
  const window = {
    setTimeout: (callback, delay = 0) => { const id = nextTimer++; timers.set(id, { callback, due: now + delay }); return id; },
    clearTimeout: id => timers.delete(id),
    matchMedia: query => ({ matches: query.includes('prefers-reduced-motion') ? reducedMotion : true }),
    MedReviewHeroDrag: drag ? { create: options => { drag.options = options; return drag; } } : undefined,
  };
  class MockImage {
    constructor() { pending.push(this); this.src = ''; this.decode = () => Promise.resolve(); }
  }
  const metadata = Object.fromEntries(images.map((url, index) => [url, index === 0 ? { alt: 'Mặt tiền thực tế' } : index === 1 ? { caption: 'Phòng chờ sáng' } : { angle: 'Phòng điều trị' }]));
  const uiText = { photo: 'Ảnh', photoOf: ' của ', viewPhoto: 'Xem ảnh ', photoInGallery: ' trong ', photoInCollection: ' trong bộ sưu tập của ', photoLoadFailed: 'Không thể tải ảnh này.' };
  const declarations = ('const galleryTriggers =' + variables).replace(/<\?php[\s\S]*?\?>/g, JSON.stringify('Cơ sở thử nghiệm'));
  // Reuse exact loading/click handlers; real gestures are browser-tested separately.
  const clickBindings = 'galleryTriggers.forEach' + clicks;
  const program = declarations + functions + navigation + '\nif (galleryReady) {' + clickBindings + '\n}\n' +
    'globalThis.heroApi = {syncHeroGallery, moveHeroGallery, updateHeroGalleryUI, state: () => ({committed:heroGalleryIndex,requested:heroGalleryRequestedIndex,request:heroImageRequest})};';
  const context = { document, window, Image: MockImage, gallerySources: images, galleryMetadata: metadata, uiText,
    openGallery: (index, trigger) => opened.push({ index, trigger }) };
  vm.runInNewContext(program, context, { filename: 'co-so-y-te-chi-tiet.php (isolated hero gallery)' });
  const tick = async milliseconds => {
    const end = now + milliseconds;
    for (;;) {
      const first = [...timers].filter(([, timer]) => timer.due <= end).sort((a, b) => a[1].due - b[1].due)[0];
      if (!first) break;
      now = first[1].due; timers.delete(first[0]); first[1].callback(); await settle();
    }
    now = end; await settle();
  };
  return { images, image, hero, counter, status, previous, next, thumbs, lightbox, pending, timers, opened, api: context.heroApi, tick,
    load: async (index = pending.length - 1) => { await pending[index].onload(); await settle(); },
    fail: async (index = pending.length - 1) => { pending[index].onerror(); await settle(); },
  };
}

(async () => {
  const initial = harness();
  same(initial.pending.length, 0, 'Server-rendered hero does not trigger another initial image request');
  same(initial.counter.textContent, '1 / 6', 'Initial counter describes the already-visible bitmap');
  same(initial.image.alt, 'Mặt tiền thực tế', 'Initial metadata alt is preserved');
  same(initial.hero.dataset.galleryIndex, '0', 'Initial lightbox target stays on the shown photo');
  same(initial.status.textContent, '', 'Initial server-rendered photo is not announced again');
  check(!initial.previous.disabled && !initial.next.disabled, 'Navigation is available for multiple photos');

  initial.next.emit('click');
  same(initial.pending[0].src, '/images/facility-2.jpg', 'Next requests the following photo');
  same(initial.api.state().committed, 0, 'Pending target is not committed before load');
  same(initial.hero.dataset.galleryIndex, '0', 'Pending target cannot change lightbox index');
  same(initial.counter.textContent, '1 / 6', 'Pending target cannot mislabel the counter');
  same(initial.hero.getAttribute('aria-busy'), 'true', 'Pending image is announced busy');
  await initial.load();
  same(initial.api.state().committed, 1, 'Successful load commits target');
  same(initial.image.src, '/images/facility-2.jpg', 'Loaded bitmap becomes visible');
  same(initial.image.alt, 'Phòng chờ sáng', 'Caption is the fallback alt when explicit alt is missing');
  same(initial.counter.textContent, '2 / 6', 'Counter changes only after successful image load');
  same(initial.hero.dataset.galleryIndex, '1', 'Lightbox index follows displayed image after load');
  same(initial.hero.getAttribute('aria-busy'), 'false', 'Successful image is no longer busy');
  same(initial.status.textContent, 'Ảnh 2 trong 6', 'Successful navigation announces the actual new photo to screen readers');
  check(initial.thumbs.find(thumb => thumb.dataset.galleryIndex === '1').classList.contains('is-active'), 'Active preview uses the full-list photo index');
  same(initial.thumbs.reduce((count, thumb) => count + thumb.scrolls.length, 0), 0, 'Changing the hero never scrolls an all-visible thumbnail grid or the page');
  await initial.tick(180);
  check(!initial.hero.classList.contains('is-changing'), 'Transition state is removed after successful display');

  const rapid = harness(); rapid.next.emit('click'); rapid.next.emit('click');
  same(rapid.pending.map(image => image.src), ['/images/facility-2.jpg', '/images/facility-3.jpg'], 'Rapid navigation can advance past an image still loading');
  await rapid.load(0);
  same(rapid.image.src, '/images/facility-1.jpg', 'Stale load cannot replace the currently requested photo');
  check(rapid.hero.classList.contains('is-changing'), 'Stale load cannot clear the current pending indicator');
  await rapid.load(1);
  same(rapid.image.src, '/images/facility-3.jpg', 'Most recent request wins');
  same(rapid.counter.textContent, '3 / 6', 'Counter reflects the final rapid selection');
  await rapid.fail(0);
  same(rapid.status.textContent, 'Ảnh 3 trong 6', 'Stale failure cannot overwrite the newer successful photo announcement');

  const decodeRace = harness(); let finishOldDecode;
  decodeRace.next.emit('click'); decodeRace.pending[0].decode = () => new Promise(resolve => { finishOldDecode = resolve; });
  const waiting = decodeRace.pending[0].onload(); await settle();
  decodeRace.next.emit('click'); await decodeRace.load(1); finishOldDecode(); await waiting; await settle();
  same(decodeRace.image.src, '/images/facility-3.jpg', 'An old asynchronous decode cannot override a newer loaded image');

  const failed = harness(); failed.next.emit('click'); await failed.fail();
  same(failed.api.state().committed, 0, 'Failed image is not committed');
  same(failed.image.src, '/images/facility-1.jpg', 'Failure retains old visible bitmap');
  same(failed.counter.textContent, '1 / 6', 'Failure retains old counter');
  same(failed.hero.dataset.galleryIndex, '0', 'Failure retains correct lightbox target');
  same(failed.status.textContent, 'Không thể tải ảnh này.', 'Current failure is announced');
  same(failed.hero.getAttribute('aria-busy'), 'false', 'Failed request releases busy state');
  failed.hero.emit('click'); same(failed.opened[0].index, 0, 'Click after failure opens the displayed photo, not the broken pending photo');
  failed.next.emit('click'); same(failed.pending[1].src, '/images/facility-3.jpg', 'Next after a failed photo continues instead of repeatedly selecting the broken photo');
  await failed.load(1); same(failed.status.textContent, 'Ảnh 3 trong 6', 'Successful recovery replaces previous error with current photo announcement');

  const cancel = harness(); cancel.next.emit('click'); cancel.previous.emit('click');
  same(cancel.pending.length, 1, 'Returning to already displayed cover does not download it again');
  same(cancel.hero.getAttribute('aria-busy'), 'false', 'Returning to shown photo cancels visual loading');
  await cancel.load(0); same(cancel.api.state().committed, 0, 'Cancelled pending load cannot later change the cover');

  const nonzero = harness({ heroIndex: 2 });
  same(nonzero.counter.textContent, '3 / 6', 'Non-first server cover starts at its actual index');
  nonzero.next.emit('click'); await nonzero.load();
  same(nonzero.thumbs.filter(thumb => thumb.classList.contains('is-active')).map(thumb => Number(thumb.dataset.galleryIndex)), [3], 'Non-first hero uses original thumbnail indexes, not preview positions');
  nonzero.api.syncHeroGallery(-1); await nonzero.load(); same(nonzero.counter.textContent, '6 / 6', 'Negative index wraps to the last full-gallery photo');
  nonzero.next.emit('click'); await nonzero.load(); same(nonzero.counter.textContent, '1 / 6', 'Next wraps through the full gallery, not just rendered previews');

  const keyboard = harness();
  const right = keyboard.hero.emit('keydown', { key: 'ArrowRight' });
  check(right.defaultPrevented && keyboard.pending.length === 1, 'Focused hero supports explicit ArrowRight navigation');
  keyboard.lightbox.classList.add('is-open');
  const modalKey = keyboard.next.emit('keydown', { key: 'ArrowRight' });
  check(!modalKey.defaultPrevented && keyboard.pending.length === 1, 'Hero keyboard navigation does not compete with an open lightbox');
  keyboard.lightbox.classList.remove('is-open');
  const unrelated = keyboard.hero.emit('keydown', { key: 'Escape' }); check(!unrelated.defaultPrevented, 'Unrelated keys are not stolen');

  let suppressedClick = true, dragSyncs = 0, dragCancels = 0;
  const drag = { sync: () => { dragSyncs++; }, cancel: () => { dragCancels++; }, consumeClick: () => suppressedClick };
  const gesture = harness({ drag });
  const suppressed = gesture.hero.emit('click');
  check(suppressed.defaultPrevented && gesture.opened.length === 0, 'Finger-drag suppression prevents synthetic lightbox clicks');
  suppressedClick = false;
  gesture.hero.emit('click'); same(gesture.opened[0].index, 0, 'A normal tap still opens the displayed photo');
  let finishSnap;
  const snap = new Promise(resolve => { finishSnap = resolve; });
  const selected = drag.options.selectIndex(1, snap);
  check(!gesture.hero.classList.contains('is-changing'), 'Finger-following movement never adds the old fading/zoom effect');
  const decoding = gesture.pending[0].onload(); await settle();
  same(gesture.counter.textContent, '1 / 6', 'A loaded neighbor waits for the snap before committing index and counter');
  finishSnap(); await decoding; await selected;
  same(gesture.counter.textContent, '2 / 6', 'The final snapped bitmap, counter and lightbox index commit together');
  same(dragSyncs, 1, 'A committed photo recenters all drag panels synchronously');
  gesture.next.emit('click'); same(dragCancels, 1, 'Explicit navigation interrupts any finger-following motion');
  drag.options.cancelPending(); await gesture.load();
  same(gesture.counter.textContent, '2 / 6', 'Cancellation rejects the stale photo still loading after gesture interruption');

  const slow = harness();
  const timedOut = slow.api.syncHeroGallery(1, true, { beforeCommit: Promise.resolve() });
  await slow.tick(15000); same(await timedOut, false, 'A stalled image request eventually resolves as failure');
  same(slow.hero.getAttribute('aria-busy'), 'false', 'Timeout releases loading state');
  await slow.load(); same(slow.counter.textContent, '1 / 6', 'Late bitmap load after deadline cannot commit a timed-out photo');

  const single = harness({ count: 1 });
  check(single.previous.disabled && single.next.disabled, 'Single-image fixture disables extra navigation defensively');
  single.api.moveHeroGallery(1); single.api.syncHeroGallery(8);
  same(single.pending.length, 0, 'Single image never downloads itself during navigation');
  same(single.counter.textContent, '1 / 1', 'Single-image counter remains valid');
  single.hero.emit('click'); same(single.opened[0].index, 0, 'Single image can still open its full viewer');
  const empty = harness({ count: 0 }); empty.api.moveHeroGallery(1); empty.api.syncHeroGallery(5);
  same(empty.pending.length, 0, 'Empty gallery is a safe no-op');
  const sparse = harness({ noControls: true, noCounter: true, noStatus: true }); sparse.api.moveHeroGallery(1); await sparse.load();
  same(sparse.api.state().committed, 1, 'Optional controls/status/counter may be absent without breaking image loading');
  const broken = harness({ initialBroken: true }); same(broken.status.textContent, 'Không thể tải ảnh này.', 'Initially broken cover is announced without duplicate preload');
  const reduced = harness({ reducedMotion: true }); reduced.next.emit('click'); await reduced.load(); await reduced.tick(0);
  check(!reduced.hero.classList.contains('is-changing'), 'Reduced-motion users do not wait for a decorative transition');
  console.log(`Facility hero gallery behavior: ${checks} checks passed. Mock DOM/Image only; no browser/network/DB.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
