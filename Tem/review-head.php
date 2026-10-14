<?php echo site_favicon_tags(); ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= review_view_escape($title) ?></title>
<meta name="description" content="<?= review_view_escape($description) ?>">
<?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?= review_view_escape($seoKeywords) ?>"><?php endif; ?>
<link rel="canonical" href="<?= review_view_escape(site_absolute_url($canonicalPath)) ?>">
<link rel="stylesheet" href="/assets/css/core/shared-typography.css">
<link rel="stylesheet" href="/assets/css/pages/review-common.css?v=<?= filemtime(__DIR__ . '/../assets/css/pages/review-common.css') ?>">
<link rel="stylesheet" href="/assets/css/pages/<?= review_view_escape($reviewStylesheet) ?>?v=<?= filemtime(__DIR__ . '/../assets/css/pages/' . $reviewStylesheet) ?>">
<script src="/assets/js/review.js?v=<?= filemtime(__DIR__ . '/../assets/js/review.js') ?>" defer></script>
