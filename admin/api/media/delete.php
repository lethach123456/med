<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = read_json_body();
$url = isset($body['url']) ? (string) $body['url'] : '';
$url = trim($url);

if ($url === '') {
    json_response(['ok' => false, 'message' => 'Thiếu url.'], 422);
}

$parsed = parse_url($url);
$path = isset($parsed['path']) ? (string) $parsed['path'] : $url;

if (strpos($path, "\0") !== false) {
    json_response(['ok' => false, 'message' => 'Đường dẫn không hợp lệ.'], 422);
}

if (strncmp($path, '/uploads/library/', 16) !== 0) {
    json_response(['ok' => false, 'message' => 'Chỉ được xoá file trong /uploads/library/.'], 403);
}

if (strpos($path, '..') !== false) {
    json_response(['ok' => false, 'message' => 'Đường dẫn không hợp lệ.'], 422);
}

$baseDir = realpath(__DIR__ . '/../../../');
if (!is_string($baseDir) || $baseDir === '') {
    json_response(['ok' => false, 'message' => 'Không xác định được thư mục dự án.'], 500);
}

$libraryDir = $baseDir . '/uploads/library';
$target = $baseDir . $path;
$realTarget = realpath($target);

if (!is_string($realTarget) || $realTarget === '') {
    json_response(['ok' => false, 'message' => 'File không tồn tại.'], 404);
}

if (strncmp($realTarget, $libraryDir, strlen($libraryDir)) !== 0) {
    json_response(['ok' => false, 'message' => 'Không được xoá ngoài thư viện.'], 403);
}

if (!is_file($realTarget)) {
    json_response(['ok' => false, 'message' => 'Không phải file.'], 422);
}

if (!unlink($realTarget)) {
    json_response(['ok' => false, 'message' => 'Xoá thất bại.'], 500);
}

json_response(['ok' => true, 'message' => 'Đã xoá file.']);

