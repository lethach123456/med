<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../medical_search_cache.php';
// The worker module is optional during deployment.  Article updates must keep
// working even if the background image worker has not been installed yet.
$medicalMediaWorker = __DIR__ . '/../../medical_media_worker.php';
if (is_file($medicalMediaWorker)) require_once $medicalMediaWorker;
medical_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);

// The admin bootstrap defines unique_slug(), but public API requests do not
// load that file. Keep the API self-contained for review creation.
if (!function_exists('medical_api_unique_slug')) {
    function medical_api_unique_slug(PDO $pdo, string $value): string
    {
        $base = function_exists('slugify') ? slugify($value) : strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
        if ($base === '') $base = bin2hex(random_bytes(6));
        $base = substr($base, 0, 180);
        $candidate = $base; $suffix = 2;
        $check = $pdo->prepare('SELECT 1 FROM medical_reviews WHERE slug = :slug LIMIT 1');
        while (true) {
            $check->execute([':slug' => $candidate]);
            if (!$check->fetchColumn()) return $candidate;
            $candidate = $base . '-' . $suffix++;
        }
    }
}

/** Keep Markdown-formatted links from being saved as literal image/map URLs. */
function medical_api_content_url(mixed $value): string
{
    $url = trim((string) ($value ?? ''));
    if (preg_match('~^\[([^\]]+)]\((https?://[^\s)]+)\)$~u', $url, $matches)) {
        $label = trim((string) ($matches[1] ?? ''));
        $href = trim((string) ($matches[2] ?? ''));
        $url = preg_match('~^https?://~i', $label) ? $label : $href;
    }
    return $url;
}

function medical_api_content_normalize_urls(mixed $value): mixed
{
    if (!is_array($value)) {
        return is_string($value) ? medical_api_content_url($value) : $value;
    }
    foreach ($value as $key => $item) {
        $value[$key] = medical_api_content_normalize_urls($item);
    }
    return $value;
}

/**
 * Normalize gallery input while retaining the useful descriptive metadata.
 *
 * Accepts both legacy `["https://..."]` payloads and the richer object form:
 * `[{"url":"...","angle":"...","caption":"...","source":"..."}]`.
 * Every stored item uses the latter form, allowing the media worker to replace
 * only `url` with the owned local URL without discarding its context.
 *
 * @return array<int,array{url:string,angle?:string,caption?:string,source?:string}>
 */
function medical_api_content_gallery_items(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [$value];
    }
    if (!is_array($value)) {
        return [];
    }

    // A single image object is also accepted, even though the documented
    // transport format is an array.
    if (array_key_exists('url', $value) || array_key_exists('src', $value)) {
        $value = [$value];
    }

    $itemsByUrl = [];
    foreach ($value as $entry) {
        $url = is_array($entry)
            ? medical_api_content_url($entry['url'] ?? $entry['src'] ?? '')
            : medical_api_content_url($entry);
        if ($url === '' || isset($itemsByUrl[$url])) {
            continue;
        }

        $item = ['url' => $url];
        if (is_array($entry)) {
            foreach (['angle' => 120, 'caption' => 500, 'source' => 120] as $key => $maxLength) {
                $text = trim((string) ($entry[$key] ?? ''));
                if ($text === '') {
                    continue;
                }
                $item[$key] = function_exists('mb_strimwidth')
                    ? mb_strimwidth($text, 0, $maxLength, '', 'UTF-8')
                    : substr($text, 0, $maxLength);
            }
        }
        $itemsByUrl[$url] = $item;
    }

    return array_values($itemsByUrl);
}

/** @return array{rating:float,reviews_count:int}|null */
function medical_api_content_rating_summary(mixed $aggregateRatings, mixed $reviews): ?array
{
    if (is_string($aggregateRatings)) {
        $decoded = json_decode($aggregateRatings, true);
        $aggregateRatings = is_array($decoded) ? $decoded : [];
    }
    $weightedScore = 0.0;
    $weightedCount = 0;
    foreach (is_array($aggregateRatings) ? $aggregateRatings : [] as $row) {
        if (!is_array($row) || !is_numeric($row['score'] ?? null) || !is_numeric($row['count'] ?? null)) continue;
        $score = (float) $row['score'];
        $count = max(0, (int) $row['count']);
        if ($score <= 0 || $count <= 0) continue;
        $weightedScore += min(5.0, $score) * $count;
        $weightedCount += $count;
    }
    if ($weightedCount > 0) {
        return ['rating' => round($weightedScore / $weightedCount, 1), 'reviews_count' => $weightedCount];
    }

    $sum = 0.0;
    $count = 0;
    foreach (is_array($reviews) ? $reviews : [] as $review) {
        if (!is_array($review) || !is_numeric($review['rating'] ?? null)) continue;
        $score = (float) $review['rating'];
        if ($score <= 0) continue;
        $sum += min(5.0, $score);
        $count++;
    }
    return $count > 0 ? ['rating' => round($sum / $count, 1), 'reviews_count' => $count] : null;
}

