<?php
declare(strict_types=1);

/**
 * Read-only source inventory and narrowly scoped reference replacement.
 * Including this file does not connect to the database, resolve DNS, migrate,
 * or touch image files. Remote URLs still need the safe downloader's DNS/IP
 * validation at download time; syntactic validation cannot prevent rebinding.
 */

/** @return array{url:string,local_path:?string}|null */
function medical_image_brand_source_normalize(string $url): ?array
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 8192 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) return null;
    $parts = parse_url($url);
    if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) return null;
    $origin = function_exists('site_canonical_origin') ? site_canonical_origin() : (string) (getenv('SITE_CANONICAL_ORIGIN') ?: 'https://medreview.vn');
    $own = parse_url($origin);
    $absolute = isset($parts['scheme']) || isset($parts['host']);
    $local = !$absolute;
    if ($absolute) {
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') return null;
        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return null;
        } elseif (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host)
            || preg_match('/\.(?:local|localhost|internal|lan|test|invalid|onion)$/i', $host)) {
            return null;
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, [80, 443], true)) return null;
        $ownScheme = strtolower((string) ($own['scheme'] ?? 'https'));
        $ownPort = (int) ($own['port'] ?? ($ownScheme === 'https' ? 443 : 80));
        $local = is_array($own) && $scheme === $ownScheme && $host === strtolower((string) ($own['host'] ?? '')) && $port === $ownPort;
        if (!$local) {
            $path = (string) ($parts['path'] ?? '/');
            $canonical = $scheme . '://' . $host . ($port === ($scheme === 'https' ? 443 : 80) ? '' : ':' . $port) . ($path !== '' ? $path : '/');
            if (isset($parts['query'])) $canonical .= '?' . $parts['query'];
            // Share the downloader's syntactic policy without resolving DNS
            // or fetching anything: special-use IPs and secret-bearing URLs
            // should not enter the persistent inventory in the first place.
            require_once __DIR__ . '/medical_handbook_remote_media.php';
            try { $approved = handbook_ai_remote_image_url($canonical); }
            catch (InvalidArgumentException) { return null; }
            return ['url' => $approved['url'], 'local_path' => null];
        }
    } elseif (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
        return null;
    }
    $path = rawurldecode((string) ($parts['path'] ?? ''));
    if (!preg_match('~^/uploads/library/[a-zA-Z0-9_./-]+\.(?:jpe?g|png|webp)$~i', $path)
        || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path) || str_contains($path, '//')) return null;
    $root = realpath(__DIR__ . '/uploads/library');
    $candidate = realpath(__DIR__ . $path);
    $localPath = null;
    if (is_string($candidate)) {
        if (!is_string($root) || !str_starts_with($candidate, $root . DIRECTORY_SEPARATOR) || !is_file($candidate) || is_link(__DIR__ . $path)) return null;
        $localPath = $candidate;
    }
    return ['url' => $path, 'local_path' => $localPath];
}

/** Register configured site icons before scanning otherwise unreferenced files. */
function medical_image_brand_source_exclude_urls(array $urls): void
{
    foreach ($urls as $url) {
        if (!is_string($url)) continue;
        $source = medical_image_brand_source_normalize($url);
        if ($source !== null) $GLOBALS['medical_image_brand_excluded_urls'][$source['url']] = true;
    }
}

/** @return array{key:string,url:string,local_path:?string}|null */
function medical_image_brand_source_identity(string $url): ?array
{
    $source = medical_image_brand_source_normalize($url);
    if ($source === null) return null;
    $path = (string) (parse_url($source['url'], PHP_URL_PATH) ?? '');
    if ((str_starts_with($source['url'], '/') && (str_starts_with($path, '/uploads/library/medreview-branded/')
        || $path === '/uploads/library/2026/07/fbef23f192e21b0669c670803723ec66.jpg'))
        || preg_match('~(?:^|/)(?:favicon(?:[-.][^/]*)?|apple-touch-icon[^/]*|logo(?:[-.][^/]*)?)$~i', $path)
        || isset($GLOBALS['medical_image_brand_excluded_urls'][$source['url']])) return null;
    return ['key' => hash('sha256', $source['url']), 'url' => $source['url'], 'local_path' => $source['local_path']];
}

/** Exact imported-entity folders; source-* backups are not public photo copies. */
function medical_image_brand_library_prefixes(): array
{
    return ['co-so-y-te/facility-', 'bac-si/doctor-'];
}

/** @return array<string,string> column => processing context */
function medical_image_brand_source_fields(string $type): array
{
    $fields = ['image_url' => 'single', 'gallery_json' => 'gallery', 'content' => 'html', 'services_json' => 'nested', 'reviews_json' => 'nested'];
    if ($type === 'facility') $fields += ['ai_image_url' => 'single', 'featured_services_json' => 'nested', 'doctors_json' => 'nested', 'team_json' => 'nested', 'reviews_list_json' => 'nested'];
    return $fields;
}

/**
 * Walk only image-bearing properties. A generic url/src is accepted only in a
 * gallery/photo context; evidence/source/link subtrees always remain untouched.
 */
