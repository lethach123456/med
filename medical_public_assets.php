<?php
declare(strict_types=1);

/** Public presentation assets only; no DB, build, remote fetch or writes in requests. */
function medical_public_shell_sources(string $type = 'shell'): array
{
    $shell = [
        '/assets/css/core/local-fonts.css',
        '/assets/css/core/ui-2026.css',
        '/assets/css/core/header-base.css',
        '/assets/css/pages/brand-home-refresh.css',
        '/assets/css/pages/brand-header-match.css',
        '/assets/css/core/public-pages-refresh.css',
        '/assets/css/core/public-components.css',
    ];
    return $type === 'home' ? array_merge([
        '/assets/css/core/shared-typography.css',
        '/assets/css/pages/home-legacy.css',
    ], $shell, [
        '/assets/css/pages/home-concept-live.css',
        '/assets/css/pages/home-polish.css',
        '/assets/css/core/footer-base.css',
        '/assets/css/pages/brand-footer-match.css',
    ]) : $shell;
}

function medical_public_asset_url(string $path): string
{
    static $urls = [];
    if (!isset($urls[$path])) {
        $file = __DIR__ . $path;
        $urls[$path] = $path . '?v=' . (is_file($file) ? substr(hash_file('sha256', $file), 0, 16) : '1');
    }
    return $urls[$path];
}

/** Reject stale builds: when a source changes, serve the originals in the same order. */
function medical_public_bundle_valid(array $manifest, string $type = 'shell'): bool
{
    $type = $type === 'home' ? 'home' : 'shell';
    if (!is_array($manifest['sources'] ?? null)) return false;
    $url = $manifest['url'] ?? '';
    if (!is_string($url) || !preg_match('~^/assets/css/generated/public-' . $type . '\.[a-f0-9]{24}\.css$~D', $url)) return false;
    $file = __DIR__ . $url;
    if (!is_file($file) || is_link($file) || hash_file('sha256', $file) !== ($manifest['sha256'] ?? null)) return false;
    $sources = medical_public_shell_sources($type);
    if (array_keys($manifest['sources']) !== $sources) return false;
    foreach ($sources as $path) {
        $source = __DIR__ . $path;
        if (!is_file($source) || hash_file('sha256', $source) !== $manifest['sources'][$path]) return false;
    }
    return true;
}

function medical_public_shell_bundle(string $type = 'shell'): ?string
{
    static $checked = [];
    static $bundles = [];
    $type = $type === 'home' ? 'home' : 'shell';
    if (isset($checked[$type])) return $bundles[$type] ?? null;
    $checked[$type] = true;
    $manifestFile = __DIR__ . '/assets/css/generated/public-' . $type . '-manifest.json';
    $manifest = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : null;
    if (!is_array($manifest) || !medical_public_bundle_valid($manifest, $type)) return null;
    return $bundles[$type] = $manifest['url'];
}

/** Can be called early in the homepage head and again by the shared header without duplicates. */
function medical_public_shell_styles(string $type = 'shell'): string
{
    if (!empty($GLOBALS['medical_public_shell_styles_emitted'])) return '';
    $GLOBALS['medical_public_shell_styles_emitted'] = true;
    $type = $type === 'home' ? 'home' : 'shell';
    if ($type === 'home') $GLOBALS['medical_public_home_assets_emitted'] = true;
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // Keep this stylesheet discoverable by the existing front-editor icon picker.
    $icons = '/assets/vendor/@phosphor-icons/web/2.1.1/phosphor.css';
    $html = '<link rel="stylesheet" href="' . $escape(medical_public_asset_url($icons)) . '">' . "\n";
    $bundle = medical_public_shell_bundle($type);
    foreach ($bundle !== null ? [$bundle] : array_map('medical_public_asset_url', medical_public_shell_sources($type)) as $url) {
        $html .= '<link rel="stylesheet" href="' . $escape($url) . '">' . "\n";
    }
    return $html;
}
