<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_facility_insights.php';
require_once __DIR__ . '/medical_facility_ratings.php';
require_once __DIR__ . '/medical_review_article_price_source.php';

/** Editorial articles are not individual patient reviews. Including this file performs no I/O. */
const MEDICAL_REVIEW_ARTICLE_CONTENT_FIELDS = ['title', 'slug', 'excerpt', 'content_html', 'verdict',
    'strengths_json', 'considerations_json', 'suitable_for_json', 'analysis_sections_json', 'sources_json',
    'evidence_limitations_json', 'seo_title', 'seo_description', 'researched_at'];

function medical_review_articles_schema_ready(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT id,entity_type,entity_id,language_code,slug,status,title,excerpt,content_html,verdict,
            strengths_json,considerations_json,suitable_for_json,analysis_sections_json,sources_json,
            evidence_limitations_json,seo_title,seo_description,source_snapshot_json,source_revision,
            source_review_count,source_rating,source_rating_basis,researched_at,published_at,created_at,updated_at,
            ai_writer_claim_json,ai_content_json FROM medical_review_articles LIMIT 0');
        return true;
    } catch (PDOException) { return false; }
}

function medical_review_articles_require_schema(PDO $pdo): void
{
    if (!medical_review_articles_schema_ready($pdo)) {
        throw new RuntimeException('Chưa sẵn sàng bảng bài viết Review. Chạy php scripts/migrate_review_articles.php --apply.');
    }
}

/** Only the explicit migration scope can create this independent additive table. */
function medical_review_articles_migrate(PDO $pdo): bool
{
    if (!medreview_schema_migration_allowed()) throw new LogicException('Review article schema changes require an explicit migration scope.');
    if (medical_review_articles_schema_ready($pdo)) return false;
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS medical_review_articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type VARCHAR(16) NOT NULL DEFAULT 'facility',
            entity_id INTEGER NOT NULL, language_code VARCHAR(5) NOT NULL DEFAULT 'vi', slug VARCHAR(191) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'draft', title VARCHAR(190) NOT NULL DEFAULT '', excerpt TEXT NULL,
            content_html TEXT NULL, verdict TEXT NULL, strengths_json TEXT NULL, considerations_json TEXT NULL,
            suitable_for_json TEXT NULL, analysis_sections_json TEXT NULL, sources_json TEXT NULL,
            evidence_limitations_json TEXT NULL, seo_title VARCHAR(160) NULL, seo_description VARCHAR(300) NULL,
            source_snapshot_json TEXT NULL, source_revision CHAR(64) NULL, source_review_count INTEGER NOT NULL DEFAULT 0,
            source_rating DECIMAL(3,1) NOT NULL DEFAULT 0, source_rating_basis VARCHAR(32) NOT NULL DEFAULT 'none',
            researched_at DATETIME NULL, published_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ai_writer_claim_json TEXT NULL, ai_content_json TEXT NULL,
            UNIQUE(entity_type,entity_id,language_code), UNIQUE(language_code,slug))");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_review_articles_public ON medical_review_articles(status,language_code,published_at,id)');
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS medical_review_articles (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT, entity_type VARCHAR(16) NOT NULL DEFAULT 'facility',
            entity_id INT UNSIGNED NOT NULL, language_code VARCHAR(5) NOT NULL DEFAULT 'vi', slug VARCHAR(191) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'draft', title VARCHAR(190) NOT NULL DEFAULT '', excerpt TEXT NULL,
            content_html LONGTEXT NULL, verdict TEXT NULL, strengths_json MEDIUMTEXT NULL, considerations_json MEDIUMTEXT NULL,
            suitable_for_json MEDIUMTEXT NULL, analysis_sections_json MEDIUMTEXT NULL, sources_json MEDIUMTEXT NULL,
            evidence_limitations_json MEDIUMTEXT NULL, seo_title VARCHAR(160) NULL, seo_description VARCHAR(300) NULL,
            source_snapshot_json LONGTEXT NULL, source_revision CHAR(64) NULL, source_review_count INT UNSIGNED NOT NULL DEFAULT 0,
            source_rating DECIMAL(3,1) NOT NULL DEFAULT 0.0, source_rating_basis VARCHAR(32) NOT NULL DEFAULT 'none',
            researched_at DATETIME NULL, published_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ai_writer_claim_json MEDIUMTEXT NULL, ai_content_json MEDIUMTEXT NULL,
            PRIMARY KEY(id), UNIQUE KEY uniq_review_article_entity(entity_type,entity_id,language_code),
            UNIQUE KEY uniq_review_article_slug(language_code,slug),
            KEY idx_review_articles_public(status,language_code,published_at,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    medical_review_articles_require_schema($pdo);
    return true;
}

function medical_review_articles_locale(string $locale): string
{
    return strtolower(trim($locale)) === 'en' ? 'en' : 'vi';
}

function medical_review_articles_path(string $locale = 'vi', string $slug = ''): string
{
    $base = (medical_review_articles_locale($locale) === 'en' ? '/en' : '') . '/review';
    $slug = trim($slug, "/ \t\n\r\0\x0B");
    return $slug === '' ? $base : $base . '/' . rawurlencode($slug);
}

function medical_review_articles_json(mixed $value, string $field = 'JSON'): mixed
{
    if (is_string($value)) {
        if (strlen($value) > 1500000) throw new InvalidArgumentException($field . ' quá lớn.');
        try { return json_decode($value, true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new InvalidArgumentException($field . ' phải là JSON hợp lệ.'); }
    }
    if ($value instanceof stdClass) return get_object_vars($value);
    return $value;
}

function medical_review_articles_json_encode(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function medical_review_articles_text(mixed $value, string $field, int $max, bool $required = false): string
{
    $value = medical_facility_insights_text($value, $field, $max, $required);
    return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function medical_review_articles_text_list(mixed $value, string $field, bool $required = false): array
{
    $value = medical_review_articles_json($value, $field);
    if (!is_array($value) || !array_is_list($value) || count($value) > 30 || ($required && $value === [])) {
        throw new InvalidArgumentException($field . ' phải là danh sách tối đa 30 nhận định, không trống khi bắt buộc.');
    }
    return array_values(array_unique(array_map(static fn($entry) => medical_review_articles_text($entry, $field, 2000, true), $value)));
}

/** Deliberately no images, scripts, styles, inline handlers, embeds or arbitrary attributes. */
function medical_review_articles_sanitize_html(string $html): string
{
    if (strlen($html) > 1000000 || preg_match('//u', $html) !== 1) throw new InvalidArgumentException('content_html quá lớn hoặc không phải UTF-8.');
    if (!class_exists('DOMDocument')) return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="review-article-root">' . $html . '</div>', LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    $root = $dom->getElementById('review-article-root');
    if (!$root) return '';
    $allowed = ['p','h2','h3','h4','ul','ol','li','strong','b','em','i','br','a','blockquote'];
    $walk = static function (DOMNode $node) use (&$walk, $allowed): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) continue;
            if (!$child instanceof DOMElement) { $node->removeChild($child); continue; }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script','style','iframe','object','embed','svg','math','form','input','button','img','video','audio','link','meta'], true)) {
                $node->removeChild($child); continue;
            }
            $walk($child);
            if (!in_array($tag, $allowed, true)) {
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child); continue;
            }
            $href = $tag === 'a' ? $child->getAttribute('href') : '';
            foreach (iterator_to_array($child->attributes) as $attribute) $child->removeAttribute($attribute->name);
            if ($href !== '') {
                try { $href = medical_facility_insights_url($href, 'content_html.href'); }
                catch (InvalidArgumentException) { $href = ''; }
                if ($href !== '') {
                    $child->setAttribute('href', $href);
                    $child->setAttribute('rel', 'nofollow noopener noreferrer');
                }
            }
        }
    };
    $walk($root);
    $result = '';
    foreach ($root->childNodes as $child) $result .= $dom->saveHTML($child);
    return medical_normalize_facility_links_html(trim($result), site_canonical_origin());
}

