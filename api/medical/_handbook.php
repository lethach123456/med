<?php
declare(strict_types=1);

/** Shared, independently testable Cẩm nang API operations. Loading this file never opens the DB. */
require_once __DIR__ . '/../../medical_handbook_ai.php';

const HANDBOOK_API_MAX_BODY_BYTES = 24 * 1024 * 1024;
const HANDBOOK_API_LEASE_SECONDS = 600;
const HANDBOOK_API_HEARTBEAT_SECONDS = 45;
const HANDBOOK_API_QUEUE_ENDPOINT = '/api/medical/handbooks-needing-content.php';
const HANDBOOK_API_RECEIVE_ENDPOINT = '/api/medical/handbook-content-update.php';

final class HandbookApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly string $reason = '')
    {
        parent::__construct($message);
    }
}

function handbook_api_bootstrap(): PDO
{
    require_once __DIR__ . '/_auth.php';
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Vary: Origin');
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $allowed = ['https://chatgpt.com', 'https://grok.com', 'https://x.com', 'https://www.x.com',
        'https://gemini.google.com', 'https://claude.ai'];
    if (in_array($origin, $allowed, true) || preg_match('/^chrome-extension:\/\/[a-p]{32}$/D', $origin) === 1) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: X-Medical-Api-Key, Content-Type');
        header('Access-Control-Max-Age: 600');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
    medical_api_auth();
    try {
        $pdo = db();
        handbook_ai_require_schema($pdo);
        return $pdo;
    } catch (Throwable $e) {
        // Avoid exposing DB details or credentials in a public error response.
        error_log('Handbook API schema unavailable (' . get_class($e) . ').');
        json_response(['ok' => false, 'message' => 'Chưa sẵn sàng schema Cẩm nang. Chạy php scripts/migrate_handbook_ai.php.',
            'reason' => 'schema_unavailable'], 503);
    }
}

function handbook_api_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function handbook_api_decode_json(mixed $raw): array
{
    if (!is_string($raw) || $raw === '') return [];
    try { $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { return []; }
    return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
}

function handbook_api_positive_id(mixed $value, string $field = 'id'): int
{
    if ((!is_int($value) && !is_string($value)) || preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value) !== 1) {
        throw new HandbookApiException($field . ' phải là số nguyên dương hợp lệ.');
    }
    $id = (int) $value;
    if ($id > 2147483647) throw new HandbookApiException($field . ' vượt giới hạn.');
    return $id;
}

function handbook_api_text(mixed $value, string $field, int $max, bool $required = false): string
{
    if ($value === null && !$required) return '';
    if (!is_string($value)) throw new HandbookApiException($field . ' phải là chuỗi.');
    $text = trim($value);
    if (($required && $text === '') || strlen($text) > $max || preg_match('/[\x00-\x1f\x7f]/', $text)) {
        throw new HandbookApiException($field . ' trống, quá dài hoặc chứa ký tự không hợp lệ.');
    }
    return $text;
}

/** Bound the stream itself, not only the caller-controlled Content-Length header. */
function handbook_api_read_body(int $maxBytes = HANDBOOK_API_MAX_BODY_BYTES): array
{
    $type = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($type !== 'application/json') throw new HandbookApiException('Content-Type phải là application/json.', 415);
    $length = $_SERVER['CONTENT_LENGTH'] ?? null;
    if ($length !== null && (!is_scalar($length) || !ctype_digit((string) $length))) {
        throw new HandbookApiException('Content-Length không hợp lệ.', 400);
    }
    if ($length !== null && (float) $length > $maxBytes) throw new HandbookApiException('JSON vượt giới hạn dung lượng.', 413);
    $stream = fopen('php://input', 'rb');
    if ($stream === false) throw new HandbookApiException('Không đọc được JSON.', 400);
    try { $raw = stream_get_contents($stream, $maxBytes + 1); }
    finally { fclose($stream); }
    if (!is_string($raw) || strlen($raw) > $maxBytes) throw new HandbookApiException('JSON vượt giới hạn dung lượng.', 413);
    return handbook_api_parse_body($raw, $maxBytes);
}

