<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function admin_is_logged_in(): bool
{
    return isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']);
}

function admin_require_login(): void
{
    if (!admin_is_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function admin_base_path(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
    $script = str_replace('\\', '/', $script);
    $dir = rtrim(dirname($script), '/');
    return $dir === '/' ? '' : $dir;
}

function admin_url(string $path = ''): string
{
    $path = ltrim((string) $path, '/');
    $base = admin_base_path();
    if ($path === '') {
        return $base !== '' ? $base . '/' : '/';
    }
    return ($base !== '' ? $base : '') . '/' . $path;
}

function site_base_path(): string
{
    $adminBase = admin_base_path();
    if ($adminBase === '') {
        return '';
    }
    if (substr($adminBase, -6) === '/admin') {
        return substr($adminBase, 0, -6);
    }
    return '';
}

function site_url(string $path = ''): string
{
    $base = site_base_path();
    $path = (string) $path;
    if ($path === '' || $path === '/') {
        return $base !== '' ? $base . '/' : '/';
    }
    return $base . '/' . ltrim($path, '/');
}

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function slugify(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = mb_strtolower($value, 'UTF-8');
    $value = strtr($value, [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
        'đ' => 'd',
    ]);
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
        $value = $ascii;
    }

    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    $value = trim($value, '-');
    $value = preg_replace('/-+/', '-', $value) ?? '';
    return $value;
}

function unique_slug(PDO $pdo, string $table, string $slug, ?int $excludeId = null): string
{
    $allowed = ['posts', 'categories', 'products', 'product_categories', 'projects', 'project_categories', 'medical_facilities', 'medical_reviews', 'medical_doctors', 'medical_toplists'];
    if (!in_array($table, $allowed, true)) {
        return $slug;
    }

    $maxLen = 191;
    $base = slugify($slug);
    if ($base === '') {
        $base = bin2hex(random_bytes(6));
    }
    if (strlen($base) > $maxLen) {
        $base = substr($base, 0, $maxLen);
        $base = rtrim($base, '-');
    }

    $candidate = $base;
    $i = 2;
    while (true) {
        if ($excludeId !== null) {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE slug = :s AND id != :id LIMIT 1");
            $stmt->execute([':s' => $candidate, ':id' => $excludeId]);
        } else {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE slug = :s LIMIT 1");
            $stmt->execute([':s' => $candidate]);
        }
        $exists = $stmt->fetchColumn();
        if (!$exists) {
            return $candidate;
        }
        $suffix = '-' . $i;
        $cut = $maxLen - strlen($suffix);
        $candidate = substr($base, 0, max(1, $cut)) . $suffix;
        $i++;
        if ($i > 200) {
            return $base . '-' . bin2hex(random_bytes(3));
        }
    }
}

function flash_toast_set(string $type, string $message, ?string $icon = null): void
{
    $_SESSION['flash_toast'] = [
        'type' => $type,
        'message' => $message,
        'icon' => $icon,
    ];
}

function flash_toast_pop(): ?array
{
    if (!isset($_SESSION['flash_toast']) || !is_array($_SESSION['flash_toast'])) {
        return null;
    }
    $toast = $_SESSION['flash_toast'];
    unset($_SESSION['flash_toast']);
    return $toast;
}

function db_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return (bool) $cache[$key];
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
               AND COLUMN_NAME = :c
             LIMIT 1"
        );
        $stmt->execute([':t' => $table, ':c' => $column]);
        $cache[$key] = (bool) $stmt->fetchColumn();
        return (bool) $cache[$key];
    } catch (Throwable $e) {
        $cache[$key] = false;
        return false;
    }
}

function normalize_content_language(?string $value): string
{
    $value = strtolower(trim((string) ($value ?? '')));
    return $value === 'en' ? 'en' : 'vi';
}

function ensure_content_language_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $tables = ['categories', 'posts', 'product_categories', 'products', 'project_categories', 'projects'];
    foreach ($tables as $table) {
        if (db_has_column($pdo, $table, 'language')) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN language VARCHAR(5) NOT NULL DEFAULT 'vi'");
        } catch (Throwable $e) {
        }
    }
}
