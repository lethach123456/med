<?php
declare(strict_types=1);

/**
 * Lightweight file index for public search suggestions.
 *
 * The autocomplete endpoint is hit on nearly every keystroke.  Reading this
 * compact JSON index keeps those requests away from MySQL while still letting
 * the source database remain the single place where content is edited.
 */
require_once __DIR__ . '/db.php';

function medical_search_cache_dir(): string
{
    $configured = trim((string) (getenv('MEDICAL_SEARCH_CACHE_DIR') ?: ''));
    return $configured !== '' ? rtrim($configured, '/\\') : __DIR__ . '/storage/cache';
}

function medical_search_cache_path(): string
{
    return medical_search_cache_dir() . '/medical-search-index-v1.json';
}

function medical_search_cache_lock_path(): string
{
    return medical_search_cache_dir() . '/medical-search-index-v1.lock';
}

function medical_search_cache_invalidation_path(): string
{
    return medical_search_cache_dir() . '/medical-search-index-v1.invalidated';
}

function medical_search_cache_ttl(): int
{
    $configured = (int) (getenv('MEDICAL_SEARCH_CACHE_TTL') ?: 0);
    // Ten minutes is a good default for autocomplete: fresh enough for the
    // directory, but it turns thousands of keystrokes into one DB rebuild.
    return min(86400, max(60, $configured > 0 ? $configured : 600));
}

function medical_search_cache_ensure_dir(): bool
{
    $directory = medical_search_cache_dir();
    return is_dir($directory) || @mkdir($directory, 0755, true);
}

/** Normalise accents and punctuation in the same way as public URL slugs. */
function medical_search_cache_normalize(string $value): string
{
    $value = slugify($value);
    return trim(str_replace('-', ' ', $value));
}

/** @return array<int,string> */
function medical_search_cache_terms(string $query): array
{
    $query = medical_search_cache_normalize($query);
    $tokens = preg_split('/\s+/', $query) ?: [];
    $tokens = array_values(array_unique(array_filter($tokens, static function ($token): bool {
        return is_string($token) && strlen($token) >= 2;
    })));

    // Intent words make natural Vietnamese searches much friendlier.  For
    // example, "spa tại Đà Nẵng" should match both category and city.
    $noise = ['tai', 'o', 'gan', 'uy', 'tin', 'tot', 'gia', 'review', 'danh', 'phu', 'hop', 'cho', 'va', 'cua', 'nhat'];
    $meaningful = array_values(array_filter($tokens, static fn(string $term): bool => !in_array($term, $noise, true)));
    return array_slice($meaningful !== [] ? $meaningful : $tokens, 0, 6);
}

/** @return array<int,string> */
function medical_search_cache_json_strings(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : $value;
    }

    $items = [];
    $collect = static function (mixed $part) use (&$collect, &$items): void {
        if (is_string($part) || is_numeric($part)) {
            $text = trim((string) $part);
            // URLs do not help a person find a facility and needlessly bloat
            // the cache.
            if ($text !== '' && !preg_match('~^https?://~i', $text)) {
                $items[] = $text;
            }
            return;
        }
        if (!is_array($part)) {
            return;
        }
        foreach ($part as $key => $child) {
            if (is_string($key) && in_array($key, ['url', 'src', 'image', 'image_url'], true)) {
                continue;
            }
            $collect($child);
        }
    };
    $collect($value);

    $out = [];
    foreach ($items as $item) {
        $item = trim($item);
        if ($item !== '' && !in_array($item, $out, true)) {
            $out[] = mb_substr($item, 0, 180, 'UTF-8');
        }
    }
    return $out;
}

/** @return array<int,string> */
function medical_search_cache_gallery_urls(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) {
        return [];
    }

    $urls = [];
    foreach ($value as $item) {
        if (is_array($item)) {
            $item = $item['url'] ?? $item['src'] ?? $item['image_url'] ?? '';
        }
        $url = trim((string) $item);
        if ($url !== '' && !in_array($url, $urls, true)) {
            $urls[] = $url;
        }
    }
    return $urls;
}

