<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_directory.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }

medical_redirect_legacy_path('/co-so-y-te.php', medical_public_facility_path());
$locale = site_page_locale('facilities');
$isEnglish = $locale === 'en';

$seo = front_editor_page_seo('co-so-y-te', [
    'title' => 'Cơ sở y tế • MedReview',
    'description' => 'Khám phá, tìm kiếm và so sánh cơ sở y tế trên MedReview.',
    'canonical_path' => medical_public_facility_path(),
]);
$title = (string) ($seo['title'] ?? 'Cơ sở y tế • MedReview');
$description = (string) ($seo['description'] ?? '');
$canonicalPath = medical_public_facility_path();
$seoKeywords = (string) ($seo['keywords'] ?? '');
if ($isEnglish) {
    $title = 'Healthcare Facilities | MedReview';
    $description = 'Search and compare healthcare facilities in Vietnam by specialty, location, services and real patient reviews.';
    $seoKeywords = 'healthcare facilities Vietnam, clinics, hospitals, medical services, reviews';
}
$canonicalPath = site_localized_path($canonicalPath, $locale);

function facility_page_list_values(?string $value): array
{
    $decoded = json_decode((string) $value, true);
    if (!is_array($decoded)) return [];
    $values = [];
    foreach ($decoded as $item) {
        if (is_array($item)) $item = $item['name'] ?? $item['title'] ?? '';
        $item = trim((string) $item);
        if ($item !== '') $values[] = $item;
    }
    return array_values(array_unique($values));
}

function facility_page_item_from_row(array $row): array
{
    $gallery = medical_directory_gallery_urls((string) ($row['gallery_json'] ?? ''));
    $image = trim((string) ($row['image_url'] ?? ''));
    if ($image === '' && $gallery !== []) $image = (string) $gallery[0];
    $services = facility_page_list_values((string) ($row['featured_services_json'] ?? ''));
    if ($services === []) $services = facility_page_list_values((string) ($row['services_json'] ?? ''));

    return [
        'slug' => (string) ($row['slug'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'category' => (string) ($row['category'] ?? 'Cơ sở y tế'),
        'city' => (string) ($row['city'] ?? ''),
        'subtitle' => trim((string) ($row['subtitle'] ?? '')),
        'address' => trim((string) ($row['address_text'] ?? '')),
        'hours' => trim((string) ($row['hours_text'] ?? '')),
        'rating' => number_format((float) ($row['rating'] ?? 0), 1, '.', ''),
        'reviews_count' => (int) ($row['reviews_count'] ?? 0),
        'followers_count' => (int) ($row['followers_count'] ?? 0),
        'verified' => (bool) ((int) ($row['verified'] ?? 0)),
        'price' => trim((string) ($row['price_text'] ?? '')),
        'image' => $image,
        'image_count' => count($gallery),
        'images_label' => trim((string) ($row['images_label'] ?? '')),
        'services' => array_slice($services, 0, 4),
    ];
}

function facility_page_filters(): array
{
    $rating = (string) ($_GET['min_rating'] ?? '');
    return [
        'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120, 'UTF-8'),
        'city' => mb_substr(trim((string) ($_GET['city'] ?? '')), 0, 120, 'UTF-8'),
        'category' => mb_substr(trim((string) ($_GET['category'] ?? '')), 0, 120, 'UTF-8'),
        'service' => mb_substr(trim((string) ($_GET['service'] ?? '')), 0, 120, 'UTF-8'),
        'min_rating' => in_array($rating, ['4', '4.5'], true) ? $rating : '',
        'sort' => in_array((string) ($_GET['sort'] ?? ''), ['recommended', 'newest', 'rating', 'reviews'], true)
            ? (string) $_GET['sort']
            : 'recommended',
    ];
}

function facility_page_query(PDO $pdo, array $filters, int $page = 1, int $limit = 12): array
{
    $where = ["status = 'published'", "TRIM(COALESCE(content, '')) <> ''"];
    $params = [];
    $queryCity = '';
    if ($filters['q'] !== '' && $filters['city'] === '') {
        $cityCandidates = $pdo->query("SELECT city FROM medical_facilities WHERE status = 'published' AND city <> '' GROUP BY city ORDER BY CHAR_LENGTH(city) DESC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($cityCandidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && mb_stripos($filters['q'], $candidate, 0, 'UTF-8') !== false) {
                $queryCity = $candidate;
                break;
            }
        }
    }
    if ($queryCity !== '') {
        $where[] = 'city = :query_city_exact';
        $params[':query_city_exact'] = $queryCity;
    }
    if ($filters['q'] !== '') {
        $terms = preg_split('/[^\p{L}\p{N}]+/u', $filters['q']) ?: [];
        $terms = array_values(array_unique(array_filter($terms, static fn(string $term): bool => mb_strlen($term, 'UTF-8') >= 2)));
        foreach (array_slice($terms, 0, 6) as $index => $term) {
            $placeholder = ':search_term_' . $index;
            $where[] = "CONCAT_WS(' ', name, subtitle, category, city, address_text, services_json, featured_services_json, slug) LIKE {$placeholder}";
            $params[$placeholder] = '%' . $term . '%';
        }
    }
    foreach (['city', 'category'] as $field) {
        if ($filters[$field] !== '') {
            $where[] = $field . ' = :' . $field;
            $params[':' . $field] = $filters[$field];
        }
    }
    if ($filters['service'] !== '') {
        $where[] = '(COALESCE(featured_services_json, \'\') LIKE :service_featured OR COALESCE(services_json, \'\') LIKE :service_all)';
        $serviceLike = '%' . $filters['service'] . '%';
        $params[':service_featured'] = $serviceLike;
        $params[':service_all'] = $serviceLike;
    }
    if ($filters['min_rating'] !== '') {
        $where[] = 'rating >= :min_rating';
        $params[':min_rating'] = (float) $filters['min_rating'];
    }
    $orderBy = match ($filters['sort']) {
        'newest' => 'updated_at DESC, id DESC',
        'rating' => 'rating DESC, reviews_count DESC, id DESC',
        'reviews' => 'reviews_count DESC, rating DESC, id DESC',
        default => 'rating DESC, reviews_count DESC, display_order ASC, id DESC',
    };
    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare("SELECT COUNT(*) FROM medical_facilities WHERE {$whereSql}");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $limit));
    $page = min(max(1, $page), $pages);
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        "SELECT slug, name, category, city, subtitle, verified, rating, reviews_count, followers_count,
                hours_text, address_text, price_text, image_url, images_label, featured_services_json, services_json, gallery_json
         FROM medical_facilities WHERE {$whereSql}
         ORDER BY {$orderBy} LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return [
        'items' => array_map('facility_page_item_from_row', $stmt->fetchAll(PDO::FETCH_ASSOC)),
        'total' => $total,
        'page' => $page,
        'total_pages' => $pages,
    ];
}

