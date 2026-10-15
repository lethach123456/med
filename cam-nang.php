<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_handbook.php';
require_once __DIR__ . '/medical_public_assets.php';
require_once __DIR__ . '/medical_media_variants.php';

$GLOBALS['site_page_key'] = 'handbook';
$locale = site_page_locale('handbook');
$GLOBALS['site_forced_locale'] = $locale;
$GLOBALS['site_language_links'] = ['vi' => handbook_view_path('vi'), 'en' => handbook_view_path('en'), 'current' => $locale];
$handbookCopy = handbook_view_copy($locale);
$handbookFilters = handbook_view_filters($_GET);
$handbookFailed = false;
try {
    $handbookPaging = handbook_directory_page(db(), $locale, $handbookFilters, handbook_view_requested_page($_GET));
} catch (Throwable $error) {
    $handbookFailed = true;
    $handbookPaging = handbook_view_paging(0) + ['published_total' => 0, 'items' => []];
    http_response_code(503);
    error_log('MedReview handbook listing unavailable: ' . get_class($error));
}
$handbookCanonical = handbook_view_page_url($locale, $handbookFilters, $handbookPaging['page']);
$handbookTitle = $handbookCopy['seoTitle'];
if ($handbookPaging['page'] > 1) $handbookTitle .= ' — ' . $handbookCopy['page'] . $handbookPaging['page'];
$handbookNoindex = $handbookFailed || $handbookFilters['q'] !== '';
?><!doctype html>
<html lang="<?= handbook_view_escape($locale) ?>">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <?= site_favicon_tags() ?>
  <title><?= handbook_view_escape($handbookTitle) ?></title>
  <meta name="description" content="<?= handbook_view_escape($handbookCopy['seoDescription']) ?>">
  <?php if ($handbookNoindex): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
  <link rel="canonical" href="<?= handbook_view_escape(site_absolute_url($handbookCanonical)) ?>">
  <?php if (!$handbookNoindex && $handbookPaging['page'] === 1): ?>
  <link rel="alternate" hreflang="vi" href="<?= handbook_view_escape(site_absolute_url(handbook_view_path('vi'))) ?>">
  <link rel="alternate" hreflang="en" href="<?= handbook_view_escape(site_absolute_url(handbook_view_path('en'))) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= handbook_view_escape(site_absolute_url(handbook_view_path('vi'))) ?>">
  <?php endif; ?>
  <?php if ($handbookPaging['page'] > 1): ?><link rel="prev" href="<?= handbook_view_escape(site_absolute_url(handbook_view_page_url($locale, $handbookFilters, $handbookPaging['page'] - 1))) ?>"><?php endif; ?>
  <?php if ($handbookPaging['page'] < $handbookPaging['pages']): ?><link rel="next" href="<?= handbook_view_escape(site_absolute_url(handbook_view_page_url($locale, $handbookFilters, $handbookPaging['page'] + 1))) ?>"><?php endif; ?>
  <?= medical_public_shell_styles() ?>
  <link rel="stylesheet" href="<?= handbook_view_escape(medical_public_asset_url('/assets/css/core/shared-typography.css')) ?>">
  <link rel="stylesheet" href="<?= handbook_view_escape(medical_public_asset_url('/assets/css/pages/handbook.css')) ?>">
</head>
<body class="handbook-page">
<?php require __DIR__ . '/Tem/header.php'; ?>
<?php require __DIR__ . '/Tem/handbook-directory.php'; ?>
<?php require __DIR__ . '/Tem/footer.php'; ?>
</body>
</html>