/** @return array<string,mixed>|null */
function medical_search_cache_read(): ?array
{
    $path = medical_search_cache_path();
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || (int) ($decoded['schema'] ?? 0) !== 1) {
        return null;
    }
    foreach (['facilities', 'doctors', 'toplists', 'cities'] as $key) {
        if (!isset($decoded[$key]) || !is_array($decoded[$key])) {
            return null;
        }
    }
    return $decoded;
}

function medical_search_cache_is_fresh(array $index): bool
{
    $generatedAt = (int) ($index['generated_at'] ?? 0);
    $expiresAt = (int) ($index['expires_at'] ?? 0);
    if ($generatedAt <= 0 || $expiresAt < time()) {
        return false;
    }
    $invalidatedAt = @filemtime(medical_search_cache_invalidation_path());
    return !is_int($invalidatedAt) || $invalidatedAt <= $generatedAt;
}

/** @param array<string,mixed> $index */
function medical_search_cache_write(array $index): bool
{
    if (!medical_search_cache_ensure_dir()) {
        return false;
    }
    $json = json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return false;
    }
    $path = medical_search_cache_path();
    $temporary = @tempnam(medical_search_cache_dir(), 'medical-search-');
    if ($temporary === false) {
        return false;
    }
    $written = @file_put_contents($temporary, $json, LOCK_EX);
    if ($written === false) {
        @unlink($temporary);
        return false;
    }
    @chmod($temporary, 0644);
    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        return false;
    }
    return true;
}

/**
 * Mark the index stale without deleting it.  A concurrent request can still
 * use the previous healthy file while one process rebuilds it.
 */
function medical_search_cache_invalidate(): void
{
    if (medical_search_cache_ensure_dir()) {
        $path = medical_search_cache_invalidation_path();
        // filemtime has second precision on some shared hosts. Advance the
        // marker beyond both the last marker *and the current index* so an
        // edit made in the same second as a rebuild is never accidentally
        // treated as already indexed.
        $previous = @filemtime($path);
        $current = medical_search_cache_read();
        $generatedAt = is_array($current) ? (int) ($current['generated_at'] ?? 0) : 0;
        $timestamp = max(
            time(),
            $generatedAt > 0 ? $generatedAt + 1 : 0,
            is_int($previous) ? $previous + 1 : 0
        );
        @touch($path, $timestamp);
    }
}

