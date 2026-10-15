<?php
declare(strict_types=1);

/**
 * Read-only lookup of explicitly prebuilt public homepage images.
 * No remote fetching, image decoding, database access or filesystem writes on page requests.
 */
if (!function_exists('medical_media_remote_variant_sources')) {
    /** @return list<string> */
    function medical_media_remote_variant_sources(): array
    {
        return [
            'https://gonight.vn/wp-content/uploads/2026/04/dahan-spa-gmap-69f2319b698f8-1.webp',
        ];
    }

    /** @return array<string,mixed> */
    function medical_media_remote_variant_manifest(): array
    {
        static $manifest = null;
        if (is_array($manifest)) return $manifest;
        $manifest = [];
        $file = __DIR__ . '/assets/media/home-image-variants.json';
        if (!is_file($file) || is_link($file) || filesize($file) > 65536) return $manifest;
        $bytes = @file_get_contents($file);
        if (!is_string($bytes)) return $manifest;
        $decoded = json_decode($bytes, true, 16);
        if (is_array($decoded)) $manifest = $decoded;
        return $manifest;
    }

    /**
     * Validation is also exposed for maintenance tests; it only reads published local files.
     * A missing or invalid variant disables the entry so callers retain its original image.
     *
     * @return array{src:string,srcset:string}|null
     */
    function medical_media_remote_image_from_manifest(string $url, array $manifest, array $widths = [320, 640, 960], int $defaultWidth = 640): ?array
    {
        if (!in_array($url, medical_media_remote_variant_sources(), true)
            || ($manifest['version'] ?? null) !== 1
            || ($manifest['encoder'] ?? null) !== 'webp-q78-v1'
            || !is_array($manifest['images'] ?? null)) return null;
        $entry = $manifest['images'][$url] ?? null;
        if (!is_array($entry)
            || !is_int($entry['source_bytes'] ?? null) || $entry['source_bytes'] < 1 || $entry['source_bytes'] > 12 * 1024 * 1024
            || !is_string($entry['source_sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $entry['source_sha256']) !== 1
            || !is_int($entry['width'] ?? null) || $entry['width'] < 1 || $entry['width'] > 12000
            || !is_int($entry['height'] ?? null) || $entry['height'] < 1 || $entry['height'] > 12000
            || $entry['width'] * $entry['height'] > 12000000
            || !is_array($entry['variants'] ?? null) || count($entry['variants']) > 3) return null;

        $root = realpath(__DIR__);
        $directory = realpath(__DIR__ . '/assets/media/variants');
        if (!is_string($root) || !is_string($directory) || $directory !== $root . '/assets/media/variants') return null;
        $available = [];
        foreach ($entry['variants'] as $variant) {
            if (!is_array($variant)
                || !is_int($variant['width'] ?? null) || !in_array($variant['width'], [320, 640, 960], true)
                || $variant['width'] > $entry['width'] || isset($available[$variant['width']])
                || !is_int($variant['height'] ?? null) || $variant['height'] !== max(1, (int) round($entry['height'] * $variant['width'] / $entry['width']))
                || !is_int($variant['bytes'] ?? null) || $variant['bytes'] < 1 || $variant['bytes'] > 12 * 1024 * 1024
                || !is_string($variant['url'] ?? null)
                || preg_match('~^/assets/media/variants/remote-[a-f0-9]{24}-' . $variant['width'] . '\.webp$~D', $variant['url']) !== 1) return null;
            $path = $root . $variant['url'];
            $resolved = realpath($path);
            if (!is_string($resolved) || dirname($resolved) !== $directory
                || !is_file($path) || is_link($path) || filesize($path) !== $variant['bytes']) return null;
            $available[$variant['width']] = $variant['url'];
        }
        ksort($available, SORT_NUMERIC);
        $selected = [];
        foreach ($available as $width => $variantUrl) {
            if (in_array($width, $widths, true)) $selected[$width] = $variantUrl;
        }
        if ($selected === []) return null;
        $src = end($selected);
        foreach ($selected as $width => $variantUrl) {
            if ($width >= $defaultWidth) { $src = $variantUrl; break; }
        }
        $srcset = [];
        foreach ($selected as $width => $variantUrl) $srcset[] = $variantUrl . ' ' . $width . 'w';
        return ['src' => (string) $src, 'srcset' => implode(', ', $srcset)];
    }

    /** @return array{src:string,srcset:string}|null */
    function medical_media_remote_responsive_image(string $url, array $widths = [320, 640, 960], int $defaultWidth = 640): ?array
    {
        // Exact URL matching prevents unrelated or customized gallery URLs being replaced.
        if (!in_array($url, medical_media_remote_variant_sources(), true)) return null;
        return medical_media_remote_image_from_manifest($url, medical_media_remote_variant_manifest(), $widths, $defaultWidth);
    }
}
