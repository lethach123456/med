<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/medical_media_library.php';
require_once dirname(__DIR__) . '/medical_media_worker.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
};
$reject = static function (callable $operation, string $message, ?string $reason = null) use ($assert): void {
    try { $operation(); } catch (InvalidArgumentException|MedicalFacilityImageFixConflict $e) {
        $assert($reason === null || ($e instanceof MedicalFacilityImageFixConflict && $e->reason === $reason), $message . ': reason');
        return;
    }
    $assert(false, $message . ': should reject');
};
$token = str_repeat('a', 64);
$row = ['id' => 71, 'name' => 'Nha khoa kiểm thử', 'slug' => 'test-clinic', 'status' => 'published', 'language_code' => 'vi',
    'address_text' => 'Địa chỉ chi nhánh A', 'website_url' => 'https://clinic.example', 'google_maps_url' => 'https://maps.google.com/?cid=71',
    'image_url' => 'https://clinic.example/broken.jpg', 'ai_image_url' => '/uploads/library/ai.jpg',
    'content' => '<p>Existing article must stay intact.</p>',
    'gallery_json' => json_encode(['https://clinic.example/broken.jpg', ['url' => 'https://clinic.example/good.jpg', 'caption' => 'Caption cũ', 'source' => 'Website', 'custom' => 123], 'https://clinic.example/blocked.jpg']),
    'ai_writer_claim_json' => json_encode(['task' => 'facility_image_fix', 'claim_token' => $token, 'expires_at' => time() + 180, 'provider' => 'gemini']),
    'image_fix_json' => null];
$inventory = medical_facility_image_fix_inventory($row);
$assert(count($inventory) === 4, 'deduplicate cover/gallery, include separate AI image');
$assert(count($inventory[0]['fields']) === 2, 'inventory preserves all source slots');
$assert($inventory[1]['inspection_url'] === 'https://medreview.vn/uploads/library/ai.jpg', 'AI can inspect local image via public absolute URL');
$revision = medical_facility_image_fix_revision($row);
$assert(medical_facility_image_fix_revision($row + ['updated_at' => 'changed-by-heartbeat']) === $revision, 'lease heartbeat does not invalidate images');
$assert(medical_facility_image_fix_revision(array_replace($row, ['address_text' => 'branch B'])) !== $revision, 'identity change invalidates old image investigation');
$item = medical_facility_image_fix_output_template($row);
$item['writer_claim_token'] = $token;
foreach ($item['inspected_images'] as &$review) {
    if ($review['url'] === $row['image_url']) {
        $review['decision'] = 'remove'; $review['reason'] = 'HTTP 404'; $review['http_status'] = 404; $review['evidence_url'] = $row['image_url'];
    } elseif (str_contains($review['url'], 'good.jpg')) $review['decision'] = 'keep';
    else { $review['decision'] = 'uncertain'; $review['reason'] = 'Cannot inspect'; }
}
unset($review);
$item['added_images'] = [['url' => 'https://lh3.googleusercontent.com/p/' . str_repeat('x', 300) . '=s1600', 'source' => 'Google Maps',
    'source_url' => 'https://maps.google.com/?cid=71', 'caption' => 'Mặt tiền đúng chi nhánh']];
