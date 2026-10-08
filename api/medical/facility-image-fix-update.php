<?php
declare(strict_types=1);
require_once __DIR__ . '/_image-fix.php';
require_once __DIR__ . '/../../medical_media_worker.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST, OPTIONS'); json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ POST.'], 405);
}
$raw = file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024 + 1);
if (!is_string($raw) || strlen($raw) > 2 * 1024 * 1024) json_response(['ok' => false, 'message' => 'JSON vượt giới hạn 2MB.'], 413);
$body = json_decode($raw, true);
if (!is_array($body) || array_is_list($body)) json_response(['ok' => false, 'message' => 'Body phải là JSON object hợp lệ, không gửi nguyên block Markdown.'], 422);
$items = array_key_exists('items', $body) ? $body['items'] : [$body];
if (!is_array($items) || !array_is_list($items) || $items === [] || count($items) > 10) json_response(['ok' => false, 'message' => 'items phải có từ 1 đến 10 object.'], 422);
// Downloads are synchronous now. This does not override a proxy timeout;
// callers should submit one facility per request and retry the same receipt.
@set_time_limit(300);
$updated = []; $errors = []; $warnings = [];
foreach ($items as $index => $item) {
    try {
        if (!is_array($item) || array_is_list($item)) throw new InvalidArgumentException('Mỗi item phải là một JSON object.');
        $result = medical_facility_image_fix_save($pdo, $item);
        $sources = $result['queue_sources'] ?? []; unset($result['queue_sources']);
        $result['images_queued'] = 0;
        $import = medical_facility_image_fix_import_now($pdo, (int) $result['id'], $sources);
        $result['images_imported'] = $import['imported'];
        $result['images_failed'] = $import['failed'];
        $result['image_results'] = $import['items'];
        $result['image_processing'] = $import['failed'] > 0 ? 'partial' : 'completed';
        if ($import['failed'] > 0) $warnings[] = ['index' => $index, 'id' => $result['id'], 'message' => 'Đã lưu JSON nhưng một số ảnh chưa tải được; giữ URL nguồn và gửi lại cùng payload để thử lại. Không đưa vào hàng đợi worker.'];
        $current = $pdo->prepare('SELECT * FROM medical_facilities WHERE id=:id');
        $current->execute([':id' => $result['id']]);
        $row = $current->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $result['image_url'] = $row['image_url'];
            $result['images_revision'] = medical_facility_image_fix_revision($row);
            $result['gallery_count'] = count(medical_directory_gallery_urls($row['gallery_json'] ?? '[]'));
        }
        $updated[] = $result;
    } catch (MedicalFacilityImageFixConflict $e) {
        $errors[] = ['index' => $index, 'id' => (int) ($item['id'] ?? 0), 'reason' => $e->reason, 'message' => $e->getMessage(), 'status' => 409];
    } catch (InvalidArgumentException $e) {
        $errors[] = ['index' => $index, 'id' => is_array($item) ? (int) ($item['id'] ?? 0) : 0, 'reason' => 'invalid_payload', 'message' => $e->getMessage(), 'status' => 422];
    } catch (Throwable $e) {
        error_log('Facility image-fix receive: ' . $e->getMessage());
        $errors[] = ['index' => $index, 'reason' => 'server_error', 'message' => 'Không lưu được kết quả Fix ảnh lúc này.', 'status' => 500];
    }
}
if ($updated !== []) medical_search_cache_invalidate();
$status = $errors === [] ? 200 : ($updated !== [] ? 207 : (count($items) === 1 ? $errors[0]['status'] : 422));
json_response(['ok' => $errors === [], 'updated_count' => count($updated), 'updated' => $updated, 'errors' => $errors,
    'warnings' => $warnings, 'image_processing' => $updated === [] ? 'not_started' : ($warnings === [] ? 'completed' : 'partial'),
    'note' => 'Ảnh được kiểm tra, tải và lưu ngay trong request, không chờ worker. Kiểm tra images_failed và image_results; ảnh tải lỗi giữ URL nguồn để thử lại.'], $status);
