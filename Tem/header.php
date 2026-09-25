<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';

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
  $langLinks['vi'] = '/ve-chung-toi.php';
}
$homePath = site_localized_path('/', $locale);
$blogPath = front_editor_page_public_path($isEnglish ? 'blog-en' : 'blog');
$aboutPath = site_localized_path('/ve-chung-toi.php', $locale);
$contactPath = front_editor_page_public_path($isEnglish ? 'contact-en' : 'contact');
$facilitiesPath = site_localized_path(medical_public_facility_path(), $locale);
$doctorsPath = site_localized_path('/bac-si.php', $locale);
$reviewPath = site_localized_path('/review.php', $locale);
$toplistPath = site_localized_path(medical_public_toplist_path(), $locale);
$uiStylesheetPath = __DIR__ . '/../assets/css/core/ui-2026.css';
$uiStylesheetVersion = is_file($uiStylesheetPath) ? (string) filemtime($uiStylesheetPath) : '1';
$brandRefreshStylesheetPath = __DIR__ . '/../assets/css/pages/brand-home-refresh.css';
$brandRefreshStylesheetVersion = is_file($brandRefreshStylesheetPath) ? (string) filemtime($brandRefreshStylesheetPath) : '1';
$headerMatchStylesheetPath = __DIR__ . '/../assets/css/pages/brand-header-match.css';
$headerMatchStylesheetVersion = is_file($headerMatchStylesheetPath) ? (string) filemtime($headerMatchStylesheetPath) : '1';
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
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="/assets/css/core/ui-2026.css?v=<?php echo htmlspecialchars($uiStylesheetVersion, ENT_QUOTES, 'UTF-8'); ?>">
<style>
  .medical-header{
    position: sticky;
    top: 0;
    z-index: 1000;
    background: rgba(255,255,255,0.95);
    border-bottom: 1px solid rgba(226,232,240,0.9);
    backdrop-filter: blur(18px);
  }
  /* Navigation uses buttons/labels, not editorial links. Keep browser defaults from underlining it. */
  .medical-header a,
  .medical-header a:hover,
  .medical-header a:focus{
    text-decoration: none !important;
  }
  .medical-header .header-shell{
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 18px;
    min-height: 82px;
  }
  .medical-header .brand-link{
    display: inline-flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    position: relative;
  }
  .medical-header .brand-mark{
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background: linear-gradient(180deg, #ffffff 0%, #eef5ff 100%);
    border: 1.5px solid rgba(59,130,246,0.3);
    box-shadow: 0 12px 24px rgba(59,130,246,0.12);
    color: #3b82f6;
    font-size: 22px;
  }
  .medical-header .brand-mark i{
    color: #286bf1;
  }
  .medical-header .brand-mark::after{
    content: "+";
    position: absolute;
    right: -4px;
    top: -3px;
    width: 16px;
    height: 16px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #3b82f6;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    box-shadow: 0 8px 14px rgba(59,130,246,0.22);
  }
  .medical-header .brand-copy{
    display: grid;
    gap: 3px;
    min-width: 0;
  }
  .medical-header .brand-copy strong{
    font-size: 22px;
    line-height: 1;
    letter-spacing: -0.04em;
    color: #2563eb;
    font-weight: 800;
  }
  .medical-header .brand-copy > .brand-tagline{
    font-size: 11px;
    line-height: 1.45;
    color: rgba(15,23,42,0.55);
    font-weight: 600;
  }
  .medical-header .brand-copy small{
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    padding: 4px 9px;
    border-radius: 999px;
    background: rgba(37,99,235,0.08);
    color: #1d4ed8;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.02em;
  }
  .medical-header .brand-mark.is-image{
    overflow:hidden;
    background:transparent;
    border-color:transparent;
    box-shadow:none;
  }
  .medical-header .brand-mark.is-image::after{display:none}
  .medical-header .brand-mark.is-image i{display:none}
  .medical-header .brand-mark img{
    display:block;
    width:100%;
    height:100%;
    object-fit:contain;
  }
  /* The wordmark has nested spans, so never let the tagline rule style or hide it. */
  .medical-header .brand-copy strong .brand-wordmark{
    display:inline-flex!important;
    align-items:baseline;
    white-space:nowrap;
    font:inherit;
    line-height:inherit;
    letter-spacing:inherit;
  }
  .medical-header .brand-copy strong .brand-wordmark-med,
  .medical-header .brand-copy strong .brand-wordmark-review{
    display:inline!important;
    font:inherit;
    line-height:inherit;
    letter-spacing:inherit;
  }
  .medical-header .brand-copy strong .brand-wordmark-med{color:#0f2747!important}
  .medical-header .brand-copy strong .brand-wordmark-review{color:#2563ff!important}
  .medical-header .header-center{
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 18px;
    min-width: 0;
  }
  .medical-header .header-nav{
    display: flex;
    align-items: center;
    gap: 2px;
    min-width: 0;
  }
  .medical-header .header-nav a{
    padding: 10px 13px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    color: rgba(15,23,42,0.75);
    transition: color 160ms ease, background 160ms ease;
    white-space: nowrap;
  }
  .medical-header .header-nav a:hover,
  .medical-header .header-nav a.is-active{
    color: #2563eb;
    background: rgba(37,99,235,0.08);
  }
  .medical-header .header-actions{
    display: flex;
    align-items: center;
    gap: 10px;
    justify-content: flex-end;
  }
  .medical-header .header-search{
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-width: 220px;
    height: 48px;
    padding: 0 16px;
    border-radius: 16px;
    border: 1px solid rgba(226,232,240,0.95);
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    color: rgba(15,23,42,0.48);
    box-shadow: 0 12px 26px rgba(15,23,42,0.05), inset 0 1px 0 rgba(255,255,255,0.82);
  }
  .medical-header .header-search i{
    color: rgba(37,99,235,0.72);
    font-size: 16px;
  }
  .medical-header .header-search input{
    width: 100%;
    border: 0;
    outline: none;
    background: transparent;
    font: inherit;
    font-size: 13px;
    color: #0f172a;
  }
  .medical-search-shell{position:relative;z-index:20}
  .medical-header .header-search-shell{min-width:220px}
  .medical-header .header-search-shell .header-search{width:100%;min-width:0}
  .medical-search-results{
    position:absolute;
    top:calc(100% + 10px);
    left:0;
    width:min(430px,calc(100vw - 32px));
    max-height:min(560px,calc(100vh - 112px));
    overflow:auto;
    padding:8px;
    border:1px solid rgba(191,219,254,.82);
    border-radius:18px;
    background:rgba(255,255,255,.98);
    box-shadow:0 24px 56px rgba(15,23,42,.18),0 4px 16px rgba(37,99,235,.08);
    backdrop-filter:blur(18px);
  }
  .medical-search-results[hidden]{display:none!important}
  .medical-search-group + .medical-search-group{margin-top:6px;padding-top:8px;border-top:1px solid rgba(226,232,240,.86)}
  .medical-search-group-title{display:flex;align-items:center;gap:7px;padding:5px 8px 6px;color:#64748b;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.055em}
  .medical-search-group-title i{font-size:15px;color:#2563eb}
  .medical-search-group-title span{margin-left:auto;color:#94a3b8;font-size:10px}
  .medical-search-item{display:flex;align-items:center;gap:10px;padding:9px 8px;border-radius:12px;color:#0f172a;transition:background 160ms ease}
  .medical-search-item:hover,.medical-search-item:focus{background:#f1f7ff;color:#0f172a}
  .medical-search-thumb{display:flex;flex:0 0 38px;width:38px;height:38px;overflow:hidden;align-items:center;justify-content:center;border-radius:11px;background:#eff6ff;color:#2563eb}
  .medical-search-thumb img{width:100%;height:100%;object-fit:cover}
  .medical-search-placeholder{display:inline-flex;align-items:center;justify-content:center;width:100%;height:100%;font-size:18px}
  .medical-search-copy{display:grid;gap:3px;min-width:0;flex:1}
  .medical-search-copy strong{overflow:hidden;font-size:13px;line-height:1.3;white-space:nowrap;text-overflow:ellipsis}
  .medical-search-copy small{overflow:hidden;color:#64748b;font-size:11px;line-height:1.35;white-space:nowrap;text-overflow:ellipsis}
  .medical-search-arrow{color:#94a3b8;font-size:16px}
  .medical-search-empty,.medical-search-loading{display:flex;align-items:center;justify-content:center;gap:8px;min-height:66px;padding:12px;color:#64748b;text-align:center;font-size:12px;font-weight:600}
  .medical-search-spinner{width:15px;height:15px;border:2px solid #bfdbfe;border-top-color:#2563eb;border-radius:999px;animation:medical-search-spin .7s linear infinite}
  @keyframes medical-search-spin{to{transform:rotate(360deg)}}
  .medical-header .lang-switch{
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px;
    border-radius: 999px;
    background: #f8fafc;
    border: 1px solid rgba(226,232,240,0.95);
  }
  .medical-header .lang-switch a{
    min-width: 34px;
    padding: 6px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    color: rgba(15,23,42,0.58);
    text-align: center;
  }
  .medical-header .lang-switch a.is-active{
    background: #2563eb;
    color: #fff;
  }
  .medical-header .header-login{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 48px;
    padding: 0 18px;
    border-radius: 14px;
    background: linear-gradient(180deg, #2d7fff 0%, #2563eb 100%);
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    box-shadow: 0 14px 22px rgba(37,99,235,0.18);
  }
  .medical-header .header-mobile{
    display: none;
  }
  .medical-header .mobile-language{
    display: none;
    position: relative;
  }
  .medical-header .mobile-language summary{
    list-style: none;
    width: 42px;
    height: 42px;
    border: 1px solid rgba(191,219,254,.9);
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f8fbff;
    color: #2563eb;
    cursor: pointer;
    font-size: 19px;
  }
  .medical-header .mobile-language summary::-webkit-details-marker{
    display: none;
  }
  .medical-header .mobile-language-panel{
    display: none;
  }
  .medical-header .mobile-language[open] .mobile-language-panel{
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    z-index: 20;
    display: grid;
    grid-template-columns: repeat(2, minmax(44px, 1fr));
    gap: 4px;
    min-width: 106px;
    padding: 5px;
    border: 1px solid rgba(191,219,254,.9);
    border-radius: 13px;
    background: rgba(255,255,255,.98);
    box-shadow: 0 14px 30px rgba(15,23,42,.14);
  }
  .medical-header .mobile-language-panel a{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    border-radius: 9px;
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
  }
  .medical-header .mobile-language-panel a.is-active{
    background: #2563eb;
    color: #fff;
  }
  .medical-header .mobile-summary{
    list-style: none;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #2563eb;
    color: #fff;
    cursor: pointer;
  }
  .medical-header .mobile-summary::-webkit-details-marker{
    display: none;
  }
  .medical-header .mobile-panel{
    display: none;
  }
  .medical-header .header-mobile[open] .mobile-panel{
    display: grid;
    gap: 10px;
    position: absolute;
    left: 16px;
    right: 16px;
    top: calc(100% + 12px);
    padding: 14px;
    border-radius: 20px;
    background: rgba(255,255,255,0.98);
    border: 1px solid rgba(226,232,240,0.95);
    box-shadow: 0 26px 60px rgba(15,23,42,0.16);
  }
  .medical-header .mobile-panel a{
    display: block;
    padding: 13px 14px;
    border-radius: 14px;
    background: #f8fafc;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
  }
  .medical-header .mobile-search{
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 14px;
    height: 46px;
    border-radius: 14px;
    border: 1px solid rgba(226,232,240,0.95);
    background: #f8fbff;
  }
  .medical-header .mobile-search input{
    width: 100%;
    border: 0;
    outline: none;
    background: transparent;
    font: inherit;
  }
  .medical-header .mobile-search-shell{width:100%}
  .medical-header .mobile-search-shell .medical-search-results{top:calc(100% + 8px);width:100%;max-height:min(440px,calc(100vh - 150px))}
  @media (max-width: 1180px){
    .medical-header .header-shell{
      grid-template-columns: auto 1fr;
    }
    .medical-header .header-center{
      justify-content: flex-end;
    }
    .medical-header .header-search{
      min-width: 220px;
    }
  }
  /* iPad landscape does not have enough room for the full navigation, search,
     language switch and login without splitting the header into two rows. */
  @media (max-width: 1100px){
    .medical-header .header-nav{
      display: none;
    }
    .medical-header .header-mobile{
      display: block;
      position: relative;
    }
    .medical-header .header-shell{
      grid-template-columns: auto 1fr auto;
    }
    .medical-header .header-mobile[open] .mobile-panel{
      position: fixed;
      top: 80px;
      right: 16px;
      left: 16px;
      width: auto;
      max-height: calc(100dvh - 96px);
      overflow: auto;
    }
  }
  /* On iPad, preserve the three high-value header actions and collapse only
     the long navigation list into the menu button. */
  @media (min-width: 721px) and (max-width: 1100px){
    .medical-header .header-actions{gap:8px}
    .medical-header .header-search-shell{display:block;min-width:180px}
    .medical-header .header-search{min-width:180px}
    .medical-header .lang-switch{display:inline-flex}
    .medical-header .header-login{display:inline-flex;padding:0 13px}
  }
  @media (max-width: 640px){
    .medical-header .header-shell{
      min-height: 68px;
      gap: 12px;
    }
    .medical-header .brand-copy strong{
      font-size: 21px;
    }
    .medical-header .brand-copy > .brand-tagline{
      display: none;
    }
  }
  @media (max-width: 720px){
    .medical-header .header-actions{gap:7px}
    .medical-header .header-search-shell{
      display: block;
      position: relative;
      width: 40px;
      min-width: 40px;
    }
    .medical-header .header-search-shell .header-search{
      width: 40px!important;
      min-width: 40px!important;
      justify-content: center;
      padding: 0;
      border-radius: 12px;
    }
    .medical-header .header-search-shell .header-search > i{margin:0}
    .medical-header .header-search-shell .header-search input{
      position: absolute;
      width: 1px;
      height: 1px;
      opacity: 0;
      pointer-events: none;
    }
    .medical-header .header-search-shell:focus-within{z-index:1010}
    .medical-header .header-search-shell:focus-within .header-search{
      position: fixed;
      top: 78px;
      right: 12px;
      left: 12px;
      width: auto!important;
      height: 48px;
      padding: 0 14px;
      justify-content: flex-start;
      border-radius: 14px;
      box-shadow: 0 16px 36px rgba(15,23,42,.14);
    }
    .medical-header .header-search-shell:focus-within .header-search > i{margin-right:10px}
    .medical-header .header-search-shell:focus-within .header-search input{
      position: static;
      width: 100%;
      height: auto;
      opacity: 1;
      pointer-events: auto;
    }
    .medical-header .header-search-shell:focus-within .medical-search-results{
      position: fixed;
      top: 134px;
      right: 12px;
      left: 12px;
      width: auto;
      max-height: calc(100dvh - 148px);
    }
    .medical-header .lang-switch,
    .medical-header .header-login{display:none!important}
    .medical-header .mobile-language{display:block}
  }
</style>
<style>
  /* Public shell refinement: the same compact blue/teal hierarchy as MedReview Admin. */
  .medical-header{
    background:rgba(255,255,255,.9)!important;
    border-bottom-color:rgba(219,234,254,.95)!important;
    box-shadow:0 10px 30px rgba(15,35,66,.055);
    isolation:isolate;
  }
  .medical-header::after{
    content:"";
    position:absolute;
    z-index:-1;
    right:0;
    bottom:-1px;
    left:0;
    height:1px;
    background:linear-gradient(90deg,transparent,rgba(37,99,235,.28),transparent);
  }
  .medical-header .header-shell{
    min-height:76px!important;
    gap:20px!important;
  }
  /* The configured pin artwork has a wider visual footprint than the fallback icon. */
  .medical-header .brand-link.has-image-brand{gap:4px!important}
  .medical-header .brand-mark{
    width:42px!important;
    height:42px!important;
    border-radius:14px!important;
    background:linear-gradient(145deg,#fff 8%,#e9f2ff 100%);
    box-shadow:0 10px 22px rgba(37,99,235,.1),inset 0 1px 0 rgba(255,255,255,.9);
  }
  .medical-header .brand-mark.is-image{
    width:48px!important;
    height:48px!important;
    border:0!important;
    border-radius:0!important;
    background:transparent!important;
    box-shadow:none!important;
  }
  .medical-header .brand-copy{gap:1px}
  .medical-header .brand-copy strong{
    font-size:23px!important;
    letter-spacing:-.055em;
  }
  .medical-header .brand-copy > .brand-tagline{
    max-width:230px;
    color:rgba(51,65,85,.68);
    font-size:10.5px;
    font-weight:650;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .medical-header .brand-copy small{display:none!important}
  .medical-header .header-nav{gap:3px!important}
  .medical-header .header-nav a{
    min-height:38px;
    padding:8px 10px!important;
    border:1px solid transparent;
    border-radius:11px!important;
    color:#475569;
    font-weight:750;
    transition:background-color .18s ease,border-color .18s ease,color .18s ease,transform .18s ease;
  }
  .medical-header .header-nav a:hover,
  .medical-header .header-nav a.is-active{
    border-color:rgba(191,219,254,.78);
    background:linear-gradient(180deg,#f7fbff,#eef6ff);
    color:#2563eb;
  }
  .medical-header .header-nav a.is-active::after{display:none!important}
  .medical-header .header-actions{gap:8px}
  .medical-header .header-search{
    padding:0 12px;
    border-color:rgba(203,213,225,.78);
    background:rgba(248,251,255,.9);
    box-shadow:inset 0 1px 0 rgba(255,255,255,.9);
    transition:border-color .18s ease,box-shadow .18s ease,background-color .18s ease;
  }
  .medical-header .header-search:focus-within,
  .medical-search-shell.is-open .header-search{
    border-color:rgba(96,165,250,.84);
    background:#fff;
    box-shadow:0 0 0 4px rgba(59,130,246,.1),inset 0 1px 0 rgba(255,255,255,.9);
  }
  .medical-header .header-search input{font-size:12px;font-weight:600}
  .medical-header .header-search input::placeholder{color:#94a3b8;opacity:1}
  .medical-header .header-login{
    background:linear-gradient(135deg,#347ff7 0%,#2563eb 100%);
    box-shadow:0 10px 18px rgba(37,99,235,.18);
    font-weight:800;
    transition:box-shadow .18s ease,transform .18s ease,filter .18s ease;
  }
  .medical-header .header-login:hover{
    box-shadow:0 14px 22px rgba(37,99,235,.26);
    filter:brightness(1.02);
    transform:translateY(-1px);
  }
  .medical-header .lang-switch{background:#f8fafc;border-color:rgba(203,213,225,.76)}
  .medical-header .lang-switch a{font-weight:850}
  .medical-header .lang-switch a.is-active{background:linear-gradient(135deg,#347bf1,#2563eb);box-shadow:0 4px 10px rgba(37,99,235,.18)}
  @media (min-width:1181px){
    .medical-header .brand-copy > .brand-tagline{max-width:300px;font-size:12px}
    .medical-header .header-search input{font-size:14px}
    /* Keep the search control inside the actions row; its 100%-wide input and
       horizontal padding must not spill over the language switch. */
    .medical-header .header-actions{min-width:0;flex-wrap:nowrap}
    .medical-header .header-actions > .header-search-shell{
      box-sizing:border-box;
      flex:0 1 340px;
      width:clamp(220px,20vw,340px);
      min-width:220px;
    }
    .medical-header .header-search-shell .header-search{box-sizing:border-box}
    .medical-header .header-search-shell .header-search input{min-width:0;flex:1 1 0}
    .medical-header .header-actions > .lang-switch,
    .medical-header .header-actions > .header-login{flex:0 0 auto}
  }
  .medical-search-results{
    border-color:rgba(191,219,254,.92);
    box-shadow:0 24px 58px rgba(15,35,66,.16),0 4px 15px rgba(37,99,235,.06);
  }
  .medical-search-item:hover,.medical-search-item:focus{background:#f0f7ff;color:#10213d}
  .medical-header .mobile-summary{
    border-color:#2563eb;
    background:linear-gradient(135deg,#347ff7,#2563eb);
    box-shadow:0 9px 16px rgba(37,99,235,.16);
  }
  .medical-header .header-mobile[open] .mobile-panel{
    border-color:rgba(191,219,254,.9);
    background:rgba(255,255,255,.98);
    box-shadow:0 26px 60px rgba(15,35,66,.18);
    backdrop-filter:blur(18px);
    -webkit-backdrop-filter:blur(18px);
  }
  .medical-header .mobile-panel a{
    min-height:43px;
    border:1px solid transparent;
    background:transparent;
    color:#334155;
  }
  .medical-header .mobile-panel a:hover{
    border-color:rgba(191,219,254,.78);
    background:#eff6ff;
    color:#2563eb;
  }
  .medical-header .mobile-panel .lang-switch{display:inline-flex!important;width:max-content;margin-top:2px}
  .medical-header .mobile-panel .header-login{
    display:inline-flex!important;
    width:max-content;
    margin-top:2px;
    background:linear-gradient(135deg,#347ff7 0%,#2563eb 100%);
    color:#fff!important;
  }
  .medical-header .header-nav a:focus-visible,
  .medical-header .header-login:focus-visible,
  .medical-header .lang-switch a:focus-visible,
  .medical-header summary:focus-visible,
  .medical-header .mobile-panel a:focus-visible{
    outline:3px solid rgba(37,99,235,.22);
    outline-offset:2px;
  }
  @media (max-width:1180px){
    .medical-header .header-shell{grid-template-columns:auto 1fr auto;gap:16px!important}
    .medical-header .header-center{display:none}
    .medical-header .header-nav{display:none}
    .medical-header .header-mobile{display:block}
    .medical-header .header-search-shell{min-width:184px}
  }
  @media (max-width:720px){
    .medical-header .header-shell{min-height:68px!important;gap:9px!important}
    .medical-header .brand-mark{width:38px!important;height:38px!important;border-radius:13px!important}
    .medical-header .brand-mark.is-image{width:36px!important;height:36px!important;border-radius:0!important}
    .medical-header .brand-copy strong{font-size:18px!important}
    .medical-header .brand-copy > .brand-tagline{
      display:block!important;
      width:fit-content;
      max-width:clamp(100px, calc(100vw - 220px), 230px);
      font-size:clamp(10px, 2.7vw, 11px)!important;
      line-height:1.2!important;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    /* Keep the three compact controls together at the far right on phones. */
    .medical-header .header-actions{
      grid-column:3;
      justify-self:end;
      width:max-content!important;
      gap:7px!important;
      flex-wrap:nowrap;
      justify-content:flex-end!important;
    }
    .medical-header .header-search-shell{
      flex:0 0 40px!important;
      width:40px!important;
      min-width:40px!important;
      margin:0!important;
    }
    .medical-header .mobile-language,
    .medical-header .header-mobile{flex:0 0 auto!important;margin:0!important}
    .medical-header .header-mobile[open] .mobile-panel{
      top:80px!important;
      right:12px!important;
      left:12px!important;
      width:auto;
      max-height:calc(100dvh - 96px);
      overflow:auto;
    }
    .medical-header .mobile-panel .header-login{display:inline-flex!important}
  }
  /* Compact mobile controls use the same quiet motion language as the rest of
     the public interface. Panels remain in the render tree while hidden so
     both opening and closing can transition without a layout jump. */
  .mobile-popover-backdrop{display:none}
  .medical-header .mobile-summary,
  .medical-header .mobile-summary i,
  .medical-header .mobile-language summary,
  .medical-header .mobile-language summary i{transition:transform .2s var(--ui-ease),background-color .2s var(--ui-ease),border-color .2s var(--ui-ease),box-shadow .2s var(--ui-ease),color .2s var(--ui-ease)}
  .medical-header .mobile-search-trigger{display:none}
  .medical-header .mobile-search-close{display:none}
  @media (max-width:1180px){
    .mobile-popover-backdrop{
      display:block;
      position:fixed;
      z-index:999;
      inset:76px 0 0;
      background:rgba(15,35,66,.16);
      opacity:0;
      pointer-events:none;
      transition:opacity .2s ease;
    }
    .medical-header.has-mobile-popover + .mobile-popover-backdrop{opacity:1;pointer-events:auto}
    .medical-header .header-shell{position:relative;z-index:2}
    .medical-header .header-mobile .mobile-panel{
      display:grid!important;
      position:fixed;
      z-index:20;
      top:88px;
      right:16px;
      left:16px;
      width:auto;
      max-height:calc(100dvh - 104px);
      overflow:auto;
      padding:14px;
      border:1px solid rgba(191,219,254,.9);
      border-radius:20px;
      background:rgba(255,255,255,.98);
      box-shadow:0 26px 60px rgba(15,35,66,.18);
      backdrop-filter:blur(18px);
      -webkit-backdrop-filter:blur(18px);
      opacity:0;
      visibility:hidden;
      pointer-events:none;
      transform:translate3d(0,-10px,0) scale(.985);
      transform-origin:top right;
      transition:opacity .2s var(--ui-ease),transform .2s var(--ui-ease),visibility 0s linear .2s;
    }
    .medical-header .header-mobile.is-open .mobile-panel,
    .medical-header .header-mobile[open] .mobile-panel{
      opacity:1;
      visibility:visible;
      pointer-events:auto;
      transform:translate3d(0,0,0) scale(1);
      transition-delay:0s;
    }
    .medical-header .header-mobile .mobile-panel > *{
      opacity:0;
      transform:translateY(-5px);
      transition:opacity .18s var(--ui-ease),transform .18s var(--ui-ease);
    }
    .medical-header .header-mobile.is-open .mobile-panel > *,
    .medical-header .header-mobile[open] .mobile-panel > *{opacity:1;transform:translateY(0)}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(1),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(1){transition-delay:.035s}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(2),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(2){transition-delay:.06s}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(3),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(3){transition-delay:.085s}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(n+4),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(n+4){transition-delay:.11s}
    .medical-header .header-mobile.is-open .mobile-summary,
    .medical-header .header-mobile[open] .mobile-summary{
      background:#fff;
      border-color:rgba(96,165,250,.72);
      color:#2563eb;
      box-shadow:0 8px 16px rgba(37,99,235,.14);
      transform:rotate(90deg);
    }
    .medical-header .mobile-language .mobile-language-panel{
      display:grid!important;
      position:absolute;
      z-index:22;
      top:calc(100% + 8px);
      right:0;
      min-width:106px;
      padding:5px;
      opacity:0;
      visibility:hidden;
      pointer-events:none;
      transform:translate3d(0,-7px,0) scale(.96);
      transform-origin:top right;
      transition:opacity .18s var(--ui-ease),transform .18s var(--ui-ease),visibility 0s linear .18s;
    }
    .medical-header .mobile-language.is-open .mobile-language-panel,
    .medical-header .mobile-language[open] .mobile-language-panel{
      opacity:1;
      visibility:visible;
      pointer-events:auto;
      transform:translate3d(0,0,0) scale(1);
      transition-delay:0s;
    }
    .medical-header .mobile-language.is-open summary,
    .medical-header .mobile-language[open] summary{
      background:#fff;
      border-color:rgba(96,165,250,.75);
      box-shadow:0 8px 16px rgba(37,99,235,.12);
      transform:translateY(-1px);
    }
    .medical-header .mobile-language.is-open summary i,
    .medical-header .mobile-language[open] summary i{transform:rotate(-12deg) scale(1.05)}
  }
  @media (max-width:720px){
    .mobile-popover-backdrop{inset:68px 0 0}
    .medical-header .header-mobile .mobile-panel{top:80px;right:12px;left:12px;max-height:calc(100dvh - 96px);padding:12px;border-radius:18px}
    .medical-header .header-search-shell{position:relative}
    .medical-header .header-search-shell .mobile-search-trigger{
      display:inline-flex;
      width:40px;
      height:40px;
      align-items:center;
      justify-content:center;
      padding:0;
      border:1px solid rgba(191,219,254,.9);
      border-radius:12px;
      background:#f8fbff;
      color:#2563eb;
      cursor:pointer;
      box-shadow:inset 0 1px 0 rgba(255,255,255,.9);
      transition:transform .18s var(--ui-ease),opacity .18s var(--ui-ease),background-color .18s var(--ui-ease),border-color .18s var(--ui-ease),box-shadow .18s var(--ui-ease);
    }
    .medical-header .header-search-shell .mobile-search-trigger:active{transform:scale(.94)}
    .medical-header .header-search-shell > .header-search{
      position:absolute;
      inset:0;
      opacity:0;
      visibility:hidden;
      pointer-events:none;
      transform:translate3d(0,-6px,0) scale(.985);
      transition:opacity .2s var(--ui-ease),transform .2s var(--ui-ease),visibility 0s linear .2s;
    }
    .medical-header .header-search-shell.is-mobile-search-open{z-index:1012}
    .medical-header .header-search-shell.is-mobile-search-open .mobile-search-trigger{opacity:0;pointer-events:none;transform:scale(.9)}
    .medical-header .header-search-shell.is-mobile-search-open > .header-search{
      position:fixed;
      z-index:22;
      top:80px;
      right:12px;
      left:12px;
      width:auto!important;
      height:48px;
      padding:0 14px;
      justify-content:flex-start;
      border-radius:14px;
      opacity:1;
      visibility:visible;
      pointer-events:auto;
      transform:translate3d(0,0,0) scale(1);
      box-shadow:0 18px 42px rgba(15,35,66,.18),0 0 0 4px rgba(59,130,246,.1);
      transition-delay:0s;
    }
    .medical-header .header-search-shell.is-mobile-search-open .mobile-search-close{
      display:inline-flex;
      flex:0 0 34px;
      align-items:center;
      justify-content:center;
      width:34px;
      height:34px;
      padding:0;
      border:1px solid rgba(203,213,225,.8);
      border-radius:11px;
      background:#f1f5f9;
      color:#52627a;
      font-size:17px;
      cursor:pointer;
      transition:background .16s ease,color .16s ease,transform .16s ease;
    }
    .medical-header .header-search-shell.is-mobile-search-open .mobile-search-close:active{transform:scale(.93);background:#e2e8f0}
    .medical-header .header-search-shell.is-mobile-search-open .mobile-search-close:focus-visible{outline:3px solid rgba(37,99,235,.25);outline-offset:2px}
    .medical-header .header-search-shell.is-mobile-search-open > .header-search > i{margin-right:10px}
    .medical-header .header-search-shell.is-mobile-search-open > .header-search input{position:static;flex:1;min-width:0;width:0;height:auto;opacity:1;pointer-events:auto}
    .medical-header .header-search-shell.is-mobile-search-open .medical-search-results{
      position:fixed;
      z-index:22;
      top:136px;
      right:12px;
      left:12px;
      width:auto;
      max-height:calc(100dvh - 150px);
      animation:medical-header-pop-in .18s var(--ui-ease);
    }
  }
  /* Phone menu: treat it as a small, tactile iOS-style navigation sheet rather
     than a conventional dropdown. These rules intentionally stay below the
     tablet breakpoint so the compact iPad header keeps its current layout. */
  @media (max-width:720px){
    .mobile-popover-backdrop{
      background:
        radial-gradient(circle at 86% 8%,rgba(96,165,250,.28),transparent 31%),
        radial-gradient(circle at 10% 104%,rgba(45,212,191,.12),transparent 38%),
        linear-gradient(180deg,rgba(15,35,66,.26),rgba(15,35,66,.48));
      backdrop-filter:blur(11px) saturate(1.12);
      -webkit-backdrop-filter:blur(11px) saturate(1.12);
      transition:opacity .26s ease;
    }
    .medical-header.has-mobile-search + .mobile-popover-backdrop{
      background:rgba(10,24,46,.38);
      backdrop-filter:blur(14px) saturate(.86);
      -webkit-backdrop-filter:blur(14px) saturate(.86);
    }
    .medical-header .header-mobile .mobile-panel{
      top:80px!important;
      padding:9px 9px calc(12px + env(safe-area-inset-bottom,0px));
      overflow-x:hidden;
      overscroll-behavior:contain;
      border:1px solid rgba(255,255,255,.88);
      border-radius:24px;
      background:
        radial-gradient(circle at 94% 0%,rgba(96,165,250,.20),transparent 28%),
        linear-gradient(145deg,rgba(255,255,255,.985),rgba(239,247,255,.94));
      box-shadow:
        0 28px 70px rgba(2,20,51,.30),
        0 8px 24px rgba(37,99,235,.12),
        inset 0 1px 0 rgba(255,255,255,.95);
      transform:translate3d(0,-15px,0) scale(.965);
      transform-origin:top right;
      transition:
        opacity .26s cubic-bezier(.22,1,.36,1),
        transform .36s cubic-bezier(.22,1.22,.36,1),
        visibility 0s linear .36s;
      scrollbar-width:none;
    }
    .medical-header .header-mobile .mobile-panel::-webkit-scrollbar{display:none}
    .medical-header .header-mobile.is-open .mobile-panel,
    .medical-header .header-mobile[open] .mobile-panel{
      transform:translate3d(0,0,0) scale(1);
      transition-delay:0s;
    }
    .medical-header .mobile-menu-context{
      display:grid;
      grid-template-columns:30px minmax(0,1fr) 26px;
      align-items:center;
      gap:10px;
      min-height:54px;
      padding:7px 9px 9px;
      border-bottom:1px solid rgba(191,219,254,.62);
    }
    .medical-header .mobile-menu-grabber{
      position:relative;
      width:30px;
      height:30px;
      border-radius:11px;
      background:linear-gradient(145deg,#eff6ff,#dbeafe);
      box-shadow:inset 0 1px 0 rgba(255,255,255,.94);
    }
    .medical-header .mobile-menu-grabber::before,
    .medical-header .mobile-menu-grabber::after{
      content:"";
      position:absolute;
      left:8px;
      width:14px;
      height:2px;
      border-radius:999px;
      background:#2563eb;
    }
    .medical-header .mobile-menu-grabber::before{top:11px;box-shadow:0 5px 0 #60a5fa}
    .medical-header .mobile-menu-grabber::after{display:none}
    .medical-header .mobile-menu-title{display:grid;gap:1px;min-width:0}
    .medical-header .mobile-menu-title small{
      color:#64748b;
      font-size:10px;
      font-weight:750;
      line-height:1.2;
      letter-spacing:.035em;
      text-transform:uppercase;
    }
    .medical-header .mobile-menu-title strong{
      color:#10213d;
      font-size:14px;
      font-weight:850;
      line-height:1.25;
      letter-spacing:-.02em;
    }
    .medical-header .mobile-menu-context > i{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:26px;
      height:26px;
      border-radius:999px;
      background:rgba(16,185,129,.10);
      color:#059669;
      font-size:14px;
    }
    .medical-header .mobile-panel .mobile-search-shell{padding:3px 1px 5px}
    .medical-header .mobile-panel .mobile-search{
      height:48px;
      padding:0 13px;
      border-color:rgba(191,219,254,.86);
      border-radius:15px;
      background:rgba(255,255,255,.76);
      box-shadow:inset 0 1px 0 rgba(255,255,255,.96);
    }
    .medical-header .mobile-panel .mobile-search:focus-within{
      border-color:rgba(96,165,250,.9);
      box-shadow:0 0 0 3px rgba(59,130,246,.10),inset 0 1px 0 rgba(255,255,255,.96);
    }
    .medical-header .mobile-panel > a:not(.header-login){
      position:relative;
      display:flex;
      align-items:center;
      min-height:47px;
      padding:11px 38px 11px 32px;
      overflow:hidden;
      border:1px solid transparent;
      border-radius:14px;
      background:rgba(255,255,255,.34);
      color:#334155;
      font-size:14px;
      font-weight:780;
      letter-spacing:-.01em;
      transition:
        transform .18s cubic-bezier(.2,.8,.3,1),
        background-color .18s ease,
        border-color .18s ease,
        box-shadow .18s ease,
        color .18s ease;
    }
    .medical-header .mobile-panel > a:not(.header-login)::before{
      content:"";
      position:absolute;
      left:14px;
      width:7px;
      height:7px;
      border-radius:999px;
      background:#93c5fd;
      box-shadow:0 0 0 4px rgba(219,234,254,.74);
    }
    .medical-header .mobile-panel > a:not(.header-login)::after{
      content:"›";
      position:absolute;
      right:14px;
      color:#94a3b8;
      font-size:24px;
      font-weight:400;
      line-height:1;
      transition:transform .18s ease,color .18s ease;
    }
    .medical-header .mobile-panel > a:not(.header-login):hover,
    .medical-header .mobile-panel > a:not(.header-login):focus-visible{
      border-color:rgba(147,197,253,.68);
      background:linear-gradient(90deg,rgba(239,246,255,.94),rgba(255,255,255,.75));
      box-shadow:0 6px 14px rgba(37,99,235,.07);
      color:#1d4ed8;
    }
    .medical-header .mobile-panel > a:not(.header-login):active{transform:scale(.982)}
    .medical-header .mobile-panel > a:not(.header-login):active::after{transform:translateX(3px)}
    .medical-header .mobile-panel > a:not(.header-login).is-active{
      border-color:rgba(96,165,250,.72);
      background:linear-gradient(100deg,#2563eb,#3b82f6);
      box-shadow:0 9px 17px rgba(37,99,235,.18),inset 0 1px 0 rgba(255,255,255,.22);
      color:#fff;
    }
    .medical-header .mobile-panel > a:not(.header-login).is-active::before{
      background:#fff;
      box-shadow:0 0 0 4px rgba(255,255,255,.20);
    }
    .medical-header .mobile-panel > a:not(.header-login).is-active::after{color:#dbeafe}
    .medical-header .mobile-menu-footer{
      display:flex;
      align-items:center;
      gap:9px;
      padding:5px 1px 1px;
    }
    .medical-header .mobile-menu-footer .lang-switch{
      display:inline-flex!important;
      flex:0 0 auto;
      width:auto;
      margin:0;
      padding:3px;
      border-color:rgba(191,219,254,.80);
      background:rgba(255,255,255,.72);
    }
    .medical-header .mobile-menu-footer .header-login{
      display:inline-flex!important;
      flex:1 1 auto;
      min-height:42px;
      width:auto;
      margin:0;
      border-radius:13px;
      box-shadow:0 9px 17px rgba(37,99,235,.17),inset 0 1px 0 rgba(255,255,255,.18);
      transition:transform .18s cubic-bezier(.2,.8,.3,1),box-shadow .18s ease,filter .18s ease;
    }
    .medical-header .mobile-menu-footer .header-login:active{transform:scale(.975)}
    .medical-header .header-mobile .mobile-panel > *{
      transform:translate3d(0,-7px,0) scale(.985);
      transition:opacity .22s ease,transform .30s cubic-bezier(.22,1.16,.36,1);
    }
    .medical-header .header-mobile.is-open .mobile-panel > *,
    .medical-header .header-mobile[open] .mobile-panel > *{transform:translate3d(0,0,0) scale(1)}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(1),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(1){transition-delay:.03s}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(2),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(2){transition-delay:.06s}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(3),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(3){transition-delay:.09s}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(4),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(4){transition-delay:.12s}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(5),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(5){transition-delay:.15s}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(6),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(6){transition-delay:.18s}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(7),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(7){transition-delay:.21s}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(8),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(8){transition-delay:.24s}
    .medical-header .header-mobile.is-open .mobile-panel > :nth-child(n+9),
    .medical-header .header-mobile[open] .mobile-panel > :nth-child(n+9){transition-delay:.27s}
    .medical-header .mobile-summary{
      overflow:hidden;
      box-shadow:0 10px 20px rgba(37,99,235,.23),inset 0 1px 0 rgba(255,255,255,.18);
      transition:transform .22s cubic-bezier(.2,.9,.3,1),background .22s ease,border-color .22s ease,box-shadow .22s ease,color .22s ease;
    }
    .medical-header .mobile-summary:active{transform:scale(.91)!important}
    .medical-header .header-mobile.is-open .mobile-summary,
    .medical-header .header-mobile[open] .mobile-summary{
      transform:rotate(0deg) scale(1.025);
      background:linear-gradient(145deg,#fff,#eff6ff);
      box-shadow:0 10px 22px rgba(37,99,235,.18),inset 0 1px 0 rgba(255,255,255,.96);
    }
    .medical-header .header-mobile.is-open .mobile-summary i,
    .medical-header .header-mobile[open] .mobile-summary i{transform:rotate(90deg) scale(1.06)}
  }
  @keyframes medical-header-pop-in{from{opacity:0;transform:translate3d(0,-5px,0)}to{opacity:1;transform:translate3d(0,0,0)}}
  @media (prefers-reduced-motion:reduce){
    .medical-header *,.medical-header *::before,.medical-header *::after{animation-duration:.01ms!important;transition-duration:.01ms!important}
  }
</style>
<!-- Lightweight public-site motion layer. Kept here because every public page
     includes this header; admin pages use a separate layout. -->
<style id="medical-motion-system">
  :root{
    --medical-motion-fast:150ms;
    --medical-motion-base:240ms;
    --medical-motion-ease:cubic-bezier(.22,1,.36,1);
  }

  /* Native cross-document transitions are progressive enhancement: browsers
     without support simply ignore this at-rule and keep normal navigation. */
  @view-transition{navigation:auto;}
  ::view-transition-group(root){animation-duration:300ms;animation-timing-function:var(--medical-motion-ease);}
  ::view-transition-old(root){animation:medical-page-out 150ms cubic-bezier(.4,0,1,1) both;}
  ::view-transition-new(root){animation:medical-page-in 300ms var(--medical-motion-ease) both;}

  @keyframes medical-page-in{
    from{opacity:0;transform:translate3d(0,8px,0);filter:blur(2px)}
    to{opacity:1;transform:translate3d(0,0,0);filter:blur(0)}
  }
  @keyframes medical-page-out{
    from{opacity:1;transform:translate3d(0,0,0);filter:blur(0)}
    to{opacity:0;transform:translate3d(0,-3px,0);filter:blur(1px)}
  }

  /* Fallback used only in browsers without the native View Transitions API. */
  html.med-motion-fallback-ready body{
    animation:medical-fallback-enter 210ms var(--medical-motion-ease) both;
  }
  html.med-motion-fallback-leaving body{
    opacity:0;
    transform:translate3d(0,-4px,0);
    transition:opacity 130ms ease,transform 130ms ease;
  }
  @keyframes medical-fallback-enter{
    from{opacity:0;transform:translate3d(0,6px,0)}
    to{opacity:1;transform:translate3d(0,0,0)}
  }

  /* Small, reusable feedback for cards and controls. Classes are added by the
     script below only to recognisable public-facing interactive surfaces. */
  html.med-motion-ready .med-motion-card,
  html.med-motion-ready .med-motion-tap{
    -webkit-tap-highlight-color:transparent;
    touch-action:manipulation;
  }
  html.med-motion-ready .med-motion-card{
    transition:transform var(--medical-motion-base) var(--medical-motion-ease);
  }
  html.med-motion-ready .med-motion-tap{
    transition:transform var(--medical-motion-fast) var(--medical-motion-ease),opacity var(--medical-motion-fast) ease;
  }
  @media (hover:hover) and (pointer:fine){
    html.med-motion-ready .med-motion-card:hover{transform:translate3d(0,-2px,0);}
    html.med-motion-ready .med-motion-tap:hover{transform:translate3d(0,-1px,0);}
  }
  html.med-motion-ready .med-motion-tap:active{transform:scale(.975);}
  html.med-motion-ready :where(a,button,input,select,textarea,summary):focus-visible{
    outline:3px solid rgba(59,130,246,.34);
    outline-offset:3px;
  }

  @media (prefers-reduced-motion:reduce){
    ::view-transition-group(root),
    ::view-transition-old(root),
    ::view-transition-new(root){animation-duration:.001ms!important;}
    html.med-motion-fallback-ready body{animation:none!important;}
    html.med-motion-fallback-leaving body{opacity:1!important;transform:none!important;transition:none!important;}
    html.med-motion-ready .med-motion-card,
    html.med-motion-ready .med-motion-tap{transition:none!important;}
  }

  /* Search suggestions are shared by header and homepage. Keep the detail
     hierarchy compact enough for the header, while exposing enough context to
     decide before opening a facility, doctor or toplist. */
  .medical-search-results{width:min(490px,calc(100vw - 32px));max-height:min(500px,calc(100vh - 112px));padding:7px;}
  .medical-search-group + .medical-search-group{margin-top:4px;padding-top:6px;}
  .medical-search-group-title{padding:5px 8px;color:#64748b;}
  .medical-search-item{align-items:flex-start;gap:10px;padding:8px 9px;border:1px solid transparent;}
  .medical-search-item:hover,.medical-search-item:focus,.medical-search-item.is-active{border-color:rgba(191,219,254,.8);background:linear-gradient(135deg,#f8fbff,#eff6ff);color:#10213d;outline:0;}
  .medical-search-thumb{flex-basis:42px;width:42px;height:42px;border-radius:12px;box-shadow:inset 0 0 0 1px rgba(191,219,254,.48);}
  .medical-search-copy{gap:2px;padding:0;}
  .medical-search-title-row{display:flex;align-items:center;gap:6px;min-width:0;}
  .medical-search-copy .medical-search-title-row strong{min-width:0;font-size:13px;line-height:1.35;}
  .medical-search-copy .medical-search-meta{font-size:10px;line-height:1.3;}
  .medical-search-summary{display:flex;align-items:center;gap:7px;min-width:0;margin-top:1px;}
  .medical-search-service{display:inline-flex;align-items:center;gap:3px;min-width:0;max-width:205px;overflow:hidden;padding:2px 5px;border-radius:6px;background:#eff6ff;color:#2563eb!important;font-size:9px!important;font-weight:750;line-height:1.25;white-space:nowrap;text-overflow:ellipsis;}
  .medical-search-service i{font-size:12px;}
  .medical-search-facts{display:flex;align-items:center;gap:6px;min-width:0;color:#64748b;font-size:9px;font-weight:750;line-height:1.3;}
  .medical-search-facts > span{display:inline-flex;align-items:center;gap:3px;white-space:nowrap;}
  .medical-search-rating{color:#b45309;}
  .medical-search-rating i{color:#f59e0b;font-size:12px;}
  .medical-search-verified{display:inline-flex;flex:0 0 auto;align-items:center;gap:3px;padding:2px 5px;border-radius:999px;background:#ecfdf5;color:#059669;font-size:8px;font-weight:850;white-space:nowrap;}
  .medical-search-verified i{font-size:11px;}
  .medical-search-toplist-count i,.medical-search-updated i{color:#3b82f6;font-size:11px;}
  .medical-search-arrow{align-self:center;flex:0 0 auto;margin-left:2px;}
  @media (max-width:720px){
    /* Search, language and menu stay on one consistent 40px touch target. */
    .medical-header .header-search-shell,
    .medical-header .header-search-shell .mobile-search-trigger,
    .medical-header .mobile-language summary,
    .medical-header .mobile-summary{
      width:40px!important;
      height:40px!important;
      flex:0 0 40px!important;
      border-radius:12px!important;
    }
    .medical-search-results{width:min(100%,calc(100vw - 24px));}
    .medical-search-item{padding:9px;gap:8px;}
    .medical-search-thumb{flex-basis:38px;width:38px;height:38px;border-radius:11px;}
    .medical-search-summary{gap:5px;}
    .medical-search-copy .medical-search-title-row strong{font-size:14px;}
    .medical-search-copy .medical-search-meta{font-size:11px;line-height:1.4;}
    .medical-search-service{max-width:145px;font-size:10px!important;}
    .medical-search-facts{gap:6px;font-size:10px;}
    .medical-search-verified{font-size:9px;}
    .medical-search-verified span{display:none;}
  }
  /* iPad uses the compact header controls, but the menu should remain a
     corner popover rather than becoming a phone-width navigation sheet. */
  @media (min-width:721px) and (max-width:1180px){
    .medical-header .header-mobile .mobile-panel{
      top:86px;
      right:22px!important;
      left:auto!important;
      width:min(380px,calc(100vw - 44px))!important;
      max-height:calc(100dvh - 102px);
      transform-origin:top right;
    }
  }
</style>
<link rel="stylesheet" href="/assets/css/pages/brand-home-refresh.css?v=<?php echo htmlspecialchars($brandRefreshStylesheetVersion, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="/assets/css/pages/brand-header-match.css?v=<?php echo htmlspecialchars($headerMatchStylesheetVersion, ENT_QUOTES, 'UTF-8'); ?>">
<div class="medical-header-utility">
  <div class="container">
    <span><i class="ph ph-shield-check" aria-hidden="true"></i><?php echo htmlspecialchars($isEnglish ? 'Transparent information. Better choices.' : 'Thông tin minh bạch. Lựa chọn tốt hơn.', ENT_QUOTES, 'UTF-8'); ?></span>
    <a href="<?php echo htmlspecialchars($contactPath, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($providerLinkLabel, ENT_QUOTES, 'UTF-8'); ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
  </div>
</div>
<header class="medical-header">
  <div class="container">
    <div class="header-shell">
      <a class="brand-link<?php echo $hasBrandIcon ? ' has-image-brand' : ''; ?>" href="<?php echo htmlspecialchars($homePath, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="brand-mark<?php echo $hasBrandIcon ? ' is-image' : ''; ?>" aria-hidden="true">
          <?php if ($hasBrandIcon): ?><img src="<?php echo htmlspecialchars($brandIconUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="eager" decoding="async" onload="this.parentElement.classList.add('brand-image-loaded');" onerror="var mark=this.parentElement;this.remove();if(mark)mark.classList.remove('is-image','brand-image-loaded');"><?php endif; ?>
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
            <?php $isDesktopActive = $matchPath !== '' && $requestPath === $matchPath; ?>
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
              <?php $isMobileActive = $mobileMatchPath !== '' && $requestPath === $mobileMatchPath; ?>
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
      var searchOpen = Boolean(searchShell && searchShell.classList.contains('is-mobile-search-open'));
      var hasPopover = Boolean((mobile && mobile.hasAttribute('open')) || (language && language.hasAttribute('open')) || searchOpen);
      header.classList.toggle('has-mobile-search', searchOpen);
      header.classList.toggle('has-mobile-popover', hasPopover);
    }
    function closeSearch(){
      if (!searchShell) return;
      searchShell.classList.remove('is-mobile-search-open');
      searchShell.classList.remove('is-open');
      if (searchTrigger) searchTrigger.setAttribute('aria-expanded', 'false');
      if (searchResults) {
        searchResults.hidden = true;
        searchResults.innerHTML = '';
      }
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
        if (isPhone() && (!searchShell || !searchShell.classList.contains('is-mobile-search-open'))) openSearch();
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
<script src="/assets/js/medical-global-search.js" defer></script>
