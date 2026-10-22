<?php
declare(strict_types=1);

/**
 * Convert the retired facility detail query URL to its public, pretty route.
 * This helper is intentionally pure: it does not resolve slugs or access a DB.
 */
function medical_canonical_facility_url(string $url, string $siteOrigin = 'https://medreview.vn'): string
{
    if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '//')
        || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7F]/', $url)
        || preg_match('/%(?![0-9a-f]{2})/i', $url)
        || preg_match('/[\x00-\x1F\x7F]/', rawurldecode($url))
        || preg_match('//u', $url) !== 1) {
        return $url;
    }
    try {
        $parts = parse_url($url);
        $origin = parse_url($siteOrigin);
    } catch (ValueError $error) {
        return $url;
    }
    if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) return $url;

    $originValid = is_array($origin)
        && in_array(strtolower((string) ($origin['scheme'] ?? '')), ['http', 'https'], true)
        && !empty($origin['host']) && !isset($origin['user']) && !isset($origin['pass'])
        && !isset($origin['query']) && !isset($origin['fragment'])
        && in_array((string) ($origin['path'] ?? ''), ['', '/'], true)
        && !str_contains($siteOrigin, '\\') && !preg_match('/[\x00-\x20\x7F]/', $siteOrigin)
        && preg_match('//u', $siteOrigin) === 1;
    $originScheme = $originValid ? strtolower((string) $origin['scheme']) : 'https';
    $originHost = $originValid ? strtolower((string) $origin['host']) : 'medreview.vn';
    $originPort = $originValid ? (int) ($origin['port'] ?? ($originScheme === 'https' ? 443 : 80)) : 443;
    $canonicalOrigin = $originValid
        ? $originScheme . '://' . $originHost . (isset($origin['port']) ? ':' . $origin['port'] : '')
        : 'https://medreview.vn';

    $absolute = isset($parts['scheme']) || isset($parts['host']);
    $prefix = '';
    if ($absolute) {
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') return $url;
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $medreviewHost = in_array($host, ['medreview.vn', 'www.medreview.vn'], true)
            && $port === ($scheme === 'https' ? 443 : 80);
        $sameOriginHost = $host === $originHost && $port === $originPort;
        if (!$medreviewHost && !$sameOriginHost) return $url;
        // A localhost/staging origin must not turn production links into local links.
        $prefix = $medreviewHost && !in_array($originHost, ['medreview.vn', 'www.medreview.vn'], true)
            ? 'https://medreview.vn' : $canonicalOrigin;
    }

    $path = (string) ($parts['path'] ?? '');
    if (!in_array($path, ['/co-so-y-te-chi-tiet.php', 'co-so-y-te-chi-tiet.php', '/en/co-so-y-te-chi-tiet.php'], true)) return $url;
    $query = (string) ($parts['query'] ?? '');
    if ($query === '' || preg_match('/[\x00-\x1F\x7F]/', urldecode($query))) return $url;

    $queryParts = explode('&', $query);
    $keys = [];
    $parameters = [];
    foreach ($queryParts as $queryPart) {
        $key = urldecode(explode('=', $queryPart, 2)[0]);
        if ($key !== ltrim($key) && in_array(ltrim($key), ['slug', 'lang'], true)) return $url;
        if (preg_match('/\A(?:slug|lang)\[/', $key)) return $url;
        if ($key === 'slug' || $key === 'lang') {
            if (isset($keys[$key])) return $url;
            $keys[$key] = true;
            // Parse only an exact, recognized key: parse_str otherwise aliases
            // keys such as " slug" and can silently overwrite the real slug.
            parse_str($queryPart, $parameter);
            $parameters[$key] = $parameter[$key] ?? null;
        }
    }
    $slug = $parameters['slug'] ?? null;
    if (!is_string($slug) || trim($slug) === '' || strlen($slug) > 2048
        || preg_match('/\A\s*\z/u', $slug) === 1
        || preg_match('//u', $slug) !== 1 || in_array($slug, ['.', '..'], true)
        || preg_match('~[\\\\/?#\x00-\x1F\x7F]~', $slug)) {
        return $url;
    }
    if (isset($parameters['lang']) && !is_string($parameters['lang'])) return $url;
    $language = str_starts_with($path, '/en/') ? 'en' : 'vi';
    $consumeLanguage = isset($keys['lang']) && in_array($parameters['lang'] ?? null, ['en', 'vi'], true);
    if ($consumeLanguage) $language = $parameters['lang'];
    $remaining = [];
    foreach ($queryParts as $queryPart) {
        $key = urldecode(explode('=', $queryPart, 2)[0]);
        if ($key === 'slug' || ($key === 'lang' && $consumeLanguage)) continue;
        $remaining[] = $queryPart;
    }
    $remainingQuery = implode('&', $remaining);
    return $prefix . ($language === 'en' ? '/en' : '') . '/co-so-y-te/' . rawurlencode($slug)
        . ($remainingQuery !== '' ? '?' . $remainingQuery : '')
        . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