function medical_image_brand_source_walk(mixed $value, callable $map, string $context = 'nested'): mixed
{
    if (is_string($value)) return $context === 'gallery' || $context === 'single' ? $map($value) : $value;
    if (!is_array($value)) return $value;
    $imageKeys = ['image', 'image_url', 'photo', 'photo_url', 'avatar', 'avatar_url', 'portrait', 'portrait_url', 'thumbnail', 'thumbnail_url'];
    $imageLists = ['images', 'photos', 'gallery', 'gallery_json', 'image_urls', 'photo_urls'];
    $protected = ['source', 'sources', 'sources_json', 'source_ids', 'evidence', 'evidence_json', 'legal', 'legal_documents', 'links', 'social_links', 'metadata', 'caption', 'alt', 'angle'];
    foreach ($value as $key => $child) {
        if (is_int($key)) {
            $value[$key] = medical_image_brand_source_walk($child, $map, $context);
            continue;
        }
        $name = strtolower((string) $key);
        if (in_array($name, $protected, true) || str_contains($name, 'evidence') || str_contains($name, 'source') || str_contains($name, 'legal')) continue;
        if (in_array($name, $imageKeys, true)) $value[$key] = medical_image_brand_source_walk($child, $map, 'single');
        elseif (in_array($name, $imageLists, true)) $value[$key] = medical_image_brand_source_walk($child, $map, 'gallery');
        elseif (in_array($context, ['gallery', 'single'], true) && in_array($name, ['url', 'src'], true)) $value[$key] = medical_image_brand_source_walk($child, $map, 'single');
        elseif (is_array($child)) $value[$key] = medical_image_brand_source_walk($child, $map, 'nested');
    }
    return $value;
}

