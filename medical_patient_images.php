<?php
declare(strict_types=1);

/**
 * Private patient-image metadata. Including this file never connects to a DB,
 * changes schema, downloads an image or makes patient media public.
 */
function medical_patient_images_text(mixed $value, string $field, int $maxLength): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException($field . ' phải là chuỗi.');
    }
    if (preg_match('//u', $value) !== 1 || preg_match('/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u', $value)) {
        throw new InvalidArgumentException($field . ' chứa UTF-8 hoặc ký tự điều khiển không hợp lệ.');
    }
    $value = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value);
    if ($length > $maxLength) {
        throw new InvalidArgumentException($field . ' tối đa ' . $maxLength . ' ký tự.');
    }
    return $value;
}

/** Only stored public HTTP(S) URLs or a local uploaded file path are accepted. */
function medical_patient_images_url(mixed $value, string $field): string
{
    $url = medical_patient_images_text($value, $field, 4000);
    if ($url === '' || preg_match('/\s/u', $url) || str_contains($url, '\\') || preg_match('/%(?![a-f0-9]{2})/i', $url)) {
        throw new InvalidArgumentException($field . ' cần URL HTTP(S) hoặc đường dẫn /uploads/... hợp lệ.');
    }
    $parts = parse_url($url);
    if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
        throw new InvalidArgumentException($field . ' không được chứa thông tin đăng nhập hoặc URL lỗi.');
    }
    $local = str_starts_with($url, '/uploads/');
    if ($local) {
        if (isset($parts['scheme']) || isset($parts['host'])) {
            throw new InvalidArgumentException($field . ' phải là đường dẫn /uploads/... nội bộ.');
        }
    } elseif (!in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        || empty($parts['host']) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException($field . ' chỉ chấp nhận URL HTTP(S) tuyệt đối hoặc /uploads/....');
    }

    // Reject literal and encoded traversal/control characters before a future
    // consumer can interpret the path differently from this metadata validator.
    $path = (string) ($parts['path'] ?? '');
    $decodedPath = $path;
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $next = rawurldecode($decodedPath);
        if ($next === $decodedPath) break;
        $decodedPath = $next;
    }
    if (preg_match('//u', $decodedPath) !== 1
        || preg_match('/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u', $decodedPath)
        || str_contains($decodedPath, '\\')
        || in_array('..', explode('/', $decodedPath), true)
        || in_array('.', explode('/', $decodedPath), true)
        || ($local && (!str_starts_with($decodedPath, '/uploads/') || $decodedPath === '/uploads/' || str_ends_with($decodedPath, '/')))) {
        throw new InvalidArgumentException($field . ' chứa đường dẫn không an toàn.');
    }
    return $url;
}

/**
 * Normalize an explicitly supplied list. Omission is handled by callers;
 * null/empty strings must not silently clear already stored patient metadata.
 */
