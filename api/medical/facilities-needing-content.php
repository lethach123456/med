<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
medical_api_auth();
$pdo = db(); medical_directory_ensure_tables($pdo);
$page = max(1, (int) ($_GET['page'] ?? 1)); $limit = min(100, max(1, (int) ($_GET['limit'] ?? 25))); $offset = ($page - 1) * $limit;
$type = trim((string) ($_GET['type'] ?? 'facility'));
$allowedTypes = ['facility', 'doctor', 'review'];
if (!in_array($type, $allowedTypes, true)) $type = 'facility';
$where = "status = 'published' AND COALESCE(TRIM(content),'') = ''";
$cityPriority = medical_directory_major_city_priority_sql('city');
$total = (int) $pdo->query("SELECT COUNT(*) FROM medical_facilities WHERE {$where}")->fetchColumn();
$stmt = $pdo->prepare("SELECT id, slug, name, category, city, address_text, phone_text, website_url, image_url, gallery_json, subtitle, content, full_json, price_text, price_table_html, hours_text, services_json, intro_json, updated_at FROM medical_facilities WHERE {$where} ORDER BY {$cityPriority} ASC, id ASC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); $stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
$resolvedPromptCache = [];
foreach ($items as &$item) {
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
json_response(['ok' => true, 'page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => (int) ceil($total / $limit), 'prompt_type' => $type, 'prompt_selection' => $type === 'facility' ? 'per_facility_category_with_default_fallback' : 'default', 'ordering' => 'major_cities_first_then_remaining', 'priority_cities' => medical_directory_major_city_priority_labels(), 'items' => $items]);
