<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_doctor_images.php';

if (!extension_loaded('gd')) throw new RuntimeException('Doctor image compression tests require GD.');
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$reject = static function (callable $operation, string $message) use ($assert): void {
    try { $operation(); }
    catch (InvalidArgumentException|RuntimeException) { $assert(true, $message); return; }
    $assert(false, $message);
};
$folder = 'test-doctor-compression-' . bin2hex(random_bytes(10));
$root = medical_media_library_dir() . '/' . $folder;
$files = [];
$directories = [];
$stagesBefore = glob(sys_get_temp_dir() . '/medreview-doctor-source-*') ?: [];
$remember = static function (array $result, string $label) use ($assert, &$files): array {
    $assert(($result['ok'] ?? false) === true && is_string($result['url'] ?? null), $label . ': storage succeeded');
    $path = medical_media_local_library_path($result['url']);
    $assert(is_string($path) && is_file($path), $label . ': local file exists');
    $files[] = $path;
    $info = getimagesize($path);
    $decoded = imagecreatefromjpeg($path);
    $assert($decoded instanceof GdImage && $info[2] === IMAGETYPE_JPEG, $label . ': final file completely decodes as JPEG');
    $assert(filesize($path) === $result['size'] && $result['size'] < 150000, $label . ': final bytes strictly below 150 KB');
    $assert($info[0] === $result['width'] && $info[1] === $result['height'], $label . ': dimensions describe stored raster');
    $assert(max($info[0], $info[1]) <= 1280, $label . ': long edge bounded to 1280 pixels');
    imagedestroy($decoded);
    return [$path, $info];
};
$encode = static function (GdImage $image, string $format): string {
    ob_start();
    $ok = match ($format) {
        'jpeg' => imagejpeg($image, null, 95),
        'webp' => imagewebp($image, null, 90),
        default => imagepng($image),
    };
    $bytes = (string) ob_get_clean();
    if (!$ok || $bytes === '') throw new RuntimeException('Cannot build synthetic fixture.');
    return $bytes;
};
$make = static function (int $width, int $height, bool $noise = false): GdImage {
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, 0xe3eef6);
    if ($noise) {
        mt_srand(612359);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) imagesetpixel($image, $x, $y, mt_rand(0, 0xffffff));
        }
    } else {
        imagefilledrectangle($image, 0, (int) ($height / 2), $width - 1, $height - 1, 0x7faaba);
        imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($width / 2), (int) ($height / 2), 0x245ca5);
    }
    return $image;
};

