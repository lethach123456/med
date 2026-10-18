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
require_once __DIR__ . '/medical_facility_ratings.php';

/** Ignore markup and invisible whitespace when deciding whether a profile is written. */
function medical_search_cache_has_written_content(string $content): bool
{
    $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', $text) ?? '') !== '';
}

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
    if (!is_array($decoded) || (int) ($decoded['schema'] ?? 0) !== 3) {
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
    if ($generatedAt <= 0 || $expiresAt < time() || (int) ($index['facility_ratings_version'] ?? 0) !== 1) {
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
    $hasTranslationColumns = medreview_translation_schema_ready($pdo, 'medical_facilities');
    $languageSelect = $hasTranslationColumns ? 'language_code' : "'vi' AS language_code";
    $rows = $pdo->query(
        "SELECT id, slug, {$languageSelect}, name, subtitle, content, category, city, address_text, image_url, gallery_json,
                verified, rating, reviews_count, aggregate_ratings_json, review_summary_json,
                followers_count, price_text, hours_text, images_label,
                featured_services_json, services_json, tags_json, highlights_json, display_order, updated_at
         FROM medical_facilities
         WHERE status = 'published'"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = [];
    foreach ($rows as $row) {
        $ratingSummary = medical_facility_rating_summary($row);
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
            'language_code' => strtolower((string) ($row['language_code'] ?? 'vi')) === 'en' ? 'en' : 'vi',
            'url' => medical_public_entity_path('facility', (string) ($row['slug'] ?? ''), (string) ($row['language_code'] ?? 'vi')),
            'name' => (string) ($row['name'] ?? ''),
            'has_content' => medical_search_cache_has_written_content((string) ($row['content'] ?? '')),
            'subtitle' => trim((string) ($row['subtitle'] ?? '')),
            'category' => trim((string) ($row['category'] ?? 'Cơ sở y tế')),
            'city' => trim((string) ($row['city'] ?? '')),
            'address' => trim((string) ($row['address_text'] ?? '')),
            'image_url' => $image,
            'verified' => (bool) ((int) ($row['verified'] ?? 0)),
            'rating' => $ratingSummary['rating'],
            'reviews_count' => $ratingSummary['reviews_count'],
            'rating_basis' => $ratingSummary['basis'],
            'rating_sources' => $ratingSummary['sources'],
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
    medreview_translation_schema_ready($pdo, 'medical_doctors');
    try {
        // Keep this tolerant of installations whose optional doctor columns
        // differ slightly; the normalizer below treats absent values as blank.
        $rows = $pdo->query(
            "SELECT * FROM medical_doctors WHERE status = 'published'"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }

    $items = [];
    foreach ($rows as $row) {
        $doctorServiceRows = json_decode((string) ($row['services_json'] ?? ''), true);
        $doctorServiceNames = is_array($doctorServiceRows)
            ? array_filter(array_column($doctorServiceRows, 'name'), 'is_string') : [];
        $specialties = array_values(array_unique(array_merge(
            medical_search_cache_json_strings($row['specialties_json'] ?? ''),
            medical_search_cache_json_strings($row['specialty_text'] ?? ''),
            $doctorServiceNames
        )));
        $gallery = medical_search_cache_gallery_urls($row['gallery_json'] ?? '');
        $image = trim((string) ($row['image_url'] ?? ''));
        if ($image === '' && $gallery !== []) {
            $image = $gallery[0];
        }
        $searchable = array_merge([
            (string) ($row['name'] ?? ''),
            (string) ($row['title_text'] ?? ''),
            (string) ($row['specialty_text'] ?? ''),
            (string) ($row['city'] ?? ''),
            (string) ($row['facility_name'] ?? ''),
            (string) ($row['address_text'] ?? ''),
            (string) ($row['slug'] ?? ''),
            (string) ($row['subtitle'] ?? ''),
        ], $specialties, medical_search_cache_json_strings($row['tags_json'] ?? ''), medical_search_cache_json_strings($row['bio_json'] ?? ''));

        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'language_code' => strtolower((string) ($row['language_code'] ?? 'vi')) === 'en' ? 'en' : 'vi',
            'url' => medical_public_entity_path('doctor', (string) ($row['slug'] ?? ''), (string) ($row['language_code'] ?? 'vi')),
            'name' => (string) ($row['name'] ?? ''),
            'has_content' => medical_search_cache_has_written_content((string) ($row['content'] ?? '')),
            'title_text' => trim((string) ($row['title_text'] ?? '')),
            'specialty_text' => trim((string) ($row['specialty_text'] ?? '')),
            'city' => trim((string) ($row['city'] ?? '')),
            'facility_name' => trim((string) ($row['facility_name'] ?? '')),
            'address' => trim((string) ($row['address_text'] ?? '')),
            'image_url' => $image,
            'verified' => (bool) ((int) ($row['verified'] ?? 0)),
            'rating' => (float) ($row['rating'] ?? 0),
            'reviews_count' => (int) ($row['reviews_count'] ?? 0),
            'followers_count' => (int) ($row['followers_count'] ?? 0),
            'hours' => trim((string) ($row['hours_text'] ?? '')),
            'price' => trim((string) ($row['price_text'] ?? '')),
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
        $hasTranslationColumns = medreview_translation_schema_ready($pdo, 'medical_toplists');
        $languageSelect = $hasTranslationColumns ? 't.language_code' : "'vi' AS language_code";
        $languageGroup = $hasTranslationColumns ? ', t.language_code' : '';
        $rows = $pdo->query(
            "SELECT t.id, t.slug, {$languageSelect}, t.entity_type, t.title, t.excerpt, t.content, t.featured_image_url, t.updated_at,
                    COUNT(DISTINCT f.id) AS facility_count,
                    COUNT(DISTINCT d.id) AS doctor_count,
                    GROUP_CONCAT(DISTINCT CONCAT_WS(' ', f.name, f.category, f.city, f.featured_services_json, f.services_json) SEPARATOR ' ') AS facility_search_text,
                    GROUP_CONCAT(DISTINCT CONCAT_WS(' ', d.name, d.specialty_text, d.city, d.facility_name) SEPARATOR ' ') AS doctor_search_text
             FROM medical_toplists t
             LEFT JOIN medical_toplist_facilities tf ON tf.toplist_id = t.id AND t.entity_type IN ('facility','mixed')
             LEFT JOIN medical_facilities f ON f.id = tf.facility_id AND f.status = 'published' AND f.language_code=t.language_code
             LEFT JOIN medical_toplist_doctors td ON td.toplist_id = t.id AND t.entity_type IN ('doctor','mixed')
             LEFT JOIN medical_doctors d ON d.id=td.doctor_id AND d.status='published' AND d.language_code=t.language_code
             WHERE t.status = 'published'
             GROUP BY t.id, t.slug{$languageGroup}, t.entity_type, t.title, t.excerpt, t.content, t.featured_image_url, t.updated_at"
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
            (string) ($row['doctor_search_text'] ?? ''),
        ];
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'language_code' => strtolower((string) ($row['language_code'] ?? 'vi')) === 'en' ? 'en' : 'vi',
            'url' => medical_public_entity_path('toplist', (string) ($row['slug'] ?? ''), (string) ($row['language_code'] ?? 'vi')),
            'title' => (string) ($row['title'] ?? ''),
            'excerpt' => trim((string) ($row['excerpt'] ?? '')),
            'featured_image_url' => trim((string) ($row['featured_image_url'] ?? '')),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'facility_count' => (int) ($row['facility_count'] ?? 0),
            'doctor_count' => (int) ($row['doctor_count'] ?? 0),
            'entity_type' => (string) ($row['entity_type'] ?? 'facility'),
            'name_key' => medical_search_cache_normalize((string) ($row['title'] ?? '')),
            'category_key' => '',
            'city_key' => '',
            // Search linked identities without treating every word in the
            // article's HTML, citations or URLs as a relevant suggestion.
            'linked_search_text' => medical_search_cache_normalize(implode(' ', [
                (string) ($row['facility_search_text'] ?? ''),
                (string) ($row['doctor_search_text'] ?? ''),
            ])),
            'search_text' => medical_search_cache_normalize(implode(' ', $searchable)),
        ];
    }
    return $items;
}

/** Keep translated copies out of the other language's search and directory results. */
function medical_search_cache_filter_locale(array $index, string $locale): array
{
    $locale = site_normalize_locale($locale);
    foreach (['facilities', 'doctors', 'toplists'] as $key) {
        $items = (array) ($index[$key] ?? []);
        $index[$key] = array_values(array_filter($items, static function ($item) use ($locale): bool {
            if (!is_array($item)) return false;
            return (strtolower((string) ($item['language_code'] ?? 'vi')) === 'en' ? 'en' : 'vi') === $locale;
        }));
    }
    $index['cities'] = medical_search_cache_cities((array) $index['facilities'], (array) $index['doctors']);
    $index['counts'] = [
        'facilities' => count($index['facilities']),
        'doctors' => count($index['doctors']),
        'toplists' => count($index['toplists']),
    ];
    return $index;
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
        'schema' => 3,
        'facility_ratings_version' => 1,
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

/** Search city labels and common abbreviations using the same location identity. */
function medical_search_cache_city_identity(string $value): string
{
    $key = medical_search_cache_normalize($value);
    $key = preg_replace('/^(?:tp|thanh pho|tinh)\s+/', '', $key) ?? $key;
    return match ($key) {
        'hn', 'hanoi', 'ha noi', 'hanoi city' => 'ha noi',
        'hcm', 'hcmc', 'tphcm', 'tp hcm', 'ho chi minh', 'ho chi minh city',
        'saigon', 'sai gon', 'saigon city' => 'ho chi minh',
        'danang', 'da nang', 'danang city', 'da nang city' => 'da nang',
        'thua thien hue', 'hue', 'hue city' => 'hue',
        default => $key,
    };
}

/** @return array<string,array{label:string,aliases:array<int,string>}> */
function medical_search_cache_city_aliases(array $cities): array
{
    $known = [];
    foreach ($cities as $city) {
        $label = trim((string) ($city['label'] ?? ''));
        $key = medical_search_cache_city_identity((string) ($city['key'] ?? $label));
        if ($key === '') continue;
        if (!isset($known[$key])) $known[$key] = ['label' => $label, 'aliases' => []];
        $known[$key]['aliases'][] = medical_search_cache_normalize($label);
        $known[$key]['aliases'][] = medical_search_cache_normalize((string) ($city['key'] ?? ''));
        $known[$key]['aliases'][] = $key;
    }
    $common = [
        'ha noi' => ['ha noi', 'hanoi', 'hn'],
        'ho chi minh' => ['ho chi minh', 'ho chi minh city', 'tp ho chi minh', 'thanh pho ho chi minh', 'tp hcm', 'tphcm', 'hcm', 'hcmc', 'sai gon', 'saigon'],
        'da nang' => ['da nang', 'danang'],
        'hue' => ['hue', 'thua thien hue'],
    ];
    foreach ($known as $key => &$city) {
        $city['aliases'] = array_values(array_unique(array_filter(array_merge(
            $city['aliases'], $common[$key] ?? [], ['tp ' . $key, 'thanh pho ' . $key, 'tinh ' . $key]
        ), static fn(string $alias): bool => $alias !== '')));
        usort($city['aliases'], static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    }
    unset($city);
    return $known;
}

/** @return array{key:string,label:string,alias:string,offset:int,explicit:bool} */
function medical_search_cache_query_city(array $aliases, string $query): array
{
    $best = ['key' => '', 'label' => '', 'alias' => '', 'offset' => -1, 'explicit' => false];
    foreach ($aliases as $key => $city) {
        foreach ($city['aliases'] as $alias) {
            if (!preg_match_all('/(?<![a-z0-9])' . preg_quote($alias, '/') . '(?![a-z0-9])/', $query, $matches, PREG_OFFSET_CAPTURE)) continue;
            foreach ($matches[0] as [$text, $offset]) {
                $before = substr($query, 0, $offset);
                $explicit = preg_match('/(?:^|\s)(?:tai|o|in|near)\s*$/', $before) === 1;
                // A final location is normally the destination; an explicit
                // "tại/ở/in" takes priority over a brand containing a city.
                $end = $offset + strlen($text);
                $bestEnd = $best['offset'] + strlen($best['alias']);
                if (($explicit && !$best['explicit']) || ($explicit === $best['explicit'] && (
                    $end > $bestEnd || ($end === $bestEnd && strlen($text) > strlen($best['alias']))
                ))) {
                    $best = ['key' => (string) $key, 'label' => (string) $city['label'], 'alias' => $text, 'offset' => $offset, 'explicit' => $explicit];
                }
            }
        }
    }
    return $best;
}

/** Match whole words; only the last typed word may be an unfinished prefix. */
function medical_search_cache_word_match(string $field, string $term, bool $prefix = false): bool
{
    if ($field === '' || $term === '') return false;
    return preg_match('/(?:^|\s)' . preg_quote($term, '/') . ($prefix ? '[a-z0-9]*' : '') . '(?=\s|$)/', $field) === 1;
}

/** Build autocomplete intent without dropping Vietnamese names such as Phú/Gia/Uy. */
function medical_search_cache_query_plan(array $index, string $query): array
{
    $queryKey = medical_search_cache_normalize($query);
    $commandPattern = '/^(?:tim(?:\s+kiem)?|search for|find)\s+(?=nha khoa|bac si|bs|dr|doctor|phong kham|benh vien|co so y te|spa|eye)/';
    $doctorPattern = '/(?:^|\s)(?:(?:pgs|gs|ts|ths)(?:\s+(?:ts|ths))?\s+)?(?:bac si|bs|b s|dr|doctor|doctors)(?:\s+(?:ckii|cki|ck1|ck2))?(?=\s|$)/';
    $withoutCommand = preg_replace($commandPattern, '', $queryKey) ?? $queryKey;
    $facilityIntent = preg_match('/^(?:nha khoa|phong kham|benh vien|co so y te|spa|dental clinic|clinic|hospital)(?=\s|$)/', $withoutCommand) === 1;
    $identity = $facilityIntent ? $withoutCommand : trim(preg_replace($doctorPattern, ' ', $withoutCommand) ?? $withoutCommand);
    $identity = preg_replace('/\s+/', ' ', $identity) ?? $identity;
    $doctorIntent = !$facilityIntent && ($identity !== $withoutCommand || preg_match('/(?:^|\s)(?:ophthalmologist|eye doctor)(?=\s|$)/', $queryKey) === 1);
    $toplistIntent = preg_match('/(?:^|\s)(?:top(?:list)?|xep hang|danh sach)(?=\s|$)/', $queryKey) === 1;
    $eyeIntent = preg_match('/(?:^|\s)(?:kham mat|nhan khoa|(?:chuyen )?khoa mat|phau thuat mat|bac si mat|bs mat|ophthalmology|ophthalmologist|eye doctor|eye clinic|eye care)(?=\s|$)/', $queryKey) === 1;
    $dentalIntent = preg_match('/(?:^|\s)(?:nha khoa|rang ham mat|dental|dentistry|dentists?)(?=\s|$)/', $queryKey) === 1;
    $exactName = false;
    foreach (['facilities', 'doctors', 'toplists'] as $type) {
        foreach ((array) ($index[$type] ?? []) as $item) {
            if (!is_array($item) || ($type !== 'toplists' && empty($item['has_content']))) continue;
            $name = (string) ($item['name_key'] ?? medical_search_cache_normalize((string) ($item['name'] ?? $item['title'] ?? '')));
            if ($name !== '' && ($name === $queryKey || ($identity !== '' && $name === $identity))) {
                $exactName = true;
                break 2;
            }
        }
    }
    $cityAliases = medical_search_cache_city_aliases((array) ($index['cities'] ?? []));
    $city = medical_search_cache_query_city($cityAliases, $queryKey);
    $semantic = $queryKey;
    if ($city['key'] !== '') {
        $before = substr($semantic, 0, $city['offset']);
        $before = preg_replace('/(?:^|\s)(?:tai|o|in|near)\s*$/', ' ', $before) ?? $before;
        $semantic = $before . ' ' . substr($semantic, $city['offset'] + strlen($city['alias']));
    }
    if (!$exactName) $semantic = preg_replace($commandPattern, '', $semantic) ?? $semantic;
    if (!$facilityIntent) $semantic = preg_replace($doctorPattern, ' ', $semantic) ?? $semantic;
    if ($toplistIntent) {
        $semantic = preg_replace('/(?:^|\s)(?:top(?:list)?(?:\s+\d+)?|xep hang|danh sach)(?=\s|$)/', ' ', $semantic) ?? $semantic;
    }
    if ($eyeIntent) {
        // "Mắt" and "mặt" share an accent-free key. Recognise the actual
        // ophthalmology intent before matching general dentistry services.
        $semantic = preg_replace('/(?:^|\s)(?:phong kham mat|benh vien mat|kham mat|nhan khoa|(?:chuyen )?khoa mat|phau thuat mat|ophthalmology|ophthalmologist|eye doctor|eye clinic|eye care|eye|mat)(?=\s|$)/', ' ', $semantic) ?? $semantic;
    }
    if (!$exactName) {
        $semantic = preg_replace('/(?:^|\s)(?:uy tin|tot nhat|noi tieng|gia re|gan day|cho toi)(?=\s|$)/', ' ', $semantic) ?? $semantic;
    }
    $semantic = trim(preg_replace('/\s+/', ' ', $semantic) ?? $semantic);
    $terms = array_values(array_unique(array_filter(explode(' ', $semantic), static fn(string $term): bool => $term !== '')));
    $last = $semantic !== '' ? substr($semantic, (int) strrpos(' ' . $semantic, ' ')) : '';
    $rawLast = $queryKey !== '' ? substr($queryKey, (int) strrpos(' ' . $queryKey, ' ')) : '';
    $personName = '';
    if ((count($terms) >= 3 || ($doctorIntent && count($terms) >= 2))
        && !preg_match('/^\s*(?:đường|street)\b/ui', $query)
        && preg_match('/^(?:nguyen|tran|le|pham|hoang|huynh|phan|bui|dang|do|ngo|duong|vu|vo|truong|dinh|luong|trinh|ha|ho|mai|cao|ta|dao|luu|lam|chu|to|ton)\s/', $semantic)) {
        $personName = $semantic;
    }
    return [
        'query' => $queryKey, 'identity' => $identity, 'semantic' => $semantic, 'terms' => $terms,
        'prefix_term' => $last !== '' && $last === $rawLast ? $last : '',
        'intent' => $toplistIntent ? 'toplists' : ($doctorIntent ? 'doctors' : ''),
        'specialty' => $dentalIntent ? 'dentistry' : ($eyeIntent ? 'ophthalmology' : ''), 'person_name' => $personName,
        'city' => $city, 'city_aliases' => $cityAliases,
    ];
}

/** @return array{score:int,service_text:string}|null */
function medical_search_cache_match(array $item, array $terms, string $queryKey, string $cityKey, string $type, array $plan = []): ?array
{
    if ($type !== 'toplists' && empty($item['has_content'])) return null;
    $nameKey = (string) ($item['name_key'] ?? medical_search_cache_normalize((string) ($item['name'] ?? $item['title'] ?? '')));
    if ($nameKey === '') return null;
    $identity = (string) ($plan['identity'] ?? $queryKey);
    $exactName = $nameKey === $queryKey || ($identity !== '' && $nameKey === $identity);
    if (($plan['intent'] ?? '') === 'doctors' && !$exactName && (
        $type === 'facilities' || ($type === 'toplists' && ($item['entity_type'] ?? '') === 'facility')
    )) return null;
    if ($cityKey !== '') {
        $itemCity = $type === 'toplists'
            ? medical_search_cache_query_city((array) ($plan['city_aliases'] ?? []), $nameKey)['key']
            : medical_search_cache_city_identity((string) ($item['city_key'] ?? $item['city'] ?? ''));
        if ($itemCity !== $cityKey && !($exactName && empty($plan['city']['explicit']))) return null;
    }
    if ($terms === [] && ($plan['specialty'] ?? '') === '' && ($plan['intent'] ?? '') !== '' && $type !== $plan['intent']) return null;
    $categoryKey = (string) ($item['category_key'] ?? '');
    $serviceNames = array_values(array_filter((array) ($item['services'] ?? []), 'is_string'));
    $serviceKeys = array_map('medical_search_cache_normalize', $serviceNames);
    $fields = [
        'name' => $nameKey,
        'category' => $categoryKey,
        'services' => implode(' ', $serviceKeys),
        'address' => medical_search_cache_normalize((string) ($item['address'] ?? '')),
        'facility' => medical_search_cache_normalize((string) ($item['facility_name'] ?? '')),
        'subtitle' => medical_search_cache_normalize(strip_tags((string) ($item['subtitle'] ?? $item['excerpt'] ?? ''))),
        'city' => (string) ($item['city_key'] ?? ''),
    ];
    if ($type === 'toplists') $fields['linked'] = (string) ($item['linked_search_text'] ?? '');
    $prefixTerm = (string) ($plan['prefix_term'] ?? '');
    $personName = (string) ($plan['person_name'] ?? '');
    if ($personName !== '' && !$exactName) {
        $identityFields = [$fields['name'], $fields['facility'], (string) ($fields['linked'] ?? '')];
        // A street can also be a person's full name. Require its complete
        // phrase in one address field, rather than combining scattered words.
        if (($plan['intent'] ?? '') !== 'doctors') $identityFields[] = $fields['address'];
        if (!array_filter($identityFields, static fn(string $field): bool => medical_search_cache_word_match($field, $personName, $prefixTerm !== ''))) return null;
    }
    $specialtyScore = 0;
    if (!$exactName && ($plan['specialty'] ?? '') === 'dentistry') {
        $dentalPattern = '/(?:^|\s)(?:nha khoa|rang ham mat|dental|dentistry|dentists?|odontolog[a-z]*)(?=\s|$)/';
        $identityDental = preg_match($dentalPattern, $fields['name'] . ' ' . $fields['category']) === 1;
        $serviceDental = array_filter($serviceKeys, static fn(string $key): bool => preg_match($dentalPattern, $key) === 1) !== [];
        // Do not assemble "nha khoa" from an address's "tòa nhà" and
        // the unrelated specialty "nhãn khoa" in a different field.
        if (!$identityDental && !$serviceDental) return null;
        $specialtyScore = $identityDental ? 1800 : 500;
    }
    if (!$exactName && ($plan['specialty'] ?? '') === 'ophthalmology') {
        $eyePattern = '/(?:^|\s)(?:nhan khoa|(?:chuyen )?khoa mat|bac si mat|kham mat|benh vien mat|phong kham mat|trung tam mat|benh mat|dieu tri mat|phau thuat mat|mat tre em|vong mac|glocom|phaco|can thi|khuc xa|ophthalmolog[a-z]*|eye(?: care| clinic| doctor)?)(?=\s|$)/';
        $identityEye = preg_match($eyePattern, $fields['name'] . ' ' . $fields['category']) === 1
            || $fields['category'] === 'mat';
        $serviceEye = preg_match($eyePattern, $fields['services']) === 1;
        if (!$identityEye && !$serviceEye) return null;
        $specialtyScore = $identityEye ? 1800 : 500;
    }
    $weights = ['name' => 1200, 'category' => 400, 'services' => 350, 'facility' => 250, 'subtitle' => 150, 'address' => 80, 'city' => 30, 'linked' => 100];
    $score = ($exactName ? 100000 : 0) + $specialtyScore;
    foreach ($terms as $term) {
        $best = 0;
        foreach ($fields as $field => $text) {
            if (medical_search_cache_word_match($text, $term)) {
                $best = max($best, $weights[$field]);
            } elseif ($term === $prefixTerm && medical_search_cache_word_match($text, $term, true)) {
                $best = max($best, (int) ($weights[$field] / 2));
            }
        }
        if ($best === 0 && !$exactName) return null;
        $score += $best;
    }
    $semantic = (string) ($plan['semantic'] ?? implode(' ', $terms));
    if ($semantic !== '') {
        if ($nameKey === $semantic) $score += 20000;
        elseif (medical_search_cache_word_match($nameKey, $semantic)) $score += 10000;
        if (medical_search_cache_word_match($categoryKey, $semantic)) $score += 1200;
        foreach ($serviceKeys as $serviceKey) {
            if (medical_search_cache_word_match($serviceKey, $semantic)) {
                // A complete service phrase is more relevant than words
                // scattered across other services, regardless of rating.
                $score += 1200;
                break;
            }
        }
    }
    $matchedService = '';
    $bestService = 0;
    foreach ($serviceNames as $position => $service) {
        $serviceScore = 0;
        foreach ($terms as $term) {
            if (medical_search_cache_word_match($serviceKeys[$position], $term)) $serviceScore += 2;
            elseif ($term === $prefixTerm && medical_search_cache_word_match($serviceKeys[$position], $term, true)) $serviceScore++;
        }
        if ($semantic !== '' && medical_search_cache_word_match($serviceKeys[$position], $semantic)) $serviceScore += count($terms) * 2;
        if ($serviceScore > $bestService) { $bestService = $serviceScore; $matchedService = $service; }
    }
    if ($matchedService === '' && $serviceNames !== []) $matchedService = $serviceNames[0];
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
            'doctor_count' => (int) ($item['doctor_count'] ?? 0),
            'entity_type' => (string) ($item['entity_type'] ?? 'facility'),
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
        $public['rating_basis'] = (string) ($item['rating_basis'] ?? 'legacy');
        $public['rating_sources'] = (array) ($item['rating_sources'] ?? []);
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
    $groups = ['facilities' => [], 'doctors' => [], 'toplists' => []];
    $totals = array_fill_keys(array_keys($groups), 0);
    $groupScores = array_fill_keys(array_keys($groups), -1);
    $plan = medical_search_cache_query_plan($index, $query);
    $queryKey = $plan['query'];
    $terms = $plan['terms'];
    $city = $plan['city'];
    if ($queryKey === '' || ($terms === [] && $city['key'] === '' && $plan['intent'] === '' && $plan['specialty'] === '')) {
        return ['groups' => $groups, 'meta' => ['city' => '', 'totals' => $totals, 'group_order' => array_keys($groups)]];
    }
    $limit = min(24, max(1, $limit));
    foreach (array_keys($groups) as $type) {
        $matches = [];
        foreach ((array) ($index[$type] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $match = medical_search_cache_match($item, $terms, $queryKey, $city['key'], $type, $plan);
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
            $reviews = ((int) ($right['item']['reviews_count'] ?? 0)) <=> ((int) ($left['item']['reviews_count'] ?? 0));
            if ($reviews !== 0) return $reviews;
            return ((int) ($left['item']['id'] ?? 0)) <=> ((int) ($right['item']['id'] ?? 0));
        });
        $totals[$type] = count($matches);
        $groupScores[$type] = $matches !== [] ? $matches[0]['score'] : -1;
        foreach (array_slice($matches, 0, $limit) as $match) {
            $groups[$type][] = medical_search_cache_public_item($type, $match['item'], $match['service_text']);
        }
    }

    $order = array_keys($groups);
    $baseOrder = array_flip($order);
    usort($order, static function (string $left, string $right) use ($plan, $groupScores, $baseOrder): int {
        $leftIntent = $left === $plan['intent'] && $groupScores[$left] >= 0;
        $rightIntent = $right === $plan['intent'] && $groupScores[$right] >= 0;
        if ($leftIntent !== $rightIntent) return $rightIntent <=> $leftIntent;
        return ($groupScores[$right] <=> $groupScores[$left]) ?: ($baseOrder[$left] <=> $baseOrder[$right]);
    });
    return ['groups' => $groups, 'meta' => ['city' => $city['label'], 'totals' => $totals, 'group_order' => $order, 'intent' => $plan['intent']]];
}

/** @return array<string,mixed> */
function medical_search_cache_directory_item(array $item): array
{
    return [
        'id' => (int) ($item['id'] ?? 0),
        'slug' => (string) ($item['slug'] ?? ''),
        'language_code' => (string) ($item['language_code'] ?? 'vi'),
        'url' => (string) ($item['url'] ?? ''),
        'name' => (string) ($item['name'] ?? ''),
        'category' => (string) ($item['category'] ?? 'Cơ sở y tế'),
        'city' => (string) ($item['city'] ?? ''),
        'subtitle' => (string) ($item['subtitle'] ?? ''),
        'address' => (string) ($item['address'] ?? ''),
        'hours' => (string) ($item['hours'] ?? ''),
        'rating' => number_format((float) ($item['rating'] ?? 0), 1, '.', ''),
        'reviews_count' => (int) ($item['reviews_count'] ?? 0),
        'rating_basis' => (string) ($item['rating_basis'] ?? 'legacy'),
        'rating_sources' => (array) ($item['rating_sources'] ?? []),
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
/** Keep directory cards, facets and totals limited to written profiles. */
function medical_search_cache_facility_directory_index(array $index): array
{
    $index['facilities'] = array_values(array_filter(
        (array) ($index['facilities'] ?? []),
        static fn(array $item): bool => !empty($item['has_content'])
    ));
    $index['cities'] = medical_search_cache_cities($index['facilities']);
    return $index;
}

function medical_search_cache_directory_search(array $index, array $filters): array
{
    $index = medical_search_cache_facility_directory_index($index);
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

/** @return array<string,mixed> */
function medical_search_cache_doctor_directory_item(array $item): array
{
    return [
        'id' => (int) ($item['id'] ?? 0),
        'slug' => (string) ($item['slug'] ?? ''),
        'language_code' => (string) ($item['language_code'] ?? 'vi'),
        'url' => (string) ($item['url'] ?? ''),
        'name' => (string) ($item['name'] ?? ''),
        'title_text' => (string) ($item['title_text'] ?? ''),
        'specialty_text' => (string) ($item['specialty_text'] ?? ''),
        'city' => (string) ($item['city'] ?? ''),
        'facility_name' => (string) ($item['facility_name'] ?? ''),
        'image' => (string) ($item['image_url'] ?? ''),
        'verified' => (bool) ($item['verified'] ?? false),
        'rating' => number_format((float) ($item['rating'] ?? 0), 1, '.', ''),
        'reviews_count' => (int) ($item['reviews_count'] ?? 0),
        'followers_count' => (int) ($item['followers_count'] ?? 0),
        'hours' => (string) ($item['hours'] ?? ''),
        'price' => (string) ($item['price'] ?? ''),
        'services' => array_slice(array_values((array) ($item['services'] ?? [])), 0, 8),
    ];
}

/** Scope this rule to the public doctor directory, not the AI research queue. */
function medical_search_cache_doctor_directory_index(array $index): array
{
    $index['doctors'] = array_values(array_filter(
        (array) ($index['doctors'] ?? []),
        static fn(mixed $item): bool => is_array($item) && !empty($item['has_content'])
    ));
    $index['cities'] = medical_search_cache_cities($index['doctors']);
    $index['counts'] = array_replace((array) ($index['counts'] ?? []), ['doctors' => count($index['doctors'])]);
    return $index;
}

/**
 * Search and paginate written doctors from the shared JSON TTL index.
 *
 * @param array<string,mixed> $filters
 * @return array{items:array<int,array<string,mixed>>,paging:array<string,int|bool>,meta:array<string,string>}
 */
function medical_search_cache_doctor_directory_search(array $index, array $filters): array
{
    $index = medical_search_cache_doctor_directory_index($index);
    $page = max(1, (int) ($filters['page'] ?? 1));
    $limit = min(24, max(6, (int) ($filters['limit'] ?? 12)));
    $query = trim((string) ($filters['q'] ?? ''));
    $city = trim((string) ($filters['city'] ?? ''));
    $specialty = trim((string) ($filters['specialty'] ?? ''));
    $minRating = (float) ($filters['min_rating'] ?? 0);
    $minRating = in_array($minRating, [0.0, 4.0, 4.5], true) ? $minRating : 0.0;
    $sort = (string) ($filters['sort'] ?? 'recommended');

    $queryKey = medical_search_cache_normalize($query);
    $terms = array_values(array_diff(medical_search_cache_terms($query), ['bac', 'si', 'bs', 'dr']));
    $cityKey = medical_search_cache_normalize($city);
    if ($cityKey === '' && $queryKey !== '') {
        $cityKey = medical_search_cache_detect_city((array) ($index['cities'] ?? []), $queryKey)['key'];
    }
    $specialtyKey = medical_search_cache_normalize($specialty);
    $matches = [];

    foreach ((array) ($index['doctors'] ?? []) as $doctor) {
        if (!is_array($doctor) || trim((string) ($doctor['search_text'] ?? '')) === '') {
            continue;
        }
        if ($cityKey !== '' && (string) ($doctor['city_key'] ?? '') !== $cityKey) {
            continue;
        }
        if ($specialtyKey !== '') {
            $hasSpecialty = str_contains((string) ($doctor['category_key'] ?? ''), $specialtyKey);
            if (!$hasSpecialty) {
                foreach ((array) ($doctor['services'] ?? []) as $candidate) {
                    if (str_contains(medical_search_cache_normalize((string) $candidate), $specialtyKey)) {
                        $hasSpecialty = true;
                        break;
                    }
                }
            }
            if (!$hasSpecialty) {
                continue;
            }
        }
        if ($minRating > 0 && (float) ($doctor['rating'] ?? 0) < $minRating) {
            continue;
        }
        $matchesQuery = true;
        foreach ($terms as $term) {
            if (!str_contains((string) $doctor['search_text'], $term)) {
                $matchesQuery = false;
                break;
            }
        }
        if ($matchesQuery) {
            $matches[] = $doctor;
        }
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
        $order = ((int) ($left['display_order'] ?? 0)) <=> ((int) ($right['display_order'] ?? 0));
        return $order !== 0 ? $order : ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
    });

    $total = count($matches);
    $totalPages = max(1, (int) ceil($total / $limit));
    $page = min($page, $totalPages);
    $items = array_map(
        'medical_search_cache_doctor_directory_item',
        array_slice($matches, ($page - 1) * $limit, $limit)
    );

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