$patch = medical_facility_image_fix_patch($row, $item);
$gallery = json_decode($patch['gallery_json'], true);
$assert(count($gallery) === 3 && count($patch['removed_images']) === 1 && count($patch['added_images']) === 1, 'remove broken, preserve uncertain, add verified-source candidate');
$assert($gallery[0]['custom'] === 123 && $gallery[0]['caption'] === 'Caption cũ', 'existing rich metadata preserved');
$assert($patch['image_url'] === 'https://clinic.example/good.jpg', 'removed cover replaced from surviving gallery');
$assert($patch['ai_image_url'] === $row['ai_image_url'], 'uncertain AI image remains');
$longCover = $item; $longCover['image_url'] = $item['added_images'][0]['url'];
$assert(strlen(medical_facility_image_fix_patch($row, $longCover)['image_url']) > 255, 'long Google URL supported without truncation');
$minimalAddition = $item;
$minimalAddition['added_images'] = [array_intersect_key($item['added_images'][0], array_flip(['url', 'source', 'source_url']))];
$minimalPatch = medical_facility_image_fix_patch($row, $minimalAddition);
$assert($minimalPatch['added_images'][0]['angle'] === '' && $minimalPatch['added_images'][0]['caption'] === '', 'new photo needs URL/source/source_url, optional caption and angle default empty');
$invalid = $item; array_pop($invalid['inspected_images']);
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'omitted source image cannot be deleted');
$invalid = $item; $invalid['inspected_images'][1] = $invalid['inspected_images'][0];
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'duplicate decisions rejected');
$invalid = $item; $invalid['inspected_images'][0]['url'] = 'https://other.example/unknown.jpg';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'unknown removal rejected');
$invalid = $item; $invalid['inspected_images'][0]['evidence_url'] = '';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'removal without evidence rejected');
foreach ([401, 403, 408, 429, 500, 503] as $status) {
    $invalid = $item; $invalid['inspected_images'][0]['http_status'] = $status;
    $reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'temporary access failure not proof of bad image: ' . $status);
}
foreach (['https://maps.google.com/?cid=71', 'https://clinic.example/page.html', 'http://127.0.0.1/a.jpg', 'http://[::1]/a.jpg', 'http://localhost/a.jpg', 'http://service.internal/a.jpg', 'file:///etc/passwd', 'data:image/png;base64,abc', 'https://user:pass@clinic.example/a.jpg', 'https://clinic.example/a.jpg?key=secret', 'https://clinic.example:3306/a.jpg'] as $url) {
    $invalid = $item; $invalid['added_images'][0]['url'] = $url;
    $reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'invalid new image URL rejected: ' . $url);
}
$invalid = $item; $invalid['added_images'][0]['source_url'] = '';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'new image requires source link');
$invalid = $item; $invalid['added_images'][0]['url'] = 'https://clinic.example/good.jpg';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'existing source cannot be added again');
$invalid = $item; $invalid['ai_image_url'] = '';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'cannot clear AI image without remove decision');
$invalid = $item; $invalid['image_url'] = 'https://clinic.example/unknown.jpg';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'cover must be a known surviving image');
$invalid = $item; $invalid['insufficient_images'] = 'false';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'boolean schema enforced');
$badGallery = array_replace($row, ['gallery_json' => '{bad-json']);
$reject(fn() => medical_facility_image_fix_inventory($badGallery), 'malformed gallery never silently treated as empty');
$public = medical_facility_image_fix_public_claim($row['ai_writer_claim_json']);
$assert($public !== null && !isset($public['claim_token']), 'queue never exposes lease secret');
[, $default] = medical_facility_image_fix_prompt_default();
$prompt = medical_facility_image_fix_prompt($default, $row);
$assert(str_contains($prompt, 'BLOCK CODE') && str_contains($prompt, 'Google Maps') && str_contains($prompt, $revision), 'prompt includes exact revision, evidence investigation and code-block contract');
$assert(!str_contains($prompt, $token), 'writer claim token excluded from AI prompt');
$customPrompt = medical_facility_image_fix_prompt('Admin edited prompt', $row, 9);
$assert(str_contains($customPrompt, 'ƯU TIÊN NGUỒN ẢNH THẬT') && str_contains($customPrompt, 'ảnh người dùng chụp thực tế'), 'saved custom templates also prioritize real Google Maps photos');
$assert(str_contains($customPrompt, 'tối thiểu 5 ảnh') && str_contains($customPrompt, 'Facebook/fanpage') && str_contains($customPrompt, 'nguồn công khai khác trên mạng'), 'saved prompts get minimum five and multi-source fallback');
$assert(str_contains($customPrompt, 'không tính ảnh AI, ảnh trùng hoặc uncertain') && str_contains($customPrompt, 'insufficient_images=true'), 'minimum does not invent or count unverified images');
$assert(str_contains($default, 'GIỚI THIỆU KHÔNG GIAN THỰC TẾ') && str_contains($default, 'ảnh bìa website'), 'default prompt is a real facility photo gallery, not branding');
$assert(str_contains($customPrompt, 'KHÔNG lấy logo đứng riêng') && str_contains($customPrompt, 'poster khuyến mãi') && str_contains($customPrompt, 'không tính vào số tối thiểu'), 'saved custom prompts also exclude logos covers and designed adverts');
$assert(str_contains($customPrompt, 'Logo/biển hiệu xuất hiện tự nhiên') && str_contains($customPrompt, 'nếu không xem được thì uncertain'), 'actual signage photos and unverified originals are preserved');
$minimumPrompt = medical_facility_image_fix_prompt('Target {{target_images}}', $row, 1);
$assert(str_contains($minimumPrompt, 'Target 5') && str_contains($minimumPrompt, '"target_images":5'), 'minimum clamp agrees between rendered prompt and source JSON');
$assert(str_contains(medical_facility_image_fix_prompt('Target {{target_images}}', $row, 99), 'Target 12'), 'upper image bound is retained');
$localOnly = medical_facility_image_fix_import_now(new PDO('sqlite::memory:'), 71, ['image_url' => ['/uploads/library/existing.jpg'], 'gallery_json' => []]);
$assert($localOnly === ['imported' => 0, 'failed' => 0, 'items' => []], 'local images do not need network or worker schema');
$assert(str_contains($customPrompt, 'inspected_images'), 'edited admin prompt retains required current JSON contract');
$assert(str_contains($customPrompt, $row['name']) && str_contains($customPrompt, $row['address_text']) && str_contains($customPrompt, $row['google_maps_url']), 'edited prompt cannot omit branch identification and Maps source');
$assert(str_contains($customPrompt, 'inspection_url') && str_contains($customPrompt, '"target_images":9'), 'edited prompt still carries source inventory metadata and target count');