try {
    // All fixtures are generated here; no real doctor data, DB or network is used.
    $noise = $make(1800, 1200, true);
    $png = $encode($noise, 'png');
    $assert(strlen($png) > 150000, 'High-entropy PNG fixture requires actual compression');
    $directories[] = $root . '/noise';
    $noiseResult = medical_doctor_image_store($png, $folder . '/noise');
    [$noisePath, $noiseInfo] = $remember($noiseResult, 'High-entropy PNG');
    $assert(abs($noiseInfo[0] / $noiseInfo[1] - 1.5) < 0.01, 'Resizing preserves landscape aspect ratio');
    $assert($noiseResult['original_size'] === strlen($png) && $noiseResult['branded_size'] > 0 && $noiseResult['target_bytes'] === 149999, 'Compression reports original, branded and strict target byte metadata');
    $before = hash_file('sha256', $noisePath);
    $again = medical_doctor_image_store($png, $folder . '/noise');
    [$samePath] = $remember($again, 'Repeated source');
    $assert($samePath === $noisePath && hash_file('sha256', $noisePath) === $before, 'Repeated valid image reuses an unchanged final file');
    file_put_contents($noisePath, 'corrupt cached bytes');
    $repaired = medical_doctor_image_store($png, $folder . '/noise');
    [$repairedPath] = $remember($repaired, 'Corrupt cached source');
    $assert($repairedPath === $noisePath && hash_file('sha256', $noisePath) === $before, 'Corrupt cached image is repaired instead of reused');
    file_put_contents($noisePath, str_repeat('x', 150001));
    $overLimit = medical_doctor_image_store($png, $folder . '/noise');
    [$overLimitPath] = $remember($overLimit, 'Over-limit cached source');
    $assert($overLimitPath === $noisePath && hash_file('sha256', $noisePath) === $before, 'Over-limit cached image is repaired under the new cap');
    $wrongDimensions = $make(220, 220);
    imagejpeg($wrongDimensions, $noisePath, 80); imagedestroy($wrongDimensions);
    $dimensionRepair = medical_doctor_image_store($png, $folder . '/noise');
    [$dimensionRepairPath] = $remember($dimensionRepair, 'Wrong-dimension cached source');
    $assert($dimensionRepairPath === $noisePath && hash_file('sha256', $noisePath) === $before, 'A decodable but wrong-dimension cached JPEG is repaired');

    $directories[] = $root . '/default';
    $default = medical_media_store_compressed_jpeg($png, $folder . '/default');
    [$defaultPath] = $remember($default, 'Shared default compression');
    $assert(filesize($defaultPath) < 102400, 'Shared two-argument caller retains its existing below-100-KiB cap');
    imagedestroy($noise);

    foreach (['png', 'jpeg', 'webp'] as $format) {
        if ($format === 'webp' && !function_exists('imagewebp')) continue;
        $portrait = $make(600, 1000);
        $bytes = $encode($portrait, $format);
        imagedestroy($portrait);
        $directories[] = $root . '/' . $format;
        $result = medical_doctor_image_store($bytes, $folder . '/' . $format);
        [$path, $info] = $remember($result, strtoupper($format) . ' portrait');
        $assert($info[0] === 600 && $info[1] === 1000, strtoupper($format) . ': safe portrait dimensions are retained');
        $source = imagecreatefromstring($bytes);
        $stored = imagecreatefromjpeg($path);
        $changed = 0;
        // Source is flat near the upper-right; the dark/blue wordmark changes it.
        for ($y = 30; $y < 100; $y++) {
            for ($x = 420; $x < 580; $x++) {
                $a = imagecolorat($source, $x, $y); $b = imagecolorat($stored, $x, $y);
                if (abs((($a >> 16) & 255) - (($b >> 16) & 255)) > 45
                    || abs((($a >> 8) & 255) - (($b >> 8) & 255)) > 45) $changed++;
            }
        }
        $assert($changed > 100, strtoupper($format) . ': wordmark remains visible after final compression');
        imagedestroy($source); imagedestroy($stored);
    }

    $invalidFolder = $folder . '/invalid';
    $reject(static fn() => medical_doctor_image_store('not a raster', $invalidFolder), 'Non-image data rejected');
    $small = $make(199, 240); $smallBytes = $encode($small, 'png'); imagedestroy($small);
    $reject(static fn() => medical_doctor_image_store($smallBytes, $invalidFolder), 'Images below 200 pixels rejected');
    $gif = $make(300, 300); ob_start(); imagegif($gif); $gifBytes = (string) ob_get_clean(); imagedestroy($gif);
    $reject(static fn() => medical_doctor_image_store($gifBytes, $invalidFolder), 'Unsupported GIF rejected even though it is a raster');
    $reject(static fn() => medical_doctor_image_store(str_repeat('x', 12 * 1024 * 1024 + 1), $invalidFolder), 'Encoded-byte budget enforced');
    $oversized = substr_replace(substr_replace($smallBytes, pack('N', 4000), 16, 4), pack('N', 4000), 20, 4);
    $reject(static fn() => medical_doctor_image_store($oversized, $invalidFolder), 'Pixel budget enforced before decode');
    $truncated = substr($png, 0, 40);
    $assert(is_array(getimagesizefromstring($truncated)), 'Truncated raster fixture has a valid header but incomplete pixels');
    $reject(static fn() => medical_doctor_image_store($truncated, $invalidFolder), 'Valid header cannot bypass full raster decoding');
    $reject(static fn() => medical_doctor_image_store($bytes, '../outside-doctor-test'), 'Unsafe storage folder rejected before publication');
    $assert(!is_dir($root . '/invalid'), 'Invalid input never creates a public destination folder');
    $assert(hash_file('sha256', $noisePath) === $before, 'Rejected inputs never alter an existing valid doctor image');
    $assert(array_diff(glob(sys_get_temp_dir() . '/medreview-doctor-source-*') ?: [], $stagesBefore) === [], 'Success and failure both clean original and branded private staging files');

    $api = (string) file_get_contents(dirname(__DIR__) . '/api/medical/doctor-image-replace.php');
    $storeAt = strpos($api, 'medical_doctor_image_store(');
    $updateAt = strpos($api, 'UPDATE medical_doctors');
    $assert($storeAt !== false && $updateAt !== false && $storeAt < $updateAt, 'API only updates doctor after branded compressed storage succeeds');
    $assert(str_contains($api, "['size_bytes']") && str_contains($api, "['original_size_bytes']"), 'API reports final and original byte sizes');
    foreach ($directories as $directory) $assert(glob($directory . '/*.tmp-*') === [], 'No partially written compressed image is left behind');
    echo "Doctor image compression: {$checks} checks passed; synthetic fixtures only, no DB/network.\n";
} finally {
    foreach (array_unique($files) as $path) {
        if (str_starts_with($path, $root . '/') && is_file($path)) unlink($path);
    }
    foreach (array_reverse(array_unique($directories)) as $directory) {
        if (str_starts_with($directory, $root . '/') && is_dir($directory) && count(scandir($directory)) === 2) rmdir($directory);
    }
    if (is_dir($root) && count(scandir($root)) === 2) rmdir($root);
}
