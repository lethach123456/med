<?php
declare(strict_types=1);
// Legacy utilities used this endpoint with type=doctor. Route to the real doctor queue.
if (($_GET['type'] ?? '') === 'doctor') { require __DIR__ . '/doctors-needing-content.php'; exit; }
require_once __DIR__ . '/_auth.php';
medical_api_auth();
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
$pdo = db();
$page = max(1, (int) ($_GET['page'] ?? 1)); $limit = min(100, max(1, (int) ($_GET['limit'] ?? 25))); $offset = ($page - 1) * $limit;
$type = trim((string) ($_GET['type'] ?? 'facility'));
$allowedTypes = ['facility', 'doctor', 'review'];
if (!in_array($type, $allowedTypes, true)) $type = 'facility';
$where = "status = 'published' AND COALESCE(TRIM(content),'') = ''";
$requestedIds = [];
if (array_key_exists('ids', $_GET)) {
    if (!is_string($_GET['ids'])) {
        json_response(['ok' => false, 'message' => 'ids phải là danh sách ID nguyên dương, phân cách bằng dấu phẩy.'], 422);
    }
    foreach (explode(',', $_GET['ids']) as $rawId) {
        $rawId = trim($rawId);
        if ($rawId === '' || !ctype_digit($rawId) || (int) $rawId <= 0) {
            json_response(['ok' => false, 'message' => 'ids phải là danh sách ID nguyên dương, phân cách bằng dấu phẩy.'], 422);
        }
        $requestedIds[(int) $rawId] = (int) $rawId;
    }
    $requestedIds = array_values($requestedIds);
    if ($requestedIds === [] || count($requestedIds) > 100) {
        json_response(['ok' => false, 'message' => 'ids cần có từ 1 đến 100 ID.'], 422);
    }
}
$params = [];
if ($requestedIds !== []) {
    $idPlaceholders = [];
    foreach ($requestedIds as $index => $requestedId) {
        $placeholder = ':requested_id_' . $index;
        $idPlaceholders[] = $placeholder;
        $params[$placeholder] = $requestedId;
    }
    $where .= ' AND id IN (' . implode(', ', $idPlaceholders) . ')';
}
$cityPriority = medical_directory_major_city_priority_sql('city');
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM medical_facilities WHERE {$where}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$stmt = $pdo->prepare("SELECT id, slug, name, category, city, address_text, phone_text, website_url, image_url, gallery_json, subtitle, content, full_json, price_text, price_table_html, hours_text, services_json, intro_json, ai_writer_claim_json, updated_at FROM medical_facilities WHERE {$where} ORDER BY {$cityPriority} ASC, id ASC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $value) $stmt->bindValue($key, $value, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
$now = time();
$resolvedPromptCache = [];
foreach ($items as &$item) {
    $storedClaim = json_decode((string) ($item['ai_writer_claim_json'] ?? ''), true);
    $activeClaim = is_array($storedClaim) && (int) ($storedClaim['expires_at'] ?? 0) > $now;
    $item['writer_claimed'] = $activeClaim;
    $item['writer_claim'] = null;
    if ($activeClaim) {
        $item['writer_claim'] = array_intersect_key($storedClaim, array_flip([
            'provider', 'model', 'task', 'instance_id', 'instance_label', 'account_label',
            'worker_id', 'claimed_at', 'heartbeat_at', 'expires_at',
        ]));
    }
    // Never expose the private claim token in the normal queue response.
    unset($item['ai_writer_claim_json']);
    $category = $type === 'facility' ? (string) ($item['category'] ?? '') : '';
    $cacheKey = $type . '|' . medical_directory_ai_prompt_normalize_category($category);
    if (!array_key_exists($cacheKey, $resolvedPromptCache)) {
        $resolvedPromptCache[$cacheKey] = medical_directory_resolve_ai_prompt($pdo, $type, $category);
    }
    $prompt = $resolvedPromptCache[$cacheKey];
    $item['prompt_type'] = $type;
    $item['prompt_label'] = (string) $prompt['label'];
    $item['prompt_source'] = (string) $prompt['source'];
    $item['prompt_variant_id'] = $prompt['variant_id'];
    $item['prompt_categories'] = $prompt['categories'];
    $item['prompt_key_used'] = (string) $prompt['prompt_key_used'];
    $promptTemplate = $type === 'facility'
        ? medical_directory_facility_json_transport_rules((string) $prompt['template'])
        : (string) $prompt['template'];
    $item['prompt'] = medical_directory_ai_prompt_render_template($promptTemplate, [
        'id' => (string) $item['id'],
        'name' => (string) $item['name'],
        'address' => (string) ($item['address_text'] ?? ''),
        'phone' => (string) ($item['phone_text'] ?? ''),
        'website' => (string) ($item['website_url'] ?? ''),
        'hours' => (string) ($item['hours_text'] ?? ''),
        'hours_text' => (string) ($item['hours_text'] ?? ''),
        'gallery_json' => (string) ($item['gallery_json'] ?? ''),
    ]);
}
unset($item);
$claimedCount = count(array_filter($items, static fn(array $item): bool => !empty($item['writer_claimed'])));
json_response(['ok' => true, 'page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => (int) ceil($total / $limit), 'claimed_count' => $claimedCount, 'prompt_type' => $type, 'prompt_selection' => $type === 'facility' ? 'per_facility_category_with_default_fallback' : 'default', 'ordering' => 'major_cities_first_then_remaining', 'priority_cities' => medical_directory_major_city_priority_labels(), 'checked_ids' => $requestedIds !== [] ? $requestedIds : null, 'eligibility_check' => $requestedIds !== [] ? 'published_with_empty_content' : null, 'items' => $items]);
