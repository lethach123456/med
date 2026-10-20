<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_facility_images.php';
require_once dirname(__DIR__) . '/medical_doctor_images.php';

if (!extension_loaded('gd')) throw new RuntimeException('Facility compression fixtures require GD.');
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void { ++$checks; if (!$ok) throw new RuntimeException($message); };
$reject = static function (callable $operation, string $message) use ($assert): void {
    try { $operation(); }
    catch (InvalidArgumentException|RuntimeException) { $assert(true, $message); return; }
    $assert(false, $message);
};
$folder = 'test-facility-compression-' . bin2hex(random_bytes(10));
$root = medical_media_library_dir() . '/' . $folder;
$files = []; $directories = [];
$stagesBefore = glob(sys_get_temp_dir() . '/medreview-facility-source-*') ?: [];
$doctorStagesBefore = glob(sys_get_temp_dir() . '/medreview-doctor-source-*') ?: [];
$remember = static function (array $stored, string $label) use ($root, &$files, $assert): array {
    $assert(($stored['ok'] ?? false) === true && is_string($stored['url'] ?? null), $label . ': store succeeds');
    $path = medical_media_local_library_path($stored['url']);
    $assert(is_string($path) && is_file($path) && str_starts_with($path, $root . '/'), $label . ': only a file in this random test folder is published');
    $files[] = $path;
    clearstatcache(true, $path);
    $info = getimagesize($path); $decoded = @imagecreatefromjpeg($path);
    $assert(is_array($info) && $info[2] === IMAGETYPE_JPEG && $decoded instanceof GdImage, $label . ': final complete raster is JPEG');
    $assert(filesize($path) === $stored['size'] && $stored['size'] > 0 && $stored['size'] < 150000, $label . ': stored file is strictly below 150,000 bytes');
    $assert($info[0] === $stored['width'] && $info[1] === $stored['height'] && max($info[0], $info[1]) <= 1280, $label . ': metadata matches a max-1280px raster');
    $assert($stored['target_bytes'] === 149999 && $stored['original_size'] > 0 && $stored['branded_size'] > 0, $label . ': compression metadata states original/branded/strict target sizes');
    imagedestroy($decoded);
    return [$path, $info];
};
$encode = static function (GdImage $image, string $format): string {
    ob_start();
    try {
        $ok = match ($format) { 'jpeg' => imagejpeg($image, null, 95), 'webp' => imagewebp($image, null, 90), default => imagepng($image) };
        $bytes = (string) ob_get_contents();
    } finally { ob_end_clean(); }
    if (!$ok || $bytes === '') throw new RuntimeException('Cannot encode a synthetic facility image.');
    return $bytes;
};
$make = static function (int $width, int $height, bool $noise = false): GdImage {
    $image = imagecreatetruecolor($width, $height); imagefill($image, 0, 0, 0xe3eef6);
    if ($noise) {
        mt_srand(976321);
        for ($y = 0; $y < $height; $y++) for ($x = 0; $x < $width; $x++) imagesetpixel($image, $x, $y, mt_rand(0, 0xffffff));
    } else {
        imagefilledrectangle($image, 0, (int) ($height / 2), $width - 1, $height - 1, 0x7faaba);
        imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($width / 2), (int) ($height / 2), 0x245ca5);
    }
    return $image;
};

