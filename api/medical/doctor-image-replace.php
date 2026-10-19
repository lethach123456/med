<?php
declare(strict_types=1);

/**
 * API thay ảnh đại diện hỏng cho bác sĩ + đóng logo MedReview đồng thời.
 * Xác thực bằng header X-Medical-Api-Key (giống các API bài viết / cơ sở).
 *
 * POST JSON:
 * {
 *   "replacements": [
 *     {"doctor_id": 12, "image_url": "https://..."},
 *     ...
 *   ]
 * }
 * - doctor_id: id bác sĩ (bảng medical_doctors)
 * - image_url: URL ảnh mới (http/https)
 *
 * Luồng xử lý mỗi ảnh: tải về -> kiểm tra ảnh thật -> đóng wordmark
 * MedReview -> nén JPEG dưới 150.000 bytes, cạnh dài tối đa 1280px -> lưu vào
 * uploads/library/doctors/{id}/ -> cập nhật DB. Không chờ worker.
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../medical_doctor_images.php';
require_once __DIR__ . '/../../medical_search_cache.php';

medical_api_auth();
header('Cache-Control: private, no-store, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ POST.'], 405);
}

$body = read_json_body();
$replacements = $body['replacements'] ?? null;
if ($replacements === null && isset($body['doctor_id'], $body['image_url'])) {
    $replacements = [$body];
}
if (!is_array($replacements) || $replacements === [] || count($replacements) > 50) {
    json_response(['ok' => false, 'message' => 'Cần mảng replacements (1-50 phần tử).'], 422);
}

function doctor_image_replace_download(string $url): string
{
    $url = trim($url);
    if (!preg_match('~^https?://~i', $url)) {
        throw new InvalidArgumentException('URL ảnh phải bắt đầu bằng http(s)://');
    }
    $ctx = stream_context_create(['http' => [
        'timeout' => 30,
        'follow_location' => 1,
        'max_redirects' => 5,
        'user_agent' => 'MedReview-ImageReplace/1.0',
    ]]);
    $data = @file_get_contents($url, false, $ctx, 0, 15 * 1024 * 1024);
    if (!is_string($data) || strlen($data) < 1024) {
        throw new RuntimeException('Không tải được ảnh từ URL.');
    }
    $info = @getimagesizefromstring($data);
    if (!is_array($info) || ($info[0] ?? 0) < 200 || ($info[1] ?? 0) < 200) {
        throw new RuntimeException('URL không phải ảnh hợp lệ (tối thiểu 200x200).');
    }
    return $data;
}

$results = [];
$pdo = db();
foreach ($replacements as $index => $item) {
    $result = ['index' => $index, 'ok' => false];
    try {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Mỗi phần tử phải là object.');
        }
        $doctorId = (int) ($item['doctor_id'] ?? 0);
        $newUrl = trim((string) ($item['image_url'] ?? ''));
        if ($doctorId < 1 || $newUrl === '') {
            throw new InvalidArgumentException('Thiếu doctor_id / image_url hợp lệ.');
        }
        $result['doctor_id'] = $doctorId;

        $stmt = $pdo->prepare('SELECT id, image_url FROM medical_doctors WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $doctorId]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doctor) {
            throw new RuntimeException('Không tìm thấy bác sĩ #' . $doctorId);
        }

        // 1. Tải ảnh mới
        $data = doctor_image_replace_download($newUrl);
        // 2. Chỉ công bố bản đã đóng logo và nén đạt giới hạn.
        $stored = medical_doctor_image_store($data, 'doctors/' . $doctorId);
        $publicUrl = $stored['url'];

        // 3. Cập nhật DB: image_url
        $update = $pdo->prepare('UPDATE medical_doctors
            SET image_url = :url, image_scan_status = :scan, updated_at = NOW()
            WHERE id = :id');
        $update->execute([
            ':url' => $publicUrl,
            ':scan' => 'unscanned',
            ':id' => $doctorId,
        ]);

        $result['ok'] = true;
        $result['public_url'] = $publicUrl;
        $result['width'] = $stored['width'];
        $result['height'] = $stored['height'];
        $result['size_bytes'] = $stored['size'];
        $result['original_size_bytes'] = $stored['original_size'];
        $result['message'] = 'Đã thay ảnh, đóng logo và nén dưới 150 KB.';
    } catch (Throwable $error) {
        $result['message'] = $error->getMessage();
        error_log('Doctor image replace: ' . get_class($error) . ': ' . $error->getMessage());
    }
    $results[] = $result;
}

$okCount = count(array_filter($results, static fn(array $r): bool => $r['ok']));
if ($okCount > 0) medical_search_cache_invalidate();
json_response(['ok' => $okCount > 0, 'processed' => count($results), 'succeeded' => $okCount, 'results' => $results]);
