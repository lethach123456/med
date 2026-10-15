<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_public_assets.php';
require_once __DIR__ . '/medical_media_variants.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }

$seo = front_editor_page_seo('home', [
  'title' => 'MedReview • Nen tang review y te va co so kham chua benh',
  'description' => 'MedReview giup nguoi dung tim kiem bac si, co so y te, danh muc chuyen khoa va noi dung review huu ich.',
]);
$title = $seo['title'];
$description = $seo['description'];
$canonicalPath = (string) ($seo['canonical_path'] ?? '/');
$seoKeywords = (string) ($seo['keywords'] ?? '');
$locale = site_page_locale('home');
$isEnglish = $locale === 'en';
$homeHeroOriginal = '/uploads/library/2026/07/38252346e52a7957cc10da6fe61849dc.jpg';
$homeHeroResponsive = medical_media_responsive_image($homeHeroOriginal, [640, 960, 1280, 1800], 1280);
$homeHeroMobile = medical_media_responsive_image($homeHeroOriginal, [960], 960)['src'];
if ($isEnglish) {
  $title = 'MedReview | Find trusted healthcare providers in Vietnam';
  $description = 'Discover healthcare facilities, doctors, real reviews and curated Toplists across Vietnam with MedReview.';
  $seoKeywords = 'healthcare Vietnam, clinics, doctors, medical reviews, MedReview';
}
$canonicalPath = site_localized_path($canonicalPath, $locale);
$siteHomeUrl = site_absolute_url('/');
$organizationSchema = [
  '@type' => 'Organization',
  '@id' => $siteHomeUrl . '#organization',
  'name' => site_title('MedReview'),
  'url' => $siteHomeUrl,
];
$siteLogo = site_icon_href('');
if ($siteLogo !== '' && !str_starts_with($siteLogo, 'data:')) {
  $organizationSchema['logo'] = str_starts_with($siteLogo, '/') ? site_absolute_url($siteLogo) : $siteLogo;
}
$homeSchema = [
  '@context' => 'https://schema.org',
  '@graph' => [
    $organizationSchema,
    [
      '@type' => 'WebSite',
      '@id' => $siteHomeUrl . '#website',
      'url' => $siteHomeUrl,
      'name' => site_title('MedReview'),
      'description' => $description,
      'inLanguage' => $locale === 'en' ? 'en' : 'vi-VN',
      'publisher' => ['@id' => $siteHomeUrl . '#organization'],
    ],
  ],
];
?>
<!doctype html>
<html lang="<?php echo $locale === 'en' ? 'en' : 'vi'; ?>">
  <head>
    <?php echo site_favicon_tags(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars(site_absolute_url($canonicalPath), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="vi" href="<?php echo htmlspecialchars(site_absolute_url('/'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars(site_absolute_url('/en'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars(site_absolute_url('/'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preload" as="image" href="<?= htmlspecialchars($homeHeroMobile, ENT_QUOTES, 'UTF-8') ?>" media="(max-width:760px)" fetchpriority="high">
    <link rel="preload" as="image" href="<?= htmlspecialchars($homeHeroResponsive['src'], ENT_QUOTES, 'UTF-8') ?>"<?php if ($homeHeroResponsive['srcset'] !== ''): ?> imagesrcset="<?= htmlspecialchars($homeHeroResponsive['srcset'], ENT_QUOTES, 'UTF-8') ?>" imagesizes="(max-width:1020px) 46vw, 600px"<?php endif; ?> media="(min-width:761px)" fetchpriority="high">
    <?php echo site_json_ld($homeSchema); ?>
    <link rel="preload" as="font" href="/assets/fonts/inter/5.3.0/inter-latin-wght-normal.woff2" type="font/woff2" crossorigin>
    <?php if (!$isEnglish): ?><link rel="preload" as="font" href="/assets/fonts/inter/5.3.0/inter-vietnamese-wght-normal.woff2" type="font/woff2" crossorigin><?php endif; ?>
    <?php echo medical_public_shell_styles('home'); ?>

  </head>
  <body class="site-home">
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <?php include __DIR__ . '/Tem/home.php'; ?>
    <?php if (function_exists('front_editor_render')) { front_editor_render('home'); } ?>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
  </body>
</html>
