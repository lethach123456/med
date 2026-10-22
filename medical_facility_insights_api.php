<?php
declare(strict_types=1);

// Pure request/response operations: loading this module never opens a DB connection.
require_once __DIR__ . '/medical_facility_insights.php';

const MEDICAL_FACILITY_INSIGHTS_API_MAX_BODY_BYTES = 2097152;
const MEDICAL_FACILITY_INSIGHTS_API_PUBLIC_FIELDS = [
    'id', 'slug', 'name', 'category', 'city', 'address_text', 'phone_text',
    'website_url', 'google_maps_url', 'social_links_json', 'language_code', 'status',
];

final class MedicalFacilityInsightsApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly string $reason = 'invalid_input')
    {
        parent::__construct($message);
    }
}

function medical_facility_insights_api_parse_body(string $raw, int $maxBytes = MEDICAL_FACILITY_INSIGHTS_API_MAX_BODY_BYTES): array
{
    if (strlen($raw) > $maxBytes) {
        throw new MedicalFacilityInsightsApiException('JSON vượt giới hạn 2 MiB.', 413, 'body_too_large');
    }
    try {
        $body = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new MedicalFacilityInsightsApiException('Body phải là JSON UTF-8 hợp lệ.', 400, 'invalid_json');
    }
    if (!$body instanceof stdClass) {
        throw new MedicalFacilityInsightsApiException('Body phải là một object JSON.', 422, 'invalid_body');
    }
    // Keep nested {} as stdClass rather than silently converting it to an array.
    return get_object_vars($body);
}

function medical_facility_insights_api_id(mixed $value): int
{
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value)
        || (int) $value > 4294967295) {
        throw new MedicalFacilityInsightsApiException('id phải là số nguyên dương của cơ sở y tế.');
    }
    return (int) $value;
}

function medical_facility_insights_api_normalize(array $body): array
{
    if (array_diff(array_keys($body), ['id', 'facility_insights_json', 'insights_revision']) !== []) {
        throw new MedicalFacilityInsightsApiException('Chỉ nhận id, facility_insights_json và insights_revision; không nhận nội dung, ảnh hoặc trạng thái hồ sơ.', 422, 'unsupported_fields');
    }
    $id = medical_facility_insights_api_id($body['id'] ?? null);
    if (!array_key_exists('facility_insights_json', $body)) {
        throw new MedicalFacilityInsightsApiException('Thiếu facility_insights_json. Gửi {} nếu muốn xóa phân tích.', 422, 'missing_insights');
    }
    // This parser preserves {} as stdClass, so [] is never an explicit clear.
    if (is_array($body['facility_insights_json']) && array_is_list($body['facility_insights_json'])) {
        throw new MedicalFacilityInsightsApiException('facility_insights_json phải là object JSON; dùng {} để xóa, không dùng [].');
    }
    try {
        $encoded = medical_facility_insights_encode($body['facility_insights_json']);
    } catch (InvalidArgumentException $error) {
        throw new MedicalFacilityInsightsApiException($error->getMessage());
    }
    $revision = null;
    if (array_key_exists('insights_revision', $body)) {
        if (!is_string($body['insights_revision']) || !preg_match('/^[a-f0-9]{64}$/Di', $body['insights_revision'])) {
            throw new MedicalFacilityInsightsApiException('insights_revision phải là mã SHA-256 gồm 64 ký tự hex.', 422, 'invalid_revision');
        }
        $revision = strtolower($body['insights_revision']);
    }
    return ['id' => $id, 'encoded' => $encoded, 'insights_revision' => $revision];
}

function medical_facility_insights_api_require_schema(PDO $pdo): void
{
    try {
        medical_facility_insights_require_schema($pdo);
    } catch (InvalidArgumentException $error) {
        throw new MedicalFacilityInsightsApiException($error->getMessage(), 503, 'schema_unavailable');
    }
}

/** Link-only social metadata: raw nested fields never enter the AI source DTO. */
function medical_facility_insights_api_social_links(mixed $value): array
{
    if (is_string($value)) $value = json_decode($value, true);
    if ($value instanceof stdClass) $value = get_object_vars($value);
    if (!is_array($value)) return [];
    $links = [];
    foreach ($value as $key => $entry) {
        if ($entry instanceof stdClass) $entry = get_object_vars($entry);
        $url = is_array($entry) ? ($entry['url'] ?? $entry['href'] ?? '') : $entry;
        try {
            $url = medical_facility_insights_url($url, 'social_links_json.url');
        } catch (InvalidArgumentException) {
            continue;
        }
        $platform = is_array($entry) ? ($entry['platform'] ?? $entry['name'] ?? '') : (is_string($key) ? $key : '');
        $platform = is_string($platform) ? trim(strip_tags($platform)) : '';
        $links[$url] = ['platform' => mb_substr($platform, 0, 120, 'UTF-8'), 'url' => $url];
        if (count($links) >= 100) break;
    }
    return array_values($links);
}

