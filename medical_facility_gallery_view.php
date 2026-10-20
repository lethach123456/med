<?php
declare(strict_types=1);

/**
 * Presentation only: one full lightbox list and at most four distinct previews.
 * No DB, disk writes, image downloads or changes to the stored gallery order.
 * Preview keys always reference the returned full list, including a non-first
 * cover. A missing cover may be included once, never duplicated as a preview.
 */
function facility_hero_gallery_view_model(array $gallery, string $heroImage = ''): array
{
    $usable = static function (mixed $value): string {
        if (!is_string($value)) return '';
        $value = trim($value);
        if ($value === '' || str_contains($value, '\\') || preg_match('/[\x00-\x1f\x7f]/', $value)) return '';
        if ($value !== '/' && str_starts_with($value, '/') && !str_starts_with($value, '//')) return $value;
        $parts = parse_url($value);
        return is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) ? $value : '';
    };
    $images = [];
    foreach ($gallery as $value) {
        $url = $usable($value);
        if ($url !== '' && !in_array($url, $images, true)) $images[] = $url;
    }
    $heroImage = $usable($heroImage);
    if ($heroImage !== '' && !in_array($heroImage, $images, true)) array_unshift($images, $heroImage);
    $heroIndex = $heroImage !== '' ? array_search($heroImage, $images, true) : 0;
    $heroIndex = $heroIndex === false ? 0 : (int) $heroIndex;
    $count = count($images);
    $thumbnails = [];
    foreach ($images as $index => $url) {
        if ($index === $heroIndex) continue;
        $thumbnails[$index] = $url;
        if (count($thumbnails) === 4) break;
    }
    return [
        'images' => $images, 'count' => $count, 'hero_index' => $heroIndex,
        'hero_image' => $images[$heroIndex] ?? '',
        'layout' => match ($count) { 0 => 'empty', 1 => 'single', 2 => 'duo', 3 => 'trio', 4 => 'quad', default => 'mosaic' },
        'thumbnails' => $thumbnails, 'remaining' => max(0, $count - 1 - count($thumbnails)),
    ];
}
