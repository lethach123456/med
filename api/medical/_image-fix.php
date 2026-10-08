<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Vary: Origin');
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if (in_array($origin, ['https://chatgpt.com', 'https://gemini.google.com', 'https://grok.com', 'https://x.com', 'https://www.x.com'], true)
    || preg_match('/^chrome-extension:\/\/[a-p]{32}$/', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: X-Medical-Api-Key, Content-Type');
    header('Access-Control-Max-Age: 600');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
medical_api_auth();
try { $pdo = db(); }
catch (Throwable $e) {
    error_log('Facility image-fix DB connection: ' . $e->getMessage());
    json_response(['ok' => false, 'reason' => 'service_unavailable', 'message' => 'Không thể kết nối dữ liệu Fix ảnh lúc này; hãy thử lại sau.'], 503);
}
try { medical_facility_image_fix_require_schema($pdo); }
catch (Throwable $e) {
    json_response(['ok' => false, 'reason' => 'schema_not_ready', 'message' => 'Chạy riêng php scripts/migrate_facility_image_fix.php trước khi sử dụng Fix ảnh.'], 503);
}
