<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_media_remote_variants.php';

/**
 * Read-only, local image variants for public templates.
 *
 * A page request only selects files already built by scripts/build_media_variants.php.
 * It never downloads an image, writes a cache, decodes a large original or changes DB data.
 */
if (!function_exists('medical_media_variant_source')) {
    /** @return array{url:string,path:string,bytes:int,mtime:int,key:string}|null */
    function medical_media_variant_source(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\\\\]/', $url)) return null;
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) return null;

        if (isset($parts['host']) || isset($parts['scheme'])) {
            // Only the canonical site's own uploads can map to this filesystem.
            // In particular, a foreign URL with an identical path is not local.
            $origin = function_exists('site_canonical_origin')
                ? site_canonical_origin()
                : (string) (getenv('SITE_CANONICAL_ORIGIN') ?: 'https://medreview.vn');
            $allowed = parse_url($origin);
            if (!is_array($allowed)
                || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                || strtolower((string) ($parts['host'] ?? '')) !== strtolower((string) ($allowed['host'] ?? ''))
                || (int) ($parts['port'] ?? (strtolower((string) $parts['scheme']) === 'https' ? 443 : 80))
                    !== (int) ($allowed['port'] ?? (strtolower((string) ($allowed['scheme'] ?? 'https')) === 'https' ? 443 : 80))) {
                return null;
            }
        } elseif (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        if (!preg_match('~^/(?:uploads/library|assets)/[a-zA-Z0-9_./-]+\.(?:jpe?g|png|webp)$~i', $path)
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)
            || str_starts_with($path, '/assets/media/variants/')) return null;

        $root = realpath(__DIR__);
        if (!is_string($root)) return null;
        $candidate = realpath($root . $path);
        if (!is_string($candidate) || !is_file($candidate)) return null;
        $insideAllowedRoot = false;
        foreach (['/uploads/library', '/assets'] as $directory) {
            $allowedRoot = realpath($root . $directory);
            if (is_string($allowedRoot) && str_starts_with($candidate, $allowedRoot . DIRECTORY_SEPARATOR)) {
                $insideAllowedRoot = true;
                break;
            }
        }
        if (!$insideAllowedRoot) return null;
        $stat = @stat($candidate);
        if (!is_array($stat) || $stat['size'] <= 0 || $stat['size'] > 12 * 1024 * 1024) return null;

        return [
            'url' => $path,
            'path' => $candidate,
            'bytes' => (int) $stat['size'],
            'mtime' => (int) $stat['mtime'],
            // Changing the source or encoder revision gives a new URL, safe for long caching.
            // Git/deploy changes mtimes: fingerprint actual bytes, not timestamps.
            'key' => substr(hash('sha256', 'webp-q78-v1|' . $path . '|' . hash_file('sha256', $candidate)), 0, 24),
        ];
    }

    /** @return list<int> */
    function medical_media_variant_widths(array $widths): array
    {
        $allowed = [64, 128, 256, 320, 480, 640, 960, 1280, 1800];
        $result = [];
        foreach ($widths as $width) {
            if (is_int($width) && in_array($width, $allowed, true)) $result[$width] = $width;
        }
        sort($result, SORT_NUMERIC);
        return array_values($result);
    }

    /** @param array{key:string} $source */
    function medical_media_variant_url(array $source, int $width): string
    {
        return '/assets/media/variants/' . $source['key'] . '-' . $width . '.webp';
    }

    /**
     * @return array{src:string,srcset:string}
     * Remote/unbuilt images keep the exact supplied source; there is no proxy fallback.
     */
    function medical_media_responsive_image(string $url, array $widths = [320, 640, 960], int $defaultWidth = 640): array
    {
        $prebuiltRemote = medical_media_remote_responsive_image($url, $widths, $defaultWidth);
        if ($prebuiltRemote !== null) return $prebuiltRemote;
        $fallback = ['src' => $url, 'srcset' => ''];
        $source = medical_media_variant_source($url);
        if ($source === null) return $fallback;
        $variants = [];
        $root = __DIR__;
        foreach (medical_media_variant_widths($widths) as $width) {
            $variantUrl = medical_media_variant_url($source, $width);
            $variantPath = $root . $variantUrl;
            // No image decoding here: the CLI builder validates and atomically publishes files.
            if (is_file($variantPath) && !is_link($variantPath) && filesize($variantPath) > 0) {
                $variants[$width] = $variantUrl;
            }
        }
        if ($variants === []) return $fallback;
        $selected = end($variants);
        foreach ($variants as $width => $variant) {
            if ($width >= $defaultWidth) { $selected = $variant; break; }
        }
        $srcset = [];
        foreach ($variants as $width => $variant) $srcset[] = $variant . ' ' . $width . 'w';
        return ['src' => (string) $selected, 'srcset' => implode(', ', $srcset)];
    }

    /** Returns safely escaped src/srcset/sizes attributes, retaining an original on a cache miss. */
    function medical_media_image_attributes(string $url, string $sizes, array $widths = [320, 640, 960], int $defaultWidth = 640): string
    {
        $image = medical_media_responsive_image($url, $widths, $defaultWidth);
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $attributes = 'src="' . $escape($image['src']) . '"';
        if ($image['srcset'] !== '') {
            $attributes .= ' srcset="' . $escape($image['srcset']) . '" sizes="' . $escape($sizes) . '"';
        }
        return $attributes;
    }
}
