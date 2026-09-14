<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

function content_clone_target_slug(string $slug, string $sourceLanguage, string $targetLanguage): string
{
    $slug = trim($slug);
    if ($slug === '') {
        return $targetLanguage === 'en' ? 'ban-sao-en' : 'ban-sao';
    }

    $base = $slug;
    if ($sourceLanguage === 'en') {
        $base = preg_replace('/-en$/', '', $slug) ?? $slug;
    }

    if ($targetLanguage === 'en') {
        return preg_match('/-en$/', $base) ? $base : ($base . '-en');
    }

    return preg_replace('/-en$/', '', $base) ?? $base;
}

$body = read_json_body();
$type = isset($body['type']) ? (string) $body['type'] : '';
$id = isset($body['id']) ? (int) $body['id'] : 0;
$targetLanguage = normalize_content_language((string) ($body['target_language'] ?? 'vi'));

$map = [
    'post' => [
        'table' => 'posts',
        'label' => 'Trang',
        'edit_url' => '/admin/content_post_edit.php?id=%d',
        'columns' => ['category_id', 'title', 'slug', 'language', 'featured_image_url', 'template', 'excerpt', 'content', 'seo_title', 'seo_description', 'seo_keywords', 'status'],
    ],
    'category' => [
        'table' => 'categories',
        'label' => 'Chuyên mục',
        'edit_url' => '/admin/content_category_edit.php?id=%d',
        'columns' => ['name', 'slug', 'language', 'description', 'seo_title', 'seo_description'],
    ],
    'product_category' => [
        'table' => 'product_categories',
        'label' => 'Chuyên mục sản phẩm',
        'edit_url' => '/admin/content_product_category_edit.php?id=%d',
        'columns' => ['name', 'slug', 'language', 'description', 'seo_title', 'seo_description'],
    ],
    'project_category' => [
        'table' => 'project_categories',
        'label' => 'Chuyên mục dự án',
        'edit_url' => '/admin/content_project_category_edit.php?id=%d',
        'columns' => ['name', 'slug', 'language', 'description', 'seo_title', 'seo_description'],
    ],
];

if (!isset($map[$type]) || $id <= 0) {
    json_response(['ok' => false, 'message' => 'Tham số không hợp lệ.'], 422);
}

try {
    $pdo = db();
    ensure_content_language_columns($pdo);

    $config = $map[$type];
    $table = $config['table'];
    $columns = $config['columns'];

    $stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$source) {
        json_response(['ok' => false, 'message' => 'Không tìm thấy dữ liệu nguồn.'], 404);
    }

    $sourceLanguage = normalize_content_language((string) ($source['language'] ?? 'vi'));
    $sourceSlug = (string) ($source['slug'] ?? '');

    if ($sourceLanguage === $targetLanguage) {
        json_response([
            'ok' => true,
            'message' => 'Mục này đã ở đúng ngôn ngữ.',
            'id' => (int) $id,
            'edit_url' => sprintf($config['edit_url'], $id),
        ]);
    }

    $preferredSlug = content_clone_target_slug($sourceSlug, $sourceLanguage, $targetLanguage);

    $existing = $pdo->prepare("SELECT id FROM {$table} WHERE slug = :slug AND language = :language LIMIT 1");
    $existing->execute([
        ':slug' => $preferredSlug,
        ':language' => $targetLanguage,
    ]);
    $existingId = (int) ($existing->fetchColumn() ?: 0);
    if ($existingId > 0) {
        json_response([
            'ok' => true,
            'message' => 'Đã có bản ngôn ngữ tương ứng, chuyển sang trang chỉnh sửa.',
            'id' => $existingId,
            'edit_url' => sprintf($config['edit_url'], $existingId),
        ]);
    }

    $insertColumns = [];
    $placeholders = [];
    $params = [];
    foreach ($columns as $column) {
        $insertColumns[] = $column;
        $placeholders[] = ':' . $column;
        if ($column === 'language') {
            $params[':' . $column] = $targetLanguage;
            continue;
        }
        if ($column === 'slug') {
            $params[':' . $column] = unique_slug($pdo, $table, $preferredSlug, null);
            continue;
        }
        $params[':' . $column] = $source[$column] ?? null;
    }

    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $pdo->prepare($sql)->execute($params);
    $newId = (int) $pdo->lastInsertId();

    json_response([
        'ok' => true,
        'message' => 'Đã tạo bản ' . strtoupper($targetLanguage) . ' cho ' . $config['label'] . '.',
        'id' => $newId,
        'edit_url' => sprintf($config['edit_url'], $newId),
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Không thể tạo bản ngôn ngữ: ' . $e->getMessage()], 500);
}
