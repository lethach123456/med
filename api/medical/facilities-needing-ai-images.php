<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

medical_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$pdo = db();
if (!medical_directory_table_exists($pdo, 'medical_facilities') || !medical_directory_table_exists($pdo, 'medical_ai_prompts')) {
    medical_directory_ensure_tables($pdo);
} else {
    medical_directory_ensure_facility_ai_image_column($pdo);
    medical_directory_ensure_ai_image_prompt($pdo);
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

// The workflow is intentionally tuned to 3 or 4 reference images.  More
// URLs do not make the image prompt better and make the AI request heavier.
$minimumInput = (int) ($_GET['min_images'] ?? $_GET['min_gallery_images'] ?? 3);
$minImages = $minimumInput >= 4 ? 4 : 3;
$onlyMissingRaw = strtolower(trim((string) ($_GET['only_missing'] ?? '1')));
$onlyMissing = !in_array($onlyMissingRaw, ['0', 'false', 'no', 'off'], true);

$candidateWhere = [
    "status = 'published'",
    "JSON_VALID(COALESCE(gallery_json, '[]'))",
    "JSON_TYPE(COALESCE(gallery_json, '[]')) = 'ARRAY'",
    "JSON_LENGTH(COALESCE(gallery_json, '[]')) >= :min_images",
];
if ($onlyMissing) {
    $candidateWhere[] = "COALESCE(TRIM(ai_image_url), '') = ''";
}
$candidateWhereSql = implode(' AND ', $candidateWhere);

// JSON_LENGTH is a quick database pre-filter.  Count the surviving URLs in
// PHP as well so duplicates, empty strings and legacy malformed arrays never
// produce an inflated total or an empty final page.
$candidateStmt = $pdo->prepare(
    "SELECT id, gallery_json
     FROM medical_facilities
     WHERE {$candidateWhereSql}
     ORDER BY id ASC"
);
$candidateStmt->execute([':min_images' => $minImages]);
$eligibleIds = [];
foreach ($candidateStmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
    if (count(medical_directory_gallery_urls((string) ($candidate['gallery_json'] ?? ''))) >= $minImages) {
        $eligibleIds[] = (int) $candidate['id'];
    }
}
$total = count($eligibleIds);
$pageIds = array_slice($eligibleIds, $offset, $limit);
$rows = [];
if ($pageIds !== []) {
    $placeholders = implode(',', array_fill(0, count($pageIds), '?'));
    $itemsStmt = $pdo->prepare(
        "SELECT id, slug, name, category, city, subtitle, content, address_text, website_url,
                image_url, ai_image_url, gallery_json, updated_at
         FROM medical_facilities
         WHERE id IN ({$placeholders})"
    );
    $itemsStmt->execute($pageIds);
    $rowsById = [];
    foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rowsById[(int) $row['id']] = $row;
    }
    foreach ($pageIds as $id) {
        if (isset($rowsById[$id])) {
            $rows[] = $rowsById[$id];
        }
    }
}

$resolved = medical_directory_resolve_ai_prompt($pdo, 'facility_image_prompt');
if (trim((string) ($resolved['template'] ?? '')) === '') {
    json_response(['ok' => false, 'message' => 'Chưa có Prompt AI tạo ảnh. Vui lòng mở Admin > Prompt AI để tạo hoặc lưu prompt này.'], 404);
}

$items = [];
foreach ($rows as $row) {
    $galleryImages = medical_directory_gallery_urls((string) ($row['gallery_json'] ?? ''));
    $referenceImages = array_slice($galleryImages, 0, 4);
    $content = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($row['content'] ?? ''))));
    if (function_exists('mb_strimwidth')) {
        $content = mb_strimwidth($content, 0, 1400, '…', 'UTF-8');
    } else {
        $content = substr($content, 0, 1400);
    }

    $prompt = medical_directory_ai_prompt_render_template((string) $resolved['template'], [
        'id' => (string) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'category' => (string) ($row['category'] ?? ''),
        'city' => (string) ($row['city'] ?? ''),
        'subtitle' => (string) ($row['subtitle'] ?? ''),
        'content' => $content,
        'address' => (string) ($row['address_text'] ?? ''),
        'website' => (string) ($row['website_url'] ?? ''),
        'gallery_count' => (string) count($galleryImages),
        'gallery_json' => medical_directory_json_encode($galleryImages),
        'reference_images' => medical_directory_json_encode($referenceImages),
    ]);

    $items[] = [
        'id' => (int) ($row['id'] ?? 0),
        'slug' => (string) ($row['slug'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'title' => (string) ($row['name'] ?? ''),
        'category' => (string) ($row['category'] ?? ''),
        'city' => (string) ($row['city'] ?? ''),
        'subtitle' => (string) ($row['subtitle'] ?? ''),
        'address' => (string) ($row['address_text'] ?? ''),
        'website' => (string) ($row['website_url'] ?? ''),
        'image_url' => (string) ($row['image_url'] ?? ''),
        'ai_image_url' => (string) ($row['ai_image_url'] ?? ''),
        'gallery_count' => count($galleryImages),
        'gallery_json' => (string) ($row['gallery_json'] ?? '[]'),
        'reference_images' => $referenceImages,
        'prompt_type' => 'facility_image_prompt',
        'prompt_label' => (string) ($resolved['label'] ?? ''),
        'prompt_source' => (string) ($resolved['source'] ?? 'default'),
        'prompt_key_used' => (string) ($resolved['prompt_key_used'] ?? 'facility_image_prompt'),
        'prompt' => $prompt,
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

json_response([
    'ok' => true,
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'pages' => (int) ceil($total / $limit),
    'min_images' => $minImages,
    'only_missing' => $onlyMissing,
    'prompt_type' => 'facility_image_prompt',
    'prompt_output_contract' => [
        'id' => 'ID cơ sở giữ nguyên',
        'image_prompt' => 'Prompt tiếng Anh dùng cho công cụ tạo ảnh ở bước kế tiếp',
    ],
    'receive_image_endpoint' => '/api/medical/facility-ai-image-update.php',
    'receive_image_contract' => [
        'url_payload' => ['id' => 123, 'ai_image_url' => 'https://image-host.example/generated.jpg'],
        'base64_payload' => ['id' => 123, 'image_data' => 'data:image/png;base64,...'],
        'set_as_cover' => 'true nếu muốn thay image_url (ảnh bìa) hiện tại; mặc định chỉ lưu ai_image_url.',
    ],
    'workflow' => 'Dùng image_prompt để tạo ảnh, rồi gửi URL/base64/file ảnh kết quả tới receive_image_endpoint.',
    'items' => $items,
]);