function facility_page_number(int $number): string
{
    return number_format($number, 0, ',', '.');
}

$filters = facility_page_filters();
$requestedPage = max(1, (int) ($_GET['page'] ?? 1));
$initial = ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
$cities = [];
$categories = [];
$services = [];
$cityCounts = [];
$directoryStats = ['facilities' => 0, 'reviews' => 0];

try {
    // The page shell, facets and first page use the same JSON snapshot as the
    // AJAX endpoint. MySQL is only touched when the TTL has expired or an
    // editor/API update invalidates the snapshot.
    $cache = medical_search_cache_index();
    $index = medical_search_cache_facility_directory_index(medical_search_cache_filter_locale($cache['index'], $locale));
    $directory = medical_search_cache_directory_search($index, $filters + ['page' => $requestedPage, 'limit' => 12]);
    $initial = [
        'items' => $directory['items'],
        'total' => (int) $directory['paging']['total'],
        'page' => (int) $directory['paging']['page'],
        'total_pages' => (int) $directory['paging']['total_pages'],
    ];

    $cityCounts = [];
    $categoryCounts = [];
    foreach ((array) ($index['facilities'] ?? []) as $facility) {
        if (!is_array($facility)) continue;
        $directoryStats['facilities']++;
        $directoryStats['reviews'] += max(0, (int) ($facility['reviews_count'] ?? 0));
        $city = trim((string) ($facility['city'] ?? ''));
        $category = trim((string) ($facility['category'] ?? ''));
        if ($city !== '') $cityCounts[$city] = ($cityCounts[$city] ?? 0) + 1;
        if ($category !== '') $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
        foreach ((array) ($facility['services'] ?? []) as $service) {
            $service = trim((string) $service);
            if ($service !== '') $services[$service] = true;
        }
    }
    arsort($cityCounts); arsort($categoryCounts);
    $cities = array_slice(array_keys($cityCounts), 0, 60);
    $categories = array_slice(array_keys($categoryCounts), 0, 40);
    $services = array_slice(array_keys($services), 0, 50);
    natcasesort($services);
    $services = array_values($services);
} catch (Throwable $e) {
    // Render the page shell even if cache storage and DB are both unavailable.
}

require_once __DIR__ . '/medical_facility_directory_view.php';
?>
<!doctype html>
<html lang="<?= $isEnglish ? 'en' : 'vi' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= site_favicon_tags() ?>
  <title><?= facility_directory_escape($title) ?></title>
  <meta name="description" content="<?= facility_directory_escape($description) ?>">
  <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?= facility_directory_escape($seoKeywords) ?>"><?php endif; ?>
  <link rel="canonical" href="<?= facility_directory_escape(site_absolute_url($canonicalPath)) ?>">
  <link rel="alternate" hreflang="vi" href="<?= facility_directory_escape(site_absolute_url(medical_public_facility_path())) ?>">
  <link rel="alternate" hreflang="en" href="<?= facility_directory_escape(site_absolute_url(site_localized_path(medical_public_facility_path(), 'en'))) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= facility_directory_escape(site_absolute_url(medical_public_facility_path())) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
  <link rel="stylesheet" href="/assets/css/pages/facility-directory.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/pages/facility-directory.css') ?>">
</head>
<body class="fd-page">
<a class="fd-skip-link" href="#fdResultsHeading"><?= $isEnglish ? 'Skip to facilities' : 'Đến danh sách cơ sở' ?></a>
<?php include __DIR__ . '/Tem/header.php'; ?>
<?php include __DIR__ . '/Tem/facility-directory.php'; ?>
<?php include __DIR__ . '/Tem/footer.php'; ?>
<script src="/assets/js/facility-directory.js?v=<?= (int) filemtime(__DIR__ . '/assets/js/facility-directory.js') ?>" defer></script>
</body>
</html>
