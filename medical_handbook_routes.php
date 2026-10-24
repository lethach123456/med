<?php
declare(strict_types=1);

require_once __DIR__ . '/medical_handbook.php';

/** The physical URL is authoritative; query parameters cannot change a rewritten route. */
function handbook_detail_route(string $requestUri): array
{
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : '/';
    $route = ['path' => $path, 'context' => '', 'locale' => 'vi', 'slug' => null];
    if (preg_match('~^/(en/)?cam-nang/([^/]+)/?$~', $path, $matches) === 1) {
        $route['context'] = 'handbook';
        $route['locale'] = ($matches[1] ?? '') === 'en/' ? 'en' : 'vi';
        $route['slug'] = rawurldecode($matches[2]);
    } elseif (preg_match('~^/(blog|news)/([^/]+)/?$~', $path, $matches) === 1) {
        $route['context'] = $matches[1];
        $route['locale'] = $matches[1] === 'news' ? 'en' : 'vi';
        $route['slug'] = rawurldecode($matches[2]);
    } elseif ($path === '/blog-chi-tiet.php') {
        $route['context'] = 'blog';
    }
    return $route;
}

/** Published handbook content has exactly one route, based on its stored language. */
function handbook_public_article_path(array $post): string
{
    if (!in_array((string) ($post['category_slug'] ?? ''), ['blog', 'blog-en'], true)
        || (string) ($post['status'] ?? '') !== 'published'
        || trim((string) ($post['slug'] ?? '')) === ''
        || trim((string) ($post['content'] ?? '')) === '') {
        return '';
    }
    $locale = handbook_content_locale(isset($post['language']) ? (string) $post['language'] : null);
    return in_array($locale, ['vi', 'en'], true)
        ? handbook_view_path($locale, (string) $post['slug']) : '';
}

function handbook_article_redirect_target(array $post, string $requestUri, bool $templatePreview = false): string
{
    if ($templatePreview) return '';
    $canonical = handbook_public_article_path($post);
    $request = handbook_detail_route($requestUri);
    return $canonical !== '' && $request['path'] !== $canonical ? $canonical : '';
}
