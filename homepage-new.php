<?php
declare(strict_types=1);

// Additive preview: the current homepage and its assets remain untouched.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_home_next_data.php';
require_once __DIR__ . '/medical_home_next_view.php';
$GLOBALS['site_page_key'] = 'home';
if (function_exists('admin_front_session_boot')) admin_front_session_boot();
$locale = site_page_locale('home');
$GLOBALS['site_language_links'] = ['current'=>$locale, 'vi'=>'/homepage-new.php?lang=vi', 'en'=>'/homepage-new.php?lang=en'];
$homeNextData = medical_home_next_empty();
try { $homeNextData = medical_home_next_data(db(), $locale); } catch (Throwable $error) { /* Keep the public preview usable without a DB connection. */ }
$homeNextCopy = medical_home_next_copy($locale);
$heroImage = '/uploads/library/2026/07/38252346e52a7957cc10da6fe61849dc.jpg';
$homeNextCssVersion = (string) filemtime(__DIR__ . '/assets/css/pages/home-next.css');
$homeNextJsVersion = (string) filemtime(__DIR__ . '/assets/js/home-next.js');
header('X-Robots-Tag: noindex, follow');
?><!doctype html>
<html lang="<?= home_next_escape($locale) ?>">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <?= site_favicon_tags() ?>
  <title><?= home_next_escape($homeNextCopy['pageTitle']) ?></title>
  <meta name="description" content="<?= home_next_escape($homeNextCopy['heroCopy']) ?>">
  <meta name="robots" content="noindex,follow">
  <link rel="canonical" href="<?= home_next_escape(site_absolute_url(site_localized_path('/', $locale))) ?>">
  <link rel="preload" as="image" href="<?= home_next_escape($heroImage) ?>" fetchpriority="high">
  <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
  <script src="/assets/js/home-next.js?v=<?= home_next_escape($homeNextJsVersion) ?>" defer></script>
</head>
<body class="site-home site-home-next">
<?php include __DIR__ . '/Tem/header.php'; ?>
<link rel="stylesheet" href="/assets/css/pages/home-next.css?v=<?= home_next_escape($homeNextCssVersion) ?>">
<?php require __DIR__ . '/Tem/home-next.php'; ?>
<?php include __DIR__ . '/Tem/footer.php'; ?>
</body>
</html>