function medical_patient_images_normalize(mixed $value): array
{
    if (is_string($value)) {
        if ($value === '' || strlen($value) > 1048576) {
            throw new InvalidArgumentException('patient_images_json phải là mảng JSON, tối đa 1 MiB; dùng [] để xóa.');
        }
        try {
            $value = json_decode($value, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('patient_images_json không phải JSON hợp lệ: ' . $e->getMessage(), 0, $e);
        }
    }
    if (!is_array($value) || !array_is_list($value)) {
        throw new InvalidArgumentException('patient_images_json phải là một mảng ảnh; dùng [] để xóa.');
    }
    if (count($value) > 100) {
        throw new InvalidArgumentException('patient_images_json tối đa 100 ảnh.');
    }
    $normalized = [];
    $seen = [];
    $allowed = ['url', 'caption', 'alt', 'stage', 'consent_confirmed', 'source_url'];
    foreach ($value as $index => $item) {
        $prefix = 'patient_images_json[' . $index . ']';
        if ($item instanceof stdClass) $item = get_object_vars($item);
        if (!is_array($item) || array_is_list($item)) {
            throw new InvalidArgumentException($prefix . ' phải là object có url, không phải chuỗi hoặc mảng con.');
        }
        foreach (array_keys($item) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException($prefix . ' có khóa không hỗ trợ. Chỉ dùng url, caption, alt, stage, consent_confirmed, source_url.');
            }
        }
        if (!array_key_exists('url', $item)) {
            throw new InvalidArgumentException($prefix . '.url là bắt buộc.');
        }
        $url = medical_patient_images_url($item['url'], $prefix . '.url');
        // Preserve the original URL, but recognize equivalent HTTP host/scheme
        // casing and fragments so duplicate records cannot inflate the list.
        $parts = parse_url($url);
        $duplicateKey = isset($parts['host'])
            ? strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host'])
                . (isset($parts['port']) ? ':' . $parts['port'] : '') . ($parts['path'] ?? '/')
                . (isset($parts['query']) ? '?' . $parts['query'] : '')
            : preg_replace('/#.*$/s', '', $url);
        if (isset($seen[$duplicateKey])) {
            throw new InvalidArgumentException($prefix . '.url bị trùng với ảnh trước đó.');
        }
        $seen[$duplicateKey] = true;
        $stage = array_key_exists('stage', $item) ? $item['stage'] : 'other';
        if (!is_string($stage) || !in_array($stage, ['before', 'during', 'after', 'other'], true)) {
            throw new InvalidArgumentException($prefix . '.stage chỉ nhận before, during, after hoặc other.');
        }
        $consent = array_key_exists('consent_confirmed', $item) ? $item['consent_confirmed'] : false;
        if (!is_bool($consent)) {
            throw new InvalidArgumentException($prefix . '.consent_confirmed phải là boolean true/false.');
        }
        $entry = [
            'url' => $url,
            'caption' => medical_patient_images_text(array_key_exists('caption', $item) ? $item['caption'] : '', $prefix . '.caption', 500),
            'alt' => medical_patient_images_text(array_key_exists('alt', $item) ? $item['alt'] : '', $prefix . '.alt', 500),
            'stage' => $stage,
            'consent_confirmed' => $consent,
        ];
        if (array_key_exists('source_url', $item)) {
            $entry['source_url'] = medical_patient_images_url($item['source_url'], $prefix . '.source_url');
        }
        $normalized[] = $entry;
    }
    if (strlen(json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) > 1048576) {
        throw new InvalidArgumentException('patient_images_json sau chuẩn hóa tối đa 1 MiB.');
    }
    return $normalized;
}

function medical_patient_images_encode(mixed $value): string
{
    return json_encode(medical_patient_images_normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/** Overall editorial observations/questions, separate from each photo caption. */
function medical_patient_images_notes_normalize(mixed $value): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException('patient_images_notes phải là chuỗi; dùng chuỗi rỗng để xóa ghi chú.');
    }
    if (preg_match('//u', $value) !== 1
        || preg_match('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u', $value)) {
        throw new InvalidArgumentException('patient_images_notes chứa UTF-8 hoặc ký tự điều khiển không hợp lệ.');
    }
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value);
    if ($length > 4000) {
        throw new InvalidArgumentException('patient_images_notes tối đa 4000 ký tự.');
    }
    return $value;
}

function medical_patient_images_table(string $entity): string
{
    return match ($entity) {
        'facility' => 'medical_facilities',
        'doctor' => 'medical_doctors',
        default => throw new InvalidArgumentException('Loại hồ sơ ảnh bệnh nhân chỉ nhận facility hoặc doctor.'),
    };
}

/** Read-only, not cached: safe for long-lived maintenance/test connections. */
function medical_patient_images_has_column(PDO $pdo, string $entity, string $column = 'patient_images_json'): bool
{
    $table = medical_patient_images_table($entity);
    if (!in_array($column, ['patient_images_json', 'patient_images_notes'], true)) {
        throw new InvalidArgumentException('Cột ảnh bệnh nhân không được hỗ trợ.');
    }
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $rows = $pdo->query('PRAGMA table_info("' . $table . '")')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) if (($row['name'] ?? '') === $column) return true;
        return false;
    }
    if ($driver !== 'mysql') throw new InvalidArgumentException('Chỉ hỗ trợ metadata schema MySQL hoặc SQLite.');
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1');
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (bool) $stmt->fetchColumn();
}

function medical_patient_images_require_schema(PDO $pdo, string $entity, string $column = 'patient_images_json'): void
{
    if (!medical_patient_images_has_column($pdo, $entity, $column)) {
        throw new InvalidArgumentException('Chưa có cột ' . $column . ' cho ' . $entity . '. Quản trị viên chạy php scripts/migrate_patient_images.php --apply trên đúng DB.');
    }
}

/**
 * Confirm dedicated-column persistence, not an echo of the incoming payload.
 * Receipts/errors contain field names only, never patient photos or notes.
 */
