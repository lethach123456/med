<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$controller = match ($path) {
    '/', '/en' => 'index.php',
    '/ve-chung-toi', '/en/ve-chung-toi' => 've-chung-toi.php',
    '/bac-si', '/en/bac-si' => 'bac-si.php',
    '/co-so-y-te', '/en/co-so-y-te' => 'co-so-y-te.php',
    default => null,
};
if ($controller === null) { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/' . $controller;
