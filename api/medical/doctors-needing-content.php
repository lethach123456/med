<?php
declare(strict_types=1);
require_once __DIR__ . '/_doctor.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, OPTIONS'); json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ GET.'], 405);
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));
$where = medical_doctor_needs_content_sql('d'); $params = [];
$ids = [];
if (isset($_GET['ids'])) {
    if (!is_string($_GET['ids'])) json_response(['ok' => false, 'message' => 'ids phải là danh sách ID phân cách bởi dấu phẩy.'], 422);
    foreach (explode(',', $_GET['ids']) as $id) {
        if (!ctype_digit(trim($id)) || (int) $id <= 0) json_response(['ok' => false, 'message' => 'ids chứa ID không hợp lệ.'], 422);
        $ids[(int) $id] = (int) $id;
    }
    if (count($ids) > 100) json_response(['ok' => false, 'message' => 'Tối đa 100 ID.'], 422);
    $placeholders = [];
    foreach (array_values($ids) as $index => $id) { $key = ':id_' . $index; $placeholders[] = $key; $params[$key] = $id; }
    $where .= ' AND d.id IN (' . implode(',', $placeholders) . ')';
}
try {
    $count = $pdo->prepare("SELECT COUNT(*) FROM medical_doctors d WHERE {$where}");
    $count->execute($params); $total = (int) $count->fetchColumn();
    $priority = str_replace('COALESCE(city', 'COALESCE(d.city', medical_directory_major_city_priority_sql());
    $stmt = $pdo->prepare("SELECT d.* FROM medical_doctors d WHERE {$where} ORDER BY {$priority}, d.id ASC LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $id) $stmt->bindValue($key, $id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
    $stmt->execute(); $items = [];
    $resolved = medical_directory_resolve_ai_prompt($pdo, 'doctor');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $source = $row;
        // Provide a known facility ID as a matching hint, never guess from a name.
        if (!empty($row['facility_slug'])) {
            $facility = $pdo->prepare('SELECT id, name, city, address_text, phone_text, website_url FROM medical_facilities WHERE slug=:slug LIMIT 1');
            $facility->execute([':slug' => $row['facility_slug']]);
            $source['primary_facility'] = $facility->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $item = medical_doctor_api_source($row);
        $item['type'] = 'doctor'; $item['prompt_type'] = 'doctor';
        $item['prompt_source'] = $resolved['source']; $item['prompt_label'] = $resolved['label'];
        $item['output_template'] = medical_doctor_output_template((int) $row['id'], $row['name']);
        $item['prompt'] = medical_doctor_research_prompt((string) ($resolved['template'] ?: medical_doctor_default_prompt()), $source);
        $items[] = $item;
    }
    json_response(['ok' => true, 'type' => 'doctor', 'contract_version' => 1, 'page' => $page, 'limit' => $limit,
        'total' => $total, 'pages' => (int) ceil($total / $limit), 'checked_ids' => array_values($ids),
        'eligibility' => 'published_vi_with_empty_content_and_no_completed_research',
        'claim_required' => true, 'receive_endpoint' => '/api/medical/doctor-content-update.php', 'items' => $items]);
} catch (Throwable $e) {
    error_log('Doctor research queue: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Không thể lấy danh sách bác sĩ cần viết.'], 500);
}
