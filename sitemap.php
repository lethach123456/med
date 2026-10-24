<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_handbook.php';
require_once __DIR__ . '/medical_review_articles.php';
require_once __DIR__ . '/medical_review_seo.php';

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

/** @return array<string,list<string>> */
function medreview_sitemap_schema(PDO $pdo): array
{
    static $cache;
    $cache ??= new WeakMap();
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    // SQLite fixtures can create tables on an existing connection. Production
    // requests never migrate a schema while rendering a sitemap.
    $version = $sqlite ? (int) $pdo->query('PRAGMA schema_version')->fetchColumn() : 0;
    if (isset($cache[$pdo]) && $cache[$pdo]['version'] === $version) {
        return $cache[$pdo]['tables'];
    }
    $allowed = [
        'medical_facilities', 'medical_doctors', 'medical_reviews',
        'medical_toplists', 'medical_toplist_facilities', 'medical_toplist_doctors',
        'medical_review_articles', 'posts', 'categories', 'products', 'front_editor_page_profiles',
    ];
    $tables = [];
    if ($sqlite) {
        foreach ($allowed as $table) {
            $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
            if ($columns !== []) $tables[$table] = array_column($columns, 'name');
        }
    } else {
        $names = implode(',', array_map(static fn (string $name): string => "'{$name}'", $allowed));
        $rows = $pdo->query('SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . $names . ')')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) $tables[(string) $row['TABLE_NAME']][] = (string) $row['COLUMN_NAME'];
    }
    $cache[$pdo] = ['version' => $version, 'tables' => $tables];
    return $tables;
}

function medreview_sitemap_table_exists(PDO $pdo, string $table): bool
{
    return isset(medreview_sitemap_schema($pdo)[$table]);
}

function medreview_sitemap_column_exists(PDO $pdo, string $table, string $column): bool
{
    return in_array($column, medreview_sitemap_schema($pdo)[$table] ?? [], true);
}

function medreview_sitemap_has_columns(PDO $pdo, string $table, array $columns): bool
{
    return array_diff($columns, medreview_sitemap_schema($pdo)[$table] ?? []) === [];
}

function medreview_sitemap_locale_sql(PDO $pdo, string $table, string $alias, string $column = 'language_code'): string
{
    return medreview_sitemap_column_exists($pdo, $table, $column)
        ? "COALESCE(NULLIF(LOWER(TRIM({$alias}.{$column})),''),'vi')"
        : "'vi'";
}

function medreview_sitemap_updated_sql(PDO $pdo, string $table, string $alias): string
{
    foreach (['updated_at', 'created_at'] as $column) {
        if (medreview_sitemap_column_exists($pdo, $table, $column)) return "{$alias}.{$column}";
    }
    return "''";
}

function medreview_sitemap_handbook_where(PDO $pdo, string $lang): string
{
    $lang = $lang === 'en' ? 'en' : 'vi';
    $hasLangCol = medreview_sitemap_column_exists($pdo, 'posts', 'language');
    $langCondition = $hasLangCol
        ? "COALESCE(NULLIF(LOWER(TRIM(p.language)), ''), 'vi') = '{$lang}'"
        : ($lang === 'en' ? "c.slug = 'blog-en'" : "c.slug = 'blog'");
    return "p.status = 'published'
        AND c.slug IN ('blog', 'blog-en')
        AND {$langCondition}
        AND LENGTH(TRIM(COALESCE(p.content, ''))) > 0
        AND LENGTH(TRIM(COALESCE(p.slug, ''))) > 0";
}

function medreview_sitemap_handbook_count(PDO $pdo, string $lang): int
{
    if (!medreview_sitemap_posts_ready($pdo)) {
        return 0;
    }
    $where = medreview_sitemap_handbook_where($pdo, $lang);
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM posts p INNER JOIN categories c ON c.id = p.category_id WHERE {$where}"
    )->fetchColumn();
}

