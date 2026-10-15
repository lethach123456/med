<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../medical_public_assets.php';
require_once __DIR__ . '/../medical_media_variants.php';

$pageKey = site_current_page_key();
$forcedLocale = trim((string) ($GLOBALS['site_forced_locale'] ?? ''));
$locale = in_array($forcedLocale, ['vi', 'en'], true) ? $forcedLocale : site_page_locale($pageKey);
$isEnglish = $locale === 'en';
$langLinks = is_array($GLOBALS['site_language_links'] ?? null)
  ? $GLOBALS['site_language_links']
  : site_language_switch_links($pageKey);
// The legacy Vietnamese custom-about slug is no longer routed by the public
// web server. Keep every language control on the real MedReview page.
if ($pageKey === 'about' && !$isEnglish) {
  $langLinks['vi'] = '/ve-chung-toi';
}
$homePath = site_localized_path('/', $locale);
$blogPath = front_editor_page_public_path($isEnglish ? 'blog-en' : 'blog');
$aboutPath = site_localized_path('/ve-chung-toi.php', $locale);
$contactPath = front_editor_page_public_path($isEnglish ? 'contact-en' : 'contact');
$facilitiesPath = site_localized_path(medical_public_facility_path(), $locale);
$doctorsPath = site_localized_path('/bac-si.php', $locale);
$reviewPath = site_localized_path('/review.php', $locale);
$toplistPath = site_localized_path(medical_public_toplist_path(), $locale);
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';

$brandName = site_title('MedReview');
$brandIconUrl = site_icon_href('');
if ($brandIconUrl === '') {
  $brandIconUrl = 'https://medreview.vn/uploads/library/2026/07/fbef23f192e21b0669c670803723ec66.jpg';
}
// Prefer the local copy of a MedReview upload when available. This keeps the
// header logo visible in local previews even when the remote image is blocked.
if (preg_match('~^https?://(?:www\.)?medreview\.vn(/uploads/[a-zA-Z0-9/_-]+\.(?:jpe?g|png|webp|svg))(?:\?.*)?$~i', $brandIconUrl, $brandIconMatch)
    && is_file(dirname(__DIR__) . $brandIconMatch[1])) {
  $brandIconUrl = $brandIconMatch[1];
}
$hasBrandIcon = $brandIconUrl !== '';
$isMedReviewWordmark = strcasecmp($brandName, 'MedReview') === 0;
$brandTagline = $isEnglish ? 'Verified medical review community' : 'Cộng đồng review y tế đáng tin cậy';
$desktopMenuLabel = $isEnglish ? 'Main navigation' : 'Điều hướng chính';
$mobileMenuLabel = $isEnglish ? 'Mobile navigation' : 'Điều hướng di động';
$loginLabel = $isEnglish ? 'Log in' : 'Đăng nhập';
$searchPlaceholder = $isEnglish ? 'Search service, doctor, clinic...' : 'Tìm kiếm dịch vụ, bác sĩ, phòng khám...';
$providerLinkLabel = $isEnglish ? 'For healthcare providers' : 'Dành cho cơ sở y tế';

$navItems = $isEnglish
  ? [
      ['label' => 'Home', 'href' => $homePath, 'match' => (string) (parse_url($homePath, PHP_URL_PATH) ?: '/')],
      ['label' => 'Healthcare facilities', 'href' => $facilitiesPath, 'match' => $facilitiesPath],
      ['label' => 'Doctors', 'href' => $doctorsPath, 'match' => $doctorsPath],
      ['label' => 'Reviews', 'href' => $reviewPath, 'match' => $reviewPath],
      ['label' => 'Toplist', 'href' => $toplistPath, 'match' => $toplistPath],
      ['label' => 'About', 'href' => $aboutPath, 'match' => parse_url($aboutPath, PHP_URL_PATH) ?: $aboutPath],
    ]
  : [
      ['label' => 'Trang chủ', 'href' => $homePath, 'match' => '/'],
      ['label' => 'Cơ sở y tế', 'href' => $facilitiesPath, 'match' => $facilitiesPath],
      ['label' => 'Bác sĩ', 'href' => $doctorsPath, 'match' => $doctorsPath],
      ['label' => 'Review', 'href' => $reviewPath, 'match' => $reviewPath],
      ['label' => 'Toplist', 'href' => $toplistPath, 'match' => $toplistPath],
      ['label' => 'Về chúng tôi', 'href' => $aboutPath, 'match' => parse_url($aboutPath, PHP_URL_PATH) ?: $aboutPath],
    ];
