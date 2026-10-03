<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_directory.php';
require_once __DIR__ . '/../../../toplist_directory.php';

admin_require_login();

$pdo = db();

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(5, (int) ($_GET['limit'] ?? 20)));
$query = trim((string) ($_GET['q'] ?? ''));
$where = '';
$params = [];
if ($query !== '') {
    $where = ' WHERE t.title LIKE :query_title OR t.slug LIKE :query_slug';
    $likeQuery = '%' . $query . '%';
    $params = [':query_title' => $likeQuery, ':query_slug' => $likeQuery];
}
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM medical_toplists t' . $where);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare(
    'SELECT t.id, t.title, t.slug, t.entity_type, t.status, t.updated_at,
        (SELECT COUNT(*) FROM medical_toplist_facilities tf WHERE tf.toplist_id=t.id) AS facility_count,
        (SELECT COUNT(*) FROM medical_toplist_doctors td WHERE td.toplist_id=t.id) AS doctor_count
     FROM medical_toplists t
     ' . $where . '
     ORDER BY t.updated_at DESC, t.id DESC
     LIMIT :limit OFFSET :offset'
);
foreach ($params as $key => $value) $stmt->bindValue($key, $value, PDO::PARAM_STR);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

json_response([
    'ok' => true,
    'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    'paging' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => $totalPages,
    ],
    'query' => $query,
]);
