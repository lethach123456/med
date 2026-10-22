(function () {
  'use strict';
  var page = document.querySelector('[data-review-articles]');
  if (!page) return;

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
