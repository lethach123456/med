<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Explicit build-only artwork, using the existing outlined brand raster.
// No runtime transforms, DB, fonts or network are needed on hosting.
if (!extension_loaded('gd')) throw new RuntimeException('GD is required to build the social image.');
$root = dirname(__DIR__);
$directory = $root . '/assets/social';
if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('Cannot create social asset directory.');
if (realpath($directory) !== realpath($root) . '/assets/social') throw new RuntimeException('Social output cannot be symlinked.');
$target = $directory . '/medreview-share.jpg';
if (is_link($target)) throw new RuntimeException('Social output cannot be symlinked.');
$logo = imagecreatefrompng($root . '/assets/brand/medreview-wordmark.png');
$canvas = imagecreatetruecolor(1200, 630);
$temporary = null;
try {
    imagefill($canvas, 0, 0, 0xf5f8ff);
    imagefilledellipse($canvas, 1180, 0, 620, 620, 0xe5eeff);
    imagefilledellipse($canvas, 0, 630, 440, 440, 0xe4f3f2);
    imagefilledrectangle($canvas, 78, 100, 1122, 530, 0xffffff);
    imagefilledrectangle($canvas, 78, 100, 86, 530, 0x2d63ff);
    $width = 790;
    $height = (int) round($width * imagesy($logo) / imagesx($logo));
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $logo, 205, (int) ((630 - $height) / 2), 0, 0, $width, $height, imagesx($logo), imagesy($logo));
    $temporary = tempnam($directory, '.social-building-');
    if (!is_string($temporary) || !imagejpeg($canvas, $temporary, 88)) throw new RuntimeException('Cannot encode social JPEG.');
    $info = getimagesize($temporary);
    if ($info[0] !== 1200 || $info[1] !== 630 || filesize($temporary) >= 150000) throw new RuntimeException('Invalid social JPEG output.');
    if (!chmod($temporary, 0644) || !rename($temporary, $target)) throw new RuntimeException('Cannot publish social JPEG.');
    echo '/assets/social/medreview-share.jpg: 1200x630, ' . filesize($target) . " bytes\n";
} finally {
    if (is_string($temporary) && is_file($temporary)) unlink($temporary);
    imagedestroy($logo);
    imagedestroy($canvas);
}
