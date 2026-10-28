/* Finger-following mobile cover carousel. The profile owns image loading/indexes. */
(function () {
  'use strict';

  function create({ element, sources, getIndex, selectIndex, cancelPending = () => {}, isDisabled = () => false, loadingText = 'Loading photo…' }) {
    const image = element?.querySelector('img');
    if (!image || sources.length < 2) return null;
    const mobile = window.matchMedia('(max-width: 767px)');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const panels = [];
    let gesture = null;
    let offset = 0;
    let token = 0;
    let timer = 0;
    let finishAnimation = null;
    let pending = false;
    let suppressUntil = 0;
    const now = () => performance.now();
    const normalize = index => (index % sources.length + sources.length) % sources.length;

    function setOffset(value) {
      offset = value;
      element.style.setProperty('--hero-drag-x', `${value}px`);
    }

    function stopAnimation() {
      window.clearTimeout(timer);
      timer = 0;
      element.classList.remove('is-settling');
      const finish = finishAnimation;
      finishAnimation = null;
      if (finish) finish();
    }

    function refreshPanels() {
      if (!mobile.matches) return;
      if (!panels.length) {
        [-1, 1].forEach(direction => {
          const panel = element.ownerDocument.createElement('img');
          panel.dataset.heroDragPanel = String(direction);
          panel.alt = '';
          panel.setAttribute('aria-hidden', 'true');
          panel.draggable = false;
          panel.decoding = 'async';
          element.appendChild(panel);
          panels.push(panel);
        });
        image.draggable = false;
      }
      panels.forEach(panel => {
        const source = sources[normalize(getIndex() + Number(panel.dataset.heroDragPanel))];
        if (panel.getAttribute('src') !== source) panel.src = source;
      });
      element.setAttribute('data-hero-drag-ready', '');
    }

    function sync() {
      ++token;
      stopAnimation();
      gesture = null;
      pending = false;
      element.classList.remove('is-dragging', 'is-awaiting-image');
      setOffset(0);
      refreshPanels();
      if (!mobile.matches) element.removeAttribute('data-hero-drag-ready');
    }

    function cancel() {
      const wasPending = pending;
      sync();
      if (wasPending) cancelPending();
    }

    function animateTo(value) {
      stopAnimation();
      // One layout read at release, never during touchmove. Start the transition
      // from the last finger position, not from a previously batched CSS update.
      element.getBoundingClientRect();
      element.classList.add('is-settling');
      setOffset(value);
      return new Promise(resolve => {
        finishAnimation = resolve;
        timer = window.setTimeout(() => {
          timer = 0;
          finishAnimation = null;
          element.classList.remove('is-settling');
          resolve();
        }, reduced.matches ? 0 : 280);
      });
    }

    function start(event) {
      if (!mobile.matches || isDisabled()) return;
      if (event.touches.length !== 1) {
        if (gesture?.axis === 'x') suppressUntil = now() + 420;
        cancel();
        return;
      }
      const touch = event.touches[0];
      // A new deliberate tap need not wait out the previous synthetic click.
      suppressUntil = 0;
      let visibleOffset = offset;
      if (element.classList.contains('is-settling')) {
        const transform = window.getComputedStyle(image).transform;
        if (transform && transform !== 'none') {
          const matrix = new window.DOMMatrixReadOnly(transform);
          visibleOffset = matrix.m41;
        }
      }
      cancel();
      // A button/keyboard change may also be decoding outside this controller.
      // Taking over with a finger must invalidate that request before dragging.
      cancelPending();
      setOffset(visibleOffset);
      gesture = {
        id: touch.identifier, x: touch.clientX, y: touch.clientY,
        base: visibleOffset, width: element.getBoundingClientRect().width,
        lastX: touch.clientX, lastTime: now(), velocity: 0, axis: null,
      };
    }

    function move(event) {
      if (!gesture) return;
      if (event.touches.length !== 1 || isDisabled()) {
        if (gesture.axis === 'x') suppressUntil = now() + 420;
        cancel();
        return;
      }
      const touch = Array.from(event.touches).find(item => item.identifier === gesture.id);
      if (!touch) { cancel(); return; }
      const dx = touch.clientX - gesture.x;
      const dy = touch.clientY - gesture.y;
      if (!gesture.axis) {
        if (Math.max(Math.abs(dx), Math.abs(dy)) < 6) return;
        if (Math.abs(dy) > Math.abs(dx) * 1.15) { cancel(); return; }
        if (Math.abs(dx) <= Math.abs(dy) * 1.15) return;
        gesture.axis = 'x';
        element.classList.add('is-dragging');
      }
      if (event.cancelable) event.preventDefault();
      const time = now();
      const elapsed = time - gesture.lastTime;
      if (elapsed > 0) gesture.velocity = (touch.clientX - gesture.lastX) / elapsed;
      gesture.lastX = touch.clientX;
      gesture.lastTime = time;
      setOffset(Math.max(-gesture.width, Math.min(gesture.width, gesture.base + dx)));
    }

    function end(event) {
      if (!gesture) return;
      if (event.touches.length) { cancel(); return; }
      const finished = gesture;
      gesture = null;
      if (finished.axis !== 'x') { cancel(); return; }
      element.classList.remove('is-dragging');
      suppressUntil = now() + 420;
      const velocity = now() - finished.lastTime < 100 ? finished.velocity : 0;
      const threshold = Math.min(96, Math.max(44, finished.width * .22));
      const flick = Math.abs(offset) >= 18 && Math.abs(velocity) >= .5 && Math.sign(velocity) === Math.sign(offset);
      const direction = Math.abs(offset) >= threshold || flick ? (offset < 0 ? 1 : -1) : 0;
      const request = ++token;
      const settled = animateTo(direction ? -direction * finished.width : 0);
      if (!direction) {
        settled.then(() => { if (request === token) sync(); });
        return;
      }
      pending = true;
      settled.then(() => {
        if (request === token && pending) element.classList.add('is-awaiting-image');
      });
      // Decode the target while it slides in. Commit only once BOTH the bitmap
      // and the snap are ready, so the counter/lightbox never get ahead of it.
      Promise.resolve(selectIndex(normalize(getIndex() + direction), settled)).then(success => {
        if (request !== token) return;
        pending = false;
        element.classList.remove('is-awaiting-image');
        if (success) sync();
        else animateTo(0).then(() => { if (request === token) sync(); });
      }).catch(() => {
        if (request !== token) return;
        pending = false;
        element.classList.remove('is-awaiting-image');
        animateTo(0).then(() => { if (request === token) sync(); });
      });
    }

    element.addEventListener('touchstart', start, { passive: true });
    element.addEventListener('touchmove', move, { passive: false });
    element.addEventListener('touchend', end, { passive: true });
    element.addEventListener('touchcancel', () => {
      if (gesture?.axis === 'x') suppressUntil = now() + 420;
      cancel();
    }, { passive: true });
    window.addEventListener('resize', cancel, { passive: true });
    mobile.addEventListener('change', cancel);
    element.dataset.heroDragLoading = loadingText;
    sync();
    return { sync, cancel, consumeClick: () => now() < suppressUntil };
  }

  window.MedReviewHeroDrag = { create };
})();
