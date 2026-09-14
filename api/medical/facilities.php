<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../medical_directory.php';

header('Content-Type: application/json; charset=utf-8');

function medical_public_list_decode(?string $value): array
{
    $decoded = json_decode((string) $value, true);
    if (!is_array($decoded)) {
        return [];
    }

    $items = [];
    foreach ($decoded as $item) {
        if (is_array($item)) {
            $item = $item['name'] ?? $item['title'] ?? '';
        }
        $item = trim((string) $item);
        if ($item !== '') {
            $items[] = $item;
        }
    }
    return array_values(array_unique($items));
}

function medical_public_list_item(array $row): array
{
    // Gallery can now contain image objects with caption/source metadata.
    // Public cards only need their URLs, while the DB keeps the full objects.
    $gallery = medical_directory_gallery_urls((string) ($row['gallery_json'] ?? ''));
    $image = trim((string) ($row['image_url'] ?? ''));
    if ($image === '' && $gallery !== []) {
        $image = (string) $gallery[0];
    }

    $services = medical_public_list_decode((string) ($row['featured_services_json'] ?? ''));
    if ($services === []) {
        $services = medical_public_list_decode((string) ($row['services_json'] ?? ''));
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
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

try {
    $pdo = db();
    if (!medical_directory_table_exists($pdo, 'medical_facilities')) {
        throw new RuntimeException('Dữ liệu cơ sở y tế chưa sẵn sàng.');
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(24, max(6, (int) ($_GET['limit'] ?? 12)));
    $query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120, 'UTF-8');
    $city = mb_substr(trim((string) ($_GET['city'] ?? '')), 0, 120, 'UTF-8');
    $category = mb_substr(trim((string) ($_GET['category'] ?? '')), 0, 120, 'UTF-8');
    $service = mb_substr(trim((string) ($_GET['service'] ?? '')), 0, 120, 'UTF-8');
    $minRating = (float) ($_GET['min_rating'] ?? 0);
    $minRating = in_array($minRating, [0.0, 4.0, 4.5], true) ? $minRating : 0.0;
    $sort = (string) ($_GET['sort'] ?? 'recommended');

    $where = ["status = 'published'"];
    $params = [];
    $queryCity = '';
    if ($query !== '' && $city === '') {
        $cityCandidates = $pdo->query("SELECT city FROM medical_facilities WHERE status = 'published' AND city <> '' GROUP BY city ORDER BY CHAR_LENGTH(city) DESC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($cityCandidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && mb_stripos($query, $candidate, 0, 'UTF-8') !== false) {
                $queryCity = $candidate;
                break;
            }
        }
    }
    if ($queryCity !== '') {
        $where[] = 'city = :query_city_exact';
        $params[':query_city_exact'] = $queryCity;
    }
    if ($query !== '') {
        $terms = preg_split('/[^\p{L}\p{N}]+/u', $query) ?: [];
        $terms = array_values(array_unique(array_filter($terms, static fn(string $term): bool => mb_strlen($term, 'UTF-8') >= 2)));
        foreach (array_slice($terms, 0, 6) as $index => $term) {
            $placeholder = ':search_term_' . $index;
            $where[] = "CONCAT_WS(' ', name, subtitle, category, city, address_text, services_json, featured_services_json, slug) LIKE {$placeholder}";
            $params[$placeholder] = '%' . $term . '%';
        }
    }
    if ($city !== '') {
        $where[] = 'city = :city';
        $params[':city'] = $city;
    }
    if ($category !== '') {
        $where[] = 'category = :category';
        $params[':category'] = $category;
    }
    if ($service !== '') {
        $where[] = '(COALESCE(featured_services_json, \'\') LIKE :service_featured OR COALESCE(services_json, \'\') LIKE :service_all)';
        $serviceLike = '%' . $service . '%';
        $params[':service_featured'] = $serviceLike;
        $params[':service_all'] = $serviceLike;
    }
    if ($minRating > 0) {
        $where[] = 'rating >= :min_rating';
        $params[':min_rating'] = $minRating;
    }

    $orderBy = match ($sort) {
        'newest' => 'updated_at DESC, id DESC',
        'rating' => 'rating DESC, reviews_count DESC, id DESC',
        'reviews' => 'reviews_count DESC, rating DESC, id DESC',
        default => 'rating DESC, reviews_count DESC, display_order ASC, id DESC',
    };
    $whereSql = implode(' AND ', $where);

    $count = $pdo->prepare("SELECT COUNT(*) FROM medical_facilities WHERE {$whereSql}");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $limit));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare(
        "SELECT id, slug, name, category, city, subtitle, verified, rating, reviews_count, followers_count,
                hours_text, address_text, price_text, image_url, images_label, featured_services_json, services_json, gallery_json
         FROM medical_facilities
         WHERE {$whereSql}
         ORDER BY {$orderBy}
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $key === ':min_rating' ? PDO::PARAM_STR : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = array_map('medical_public_list_item', $stmt->fetchAll(PDO::FETCH_ASSOC));
    echo json_encode([
        'ok' => true,
        'items' => $items,
        'paging' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Không thể tải danh sách cơ sở y tế.'], JSON_UNESCAPED_UNICODE);
}
