<?php
declare(strict_types=1);

/** Pure renderer: keep custom admin icons, use browser-sized assets for the brand logo. */
function site_favicon_markup(string $configured): string
{
    $brand = '/uploads/library/2026/07/fbef23f192e21b0669c670803723ec66.jpg';
    $path = parse_url($configured, PHP_URL_PATH);
    $host = strtolower((string) parse_url($configured, PHP_URL_HOST));
    $useBrand = $configured === '' || ($path === $brand && in_array($host, ['', 'medreview.vn', 'www.medreview.vn'], true));
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    if (!$useBrand) {
        return '<link rel="icon" href="' . $escape($configured) . '">' . "\n"
            . '<link rel="apple-touch-icon" href="' . $escape($configured) . '">';
    }
    $version = '20261012';
    return '<link rel="icon" type="image/x-icon" href="/favicon.ico?v=' . $version . '">' . "\n"
        . '<link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32.png?v=' . $version . '">' . "\n"
        . '<link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png?v=' . $version . '">';
}
