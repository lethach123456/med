<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    $rows = $pdo->query('SHOW TABLES')->fetchAll();
    $tables = [];
    foreach ($rows as $r) {
        // SHOW TABLES returns a row with a single column (table name),
        // but the key name varies by DB name. Just take the first value.
        $tables[] = (string) array_values($r)[0];
    }
    sort($tables);
    $expected = [
        'users',
        'site_settings',
        'categories',
        'posts',
        'product_categories',
        'products',
        'project_categories',
        'projects',
        'migration_log',
        'visits',
        'front_editor_history',
    ];
    $status = [];
    $tableSet = array_fill_keys($tables, true);
    foreach ($expected as $t) {
        $status[] = ['table' => $t, 'exists' => isset($tableSet[$t])];
    }
    echo json_encode(['ok' => true, 'tables' => $tables, 'status' => $status], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
