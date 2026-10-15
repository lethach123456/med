<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_media_variants.php';

// Explicit local-file maintenance only. No DB, URL fetching, request-time generation or proxy.
$arguments = array_slice($argv, 1);
if (in_array('--help', $arguments, true)) {
    echo "Usage: php scripts/build_media_variants.php [--widths=64,128,...] /uploads/library/image.jpg [...]\n";
    echo "Without paths, builds the existing MedReview brand (64/128) and homepage hero (640/960/1280/1800).\n";
    echo "Allowed widths: 64,128,256,320,480,640,960,1280,1800. Only local /uploads/library and /assets images.\n";
    exit(0);
}

try {
    if (!extension_loaded('gd') || !function_exists('imagewebp')) throw new RuntimeException('GD with WebP encoding is required.');
    $selectedWidths = null;
    $jobs = [];
    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--widths=')) {
            $value = substr($argument, strlen('--widths='));
            if (!preg_match('/^\d+(?:,\d+)*$/', $value)) throw new RuntimeException('Widths must be comma-separated allowed integers.');
            $requested = array_values(array_unique(array_map('intval', explode(',', $value))));
            $selectedWidths = medical_media_variant_widths($requested);
            sort($requested, SORT_NUMERIC);
            if ($selectedWidths !== $requested) throw new RuntimeException('Unsupported image width; see --help.');
        } elseif (str_starts_with($argument, '--')) {
            throw new RuntimeException('Unknown option: ' . $argument);
        } else {
            $jobs[] = ['url' => $argument, 'widths' => []];
        }
    }
    if ($jobs === []) {
        $jobs = [
            ['url' => '/uploads/library/2026/07/fbef23f192e21b0669c670803723ec66.jpg', 'widths' => [64, 128]],
            ['url' => '/uploads/library/2026/07/38252346e52a7957cc10da6fe61849dc.jpg', 'widths' => [640, 960, 1280, 1800]],
        ];
    }
    if (count($jobs) > 50) throw new RuntimeException('At most 50 explicitly selected local images per run.');
    foreach ($jobs as &$job) {
        $job['source'] = medical_media_variant_source($job['url']);
        if ($job['source'] === null || preg_match('~^https?://~i', $job['url'])) {
            throw new RuntimeException('Use an existing site-relative local image path: ' . $job['url']);
        }
        $job['widths'] = $selectedWidths ?? ($job['widths'] !== [] ? $job['widths'] : [320, 640, 960]);
        $info = @getimagesize($job['source']['path']);
        if (!is_array($info) || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 12000000) {
            throw new RuntimeException('Unsupported or oversized image (max 12 megapixels): ' . $job['url']);
        }
        $job['info'] = $info;
    }
    unset($job);

    $directory = dirname(__DIR__) . '/assets/media/variants';
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('Cannot create variant directory.');
    $realDirectory = realpath($directory);
    $root = realpath(dirname(__DIR__));
    if (!is_string($root) || !is_string($realDirectory) || $realDirectory !== $root . '/assets/media/variants') {
        throw new RuntimeException('Variant output must be the real, non-symlinked local directory.');
    }

    foreach ($jobs as $job) {
        $source = $job['source'];
        $original = match ($job['info'][2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source['path']),
            IMAGETYPE_PNG => @imagecreatefrompng($source['path']),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source['path']),
        };
        if (!$original instanceof GdImage) throw new RuntimeException('Cannot decode image: ' . $job['url']);
        try {
            foreach ($job['widths'] as $width) {
                if ($width > $job['info'][0]) continue; // Never upscale a small upload.
                $height = max(1, (int) round($job['info'][1] * $width / $job['info'][0]));
                $url = medical_media_variant_url($source, $width);
                $destination = dirname(__DIR__) . $url;
                if (is_link($destination)) throw new RuntimeException('Refusing a symlinked output file.');
                if (is_file($destination)) {
                    echo $url . ' ' . $width . 'x' . $height . ' ' . filesize($destination) . " B (already built)\n";
                    continue;
                }
                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
                imagecopyresampled($canvas, $original, 0, 0, 0, 0, $width, $height, $job['info'][0], $job['info'][1]);
                $temporary = tempnam($directory, '.building-');
                if (!is_string($temporary)) throw new RuntimeException('Cannot create temporary output.');
                try {
                    if (!imagewebp($canvas, $temporary, 78)) throw new RuntimeException('WebP encoding failed.');
                    $encoded = @getimagesize($temporary);
                    if (!is_array($encoded) || $encoded[0] !== $width || $encoded[1] !== $height || $encoded[2] !== IMAGETYPE_WEBP) {
                        throw new RuntimeException('Generated WebP failed validation.');
                    }
                    if (!chmod($temporary, 0644) || !rename($temporary, $destination)) throw new RuntimeException('Cannot publish WebP.');
                } finally {
                    imagedestroy($canvas);
                    if (is_file($temporary)) unlink($temporary);
                }
                echo $url . ' ' . $width . 'x' . $height . ' ' . filesize($destination) . " B\n";
            }
        } finally {
            imagedestroy($original);
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
