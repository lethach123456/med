<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Build browser assets from the existing brand artwork; no DB or network.
$root = dirname(__DIR__);
$source = $root . '/uploads/library/2026/07/fbef23f192e21b0669c670803723ec66.jpg';
if (!extension_loaded('gd')) throw new RuntimeException('GD is required to build favicon assets.');
$image = imagecreatefromjpeg($source);
if (!$image) throw new RuntimeException('Cannot read existing brand artwork.');
$directory = $root . '/assets/icons';
if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('Cannot create icon directory.');
foreach ([32 => 'favicon-32.png', 180 => 'apple-touch-icon.png'] as $size => $name) {
    $canvas = imagecreatetruecolor($size, $size);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));
    if (!imagepng($canvas, $directory . '/' . $name, 9)) throw new RuntimeException('Cannot write ' . $name);
    imagedestroy($canvas);
}
$png = file_get_contents($directory . '/favicon-32.png');
if ($png === false) throw new RuntimeException('Cannot read generated PNG.');
// ICO supports embedded PNG; publish the conventional root path as well.
$ico = pack('vvv', 0, 1, 1) . pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22) . $png;
if (file_put_contents($root . '/favicon.ico', $ico) === false) throw new RuntimeException('Cannot write favicon.ico.');
imagedestroy($image);
echo "Built favicon.ico, favicon-32.png and apple-touch-icon.png from existing brand artwork.\n";
