<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = ['https://chatgpt.com', 'https://grok.com', 'https://x.com', 'https://www.x.com'];
header('Vary: Origin');
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: X-Medical-Api-Key, Content-Type');
    header('Access-Control-Max-Age: 600');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

medical_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

/** @return array{ok:bool,bytes?:string,error?:string} */
function medical_api_ai_image_decode_data(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') {
        return ['ok' => false, 'error' => 'Thiếu dữ liệu ảnh base64.'];
    }
    $value = trim($value);
    if (preg_match('#^data:image/[a-z0-9.+-]+;base64,(.+)$#is', $value, $matches)) {
        $value = (string) ($matches[1] ?? '');
    }
    $value = preg_replace('/\s+/', '', $value) ?? '';
    if ($value === '' || strlen($value) > 18 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Dữ liệu ảnh base64 không hợp lệ hoặc quá lớn.'];
    }
    $bytes = base64_decode($value, true);
    if (!is_string($bytes) || $bytes === '' || strlen($bytes) > 12 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Ảnh base64 không hợp lệ hoặc vượt 12MB.'];
    }
    return ['ok' => true, 'bytes' => $bytes];
}

function medical_api_ai_image_url(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }
    $url = trim($value);
    if ($url === '' || strlen($url) > 8000 || str_contains($url, "\0")) {
        return '';
    }
    if (str_starts_with($url, '/uploads/library/') && !str_contains($url, '..')) {
        return $url;
    }
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL)
        ? $url
        : '';
}

function medical_api_ai_image_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function medical_api_ai_image_folder(int $facilityId, string $facilitySlug): string
{
    $slug = medical_media_folder_slug($facilitySlug);
    if ($slug === '') {
        $slug = 'co-so-y-te';
    }
    return 'co-so-y-te-ai/facility-' . max(1, $facilityId) . '-' . $slug;
}

$pdo = db();
if (!medical_directory_table_exists($pdo, 'medical_facilities')) {
    medical_directory_ensure_tables($pdo);
} else {
    medical_directory_ensure_facility_ai_image_column($pdo);
}

$body = read_json_body();
if ($body === [] && $_POST !== []) {
    $body = $_POST;
}
$items = isset($body['items']) && is_array($body['items']) ? $body['items'] : [$body];

$uploadedBytes = null;
if (isset($_FILES['image']) && is_array($_FILES['image'])) {
    $upload = $_FILES['image'];
    if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'message' => 'Upload ảnh lỗi (code: ' . (int) ($upload['error'] ?? 0) . ').'], 422);
    }
    $size = (int) ($upload['size'] ?? 0);
    $tmp = (string) ($upload['tmp_name'] ?? '');
    if ($size < 1 || $size > 12 * 1024 * 1024 || $tmp === '' || !is_uploaded_file($tmp)) {
        json_response(['ok' => false, 'message' => 'File ảnh upload không hợp lệ hoặc vượt 12MB.'], 422);
    }
    $uploadedBytes = @file_get_contents($tmp);
    if (!is_string($uploadedBytes) || $uploadedBytes === '') {
        json_response(['ok' => false, 'message' => 'Không đọc được file ảnh upload.'], 422);
    }
}

$updated = [];
$errors = [];
$lookupById = $pdo->prepare('SELECT id, slug, image_url FROM medical_facilities WHERE id = :id LIMIT 1');
$lookupBySlug = $pdo->prepare('SELECT id, slug, image_url FROM medical_facilities WHERE slug = :slug LIMIT 1');