/** Pure strict JSON parser, also exercised without HTTP or a runtime DB. */
function handbook_api_parse_body(string $raw, int $maxBytes = HANDBOOK_API_MAX_BODY_BYTES): array
{
    if (strlen($raw) > $maxBytes) throw new HandbookApiException('JSON vượt giới hạn dung lượng.', 413);
    // BASE64 has no commas/brackets. Bound container/field amplification before
    // json_decode allocates a huge PHP array from a small hostile JSON body.
    if (substr_count($raw, ',') > 10000 || substr_count($raw, '{') > 256 || substr_count($raw, '[') > 256) {
        throw new HandbookApiException('JSON có cấu trúc quá phức tạp cho một bài Cẩm nang.', 400);
    }
    try { $body = json_decode($raw, true, 48, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new HandbookApiException('Body không phải JSON hợp lệ.', 400); }
    unset($raw);
    if (!is_array($body) || array_is_list($body)) throw new HandbookApiException('Body phải là một JSON object.', 400);
    return $body;
}

function handbook_api_queue_filters(array $query): array
{
    $page = isset($query['page']) ? handbook_api_positive_id($query['page'], 'page') : 1;
    $limit = isset($query['limit']) ? handbook_api_positive_id($query['limit'], 'limit') : 10;
    if ($limit > 25) throw new HandbookApiException('Mỗi trang tối đa 25 bài.');
    $language = handbook_api_text($query['language'] ?? '', 'language', 2);
    if ($language !== '' && !in_array($language, ['vi', 'en'], true)) throw new HandbookApiException('language phải là vi hoặc en.');
    $ids = [];
    if (isset($query['ids'])) {
        if (!is_string($query['ids']) || strlen($query['ids']) > 300) throw new HandbookApiException('ids phải là danh sách ID phân cách bởi dấu phẩy.');
        foreach (explode(',', $query['ids']) as $id) { $parsed = handbook_api_positive_id(trim($id)); $ids[$parsed] = $parsed; }
        if (count($ids) > 25) throw new HandbookApiException('Tối đa 25 ID.');
    }
    return ['page' => $page, 'limit' => $limit, 'language' => $language, 'ids' => array_values($ids)];
}

function handbook_api_public_claim(array $claim, int $now): ?array
{
    if ((int) ($claim['expires_at'] ?? 0) <= $now) return null;
    return array_intersect_key($claim, array_flip(['instance_id', 'instance_label', 'account_label', 'worker_id',
        'provider', 'model', 'task', 'claimed_at', 'heartbeat_at', 'expires_at']));
}

function handbook_api_scoped_row(array $row): bool
{
    $language = strtolower(trim((string) ($row['language'] ?? ''))) ?: 'vi';
    return in_array((string) ($row['category_slug'] ?? ''), ['blog', 'blog-en'], true)
        && in_array($language, ['vi', 'en'], true);
}

function handbook_api_row(PDO $pdo, int $id, bool $lock = false): ?array
{
    $locking = $lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare('SELECT p.*,c.slug AS category_slug FROM posts p INNER JOIN categories c ON c.id=p.category_id WHERE p.id=:id LIMIT 1' . $locking);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) && handbook_api_scoped_row($row) ? $row : null;
}

