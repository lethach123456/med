<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_media_library.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (!isset($_FILES['file'])) {
    json_response(['ok' => false, 'message' => 'Thiếu file upload.'], 422);
}

$file = $_FILES['file'];
if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['name'], $file['size'])) {
    json_response(['ok' => false, 'message' => 'File upload không hợp lệ.'], 422);
}

if ((int) $file['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'message' => 'Upload lỗi (code: ' . (int) $file['error'] . ').'], 422);
}

$maxBytes = 25 * 1024 * 1024;
if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) {
    json_response(['ok' => false, 'message' => 'File quá lớn (tối đa 25MB).'], 422);
}

$tmpPath = (string) $file['tmp_name'];
if (!is_uploaded_file($tmpPath)) {
    json_response(['ok' => false, 'message' => 'Upload không hợp lệ.'], 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($tmpPath);

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'video/mp4' => 'mp4',
];

if (!array_key_exists($mime, $allowed)) {
    json_response(['ok' => false, 'message' => 'Chỉ cho phép upload ảnh (jpg/png/webp/gif) hoặc video mp4.'], 422);
}

$ext = $allowed[$mime];
$type = (strncmp($mime, 'image/', 6) === 0) ? 'image' : 'video';

if ($type === 'image') {
    $info = @getimagesize($tmpPath);
    if ($info === false) {
        json_response(['ok' => false, 'message' => 'Ảnh không hợp lệ.'], 422);
    }
}

$baseDir = medical_media_project_root();
if (!is_string($baseDir) || $baseDir === '') {
    json_response(['ok' => false, 'message' => 'Không xác định được thư mục dự án.'], 500);
}

$requestedFolder = medical_media_normalize_folder((string) ($_POST['folder'] ?? ''));
if ($requestedFolder === null) {
    json_response(['ok' => false, 'message' => 'Folder upload không hợp lệ.'], 422);
}
if ($requestedFolder === '') $requestedFolder = date('Y') . '/' . date('m');
$folder = medical_media_create_folder($requestedFolder);
if ($folder === null) {
    json_response(['ok' => false, 'message' => 'Không tạo được thư mục upload.'], 500);
}
$subDir = 'uploads/library/' . $folder['folder'];
$targetDir = $folder['path'];

$random = bin2hex(random_bytes(16));
$filename = $random . '.' . $ext;
$targetPath = $targetDir . '/' . $filename;

if (!move_uploaded_file($tmpPath, $targetPath)) {
    json_response(['ok' => false, 'message' => 'Không lưu được file.'], 500);
}

chmod($targetPath, 0644);

$url = '/' . $subDir . '/' . $filename;

$compression = [
    'attempted' => false,
    'ok' => false,
    'skipped' => false,
];

// Every JPG, PNG and WebP uploaded into the media library is immediately
// passed through the same <200 KB JPEG compressor as the manual action.
// GIF is intentionally excluded so animated uploads are not flattened.
if ($type === 'image' && in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    $compression['attempted'] = true;
    $compressed = medical_media_compress_local_image($targetPath, true);
    if (($compressed['ok'] ?? false) && is_array($compressed['file'] ?? null)) {
        $compressedFile = $compressed['file'];
        $compressedUrl = (string) ($compressedFile['url'] ?? '');
        $compressedPath = $compressedUrl !== '' ? medical_media_local_library_path($compressedUrl) : null;
        if ($compressedPath !== null) {
            $targetPath = $compressedPath;
            $url = $compressedUrl;
            $ext = 'jpg';
            $mime = 'image/jpeg';
            $compression = [
                'attempted' => true,
                'ok' => true,
                'skipped' => false,
                'message' => (string) ($compressed['message'] ?? 'Đã nén ảnh.'),
                'old_size' => (int) ($compressedFile['old_size'] ?? 0),
                'new_size' => (int) ($compressedFile['new_size'] ?? 0),
                'source_removed' => (bool) ($compressedFile['source_removed'] ?? false),
            ];
            $warning = trim((string) ($compressedFile['warning'] ?? ''));
            if ($warning !== '') $compression['warning'] = $warning;
        } else {
            $compression['warning'] = 'Đã nén ảnh nhưng không xác định được file đầu ra.';
        }
    } else {
        // Preserve the just-uploaded file if compression cannot complete; the
        // response tells the interface exactly why it was not compressed.
        $compression['warning'] = (string) ($compressed['error'] ?? 'Không thể tự nén ảnh.');
    }
} elseif ($type === 'image' && $mime === 'image/gif') {
    $compression = [
        'attempted' => false,
        'ok' => false,
        'skipped' => true,
        'message' => 'Ảnh GIF được giữ nguyên để bảo toàn ảnh động.',
    ];
}

$message = ($compression['ok'] ?? false) ? 'Upload và nén ảnh thành công.' : 'Upload thành công.';
if (!empty($compression['warning'])) $message .= ' ' . (string) $compression['warning'];

json_response([
    'ok' => true,
    'message' => $message,
    'file' => [
        'url' => $url,
        'folder' => $folder['folder'],
        'type' => $type,
        'mime' => $mime,
        'size' => (int) (is_file($targetPath) ? (filesize($targetPath) ?: (int) $file['size']) : (int) $file['size']),
        'name' => (string) ($file['name'] ?? ''),
        'compression' => $compression,
    ],
]);
