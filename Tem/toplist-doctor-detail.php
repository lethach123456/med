<?php
declare(strict_types=1);
require_once __DIR__ . '/../medical_toplist_detail_view.php';
$tdLabels = medical_toplist_detail_labels($toplistLanguage, $toplistEntityType === 'mixed');
$tdMembers = medical_toplist_detail_members($linkedRows, $toplistLanguage);
$tdEscape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tdSchema = ['@context'=>'https://schema.org', '@type'=>'ItemList', 'name'=>$title,
    'numberOfItems'=>count($tdMembers), 'itemListElement'=>[]];
foreach ($tdMembers as $index=>$member) {
    $tdSchema['itemListElement'][] = ['@type'=>'ListItem', 'position'=>$index+1,
        'item'=>['@type'=>$member['type'] === 'doctor' ? 'Physician' : 'MedicalClinic',
            'name'=>$member['name'], 'url'=>site_absolute_url($member['profile_url'])]];
}
?>
<!doctype html>
<html lang="<?= $tdEscape($toplistLanguage) ?>">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <?= site_language_metadata_tags($toplistLanguage, !empty($toplistLanguageLinks['has_counterpart']) ? ['vi', 'en'] : []) ?>
  <title><?= $tdEscape($title) ?> | MedReview</title>
  <meta name="description" content="<?= $tdEscape($description) ?>">
  <link rel="canonical" href="<?= $tdEscape($canonicalUrl) ?>">
  <?php if (!empty($toplistLanguageLinks['has_counterpart'])): foreach (['vi','en'] as $language): ?>
    <link rel="alternate" hreflang="<?= $language ?>" href="<?= $tdEscape(site_absolute_url($toplistLanguageLinks[$language])) ?>">
  <?php endforeach; endif; ?>
  <?= site_social_meta_tags([
      'title' => $title, 'description' => $description, 'url' => $canonicalUrl, 'type' => 'article',
      'image' => $heroImage, 'image_alt' => $heroImageAlt,
      'modified_time' => (string) ($toplist['updated_at'] ?? ''),
      'section' => 'Toplist',
  ]) ?>
  <?= site_favicon_tags() ?><?= site_json_ld($toplistSchema) ?><?= site_json_ld($tdSchema) ?>
  <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
  <link rel="stylesheet" href="/assets/css/pages/toplist-doctors.css?v=<?= filemtime(__DIR__ . '/../assets/css/pages/toplist-doctors.css') ?>">
  <script src="/assets/js/toplist-doctor-detail.js?v=<?= filemtime(__DIR__ . '/../assets/js/toplist-doctor-detail.js') ?>" defer></script>
</head>
<body class="doctor-toplist-page">
<?php include __DIR__ . '/header.php'; ?>
<?php require __DIR__ . '/toplist-doctor-detail-content.php'; ?>
<?php include __DIR__ . '/footer.php'; ?>
</body></html>