function medical_api_content_bool(mixed $value): int
{
    if (is_bool($value)) return $value ? 1 : 0;
    if (is_numeric($value)) return (int) $value > 0 ? 1 : 0;
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

$body = read_json_body();
// Hỗ trợ cả payload chuẩn {items:[...]} và payload tiện ích bọc JSON AI trong content.
if (isset($body['items']) && is_array($body['items'])) {
    $items = $body['items'];
} elseif (isset($body['content']) && is_string($body['content'])) {
    $decodedContent = json_decode($body['content'], true);
    $items = is_array($decodedContent) ? [$decodedContent] : [$body];
} else {
    $items = [$body];
}
// The schema is installed by setup/admin migrations. Running the full schema
// bootstrap here used to issue several CREATE/ALTER statements on every API
// request, which can wait for MySQL metadata locks long enough for the Chrome
// extension's 30-second request timer to abort. Only bootstrap a genuinely
// new install; normal article updates use the existing schema directly.
$pdo = db();
if (!medical_directory_table_exists($pdo, 'medical_facilities')) medical_directory_ensure_tables($pdo);
medical_directory_ensure_facility_content_columns($pdo);
$updated = [];
$errors = [];
$reviewsCreated = 0;
// Image fetching/compression is deliberately asynchronous.  This endpoint
// only persists the URLs supplied by the article generator so its response is
// fast and a slow third-party image host cannot make article updates fail.
$imagesRequested = 0;
$imagesQueued = 0;
$galleryImagesRequested = 0;
$galleryImagesQueued = 0;
$imageErrors = [];
$processedItems = [];
try { foreach ($items as $item) {
    if (!is_array($item)) continue; $id = (int) ($item['id'] ?? 0); $slug = trim((string) ($item['slug'] ?? ''));
    if ($id <= 0 && $slug === '') { $errors[] = 'Thiếu id hoặc slug.'; continue; }
    $where = $id > 0 ? 'id = :lookup_id' : 'slug = :lookup_slug'; $lookup = $pdo->prepare("SELECT id, slug, name, image_url FROM medical_facilities WHERE {$where} LIMIT 1"); $lookup->execute($id > 0 ? [':lookup_id'=>$id] : [':lookup_slug'=>$slug]); $facilityRow = $lookup->fetch(PDO::FETCH_ASSOC) ?: []; $facilityId = (int) ($facilityRow['id'] ?? 0);
    if ($facilityId <= 0) { $errors[] = 'Không tìm thấy cơ sở: ' . ($slug ?: $id); continue; }
    $fields = [
        'name' => 'name', 'category' => 'category', 'city' => 'city',
        'address' => 'address_text', 'address_text' => 'address_text',
        'phone' => 'phone_text', 'phone_text' => 'phone_text',
        'website' => 'website_url', 'website_url' => 'website_url',
        'email' => 'email_text', 'email_text' => 'email_text',
        'subtitle' => 'subtitle', 'content' => 'content', 'content_html' => 'content',
        'price' => 'price_text', 'price_text' => 'price_text',
        'price_table_html' => 'price_table_html', 'price_source_scope' => 'price_source_scope',
        'hours' => 'hours_text', 'hours_text' => 'hours_text',
        'google_maps_url' => 'google_maps_url', 'parking_info' => 'parking_info',
        'nearby_landmarks' => 'nearby_landmarks', 'emergency_hotline' => 'emergency_hotline',
        'booking_url' => 'booking_url', 'business_license' => 'business_license',
        'medical_operation_license' => 'medical_operation_license',
        'warranty_policy' => 'warranty_policy', 'notes_for_editor' => 'notes_for_editor',
    ];
    $urlColumns = array_fill_keys(['website_url', 'google_maps_url', 'booking_url'], true);
    // Keep the original AI payload intact in full_json.  In particular, image
    // URLs remain the source URLs until the independent media worker has
    // downloaded and replaced them with locally owned files.
    $normalizedItem = $item;
    $set = ['full_json' => ':full_json'];
    $params = [':id' => $facilityId];
    foreach ($fields as $input => $column) {
        if (!array_key_exists($input, $item)) continue;
        $set[$column] = ':' . $column;
        $value = $item[$input];
        if ($value === null) {
            $params[':' . $column] = null;
        } elseif (isset($urlColumns[$column])) {
            $params[':' . $column] = medical_api_content_url($value);
        } else {
            $params[':' . $column] = is_array($value) ? medical_api_json($value) : trim((string) $value);
        }
    }

    $jsonFields = [
        'social_links' => 'social_links_json', 'social_links_json' => 'social_links_json',
        'insurance_accepted' => 'insurance_accepted_json', 'insurance_accepted_json' => 'insurance_accepted_json',
        'payment_methods' => 'payment_methods_json', 'payment_methods_json' => 'payment_methods_json',
        'languages_supported' => 'languages_supported_json', 'languages_supported_json' => 'languages_supported_json',
        'equipment_mentioned' => 'equipment_mentioned_json', 'equipment_mentioned_json' => 'equipment_mentioned_json',
        'doctors' => 'doctors_json', 'doctors_json' => 'doctors_json',
        'video_urls' => 'video_urls_json', 'video_urls_json' => 'video_urls_json',
        'aggregate_ratings' => 'aggregate_ratings_json', 'aggregate_ratings_json' => 'aggregate_ratings_json',
    ];
    foreach ($jsonFields as $input => $column) {
        if (!array_key_exists($input, $item)) continue;
        $set[$column] = ':' . $column;
        $params[':' . $column] = $item[$input] === null
            ? null
            : medical_api_json(medical_api_content_normalize_urls($item[$input]));
    }

    foreach (['established_year' => 9999, 'branch_count' => 999] as $input => $max) {
        if (!array_key_exists($input, $item)) continue;
        $set[$input] = ':' . $input;
        $value = $item[$input];
        $params[':' . $input] = is_numeric($value) ? min($max, max(0, (int) $value)) : null;
    }
    if (array_key_exists('insufficient_data', $item)) {
        $set['insufficient_data'] = ':insufficient_data';
        $params[':insufficient_data'] = medical_api_content_bool($item['insufficient_data']);
    }

    $coordinates = is_array($item['coordinates'] ?? null) ? $item['coordinates'] : [];
    foreach (['latitude' => 'lat', 'longitude' => 'lng'] as $column => $coordinateKey) {
        if (!array_key_exists($column, $item) && !array_key_exists($coordinateKey, $coordinates)) continue;
        $value = $item[$column] ?? $coordinates[$coordinateKey] ?? null;
        $set[$column] = ':' . $column;
        $params[':' . $column] = is_numeric($value) ? (float) $value : null;
    }

    $ratingSummary = medical_api_content_rating_summary(
        $item['aggregate_ratings'] ?? $item['aggregate_ratings_json'] ?? [],
        $item['reviews'] ?? $item['reviews_list'] ?? []
    );
    if ($ratingSummary !== null) {
        $set['rating'] = ':rating';
        $set['reviews_count'] = ':reviews_count';
        $params[':rating'] = $ratingSummary['rating'];
        $params[':reviews_count'] = $ratingSummary['reviews_count'];
    }

    // Save gallery URLs immediately.  The old flow downloaded and compressed
    // each remote image inside this HTTP request, which caused 30-second
    // extension timeouts whenever an image host was slow.
    $galleryProvided = false;
    $galleryItems = [];
    foreach (['gallery_json', 'gallery', 'images'] as $galleryKey) {
        if (!array_key_exists($galleryKey, $item)) continue;
        $galleryProvided = true; $value = $item[$galleryKey];
        foreach (medical_api_content_gallery_items($value) as $galleryItem) {
            $url = (string) ($galleryItem['url'] ?? '');
            if ($url === '') continue;
            // The first occurrence wins, so a later alias field cannot erase
            // caption/source data supplied in the canonical gallery_json.
            if (!isset($galleryItems[$url])) {
                $galleryItems[$url] = $galleryItem;
            }
        }
    }
    $galleryItems = array_values($galleryItems);
    $gallerySources = array_values(array_map(
        static fn(array $galleryItem): string => (string) ($galleryItem['url'] ?? ''),
        $galleryItems
    ));
    $featureProvided = false;
    $featureSources = [];
    foreach (['image_url', 'featured_image_url'] as $featureKey) {
        if (!array_key_exists($featureKey, $item)) continue;
        $featureProvided = true; $candidate = medical_api_content_url($item[$featureKey]);
        if ($candidate !== '') $featureSources[$featureKey] = $candidate;
    }

    // An explicitly supplied empty gallery clears the saved gallery.  A
    // non-empty gallery is stored as-is, including remote URLs.
    if ($galleryProvided) {
        $set['gallery_json'] = ':gallery_json';
        $params[':gallery_json'] = medical_api_json($galleryItems);
    }

    // Prefer an explicit cover image.  When it is omitted, use the first
    // image in the supplied gallery so the card can render before the worker
    // finishes importing the local copies.
    $resolvedFeature = '';
    foreach (['image_url', 'featured_image_url'] as $featureKey) {
        if (!empty($featureSources[$featureKey])) {
            $resolvedFeature = (string) $featureSources[$featureKey];
            break;
        }
    }
    if ($resolvedFeature === '' && $galleryProvided && isset($gallerySources[0])) $resolvedFeature = (string) $gallerySources[0];
    if ($resolvedFeature !== '') {
        $set['image_url'] = ':image_url';
        $params[':image_url'] = $resolvedFeature;
    } elseif ($featureProvided) {
        // An explicit empty cover is treated as a request to clear it.
        $set['image_url'] = ':image_url';
        $params[':image_url'] = '';
    }

    $queueSources = [
        'image_url' => array_values($featureSources),
        'gallery_json' => $gallerySources,
    ];
    $queueUrls = array_values(array_unique(array_merge($queueSources['image_url'], $queueSources['gallery_json'])));
    $imagesRequested += count($queueUrls);
    $galleryImagesRequested += count($gallerySources);
    foreach (['services'=>'services_json', 'featured_services'=>'featured_services_json', 'highlights'=>'highlights_json'] as $input => $column) {
        if (!array_key_exists($input, $item)) continue;
        $set[$column] = ':' . $column;
        $value = $item[$input];
        $params[':' . $column] = is_array($value) ? medical_api_json($value) : medical_api_json([(string) $value]);
    }
    $params[':full_json'] = medical_api_json($normalizedItem);
    $sql = 'UPDATE medical_facilities SET ' . implode(', ', array_map(static fn($column, $placeholder) => $column . '=' . $placeholder, array_keys($set), array_values($set))) . ' WHERE id=:id';
    $pdo->prepare($sql)->execute($params);
    $updated[] = $facilityId;
    // Queue is a best-effort optimization.  A background scanner can still
    // discover the remote URLs in gallery_json if queue storage is temporarily
    // unavailable, so a queue error must never roll back the article update.
    $queuedForEntity = count($queueSources);
    if ($queueUrls !== [] && function_exists('medical_media_jobs_enqueue_entity_urls')) {
        try {
            $queueResult = medical_media_jobs_enqueue_entity_urls($pdo, 'facility', $facilityId, $queueSources);
            if (is_array($queueResult)) {
                $queuedForEntity = max(0, (int) ($queueResult['queued'] ?? $queueResult['queued_count'] ?? $queuedForEntity));
            }
        } catch (Throwable $queueError) {
            $imageErrors[] = [
                'facility_id' => $facilityId,
                'input_field' => 'gallery_json',
                'message' => 'Đã lưu URL ảnh; Worker sẽ quét lại sau. Chi tiết hàng đợi: ' . $queueError->getMessage(),
            ];
        }
    }
    $imagesQueued += $queuedForEntity;
    $galleryImagesQueued += count($gallerySources);
    $processedItems[] = $normalizedItem;
    $reviewItems = $item['reviews'] ?? $item['reviews_list'] ?? $item['reivew'] ?? $item['review'] ?? [];
    if (is_array($reviewItems)) {
        $reviewStmt = $pdo->prepare("INSERT INTO medical_reviews (slug, facility_slug, facility_id, facility_name, title, verified, rating, author_text, location_text, review_date_text, price_text, excerpt, likes_count, comments_count, service_text, method_text, duration_text, condition_text, source_text, status, display_order) VALUES (:slug,:facility_slug,:facility_id,:facility_name,:title,:verified,:rating,:author,:location,:date,:price,:excerpt,:likes,:comments,:service,:method,:duration,:condition,:source,'published',:display_order)");
        // A browser can time out after PHP has already persisted the article.
        // Retrying the same AI JSON must not insert the same review twice.
        $reviewExistsStmt = $pdo->prepare(
            'SELECT id FROM medical_reviews
             WHERE facility_id = :facility_id
               AND author_text = :author
               AND rating = :rating
               AND excerpt = :excerpt
               AND source_text = :source
             LIMIT 1'
        );
        foreach ($reviewItems as $reviewIndex => $review) {
            if (!is_array($review)) continue;
            $title = trim((string) ($review['title'] ?? $review['name'] ?? 'Review tại ' . ($facilityRow['name'] ?? 'cơ sở y tế')));
            $rating = (float) str_replace('/5', '', (string) ($review['rating'] ?? 0));
            $author = (string) ($review['author_text'] ?? $review['author'] ?? $review['name'] ?? '');
            $excerpt = (string) ($review['content'] ?? $review['excerpt'] ?? $review['review'] ?? '');
            $source = (string) ($review['source_text'] ?? $review['source'] ?? '');
            $reviewExistsStmt->execute([':facility_id'=>$facilityId, ':author'=>$author, ':rating'=>$rating, ':excerpt'=>$excerpt, ':source'=>$source]);
            if ($reviewExistsStmt->fetchColumn()) continue;
            $reviewSlug = medical_api_unique_slug($pdo, $title . '-' . ($facilityRow['slug'] ?? $facilityId) . '-' . $reviewIndex);
            $reviewStmt->execute([':slug'=>$reviewSlug, ':facility_slug'=>(string)($facilityRow['slug'] ?? ''), ':facility_id'=>$facilityId, ':facility_name'=>(string)($facilityRow['name'] ?? ''), ':title'=>$title, ':verified'=>(int)($review['verified'] ?? 1), ':rating'=>$rating, ':author'=>$author, ':location'=>(string)($review['location_text'] ?? $review['location'] ?? ''), ':date'=>(string)($review['review_date_text'] ?? $review['date'] ?? ''), ':price'=>(string)($review['price_text'] ?? $review['price'] ?? ''), ':excerpt'=>$excerpt, ':likes'=>(int)($review['likes_count'] ?? $review['likes'] ?? 0), ':comments'=>(int)($review['comments_count'] ?? $review['comments'] ?? 0), ':service'=>(string)($review['service_text'] ?? $review['service'] ?? ''), ':method'=>(string)($review['method_text'] ?? $review['method'] ?? ''), ':duration'=>(string)($review['duration_text'] ?? $review['duration'] ?? ''), ':condition'=>(string)($review['condition_text'] ?? $review['condition'] ?? ''), ':source'=>$source, ':display_order'=>(int)$reviewIndex]);
            $reviewsCreated++;
        }
    }
} } catch (Throwable $e) {
    // A later item can fail after earlier facilities have already been
    // persisted. Mark the snapshot stale before returning that partial
    // failure so public search cannot keep showing the prior values.
    if ($updated !== []) {
        medical_search_cache_invalidate();
    }
    json_response(['ok' => false, 'message' => 'Cập nhật thất bại: ' . $e->getMessage(), 'updated_ids' => $updated, 'reviews_created' => $reviewsCreated, 'image_processing' => 'queued', 'images_requested' => $imagesRequested, 'images_queued' => $imagesQueued, 'images_imported' => 0, 'gallery_images_requested' => $galleryImagesRequested, 'gallery_images_queued' => $galleryImagesQueued, 'gallery_images_imported' => 0, 'image_errors' => $imageErrors, 'processed_items' => $processedItems], 500);
}
if ($updated !== []) {
    medical_search_cache_invalidate();
}
json_response(['ok' => true, 'updated_ids' => $updated, 'updated_count' => count($updated), 'reviews_created' => $reviewsCreated, 'image_processing' => 'queued', 'images_requested' => $imagesRequested, 'images_queued' => $imagesQueued, 'images_imported' => 0, 'gallery_images_requested' => $galleryImagesRequested, 'gallery_images_queued' => $galleryImagesQueued, 'gallery_images_imported' => 0, 'image_errors' => $imageErrors, 'processed_items' => $processedItems, 'errors' => $errors]);
