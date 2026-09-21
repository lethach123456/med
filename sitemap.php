<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const MEDREVIEW_SITEMAP_PAGE_SIZE = 10000;

function medreview_sitemap_xml(string $xml): never
{
    header('Content-Type: application/xml; charset=UTF-8');
    header('Cache-Control: public, max-age=300, s-maxage=900, stale-while-revalidate=3600');
    echo $xml;
    exit;
}

function medreview_sitemap_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function medreview_sitemap_table_exists(PDO $pdo, string $table): bool
{
    static $allowed = [
        'medical_facilities', 'medical_doctors', 'medical_reviews',
        'medical_toplists', 'posts', 'categories', 'products', 'front_editor_page_profiles',
    ];
    if (!in_array($table, $allowed, true)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
    );
    $stmt->execute([':table' => $table]);
    return (bool) $stmt->fetchColumn();
}

function medreview_sitemap_lastmod($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    } catch (Throwable $e) {
        return '';
    }
}

function medreview_sitemap_static_paths(): array
{
    $paths = [
        '/',
        medical_public_facility_path(),
        '/bac-si.php',
        '/review.php',
        '/danh-muc-y-te.php',
        medical_public_toplist_path(),
    ];
    $pageKeys = [
        'about', 'contact', 'blog', 'blog-en', 'about-en', 'contact-en',
        'products', 'dich-vu', 'services-en',
    ];
    $catalog = front_editor_page_catalog();
    $customSlugs = [];
    try {
        $pdo = db();
        if (medreview_sitemap_table_exists($pdo, 'front_editor_page_profiles')) {
            $rows = $pdo->query(
                "SELECT page_key, slug FROM front_editor_page_profiles
                 WHERE TRIM(COALESCE(slug,'')) <> ''"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $customSlugs[(string) ($row['page_key'] ?? '')] = trim((string) ($row['slug'] ?? ''));
            }
        }
    } catch (Throwable $e) {
        // Keep the built-in routes available if the optional CMS profile table is absent.
    }
    foreach ($pageKeys as $pageKey) {
        $meta = $catalog[$pageKey] ?? [];
        $customSlug = $customSlugs[$pageKey] ?? '';
        $path = !empty($meta['supports_slug']) && $customSlug !== ''
            ? '/' . rawurlencode($customSlug)
            : (string) ($meta['default_route'] ?? '/');
        if ($path !== '/') {
            $paths[] = $path;
        }
    }
    return array_values(array_unique($paths));
}

function medreview_sitemap_sources(): array
{
    return [
        'facilities' => [
            'table' => 'medical_facilities',
            'count' => "SELECT COUNT(*) FROM medical_facilities WHERE status='published' AND TRIM(COALESCE(slug,''))<>''",
            'rows' => "SELECT slug, updated_at FROM medical_facilities WHERE status='published' AND TRIM(COALESCE(slug,''))<>'' ORDER BY id ASC LIMIT :limit OFFSET :offset",
            'path' => static fn (array $row): string => medical_public_facility_path((string) $row['slug']),
        ],
        'doctors' => [
            'table' => 'medical_doctors',
            'count' => "SELECT COUNT(*) FROM medical_doctors WHERE status='published' AND TRIM(COALESCE(slug,''))<>''",
            'rows' => "SELECT slug, updated_at FROM medical_doctors WHERE status='published' AND TRIM(COALESCE(slug,''))<>'' ORDER BY id ASC LIMIT :limit OFFSET :offset",
            'path' => static fn (array $row): string => '/bac-si-chi-tiet.php?slug=' . rawurlencode((string) $row['slug']),
        ],
        'reviews' => [
            'table' => 'medical_reviews',
            'count' => "SELECT COUNT(*) FROM medical_reviews WHERE status='published' AND TRIM(COALESCE(slug,''))<>''",
            'rows' => "SELECT slug, updated_at FROM medical_reviews WHERE status='published' AND TRIM(COALESCE(slug,''))<>'' ORDER BY id ASC LIMIT :limit OFFSET :offset",
            'path' => static fn (array $row): string => '/review-chi-tiet.php?slug=' . rawurlencode((string) $row['slug']),
        ],
        'toplists' => [
            'table' => 'medical_toplists',
            'count' => "SELECT COUNT(*) FROM medical_toplists WHERE status='published' AND TRIM(COALESCE(slug,''))<>''",
            'rows' => "SELECT slug, updated_at FROM medical_toplists WHERE status='published' AND TRIM(COALESCE(slug,''))<>'' ORDER BY id ASC LIMIT :limit OFFSET :offset",
            'path' => static fn (array $row): string => medical_public_toplist_path((string) $row['slug']),
        ],
        'products' => [
            'table' => 'products',
            'count' => "SELECT COUNT(*) FROM products WHERE status='published' AND TRIM(COALESCE(slug,''))<>''",
            'rows' => "SELECT slug, updated_at FROM products WHERE status='published' AND TRIM(COALESCE(slug,''))<>'' ORDER BY id ASC LIMIT :limit OFFSET :offset",
            'path' => static fn (array $row): string => '/san-pham/' . rawurlencode((string) $row['slug']),
        ],
    ];
}