final class ImageFixStatement extends PDOStatement
{
    public function __construct(private ImageFixPDO $database, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if (str_starts_with($this->sql, 'UPDATE medical_facilities')) {
            $this->database->writes++;
            $this->database->row = array_replace($this->database->row, ['image_url' => $params[':cover'], 'ai_image_url' => $params[':ai'],
                'gallery_json' => $params[':gallery'], 'image_fix_json' => $params[':audit'], 'ai_writer_claim_json' => null]);
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->database->row; }
}
final class ImageFixPDO extends PDO
{
    public int $writes = 0; public int $rollbacks = 0; public int $commits = 0; private bool $transaction = false;
    public function __construct(public array $row) {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new ImageFixStatement($this, $query); }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; $this->commits++; return true; }
    public function rollBack(): bool { $this->transaction = false; $this->rollbacks++; return true; }
    public function inTransaction(): bool { return $this->transaction; }
}
$db = new ImageFixPDO($row);
$result = medical_facility_image_fix_save($db, $item);
$assert($result['added_count'] === 1 && $db->writes === 1 && $db->commits === 1, 'valid result saved atomically');
$assert($db->row['content'] === $row['content'] && $db->row['status'] === 'published', 'article and publication unchanged');
$assert($db->row['ai_writer_claim_json'] === null, 'lease cleared only after successful save');
$audit = json_decode($db->row['image_fix_json'], true);
$assert($audit['before']['gallery_json'] === $row['gallery_json'] && $audit['source_revision'] === $revision, 'audit retains recoverable prior URLs and revision');
$assert(!str_contains($db->row['image_fix_json'], $token), 'audit stores token hash, never raw secret');
$retry = medical_facility_image_fix_save($db, $item);
$assert($retry['already_processed'] === true && $db->writes === 1, 'same result retry is idempotent after lease cleared');
$assert(isset($retry['queue_sources']['gallery_json']), 'idempotent retry can recover interrupted download enqueue');
foreach (['stale' => 'images_changed', 'expired' => 'lease_lost', 'wrong_owner' => 'lease_lost', 'wrong_task' => 'lease_lost', 'draft' => 'not_published'] as $case => $reason) {
    $changed = $row;
    if ($case === 'stale') $changed['gallery_json'] = '[]';
    elseif ($case === 'draft') $changed['status'] = 'draft';
    else {
        $claim = json_decode($changed['ai_writer_claim_json'], true);
        if ($case === 'expired') $claim['expires_at'] = time() - 1;
        if ($case === 'wrong_owner') $claim['claim_token'] = str_repeat('b', 64);
        if ($case === 'wrong_task') $claim['task'] = 'facility_article';
        $changed['ai_writer_claim_json'] = json_encode($claim);
    }
    $db = new ImageFixPDO($changed);
    $reject(fn() => medical_facility_image_fix_save($db, $item), 'concurrent/lease conflict ' . $case, $reason);
    $assert($db->writes === 0 && $db->rollbacks === 1, 'no partial write on conflict ' . $case);
}
$db = new ImageFixPDO($row); $invalid = $item; $invalid['inspected_images'] = [];
$reject(fn() => medical_facility_image_fix_save($db, $invalid), 'invalid payload rejected inside lock');
$assert($db->writes === 0 && $db->row['ai_writer_claim_json'] === $row['ai_writer_claim_json'], 'failed validation preserves images and lease');
$empty = array_replace($row, ['image_url' => '', 'ai_image_url' => '', 'gallery_json' => '[]']);
$emptyItem = medical_facility_image_fix_output_template($empty); $emptyItem['added_images'] = $item['added_images'];
$assert(medical_facility_image_fix_patch($empty, $emptyItem)['image_url'] === $item['added_images'][0]['url'], 'empty facility can receive new real images');

