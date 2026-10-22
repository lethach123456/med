<?php
declare(strict_types=1);

require_once __DIR__ . '/medical_image_brand_renderer.php';
require_once __DIR__ . '/medical_media_library.php';

/**
 * Replacement APIs share one policy: brand first, then publish only a verified
 * JPEG strictly below 150 decimal KB. Originals/staging stay outside uploads.
 * No DB/network access or changes to the previous entity image.
 * @return array{ok:bool,url:string,width:int,height:int,size:int,original_size:int,branded_size:int,target_bytes:int}
 */
function medical_replacement_image_store(string $bytes, string $folder, string $entity): array
{
    $label = match ($entity) {
        'doctor' => 'bác sĩ', 'facility' => 'cơ sở y tế', 'toplist' => 'Toplist',
        default => throw new InvalidArgumentException('Loại ảnh thay thế không hợp lệ.'),
    };
    if (strlen($bytes) > 12 * 1024 * 1024) throw new InvalidArgumentException('Ảnh ' . $label . ' không được vượt quá 12 MB.');
    $info = @getimagesizefromstring($bytes);
    if (!is_array($info)
        || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
        || ($info[0] ?? 0) < 200 || ($info[1] ?? 0) < 200 || $info[0] * $info[1] > 12_000_000) {
        throw new InvalidArgumentException('Ảnh ' . $label . ' phải là PNG/JPEG/WebP thật, tối thiểu 200×200, tối đa 12 MB và 12 megapixel.');
    }
    if (!extension_loaded('gd') || !function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        throw new RuntimeException('Máy chủ chưa bật GD để xử lý ảnh ' . $label . '.');
    }
    if (medical_media_normalize_folder($folder) === null || trim($folder, '/ ') === '') {
        throw new InvalidArgumentException('Thư mục lưu ảnh ' . $label . ' không hợp lệ.');
    }
    $sourcePath = tempnam(sys_get_temp_dir(), 'medreview-' . $entity . '-source-');
    if (!is_string($sourcePath)) throw new RuntimeException('Không tạo được file ảnh tạm.');
    $brandedPath = $sourcePath . '.jpg';
    try {
        if (file_put_contents($sourcePath, $bytes, LOCK_EX) !== strlen($bytes)) throw new RuntimeException('Không lưu được file ảnh tạm.');
        medical_image_brand_render($sourcePath, $brandedPath, ['format' => 'jpeg']);
        $branded = @file_get_contents($brandedPath);
        if (!is_string($branded) || $branded === '') throw new RuntimeException('Không đọc được ảnh đã đóng logo.');
        $targetBytes = 149999;
        $stored = medical_media_store_compressed_jpeg($branded, $folder, $targetBytes);
        if (!($stored['ok'] ?? false) || !is_string($stored['url'] ?? null)) {
            throw new RuntimeException((string) ($stored['error'] ?? 'Không nén/lưu được ảnh ' . $label . '.'));
        }
        $path = medical_media_local_library_path($stored['url']);
        $final = is_string($path) ? @getimagesize($path) : false;
        $size = is_string($path) ? @filesize($path) : false;
        $decoded = is_array($final) && ($final[2] ?? 0) === IMAGETYPE_JPEG ? @imagecreatefromjpeg($path) : false;
        if (!$decoded instanceof GdImage || !is_int($size) || $size < 1 || $size > $targetBytes || max((int) $final[0], (int) $final[1]) > 1280) {
            if ($decoded instanceof GdImage) imagedestroy($decoded);
            throw new RuntimeException('Ảnh ' . $label . ' đã lưu không giải mã được hoặc chưa đạt giới hạn 150 KB/1280px.');
        }
        imagedestroy($decoded);
        @chmod(dirname($path), 0755);
        @chmod($path, 0644);
        return array_replace($stored, ['width' => (int) $final[0], 'height' => (int) $final[1], 'size' => $size,
            'original_size' => strlen($bytes), 'branded_size' => strlen($branded), 'target_bytes' => $targetBytes]);
    } finally {
        @unlink($sourcePath);
        if (is_file($brandedPath)) @unlink($brandedPath);
    }
}
