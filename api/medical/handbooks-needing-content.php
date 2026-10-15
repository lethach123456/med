<?php
declare(strict_types=1);
require_once __DIR__ . '/_handbook.php';
$pdo = handbook_api_bootstrap();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
try {
    if ($method === 'GET') json_response(handbook_api_list($pdo, $_GET));
    if ($method === 'POST') json_response(handbook_api_lease($pdo, handbook_api_read_body(64 * 1024)));
    header('Allow: GET, POST, OPTIONS');
    throw new HandbookApiException('Chỉ hỗ trợ GET, POST và OPTIONS.', 405);
} catch (Throwable $error) { handbook_api_error($error); }
