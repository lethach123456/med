<?php
declare(strict_types=1);
require_once __DIR__ . '/_handbook.php';
$pdo = handbook_api_bootstrap();
try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        header('Allow: POST, OPTIONS');
        throw new HandbookApiException('Chỉ hỗ trợ POST và OPTIONS.', 405);
    }
    json_response(handbook_api_save($pdo, handbook_api_read_body()));
} catch (Throwable $error) { handbook_api_error($error); }
