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
 * MedReview -> lưu vào uploads/library/doctors/{id}/ -> cập nhật DB.
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
        $hash = substr(hash('sha256', $data), 0, 16);

        // 2. Lưu file tạm rồi đóng logo
        $dir = __DIR__ . '/../../uploads/library/doctors/' . $doctorId;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Không tạo được thư mục lưu ảnh.');
        }
        // Đảm bảo web server đọc được (tránh lỗi quyền 0750 như cơ sở từng gặp)
        @chmod($dir, 0755);
        $tmpPath = $dir . '/.tmp-' . $hash;
        if (file_put_contents($tmpPath, $data, LOCK_EX) !== strlen($data)) {
            throw new RuntimeException('Không lưu được file ảnh tạm.');
        }
        $finalPath = $dir . '/image-' . $hash . '.jpg';
        try {
            $rendered = medical_image_brand_render($tmpPath, $finalPath, ['format' => 'jpeg']);
        } finally {
            @unlink($tmpPath);
        }
        // Đảm bảo web server đọc được file ảnh
        @chmod($finalPath, 0644);
        $publicUrl = '/uploads/library/doctors/' . $doctorId . '/image-' . $hash . '.jpg';

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
        $result['width'] = $rendered['width'] ?? null;
        $result['height'] = $rendered['height'] ?? null;
        $result['message'] = 'Đã thay ảnh và đóng logo.';
    } catch (Throwable $error) {
        $result['message'] = $error->getMessage();
        error_log('Doctor image replace: ' . get_class($error) . ': ' . $error->getMessage());
    }
    $results[] = $result;
}

$okCount = count(array_filter($results, static fn(array $r): bool => $r['ok']));
json_response(['ok' => $okCount > 0, 'processed' => count($results), 'succeeded' => $okCount, 'results' => $results]);
