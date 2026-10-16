<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_image_brand_renderer.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    $checks++;
};
$reject = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
    } catch (InvalidArgumentException|RuntimeException $error) {
        $assert(true, $message);
        return;
    }
    $assert(false, $message);
};
$directory = sys_get_temp_dir() . '/medreview-brand-test-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Cannot make isolated fixture directory.');
$make = static function (string $path, int $width, int $height, string $format, bool $alpha = false): void {
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 227, 236, 243, $alpha ? 64 : 0));
    for ($row = (int) round($height * 0.4); $row < $height; $row++) {
        $ratio = $row / max(1, $height - 1);
        $color = imagecolorallocatealpha($image, (int) (180 - 40 * $ratio), (int) (210 - 20 * $ratio), (int) (223 + 20 * $ratio), 0);
        imageline($image, 0, $row, $width - 1, $row, $color);
    }
    $encoder = 'image' . $format;
    if (!$encoder($image, $path)) throw new RuntimeException('Cannot create local fixture.');
    imagedestroy($image);
};

try {
    foreach ([['landscape', 1200, 800], ['portrait', 600, 1000], ['panorama', 1200, 24]] as [$name, $width, $height]) {
        $source = $directory . '/' . $name . '.png';
        $target = $directory . '/' . $name . '-branded.png';
        $make($source, $width, $height, 'png', true);
        $before = hash_file('sha256', $source);
        $result = medical_image_brand_render($source, $target);
        $assert($result['mime'] === 'image/png' && $result['format'] === 'png', $name . ': original format retained');
        $assert($result['width'] === $width && $result['height'] === $height, $name . ': aspect and dimensions retained');
        $assert(hash_file('sha256', $source) === $before && $result['source_hash'] === $before, $name . ': source never modified');
        $assert($result['hash'] === hash_file('sha256', $target) && $result['bytes'] === filesize($target), $name . ': verified result metadata');
        $logo = $result['logo'];
        $assert($logo['x'] >= 0 && $logo['y'] >= 0 && $logo['x'] + $logo['width'] <= $width && $logo['y'] + $logo['height'] <= $height, $name . ': wordmark fits');
        $assert($logo['x'] > $width / 2 && $logo['y'] < $height / 2, $name . ': wordmark in upper right');
        if ($name !== 'panorama') {
            $assert(abs($logo['width'] - $width * 0.22) <= 1, $name . ': 22 percent logo width');
            $assert(abs($logo['y'] - $height * 0.03) <= 1 && abs(($width - $logo['x'] - $logo['width']) - $width * 0.03) <= 1, $name . ': 3 percent margins');
        }
        $original = imagecreatefrompng($source);
        $branded = imagecreatefrompng($target);
        $assert(imagecolorat($original, 0, 0) === imagecolorat($branded, 0, 0), $name . ': untouched alpha retained');
        $changed = 0;
        for ($y = $logo['y']; $y < $logo['y'] + $logo['height']; $y++) {
            for ($x = $logo['x']; $x < $logo['x'] + $logo['width']; $x++) {
                if (imagecolorat($original, $x, $y) !== imagecolorat($branded, $x, $y)) $changed++;
            }
        }
        $assert($changed > 0, $name . ': text was visibly composited');
        imagedestroy($original);
        imagedestroy($branded);
        $reject(static fn() => medical_image_brand_render($source, $source), $name . ': source cannot be target');
        $reject(static fn() => medical_image_brand_render($source, $target), $name . ': existing output cannot be overwritten');
        $assert($result['hash'] === hash_file('sha256', $target), $name . ': existing output unchanged after rejection');
    }

    $source = $directory . '/landscape.png';
    foreach (['jpeg', 'webp'] as $format) {
        if (!function_exists('image' . $format)) continue;
        $extension = $format === 'jpeg' ? 'jpg' : $format;
        $target = $directory . '/converted.' . $extension;
        $result = medical_image_brand_render($source, $target, ['format' => $format]);
        $info = getimagesize($target);
        $assert($result['mime'] === 'image/' . $format && $info['mime'] === $result['mime'], $format . ': MIME and encoding match');
        $assert($info[0] === 1200 && $info[1] === 800, $format . ': conversion keeps dimensions');
        $decoder = 'imagecreatefrom' . $format;
        $image = $decoder($target);
        $alpha = (imagecolorat($image, 0, 0) >> 24) & 0x7f;
        $assert($alpha === ($format === 'jpeg' ? 0 : 64), $format . ': sensible alpha handling');
        imagedestroy($image);
        $copy = $directory . '/rerendered.' . $extension;
        $sameFormat = medical_image_brand_render($target, $copy);
        $assert($sameFormat['format'] === $format, $format . ': decoder and default encoder supported');
    }

    // Inject a tiny EXIF orientation tag into a generated local JPEG, not a photo.
    $jpeg = $directory . '/oriented-source.jpg';
    $make($jpeg, 120, 80, 'jpeg');
    $originalBytes = (string) file_get_contents($jpeg);
    foreach ([2, 3, 4, 5, 6, 7, 8] as $orientation) {
        $exif = "Exif\0\0II" . pack('vV', 42, 8) . pack('v', 1) . pack('vvVv', 0x0112, 3, 1, $orientation) . "\0\0" . pack('V', 0);
        file_put_contents($jpeg, substr($originalBytes, 0, 2) . "\xff\xe1" . pack('n', strlen($exif) + 2) . $exif . substr($originalBytes, 2));
        $result = medical_image_brand_render($jpeg, $directory . '/orientation-' . $orientation . '.jpg');
        $assert($result['width'] === ($orientation >= 5 ? 80 : 120) && $result['height'] === ($orientation >= 5 ? 120 : 80), 'EXIF orientation ' . $orientation . ': correct display dimensions');
    }

    $reject(static fn() => medical_image_brand_render($source, $directory . '/too-many-pixels.png', ['max_pixels' => 100]), 'Pixel guard rejects before decode');
    $reject(static fn() => medical_image_brand_render($source, $directory . '/too-many-bytes.png', ['max_bytes' => 1]), 'Encoded-byte guard rejects');
    $reject(static fn() => medical_image_brand_render($source, $directory . '/invalid-option.png', ['width_ratio' => NAN]), 'Nonfinite ratio rejected');
    $reject(static fn() => medical_image_brand_render($source, $directory . '/invalid-option.png', ['quality' => 101]), 'Invalid encoder quality rejected');
    $reject(static fn() => medical_image_brand_render($source, $directory . '/invalid-option.png', ['max_pixels' => 12000001]), 'Pixel cap cannot be raised');
    $reject(static fn() => medical_image_brand_render($source, $directory . '/invalid-option.png', ['unexpected' => true]), 'Unknown option rejected');
    $reject(static fn() => medical_image_brand_render('https://example.org/image.png', $directory . '/remote.png'), 'Remote sources rejected');
    $reject(static fn() => medical_image_brand_render('file://' . $source, $directory . '/wrapper.png'), 'Stream wrappers rejected');
    $reject(static fn() => medical_image_brand_render($source, $directory . '/invalid.php'), 'Executable target extension rejected');
    $reject(static fn() => medical_image_brand_render($source, $directory . '/mismatched.jpg'), 'Mismatched extension rejected');
    $reject(static fn() => medical_image_brand_render($source, $directory . '/missing/new.png'), 'Missing target parent rejected');
    file_put_contents($directory . '/not-an-image.png', 'not an image');
    $reject(static fn() => medical_image_brand_render($directory . '/not-an-image.png', $directory . '/invalid-source.png'), 'Non-image source rejected');

    if (link($source, $directory . '/source-hardlink.png')) {
        $reject(static fn() => medical_image_brand_render($source, $directory . '/source-hardlink.png'), 'Hard-linked source target rejected');
    }
    if (symlink($source, $directory . '/source-symlink.png')) {
        $reject(static fn() => medical_image_brand_render($source, $directory . '/source-symlink.png'), 'Symlinked source target rejected');
    }
    if (symlink($directory . '/missing-source.png', $directory . '/dangling-symlink.png')) {
        $reject(static fn() => medical_image_brand_render($source, $directory . '/dangling-symlink.png'), 'Dangling target symlink rejected');
    }
    $assert(glob($directory . '/.medreview-brand-*') === [], 'All temporary encoder files cleaned up');
    $asset = imagecreatefrompng(dirname(__DIR__) . '/assets/brand/medreview-wordmark.png');
    $assert(((imagecolorat($asset, 0, 0) >> 24) & 0x7f) === 127, 'Bundled wordmark background is transparent');
    imagedestroy($asset);
    echo "Image brand renderer: {$checks} checks passed; synthetic fixtures only, no DB/network.\n";
    if (getenv('MEDREVIEW_KEEP_BRAND_FIXTURES') === '1') echo 'Preview fixtures: ' . $directory . "\n";
} finally {
    if (getenv('MEDREVIEW_KEEP_BRAND_FIXTURES') !== '1') {
        foreach (scandir($directory) ?: [] as $filename) {
            if ($filename !== '.' && $filename !== '..') unlink($directory . '/' . $filename);
        }
        rmdir($directory);
    }
}
