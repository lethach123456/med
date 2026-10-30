<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_facility_ratings.php';
require_once __DIR__ . '/medical_public_stats.php';

/** Read-only homepage selection. Never fall back to another language. */
function medical_home_data(PDO $pdo, string $locale, ?array $publicIndex = null): array
{
    $locale = $locale === 'en' ? 'en' : 'vi';
    $publicStats = medical_public_stats($locale, $pdo, $publicIndex);
    $ratingSql = medical_facility_stored_rating_sql();
    $facilities = $pdo->prepare(
        "SELECT f.id, f.slug, f.language_code, f.name, f.category, f.city, f.subtitle, f.verified,
                f.rating, f.reviews_count, f.aggregate_ratings_json, f.review_summary_json, f.address_text,
                f.image_url, f.gallery_json, f.tags_json, f.featured_services_json,
                COALESCE(facility_reviews.stored_rating, 0) AS stored_rating,
                COALESCE(facility_reviews.stored_reviews_count, 0) AS stored_reviews_count,
                facility_reviews.stored_review_source_kinds
         FROM medical_facilities f
         LEFT JOIN ({$ratingSql}) facility_reviews ON facility_reviews.facility_id = f.id
         WHERE f.status = 'published' AND f.language_code = :locale
         ORDER BY stored_rating DESC, stored_reviews_count DESC, f.updated_at DESC, f.id DESC
         LIMIT 24"
    );
    $facilities->execute([':locale' => $locale]);
    $toplists = $pdo->prepare(
        "SELECT t.id, t.slug, t.language_code, t.entity_type, t.title, t.excerpt, t.featured_image_url, t.updated_at,
         (SELECT COUNT(*) FROM medical_toplist_facilities tf JOIN medical_facilities f ON f.id=tf.facility_id WHERE tf.toplist_id=t.id AND f.status='published' AND f.language_code=t.language_code) AS facility_count,
         (SELECT COUNT(*) FROM medical_toplist_doctors td JOIN medical_doctors d ON d.id=td.doctor_id WHERE td.toplist_id=t.id AND d.status='published' AND d.language_code=t.language_code) AS doctor_count
         FROM medical_toplists t
         WHERE t.status = 'published' AND t.language_code = :locale
         ORDER BY t.updated_at DESC, t.id DESC
         LIMIT 3"
    );
    $toplists->execute([':locale' => $locale]);
    $facilityRows = array_map(static function (array $row): array {
        $summary = medical_facility_public_rating_summary($row);
        return array_replace($row, ['rating' => $summary['rating'], 'reviews_count' => $summary['reviews_count'],
            'rating_basis' => $summary['basis'], 'rating_sources' => $summary['sources']]);
    }, $facilities->fetchAll(PDO::FETCH_ASSOC));
    return ['facilities' => $facilityRows,
        'toplists' => $toplists->fetchAll(PDO::FETCH_ASSOC),
        'stats' => ['facilities' => $publicStats['facilities']]];
}
