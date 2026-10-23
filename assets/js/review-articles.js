(function () {
  'use strict';
  var page = document.querySelector('[data-review-articles]');
  if (!page) return;

  // The photo rail is a native horizontal scroller; controls only enhance it.
  // Touch scrolling and the original image links also work without JavaScript.
  var photoSlider = page.querySelector('[data-review-photo-slider]');
  if (photoSlider) {
    var photoTrack = photoSlider.querySelector('[data-review-photo-track]');
    var sliderControls = photoSlider.querySelector('[data-review-slider-controls]');
    var sliderPrevious = photoSlider.querySelector('[data-review-slider-prev]');
    var sliderNext = photoSlider.querySelector('[data-review-slider-next]');
    var sliderPosition = photoSlider.querySelector('[data-review-slider-position]');
    var sliderCards = photoTrack ? Array.from(photoTrack.querySelectorAll('.ra-customer-card')) : [];
    if (photoTrack && sliderCards.length > 2 && sliderControls && sliderPrevious && sliderNext && sliderPosition) {
      var sliderStep = 0;
      var sliderMaximum = 0;
      var sliderFrame = false;
      var updateSliderPosition = function () {
        var offset = Math.max(0, Math.min(sliderMaximum, photoTrack.scrollLeft));
        var first = sliderStep > 0 ? Math.min(sliderCards.length - 2, Math.round(offset / sliderStep)) : 0;
        var label = (first + 1) + '–' + Math.min(first + 2, sliderCards.length) + ' / ' + sliderCards.length;
        if (sliderPosition.textContent !== label) sliderPosition.textContent = label;
        sliderPrevious.disabled = offset <= 1;
        sliderNext.disabled = offset >= sliderMaximum - 1;
      };
      var measureSlider = function () {
        sliderStep = Math.abs(sliderCards[1].offsetLeft - sliderCards[0].offsetLeft);
        sliderMaximum = Math.max(0, photoTrack.scrollWidth - photoTrack.clientWidth);
        sliderControls.hidden = sliderStep <= 0 || sliderMaximum <= 1;
        updateSliderPosition();
      };
      var scheduleSliderPosition = function () {
        if (sliderFrame) return;
        if (typeof window.requestAnimationFrame !== 'function') { updateSliderPosition(); return; }
        sliderFrame = true;
        window.requestAnimationFrame(function () {
          sliderFrame = false;
          updateSliderPosition();
        });
      };
      var moveSlider = function (direction) {
        if (sliderControls.hidden) return;
        var offset = Math.max(0, Math.min(sliderMaximum, photoTrack.scrollLeft));
        var destination = Math.max(0, Math.min(sliderMaximum, offset + direction * sliderStep * 2));
        var reduceMotion = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (typeof photoTrack.scrollTo === 'function') {
          photoTrack.scrollTo({ left: destination, behavior: reduceMotion ? 'auto' : 'smooth' });
        } else {
          photoTrack.scrollLeft = destination;
          updateSliderPosition();
        }
      };
      sliderPrevious.addEventListener('click', function () { moveSlider(-1); });
      sliderNext.addEventListener('click', function () { moveSlider(1); });
      photoTrack.addEventListener('scroll', scheduleSliderPosition, { passive: true });
      photoTrack.addEventListener('keydown', function (event) {
        // Image links and the lightbox retain their own keyboard behavior.
        if (event.target !== photoTrack || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || sliderControls.hidden) return;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
          event.preventDefault();
          moveSlider(event.key === 'ArrowLeft' ? -1 : 1);
        }
      });
      if (typeof window.ResizeObserver === 'function') {
        var sliderResizeObserver = new window.ResizeObserver(measureSlider);
        sliderResizeObserver.observe(photoTrack);
      } else if (typeof window.addEventListener === 'function') {
        window.addEventListener('resize', measureSlider, { passive: true });
      }
      measureSlider();
    }
  }

  var photos = Array.from(page.querySelectorAll('[data-review-photo]'));
  var photoDialog = page.querySelector('[data-review-photo-dialog]');
  if (photos.length && photoDialog && typeof photoDialog.showModal === 'function') {
    var photoIndex = 0;
    var photoTrigger = null;
    var photoImage = photoDialog.querySelector('[data-review-photo-image]');
    var photoStatus = photoDialog.querySelector('[data-review-photo-status]');
    var photoRetry = photoDialog.querySelector('[data-review-photo-retry]');
    var photoCaption = photoDialog.querySelector('[data-review-photo-caption]');
    var photoCounter = photoDialog.querySelector('[data-review-photo-counter]');
    var photoStage = photoDialog.querySelector('[data-review-photo-stage]') || photoDialog.querySelector('figure');
    var photoClose = photoDialog.querySelector('[data-review-photo-close]');
    var photoPrevious = photoDialog.querySelector('[data-review-photo-prev]');
    var photoNext = photoDialog.querySelector('[data-review-photo-next]');
    var photoRequest = 0;
    var oldOverflow = '';
    var overflowLocked = false;
    var photoGesture = null;
    var photoText = function (vi, en) { return page.dataset.locale === 'en' ? en : vi; };
    var setPhotoState = function (state) {
      photoDialog.dataset.photoState = state;
      photoImage.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
      if (photoRetry) photoRetry.hidden = state !== 'error';
      if (photoStatus) {
        photoStatus.hidden = state === 'ready' || !state;
        photoStatus.textContent = state === 'loading'
          ? photoText('Đang tải ảnh…', 'Loading photo…')
          : state === 'error' ? photoText('Chưa tải được ảnh. Bạn có thể thử lại.', 'This photo could not load. You can try again.') : '';
      }
    };
    var stopPhotoLoad = function () {
      photoRequest += 1;
      photoImage.onload = null;
      photoImage.onerror = null;
      photoImage.removeAttribute('src');
    };
    var showPhoto = function (index) {
      photoIndex = (index + photos.length) % photos.length;
      var photo = photos[photoIndex];
      var thumbnail = photo.querySelector('img');
      stopPhotoLoad();
      var request = photoRequest;
      // A separate image node makes late events from the previous URL harmless.
      var nextImage = photoImage.cloneNode(false);
      nextImage.removeAttribute('src');
      nextImage.removeAttribute('srcset');
      photoImage.replaceWith(nextImage);
      photoImage = nextImage;
      photoImage.alt = thumbnail ? thumbnail.alt : '';
      photoCaption.textContent = photo.dataset.caption || '';
      photoCounter.textContent = (photoIndex + 1) + ' / ' + photos.length;
      photoGesture = null;
      setPhotoState('loading');
      photoImage.onload = function () {
        if (request !== photoRequest) return;
        setPhotoState(photoImage.naturalWidth > 0 ? 'ready' : 'error');
      };
      photoImage.onerror = function () {
        if (request === photoRequest) setPhotoState('error');
      };
      photoImage.src = photo.href;
      if (photoImage.complete) photoImage.onload();
    };
    photos.forEach(function (photo, index) {
      photo.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        showPhoto(index);
        if (!photoDialog.open) {
          try {
            photoDialog.showModal();
          } catch (error) {
            stopPhotoLoad();
            setPhotoState('');
            return; // The original image link remains usable if a modal cannot open.
          }
          oldOverflow = document.body.style.overflow;
          document.body.style.overflow = 'hidden';
          overflowLocked = true;
        }
        photoTrigger = photo;
        event.preventDefault();
      });
    });
    photoClose.addEventListener('click', function () { photoDialog.close(); });
    photoPrevious.addEventListener('click', function () { showPhoto(photoIndex - 1); });
    photoNext.addEventListener('click', function () { showPhoto(photoIndex + 1); });
    photoPrevious.hidden = photos.length < 2;
    photoNext.hidden = photos.length < 2;
    if (photoRetry) photoRetry.addEventListener('click', function () {
      showPhoto(photoIndex);
      photoClose.focus({ preventScroll: true }); // Retry is now hidden; keep keyboard focus in the viewer.
    });
    photoDialog.addEventListener('keydown', function (event) {
      var target = event.target;
      if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey ||
          (target && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName)))) return;
      if (photos.length > 1 && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
        event.preventDefault();
        showPhoto(photoIndex + (event.key === 'ArrowLeft' ? -1 : 1));
      }
    });
    // Only a deliberate horizontal touch gesture changes photos. Browser scrolling
    // and pinch-to-zoom stay native; no touch event is cancelled or captured.
    var isPhotoZoomed = function () { return window.visualViewport && window.visualViewport.scale > 1.01; };
    var beginPhotoGesture = function (id, x, y) {
      photoGesture = photoDialog.open && photos.length > 1 && !isPhotoZoomed()
        ? { id: id, x: x, y: y, time: Date.now() } : null;
    };
    var endPhotoGesture = function (id, x, y) {
      var gesture = photoGesture;
      photoGesture = null;
      if (!gesture || gesture.id !== id || isPhotoZoomed() || !photoDialog.open) return;
      var dx = x - gesture.x;
      var dy = y - gesture.y;
      if (Date.now() - gesture.time < 900 && Math.abs(dx) >= 56 && Math.abs(dx) > Math.abs(dy) * 1.35) {
        showPhoto(photoIndex + (dx < 0 ? 1 : -1));
      }
    };
    if (photoStage && 'PointerEvent' in window) {
      photoStage.addEventListener('pointerdown', function (event) {
        if (event.pointerType !== 'touch' && event.pointerType !== 'pen') return;
        if (event.isPrimary === false || event.button !== 0) { photoGesture = null; return; }
        beginPhotoGesture(event.pointerId, event.clientX, event.clientY);
      }, { passive: true });
      photoStage.addEventListener('pointerup', function (event) { endPhotoGesture(event.pointerId, event.clientX, event.clientY); }, { passive: true });
      photoStage.addEventListener('pointercancel', function () { photoGesture = null; }, { passive: true });
    } else if (photoStage) {
      photoStage.addEventListener('touchstart', function (event) {
        if (event.touches.length !== 1) { photoGesture = null; return; }
        var touch = event.touches[0];
        beginPhotoGesture(touch.identifier, touch.clientX, touch.clientY);
      }, { passive: true });
      photoStage.addEventListener('touchmove', function (event) { if (event.touches.length !== 1) photoGesture = null; }, { passive: true });
      photoStage.addEventListener('touchend', function (event) {
        if (event.changedTouches.length !== 1) { photoGesture = null; return; }
        var touch = event.changedTouches[0];
        endPhotoGesture(touch.identifier, touch.clientX, touch.clientY);
      }, { passive: true });
      photoStage.addEventListener('touchcancel', function () { photoGesture = null; }, { passive: true });
    }
    photoDialog.addEventListener('click', function (event) { if (event.target === photoDialog) photoDialog.close(); });
    photoDialog.addEventListener('close', function () {
      if (overflowLocked) document.body.style.overflow = oldOverflow;
      overflowLocked = false;
      photoGesture = null;
      stopPhotoLoad();
      setPhotoState('');
      if (photoTrigger && photoTrigger.isConnected !== false) photoTrigger.focus({ preventScroll: true });
      photoTrigger = null;
    });
  }

  // A native details disclosure remains usable with JavaScript disabled.
  var filters = page.querySelector('[data-review-article-filters]');
  if (filters) {
    document.addEventListener('click', function (event) {
      if (filters.open && !filters.contains(event.target)) filters.open = false;
    });
    filters.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape' || !filters.open) return;
      filters.open = false;
      var summary = filters.querySelector('summary');
      if (summary) summary.focus();
    });
  }

  var tocLinks = Array.from(page.querySelectorAll('[data-review-article-toc] a[href^="#"]'));
  if (tocLinks.length && 'IntersectionObserver' in window) {
    var tracked = new Map();
    tocLinks.forEach(function (link) {
      var target = document.getElementById(link.getAttribute('href').slice(1));
      if (target) tracked.set(target.id, target);
    });
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        tocLinks.forEach(function (link) {
          var active = link.getAttribute('href') === '#' + entry.target.id;
          link.classList.toggle('is-current', active);
          if (active) link.setAttribute('aria-current', 'location');
          else link.removeAttribute('aria-current');
        });
      });
    }, { rootMargin: '-20% 0px -65% 0px', threshold: 0 });
    tracked.forEach(function (target) { observer.observe(target); });
  }

  var share = page.querySelector('[data-review-article-share]');
  var status = page.querySelector('[data-review-article-share-status]');
  if (share && status && (navigator.share || (navigator.clipboard && window.isSecureContext))) {
    share.hidden = false;
    share.addEventListener('click', async function () {
      if (share.disabled) return;
      share.disabled = true;
      status.textContent = '';
      try {
        var canonical = document.querySelector('link[rel="canonical"]');
        var url = canonical ? canonical.href : window.location.href;
        if (navigator.share) await navigator.share({ title: document.title, url: url });
        else {
          await navigator.clipboard.writeText(url);
          status.textContent = page.dataset.locale === 'en' ? 'Article link copied.' : 'Đã sao chép liên kết bài viết.';
        }
      } catch (error) {
        if (!error || error.name !== 'AbortError') {
          status.textContent = page.dataset.locale === 'en' ? 'Please copy the link from your address bar.' : 'Bạn có thể sao chép liên kết trên thanh địa chỉ.';
        }
      } finally {
        share.disabled = false;
      }
    });
  }
}());
