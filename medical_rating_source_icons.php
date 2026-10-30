<?php
declare(strict_types=1);

/** Fixed SVG assets only; never render source-provided markup. */
function medical_rating_source_icons(array $sources, string $locale = 'vi'): string
{
    $icons = [];
    foreach ($sources as $source) {
        if (!is_array($source)) continue;
        $name = strtolower(trim((string) ($source['source'] ?? '')));
        $host = strtolower((string) parse_url((string) ($source['url'] ?? ''), PHP_URL_HOST));
        $kind = preg_match('~(^|\.)(facebook\.com|fb\.com)$~', $host) || in_array($name, ['facebook', 'fb'], true) ? 'facebook'
            : (preg_match('~(^|\.)(google\.com|google\.com\.vn|maps\.app\.goo\.gl)$~', $host) || in_array($name, ['google', 'google maps', 'google map'], true) ? 'google' : 'website');
        $icons[$kind] = true;
    }
    if (!$icons) $icons['website'] = true;
    $html = '<span class="review-source-icons" style="display:inline-flex;align-items:center;gap:5px;vertical-align:middle">';
    foreach (array_keys($icons) as $kind) {
        $label = $kind === 'google' ? 'Google' : ($kind === 'facebook' ? 'Facebook' : ($sources ? 'Website' : ($locale === 'en' ? 'Stored reviews; source unspecified' : 'Review đã lưu; chưa xác định nguồn')));
        $path = '/assets/icons/review-source-' . $kind . '.svg';
        $html .= '<img src="' . $path . '" width="16" height="16" alt="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;width:16px;height:16px;object-fit:contain;flex:none">';
    }
    return $html . '</span>';
}