/** Content-only contract. API metadata/status/claims are handled separately by the receiver. */
function medical_review_articles_validate_article(array $payload): array
{
    if (array_diff(array_keys($payload), MEDICAL_REVIEW_ARTICLE_CONTENT_FIELDS) !== []) throw new InvalidArgumentException('Bài Review có trường nội dung không được hỗ trợ.');
    $result = [];
    foreach (['title' => 190, 'excerpt' => 2000, 'verdict' => 4000] as $field => $limit) {
        $result[$field] = medical_review_articles_text($payload[$field] ?? null, $field, $limit, true);
        if ($result[$field] === '') throw new InvalidArgumentException($field . ' không được chỉ gồm HTML.');
    }
    $slug = $payload['slug'] ?? '';
    if (!is_string($slug)) throw new InvalidArgumentException('slug phải là chuỗi.');
    $slug = trim($slug) === '' ? slugify($result['title']) : trim($slug);
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 191) throw new InvalidArgumentException('slug cần dạng URL chữ thường, số và gạch nối, tối đa 191 ký tự.');
    $result['slug'] = $slug;
    if (!is_string($payload['content_html'] ?? null)) throw new InvalidArgumentException('content_html phải là chuỗi HTML.');
    $result['content_html'] = medical_review_articles_sanitize_html($payload['content_html']);
    $plain = trim(html_entity_decode(strip_tags($result['content_html']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (mb_strlen($plain, 'UTF-8') < 300) throw new InvalidArgumentException('content_html cần ít nhất 300 ký tự nội dung đọc được.');
    foreach (['strengths_json','considerations_json','suitable_for_json','evidence_limitations_json'] as $field) {
        $result[$field] = medical_review_articles_text_list($payload[$field] ?? [], $field, $field === 'evidence_limitations_json');
    }
    $sources = medical_review_articles_json($payload['sources_json'] ?? [], 'sources_json');
    if (!is_array($sources) || !array_is_list($sources) || count($sources) > 100 || $sources === []) throw new InvalidArgumentException('sources_json cần từ 1 đến 100 nguồn đối chiếu.');
    $sourceIds = []; $result['sources_json'] = [];
    foreach ($sources as $entry) {
        if ($entry instanceof stdClass) $entry = get_object_vars($entry);
        if (!is_array($entry) || array_is_list($entry)) throw new InvalidArgumentException('Mỗi sources_json phải là object.');
        $id = medical_review_articles_text($entry['id'] ?? null, 'sources_json.id', 80, true);
        if (isset($sourceIds[$id]) || !preg_match('/^[a-zA-Z0-9_-]+$/D', $id)) throw new InvalidArgumentException('sources_json.id cần duy nhất và chỉ gồm chữ/số/gạch nối.');
        $sourceIds[$id] = true;
        $result['sources_json'][] = ['id' => $id,
            'title' => medical_review_articles_text($entry['title'] ?? '', 'sources_json.title', 500),
            'url' => medical_facility_insights_url($entry['url'] ?? null, 'sources_json.url'),
            'platform' => medical_review_articles_text($entry['platform'] ?? '', 'sources_json.platform', 120)];
    }
    $sections = medical_review_articles_json($payload['analysis_sections_json'] ?? [], 'analysis_sections_json');
    if (!is_array($sections) || !array_is_list($sections) || count($sections) > 20) throw new InvalidArgumentException('analysis_sections_json phải là danh sách tối đa 20 mục.');
    $seen = []; $result['analysis_sections_json'] = [];
    foreach ($sections as $entry) {
        if ($entry instanceof stdClass) $entry = get_object_vars($entry);
        if (!is_array($entry) || array_is_list($entry)) throw new InvalidArgumentException('Mỗi mục phân tích cần là object.');
        $id = medical_review_articles_text($entry['id'] ?? null, 'analysis_sections_json.id', 80, true);
        if (isset($seen[$id]) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $id)) throw new InvalidArgumentException('Mã mục phân tích phải duy nhất, chữ thường và gạch nối.');
        $seen[$id] = true;
        if (!is_string($entry['content_html'] ?? null)) throw new InvalidArgumentException('Mục phân tích cần content_html.');
        $content = medical_review_articles_sanitize_html($entry['content_html']);
        if (trim(strip_tags($content)) === '') throw new InvalidArgumentException('Mục phân tích không được trống.');
        $refs = medical_review_articles_json($entry['source_ids'] ?? [], 'analysis_sections_json.source_ids');
        if (!is_array($refs) || !array_is_list($refs) || $refs === [] || count($refs) > 100) throw new InvalidArgumentException('Mỗi mục phân tích phải dẫn ít nhất một source_id.');
        foreach ($refs as $ref) if (!is_string($ref) || !isset($sourceIds[$ref])) throw new InvalidArgumentException('Mục phân tích dẫn source_id không tồn tại.');
        $result['analysis_sections_json'][] = ['id' => $id,
            'title' => medical_review_articles_text($entry['title'] ?? null, 'analysis_sections_json.title', 190, true),
            'content_html' => $content, 'source_ids' => array_values(array_unique($refs))];
    }
    foreach (['seo_title' => 160, 'seo_description' => 300] as $field => $limit) $result[$field] = medical_review_articles_text($payload[$field] ?? '', $field, $limit);
    $date = $payload['researched_at'] ?? gmdate('Y-m-d H:i:s');
    if (!is_string($date) || medical_facility_rating_checked_at($date) === '') throw new InvalidArgumentException('researched_at cần ngày ISO hợp lệ.');
    $result['researched_at'] = (new DateTimeImmutable($date, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    return $result;
}

/** Ignore arbitrary scalar legacy counters. Only real stored reviews or valid classified aggregates qualify. */
function medical_review_articles_review_metrics(array $row): array
{
    $stored = medical_facility_public_rating_summary($row);
    $external = medical_facility_rating_summary(['aggregate_ratings_json' => $row['aggregate_ratings_json'] ?? []]);
    if ($external['basis'] === 'external_aggregates' && $external['reviews_count'] > 40) return $external;
    if ($stored['reviews_count'] > 40) return $stored;
    return $external['basis'] === 'external_aggregates' ? $external : $stored;
}

function medical_review_articles_insights_meaningful(mixed $value): bool
{
    try { $object = medical_facility_insights_normalize($value); }
    catch (InvalidArgumentException) { return false; }
    $skip = ['schema_version','source','researched_at','swept_at','swept_by','backfill','backfilled_at','backfill_source',
        'richest_source','updated_at','created_at','status','version','id','facility_id','facility_name','name',
        'address','address_text','url','urls','sources','source_urls','image_urls','images','gallery','image_count','count'];
    $hasText = static function (mixed $node, int $depth = 0) use (&$hasText): bool {
        if ($depth > 10) return false;
        if (is_string($node)) return mb_strlen(trim(strip_tags($node)), 'UTF-8') >= 20;
        if ($node instanceof stdClass) $node = get_object_vars($node);
        if (is_array($node)) foreach ($node as $key => $entry) {
            if (is_string($key) && in_array(strtolower($key), ['url','id','platform','title','source_ids'], true)) continue;
            if ($hasText($entry, $depth + 1)) return true;
        }
        return false;
    };
    foreach (get_object_vars($object) as $key => $entry) if (!in_array($key, $skip, true) && $hasText($entry)) return true;
    return false;
}

function medical_review_articles_eligibility(array $source): array
{
    $reasons = [];
    if (($source['entity_type'] ?? '') !== 'facility') $reasons[] = 'facility_only';
    $facility = $source['facility'] ?? [];
    if (($facility['status'] ?? '') !== 'published') $reasons[] = 'facility_not_published';
    $metrics = $source['review_metrics'] ?? [];
    $stored = $source['stored_review_metrics'] ?? [];
    $externalCount = ($metrics['basis'] ?? '') === 'external_aggregates' ? (int) ($metrics['reviews_count'] ?? 0) : 0;
    $storedCount = ($stored['basis'] ?? '') === 'stored_reviews' ? (int) ($stored['reviews_count'] ?? 0) : 0;
    if (max($externalCount, $storedCount) <= 40) $reasons[] = 'insufficient_reviews';
    if (($source['stored_insights_valid'] ?? false) !== true || !medical_review_articles_insights_meaningful($source['facility_insights_json'] ?? null)) $reasons[] = 'insufficient_insights';
    return ['eligible' => $reasons === [], 'reasons' => $reasons, 'review_count' => max($externalCount, $storedCount),
        'external_review_count' => $externalCount, 'stored_review_count' => $storedCount,
        'sample_count' => count($source['review_samples'] ?? [])];
}

/** Selected public facts only. Never select full_json, patient data, claims or editor/private notes. */
function medical_review_articles_facility_columns(): array
{
    return ['id','translation_of_id','slug','name','category','city','subtitle','address_text','phone_text','website_url','google_maps_url',
        'hours_text','price_text','price_table_html','price_source_scope','image_url','status','language_code','featured_services_json','services_json',
        'equipment_mentioned_json','doctors_json','aggregate_ratings_json','social_links_json','facility_insights_json',
        'business_license','medical_operation_license','established_year','insurance_accepted_json','payment_methods_json',
        'languages_supported_json','parking_info','nearby_landmarks','latitude','longitude','booking_url','branch_count',
        'utilities_json','warranty_policy','intro_json','highlights_json','content'];
}

function medical_review_articles_public_fact_node(mixed $value, int $depth, int &$remaining): mixed
{
    if ($depth > 5 || --$remaining < 0) return null;
    if (is_string($value)) return mb_substr(trim(strip_tags($value)), 0, 2000, 'UTF-8');
    if (is_bool($value) || is_int($value) || is_float($value) || $value === null) return $value;
    if ($value instanceof stdClass) $value = get_object_vars($value);
    if (!is_array($value)) return null;
    $keys = ['name','title','label','text','description','summary','content','paragraphs','items','list','value',
        'category','section','subsections','heading','details','highlights','subtitle','services','type'];
    $copy = [];
    foreach (array_slice($value, 0, 50, true) as $key => $entry) {
        if (!is_int($key) && !in_array($key, $keys, true)) continue;
        $clean = medical_review_articles_public_fact_node($entry, $depth + 1, $remaining);
        if ($clean !== null) $copy[$key] = $clean;
    }
    return array_is_list($value) ? array_values($copy) : $copy;
}

/** Known public nested keys only; extension metadata is never copied into research prompts. */
function medical_review_articles_nested_facts(mixed $value, string $field): array
{
    try { $value = medical_review_articles_json($value); }
    catch (InvalidArgumentException) { return []; }
    if (!is_array($value)) return [];
    if ($field === 'aggregate_ratings_json') return medical_facility_rating_normalize_aggregates($value);
    if (in_array($field, ['intro_json','highlights_json'], true)) {
        $remaining = 500;
        return medical_review_articles_public_fact_node($value, 0, $remaining) ?? [];
    }
    $result = [];
    foreach ($value as $key => $entry) {
        if (count($result) >= 100) break;
        if ($entry instanceof stdClass) $entry = get_object_vars($entry);
        if ($field === 'social_links_json') {
            $url = is_array($entry) ? ($entry['url'] ?? $entry['href'] ?? null) : $entry;
            try { $url = medical_facility_insights_url($url, 'social_links_json.url'); }
            catch (InvalidArgumentException) { continue; }
            $platform = is_array($entry) ? ($entry['platform'] ?? $entry['name'] ?? '') : (is_string($key) ? $key : '');
            $result[] = ['platform' => is_string($platform) ? mb_substr(strip_tags($platform), 0, 120, 'UTF-8') : '', 'url' => $url];
            continue;
        }
        if (is_string($entry)) {
            $text = mb_substr(trim(strip_tags($entry)), 0, 2000, 'UTF-8');
            if ($text !== '') $result[] = $text;
            continue;
        }
        if (!is_array($entry) || array_is_list($entry)) continue;
        $fields = $field === 'doctors_json' ? ['name','title','title_text','specialty','specialty_text','role','url']
            : ['name','title','label','description','summary','category','price','price_text','unit','currency','url'];
        if ($field === 'utilities_json') $fields = ['name','title','label','text','description','summary'];
        if ($field === 'equipment_mentioned_json') $fields = ['name','title','label','description','summary','model','manufacturer','type','purpose','url'];
        $clean = [];
        foreach ($fields as $allowed) {
            if ($allowed === 'price' && (is_int($entry[$allowed] ?? null) || is_float($entry[$allowed] ?? null))) {
                if (is_finite((float) $entry[$allowed]) && $entry[$allowed] >= 0 && $entry[$allowed] <= 1000000000000) $clean[$allowed] = $entry[$allowed];
                continue;
            }
            if (!is_string($entry[$allowed] ?? null)) continue;
            if ($allowed === 'url') {
                try { $clean[$allowed] = medical_facility_insights_url($entry[$allowed], $field . '.url'); }
                catch (InvalidArgumentException) { /* Invalid references are not facts. */ }
            } else $clean[$allowed] = mb_substr(trim(strip_tags($entry[$allowed])), 0, 2000, 'UTF-8');
        }
        if ($clean !== []) $result[] = $clean;
    }
    return $result;
}

/** Publicly imported feedback is sampled anonymously, without contact identifiers. */
function medical_review_articles_anonymous_excerpt(string $value, int $limit = 3000): string
{
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu', '[email đã ẩn]', $value) ?? '';
    $value = preg_replace('/(?<![\pL\pN])(?:\+?84[ .-]?|0)(?:[ .-]?\d){8,10}(?![\pL\pN])/u', '[số liên hệ đã ẩn]', $value) ?? '';
    return mb_substr(trim($value), 0, $limit, 'UTF-8');
}

function medical_review_articles_private_redact(mixed $value): mixed
{
    if ($value instanceof stdClass) {
        $copy = new stdClass();
        foreach (get_object_vars($value) as $key => $entry) {
            if (preg_match('/patient_(?:images|notes|name|email|phone|id)|^ai_|token|secret|password|^full_json$|^notes_for_editor$|^author_(?:text|name)$/i', $key)) continue;
            $copy->{$key} = medical_review_articles_private_redact($entry);
        }
        return $copy;
    }
    if (is_array($value)) {
        $copy = [];
        foreach ($value as $key => $entry) {
            if (is_string($key) && preg_match('/patient_(?:images|notes|name|email|phone|id)|^ai_|token|secret|password|^full_json$|^notes_for_editor$|^author_(?:text|name)$/i', $key)) continue;
            $copy[$key] = medical_review_articles_private_redact($entry);
        }
        return array_is_list($value) ? array_values($copy) : $copy;
    }
    return $value;
}

function medical_review_articles_source_from_row(array $row, array $samples = []): array
{
    $facility = [];
    foreach (medical_review_articles_facility_columns() as $field) {
        if ($field === 'facility_insights_json') continue;
        if ($field === 'price_table_html') {
            $facility[$field] = medical_review_article_price_source($row[$field] ?? null);
        } elseif (in_array($field, ['latitude', 'longitude'], true)) {
            $value = $row[$field] ?? null;
            $limit = $field === 'latitude' ? 90 : 180;
            $facility[$field] = (is_int($value) || is_float($value) || is_string($value)) && is_numeric($value)
                && is_finite((float) $value) && abs((float) $value) <= $limit ? (float) $value : null;
        } elseif ($field === 'branch_count') {
            $value = $row[$field] ?? null;
            $facility[$field] = (is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]{0,2}$/D', (string) $value) === 1 ? (int) $value : null;
        } elseif (str_ends_with($field, '_json')) {
            $facility[$field] = medical_review_articles_nested_facts($row[$field] ?? [], $field);
        } else {
            $limit = $field === 'content' ? 20000 : 5000;
            $facility[$field] = in_array($field, ['id','translation_of_id','established_year'], true)
                ? (isset($row[$field]) ? (int) $row[$field] : null)
                : (is_string($row[$field] ?? null) ? mb_substr(trim(strip_tags($row[$field])), 0, $limit, 'UTF-8') : '');
        }
    }
    foreach (['website_url','google_maps_url','booking_url'] as $field) {
        try { $facility[$field] = medical_facility_insights_url($facility[$field], $field); }
        catch (InvalidArgumentException) { $facility[$field] = ''; }
    }
    $facility['language_code'] = medical_review_articles_locale($facility['language_code']);
    $facility['url'] = medical_public_entity_path('facility', $facility['slug'], $facility['language_code']);
    $facility['profile_claims_notice'] = 'Thông tin dịch vụ, bác sĩ và giấy phép là nội dung hồ sơ cơ sở công bố; không phải xác minh pháp lý hoặc bảo chứng chất lượng điều trị.';
    $valid = true;
    try { $insights = medical_review_articles_private_redact(medical_facility_insights_normalize($row['facility_insights_json'] ?? null)); }
    catch (InvalidArgumentException) { $valid = false; $insights = new stdClass(); }
    $cleanSamples = [];
    foreach (array_slice($samples, 0, 40) as $sample) {
        $rating = medical_facility_rating_score($sample['rating'] ?? null);
        if ($rating === null) continue;
        $entry = ['rating' => $rating];
        foreach (['excerpt' => 3000,'service_text' => 120,'review_date_text' => 40,'source_text' => 255] as $key => $limit) {
            $text = is_string($sample[$key] ?? null) ? $sample[$key] : '';
            $entry[$key] = medical_review_articles_anonymous_excerpt($text, $limit);
        }
        $cleanSamples[] = $entry;
    }
    $metrics = medical_review_articles_review_metrics($row);
    return ['entity_type' => 'facility', 'entity_id' => $facility['id'], 'language_code' => $facility['language_code'],
        'facility' => $facility, 'review_metrics' => $metrics,
        'stored_review_metrics' => medical_facility_public_rating_summary($row), 'review_samples' => $cleanSamples,
        'sample_count' => count($cleanSamples), 'review_count' => $metrics['reviews_count'],
        'rating' => $metrics['rating'], 'rating_basis' => $metrics['basis'],
        'facility_insights_json' => $insights, 'stored_insights_valid' => $valid];
}

/** Batch source reader; eligibility is deliberately evaluated separately. No row creation/claims here. */
function medical_review_articles_facility_sources(PDO $pdo, string $locale = 'vi', array $ids = [], int $limit = 100, int $offset = 0): array
{
    $locale = medical_review_articles_locale($locale);
    $limit = min(100, max(1, $limit)); $offset = min(1000000, max(0, $offset));
    $where = "COALESCE(NULLIF(LOWER(TRIM(f.language_code)),''),'vi')=:locale";
    $params = [':locale' => $locale];
    if ($ids !== []) {
        if (count($ids) > 100) throw new InvalidArgumentException('Tối đa 100 cơ sở mỗi lần đọc.');
        $placeholders = [];
        foreach (array_values(array_unique($ids)) as $index => $id) {
            if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string) $id) || (int) $id > 4294967295) throw new InvalidArgumentException('ID cơ sở không hợp lệ.');
            $placeholders[] = ':id' . $index; $params[':id' . $index] = (int) $id;
        }
        $where .= ' AND f.id IN (' . implode(',', $placeholders) . ')';
    }
    $columns = implode(',', array_map(static fn($field) => 'f.' . $field, medical_review_articles_facility_columns()));
    $statement = $pdo->prepare('SELECT ' . $columns . ',sr.stored_rating,sr.stored_reviews_count FROM medical_facilities f LEFT JOIN ('
        . medical_facility_stored_rating_sql() . ') sr ON sr.facility_id=f.id WHERE ' . $where . ' ORDER BY f.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset);
    $statement->execute($params); $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) return [];
    $sampleParams = []; $slots = [];
    foreach ($rows as $index => $row) { $slots[] = ':facility' . $index; $sampleParams[':facility' . $index] = (int) $row['id']; }
    // Explicit owner wins; older unlinked records may match by slug, just like the public rating helper.
    $samples = $pdo->prepare('SELECT COALESCE(r.facility_id,f.id) AS matched_facility_id,r.id,r.rating,r.excerpt,r.service_text,r.review_date_text,r.source_text
        FROM medical_reviews r LEFT JOIN medical_facilities f ON r.facility_id IS NULL AND f.slug=r.facility_slug
        WHERE r.status=\'published\' AND r.rating>0 AND r.rating<=5 AND COALESCE(r.facility_id,f.id) IN (' . implode(',', $slots) . ')
        ORDER BY r.id DESC LIMIT 5000');
    foreach ($sampleParams as $parameter => $value) $samples->bindValue($parameter, $value, PDO::PARAM_INT);
    $samples->execute(); $byId = [];
    while ($row = $samples->fetch(PDO::FETCH_ASSOC)) {
        $id = (int) $row['matched_facility_id'];
        if (count($byId[$id] ?? []) < 40) $byId[$id][] = $row;
    }
    return array_map(static fn($row) => medical_review_articles_source_from_row($row, $byId[(int) $row['id']] ?? []), $rows);
}

function medical_review_articles_facility_source(PDO $pdo, int $id, ?string $locale = null): ?array
{
    if ($id < 1) return null;
    if ($locale === null) {
        $query = $pdo->prepare('SELECT language_code FROM medical_facilities WHERE id=:id LIMIT 1');
        $query->execute([':id' => $id]); $value = $query->fetchColumn();
        if ($value === false) return null;
        $locale = medical_review_articles_locale((string) $value);
    }
    return medical_review_articles_facility_sources($pdo, $locale, [$id], 1)[0] ?? null;
}

/** Stable object-key ordering prevents harmless JSON formatting changes from invalidating a source snapshot. */
function medical_review_articles_canonical_value(mixed $value): mixed
{
    if ($value instanceof stdClass) {
        $items = get_object_vars($value); ksort($items, SORT_STRING); $copy = new stdClass();
        foreach ($items as $key => $entry) $copy->{$key} = medical_review_articles_canonical_value($entry);
        return $copy;
    }
    if (is_array($value)) {
        if (!array_is_list($value)) ksort($value, SORT_STRING);
        foreach ($value as $key => $entry) $value[$key] = medical_review_articles_canonical_value($entry);
    }
    return $value;
}

function medical_review_articles_source_snapshot(array $source): array
{
    return medical_review_articles_private_redact(array_intersect_key($source, array_flip(['entity_type','entity_id','language_code','facility','review_metrics',
        'stored_review_metrics','review_samples','sample_count','facility_insights_json','stored_insights_valid'])));
}

function medical_review_articles_source_revision(array $source): string
{
    return hash('sha256', medical_review_articles_json_encode(medical_review_articles_canonical_value(medical_review_articles_source_snapshot($source))));
}

function medical_review_articles_filters(array $query): array
{
    $result = [];
    foreach (['q' => 120,'category' => 120,'city' => 120] as $key => $limit) {
        $value = is_string($query[$key] ?? null) ? $query[$key] : '';
        $result[$key] = mb_substr(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 0, $limit, 'UTF-8');
    }
    return $result;
}

function medical_review_articles_public_scope(string $locale, array $filters = []): array
{
    $params = [':locale' => medical_review_articles_locale($locale), ':facility_locale' => medical_review_articles_locale($locale)];
    $where = "a.status='published' AND a.entity_type='facility' AND a.language_code=:locale AND f.status='published'
        AND COALESCE(NULLIF(LOWER(TRIM(f.language_code)),''),'vi')=:facility_locale
        AND TRIM(COALESCE(a.content_html,''))<>'' AND TRIM(a.title)<>'' AND TRIM(a.slug)<>''";
    $filters = medical_review_articles_filters($filters);
    foreach (['category','city'] as $field) if ($filters[$field] !== '') { $where .= ' AND f.' . $field . '=:' . $field; $params[':' . $field] = $filters[$field]; }
    if ($filters['q'] !== '') {
        $keyword = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $filters['q']) . '%';
        $parts = [];
        foreach (['a.title','a.excerpt','f.name','f.city'] as $index => $column) {
            $parts[] = $column . " LIKE :query" . $index . " ESCAPE '!'"; $params[':query' . $index] = $keyword;
        }
        $where .= ' AND (' . implode(' OR ', $parts) . ')';
    }
    return ['sql' => $where,'parameters' => $params];
}