try {
    // No real facility image, remote request, application DB or cache is used.
    $noise = $make(1800, 1200, true); $png = $encode($noise, 'png'); imagedestroy($noise);
    $assert(strlen($png) > 150000, 'Large high-entropy landscape genuinely needs compression');
    $directories[] = $root . '/landscape';
    $large = medical_facility_image_store($png, $folder . '/landscape');
    [$largePath, $largeInfo] = $remember($large, 'Noisy landscape PNG');
    $assert(abs($largeInfo[0] / $largeInfo[1] - 1.5) < .01 && $largeInfo[0] <= 1280, 'Landscape is not cropped or stretched');
    $assert($large['original_size'] === strlen($png), 'Original byte metadata is the exact input size');
    $originalHash = hash_file('sha256', $largePath);
    $repeated = medical_facility_image_store($png, $folder . '/landscape');
    [$repeatedPath] = $remember($repeated, 'Repeated source');
    $assert($repeatedPath === $largePath && hash_file('sha256', $largePath) === $originalHash, 'Retry reuses unchanged valid compressed output');
    file_put_contents($largePath, 'corrupt fixture cache');
    $repaired = medical_facility_image_store($png, $folder . '/landscape');
    [$repairedPath] = $remember($repaired, 'Corrupt cached output');
    $assert($repairedPath === $largePath && hash_file('sha256', $largePath) === $originalHash, 'Corrupt cached output is replaced with the verified compressed raster');
    file_put_contents($largePath, str_repeat('x', 150001));
    $repairedLarge = medical_facility_image_store($png, $folder . '/landscape');
    [$repairedLargePath] = $remember($repairedLarge, 'Over-cap cached output');
    $assert($repairedLargePath === $largePath && hash_file('sha256', $largePath) === $originalHash, 'Over-cap legacy cache is not silently reused');
    $wrongRaster = $make(220, 220); imagejpeg($wrongRaster, $largePath, 80); imagedestroy($wrongRaster);
    $repairedDimensions = medical_facility_image_store($png, $folder . '/landscape');
    [$repairedDimensionsPath] = $remember($repairedDimensions, 'Wrong-dimension cached output');
    $assert($repairedDimensionsPath === $largePath && hash_file('sha256', $largePath) === $originalHash, 'A valid JPEG of wrong dimensions is repaired as well');

    $lastPortraitBytes = '';
    foreach (['png', 'jpeg', 'webp'] as $format) {
        if ($format === 'webp' && !function_exists('imagewebp')) continue;
        $portrait = $make(600, 1000); $bytes = $encode($portrait, $format); imagedestroy($portrait); $lastPortraitBytes = $bytes;
        $directories[] = $root . '/' . $format;
        [$path, $info] = $remember(medical_facility_image_store($bytes, $folder . '/' . $format), strtoupper($format) . ' portrait');
        $assert($info[0] === 600 && $info[1] === 1000, strtoupper($format) . ': small-enough dimensions stay unchanged');
        $source = imagecreatefromstring($bytes); $final = imagecreatefromjpeg($path); $changed = 0;
        // Synthetic top-right is flat; wordmark pixels are dark navy/royal blue.
        for ($y = 30; $y < 100; $y++) for ($x = 420; $x < 580; $x++) {
            $a = imagecolorat($source, $x, $y); $b = imagecolorat($final, $x, $y);
            if (abs((($a >> 16) & 255) - (($b >> 16) & 255)) > 45 || abs((($a >> 8) & 255) - (($b >> 8) & 255)) > 45) $changed++;
        }
        $assert($changed > 100, strtoupper($format) . ': wordmark remains visible in the top-right after compression');
        imagedestroy($source); imagedestroy($final);
    }
    $small = $make(200, 200); $smallBytes = $encode($small, 'png'); imagedestroy($small);
    $directories[] = $root . '/small';
    [$smallPath, $smallInfo] = $remember(medical_facility_image_store($smallBytes, $folder . '/small'), 'Minimum-size PNG');
    $assert($smallInfo[0] === 200 && $smallInfo[1] === 200, 'Minimum-size image is accepted without upscaling');

    // Both wrappers must keep exactly the same policy after extraction.
    $directories[] = $root . '/facility-pair'; $directories[] = $root . '/doctor-pair';
    [$facilityPair] = $remember(medical_facility_image_store($lastPortraitBytes, $folder . '/facility-pair'), 'Facility shared policy');
    [$doctorPair] = $remember(medical_doctor_image_store($lastPortraitBytes, $folder . '/doctor-pair'), 'Doctor shared policy');
    $assert(hash_file('sha256', $facilityPair) === hash_file('sha256', $doctorPair), 'Doctor and facility wrappers produce the same branded compressed image');

    $invalidFolder = $folder . '/invalid';
    $reject(static fn () => medical_facility_image_store('not a raster', $invalidFolder), 'Non-image bytes are rejected');
    $undersize = $make(199, 240); $undersizeBytes = $encode($undersize, 'png'); imagedestroy($undersize);
    $reject(static fn () => medical_facility_image_store($undersizeBytes, $invalidFolder), 'Below-200px image is rejected');
    $gif = $make(300, 300); ob_start(); imagegif($gif); $gifBytes = (string) ob_get_clean(); imagedestroy($gif);
    $reject(static fn () => medical_facility_image_store($gifBytes, $invalidFolder), 'GIF is rejected even if decodable');
    $reject(static fn () => medical_facility_image_store(str_repeat('x', 12 * 1024 * 1024 + 1), $invalidFolder), '12-MiB encoded-byte cap is enforced');
    $oversized = substr_replace(substr_replace($smallBytes, pack('N', 4000), 16, 4), pack('N', 4000), 20, 4);
    $reject(static fn () => medical_facility_image_store($oversized, $invalidFolder), '12-megapixel cap is checked before raster decoding');
    $truncated = substr($png, 0, 40);
    $assert(is_array(getimagesizefromstring($truncated)), 'Truncated fixture still has a plausible PNG header');
    $reject(static fn () => medical_facility_image_store($truncated, $invalidFolder), 'Header-only image cannot pass full decoding');
    $reject(static fn () => medical_facility_image_store($smallBytes, '../outside-facility-test'), 'Traversal storage folder rejected');
    $reject(static fn () => medical_facility_image_store($smallBytes, ''), 'Empty storage folder rejected');
    $reject(static fn () => medical_replacement_image_store($smallBytes, $invalidFolder, 'unknown'), 'Shared replacement helper rejects unknown entity types');
    $assert(!is_dir($root . '/invalid'), 'Invalid input never creates a public destination');
    $assert(hash_file('sha256', $largePath) === $originalHash && is_file($smallPath), 'Rejected replacements do not change existing valid files');
    $assert(array_diff(glob(sys_get_temp_dir() . '/medreview-facility-source-*') ?: [], $stagesBefore) === [], 'Facility source/branded private staging is cleaned on success and failure');
    $assert(array_diff(glob(sys_get_temp_dir() . '/medreview-doctor-source-*') ?: [], $doctorStagesBefore) === [], 'Compatibility doctor wrapper also cleans staging');

    $api = (string) file_get_contents(dirname(__DIR__) . '/api/medical/facility-image-replace.php');
    $storeAt = strpos($api, 'medical_facility_image_store('); $updateAt = strpos($api, 'UPDATE medical_facilities');
    $assert($storeAt !== false && $updateAt !== false && $storeAt < $updateAt, 'API updates DB only after branded compressed image is valid and stored');
    $assert(str_contains($api, "['size_bytes']") && str_contains($api, "['original_size_bytes']"), 'API exposes compressed/original byte metadata');
    $assert(substr_count($api, 'medical_search_cache_invalidate();') === 1 && str_contains($api, 'if ($okCount > 0) medical_search_cache_invalidate();'), 'Successful batch invalidates search cache once, failed batch never does');
    $assert(!str_contains($api, '$tmpPath') && !str_contains($api, 'medical_image_brand_render('), 'API no longer publishes original staging bytes before compression');
    $galleryStart = strpos($api, '$gallery = [];'); $galleryEnd = strpos($api, '$update = $pdo->prepare(', $galleryStart);
    $assert($galleryStart !== false && $galleryEnd !== false, 'Existing pure gallery-update fragment remains available');
    $galleryCode = substr($api, $galleryStart, $galleryEnd - $galleryStart);
    $galleryUpdate = static function (array $facility, int $galleryIndex, string $caption, string $publicUrl) use ($galleryCode): array {
        eval($galleryCode); return [$gallery, $coverValue];
    };
    $oldFacility = ['image_url' => '/old-cover.jpg', 'gallery_json' => json_encode([
        ['url' => '/old-cover.jpg', 'caption' => 'Mặt tiền', 'alt' => 'Alt giữ nguyên', 'source' => 'Google Maps'],
        ['url' => '/old-room.jpg', 'caption' => 'Phòng khám', 'source' => 'Website'],
    ], JSON_THROW_ON_ERROR)];
    [$gallery, $cover] = $galleryUpdate($oldFacility, 1, '', '/new-room.jpg');
    $assert($cover === '/old-cover.jpg' && $gallery[1] === ['url' => '/new-room.jpg', 'caption' => 'Phòng khám', 'source' => 'Website'], 'Gallery-only replacement preserves cover and previous caption/source');
    [$gallery, $cover] = $galleryUpdate($oldFacility, 0, 'Mặt tiền mới', '/new-cover.jpg');
    $assert($cover === '/new-cover.jpg' && $gallery[0]['caption'] === 'Mặt tiền mới' && $gallery[0]['alt'] === 'Alt giữ nguyên' && $gallery[1]['url'] === '/old-room.jpg', 'Cover replacement keeps other gallery entries and metadata');
    [$gallery, $cover] = $galleryUpdate($oldFacility, 5, 'Ghế điều trị', '/new-chair.jpg');
    $assert(count($gallery) === 3 && $gallery[2]['url'] === '/new-chair.jpg' && $cover === '/old-cover.jpg', 'Appending a gallery image retains existing cover semantics');
    foreach ($directories as $directory) $assert(glob($directory . '/*.tmp-*') === [], 'No partial compressed publication remains');
    echo "Facility image compression: {$checks} checks passed; synthetic GD fixtures only, no DB/network.\n";
} finally {
    // Only exact paths returned for this randomly named fixture are removed.
    foreach (array_unique($files) as $path) if (str_starts_with($path, $root . '/') && is_file($path)) unlink($path);
    foreach (array_reverse(array_unique($directories)) as $directory) {
        if (str_starts_with($directory, $root . '/') && is_dir($directory) && count(scandir($directory)) === 2) rmdir($directory);
    }
    if (is_dir($root) && count(scandir($root)) === 2) rmdir($root);
}
