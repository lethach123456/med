(function () {
  'use strict';
  var page = document.querySelector('[data-review-articles]');
  if (!page) return;

  var photos = Array.from(page.querySelectorAll('[data-review-photo]'));
  var photoDialog = page.querySelector('[data-review-photo-dialog]');
  if (photos.length && photoDialog && typeof photoDialog.showModal === 'function') {
    var photoIndex = 0;
    var photoTrigger = null;
    var photoImage = photoDialog.querySelector('[data-review-photo-image]');
    var oldOverflow = '';
    var showPhoto = function (index) {
      photoIndex = (index + photos.length) % photos.length;
      var photo = photos[photoIndex];
      photoImage.src = photo.href;
      photoImage.alt = photo.querySelector('img').alt;
      photoDialog.querySelector('[data-review-photo-caption]').textContent = photo.dataset.caption || '';
      photoDialog.querySelector('[data-review-photo-counter]').textContent = (photoIndex + 1) + ' / ' + photos.length;
    };
    photos.forEach(function (photo, index) {
      photo.addEventListener('click', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault();
        photoTrigger = photo;
        showPhoto(index);
        oldOverflow = document.body.style.overflow;
        photoDialog.showModal();
        document.body.style.overflow = 'hidden';
      });
    });
    photoDialog.querySelector('[data-review-photo-close]').addEventListener('click', function () { photoDialog.close(); });
    photoDialog.querySelector('[data-review-photo-prev]').addEventListener('click', function () { showPhoto(photoIndex - 1); });
    photoDialog.querySelector('[data-review-photo-next]').addEventListener('click', function () { showPhoto(photoIndex + 1); });
    photoDialog.querySelector('[data-review-photo-prev]').hidden = photos.length < 2;
    photoDialog.querySelector('[data-review-photo-next]').hidden = photos.length < 2;
    photoDialog.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        showPhoto(photoIndex + (event.key === 'ArrowLeft' ? -1 : 1));
      }
    });
    photoDialog.addEventListener('click', function (event) { if (event.target === photoDialog) photoDialog.close(); });
    photoDialog.addEventListener('close', function () {
      document.body.style.overflow = oldOverflow;
      photoImage.removeAttribute('src');
      if (photoTrigger) photoTrigger.focus();
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