/** @return array<int,array<string,mixed>> */
function medical_search_cache_facilities(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT id, slug, name, subtitle, category, city, address_text, image_url, gallery_json,
                verified, rating, reviews_count, followers_count, price_text, hours_text, images_label,
                featured_services_json, services_json, tags_json, highlights_json, display_order, updated_at
         FROM medical_facilities
         WHERE status = 'published'"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = [];
    foreach ($rows as $row) {
        $services = array_values(array_unique(array_merge(
            medical_search_cache_json_strings($row['featured_services_json'] ?? ''),
            medical_search_cache_json_strings($row['services_json'] ?? '')
        )));
        $gallery = medical_search_cache_gallery_urls($row['gallery_json'] ?? '');
        $image = trim((string) ($row['image_url'] ?? ''));
        if ($image === '' && $gallery !== []) {
            $image = $gallery[0];
        }
        $searchable = array_merge([
            (string) ($row['name'] ?? ''),
            (string) ($row['subtitle'] ?? ''),
            (string) ($row['category'] ?? ''),
            (string) ($row['city'] ?? ''),
            (string) ($row['address_text'] ?? ''),
            (string) ($row['slug'] ?? ''),
        ], $services, medical_search_cache_json_strings($row['tags_json'] ?? ''), medical_search_cache_json_strings($row['highlights_json'] ?? ''));

        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'url' => medical_public_facility_path((string) ($row['slug'] ?? '')),
            'name' => (string) ($row['name'] ?? ''),
            'subtitle' => trim((string) ($row['subtitle'] ?? '')),
            'category' => trim((string) ($row['category'] ?? 'Cơ sở y tế')),
            'city' => trim((string) ($row['city'] ?? '')),
            'address' => trim((string) ($row['address_text'] ?? '')),
            'image_url' => $image,
            'verified' => (bool) ((int) ($row['verified'] ?? 0)),
            'rating' => (float) ($row['rating'] ?? 0),
            'reviews_count' => (int) ($row['reviews_count'] ?? 0),
            'followers_count' => (int) ($row['followers_count'] ?? 0),
            'price' => trim((string) ($row['price_text'] ?? '')),
            'hours' => trim((string) ($row['hours_text'] ?? '')),
            'images_label' => trim((string) ($row['images_label'] ?? '')),
            'image_count' => count($gallery),
            // Keep the full service list in the index. The directory card
            // decides how many labels to render, while filters and facets
            // must still be able to find a service beyond the first 16.
            'services' => $services,
            'name_key' => medical_search_cache_normalize((string) ($row['name'] ?? '')),
            'category_key' => medical_search_cache_normalize((string) ($row['category'] ?? '')),
            'city_key' => medical_search_cache_normalize((string) ($row['city'] ?? '')),
            'search_text' => medical_search_cache_normalize(implode(' ', $searchable)),
            'display_order' => (int) ($row['display_order'] ?? 0),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
    return $items;
}

/** @return array<int,array<string,mixed>> */
function medical_search_cache_doctors(PDO $pdo): array
{
    try {
        $rows = $pdo->query(
            "SELECT id, slug, name, title_text, specialty_text, city, facility_name, image_url,
                    verified, rating, reviews_count, tags_json, specialties_json, bio_json, display_order, updated_at
             FROM medical_doctors
             WHERE status = 'published'"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }

    $items = [];
    foreach ($rows as $row) {
        $specialties = array_values(array_unique(array_merge(
            medical_search_cache_json_strings($row['specialties_json'] ?? ''),
            medical_search_cache_json_strings($row['specialty_text'] ?? '')
        )));
        $searchable = array_merge([
            (string) ($row['name'] ?? ''),
            (string) ($row['title_text'] ?? ''),
            (string) ($row['specialty_text'] ?? ''),
            (string) ($row['city'] ?? ''),
            (string) ($row['facility_name'] ?? ''),
            (string) ($row['slug'] ?? ''),
        ], $specialties, medical_search_cache_json_strings($row['tags_json'] ?? ''), medical_search_cache_json_strings($row['bio_json'] ?? ''));

        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'url' => '/bac-si-chi-tiet.php?slug=' . rawurlencode((string) ($row['slug'] ?? '')),
            'name' => (string) ($row['name'] ?? ''),
            'title_text' => trim((string) ($row['title_text'] ?? '')),
            'specialty_text' => trim((string) ($row['specialty_text'] ?? '')),
            'city' => trim((string) ($row['city'] ?? '')),
            'facility_name' => trim((string) ($row['facility_name'] ?? '')),
            'image_url' => trim((string) ($row['image_url'] ?? '')),
            'verified' => (bool) ((int) ($row['verified'] ?? 0)),
            'rating' => (float) ($row['rating'] ?? 0),
            'reviews_count' => (int) ($row['reviews_count'] ?? 0),
            'services' => $specialties,
            'name_key' => medical_search_cache_normalize((string) ($row['name'] ?? '')),
            'category_key' => medical_search_cache_normalize((string) ($row['specialty_text'] ?? '')),
            'city_key' => medical_search_cache_normalize((string) ($row['city'] ?? '')),
            'search_text' => medical_search_cache_normalize(implode(' ', $searchable)),
            'display_order' => (int) ($row['display_order'] ?? 0),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
    return $items;
}

/** @return array<int,array<string,mixed>> */
function medical_search_cache_toplists(PDO $pdo): array
{
    try {
        $rows = $pdo->query(
            "SELECT t.id, t.slug, t.title, t.excerpt, t.content, t.featured_image_url, t.updated_at,
                    COUNT(DISTINCT f.id) AS facility_count,
                    GROUP_CONCAT(DISTINCT CONCAT_WS(' ', f.name, f.category, f.city, f.featured_services_json, f.services_json) SEPARATOR ' ') AS facility_search_text
             FROM medical_toplists t
             LEFT JOIN medical_toplist_facilities tf ON tf.toplist_id = t.id
             LEFT JOIN medical_facilities f ON f.id = tf.facility_id AND f.status = 'published'
             WHERE t.status = 'published'
             GROUP BY t.id, t.slug, t.title, t.excerpt, t.content, t.featured_image_url, t.updated_at"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }

    $items = [];
    foreach ($rows as $row) {
        $searchable = [
            (string) ($row['title'] ?? ''),
            (string) ($row['slug'] ?? ''),
            (string) ($row['excerpt'] ?? ''),
            (string) ($row['content'] ?? ''),
            (string) ($row['facility_search_text'] ?? ''),
        ];
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'url' => medical_public_toplist_path((string) ($row['slug'] ?? '')),
            'title' => (string) ($row['title'] ?? ''),
            'excerpt' => trim((string) ($row['excerpt'] ?? '')),
            'featured_image_url' => trim((string) ($row['featured_image_url'] ?? '')),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'facility_count' => (int) ($row['facility_count'] ?? 0),
            'name_key' => medical_search_cache_normalize((string) ($row['title'] ?? '')),
            'category_key' => '',
            'city_key' => '',
            'search_text' => medical_search_cache_normalize(implode(' ', $searchable)),
        ];
    }
    return $items;
}

/** @return array<int,array{label:string,key:string}> */
function medical_search_cache_cities(array ...$groups): array
{
    $cities = [];
    foreach ($groups as $items) {
        foreach ($items as $item) {
            $city = trim((string) ($item['city'] ?? ''));
            $key = medical_search_cache_normalize($city);
            if ($city !== '' && $key !== '') {
                $cities[$key] = ['label' => $city, 'key' => $key];
            }
        }
    }
    $cities = array_values($cities);
    usort($cities, static fn(array $left, array $right): int => strlen($right['key']) <=> strlen($left['key']));
    return $cities;
}

/** @return array<string,mixed> */
function medical_search_cache_rebuild(PDO $pdo): array
{
    $invalidatedAt = @filemtime(medical_search_cache_invalidation_path());
    $generatedAt = max(time(), is_int($invalidatedAt) ? $invalidatedAt : 0);
    $facilities = medical_search_cache_facilities($pdo);
    $doctors = medical_search_cache_doctors($pdo);
    $toplists = medical_search_cache_toplists($pdo);

    return [
        'schema' => 1,
        'generated_at' => $generatedAt,
        'expires_at' => $generatedAt + medical_search_cache_ttl(),
        'facilities' => $facilities,
        'doctors' => $doctors,
        'toplists' => $toplists,
        'cities' => medical_search_cache_cities($facilities, $doctors),
        'counts' => [
            'facilities' => count($facilities),
            'doctors' => count($doctors),
            'toplists' => count($toplists),
        ],
    ];
}

/**
 * @return array{index:array<string,mixed>,source:string,stale:bool}
 */
function medical_search_cache_index(?PDO $pdo = null, bool $force = false): array
{
    $cached = medical_search_cache_read();
    if (!$force && is_array($cached) && medical_search_cache_is_fresh($cached)) {
        return ['index' => $cached, 'source' => 'json-ttl', 'stale' => false];
    }

    if (!medical_search_cache_ensure_dir()) {
        // No file system is available. Fall back to a one-off index rather
        // than breaking public search.
        $index = medical_search_cache_rebuild($pdo ?? db());
        return ['index' => $index, 'source' => 'database-fallback', 'stale' => false];
    }

    $lock = @fopen(medical_search_cache_lock_path(), 'c');
    if ($lock === false) {
        if (is_array($cached)) {
            return ['index' => $cached, 'source' => 'stale-json', 'stale' => true];
        }
        $index = medical_search_cache_rebuild($pdo ?? db());
        return ['index' => $index, 'source' => 'database-fallback', 'stale' => false];
    }

    $locked = @flock($lock, LOCK_EX | LOCK_NB);
    if (!$locked && is_array($cached)) {
        fclose($lock);
        return ['index' => $cached, 'source' => 'stale-json', 'stale' => true];
    }
    if (!$locked) {
        @flock($lock, LOCK_EX);
    }

    try {
        // Another request may have refreshed it while this request waited.
        $latest = medical_search_cache_read();
        if (!$force && is_array($latest) && medical_search_cache_is_fresh($latest)) {
            return ['index' => $latest, 'source' => 'json-ttl', 'stale' => false];
        }
        // Keep any healthy previous file available as a safety net. Search
        // should remain usable if the database has a short outage exactly
        // when the TTL expires.
        $stale = is_array($latest) ? $latest : $cached;
        try {
            $index = medical_search_cache_rebuild($pdo ?? db());
            if (!medical_search_cache_write($index) && is_array($stale)) {
                return ['index' => $stale, 'source' => 'stale-json', 'stale' => true];
            }
            return ['index' => $index, 'source' => 'rebuilt', 'stale' => false];
        } catch (Throwable $error) {
            if (is_array($stale)) {
                error_log('medical search cache rebuild failed; serving stale JSON: ' . $error->getMessage());
                return ['index' => $stale, 'source' => 'stale-json', 'stale' => true];
            }
            throw $error;
        }
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** @return array{key:string,label:string} */
function medical_search_cache_detect_city(array $cities, string $queryKey): array
{
    foreach ($cities as $city) {
        $key = trim((string) ($city['key'] ?? ''));
        if ($key === '') {
            continue;
        }
        if (preg_match('/(?:^|\s)' . preg_quote($key, '/') . '(?:\s|$)/', $queryKey)) {
            return ['key' => $key, 'label' => (string) ($city['label'] ?? '')];
        }
    }
    return ['key' => '', 'label' => ''];
}

/** @return array{score:int,service_text:string}|null */
function medical_search_cache_match(array $item, array $terms, string $queryKey, string $cityKey, string $type): ?array
{
    $haystack = (string) ($item['search_text'] ?? '');
    if ($haystack === '') {
        return null;
    }
    if ($cityKey !== '') {
        $itemCity = (string) ($item['city_key'] ?? '');
        if ($type === 'toplists') {
            if (!str_contains($haystack, $cityKey)) {
                return null;
            }
        } elseif ($itemCity !== $cityKey) {
            return null;
        }
    }
    foreach ($terms as $term) {
        if (!str_contains($haystack, $term)) {
            return null;
        }
    }

    $nameKey = (string) ($item['name_key'] ?? '');
    $categoryKey = (string) ($item['category_key'] ?? '');
    $score = 0;
    if ($queryKey !== '' && $nameKey === $queryKey) {
        $score += 10000;
    } elseif ($queryKey !== '' && str_contains($nameKey, $queryKey)) {
        $score += 2500;
    }
    foreach ($terms as $term) {
        if (str_contains($nameKey, $term)) {
            $score += 420;
        }
        if ($categoryKey !== '' && str_contains($categoryKey, $term)) {
            $score += 220;
        }
        if (str_contains((string) ($item['city_key'] ?? ''), $term)) {
            $score += 90;
        }
    }

    $matchedService = '';
    foreach ((array) ($item['services'] ?? []) as $service) {
        $service = trim((string) $service);
        $serviceKey = medical_search_cache_normalize($service);
        if ($service === '' || $serviceKey === '') {
            continue;
        }
        foreach ($terms as $term) {
            if (str_contains($serviceKey, $term)) {
                $matchedService = $service;
                $score += 170;
                break 2;
            }
        }
    }
    if ($matchedService === '' && !empty($item['services'][0])) {
        $matchedService = (string) $item['services'][0];
    }

    $score += (int) round(((float) ($item['rating'] ?? 0)) * 12);
    $score += min(90, (int) floor(log10(max(1, (int) ($item['reviews_count'] ?? 0))) * 25));
    return ['score' => $score, 'service_text' => $matchedService];
}

/** @return array<string,mixed> */
function medical_search_cache_public_item(string $type, array $item, string $serviceText = ''): array
{
    if ($type === 'toplists') {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'slug' => (string) ($item['slug'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'excerpt' => (string) ($item['excerpt'] ?? ''),
            'featured_image_url' => (string) ($item['featured_image_url'] ?? ''),
            'updated_at' => (string) ($item['updated_at'] ?? ''),
            'facility_count' => (int) ($item['facility_count'] ?? 0),
        ];
    }
    $public = [
        'id' => (int) ($item['id'] ?? 0),
        'slug' => (string) ($item['slug'] ?? ''),
        'url' => (string) ($item['url'] ?? ''),
        'name' => (string) ($item['name'] ?? ''),
        'city' => (string) ($item['city'] ?? ''),
        'image_url' => (string) ($item['image_url'] ?? ''),
        'verified' => (bool) ($item['verified'] ?? false),
        'rating' => (float) ($item['rating'] ?? 0),
        'reviews_count' => (int) ($item['reviews_count'] ?? 0),
        'service_text' => $serviceText,
    ];
    if ($type === 'facilities') {
        $public['category'] = (string) ($item['category'] ?? '');
        $public['subtitle'] = (string) ($item['subtitle'] ?? '');
    } else {
        $public['title_text'] = (string) ($item['title_text'] ?? '');
        $public['specialty_text'] = (string) ($item['specialty_text'] ?? '');
        $public['facility_name'] = (string) ($item['facility_name'] ?? '');
    }
    return $public;
}

/**
 * @return array{groups:array{facilities:array<int,array<string,mixed>>,doctors:array<int,array<string,mixed>>,toplists:array<int,array<string,mixed>>},meta:array<string,mixed>}
 */
function medical_search_cache_search(array $index, string $query, int $limit): array
{
    $queryKey = medical_search_cache_normalize($query);
    $terms = medical_search_cache_terms($query);
    $groups = ['facilities' => [], 'doctors' => [], 'toplists' => []];
    if ($terms === []) {
        return ['groups' => $groups, 'meta' => ['city' => '']];
    }
    $city = medical_search_cache_detect_city((array) ($index['cities'] ?? []), $queryKey);

    foreach (array_keys($groups) as $type) {
        $matches = [];
        foreach ((array) ($index[$type] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $match = medical_search_cache_match($item, $terms, $queryKey, $city['key'], $type);
            if ($match === null) {
                continue;
            }
            $matches[] = ['item' => $item, 'score' => $match['score'], 'service_text' => $match['service_text']];
        }
        usort($matches, static function (array $left, array $right): int {
            $score = $right['score'] <=> $left['score'];
            if ($score !== 0) {
                return $score;
            }
            $rating = ((float) ($right['item']['rating'] ?? 0)) <=> ((float) ($left['item']['rating'] ?? 0));
            if ($rating !== 0) {
                return $rating;
            }
            return ((int) ($right['item']['reviews_count'] ?? 0)) <=> ((int) ($left['item']['reviews_count'] ?? 0));
        });
        foreach (array_slice($matches, 0, $limit) as $match) {
            $groups[$type][] = medical_search_cache_public_item($type, $match['item'], $match['service_text']);
        }
    }

    return ['groups' => $groups, 'meta' => ['city' => $city['label']]];
}

/** @return array<string,mixed> */
function medical_search_cache_directory_item(array $item): array
{
    return [
        'id' => (int) ($item['id'] ?? 0),
        'slug' => (string) ($item['slug'] ?? ''),
        'name' => (string) ($item['name'] ?? ''),
        'category' => (string) ($item['category'] ?? 'Cơ sở y tế'),
        'city' => (string) ($item['city'] ?? ''),
        'subtitle' => (string) ($item['subtitle'] ?? ''),
        'address' => (string) ($item['address'] ?? ''),
        'hours' => (string) ($item['hours'] ?? ''),
        'rating' => number_format((float) ($item['rating'] ?? 0), 1, '.', ''),
        'reviews_count' => (int) ($item['reviews_count'] ?? 0),
        'followers_count' => (int) ($item['followers_count'] ?? 0),
        'verified' => (bool) ($item['verified'] ?? false),
        'price' => (string) ($item['price'] ?? ''),
        'image' => (string) ($item['image_url'] ?? ''),
        'image_count' => (int) ($item['image_count'] ?? 0),
        'images_label' => (string) ($item['images_label'] ?? ''),
        'services' => array_slice(array_values((array) ($item['services'] ?? [])), 0, 4),
    ];
}

/**
 * Filter and paginate the facility directory from the same JSON index used by
 * autocomplete. This removes the expensive DB CONCAT/LIKE scans from every
 * AJAX filter change while preserving the existing response shape.
 *
 * @param array<string,mixed> $filters
 * @return array{items:array<int,array<string,mixed>>,paging:array<string,int|bool>,meta:array<string,string>}
 */
function medical_search_cache_directory_search(array $index, array $filters): array
{
    $page = max(1, (int) ($filters['page'] ?? 1));
    $limit = min(24, max(6, (int) ($filters['limit'] ?? 12)));
    $query = trim((string) ($filters['q'] ?? ''));
    $city = trim((string) ($filters['city'] ?? ''));
    $category = trim((string) ($filters['category'] ?? ''));
    $service = trim((string) ($filters['service'] ?? ''));
    $minRating = (float) ($filters['min_rating'] ?? 0);
    $minRating = in_array($minRating, [0.0, 4.0, 4.5], true) ? $minRating : 0.0;
    $sort = (string) ($filters['sort'] ?? 'recommended');

    $queryKey = medical_search_cache_normalize($query);
    $terms = medical_search_cache_terms($query);
    $cityKey = medical_search_cache_normalize($city);
    if ($cityKey === '' && $queryKey !== '') {
        $cityKey = medical_search_cache_detect_city((array) ($index['cities'] ?? []), $queryKey)['key'];
    }
    $categoryKey = medical_search_cache_normalize($category);
    $serviceKey = medical_search_cache_normalize($service);

    $matches = [];
    foreach ((array) ($index['facilities'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $haystack = (string) ($item['search_text'] ?? '');
        if ($haystack === '') {
            continue;
        }
        if ($cityKey !== '' && (string) ($item['city_key'] ?? '') !== $cityKey) {
            continue;
        }
        if ($categoryKey !== '' && !str_contains((string) ($item['category_key'] ?? ''), $categoryKey)) {
            continue;
        }
        if ($serviceKey !== '') {
            $hasService = false;
            foreach ((array) ($item['services'] ?? []) as $candidate) {
                if (str_contains(medical_search_cache_normalize((string) $candidate), $serviceKey)) {
                    $hasService = true;
                    break;
                }
            }
            if (!$hasService) {
                continue;
            }
        }
        if ($minRating > 0 && (float) ($item['rating'] ?? 0) < $minRating) {
            continue;
        }
        $matchesQuery = true;
        foreach ($terms as $term) {
            if (!str_contains($haystack, $term)) {
                $matchesQuery = false;
                break;
            }
        }
        if (!$matchesQuery) {
            continue;
        }
        $matches[] = $item;
    }

    usort($matches, static function (array $left, array $right) use ($sort): int {
        $leftRating = (float) ($left['rating'] ?? 0);
        $rightRating = (float) ($right['rating'] ?? 0);
        $leftReviews = (int) ($left['reviews_count'] ?? 0);
        $rightReviews = (int) ($right['reviews_count'] ?? 0);
        if ($sort === 'newest') {
            $newest = strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
            return $newest !== 0 ? $newest : ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
        }
        if ($sort === 'reviews') {
            $reviews = $rightReviews <=> $leftReviews;
            return $reviews !== 0 ? $reviews : ($rightRating <=> $leftRating);
        }
        $rating = $rightRating <=> $leftRating;
        if ($rating !== 0) {
            return $rating;
        }
        $reviews = $rightReviews <=> $leftReviews;
        if ($reviews !== 0) {
            return $reviews;
        }
        if ($sort === 'rating') {
            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        }
        $displayOrder = ((int) ($left['display_order'] ?? 0)) <=> ((int) ($right['display_order'] ?? 0));
        return $displayOrder !== 0 ? $displayOrder : ((int) ($right['id'] ?? 0) <=> ((int) ($left['id'] ?? 0)));
    });

    $total = count($matches);
    $totalPages = max(1, (int) ceil($total / $limit));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $limit;
    $items = array_map('medical_search_cache_directory_item', array_slice($matches, $offset, $limit));

    return [
        'items' => $items,
        'paging' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1,
        ],
        'meta' => ['city' => $cityKey],
    ];
}