?>
<?php echo medical_public_shell_styles(); ?>
<!-- Shared navigation and motion CSS are cached in the public shell bundle. -->
<div class="medical-header-utility">
  <div class="container">
    <span><i class="ph ph-shield-check" aria-hidden="true"></i><?php echo htmlspecialchars($isEnglish ? 'Transparent information. Better choices.' : 'Thông tin minh bạch. Lựa chọn tốt hơn.', ENT_QUOTES, 'UTF-8'); ?></span>
    <a href="<?php echo htmlspecialchars($contactPath . '?topic=provider#gui-lien-he', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($providerLinkLabel, ENT_QUOTES, 'UTF-8'); ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
  </div>
</div>
<header class="medical-header">
  <div class="container">
    <div class="header-shell">
      <a class="brand-link<?php echo $hasBrandIcon ? ' has-image-brand' : ''; ?>" href="<?php echo htmlspecialchars($homePath, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="brand-mark<?php echo $hasBrandIcon ? ' is-image' : ''; ?>" aria-hidden="true">
          <?php if ($hasBrandIcon): ?><img <?php echo medical_media_image_attributes($brandIconUrl, '53px', [64, 128], 64); ?> alt="" width="53" height="53" loading="eager" decoding="async" onload="this.parentElement.classList.add('brand-image-loaded');" onerror="var mark=this.parentElement;this.remove();if(mark)mark.classList.remove('is-image','brand-image-loaded');"><?php endif; ?>
          <i class="ph ph-heart"></i>
        </span>
        <span class="brand-copy">
          <strong><?php if ($isMedReviewWordmark): ?><span class="brand-wordmark"><span class="brand-wordmark-med">Med</span><span class="brand-wordmark-review">Review</span></span><?php else: ?><?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></strong>
          <span class="brand-tagline"><?php echo htmlspecialchars($brandTagline, ENT_QUOTES, 'UTF-8'); ?></span>
          <small><i class="ph-fill ph-seal-check"></i> <?php echo $isEnglish ? 'Verified reviews' : 'Review xác thực'; ?></small>
        </span>
      </a>
      <div class="header-center">
        <nav class="header-nav" aria-label="<?php echo htmlspecialchars($desktopMenuLabel, ENT_QUOTES, 'UTF-8'); ?>">
          <?php foreach ($navItems as $item): ?>
            <?php $matchPath = (string) ($item['match'] ?? ''); ?>
            <?php $isDesktopActive = $matchPath !== '' && ($requestPath === $matchPath || ($pageKey === 'reviews' && $matchPath === $reviewPath)); ?>
            <a href="<?php echo htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isDesktopActive ? 'is-active' : ''; ?>"<?php echo $isDesktopActive ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8'); ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
      <div class="header-actions">
        <div class="medical-search-shell header-search-shell" data-medical-search>
          <button class="mobile-search-trigger" type="button" aria-label="<?php echo $isEnglish ? 'Open search' : 'Mở tìm kiếm'; ?>" aria-expanded="false" aria-controls="medical-header-search"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></button>
          <div class="header-search" id="medical-header-search">
            <i class="ph ph-magnifying-glass"></i>
            <input type="search" data-medical-search-input aria-label="<?php echo $isEnglish ? 'Search services, doctors, and clinics' : 'Tìm cơ sở y tế, bác sĩ và dịch vụ'; ?>" autocomplete="off" placeholder="<?php echo htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>">
            <button class="mobile-search-close" type="button" aria-label="<?php echo $isEnglish ? 'Close search' : 'Đóng tìm kiếm'; ?>"><i class="ph ph-x" aria-hidden="true"></i></button>
          </div>
          <div class="medical-search-results" data-medical-search-results hidden></div>
        </div>
        <details class="mobile-language" data-mobile-language>
          <summary aria-label="<?php echo $isEnglish ? 'Choose language' : 'Chọn ngôn ngữ'; ?>" aria-expanded="false" aria-controls="medical-mobile-language-panel"><i class="ph ph-globe" aria-hidden="true"></i><span class="lang-code"><?php echo $isEnglish ? 'EN' : 'VI'; ?></span><i class="ph ph-caret-down language-chevron" aria-hidden="true"></i></summary>
          <span class="mobile-language-panel" id="medical-mobile-language-panel">
            <a href="<?php echo htmlspecialchars((string) ($langLinks['vi'] ?? '/'), ENT_QUOTES, 'UTF-8'); ?>" lang="vi" class="<?php echo (($langLinks['current'] ?? 'vi') === 'vi') ? 'is-active' : ''; ?>">Tiếng Việt<?php if (($langLinks['current'] ?? 'vi') === 'vi'): ?><i class="ph ph-check" aria-hidden="true"></i><?php endif; ?></a>
            <a href="<?php echo htmlspecialchars((string) ($langLinks['en'] ?? '/services'), ENT_QUOTES, 'UTF-8'); ?>" lang="en" class="<?php echo (($langLinks['current'] ?? 'vi') === 'en') ? 'is-active' : ''; ?>">English<?php if (($langLinks['current'] ?? 'vi') === 'en'): ?><i class="ph ph-check" aria-hidden="true"></i><?php endif; ?></a>
          </span>
        </details>
        <a class="header-login" href="<?php echo htmlspecialchars($contactPath, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($loginLabel, ENT_QUOTES, 'UTF-8'); ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        <details class="header-mobile" data-mobile-menu>
          <summary class="mobile-summary" aria-label="<?php echo htmlspecialchars($mobileMenuLabel, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false" aria-controls="medical-mobile-menu-panel"><i class="ph ph-list" aria-hidden="true"></i></summary>
          <div class="mobile-panel" id="medical-mobile-menu-panel">
            <?php foreach ($navItems as $index => $item): ?>
              <?php $mobileMatchPath = (string) ($item['match'] ?? ''); ?>
              <?php $isMobileActive = $mobileMatchPath !== '' && ($requestPath === $mobileMatchPath || ($pageKey === 'reviews' && $mobileMatchPath === $reviewPath)); ?>
              <?php $mobileLabel = (!$isEnglish && $index === count($navItems) - 1) ? 'Về MedReview' : (string) $item['label']; ?>
              <a href="<?php echo htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isMobileActive ? 'is-active' : ''; ?>"<?php echo $isMobileActive ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars($mobileLabel, ENT_QUOTES, 'UTF-8'); ?><i class="ph <?php echo $index === 0 ? 'ph-arrow-right' : 'ph-arrow-up-right'; ?>" aria-hidden="true"></i></a>
            <?php endforeach; ?>
            <div class="menu-bottom">
              <a class="header-login" href="<?php echo htmlspecialchars($contactPath, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($isEnglish ? 'Join the community' : 'Tham gia cộng đồng', ENT_QUOTES, 'UTF-8'); ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
            </div>
          </div>
        </details>
      </div>
    </div>
  </div>
