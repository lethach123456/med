<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_media_library.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$url = isset($body['url']) ? trim((string) $body['url']) : '';
if ($url === '' || $url[0] !== '/' || str_contains($url, '..')) {
    json_response(['ok' => false, 'message' => 'URL không hợp lệ.'], 422);
}

$baseDir = medical_media_project_root();
$abs = realpath($baseDir . $url);
if (!is_string($abs) || $abs === '' || !is_file($abs)) {
    json_response(['ok' => false, 'message' => 'File không tồn tại.'], 404);
}

$libraryDir = realpath(medical_media_library_dir());
if (!is_string($libraryDir) || $libraryDir === '' || !str_starts_with($abs, $libraryDir . DIRECTORY_SEPARATOR)) {
    json_response(['ok' => false, 'message' => 'Chỉ cho phép nén file trong uploads/library.'], 403);
}

$result = medical_media_compress_local_image($abs);
if (!($result['ok'] ?? false)) {
    json_response([
        'ok' => false,
        'message' => (string) ($result['error'] ?? 'Nén ảnh thất bại.'),
    ], (int) ($result['status'] ?? 500));
}

json_response([
    'ok' => true,
    'message' => (string) ($result['message'] ?? 'Đã nén ảnh.'),
    'file' => is_array($result['file'] ?? null) ? $result['file'] : [],
]);
