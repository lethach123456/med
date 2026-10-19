<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Render only self-contained template fragments: no bootstrap, DB or network.
$root = dirname(__DIR__);
$home = (string) file_get_contents($root . '/Tem/home.php');
$header = (string) file_get_contents($root . '/Tem/header.php');
$footer = (string) file_get_contents($root . '/Tem/footer.php');
$polish = (string) file_get_contents($root . '/assets/css/pages/home-polish.css');
$concept = (string) file_get_contents($root . '/assets/css/pages/home-concept-live.css');
$headerStyles = (string) file_get_contents($root . '/assets/css/pages/brand-header-match.css');
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException($message);
};
$fragment = static function (string $source, string $pattern): string {
    if (!preg_match($pattern, $source, $match)) throw new RuntimeException('Required template fragment not found: ' . $pattern);
    return $match[0];
};
$render = static function (string $template, array $variables): string {
    extract($variables, EXTR_SKIP);
    ob_start();
    try { eval('?>' . $template); return (string) ob_get_contents(); }
    finally { ob_end_clean(); }
};
$document = static function (string $markup): DOMXPath {
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $dom->loadHTML('<?xml encoding="UTF-8"><!doctype html><html><body>' . $markup . '</body></html>'); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    return new DOMXPath($dom);
};
$text = static fn (string $value): string => trim((string) preg_replace('/\s+/u', ' ', $value));
function site_localized_path(string $path, string $locale): string { return ($locale === 'en' ? '/en' : '') . $path; }

$steps = $fragment($home, '~<ol\b[^>]*class="approach-steps"[^>]*>[\s\S]*?</ol>~');
$labelsAssignment = $fragment($home, '~\$labels\s*=\s*\$isEnglish\s*\?[\s\S]*?\n\];~');
$language = $fragment($header, '~<summary\b[^\r\n]*aria-controls="medical-mobile-language-panel"[^\r\n]*</summary>~');
$brand = $fragment($footer, '~<a\b[^>]*class="footer-brand-link"[^>]*>[\s\S]*?</a>~');
$taglineAssignment = $fragment($footer, '~\$footerBrandTagline\s*=[^;]+;~');

foreach (['vi', 'en'] as $locale) {
    $isEnglish = $locale === 'en';
    eval($labelsAssignment);
    $xpath = $document($render($steps, ['isEnglish' => $isEnglish, 'labels' => $labels]));
    $lists = $xpath->query('//ol[contains(concat(" ", normalize-space(@class), " "), " approach-steps ")]');
    $assert($lists->length === 1, 'One native ordered steps list: ' . $locale);
    $list = $lists->item(0);
    $items = $xpath->query('./li[contains(concat(" ", normalize-space(@class), " "), " step ")]', $list);
    $assert($items->length === 3 && $xpath->query('./*', $list)->length === 3, 'Exactly three direct native list items: ' . $locale);
    $assert($list->getAttribute('role') === 'list' && $xpath->query('.//*[@role]', $list)->length === 0, 'Markerless list preserves Safari semantics and native item roles: ' . $locale);
    $assert($xpath->query('./article', $list)->length === 0, 'Article is not used as a listitem: ' . $locale);
    foreach (['stepOne', 'stepTwo', 'stepThree'] as $index => $key) {
        $item = $items->item($index);
        $assert(str_contains(' ' . $item->getAttribute('class') . ' ', ' reveal '), 'Reveal presentation hook retained: ' . $locale . ' ' . $key);
        $assert($text($xpath->query('./h3', $item)->item(0)->textContent) === $labels[$key], 'Localized step heading retained: ' . $locale . ' ' . $key);
        $assert($xpath->query('./p', $item)->length === 1 && $xpath->query('.//svg[@aria-hidden="true"]', $item)->length === 1, 'Step copy and decorative icon retained: ' . $locale . ' ' . $key);
    }

    $xpath = $document($render($language, ['isEnglish' => $isEnglish]));
    $summary = $xpath->query('//summary')->item(0);
    $visibleCode = $text($xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " lang-code ")]', $summary)->item(0)->textContent);
    $name = $summary->getAttribute('aria-label');
    $assert($visibleCode === ($isEnglish ? 'EN' : 'VI') && str_contains($name, $visibleCode), 'Language control accessible name includes its visible code: ' . $locale);
    $assert(str_contains($name, $isEnglish ? 'Choose language' : 'Chọn ngôn ngữ'), 'Language action is localized: ' . $locale);
    $assert($xpath->query('.//i[not(@aria-hidden="true")]', $summary)->length === 0, 'Language icons are decorative: ' . $locale);

    eval($taglineAssignment);
    $xpath = $document($render($brand, [
        'locale' => $locale, 'brandName' => 'MedReview', 'displayBrandName' => 'MedReview',
        'isMedReviewWordmark' => true, 'brandIconUrl' => '', 'footerBrandTagline' => $footerBrandTagline,
        'escape' => static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'),
    ]));
    $link = $xpath->query('//a')->item(0);
    $assert(!$link->hasAttribute('aria-label') && !$link->hasAttribute('aria-labelledby'), 'Footer brand link derives its name from visible text: ' . $locale);
    $assert(str_contains($text($link->textContent), 'MedReview') && str_contains($text($link->textContent), $footerBrandTagline), 'Footer name contains complete visible brand and tagline: ' . $locale);
    $assert($link->getAttribute('href') === ($isEnglish ? '/en/' : '/'), 'Footer logo points to the localized homepage: ' . $locale);
}

