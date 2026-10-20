<?php
declare(strict_types=1);

/**
 * Server-rendered sharing metadata. No DB, HTTP fetches, image transforms or
 * writes during page rendering. Canonical/hreflang/OG locale remain with pages.
 */
function site_social_text(mixed $value, int $limit): string
{
    if (!is_string($value)) return '';
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $value) ?? '';
    $value = strip_tags($value);
    $value = preg_replace('/[\p{C}\p{Z}\s]+/u', ' ', $value) ?? '';
    $value = trim($value);
    if (mb_strlen($value, 'UTF-8') <= $limit) return $value;
    return rtrim(mb_substr($value, 0, $limit - 1, 'UTF-8')) . '…';
}

/** Parse public HTTP(S) URLs without following redirects or resolving DNS. */
function site_social_http_url(string $value): string
{
    $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($value === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $value)
        || preg_match('/%(?:00|0a|0d)/i', $value) || !filter_var($value, FILTER_VALIDATE_URL)) return '';
    $parts = parse_url($value);
    if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        || isset($parts['user']) || isset($parts['pass'])) return '';
    $host = strtolower(trim($parts['host'] ?? '', '[]'));
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return '';
    } elseif (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]*$/D', $host)
        || preg_match('/\.(?:localhost|local|internal|test|invalid)$/D', $host)) return '';
    return explode('#', $value, 2)[0];
}

/** Page URLs must use the configured canonical origin, never HTTP_HOST. */
function site_social_page_url(mixed $value): string
{
    $origin = site_canonical_origin();
    if (!is_string($value) || trim($value) === '') return $origin . '/';
    $value = trim($value);
    if (!preg_match('~^[a-z][a-z0-9+.-]*:~i', $value) && !str_starts_with($value, '//')) {
        $value = $origin . '/' . ltrim($value, '/');
    }
    $url = site_social_http_url($value);
    $parts = $url !== '' ? parse_url($url) : false;
    $base = parse_url($origin);
    if (!is_array($parts) || strtolower($parts['scheme']) !== strtolower($base['scheme'])
        || strtolower($parts['host']) !== strtolower($base['host'])
        || ($parts['port'] ?? 443) !== ($base['port'] ?? 443)) return $origin . '/';
    return $url;
}

/**
 * Use actual local image headers, never pretend a portrait is 1200x630. Remote
 * media are not fetched, so their MIME/dimensions are deliberately unspecified.
 * @return array{url:string,alt:string,width?:int,height?:int,mime?:string}
 */
function site_social_image(mixed $value, mixed $alt = ''): array
{
    $fallback = ['url' => site_absolute_url('/assets/social/medreview-share.jpg'), 'alt' => 'MedReview.vn',
        'width' => 1200, 'height' => 630, 'mime' => 'image/jpeg'];
    if (!is_string($value) || trim($value) === '') return $fallback;
    $value = trim($value);
    if (str_starts_with($value, '//')) $value = 'https:' . $value;
    elseif (!preg_match('~^[a-z][a-z0-9+.-]*:~i', $value)) $value = site_absolute_url($value);
    $url = site_social_http_url($value);
    if ($url === '') return $fallback;
    $parts = parse_url($url);
    $host = strtolower($parts['host']);
    $ownHosts = [strtolower((string) parse_url(site_canonical_origin(), PHP_URL_HOST)), 'medreview.vn', 'www.medreview.vn'];
    $image = ['url' => $url, 'alt' => site_social_text($alt, 420) ?: 'MedReview'];
    if (in_array($host, $ownHosts, true)) {
        if (preg_match('/%(?:2f|5c)/i', $parts['path'] ?? '')) return $fallback;
        $path = rawurldecode($parts['path'] ?? '/');
        if (!preg_match('~^/(?:assets|uploads|images|img)/~', $path)
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\x00-\x1f\x7f\\\\%]~', $path)) return $fallback;
        $root = realpath(__DIR__);
        $file = realpath(__DIR__ . $path);
        if (!is_string($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)
            || !is_file($file) || !is_readable($file)) return $fallback;
        $info = @getimagesize($file);
        $mimes = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp', IMAGETYPE_GIF => 'image/gif'];
        if (!is_array($info) || !isset($mimes[$info[2]]) || $info[0] < 200 || $info[1] < 200) return $fallback;
        // Treat MedReview's www alias/HTTP URLs as our single stable HTTPS origin.
        $image['url'] = site_absolute_url($parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : ''));
        $image += ['width' => $info[0], 'height' => $info[1], 'mime' => $mimes[$info[2]]];
    }
    return $image;
}

/** Accept real ISO/date-time values only; never invent publication dates. */
function site_social_datetime(mixed $value): string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?)?$/D', $value)) return '';
    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('Asia/Ho_Chi_Minh'));
        $errors = DateTimeImmutable::getLastErrors();
        if (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) return '';
        return $date->format(DATE_ATOM);
    } catch (Throwable) { return ''; }
}

/**
 * Exactly one shared OG/Twitter set per successful public page head. Unknown
 * types become website; optional article fields require an actual article.
 */
function site_social_meta_tags(array $meta): string
{
    $title = site_social_text($meta['title'] ?? '', 100) ?: 'MedReview';
    $description = site_social_text($meta['description'] ?? '', 200)
        ?: 'MedReview – Thông tin cơ sở y tế, bác sĩ, đánh giá và cẩm nang sức khỏe.';
    $type = in_array($meta['type'] ?? '', ['website', 'article', 'profile'], true) ? $meta['type'] : 'website';
    $image = site_social_image($meta['image'] ?? '', $meta['image_alt'] ?? $title);
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $tags = [];
    $tag = static function (string $attribute, string $name, string $value) use (&$tags, $escape): void {
        $tags[] = '<meta ' . $attribute . '="' . $name . '" content="' . $escape($value) . '">';
    };
    foreach (['og:type' => $type, 'og:site_name' => 'MedReview', 'og:title' => $title,
        'og:description' => $description, 'og:url' => site_social_page_url($meta['url'] ?? '/'), 'og:image' => $image['url']] as $name => $value) {
        $tag('property', $name, $value);
    }
    if (str_starts_with($image['url'], 'https://')) $tag('property', 'og:image:secure_url', $image['url']);
    foreach (['mime' => 'type', 'width' => 'width', 'height' => 'height', 'alt' => 'alt'] as $key => $suffix) {
        if (isset($image[$key])) $tag('property', 'og:image:' . $suffix, (string) $image[$key]);
    }
    // Portraits and square photos should not be forced into a wide X card crop.
    $large = !isset($image['width'], $image['height']) || ($image['width'] >= 300 && $image['width'] / $image['height'] >= 1.5);
    foreach (['twitter:card' => $large ? 'summary_large_image' : 'summary', 'twitter:title' => $title,
        'twitter:description' => $description, 'twitter:image' => $image['url'], 'twitter:image:alt' => $image['alt']] as $name => $value) {
        $tag('name', $name, $value);
    }
    if ($type === 'article') {
        foreach (['published_time', 'modified_time'] as $key) {
            $date = site_social_datetime($meta[$key] ?? '');
            if ($date !== '') $tag('property', 'article:' . $key, $date);
        }
        $section = site_social_text($meta['section'] ?? '', 100);
        if ($section !== '') $tag('property', 'article:section', $section);
    }
    return implode("\n", $tags);
}
