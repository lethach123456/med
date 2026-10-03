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
if ($filterType !== null && !in_array($filterType, ['facility', 'doctor', 'mixed'], true)) json_response(['ok' => false, 'message' => 'entity_type phải là facility, doctor hoặc mixed.'], 422);
// Existing Chrome clients parse only `facilities`; doctor queues are explicitly opt-in.
if ($filterType === null && !defined('MEDICAL_TOPLIST_QUEUE_ALL')) $filterType = 'facility';
$where = "t.language_code='vi' AND ((t.entity_type='facility' AND NOT EXISTS (SELECT 1 FROM medical_toplist_facilities tf WHERE tf.toplist_id=t.id))
    OR (t.entity_type='doctor' AND NOT EXISTS (SELECT 1 FROM medical_toplist_doctors td WHERE td.toplist_id=t.id))
    OR (t.entity_type='mixed' AND NOT EXISTS (SELECT 1 FROM medical_toplist_facilities tf WHERE tf.toplist_id=t.id) AND NOT EXISTS (SELECT 1 FROM medical_toplist_doctors td WHERE td.toplist_id=t.id)))";
if ($filterType !== null) $where .= " AND t.entity_type='{$filterType}'";
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

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($items as &$item) {
    $promptRow = toplist_directory_resolve_prompt($pdo, $item);
    $item['prompt_type'] = 'toplist';
    $item['prompt_label'] = (string) $promptRow['label'];
    $item['prompt_key_used'] = $promptRow['prompt_key_used'];
    $item['prompt_source'] = $promptRow['source'];
    $item['output_template'] = toplist_directory_output_template($item);
    $item['receive_endpoint'] = '/api/medical/toplist-members-update.php';
    $item['prompt'] = toplist_directory_research_prompt((string) $promptRow['template'], $item, $item['entity_type'] === 'doctor');
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