function medical_patient_images_verify_saved(PDO $pdo, string $entity, int $id, array $expected): array
{
    $table = medical_patient_images_table($entity);
    if ($id <= 0) throw new InvalidArgumentException('ID hồ sơ ảnh bệnh nhân phải là số nguyên dương.');
    if ($expected === []) return [];
    foreach ($expected as $column => $value) {
        if (!in_array($column, ['patient_images_json', 'patient_images_notes'], true) || !is_string($value)) {
            throw new InvalidArgumentException('Chỉ đối chiếu cột ảnh/ghi chú bệnh nhân với giá trị đã chuẩn hóa.');
        }
    }
    $columns = array_keys($expected);
    $select = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
    $stmt = $pdo->prepare('SELECT ' . $select . ' FROM `' . $table . '` WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $stored = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($stored)) throw new RuntimeException('Không thể xác nhận dữ liệu ảnh bệnh nhân: hồ sơ không còn tồn tại.');
    foreach ($expected as $column => $value) {
        if (!is_string($stored[$column] ?? null) || !hash_equals($value, $stored[$column])) {
            throw new RuntimeException('Chưa xác nhận lưu đúng cột ' . $column . '; không coi phản hồi echo/full_json là đã lưu thành công.');
        }
    }
    return ['id' => $id, 'saved_fields' => $columns, 'verified' => true];
}

/** Only the dedicated, explicit maintenance command may add these columns. */
function medical_patient_images_migrate(PDO $pdo): array
{
    if (!function_exists('medreview_schema_migration_allowed') || !medreview_schema_migration_allowed()) {
        throw new LogicException('Chỉ thêm cột ảnh bệnh nhân trong maintenance scope được cho phép rõ ràng.');
    }
    $added = [];
    foreach (['facility', 'doctor'] as $entity) {
        $table = medical_patient_images_table($entity);
        $missing = [];
        foreach (['patient_images_json' => 'MEDIUMTEXT NULL', 'patient_images_notes' => 'TEXT NULL'] as $column => $definition) {
            if (!medical_patient_images_has_column($pdo, $entity, $column)) $missing[$column] = $definition;
        }
        if ($missing === []) continue;
        $alter = [];
        foreach ($missing as $column => $definition) $alter[] = 'ADD COLUMN `' . $column . '` ' . $definition;
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            // SQLite supports one added column per statement; keep local
            // in-memory verification equivalent to MySQL's grouped ALTER.
            foreach ($missing as $column => $definition) {
                try { $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition); }
                catch (PDOException $e) {
                    if (!medical_patient_images_has_column($pdo, $entity, $column)) throw $e;
                }
            }
            $added[] = $table;
            continue;
        }
        try {
            $pdo->exec('ALTER TABLE `' . $table . '` ' . implode(', ', $alter));
            $added[] = $table;
        } catch (PDOException $e) {
            // An overlapping explicit migration may have added it first.
            foreach (array_keys($missing) as $column) {
                if (!medical_patient_images_has_column($pdo, $entity, $column)) throw $e;
            }
        }
    }
    return $added;
}

/** Internal traversal also understands JSON strings in named JSON containers. */
function medical_patient_images_redact_node(mixed $value, bool &$changed, ?string $containerKey = null): mixed
{
    if (is_string($value) && $containerKey !== null && str_ends_with($containerKey, '_json')) {
        try {
            $decoded = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // Never echo a corrupt legacy container that may hold patient
            // metadata. Drop it instead of guessing where its private value ends.
            if (preg_match('/patient_images(?:_json|_notes)?/i', $value)) {
                $changed = true;
                return 'null';
            }
            // Unrelated malformed JSON, HTML and arbitrary prose stay intact.
            return $value;
        }
        $nestedChanged = false;
        $clean = medical_patient_images_redact_node($decoded, $nestedChanged, $containerKey);
        if (!$nestedChanged) return $value; // Keep byte-for-byte when no private key exists.
        $changed = true;
        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
    if (!is_array($value) && !$value instanceof stdClass) return $value;
    $isObject = $value instanceof stdClass;
    $items = $isObject ? get_object_vars($value) : $value;
    $localChanged = false;
    foreach ($items as $key => $item) {
        if (in_array($key, ['patient_images_json', 'patient_images', 'patient_images_notes'], true)) {
            unset($items[$key]);
            $localChanged = true;
            continue;
        }
        $items[$key] = medical_patient_images_redact_node($item, $localChanged, is_string($key) ? $key : null);
    }
    if (!$localChanged) return $value;
    $changed = true;
    return $isObject ? (object) $items : $items;
}

/**
 * Remove private patient metadata from arrays, objects and nested *_json string
 * containers. Unchanged JSON stays byte-for-byte; corrupt private containers
 * fail closed rather than leaking their raw patient values.
 */
function medical_patient_images_redact(array $value): array
{
    $changed = false;
    return medical_patient_images_redact_node($value, $changed);
}
