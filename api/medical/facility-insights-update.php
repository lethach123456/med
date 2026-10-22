<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../medical_facility_insights_api.php';

header('Cache-Control: private, no-store');
header('Vary: Origin');
header('X-MedReview-Facility-Insights-Contract: 1');
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if (in_array($origin, ['https://chatgpt.com', 'https://gemini.google.com', 'https://grok.com', 'https://x.com', 'https://www.x.com', 'https://claude.ai'], true)
    || preg_match('/^chrome-extension:\/\/[a-p]{32}$/D', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Medical-Api-Key');
    header('Access-Control-Max-Age: 600');
}
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'POST') {
    header('Allow: POST, OPTIONS');
    json_response(['ok' => false, 'message' => 'Chỉ nhận POST JSON.', 'reason' => 'method_not_allowed'], 405);
}
try {
    medical_api_auth();
} catch (Throwable $error) {
    error_log('Facility insights authentication unavailable (' . get_class($error) . ').');
    json_response(['ok' => false, 'message' => 'Chưa thể xác thực API lúc này.', 'reason' => 'authentication_unavailable'], 503);
}
if (!preg_match('~^application/(?:[a-z0-9.+-]+\+)?json(?:\s*;|$)~i', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))) {
    json_response(['ok' => false, 'message' => 'Cần Content-Type: application/json.', 'reason' => 'unsupported_media_type'], 415);
}
try {
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > MEDICAL_FACILITY_INSIGHTS_API_MAX_BODY_BYTES) {
        throw new MedicalFacilityInsightsApiException('JSON vượt giới hạn 2 MiB.', 413, 'body_too_large');
    }
    $stream = fopen('php://input', 'rb');
    if ($stream === false) throw new MedicalFacilityInsightsApiException('Không đọc được body JSON.', 400, 'invalid_json');
    try { $raw = stream_get_contents($stream, MEDICAL_FACILITY_INSIGHTS_API_MAX_BODY_BYTES + 1); }
    finally { fclose($stream); }
    $body = medical_facility_insights_api_parse_body(is_string($raw) ? $raw : '');
    medical_facility_insights_api_normalize($body); // Validate before opening the write connection.
    json_response(medical_facility_insights_api_update(db(), $body));
} catch (MedicalFacilityInsightsApiException $error) {
    json_response(['ok' => false, 'message' => $error->getMessage(), 'reason' => $error->reason], $error->status);
} catch (Throwable $error) {
    error_log('Facility insights POST failed (' . get_class($error) . ').');
    json_response(['ok' => false, 'message' => 'Không thể lưu phân tích cơ sở lúc này; vui lòng thử lại.', 'reason' => 'save_failed'], 500);
}