/** @return list<array{path:string,updated_at:string}> */
function medreview_sitemap_handbook_items(PDO $pdo, string $lang, int $page): array
{
    if (!medreview_sitemap_posts_ready($pdo)) {
        return [];
    }
    $lang = $lang === 'en' ? 'en' : 'vi';
    $where = medreview_sitemap_handbook_where($pdo, $lang);
    $updated = medreview_sitemap_updated_sql($pdo, 'posts', 'p');
    $stmt = $pdo->prepare(
        "SELECT p.slug, {$updated} AS updated_at
         FROM posts p
         INNER JOIN categories c ON c.id = p.category_id
         WHERE {$where}
         ORDER BY p.id ASC
         LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':limit', MEDREVIEW_SITEMAP_PAGE_SIZE, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * MEDREVIEW_SITEMAP_PAGE_SIZE, PDO::PARAM_INT);
    $stmt->execute();
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $slug = trim((string) ($row['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $items[] = [
            'path' => handbook_view_path($lang, $slug),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
    return $items;
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

function medreview_sitemap_static_paths(string $language = 'vi', ?PDO $pdo = null): array
{
    $paths = [
        '/',
        '/en',
        medical_public_facility_path(),
        site_localized_path(medical_public_facility_path(), 'en'),
        site_localized_path('/bac-si', 'vi'),
        site_localized_path('/bac-si', 'en'),
        site_localized_path('/review', 'vi'),
        site_localized_path('/review', 'en'),
        site_localized_path('/ve-chung-toi', 'vi'),
        site_localized_path('/danh-muc-y-te', 'vi'),
        '/ho-so-da-xac-thuc',
        '/en/ho-so-da-xac-thuc',
        '/cam-nang',
        '/en/cam-nang',
        medical_public_toplist_path(),
        site_localized_path(medical_public_toplist_path(), 'en'),
        site_localized_path('/ve-chung-toi', 'en'),
    ];
    $paths = array_values(array_filter($paths, static fn (string $path): bool =>
        (preg_match('~^/en(?:/|$)~', $path) === 1) === ($language === 'en')));
    $pageKeys = [
        'about', 'contact', 'about-en', 'contact-en',
        'products', 'dich-vu', 'services-en',
    ];
    $catalog = front_editor_page_catalog();
    $customSlugs = [];
    try {
        $pdo ??= db();
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
        if (str_ends_with($pageKey, '-en') !== ($language === 'en')) continue;
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

function medreview_sitemap_posts_ready(PDO $pdo): bool
{
    return medreview_sitemap_has_columns($pdo, 'posts', ['id', 'category_id', 'slug', 'status', 'content'])
        && medreview_sitemap_has_columns($pdo, 'categories', ['id', 'slug']);
}

function medreview_sitemap_query_source(string $table, string $alias, string $where, string $projection, callable $path, string $join = ''): array
{
    $from = " FROM {$table} {$alias}{$join} WHERE {$where}";
    return [
        'table' => $table,
        'count' => 'SELECT COUNT(*)' . $from,
        'rows' => 'SELECT ' . $projection . $from . " ORDER BY {$alias}.id ASC LIMIT :limit OFFSET :offset",
        'path' => $path,
    ];
}

/** Build the same capability-aware queries for root, locale, and leaf maps. */
function medreview_sitemap_sources(PDO $pdo, string $language = 'vi'): array
{
    $language = $language === 'en' ? 'en' : 'vi';
    $sources = [];
    foreach (['facilities' => ['medical_facilities', 'facility', 'f'], 'doctors' => ['medical_doctors', 'doctor', 'd']] as $name => [$table, $type, $alias]) {
        if (!medreview_sitemap_has_columns($pdo, $table, ['id', 'slug', 'status', 'content'])) continue;
        $locale = medreview_sitemap_locale_sql($pdo, $table, $alias);
        $updated = medreview_sitemap_updated_sql($pdo, $table, $alias);
        $where = "{$alias}.status='published' AND TRIM(COALESCE({$alias}.slug,''))<>''
            AND LENGTH(TRIM(COALESCE({$alias}.content,'')))>0 AND {$locale}='{$language}'";
        $sources[$name] = medreview_sitemap_query_source($table, $alias, $where,
            "{$alias}.slug, {$locale} AS language_code, {$updated} AS updated_at",
            static fn (array $row): string => medical_public_entity_path($type, (string) $row['slug'], (string) $row['language_code']));
    }

    // Older installations can omit newer sources without breaking every sitemap.
    if ($language === 'vi' && medreview_sitemap_has_columns($pdo, 'medical_reviews',
        ['id', 'slug', 'title', 'status', 'rating', 'author_text', 'method_text', 'condition_text', 'excerpt', 'story_json'])) {
        $updated = medreview_sitemap_updated_sql($pdo, 'medical_reviews', 'r');
        $sources['reviews'] = medreview_sitemap_query_source('medical_reviews', 'r', medical_review_seo_indexable_where('r.'),
            "r.slug, {$updated} AS updated_at",
            static fn (array $row): string => '/review-chi-tiet.php?slug=' . rawurlencode((string) $row['slug']));
    }

    if (medreview_sitemap_has_columns($pdo, 'medical_review_articles', ['id', 'entity_type', 'entity_id', 'status', 'slug', 'title', 'content_html'])
        && medreview_sitemap_has_columns($pdo, 'medical_facilities', ['id', 'status'])) {
        $locale = medreview_sitemap_locale_sql($pdo, 'medical_review_articles', 'a');
        $facilityLocale = medreview_sitemap_locale_sql($pdo, 'medical_facilities', 'f');
        $updated = medreview_sitemap_updated_sql($pdo, 'medical_review_articles', 'a');
        $where = "a.entity_type='facility' AND a.status='published' AND TRIM(a.slug)<>'' AND TRIM(a.title)<>'' AND TRIM(a.content_html)<>''
            AND {$locale}='{$language}' AND EXISTS (SELECT 1 FROM medical_facilities f WHERE f.id=a.entity_id
                AND f.status='published' AND {$facilityLocale}={$locale})";
        $sources['review-articles'] = medreview_sitemap_query_source('medical_review_articles', 'a', $where,
            "a.slug, {$locale} AS language_code, {$updated} AS updated_at",
            static fn (array $row): string => medical_review_articles_path((string) $row['language_code'], (string) $row['slug']));
    }

    if (medreview_sitemap_has_columns($pdo, 'medical_toplists', ['id', 'slug', 'status'])) {
        $locale = medreview_sitemap_locale_sql($pdo, 'medical_toplists', 't');
        $type = medreview_sitemap_column_exists($pdo, 'medical_toplists', 'entity_type') ? 't.entity_type' : "'facility'";
        $members = [];
        $guards = [];
        foreach (['facility' => ['medical_toplist_facilities', 'facility_id', 'medical_facilities', 'tf', 'f'],
                  'doctor' => ['medical_toplist_doctors', 'doctor_id', 'medical_doctors', 'td', 'd']] as $entity => [$links, $foreignKey, $table, $linkAlias, $alias]) {
            if (!medreview_sitemap_has_columns($pdo, $links, ['toplist_id', $foreignKey])) continue;
            $linked = "SELECT 1 FROM {$links} {$linkAlias} WHERE {$linkAlias}.toplist_id=t.id";
            if (!medreview_sitemap_has_columns($pdo, $table, ['id', 'status', 'content'])) {
                // Any link to an unsupported profile must keep the list out.
                $guards[] = 'NOT EXISTS (' . $linked . ')';
                continue;
            }
            $memberLocale = medreview_sitemap_locale_sql($pdo, $table, $alias);
            $members[$entity] = 'EXISTS (' . $linked . ')';
            $guards[] = "NOT EXISTS (SELECT 1 FROM {$links} {$linkAlias} LEFT JOIN {$table} {$alias} ON {$alias}.id={$linkAlias}.{$foreignKey}
                WHERE {$linkAlias}.toplist_id=t.id AND ({$alias}.id IS NULL OR COALESCE({$alias}.status,'')<>'published'
                    OR {$memberLocale}<>{$locale} OR LENGTH(TRIM(COALESCE({$alias}.content,'')))=0))";
        }
        $types = [];
        foreach ($members as $entity => $exists) $types[] = "({$type}='{$entity}' AND {$exists})";
        if (isset($members['facility'], $members['doctor'])) {
            $types[] = "({$type}='mixed' AND ({$members['facility']} OR {$members['doctor']}))";
        }
        if ($types !== []) {
            $updated = medreview_sitemap_updated_sql($pdo, 'medical_toplists', 't');
            $where = "t.status='published' AND TRIM(COALESCE(t.slug,''))<>'' AND {$locale}='{$language}'
                AND (" . implode(' OR ', $types) . ') AND ' . implode(' AND ', $guards);
            $sources['toplists'] = medreview_sitemap_query_source('medical_toplists', 't', $where,
                "t.slug, {$locale} AS language_code, {$updated} AS updated_at",
                static fn (array $row): string => medical_public_entity_path('toplist', (string) $row['slug'], (string) $row['language_code']));
        }
    }

    if ($language === 'vi' && medreview_sitemap_has_columns($pdo, 'products', ['id', 'slug', 'status'])) {
        $updated = medreview_sitemap_updated_sql($pdo, 'products', 'p');
        $sources['products'] = medreview_sitemap_query_source('products', 'p', "p.status='published' AND TRIM(COALESCE(p.slug,''))<>''",
            "p.slug, {$updated} AS updated_at", static fn (array $row): string => '/san-pham/' . rawurlencode((string) $row['slug']));
    }

    if (medreview_sitemap_posts_ready($pdo)) {
        // Blog categories are served only by the canonical handbook map.
        $category = $language === 'en' ? 'dich-vu-en' : 'dich-vu';
        $updated = medreview_sitemap_updated_sql($pdo, 'posts', 'p');
        $where = "p.status='published' AND TRIM(COALESCE(p.slug,''))<>'' AND LENGTH(TRIM(COALESCE(p.content,'')))>0 AND c.slug='{$category}'";
        $sources['articles'] = medreview_sitemap_query_source('posts', 'p', $where, "p.slug, {$updated} AS updated_at",
            static fn (array $row): string => '/' . rawurlencode((string) $row['slug']), ' JOIN categories c ON c.id=p.category_id');
    }
    return $sources;
}

function medreview_sitemap_source_count(PDO $pdo, array $source): int
{
    if (!medreview_sitemap_table_exists($pdo, (string) $source['table'])) {
        return 0;
    }
    return (int) $pdo->query((string) $source['count'])->fetchColumn();
}

// Allows isolated query fixtures to load the same builders without opening db().
if (defined('MEDREVIEW_SITEMAP_FUNCTIONS_ONLY') && MEDREVIEW_SITEMAP_FUNCTIONS_ONLY) return;

$map = strtolower(trim((string) ($_GET['map'] ?? 'index')));
$language = 'vi';
if (preg_match('/^(vi|en)(?:-(.+))?$/', $map, $localized)) {
    $language = $localized[1];
    $map = $localized[2] ?? 'index';
}
if ($map === 'cam-nang' || $map === 'cam_nang' || $map === 'guides') {
    $map = 'handbook';
}
$rootIndex = $map === 'index' && !in_array(strtolower(trim((string) ($_GET['map'] ?? 'index'))), ['vi', 'en'], true);
$page = max(1, (int) ($_GET['page'] ?? 1));
$origin = site_canonical_origin();

try {
    $pdo = db();
    $sources = medreview_sitemap_sources($pdo, $language);

    if ($rootIndex) {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach (['vi', 'en'] as $lang) {
            // Keep the master index flat: its children must be URL sitemaps,
            // not nested sitemap indexes.
            $parts = ['pages' => 1];
            foreach (medreview_sitemap_sources($pdo, $lang) as $name => $source) {
                if ($name === 'products') continue;
                $count = medreview_sitemap_source_count($pdo, $source);
                if ($count > 0) $parts[$name] = (int) ceil($count / MEDREVIEW_SITEMAP_PAGE_SIZE);
            }
            $handbookCount = medreview_sitemap_handbook_count($pdo, $lang);
            if ($handbookCount > 0) {
                $parts['handbook'] = (int) ceil($handbookCount / MEDREVIEW_SITEMAP_PAGE_SIZE);
            }
            foreach ($parts as $name => $partCount) {
                for ($part = 1; $part <= $partCount; $part++) {
                    $xml[] = '  <sitemap><loc>' . medreview_sitemap_escape($origin . '/sitemap-' . $lang . '-' . $name . '-' . $part . '.xml') . '</loc></sitemap>';
                }
            }
        }
        $xml[] = '</sitemapindex>';
        medreview_sitemap_xml(implode("\n", $xml));
    }

    if ($map === 'index') {
        $entries = ['pages' => 1];
        foreach ($sources as $name => $source) {
            // Product URLs are intentionally excluded from the public sitemap index.
            if ($name === 'products') {
                continue;
            }
            $count = medreview_sitemap_source_count($pdo, $source);
            if ($name === 'toplists' && !medreview_sitemap_table_exists($pdo, 'medical_toplists')) {
                continue;
            }
            if ($count > 0) {
                $entries[$name] = (int) ceil($count / MEDREVIEW_SITEMAP_PAGE_SIZE);
            }
        }
        $handbookCount = medreview_sitemap_handbook_count($pdo, $language);
        if ($handbookCount > 0) {
            $entries['handbook'] = (int) ceil($handbookCount / MEDREVIEW_SITEMAP_PAGE_SIZE);
        }

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach ($entries as $name => $partCount) {
            for ($part = 1; $part <= $partCount; $part++) {
                $loc = $origin . '/sitemap-' . $language . '-' . $name . '-' . $part . '.xml';
                $xml[] = '  <sitemap><loc>' . medreview_sitemap_escape($loc) . '</loc></sitemap>';
            }
        }
        $xml[] = '</sitemapindex>';
        medreview_sitemap_xml(implode("\n", $xml));
    }

    $items = [];
    if ($map === 'pages') {
        foreach (medreview_sitemap_static_paths($language, $pdo) as $path) {
            $items[] = ['path' => $path, 'updated_at' => ''];
        }
    } elseif ($map === 'handbook') {
        $items = medreview_sitemap_handbook_items($pdo, $language, $page);
    } elseif (isset($sources[$map])) {
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
    } elseif (!in_array($map, ['facilities', 'doctors', 'reviews', 'review-articles', 'toplists', 'products', 'articles'], true)) {
        http_response_code(404);
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
