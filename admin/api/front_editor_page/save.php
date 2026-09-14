<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

$pdo = db();
ensure_front_editor_page_profiles_table($pdo);

$input = read_json_body();
$pageKey = trim((string) ($input['page_key'] ?? ''));
$catalog = front_editor_page_catalog();
if ($pageKey === '' || !isset($catalog[$pageKey])) {
    json_response(['ok' => false, 'message' => 'Trang front editor không hợp lệ.'], 422);
}

$supportsSlug = !empty($catalog[$pageKey]['supports_slug']);
$defaultRoute = trim((string) ($catalog[$pageKey]['default_route'] ?? ''));
$defaultSlug = $defaultRoute !== '' && $defaultRoute !== '/' ? trim($defaultRoute, '/') : '';

$seoTitle = trim((string) ($input['seo_title'] ?? ''));
$seoDescription = trim((string) ($input['seo_description'] ?? ''));
$seoKeywords = trim((string) ($input['seo_keywords'] ?? ''));
$slugInput = trim((string) ($input['slug'] ?? ''));
$slug = $supportsSlug ? slugify($slugInput) : '';

if (mb_strlen($seoTitle) > 160) {
    json_response(['ok' => false, 'message' => 'SEO title quá dài (tối đa 160 ký tự).'], 422);
}
if (mb_strlen($seoDescription) > 300) {
    json_response(['ok' => false, 'message' => 'SEO description quá dài (tối đa 300 ký tự).'], 422);
}
if (mb_strlen($seoKeywords) > 255) {
    json_response(['ok' => false, 'message' => 'SEO keywords quá dài (tối đa 255 ký tự).'], 422);
}

if ($supportsSlug && $slug !== '') {
    $reserved = front_editor_page_reserved_slugs();
    $isOwnDefaultSlug = $defaultSlug !== '' && $slug === $defaultSlug;
    if (!$isOwnDefaultSlug && in_array($slug, $reserved, true)) {
        json_response(['ok' => false, 'message' => 'Slug đang trùng với route hệ thống hoặc route mặc định khác.'], 422);
    }

    $stmt = $pdo->prepare(
        "SELECT page_key
         FROM front_editor_page_profiles
         WHERE slug = :slug AND page_key <> :page_key
         LIMIT 1"
    );
    $stmt->execute([
        ':slug' => $slug,
        ':page_key' => $pageKey,
    ]);
    if ($stmt->fetchColumn()) {
        json_response(['ok' => false, 'message' => 'Slug này đã được dùng cho một trang front editor khác.'], 422);
    }

    $stmt = $pdo->prepare("SELECT id FROM posts WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    if ($stmt->fetchColumn()) {
        json_response(['ok' => false, 'message' => 'Slug này đang trùng với một bài/trang trong bảng posts.'], 422);
    }
}

$stmt = $pdo->prepare(
    "INSERT INTO front_editor_page_profiles (page_key, slug, seo_title, seo_description, seo_keywords)
     VALUES (:page_key, :slug, :seo_title, :seo_description, :seo_keywords)
     ON DUPLICATE KEY UPDATE
        slug = VALUES(slug),
        seo_title = VALUES(seo_title),
        seo_description = VALUES(seo_description),
        seo_keywords = VALUES(seo_keywords)"
);
$stmt->execute([
    ':page_key' => $pageKey,
    ':slug' => ($supportsSlug && $slug !== '') ? $slug : null,
    ':seo_title' => $seoTitle !== '' ? $seoTitle : null,
    ':seo_description' => $seoDescription !== '' ? $seoDescription : null,
    ':seo_keywords' => $seoKeywords !== '' ? $seoKeywords : null,
]);

$profile = front_editor_page_profile($pageKey);

json_response([
    'ok' => true,
    'message' => 'Đã lưu cấu hình SEO cho trang front editor.',
    'profile' => $profile,
    'public_path' => front_editor_page_public_path($pageKey),
]);