function medical_review_articles_public_select(): string
{
    // Private snapshot, AI claims and idempotency metadata must never reach a template.
    return 'a.id,a.entity_type,a.entity_id,a.language_code,a.slug,a.status,a.title,a.excerpt,a.content_html,a.verdict,
        a.strengths_json,a.considerations_json,a.suitable_for_json,a.analysis_sections_json,a.sources_json,
        a.evidence_limitations_json,a.seo_title,a.seo_description,a.source_review_count,a.source_rating,a.source_rating_basis,
        a.researched_at,a.published_at,a.updated_at,f.id AS facility_id,f.slug AS facility_slug,f.name AS facility_name,
        f.category AS facility_category,f.city AS facility_city,f.address_text AS facility_address,f.image_url AS facility_image,
        f.translation_of_id AS facility_translation_of_id';
}

function medical_review_articles_public_dto(array $row): array
{
    $result = [];
    foreach (array_merge(['id','entity_type','entity_id','language_code','slug','status'], MEDICAL_REVIEW_ARTICLE_CONTENT_FIELDS,
        ['source_review_count','source_rating','source_rating_basis','published_at','updated_at']) as $field) {
        if ($field === 'id' || $field === 'entity_id' || $field === 'source_review_count') $result[$field] = (int) ($row[$field] ?? 0);
        elseif ($field === 'source_rating') $result[$field] = (float) ($row[$field] ?? 0);
        elseif (str_ends_with($field, '_json')) {
            try { $decoded = medical_review_articles_json($row[$field] ?? []); }
            catch (InvalidArgumentException) { $decoded = []; }
            $result[$field] = is_array($decoded) && array_is_list($decoded) ? $decoded : [];
        } else $result[$field] = (string) ($row[$field] ?? '');
    }
    $result['content_html'] = medical_review_articles_sanitize_html($result['content_html']);
    // Legacy/manual DB edits are also validated and projected at the presentation boundary.
    foreach (['strengths_json','considerations_json','suitable_for_json','evidence_limitations_json'] as $field) {
        $result[$field] = array_values(array_map(static fn($value) => mb_substr(trim(strip_tags($value)), 0, 2000, 'UTF-8'),
            array_filter(array_slice($result[$field], 0, 30), 'is_string')));
    }
    $safeSources = []; $sourceIds = [];
    foreach (array_slice($result['sources_json'], 0, 100) as $source) {
        if (!is_array($source) || !is_string($source['id'] ?? null) || isset($sourceIds[$source['id']])) continue;
        try { $url = medical_facility_insights_url($source['url'] ?? null, 'sources_json.url'); }
        catch (InvalidArgumentException) { continue; }
        $sourceIds[$source['id']] = true;
        $safeSources[] = ['id' => mb_substr(strip_tags($source['id']), 0, 80, 'UTF-8'), 'url' => $url,
            'title' => is_string($source['title'] ?? null) ? mb_substr(strip_tags($source['title']), 0, 500, 'UTF-8') : '',
            'platform' => is_string($source['platform'] ?? null) ? mb_substr(strip_tags($source['platform']), 0, 120, 'UTF-8') : ''];
    }
    $result['sources_json'] = $safeSources;
    $safeSections = [];
    foreach (array_slice($result['analysis_sections_json'], 0, 20) as $section) {
        if (!is_array($section) || !is_string($section['id'] ?? null) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $section['id'])) continue;
        $refs = is_array($section['source_ids'] ?? null) ? $section['source_ids'] : [];
        $safeSections[] = ['id' => $section['id'],
            'title' => is_string($section['title'] ?? null) ? mb_substr(strip_tags($section['title']), 0, 190, 'UTF-8') : '',
            'content_html' => medical_review_articles_sanitize_html(is_string($section['content_html'] ?? null) ? $section['content_html'] : ''),
            'source_ids' => array_values(array_filter($refs, static fn($id) => is_string($id) && isset($sourceIds[$id])))];
    }
    $result['analysis_sections_json'] = $safeSections;
    $locale = medical_review_articles_locale($result['language_code']);
    $result['url'] = medical_review_articles_path($locale, $result['slug']);
    $image = is_string($row['facility_image'] ?? null) ? trim($row['facility_image']) : '';
    if (!(str_starts_with($image, '/') && !str_starts_with($image, '//')) && !preg_match('~^https?://~i', $image)) $image = '';
    $result['cover_image_url'] = $image;
    $result['facility'] = ['id' => (int) ($row['facility_id'] ?? $result['entity_id']),
        'slug' => (string) ($row['facility_slug'] ?? ''), 'name' => (string) ($row['facility_name'] ?? ''),
        'category' => (string) ($row['facility_category'] ?? ''), 'city' => (string) ($row['facility_city'] ?? ''),
        'address_text' => (string) ($row['facility_address'] ?? ''), 'image_url' => $image,
        'url' => medical_public_entity_path('facility', (string) ($row['facility_slug'] ?? ''), $locale)];
    $words = preg_split('/\s+/u', trim(strip_tags($result['content_html'])), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $result['reading_minutes'] = max(1, (int) ceil(count($words) / 200));
    return $result;
}

