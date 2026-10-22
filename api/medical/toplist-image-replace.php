<?php
declare(strict_types=1);

/**
 * API thay ảnh đại diện cho bài Toplist: nhận URL ảnh -> nén -> lưu trữ nội bộ.
 * Xác thực bằng header X-Medical-Api-Key (giống các API bài viết / cơ sở).
 *
 * POST JSON:
 * {
 *   "replacements": [
 *     {"toplist_id": 12, "image_url": "https://..."},
 *     ...
 *   ]
 * }
 * (hoặc một object đơn {"toplist_id": ..., "image_url": ...})
 * - toplist_id: id bài Toplist (bảng medical_toplists)
 * - image_url: URL ảnh mới (http/https)
 *
 * Luồng xử lý mỗi ảnh: tải về -> kiểm tra ảnh thật -> nén JPEG dưới 150.000
 * bytes, cạnh dài tối đa 1280px -> lưu vào uploads/library/toplists/{id}/
 * -> cập nhật medical_toplists.featured_image_url. KHÁC facility/doctor
 * image-replace: ảnh Toplist KHÔNG đóng wordmark MedReview.
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../medical_media_library.php';
require_once __DIR__ . '/../../medical_search_cache.php';

medical_api_auth();
header('Cache-Control: private, no-store, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'message' => 'Chỉ hỗ trợ POST.'], 405);
}

$body = read_json_body();
$replacements = $body['replacements'] ?? null;
if ($replacements === null && isset($body['toplist_id'], $body['image_url'])) {
    $replacements = [$body];
}
if (!is_array($replacements) || $replacements === [] || count($replacements) > 50) {
    json_response(['ok' => false, 'message' => 'Cần mảng replacements (1-50 phần tử).'], 422);
}

function toplist_image_replace_download(string $url): string
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

/**
 * Compress + store WITHOUT the MedReview wordmark (toplist cover policy),
 * mirroring the verification medical_replacement_image_store applies after
 * branding: only a decodable JPEG below 150 KB / 1280px is published.
 * @return array{url:string,width:int,height:int,size:int,original_size:int,target_bytes:int}
 */
function toplist_image_store(string $bytes, string $folder): array
{
    if (strlen($bytes) > 12 * 1024 * 1024) throw new InvalidArgumentException('Ảnh Toplist không được vượt quá 12 MB.');
    $info = @getimagesizefromstring($bytes);
    if (!is_array($info)
        || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
        || ($info[0] ?? 0) < 200 || ($info[1] ?? 0) < 200 || $info[0] * $info[1] > 12_000_000) {
        throw new InvalidArgumentException('Ảnh Toplist phải là PNG/JPEG/WebP thật, tối thiểu 200×200, tối đa 12 MB và 12 megapixel.');
    }
    if (!extension_loaded('gd') || !function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        throw new RuntimeException('Máy chủ chưa bật GD để xử lý ảnh Toplist.');
    }
    if (medical_media_normalize_folder($folder) === null || trim($folder, '/ ') === '') {
        throw new InvalidArgumentException('Thư mục lưu ảnh Toplist không hợp lệ.');
    }
    $targetBytes = 149999;
    $stored = medical_media_store_compressed_jpeg($bytes, $folder, $targetBytes);
    if (!($stored['ok'] ?? false) || !is_string($stored['url'] ?? null)) {
        throw new RuntimeException((string) ($stored['error'] ?? 'Không nén/lưu được ảnh Toplist.'));
    }
    $path = medical_media_local_library_path($stored['url']);
    $final = is_string($path) ? @getimagesize($path) : false;
    $size = is_string($path) ? @filesize($path) : false;
    $decoded = is_array($final) && ($final[2] ?? 0) === IMAGETYPE_JPEG ? @imagecreatefromjpeg($path) : false;
    if (!$decoded instanceof GdImage || !is_int($size) || $size < 1 || $size > $targetBytes || max((int) $final[0], (int) $final[1]) > 1280) {
        if ($decoded instanceof GdImage) imagedestroy($decoded);
        throw new RuntimeException('Ảnh Toplist đã lưu không giải mã được hoặc chưa đạt giới hạn 150 KB/1280px.');
    }
    imagedestroy($decoded);
    @chmod(dirname($path), 0755);
    @chmod($path, 0644);
    return array_replace($stored, ['width' => (int) $final[0], 'height' => (int) $final[1], 'size' => $size,
        'original_size' => strlen($bytes), 'target_bytes' => $targetBytes]);
}

$results = [];
$pdo = db();
foreach ($replacements as $index => $item) {
    $result = ['index' => $index, 'ok' => false];
    try {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Mỗi phần tử phải là object.');
        }
        $toplistId = (int) ($item['toplist_id'] ?? $item['id'] ?? 0);
        $newUrl = trim((string) ($item['image_url'] ?? ''));
        if ($toplistId < 1 || $newUrl === '') {
            throw new InvalidArgumentException('Thiếu toplist_id / image_url hợp lệ.');
        }
        $result['toplist_id'] = $toplistId;

        $stmt = $pdo->prepare('SELECT id, featured_image_url FROM medical_toplists WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $toplistId]);
        $toplist = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$toplist) {
            throw new RuntimeException('Không tìm thấy Toplist #' . $toplistId);
        }
        $result['previous_url'] = (string) ($toplist['featured_image_url'] ?? '');

        // 1. Tải ảnh mới
        $data = toplist_image_replace_download($newUrl);
        // 2. Nén + lưu nội bộ (không đóng logo).
        $stored = toplist_image_store($data, 'toplists/' . $toplistId);
        $publicUrl = $stored['url'];

        // 3. Cập nhật DB: featured_image_url (updated_at tự cập nhật theo schema).
        $update = $pdo->prepare('UPDATE medical_toplists SET featured_image_url = :url WHERE id = :id');
        $update->execute([':url' => $publicUrl, ':id' => $toplistId]);

        $result['ok'] = true;
        $result['public_url'] = $publicUrl;
        $result['width'] = $stored['width'];
        $result['height'] = $stored['height'];
        $result['size_bytes'] = $stored['size'];
        $result['original_size_bytes'] = $stored['original_size'];
        $result['message'] = 'Đã thay ảnh đại diện và nén dưới 150 KB (không đóng logo).';
    } catch (Throwable $error) {
        $result['message'] = $error->getMessage();
        error_log('Toplist image replace: ' . get_class($error) . ': ' . $error->getMessage());
    }
    $results[] = $result;
}

$okCount = count(array_filter($results, static fn(array $r): bool => $r['ok']));
if ($okCount > 0) medical_search_cache_invalidate();
json_response(['ok' => $okCount > 0, 'processed' => count($results), 'succeeded' => $okCount, 'results' => $results]);
