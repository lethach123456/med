<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

/** @param array<string,mixed> $payload */
function medical_public_search_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * MySQL's Unicode collation still treats Vietnamese đ/Đ differently from
 * ASCII d/D. Normalising this one character supports both “Đà Nẵng” and
 * “Da Nang”, while the database collation handles the remaining accents.
 */
function medical_public_search_normalize(string $value): string
{
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    return str_replace('đ', 'd', $value);
}

/** @return array<int,string> */
function medical_public_search_terms(string $query): array
{
    $tokens = preg_split('/[^\p{L}\p{N}]+/u', medical_public_search_normalize($query)) ?: [];
    $tokens = array_values(array_unique(array_filter($tokens, static function ($token): bool {
        return is_string($token) && mb_strlen($token, 'UTF-8') >= 2;
    })));

    // Intent words make a query friendlier, but do not identify the subject
    // itself. Dropping them lets “spa tại Đà Nẵng” search across category and
    // city even when “tại” is not stored in any record.
    $noise = ['tai', 'o', 'gan', 'uy', 'tin', 'tot', 'gia', 'review', 'danh', 'phu', 'hop', 'cho', 'va', 'cua', 'nhat'];
    $meaningful = array_values(array_filter($tokens, static fn(string $term): bool => !in_array($term, $noise, true)));
    return array_slice($meaningful !== [] ? $meaningful : $tokens, 0, 6);
}

function medical_public_search_detect_city(PDO $pdo, string $query): string
{
    $querySlug = slugify($query);
    if ($querySlug === '') {
        return '';
    }

    $cities = $pdo->query(
        "SELECT city
         FROM medical_facilities
         WHERE status = 'published' AND city <> ''
         GROUP BY city
         ORDER BY CHAR_LENGTH(city) DESC"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];

    foreach ($cities as $city) {
        $city = trim((string) $city);
        $citySlug = slugify($city);
        if ($citySlug !== '' && str_contains($querySlug, $citySlug)) {
            return $city;
        }
    }
    return '';
}

/**
 * @param array<int,string> $terms
 * @param array<string,string> $params
 */
function medical_public_search_where(string $haystack, array $terms, string $prefix, array &$params): string
{
    $where = [];
    $normalisedHaystack = "REPLACE(LOWER(COALESCE({$haystack}, '')), 'đ', 'd')";
    foreach ($terms as $index => $term) {
        $placeholder = ':' . $prefix . '_' . $index;
        $where[] = "{$normalisedHaystack} LIKE {$placeholder}";
        $params[$placeholder] = '%' . $term . '%';
    }
    return $where === [] ? '1 = 1' : implode(' AND ', $where);
}

/** @param array<int,string> $terms */
function medical_public_search_service_text(?string $json, array $terms = []): string
{
    $items = json_decode((string) $json, true);
    if (!is_array($items)) {
        return '';
    }
    $fallback = '';
    foreach ($items as $item) {
        if (is_array($item)) {
            $item = $item['name'] ?? $item['title'] ?? $item['label'] ?? '';
        }
        $item = trim((string) $item);
        if ($item !== '') {
            $item = mb_substr($item, 0, 90, 'UTF-8');
            $fallback = $fallback !== '' ? $fallback : $item;
            $normalisedItem = medical_public_search_normalize($item);
            foreach ($terms as $term) {
                if (mb_strpos($normalisedItem, $term, 0, 'UTF-8') !== false) {
                    return $item;
                }
            }
        }
    }
    return $fallback;
}

$query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120, 'UTF-8');
if (mb_strlen($query, 'UTF-8') < 2) {
    medical_public_search_response([
        'ok' => true,
        'query' => $query,
        'min_length' => 2,
        'groups' => ['facilities' => [], 'doctors' => [], 'toplists' => []],
    ]);
}

