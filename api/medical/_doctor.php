<?php
declare(strict_types=1);
require_once __DIR__ . '/_auth.php';
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Vary: Origin');
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if (in_array($origin, ['https://chatgpt.com', 'https://grok.com', 'https://x.com', 'https://www.x.com',
    'https://gemini.google.com', 'https://claude.ai'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: X-Medical-Api-Key, Content-Type');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
medical_api_auth();
try { $pdo = db(); medical_doctor_require_schema($pdo); }
catch (Throwable $e) {
    error_log('Doctor schema unavailable: ' . $e->getMessage());
    json_response(['ok' => false, 'message' => 'Chưa sẵn sàng schema bác sĩ. Chạy scripts/migrate_doctor_content.php.'], 503);
}

/** Authenticated research response, without a lease token or private reviewer metadata. */
function medical_doctor_api_source(array $row): array
{
    $claim = medical_directory_json_decode($row['ai_writer_claim_json'] ?? '');
    $active = (int) ($claim['expires_at'] ?? 0) > time();
    unset($row['ai_writer_claim_json'], $row['reviewed_by']);
    foreach ($row as $key => $value) {
        if (($key === 'full_json' || str_ends_with($key, '_json')) && is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) $row[$key] = $decoded;
        }
    }
    $row['writer_claimed'] = $active;
    $row['writer_claim'] = $active ? array_intersect_key($claim, array_flip(['provider', 'model', 'task',
        'instance_id', 'instance_label', 'account_label', 'worker_id', 'claimed_at', 'heartbeat_at', 'expires_at'])) : null;
    $row['needs_content'] = medical_doctor_needs_content($row);
    return $row;
}
