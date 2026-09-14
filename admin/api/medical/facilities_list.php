<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_directory.php';

admin_require_login();

$pdo = db();
medical_directory_ensure_tables($pdo);

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(5, (int) ($_GET['limit'] ?? 20)));
$query = trim((string) ($_GET['q'] ?? ''));
$where = '';
$params = [];
if ($query !== '') {
    $where = ' WHERE name LIKE :query_name OR slug LIKE :query_slug OR category LIKE :query_category OR city LIKE :query_city OR address_text LIKE :query_address';
    $likeQuery = '%' . $query . '%';
    $params = [
        ':query_name' => $likeQuery,
        ':query_slug' => $likeQuery,
        ':query_category' => $likeQuery,
        ':query_city' => $likeQuery,
        ':query_address' => $likeQuery,
    ];
}
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM medical_facilities' . $where);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare(
    'SELECT id, slug, name, category, city, rating, reviews_count, status, display_order, updated_at
     FROM medical_facilities
     ' . $where . '
     ORDER BY display_order ASC, id DESC
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
