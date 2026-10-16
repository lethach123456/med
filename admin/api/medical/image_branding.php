<?php
declare(strict_types=1);
require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_image_branding.php';

admin_require_admin_json();
header('Cache-Control: private, no-store, max-age=0');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) { header('Allow: GET, POST'); json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ GET hoặc POST.'], 405); }
$body = [];
if ($method === 'POST') {
    admin_require_csrf_json();
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if (!is_string($raw) || strlen($raw) > 8192) json_response(['ok' => false, 'message' => 'Yêu cầu không hợp lệ.'], 422);
    try { $body = json_decode($raw, true, 8, JSON_THROW_ON_ERROR); }
    catch (JsonException) { json_response(['ok' => false, 'message' => 'Body cần JSON hợp lệ.'], 422); }
    if (!is_array($body) || array_is_list($body)) json_response(['ok' => false, 'message' => 'Body cần một JSON object.'], 422);
}
$type = $body['type'] ?? $_GET['type'] ?? 'all';
$status = $body['status'] ?? $_GET['status'] ?? 'all';
$page = $body['page'] ?? $_GET['page'] ?? 1;
if (!in_array($type, ['all', 'facility', 'doctor'], true) || !in_array($status, ['all', 'pending', 'processing', 'done', 'failed'], true)
    || (!is_int($page) && !is_string($page)) || !ctype_digit((string) $page) || (int) $page < 1 || (int) $page > 1000000) {
    json_response(['ok' => false, 'message' => 'Bộ lọc hoặc số trang không hợp lệ.'], 422);
}
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
try {
    $message = null;
    if ($method === 'POST') {
        $action = $body['action'] ?? '';
        if (!in_array($action, ['scan_start', 'scan_step', 'run', 'retry_failed'], true)) json_response(['ok' => false, 'message' => 'Thao tác không hợp lệ.'], 422);
        if ($action === 'run' && ($body['overwrite_confirmed'] ?? null) !== true) {
            json_response(['ok' => false, 'message' => 'Chế độ mới ghi đè ảnh gốc, không lưu bản sao. Tải lại trang và xác nhận ghi đè trước khi chạy.'], 422);
        }
        $result = medical_image_brand_operation(static function () use ($action, $type): array {
            if ($action === 'scan_start') return ['scan' => medical_image_brand_scan_start(), 'message' => 'Bắt đầu quét ảnh. Hồ sơ và ảnh gốc chưa bị thay đổi.'];
            if ($action === 'scan_step') return ['scan' => medical_image_brand_scan_step(db())];
            if ($action === 'retry_failed') return ['message' => 'Đã đưa ' . medical_image_brand_retry_failed($type) . ' ảnh lỗi trở lại hàng chờ.'];
            return medical_image_brand_run(db(), $type);
        });
        $message = $result['message'] ?? null;
    }
    $result = medical_image_brand_status($type, $status, (int) $page);
    $stateDir = medical_image_brand_store_dir();
    $parent = is_dir($stateDir) ? $stateDir : dirname($stateDir);
    $library = __DIR__ . '/../../../uploads/library';
    $result['capabilities'] = ['gd' => extension_loaded('gd'), 'logo' => is_file(__DIR__ . '/../../../assets/brand/medreview-wordmark.png'),
        'writable' => is_writable($parent) && (is_dir($library) ? is_writable($library) : is_writable(dirname($library)))];
    if ($message !== null) $result['message'] = $message;
    json_response($result);
} catch (Throwable $error) {
    error_log('Admin image branding (' . get_class($error) . '): ' . $error->getMessage());
    json_response(['ok' => false, 'message' => $error->getCode() === 409 ? $error->getMessage()
        : 'Không hoàn tất thao tác gắn logo. Có thể ảnh đã được ghi đè; làm mới để kiểm tra trạng thái trước khi tiếp tục. Kiểm tra quyền ghi storage/uploads và log máy chủ.'], $error->getCode() === 409 ? 409 : 500);
}
