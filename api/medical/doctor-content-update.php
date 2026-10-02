<?php
declare(strict_types=1);
require_once __DIR__ . '/_doctor.php';
require_once __DIR__ . '/../../medical_search_cache.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST, OPTIONS'); json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ POST.'], 405);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8 * 1024 * 1024) json_response(['ok' => false, 'message' => 'Body tối đa 8MB.'], 413);
$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 8 * 1024 * 1024) json_response(['ok' => false, 'message' => 'Body tối đa 8MB.'], 413);
try { $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
catch (JsonException $e) { json_response(['ok' => false, 'message' => 'Body không phải JSON hợp lệ.'], 400); }
if (!is_array($body) || array_is_list($body)) json_response(['ok' => false, 'message' => 'Body phải là JSON object.'], 400);
// Accept the utility's existing {content:"JSON",writer_claim_token:"..."} envelope too.
if (isset($body['items'])) $items = $body['items'];
elseif (isset($body['content']) && is_string($body['content']) && str_starts_with(ltrim($body['content']), '{')) {
    try { $decoded = json_decode($body['content'], true, 64, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { json_response(['ok' => false, 'message' => 'content chứa JSON không hợp lệ.'], 422); }
    $decoded['writer_claim_token'] = $body['writer_claim_token'] ?? '';
    $items = [$decoded];
} else $items = [$body];
if (!is_array($items) || !array_is_list($items) || $items === [] || count($items) > 25) {
    json_response(['ok' => false, 'message' => 'items cần từ 1 đến 25 JSON object.'], 422);
}
$results = []; $errors = [];
foreach ($items as $index => $item) {
    try {
        if (!is_array($item) || array_is_list($item)) throw new InvalidArgumentException('Item phải là JSON object.');
        $result = medical_doctor_save_research($pdo, $item);
        $results[] = $result;
        // Enqueue owned copies asynchronously; never block saving on remote image downloads.
        try {
            require_once __DIR__ . '/../../medical_media_worker.php';
            medical_media_jobs_enqueue_entity_urls($pdo, 'doctor', $result['id'], [
                'image_url' => [(string) ($item['image_url'] ?? '')],
                'gallery_json' => medical_directory_gallery_urls($item['gallery_json'] ?? [])], false);
        } catch (Throwable $mediaError) { error_log('Doctor images queue: ' . $mediaError->getMessage()); }
    } catch (MedicalDoctorConflict $e) {
        $errors[] = ['index' => $index, 'id' => $item['id'] ?? null, 'status' => 409, 'reason' => $e->getMessage(), 'message' => 'Bài đã được xử lý hoặc khóa bài đã mất/hết hạn.'];
    } catch (InvalidArgumentException $e) {
        $errors[] = ['index' => $index, 'id' => is_array($item) ? ($item['id'] ?? null) : null, 'status' => 422, 'message' => $e->getMessage()];
    } catch (Throwable $e) {
        error_log('Doctor JSON save: ' . $e->getMessage());
        $errors[] = ['index' => $index, 'status' => 500, 'message' => 'Không thể lưu hồ sơ bác sĩ.'];
    }
}
if ($results !== []) medical_search_cache_invalidate();
$status = $errors === [] ? 200 : ($results !== [] ? 207 : max(array_column($errors, 'status')));
json_response(['ok' => $errors === [], 'type' => 'doctor', 'updated_count' => count($results),
    'updated' => $results, 'results' => $results, 'errors' => $errors], $status);