/** SELECT-only directory, with one explicit language stream and literal search keywords. */
function medical_review_articles_directory_page(PDO $pdo, string $locale, array $filters = [], int $page = 1, int $perPage = 9): array
{
    medical_review_articles_require_schema($pdo);
    $from = ' FROM medical_review_articles a INNER JOIN medical_facilities f ON a.entity_type=\'facility\' AND f.id=a.entity_id WHERE ';
    $scope = medical_review_articles_public_scope($locale);
    $statement = $pdo->prepare('SELECT COUNT(*)' . $from . $scope['sql']); $statement->execute($scope['parameters']);
    $publishedTotal = (int) $statement->fetchColumn();
    $scope = medical_review_articles_public_scope($locale, $filters);
    $statement = $pdo->prepare('SELECT COUNT(*)' . $from . $scope['sql']); $statement->execute($scope['parameters']);
    $total = (int) $statement->fetchColumn(); $perPage = min(30, max(1, $perPage));
    $pages = max(1, (int) ceil($total / $perPage)); $page = min(max(1, $page), $pages); $offset = ($page - 1) * $perPage;
    $statement = $pdo->prepare('SELECT ' . medical_review_articles_public_select() . $from . $scope['sql']
        . ' ORDER BY a.published_at DESC,a.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
    $statement->execute($scope['parameters']);
    $items = array_map('medical_review_articles_public_dto', $statement->fetchAll(PDO::FETCH_ASSOC));
    $facetScope = medical_review_articles_public_scope($locale);
    $facetsQuery = $pdo->prepare('SELECT DISTINCT f.category,f.city' . $from . $facetScope['sql'] . ' ORDER BY f.category ASC,f.city ASC');
    $facetsQuery->execute($facetScope['parameters']); $cities = []; $categories = [];
    foreach ($facetsQuery->fetchAll(PDO::FETCH_ASSOC) as $facet) {
        if (trim((string) $facet['city']) !== '') $cities[(string) $facet['city']] = true;
        if (trim((string) $facet['category']) !== '') $categories[(string) $facet['category']] = true;
    }
    return ['total' => $total,'published_total' => $publishedTotal,'page' => $page,'pages' => $pages,'per_page' => $perPage,
        'offset' => $offset,'start' => $total > 0 ? $offset + 1 : 0,'end' => min($offset + $perPage, $total),
        'items' => $items,'facets' => ['cities' => array_keys($cities),'categories' => array_keys($categories)]];
}

function medical_review_articles_find_public(PDO $pdo, string $slug, string $locale = 'vi'): ?array
{
    medical_review_articles_require_schema($pdo);
    $scope = medical_review_articles_public_scope($locale);
    $statement = $pdo->prepare('SELECT ' . medical_review_articles_public_select()
        . ' FROM medical_review_articles a INNER JOIN medical_facilities f ON a.entity_type=\'facility\' AND f.id=a.entity_id WHERE '
        . $scope['sql'] . ' AND a.slug=:slug LIMIT 1');
    $statement->execute($scope['parameters'] + [':slug' => $slug]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) return null;
    $article = medical_review_articles_public_dto($row);
    $alternates = $pdo->prepare("SELECT a.language_code,a.slug FROM medical_review_articles a
        INNER JOIN medical_facilities f ON a.entity_type='facility' AND f.id=a.entity_id
        WHERE a.entity_type='facility' AND (f.id=:family_id OR f.translation_of_id=:translation_parent) AND a.status='published' AND f.status='published'
        AND a.language_code IN ('vi','en') AND COALESCE(NULLIF(LOWER(TRIM(f.language_code)),''),'vi')=a.language_code
        AND TRIM(COALESCE(a.content_html,''))<>'' AND TRIM(a.title)<>'' AND TRIM(a.slug)<>''");
    $parentId = (int) ($row['facility_translation_of_id'] ?? 0);
    $familyId = $parentId > 0 ? $parentId : (int) $article['entity_id'];
    $alternates->execute([':family_id' => $familyId, ':translation_parent' => $familyId]);
    $article['alternates'] = [];
    foreach ($alternates->fetchAll(PDO::FETCH_ASSOC) as $alternate) {
        $code = medical_review_articles_locale((string) $alternate['language_code']);
        $article['alternates'][$code] = medical_review_articles_path($code, (string) $alternate['slug']);
    }
    return $article;
}