</header>
<span class="mobile-popover-backdrop" aria-hidden="true"></span>
<script>
  (function(){
    var header = document.querySelector('.medical-header');
    if (!header) return;

    var mobile = header.querySelector('[data-mobile-menu]');
    var language = header.querySelector('[data-mobile-language]');
    var searchShell = header.querySelector('.header-search-shell[data-medical-search]');
    var searchTrigger = searchShell ? searchShell.querySelector('.mobile-search-trigger') : null;
    var searchClose = searchShell ? searchShell.querySelector('.mobile-search-close') : null;
    var searchInput = searchShell ? searchShell.querySelector('[data-medical-search-input]') : null;
    var searchResults = searchShell ? searchShell.querySelector('[data-medical-search-results]') : null;
    var backdrop = document.querySelector('.mobile-popover-backdrop');
    var mobileQuery = window.matchMedia ? window.matchMedia('(max-width: 1020px)') : null;

    function isPhone(){
      return !mobileQuery || mobileQuery.matches;
    }
    function syncDetails(details){
      if (!details) return;
      var open = details.hasAttribute('open');
      details.classList.toggle('is-open', open);
      var summary = details.querySelector('summary');
      if (summary) summary.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (details === mobile && summary) {
        var icon = summary.querySelector('i');
        if (icon) {
          icon.classList.toggle('ph-list', !open);
          icon.classList.toggle('ph-x', open);
        }
      }
    }
    function syncBackdrop(){
      var searchOpen = Boolean(searchShell && (
        searchShell.classList.contains('is-mobile-search-open') ||
        searchShell.classList.contains('is-desktop-search-open')
      ));
      var hasPopover = Boolean((mobile && mobile.hasAttribute('open')) || (language && language.hasAttribute('open')) || searchOpen);
      header.classList.toggle('has-mobile-search', searchOpen);
      header.classList.toggle('has-mobile-popover', hasPopover);
    }
    function closeResults(){
      if (!searchResults) return;
      if (searchResults.hidden || !searchResults.childElementCount) {
        searchResults.hidden = true;
        searchResults.innerHTML = '';
        searchResults.classList.remove('is-closing');
        searchResults.removeAttribute('inert');
        return;
      }
      searchResults.classList.add('is-closing');
      searchResults.setAttribute('inert', '');
      window.setTimeout(function(){
        if (!searchResults.classList.contains('is-closing')) return;
        searchResults.hidden = true;
        searchResults.innerHTML = '';
        searchResults.classList.remove('is-closing');
      }, 170);
    }
    function closeSearch(){
      if (!searchShell) return;
      searchShell.classList.remove('is-mobile-search-open');
      searchShell.classList.remove('is-desktop-search-open');
      searchShell.classList.remove('is-open');
      if (searchTrigger) searchTrigger.setAttribute('aria-expanded', 'false');
      closeResults();
      if (searchInput && document.activeElement === searchInput) searchInput.blur();
      syncBackdrop();
    }
    function closeDetails(except){
      [mobile, language].forEach(function(details){
        if (details && details !== except) details.removeAttribute('open');
      });
      syncDetails(mobile);
      syncDetails(language);
      syncBackdrop();
    }
    function openSearch(){
      if (!searchShell || !isPhone()) return;
      closeDetails();
      searchShell.classList.add('is-mobile-search-open');
      if (searchTrigger) searchTrigger.setAttribute('aria-expanded', 'true');
      syncBackdrop();
      window.requestAnimationFrame(function(){
        if (searchInput) searchInput.focus({preventScroll:true});
      });
    }

    [mobile, language].forEach(function(details){
      if (!details) return;
      details.addEventListener('toggle', function(){
        syncDetails(details);
        if (details.hasAttribute('open')) {
          closeSearch();
          closeDetails(details);
        }
        syncBackdrop();
      });
      details.querySelectorAll('a').forEach(function(link){
        link.addEventListener('click', function(){
          details.removeAttribute('open');
          syncDetails(details);
          syncBackdrop();
        });
      });
      syncDetails(details);
    });

    if (searchTrigger) {
      searchTrigger.addEventListener('click', function(event){
        event.preventDefault();
        var homeSearch = document.querySelector('.med-home.hc-home .hero-search-shell');
        var homeInput = homeSearch ? homeSearch.querySelector('[data-medical-search-input]') : null;
        if (document.body.classList.contains('site-home') && homeSearch && homeInput) {
          closeSearch();
          closeDetails();
          homeSearch.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
            block: 'start'
          });
          window.requestAnimationFrame(function(){ homeInput.focus({preventScroll:true}); });
          return;
        }
        if (searchShell && searchShell.classList.contains('is-mobile-search-open')) closeSearch();
        else openSearch();
      });
    }
    if (searchClose) {
      searchClose.addEventListener('click', function(event){
        event.preventDefault();
        closeSearch();
        if (searchTrigger) searchTrigger.focus({preventScroll:true});
      });
    }
    if (searchInput) {
      searchInput.addEventListener('focus', function(){
        if (isPhone()) {
          if (!searchShell || !searchShell.classList.contains('is-mobile-search-open')) openSearch();
          return;
        }
        closeDetails();
        searchShell.classList.add('is-desktop-search-open');
        syncBackdrop();
      });
      searchShell.addEventListener('focusout', function(){
        window.setTimeout(function(){
          if (searchShell.classList.contains('is-desktop-search-open') && !searchShell.contains(document.activeElement)) closeSearch();
        }, 0);
      });
      searchInput.addEventListener('keydown', function(event){
        if (event.key === 'Escape') {
          event.preventDefault();
          closeSearch();
        }
      });
    }

    if (backdrop) {
      backdrop.addEventListener('click', function(){
        closeSearch();
        closeDetails();
      });
    }
    document.addEventListener('click', function(event){
      if (searchShell && !searchShell.contains(event.target) && searchShell.classList.contains('is-desktop-search-open')) {
        closeSearch();
      }
    });
    document.addEventListener('click', function(event){
      if (header.contains(event.target)) return;
      closeSearch();
      closeDetails();
    });
    document.addEventListener('keydown', function(event){
      if (event.key !== 'Escape') return;
      closeSearch();
      closeDetails();
    });
    if (mobileQuery) {
      var resetForDesktop = function(event){
        if (event.matches) return;
        closeSearch();
        closeDetails();
      };
      if (typeof mobileQuery.addEventListener === 'function') mobileQuery.addEventListener('change', resetForDesktop);
      else if (typeof mobileQuery.addListener === 'function') mobileQuery.addListener(resetForDesktop);
    }
  })();
