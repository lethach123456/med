<?php
declare(strict_types=1);

/** Presentation-only GA4 markup. No DB calls or server-side tracking requests.
 * Historical hook names remain compatible with all existing public templates.
 */
function site_tag_manager_enabled(?array $server = null, ?array $query = null): bool
{
    $server ??= $_SERVER;
    $query ??= $_GET;
    $host = strtolower((string) ($server['HTTP_HOST'] ?? ''));
    // Local/staging previews must not pollute the production analytics property.
    if (!preg_match('/\A(?:www\.)?medreview\.vn(?::(?:80|443))?\z/D', $host)) return false;
    foreach (['REQUEST_URI', 'SCRIPT_NAME'] as $key) {
        $path = parse_url((string) ($server[$key] ?? '/'), PHP_URL_PATH);
        if (!is_string($path)) return false;
        $path = strtolower(rawurldecode($path));
        if (preg_match('~\A/(?:en/)?(?:admin|api|builder|scripts|cron)(?:/|\z)~', $path)) return false;
    }
    if (isset($query['edit_template']) || isset($query['front_editor_no_redirect'])) return false;
    return true;
}

/** Render once near the start of each public document's head. */
function site_tag_manager_head(): string
{
    if (!site_tag_manager_enabled() || !empty($GLOBALS['site_tag_manager_head_emitted'])) return '';
    $GLOBALS['site_tag_manager_head_emitted'] = true;
    return <<<'HTML'
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-RS82TMHNPT"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-RS82TMHNPT');
</script>

HTML;
}

/** Compatibility hook: direct GA4 has no GTM noscript iframe in the body. */
function site_tag_manager_body(): string
{
    return '';
}
