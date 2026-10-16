<?php
declare(strict_types=1);

/**
 * Explicit, local-file branding only. This file does not use the database, fetch
 * URLs or run at page-render time. A caller owns authorization and job selection.
 */
if (!function_exists('medical_image_brand_render')) {
    /**
     * Write a new, branded image without modifying or replacing an existing file.
     *
     * Defaults: original image format/dimensions, a 22%-wide wordmark, 3% margins.
     * Portraits and shallow panoramas retain their aspect ratio; the wordmark is
     * reduced if needed to fit within both axes. JPEG EXIF orientation is applied
     * before placement. PNG/WebP alpha is retained (JPEG conversion uses white).
     *
     * Options: format (jpeg|png|webp), width_ratio (0.05..0.5), margin_ratio
     * (0..0.2), quality (1..100; JPEG/WebP), max_pixels (1..12,000,000),
     * max_bytes (1..12,582,912). Limits may be lowered, never raised above caps.
     * Target must have a matching image extension and an existing local parent.
     * Publication is atomic and no-clobber, including when concurrent jobs race.
     *
     * @return array{mime:string,format:string,width:int,height:int,bytes:int,hash:string,source_hash:string,version:string,logo:array{x:int,y:int,width:int,height:int,hash:string}}
     * @throws InvalidArgumentException|RuntimeException
     */
    function medical_image_brand_render(string $sourcePath, string $targetPath, array $options = []): array
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Image branding requires the GD extension.');
        }
        $knownOptions = ['format', 'width_ratio', 'margin_ratio', 'quality', 'max_pixels', 'max_bytes'];
        if (array_diff(array_keys($options), $knownOptions) !== []) {
            throw new InvalidArgumentException('Unknown image branding option.');
        }
        $maxPixels = medical_image_brand_integer_option($options, 'max_pixels', 12000000, 1, 12000000);
        $maxBytes = medical_image_brand_integer_option($options, 'max_bytes', 12582912, 1, 12582912);
        $quality = medical_image_brand_integer_option($options, 'quality', 90, 1, 100);
        $widthRatio = medical_image_brand_ratio_option($options, 'width_ratio', 0.22, 0.05, 0.5);
        $marginRatio = medical_image_brand_ratio_option($options, 'margin_ratio', 0.03, 0.0, 0.2);

        medical_image_brand_assert_local_path($sourcePath);
        medical_image_brand_assert_local_path($targetPath);
        $source = realpath($sourcePath);
        if (!is_string($source) || !is_file($source) || !is_readable($source)) {
            throw new InvalidArgumentException('Branding source must be a readable local image.');
        }
        $sourceBytes = @filesize($source);
        if (!is_int($sourceBytes) || $sourceBytes < 1 || $sourceBytes > $maxBytes) {
            throw new InvalidArgumentException('Branding source exceeds the encoded-byte limit.');
        }
        $info = @getimagesize($source);
        $formats = [IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (!is_array($info) || !isset($formats[$info[2]]) || $info[0] < 1 || $info[1] < 1
            || $info[0] > 20000 || $info[1] > 20000 || $info[0] * $info[1] > $maxPixels) {
            throw new InvalidArgumentException('Branding source must be JPEG, PNG or WebP within the pixel limit.');
        }
        $format = $options['format'] ?? $formats[$info[2]];
        if (!is_string($format) || !in_array($format, ['jpeg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException('Unsupported branding output format.');
        }
        $decoder = 'imagecreatefrom' . $formats[$info[2]];
        $encoder = 'image' . $format;
        if (!function_exists($decoder) || !function_exists($encoder) || !function_exists('imagecreatefrompng')) {
            throw new RuntimeException('GD does not support the requested branding image format.');
        }

        $directory = realpath(dirname($targetPath));
        $filename = basename($targetPath);
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $extensions = ['jpeg' => ['jpg', 'jpeg'], 'png' => ['png'], 'webp' => ['webp']];
        if (!is_string($directory) || !is_dir($directory) || !is_writable($directory)
            || $filename === '' || $filename === '.' || $filename === '..'
            || !in_array($extension, $extensions[$format], true)) {
            throw new InvalidArgumentException('Branding target needs a writable local parent and a matching image extension.');
        }
        $target = $directory . DIRECTORY_SEPARATOR . $filename;
        if ($target === $source || file_exists($target) || is_link($target)) {
            throw new InvalidArgumentException('Branding target must be a new file; existing images are never overwritten.');
        }

        // Production uses this committed raster; neither FreeType nor installed
        // fonts nor SVG rendering are runtime dependencies.
        $logoPath = __DIR__ . '/assets/brand/medreview-wordmark.png';
        $logoInfo = @getimagesize($logoPath);
        $logoBytes = @filesize($logoPath);
        if (!is_array($logoInfo) || $logoInfo[2] !== IMAGETYPE_PNG || $logoInfo[0] < 1 || $logoInfo[1] < 1
            || $logoInfo[0] * $logoInfo[1] > 2000000 || !is_int($logoBytes) || $logoBytes > 1048576) {
            throw new RuntimeException('The bundled MedReview wordmark is missing or invalid.');
        }
        $orientation = 1;
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($source, 'IFD0', true, false);
            $value = is_array($exif) ? ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1) : 1;
            if (is_int($value) && $value >= 1 && $value <= 8) $orientation = $value;
        }
        $width = $orientation >= 5 ? (int) $info[1] : (int) $info[0];
        $height = $orientation >= 5 ? (int) $info[0] : (int) $info[1];
        $marginX = min(max(0, (int) round($width * $marginRatio)), max(0, (int) floor(($width - 1) / 2)));
        $marginY = min(max(0, (int) round($height * $marginRatio)), max(0, (int) floor(($height - 1) / 2)));
        $logoWidth = max(1, min((int) round($width * $widthRatio), $width - 2 * $marginX));
        $logoHeight = max(1, (int) round($logoWidth * $logoInfo[1] / $logoInfo[0]));
        if ($logoHeight > $height - 2 * $marginY) {
            $logoHeight = $height - 2 * $marginY;
            $logoWidth = max(1, min($logoWidth, (int) floor($logoHeight * $logoInfo[0] / $logoInfo[1])));
        }
        $x = $width - $marginX - $logoWidth;
        $y = $marginY;

        // GD allocations are not consistently reflected in memory_get_usage().
        // Budget conservatively before decoding, including orientation/flatten
        // copies, the bundled raster, decoder buffers and encoder overhead.
        $copies = ($orientation !== 1 || ($format === 'jpeg' && $info[2] !== IMAGETYPE_JPEG)) ? 12 : 8;
        $estimate = $width * $height * $copies + $logoInfo[0] * $logoInfo[1] * 8
            + $logoWidth * $logoHeight * 8 + $sourceBytes * 2 + 16 * 1024 * 1024;
        $memoryLimit = medical_image_brand_memory_limit();
        if ($estimate + memory_get_usage(true) > $memoryLimit) {
            throw new RuntimeException('Insufficient memory budget to safely decode this branding source.');
        }

        $original = null;
        $logo = null;
        $overlay = null;
        $temporary = null;
        try {
            $original = @$decoder($source);
            $logo = @imagecreatefrompng($logoPath);
            if (!$original instanceof GdImage || !$logo instanceof GdImage) {
                throw new RuntimeException('Cannot decode the source image or bundled wordmark.');
            }
            if (imagesx($original) !== (int) $info[0] || imagesy($original) !== (int) $info[1]) {
                throw new RuntimeException('The branding source changed during decoding.');
            }
            if (!imageistruecolor($original) && !imagepalettetotruecolor($original)) {
                throw new RuntimeException('Cannot convert the source image to true color.');
            }
            $original = medical_image_brand_orient($original, $orientation);
            if ($format === 'jpeg' && $info[2] !== IMAGETYPE_JPEG) {
                $flattened = imagecreatetruecolor($width, $height);
                if (!$flattened instanceof GdImage) throw new RuntimeException('Cannot allocate JPEG canvas.');
                imagefill($flattened, 0, 0, imagecolorallocate($flattened, 255, 255, 255));
                if (!imagecopy($flattened, $original, 0, 0, 0, 0, $width, $height)) {
                    imagedestroy($flattened);
                    throw new RuntimeException('Cannot flatten image transparency for JPEG.');
                }
                imagedestroy($original);
                $original = $flattened;
            }
            imagesavealpha($original, true);
            imagealphablending($original, true);
            $overlay = imagecreatetruecolor($logoWidth, $logoHeight);
            if (!$overlay instanceof GdImage) throw new RuntimeException('Cannot allocate wordmark overlay.');
            imagealphablending($overlay, false);
            imagesavealpha($overlay, true);
            imagefill($overlay, 0, 0, imagecolorallocatealpha($overlay, 0, 0, 0, 127));
            if (!imagecopyresampled($overlay, $logo, 0, 0, 0, 0, $logoWidth, $logoHeight, $logoInfo[0], $logoInfo[1])
                || !imagecopy($original, $overlay, $x, $y, 0, 0, $logoWidth, $logoHeight)) {
                throw new RuntimeException('Cannot composite the MedReview wordmark.');
            }
            $temporary = @tempnam($directory, '.medreview-brand-');
            if (!is_string($temporary) || realpath(dirname($temporary)) !== $directory) {
                throw new RuntimeException('Cannot create an atomic branding output in the target directory.');
            }
            $written = $format === 'png'
                ? @imagepng($original, $temporary, 6)
                : @$encoder($original, $temporary, $quality);
            clearstatcache(true, $temporary);
            $outputInfo = @getimagesize($temporary);
            $bytes = @filesize($temporary);
            $outputType = array_search($format, $formats, true);
            if (!$written || !is_array($outputInfo) || $outputInfo[0] !== $width || $outputInfo[1] !== $height
                || $outputInfo[2] !== $outputType || !is_int($bytes) || $bytes < 1) {
                throw new RuntimeException('The encoded branded image failed validation.');
            }
            $hash = hash_file('sha256', $temporary);
            $sourceHash = hash_file('sha256', $source);
            $logoHash = hash_file('sha256', $logoPath);
            if (!is_string($hash) || !is_string($sourceHash) || !is_string($logoHash)) {
                throw new RuntimeException('Cannot fingerprint the branded image.');
            }
            // A same-directory hard link is an atomic, no-clobber publish: unlike
            // rename(), it cannot replace an existing source/target during a race.
            if (!@chmod($temporary, 0644) || !@link($temporary, $target)) {
                throw new RuntimeException('Cannot publish branded image; target may already exist.');
            }
            return [
                'mime' => $format === 'jpeg' ? 'image/jpeg' : 'image/' . $format,
                'format' => $format,
                'width' => $width,
                'height' => $height,
                'bytes' => $bytes,
                'hash' => $hash,
                'source_hash' => $sourceHash,
                'version' => 'medreview-wordmark-v1',
                'logo' => ['x' => $x, 'y' => $y, 'width' => $logoWidth, 'height' => $logoHeight, 'hash' => $logoHash],
            ];
        } finally {
            foreach ([$overlay, $logo, $original] as $image) {
                if ($image instanceof GdImage) imagedestroy($image);
            }
            if (is_string($temporary) && is_file($temporary)) @unlink($temporary);
        }
    }

    /** Never allow stream wrappers or control-character filenames. */
    function medical_image_brand_assert_local_path(string $path): void
    {
        if ($path === '' || strlen($path) > 4096 || preg_match('/[\x00-\x1F\x7F]/', $path)
            || str_contains($path, '://') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            throw new InvalidArgumentException('Branding paths must be plain local filesystem paths.');
        }
    }

    function medical_image_brand_integer_option(array $options, string $key, int $default, int $minimum, int $maximum): int
    {
        $value = $options[$key] ?? $default;
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException('Invalid branding option: ' . $key . '.');
        }
        return $value;
    }

    function medical_image_brand_ratio_option(array $options, string $key, float $default, float $minimum, float $maximum): float
    {
        $value = $options[$key] ?? $default;
        if ((!is_float($value) && !is_int($value)) || !is_finite((float) $value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException('Invalid branding option: ' . $key . '.');
        }
        return (float) $value;
    }

    /** An unlimited PHP setting still gets a finite per-operation ceiling. */
    function medical_image_brand_memory_limit(): int
    {
        $limit = trim((string) ini_get('memory_limit'));
        $maximum = 256 * 1024 * 1024;
        if ($limit === '' || $limit === '-1') return $maximum;
        if (!preg_match('/^(\d+)\s*([KMG]?)B?$/i', $limit, $match)) return min($maximum, 128 * 1024 * 1024);
        $multiplier = match (strtoupper($match[2])) { 'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1 };
        return (int) min($maximum, (float) $match[1] * $multiplier);
    }

    function medical_image_brand_orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            $flip = in_array($orientation, [2, 5, 7], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL;
            if (!imageflip($image, $flip)) throw new RuntimeException('Cannot apply image orientation.');
        }
        $angle = match ($orientation) { 3 => 180, 5, 8 => 90, 6, 7 => -90, default => 0 };
        if ($angle === 0) return $image;
        $rotated = imagerotate($image, $angle, 0);
        if (!$rotated instanceof GdImage) throw new RuntimeException('Cannot rotate image orientation.');
        imagedestroy($image);
        return $rotated;
    }
}