/** Replace only img src attribute values; retain all other markup byte-for-byte. */
function medical_image_brand_source_html(string $html, callable $map): string
{
    $pattern = '~<!--.*?-->|<(?:script|style|textarea)\b[^>]*>.*?</(?:script|style|textarea)\s*>|<img\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~is';
    return preg_replace_callback($pattern, static function (array $tag) use ($map): string {
        if (!preg_match('/^<img\b/i', $tag[0])) return $tag[0];
        // Consume every complete attribute, not just src. Otherwise text such
        // as alt='example src="..."' could be mistaken for a real attribute.
        $attribute = '~(\s+)([^\s/=>]+)(\s*=\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'<>`]+))~i';
        return preg_replace_callback($attribute, static function (array $match) use ($map): string {
            if (strtolower($match[2]) !== 'src') return $match[0];
            $prefix = $match[1] . $match[2] . $match[3];
            $token = substr($match[0], strlen($prefix));
            $quoted = in_array($token[0] ?? '', ['"', "'"], true) ? $token[0] : '';
            $old = $quoted !== '' ? substr($token, 1, -1) : $token;
            $decoded = html_entity_decode($old, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $new = $map($decoded);
            if ($new === $decoded) return $match[0];
            $quote = $quoted !== '' ? $quoted : '"';
            return $prefix . $quote . htmlspecialchars($new, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $quote;
        }, $tag[0]) ?? $tag[0];
    }, $html) ?? $html;
}

/** @return array<string,mixed> only fields whose image references changed */
function medical_image_brand_source_patch(array $row, string $type, callable $map): array
{
    $patch = [];
    foreach (medical_image_brand_source_fields($type) as $field => $context) {
        if (!array_key_exists($field, $row) || $row[$field] === null) continue;
        $raw = $row[$field];
        if ($context === 'single') $next = is_string($raw) ? $map($raw) : $raw;
        elseif ($context === 'html') $next = is_string($raw) ? medical_image_brand_source_html($raw, $map) : $raw;
        else {
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            // Malformed JSON must not become [] or silently lose content.
            if (!is_array($decoded)) continue;
            $nextValue = medical_image_brand_source_walk($decoded, $map, $context);
            if ($nextValue === $decoded) continue;
            $next = is_string($raw) ? json_encode($nextValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : $nextValue;
        }
        if ($next !== $raw) $patch[$field] = $next;
    }
    return $patch;
}

/** @return list<array{key:string,url:string,local_path:?string}> */
function medical_image_brand_sources_from_row(array $row, string $type): array
{
    $sources = [];
    medical_image_brand_source_patch($row, $type, static function (string $url) use (&$sources): string {
        $source = medical_image_brand_source_identity($url);
        if ($source !== null) $sources[$source['key']] = $source;
        return $url;
    });
    return array_values($sources);
}

/** Installed columns only. No CREATE/ALTER or schema migration paths. */
function medical_image_brand_source_columns(PDO $pdo, string $table): array
{
    if (!in_array($table, ['medical_facilities', 'medical_doctors', 'site_settings'], true)) throw new InvalidArgumentException('Unsupported inventory table.');
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
        return array_fill_keys(array_column($rows, 'name'), true);
    }
    $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
    $stmt->execute([':table_name' => $table]);
    return array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
}

function medical_image_brand_source_load_exclusions(PDO $pdo): void
{
    $columns = medical_image_brand_source_columns($pdo, 'site_settings');
    if (!isset($columns['setting_key'], $columns['setting_value'])) return;
    $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :key');
    $stmt->execute([':key' => 'site_icon_href']);
    medical_image_brand_source_exclude_urls($stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** @return array{rows:array,next_id:int,has_more:bool,scanned:int} */
function medical_image_brand_source_page(PDO $pdo, string $type, int $afterId = 0, int $limit = 100): array
{
    if (!in_array($type, ['facility', 'doctor'], true)) throw new InvalidArgumentException('Unsupported entity type.');
    $table = $type === 'facility' ? 'medical_facilities' : 'medical_doctors';
    $columns = medical_image_brand_source_columns($pdo, $table);
    if (!isset($columns['id'])) return ['rows' => [], 'next_id' => max(0, $afterId), 'has_more' => false, 'scanned' => 0];
    medical_image_brand_source_load_exclusions($pdo);
    $limit = max(1, min(500, $limit));
    $select = array_keys(array_intersect_key(['id' => true, 'name' => true] + medical_image_brand_source_fields($type), $columns));
    $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM ' . $table . ' WHERE id > :after_id ORDER BY id ASC LIMIT ' . ($limit + 1));
    $stmt->execute([':after_id' => max(0, $afterId)]);
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $more = count($rawRows) > $limit;
    $rows = []; $nextId = max(0, $afterId);
    foreach (array_slice($rawRows, 0, $limit) as $row) {
        $nextId = (int) $row['id'];
        $rows[] = ['id' => $nextId, 'type' => $type, 'name' => (string) ($row['name'] ?? ''), 'sources' => medical_image_brand_sources_from_row($row, $type)];
    }
    return ['rows' => $rows, 'next_id' => $nextId, 'has_more' => $more, 'scanned' => count($rows)];
}

/**
 * Caller owns the transaction and its commit/rollback. Re-read current rows
 * under lock; removed images are never resurrected from inventory snapshots.
 * @param list<array{type:string,id:int}> $refs
 * @return array{updated:int,skipped:int}
 */
function medical_image_brand_apply(PDO $pdo, array $refs, string $sourceKey, string $outputUrl, ?string $previousOutputUrl = null): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Image reference replacement requires a caller transaction.');
    if (!preg_match('/^[a-f0-9]{64}$/', $sourceKey)) throw new InvalidArgumentException('Invalid source key.');
    $output = medical_image_brand_source_normalize($outputUrl);
    if ($output === null || !preg_match('~^/uploads/library/medreview-branded/[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp)$~', $output['url'])) throw new InvalidArgumentException('Invalid branded output URL.');
    $outputUrl = $output['url'];
    $previousOutput = null;
    if ($previousOutputUrl !== null) {
        $previous = medical_image_brand_source_normalize($previousOutputUrl);
        if ($previous === null || !preg_match('~^/uploads/library/medreview-branded/[a-f0-9]{24}-[a-f0-9]{16}\.(?:jpg|png|webp)$~D', $previous['url'])) {
            throw new InvalidArgumentException('Invalid previous branded output URL.');
        }
        $previousOutput = $previous['url'];
    }
    medical_image_brand_source_load_exclusions($pdo);
    $updated = 0; $skipped = 0; $seen = []; $tableColumns = [];
    foreach ($refs as $ref) {
        $type = (string) ($ref['type'] ?? ''); $id = (int) ($ref['id'] ?? 0);
        if (!in_array($type, ['facility', 'doctor'], true) || $id <= 0) { $skipped++; continue; }
        if (isset($seen[$type . ':' . $id])) continue;
        $seen[$type . ':' . $id] = true;
        $table = $type === 'facility' ? 'medical_facilities' : 'medical_doctors';
        $columns = $tableColumns[$table] ??= medical_image_brand_source_columns($pdo, $table);
        if (!isset($columns['id'])) { $skipped++; continue; }
        $fields = array_keys(array_intersect_key(medical_image_brand_source_fields($type), $columns));
        if ($fields === []) { $skipped++; continue; }
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $stmt = $pdo->prepare('SELECT ' . implode(', ', $fields) . ' FROM ' . $table . ' WHERE id = :id' . $lock);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) { $skipped++; continue; }
        $patch = medical_image_brand_source_patch($row, $type, static function (string $url) use ($sourceKey, $outputUrl, $previousOutput): string {
            $identity = medical_image_brand_source_identity($url);
            if ($identity !== null && hash_equals($sourceKey, $identity['key'])) return $outputUrl;
            // A regenerated output repairs only this job's exact old output.
            // Do not resurrect removed sources or rewrite another job's image.
            if ($previousOutput !== null) {
                $current = medical_image_brand_source_normalize($url);
                if ($current !== null && $current['url'] === $previousOutput) return $outputUrl;
            }
            return $url;
        });
        if ($patch === []) { $skipped++; continue; }
        $set = []; $params = [':id' => $id];
        foreach ($patch as $field => $value) { $set[] = $field . ' = :' . $field; $params[':' . $field] = $value; }
        $stmt = $pdo->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);
        $updated += $stmt->rowCount();
    }
    // Invalidate public search only after the caller successfully commits.
    return ['updated' => $updated, 'skipped' => $skipped];
}
