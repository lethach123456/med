<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../toplist_directory.php';

medical_api_auth();
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ GET.'], 405);

$pdo = db();
medical_directory_ensure_tables($pdo);
toplist_directory_ensure_tables($pdo);

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

$filterType = $_GET['entity_type'] ?? null;
if ($filterType !== null && !in_array($filterType, ['facility', 'doctor'], true)) json_response(['ok' => false, 'message' => 'entity_type phải là facility hoặc doctor.'], 422);
// Existing Chrome clients parse only `facilities`; doctor queues are explicitly opt-in.
if ($filterType === null && !defined('MEDICAL_TOPLIST_QUEUE_ALL')) $filterType = 'facility';
$where = "t.language_code='vi' AND ((t.entity_type='facility' AND NOT EXISTS (SELECT 1 FROM medical_toplist_facilities tf WHERE tf.toplist_id=t.id))
    OR (t.entity_type='doctor' AND NOT EXISTS (SELECT 1 FROM medical_toplist_doctors td WHERE td.toplist_id=t.id)))";
if ($filterType !== null) $where .= $filterType === 'doctor' ? " AND t.entity_type='doctor'" : " AND t.entity_type='facility'";
$total = (int) $pdo->query("SELECT COUNT(*) FROM medical_toplists t WHERE {$where}")->fetchColumn();
$stmt = $pdo->prepare(
    "SELECT t.id, t.slug, t.title, t.entity_type, t.excerpt, t.content, t.featured_image_url, t.status, t.updated_at
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
    $item['receive_endpoint'] = '/api/medical/toplist-members-update.php';
    $item['prompt'] = toplist_directory_research_prompt((string) $promptRow['template'], $item);
}
unset($item);

json_response([
    'ok' => true,
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'pages' => (int) ceil($total / $limit),
    'prompt_type' => 'toplist',
    'entity_type' => $filterType ?? 'all',
    'items' => $items,
]);
