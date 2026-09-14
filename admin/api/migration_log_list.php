<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

admin_require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    $stmt = $pdo->query("SELECT id, run_at, notes FROM migration_log ORDER BY run_at DESC, id DESC LIMIT 50");
    $rows = $stmt->fetchAll();
    $out = array_map(function($r){
        return [
            'id' => (int) ($r['id'] ?? 0),
            'run_at' => (string) ($r['run_at'] ?? ''),
            'notes' => (string) ($r['notes'] ?? ''),
        ];
    }, $rows ?: []);
    echo json_encode(['ok' => true, 'logs' => $out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

