<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug !== '') {
    $profile = front_editor_page_find_by_slug($slug);
    $pageKey = is_array($profile) && !empty($profile['page_key']) ? (string) $profile['page_key'] : '';
    if ($pageKey === '') {
        foreach (front_editor_page_catalog() as $candidateKey => $meta) {
            $defaultRoute = trim((string) ($meta['default_route'] ?? ''), '/');
            if ($defaultRoute !== '' && $defaultRoute === $slug) {
                $pageKey = (string) $candidateKey;
                break;
            }
        }
    }
    if ($pageKey !== '') {
        $map = front_editor_allowed_pages();
        $file = $map[$pageKey] ?? '';
        if (is_string($file) && $file !== '' && is_file($file)) {
            unset($_GET['slug']);
            $_GET['front_editor_route_page'] = $pageKey;
            $GLOBALS['site_page_key'] = $pageKey;
            require $file;
            exit;
        }
    }
}

require __DIR__ . '/post.php';
