<?php
declare(strict_types=1);

/**
 * Read-only homepage data. Dependencies are supplied by the calling page;
 * requiring this file alone never opens a connection, seeds data or migrates.
 */
function medical_home_next_empty(): array
{
    return ['stats' => ['facilities' => 0, 'doctors' => 0, 'toplists' => 0], 'facilities' => [], 'doctors' => [], 'toplists' => []];
}

function medical_home_next_text(mixed $value): string
{
    return is_scalar($value) ? trim((string) $value) : '';
}

/** Pure media normalization; production may inject site_absolute_media_url. */
function medical_home_next_media(mixed $value, ?callable $resolveMedia = null): string
{
    $url = medical_home_next_text($value);
    if ($url === '') return '';
    if (preg_match('/[\x00-\x1F\x7F]/', $url)) return '';
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $url) && !preg_match('~^https?://~i', $url)) return '';
    $resolved = $resolveMedia === null ? $url : medical_home_next_text($resolveMedia($url));
    if (preg_match('/[\x00-\x1F\x7F]/', $resolved)) return '';
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $resolved) && !preg_match('~^https?://~i', $resolved)) return '';
    return $resolved;
}

/** @return list<string> */
function medical_home_next_gallery(mixed $gallery, ?callable $resolveMedia = null): array
{
    if (is_string($gallery)) $gallery = json_decode($gallery, true);
    if (!is_array($gallery)) return [];
    $images = [];
    foreach ($gallery as $image) {
        if (is_array($image)) $image = $image['url'] ?? $image['src'] ?? '';
        $url = medical_home_next_media($image, $resolveMedia);
        if ($url !== '' && !in_array($url, $images, true)) $images[] = $url;
    }
    return $images;
}

function medical_home_next_image(array $row, ?callable $resolveMedia = null): string
{
    $image = medical_home_next_media($row['image_url'] ?? $row['featured_image_url'] ?? '', $resolveMedia);
    return $image !== '' ? $image : (medical_home_next_gallery($row['gallery_json'] ?? null, $resolveMedia)[0] ?? '');
}

function medical_home_next_facility_row(array $row, ?callable $resolveMedia = null): array
{
    return [
        'id' => max(0, (int) ($row['id'] ?? 0)),
        'slug' => medical_home_next_text($row['slug'] ?? ''),
        'name' => medical_home_next_text($row['name'] ?? ''),
        'category' => medical_home_next_text($row['category'] ?? ''),
        'city' => medical_home_next_text($row['city'] ?? ''),
        'subtitle' => medical_home_next_text($row['subtitle'] ?? ''),
        'verified' => (int) ($row['verified'] ?? 0) === 1,
        'rating' => max(0.0, (float) ($row['rating'] ?? 0)),
        'reviews_count' => max(0, (int) ($row['reviews_count'] ?? 0)),
        'address_text' => medical_home_next_text($row['address_text'] ?? ''),
        'image_url' => medical_home_next_image($row, $resolveMedia),
        'gallery_json' => is_string($row['gallery_json'] ?? null) ? $row['gallery_json'] : '[]',
    ];
}

function medical_home_next_doctor_row(array $row, ?callable $resolveMedia = null): array
{
    return [
        'id' => max(0, (int) ($row['id'] ?? 0)),
        'slug' => medical_home_next_text($row['slug'] ?? ''),
        'name' => medical_home_next_text($row['name'] ?? ''),
        'specialty_text' => medical_home_next_text($row['specialty_text'] ?? ''),
        'title_text' => medical_home_next_text($row['title_text'] ?? ''),
        'city' => medical_home_next_text($row['city'] ?? ''),
        'image_url' => medical_home_next_image($row, $resolveMedia),
        'rating' => max(0.0, (float) ($row['rating'] ?? 0)),
        'reviews_count' => max(0, (int) ($row['reviews_count'] ?? 0)),
        'verified' => (int) ($row['verified'] ?? 0) === 1,
    ];
}

