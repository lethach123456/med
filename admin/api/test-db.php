<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = db();
    $version = $pdo->query('select version() as v')->fetch();
    json_response([
        'ok' => true,
        'message' => 'Kết nối CSDL OK.',
        'mysql_version' => $version['v'] ?? null,
        'db_name' => getenv('DB_NAME') ?: 'at',
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'message' => 'Kết nối CSDL thất bại: ' . $e->getMessage(),
    ], 500);
}

