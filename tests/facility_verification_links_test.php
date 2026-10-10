<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Presentation-only regression tests. No bootstrap, database, or network access.
$root = dirname(__DIR__);
require_once $root . '/medical_facility_directory_view.php';
function site_localized_path(string $path, string $locale): string
{
    return ($locale === 'en' ? '/en' : '') . $path;
}
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    ++$checks;
    if (!$condition) throw new RuntimeException($label);
};
$hasClass = static fn(string $class): string => "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
$parse = static function (string $html): DOMXPath {
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    return new DOMXPath($document);
};
$assertNoNestedLinks = static function (string $html, string $label) use ($assert): void {
    // DOMDocument repairs nested anchors, so inspect the raw tags as well.
    preg_match_all('~</?a\b[^>]*>~i', $html, $matches);
    $depth = 0;
    $valid = true;
    foreach ($matches[0] as $tag) {
        if (str_starts_with(strtolower($tag), '</')) { if ($depth !== 1) $valid = false; $depth = 0; }
        else { if ($depth !== 0) $valid = false; $depth = 1; }
    }
    $assert($valid && $depth === 0, $label . ': independent, balanced links');
};
$facility = ['name' => 'Clinic <A> & Care', 'slug' => 'clinic', 'verified' => true,
    'category' => 'Nha khoa', 'city' => 'Hà Nội', 'rating' => 0, 'reviews_count' => 0];
foreach (['vi', 'en'] as $locale) {
    $policy = site_localized_path('/ho-so-da-xac-thuc', $locale);
    $profile = site_localized_path('/co-so-y-te/clinic', $locale);
    $html = facility_directory_card($facility, $locale);
    $xpath = $parse($html);
    $badge = $xpath->query('//a[' . $hasClass('fd-verified') . ']');
    $assert($badge->length === 1 && $badge->item(0)->getAttribute('href') === $policy, $locale . ': directory badge has localized policy destination');
    $assert($badge->item(0)->getAttribute('aria-label') === ($locale === 'en' ? 'Understand the verified facility profile label' : 'Tìm hiểu hồ sơ cơ sở đã xác thực'), $locale . ': icon link has meaningful accessible name');
    $assert($badge->item(0)->getAttribute('role') !== 'img', $locale . ': linked icon retains native link semantics');
    $assert($xpath->query('//h2/a[@href="' . $profile . '"]')->length === 1 && $xpath->query('//h2/a')->length === 2, $locale . ': name and badge are sibling links');
    $assert($xpath->query('//a[' . $hasClass('fd-profile-link') . '][@href="' . $profile . '"]')->length === 1, $locale . ': profile CTA retains facility destination');
    $assert($xpath->query('//a[' . $hasClass('fd-verified') . ']//i[@aria-hidden="true"]')->length === 1, $locale . ': decorative seal is hidden from accessibility tree');
    $assert(str_contains($html, 'Clinic &lt;A&gt; &amp; Care'), $locale . ': database text remains escaped');
    $assertNoNestedLinks($html, $locale . ' directory');
    $unverified = $parse(facility_directory_card(array_replace($facility, ['verified' => false]), $locale));
    $assert($unverified->query('//a[' . $hasClass('facility-verification-link') . ']')->length === 0, $locale . ': unverified profile has no policy badge');
}

// Render the actual mixed-Toplist card, without its DB controller/header/footer.
$mixedSource = (string) file_get_contents($root . '/Tem/toplist-doctor-detail.php');
$assert((bool) preg_match('~<article class="td-doctor".*?</article>~s', $mixedSource, $cardMatch), 'Mixed Toplist card is available for isolated rendering');
$renderMixedCard = static function (string $locale, bool $isDoctor, bool $verified) use ($cardMatch): string {
    $toplistLanguage = $locale;
    $tdEnglish = $locale === 'en';
    $tdIsDoctor = $isDoctor;
    $tdDoctor = ['name' => 'Provider <A>', 'verified' => $verified ? 1 : 0, 'rank_order' => 1, 'title_text' => '', 'reviews_count' => 0, 'rating' => 0];
    $tdImage = $tdBio = '';
    $tdProfile = site_localized_path(($isDoctor ? '/bac-si/' : '/co-so-y-te/') . 'provider', $locale);
    $tdKind = $isDoctor ? 'Doctor' : 'Facility';
    $tdProfileLabel = 'View profile';
    $tdLabels = ['verified' => $tdEnglish ? 'Verified' : 'Đã xác thực'];
    $tdEscape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    ob_start();
    try { eval('?>' . $cardMatch[0]); return (string) ob_get_contents(); }
    finally { ob_end_clean(); }
};
foreach (['vi', 'en'] as $locale) {
    foreach ([false, true] as $doctor) {
        $kind = $doctor ? 'doctor' : 'facility';
        $html = $renderMixedCard($locale, $doctor, true);
        $xpath = $parse($html);
        $policyLinks = $xpath->query('//a[' . $hasClass('facility-verification-link') . ']');
        $assert($policyLinks->length === ($doctor ? 0 : 1), $locale . ': mixed Toplist links facility verification only, ' . $kind);
        if (!$doctor) $assert($policyLinks->item(0)->getAttribute('href') === site_localized_path('/ho-so-da-xac-thuc', $locale), $locale . ': mixed facility destination is localized');
        else $assert($xpath->query('//span[' . $hasClass('td-verified') . ']')->length === 1, $locale . ': doctor verification remains descriptive');
        $assert($xpath->query('//h2/a')->length === 1 && str_contains($html, 'Provider &lt;A&gt;'), $locale . ': mixed ' . $kind . ' name link and escaping retained');
        $assertNoNestedLinks($html, $locale . ' mixed ' . $kind);
        $unverified = $parse($renderMixedCard($locale, $doctor, false));
        $assert($unverified->query('//*[' . $hasClass('td-verified') . ']')->length === 0, $locale . ': unverified mixed ' . $kind . ' badge omitted');
    }
}

