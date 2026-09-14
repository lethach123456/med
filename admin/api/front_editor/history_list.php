<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = db();
    $body = read_json_body();
    $page = isset($body['page']) ? (int) $body['page'] : 1;
    $limit = isset($body['limit']) ? (int) $body['limit'] : 10;
    $page = max(1, $page);
    $limit = max(1, min(50, $limit));
    $offset = ($page - 1) * $limit;

    $total = (int) ($pdo->query("SELECT COUNT(*) FROM front_editor_history")->fetchColumn() ?: 0);

    $stmt = $pdo->prepare("SELECT id, page_key, element_id, admin_user_id, action, old_text, new_text, created_at
                           FROM front_editor_history
                           ORDER BY id DESC
                           LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows ?: [] as $r) {
        $out[] = [
            'id' => (int) ($r['id'] ?? 0),
            'page_key' => (string) ($r['page_key'] ?? ''),
            'element_id' => (string) ($r['element_id'] ?? ''),
            'admin_user_id' => $r['admin_user_id'] === null ? null : (int) $r['admin_user_id'],
            'action' => (string) ($r['action'] ?? ''),
            'old_text' => (string) ($r['old_text'] ?? ''),
            'new_text' => (string) ($r['new_text'] ?? ''),
            'created_at' => (string) ($r['created_at'] ?? ''),
        ];
    }
    json_response([
        'ok' => true,
        'items' => $out,
        'paging' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
        ],
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