$assert((bool) preg_match('~\.approach-steps\s*\{[^}]*list-style\s*:\s*none[^}]*\}~', $polish), 'Native ordered list reset preserves the existing appearance');
$assert((bool) preg_match('~\.approach-steps\s*\{[^}]*margin\s*:\s*0[^}]*\}~', $polish), 'Native ordered list has no browser margin');
$assert((bool) preg_match('~\.approach-steps\s*\{[^}]*padding\s*:\s*0[^}]*\}~', $polish), 'Native ordered list has no browser indent');

// Read actual declarations rather than asserting a color happens to look dark.
// These are source-level representative contrast checks, not a browser CSS audit.
$cssHex = static function (string $css, string $selector, string $property): string {
    preg_match_all('~([^{}]+)\{([^{}]*)\}~', preg_replace('~/\*[\s\S]*?\*/~', '', $css), $rules, PREG_SET_ORDER);
    $value = null;
    foreach ($rules as $rule) {
        $selectors = array_map(static fn ($value) => trim((string) preg_replace('/\s+/', ' ', $value)), explode(',', $rule[1]));
        if (!in_array($selector, $selectors, true)) continue;
        if (preg_match('~(?:^|;)\s*' . preg_quote($property, '~') . '\s*:\s*(#[a-f0-9]{6}|#[a-f0-9]{3})(?![a-f0-9])~i', $rule[2], $match)) $value = strtolower($match[1]);
    }
    if ($value === null) throw new RuntimeException('Expected explicit hex ' . $property . ' declaration: ' . $selector);
    return $value;
};
$luminance = static function (string $hex): float {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    $channels = array_map(static function (string $pair): float {
        $value = hexdec($pair) / 255;
        return $value <= .04045 ? $value / 12.92 : (($value + .055) / 1.055) ** 2.4;
    }, str_split($hex, 2));
    return $channels[0] * .2126 + $channels[1] * .7152 + $channels[2] * .0722;
};
$contrast = static function (string $left, string $right) use ($luminance): float {
    $a = $luminance($left); $b = $luminance($right);
    return (max($a, $b) + .05) / (min($a, $b) + .05);
};
$specialtyBackgrounds = [$cssHex($concept, '.med-home.hc-home .specialty', 'background')];
foreach (range(2, 6) as $index) $specialtyBackgrounds[] = $cssHex($concept, '.med-home.hc-home .specialty:nth-child(' . $index . ')', 'background');
$assert(count(array_unique($specialtyBackgrounds)) === 6, 'All six actual mobile specialty card backgrounds are covered');
$specialtyBackgrounds[] = '#fff'; // Desktop cards inherit the white homepage background.
$specialtyBackgrounds[] = $cssHex($concept, '.med-home.hc-home .specialty:hover', 'background');
$scope = 'body.site-home:not(.site-home-next) main.med-home.hc-home ';
$foreground = $cssHex($polish, $scope . '.specialty small', 'color');
foreach ($specialtyBackgrounds as $background) {
    $assert($contrast($foreground, $background) >= 4.5, 'Specialty supporting copy passes 4.5:1 on ' . $background);
}
$backgrounds = [
    '.city-filters button' => [$cssHex($concept, '.med-home.hc-home .city-filters button', 'background')],
    '.toolbar-note' => ['#fff'], '.snapshot-note' => ['#fff'],
    '.rail-count' => ['#fff', $cssHex($concept, '.med-home.hc-home .toplist-section', 'background')],
    '.city-filters button[aria-pressed=true]' => [$cssHex($concept, '.med-home.hc-home .city-filters button[aria-pressed=true]', 'background')],
];
foreach ($backgrounds as $selector => $surfaces) {
    foreach ($surfaces as $background) {
        $assert($contrast($cssHex($polish, $scope . $selector, 'color'), $background) >= 4.5, $selector . ' passes 4.5:1 on ' . $background);
    }
}
$taglineColor = $cssHex($headerStyles, '.medical-header .brand-copy>.brand-tagline', 'color');
foreach (['#fff', '#f2f2f2'] as $background) {
    // The 95% white header remains at least #f2f2f2 even over a black image.
    $assert($contrast($taglineColor, $background) >= 4.5, 'Shared header tagline passes 4.5:1 on ' . $background);
}

echo "Home accessibility: {$checks} checks passed. Fragment renders only; no DB/network.\n";
