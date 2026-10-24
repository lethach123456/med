<?php
declare(strict_types=1);
$handbookDate = handbook_view_date($updatedAt, $handbookLocale);
$handbookImage = handbook_view_image_url($featured);
?><!doctype html>
<html lang="<?= handbook_view_escape($handbookLocale) ?>">
<head>
<?= site_tag_manager_head() ?>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <?= site_language_metadata_tags($handbookLocale, array_keys($handbookArticleAlternates ?? [])) ?>
  <?= site_favicon_tags() ?>
  <title><?= handbook_view_escape($seoTitle) ?></title>
  <meta name="description" content="<?= handbook_view_escape($seoDescription) ?>">
  <?php if (!$isIndexableArticle): ?><meta name="robots" content="noindex,follow"><?php else: ?>
  <link rel="canonical" href="<?= handbook_view_escape(site_absolute_url($articleCanonicalPath)) ?>">
  <?php foreach (($handbookArticleAlternates ?? []) as $alternateLanguage=>$alternatePath): ?>
  <link rel="alternate" hreflang="<?= handbook_view_escape($alternateLanguage) ?>" href="<?= handbook_view_escape(site_absolute_url($alternatePath)) ?>">
  <?php endforeach; ?>
  <?= site_social_meta_tags([
      'title' => $seoTitle, 'description' => $seoDescription, 'url' => $articleCanonicalPath, 'type' => 'article',
      'image' => $handbookImage, 'image_alt' => $title,
      'modified_time' => $articleModified,
      'section' => $handbookLocale === 'en' ? 'Health guides' : 'Cẩm nang sức khỏe',
  ]) ?>
  <?= site_json_ld($articleSchema) ?>
  <?php endif; ?>
  <?= medical_public_shell_styles() ?>
  <link rel="stylesheet" href="<?= handbook_view_escape(medical_public_asset_url('/assets/css/core/shared-typography.css')) ?>">
  <link rel="stylesheet" href="<?= handbook_view_escape(medical_public_asset_url('/assets/css/pages/handbook.css')) ?>">
  <link rel="stylesheet" href="<?= handbook_view_escape(medical_public_asset_url('/assets/css/pages/handbook-article.css')) ?>">
</head>
<body class="handbook-page">
<?= site_tag_manager_body() ?>
<?php require __DIR__ . '/header.php'; ?>
<main class="hb-article site-typo" id="main">
  <div class="hb-container">
    <nav class="hb-breadcrumb" aria-label="<?= $isEnglishPost ? 'Breadcrumb' : 'Đường dẫn' ?>">
      <a href="<?= handbook_view_escape($homePath) ?>"><?= handbook_view_escape($homeLabel) ?></a><i class="ph ph-caret-right" aria-hidden="true"></i>
      <a href="<?= handbook_view_escape($listingPath) ?>"><?= handbook_view_escape($listingLabel) ?></a><i class="ph ph-caret-right" aria-hidden="true"></i>
      <span aria-current="page"><?= $isEnglishPost ? 'Article' : 'Bài viết' ?></span>
    </nav>
    <article class="hb-reading">
      <header class="hb-article-header">
        <a class="hb-article-kicker" href="<?= handbook_view_escape($listingPath) ?>"><i class="ph ph-book-open-text" aria-hidden="true"></i><?= $isEnglishPost ? 'HEALTH HANDBOOK' : 'CẨM NANG SỨC KHỎE' ?></a>
        <h1><?= handbook_view_escape($title) ?></h1>
        <?php if (trim($excerpt) !== ''): ?><p class="hb-article-lead"><?= handbook_view_escape(handbook_view_plain_text($excerpt)) ?></p><?php endif; ?>
        <div class="hb-article-meta"><span><i class="ph ph-notebook" aria-hidden="true"></i>MedReview</span>
        <?php if ($handbookDate !== []): ?><span><i class="ph ph-calendar-blank" aria-hidden="true"></i><?= handbook_view_escape($updatedLabel) ?> <time datetime="<?= handbook_view_escape($handbookDate['datetime']) ?>"><?= handbook_view_escape($handbookDate['label']) ?></time></span><?php endif; ?></div>
      </header>
      <?php if ($handbookImage !== ''): ?><figure class="hb-article-image"><img <?= medical_media_image_attributes($handbookImage, '(max-width:760px) calc(100vw - 32px), 860px', [640,960,1280], 960) ?> width="960" height="540" alt="<?= handbook_view_escape($title) ?>" decoding="async" fetchpriority="high"></figure><?php endif; ?>
      <div class="hb-article-content"><?= $content ?></div>
      <aside class="hb-note"><span><i class="ph ph-info" aria-hidden="true"></i></span><div><h2><?= $isEnglishPost ? 'For your reference' : 'Thông tin để bạn tham khảo' ?></h2><p><?= $isEnglishPost ? 'This article is for general reference and does not replace a diagnosis or advice from a qualified healthcare professional.' : 'Bài viết cung cấp thông tin tham khảo, không thay thế chẩn đoán hoặc tư vấn từ người có chuyên môn y tế.' ?></p></div></aside>
      <div class="hb-article-bottom"><a class="hb-button" href="<?= handbook_view_escape($listingPath) ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i><?= handbook_view_escape($backLabel) ?></a></div>
    </article>
  </div>
</main>
<?php if ($frontEditorPostKey !== '' && function_exists('front_editor_render')) front_editor_render($frontEditorPostKey); ?>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
