<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_search_cache.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }
$locale = site_page_locale('doctors');
medical_redirect_legacy_path('/bac-si.php', site_localized_path('/bac-si', $locale));
site_language_response_header($locale);
$isEnglish = $locale === 'en';

$seo = front_editor_page_seo('bac-si', [
    'title' => 'Bác sĩ • MedReview',
    'description' => 'Tìm kiếm bác sĩ theo chuyên khoa, khu vực và đánh giá thực tế trên MedReview.',
    'canonical_path' => '/bac-si.php',
]);
$title = (string) ($seo['title'] ?? 'Bác sĩ • MedReview');
$description = (string) ($seo['description'] ?? '');
$canonicalPath = (string) ($seo['canonical_path'] ?? '/bac-si.php');
$seoKeywords = (string) ($seo['keywords'] ?? '');
if ($isEnglish) {
    $title = 'Find a Doctor | MedReview';
    $description = 'Search doctor profiles by specialty and location, and review their professional information and patient ratings on MedReview.';
    $seoKeywords = 'doctors Vietnam, find a doctor, medical specialists, doctor reviews';
}
$canonicalPath = site_localized_path($canonicalPath, $locale);

$ratingFilter = (string) ($_GET['min_rating'] ?? '');
$sortFilter = (string) ($_GET['sort'] ?? 'recommended');
$filters = [
    'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120, 'UTF-8'),
    'city' => mb_substr(trim((string) ($_GET['city'] ?? '')), 0, 120, 'UTF-8'),
    'specialty' => mb_substr(trim((string) ($_GET['specialty'] ?? '')), 0, 120, 'UTF-8'),
    'min_rating' => in_array($ratingFilter, ['4', '4.5'], true) ? $ratingFilter : '',
    'sort' => in_array($sortFilter, ['recommended', 'newest', 'rating', 'reviews'], true) ? $sortFilter : 'recommended',
];
$directoryPage = max(1, (int) ($_GET['page'] ?? 1));
$hasSeoFilters = $filters['q'] !== '' || $filters['city'] !== '' || $filters['specialty'] !== ''
    || $filters['min_rating'] !== '' || $filters['sort'] !== 'recommended';
$isIndexableDirectoryPage = !$hasSeoFilters;
$canonicalPath = site_localized_path('/bac-si', $locale);
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$number = static fn(int $value): string => number_format(max(0, $value), 0, $isEnglish ? '.' : ',', $isEnglish ? ',' : '.');

$index = ['doctors' => [], 'cities' => [], 'counts' => []];
$initial = ['items' => [], 'paging' => ['page' => 1, 'total' => 0, 'total_pages' => 1]];
$cities = [];
$cityCounts = [];
$specialties = [];
$doctorCount = 0;
$reviewCount = 0;
$averageRating = 0.0;
try {
    // The doctor directory uses the same shared JSON TTL snapshot as search,
    // keeping normal page views and filter requests off MySQL.
    $cache = medical_search_cache_index();
    $index = medical_search_cache_doctor_directory_index(medical_search_cache_filter_locale($cache['index'], $locale));
    $result = medical_search_cache_doctor_directory_search(
        $index,
        $filters + ['page' => $directoryPage, 'limit' => 12]
    );
    $initial = ['items' => $result['items'], 'paging' => $result['paging']];

    $specialtySet = [];
    $weightedRating = 0.0;
    foreach ((array) ($index['doctors'] ?? []) as $doctor) {
        if (!is_array($doctor)) continue;
        $doctorCount++;
        $reviews = max(0, (int) ($doctor['reviews_count'] ?? 0));
        $reviewCount += $reviews;
        $weightedRating += (float) ($doctor['rating'] ?? 0) * $reviews;
        $city = trim((string) ($doctor['city'] ?? ''));
        if ($city !== '') $cityCounts[$city] = ($cityCounts[$city] ?? 0) + 1;
        foreach ((array) ($doctor['services'] ?? []) as $specialty) {
            $specialty = trim((string) $specialty);
            if ($specialty !== '') $specialtySet[$specialty] = true;
        }
        $mainSpecialty = trim((string) ($doctor['specialty_text'] ?? ''));
        if ($mainSpecialty !== '') $specialtySet[$mainSpecialty] = true;
    }
    $averageRating = $reviewCount > 0 ? $weightedRating / $reviewCount : 0.0;
    arsort($cityCounts);
    $cities = array_slice(array_keys($cityCounts), 0, 60);
    $specialties = array_keys($specialtySet);
    natcasesort($specialties);
    $specialties = array_slice(array_values($specialties), 0, 60);
} catch (Throwable $e) {
    error_log('doctor directory page failed: ' . $e->getMessage());
}
$canonicalPage = (int) ($initial['paging']['page'] ?? 1);
$canonicalQuery = array_filter($filters, static fn($value, $key): bool => $value !== '' && !($key === 'sort' && $value === 'recommended'), ARRAY_FILTER_USE_BOTH);
if ($canonicalPage > 1) $canonicalQuery['page'] = $canonicalPage;
$canonicalPath = site_localized_path('/bac-si', $locale, $canonicalQuery);
if ($isIndexableDirectoryPage && $canonicalPage > 1) {
    $title .= $isEnglish ? ' — Page ' . $canonicalPage : ' — Trang ' . $canonicalPage;
}

require_once __DIR__ . '/medical_doctor_directory_view.php';
?>
<!doctype html>
<html lang="<?php echo $isEnglish ? 'en' : 'vi'; ?>">
<head>
    <?php echo site_favicon_tags(); ?>
  <meta charset="utf-8">
  <?php echo site_language_metadata_tags($locale, ['vi', 'en']); ?>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $escape($title); ?></title>
  <meta name="description" content="<?php echo $escape($description); ?>">
  <?php if (!$isIndexableDirectoryPage): ?><meta name="robots" content="noindex,follow">
  <?php else: ?><meta name="robots" content="index,follow">
  <?php endif; ?>
  <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo $escape($seoKeywords); ?>"><?php endif; ?>
  <link rel="canonical" href="<?php echo $escape(site_absolute_url($canonicalPath)); ?>">
  <?php echo site_social_meta_tags(['title' => $title, 'description' => $description, 'url' => $canonicalPath, 'type' => 'website']); ?>
  <?php if ($isIndexableDirectoryPage && $directoryPage === 1): ?>
  <link rel="alternate" hreflang="vi" href="<?php echo $escape(site_absolute_url('/bac-si')); ?>">
  <link rel="alternate" hreflang="en" href="<?php echo $escape(site_absolute_url(site_localized_path('/bac-si', 'en'))); ?>">
  <link rel="alternate" hreflang="x-default" href="<?php echo $escape(site_absolute_url('/bac-si')); ?>">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
  <link rel="stylesheet" href="/assets/css/pages/doctor-directory.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/css/pages/doctor-directory.css'); ?>">
  <link rel="stylesheet" href="/assets/css/core/directory-service-labels.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/core/directory-service-labels.css') ?>">
</head>
<body class="doctor-directory-page">
<?php include __DIR__ . '/Tem/header.php'; ?>
<?php require __DIR__ . '/Tem/doctor-directory.php'; ?>
<?php include __DIR__ . '/Tem/footer.php'; ?>
<script src="/assets/js/doctor-directory.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/js/doctor-directory.js'); ?>" defer></script>
</body>
</html>
