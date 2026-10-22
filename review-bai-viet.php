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
$notFoundLocale = $locale;
$slug = review_articles_view_string($_GET['slug'] ?? '', 191);
try {
    $reviewArticle = medical_review_articles_find_public(db(), $slug, $locale);
} catch (Throwable $error) {
    error_log('Editorial review detail unavailable: ' . get_class($error));
    http_response_code(503);
    $notFoundTitle = $isEnglish ? 'This article could not be loaded' : 'Chưa tải được bài phân tích';
    $notFoundDescription = $isEnglish ? 'Please try again shortly. The published content has not been changed.' : 'Vui lòng thử lại sau ít phút. Nội dung đã xuất bản vẫn được giữ nguyên.';
    require __DIR__ . '/Tem/public-404.php';
    exit;
}
if ($reviewArticle === null) {
    http_response_code(404);
    $notFoundTitle = $isEnglish ? 'Review article not found' : 'Không tìm thấy bài review';
    $notFoundDescription = $isEnglish ? 'This article does not exist in this language or is not published.' : 'Bài viết không tồn tại trong ngôn ngữ này hoặc chưa được xuất bản.';
    require __DIR__ . '/Tem/public-404.php';
    exit;
}
$reviewArticleAlternates = is_array($reviewArticle['alternates'] ?? null) ? $reviewArticle['alternates'] : [];
$GLOBALS['site_language_links'] = ['current' => $locale, 'vi' => $reviewArticleAlternates['vi'] ?? '/review', 'en' => $reviewArticleAlternates['en'] ?? '/en/review'];
$reviewArticleFacility = is_array($reviewArticle['facility'] ?? null) ? $reviewArticle['facility'] : [];
$reviewArticleSections = review_articles_view_sections($reviewArticle, $locale);
$reviewArticleToc = review_articles_view_toc($reviewArticle, $reviewArticleSections, $locale);
$reviewArticleRelated = [];
try {
    $relatedPaging = medical_review_articles_directory_page(db(), $locale, ['category' => review_articles_view_string($reviewArticleFacility['category'] ?? '')], 1, 4);
    foreach ($relatedPaging['items'] as $relatedArticle) if ($relatedArticle['id'] !== $reviewArticle['id']) $reviewArticleRelated[] = $relatedArticle;
    $reviewArticleRelated = array_slice($reviewArticleRelated, 0, 3);
} catch (Throwable $error) { error_log('Editorial review related articles unavailable: ' . get_class($error)); }
$reviewArticleTitle = review_articles_view_string($reviewArticle['seo_title'] ?? '') ?: review_articles_view_string($reviewArticle['title']);
$reviewArticleTitle .= ' | MedReview';
$reviewArticleDescription = site_meta_description(review_articles_view_string($reviewArticle['seo_description'] ?? '') ?: review_articles_view_string($reviewArticle['excerpt'] ?? '') ?: review_articles_view_string($reviewArticle['title']));
$reviewArticleCanonical = review_articles_view_path($locale, $reviewArticle['slug']);
$reviewArticleImage = review_articles_view_safe_url($reviewArticle['cover_image_url'] ?? '');
$reviewArticleSchema = ['@context' => 'https://schema.org', '@type' => 'Article',
    'headline' => $reviewArticle['title'], 'description' => $reviewArticleDescription,
    'mainEntityOfPage' => site_absolute_url($reviewArticleCanonical), 'inLanguage' => $locale,
    'author' => ['@type' => 'Organization', 'name' => 'MedReview', 'url' => site_absolute_url('/')],
    'publisher' => ['@type' => 'Organization', 'name' => 'MedReview', 'url' => site_absolute_url('/')],
    'articleSection' => $isEnglish ? 'Healthcare facility analysis' : 'Phân tích cơ sở y tế'];
foreach (['published_at' => 'datePublished', 'updated_at' => 'dateModified'] as $column => $schemaKey) {
    $schemaDate = review_articles_view_date($reviewArticle[$column] ?? '', $locale);
    if ($schemaDate !== []) $reviewArticleSchema[$schemaKey] = $schemaDate['datetime'];
}
if ($reviewArticleImage !== '') $reviewArticleSchema['image'] = site_absolute_media_url($reviewArticleImage);
if (review_articles_view_string($reviewArticleFacility['name'] ?? '') !== '') $reviewArticleSchema['about'] = ['@type' => 'MedicalOrganization', 'name' => $reviewArticleFacility['name'], 'url' => site_absolute_url($reviewArticleFacility['url'])];
$e = 'review_articles_view_escape';
?><!doctype html>
<html lang="<?= $e($locale) ?>">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <?= site_language_metadata_tags($locale, array_keys($reviewArticleAlternates)) ?>
  <?= site_favicon_tags() ?>
  <title><?= $e($reviewArticleTitle) ?></title>
  <meta name="description" content="<?= $e($reviewArticleDescription) ?>">
  <link rel="canonical" href="<?= $e(site_absolute_url($reviewArticleCanonical)) ?>">
  <?php foreach ($reviewArticleAlternates as $alternateLocale => $alternatePath): if (!in_array($alternateLocale, ['vi', 'en'], true)) continue; ?><link rel="alternate" hreflang="<?= $e($alternateLocale) ?>" href="<?= $e(site_absolute_url($alternatePath)) ?>"><?php endforeach; ?>
  <?= site_social_meta_tags(['title' => $reviewArticleTitle, 'description' => $reviewArticleDescription, 'url' => $reviewArticleCanonical, 'type' => 'article', 'image' => $reviewArticleImage, 'image_alt' => $reviewArticleFacility['name'] ?? $reviewArticle['title'], 'published_time' => $reviewArticle['published_at'] ?? '', 'modified_time' => $reviewArticle['updated_at'] ?? '', 'section' => $isEnglish ? 'Healthcare facility analysis' : 'Phân tích cơ sở y tế']) ?>
  <?= site_json_ld($reviewArticleSchema) ?>
  <?= medical_public_shell_styles() ?>
  <link rel="stylesheet" href="<?= $e(medical_public_asset_url('/assets/css/core/shared-typography.css')) ?>">
  <link rel="stylesheet" href="<?= $e(medical_public_asset_url('/assets/css/pages/review-articles.css')) ?>">
  <script src="<?= $e(medical_public_asset_url('/assets/js/review-articles.js')) ?>" defer></script>
</head>
<body class="review-articles-page">
<a class="ra-skip" href="#main-content"><?= $isEnglish ? 'Skip to article' : 'Đến nội dung bài viết' ?></a>
<?php require __DIR__ . '/Tem/header.php'; ?>
<?php require __DIR__ . '/Tem/review-article.php'; ?>
<?php require __DIR__ . '/Tem/footer.php'; ?>
</body>
</html>
