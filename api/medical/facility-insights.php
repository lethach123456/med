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
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Medical-Api-Key');
    header('Access-Control-Max-Age: 600');
}
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') { http_response_code(204); exit; }
if ($method !== 'GET') {
    header('Allow: GET, OPTIONS');
    json_response(['ok' => false, 'message' => 'Chỉ nhận GET với id cơ sở.', 'reason' => 'method_not_allowed'], 405);
}
try {
    medical_api_auth();
} catch (Throwable $error) {
    error_log('Facility insights authentication unavailable (' . get_class($error) . ').');
    json_response(['ok' => false, 'message' => 'Chưa thể xác thực API lúc này.', 'reason' => 'authentication_unavailable'], 503);
}
try {
    $id = medical_facility_insights_api_id($_GET['id'] ?? null);
    json_response(medical_facility_insights_api_read(db(), $id));
} catch (MedicalFacilityInsightsApiException $error) {
    json_response(['ok' => false, 'message' => $error->getMessage(), 'reason' => $error->reason], $error->status);
} catch (Throwable $error) {
    error_log('Facility insights GET failed (' . get_class($error) . ').');
    json_response(['ok' => false, 'message' => 'Không thể đọc phân tích cơ sở lúc này.', 'reason' => 'read_failed'], 500);
}