function medreview_sitemap_source_count(PDO $pdo, array $source): int
{
    if (!medreview_sitemap_table_exists($pdo, (string) $source['table'])) {
        return 0;
    }
    return (int) $pdo->query((string) $source['count'])->fetchColumn();
}

$map = strtolower(trim((string) ($_GET['map'] ?? 'index')));
$page = max(1, (int) ($_GET['page'] ?? 1));
$origin = site_canonical_origin();

try {
    $sources = medreview_sitemap_sources();

    if ($map === 'index') {
        $pdo = db();
        $entries = ['pages' => 1];
        foreach ($sources as $name => $source) {
            $count = medreview_sitemap_source_count($pdo, $source);
            if ($name === 'toplists' && !medreview_sitemap_table_exists($pdo, 'medical_toplists')) {
                continue;
            }
            if ($count > 0) {
                $entries[$name] = (int) ceil($count / MEDREVIEW_SITEMAP_PAGE_SIZE);
            }
        }
        if (medreview_sitemap_table_exists($pdo, 'posts') && medreview_sitemap_table_exists($pdo, 'categories')) {
            $articleCount = (int) $pdo->query(
                "SELECT COUNT(*) FROM posts p JOIN categories c ON c.id=p.category_id
                 WHERE p.status='published' AND TRIM(COALESCE(p.slug,''))<>''
                   AND c.slug IN ('blog','blog-en','dich-vu','dich-vu-en')"
            )->fetchColumn();
            if ($articleCount > 0) {
                $entries['articles'] = (int) ceil($articleCount / MEDREVIEW_SITEMAP_PAGE_SIZE);
            }
        }

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach ($entries as $name => $partCount) {
            for ($part = 1; $part <= $partCount; $part++) {
                $loc = $origin . '/sitemap-' . $name . '-' . $part . '.xml';
                $xml[] = '  <sitemap><loc>' . medreview_sitemap_escape($loc) . '</loc></sitemap>';
            }
        }
        $xml[] = '</sitemapindex>';
        medreview_sitemap_xml(implode("\n", $xml));
    }

    $items = [];
    if ($map === 'pages') {
        foreach (medreview_sitemap_static_paths() as $path) {
            $items[] = ['path' => $path, 'updated_at' => ''];
        }
    } elseif ($map === 'articles') {
        $pdo = db();
        if (medreview_sitemap_table_exists($pdo, 'posts') && medreview_sitemap_table_exists($pdo, 'categories')) {
            $stmt = $pdo->prepare(
                "SELECT p.slug, p.updated_at, c.slug AS category_slug
                 FROM posts p JOIN categories c ON c.id=p.category_id
                 WHERE p.status='published' AND TRIM(COALESCE(p.slug,''))<>''
                   AND c.slug IN ('blog','blog-en','dich-vu','dich-vu-en')
                 ORDER BY p.id ASC LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue(':limit', MEDREVIEW_SITEMAP_PAGE_SIZE, PDO::PARAM_INT);
            $stmt->bindValue(':offset', ($page - 1) * MEDREVIEW_SITEMAP_PAGE_SIZE, PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $categorySlug = (string) ($row['category_slug'] ?? '');
                $path = match ($categorySlug) {
                    'blog' => '/blog/' . rawurlencode((string) $row['slug']),
                    'blog-en' => '/news/' . rawurlencode((string) $row['slug']),
                    default => '/' . rawurlencode((string) $row['slug']),
                };
                $items[] = ['path' => $path, 'updated_at' => (string) $row['updated_at']];
            }
        }
    } elseif (isset($sources[$map])) {
        $pdo = db();
        $source = $sources[$map];
        if (medreview_sitemap_table_exists($pdo, (string) $source['table'])) {
            $stmt = $pdo->prepare((string) $source['rows']);
            $stmt->bindValue(':limit', MEDREVIEW_SITEMAP_PAGE_SIZE, PDO::PARAM_INT);
            $stmt->bindValue(':offset', ($page - 1) * MEDREVIEW_SITEMAP_PAGE_SIZE, PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $items[] = ['path' => $source['path']($row), 'updated_at' => (string) ($row['updated_at'] ?? '')];
            }
        }
    } else {
        medreview_sitemap_xml('<?xml version="1.0" encoding="UTF-8"?><error>Not found</error>');
    }

    $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
    foreach ($items as $item) {
        $loc = site_absolute_url((string) $item['path']);
        $lastmod = medreview_sitemap_lastmod($item['updated_at'] ?? '');
        $xml[] = '  <url><loc>' . medreview_sitemap_escape($loc) . '</loc>'
            . ($lastmod !== '' ? '<lastmod>' . $lastmod . '</lastmod>' : '')
            . '</url>';
    }
    $xml[] = '</urlset>';
    medreview_sitemap_xml(implode("\n", $xml));
} catch (Throwable $e) {
    http_response_code(503);
    medreview_sitemap_xml('<?xml version="1.0" encoding="UTF-8"?><error>Sitemap temporarily unavailable</error>');
}