try {
    $pdo = db();
    // Autocomplete runs for each keystroke. Keep schema creation/migrations
    // in admin/setup routes rather than adding DDL cost to public requests.
    $limit = min(6, max(1, (int) ($_GET['limit'] ?? 4)));
    $terms = medical_public_search_terms($query);
    if ($terms === []) {
        medical_public_search_response([
            'ok' => true,
            'query' => $query,
            'min_length' => 2,
            'groups' => ['facilities' => [], 'doctors' => [], 'toplists' => []],
        ]);
    }
    $queryCity = medical_public_search_detect_city($pdo, $query);

    $facilityParams = [];
    $facilityHaystack = "CONCAT_WS(' ', name, subtitle, category, city, address_text, featured_services_json, services_json, tags_json, highlights_json, slug)";
    $facilityWhere = medical_public_search_where($facilityHaystack, $terms, 'facility_term', $facilityParams);
    $facilityCityWhere = $queryCity !== '' ? ' AND city = :facility_query_city' : '';
    $facilityStmt = $pdo->prepare(
        "SELECT id, slug, name, category, city, subtitle, image_url, verified, rating, reviews_count,
                featured_services_json, services_json
         FROM medical_facilities
         WHERE status = 'published' AND {$facilityWhere}{$facilityCityWhere}
         ORDER BY (REPLACE(LOWER(name), 'đ', 'd') = :facility_exact_name) DESC,
                  rating DESC, reviews_count DESC, display_order ASC, id DESC
         LIMIT :limit"
    );
    foreach ($facilityParams as $key => $value) {
        $facilityStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    if ($queryCity !== '') {
        $facilityStmt->bindValue(':facility_query_city', $queryCity, PDO::PARAM_STR);
    }
    $facilityStmt->bindValue(':facility_exact_name', medical_public_search_normalize($query), PDO::PARAM_STR);
    $facilityStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $facilityStmt->execute();
    $facilities = $facilityStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($facilities as &$facility) {
        $facility['service_text'] = medical_public_search_service_text((string) ($facility['featured_services_json'] ?? ''), $terms);
        if ($facility['service_text'] === '') {
            $facility['service_text'] = medical_public_search_service_text((string) ($facility['services_json'] ?? ''), $terms);
        }
        unset($facility['featured_services_json'], $facility['services_json']);
        $facility['url'] = medical_public_facility_path((string) $facility['slug']);
    }
    unset($facility);

    $doctorParams = [];
    $doctorHaystack = "CONCAT_WS(' ', name, title_text, specialty_text, city, facility_name, tags_json, specialties_json, bio_json, slug)";
    $doctorWhere = medical_public_search_where($doctorHaystack, $terms, 'doctor_term', $doctorParams);
    $doctorCityWhere = $queryCity !== '' ? ' AND city = :doctor_query_city' : '';
    $doctorStmt = $pdo->prepare(
        "SELECT id, slug, name, title_text, specialty_text, city, facility_name, image_url, verified, rating, reviews_count
         FROM medical_doctors
         WHERE status = 'published' AND {$doctorWhere}{$doctorCityWhere}
         ORDER BY (REPLACE(LOWER(name), 'đ', 'd') = :doctor_exact_name) DESC,
                  rating DESC, reviews_count DESC, display_order ASC, id DESC
         LIMIT :limit"
    );
    foreach ($doctorParams as $key => $value) {
        $doctorStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    if ($queryCity !== '') {
        $doctorStmt->bindValue(':doctor_query_city', $queryCity, PDO::PARAM_STR);
    }
    $doctorStmt->bindValue(':doctor_exact_name', medical_public_search_normalize($query), PDO::PARAM_STR);
    $doctorStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $doctorStmt->execute();
    $doctors = $doctorStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($doctors as &$doctor) {
        $doctor['url'] = '/bac-si-chi-tiet.php?slug=' . rawurlencode((string) $doctor['slug']);
    }
    unset($doctor);

    $toplistParams = [];
    $toplistHaystack = "CONCAT_WS(' ', t.title, t.slug, t.excerpt, t.content, f.name, f.category, f.city, f.featured_services_json, f.services_json)";
    $toplistWhere = medical_public_search_where($toplistHaystack, $terms, 'toplist_term', $toplistParams);
    $toplistCityWhere = $queryCity !== ''
        ? " AND (f.city = :toplist_query_city OR REPLACE(LOWER(CONCAT_WS(' ', t.title, t.excerpt, t.content)), 'đ', 'd') LIKE :toplist_query_city_text)"
        : '';
    $toplistStmt = $pdo->prepare(
        "SELECT t.id, t.slug, t.title, t.excerpt, t.featured_image_url, t.updated_at,
                COUNT(DISTINCT f.id) AS facility_count
         FROM medical_toplists t
         LEFT JOIN medical_toplist_facilities tf ON tf.toplist_id = t.id
         LEFT JOIN medical_facilities f ON f.id = tf.facility_id AND f.status = 'published'
         WHERE t.status = 'published' AND {$toplistWhere}{$toplistCityWhere}
         GROUP BY t.id, t.slug, t.title, t.excerpt, t.featured_image_url, t.updated_at
         ORDER BY (REPLACE(LOWER(t.title), 'đ', 'd') = :toplist_exact_title) DESC,
                  t.updated_at DESC, t.id DESC
         LIMIT :limit"
    );
    foreach ($toplistParams as $key => $value) {
        $toplistStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    if ($queryCity !== '') {
        $toplistStmt->bindValue(':toplist_query_city', $queryCity, PDO::PARAM_STR);
        $toplistStmt->bindValue(':toplist_query_city_text', '%' . medical_public_search_normalize($queryCity) . '%', PDO::PARAM_STR);
    }
    $toplistStmt->bindValue(':toplist_exact_title', medical_public_search_normalize($query), PDO::PARAM_STR);
    $toplistStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $toplistStmt->execute();
    $toplists = $toplistStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($toplists as &$toplist) {
        $toplist['url'] = medical_public_toplist_path((string) $toplist['slug']);
    }
    unset($toplist);

    medical_public_search_response([
        'ok' => true,
        'query' => $query,
        'min_length' => 2,
        'groups' => [
            'facilities' => $facilities,
            'doctors' => $doctors,
            'toplists' => $toplists,
        ],
    ]);
} catch (Throwable $e) {
    error_log('medical public search failed: ' . $e->getMessage());
    medical_public_search_response(['ok' => false, 'message' => 'Không thể tìm kiếm lúc này.'], 500);
}
