<?php
declare(strict_types=1);
require_once __DIR__ . '/_cam_nang.php';

/** Validate the entire title-only batch before writing any article. */
function admin_handbook_import_titles(string $json, string $defaultLanguage): array
{
    if (!in_array($defaultLanguage, ['vi', 'en'], true)) throw new InvalidArgumentException('Chọn ngôn ngữ Tiếng Việt hoặc English.');
    if (strlen($json) > 131072) throw new InvalidArgumentException('JSON tối đa 128 KB, mỗi lần tối đa 100 tiêu đề.');
    try { $items = json_decode($json, false, 16, JSON_THROW_ON_ERROR); }
    catch (JsonException $error) { throw new InvalidArgumentException('JSON không hợp lệ. Dùng một mảng tiêu đề theo mẫu bên dưới.'); }
    if (!is_array($items) || count($items) < 1 || count($items) > 100) throw new InvalidArgumentException('JSON phải là một mảng từ 1 đến 100 tiêu đề.');
    $rows = [];
    foreach ($items as $index => $item) {
        $number = $index + 1;
        $language = $defaultLanguage;
        if ($item instanceof stdClass) {
            if (array_diff(array_keys(get_object_vars($item)), ['title', 'language']) !== []) throw new InvalidArgumentException("Mục {$number}: chỉ nhận title và language, không nhập nội dung hoặc trạng thái.");
            $title = $item->title ?? null;
            $language = $item->language ?? $defaultLanguage;
        } else { $title = $item; }
        if (!is_string($title) || !is_string($language) || !in_array($language, ['vi', 'en'], true)) throw new InvalidArgumentException("Mục {$number}: title phải là chuỗi, language là vi hoặc en.");
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        if ($title === '' || mb_strlen($title) > 160 || strip_tags($title) !== $title || preg_match('/[\x00-\x1F\x7F]/u', $title)) throw new InvalidArgumentException("Mục {$number}: tiêu đề cần 1–160 ký tự văn bản, không chứa HTML.");
        $rows[] = ['number' => $number, 'title' => $title, 'language' => $language];
    }
    return $rows;
}

function admin_handbook_import_title_key(string $title, string $language): string
{
    return $language . ':' . mb_strtolower(trim(preg_replace('/\s+/u', ' ', $title) ?? ''), 'UTF-8');
}

/** Authenticated title import: draft-only, no overwrite, all writes atomic. */
function admin_handbook_import_request(PDO $pdo, array $post, string $expectedToken, array $options = []): array
{
    $token = $post['csrf_token'] ?? null;
    if ($expectedToken === '' || !is_string($token) || !hash_equals($expectedToken, $token)) throw new InvalidArgumentException('Yêu cầu đã hết hạn hoặc không hợp lệ. Tải lại trang rồi thử lại.');
    if (($post['action'] ?? null) !== 'import_titles' || !is_string($post['titles_json'] ?? null) || !is_string($post['default_language'] ?? null)) throw new InvalidArgumentException('Dữ liệu nhập không hợp lệ. Dán JSON và chọn ngôn ngữ.');
    $rows = admin_handbook_import_titles($post['titles_json'], $post['default_language']);
    if ($pdo->inTransaction()) throw new LogicException('Import requires its own transaction.');
    $pdo->beginTransaction();
    try {
        $languages = array_values(array_unique(array_column($rows, 'language'))); sort($languages);
        $categories = [];
        // Locking reads here avoid taking an old repeatable-read snapshot
        // before another import holding the same category has committed.
        foreach ($languages as $language) $categories[$language] = admin_handbook_ensure_category($pdo, $language, true);
        // Serialize simultaneous bulk imports for these language categories.
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $ids = array_values($categories); sort($ids);
            $lock = $pdo->prepare('SELECT id FROM categories WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id FOR UPDATE');
            $lock->execute($ids); $lock->fetchAll();
        }
        $wanted = [];
        foreach ($rows as $row) $wanted[admin_handbook_import_title_key($row['title'], $row['language'])] = true;
        $existing = [];
        $find = $pdo->query("SELECT p.id,p.title,COALESCE(NULLIF(LOWER(TRIM(p.language)),''),'vi') AS language FROM posts p INNER JOIN categories c ON c.id=p.category_id WHERE c.slug IN ('blog','blog-en')");
        while ($article = $find->fetch(PDO::FETCH_ASSOC)) {
            $key = admin_handbook_import_title_key((string)$article['title'], (string)$article['language']);
            if (isset($wanted[$key])) $existing[$key] = (int)$article['id'];
        }
        $columns = ['category_id', 'title', 'slug', 'language', 'status', 'content', 'excerpt'];
        if ($options['has_template'] ?? true) $columns[] = 'template';
        $insert = $pdo->prepare('INSERT INTO posts(' . implode(',', $columns) . ') VALUES(' . implode(',', array_fill(0, count($columns), '?')) . ')');
        $result = ['created' => [], 'skipped' => []];
        foreach ($rows as $row) {
            $key = admin_handbook_import_title_key($row['title'], $row['language']);
            if (isset($existing[$key])) {
                $result['skipped'][] = $row + ['id' => $existing[$key]];
                continue;
            }
            $slug = unique_slug($pdo, 'posts', $row['title']);
            $values = [$categories[$row['language']], $row['title'], $slug, $row['language'], 'draft', null, null];
            if ($options['has_template'] ?? true) $values[] = 0;
            $insert->execute($values);
            $id = (int)$pdo->lastInsertId(); $existing[$key] = $id;
            $result['created'][] = $row + ['id' => $id];
        }
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
