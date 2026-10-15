<?php
declare(strict_types=1);

/** Pure request normalization, shared by the list and its database-free tests. */
function admin_handbook_filters(array $query): array
{
    $scalar = static fn (string $key): string => isset($query[$key]) && is_scalar($query[$key]) ? trim((string) $query[$key]) : '';
    $status = $scalar('status');
    $language = $scalar('language');
    $page = $scalar('page');
    return [
        'q' => mb_substr($scalar('q'), 0, 160),
        'status' => in_array($status, ['draft', 'published'], true) ? $status : '',
        'language' => in_array($language, ['vi', 'en'], true) ? $language : '',
        'page' => preg_match('/^[1-9][0-9]{0,8}$/D', $page) === 1 ? (int) $page : 1,
    ];
}

function admin_handbook_categories(PDO $pdo): array
{
    $rows = $pdo->query("SELECT id, name, slug FROM categories WHERE slug IN ('blog', 'blog-en') ORDER BY id ASC")->fetchAll();
    $categories = [];
    foreach ($rows as $row) {
        $slug = (string) ($row['slug'] ?? '');
        if (!isset($categories[$slug])) $categories[$slug] = $row;
    }
    return $categories;
}

function admin_handbook_category_id(array $categories, string $language): int
{
    return (int) ($categories[$language === 'en' ? 'blog-en' : 'blog']['id'] ?? 0);
}

function admin_handbook_contains_post(array $categories, array $post): bool
{
    $language = strtolower(trim((string) ($post['language'] ?? '')));
    if ($language !== '' && !in_array($language, ['vi', 'en'], true)) return false;
    $categoryId = (int) ($post['category_id'] ?? 0);
    foreach ($categories as $category) {
        if ($categoryId > 0 && $categoryId === (int) ($category['id'] ?? 0)) return true;
    }
    return false;
}

function admin_handbook_page(PDO $pdo, array $filters, int $perPage = 20): array
{
    // Match the public handbook: category-bound articles in either supported language.
    $languageSql = "COALESCE(NULLIF(LOWER(TRIM(p.language)), ''), 'vi')";
    $where = ["c.slug IN ('blog', 'blog-en')", $languageSql . " IN ('vi', 'en')"];
    $params = [];
    if ($filters['q'] !== '') {
        $where[] = "(p.title LIKE :search_title ESCAPE '!' OR p.slug LIKE :search_slug ESCAPE '!' OR p.excerpt LIKE :search_excerpt ESCAPE '!')";
        $pattern = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $params[':search_title'] = $pattern;
        $params[':search_slug'] = $pattern;
        $params[':search_excerpt'] = $pattern;
    }
    foreach (['status', 'language'] as $key) {
        if ($filters[$key] !== '') {
            $where[] = ($key === 'language' ? $languageSql : 'p.status') . ' = :' . $key;
            $params[':' . $key] = $filters[$key];
        }
    }
    $from = ' FROM posts p INNER JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*)' . $from);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $perPage = max(1, min(100, $perPage));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($pages, (int) $filters['page']));
    $offset = ($page - 1) * $perPage;
    $statement = $pdo->prepare('SELECT p.id, p.title, p.slug, ' . $languageSql . ' AS language, p.status, p.updated_at, c.name AS category_name,
        CASE WHEN LENGTH(TRIM(COALESCE(p.content, \'\'))) > 0 THEN 1 ELSE 0 END AS has_public_content'
        . $from . ' ORDER BY p.updated_at DESC, p.id DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) $statement->bindValue($key, $value, PDO::PARAM_STR);
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    return [
        'items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages,
        'start' => $total > 0 ? $offset + 1 : 0, 'end' => min($total, $offset + $perPage),
    ];
}

function admin_handbook_page_url(array $filters, int $page): string
{
    $query = [];
    foreach (['q', 'status', 'language'] as $key) {
        if (($filters[$key] ?? '') !== '') $query[$key] = $filters[$key];
    }
    if ($page > 1) $query['page'] = $page;
    return '/admin/cam_nang.php' . ($query ? '?' . http_build_query($query) : '');
}

function admin_handbook_public_url(array $post): string
{
    return ((string) ($post['language'] ?? 'vi') === 'en' ? '/en' : '') . '/cam-nang/' . rawurlencode((string) ($post['slug'] ?? ''));
}