function handbook_api_list(PDO $pdo, array $query, ?int $now = null): array
{
    $now ??= time();
    $filter = handbook_api_queue_filters($query);
    $languageSql = "COALESCE(NULLIF(LOWER(TRIM(p.language)),''),'vi')";
    $where = "p.status='draft' AND c.slug IN ('blog','blog-en') AND {$languageSql} IN ('vi','en')"
        . " AND TRIM(COALESCE(p.content,''))='' AND TRIM(COALESCE(p.title,''))<>'' AND TRIM(COALESCE(p.slug,''))<>''"
        . " AND (CASE WHEN JSON_VALID(p.ai_writer_claim_json) THEN COALESCE(CAST(JSON_EXTRACT(p.ai_writer_claim_json,'$.expires_at') AS DECIMAL(20,0)),0) ELSE 0 END)<=:now";
    $params = [':now' => $now];
    if ($filter['language'] !== '') { $where .= " AND {$languageSql}=:language"; $params[':language'] = $filter['language']; }
    if ($filter['ids'] !== []) {
        $keys = [];
        foreach ($filter['ids'] as $index => $id) { $key = ':id_' . $index; $keys[] = $key; $params[$key] = $id; }
        $where .= ' AND p.id IN (' . implode(',', $keys) . ')';
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM posts p INNER JOIN categories c ON c.id=p.category_id WHERE ' . $where);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = (int) ceil($total / $filter['limit']);
    $page = min($filter['page'], max(1, $pages));
    $stmt = $pdo->prepare('SELECT p.*,c.slug AS category_slug FROM posts p INNER JOIN categories c ON c.id=p.category_id WHERE '
        . $where . ' ORDER BY p.id ASC LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->bindValue(':limit', $filter['limit'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $filter['limit'], PDO::PARAM_INT);
    $stmt->execute();
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!handbook_ai_needs_content($row)) continue;
        $prompt = handbook_ai_prompt($pdo, $row);
        $items[] = ['id' => (int) $row['id'], 'type' => 'handbook', 'prompt_type' => 'handbook',
            'title' => (string) $row['title'], 'topic' => (string) $row['title'], 'slug' => (string) $row['slug'],
            'language' => strtolower(trim((string) ($row['language'] ?? ''))) ?: 'vi',
            'source_revision' => handbook_ai_revision($row), 'needs_content' => true,
            'prompt' => (string) $prompt['prompt'], 'prompt_label' => (string) $prompt['label'],
            'prompt_source' => (string) $prompt['prompt_source'], 'output_template' => $prompt['output_template']];
    }
    return ['ok' => true, 'type' => 'handbook', 'contract_version' => 1, 'page' => $page, 'limit' => $filter['limit'],
        'total' => $total, 'pages' => $pages, 'checked_ids' => $filter['ids'],
        'eligibility' => 'draft_handbook_with_empty_content', 'claim_required' => true,
        'claim_endpoint' => HANDBOOK_API_QUEUE_ENDPOINT, 'receive_endpoint' => HANDBOOK_API_RECEIVE_ENDPOINT,
        'lease_seconds' => HANDBOOK_API_LEASE_SECONDS, 'heartbeat_interval_seconds' => HANDBOOK_API_HEARTBEAT_SECONDS,
        'max_body_bytes' => HANDBOOK_API_MAX_BODY_BYTES, 'limits' => handbook_ai_limits(), 'save_status' => 'draft', 'items' => $items];
}

function handbook_api_write_claim(PDO $pdo, int $id, ?array $claim): void
{
    // Claims are operational metadata, not editorial changes; preserve the revision timestamp.
    $pdo->prepare('UPDATE posts SET ai_writer_claim_json=:claim,updated_at=updated_at WHERE id=:id')
        ->execute([':claim' => $claim === null ? null : handbook_api_json($claim), ':id' => $id]);
}

