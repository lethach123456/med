<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_media_library.php';

admin_require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$name = trim((string) ($body['name'] ?? ''));
$parent = medical_media_normalize_folder((string) ($body['parent'] ?? ''));
if ($name === '' || $parent === null) {
    json_response(['ok' => false, 'message' => 'Tên hoặc đường dẫn folder không hợp lệ.'], 422);
}
$segment = medical_media_folder_slug($name);
if ($segment === '') {
    json_response(['ok' => false, 'message' => 'Tên folder không hợp lệ.'], 422);
}
$folder = ($parent !== '' ? $parent . '/' : '') . $segment;
$folder = medical_media_normalize_folder($folder);
if ($folder === null || $folder === '') {
    json_response(['ok' => false, 'message' => 'Không tạo được folder.'], 422);
}

$target = medical_media_library_dir() . '/' . $folder;
$exists = is_dir($target);
$created = medical_media_create_folder($folder);
if ($created === null) {
    json_response(['ok' => false, 'message' => 'Không tạo được folder trên máy chủ.'], 500);
}

json_response([
    'ok' => true,
    'message' => $exists ? 'Folder đã tồn tại.' : 'Đã tạo folder.',
    'folder' => $created['folder'],
    'url' => $created['url'],
    'created' => !$exists,
]);