foreach (['Tem/home.php' => '$locale', 'Tem/home-next.php' => '$locale', 'co-so-y-te-chi-tiet.php' => '$facilityLanguage'] as $file => $localeVariable) {
    $source = (string) file_get_contents($root . '/' . $file);
    $assert(str_contains($source, "site_localized_path('/ho-so-da-xac-thuc', {$localeVariable})"), $file . ': policy destination uses current locale');
    $assert((bool) preg_match('~<a class="[^"]*facility-verification-link[^"]*"(?:(?!</a>).)*?aria-label=~s', $source), $file . ': icon badge is a named native link');
    $assert(str_contains($source, "['verified']") && str_contains($source, 'aria-hidden="true"'), $file . ': verification condition and decorative icons retained');
}
$ajax = (string) file_get_contents($root . '/assets/js/facility-directory.js');
$assert(str_contains($ajax, "const verificationPath = (en ? '/en' : '') + '/ho-so-da-xac-thuc'"), 'AJAX directory cards retain locale-specific policy route');
$assert(str_contains($ajax, 'item.verified ? `<a class="fd-verified facility-verification-link') && str_contains($ajax, 'href="${verificationPath}" aria-label="${verificationLabel}"'), 'AJAX directory verification remains conditional, named, and linked');
$assert(str_contains($ajax, '<h2><a href="${escape(url)}">${escape(item.name)}</a>${item.verified'), 'AJAX name anchor closes before verification anchor');

// Execute only the existing Toplist output-filter closure. Its script accumulator
// replaces earlier content, so source-only checks can miss discarded JS config.
$toplist = (string) file_get_contents($root . '/toplist-chi-tiet-mau.php');
$start = strpos($toplist, 'ob_start(static function (string $html)');
$assert($start !== false, 'Facility Toplist output filter is available');
$tokens = token_get_all('<?php ' . substr($toplist, $start));
$callback = '';
$record = false;
$opened = false;
$depth = 0;
foreach ($tokens as $token) {
    if (!$record && is_array($token) && $token[0] === T_STATIC) $record = true;
    if (!$record) continue;
    $text = is_array($token) ? $token[1] : $token;
    if (is_array($token) && $token[0] === T_DIR) $text = var_export($root, true);
    $callback .= $text;
    if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { ++$depth; $opened = true; }
    elseif ($token === '}' && --$depth === 0 && $opened) break;
}
$assert($opened && $depth === 0, 'Only the trusted presentation closure is extracted');
$renderToplistFilter = static function (string $locale) use ($callback): string {
    $facilities = $toplistGalleryMetadata = [];
    $toplistLanguage = $locale;
    $filter = eval('return ' . $callback . ';');
    return $filter('<html><head></head><body><article class="facility"><div class="facility-body"><div class="facility-title"><h2>Clinic</h2></div></div></article></body></html>');
};
foreach (['vi', 'en'] as $locale) {
    $html = $renderToplistFilter($locale);
    $assert((bool) preg_match('~const toplistVerificationLink=(\{.*?\});~s', $html, $configMatch), $locale . ': final Toplist output retains verification JS config');
    $config = json_decode($configMatch[1], true, 512, JSON_THROW_ON_ERROR);
    $assert($config['url'] === site_localized_path('/ho-so-da-xac-thuc', $locale), $locale . ': emitted Toplist config has localized destination');
    $assert($config['aria'] === ($locale === 'en' ? 'Understand the verified facility profile label' : 'Tìm hiểu hồ sơ cơ sở đã xác thực'), $locale . ': emitted Toplist config has localized accessible name');
    $assert(str_contains($html, 'document.createElement("a")') && str_contains($html, 'badge.href=toplistVerificationLink.url') && str_contains($html, 'badge.className="facility-profile-eyebrow facility-verification-link"'), $locale . ': surviving script creates a semantic policy link');
    $assert(strpos($html, 'const toplistVerificationLink=') < strpos($html, 'badge.href=toplistVerificationLink.url'), $locale . ': final JS defines config before use');
}
$css = (string) file_get_contents($root . '/assets/css/core/public-components.css');
$assert((bool) preg_match('~body a\.facility-verification-link:focus-visible\s*\{[^}]*outline:2px solid #2563ff;[^}]*outline-offset:3px~s', $css), 'Policy links have visible keyboard focus');
$assert((bool) preg_match('~@media\s*\(pointer:coarse\)[^{]*\{.*?body a\.facility-verification-link\{min-height:44px\}.*?body a\.facility-verification-link\.facility-verification-icon\{min-width:44px;min-height:44px\}~s', $css), 'Coarse pointers have at least 44px policy-link hit areas');
$assert(str_contains($css, 'touch-action:manipulation') && str_contains($css, '@media(prefers-reduced-motion:reduce){body a.facility-verification-link{transition:none}}'), 'Policy interactions respect touch and reduced motion');
$assert(str_contains((string) file_get_contents($root . '/Tem/header.php'), '/assets/css/core/public-components.css'), 'Policy interaction rules are loaded site-wide');
foreach (['medical_doctor_directory_view.php', 'Tem/doctor-profile.php', 'review.php', 'review-chi-tiet.php', 'assets/js/medical-global-search.js'] as $file) {
    $assert(!str_contains((string) file_get_contents($root . '/' . $file), 'facility-verification-link'), $file . ': unrelated doctor/review verification and full-row search links remain unchanged');
}
echo "Facility verification links: {$checks} checks passed. No DB or network access.\n";
