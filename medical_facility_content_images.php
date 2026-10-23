<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_facility_images.php';
require_once __DIR__ . '/medical_patient_images.php';

/** Resolve only genuine local library URLs; a foreign host never aliases a local file. */
function medical_facility_content_local_image(string $url): ?string
{
    $parts = parse_url($url);
    if (!is_array($parts)) return null;
    if (isset($parts['host'])) {
        if (!function_exists('site_absolute_url')) return null;
        $origin = parse_url(site_absolute_url('/'));
        if (strtolower((string) $parts['host']) !== strtolower((string) ($origin['host'] ?? ''))
            || (int) ($parts['port'] ?? 0) !== (int) ($origin['port'] ?? 0)) return null;
    }
    $path = (string) ($parts['path'] ?? '');
    return str_starts_with($path, '/uploads/library/') ? medical_media_local_library_path($path) : null;
}

/** Verify an owned output before reusing it, so retries never stamp a second logo. */
function medical_facility_content_branded_file(string $path, string $folder): bool
{
    $root = realpath(medical_media_library_dir() . '/' . $folder);
    if (!is_string($root) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) return false;
    $size = @filesize($path); $info = @getimagesize($path);
    if (!is_int($size) || $size < 1 || $size >= 150000 || !is_array($info)
        || ($info[2] ?? 0) !== IMAGETYPE_JPEG || min($info[0], $info[1]) < 1 || max($info[0], $info[1]) > 1280) return false;
    $image = function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false;
    if (!$image instanceof GdImage) return false;
    imagedestroy($image);
    return true;
}

/**
 * No DB writes. An item is published only after every requested image succeeds.
 * Callbacks are internal test seams, never selected by HTTP input.
 */
function medical_facility_content_import_images(int $facilityId, array $urls, float $deadline, ?callable $download = null, ?callable $store = null): array
{
    if ($facilityId < 1) throw new InvalidArgumentException('ID cơ sở không hợp lệ.');
    $urls = array_values(array_unique($urls));
    if (count($urls) > 12) throw new InvalidArgumentException('Mỗi cơ sở nhận tối đa 12 ảnh bìa/gallery khác nhau mỗi lượt.');
    $folder = 'facilities/' . $facilityId . '/content-branded-v1';
    $map = [];
    foreach ($urls as $url) {
        $url = medical_patient_images_url($url, 'URL ảnh cơ sở');
        $remaining = (int) floor($deadline - microtime(true));
        if ($remaining < 1) throw new RuntimeException('Hết thời gian xử lý ảnh; bài cũ được giữ nguyên, hãy gửi lại.');
        $path = medical_facility_content_local_image($url);
        if ($path !== null && medical_facility_content_branded_file($path, $folder)) {
            $map[$url] = '/uploads/library/' . $folder . '/' . basename($path);
            continue;
        }
        if ($path !== null) {
            $size = @filesize($path);
            if (!is_int($size) || $size > 12 * 1024 * 1024) throw new RuntimeException('Ảnh cơ sở nội bộ vượt giới hạn 12 MB.');
            $bytes = @file_get_contents($path);
        } elseif (str_starts_with($url, '/')) {
            throw new InvalidArgumentException('Chỉ đọc ảnh nội bộ có thật trong /uploads/library/.');
        } else {
            $result = $download !== null ? $download($url, min(8, $remaining)) : medical_media_download_remote_image($url, min(8, $remaining));
            if (!($result['ok'] ?? false) || !is_string($result['bytes'] ?? null)) {
                throw new RuntimeException((string) ($result['error'] ?? 'Không tải được ảnh cơ sở.'));
            }
            $bytes = $result['bytes'];
        }
        if (!is_string($bytes) || $bytes === '') throw new RuntimeException('Không đọc được ảnh cơ sở.');
        $stored = $store !== null ? $store($bytes, $folder) : medical_facility_image_store($bytes, $folder);
        if (!($stored['ok'] ?? false) || !is_string($stored['url'] ?? null)) throw new RuntimeException('Không lưu được ảnh đã đóng logo và nén.');
        $map[$url] = $stored['url'];
    }
    return $map;
}
