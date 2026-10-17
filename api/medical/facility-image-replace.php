<?php
declare(strict_types=1);

/**
 * API thay ảnh hỏng cho cơ sở y tế + đóng logo MedReview đồng thời.
 * Xác thực bằng header X-Medical-Api-Key (giống các API bài viết).
 *
 * POST JSON:
 * {
 *   "replacements": [
 *     {"facility_id": 221, "image_url": "https://...", "gallery_index": 0},
 *     ...
 *   ]
 * }
 * - facility_id: id cơ sở (bảng medical_facilities)
 * - image_url: URL ảnh mới (http/https)
 * - gallery_index: vị trí trong gallery_json cần thay (mặc định 0 = ảnh bìa image_url)
 *
 * Luồng xử lý mỗi ảnh: tải về -> kiểm tra ảnh thật -> đóng wordmark
 * MedReview -> lưu vào uploads/library/facilities/{id}/ -> cập nhật DB.
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../medical_image_branding.php';

medical_api_auth();
header('Cache-Control: private, no-store, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ POST.'], 405);
}

$body = read_json_body();
$replacements = $body['replacements'] ?? null;
if ($replacements === null && isset($body['facility_id'], $body['image_url'])) {
    $replacements = [$body];
}
if (!is_array($replacements) || $replacements === [] || count($replacements) > 50) {
    json_response(['ok' => false, 'message' => 'Cần mảng replacements (1-50 phần tử).'], 422);
}

function facility_image_replace_download(string $url): string
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
        $facilityId = (int) ($item['facility_id'] ?? 0);
        $newUrl = trim((string) ($item['image_url'] ?? ''));
        $galleryIndex = (int) ($item['gallery_index'] ?? 0);
        $caption = trim((string) ($item['caption'] ?? ''));
        if ($facilityId < 1 || $newUrl === '' || $galleryIndex < 0) {
            throw new InvalidArgumentException('Thiếu facility_id / image_url hợp lệ.');
        }
        $result['facility_id'] = $facilityId;

        $stmt = $pdo->prepare('SELECT id, image_url, gallery_json FROM medical_facilities WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $facilityId]);
        $facility = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$facility) {
            throw new RuntimeException('Không tìm thấy cơ sở #' . $facilityId);
        }

        // 1. Tải ảnh mới
        $data = facility_image_replace_download($newUrl);
        $hash = substr(hash('sha256', $data), 0, 16);

        // 2. Lưu file tạm rồi đóng logo
        $dir = __DIR__ . '/../../uploads/library/facilities/' . $facilityId;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Không tạo được thư mục lưu ảnh.');
        }
        $tmpPath = $dir . '/.tmp-' . $hash;
        if (file_put_contents($tmpPath, $data, LOCK_EX) !== strlen($data)) {
            throw new RuntimeException('Không lưu được file ảnh tạm.');
        }
        $finalPath = $dir . '/image-' . $hash . '.jpg';
        try {
            $rendered = medical_image_brand_render($tmpPath, $finalPath, []);
        } finally {
            @unlink($tmpPath);
        }
        $publicUrl = '/uploads/library/facilities/' . $facilityId . '/image-' . $hash . '.jpg';

        // 3. Cập nhật DB: image_url + gallery_json
        $gallery = [];
        if (is_string($facility['gallery_json'] ?? null) && trim((string) $facility['gallery_json']) !== '') {
            $decoded = json_decode((string) $facility['gallery_json'], true);
            if (is_array($decoded)) {
                $gallery = array_values($decoded);
            }
        }
        if ($galleryIndex < count($gallery)) {
            $old = is_array($gallery[$galleryIndex]) ? $gallery[$galleryIndex] : [];
            $merged = array_merge($old, ['url' => $publicUrl]);
            if ($caption !== '') {
                $merged['caption'] = $caption;
            }
            $gallery[$galleryIndex] = $merged;
        } else {
            $gallery[] = ['url' => $publicUrl, 'caption' => $caption, 'source' => 'MedReview'];
        }
        $coverValue = ($galleryIndex === 0) ? $publicUrl : (string) ($facility['image_url'] ?? '');

        $update = $pdo->prepare('UPDATE medical_facilities
            SET image_url = :cover, gallery_json = :gallery,
                image_scan_status = :scan, updated_at = NOW()
            WHERE id = :id');
        $update->execute([
            ':cover' => $coverValue,
            ':gallery' => json_encode($gallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':scan' => 'unscanned',
            ':id' => $facilityId,
        ]);

        $result['ok'] = true;
        $result['public_url'] = $publicUrl;
        $result['debug_realpath'] = realpath($finalPath);
        $result['debug_exists'] = is_file($finalPath);
        $result['debug_dir'] = realpath($dir);
        $result['width'] = $rendered['width'] ?? null;
        $result['height'] = $rendered['height'] ?? null;
        $result['message'] = 'Đã thay ảnh và đóng logo.';
    } catch (Throwable $error) {
        $result['message'] = $error->getMessage();
        error_log('Facility image replace: ' . get_class($error) . ': ' . $error->getMessage());
    }
    $results[] = $result;
}

$okCount = count(array_filter($results, static fn(array $r): bool => $r['ok']));
json_response(['ok' => $okCount > 0, 'processed' => count($results), 'succeeded' => $okCount, 'results' => $results]);