</script>
<script>
  (function(){
    var root = document.documentElement;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var supportsNativeTransitions = !reduceMotion
      && 'startViewTransition' in document
      && typeof window.CSS !== 'undefined'
      && typeof window.CSS.supports === 'function'
      && window.CSS.supports('view-transition-name: none');

    root.classList.add('med-motion-ready');
    if (reduceMotion || supportsNativeTransitions) return;

    /* Browsers without cross-document View Transitions still get a very short
       exit/enter cue. This only handles ordinary same-origin document links;
       anchors, downloads, forms, new tabs and external destinations are left
       entirely to the browser. */
    root.classList.add('med-motion-fallback-ready');
    var isNavigating = false;

    function isOrdinaryDocumentLink(link, event){
      if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
      if (link.hasAttribute('download') || link.getAttribute('aria-disabled') === 'true') return false;
      if (link.closest('form,[data-no-page-transition],[data-medical-search]')) return false;
      var target = (link.getAttribute('target') || '').toLowerCase();
      if (target && target !== '_self') return false;
      var rel = (link.getAttribute('rel') || '').toLowerCase();
      if (/(^|\\s)external(\\s|$)/.test(rel)) return false;

      var rawHref = (link.getAttribute('href') || '').trim();
      if (!rawHref || rawHref.charAt(0) === '#') return false;
      if (/^(?:javascript|mailto|tel|sms|data):/i.test(rawHref)) return false;

      var destination;
      try {
        destination = new URL(link.href, window.location.href);
      } catch (error) {
        return false;
      }
      if (destination.origin !== window.location.origin || !/^https?:$/.test(destination.protocol)) return false;

      var current = new URL(window.location.href);
      /* Do not interrupt an in-page anchor, including a full path that points
         back to the exact current document. */
      if (destination.pathname === current.pathname && destination.search === current.search) return false;
      return destination;
    }

    document.addEventListener('click', function(event){
      if (isNavigating || !event.isTrusted) return;
      var element = event.target;
      var link = element && element.closest ? element.closest('a[href]') : null;
      var destination = isOrdinaryDocumentLink(link, event);
      if (!destination) return;

      isNavigating = true;
      event.preventDefault();
      root.classList.add('med-motion-fallback-leaving');
      window.setTimeout(function(){ window.location.assign(destination.href); }, 145);
    }, false);

    window.addEventListener('pageshow', function(){
      isNavigating = false;
      root.classList.remove('med-motion-fallback-leaving');
    });
  })();
</script>
<script>
  (function(){
    function decoratePublicInteractions(){
      var cardSelector = [
        'main .facility-card', 'main .doctor-card', 'main .toplist-card',
        'main .category-card', 'main .article-card', 'main .service-card',
        'main .review-card', 'main .project-card', 'main .post-card'
      ].join(',');
      var tapSelector = [
        'button', '[role="button"]', 'input[type="submit"]', 'input[type="button"]',
        '.btn', '.button', '.cta', '.detail-btn', '.section-link',
        '.header-login', '.header-nav a', '.lang-switch a',
        '.mobile-summary', '.mobile-language summary'
      ].join(',');

      document.querySelectorAll(cardSelector).forEach(function(element){
        element.classList.add('med-motion-card');
      });
      document.querySelectorAll(tapSelector).forEach(function(element){
        element.classList.add('med-motion-tap');
      });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', decoratePublicInteractions, {once:true});
    else decoratePublicInteractions();
  })();
</script>
<?php $medicalGlobalSearchScript = __DIR__ . '/../assets/js/medical-global-search.js'; ?>
<script src="/assets/js/medical-global-search.js?v=<?= file_exists($medicalGlobalSearchScript) ? (int) filemtime($medicalGlobalSearchScript) : 1 ?>" defer></script>
