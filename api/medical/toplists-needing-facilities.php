<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../toplist_directory.php';

medical_api_auth();

$pdo = db();
medical_directory_ensure_tables($pdo);
toplist_directory_ensure_tables($pdo);

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

$where = 'NOT EXISTS (SELECT 1 FROM medical_toplist_facilities tf WHERE tf.toplist_id = t.id)';
$total = (int) $pdo->query("SELECT COUNT(*) FROM medical_toplists t WHERE {$where}")->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT t.id, t.slug, t.title, t.excerpt, t.content, t.featured_image_url, t.status, t.updated_at
     FROM medical_toplists t
     WHERE {$where}
     ORDER BY t.id ASC
     LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$promptStmt = $pdo->prepare('SELECT label, template FROM medical_ai_prompts WHERE prompt_key = :key LIMIT 1');
$promptStmt->execute([':key' => 'toplist']);
$promptRow = $promptStmt->fetch(PDO::FETCH_ASSOC) ?: ['label' => '', 'template' => ''];

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($items as &$item) {
    $item['prompt_type'] = 'toplist';
    $item['prompt_label'] = (string) $promptRow['label'];
    $item['prompt'] = strtr((string) $promptRow['template'], [
        '{{id}}' => (string) $item['id'],
        '{{toplist_id}}' => (string) $item['id'],
        '{{title}}' => (string) $item['title'],
        '{{name}}' => (string) $item['title'],
        '{{excerpt}}' => (string) ($item['excerpt'] ?? ''),
        '{{content}}' => (string) ($item['content'] ?? ''),
    ]);
}
unset($item);

json_response([
    'ok' => true,
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'pages' => (int) ceil($total / $limit),
    'prompt_type' => 'toplist',
    'items' => $items,
]);