// Simulate the receiver removing a photo between a worker's SELECT and UPDATE.
final class ImageFixWorkerStatement extends PDOStatement
{
    public function __construct(private ImageFixWorkerPDO $database, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if (str_starts_with($this->sql, 'UPDATE ')) {
            $this->database->updates++;
            $this->database->compareAndSwap = str_contains($this->sql, 'image_url <=> :original_cover AND gallery_json <=> :original_gallery');
            if ($this->database->removeDuringUpdate) $this->database->row = ['image_url' => '', 'gallery_json' => '[]'];
        }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed { return 1; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $this->database->reads++;
        return $this->database->row;
    }
    public function rowCount(): int { return 0; }
}
final class ImageFixWorkerPDO extends PDO
{
    public int $reads = 0; public int $updates = 0; public bool $compareAndSwap = false;
    public function __construct(public array $row, public bool $removeDuringUpdate = true) {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new ImageFixWorkerStatement($this, $query); }
}
$workerDb = new ImageFixWorkerPDO(['image_url' => $row['image_url'], 'gallery_json' => $row['gallery_json']]);
$job = ['entity_type' => 'facility', 'entity_id' => 71, 'source_url' => $row['image_url']];
$workerResult = medical_media_jobs_apply_local_url($workerDb, $job, '/uploads/library/new.jpg');
$assert($workerDb->compareAndSwap, 'worker UPDATE checks exact cover and gallery snapshot');
$assert(!$workerResult['changed'] && $workerDb->reads === 2 && $workerDb->updates === 1, 'worker rereads concurrent removal and never restores deleted photo');
$assert($workerDb->row === ['image_url' => '', 'gallery_json' => '[]'], 'receiver removal remains authoritative');
$workerDb = new ImageFixWorkerPDO(['image_url' => $row['image_url'], 'gallery_json' => $row['gallery_json']], false);
$workerResult = medical_media_jobs_apply_local_url($workerDb, $job, '/uploads/library/new.jpg');
$assert(($workerResult['reason'] ?? '') === 'images_changed' && $workerDb->updates === 3, 'worker contention retry is bounded');
$assert(isset($workerResult['error']), 'contention is retryable, never reported as a finished download rewrite');

if (in_array('--mysql-temporary', $argv, true)) {
    $mysql = db();
    $temporaryCreated = false;
    try {
        // A same-name TEMPORARY table shadows the real table on this connection.
        // Never write unless SHOW CREATE confirms that the shadow is active.
        $create = (string) $mysql->query('SHOW CREATE TABLE medical_facilities')->fetch(PDO::FETCH_NUM)[1];
        $create = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create, 1);
        $create = preg_replace('/^\s*CONSTRAINT[^\n]*\n/m', '', $create);
        $create = preg_replace('/,\n\)/', "\n)", $create);
        $mysql->exec($create);
        $temporaryCreated = true;
        $shadow = (string) $mysql->query('SHOW CREATE TABLE medical_facilities')->fetch(PDO::FETCH_NUM)[1];
        $assert(str_starts_with($shadow, 'CREATE TEMPORARY TABLE'), 'MySQL test writes only to a confirmed temporary shadow');
        medical_facility_image_fix_require_schema($mysql);
        $keys = array_keys($row);
        $insert = $mysql->prepare('INSERT INTO medical_facilities (`' . implode('`,`', $keys) . '`) VALUES (' . implode(',', array_fill(0, count($keys), '?')) . ')');
        $insert->execute(array_values($row));
        $read = static function () use ($mysql): array {
            return $mysql->query('SELECT * FROM medical_facilities WHERE id=71')->fetch(PDO::FETCH_ASSOC);
        };
        $storedRow = $read();
        $mysqlItem = $item;
        $mysqlItem['images_revision'] = medical_facility_image_fix_revision($storedRow);
        $mysqlItem['image_url'] = $item['added_images'][0]['url'];
        $mysqlResult = medical_facility_image_fix_save($mysql, $mysqlItem);
        $saved = $read();
        $assert(strlen($saved['image_url']) > 255 && $saved['image_url'] === $mysqlItem['image_url'], 'MySQL preserves full long Google cover URL');
        $assert($saved['content'] === $row['content'] && $saved['status'] === 'published' && $saved['ai_writer_claim_json'] === null, 'MySQL saves images only and releases completed claim');
        $assert($mysqlResult['removed_count'] === 1 && $mysqlResult['added_count'] === 1 && $saved['images_label'] === '3 ảnh', 'MySQL returns correct image changes and count');
        $savedAudit = json_decode($saved['image_fix_json'], true);
        $assert(($savedAudit['before']['gallery_json'] ?? '') === $storedRow['gallery_json'], 'MySQL audit preserves prior gallery');
        $replayed = medical_facility_image_fix_save($mysql, $mysqlItem);
        $assert($replayed['already_processed'] === true && $read()['gallery_json'] === $saved['gallery_json'], 'MySQL repeat POST is idempotent');

        $reset = $mysql->prepare('UPDATE medical_facilities SET image_url=?,gallery_json=?,ai_writer_claim_json=?,image_fix_json=NULL WHERE id=71');
        $reset->execute([$row['image_url'], $row['gallery_json'], $row['ai_writer_claim_json']]);
        $fresh = $read();
        $stale = $item; $stale['images_revision'] = medical_facility_image_fix_revision($fresh);
        $mysql->exec("UPDATE medical_facilities SET gallery_json='[]' WHERE id=71");
        $beforeFailure = $read();
        $reject(fn() => medical_facility_image_fix_save($mysql, $stale), 'MySQL stale image result rejected', 'images_changed');
        $assert($read() === $beforeFailure && !$mysql->inTransaction(), 'MySQL conflict rolls back without changing images or claim');

        $reset->execute([$row['image_url'], $row['gallery_json'], $row['ai_writer_claim_json']]);
        $mysql->exec("UPDATE medical_facilities SET language_code='en' WHERE id=71");
        $english = $item; $english['images_revision'] = medical_facility_image_fix_revision($read());
        medical_facility_image_fix_save($mysql, $english);
        $assert($read()['images_label'] === '3 photos', 'MySQL English image count remains localized');
    } finally {
        if ($mysql->inTransaction()) $mysql->rollBack();
        if ($temporaryCreated) $mysql->exec('DROP TEMPORARY TABLE medical_facilities');
    }
}
echo "Facility image fix: {$checks} checks passed. " . (in_array('--mysql-temporary', $argv, true)
    ? 'MySQL writes used a connection-scoped temporary shadow only; real facility images unchanged.'
    : 'Mocked PDO only; no network or DB records modified.') . "\n";
