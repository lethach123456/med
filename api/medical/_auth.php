<?php
declare(strict_types=1);
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../medical_directory.php';
require_once __DIR__ . '/../../medical_media_library.php';
ini_set('display_errors', '0');
register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (is_array($error) && in_array($error['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) && !headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'API server error: ' . (string) ($error['message'] ?? 'Unknown error')], JSON_UNESCAPED_UNICODE);
    }
});

if (!function_exists('json_response')) {
    function json_response(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('read_json_body')) {
    function read_json_body(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode(is_string($raw) ? $raw : '', true);
        return is_array($decoded) ? $decoded : [];
    }
}

function medical_api_auth(): void
{
    $expected = trim((string) (getenv('MEDICAL_CONTENT_API_KEY') ?: site_setting('medical_content_api_key', 'them')));
    $provided = trim((string) ($_SERVER['HTTP_X_MEDICAL_API_KEY'] ?? ''));
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        json_response(['ok' => false, 'message' => 'API key không hợp lệ.'], 401);
    }
}

function medical_api_json($value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '';
}
