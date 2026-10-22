<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_directory.php';
require_once __DIR__ . '/medical_review_articles.php';
require_once __DIR__ . '/medical_review_articles_view.php';
require_once __DIR__ . '/medical_public_assets.php';
require_once __DIR__ . '/medical_media_variants.php';
if (function_exists('admin_front_session_boot')) admin_front_session_boot();
$GLOBALS['site_page_key'] = 'reviews';
$locale = site_page_locale('reviews');
site_language_response_header($locale);
$isEnglish = $locale === 'en';
$GLOBALS['site_forced_locale'] = $locale;
$GLOBALS['site_language_links'] = ['vi' => '/review', 'en' => '/en/review', 'current' => $locale];
$reviewArticleFilters = review_articles_view_filters($_GET);
$reviewArticleUnavailable = false;
try {
    $reviewArticlePaging = medical_review_articles_directory_page(db(), $locale, $reviewArticleFilters, review_articles_view_page($_GET['page'] ?? 1), 9);
} catch (Throwable $error) {
    error_log('Editorial review directory unavailable: ' . get_class($error));
    http_response_code(503);
    $reviewArticleUnavailable = true;
    $reviewArticlePaging = ['items' => [], 'total' => 0, 'published_total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 9, 'start' => 0, 'end' => 0, 'facets' => ['categories' => [], 'cities' => []]];
}
$title = $isEnglish ? 'Healthcare facility reviews & analysis | MedReview' : 'Review & phân tích cơ sở y tế | MedReview';
$description = $isEnglish ? 'Read clear, source-based analysis of healthcare facilities, services and public feedback. Understand the evidence and its limitations before choosing.' : 'Đọc bài phân tích cơ sở y tế từ thông tin hồ sơ, đánh giá và nguồn công khai. Hiểu điểm nổi bật, điều cần cân nhắc và giới hạn dữ liệu trước khi lựa chọn.';
if ($reviewArticlePaging['page'] > 1) $title .= ($isEnglish ? ' — Page ' : ' — Trang ') . $reviewArticlePaging['page'];
$canonicalPath = review_articles_view_page_url($locale, $reviewArticleFilters, $reviewArticlePaging['page']);
$reviewArticleNoindex = $reviewArticleUnavailable || count(array_filter($reviewArticleFilters)) > 0;
$e = 'review_articles_view_escape';
?><!doctype html>
<html lang="<?= $e($locale) ?>">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <?= site_language_metadata_tags($locale, !$reviewArticleNoindex && $reviewArticlePaging['page'] === 1 ? ['vi', 'en'] : []) ?>
  <?= site_favicon_tags() ?>
  <title><?= $e($title) ?></title>
  <meta name="description" content="<?= $e($description) ?>">
  <?php if ($reviewArticleNoindex): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
  <link rel="canonical" href="<?= $e(site_absolute_url($canonicalPath)) ?>">
  <?= site_social_meta_tags(['title' => $title, 'description' => $description, 'url' => $canonicalPath, 'type' => 'website']) ?>
  <?php if (!$reviewArticleNoindex && $reviewArticlePaging['page'] === 1): ?>
  <link rel="alternate" hreflang="vi" href="<?= $e(site_absolute_url('/review')) ?>">
  <link rel="alternate" hreflang="en" href="<?= $e(site_absolute_url('/en/review')) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= $e(site_absolute_url('/review')) ?>">
  <?php endif; ?>
  <?php if ($reviewArticlePaging['page'] > 1): ?><link rel="prev" href="<?= $e(site_absolute_url(review_articles_view_page_url($locale, $reviewArticleFilters, $reviewArticlePaging['page'] - 1))) ?>"><?php endif; ?>
  <?php if ($reviewArticlePaging['page'] < $reviewArticlePaging['pages']): ?><link rel="next" href="<?= $e(site_absolute_url(review_articles_view_page_url($locale, $reviewArticleFilters, $reviewArticlePaging['page'] + 1))) ?>"><?php endif; ?>
  <?= medical_public_shell_styles() ?>
  <link rel="stylesheet" href="<?= $e(medical_public_asset_url('/assets/css/core/shared-typography.css')) ?>">
  <link rel="stylesheet" href="<?= $e(medical_public_asset_url('/assets/css/pages/review-articles.css')) ?>">
  <script src="<?= $e(medical_public_asset_url('/assets/js/review-articles.js')) ?>" defer></script>
</head>
<body class="review-articles-page">
<a class="ra-skip" href="#main-content"><?= $isEnglish ? 'Skip to review articles' : 'Đến các bài review' ?></a>
<?php include __DIR__ . '/Tem/header.php'; ?>
<?php require __DIR__ . '/Tem/review-articles-directory.php'; ?>
<?php include __DIR__ . '/Tem/footer.php'; ?>
</body></html>
