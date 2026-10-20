<?php echo site_favicon_tags(); ?>
<meta charset="utf-8">
<?= site_language_metadata_tags($locale, !empty($reviewDirectoryScript) ? ['vi', 'en'] : []) ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= review_view_escape($title) ?></title>
<meta name="description" content="<?= review_view_escape($description) ?>">
<?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?= review_view_escape($seoKeywords) ?>"><?php endif; ?>
<link rel="canonical" href="<?= review_view_escape(site_absolute_url($canonicalPath)) ?>">
<?= site_social_meta_tags([
    'title' => $title, 'description' => $description, 'url' => $canonicalPath,
    'type' => !empty($reviewDirectoryScript) ? 'website' : 'article',
    'image' => !empty($reviewDirectoryScript) ? '' : (string) ($review['media'][0]['url'] ?? ''),
    'image_alt' => !empty($reviewDirectoryScript) ? '' : (string) ($review['media'][0]['alt'] ?? $review['title'] ?? ''),
    'section' => !empty($reviewDirectoryScript) ? '' : ($locale === 'en' ? 'Healthcare experiences' : 'Trải nghiệm y tế'),
]) ?>
<link rel="stylesheet" href="/assets/css/core/shared-typography.css">
<link rel="stylesheet" href="/assets/css/pages/review-common.css?v=<?= filemtime(__DIR__ . '/../assets/css/pages/review-common.css') ?>">
<link rel="stylesheet" href="/assets/css/pages/<?= review_view_escape($reviewStylesheet) ?>?v=<?= filemtime(__DIR__ . '/../assets/css/pages/' . $reviewStylesheet) ?>">
<?php if (!empty($reviewDirectoryScript)): ?><link rel="stylesheet" href="/assets/css/pages/review-directory-controls.css?v=<?= filemtime(__DIR__ . '/../assets/css/pages/review-directory-controls.css') ?>"><?php endif; ?>
<script src="/assets/js/review.js?v=<?= filemtime(__DIR__ . '/../assets/js/review.js') ?>" defer></script>
<?php if (!empty($reviewDirectoryScript)): ?><script src="/assets/js/review-directory.js?v=<?= filemtime(__DIR__ . '/../assets/js/review-directory.js') ?>" defer></script><?php endif; ?>
