<?php
declare(strict_types=1);

/** Presentation-only GTM integration. Never queries the DB or sends visitor data. */
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
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-WQKB3F22');</script>
<!-- End Google Tag Manager -->

HTML;
}

/** Render once immediately after the public document's opening body tag. */
function site_tag_manager_body(): string
{
    if (!site_tag_manager_enabled() || !empty($GLOBALS['site_tag_manager_body_emitted'])) return '';
    $GLOBALS['site_tag_manager_body_emitted'] = true;
    return <<<'HTML'
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WQKB3F22"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

HTML;
}
