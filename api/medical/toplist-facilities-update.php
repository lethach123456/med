<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../toplist_directory.php';
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
medical_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ POST.'], 405);
$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 8 * 1024 * 1024) json_response(['ok' => false, 'message' => 'Body tối đa 8MB.'], 413);
try { $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
catch (JsonException $e) { json_response(['ok' => false, 'message' => 'JSON không hợp lệ.'], 400); }
if (!is_array($body)) json_response(['ok' => false, 'message' => 'Body cần object hoặc danh sách object.'], 422);
// Preserve the existing utility envelope {content:"serialized JSON"}.
if (isset($body['content']) && is_string($body['content']) && !isset($body['toplist_id'])) {
    try { $body = json_decode($body['content'], true, 64, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { json_response(['ok' => false, 'message' => 'content không chứa JSON hợp lệ.'], 422); }
}
if (!is_array($body)) json_response(['ok' => false, 'message' => 'Body cần object hoặc danh sách object.'], 422);
$items = $body['items'] ?? (array_is_list($body) ? $body : [$body]);
if (!is_array($items) || !array_is_list($items) || $items === [] || count($items) > 25) json_response(['ok' => false, 'message' => 'items cần 1–25 object.'], 422);
$pdo = db();
$updated = []; $createdFacilities = []; $createdDoctors = []; $errors = []; $results = [];
foreach ($items as $index => $item) {
    try {
        if (!is_array($item) || array_is_list($item)) throw new InvalidArgumentException('Item phải là JSON object.');
        $id = filter_var($item['toplist_id'] ?? $item['toplistId'] ?? $item['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) throw new InvalidArgumentException('Cần toplist_id nguyên dương.');
        $pdo->beginTransaction();
        $lookup = $pdo->prepare('SELECT id,entity_type FROM medical_toplists WHERE id=:id FOR UPDATE');
        $lookup->execute([':id' => $id]); $row = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('Không tìm thấy Toplist #' . $id . '.');
        $type = toplist_directory_entity_type($item, $row['entity_type']);
        if ($type !== $row['entity_type']) throw new InvalidArgumentException('Loại danh sách không khớp Toplist. Đổi đối tượng xếp hạng trong trang chỉnh sửa trước.');
        if (defined('MEDICAL_TOPLIST_EXPECTED_TYPE') && $type !== MEDICAL_TOPLIST_EXPECTED_TYPE) throw new InvalidArgumentException('Endpoint này chỉ nhận Toplist bác sĩ.');
        $members = toplist_directory_payload_members($item, $type);
        if ($members === []) throw new InvalidArgumentException('Cần danh sách hồ sơ không rỗng.');
        $saved = toplist_directory_import_members($pdo, (int) $id, $type, $members);
        $pdo->commit();
        $updated[] = (int) $id;
        $createdDoctors = array_merge($createdDoctors, $saved['created_doctor_ids']);
        $createdFacilities = array_merge($createdFacilities, $saved['created_facility_ids']);
        $results[] = ['index' => $index, 'toplist_id' => (int) $id, 'entity_type' => $type, 'member_ids' => $saved['ids'], 'members' => $saved['members']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $status = $e instanceof InvalidArgumentException ? 422 : 500;
        if ($status === 500) error_log('Toplist members save: ' . $e->getMessage());
        $errors[] = ['index' => $index, 'toplist_id' => is_array($item) ? ($item['toplist_id'] ?? $item['id'] ?? null) : null,
            'status' => $status, 'message' => $status === 422 ? $e->getMessage() : 'Không thể lưu danh sách Toplist; item này chưa được cập nhật.'];
    }
}
if ($updated !== []) medical_search_cache_invalidate();
json_response(['ok' => $errors === [], 'updated_toplist_ids' => $updated, 'updated_count' => count($updated),
    'created_facility_ids' => $createdFacilities, 'created_facility_count' => count($createdFacilities),
    'created_doctor_ids' => $createdDoctors, 'created_doctor_count' => count($createdDoctors), 'results' => $results, 'errors' => $errors],
    $errors === [] ? 200 : ($updated !== [] ? 207 : max(array_column($errors, 'status'))));