foreach ($items as $index => $item) {
    if (!is_array($item)) {
        $errors[] = ['index' => $index, 'message' => 'Mỗi ảnh phải là một object JSON.'];
        continue;
    }
    $id = max(0, (int) ($item['id'] ?? $item['facility_id'] ?? 0));
    $slug = trim((string) ($item['slug'] ?? $item['facility_slug'] ?? ''));
    if ($id > 0) {
        $lookupById->execute([':id' => $id]);
    } elseif ($slug !== '') {
        $lookupBySlug->execute([':slug' => $slug]);
    } else {
        $errors[] = ['index' => $index, 'message' => 'Thiếu id hoặc slug cơ sở.'];
        continue;
    }
    $facility = ($id > 0 ? $lookupById : $lookupBySlug)->fetch(PDO::FETCH_ASSOC);
    if (!is_array($facility)) {
        $errors[] = ['index' => $index, 'facility_id' => $id, 'message' => 'Không tìm thấy cơ sở y tế.'];
        continue;
    }
    if ($id > 0 && $slug !== '' && !hash_equals((string) ($facility['slug'] ?? ''), $slug)) {
        $errors[] = [
            'index' => $index,
            'facility_id' => $id,
            'message' => 'id và slug không cùng một cơ sở; ảnh chưa được lưu để tránh ghi nhầm dữ liệu.',
        ];
        continue;
    }
    $facilityId = (int) $facility['id'];

    $localImage = false;
    $storedMeta = [];
    $imageUrl = medical_api_ai_image_url($item['ai_image_url'] ?? $item['generated_image_url'] ?? $item['image_url'] ?? $item['url'] ?? '');
    $imageData = $item['image_data'] ?? $item['image_base64'] ?? null;
    if ($imageData !== null && $imageData !== '') {
        $decoded = medical_api_ai_image_decode_data($imageData);
        if (!($decoded['ok'] ?? false) || !isset($decoded['bytes'])) {
            $errors[] = ['index' => $index, 'facility_id' => $facilityId, 'message' => (string) ($decoded['error'] ?? 'Không đọc được ảnh base64.')];
            continue;
        }
        $stored = medical_media_store_downloaded_original((string) $decoded['bytes'], medical_api_ai_image_folder($facilityId, (string) $facility['slug']));
        if (!($stored['ok'] ?? false) || empty($stored['url'])) {
            $errors[] = ['index' => $index, 'facility_id' => $facilityId, 'message' => (string) ($stored['error'] ?? 'Không lưu được ảnh AI.')];
            continue;
        }
        $imageUrl = (string) $stored['url'];
        $localImage = true;
        $storedMeta = $stored;
    } elseif ($uploadedBytes !== null) {
        $stored = medical_media_store_downloaded_original($uploadedBytes, medical_api_ai_image_folder($facilityId, (string) $facility['slug']));
        if (!($stored['ok'] ?? false) || empty($stored['url'])) {
            $errors[] = ['index' => $index, 'facility_id' => $facilityId, 'message' => (string) ($stored['error'] ?? 'Không lưu được ảnh AI upload.')];
            continue;
        }
        $imageUrl = (string) $stored['url'];
        $localImage = true;
        $storedMeta = $stored;
    }

    if ($imageUrl === '') {
        $errors[] = ['index' => $index, 'facility_id' => $facilityId, 'message' => 'Thiếu ai_image_url/image_url hợp lệ, image_data base64 hoặc file image upload.'];
        continue;
    }

    $setAsCover = medical_api_ai_image_bool($item['set_as_cover'] ?? $item['promote_to_cover'] ?? false);
    if ($setAsCover && strlen($imageUrl) > 255) {
        $errors[] = [
            'index' => $index,
            'facility_id' => $facilityId,
            'message' => 'URL ảnh quá dài để thay ảnh bìa hiện tại. Ảnh vẫn có thể lưu ở ai_image_url; hãy bỏ set_as_cover hoặc gửi image_data/file để lưu nội bộ.',
        ];
        continue;
    }
    $set = ['ai_image_url = :ai_image_url'];
    $params = [':ai_image_url' => $imageUrl, ':id' => $facilityId];
    if ($setAsCover) {
        $set[] = 'image_url = :image_url';
        $params[':image_url'] = $imageUrl;
    }
    $update = $pdo->prepare('UPDATE medical_facilities SET ' . implode(', ', $set) . ' WHERE id = :id');
    $update->execute($params);
    $updated[] = [
        'facility_id' => $facilityId,
        'ai_image_url' => $imageUrl,
        'storage' => $localImage ? 'local_library' : 'external_url',
        'set_as_cover' => $setAsCover,
        'file' => $localImage ? [
            'width' => (int) ($storedMeta['width'] ?? 0),
            'height' => (int) ($storedMeta['height'] ?? 0),
            'size' => (int) ($storedMeta['size'] ?? 0),
            'format' => (string) ($storedMeta['format'] ?? ''),
        ] : null,
    ];
}

$status = $updated === [] && $errors !== [] ? 422 : 200;
json_response([
    'ok' => $updated !== [],
    'updated_count' => count($updated),
    'updated' => $updated,
    'errors' => $errors,
    'note' => 'Mặc định ảnh AI được lưu riêng ở medical_facilities.ai_image_url. Gửi set_as_cover=true để đồng thời thay image_url.',
], $status);