/** Rewrite an anchor tag without serializing or reformatting its other bytes. */
function medical_normalize_facility_anchor_tag(string $tag, string $siteOrigin): string
{
    if (preg_match('/\A<a(?=[\s\/>])/i', $tag, $start) !== 1) return $tag;
    $length = strlen($tag);
    $cursor = strlen($start[0]);
    $href = null;
    while ($cursor < $length) {
        while ($cursor < $length && str_contains(" \t\r\n\f", $tag[$cursor])) ++$cursor;
        if ($cursor >= $length || $tag[$cursor] === '>' || substr($tag, $cursor, 2) === '/>') break;
        $nameStart = $cursor;
        while ($cursor < $length && !str_contains(" \t\r\n\f=/><\"'`", $tag[$cursor])) ++$cursor;
        if ($cursor === $nameStart) return $tag;
        $name = strtolower(substr($tag, $nameStart, $cursor - $nameStart));
        while ($cursor < $length && str_contains(" \t\r\n\f", $tag[$cursor])) ++$cursor;
        $value = null;
        if ($cursor < $length && $tag[$cursor] === '=') {
            ++$cursor;
            while ($cursor < $length && str_contains(" \t\r\n\f", $tag[$cursor])) ++$cursor;
            if ($cursor >= $length) return $tag;
            $quote = in_array($tag[$cursor], ['"', "'"], true) ? $tag[$cursor++] : '';
            $valueStart = $cursor;
            if ($quote !== '') {
                $end = strpos($tag, $quote, $cursor);
                if ($end === false) return $tag;
                $cursor = $end + 1;
                $value = [$valueStart, $end - $valueStart, $quote];
            } else {
                while ($cursor < $length && !str_contains(" \t\r\n\f>", $tag[$cursor])) {
                    if (str_contains("<\"'`", $tag[$cursor])) return $tag;
                    ++$cursor;
                }
                if ($cursor === $valueStart) return $tag;
                $value = [$valueStart, $cursor - $valueStart, ''];
            }
        }
        if ($name === 'href') {
            // Duplicate href attributes have ambiguous first/last-value semantics.
            if ($href !== null) return $tag;
            $href = $value ?? false;
        }
    }
    if (!is_array($href)) return $tag;
    [$valueStart, $valueLength, $quote] = $href;
    $original = substr($tag, $valueStart, $valueLength);
    $decoded = html_entity_decode($original, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalized = medical_canonical_facility_url($decoded, $siteOrigin);
    if ($normalized === $decoded) return $tag;
    $replacement = htmlspecialchars($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($quote === '') $replacement = '"' . $replacement . '"';
    return substr_replace($tag, $replacement, $valueStart, $valueLength);
}

/**
 * Normalize only real anchor href attributes in stored/editorial HTML.
 * Raw-text and literal-code blocks, comments, and all non-href bytes survive.
 */
function medical_normalize_facility_links_html(string $html, string $siteOrigin = 'https://medreview.vn'): string
{
    if (!str_contains($html, 'co-so-y-te-chi-tiet.php')) return $html;
    $result = '';
    $cursor = 0;
    $length = strlen($html);
    while ($cursor < $length) {
        $opening = strpos($html, '<', $cursor);
        if ($opening === false) return $result . substr($html, $cursor);
        $result .= substr($html, $cursor, $opening - $cursor);
        if (substr($html, $opening, 4) === '<!--') {
            $end = strpos($html, '-->', $opening + 4);
            if ($end === false) return $result . substr($html, $opening);
            $cursor = $end + 3;
            $result .= substr($html, $opening, $cursor - $opening);
            continue;
        }
        if (substr($html, $opening, 9) === '<![CDATA[' || substr($html, $opening, 2) === '<?') {
            $terminator = substr($html, $opening, 2) === '<?' ? '?>' : ']]>';
            $end = strpos($html, $terminator, $opening + 2);
            if ($end === false) return $result . substr($html, $opening);
            $cursor = $end + strlen($terminator);
            $result .= substr($html, $opening, $cursor - $opening);
            continue;
        }
        if (preg_match('/\G<(\/?)([a-z][a-z0-9:-]*)(?=[\s\/>])/i', $html, $match, 0, $opening) !== 1) {
            $result .= '<';
            $cursor = $opening + 1;
            continue;
        }
        $quote = '';
        $end = $opening + strlen($match[0]);
        for (; $end < $length; ++$end) {
            $character = $html[$end];
            if ($quote !== '') {
                if ($character === $quote) $quote = '';
            } elseif ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '>') {
                break;
            }
        }
        if ($end >= $length) return $result . substr($html, $opening);
        $cursor = $end + 1;
        $tag = substr($html, $opening, $cursor - $opening);
        $name = strtolower($match[2]);
        if ($match[1] === '' && in_array($name, ['script', 'style', 'textarea', 'title', 'code', 'pre', 'xmp', 'iframe', 'noembed', 'noframes', 'plaintext'], true)) {
            $result .= $tag;
            if ($name === 'plaintext') return $result . substr($html, $cursor);
            if (preg_match('~</' . $name . '(?=[\s/>])[^>]*>~i', $html, $closing, PREG_OFFSET_CAPTURE, $cursor) !== 1) {
                return $result . substr($html, $cursor);
            }
            $skipEnd = $closing[0][1] + strlen($closing[0][0]);
            $result .= substr($html, $cursor, $skipEnd - $cursor);
            $cursor = $skipEnd;
            continue;
        }
        $result .= $match[1] === '' && $name === 'a'
            ? medical_normalize_facility_anchor_tag($tag, $siteOrigin) : $tag;
    }
    return $result;
}
