<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/toplist_directory.php';
require_once __DIR__ . '/medical_directory.php';
require_once __DIR__ . '/medical_toplist_directory_view.php';
medical_redirect_legacy_path('/toplist.php', medical_public_toplist_path());
$locale = site_page_locale('toplist');
site_language_response_header($locale);
$isEnglish = $locale === 'en';
$pdo = db();
$hasToplistLanguage = medreview_translation_schema_ready($pdo, 'medical_toplists');
if (!$hasToplistLanguage && $locale === 'en') {
  $rows = [];
} else {
  $languageClause = $hasToplistLanguage ? ' AND t.language_code=:locale' : '';
  $toplistRows = $pdo->prepare("SELECT t.id,t.title,t.slug,t.language_code,t.entity_type,t.excerpt,t.featured_image_url,t.updated_at,
    (SELECT COUNT(*) FROM medical_toplist_facilities tf JOIN medical_facilities f ON f.id=tf.facility_id WHERE tf.toplist_id=t.id AND f.status='published' AND f.language_code=t.language_code) AS facility_count,
    (SELECT COUNT(*) FROM medical_toplist_doctors td JOIN medical_doctors d ON d.id=td.doctor_id WHERE td.toplist_id=t.id AND d.status='published' AND d.language_code=t.language_code) AS doctor_count
    FROM medical_toplists t WHERE t.status='published'{$languageClause} ORDER BY t.updated_at DESC,t.id DESC");
  $toplistRows->execute($hasToplistLanguage ? [':locale' => $locale] : []);
  $rows = $toplistRows->fetchAll(PDO::FETCH_ASSOC);
}
foreach ($rows as &$row) {
  $row['member_count'] = $row['entity_type'] === 'mixed' ? (int) $row['facility_count'] + (int) $row['doctor_count'] : (int) ($row[$row['entity_type'] === 'doctor' ? 'doctor_count' : 'facility_count'] ?? 0);
}
unset($row);
$toplistFilters = toplist_view_filters($_GET);
$toplistNoindex = $toplistFilters['q'] !== '' || $toplistFilters['type'] !== '' || $toplistFilters['sort'] !== 'updated';
$toplistPaging = toplist_view_paginate($rows, $toplistFilters, toplist_view_requested_page($_GET));
$toplistPageItems = $toplistPaging['items'];
// Build image collages only for the visible page, not for every published list.
foreach ($toplistPageItems as &$row) {
  $images = [];
  foreach (toplist_directory_linked_rows($pdo, $row, 4) as $facility) {
    $image = trim((string) ($facility['image_url'] ?? ''));
    if ($image === '') {
      $gallery = medical_directory_gallery_urls((string) ($facility['gallery_json'] ?? ''));
      $image = trim((string) ($gallery[0] ?? ''));
    }
    $image = site_absolute_media_url($image);
    if ($image !== '') { $images[] = $image; }
  }
  $row['featured_image_url'] = site_absolute_media_url((string) ($row['featured_image_url'] ?? ''));
  $row['collage_images'] = array_values(array_unique($images));
  if ($row['collage_images'] === [] && $row['featured_image_url'] !== '') {
    $row['collage_images'][] = $row['featured_image_url'];
  } elseif ($row['featured_image_url'] !== '' && count($row['collage_images']) < 4 && !in_array($row['featured_image_url'], $row['collage_images'], true)) {
    $row['collage_images'][] = $row['featured_image_url'];
  }
}
unset($row);
$seo = front_editor_page_seo('toplist', [
  'title' => 'Toplist cơ sở y tế & bác sĩ | MedReview',
  'description' => 'Khám phá danh sách cơ sở y tế và bác sĩ theo chuyên khoa, nhu cầu và khu vực để có thêm góc nhìn trước khi lựa chọn.',
  'canonical_path' => medical_public_toplist_path(),
]);
$seoTitle = (string) ($seo['title'] ?? 'Toplist cơ sở y tế | MedReview');
$seoDescription = (string) ($seo['description'] ?? '');
$seoKeywords = (string) ($seo['keywords'] ?? '');
$seoCanonical = (string) ($seo['canonical_path'] ?? medical_public_toplist_path());
if ($isEnglish) {
  $seoTitle = 'Healthcare & Doctor Toplists | MedReview';
  $seoDescription = 'Explore curated lists of healthcare facilities and doctors on MedReview. Get to know your options before making a choice.';
  $seoKeywords = 'healthcare Toplists, doctor lists, compare clinics, Vietnam healthcare';
}
$seoCanonical = site_localized_path($seoCanonical, $locale);
$seoCanonical = toplist_view_page_url($seoCanonical, $toplistFilters, $toplistPaging['page']);
if ($toplistPaging['page'] > 1) $seoTitle .= ($isEnglish ? ' — Page ' : ' — Trang ') . $toplistPaging['page'];
$toplistStyleVersion = (string) filemtime(__DIR__ . '/assets/css/pages/toplist-directory.css');
$toplistScriptVersion = (string) filemtime(__DIR__ . '/assets/js/toplist-directory.js');
?><!doctype html>
<html lang="<?= $isEnglish ? 'en' : 'vi' ?>">
<head>
  <?= site_tag_manager_head() ?>
  <?php echo site_favicon_tags(); ?>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <?= site_language_metadata_tags($locale, !$toplistNoindex && $toplistPaging['page'] === 1 ? ['vi', 'en'] : []) ?>
  <title><?= toplist_view_escape($seoTitle) ?></title>
  <meta name="description" content="<?= toplist_view_escape($seoDescription) ?>">
  <?php if ($toplistNoindex): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
  <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?= toplist_view_escape($seoKeywords) ?>"><?php endif; ?>
  <link rel="canonical" href="<?= toplist_view_escape(site_absolute_url($seoCanonical)) ?>">
  <?= site_social_meta_tags(['title' => $seoTitle, 'description' => $seoDescription, 'url' => $seoCanonical, 'type' => 'website']) ?>
  <?php if (!$toplistNoindex && $toplistPaging['page'] === 1): // Translations can have different counts; do not invent an equivalent page N. ?>
  <link rel="alternate" hreflang="vi" href="<?= toplist_view_escape(site_absolute_url(medical_public_toplist_path())) ?>">
  <link rel="alternate" hreflang="en" href="<?= toplist_view_escape(site_absolute_url(site_localized_path(medical_public_toplist_path(), 'en'))) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= toplist_view_escape(site_absolute_url(medical_public_toplist_path())) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
  <link rel="stylesheet" href="/assets/css/pages/toplist-directory.css?v=<?= toplist_view_escape($toplistStyleVersion) ?>">
  <script src="/assets/js/toplist-directory.js?v=<?= toplist_view_escape($toplistScriptVersion) ?>" defer></script>
</head>
<body class="toplist-directory-page">
<?= site_tag_manager_body() ?>
<?php include __DIR__ . '/Tem/header.php'; ?>
<?php require __DIR__ . '/Tem/toplist-directory.php'; ?>
<?php include __DIR__ . '/Tem/footer.php'; ?>
</body></html>