/** Explicit allowlist; never serialize the raw facility row or full_json. */
function medical_facility_insights_api_source(array $row): array
{
    $source = [];
    foreach (MEDICAL_FACILITY_INSIGHTS_API_PUBLIC_FIELDS as $field) {
        if ($field === 'social_links_json') {
            $source[$field] = medical_facility_insights_api_social_links($row[$field] ?? null);
        } else {
            $source[$field] = $field === 'id' ? (int) ($row[$field] ?? 0) : (string) ($row[$field] ?? '');
        }
    }
    foreach (['website_url', 'google_maps_url'] as $field) {
        try { $source[$field] = medical_facility_insights_url($source[$field], $field); }
        catch (InvalidArgumentException) { $source[$field] = ''; }
    }
    $raw = $row['facility_insights_json'] ?? null;
    $source['facility_insights_json'] = new stdClass();
    $source['stored_insights_valid'] = true;
    if ($raw !== null) {
        try {
            $source['facility_insights_json'] = medical_facility_insights_normalize($raw);
        } catch (InvalidArgumentException) {
            // A malformed historic value must not be copied into a prompt or treated as verified analysis.
            $source['stored_insights_valid'] = false;
        }
    }
    return $source;
}

function medical_facility_insights_api_read(PDO $pdo, mixed $id): array
{
    $id = medical_facility_insights_api_id($id);
    medical_facility_insights_api_require_schema($pdo);
    $columns = implode(', ', array_merge(MEDICAL_FACILITY_INSIGHTS_API_PUBLIC_FIELDS, ['facility_insights_json']));
    try {
        $statement = $pdo->prepare('SELECT ' . $columns . ' FROM medical_facilities WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException) {
        throw new MedicalFacilityInsightsApiException('Chưa đọc được dữ liệu hồ sơ công khai của cơ sở; kiểm tra schema cơ sở y tế.', 503, 'source_unavailable');
    }
    if (!is_array($row)) throw new MedicalFacilityInsightsApiException('Không tìm thấy cơ sở y tế.', 404, 'facility_not_found');
    $source = medical_facility_insights_api_source($row);
    $facility = array_intersect_key($source, array_flip(MEDICAL_FACILITY_INSIGHTS_API_PUBLIC_FIELDS));
    return [
        'ok' => true,
        'type' => 'facility_insights',
        'contract_version' => 1,
        'facility' => $facility,
        'facility_insights_json' => $source['facility_insights_json'],
        'stored_insights_valid' => $source['stored_insights_valid'],
        'insights_revision' => medical_facility_insights_revision($row['facility_insights_json'] ?? null),
        'prompt' => medical_facility_insights_prompt($source),
        'output_template' => ['id' => $id, 'facility_insights_json' => medical_facility_insights_template()],
    ];
}

/** Dedicated single-column transaction, independent from article writer leases. */
function medical_facility_insights_api_update(PDO $pdo, array $body): array
{
    $input = medical_facility_insights_api_normalize($body);
    medical_facility_insights_api_require_schema($pdo);
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if (!in_array($driver, ['mysql', 'sqlite'], true) || $pdo->inTransaction()) {
        throw new MedicalFacilityInsightsApiException('Không thể bắt đầu giao dịch lưu phân tích.', 503, 'transaction_unavailable');
    }
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT id, facility_insights_json FROM medical_facilities WHERE id = :id LIMIT 1'
            . ($driver === 'mysql' ? ' FOR UPDATE' : ''));
        $statement->execute([':id' => $input['id']]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new MedicalFacilityInsightsApiException('Không tìm thấy cơ sở y tế.', 404, 'facility_not_found');
        $currentRevision = medical_facility_insights_revision($row['facility_insights_json'] ?? null);
        if ($input['insights_revision'] !== null && !hash_equals($currentRevision, $input['insights_revision'])) {
            throw new MedicalFacilityInsightsApiException('Phân tích đã thay đổi từ lần đọc trước. GET lại dữ liệu, đối chiếu rồi gửi với insights_revision mới.', 409, 'insights_conflict');
        }
        $update = $pdo->prepare('UPDATE medical_facilities SET facility_insights_json = :insights WHERE id = :id');
        $update->execute([':insights' => $input['encoded'], ':id' => $input['id']]);
        $receipt = medical_facility_insights_verify_saved($pdo, $input['id'], $input['encoded']);
        if (($receipt['saved'] ?? false) !== true || ($receipt['storage_verified'] ?? false) !== true) {
            throw new RuntimeException('Facility insights storage verification failed.');
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return ['ok' => true, 'type' => 'facility_insights', 'contract_version' => 1] + $receipt;
}
