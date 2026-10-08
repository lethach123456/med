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
$updated = []; $errors = []; $warnings = [];
foreach ($items as $index => $item) {
    try {
        if (!is_array($item) || array_is_list($item)) throw new InvalidArgumentException('Mỗi item phải là một JSON object.');
        $result = medical_facility_image_fix_save($pdo, $item);
        $sources = $result['queue_sources'] ?? []; unset($result['queue_sources']);
        $result['images_queued'] = 0;
        if ($sources !== []) {
            try {
                $queued = medical_media_jobs_enqueue_entity_urls($pdo, 'facility', $result['id'], $sources, false);
                $result['images_queued'] = (int) $queued['queued'];
            } catch (Throwable $e) {
                error_log('Image-fix queue import: ' . $e->getMessage());
                $warnings[] = ['index' => $index, 'id' => $result['id'], 'message' => 'Đã lưu kết quả; hàng đợi tải ảnh chưa sẵn sàng. Chạy migration/worker ảnh để tải các URL về thư viện.'];
            }
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
    'warnings' => $warnings, 'image_processing' => 'queued',
    'note' => 'Chỉ cập nhật trường ảnh và nhật ký Fix ảnh. Ảnh ngoài được worker kiểm tra/tải về thư viện; không coi queued là đã tải thành công.'], $status);