/** @param list<array> $linkedRows */
function medical_home_next_toplist_row(array $row, array $linkedRows = [], ?callable $resolveMedia = null): array
{
    $type = medical_home_next_text($row['entity_type'] ?? 'facility');
    if (!in_array($type, ['facility', 'doctor', 'mixed'], true)) $type = 'facility';
    $facilityCount = max(0, (int) ($row['facility_count'] ?? 0));
    $doctorCount = max(0, (int) ($row['doctor_count'] ?? 0));
    $cover = medical_home_next_media($row['featured_image_url'] ?? '', $resolveMedia);
    $images = $cover !== '' ? [$cover] : [];
    foreach ($linkedRows as $member) {
        if (!is_array($member)) continue;
        $image = medical_home_next_image($member, $resolveMedia);
        if ($image !== '' && !in_array($image, $images, true)) $images[] = $image;
        if (count($images) >= 3) break;
    }
    return [
        'id' => max(0, (int) ($row['id'] ?? 0)),
        'slug' => medical_home_next_text($row['slug'] ?? ''),
        'language_code' => medical_home_next_text($row['language_code'] ?? 'vi') === 'en' ? 'en' : 'vi',
        'entity_type' => $type,
        'title' => medical_home_next_text($row['title'] ?? ''),
        'excerpt' => medical_home_next_text($row['excerpt'] ?? ''),
        'featured_image_url' => $cover !== '' ? $cover : ($images[0] ?? ''),
        'updated_at' => medical_home_next_text($row['updated_at'] ?? ''),
        'member_count' => $type === 'mixed' ? $facilityCount + $doctorCount : ($type === 'doctor' ? $doctorCount : $facilityCount),
        'collage_images' => array_slice($images, 0, 3),
    ];
}

function medical_home_next_data(PDO $pdo, string $locale): array
{
    $locale = strtolower(trim($locale)) === 'en' ? 'en' : 'vi';
    $data = medical_home_next_empty();
    $resolveMedia = function_exists('site_absolute_media_url') ? 'site_absolute_media_url' : null;

    // Statistics and sections fail independently, preserving available data.
    foreach (['facilities' => 'medical_facilities', 'doctors' => 'medical_doctors', 'toplists' => 'medical_toplists'] as $key => $table) {
        try {
            $count = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE status = 'published' AND language_code = :locale");
            if ($count === false || !$count->execute([':locale' => $locale])) continue;
            $data['stats'][$key] = max(0, (int) $count->fetchColumn());
        } catch (Throwable $error) {
            // An uninitialized or temporarily unavailable table remains empty.
        }
    }

    try {
        $statement = $pdo->prepare(
            "SELECT id, slug, name, category, city, subtitle, verified, rating, reviews_count,
                    address_text, image_url, gallery_json
             FROM medical_facilities WHERE status = 'published' AND language_code = :locale
             ORDER BY rating DESC, reviews_count DESC, updated_at DESC, id DESC LIMIT 6"
        );
        if ($statement !== false && $statement->execute([':locale' => $locale])) {
            foreach (array_slice($statement->fetchAll(PDO::FETCH_ASSOC), 0, 6) as $row) {
                $data['facilities'][] = medical_home_next_facility_row($row, $resolveMedia);
            }
        }
    } catch (Throwable $error) {
        // Never fall back to demo or seeded records.
    }

    try {
        $statement = $pdo->prepare(
            "SELECT id, slug, name, specialty_text, title_text, city, image_url, gallery_json,
                    rating, reviews_count, verified
             FROM medical_doctors WHERE status = 'published' AND language_code = :locale
             ORDER BY rating DESC, reviews_count DESC, display_order ASC, id DESC LIMIT 3"
        );
        if ($statement !== false && $statement->execute([':locale' => $locale])) {
            foreach (array_slice($statement->fetchAll(PDO::FETCH_ASSOC), 0, 3) as $row) {
                $data['doctors'][] = medical_home_next_doctor_row($row, $resolveMedia);
            }
        }
    } catch (Throwable $error) {
        // The other homepage sections can still render.
    }

    try {
        $statement = $pdo->prepare(
            "SELECT t.id, t.slug, t.language_code, t.entity_type, t.title, t.excerpt,
                    t.featured_image_url, t.updated_at,
                    (SELECT COUNT(*) FROM medical_toplist_facilities tf
                     JOIN medical_facilities f ON f.id = tf.facility_id
                     WHERE tf.toplist_id = t.id AND f.status = 'published'
                       AND f.language_code = t.language_code) AS facility_count,
                    (SELECT COUNT(*) FROM medical_toplist_doctors td
                     JOIN medical_doctors d ON d.id = td.doctor_id
                     WHERE td.toplist_id = t.id AND d.status = 'published'
                       AND d.language_code = t.language_code) AS doctor_count
             FROM medical_toplists t WHERE t.status = 'published' AND t.language_code = :locale
             ORDER BY t.updated_at DESC, t.id DESC LIMIT 3"
        );
        if ($statement !== false && $statement->execute([':locale' => $locale])) {
            foreach (array_slice($statement->fetchAll(PDO::FETCH_ASSOC), 0, 3) as $row) {
                // These editorial cards have no photo collage. Avoid fetching
                // per-list member galleries that the preview never renders.
                $data['toplists'][] = medical_home_next_toplist_row($row, [], $resolveMedia);
            }
        }
    } catch (Throwable $error) {
        // Missing relationship tables must not break the homepage preview.
    }
    return $data;
}