function handbook_api_lease(PDO $pdo, array $body, ?int $now = null): array
{
    $id = handbook_api_positive_id($body['id'] ?? null);
    $action = handbook_api_text($body['action'] ?? 'claim', 'action', 12, true);
    if (!in_array($action, ['claim', 'heartbeat', 'release', 'status'], true)) throw new HandbookApiException('action không hợp lệ.');
    $token = $action === 'heartbeat' || $action === 'release'
        ? handbook_api_text($body['claim_token'] ?? null, 'claim_token', 64, true) : '';
    $client = $body['client'] ?? $body;
    if (!is_array($client) || array_is_list($client)) throw new HandbookApiException('client phải là JSON object.');
    if ($action === 'claim') {
        $revision = handbook_api_text($body['source_revision'] ?? null, 'source_revision', 64, true);
        if (preg_match('/^[a-f0-9]{64}$/D', $revision) !== 1) throw new HandbookApiException('source_revision không hợp lệ.');
        $instance = handbook_api_text($client['instance_id'] ?? null, 'instance_id', 100, true);
        $worker = handbook_api_text($client['worker_id'] ?? null, 'worker_id', 120, true);
        $request = handbook_api_text($body['request_id'] ?? null, 'request_id', 120, true);
        $labels = [];
        foreach (['instance_label' => 100, 'account_label' => 100, 'provider' => 32, 'model' => 80] as $field => $max) {
            $labels[$field] = handbook_api_text($client[$field] ?? '', $field, $max);
        }
    }
    try {
        $pdo->beginTransaction();
        $row = handbook_api_row($pdo, $id, $action !== 'status');
        $now ??= time(); // A lock wait must not let an expired writer use an old clock.
        if ($row === null) throw new HandbookApiException('Không tìm thấy bài Cẩm nang.', 404, 'not_found');
        $claim = handbook_api_decode_json($row['ai_writer_claim_json'] ?? null);
        $active = (int) ($claim['expires_at'] ?? 0) > $now;
        if ($action === 'status') {
            $pdo->commit();
            return ['ok' => true, 'type' => 'handbook', 'id' => $id, 'claimed' => $active,
                'writer' => handbook_api_public_claim($claim, $now), 'server_now' => $now,
                'lease_seconds' => HANDBOOK_API_LEASE_SECONDS, 'needs_content' => handbook_ai_needs_content($row)];
        }
        if ($action !== 'claim') {
            if (!$active || !isset($claim['claim_token']) || !hash_equals((string) $claim['claim_token'], $token)) {
                throw new HandbookApiException('Quyền giữ bài đã mất hoặc hết hạn; hãy kiểm tra và nhận bài lại.', 409, 'lease_lost');
            }
            if ($action === 'release') {
                handbook_api_write_claim($pdo, $id, null);
                $pdo->commit();
                return ['ok' => true, 'type' => 'handbook', 'id' => $id, 'released' => true, 'server_now' => $now];
            }
            if (!handbook_ai_needs_content($row)) throw new HandbookApiException('Bài đã có nội dung hoặc không còn cần viết.', 409, 'not_eligible');
            if (!hash_equals((string) ($claim['source_revision'] ?? ''), handbook_ai_revision($row))) {
                throw new HandbookApiException('Chủ đề đã được sửa; tải lại trước khi viết.', 409, 'source_revision_changed');
            }
        } else {
            if (!handbook_ai_needs_content($row)) throw new HandbookApiException('Bài đã có nội dung hoặc không còn cần viết.', 409, 'not_eligible');
            if (!hash_equals(handbook_ai_revision($row), $revision)) {
                throw new HandbookApiException('Chủ đề đã được sửa; tải lại trước khi nhận bài.', 409, 'source_revision_changed');
            }
            if ($active) {
                $same = hash_equals((string) ($claim['instance_id'] ?? ''), $instance)
                    && hash_equals((string) ($claim['worker_id'] ?? ''), $worker)
                    && hash_equals((string) ($claim['request_id'] ?? ''), $request);
                if (!$same) throw new HandbookApiException('Bài đang được xử lý ở máy hoặc tài khoản khác.', 409, 'already_claimed');
                if (!hash_equals((string) ($claim['source_revision'] ?? ''), $revision)) {
                    throw new HandbookApiException('Chủ đề đã thay đổi từ khi giữ bài; hủy lượt cũ và tải lại.', 409, 'source_revision_changed');
                }
            } else {
                $claim = array_merge($labels, ['claim_token' => bin2hex(random_bytes(32)), 'instance_id' => $instance,
                    'worker_id' => $worker, 'request_id' => $request, 'task' => 'handbook_article',
                    'source_revision' => $revision, 'claimed_at' => gmdate('c', $now)]);
            }
        }
        $claim['heartbeat_at'] = gmdate('c', $now);
        $claim['expires_at'] = $now + HANDBOOK_API_LEASE_SECONDS;
        handbook_api_write_claim($pdo, $id, $claim);
        $pdo->commit();
        return ['ok' => true, 'type' => 'handbook', 'id' => $id, 'claimed' => true,
            'claim_token' => $claim['claim_token'], 'source_revision' => handbook_ai_revision($row),
            'server_now' => $now, 'expires_at' => $claim['expires_at'], 'lease_seconds' => HANDBOOK_API_LEASE_SECONDS,
            'heartbeat_interval_seconds' => HANDBOOK_API_HEARTBEAT_SECONDS];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Save one leased article atomically. Media callback is injectable for isolated tests. */
function handbook_api_save(PDO $pdo, array $body, ?callable $prepareMedia = null, ?callable $cleanupMedia = null, ?int $now = null): array
{
    $id = handbook_api_positive_id($body['id'] ?? null);
    $token = handbook_api_text($body['claim_token'] ?? $body['writer_claim_token'] ?? null, 'claim_token', 64, true);
    if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) throw new HandbookApiException('claim_token không hợp lệ.');
    $request = handbook_api_text($body['request_id'] ?? null, 'request_id', 120, true);
    $revision = handbook_api_text($body['source_revision'] ?? null, 'source_revision', 64, true);
    if (preg_match('/^[a-f0-9]{64}$/D', $revision) !== 1) throw new HandbookApiException('source_revision không hợp lệ.');
    $created = [];
    $commitStarted = false;
    if ($prepareMedia === null || $cleanupMedia === null) require_once __DIR__ . '/../../medical_handbook_media.php';
    $prepareMedia ??= 'handbook_ai_prepare_article_html';
    $cleanupMedia ??= 'handbook_ai_cleanup_created_files';
    try {
        $pdo->beginTransaction();
        $row = handbook_api_row($pdo, $id, true);
        $now ??= time();
        if ($row === null) throw new HandbookApiException('Không tìm thấy bài Cẩm nang.', 404, 'not_found');
        $normalized = handbook_ai_validate_payload($body, $row);
        $hash = handbook_ai_payload_hash($normalized);
        $previous = handbook_api_decode_json($row['ai_content_json'] ?? null);
        if (($previous['request_id'] ?? null) === $request) {
            if (!hash_equals((string) ($previous['claim_token_sha256'] ?? ''), hash('sha256', $token))
                || !hash_equals((string) ($previous['payload_sha256'] ?? ''), $hash)
                || !hash_equals((string) ($previous['source_revision'] ?? ''), $revision)) {
                throw new HandbookApiException('request_id đã được dùng với dữ liệu khác.', 409, 'idempotency_conflict');
            }
            $pdo->commit();
            return ['ok' => true, 'type' => 'handbook', 'id' => $id, 'status' => (string) $row['status'],
                'idempotent' => true, 'images' => $previous['images'] ?? [],
                'images_count' => count(is_array($previous['images'] ?? null) ? $previous['images'] : [])];
        }
        if (!handbook_ai_needs_content($row)) throw new HandbookApiException('Bài đã có nội dung hoặc không còn cần viết; không ghi đè.', 409, 'not_eligible');
        $claim = handbook_api_decode_json($row['ai_writer_claim_json'] ?? null);
        if ((int) ($claim['expires_at'] ?? 0) <= $now
            || !hash_equals((string) ($claim['claim_token'] ?? ''), $token)
            || !hash_equals((string) ($claim['request_id'] ?? ''), $request)) {
            throw new HandbookApiException('Quyền giữ bài đã mất hoặc hết hạn; cần nhận bài lại trước khi gửi.', 409, 'lease_lost');
        }
        if (!hash_equals((string) ($claim['source_revision'] ?? ''), $revision)
            || !hash_equals(handbook_ai_revision($row), $revision)) {
            throw new HandbookApiException('Chủ đề hoặc dữ liệu bài đã thay đổi. Tải lại bài trước khi viết.', 409, 'source_revision_changed');
        }
        try { $media = $prepareMedia($normalized['content_html'], $id); }
        catch (RuntimeException $error) {
            throw new HandbookApiException('Máy chủ chưa thể nén hoặc lưu ảnh. Kiểm tra GD, DOM, memory_limit và quyền ghi uploads/handbook.', 503, 'image_processing_unavailable');
        }
        $created = is_array($media['created_files'] ?? null) ? $media['created_files'] : [];
        if (!is_string($media['content'] ?? null) || !is_array($media['images'] ?? null)) throw new RuntimeException('Invalid media preparation result.');
        if (mb_strlen(trim(html_entity_decode(strip_tags($media['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'UTF-8') < 200) {
            throw new InvalidArgumentException('Sau khi làm sạch HTML, bài cần ít nhất 200 ký tự nội dung.');
        }
        $images = $media['images'];
        $firstImage = $images[0] ?? null;
        $featured = is_array($firstImage) ? (string) ($firstImage['url'] ?? $firstImage['src'] ?? '') : (is_string($firstImage) ? $firstImage : '');
        $metadata = ['contract_version' => 1, 'request_id' => $request, 'payload_sha256' => $hash,
            'claim_token_sha256' => hash('sha256', $token), 'source_revision' => $revision,
            'language' => $normalized['language'], 'sources_json' => $normalized['sources_json'],
            'images' => $images, 'processed_at' => gmdate('c', $now), 'status' => 'draft'];
        $statement = $pdo->prepare("UPDATE posts SET title=:title,excerpt=:excerpt,content=:content,seo_title=:seo_title,"
            . "seo_description=:seo_description,language=:language,featured_image_url=:featured,template=0,status='draft',"
            . 'ai_content_json=:metadata,ai_writer_claim_json=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $statement->execute([':title' => $normalized['title'], ':excerpt' => $normalized['excerpt'], ':content' => $media['content'],
            ':seo_title' => $normalized['seo_title'], ':seo_description' => $normalized['seo_description'], ':language' => $normalized['language'],
            ':featured' => $featured !== '' ? $featured : null, ':metadata' => handbook_api_json($metadata), ':id' => $id]);
        $commitStarted = true;
        $pdo->commit();
        return ['ok' => true, 'type' => 'handbook', 'id' => $id, 'status' => 'draft', 'idempotent' => false,
            'images_count' => count($images), 'images' => $images,
            'message' => 'Đã lưu bài nháp và ảnh tối ưu trên hosting; cần quản trị viên kiểm tra trước khi xuất bản.'];
    } catch (Throwable $e) {
        $rollbackConfirmed = false;
        if ($pdo->inTransaction()) {
            try { $rollbackConfirmed = $pdo->rollBack(); } catch (Throwable) { /* Preserve the original error. */ }
        }
        // A lost COMMIT acknowledgement may mean the DB already references these
        // files. Preserve them rather than break the saved article. A retry uses
        // request_id/payload hash to reconcile the actual DB outcome safely.
        if ($created !== [] && $commitStarted && !$rollbackConfirmed) error_log('Handbook commit outcome uncertain; preserved media for post ' . $id . '.');
        if ($created !== [] && (!$commitStarted || $rollbackConfirmed)) {
            try { $cleanupMedia($created); }
            catch (Throwable $cleanupError) { error_log('Handbook media rollback cleanup failed.'); }
        }
        throw $e;
    }
}

function handbook_api_error(Throwable $error): never
{
    if ($error instanceof HandbookApiException) {
        json_response(['ok' => false, 'type' => 'handbook', 'message' => $error->getMessage(), 'reason' => $error->reason], $error->status);
    }
    if ($error instanceof InvalidArgumentException) {
        json_response(['ok' => false, 'type' => 'handbook', 'message' => $error->getMessage(), 'reason' => 'invalid_payload'], 422);
    }
    error_log('Handbook API operation failed (' . get_class($error) . ').');
    json_response(['ok' => false, 'type' => 'handbook', 'message' => 'Không thể xử lý Cẩm nang lúc này. Thử lại sau.', 'reason' => 'server_error'], 500);
}
