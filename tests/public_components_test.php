<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Presentation regressions only: no bootstrap, database or cache rebuild.
$root = dirname(__DIR__);
$css = (string) file_get_contents($root . '/assets/css/core/public-components.css');
$header = (string) file_get_contents($root . '/Tem/header.php');
$legacy = (string) file_get_contents($root . '/assets/css/core/ui-2026.css');
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$uses = static function (string $selector, string $declaration) use ($css): bool {
    $withoutComments = preg_replace('~/\*.*?\*/~s', '', $css) ?? $css;
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $withoutComments, $rules, PREG_SET_ORDER);
    foreach ($rules as $rule) {
        $selectors = array_map('trim', explode(',', $rule[1]));
        if (in_array($selector, $selectors, true) && str_contains($rule[2], $declaration)) return true;
    }
    return false;
};
foreach (['--med-ui-card-title:18px', '--med-ui-body:14px', '--med-ui-meta:13px',
    '--med-ui-action:14px', '--med-ui-field:16px', '--med-ui-send-size:40px',
    '--med-ui-control-height:40px', '--med-ui-control-padding:8px 14px'] as $token) {
    $assert(str_contains($css, $token), 'Readable shared token: ' . $token);
}
foreach (['body.site-home main.med-home.hc-home .facility-title h3', 'body.fd-page .fd-directory .fd-card h2'] as $selector) {
    $assert($uses($selector, 'font-size:var(--med-ui-card-title)'), 'Shared card heading: ' . $selector);
}
foreach (['body.site-home main.med-home.hc-home .button', 'body.fd-page .fd-directory .fd-apply',
    'body.fd-page .fd-directory .fd-filter-toggle', 'body .doctor-directory .doctor-action',
    'body .review-page .detail-btn', 'body .facility-page .detail-btn',
    'body .mr-about .mr-about__button', 'body .mr-contact .mr-contact__button',
    'body main.doctor-profile.site-typo .dp-button',
    'body main.doctor-profile.site-typo .dp-read-more'] as $selector) {
    $assert($uses($selector, 'font-size:var(--med-ui-action)') && $uses($selector, 'min-height:var(--med-ui-control-height)'), 'Shared action: ' . $selector);
    $assert($uses($selector, 'box-sizing:border-box') && $uses($selector, 'padding:var(--med-ui-control-padding)'), 'Compact padding cannot inflate action height: ' . $selector);
}
foreach (['body.site-home main.med-home.hc-home .send-button', 'body.fd-page .fd-directory .fd-search button',
    'body .doctor-directory .doctor-search-submit'] as $selector) {
    $assert($uses($selector, 'width:var(--med-ui-send-size)') && $uses($selector, 'border-radius:var(--med-ui-send-radius)'), 'Shared send control: ' . $selector);
}
$assert($uses('body.fd-page .fd-directory .fd-pagination', 'flex-wrap:wrap')
    && $uses('body .doctor-directory .doctor-pagination', 'flex-wrap:wrap'), 'Touch-sized pagers wrap instead of overflowing narrow screens');
$assert($uses('body .doctor-directory .doctor-action', 'white-space:normal'), 'Long English doctor action can wrap inside its fixed column');
preg_match('/\.facility-page \.detail-btn,\.review-page \.detail-btn\{([^}]+)\}/', $legacy, $legacyDetail);
$assert(isset($legacyDetail[1]) && !str_contains($legacyDetail[1], 'font-size:12px!important'), 'Legacy detail-button font cannot defeat the shared scale');
$assert(str_contains($css, '@media(prefers-reduced-motion:reduce)') && str_contains($css, 'transition:none'), 'Reduced motion retained');
$assert(str_contains($header, 'Inter:wght@400;450;500;550;600;650;700;750;800'), 'Common font includes real 650 and 750 weights');
$assert(substr_count($header, 'href="/assets/css/core/public-components.css?v=') === 1
    && str_contains($header, 'filemtime($publicComponentsStylesheetPath)'), 'Shared component layer loaded once with cache version');
$assert(str_contains($css, '--med-ui-card-title:17px'), 'Mobile heading uses the same responsive token');
$assert((bool) preg_match('/@media\(max-width:760px\)\{\s*:root\{[^}]*--med-ui-control-height:44px;--med-ui-send-size:44px/s', $css), 'Mobile controls retain a 44px touch target');
$headerControls = (string) file_get_contents($root . '/assets/css/pages/brand-header-match.css');
$assert((bool) preg_match('/\.medical-header \.header-login\{[^}]*box-sizing:border-box;[^}]*height:var\(--med-ui-control-height,40px\)!important/s', $headerControls), 'Header login uses border-box and the shared height');
$doctorControls = (string) file_get_contents($root . '/assets/css/pages/doctor-directory.css');
foreach (['doctor-action', 'doctor-filter-toggle', 'doctor-pagination button'] as $control) {
    $assert((bool) preg_match('/\.dd-redesign\.doctor-directory \.' . preg_quote($control, '/') . '\{[^}]*min-height:var\(--med-ui-control-height,40px\)/s', $doctorControls), 'Higher-specificity doctor controls keep shared sizing: ' . $control);
}

$home = (string) file_get_contents($root . '/Tem/home.php');
$directory = (string) file_get_contents($root . '/Tem/facility-directory.php');
$assert(str_contains($home, 'm21 3-6.8 18-3.5-7.7L3 9.8 21 3Z')
    && str_contains($directory, 'm21 3-6.8 18-3.5-7.7L3 9.8 21 3Z'), 'Homepage and facility search use the same send icon');
foreach (['medical_facility_directory_view.php', 'assets/js/facility-directory.js'] as $file) {
    $source = (string) file_get_contents($root . '/' . $file);
    $assert((bool) preg_match('/fd-profile-link[^\n]+ph-arrow-right/', $source), 'SSR/AJAX profile-link icon stays consistent: ' . $file);
}
echo "Public components: {$checks} checks passed. No DB records modified.\n";
