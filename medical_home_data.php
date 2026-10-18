<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_facility_ratings.php';

/** Read-only homepage selection. Never fall back to another language. */
function medical_home_data(PDO $pdo, string $locale): array
{
    $locale = $locale === 'en' ? 'en' : 'vi';
    $count = $pdo->prepare("SELECT COUNT(*) FROM medical_facilities WHERE status = 'published' AND language_code = :locale");
    $count->execute([':locale' => $locale]);
    $facilities = $pdo->prepare(
        "SELECT id, slug, language_code, name, category, city, subtitle, verified, rating, reviews_count, aggregate_ratings_json, review_summary_json, address_text,
                image_url, gallery_json, tags_json, featured_services_json
         FROM medical_facilities
         WHERE status = 'published' AND language_code = :locale
         ORDER BY rating DESC, reviews_count DESC, updated_at DESC, id DESC
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
        $summary = medical_facility_rating_summary($row);
        return array_replace($row, ['rating' => $summary['rating'], 'reviews_count' => $summary['reviews_count'],
            'rating_basis' => $summary['basis'], 'rating_sources' => $summary['sources']]);
    }, $facilities->fetchAll(PDO::FETCH_ASSOC));
    return ['facilities' => $facilityRows,
        'toplists' => $toplists->fetchAll(PDO::FETCH_ASSOC),
        'stats' => ['facilities' => (int) $count->fetchColumn()]];
}
