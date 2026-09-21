<?php
declare(strict_types=1);

/**
 * Loads machine-specific database details when present.
 *
 * Environment variables always take precedence, so the deployment can still
 * use its host-provided secret manager. `config/database.runtime.php` is for
 * this installed instance only and is intentionally excluded from Git.
 */
function db_runtime_config(): array
{
    static $loaded = false;
    static $config = [];

    if ($loaded) {
        return $config;
    }
    $loaded = true;

    $file = __DIR__ . '/config/database.runtime.php';
    if (!is_file($file)) {
        return $config;
    }

    $loadedConfig = require $file;
    if (!is_array($loadedConfig)) {
        return $config;
    }

    foreach (['host', 'name', 'user', 'pass', 'charset'] as $key) {
        if (array_key_exists($key, $loadedConfig) && is_string($loadedConfig[$key])) {
            $config[$key] = $loadedConfig[$key];
        }
    }

    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $runtime = db_runtime_config();
    $host = getenv('DB_HOST') ?: ($runtime['host'] ?? 'localhost');
    $name = getenv('DB_NAME') ?: ($runtime['name'] ?? 'top2');
    $user = getenv('DB_USER') ?: ($runtime['user'] ?? 'root');
    $pass = getenv('DB_PASS') ?: ($runtime['pass'] ?? 'ServBay.dev');
    $charset = getenv('DB_CHARSET') ?: ($runtime['charset'] ?? 'utf8mb4');

    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=' . $charset;

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

if (!function_exists('slugify')) {
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
}

/** Canonical public paths for medical directory content. */
function medical_public_facility_path(string $slug = ''): string
{
    $slug = trim($slug, "/ \t\n\r\0\x0B");
    return $slug === '' ? '/co-so-y-te' : '/co-so-y-te/' . rawurlencode($slug);
}

function medical_public_toplist_path(string $slug = ''): string
{
    $slug = trim($slug, "/ \t\n\r\0\x0B");
    return $slug === '' ? '/toplist' : '/toplist/' . rawurlencode($slug);
}

/**
 * Moves a legacy .php URL to its public route while retaining allowed filters.
 * This only runs for a direct legacy request; internally rewritten pretty URLs
 * keep their original REQUEST_URI and therefore never loop.
 */
function medical_redirect_legacy_path(string $legacyPath, string $targetPath, array $dropQueryKeys = []): void
{
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (!is_string($requestPath) || $requestPath !== $legacyPath) {
        return;
    }

    $query = is_array($_GET) ? $_GET : [];
    foreach ($dropQueryKeys as $key) {
        unset($query[$key]);
    }
    $target = $targetPath;
    $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    if ($queryString !== '') {
        $target .= '?' . $queryString;
    }
    header('Location: ' . $target, true, 301);
    exit;
}

function site_setting(string $key, string $default = ''): string
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        if (is_array($row) && array_key_exists('setting_value', $row)) {
            return (string) $row['setting_value'];
        }
        return $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function site_setting_set(string $key, string $value): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([':k' => $key, ':v' => $value]);
}

function front_editor_image_slots_key(string $pageKey): string
{
    $pageKey = preg_replace('/[^a-z0-9_-]+/i', '-', trim($pageKey)) ?: 'page';
    return 'front_editor_image_slots_' . strtolower($pageKey);
}

function front_editor_image_slots(string $pageKey): array
{
    $raw = trim(site_setting(front_editor_image_slots_key($pageKey), ''));
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $slot => $url) {
        $slot = trim((string) $slot);
        $url = trim((string) $url);
        if ($slot === '' || $url === '') {
            continue;
        }
        $out[$slot] = $url;
    }
    return $out;
}

function front_editor_image_slot(string $pageKey, string $slotKey, string $defaultUrl = ''): string
{
    $slotKey = trim($slotKey);
    if ($slotKey === '') {
        return $defaultUrl;
    }
    $slots = front_editor_image_slots($pageKey);
    return $slots[$slotKey] ?? $defaultUrl;
}

function front_editor_image_slot_set(string $pageKey, string $slotKey, string $url): array
{
    $slotKey = trim($slotKey);
    $url = trim($url);
    if ($pageKey === '' || $slotKey === '' || $url === '') {
        return ['ok' => false, 'message' => 'Thiếu dữ liệu ảnh.'];
    }
    if (!front_editor_is_editable_media_url($url)) {
        return ['ok' => false, 'message' => 'URL ảnh không hợp lệ.'];
    }
    $slots = front_editor_image_slots($pageKey);
    $slots[$slotKey] = $url;
    try {
        site_setting_set(
            front_editor_image_slots_key($pageKey),
            json_encode($slots, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'
        );
        return ['ok' => true, 'slot' => $slotKey, 'url' => $url];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function front_editor_text_slots_key(string $pageKey): string
{
    $pageKey = preg_replace('/[^a-z0-9_-]+/i', '-', trim($pageKey)) ?: 'page';
    return 'front_editor_text_slots_' . strtolower($pageKey);
}

function front_editor_text_slots(string $pageKey): array
{
    $raw = trim(site_setting(front_editor_text_slots_key($pageKey), ''));
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $slot => $text) {
        $slot = trim((string) $slot);
        if ($slot === '') {
            continue;
        }
        $out[$slot] = trim((string) $text);
    }
    return $out;
}

function front_editor_text_slot(string $pageKey, string $slotKey, string $defaultText = ''): string
{
    $slotKey = trim($slotKey);
    if ($slotKey === '') {
        return $defaultText;
    }
    $slots = front_editor_text_slots($pageKey);
    return array_key_exists($slotKey, $slots) ? (string) $slots[$slotKey] : $defaultText;
}

function front_editor_sanitize_rich_text_style(string $style): string
{
    $style = trim($style);
    if ($style === '') {
        return '';
    }
    $allowed = [
        'color',
        'font-size',
        'font-weight',
        'font-style',
        'text-decoration',
        'text-align',
        'display',
        'line-height',
        'letter-spacing',
        'text-transform',
    ];
    $safe = [];
    foreach (preg_split('/\s*;\s*/', $style) ?: [] as $chunk) {
        if ($chunk === '' || strpos($chunk, ':') === false) {
            continue;
        }
        [$prop, $value] = array_map('trim', explode(':', $chunk, 2));
        $prop = strtolower($prop);
        if ($prop === '' || $value === '' || !in_array($prop, $allowed, true)) {
            continue;
        }
        if (preg_match('/(?:expression|javascript:|url\s*\(|@import|behavior:|-moz-binding)/i', $value)) {
            continue;
        }
        if ($prop === 'display' && !in_array(strtolower($value), ['inline', 'inline-block', 'block'], true)) {
            continue;
        }
        if ($prop === 'text-align' && !in_array(strtolower($value), ['left', 'center', 'right', 'justify'], true)) {
            continue;
        }
        if ($prop === 'font-weight' && !preg_match('/^(normal|bold|[1-9]00)$/i', $value)) {
            continue;
        }
        if ($prop === 'font-style' && !preg_match('/^(normal|italic|oblique)$/i', $value)) {
            continue;
        }
        if ($prop === 'text-decoration' && !preg_match('/^(none|underline|line-through|overline)(\s+(underline|line-through|overline))*$/i', $value)) {
            continue;
        }
        if ($prop === 'font-size' && !preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|%)$/i', $value)) {
            continue;
        }
        if ($prop === 'line-height' && !preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|%)?$/i', $value)) {
            continue;
        }
        if ($prop === 'letter-spacing' && !preg_match('/^-?\d+(?:\.\d+)?(?:px|rem|em)$/i', $value)) {
            continue;
        }
        if ($prop === 'color') {
            $val = strtolower($value);
            if (!preg_match('/^(#[0-9a-f]{3,8}|rgba?\([0-9\s.,%]+\)|hsla?\([0-9\s.,%]+\)|[a-z]+)$/i', $val)) {
                continue;
            }
        }
        if ($prop === 'text-transform' && !preg_match('/^(none|uppercase|lowercase|capitalize)$/i', $value)) {
            continue;
        }
        $safe[] = $prop . ':' . $value;
    }
    return implode(';', $safe);
}

function front_editor_sanitize_rich_text(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (strpos($html, '<') === false) {
        return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
    }
    if (!class_exists('DOMDocument')) {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }

    $allowedTags = ['span', 'strong', 'b', 'em', 'i', 'u', 's', 'br', 'small', 'sub', 'sup', 'mark'];
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapperId = 'fe-rich-root';
    $loaded = $dom->loadHTML(
        '<?xml encoding="utf-8" ?><div id="' . $wrapperId . '">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    if ($previous !== null) {
        libxml_use_internal_errors($previous);
    }
    if (!$loaded) {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }

    $rootList = $dom->getElementsByTagName('div');
    $root = null;
    foreach ($rootList as $candidate) {
        if ($candidate instanceof DOMElement && $candidate->getAttribute('id') === $wrapperId) {
            $root = $candidate;
            break;
        }
    }
    if (!$root instanceof DOMElement) {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }

    $sanitizeNode = static function (DOMNode $node) use (&$sanitizeNode, $allowedTags): void {
        if (!$node->hasChildNodes()) {
            return;
        }
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if (!$child instanceof DOMNode) {
                continue;
            }
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE || !$child instanceof DOMElement) {
                $node->removeChild($child);
                continue;
            }
            $tag = strtolower($child->tagName);
            if (!in_array($tag, $allowedTags, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            $rawStyle = (string) $child->getAttribute('style');
            $attrsToRemove = [];
            foreach ($child->attributes as $attr) {
                $attrsToRemove[] = $attr->nodeName;
            }
            foreach ($attrsToRemove as $attrName) {
                $child->removeAttribute($attrName);
            }
            $style = front_editor_sanitize_rich_text_style($rawStyle);
            if ($style !== '') {
                $child->setAttribute('style', $style);
            }
            $sanitizeNode($child);
        }
    };
    $sanitizeNode($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return trim($out);
}

function front_editor_text_slot_html(string $pageKey, string $slotKey, string $defaultText = ''): string
{
    $value = front_editor_text_slot($pageKey, $slotKey, $defaultText);
    return front_editor_sanitize_rich_text((string) $value);
}

function front_editor_text_slot_set(string $pageKey, string $slotKey, string $text, string $html = ''): array
{
    $slotKey = trim($slotKey);
    $text = trim($text);
    if ($pageKey === '' || $slotKey === '') {
        return ['ok' => false, 'message' => 'Thiếu dữ liệu text.'];
    }
    $slots = front_editor_text_slots($pageKey);
    $slots[$slotKey] = $html !== '' ? front_editor_sanitize_rich_text($html) : $text;
    try {
        site_setting_set(
            front_editor_text_slots_key($pageKey),
            json_encode($slots, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'
        );
        return ['ok' => true, 'slot' => $slotKey, 'text' => $text, 'html' => (string) $slots[$slotKey]];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function front_editor_text_slot_delete(string $pageKey, string $slotKey): array
{
    $slotKey = trim($slotKey);
    if ($pageKey === '' || $slotKey === '') {
        return ['ok' => false, 'message' => 'Thiếu dữ liệu text.'];
    }
    $slots = front_editor_text_slots($pageKey);
    $slots[$slotKey] = '';
    try {
        site_setting_set(
            front_editor_text_slots_key($pageKey),
            json_encode($slots, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'
        );
        return ['ok' => true, 'slot' => $slotKey, 'text' => '', 'html' => ''];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function site_hotline(string $default = '0988 123 456'): string
{
    return site_setting('site_hotline', $default);
}

function site_email(string $default = 'info@example.com'): string
{
    return site_setting('site_email', $default);
}

function site_address(string $default = 'Địa chỉ đang cập nhật'): string
{
    return site_setting('site_address', $default);
}

function site_facebook(string $default = ''): string
{
    return site_setting('site_facebook', $default);
}

function site_zalo(string $default = ''): string
{
    return site_setting('site_zalo', $default);
}

function site_instagram(string $default = ''): string
{
    return site_setting('site_instagram', $default);
}

function track_visit(?string $path = null): void
{
    try {
        $p = $path ?? (isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/');
        if ($p === '' || $p[0] !== '/') $p = '/' . $p;
        // Không track admin
        if (strpos($p, '/admin') === 0) {
            return;
        }
        $ref = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : null;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null;
        // Giới hạn độ dài
        $ref = $ref !== null ? substr($ref, 0, 255) : null;
        $ip = $ip !== null ? substr($ip, 0, 45) : null;
        $ua = $ua !== null ? substr($ua, 0, 255) : null;
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO visits (path, referrer, ip, user_agent) VALUES (:p, :r, :i, :u)");
        $stmt->execute([':p' => $p, ':r' => $ref, ':i' => $ip, ':u' => $ua]);
    } catch (Throwable $e) {
        // Bỏ qua lỗi tracking
    }
}

function visits_daily_counts(int $days = 14): array
{
    $days = max(1, min(180, $days));
    $labels = [];
    $today = new DateTimeImmutable('today');
    for ($i = $days - 1; $i >= 0; $i--) {
        $labels[] = $today->sub(new DateInterval("P{$i}D"))->format('Y-m-d');
    }
    $map = array_fill_keys($labels, 0);
    try {
        $pdo = db();
        $start = $today->sub(new DateInterval("P" . ($days - 1) . "D"))->format('Y-m-d');
        $st = $pdo->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM visits WHERE created_at >= :start GROUP BY DATE(created_at) ORDER BY d ASC");
        $st->execute([':start' => $start . ' 00:00:00']);
        foreach ($st->fetchAll() as $r) {
            $d = (string) ($r['d'] ?? '');
            if (isset($map[$d])) {
                $map[$d] = (int) ($r['c'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
    return ['labels' => array_values(array_keys($map)), 'series' => array_values($map)];
}

function admin_front_session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $name = session_name();
    if (isset($_COOKIE[$name]) && is_string($_COOKIE[$name]) && $_COOKIE[$name] !== '') {
        @session_start();
    }
}

function admin_front_is_logged_in(): bool
{
    admin_front_session_boot();
    return isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']);
}

function front_editor_allowed_pages(): array
{
    return [
        'home' => __DIR__ . '/Tem/home.php',
        'products' => __DIR__ . '/san-pham.php',
        'projects' => __DIR__ . '/du-an.php',
        'blog' => __DIR__ . '/blog.php',
        'contact' => __DIR__ . '/lien-he.php',
        'about' => __DIR__ . '/ve-chung-toi.php',
        'dich-vu' => __DIR__ . '/dich-vu.php',
        'services-en' => __DIR__ . '/services-en.php',
        'about-en' => __DIR__ . '/about-us.php',
        'blog-en' => __DIR__ . '/news.php',
        'contact-en' => __DIR__ . '/contact-us.php',
        'rang-su-dang-hot-girl-da-nang' => __DIR__ . '/rang-su-dang-hot-girl-da-nang.php',
        'porcelain-crowns-da-nang' => __DIR__ . '/porcelain-crowns-da-nang.php',
    ];
}

function front_editor_page_catalog(): array
{
    return [
        'home' => [
            'title' => 'Trang chủ',
            'default_route' => '/',
            'aliases' => ['/'],
            'supports_slug' => false,
        ],
        'co-so-y-te' => [
            'title' => 'Danh sách cơ sở y tế',
            'default_route' => '/co-so-y-te',
            'aliases' => ['/co-so-y-te', '/co-so-y-te.php'],
            'supports_slug' => false,
        ],
        'bac-si' => [
            'title' => 'Danh sách bác sĩ',
            'default_route' => '/bac-si.php',
            'aliases' => ['/bac-si.php'],
            'supports_slug' => false,
        ],
        'review' => [
            'title' => 'Danh sách review',
            'default_route' => '/review.php',
            'aliases' => ['/review.php'],
            'supports_slug' => false,
        ],
        'danh-muc-y-te' => [
            'title' => 'Danh mục y tế',
            'default_route' => '/danh-muc-y-te.php',
            'aliases' => ['/danh-muc-y-te.php'],
            'supports_slug' => false,
        ],
        'toplist' => [
            'title' => 'Danh sách Toplist',
            'default_route' => '/toplist',
            'aliases' => ['/toplist', '/toplist.php'],
            'supports_slug' => false,
        ],
        'products' => [
            'title' => 'Trang sản phẩm',
            'default_route' => '/san-pham',
            'aliases' => ['/san-pham', '/san-pham.php'],
            'supports_slug' => true,
        ],
        'projects' => [
            'title' => 'Trang dự án',
            'default_route' => '/du-an',
            'aliases' => ['/du-an', '/du-an.php'],
            'supports_slug' => true,
        ],
        'blog' => [
            'title' => 'Trang blog',
            'default_route' => '/blog',
            'aliases' => ['/blog', '/blog.php'],
            'supports_slug' => true,
        ],
        'contact' => [
            'title' => 'Liên hệ',
            'default_route' => '/lien-he',
            'aliases' => ['/lien-he', '/lien-he.php'],
            'supports_slug' => true,
        ],
        'about' => [
            'title' => 'Về chúng tôi',
            'default_route' => '/ve-chung-toi',
            'aliases' => ['/ve-chung-toi', '/ve-chung-toi.php'],
            'supports_slug' => true,
        ],
        'dich-vu' => [
            'title' => 'Dịch vụ',
            'default_route' => '/dich-vu',
            'aliases' => ['/dich-vu', '/dich-vu.php'],
            'supports_slug' => true,
        ],
        'services-en' => [
            'title' => 'Services',
            'default_route' => '/services',
            'aliases' => ['/services', '/services-en.php'],
            'supports_slug' => true,
        ],
        'rang-su-dang-hot-girl-da-nang' => [
            'title' => 'Landing page răng sứ dáng hot girl',
            'default_route' => '/rang-su-dang-hot-girl-da-nang',
            'aliases' => ['/rang-su-dang-hot-girl-da-nang', '/rang-su-dang-hot-girl-da-nang.php'],
            'supports_slug' => true,
        ],
        'about-en' => [
            'title' => 'About us',
            'default_route' => '/about-us',
            'aliases' => ['/about-us', '/about-us.php'],
            'supports_slug' => true,
        ],
        'blog-en' => [
            'title' => 'News',
            'default_route' => '/news',
            'aliases' => ['/news', '/news.php'],
            'supports_slug' => true,
        ],
        'contact-en' => [
            'title' => 'Contact',
            'default_route' => '/contact-us',
            'aliases' => ['/contact-us', '/contact-us.php'],
            'supports_slug' => true,
        ],
        'porcelain-crowns-da-nang' => [
            'title' => 'English landing page for porcelain crowns',
            'default_route' => '/porcelain-crowns-da-nang',
            'aliases' => ['/porcelain-crowns-da-nang', '/porcelain-crowns-da-nang.php'],
            'supports_slug' => true,
        ],
    ];
}

function ensure_front_editor_page_profiles_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS front_editor_page_profiles (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                page_key VARCHAR(120) NOT NULL,
                slug VARCHAR(191) NULL,
                seo_title VARCHAR(160) NULL,
                seo_description VARCHAR(300) NULL,
                seo_keywords VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_front_editor_page_key (page_key),
                UNIQUE KEY uniq_front_editor_page_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
    }
}

function front_editor_page_reserved_slugs(): array
{
    $catalog = front_editor_page_catalog();
    $reserved = [
        'admin', 'builder', 'uploads', 'api', 'index.php', 'post.php',
        'blog.php', 'du-an.php', 'san-pham.php', 'dich-vu.php',
        've-chung-toi.php', 'lien-he.php',
    ];
    foreach ($catalog as $meta) {
        $route = trim((string) ($meta['default_route'] ?? ''));
        if ($route !== '' && $route !== '/') {
            $reserved[] = trim($route, '/');
        }
    }
    return array_values(array_unique(array_filter($reserved, static fn ($v) => $v !== '')));
}

function front_editor_page_profile_row(string $pageKey): ?array
{
    $catalog = front_editor_page_catalog();
    if (!isset($catalog[$pageKey])) {
        return null;
    }
    try {
        $pdo = db();
        ensure_front_editor_page_profiles_table($pdo);
        $stmt = $pdo->prepare(
            "SELECT page_key, slug, seo_title, seo_description, seo_keywords, updated_at
             FROM front_editor_page_profiles
             WHERE page_key = :page_key
             LIMIT 1"
        );
        $stmt->execute([':page_key' => $pageKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}

function front_editor_page_profile(string $pageKey): array
{
    $catalog = front_editor_page_catalog();
    $meta = $catalog[$pageKey] ?? [];
    $row = front_editor_page_profile_row($pageKey);
    $supportsSlug = !empty($meta['supports_slug']);
    $customSlug = trim((string) (($row['slug'] ?? '')));
    return [
        'page_key' => $pageKey,
        'title' => (string) ($meta['title'] ?? $pageKey),
        'default_route' => (string) ($meta['default_route'] ?? '/'),
        'aliases' => is_array($meta['aliases'] ?? null) ? $meta['aliases'] : [],
        'supports_slug' => $supportsSlug,
        'slug' => ($supportsSlug && $customSlug !== '') ? $customSlug : '',
        'seo_title' => trim((string) ($row['seo_title'] ?? '')),
        'seo_description' => trim((string) ($row['seo_description'] ?? '')),
        'seo_keywords' => trim((string) ($row['seo_keywords'] ?? '')),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function front_editor_page_public_path(string $pageKey): string
{
    $profile = front_editor_page_profile($pageKey);
    $slug = trim((string) ($profile['slug'] ?? ''));
    if (!empty($profile['supports_slug']) && $slug !== '') {
        return '/' . rawurlencode($slug);
    }
    $defaultRoute = trim((string) ($profile['default_route'] ?? '/'));
    return $defaultRoute !== '' ? $defaultRoute : '/';
}

function site_page_script_map(): array
{
    return [
        'index.php' => 'home',
        'san-pham.php' => 'products',
        'du-an.php' => 'projects',
        'blog.php' => 'blog',
        'lien-he.php' => 'contact',
        've-chung-toi.php' => 'about',
        'dich-vu.php' => 'dich-vu',
        'rang-su-dang-hot-girl-da-nang.php' => 'rang-su-dang-hot-girl-da-nang',
        'services-en.php' => 'services-en',
        'about-us.php' => 'about-en',
        'news.php' => 'blog-en',
        'contact-us.php' => 'contact-en',
        'porcelain-crowns-da-nang.php' => 'porcelain-crowns-da-nang',
    ];
}

function site_current_page_key(): string
{
    $pageKey = trim((string) ($GLOBALS['site_page_key'] ?? ''));
    if ($pageKey !== '') {
        return $pageKey;
    }
    $routePageKey = trim((string) ($_GET['front_editor_route_page'] ?? ''));
    if ($routePageKey !== '') {
        return $routePageKey;
    }
    $scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $map = site_page_script_map();
    return (string) ($map[$scriptName] ?? '');
}

function site_normalize_locale(?string $locale, string $default = 'vi'): string
{
    $locale = strtolower(trim((string) $locale));
    if ($locale === '') {
        return $default;
    }
    if (str_starts_with($locale, 'en')) {
        return 'en';
    }
    if (str_starts_with($locale, 'vi')) {
        return 'vi';
    }
    return $default;
}

function site_default_locale(): string
{
    return 'vi';
}

function site_page_locale_map(): array
{
    return [
        'services-en' => 'en',
        'about-en' => 'en',
        'blog-en' => 'en',
        'contact-en' => 'en',
        'porcelain-crowns-da-nang' => 'en',
        'home' => 'vi',
        'products' => 'vi',
        'projects' => 'vi',
        'blog' => 'vi',
        'contact' => 'vi',
        'about' => 'vi',
        'dich-vu' => 'vi',
        'rang-su-dang-hot-girl-da-nang' => 'vi',
    ];
}

function site_request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '/';
    }
    return $path;
}

function site_locale_from_path(?string $path = null): ?string
{
    $path = trim((string) ($path ?? site_request_path()));
    if ($path === '') {
        return null;
    }
    $englishPrefixes = [
        '/services',
        '/about-us',
        '/news',
        '/contact-us',
        '/porcelain-crowns-da-nang',
        '/services-en.php',
        '/about-us.php',
        '/news.php',
        '/contact-us.php',
    ];
    foreach ($englishPrefixes as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            return 'en';
        }
    }
    $vietnamesePrefixes = [
        '/',
        '/dich-vu',
        '/ve-chung-toi',
        '/blog',
        '/lien-he',
        '/rang-su-dang-hot-girl-da-nang',
        '/index.php',
        '/dich-vu.php',
        '/ve-chung-toi.php',
        '/blog.php',
        '/lien-he.php',
    ];
    foreach ($vietnamesePrefixes as $prefix) {
        if ($prefix === '/') {
            if ($path === '/') {
                return 'vi';
            }
            continue;
        }
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            return 'vi';
        }
    }
    return null;
}

function site_browser_preferred_locale(): string
{
    $header = trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    if ($header === '') {
        return site_default_locale();
    }
    $tokens = preg_split('/,/', $header);
    if (!is_array($tokens)) {
        return site_default_locale();
    }
    foreach ($tokens as $token) {
        $candidate = site_normalize_locale($token, '');
        if ($candidate === 'vi') {
            return $candidate;
        }
    }
    return site_default_locale();
}

function site_detect_locale(?string $pageKey = null): string
{
    $pageKey = trim((string) ($pageKey ?? site_current_page_key()));
    if ($pageKey !== '') {
        $map = site_page_locale_map();
        $matched = trim((string) ($map[$pageKey] ?? ''));
        if ($matched !== '') {
            return site_normalize_locale($matched);
        }
    }
    $pathLocale = site_locale_from_path();
    if ($pathLocale !== null) {
        return $pathLocale;
    }
    $queryLocale = trim((string) ($_GET['lang'] ?? ''));
    if ($queryLocale !== '') {
        return site_normalize_locale($queryLocale, site_default_locale());
    }
    return site_browser_preferred_locale();
}

function site_page_locale(?string $pageKey = null): string
{
    return site_detect_locale($pageKey);
}

function site_language_pair_map(): array
{
    return [
        'dich-vu' => 'services-en',
        'services-en' => 'dich-vu',
        'about' => 'about-en',
        'about-en' => 'about',
        'blog' => 'blog-en',
        'blog-en' => 'blog',
        'contact' => 'contact-en',
        'contact-en' => 'contact',
        'rang-su-dang-hot-girl-da-nang' => 'porcelain-crowns-da-nang',
        'porcelain-crowns-da-nang' => 'rang-su-dang-hot-girl-da-nang',
    ];
}

function site_page_language_counterpart(?string $pageKey = null): ?string
{
    $pageKey = trim((string) ($pageKey ?? site_current_page_key()));
    if ($pageKey === '') {
        return null;
    }
    $pairs = site_language_pair_map();
    $target = trim((string) ($pairs[$pageKey] ?? ''));
    return $target !== '' ? $target : null;
}

function site_language_switch_links(?string $pageKey = null): array
{
    $pageKey = trim((string) ($pageKey ?? site_current_page_key()));
    $locale = site_page_locale($pageKey);
    $counterpart = site_page_language_counterpart($pageKey);
    $viPath = '/';
    $enPath = front_editor_page_public_path('services-en');
    if ($pageKey !== '' && $locale === 'vi') {
        $viPath = front_editor_page_public_path($pageKey);
    } elseif ($counterpart !== null) {
        $viPath = front_editor_page_public_path($counterpart);
    }
    if ($pageKey !== '' && $locale === 'en') {
        $enPath = front_editor_page_public_path($pageKey);
    } elseif ($counterpart !== null) {
        $enPath = front_editor_page_public_path($counterpart);
    }
    return [
        'current' => $locale,
        'vi' => $viPath,
        'en' => $enPath,
    ];
}

function site_navigation_links(?string $locale = null): array
{
    $locale = ($locale === 'en') ? 'en' : site_page_locale();
    $serviceChildren = site_service_menu_links($locale);
    if ($locale === 'en') {
        return [
            ['key' => 'services-en-home', 'label' => 'Home', 'href' => front_editor_page_public_path('services-en')],
            ['key' => 'services-en', 'label' => 'Services', 'href' => front_editor_page_public_path('services-en') . '#services', 'children' => $serviceChildren],
            ['key' => 'about-en', 'label' => 'About Us', 'href' => front_editor_page_public_path('about-en')],
            ['key' => 'blog-en', 'label' => 'News', 'href' => front_editor_page_public_path('blog-en')],
            ['key' => 'contact-en', 'label' => 'Contact', 'href' => front_editor_page_public_path('contact-en')],
        ];
    }
    return [
        ['key' => 'home', 'label' => 'Trang chủ', 'href' => front_editor_page_public_path('home')],
        ['key' => 'dich-vu', 'label' => 'Dịch vụ', 'href' => front_editor_page_public_path('dich-vu'), 'children' => $serviceChildren],
        ['key' => 'about', 'label' => 'Về chúng tôi', 'href' => front_editor_page_public_path('about')],
        ['key' => 'blog', 'label' => 'Tin tức', 'href' => front_editor_page_public_path('blog')],
        ['key' => 'contact', 'label' => 'Liên hệ', 'href' => front_editor_page_public_path('contact')],
    ];
}

function site_service_menu_links(?string $locale = null): array
{
    $locale = ($locale === 'en') ? 'en' : site_page_locale();
    $categorySlugGroups = $locale === 'en'
        ? [['dich-vu-en'], ['dich-vu']]
        : [['dich-vu']];

    try {
        $pdo = db();
        foreach ($categorySlugGroups as $group) {
            $group = array_values(array_filter(array_map('trim', $group), static fn ($slug) => $slug !== ''));
            if ($group === []) {
                continue;
            }

            $conditions = [];
            $params = [];
            foreach ($group as $index => $categorySlug) {
                $placeholder = ':category_slug_' . $index;
                $conditions[] = 'c.slug = ' . $placeholder;
                $params[$placeholder] = $categorySlug;
            }

            $stmt = $pdo->prepare(
                "SELECT p.id, p.title, p.slug
                 FROM posts p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.status = 'published'
                   AND TRIM(COALESCE(p.slug, '')) <> ''
                   AND (" . implode(' OR ', $conditions) . ")
                 ORDER BY p.updated_at DESC, p.id DESC"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows) || $rows === []) {
                continue;
            }

            $items = [];
            $seenSlugs = [];
            foreach ($rows as $row) {
                $slug = trim((string) ($row['slug'] ?? ''));
                $label = trim((string) ($row['title'] ?? ''));
                if ($slug === '' || $label === '' || isset($seenSlugs[$slug])) {
                    continue;
                }
                $seenSlugs[$slug] = true;
                $items[] = [
                    'key' => 'service-post-' . (int) ($row['id'] ?? 0),
                    'label' => $label,
                    'href' => '/' . rawurlencode($slug),
                ];
            }

            if ($items !== []) {
                return $items;
            }
        }
    } catch (Throwable $e) {
        return [];
    }

    return [];
}

function front_editor_page_seo(string $pageKey, array $defaults = []): array
{
    $profile = front_editor_page_profile($pageKey);
    return [
        'title' => trim((string) ($profile['seo_title'] ?? '')) !== '' ? (string) $profile['seo_title'] : (string) ($defaults['title'] ?? ''),
        'description' => trim((string) ($profile['seo_description'] ?? '')) !== '' ? (string) $profile['seo_description'] : (string) ($defaults['description'] ?? ''),
        'keywords' => trim((string) ($profile['seo_keywords'] ?? '')),
        'canonical_path' => front_editor_page_public_path($pageKey),
        'profile' => $profile,
    ];
}

function front_editor_page_maybe_redirect(string $pageKey): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        return;
    }
    if (isset($_GET['edit_template']) || (isset($_GET['front_editor_no_redirect']) && (string) $_GET['front_editor_no_redirect'] === '1')) {
        return;
    }
    $profile = front_editor_page_profile($pageKey);
    if (empty($profile['supports_slug']) || trim((string) ($profile['slug'] ?? '')) === '') {
        return;
    }
    $canonicalPath = front_editor_page_public_path($pageKey);
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';
    $aliases = is_array($profile['aliases'] ?? null) ? $profile['aliases'] : [];
    $aliases[] = $canonicalPath;
    $aliases = array_values(array_unique($aliases));
    if (!in_array($requestPath, $aliases, true) || $requestPath === $canonicalPath) {
        return;
    }
    $query = $_GET;
    unset($query['slug']);
    $target = $canonicalPath;
    if (count($query) > 0) {
        $target .= '?' . http_build_query($query);
    }
    header('Location: ' . $target, true, 301);
    exit;
}

function front_editor_page_find_by_slug(string $slug): ?array
{
    $slug = trim(slugify($slug));
    if ($slug === '') {
        return null;
    }
    try {
        $pdo = db();
        ensure_front_editor_page_profiles_table($pdo);
        $stmt = $pdo->prepare(
            "SELECT page_key, slug, seo_title, seo_description, seo_keywords, updated_at
             FROM front_editor_page_profiles
             WHERE slug = :slug
             LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $pageKey = (string) ($row['page_key'] ?? '');
        $catalog = front_editor_page_catalog();
        if (!isset($catalog[$pageKey])) {
            return null;
        }
        return front_editor_page_profile($pageKey);
    } catch (Throwable $e) {
        return null;
    }
}

function front_editor_post_page_info(string $pageKey): ?array
{
    if (!preg_match('/^post:(\d+)$/', $pageKey, $m)) {
        return null;
    }
    $postId = (int) ($m[1] ?? 0);
    if ($postId <= 0) {
        return null;
    }
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, slug, content, title, status, template FROM posts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $postId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'content' => (string) ($row['content'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'template' => (int) ($row['template'] ?? 0),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function post_template_related_slugs(string $slug): array
{
    $slug = trim($slug);
    if ($slug === '') {
        return [];
    }
    $base = preg_replace('/-en$/', '', $slug) ?? $slug;
    $candidates = [$slug];
    if ($base !== '') {
        $candidates[] = $base;
        $candidates[] = $base . '-en';
    }
    $out = [];
    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '' || in_array($candidate, $out, true)) {
            continue;
        }
        $out[] = $candidate;
    }
    return $out;
}

function post_template_legacy_file(string $slug): ?string
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $base = preg_replace('/-en$/', '', $slug) ?? $slug;
    $map = [
        've-chung-toi' => __DIR__ . '/ve-chung-toi.php',
        'dich-vu' => __DIR__ . '/dich-vu.php',
        'lien-he' => __DIR__ . '/lien-he.php',
    ];
    $file = $map[$base] ?? null;
    if (!is_string($file) || $file === '' || !is_file($file)) {
        return null;
    }
    return $file;
}

function post_template_default_content(array $post): string
{
    return '<section class="template-blank-canvas" style="position:relative;width:100vw;max-width:100vw;min-height:78vh;margin:0 calc(50% - 50vw);padding:0;background:transparent;">'
        . '<span style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">Blank template canvas block for right click editing</span>'
        . '</section>';
}

function post_template_seed_content(array $post): array
{
    $postId = (int) ($post['id'] ?? 0);
    $slug = trim((string) ($post['slug'] ?? ''));
    $template = (int) ($post['template'] ?? 0) === 1;
    $content = (string) ($post['content'] ?? '');
    if ($postId <= 0 || !$template || trim($content) !== '') {
        return $post;
    }

    $seedContent = '';
    try {
        $pdo = db();
        $relatedSlugs = post_template_related_slugs($slug);
        foreach ($relatedSlugs as $candidateSlug) {
            if ($candidateSlug === $slug) {
                continue;
            }
            $stmt = $pdo->prepare(
                "SELECT content
                 FROM posts
                 WHERE slug = :slug AND id <> :id AND template = 1
                 ORDER BY id ASC
                 LIMIT 1"
            );
            $stmt->execute([
                ':slug' => $candidateSlug,
                ':id' => $postId,
            ]);
            $candidateContent = trim((string) $stmt->fetchColumn());
            if ($candidateContent !== '') {
                $seedContent = $candidateContent;
                break;
            }
        }

        if ($seedContent === '') {
            $legacyFile = post_template_legacy_file($slug);
            if ($legacyFile !== null) {
                $raw = file_get_contents($legacyFile);
                if (is_string($raw) && trim($raw) !== '') {
                    $seedContent = $raw;
                }
            }
        }

        if ($seedContent === '') {
            $seedContent = post_template_default_content($post);
        }

        if ($seedContent !== '') {
            $stmt = $pdo->prepare(
                "UPDATE posts
                 SET content = :content
                 WHERE id = :id AND (content IS NULL OR TRIM(content) = '')"
            );
            $stmt->execute([
                ':content' => $seedContent,
                ':id' => $postId,
            ]);
            $post['content'] = $seedContent;
        }
    } catch (Throwable $e) {
        return $post;
    }

    return $post;
}

function front_editor_source_read(string $pageKey): ?array
{
    $postInfo = front_editor_post_page_info($pageKey);
    if ($postInfo) {
        $postInfo = post_template_seed_content($postInfo);
        return [
            'type' => 'post',
            'post_id' => (int) ($postInfo['id'] ?? 0),
            'slug' => (string) ($postInfo['slug'] ?? ''),
            'content' => (string) ($postInfo['content'] ?? ''),
        ];
    }

    $map = front_editor_allowed_pages();
    if (!isset($map[$pageKey])) {
        return null;
    }
    $file = $map[$pageKey];
    if (!is_file($file)) {
        return null;
    }
    $content = file_get_contents($file);
    if (!is_string($content)) {
        return null;
    }
    return [
        'type' => 'file',
        'file' => $file,
        'content' => $content,
    ];
}

function front_editor_source_write(string $pageKey, string $content): array
{
    $postInfo = front_editor_post_page_info($pageKey);
    if ($postInfo) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('UPDATE posts SET content = :content WHERE id = :id');
            $stmt->execute([
                ':content' => $content !== '' ? $content : null,
                ':id' => (int) ($postInfo['id'] ?? 0),
            ]);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    $map = front_editor_allowed_pages();
    if (!isset($map[$pageKey])) {
        return ['ok' => false, 'message' => 'Page không hợp lệ.'];
    }
    $file = $map[$pageKey];
    if (!is_file($file) || !is_writable($file)) {
        return ['ok' => false, 'message' => 'File không tồn tại hoặc không ghi được.'];
    }
    $ok = file_put_contents($file, $content);
    if ($ok === false) {
        return ['ok' => false, 'message' => 'Ghi file thất bại.'];
    }
    return ['ok' => true];
}

function front_editor_source_editor_mode(array $source): string
{
    $type = (string) ($source['type'] ?? '');
    $content = (string) ($source['content'] ?? '');
    $file = strtolower((string) ($source['file'] ?? ''));
    if ($type === 'post') {
        return 'html';
    }
    if ($file !== '' && preg_match('~\.(?:html?|xhtml)$~i', $file)) {
        return 'html';
    }
    if (strpos($content, '<?') !== false) {
        return 'code';
    }
    if (preg_match('~^\s*<(?:!doctype|html|head|body|main|section|article|div)\b~i', $content)) {
        return 'html';
    }
    return $type === 'file' ? 'code' : 'html';
}

function front_editor_source_editor_syntax(array $source): string
{
    return front_editor_source_editor_mode($source) === 'code' ? 'php' : 'html';
}

function front_editor_source_editor_title(array $source): string
{
    return front_editor_source_editor_mode($source) === 'code'
        ? 'Sửa toàn trang bằng code'
        : 'Sửa toàn trang bằng HTML';
}

function front_editor_page_source_get(string $pageKey): array
{
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return ['ok' => false, 'message' => 'Không tìm thấy source của trang.'];
    }
    return [
        'ok' => true,
        'mode' => front_editor_source_editor_mode($source),
        'syntax' => front_editor_source_editor_syntax($source),
        'title' => front_editor_source_editor_title($source),
        'content' => (string) ($source['content'] ?? ''),
        'source_type' => (string) ($source['type'] ?? ''),
        'path' => (string) ($source['file'] ?? ''),
        'post_id' => (int) ($source['post_id'] ?? 0),
    ];
}

function front_editor_page_source_save(string $pageKey, string $content): array
{
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return ['ok' => false, 'message' => 'Không tìm thấy source của trang.'];
    }
    $before = (string) ($source['content'] ?? '');
    if ($before === $content) {
        return [
            'ok' => true,
            'message' => 'Không có thay đổi mới.',
            'mode' => front_editor_source_editor_mode($source),
            'syntax' => front_editor_source_editor_syntax($source),
            'title' => front_editor_source_editor_title($source),
        ];
    }
    $write = front_editor_source_write($pageKey, $content);
    if (!($write['ok'] ?? false)) {
        return $write;
    }
    $mode = front_editor_source_editor_mode($source);
    front_editor_history_insert(
        $pageKey,
        '__page__',
        $mode === 'code' ? 'update_page_code' : 'update_page_html',
        $mode,
        $mode,
        $before,
        $content
    );
    return [
        'ok' => true,
        'message' => $mode === 'code' ? 'Đã lưu code toàn trang.' : 'Đã lưu HTML toàn trang.',
        'mode' => $mode,
        'syntax' => front_editor_source_editor_syntax($source),
        'title' => front_editor_source_editor_title($source),
    ];
}

function front_editor_scan(string $pageKey): array
{
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return [];
    }
    $src = (string) ($source['content'] ?? '');
    if ($src === '') {
        return [];
    }

    $items = [];
    $pattern = '~<(p|h[1-6]|b|strong|span|a|button)(?:\s+[^>]*)?>(.*?)</\1>~isu';
    $seen = [];
    $norm = static function(string $s): string {
        $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
        $s = preg_replace('/\s+/u', ' ', $s) ?? '';
        return trim($s);
    };
    if (preg_match_all($pattern, $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($m as $match) {
            $tag = strtolower((string) $match[1][0]);
            $outerRaw = (string) $match[0][0];
            $outerOffset = (int) $match[0][1];
            $innerRaw = (string) $match[2][0];
            $innerOffset = (int) $match[2][1];
            if (strpos($innerRaw, '<?') !== false) {
                continue;
            }
            $innerTrim = $norm(strip_tags($innerRaw));
            if ($innerTrim === '') {
                continue;
            }
            if (mb_strlen($innerTrim, 'UTF-8') < 2) {
                continue;
            }
            $innerEnd = $innerOffset + strlen($innerRaw);
            $outerEnd = $outerOffset + strlen($outerRaw);
            $id = substr(sha1($pageKey . '|' . $tag . '|' . $innerOffset . '|' . $innerRaw), 0, 14);
            $k = $tag . '|' . $innerTrim;
            $seen[$k] = ($seen[$k] ?? 0) + 1;
            $occ = (int) $seen[$k];
            $items[] = [
                'id' => $id,
                'tag' => $tag,
                'text' => $innerTrim,
                'raw' => $innerRaw,
                'start' => $innerOffset,
                'end' => $innerEnd,
                'outer_raw' => $outerRaw,
                'outer_start' => $outerOffset,
                'outer_end' => $outerEnd,
                'occ' => $occ,
            ];
        }
    }
    return $items;
}

function front_editor_find_text_item(string $pageKey, string $id): ?array
{
    foreach (front_editor_scan($pageKey) as $it) {
        if (($it['id'] ?? '') === $id) {
            return $it;
        }
    }
    return null;
}

function front_editor_history_insert(string $pageKey, string $elementId, string $action, string $oldText, string $newText, string $before, string $after): void
{
    try {
        admin_front_session_boot();
        $adminUserId = isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
        $pdo = db();
        $ins = $pdo->prepare("INSERT INTO front_editor_history
            (page_key, element_id, admin_user_id, action, old_text, new_text, content_before, content_after)
            VALUES (:page_key, :element_id, :admin_user_id, :action, :old_text, :new_text, :before, :after)");
        $ins->execute([
            ':page_key' => $pageKey,
            ':element_id' => $elementId,
            ':admin_user_id' => $adminUserId,
            ':action' => $action,
            ':old_text' => $oldText,
            ':new_text' => $newText,
            ':before' => $before,
            ':after' => $after,
        ]);
    } catch (Throwable $e) {
    }
}

function front_editor_text_delete(string $pageKey, string $id): array
{
    $source = front_editor_source_read($pageKey);
    $picked = front_editor_find_text_item($pageKey, $id);
    if (!$source || !$picked) {
        return ['ok' => false, 'message' => 'Không tìm thấy đoạn text để xoá.'];
    }
    $src = (string) ($source['content'] ?? '');
    $start = (int) ($picked['outer_start'] ?? -1);
    $end = (int) ($picked['outer_end'] ?? -1);
    if ($start < 0 || $end < $start || $end > strlen($src)) {
        return ['ok' => false, 'message' => 'Vị trí xoá text không hợp lệ.'];
    }
    $out = substr($src, 0, $start) . substr($src, $end);
    $write = front_editor_source_write($pageKey, $out);
    if (!($write['ok'] ?? false)) {
        return ['ok' => false, 'message' => (string) ($write['message'] ?? 'Xoá text thất bại.')];
    }
    front_editor_history_insert($pageKey, $id, 'delete_text', (string) ($picked['text'] ?? ''), '', $src, $out);
    return ['ok' => true, 'message' => 'Đã xoá text.'];
}

function front_editor_text_duplicate(string $pageKey, string $id): array
{
    $source = front_editor_source_read($pageKey);
    $picked = front_editor_find_text_item($pageKey, $id);
    if (!$source || !$picked) {
        return ['ok' => false, 'message' => 'Không tìm thấy đoạn text để nhân đôi.'];
    }
    $src = (string) ($source['content'] ?? '');
    $end = (int) ($picked['outer_end'] ?? -1);
    $outerRaw = (string) ($picked['outer_raw'] ?? '');
    if ($end < 0 || $end > strlen($src) || $outerRaw === '') {
        return ['ok' => false, 'message' => 'Không thể nhân đôi đoạn text này.'];
    }
    $out = substr($src, 0, $end) . $outerRaw . substr($src, $end);
    $write = front_editor_source_write($pageKey, $out);
    if (!($write['ok'] ?? false)) {
        return ['ok' => false, 'message' => (string) ($write['message'] ?? 'Nhân đôi text thất bại.')];
    }
    front_editor_history_insert($pageKey, $id, 'duplicate_text', (string) ($picked['text'] ?? ''), (string) ($picked['text'] ?? ''), $src, $out);
    return ['ok' => true, 'message' => 'Đã nhân đôi text.'];
}

function front_editor_text_move(string $pageKey, string $id, string $direction): array
{
    $direction = $direction === 'down' ? 'down' : 'up';
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return ['ok' => false, 'message' => 'Page không hợp lệ.'];
    }
    $items = front_editor_scan($pageKey);
    usort($items, static function (array $a, array $b): int {
        return ((int) ($a['outer_start'] ?? 0)) <=> ((int) ($b['outer_start'] ?? 0));
    });
    $currentIndex = -1;
    foreach ($items as $index => $it) {
        if (($it['id'] ?? '') === $id) {
            $currentIndex = $index;
            break;
        }
    }
    if ($currentIndex < 0) {
        return ['ok' => false, 'message' => 'Không tìm thấy đoạn text để di chuyển.'];
    }
    $current = $items[$currentIndex];
    $swap = null;
    if ($direction === 'up') {
        for ($i = $currentIndex - 1; $i >= 0; $i--) {
            $candidate = $items[$i];
            if ((int) ($candidate['outer_end'] ?? 0) <= (int) ($current['outer_start'] ?? 0)) {
                $swap = $candidate;
                break;
            }
        }
    } else {
        for ($i = $currentIndex + 1; $i < count($items); $i++) {
            $candidate = $items[$i];
            if ((int) ($candidate['outer_start'] ?? 0) >= (int) ($current['outer_end'] ?? 0)) {
                $swap = $candidate;
                break;
            }
        }
    }
    if (!$swap) {
        return ['ok' => false, 'message' => $direction === 'up' ? 'Text này đã ở trên cùng.' : 'Text này đã ở dưới cùng.'];
    }

    $src = (string) ($source['content'] ?? '');
    $currentStart = (int) ($current['outer_start'] ?? -1);
    $currentEnd = (int) ($current['outer_end'] ?? -1);
    $swapStart = (int) ($swap['outer_start'] ?? -1);
    $swapEnd = (int) ($swap['outer_end'] ?? -1);
    if ($currentStart < 0 || $currentEnd < $currentStart || $swapStart < 0 || $swapEnd < $swapStart) {
        return ['ok' => false, 'message' => 'Vị trí di chuyển text không hợp lệ.'];
    }

    if ($direction === 'up') {
        $firstStart = $swapStart;
        $firstEnd = $swapEnd;
        $secondStart = $currentStart;
        $secondEnd = $currentEnd;
    } else {
        $firstStart = $currentStart;
        $firstEnd = $currentEnd;
        $secondStart = $swapStart;
        $secondEnd = $swapEnd;
    }
    $firstHtml = substr($src, $firstStart, $firstEnd - $firstStart);
    $middle = substr($src, $firstEnd, $secondStart - $firstEnd);
    $secondHtml = substr($src, $secondStart, $secondEnd - $secondStart);
    $out = substr($src, 0, $firstStart) . $secondHtml . $middle . $firstHtml . substr($src, $secondEnd);

    $write = front_editor_source_write($pageKey, $out);
    if (!($write['ok'] ?? false)) {
        return ['ok' => false, 'message' => (string) ($write['message'] ?? 'Di chuyển text thất bại.')];
    }
    front_editor_history_insert($pageKey, $id, $direction === 'up' ? 'move_text_up' : 'move_text_down', (string) ($current['text'] ?? ''), (string) ($swap['text'] ?? ''), $src, $out);
    return ['ok' => true, 'message' => $direction === 'up' ? 'Đã đưa text lên trên.' : 'Đã đưa text xuống dưới.'];
}

function front_editor_update(string $pageKey, string $id, string $newText, string $newHtml = ''): array
{
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return ['ok' => false, 'message' => 'Page không hợp lệ.'];
    }
    $items = front_editor_scan($pageKey);
    $picked = null;
    foreach ($items as $it) {
        if (($it['id'] ?? '') === $id) {
            $picked = $it;
            break;
        }
    }
    if (!$picked) {
        return ['ok' => false, 'message' => 'Không tìm thấy đoạn text để sửa.'];
    }

    $src = (string) ($source['content'] ?? '');
    $start = (int) ($picked['start'] ?? 0);
    $end = (int) ($picked['end'] ?? 0);
    if ($start < 0 || $end < $start || $end > strlen($src)) {
        return ['ok' => false, 'message' => 'Vị trí thay thế không hợp lệ.'];
    }

    $newText = trim($newText);
    if ($newText === '') {
        return ['ok' => false, 'message' => 'Nội dung mới không được rỗng.'];
    }
    $sanitizedHtml = trim($newHtml) !== '' ? front_editor_sanitize_rich_text($newHtml) : htmlspecialchars($newText, ENT_QUOTES, 'UTF-8');
    $replacement = '';
    $rawInner = (string) ($picked['raw'] ?? '');
    $tag = strtolower((string) ($picked['tag'] ?? ''));
    if ($tag === 'a' || $tag === 'button' || strpos($rawInner, '<') !== false) {
        $replacement = front_editor_replace_text_fragment($rawInner, $sanitizedHtml);
        if ($replacement === '') {
            return ['ok' => false, 'message' => 'Không thể cập nhật text của nút/liên kết này.'];
        }
    } else {
        $replacement = $sanitizedHtml;
    }
    $out = substr($src, 0, $start) . $replacement . substr($src, $end);
    $write = front_editor_source_write($pageKey, $out);
    if (!($write['ok'] ?? false)) {
        return ['ok' => false, 'message' => (string) ($write['message'] ?? 'Lưu nội dung thất bại.')];
    }

    try {
        admin_front_session_boot();
        $adminUserId = isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
        $pdo = db();
        $ins = $pdo->prepare("INSERT INTO front_editor_history
            (page_key, element_id, admin_user_id, action, old_text, new_text, content_before, content_after)
            VALUES (:page_key, :element_id, :admin_user_id, 'update', :old_text, :new_text, :before, :after)");
        $ins->execute([
            ':page_key' => $pageKey,
            ':element_id' => $id,
            ':admin_user_id' => $adminUserId,
            ':old_text' => (string) ($picked['text'] ?? ''),
            ':new_text' => $newText,
            ':before' => $src,
            ':after' => $out,
        ]);
    } catch (Throwable $e) {
    }

    return ['ok' => true, 'message' => 'Đã cập nhật.', 'text' => $newText, 'html' => $replacement];
}

function front_editor_replace_text_fragment(string $html, string $newText): string
{
    $html = (string) $html;
    $newText = trim($newText);
    if ($html === '' || $newText === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        return htmlspecialchars($newText, ENT_QUOTES, 'UTF-8');
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapperId = 'fe-fragment-root';
    $loaded = $dom->loadHTML(
        '<?xml encoding="utf-8" ?><div id="' . $wrapperId . '">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    if ($previous !== null) {
        libxml_use_internal_errors($previous);
    }
    if (!$loaded) {
        return '';
    }

    $xpath = new DOMXPath($dom);
    $rootList = $xpath->query('//*[@id="' . $wrapperId . '"]');
    $root = ($rootList instanceof DOMNodeList && $rootList->length > 0) ? $rootList->item(0) : null;
    if (!$root instanceof DOMElement) {
        return '';
    }

    $textNodes = [];
    $collect = static function (DOMNode $node) use (&$collect, &$textNodes): void {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $raw = (string) $child->nodeValue;
                $trimmed = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
                if ($trimmed !== '') {
                    $textNodes[] = $child;
                }
                continue;
            }
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $collect($child);
            }
        }
    };
    $collect($root);

    if (count($textNodes) === 0) {
        return '';
    }

    $target = $textNodes[count($textNodes) - 1];
    $original = (string) $target->nodeValue;
    $leading = '';
    $trailing = '';
    if (preg_match('/^(\s*).*?(\s*)$/us', $original, $m)) {
        $leading = (string) ($m[1] ?? '');
        $trailing = (string) ($m[2] ?? '');
    }

    $parent = $target->parentNode;
    if (!$parent instanceof DOMNode) {
        return '';
    }
    if ($leading !== '') {
        $parent->insertBefore($dom->createTextNode($leading), $target);
    }
    $fragmentDom = new DOMDocument('1.0', 'UTF-8');
    $fragmentLoaded = $fragmentDom->loadHTML(
        '<?xml encoding="utf-8" ?><div id="' . $wrapperId . '-replace">' . $newText . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    if (!$fragmentLoaded) {
        return '';
    }
    $fragmentRoot = null;
    foreach ($fragmentDom->getElementsByTagName('div') as $candidate) {
        if ($candidate instanceof DOMElement && $candidate->getAttribute('id') === $wrapperId . '-replace') {
            $fragmentRoot = $candidate;
            break;
        }
    }
    if (!$fragmentRoot instanceof DOMElement) {
        return '';
    }
    while ($fragmentRoot->firstChild) {
        $imported = $dom->importNode($fragmentRoot->firstChild, true);
        $parent->insertBefore($imported, $target);
        $fragmentRoot->removeChild($fragmentRoot->firstChild);
    }
    if ($trailing !== '') {
        $parent->insertBefore($dom->createTextNode($trailing), $target);
    }
    $parent->removeChild($target);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return $out;
}

function front_editor_is_editable_media_url(string $url): bool
{
    $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    if ($url === '' || strpos($url, '<?') !== false) {
        return false;
    }
    if (preg_match('~^(?:data|javascript|mailto|tel):~i', $url)) {
        return false;
    }
    if (preg_match('~\.(?:css|js|map|woff2?|ttf|eot|otf)(?:[?#].*)?$~i', $url)) {
        return false;
    }
    if (preg_match('~\.(?:avif|bmp|gif|ico|jpe?g|png|svg|webp)(?:[?#].*)?$~i', $url)) {
        return true;
    }
    if (preg_match('~^https?://images\.unsplash\.com/~i', $url)) {
        return true;
    }
    if (preg_match('~^/?uploads/~i', $url)) {
        return true;
    }
    if (preg_match('~^(?:https?:)?//~i', $url)) {
        return true;
    }
    if (preg_match('~^(?:\./|\.\./|/)~', $url)) {
        return true;
    }
    return false;
}

function front_editor_scan_images(string $pageKey): array
{
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return [];
    }
    $src = (string) ($source['content'] ?? '');
    if ($src === '') {
        return [];
    }

    $items = [];
    $seen = [];
    $used = [];
    $push = static function (string $rawUrl, int $start) use (&$items, &$seen, &$used, $pageKey): void {
        $url = trim(html_entity_decode($rawUrl, ENT_QUOTES, 'UTF-8'));
        if (!front_editor_is_editable_media_url($url)) {
            return;
        }
        $end = $start + strlen($rawUrl);
        $loc = $start . ':' . $end;
        if (isset($used[$loc])) {
            return;
        }
        $used[$loc] = true;
        $seen[$url] = ($seen[$url] ?? 0) + 1;
        $items[] = [
            'id' => substr(sha1($pageKey . '|img|' . $start . '|' . $rawUrl), 0, 14),
            'url' => $url,
            'raw' => $rawUrl,
            'source' => 'file',
            'start' => $start,
            'end' => $end,
            'occ' => (int) $seen[$url],
        ];
    };

    if (preg_match_all('~<img\b[^>]*\bsrc\s*=\s*(["\'])([^"\']+)\1~isu', $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($m as $match) {
            $push((string) $match[2][0], (int) $match[2][1]);
        }
    }
    if (preg_match_all('~url\(\s*(["\']?)([^)"\']+)\1\s*\)~isu', $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($m as $match) {
            $push((string) $match[2][0], (int) $match[2][1]);
        }
    }

    $galleryLines = ($source['type'] ?? '') === 'post' ? '' : site_setting('site_home_gallery_lines', '');
    if (trim($galleryLines) !== '') {
        $lines = preg_split('/\r\n|\r|\n/', $galleryLines) ?: [];
        foreach ($lines as $lineIndex => $line) {
            $url = trim(html_entity_decode((string) $line, ENT_QUOTES, 'UTF-8'));
            if (!front_editor_is_editable_media_url($url)) {
                continue;
            }
            $seen[$url] = ($seen[$url] ?? 0) + 1;
            $items[] = [
                'id' => substr(sha1($pageKey . '|setting|site_home_gallery_lines|' . $lineIndex . '|' . $url), 0, 14),
                'url' => $url,
                'raw' => $url,
                'source' => 'setting',
                'setting_key' => 'site_home_gallery_lines',
                'line_index' => (int) $lineIndex,
                'start' => PHP_INT_MAX - 1000 + (int) $lineIndex,
                'end' => PHP_INT_MAX - 1000 + (int) $lineIndex + strlen($url),
                'occ' => (int) $seen[$url],
            ];
        }
    }

    usort($items, static function (array $a, array $b): int {
        return ((int) ($a['start'] ?? 0)) <=> ((int) ($b['start'] ?? 0));
    });
    return $items;
}

function front_editor_update_image(string $pageKey, string $id, string $newUrl): array
{
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return ['ok' => false, 'message' => 'Page không hợp lệ.'];
    }
    $items = front_editor_scan_images($pageKey);
    $picked = null;
    foreach ($items as $it) {
        if (($it['id'] ?? '') === $id) {
            $picked = $it;
            break;
        }
    }
    if (!$picked) {
        return ['ok' => false, 'message' => 'Không tìm thấy ảnh để sửa.'];
    }

    if (($picked['source'] ?? 'file') === 'setting') {
        $settingKey = (string) ($picked['setting_key'] ?? '');
        $lineIndex = (int) ($picked['line_index'] ?? -1);
        if ($settingKey === '' || $lineIndex < 0) {
            return ['ok' => false, 'message' => 'Nguồn ảnh không hợp lệ.'];
        }

        $newUrl = trim(html_entity_decode($newUrl, ENT_QUOTES, 'UTF-8'));
        if (!front_editor_is_editable_media_url($newUrl)) {
            return ['ok' => false, 'message' => 'URL ảnh không hợp lệ.'];
        }
        if (preg_match('/["\'<>`]/', $newUrl)) {
            return ['ok' => false, 'message' => 'URL ảnh chứa ký tự không hợp lệ.'];
        }

        $before = site_setting($settingKey, '');
        $lines = preg_split('/\r\n|\r|\n/', $before) ?: [];
        if (!array_key_exists($lineIndex, $lines)) {
            return ['ok' => false, 'message' => 'Không tìm thấy ảnh trong cài đặt.'];
        }
        $lines[$lineIndex] = $newUrl;
        $after = implode("\n", $lines);
        site_setting_set($settingKey, $after);

        try {
            admin_front_session_boot();
            $adminUserId = isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
            $pdo = db();
            $ins = $pdo->prepare("INSERT INTO front_editor_history
                (page_key, element_id, admin_user_id, action, old_text, new_text, content_before, content_after)
                VALUES (:page_key, :element_id, :admin_user_id, 'update_image', :old_text, :new_text, :before, :after)");
            $ins->execute([
                ':page_key' => $pageKey,
                ':element_id' => $id,
                ':admin_user_id' => $adminUserId,
                ':old_text' => (string) ($picked['url'] ?? ''),
                ':new_text' => $newUrl,
                ':before' => $before,
                ':after' => $after,
            ]);
        } catch (Throwable $e) {
        }

        return ['ok' => true, 'message' => 'Đã cập nhật ảnh.', 'url' => $newUrl];
    }

    $src = (string) ($source['content'] ?? '');
    $start = (int) ($picked['start'] ?? 0);
    $end = (int) ($picked['end'] ?? 0);
    if ($start < 0 || $end < $start || $end > strlen($src)) {
        return ['ok' => false, 'message' => 'Vị trí thay thế không hợp lệ.'];
    }

    $newUrl = trim(html_entity_decode($newUrl, ENT_QUOTES, 'UTF-8'));
    if (!front_editor_is_editable_media_url($newUrl)) {
        return ['ok' => false, 'message' => 'URL ảnh không hợp lệ.'];
    }
    if (preg_match('/["\'<>`]/', $newUrl)) {
        return ['ok' => false, 'message' => 'URL ảnh chứa ký tự không hợp lệ.'];
    }

    $out = substr($src, 0, $start) . $newUrl . substr($src, $end);
    $write = front_editor_source_write($pageKey, $out);
    if (!($write['ok'] ?? false)) {
        return ['ok' => false, 'message' => (string) ($write['message'] ?? 'Lưu ảnh thất bại.')];
    }

    try {
        admin_front_session_boot();
        $adminUserId = isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
        $pdo = db();
        $ins = $pdo->prepare("INSERT INTO front_editor_history
            (page_key, element_id, admin_user_id, action, old_text, new_text, content_before, content_after)
            VALUES (:page_key, :element_id, :admin_user_id, 'update_image', :old_text, :new_text, :before, :after)");
        $ins->execute([
            ':page_key' => $pageKey,
            ':element_id' => $id,
            ':admin_user_id' => $adminUserId,
            ':old_text' => (string) ($picked['url'] ?? ''),
            ':new_text' => $newUrl,
            ':before' => $src,
            ':after' => $out,
        ]);
    } catch (Throwable $e) {
    }

    return ['ok' => true, 'message' => 'Đã cập nhật ảnh.', 'url' => $newUrl];
}

function front_editor_is_icon_class_token(string $token): bool
{
    $token = trim(strtolower($token));
    if ($token === '') {
        return false;
    }
    if ($token === 'ph' || $token === 'ph-fill' || $token === 'ph-bold' || $token === 'ph-duotone' || $token === 'ph-light' || $token === 'ph-thin') {
        return true;
    }
    return strpos($token, 'fa-') === 0 || strpos($token, 'ph-') === 0;
}

function front_editor_normalize_icon_signature(string $classAttr): string
{
    $tokens = preg_split('/\s+/', trim($classAttr)) ?: [];
    $filtered = [];
    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || !front_editor_is_icon_class_token($token)) {
            continue;
        }
        $filtered[] = strtolower($token);
    }
    $filtered = array_values(array_unique($filtered));
    sort($filtered, SORT_STRING);
    return implode(' ', $filtered);
}

function front_editor_merge_icon_classes(string $existingClassAttr, string $iconClassAttr): string
{
    $existingTokens = preg_split('/\s+/', trim($existingClassAttr)) ?: [];
    $keep = [];
    foreach ($existingTokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || front_editor_is_icon_class_token($token)) {
            continue;
        }
        $keep[] = $token;
    }

    $iconTokens = preg_split('/\s+/', trim($iconClassAttr)) ?: [];
    $nextIcons = [];
    foreach ($iconTokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || !front_editor_is_icon_class_token($token)) {
            continue;
        }
        $nextIcons[] = strtolower($token);
    }
    $nextIcons = array_values(array_unique($nextIcons));
    if (count($nextIcons) === 0) {
        return '';
    }

    return trim(implode(' ', array_merge($keep, $nextIcons)));
}

function front_editor_scan_icons(string $pageKey): array
{
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return [];
    }
    $src = (string) ($source['content'] ?? '');
    if ($src === '') {
        return [];
    }

    $items = [];
    $seen = [];
    $pattern = '~<i\b([^>]*?)\bclass\s*=\s*(["\'])([^"\']+)\2([^>]*)>~isu';
    if (preg_match_all($pattern, $src, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($matches as $match) {
            $fullRaw = (string) ($match[0][0] ?? '');
            $fullStart = (int) ($match[0][1] ?? 0);
            $classRaw = trim((string) ($match[3][0] ?? ''));
            $classStart = (int) ($match[3][1] ?? 0);
            if ($classRaw === '' || strpos($fullRaw, '<?') !== false) {
                continue;
            }
            $signature = front_editor_normalize_icon_signature($classRaw);
            if ($signature === '') {
                continue;
            }
            $seen[$signature] = ($seen[$signature] ?? 0) + 1;
            $items[] = [
                'id' => substr(sha1($pageKey . '|icon|' . $classStart . '|' . $classRaw), 0, 14),
                'class' => $classRaw,
                'icon_key' => $signature,
                'start' => $classStart,
                'end' => $classStart + strlen($classRaw),
                'occ' => (int) $seen[$signature],
            ];
        }
    }

    usort($items, static function (array $a, array $b): int {
        return ((int) ($a['start'] ?? 0)) <=> ((int) ($b['start'] ?? 0));
    });
    return $items;
}

function front_editor_update_icon(string $pageKey, string $id, string $iconClass): array
{
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return ['ok' => false, 'message' => 'Page không hợp lệ.'];
    }
    $items = front_editor_scan_icons($pageKey);
    $picked = null;
    foreach ($items as $it) {
        if (($it['id'] ?? '') === $id) {
            $picked = $it;
            break;
        }
    }
    if (!$picked) {
        return ['ok' => false, 'message' => 'Không tìm thấy icon để sửa.'];
    }

    $nextClass = front_editor_merge_icon_classes((string) ($picked['class'] ?? ''), $iconClass);
    if ($nextClass === '') {
        return ['ok' => false, 'message' => 'Class icon không hợp lệ.'];
    }

    $src = (string) ($source['content'] ?? '');
    $start = (int) ($picked['start'] ?? 0);
    $end = (int) ($picked['end'] ?? 0);
    if ($start < 0 || $end < $start || $end > strlen($src)) {
        return ['ok' => false, 'message' => 'Vị trí thay thế icon không hợp lệ.'];
    }

    $out = substr($src, 0, $start) . $nextClass . substr($src, $end);
    $write = front_editor_source_write($pageKey, $out);
    if (!($write['ok'] ?? false)) {
        return ['ok' => false, 'message' => (string) ($write['message'] ?? 'Lưu icon thất bại.')];
    }

    front_editor_history_insert($pageKey, $id, 'update_icon', (string) ($picked['class'] ?? ''), $nextClass, $src, $out);
    return ['ok' => true, 'message' => 'Đã cập nhật icon.', 'class' => $nextClass];
}

function front_editor_scan_blocks(string $pageKey): array
{
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return [];
    }
    $src = (string) ($source['content'] ?? '');
    if ($src === '') {
        return [];
    }

    $pattern = '~<(section|article|aside|div)\b([^>]*)>|</(section|article|aside|div)\s*>~i';
    if (!preg_match_all($pattern, $src, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        return [];
    }

    $stack = [];
    $items = [];
    $seen = [];
    foreach ($matches as $match) {
        $fullTag = (string) ($match[0][0] ?? '');
        $fullStart = (int) ($match[0][1] ?? 0);
        $fullEnd = $fullStart + strlen($fullTag);
        $openTag = strtolower((string) ($match[1][0] ?? ''));
        $openAttrs = (string) ($match[2][0] ?? '');
        $closeTag = strtolower((string) ($match[3][0] ?? ''));

        if ($openTag !== '') {
            if (preg_match('~/\s*>$~', $fullTag)) {
                continue;
            }
            $stack[] = [
                'tag' => $openTag,
                'attrs' => $openAttrs,
                'full_start' => $fullStart,
                'open_end' => $fullEnd,
                'depth' => count($stack) + 1,
                'parent_start' => count($stack) > 0 ? ($stack[count($stack) - 1]['full_start'] ?? null) : null,
            ];
            continue;
        }

        if ($closeTag === '') {
            continue;
        }

        for ($i = count($stack) - 1; $i >= 0; $i--) {
            $open = $stack[$i];
            if (($open['tag'] ?? '') !== $closeTag) {
                continue;
            }
            array_splice($stack, $i, 1);
            $start = (int) ($open['full_start'] ?? 0);
            $openEnd = (int) ($open['open_end'] ?? $start);
            $outerHtml = substr($src, $start, $fullEnd - $start);
            $innerHtml = substr($src, $openEnd, $fullStart - $openEnd);
            if (!is_string($outerHtml) || !is_string($innerHtml) || $outerHtml === '') {
                break;
            }
            $outerTrim = trim($outerHtml);
            if ($outerTrim === '' || strlen($outerTrim) < 80 || strlen($outerTrim) > 50000) {
                break;
            }

            $attrs = (string) ($open['attrs'] ?? '');
            $idAttr = '';
            $classAttr = '';
            if (preg_match('/\bid\s*=\s*([\'"])(.*?)\1/i', $attrs, $mId)) {
                $idAttr = trim((string) ($mId[2] ?? ''));
            }
            if (preg_match('/\bclass\s*=\s*([\'"])(.*?)\1/i', $attrs, $mClass)) {
                $classAttr = trim((string) ($mClass[2] ?? ''));
            }
            if ($closeTag === 'div' && $idAttr === '' && $classAttr === '') {
                break;
            }

            $previewSource = preg_replace('~<\?(?:php|=)?[\s\S]*?\?>~i', ' ', $outerHtml) ?? $outerHtml;
            $textPreview = trim(preg_replace('/\s+/u', ' ', strip_tags($previewSource)) ?? '');
            if (mb_strlen($textPreview, 'UTF-8') < 8) {
                break;
            }
            $textKey = mb_substr($textPreview, 0, 80, 'UTF-8');
            $sig = $closeTag . '|' . $idAttr . '|' . $classAttr . '|' . $textKey;
            $seen[$sig] = ($seen[$sig] ?? 0) + 1;

            $items[] = [
                'id' => substr(sha1($pageKey . '|block|' . $start . '|' . $outerHtml), 0, 14),
                'tag' => $closeTag,
                'id_attr' => $idAttr,
                'class_attr' => $classAttr,
                'text_key' => $textKey,
                'preview' => mb_substr($textPreview, 0, 160, 'UTF-8'),
                'html' => $outerHtml,
                'start' => $start,
                'end' => $fullEnd,
                'depth' => (int) ($open['depth'] ?? 1),
                'parent_start' => $open['parent_start'] ?? null,
                'occ' => (int) $seen[$sig],
            ];
            break;
        }
    }

    return $items;
}

function front_editor_templates_ensure_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS front_editor_templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            page_key VARCHAR(32) NULL,
            source_element_id VARCHAR(32) NULL,
            tag_name VARCHAR(24) NULL,
            preview_text VARCHAR(255) NULL,
            html_content MEDIUMTEXT NOT NULL,
            admin_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_fet_created (created_at),
            KEY idx_fet_page (page_key),
            KEY idx_fet_user (admin_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function front_editor_template_save(string $pageKey, string $blockId, string $name, bool $saveAll = false): array
{
    $name = trim($name);
    if ($name === '') {
        return ['ok' => false, 'message' => 'Tên template không được rỗng.'];
    }

    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return ['ok' => false, 'message' => 'Page không hợp lệ.'];
    }

    $picked = null;
    if ($saveAll) {
        $src = trim((string) ($source['content'] ?? ''));
        if ($src === '') {
            return ['ok' => false, 'message' => 'Trang hiện chưa có nội dung để lưu template.'];
        }
        $previewSource = preg_replace('~<\?(?:php|=)?[\s\S]*?\?>~i', ' ', $src) ?? $src;
        $preview = trim(preg_replace('/\s+/u', ' ', strip_tags($previewSource)) ?? '');
        $picked = [
            'id' => '__page__',
            'tag' => 'page',
            'preview' => mb_substr($preview !== '' ? $preview : 'Template toàn trang', 0, 160, 'UTF-8'),
            'html' => $src,
        ];
    } else {
        $blocks = front_editor_scan_blocks($pageKey);
        foreach ($blocks as $block) {
            if (($block['id'] ?? '') === $blockId) {
                $picked = $block;
                break;
            }
        }
        if (!$picked) {
            return ['ok' => false, 'message' => 'Không tìm thấy block để lưu template.'];
        }
    }

    try {
        front_editor_templates_ensure_table();
        admin_front_session_boot();
        $adminUserId = isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO front_editor_templates
            (name, page_key, source_element_id, tag_name, preview_text, html_content, admin_user_id)
            VALUES (:name, :page_key, :source_element_id, :tag_name, :preview_text, :html_content, :admin_user_id)");
        $stmt->execute([
            ':name' => $name,
            ':page_key' => $pageKey,
            ':source_element_id' => (string) ($picked['id'] ?? ''),
            ':tag_name' => (string) ($picked['tag'] ?? ''),
            ':preview_text' => (string) ($picked['preview'] ?? ''),
            ':html_content' => (string) ($picked['html'] ?? ''),
            ':admin_user_id' => $adminUserId,
        ]);
        return [
            'ok' => true,
            'message' => $saveAll ? 'Đã lưu toàn bộ template của trang.' : 'Đã lưu template.',
            'template' => [
                'id' => (int) $pdo->lastInsertId(),
                'name' => $name,
            ],
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function front_editor_template_list(int $limit = 100): array
{
    try {
        front_editor_templates_ensure_table();
        $limit = max(1, min(200, $limit));
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, name, page_key, source_element_id, tag_name, preview_text, html_content, admin_user_id, created_at, updated_at
                               FROM front_editor_templates
                               ORDER BY id DESC
                               LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'page_key' => (string) ($row['page_key'] ?? ''),
                'source_element_id' => (string) ($row['source_element_id'] ?? ''),
                'tag_name' => (string) ($row['tag_name'] ?? ''),
                'preview_text' => (string) ($row['preview_text'] ?? ''),
                'html_content' => (string) ($row['html_content'] ?? ''),
                'admin_user_id' => $row['admin_user_id'] === null ? null : (int) $row['admin_user_id'],
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }
        return ['ok' => true, 'items' => $items];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function front_editor_template_update(int $id, string $name, string $html): array
{
    if ($id <= 0) {
        return ['ok' => false, 'message' => 'ID template không hợp lệ.'];
    }
    $name = trim($name);
    $html = trim($html);
    if ($name === '' || $html === '') {
        return ['ok' => false, 'message' => 'Tên và nội dung template không được rỗng.'];
    }
    try {
        front_editor_templates_ensure_table();
        $previewSource = preg_replace('~<\?(?:php|=)?[\s\S]*?\?>~i', ' ', $html) ?? $html;
        $preview = trim(preg_replace('/\s+/u', ' ', strip_tags($previewSource)) ?? '');
        $preview = mb_substr($preview, 0, 160, 'UTF-8');
        $tagName = '';
        if (preg_match('~^\s*<([a-z0-9]+)\b~i', $html, $m)) {
            $tagName = strtolower((string) ($m[1] ?? ''));
        }
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE front_editor_templates
                               SET name = :name, tag_name = :tag_name, preview_text = :preview_text, html_content = :html_content
                               WHERE id = :id");
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':tag_name' => $tagName,
            ':preview_text' => $preview,
            ':html_content' => $html,
        ]);
        return ['ok' => true, 'message' => 'Đã cập nhật template.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function front_editor_template_delete(int $id): array
{
    if ($id <= 0) {
        return ['ok' => false, 'message' => 'ID template không hợp lệ.'];
    }
    try {
        front_editor_templates_ensure_table();
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM front_editor_templates WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return ['ok' => true, 'message' => 'Đã xoá template.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function front_editor_template_apply(string $pageKey, string $blockId, int $templateId, string $position = 'replace'): array
{
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return ['ok' => false, 'message' => 'Page không hợp lệ.'];
    }

    try {
        front_editor_templates_ensure_table();
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, name, source_element_id, tag_name, html_content FROM front_editor_templates WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $templateId]);
        $tpl = $stmt->fetch();
        if (!$tpl) {
            return ['ok' => false, 'message' => 'Không tìm thấy template.'];
        }

        $html = trim((string) ($tpl['html_content'] ?? ''));
        if ($html === '') {
            return ['ok' => false, 'message' => 'Nội dung template không hợp lệ.'];
        }

        $sourceElementId = (string) ($tpl['source_element_id'] ?? '');
        $tagName = strtolower((string) ($tpl['tag_name'] ?? ''));
        $isFullPageTemplate = $sourceElementId === '__page__' || $tagName === 'page';
        $src = (string) ($source['content'] ?? '');

        $blocks = front_editor_scan_blocks($pageKey);
        $picked = null;
        foreach ($blocks as $block) {
            if (($block['id'] ?? '') === $blockId) {
                $picked = $block;
                break;
            }
        }
        if (!$picked) {
            return ['ok' => false, 'message' => 'Không tìm thấy block đích.'];
        }

        $start = (int) ($picked['start'] ?? 0);
        $end = (int) ($picked['end'] ?? 0);
        if ($start < 0 || $end < $start || $end > strlen($src)) {
            return ['ok' => false, 'message' => 'Vị trí block không hợp lệ.'];
        }

        $position = strtolower(trim($position));
        if ($isFullPageTemplate) {
            if ($position !== 'before' && $position !== 'after') {
                return ['ok' => false, 'message' => 'Template toàn trang cần chọn chèn trên hoặc dưới block.'];
            }
            if ($position === 'before') {
                $out = substr($src, 0, $start) . $html . "\n" . substr($src, $start);
            } else {
                $out = substr($src, 0, $end) . "\n" . $html . substr($src, $end);
            }
        } else {
            $out = substr($src, 0, $start) . $html . substr($src, $end);
        }

        $write = front_editor_source_write($pageKey, $out);
        if (!($write['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($write['message'] ?? ($isFullPageTemplate ? 'Chèn template toàn trang thất bại.' : 'Lưu template thất bại.'))];
        }

        try {
            admin_front_session_boot();
            $adminUserId = isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
            $ins = $pdo->prepare("INSERT INTO front_editor_history
                (page_key, element_id, admin_user_id, action, old_text, new_text, content_before, content_after)
                VALUES (:page_key, :element_id, :admin_user_id, :action, :old_text, :new_text, :before, :after)");
            $ins->execute([
                ':page_key' => $pageKey,
                ':element_id' => $blockId,
                ':admin_user_id' => $adminUserId,
                ':action' => $isFullPageTemplate
                    ? ($position === 'before' ? 'insert_template_page_before' : 'insert_template_page_after')
                    : 'apply_template',
                ':old_text' => (string) ($picked['preview'] ?? ''),
                ':new_text' => 'template#' . $templateId . ' ' . (string) ($tpl['name'] ?? ''),
                ':before' => $src,
                ':after' => $out,
            ]);
        } catch (Throwable $e) {
        }

        return [
            'ok' => true,
            'message' => $isFullPageTemplate
                ? ($position === 'before' ? 'Đã chèn template toàn trang lên trên block.' : 'Đã chèn template toàn trang xuống dưới block.')
                : 'Đã áp dụng template.',
            'html' => $html,
            'template' => [
                'id' => (int) ($tpl['id'] ?? 0),
                'name' => (string) ($tpl['name'] ?? ''),
            ],
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function front_editor_block_delete(string $pageKey, string $blockId): array
{
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return ['ok' => false, 'message' => 'Page không hợp lệ.'];
    }

    $blocks = front_editor_scan_blocks($pageKey);
    $picked = null;
    foreach ($blocks as $block) {
        if (($block['id'] ?? '') === $blockId) {
            $picked = $block;
            break;
        }
    }
    if (!$picked) {
        return ['ok' => false, 'message' => 'Không tìm thấy section để xoá.'];
    }

    try {
        $src = (string) ($source['content'] ?? '');

        $start = (int) ($picked['start'] ?? 0);
        $end = (int) ($picked['end'] ?? 0);
        if ($start < 0 || $end < $start || $end > strlen($src)) {
            return ['ok' => false, 'message' => 'Vị trí section không hợp lệ.'];
        }

        $before = substr($src, 0, $start);
        $after = substr($src, $end);
        $out = rtrim($before, "\r\n") . "\n\n" . ltrim($after, "\r\n");
        $write = front_editor_source_write($pageKey, $out);
        if (!($write['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($write['message'] ?? 'Xoá section thất bại.')];
        }

        try {
            $pdo = db();
            admin_front_session_boot();
            $adminUserId = isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
            $ins = $pdo->prepare("INSERT INTO front_editor_history
                (page_key, element_id, admin_user_id, action, old_text, new_text, content_before, content_after)
                VALUES (:page_key, :element_id, :admin_user_id, 'delete_block', :old_text, :new_text, :before, :after)");
            $ins->execute([
                ':page_key' => $pageKey,
                ':element_id' => $blockId,
                ':admin_user_id' => $adminUserId,
                ':old_text' => (string) ($picked['preview'] ?? ''),
                ':new_text' => '',
                ':before' => $src,
                ':after' => $out,
            ]);
        } catch (Throwable $e) {
        }

        return ['ok' => true, 'message' => 'Đã xoá section.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function front_editor_block_move(string $pageKey, string $blockId, string $direction): array
{
    $direction = $direction === 'down' ? 'down' : 'up';
    $source = front_editor_source_read($pageKey);
    if (!$source) {
        return ['ok' => false, 'message' => 'Page không hợp lệ.'];
    }

    $blocks = front_editor_scan_blocks($pageKey);
    usort($blocks, static function (array $a, array $b): int {
        return ((int) ($a['start'] ?? 0)) <=> ((int) ($b['start'] ?? 0));
    });

    $currentIndex = -1;
    foreach ($blocks as $i => $block) {
        if (($block['id'] ?? '') === $blockId) {
            $currentIndex = $i;
            break;
        }
    }
    if ($currentIndex < 0) {
        return ['ok' => false, 'message' => 'Không tìm thấy section cần di chuyển.'];
    }

    $current = $blocks[$currentIndex];
    $siblings = [];
    foreach ($blocks as $i => $block) {
        if ((int) ($block['depth'] ?? 1) !== (int) ($current['depth'] ?? 1)) {
            continue;
        }
        if (($block['parent_start'] ?? null) !== ($current['parent_start'] ?? null)) {
            continue;
        }
        $siblings[] = ['index' => $i, 'block' => $block];
    }

    $siblingPos = -1;
    foreach ($siblings as $i => $entry) {
        if (($entry['block']['id'] ?? '') === $blockId) {
            $siblingPos = $i;
            break;
        }
    }
    if ($siblingPos < 0) {
        return ['ok' => false, 'message' => 'Không xác định được thứ tự section.'];
    }

    $swapEntry = null;
    if ($direction === 'up' && $siblingPos > 0) {
        $swapEntry = $siblings[$siblingPos - 1];
    }
    if ($direction === 'down' && $siblingPos < count($siblings) - 1) {
        $swapEntry = $siblings[$siblingPos + 1];
    }
    if (!$swapEntry) {
        return ['ok' => false, 'message' => $direction === 'up' ? 'Section này đã ở trên cùng.' : 'Section này đã ở dưới cùng.'];
    }

    try {
        $src = (string) ($source['content'] ?? '');

        $other = $swapEntry['block'];
        $currentStart = (int) ($current['start'] ?? 0);
        $currentEnd = (int) ($current['end'] ?? 0);
        $otherStart = (int) ($other['start'] ?? 0);
        $otherEnd = (int) ($other['end'] ?? 0);

        if ($direction === 'up') {
            $firstStart = $otherStart;
            $firstEnd = $otherEnd;
            $secondStart = $currentStart;
            $secondEnd = $currentEnd;
            $firstHtml = substr($src, $firstStart, $firstEnd - $firstStart);
            $middle = substr($src, $firstEnd, $secondStart - $firstEnd);
            $secondHtml = substr($src, $secondStart, $secondEnd - $secondStart);
            $out = substr($src, 0, $firstStart) . $secondHtml . $middle . $firstHtml . substr($src, $secondEnd);
        } else {
            $firstStart = $currentStart;
            $firstEnd = $currentEnd;
            $secondStart = $otherStart;
            $secondEnd = $otherEnd;
            $firstHtml = substr($src, $firstStart, $firstEnd - $firstStart);
            $middle = substr($src, $firstEnd, $secondStart - $firstEnd);
            $secondHtml = substr($src, $secondStart, $secondEnd - $secondStart);
            $out = substr($src, 0, $firstStart) . $secondHtml . $middle . $firstHtml . substr($src, $secondEnd);
        }

        $write = front_editor_source_write($pageKey, $out);
        if (!($write['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($write['message'] ?? 'Di chuyển section thất bại.')];
        }

        try {
            $pdo = db();
            admin_front_session_boot();
            $adminUserId = isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
            $ins = $pdo->prepare("INSERT INTO front_editor_history
                (page_key, element_id, admin_user_id, action, old_text, new_text, content_before, content_after)
                VALUES (:page_key, :element_id, :admin_user_id, :action, :old_text, :new_text, :before, :after)");
            $ins->execute([
                ':page_key' => $pageKey,
                ':element_id' => $blockId,
                ':admin_user_id' => $adminUserId,
                ':action' => $direction === 'up' ? 'move_block_up' : 'move_block_down',
                ':old_text' => (string) ($current['preview'] ?? ''),
                ':new_text' => (string) ($other['preview'] ?? ''),
                ':before' => $src,
                ':after' => $out,
            ]);
        } catch (Throwable $e) {
        }

        return ['ok' => true, 'message' => $direction === 'up' ? 'Đã đưa section lên trên.' : 'Đã đưa section xuống dưới.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function front_editor_block_commit(string $pageKey, array $ops): array
{
    if ($pageKey === '') {
        return ['ok' => false, 'message' => 'Page không hợp lệ.'];
    }
    if (count($ops) === 0) {
        return ['ok' => false, 'message' => 'Không có thay đổi để lưu.'];
    }

    $done = 0;
    foreach ($ops as $index => $op) {
        if (!is_array($op)) {
            return ['ok' => false, 'message' => 'Dữ liệu thao tác không hợp lệ.'];
        }
        $type = isset($op['type']) ? (string) $op['type'] : '';
        $blockId = isset($op['blockId']) ? (string) $op['blockId'] : '';
        if ($blockId === '') {
            return ['ok' => false, 'message' => 'Thiếu block id ở thao tác #' . ($index + 1) . '.'];
        }

        if ($type === 'move') {
            $direction = isset($op['direction']) ? (string) $op['direction'] : 'up';
            $res = front_editor_block_move($pageKey, $blockId, $direction);
        } elseif ($type === 'delete') {
            $res = front_editor_block_delete($pageKey, $blockId);
        } elseif ($type === 'apply_template') {
            $templateId = isset($op['templateId']) ? (int) $op['templateId'] : 0;
            $res = front_editor_template_apply($pageKey, $blockId, $templateId);
        } else {
            return ['ok' => false, 'message' => 'Thao tác không hỗ trợ ở mục #' . ($index + 1) . '.'];
        }

        if (!($res['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($res['message'] ?? 'Lưu thay đổi thất bại.'),
                'failed_index' => $index,
                'applied_count' => $done,
            ];
        }
        $done++;
    }

    return ['ok' => true, 'message' => 'Đã lưu thay đổi.', 'applied_count' => $done];
}

function front_editor_render(string $pageKey): void
{
    return;
    if (!admin_front_is_logged_in()) {
        return;
    }
    $textItems = front_editor_scan($pageKey);
    $imageItems = front_editor_scan_images($pageKey);
    $iconItems = front_editor_scan_icons($pageKey);
    $blockItems = front_editor_scan_blocks($pageKey);
    if (count($textItems) === 0 && count($imageItems) === 0 && count($iconItems) === 0 && count($blockItems) === 0) {
        return;
    }
    $textPayload = [];
    foreach ($textItems as $it) {
        $textPayload[] = ['id' => $it['id'], 'tag' => $it['tag'], 'text' => $it['text'], 'occ' => (int) ($it['occ'] ?? 1)];
    }
    $imagePayload = [];
    foreach ($imageItems as $it) {
        $imagePayload[] = ['id' => $it['id'], 'url' => $it['url'], 'occ' => (int) ($it['occ'] ?? 1)];
    }
    $iconPayload = [];
    foreach ($iconItems as $it) {
        $iconPayload[] = ['id' => $it['id'], 'class' => $it['class'], 'icon_key' => $it['icon_key'], 'occ' => (int) ($it['occ'] ?? 1)];
    }
    $blockPayload = [];
    foreach ($blockItems as $it) {
        $blockPayload[] = [
            'id' => $it['id'],
            'tag' => $it['tag'],
            'id_attr' => $it['id_attr'],
            'class_attr' => $it['class_attr'],
            'text_key' => $it['text_key'],
            'preview' => $it['preview'],
            'occ' => (int) ($it['occ'] ?? 1),
        ];
    }
    ?>
    <style>
      .fe-editable{ position: relative !important; outline: 0 !important; }
      .fe-editable.fe-inline{ display: inline-block !important; }
      .fe-editable:hover{ outline: 2px dashed rgba(47,42,36,0.55) !important; outline-offset: 4px !important; }
      .fe-editable.fe-editing{ outline: 2px solid rgba(154,133,95,0.75) !important; outline-offset: 4px; }
      .fe-text{ outline: none !important; border: 0 !important; box-shadow: none !important; }
      .fe-text:focus{ outline: none !important; border: 0 !important; box-shadow: none !important; }
      .fe-toolbar-inline{
        position: absolute !important;
        top: auto !important;
        bottom: calc(100% + 18px) !important;
        right: auto !important;
        left: 0 !important;
        z-index: 2147483646 !important;
        display: none !important;
        gap: 6px !important;
        flex-wrap: nowrap !important;
        align-items: center !important;
        padding: 6px 8px !important;
        border-radius: 14px !important;
        border: 1px solid rgba(255,255,255,0.18) !important;
        background: rgba(9,16,34,0.82) !important;
        backdrop-filter: blur(14px) !important;
        -webkit-backdrop-filter: blur(14px) !important;
        box-shadow: 0 26px 80px rgba(0,0,0,0.30) !important;
        pointer-events: auto !important;
        white-space: nowrap !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        scrollbar-width: thin !important;
        max-width: min(calc(100vw - 24px), 760px) !important;
        isolation: isolate !important;
        contain: layout style paint !important;
        font: 700 11px/1.2 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
        text-transform: none !important;
        letter-spacing: normal !important;
      }
      .fe-toolbar-inline,
      .fe-toolbar-inline *,
      .fe-toolbar-inline *::before,
      .fe-toolbar-inline *::after{
        box-sizing: border-box !important;
      }
      .fe-toolbar-inline button,
      .fe-toolbar-inline select,
      .fe-toolbar-inline input{
        margin: 0 !important;
        font: inherit !important;
        line-height: inherit !important;
        text-transform: none !important;
        letter-spacing: normal !important;
        text-indent: 0 !important;
        text-shadow: none !important;
        box-shadow: none !important;
      }
      .fe-editable.fe-editing .fe-toolbar-inline{ display: flex !important; }
      .fe-toolbar-group{
        display:flex !important;
        align-items:center !important;
        gap:6px !important;
      }
      .fe-toolbar-sep{
        width:1px !important;
        align-self: stretch !important;
        min-height: 34px !important;
        background: rgba(255,255,255,0.14) !important;
        border-radius: 999px !important;
      }
      .fe-btn{
        all: unset !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        padding: 8px 10px !important;
        border-radius: 10px !important;
        border: 1px solid rgba(255,255,255,0.18) !important;
        background: rgba(255,255,255,0.08) !important;
        color: rgba(255,255,255,0.96) !important;
        font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
        font-weight: 800 !important;
        font-size: 11px !important;
        line-height: 1.2 !important;
        letter-spacing: 0.02em !important;
        cursor: pointer !important;
        user-select: none !important;
        -webkit-appearance: none !important;
        appearance: none !important;
      }
      .fe-btn:hover{ background: rgba(255,255,255,0.14) !important; }
      .fe-btn:disabled,
      .fe-toolbar-select:disabled,
      .fe-toolbar-color:disabled{
        opacity: 0.42 !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
      }
      .fe-btn.fe-danger{ border-color: rgba(255,120,120,0.35) !important; }
      .fe-btn.fe-danger:hover{ background: rgba(255,90,90,0.16) !important; }
      .fe-btn.is-icon{
        width: 34px !important;
        height: 34px !important;
        justify-content: center !important;
        padding: 0 !important;
        gap: 0 !important;
      }
      .fe-btn.is-icon.is-active{
        background: rgba(139,92,246,0.34) !important;
        border-color: rgba(167,139,250,0.52) !important;
      }
      .fe-toolbar-select,
      .fe-toolbar-color{
        all: unset !important;
        height: 34px !important;
        border-radius: 10px !important;
        border: 1px solid rgba(255,255,255,0.18) !important;
        background: rgba(255,255,255,0.08) !important;
        color: rgba(255,255,255,0.96) !important;
        padding: 0 8px !important;
        font: 700 11px/1.2 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
        -webkit-appearance: none !important;
        appearance: none !important;
      }
      .fe-toolbar-select option{
        color:#0f172a !important;
        background:#fff !important;
      }
      .fe-toolbar-color{
        width: 34px !important;
        padding: 3px !important;
      }
      .fe-toolbar-select{
        min-width: 78px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: space-between !important;
      }
      .fe-image-editable{
        cursor: pointer !important;
        transition: box-shadow 160ms ease, filter 160ms ease !important;
      }
      .fe-image-editable:hover{
        box-shadow: 0 0 0 3px rgba(59,130,246,0.42) !important;
        filter: saturate(1.05) !important;
      }
      .fe-image-editable.fe-image-loading{
        pointer-events: none !important;
        opacity: 0.72 !important;
      }
      .fe-icon-editable{
        cursor: pointer !important;
        transition: filter 160ms ease, transform 160ms ease !important;
      }
      .fe-icon-editable:hover{
        filter: drop-shadow(0 0 10px rgba(96,165,250,0.35)) !important;
        transform: translateY(-1px) !important;
      }
      .fe-block-editable{
        cursor: pointer !important;
        transition: box-shadow 160ms ease, outline-color 160ms ease !important;
      }
      .fe-block-editable:hover{
        box-shadow: inset 0 0 0 2px rgba(168,85,247,0.40) !important;
      }
      .fe-block-editable.fe-block-pending{
        box-shadow: inset 0 0 0 3px rgba(34,197,94,0.48) !important;
      }
      .fe-block-editable.fe-block-pending-delete{
        opacity: 0.32 !important;
        display: none !important;
      }
      .fe-context-item.is-primary{
        color: #86efac !important;
      }
      .fe-media-modal[hidden]{ display: none !important; }
      .fe-icon-modal[hidden]{ display: none !important; }
      .fe-media-modal{
        position: fixed !important;
        inset: 0 !important;
        z-index: 2147483647 !important;
        font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
      }
      .fe-icon-modal{
        position: fixed !important;
        inset: 0 !important;
        z-index: 2147483647 !important;
        font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
      }
      .fe-media-backdrop{
        position: absolute !important;
        inset: 0 !important;
        background: rgba(15,23,42,0.56) !important;
      }
      .fe-icon-backdrop{
        position: absolute !important;
        inset: 0 !important;
        background: rgba(15,23,42,0.56) !important;
      }
      .fe-media-dialog{
        position: relative !important;
        width: min(980px, calc(100vw - 24px)) !important;
        max-height: calc(100vh - 24px) !important;
        margin: 12px auto !important;
        background: #fff !important;
        border-radius: 18px !important;
        overflow: hidden !important;
        box-shadow: 0 30px 80px rgba(15,23,42,0.38) !important;
        display: flex !important;
        flex-direction: column !important;
      }
      .fe-icon-dialog{
        position: relative !important;
        width: min(760px, calc(100vw - 24px)) !important;
        max-height: calc(100vh - 24px) !important;
        margin: 12px auto !important;
        background: #fff !important;
        border-radius: 18px !important;
        overflow: hidden !important;
        box-shadow: 0 30px 80px rgba(15,23,42,0.38) !important;
        display: flex !important;
        flex-direction: column !important;
      }
      .fe-media-header,
      .fe-media-footer{
        padding: 14px 18px !important;
        border-bottom: 1px solid rgba(148,163,184,0.22) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
      }
      .fe-media-footer{
        border-top: 1px solid rgba(148,163,184,0.22) !important;
        border-bottom: 0 !important;
      }
      .fe-media-title{
        font-size: 24px !important;
        font-weight: 700 !important;
        color: #111827 !important;
      }
      .fe-icon-search{
        width: 100% !important;
        border: 1px solid rgba(148,163,184,0.32) !important;
        border-radius: 14px !important;
        padding: 12px 14px !important;
        font: 600 14px/1.4 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
        color: #0f172a !important;
        background: #fff !important;
        outline: none !important;
      }
      .fe-icon-search:focus{
        border-color: rgba(59,130,246,0.48) !important;
        box-shadow: 0 0 0 4px rgba(59,130,246,0.12) !important;
      }
      .fe-icon-grid{
        display: grid !important;
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
        gap: 12px !important;
        padding: 16px 0 4px !important;
        overflow: auto !important;
      }
      .fe-icon-card{
        all: unset !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 10px !important;
        min-height: 104px !important;
        padding: 14px 10px !important;
        border: 1px solid rgba(148,163,184,0.24) !important;
        border-radius: 16px !important;
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%) !important;
        color: #0f172a !important;
        cursor: pointer !important;
        text-align: center !important;
        transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease !important;
      }
      .fe-icon-card:hover{
        transform: translateY(-2px) !important;
        border-color: rgba(59,130,246,0.42) !important;
        box-shadow: 0 18px 36px rgba(15,23,42,0.12) !important;
      }
      .fe-icon-card.is-active{
        border-color: rgba(59,130,246,0.62) !important;
        box-shadow: 0 0 0 4px rgba(59,130,246,0.12) !important;
      }
      .fe-icon-card-preview{
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 50px !important;
        height: 50px !important;
        border-radius: 14px !important;
        background: rgba(37,99,235,0.10) !important;
        color: #1d4ed8 !important;
        font-size: 24px !important;
      }
      .fe-icon-card-label{
        font: 700 13px/1.35 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
        color: #0f172a !important;
      }
      .fe-icon-card-meta{
        font: 600 11px/1.3 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
        color: #64748b !important;
      }
      .fe-icon-empty{
        padding: 18px 0 6px !important;
        font: 600 14px/1.5 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
        color: #64748b !important;
      }
      .fe-icon-load-more{
        padding: 12px 0 2px !important;
        text-align: center !important;
        font: 700 12px/1.4 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
        color: #3b82f6 !important;
      }
      .fe-media-close{
        width: 42px !important;
        height: 42px !important;
        border-radius: 999px !important;
        border: 1px solid rgba(148,163,184,0.35) !important;
        background: #fff !important;
        color: #64748b !important;
        cursor: pointer !important;
      }
      .fe-media-body{
        padding: 14px 18px !important;
        overflow: auto !important;
      }
      .fe-media-toolbar{
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        margin-bottom: 16px !important;
      }
      .fe-media-filters{
        display: inline-flex !important;
        border: 1px solid rgba(148,163,184,0.45) !important;
        border-radius: 999px !important;
        overflow: hidden !important;
      }
      .fe-media-filter{
        border: 0 !important;
        background: #fff !important;
        color: #475569 !important;
        padding: 10px 18px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
      }
      .fe-media-filter.is-active{
        background: #6b7280 !important;
        color: #fff !important;
      }
      .fe-media-actions{
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 10px !important;
      }
      .fe-media-action{
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        border-radius: 14px !important;
        border: 1px solid rgba(59,130,246,0.45) !important;
        background: #fff !important;
        color: #2563eb !important;
        padding: 10px 16px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
      }
      .fe-media-action.is-secondary{
        border-color: rgba(148,163,184,0.45) !important;
        color: #64748b !important;
      }
      .fe-media-progress[hidden]{
        display: none !important;
      }
      .fe-media-progress{
        margin-bottom: 16px !important;
        padding: 12px 14px !important;
        border-radius: 14px !important;
        background: #f8fafc !important;
        border: 1px solid rgba(148,163,184,0.24) !important;
      }
      .fe-media-progress-head{
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 12px !important;
        margin-bottom: 8px !important;
        color: #475569 !important;
        font-size: 14px !important;
      }
      .fe-media-progress-text{
        color: #0f172a !important;
        font-weight: 700 !important;
      }
      .fe-media-progress-bar{
        width: 100% !important;
        height: 10px !important;
        border-radius: 999px !important;
        overflow: hidden !important;
        background: rgba(148,163,184,0.18) !important;
      }
      .fe-media-progress-fill{
        width: 0% !important;
        height: 100% !important;
        border-radius: inherit !important;
        background: linear-gradient(90deg, #2563eb, #60a5fa) !important;
        transition: width 160ms ease !important;
      }
      .fe-media-grid{
        display: grid !important;
        grid-template-columns: repeat(auto-fill, minmax(156px, 1fr)) !important;
        gap: 14px !important;
      }
      .fe-media-item{
        border: 0 !important;
        background: transparent !important;
        padding: 0 !important;
        cursor: pointer !important;
        text-align: left !important;
      }
      .fe-media-thumb{
        aspect-ratio: 1 / 1 !important;
        border-radius: 16px !important;
        overflow: hidden !important;
        border: 2px solid rgba(203,213,225,0.82) !important;
        background: #f8fafc !important;
      }
      .fe-media-item.is-active .fe-media-thumb{
        border-color: #2563eb !important;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.18) !important;
      }
      .fe-media-thumb img{
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        display: block !important;
      }
      .fe-media-meta{
        margin-top: 6px !important;
        color: #6b7280 !important;
        font-size: 14px !important;
      }
      .fe-media-empty{
        color: #64748b !important;
        padding: 24px 0 !important;
      }
      .fe-media-footer-note{
        color: #64748b !important;
        font-size: 14px !important;
      }
      .fe-context-menu[hidden]{ display:none !important; }
      .fe-context-menu{
        position: fixed !important;
        z-index: 2147483647 !important;
        min-width: 220px !important;
        padding: 8px !important;
        border-radius: 14px !important;
        border: 1px solid rgba(255,255,255,0.18) !important;
        background: rgba(15,23,42,0.96) !important;
        box-shadow: 0 20px 60px rgba(15,23,42,0.35) !important;
        backdrop-filter: blur(14px) !important;
      }
      .fe-context-item{
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        padding: 11px 12px !important;
        border: 0 !important;
        background: transparent !important;
        color: #f8fafc !important;
        border-radius: 10px !important;
        font: 600 14px/1.2 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
        text-align: left !important;
        cursor: pointer !important;
      }
      .fe-context-item:hover{ background: rgba(255,255,255,0.10) !important; }
      .fe-template-modal[hidden]{ display:none !important; }
      .fe-template-modal{
        position: fixed !important;
        inset: 0 !important;
        z-index: 2147483647 !important;
      }
      .fe-template-backdrop{
        position:absolute !important;
        inset:0 !important;
        background: rgba(15,23,42,0.58) !important;
      }
      .fe-template-dialog{
        position: relative !important;
        width: min(1120px, calc(100vw - 24px)) !important;
        max-height: calc(100vh - 24px) !important;
        margin: 12px auto !important;
        background: #fff !important;
        border-radius: 18px !important;
        overflow: hidden !important;
        box-shadow: 0 30px 80px rgba(15,23,42,0.38) !important;
        display: flex !important;
        flex-direction: column !important;
      }
      .fe-template-header,
      .fe-template-footer{
        padding: 14px 18px !important;
        border-bottom: 1px solid rgba(148,163,184,0.22) !important;
        display:flex !important;
        align-items:center !important;
        justify-content:space-between !important;
        gap:12px !important;
      }
      .fe-template-footer{
        border-top: 1px solid rgba(148,163,184,0.22) !important;
        border-bottom: 0 !important;
      }
      .fe-template-body{
        padding: 14px 18px !important;
        overflow:auto !important;
      }
      .fe-template-layout{
        display:grid !important;
        grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.9fr) !important;
        gap:18px !important;
      }
      .fe-template-list{
        display:grid !important;
        gap:12px !important;
      }
      .fe-template-card{
        border:1px solid rgba(148,163,184,0.24) !important;
        border-radius:16px !important;
        padding:14px !important;
        background:#fff !important;
      }
      .fe-template-card-head{
        display:flex !important;
        align-items:flex-start !important;
        justify-content:space-between !important;
        gap:12px !important;
        margin-bottom:8px !important;
      }
      .fe-template-name{
        font-weight:700 !important;
        color:#0f172a !important;
        font-size:16px !important;
      }
      .fe-template-meta{
        color:#64748b !important;
        font-size:12px !important;
      }
      .fe-template-preview{
        color:#475569 !important;
        font-size:14px !important;
        line-height:1.5 !important;
        margin-bottom:12px !important;
      }
      .fe-template-actions{
        display:flex !important;
        flex-wrap:wrap !important;
        gap:8px !important;
      }
      .fe-template-btn{
        display:inline-flex !important;
        align-items:center !important;
        gap:8px !important;
        border-radius:12px !important;
        border:1px solid rgba(148,163,184,0.30) !important;
        background:#fff !important;
        color:#0f172a !important;
        padding:9px 12px !important;
        font: 600 13px/1.2 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
        cursor:pointer !important;
      }
      .fe-template-btn.is-primary{
        border-color: rgba(59,130,246,0.42) !important;
        color:#2563eb !important;
      }
      .fe-template-btn.is-danger{
        border-color: rgba(239,68,68,0.32) !important;
        color:#dc2626 !important;
      }
      .fe-template-editor{
        border:1px solid rgba(148,163,184,0.24) !important;
        border-radius:16px !important;
        padding:14px !important;
        background:#f8fafc !important;
        display:grid !important;
        gap:10px !important;
      }
      .fe-template-editor input,
      .fe-template-editor textarea{
        width:100% !important;
        border:1px solid rgba(148,163,184,0.32) !important;
        border-radius:12px !important;
        padding:12px !important;
        font: 500 14px/1.5 Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial !important;
        background:#fff !important;
        color:#0f172a !important;
      }
      .fe-template-editor textarea{
        min-height: 280px !important;
        resize: vertical !important;
      }
      .fe-source-dialog{
        width: min(1240px, calc(100vw - 24px)) !important;
      }
      .fe-source-editor textarea{
        min-height: min(70vh, 760px) !important;
        font: 500 13px/1.6 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace !important;
        white-space: pre !important;
        tab-size: 2 !important;
      }
      .fe-template-empty{
        color:#64748b !important;
        padding:20px 0 !important;
      }
      @media (max-width: 680px){
        .fe-media-dialog{
          width: calc(100vw - 12px) !important;
          max-height: calc(100vh - 12px) !important;
          margin: 6px auto !important;
          border-radius: 14px !important;
        }
        .fe-media-header,
        .fe-media-body,
        .fe-media-footer{ padding: 12px !important; }
        .fe-media-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        .fe-media-title{ font-size: 20px !important; }
        .fe-template-dialog{
          width: calc(100vw - 12px) !important;
          max-height: calc(100vh - 12px) !important;
          margin: 6px auto !important;
        }
        .fe-icon-dialog{
          width: calc(100vw - 12px) !important;
          max-height: calc(100vh - 12px) !important;
          margin: 6px auto !important;
        }
        .fe-template-layout{ grid-template-columns: 1fr !important; }
        .fe-icon-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
      }
    </style>
    <div class="fe-context-menu" id="feContextMenu" hidden>
      <button type="button" class="fe-context-item is-primary" data-fe-menu-act="save-page">
        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
        <span>Lưu thay đổi</span>
      </button>
      <button type="button" class="fe-context-item" data-fe-menu-act="edit-page-source">
        <i class="fa-solid fa-code" aria-hidden="true"></i>
        <span>Sửa toàn trang</span>
      </button>
      <button type="button" class="fe-context-item" data-fe-menu-act="change-image" hidden>
        <i class="fa-solid fa-image" aria-hidden="true"></i>
        <span>Đổi ảnh</span>
      </button>
      <button type="button" class="fe-context-item" data-fe-menu-act="save-template-all">
        <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
        <span>Lưu tất cả template</span>
      </button>
      <button type="button" class="fe-context-item" data-fe-menu-act="move-up">
        <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
        <span>Đưa lên</span>
      </button>
      <button type="button" class="fe-context-item" data-fe-menu-act="move-down">
        <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
        <span>Đưa xuống</span>
      </button>
      <button type="button" class="fe-context-item" data-fe-menu-act="save-template">
        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
        <span>Lưu template</span>
      </button>
      <button type="button" class="fe-context-item" data-fe-menu-act="use-template">
        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
        <span>Sử dụng template</span>
      </button>
      <button type="button" class="fe-context-item" data-fe-menu-act="delete-block">
        <i class="fa-solid fa-trash" aria-hidden="true"></i>
        <span>Xoá section</span>
      </button>
    </div>
    <div class="fe-media-modal" id="feMediaModal" hidden>
      <div class="fe-media-backdrop" data-fe-media-close="1"></div>
      <div class="fe-media-dialog" role="dialog" aria-modal="true" aria-labelledby="feMediaTitle">
        <div class="fe-media-header">
          <div class="fe-media-title" id="feMediaTitle">Thư viện</div>
          <button type="button" class="fe-media-close" data-fe-media-close="1" aria-label="Đóng">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
        <div class="fe-media-body">
          <div class="fe-media-toolbar">
            <div class="fe-media-filters" role="group" aria-label="Lọc media">
              <button type="button" class="fe-media-filter is-active" data-fe-media-filter="image">Ảnh</button>
              <button type="button" class="fe-media-filter" data-fe-media-filter="all">Tất cả</button>
            </div>
            <div class="fe-media-actions">
              <label class="fe-media-action" for="feMediaUploadInput">
                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                <span>Upload ảnh</span>
              </label>
              <input id="feMediaUploadInput" type="file" accept="image/*" hidden>
              <button type="button" class="fe-media-action is-secondary" id="feMediaReload">
                <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                <span>Reload</span>
              </button>
            </div>
          </div>
          <div class="fe-media-progress" id="feMediaUploadProgress" hidden>
            <div class="fe-media-progress-head">
              <span id="feMediaUploadProgressLabel">Đang tải lên...</span>
              <span class="fe-media-progress-text" id="feMediaUploadProgressText">0%</span>
            </div>
            <div class="fe-media-progress-bar" role="progressbar" aria-label="Tiến trình upload ảnh" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
              <div class="fe-media-progress-fill" id="feMediaUploadProgressBar"></div>
            </div>
          </div>
          <div class="fe-media-grid" id="feMediaGrid"></div>
          <div class="fe-media-empty" id="feMediaEmpty" hidden>Chưa có file trong thư viện.</div>
        </div>
        <div class="fe-media-footer">
          <div class="fe-media-footer-note">Click vào ảnh để chọn hoặc upload ảnh mới.</div>
          <button type="button" class="fe-media-action is-secondary" data-fe-media-close="1">Đóng</button>
        </div>
      </div>
    </div>
    <div class="fe-icon-modal" id="feIconModal" hidden>
      <div class="fe-icon-backdrop" data-fe-icon-close="1"></div>
      <div class="fe-icon-dialog" role="dialog" aria-modal="true" aria-labelledby="feIconTitle">
        <div class="fe-media-header">
          <div>
            <div class="fe-media-title" id="feIconTitle">Chọn icon</div>
            <div class="fe-media-footer-note">Click vào icon để thay cho icon đang chọn.</div>
          </div>
          <button type="button" class="fe-media-close" data-fe-icon-close="1" aria-label="Đóng">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
        <div class="fe-media-body" id="feIconBody">
          <input type="search" class="fe-icon-search" id="feIconSearch" placeholder="Tìm icon: phone, tooth, calendar...">
          <div class="fe-icon-grid" id="feIconGrid"></div>
          <div class="fe-icon-empty" id="feIconEmpty" hidden>Không tìm thấy icon phù hợp.</div>
          <div class="fe-icon-load-more" id="feIconLoadMore" hidden>Kéo xuống để tải thêm icon.</div>
        </div>
        <div class="fe-media-footer">
          <div class="fe-media-footer-note">Hỗ trợ Font Awesome và Phosphor.</div>
          <button type="button" class="fe-media-action is-secondary" data-fe-icon-close="1">Đóng</button>
        </div>
      </div>
    </div>
    <div class="fe-template-modal" id="feTemplateModal" hidden>
      <div class="fe-template-backdrop" data-fe-template-close="1"></div>
      <div class="fe-template-dialog" role="dialog" aria-modal="true" aria-labelledby="feTemplateTitle">
        <div class="fe-template-header">
          <div>
            <div class="fe-media-title" id="feTemplateTitle">Template</div>
            <div class="fe-media-footer-note">Lưu block hiện tại thành template hoặc áp dụng template đã có.</div>
          </div>
          <button type="button" class="fe-media-close" data-fe-template-close="1" aria-label="Đóng">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
        <div class="fe-template-body">
          <div class="fe-template-layout">
            <div>
              <div class="fe-template-list" id="feTemplateList"></div>
              <div class="fe-template-empty" id="feTemplateEmpty" hidden>Chưa có template nào.</div>
            </div>
            <div class="fe-template-editor">
              <div class="fe-template-name">Sửa template</div>
              <input id="feTemplateEditName" type="text" placeholder="Tên template">
              <textarea id="feTemplateEditHtml" placeholder="Nội dung HTML template"></textarea>
              <div class="fe-template-actions">
                <button type="button" class="fe-template-btn is-primary" id="feTemplateSaveEdit">
                  <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                  <span>Lưu chỉnh sửa</span>
                </button>
                <button type="button" class="fe-template-btn" id="feTemplateCancelEdit">
                  <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                  <span>Huỷ</span>
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="fe-template-footer">
          <div class="fe-media-footer-note">Chuột phải lên block để mở menu template.</div>
          <button type="button" class="fe-media-action is-secondary" data-fe-template-close="1">Đóng</button>
        </div>
      </div>
    </div>
    <div class="fe-template-modal fe-source-modal" id="feSourceModal" hidden>
      <div class="fe-template-backdrop" data-fe-source-close="1"></div>
      <div class="fe-template-dialog fe-source-dialog" role="dialog" aria-modal="true" aria-labelledby="feSourceTitle">
        <div class="fe-template-header">
          <div>
            <div class="fe-media-title" id="feSourceTitle">Sửa toàn trang</div>
            <div class="fe-media-footer-note" id="feSourceMeta">Đang tải source...</div>
          </div>
          <button type="button" class="fe-media-close" data-fe-source-close="1" aria-label="Đóng">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>
        </div>
        <div class="fe-template-body">
          <div class="fe-template-editor fe-source-editor">
            <textarea id="feSourceEditor" spellcheck="false" autocapitalize="off" autocomplete="off" autocorrect="off" placeholder="Đang tải source..."></textarea>
          </div>
        </div>
        <div class="fe-template-footer">
          <div class="fe-media-footer-note">File PHP sẽ sửa theo dạng code. Nội dung bài/post sẽ sửa theo dạng HTML.</div>
          <div class="fe-template-actions">
            <button type="button" class="fe-template-btn" data-fe-source-close="1">Đóng</button>
            <button type="button" class="fe-template-btn is-primary" id="feSourceSave">
              <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
              <span>Lưu toàn trang</span>
            </button>
          </div>
        </div>
      </div>
    </div>
    <script>
      (function(){
        var pageKey = <?php echo json_encode($pageKey, JSON_UNESCAPED_UNICODE); ?>;
        var textItems = <?php echo json_encode($textPayload, JSON_UNESCAPED_UNICODE); ?>;
        var imageItems = <?php echo json_encode($imagePayload, JSON_UNESCAPED_UNICODE); ?>;
        var iconItems = <?php echo json_encode($iconPayload, JSON_UNESCAPED_UNICODE); ?>;
        var blockItems = <?php echo json_encode($blockPayload, JSON_UNESCAPED_UNICODE); ?>;

        function norm(s){ return String(s||"").replace(/\s+/g," ").trim(); }
        function normClass(s){ return String(s||"").split(/\s+/).filter(Boolean).join(" "); }
        function esc(s){ return String(s == null ? "" : s).replace(/[&<>"']/g, function(c){ return ({ "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;" })[c]; }); }
        function eventTargetElement(target){
          if (!target) return null;
          if (target.nodeType === 1) return target;
          if (target.nodeType === 3 && target.parentElement) return target.parentElement;
          return target.parentElement || null;
        }
        function isInsideLink(el){ return !!(el && el.closest && el.closest("a")); }
        function textKey(s){ return norm(String(s || "")).slice(0, 80); }
        function resolveUrl(url){
          try { return new URL(String(url || ""), window.location.href).href; } catch (e) { return String(url || ""); }
        }
        function formatBytes(n){
          var num = Number(n || 0);
          if (num < 1024) return num + " B";
          var kb = num / 1024;
          if (kb < 1024) return kb.toFixed(1) + " KB";
          return (kb / 1024).toFixed(1) + " MB";
        }
        async function postJson(url, payload){
          var res = await fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: payload ? JSON.stringify(payload) : null
          });
          var data = await res.json().catch(function(){ return {}; });
          return { res: res, data: data };
        }
        function uploadFileWithProgress(file, options){
          return new Promise(function(resolve){
            if (!file) {
              resolve({ res: { ok: false, status: 400 }, data: { ok: false, message: "Thiếu file upload." } });
              return;
            }
            var opts = options || {};
            var fd = new FormData();
            fd.append("file", file);
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "/admin/api/media/upload.php", true);
            xhr.responseType = "json";

            if (xhr.upload && typeof opts.onProgress === "function") {
              xhr.upload.addEventListener("progress", function(event){
                if (!event.lengthComputable) return;
                var percent = Math.round((event.loaded / event.total) * 100);
                opts.onProgress(percent, event);
              });
            }

            xhr.addEventListener("load", function(){
              var data = xhr.response;
              if (!data || typeof data !== "object") {
                try {
                  data = JSON.parse(xhr.responseText || "{}");
                } catch (e) {
                  data = {};
                }
              }
              resolve({
                res: { ok: xhr.status >= 200 && xhr.status < 300, status: xhr.status },
                data: data
              });
            });

            xhr.addEventListener("error", function(){
              resolve({
                res: { ok: false, status: 0 },
                data: { ok: false, message: "Không kết nối được đến máy chủ upload." }
              });
            });

            xhr.send(fd);
          });
        }
        function setMediaUploadProgress(percent, label){
          if (!modalUploadProgress || !modalUploadProgressBar || !modalUploadProgressText) return;
          var value = Math.max(0, Math.min(100, Math.round(Number(percent || 0))));
          modalUploadProgress.hidden = false;
          modalUploadProgressBar.style.width = value + "%";
          modalUploadProgressBar.parentElement.setAttribute("aria-valuenow", String(value));
          modalUploadProgressText.textContent = value + "%";
          if (modalUploadProgressLabel && label) {
            modalUploadProgressLabel.textContent = label;
          }
        }
        function resetMediaUploadProgress(){
          if (!modalUploadProgress || !modalUploadProgressBar || !modalUploadProgressText) return;
          modalUploadProgress.hidden = true;
          modalUploadProgressBar.style.width = "0%";
          modalUploadProgressBar.parentElement.setAttribute("aria-valuenow", "0");
          modalUploadProgressText.textContent = "0%";
          if (modalUploadProgressLabel) {
            modalUploadProgressLabel.textContent = "Đang tải lên...";
          }
        }
        async function uploadImage(file){
          if (!file) return null;
          if (modalReload) modalReload.disabled = true;
          setMediaUploadProgress(0, "Đang tải lên " + file.name + "...");
          var out = null;
          try {
            out = await uploadFileWithProgress(file, {
              onProgress: function(percent){
                setMediaUploadProgress(percent, "Đang tải lên " + file.name + "...");
              }
            });
          } finally {
            if (modalReload) modalReload.disabled = false;
          }
          var res = out && out.res ? out.res : { ok: false };
          var data = out && out.data ? out.data : {};
          if (!res.ok || !data.ok) {
            resetMediaUploadProgress();
            alert((data && data.message) ? data.message : "Upload thất bại.");
            return null;
          }
          setMediaUploadProgress(100, "Đã tải lên " + file.name);
          setTimeout(resetMediaUploadProgress, 600);
          return data.file || null;
        }
        function buildTextIndex(){
          var idx = new Map();
          for (var i = 0; i < textItems.length; i++) {
            var it = textItems[i];
            var k = String(it.tag || "") + "|" + norm(it.text);
            if (!idx.has(k)) idx.set(k, []);
            idx.get(k).push(it);
          }
          return idx;
        }
        function findDirectEditableTextNode(el){
          if (!el || !el.childNodes) return null;
          var found = null;
          for (var i = 0; i < el.childNodes.length; i++) {
            var node = el.childNodes[i];
            if (!node || node.nodeType !== Node.TEXT_NODE) continue;
            if (!norm(node.nodeValue || "")) continue;
            found = node;
          }
          return found;
        }
        function shouldStayOutsideEditable(node){
          if (!node || node.nodeType !== 1) return false;
          var tag = String(node.tagName || "").toUpperCase();
          if (/^(I|IMG|SVG|PICTURE|VIDEO|AUDIO|SOURCE|USE)$/.test(tag)) return true;
          if (node.getAttribute && node.getAttribute("aria-hidden") === "true" && !norm(node.textContent || "")) return true;
          return false;
        }
        function mountEditableText(el, original){
          if (!el) return null;
          var textWrap = document.createElement("span");
          textWrap.className = "fe-text";
          textWrap.contentEditable = "false";
          var nodes = Array.prototype.slice.call(el.childNodes || []);
          var inserted = false;
          var moved = false;
          for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (!node) continue;
            if (node.nodeType === Node.TEXT_NODE) {
              if (!norm(node.nodeValue || "")) continue;
              if (!inserted) {
                el.insertBefore(textWrap, node);
                inserted = true;
              }
              textWrap.appendChild(node);
              moved = true;
              continue;
            }
            if (node.nodeType === Node.ELEMENT_NODE && !shouldStayOutsideEditable(node) && norm(node.textContent || "")) {
              if (!inserted) {
                el.insertBefore(textWrap, node);
                inserted = true;
              }
              textWrap.appendChild(node);
              moved = true;
            }
          }
          if (!moved) {
            textWrap.textContent = original;
            el.appendChild(textWrap);
          } else if (!inserted) {
            el.appendChild(textWrap);
          }
          return textWrap;
        }
        function stripEditorAttributes(node){
          if (!node || node.nodeType !== 1) return;
          node.removeAttribute("contenteditable");
          node.removeAttribute("spellcheck");
          node.classList.remove("fe-text");
          var nested = node.querySelectorAll ? node.querySelectorAll("[contenteditable],[spellcheck],.fe-text") : [];
          Array.prototype.forEach.call(nested, function(child){
            child.removeAttribute("contenteditable");
            child.removeAttribute("spellcheck");
            child.classList.remove("fe-text");
          });
        }
        function serializeEditableHtml(textEl){
          if (!textEl) return "";
          normalizeEditorFonts(textEl);
          var clone = textEl.cloneNode(true);
          stripEditorAttributes(clone);
          var inner = clone.innerHTML || "";
          var style = clone.getAttribute("style") || "";
          if (style) {
            return '<span style="' + esc(style) + '">' + inner + '</span>';
          }
          return inner;
        }
        function restoreEditableHtml(textEl, html, plainText){
          if (!textEl) return;
          var nextHtml = String(html || "").trim();
          if (nextHtml !== "") {
            textEl.innerHTML = nextHtml;
          } else {
            textEl.textContent = String(plainText || "");
          }
        }
        function applyExecCommand(command, value){
          try {
            document.execCommand("styleWithCSS", false, true);
          } catch (e) {}
          try {
            document.execCommand(command, false, value);
          } catch (e) {}
        }
        function wrapSelectionWithSpan(styleMap){
          if (!activeText) return;
          var sel = window.getSelection ? window.getSelection() : null;
          if (!sel || sel.rangeCount === 0 || sel.isCollapsed) {
            Object.keys(styleMap || {}).forEach(function(key){
              activeText.style[key] = styleMap[key];
            });
            return;
          }
          var range = sel.getRangeAt(0);
          var marker = document.createElement("span");
          Object.keys(styleMap || {}).forEach(function(key){
            marker.style[key] = styleMap[key];
          });
          try {
            range.surroundContents(marker);
          } catch (e) {
            marker.appendChild(range.extractContents());
            range.insertNode(marker);
          }
          sel.removeAllRanges();
          var nextRange = document.createRange();
          nextRange.selectNodeContents(marker);
          sel.addRange(nextRange);
        }
        function normalizeEditorFonts(root){
          if (!root || !root.querySelectorAll) return;
          var fonts = root.querySelectorAll("font[size]");
          Array.prototype.forEach.call(fonts, function(node){
            var size = String(node.getAttribute("size") || "3");
            var mapped = {
              "1": "12px",
              "2": "14px",
              "3": "16px",
              "4": "18px",
              "5": "24px",
              "6": "32px",
              "7": "40px"
            }[size] || "16px";
            var span = document.createElement("span");
            span.style.fontSize = mapped;
            span.innerHTML = node.innerHTML;
            node.parentNode.replaceChild(span, node);
          });
        }
        function buildImageIndex(){
          var idx = new Map();
          for (var i = 0; i < imageItems.length; i++) {
            var it = imageItems[i];
            var key = resolveUrl(it.url || "");
            if (!key) continue;
            if (!idx.has(key)) idx.set(key, []);
            idx.get(key).push(it);
          }
          return idx;
        }
        function isIconClassToken(token){
          token = String(token || "").trim().toLowerCase();
          if (!token) return false;
          if (token === "ph" || token === "ph-fill" || token === "ph-bold" || token === "ph-duotone" || token === "ph-light" || token === "ph-thin") return true;
          return token.indexOf("fa-") === 0 || token.indexOf("ph-") === 0;
        }
        function iconSignature(className){
          var tokens = String(className || "").split(/\s+/).filter(function(token){
            return isIconClassToken(token);
          }).map(function(token){
            return String(token || "").toLowerCase();
          }).filter(Boolean);
          tokens = tokens.filter(function(token, index){ return tokens.indexOf(token) === index; }).sort();
          return tokens.join(" ");
        }
        function buildIconIndex(){
          var idx = new Map();
          for (var i = 0; i < iconItems.length; i++) {
            var it = iconItems[i];
            var key = iconSignature(it.class || it.icon_key || "");
            if (!key) continue;
            if (!idx.has(key)) idx.set(key, []);
            idx.get(key).push(it);
          }
          return idx;
        }
        function mergeIconClasses(existingClassName, iconClassName){
          var keep = String(existingClassName || "").split(/\s+/).filter(function(token){
            return token && !isIconClassToken(token);
          });
          var icons = String(iconClassName || "").split(/\s+/).filter(function(token){
            return token && isIconClassToken(token);
          });
          var next = keep.concat(icons).filter(function(token, index, arr){
            return arr.indexOf(token) === index;
          });
          return next.join(" ").trim();
        }
        function buildIconEntries(family, items){
          return items.map(function(item){
            return {
              label: item[0],
              family: family,
              terms: item[1],
              className: item[2]
            };
          });
        }
        function titleizeIconSlug(slug){
          return String(slug || "").split("-").filter(Boolean).map(function(part){
            return part.length <= 2
              ? part.toUpperCase()
              : part.charAt(0).toUpperCase() + part.slice(1);
          }).join(" ");
        }
        function normalizeIconLabel(label, className){
          var out = String(label || "").trim();
          if (out) return out;
          var match = String(className || "").match(/\b(?:fa|ph)-(?:solid|regular|brands|fill|bold|duotone|light|thin)\b\s+\b(?:fa|ph)-([a-z0-9-]+)/i);
          return titleizeIconSlug(match ? match[1] : "");
        }
        function normalizeIconTerms(terms, label, className, family){
          var bits = [];
          if (terms) bits.push(String(terms || ""));
          if (label) bits.push(String(label || ""));
          if (className) bits.push(String(className || "").replace(/\s+/g, " "));
          if (family) bits.push(String(family || ""));
          return bits.join(" ").toLowerCase().trim();
        }
        function dedupeIconLibrary(items){
          var seen = new Set();
          var out = [];
          for (var i = 0; i < items.length; i++) {
            var item = items[i] || {};
            var className = String(item.className || "").trim();
            if (!className || seen.has(className)) continue;
            seen.add(className);
            out.push({
              label: normalizeIconLabel(item.label || "", className),
              family: String(item.family || "").trim() || "Icon",
              terms: normalizeIconTerms(item.terms || "", item.label || "", className, item.family || ""),
              className: className
            });
          }
          out.sort(function(a, b){
            var fa = String(a.family || "");
            var fb = String(b.family || "");
            if (fa !== fb) return fa.localeCompare(fb);
            return String(a.label || "").localeCompare(String(b.label || ""));
          });
          return out;
        }
        function buildFontAwesomeStyleClass(iconName){
          var brands = new Set([
            "facebook", "facebook-f", "facebook-messenger", "instagram", "youtube", "tiktok", "x-twitter", "twitter", "linkedin", "linkedin-in",
            "telegram", "telegram-plane", "pinterest", "pinterest-p", "github", "gitlab", "behance", "dribbble", "threads", "whatsapp",
            "snapchat", "snapchat-ghost", "google", "google-plus-g", "apple", "android", "wordpress", "viber", "skype", "spotify"
          ]);
          var regular = new Set(["clock", "calendar", "circle", "heart", "star", "bell", "message", "comment", "face-smile"]);
          if (brands.has(String(iconName || ""))) return "fa-brands";
          if (regular.has(String(iconName || ""))) return "fa-regular";
          return "fa-solid";
        }
        function extractFontAwesomeIcons(cssText){
          var found = [];
          var seen = new Set();
          var re = /\.fa-([a-z0-9-]+):before\{content:/g;
          var skip = new Set([
            "0","1","2","3","4","5","6","7","8","9","a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z"
          ]);
          var match;
          while ((match = re.exec(String(cssText || "")))) {
            var name = String(match[1] || "").trim().toLowerCase();
            if (!name || skip.has(name) || seen.has(name)) continue;
            if (/^(xs|sm|lg|xl|2xl|spin|pulse|beat|fade|bounce|shake|flip|rotate|stack|layers|fw|ul|li|border|pull-left|pull-right)$/.test(name)) continue;
            seen.add(name);
            found.push({
              label: titleizeIconSlug(name),
              family: "Font Awesome",
              terms: name.replace(/-/g, " ") + " font awesome",
              className: buildFontAwesomeStyleClass(name) + " fa-" + name
            });
          }
          return found;
        }
        function extractPhosphorIcons(cssText, familyLabel, baseClass){
          var found = [];
          var seen = new Set();
          var re = new RegExp("\\." + baseClass.replace("-", "\\-") + "\\.ph-([a-z0-9-]+):before\\s*\\{", "g");
          var match;
          while ((match = re.exec(String(cssText || "")))) {
            var name = String(match[1] || "").trim().toLowerCase();
            if (!name || seen.has(name)) continue;
            seen.add(name);
            found.push({
              label: titleizeIconSlug(name),
              family: familyLabel,
              terms: name.replace(/-/g, " ") + " phosphor",
              className: baseClass + " ph-" + name
            });
          }
          return found;
        }
        function iconStylesheetUrls(){
          var links = Array.prototype.slice.call(document.querySelectorAll('link[rel="stylesheet"][href]'));
          var out = { fontAwesome: "", phosphor: [], seen: new Set() };
          for (var i = 0; i < links.length; i++) {
            var href = String(links[i].href || "").trim();
            if (!href) continue;
            if (!out.fontAwesome && href.indexOf("font-awesome") !== -1) {
              out.fontAwesome = href;
            }
            if (href.indexOf("@phosphor-icons/web") !== -1 && !out.seen.has(href)) {
              out.seen.add(href);
              out.phosphor.push(href);
            }
          }
          return out;
        }
        async function fetchIconCssText(url){
          var res = await fetch(url, { method: "GET", mode: "cors", credentials: "omit" });
          if (!res.ok) throw new Error("HTTP " + res.status);
          return await res.text();
        }
        var fallbackIconLibrary = []
          .concat(buildIconEntries("Font Awesome", [
            ["Tooth", "tooth dental dentist", "fa-solid fa-tooth"],
            ["Teeth", "teeth smile dental", "fa-solid fa-teeth"],
            ["Teeth Open", "teeth open smile", "fa-solid fa-teeth-open"],
            ["Calendar", "calendar booking appointment", "fa-solid fa-calendar-check"],
            ["Calendar Days", "calendar date schedule", "fa-solid fa-calendar-days"],
            ["Clock", "clock time schedule", "fa-regular fa-clock"],
            ["Phone", "phone hotline call", "fa-solid fa-phone"],
            ["Phone Volume", "phone hotline support", "fa-solid fa-phone-volume"],
            ["Message", "comment chat support", "fa-solid fa-comment-dots"],
            ["Comments", "comments chat support", "fa-solid fa-comments"],
            ["Envelope", "email contact envelope", "fa-solid fa-envelope"],
            ["Paper Plane", "send message plane", "fa-solid fa-paper-plane"],
            ["Location", "map pin location address", "fa-solid fa-location-dot"],
            ["Map", "map address branch", "fa-solid fa-map-location-dot"],
            ["Star", "star rating trust", "fa-solid fa-star"],
            ["Heart", "heart care love", "fa-solid fa-heart"],
            ["Heart Hand", "heart care love hand", "fa-solid fa-hand-holding-heart"],
            ["Shield", "shield safety care", "fa-solid fa-shield-heart"],
            ["Gem", "gem premium luxury", "fa-solid fa-gem"],
            ["Magic", "magic sparkle aesthetic", "fa-solid fa-wand-magic-sparkles"],
            ["Microscope", "microscope technology", "fa-solid fa-microscope"],
            ["Hospital", "hospital clinic patient", "fa-solid fa-hospital"],
            ["Hospital User", "hospital clinic patient", "fa-solid fa-hospital-user"],
            ["Doctor", "doctor dentist staff", "fa-solid fa-user-doctor"],
            ["Users", "users team patient", "fa-solid fa-users"],
            ["Award", "award quality top", "fa-solid fa-award"],
            ["Camera", "camera gallery before after", "fa-solid fa-camera-retro"],
            ["Image", "image photo media", "fa-solid fa-image"],
            ["Images", "images gallery media", "fa-solid fa-images"],
            ["Play", "video play media", "fa-solid fa-circle-play"],
            ["Video", "video media play", "fa-solid fa-video"],
            ["Headset", "support service hotline", "fa-solid fa-headset"],
            ["Globe", "global language website", "fa-solid fa-globe"],
            ["Language", "language translate locale", "fa-solid fa-language"],
            ["House", "home main page", "fa-solid fa-house"],
            ["Check", "check success done", "fa-solid fa-check"],
            ["Circle Check", "check success done", "fa-solid fa-circle-check"],
            ["Arrow Right", "arrow next right", "fa-solid fa-arrow-right"],
            ["Arrow Left", "arrow previous left", "fa-solid fa-arrow-left"],
            ["Arrow Up", "arrow up move", "fa-solid fa-arrow-up"],
            ["Arrow Down", "arrow down move", "fa-solid fa-arrow-down"],
            ["Chevron Left", "chevron left slider", "fa-solid fa-chevron-left"],
            ["Chevron Right", "chevron right slider", "fa-solid fa-chevron-right"],
            ["Bars", "menu navigation", "fa-solid fa-bars"],
            ["Xmark", "close remove xmark", "fa-solid fa-xmark"],
            ["Plus", "plus add", "fa-solid fa-plus"],
            ["Minus", "minus remove", "fa-solid fa-minus"],
            ["Quote Left", "quote testimonial", "fa-solid fa-quote-left"],
            ["Quote Right", "quote testimonial", "fa-solid fa-quote-right"],
            ["Facebook", "facebook social", "fa-brands fa-facebook-f"],
            ["Instagram", "instagram social", "fa-brands fa-instagram"],
            ["Youtube", "youtube social video", "fa-brands fa-youtube"]
          ]))
          .concat(buildIconEntries("Phosphor", [
            ["Tooth", "tooth dental dentist", "ph ph-tooth"],
            ["Tooth Fill", "tooth dental dentist", "ph-fill ph-tooth"],
            ["Calendar", "calendar booking appointment", "ph ph-calendar-check"],
            ["Calendar Plus", "calendar booking add", "ph ph-calendar-plus"],
            ["Phone", "phone hotline call", "ph ph-phone-call"],
            ["Phone Plus", "phone hotline booking", "ph ph-phone-plus"],
            ["Chat", "chat message support", "ph ph-chat-circle-dots"],
            ["Envelope", "email envelope contact", "ph ph-envelope-simple"],
            ["Map", "map pin location address", "ph ph-map-pin-area"],
            ["Clock", "clock countdown time schedule", "ph ph-clock-countdown"],
            ["House", "home main page", "ph ph-house"],
            ["Heart", "heart care love", "ph ph-heart"],
            ["Heart Fill", "heart care love", "ph-fill ph-heart"],
            ["Star", "star rating trust", "ph ph-star"],
            ["Star Fill", "star rating trust", "ph-fill ph-star"],
            ["Sparkle", "sparkle beauty magic", "ph ph-sparkle"],
            ["Shield Check", "shield safety check", "ph ph-shield-check"],
            ["Check Circle", "check success done", "ph ph-check-circle"],
            ["X Circle", "close remove error", "ph ph-x-circle"],
            ["Arrow Right", "arrow next right", "ph ph-arrow-right"],
            ["Arrow Left", "arrow previous left", "ph ph-arrow-left"],
            ["Arrow Up", "arrow up move", "ph ph-arrow-up"],
            ["Arrow Down", "arrow down move", "ph ph-arrow-down"],
            ["Caret Left", "chevron caret left", "ph ph-caret-left"],
            ["Caret Right", "chevron caret right", "ph ph-caret-right"],
            ["Camera", "camera gallery before after", "ph ph-camera"],
            ["Image", "image photo media", "ph ph-image"],
            ["Images", "images gallery media", "ph ph-images"],
            ["Play", "video play media", "ph ph-play-circle"],
            ["Video", "video media play", "ph ph-video-camera"],
            ["User", "user patient profile", "ph ph-user"],
            ["Users", "users team group", "ph ph-users-three"],
            ["Medal", "award quality top", "ph ph-medal"],
            ["Globe", "global language website", "ph ph-globe"],
            ["Bell", "notification reminder bell", "ph ph-bell"],
            ["Scissors", "beauty treatment trim", "ph ph-scissors"],
            ["Facebook", "facebook social", "ph-fill ph-facebook-logo"],
            ["Instagram", "instagram social", "ph-fill ph-instagram-logo"],
            ["Youtube", "youtube social video", "ph-fill ph-youtube-logo"],
            ["Whatsapp", "whatsapp chat support", "ph-fill ph-whatsapp-logo"]
          ]));
        var iconLibrary = dedupeIconLibrary(fallbackIconLibrary.slice());
        var iconLibraryReady = false;
        var iconLibraryPromise = null;
        async function ensureIconLibraryLoaded(){
          if (iconLibraryReady) return iconLibrary;
          if (iconLibraryPromise) return iconLibraryPromise;
          if (iconLoadMore) {
            iconLoadMore.hidden = false;
            iconLoadMore.textContent = "Dang tai full icon...";
          }
          iconLibraryPromise = (async function(){
            var urls = iconStylesheetUrls();
            var collected = fallbackIconLibrary.slice();
            var tasks = [];
            if (urls.fontAwesome) {
              tasks.push(fetchIconCssText(urls.fontAwesome).then(function(cssText){
                collected = collected.concat(extractFontAwesomeIcons(cssText));
              }));
            }
            for (var i = 0; i < urls.phosphor.length; i++) {
              (function(url){
                tasks.push(fetchIconCssText(url).then(function(cssText){
                  var isFill = url.indexOf("/fill/") !== -1;
                  collected = collected.concat(extractPhosphorIcons(cssText, isFill ? "Phosphor Fill" : "Phosphor", isFill ? "ph-fill" : "ph"));
                }));
              })(urls.phosphor[i]);
            }
            await Promise.allSettled(tasks);
            iconLibrary = dedupeIconLibrary(collected);
            iconLibraryReady = true;
            iconLibraryPromise = null;
            return iconLibrary;
          })();
          return iconLibraryPromise;
        }
        function buildBlockIndex(){
          var idx = new Map();
          for (var i = 0; i < blockItems.length; i++) {
            var it = blockItems[i];
            var key = [
              String(it.tag || ""),
              String(it.id_attr || ""),
              normClass(it.class_attr || ""),
              String(it.text_key || "")
            ].join("|");
            if (!idx.has(key)) idx.set(key, []);
            idx.get(key).push(it);
          }
          return idx;
        }
        function setToolbarMode(toolbar, isEditing){
          var saveBtn = toolbar.querySelector('[data-fe-act="save"]');
          if (!saveBtn) return;
          toolbar.style.display = isEditing ? "flex" : "none";
          saveBtn.style.display = isEditing ? "" : "none";
        }
        function positionToolbar(el){
          if (!el) return;
          var toolbar = el.querySelector(".fe-toolbar-inline");
          if (!toolbar || toolbar.style.display === "none") return;
          toolbar.style.left = "0px";
          toolbar.style.right = "auto";
          var vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
          var rect = toolbar.getBoundingClientRect();
          var left = 0;
          if (rect.right > vw - 12) {
            left -= (rect.right - (vw - 12));
            toolbar.style.left = left + "px";
            rect = toolbar.getBoundingClientRect();
          }
          if (rect.left < 12) {
            left += (12 - rect.left);
            toolbar.style.left = left + "px";
          }
        }
        var activeText = null;
        var activeWrap = null;
        function enterEdit(el){
          if (!el) return;
          if (activeWrap && activeWrap !== el) exitEdit(activeWrap, true);
          var textEl = el.querySelector(".fe-text");
          var toolbar = el.querySelector(".fe-toolbar-inline");
          if (!textEl || !toolbar) return;
          el.classList.add("fe-editing");
          el.dataset.feOriginal = textEl.textContent || "";
          el.dataset.feOriginalHtml = textEl.innerHTML || "";
          el.dataset.feOriginalStyle = textEl.getAttribute("style") || "";
          textEl.contentEditable = "true";
          textEl.setAttribute("spellcheck", "false");
          setToolbarMode(toolbar, true);
          activeText = textEl;
          activeWrap = el;
          syncToolbarState(toolbar, textEl);
          positionToolbar(el);
          try { textEl.focus({ preventScroll: true }); } catch (e) { textEl.focus(); }
          try { document.getSelection().selectAllChildren(textEl); } catch (e) {}
        }
        function exitEdit(el, restore){
          if (!el) return;
          var textEl = el.querySelector(".fe-text");
          var toolbar = el.querySelector(".fe-toolbar-inline");
          if (!textEl || !toolbar) return;
          if (restore) {
            restoreEditableHtml(textEl, el.dataset.feOriginalHtml || "", el.dataset.feOriginal || textEl.textContent || "");
            if (el.dataset.feOriginalStyle) {
              textEl.setAttribute("style", el.dataset.feOriginalStyle);
            } else {
              textEl.removeAttribute("style");
            }
          }
          textEl.contentEditable = "false";
          el.classList.remove("fe-editing");
          setToolbarMode(toolbar, false);
          el.dataset.feOriginal = "";
          el.dataset.feOriginalHtml = "";
          el.dataset.feOriginalStyle = "";
          if (activeText === textEl) activeText = null;
          if (activeWrap === el) activeWrap = null;
        }
        async function saveTextEdit(el){
          if (!el) return;
          var id = el.dataset.feId || "";
          var slotKey = el.dataset.feTextSlot || "";
          var slotPageKey = el.dataset.fePageKey || pageKey;
          var textEl = el.querySelector(".fe-text");
          if ((!id && !slotKey) || !textEl) return false;
          normalizeEditorFonts(textEl);
          var next = norm(textEl.textContent || "");
          var nextHtml = serializeEditableHtml(textEl);
          if (!next) { exitEdit(el, true); return false; }
          var out;
          if (slotKey) {
            out = await postJson("/admin/api/front_editor/text_slot_save.php", {
              pageKey: slotPageKey,
              slot: slotKey,
              text: next,
              html: nextHtml
            });
          } else {
            out = await postJson("/admin/api/front_editor/update.php", { pageKey: pageKey, id: id, text: next, html: nextHtml, type: "text" });
          }
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Lưu thất bại");
            return false;
          }
          restoreEditableHtml(textEl, out.data.html || nextHtml, next);
          exitEdit(el, false);
          return true;
        }
        function isTextDirty(el){
          if (!el) return false;
          var textEl = el.querySelector(".fe-text");
          if (!textEl) return false;
          normalizeEditorFonts(textEl);
          var currentText = norm(textEl.textContent || "");
          var currentHtml = serializeEditableHtml(textEl);
          var originalText = norm(el.dataset.feOriginal || "");
          var originalHtml = String(el.dataset.feOriginalHtml || "").trim();
          return currentText !== originalText || currentHtml !== originalHtml;
        }
        async function ensureTextSaved(el){
          if (!el || activeWrap !== el) return true;
          if (!isTextDirty(el)) return true;
          return !!(await saveTextEdit(el));
        }
        function applyTextAlignment(value){
          if (!activeText) return;
          activeText.style.display = "block";
          activeText.style.textAlign = value;
        }
        function currentTextAlignment(textEl){
          if (!textEl) return "left";
          var computed = "";
          try { computed = window.getComputedStyle(textEl).textAlign || ""; } catch (e) { computed = ""; }
          var align = String(textEl.style.textAlign || computed || "left").toLowerCase();
          if (align === "center" || align === "right" || align === "justify") return align;
          return "left";
        }
        function updateAlignButton(toolbar, value){
          if (!toolbar) return;
          var btn = toolbar.querySelector('[data-fe-act="align-cycle"]');
          var icon = btn ? btn.querySelector("i") : null;
          var next = value === "center" ? "center" : (value === "right" ? "right" : "left");
          if (btn) btn.setAttribute("data-fe-align", next);
          if (!icon) return;
          icon.className = next === "center"
            ? "fa-solid fa-align-center"
            : (next === "right" ? "fa-solid fa-align-right" : "fa-solid fa-align-left");
        }
        function cycleTextAlignment(toolbar){
          if (!activeText) return;
          var current = currentTextAlignment(activeText);
          var next = current === "left" ? "center" : (current === "center" ? "right" : "left");
          applyTextAlignment(next);
          updateAlignButton(toolbar, next);
        }
        function applyTextSize(value){
          if (!activeText || !value) return;
          wrapSelectionWithSpan({ fontSize: value });
        }
        function applyTextColor(value){
          if (!activeText || !value) return;
          var sel = window.getSelection ? window.getSelection() : null;
          if (sel && sel.rangeCount > 0 && !sel.isCollapsed && activeText.contains(sel.anchorNode)) {
            applyExecCommand("foreColor", value);
          } else {
            activeText.style.color = value;
          }
        }
        function syncToolbarState(toolbar, textEl){
          if (!toolbar || !textEl) return;
          var sizeSelect = toolbar.querySelector('[data-fe-control="font-size"]');
          var colorInput = toolbar.querySelector('[data-fe-control="fore-color"]');
          if (sizeSelect) sizeSelect.value = textEl.style.fontSize || "16px";
          if (colorInput) colorInput.value = normalizeColorValue(textEl.style.color || "#ffffff");
          updateAlignButton(toolbar, currentTextAlignment(textEl));
        }
        function normalizeColorValue(color){
          var probe = document.createElement("span");
          probe.style.color = color || "#ffffff";
          document.body.appendChild(probe);
          var computed = window.getComputedStyle(probe).color || "rgb(255, 255, 255)";
          probe.remove();
          var match = computed.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
          if (!match) return "#ffffff";
          var hex = [match[1], match[2], match[3]].map(function(part){
            var out = Number(part).toString(16);
            return out.length === 1 ? "0" + out : out;
          }).join("");
          return "#" + hex;
        }
        function buildFontSizeOptions(selected){
          var current = String(selected || "16px");
          var out = [];
          for (var size = 8; size <= 150; size++) {
            var value = size + "px";
            out.push('<option value="' + value + '"' + (value === current ? ' selected' : '') + '>' + value + '</option>');
          }
          return out.join("");
        }
        function setActionDisabled(toolbar, act, disabled){
          if (!toolbar) return;
          var btn = toolbar.querySelector('[data-fe-act="' + act + '"]');
          if (btn) btn.disabled = !!disabled;
        }
        function syncTextActionButtons(toolbar, el){
          if (!toolbar || !el) return;
          var hasSource = !!(el.dataset.feId || "");
          var hasSlot = !!(el.dataset.feTextSlot || "");
          var isInline = el.dataset.feInline === "1";
          setActionDisabled(toolbar, "delete", false);
          setActionDisabled(toolbar, "duplicate", !hasSource);
          setActionDisabled(toolbar, "move-up", !hasSource || isInline);
          setActionDisabled(toolbar, "move-down", !hasSource || isInline);
          if (hasSlot) {
            setActionDisabled(toolbar, "duplicate", true);
            setActionDisabled(toolbar, "move-up", true);
            setActionDisabled(toolbar, "move-down", true);
          }
        }
        async function runTextAction(el, action, extra){
          if (!el) return;
          if (action === "delete") {
            if (!window.confirm("Xoá đoạn text này?")) return;
          } else {
            var ready = await ensureTextSaved(el);
            if (!ready) return;
          }
          var id = el.dataset.feId || "";
          var slotKey = el.dataset.feTextSlot || "";
          var slotPageKey = el.dataset.fePageKey || pageKey;
          if (!id && !slotKey) return;
          var payload = {
            pageKey: slotPageKey,
            id: id,
            slot: slotKey,
            action: action
          };
          if (extra && extra.direction) payload.direction = extra.direction;
          var out = await postJson("/admin/api/front_editor/text_action.php", payload);
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Thao tác text thất bại.");
            return;
          }
          if (slotKey && action === "delete") {
            var textEl = el.querySelector(".fe-text");
            if (textEl) {
              textEl.innerHTML = "";
              textEl.textContent = "";
            }
            exitEdit(el, false);
            return;
          }
          window.location.reload();
        }
        function attachSingleTextEditor(el, meta){
          if (!el || !meta || el.dataset.feTextBound === "1") return;
          var inline = !!meta.inline;
          el.dataset.feTextBound = "1";
          el.classList.add("fe-editable");
          if (inline) el.classList.add("fe-inline");
          el.dataset.feInline = inline ? "1" : "0";
          if (meta.id) el.dataset.feId = meta.id;
          if (meta.slot) el.dataset.feTextSlot = meta.slot;
          var original = el.textContent || "";
          mountEditableText(el, original);
          var toolbar = document.createElement("div");
          toolbar.className = "fe-toolbar-inline";
          toolbar.setAttribute("contenteditable", "false");
          toolbar.innerHTML =
            '<button type="button" class="fe-btn is-icon" data-fe-act="save" style="display:none" aria-label="Lưu"><i class="fa-solid fa-check" aria-hidden="true"></i></button>' +
            '<span class="fe-toolbar-sep" aria-hidden="true"></span>' +
            '<div class="fe-toolbar-group">' +
              '<button type="button" class="fe-btn is-icon" data-fe-act="duplicate" aria-label="Nhân đôi"><i class="fa-solid fa-clone" aria-hidden="true"></i></button>' +
              '<button type="button" class="fe-btn is-icon" data-fe-act="delete" aria-label="Xoá"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>' +
              '<button type="button" class="fe-btn is-icon" data-fe-act="move-up" aria-label="Đưa lên"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>' +
              '<button type="button" class="fe-btn is-icon" data-fe-act="move-down" aria-label="Đưa xuống"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button>' +
            '</div>' +
            '<span class="fe-toolbar-sep" aria-hidden="true"></span>' +
            '<div class="fe-toolbar-group">' +
              '<button type="button" class="fe-btn is-icon" data-fe-act="bold" aria-label="Đậm"><i class="fa-solid fa-bold" aria-hidden="true"></i></button>' +
              '<button type="button" class="fe-btn is-icon" data-fe-act="italic" aria-label="Nghiêng"><i class="fa-solid fa-italic" aria-hidden="true"></i></button>' +
              '<button type="button" class="fe-btn is-icon" data-fe-act="underline" aria-label="Gạch chân"><i class="fa-solid fa-underline" aria-hidden="true"></i></button>' +
              '<button type="button" class="fe-btn is-icon" data-fe-act="strike" aria-label="Gạch ngang"><i class="fa-solid fa-strikethrough" aria-hidden="true"></i></button>' +
            '</div>' +
            '<span class="fe-toolbar-sep" aria-hidden="true"></span>' +
            '<div class="fe-toolbar-group">' +
              '<button type="button" class="fe-btn is-icon" data-fe-act="align-cycle" data-fe-align="left" aria-label="Đổi căn lề"><i class="fa-solid fa-align-left" aria-hidden="true"></i></button>' +
            '</div>' +
            '<span class="fe-toolbar-sep" aria-hidden="true"></span>' +
            '<input type="color" class="fe-toolbar-color" data-fe-control="fore-color" value="#ffffff" aria-label="Màu chữ">' +
            '<select class="fe-toolbar-select" data-fe-control="font-size" aria-label="Cỡ chữ">' +
              buildFontSizeOptions("16px") +
            '</select>';
          el.appendChild(toolbar);
          setToolbarMode(toolbar, false);
          syncTextActionButtons(toolbar, el);
          toolbar.addEventListener("click", function(e){
            var btn = e.target && e.target.closest ? e.target.closest("[data-fe-act]") : null;
            if (!btn) return;
            if (btn.disabled) return;
            e.preventDefault();
            e.stopPropagation();
            var act = btn.getAttribute("data-fe-act");
            if (act === "save") saveTextEdit(el);
            if (act === "duplicate") runTextAction(el, "duplicate");
            if (act === "delete") runTextAction(el, "delete");
            if (act === "move-up") runTextAction(el, "move", { direction: "up" });
            if (act === "move-down") runTextAction(el, "move", { direction: "down" });
            if (act === "bold") applyExecCommand("bold");
            if (act === "italic") applyExecCommand("italic");
            if (act === "underline") applyExecCommand("underline");
            if (act === "strike") applyExecCommand("strikeThrough");
            if (act === "align-cycle") cycleTextAlignment(toolbar);
          }, true);
          var sizeSelect = toolbar.querySelector('[data-fe-control="font-size"]');
          if (sizeSelect) {
            sizeSelect.addEventListener("change", function(e){
              e.stopPropagation();
              applyTextSize(sizeSelect.value || "16px");
            });
          }
          var colorInput = toolbar.querySelector('[data-fe-control="fore-color"]');
          if (colorInput) {
            colorInput.addEventListener("input", function(e){
              e.stopPropagation();
              applyTextColor(colorInput.value || "#ffffff");
            });
          }
          el.addEventListener("click", function(e){
            if (el.classList.contains("fe-editing")) return;
            if (e.target && e.target.closest && e.target.closest(".fe-toolbar-inline")) return;
            if (e.target && e.target.closest && e.target.closest(".fe-icon-editable")) return;
            e.preventDefault();
            e.stopPropagation();
            enterEdit(el);
          }, true);
        }
        function attachTextEditors(){
          var idx = buildTextIndex();
          var matched = new Set();
          idx.forEach(function(list, key){
            var parts = key.split("|");
            var tag = String(parts[0] || "").toUpperCase();
            var want = parts.slice(1).join("|");
            if (!tag || !want) return;
            var nodes = Array.prototype.slice.call(document.getElementsByTagName(tag));
            var candidates = nodes.filter(function(el){
              if (!el || norm(el.textContent) !== want) return false;
              if (tag !== "A" && isInsideLink(el)) return false;
              if (el.closest && el.closest(".fe-media-modal, .fe-icon-modal, .fe-template-modal, .fe-source-modal, .fe-context-menu")) return false;
              return true;
            });
            list.sort(function(a, b){ return (a.occ || 1) - (b.occ || 1); });
            for (var j = 0; j < list.length; j++) {
              var it = list[j];
              var pos = (it.occ || 1) - 1;
              var el = candidates[pos];
              if (!el || matched.has(el)) continue;
              matched.add(el);
              var inline = String(it.tag || "") === "span" || String(it.tag || "") === "b" || String(it.tag || "") === "strong";
              attachSingleTextEditor(el, { id: it.id, inline: inline });
            }
          });
        }
        function attachManualTextEditors(){
          var nodes = Array.prototype.slice.call(document.querySelectorAll("[data-fe-text-slot]"));
          for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (!node || !node.getAttribute) continue;
            var slotKey = String(node.getAttribute("data-fe-text-slot") || "").trim();
            if (!slotKey) continue;
            var tag = String(node.tagName || "").toLowerCase();
            var inline = tag === "span" || tag === "b" || tag === "strong";
            attachSingleTextEditor(node, { slot: slotKey, inline: inline });
          }
        }

        var modal = document.getElementById("feMediaModal");
        var modalGrid = document.getElementById("feMediaGrid");
        var modalEmpty = document.getElementById("feMediaEmpty");
        var modalReload = document.getElementById("feMediaReload");
        var modalUpload = document.getElementById("feMediaUploadInput");
        var modalUploadProgress = document.getElementById("feMediaUploadProgress");
        var modalUploadProgressBar = document.getElementById("feMediaUploadProgressBar");
        var modalUploadProgressText = document.getElementById("feMediaUploadProgressText");
        var modalUploadProgressLabel = document.getElementById("feMediaUploadProgressLabel");
        var modalFilterButtons = modal ? modal.querySelectorAll("[data-fe-media-filter]") : [];
        var modalState = { open: false, filter: "image", onPick: null, selectedUrl: "" };

        function openMediaModal(onPick, selectedUrl){
          if (!modal) return;
          modalState.open = true;
          modalState.onPick = typeof onPick === "function" ? onPick : null;
          modalState.selectedUrl = resolveUrl(selectedUrl || "");
          modal.hidden = false;
          document.documentElement.style.overflow = "hidden";
          loadMediaLibrary();
        }
        function closeMediaModal(){
          if (!modal) return;
          modalState.open = false;
          modalState.onPick = null;
          modal.hidden = true;
          document.documentElement.style.overflow = "";
          resetMediaUploadProgress();
        }
        function setMediaFilter(nextFilter){
          modalState.filter = nextFilter === "all" ? "all" : "image";
          Array.prototype.forEach.call(modalFilterButtons, function(btn){
            var active = btn.getAttribute("data-fe-media-filter") === modalState.filter;
            btn.classList.toggle("is-active", active);
          });
        }
        function renderMediaItems(items){
          if (!modalGrid || !modalEmpty) return;
          modalGrid.innerHTML = "";
          modalEmpty.hidden = items.length !== 0;
          if (items.length === 0) return;
          modalGrid.innerHTML = items.map(function(file){
            var rawUrl = String(file.url || "");
            var safeUrl = esc(rawUrl);
            var meta = esc(String(file.type || "")) + " • " + esc(formatBytes(file.size));
            var active = resolveUrl(rawUrl) === modalState.selectedUrl;
            return '' +
              '<button type="button" class="fe-media-item' + (active ? ' is-active' : '') + '" data-fe-pick-url="' + safeUrl + '">' +
                '<div class="fe-media-thumb">' +
                  '<img src="' + safeUrl + '" alt="">' +
                '</div>' +
                '<div class="fe-media-meta">' + meta + '</div>' +
              '</button>';
          }).join("");
        }
        async function loadMediaLibrary(){
          if (!modalGrid || !modalEmpty || !modalState.open) return;
          modalGrid.innerHTML = '<div class="fe-media-empty">Đang tải...</div>';
          modalEmpty.hidden = true;
          var out = await postJson("/admin/api/media/list.php");
          if (!out.res.ok || !out.data || !out.data.ok) {
            modalGrid.innerHTML = '<div class="fe-media-empty">' + esc((out.data && out.data.message) ? out.data.message : "Không tải được thư viện.") + '</div>';
            return;
          }
          var files = Array.isArray(out.data.files) ? out.data.files : [];
          if (modalState.filter === "image") {
            files = files.filter(function(file){ return String(file.type || "") === "image"; });
          }
          renderMediaItems(files);
        }
        function bindMediaModal(){
          if (!modal || modal.dataset.bound === "1") return;
          modal.dataset.bound = "1";
          modal.addEventListener("click", function(e){
            var closeBtn = e.target && e.target.closest ? e.target.closest("[data-fe-media-close='1']") : null;
            if (closeBtn) {
              e.preventDefault();
              closeMediaModal();
              return;
            }
            var pickBtn = e.target && e.target.closest ? e.target.closest("[data-fe-pick-url]") : null;
            if (!pickBtn) return;
            e.preventDefault();
            var picked = pickBtn.getAttribute("data-fe-pick-url") || "";
            if (typeof modalState.onPick === "function") {
              modalState.onPick(picked);
            }
            closeMediaModal();
          });
          Array.prototype.forEach.call(modalFilterButtons, function(btn){
            btn.addEventListener("click", function(){
              setMediaFilter(btn.getAttribute("data-fe-media-filter") || "image");
              loadMediaLibrary();
            });
          });
          if (modalReload) {
            modalReload.addEventListener("click", function(){ loadMediaLibrary(); });
          }
          if (modalUpload) {
            modalUpload.addEventListener("change", async function(){
              var file = modalUpload.files && modalUpload.files[0] ? modalUpload.files[0] : null;
              modalUpload.value = "";
              var uploaded = await uploadImage(file);
              if (!uploaded || uploaded.type !== "image" || !uploaded.url) return;
              modalState.selectedUrl = resolveUrl(uploaded.url);
              await loadMediaLibrary();
              if (typeof modalState.onPick === "function") {
                modalState.onPick(uploaded.url);
              }
              closeMediaModal();
            });
          }
          document.addEventListener("keydown", function(e){
            if (e.key === "Escape" && modalState.open) {
              e.preventDefault();
              closeMediaModal();
            }
          }, true);
        }
        var iconModal = document.getElementById("feIconModal");
        var iconBody = document.getElementById("feIconBody");
        var iconSearch = document.getElementById("feIconSearch");
        var iconGrid = document.getElementById("feIconGrid");
        var iconEmpty = document.getElementById("feIconEmpty");
        var iconLoadMore = document.getElementById("feIconLoadMore");
        var iconState = { open: false, currentNode: null, currentItem: null, query: "", filtered: [], visibleCount: 0, batchSize: 24, loading: false };
        function getFilteredIconLibrary(){
          var query = norm(iconState.query || "").toLowerCase();
          return iconLibrary.filter(function(item){
            if (!query) return true;
            var hay = (item.label + " " + item.family + " " + item.terms + " " + item.className).toLowerCase();
            return hay.indexOf(query) !== -1;
          });
        }
        function updateIconLoadMore(total){
          if (!iconLoadMore) return;
          if (iconState.loading) {
            iconLoadMore.hidden = false;
            iconLoadMore.textContent = "Dang tai full icon...";
            return;
          }
          var remain = Math.max(0, Number(total || 0) - Number(iconState.visibleCount || 0));
          iconLoadMore.hidden = remain <= 0;
          if (remain > 0) {
            iconLoadMore.textContent = "Kéo xuống để tải thêm " + remain + " icon";
          }
        }
        function renderIconOptions(reset){
          if (!iconGrid || !iconEmpty) return;
          if (reset) {
            iconState.filtered = getFilteredIconLibrary();
            iconState.visibleCount = 0;
            iconGrid.innerHTML = "";
            if (iconBody) iconBody.scrollTop = 0;
          }
          var items = Array.isArray(iconState.filtered) ? iconState.filtered : [];
          var currentSignature = iconState.currentNode ? iconSignature(iconState.currentNode.className || "") : "";
          if (items.length === 0) {
            iconGrid.innerHTML = "";
            iconEmpty.hidden = false;
            updateIconLoadMore(0);
            return;
          }
          iconEmpty.hidden = true;
          var start = Number(iconState.visibleCount || 0);
          var end = Math.min(items.length, start + Number(iconState.batchSize || 24));
          if (start >= end) {
            updateIconLoadMore(items.length);
            return;
          }
          var html = items.slice(start, end).map(function(item){
            var active = iconSignature(item.className || "") === currentSignature;
            return '' +
              '<button type="button" class="fe-icon-card' + (active ? ' is-active' : '') + '" data-fe-pick-icon="' + esc(item.className || "") + '">' +
                '<span class="fe-icon-card-preview"><i class="' + esc(item.className || "") + '" aria-hidden="true"></i></span>' +
                '<span class="fe-icon-card-label">' + esc(item.label || "") + '</span>' +
                '<span class="fe-icon-card-meta">' + esc(item.family || "") + '</span>' +
              '</button>';
          }).join("");
          if (start === 0) iconGrid.innerHTML = html;
          else iconGrid.insertAdjacentHTML("beforeend", html);
          iconState.visibleCount = end;
          updateIconLoadMore(items.length);
          if (iconBody && iconState.visibleCount < items.length && iconBody.scrollHeight <= (iconBody.clientHeight + 24)) {
            renderIconOptions(false);
          }
        }
        function openIconModal(node, item){
          if (!iconModal || !node || !item) return;
          iconState.open = true;
          iconState.currentNode = node;
          iconState.currentItem = item;
          iconState.query = "";
          if (iconSearch) iconSearch.value = "";
          iconModal.hidden = false;
          document.documentElement.style.overflow = "hidden";
          renderIconOptions(true);
          if (!iconLibraryReady) {
            iconState.loading = true;
            updateIconLoadMore(iconState.filtered.length || 0);
            ensureIconLibraryLoaded().then(function(){
              if (!iconState.open) return;
              iconState.loading = false;
              renderIconOptions(true);
            }).catch(function(){
              iconState.loading = false;
              updateIconLoadMore(iconState.filtered.length || 0);
            });
          }
          if (iconSearch) setTimeout(function(){ try { iconSearch.focus(); } catch (e) {} }, 30);
        }
        function closeIconModal(){
          if (!iconModal) return;
          iconState.open = false;
          iconState.currentNode = null;
          iconState.currentItem = null;
          iconState.query = "";
          iconState.filtered = [];
          iconState.visibleCount = 0;
          iconState.loading = false;
          iconModal.hidden = true;
          document.documentElement.style.overflow = "";
        }
        async function applyPickedIcon(iconClassName){
          if (!iconState.currentNode || !iconState.currentItem || !iconClassName) return;
          var out = await postJson("/admin/api/front_editor/update.php", {
            pageKey: pageKey,
            id: iconState.currentItem.id,
            type: "icon",
            iconClass: iconClassName
          });
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Lưu icon thất bại.");
            return;
          }
          var nextClass = String((out.data && out.data.class) || mergeIconClasses(iconState.currentNode.className || "", iconClassName));
          iconState.currentNode.className = nextClass;
          iconState.currentNode.classList.add("fe-icon-editable");
          if (iconState.currentItem) {
            iconState.currentItem.class = nextClass;
            iconState.currentItem.icon_key = iconSignature(nextClass);
          }
          closeIconModal();
        }
        function bindIconModal(){
          if (!iconModal || iconModal.dataset.bound === "1") return;
          iconModal.dataset.bound = "1";
          iconModal.addEventListener("click", function(e){
            var closeBtn = e.target && e.target.closest ? e.target.closest("[data-fe-icon-close='1']") : null;
            if (closeBtn) {
              e.preventDefault();
              closeIconModal();
              return;
            }
            var pickBtn = e.target && e.target.closest ? e.target.closest("[data-fe-pick-icon]") : null;
            if (!pickBtn) return;
            e.preventDefault();
            applyPickedIcon(pickBtn.getAttribute("data-fe-pick-icon") || "");
          });
          if (iconSearch) {
            iconSearch.addEventListener("input", function(){
              iconState.query = iconSearch.value || "";
              renderIconOptions(true);
            });
          }
          if (iconBody) {
            iconBody.addEventListener("scroll", function(){
              if (!iconState.open) return;
              if ((iconBody.scrollTop + iconBody.clientHeight) >= (iconBody.scrollHeight - 140)) {
                renderIconOptions(false);
              }
            });
          }
          document.addEventListener("keydown", function(e){
            if (e.key === "Escape" && iconState.open) {
              e.preventDefault();
              closeIconModal();
            }
          }, true);
        }
        var contextMenu = document.getElementById("feContextMenu");
        var contextMenuImageBtn = contextMenu ? contextMenu.querySelector('[data-fe-menu-act="change-image"]') : null;
        var templateModal = document.getElementById("feTemplateModal");
        var sourceModal = document.getElementById("feSourceModal");
        var sourceTitleEl = document.getElementById("feSourceTitle");
        var sourceMetaEl = document.getElementById("feSourceMeta");
        var sourceEditorEl = document.getElementById("feSourceEditor");
        var sourceSaveBtn = document.getElementById("feSourceSave");
        var templateListEl = document.getElementById("feTemplateList");
        var templateEmptyEl = document.getElementById("feTemplateEmpty");
        var templateEditName = document.getElementById("feTemplateEditName");
        var templateEditHtml = document.getElementById("feTemplateEditHtml");
        var templateSaveEditBtn = document.getElementById("feTemplateSaveEdit");
        var templateCancelEditBtn = document.getElementById("feTemplateCancelEdit");
        var menuTargetBlock = null;
        var menuTargetSource = null;
        var templateState = { open: false, currentBlock: null, items: [], editingId: 0 };
        var sourceState = { open: false, mode: "html", syntax: "html", original: "", loading: false };
        var blockDraftState = { ops: [] };

        function hideContextMenu(){
          if (!contextMenu) return;
          contextMenu.hidden = true;
          menuTargetBlock = null;
          menuTargetSource = null;
        }
        function updateSourceModalMeta(meta){
          var mode = String(meta && meta.mode ? meta.mode : "html");
          var syntax = String(meta && meta.syntax ? meta.syntax : (mode === "code" ? "php" : "html"));
          var title = String(meta && meta.title ? meta.title : (mode === "code" ? "Sửa toàn trang bằng code" : "Sửa toàn trang bằng HTML"));
          sourceState.mode = mode === "code" ? "code" : "html";
          sourceState.syntax = syntax;
          if (sourceTitleEl) sourceTitleEl.textContent = title;
          if (sourceMetaEl) {
            sourceMetaEl.textContent = sourceState.mode === "code"
              ? "Trang này đang dùng source code. Bạn có thể sửa trực tiếp file PHP/code rồi lưu."
              : "Trang này đang dùng HTML thuần. Bạn có thể sửa trực tiếp HTML rồi lưu.";
          }
          if (sourceEditorEl) {
            sourceEditorEl.setAttribute("data-fe-source-mode", sourceState.mode);
            sourceEditorEl.setAttribute("data-fe-source-syntax", sourceState.syntax);
            sourceEditorEl.placeholder = sourceState.mode === "code" ? "Nhập code toàn trang..." : "Nhập HTML toàn trang...";
            sourceEditorEl.wrap = sourceState.mode === "code" ? "off" : "soft";
          }
        }
        function openSourceModalShell(){
          if (!sourceModal) return;
          sourceState.open = true;
          sourceModal.hidden = false;
          document.documentElement.style.overflow = "hidden";
        }
        function closeSourceModal(){
          if (!sourceModal) return;
          sourceState.open = false;
          sourceState.loading = false;
          sourceModal.hidden = true;
          document.documentElement.style.overflow = "";
        }
        async function openSourceModal(){
          if (!sourceModal || sourceState.loading) return;
          openSourceModalShell();
          sourceState.loading = true;
          updateSourceModalMeta({ mode: "html", syntax: "html", title: "Đang tải source..." });
          sourceState.original = "";
          if (sourceEditorEl) {
            sourceEditorEl.value = "";
            sourceEditorEl.disabled = true;
            sourceEditorEl.placeholder = "Đang tải source...";
          }
          if (sourceSaveBtn) sourceSaveBtn.disabled = true;
          var out = await postJson("/admin/api/front_editor/source_get.php", { pageKey: pageKey });
          sourceState.loading = false;
          if (!sourceState.open) return;
          if (!out.res.ok || !out.data || !out.data.ok) {
            if (sourceMetaEl) sourceMetaEl.textContent = (out.data && out.data.message) ? out.data.message : "Không tải được source toàn trang.";
            if (sourceEditorEl) sourceEditorEl.value = "";
            if (sourceSaveBtn) sourceSaveBtn.disabled = true;
            return;
          }
          updateSourceModalMeta(out.data || {});
          sourceState.original = String((out.data && out.data.content) || "");
          if (sourceEditorEl) {
            sourceEditorEl.disabled = false;
            sourceEditorEl.value = sourceState.original;
            setTimeout(function(){ try { sourceEditorEl.focus(); } catch (e) {} }, 30);
          }
          if (sourceSaveBtn) sourceSaveBtn.disabled = false;
        }
        async function saveSourceModal(){
          if (!sourceEditorEl || sourceState.loading) return;
          var next = String(sourceEditorEl.value || "");
          if (next === sourceState.original) {
            alert("Chưa có thay đổi mới.");
            return;
          }
          sourceState.loading = true;
          sourceEditorEl.disabled = true;
          if (sourceSaveBtn) sourceSaveBtn.disabled = true;
          var out = await postJson("/admin/api/front_editor/source_save.php", {
            pageKey: pageKey,
            content: next
          });
          sourceState.loading = false;
          sourceEditorEl.disabled = false;
          if (sourceSaveBtn) sourceSaveBtn.disabled = false;
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Lưu source toàn trang thất bại.");
            return;
          }
          sourceState.original = next;
          alert((out.data && out.data.message) ? out.data.message : "Đã lưu toàn trang.");
          window.location.reload();
        }
        function getEditableImageTarget(node){
          if (!node) return null;
          var slotKey = node.getAttribute && String(node.getAttribute("data-fe-image-slot") || "").trim();
          if (slotKey) {
            return {
              kind: "slot",
              node: node,
              slotKey: slotKey,
              url: String(node.getAttribute("data-fe-image-url") || node.dataset.feImageUrl || "")
            };
          }
          var imageId = node.dataset ? String(node.dataset.feImageId || "").trim() : "";
          if (!imageId) return null;
          var item = node.__feImageItem || null;
          var actualNode = node.__feImageTarget || node;
          return {
            kind: "image",
            node: actualNode,
            proxyNode: node,
            item: item,
            url: String(node.dataset.feImageUrl || actualNode.dataset.feImageUrl || (item && item.url) || "")
          };
        }
        function findBlockImageTarget(blockEl, sourceEl){
          if (!blockEl) return null;
          var selector = "[data-fe-image-slot], [data-fe-image-id]";
          if (sourceEl && sourceEl.closest) {
            var sourceMatch = sourceEl.closest(selector);
            if (sourceMatch && (sourceMatch === blockEl || blockEl.contains(sourceMatch))) {
              var sourceTarget = getEditableImageTarget(sourceMatch);
              if (sourceTarget) return sourceTarget;
            }
          }
          var selfTarget = getEditableImageTarget(blockEl);
          if (selfTarget) return selfTarget;
          var first = blockEl.querySelector(selector);
          return getEditableImageTarget(first);
        }
        function showContextMenu(x, y, blockEl, sourceEl){
          if (!contextMenu || !blockEl) return;
          menuTargetBlock = blockEl;
          menuTargetSource = sourceEl || null;
          if (contextMenuImageBtn) {
            contextMenuImageBtn.hidden = !findBlockImageTarget(blockEl, menuTargetSource);
          }
          contextMenu.hidden = false;
          var vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
          var vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
          contextMenu.style.left = "0px";
          contextMenu.style.top = "0px";
          var rect = contextMenu.getBoundingClientRect();
          var nextLeft = Math.min(x, Math.max(12, vw - rect.width - 12));
          var nextTop = Math.min(y, Math.max(12, vh - rect.height - 12));
          contextMenu.style.left = nextLeft + "px";
          contextMenu.style.top = nextTop + "px";
        }
        async function openImagePickerForBlock(blockEl, sourceEl){
          var imageTarget = findBlockImageTarget(blockEl, sourceEl);
          if (!imageTarget) {
            alert("Section này chưa nhận diện được ảnh để đổi.");
            return;
          }
          var currentUrl = String(imageTarget.url || "");
          openMediaModal(function(url){
            if (!url) return;
            var indicatorNode = imageTarget.proxyNode || imageTarget.node;
            if (indicatorNode) indicatorNode.classList.add("fe-image-loading");
            var task = imageTarget.kind === "slot"
              ? saveManualImageSlot(imageTarget.node, imageTarget.slotKey, url)
              : saveImageEdit(imageTarget.node, imageTarget.item, url);
            Promise.resolve(task).then(function(){
              if (imageTarget.proxyNode && imageTarget.proxyNode !== imageTarget.node) {
                imageTarget.proxyNode.dataset.feImageUrl = resolveUrl(url);
                imageTarget.proxyNode.setAttribute("data-fe-image-url", url);
              }
            }).finally(function(){
              if (indicatorNode) indicatorNode.classList.remove("fe-image-loading");
            });
          }, currentUrl);
        }
        function findCurrentBlockEl(blockId){
          if (!blockId) return null;
          return document.querySelector('[data-fe-block-id="' + CSS.escape(String(blockId)) + '"]');
        }
        function markBlockPending(el){
          if (!el) return;
          el.classList.add("fe-block-pending");
        }
        function recordBlockOp(op){
          blockDraftState.ops.push(op);
          var saveBtn = contextMenu ? contextMenu.querySelector('[data-fe-menu-act="save-page"] span') : null;
          if (saveBtn) {
            saveBtn.textContent = blockDraftState.ops.length > 0 ? ("Lưu thay đổi (" + blockDraftState.ops.length + ")") : "Lưu thay đổi";
          }
        }
        function getSiblingBlocks(blockEl){
          if (!blockEl || !blockEl.parentNode) return [];
          return Array.prototype.slice.call(blockEl.parentNode.children).filter(function(node){
            return node && node.nodeType === 1 && node.matches && node.matches("[data-fe-block-id]") && node.dataset.feDeleted !== "1";
          });
        }
        function swapBlocksPreview(blockEl, direction){
          if (!blockEl || !blockEl.parentNode) return false;
          var siblings = getSiblingBlocks(blockEl);
          var idx = siblings.indexOf(blockEl);
          if (idx < 0) return false;
          var other = direction === "down" ? siblings[idx + 1] : siblings[idx - 1];
          if (!other || !other.parentNode) return false;
          var parent = blockEl.parentNode;
          if (direction === "up") {
            parent.insertBefore(blockEl, other);
          } else {
            parent.insertBefore(other, blockEl);
          }
          markBlockPending(blockEl);
          markBlockPending(other);
          return true;
        }
        function deleteBlockPreview(blockEl){
          if (!blockEl || blockEl.dataset.feDeleted === "1") return false;
          blockEl.dataset.feDeleted = "1";
          blockEl.classList.add("fe-block-pending-delete");
          return true;
        }
        function canPreviewTemplateHtml(html){
          if (!html || String(html).indexOf("<" + "?") !== -1) return false;
          return true;
        }
        function applyTemplatePreview(blockEl, html){
          if (!blockEl || !canPreviewTemplateHtml(html)) return false;
          blockEl.innerHTML = html;
          markBlockPending(blockEl);
          return true;
        }
        async function commitBlockChanges(){
          if (blockDraftState.ops.length === 0) {
            alert("Chưa có thay đổi để lưu.");
            return;
          }
          var out = await postJson("/admin/api/front_editor/block_commit.php", {
            pageKey: pageKey,
            ops: blockDraftState.ops
          });
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Lưu thay đổi thất bại.");
            return;
          }
          window.location.reload();
        }
        function resetTemplateEditor(){
          templateState.editingId = 0;
          if (templateEditName) templateEditName.value = "";
          if (templateEditHtml) templateEditHtml.value = "";
        }
        function openTemplateModal(blockEl){
          if (!templateModal) return;
          templateState.currentBlock = blockEl || null;
          templateState.open = true;
          templateModal.hidden = false;
          document.documentElement.style.overflow = "hidden";
          resetTemplateEditor();
          loadTemplates();
        }
        function closeTemplateModal(){
          if (!templateModal) return;
          templateState.open = false;
          templateModal.hidden = true;
          document.documentElement.style.overflow = "";
          resetTemplateEditor();
        }
        function fillTemplateEditor(item){
          templateState.editingId = Number(item && item.id ? item.id : 0);
          if (templateEditName) templateEditName.value = String(item && item.name ? item.name : "");
          if (templateEditHtml) templateEditHtml.value = String(item && item.html_content ? item.html_content : "");
        }
        function isFullPageTemplate(item){
          if (!item) return false;
          return String(item.source_element_id || "") === "__page__" || String(item.tag_name || "").toLowerCase() === "page";
        }
        function renderTemplateList(items){
          if (!templateListEl || !templateEmptyEl) return;
          templateState.items = Array.isArray(items) ? items : [];
          templateEmptyEl.hidden = templateState.items.length !== 0;
          templateListEl.innerHTML = templateState.items.map(function(item){
            var preview = esc(item.preview_text || "").slice(0, 180);
            var isPageTemplate = isFullPageTemplate(item);
            return '' +
              '<div class="fe-template-card" data-fe-template-id="' + Number(item.id || 0) + '">' +
                '<div class="fe-template-card-head">' +
                  '<div>' +
                    '<div class="fe-template-name">' + esc(item.name || "") + '</div>' +
                    '<div class="fe-template-meta">' + esc(item.page_key || "") + ' • #' + Number(item.id || 0) + ' • ' + (isPageTemplate ? 'Toàn trang' : 'Block') + '</div>' +
                  '</div>' +
                '</div>' +
                '<div class="fe-template-preview">' + (preview || 'Không có mô tả') + '</div>' +
                '<div class="fe-template-actions">' +
                  (isPageTemplate
                    ? '<button type="button" class="fe-template-btn is-primary" data-fe-template-act="apply-page-before"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i><span>Chèn lên trên</span></button>' +
                      '<button type="button" class="fe-template-btn" data-fe-template-act="apply-page-after"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i><span>Chèn xuống dưới</span></button>'
                    : '<button type="button" class="fe-template-btn is-primary" data-fe-template-act="apply"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>Dùng template</span></button>') +
                  '<button type="button" class="fe-template-btn" data-fe-template-act="edit"><i class="fa-solid fa-pen" aria-hidden="true"></i><span>Sửa</span></button>' +
                  '<button type="button" class="fe-template-btn is-danger" data-fe-template-act="delete"><i class="fa-solid fa-trash" aria-hidden="true"></i><span>Xoá</span></button>' +
                '</div>' +
              '</div>';
          }).join("");
        }
        async function loadTemplates(){
          if (!templateState.open || !templateListEl) return;
          templateListEl.innerHTML = '<div class="fe-template-empty">Đang tải...</div>';
          templateEmptyEl.hidden = true;
          var out = await postJson("/admin/api/front_editor/template_list.php", { limit: 120 });
          if (!out.res.ok || !out.data || !out.data.ok) {
            templateListEl.innerHTML = '<div class="fe-template-empty">' + esc((out.data && out.data.message) ? out.data.message : "Không tải được template.") + '</div>';
            return;
          }
          renderTemplateList(out.data.items || []);
        }
        async function saveTemplateFromBlock(blockEl){
          if (!blockEl) return;
          var defaultName = "Template " + pageKey + " " + (blockEl.tagName || "block").toLowerCase();
          var name = window.prompt("Tên template:", defaultName);
          if (!name) return;
          var blockId = blockEl.dataset.feBlockId || "";
          if (!blockId) return;
          var out = await postJson("/admin/api/front_editor/template_save.php", { pageKey: pageKey, blockId: blockId, name: name });
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Lưu template thất bại.");
            return;
          }
          alert(out.data.message || "Đã lưu template.");
        }
        async function saveFullPageTemplate(){
          var name = window.prompt("Tên template toàn trang:", "Template full " + pageKey);
          if (!name) return;
          var out = await postJson("/admin/api/front_editor/template_save.php", {
            pageKey: pageKey,
            name: name,
            saveAll: true
          });
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Lưu toàn bộ template thất bại.");
            return;
          }
          alert(out.data.message || "Đã lưu toàn bộ template.");
        }
        async function applyTemplateToBlock(blockEl, templateId){
          if (!blockEl) return;
          var blockId = blockEl.dataset.feBlockId || "";
          if (!blockId || !templateId) return;
          var item = null;
          for (var i = 0; i < templateState.items.length; i++) {
            if (Number(templateState.items[i].id || 0) === Number(templateId)) {
              item = templateState.items[i];
              break;
            }
          }
          if (isFullPageTemplate(item)) {
            alert("Template toàn trang không áp dụng trực tiếp vào một block.");
            return;
          }
          recordBlockOp({ type: "apply_template", blockId: blockId, templateId: Number(templateId) });
          if (item && canPreviewTemplateHtml(item.html_content || "")) {
            applyTemplatePreview(blockEl, item.html_content || "");
          } else {
            markBlockPending(blockEl);
            alert("Đã thêm template vào danh sách chờ lưu. Template có PHP sẽ cập nhật sau khi bấm Lưu thay đổi.");
          }
        }
        async function applyTemplateToPage(templateId, position){
          if (!templateId || !templateState.currentBlock) return;
          var blockId = templateState.currentBlock.dataset.feBlockId || "";
          if (!blockId) return;
          var humanPosition = position === "before" ? "lên trên" : "xuống dưới";
          if (!window.confirm("Chèn template toàn trang " + humanPosition + " block đang chọn?")) return;
          var out = await postJson("/admin/api/front_editor/template_apply.php", {
            pageKey: pageKey,
            blockId: blockId,
            templateId: Number(templateId),
            position: position === "before" ? "before" : "after"
          });
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Chèn template toàn trang thất bại.");
            return;
          }
          alert(out.data.message || "Đã chèn template toàn trang.");
          window.location.reload();
        }
        async function deleteCurrentBlock(blockEl){
          if (!blockEl) return;
          var blockId = blockEl.dataset.feBlockId || "";
          if (!blockId) return;
          if (!window.confirm("Xoá section này khỏi trang?")) return;
          if (!deleteBlockPreview(blockEl)) return;
          recordBlockOp({ type: "delete", blockId: blockId });
        }
        async function moveCurrentBlock(blockEl, direction){
          if (!blockEl) return;
          var blockId = blockEl.dataset.feBlockId || "";
          if (!blockId) return;
          if (!swapBlocksPreview(blockEl, direction === "down" ? "down" : "up")) {
            alert(direction === "down" ? "Section này đã ở dưới cùng." : "Section này đã ở trên cùng.");
            return;
          }
          recordBlockOp({ type: "move", blockId: blockId, direction: direction === "down" ? "down" : "up" });
        }
        async function updateTemplateRecord(){
          if (!templateState.editingId) return;
          var name = templateEditName ? templateEditName.value.trim() : "";
          var html = templateEditHtml ? templateEditHtml.value.trim() : "";
          if (!name || !html) {
            alert("Tên và HTML template không được rỗng.");
            return;
          }
          var out = await postJson("/admin/api/front_editor/template_update.php", {
            id: templateState.editingId,
            name: name,
            html: html
          });
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Cập nhật template thất bại.");
            return;
          }
          resetTemplateEditor();
          await loadTemplates();
        }
        async function deleteTemplateRecord(id){
          if (!id) return;
          if (!window.confirm("Xoá template này?")) return;
          var out = await postJson("/admin/api/front_editor/template_delete.php", { id: Number(id) });
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Xoá template thất bại.");
            return;
          }
          if (templateState.editingId === Number(id)) resetTemplateEditor();
          await loadTemplates();
        }
        function bindTemplateUi(){
          if (contextMenu && contextMenu.dataset.bound !== "1") {
            contextMenu.dataset.bound = "1";
            contextMenu.addEventListener("click", function(e){
              var btn = e.target && e.target.closest ? e.target.closest("[data-fe-menu-act]") : null;
              if (!btn || !menuTargetBlock) return;
              var targetBlock = menuTargetBlock;
              var targetSource = menuTargetSource;
              var act = btn.getAttribute("data-fe-menu-act") || "";
              hideContextMenu();
              if (act === "save-page") commitBlockChanges();
              if (act === "edit-page-source") openSourceModal();
              if (act === "change-image") openImagePickerForBlock(targetBlock, targetSource);
              if (act === "save-template-all") saveFullPageTemplate();
              if (act === "move-up") moveCurrentBlock(targetBlock, "up");
              if (act === "move-down") moveCurrentBlock(targetBlock, "down");
              if (act === "save-template") saveTemplateFromBlock(targetBlock);
              if (act === "use-template") openTemplateModal(targetBlock);
              if (act === "delete-block") deleteCurrentBlock(targetBlock);
            });
          }
          if (templateModal && templateModal.dataset.bound !== "1") {
            templateModal.dataset.bound = "1";
            templateModal.addEventListener("click", function(e){
              var closeBtn = e.target && e.target.closest ? e.target.closest("[data-fe-template-close='1']") : null;
              if (closeBtn) {
                e.preventDefault();
                closeTemplateModal();
                return;
              }
              var card = e.target && e.target.closest ? e.target.closest("[data-fe-template-id]") : null;
              var actBtn = e.target && e.target.closest ? e.target.closest("[data-fe-template-act]") : null;
              if (!card || !actBtn) return;
              var id = Number(card.getAttribute("data-fe-template-id") || 0);
              var act = actBtn.getAttribute("data-fe-template-act") || "";
              var item = null;
              for (var i = 0; i < templateState.items.length; i++) {
                if (Number(templateState.items[i].id || 0) === id) { item = templateState.items[i]; break; }
              }
              if (!item) return;
              if (act === "apply") applyTemplateToBlock(templateState.currentBlock, id);
              if (act === "apply-page-before") applyTemplateToPage(id, "before");
              if (act === "apply-page-after") applyTemplateToPage(id, "after");
              if (act === "edit") fillTemplateEditor(item);
              if (act === "delete") deleteTemplateRecord(id);
            });
          }
          if (templateSaveEditBtn && templateSaveEditBtn.dataset.bound !== "1") {
            templateSaveEditBtn.dataset.bound = "1";
            templateSaveEditBtn.addEventListener("click", function(){ updateTemplateRecord(); });
          }
          if (templateCancelEditBtn && templateCancelEditBtn.dataset.bound !== "1") {
            templateCancelEditBtn.dataset.bound = "1";
            templateCancelEditBtn.addEventListener("click", function(){ resetTemplateEditor(); });
          }
          if (sourceModal && sourceModal.dataset.bound !== "1") {
            sourceModal.dataset.bound = "1";
            sourceModal.addEventListener("click", function(e){
              var closeBtn = e.target && e.target.closest ? e.target.closest("[data-fe-source-close='1']") : null;
              if (!closeBtn) return;
              e.preventDefault();
              closeSourceModal();
            });
          }
          if (sourceSaveBtn && sourceSaveBtn.dataset.bound !== "1") {
            sourceSaveBtn.dataset.bound = "1";
            sourceSaveBtn.addEventListener("click", function(){ saveSourceModal(); });
          }
          document.addEventListener("keydown", function(e){
            if (e.key === "Escape" && sourceState.open) {
              e.preventDefault();
              closeSourceModal();
            }
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "s" && sourceState.open) {
              e.preventDefault();
              saveSourceModal();
            }
          }, true);
          document.addEventListener("click", function(e){
            if (contextMenu && !contextMenu.hidden && e.target && !(contextMenu.contains(e.target))) {
              hideContextMenu();
            }
          }, true);
          document.addEventListener("scroll", function(){ hideContextMenu(); }, true);
        }
        function collectBlockCandidates(){
          var store = new Map();
          var nodes = Array.prototype.slice.call(document.body.querySelectorAll("section,article,aside,div"));
          for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            if (!el) continue;
            if ((modal && modal.contains(el)) || (iconModal && iconModal.contains(el)) || (templateModal && templateModal.contains(el)) || (sourceModal && sourceModal.contains(el)) || (contextMenu && contextMenu.contains(el))) continue;
            if (el.tagName === "DIV" && !el.id && !normClass(el.className || "")) continue;
            var key = [
              String(el.tagName || "").toLowerCase(),
              String(el.id || ""),
              normClass(el.className || ""),
              textKey(el.textContent || "")
            ].join("|");
            if (!store.has(key)) store.set(key, []);
            store.get(key).push(el);
          }
          return store;
        }
        function bindBlockEditor(el, item){
          if (!el || !item || el.dataset.feBlockBound === "1") return;
          el.dataset.feBlockBound = "1";
          el.addEventListener("click", function(e){
            if (!e || e.button !== 0) return;
            var targetEl = eventTargetElement(e.target);
            if (targetEl && targetEl.closest) {
              if (targetEl.closest(".fe-toolbar-inline")) return;
              if (targetEl.closest(".fe-context-menu")) return;
              if (targetEl.closest(".fe-template-modal")) return;
              if (targetEl.closest(".fe-source-modal")) return;
              if (targetEl.closest(".fe-media-modal")) return;
              if (targetEl.closest(".fe-icon-modal")) return;
              if (targetEl.closest(".fe-editable")) return;
              if (targetEl.closest(".fe-image-editable")) return;
              if (targetEl.closest(".fe-icon-editable")) return;
              if (targetEl.closest("a,button,input,textarea,select,label")) return;
            }
            e.preventDefault();
            e.stopPropagation();
            var rect = el.getBoundingClientRect();
            showContextMenu(Math.max(12, rect.right - 12), Math.max(12, rect.top + 12), el, targetEl);
          }, true);
          el.addEventListener("contextmenu", function(e){
            e.preventDefault();
            e.stopPropagation();
            showContextMenu(e.clientX, e.clientY, el, eventTargetElement(e.target));
          }, true);
        }
        function attachTemplateBlocks(){
          var idx = buildBlockIndex();
          if (idx.size === 0) return;
          var candidates = collectBlockCandidates();
          var matched = new Set();
          idx.forEach(function(list, key){
            var els = candidates.get(key) || [];
            list.sort(function(a, b){ return (a.occ || 1) - (b.occ || 1); });
            for (var i = 0; i < list.length; i++) {
              var item = list[i];
              var pos = (item.occ || 1) - 1;
              var el = els[pos];
              if (!el || matched.has(el)) continue;
              matched.add(el);
              el.classList.add("fe-block-editable");
              el.dataset.feBlockId = item.id;
              bindBlockEditor(el, item);
            }
          });
        }
        function extractBgUrls(bg){
          var out = [];
          var re = /url\((['"]?)(.*?)\1\)/g;
          var match;
          while ((match = re.exec(String(bg || "")))) {
            if (match[2]) out.push(match[2]);
          }
          return out;
        }
        function addImageCandidate(store, key, el){
          if (!key || !el) return;
          if (!store.has(key)) store.set(key, []);
          store.get(key).push(el);
        }
        function collectImageCandidates(){
          var store = new Map();
          var nodes = Array.prototype.slice.call(document.body.querySelectorAll("*"));
          for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            if (!el || (modal && modal.contains(el)) || (iconModal && iconModal.contains(el)) || (templateModal && templateModal.contains(el)) || (sourceModal && sourceModal.contains(el)) || (contextMenu && contextMenu.contains(el))) continue;
            if (el.hasAttribute && el.hasAttribute("data-fe-image-slot")) continue;
            if (el.tagName === "IMG") {
              var src = el.getAttribute("src") || el.currentSrc || el.src || "";
              if (src) addImageCandidate(store, resolveUrl(src), el);
              continue;
            }
            var bg = "";
            try { bg = window.getComputedStyle(el).backgroundImage || ""; } catch (e) { bg = ""; }
            if (!bg || bg === "none") continue;
            var urls = extractBgUrls(bg);
            for (var j = 0; j < urls.length; j++) {
              addImageCandidate(store, resolveUrl(urls[j]), el);
            }
          }
          return store;
        }
        function replaceBgUrl(backgroundImage, oldUrl, newUrl){
          var oldResolved = resolveUrl(oldUrl);
          var newResolved = resolveUrl(newUrl);
          var bg = String(backgroundImage || "");
          var hit = false;
          var next = bg.replace(/url\((['"]?)(.*?)\1\)/g, function(all, q, url){
            if (hit) return all;
            if (resolveUrl(url) !== oldResolved) return all;
            hit = true;
            return 'url("' + newResolved.replace(/"/g, '\\"') + '")';
          });
          if (!hit) {
            return 'url("' + newResolved.replace(/"/g, '\\"') + '")';
          }
          return next;
        }
        async function saveImageEdit(el, item, newUrl){
          if (!el || !item || !newUrl) return;
          var out = await postJson("/admin/api/front_editor/update.php", {
            pageKey: pageKey,
            id: item.id,
            type: "image",
            url: newUrl
          });
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Lưu ảnh thất bại.");
            return;
          }
          var resolvedOld = resolveUrl(item.url || "");
          var resolvedNew = resolveUrl(newUrl);
          if (el.tagName === "IMG") {
            el.setAttribute("src", newUrl);
            try { el.src = newUrl; } catch (e) {}
          } else {
            var currentBg = "";
            try { currentBg = window.getComputedStyle(el).backgroundImage || ""; } catch (e) { currentBg = ""; }
            el.style.backgroundImage = replaceBgUrl(currentBg, resolvedOld, newUrl);
          }
          el.dataset.feImageUrl = resolvedNew;
          item.url = newUrl;
        }
        function applyManualImageUrl(node, newUrl){
          if (!node || !newUrl) return;
          var resolvedNew = resolveUrl(newUrl);
          var mode = String(node.getAttribute("data-fe-image-mode") || "").toLowerCase();
          var oldUrl = node.dataset.feImageUrl || node.getAttribute("data-fe-image-url") || "";
          if (mode === "background") {
            var currentBg = "";
            try { currentBg = window.getComputedStyle(node).backgroundImage || ""; } catch (e) { currentBg = ""; }
            node.style.backgroundImage = replaceBgUrl(currentBg, oldUrl, newUrl);
          } else {
            var target = node;
            var targetSelector = node.getAttribute("data-fe-image-target") || "";
            if (targetSelector && node.querySelector) {
              var nextTarget = node.querySelector(targetSelector);
              if (nextTarget) target = nextTarget;
            }
            if (target && target.tagName === "IMG") {
              target.setAttribute("src", newUrl);
              try { target.src = newUrl; } catch (e) {}
              target.dataset.feImageUrl = resolvedNew;
            } else {
              var fallbackBg = "";
              try { fallbackBg = window.getComputedStyle(node).backgroundImage || ""; } catch (e) { fallbackBg = ""; }
              node.style.backgroundImage = replaceBgUrl(fallbackBg, oldUrl, newUrl);
            }
          }
          node.dataset.feImageUrl = resolvedNew;
          node.setAttribute("data-fe-image-url", newUrl);
        }
        function bindImageProxyEditor(proxyNode, targetNode, item){
          if (!proxyNode || !targetNode || !item || proxyNode.dataset.feImageProxyBound === "1") return;
          if (proxyNode.hasAttribute && proxyNode.hasAttribute("data-fe-image-slot")) return;
          proxyNode.dataset.feImageProxyBound = "1";
          proxyNode.classList.add("fe-image-editable");
          proxyNode.setAttribute("title", "Chuột phải vào section để đổi ảnh");
          proxyNode.dataset.feImageId = String(item.id || "");
          proxyNode.dataset.feImageUrl = resolveUrl(item.url || "");
          proxyNode.__feImageItem = item;
          proxyNode.__feImageTarget = targetNode;
        }
        function findImageProxyNode(node){
          if (!node || !node.parentElement) return null;
          var current = node.parentElement;
          var depth = 0;
          while (current && current !== document.body && depth < 5) {
            if (current.hasAttribute && current.hasAttribute("data-fe-image-slot")) return current;
            var tag = String(current.tagName || "");
            if (/^(DIV|ARTICLE|FIGURE|LI|SECTION|ASIDE)$/.test(tag)) {
              return current;
            }
            current = current.parentElement;
            depth += 1;
          }
          return null;
        }
        async function saveManualImageSlot(node, slotKey, newUrl){
          if (!node || !slotKey || !newUrl) return;
          var slotPageKey = node.getAttribute("data-fe-page-key") || pageKey;
          var out = await postJson("/admin/api/front_editor/image_slot_save.php", {
            pageKey: slotPageKey,
            slot: slotKey,
            url: newUrl
          });
          if (!out.res.ok || !out.data || !out.data.ok) {
            alert((out.data && out.data.message) ? out.data.message : "Lưu ảnh thất bại.");
            return;
          }
          var all = Array.prototype.slice.call(document.querySelectorAll("[data-fe-image-slot]"));
          for (var i = 0; i < all.length; i++) {
            var sameNode = all[i];
            if (!sameNode || String(sameNode.getAttribute("data-fe-image-slot") || "") !== String(slotKey)) continue;
            applyManualImageUrl(sameNode, newUrl);
          }
        }
        function bindManualImageEditor(node){
          if (!node || !node.getAttribute || node.dataset.feManualImageBound === "1") return;
          var slotKey = String(node.getAttribute("data-fe-image-slot") || "").trim();
          if (!slotKey) return;
          node.dataset.feManualImageBound = "1";
          node.classList.add("fe-image-editable");
          node.setAttribute("title", "Chuột phải vào section để đổi ảnh");
        }
        function attachManualImageEditors(){
          var nodes = Array.prototype.slice.call(document.querySelectorAll("[data-fe-image-slot]"));
          for (var i = 0; i < nodes.length; i++) {
            bindManualImageEditor(nodes[i]);
          }
        }
        function bindImageEditor(node, item){
          if (!node || !item || node.dataset.feImageBound === "1") return;
          if (node.hasAttribute && node.hasAttribute("data-fe-image-slot")) return;
          node.dataset.feImageBound = "1";
          node.classList.add("fe-image-editable");
          node.dataset.feImageId = String(item.id || "");
          node.dataset.feImageUrl = resolveUrl(item.url || "");
          node.__feImageItem = item;
          node.setAttribute("title", "Chuột phải vào section để đổi ảnh");
        }
        function collectIconCandidates(){
          var store = new Map();
          var nodes = Array.prototype.slice.call(document.body.querySelectorAll("i"));
          for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            if (!el) continue;
            if ((modal && modal.contains(el)) || (iconModal && iconModal.contains(el)) || (templateModal && templateModal.contains(el)) || (sourceModal && sourceModal.contains(el)) || (contextMenu && contextMenu.contains(el))) continue;
            var key = iconSignature(el.className || "");
            if (!key) continue;
            if (!store.has(key)) store.set(key, []);
            store.get(key).push(el);
          }
          return store;
        }
        function bindIconEditor(node, item){
          if (!node || !item || node.dataset.feIconBound === "1") return;
          node.dataset.feIconBound = "1";
          node.dataset.feIconId = String(item.id || "");
          node.classList.add("fe-icon-editable");
          node.__feIconItem = item;
          node.setAttribute("title", "Click để đổi icon");
          node.addEventListener("click", function(e){
            e.preventDefault();
            e.stopPropagation();
            openIconModal(node, item);
          }, true);
        }
        function attachIconEditors(){
          var idx = buildIconIndex();
          if (idx.size === 0) return;
          var candidates = collectIconCandidates();
          var matched = new Set();
          idx.forEach(function(list, key){
            var els = candidates.get(key) || [];
            list.sort(function(a, b){ return (a.occ || 1) - (b.occ || 1); });
            for (var i = 0; i < list.length; i++) {
              var item = list[i];
              var pos = (item.occ || 1) - 1;
              var el = els[pos];
              if (!el || matched.has(el)) continue;
              matched.add(el);
              bindIconEditor(el, item);
            }
          });
        }
        function attachImageEditors(){
          var idx = buildImageIndex();
          if (idx.size === 0) return;
          var candidates = collectImageCandidates();
          var matched = new Set();
          idx.forEach(function(list, key){
            var els = candidates.get(key) || [];
            list.sort(function(a, b){ return (a.occ || 1) - (b.occ || 1); });
            if (list.length === 1 && els.length > 0) {
              var singleItem = list[0];
              for (var k = 0; k < els.length; k++) {
                var sameEl = els[k];
                if (!sameEl || matched.has(sameEl)) continue;
                matched.add(sameEl);
                bindImageEditor(sameEl, singleItem);
              }
              return;
            }
            for (var i = 0; i < list.length; i++) {
              var item = list[i];
              var pos = (item.occ || 1) - 1;
              var el = els[pos];
              if (!el || matched.has(el)) continue;
              matched.add(el);
              bindImageEditor(el, item);
              var proxy = findImageProxyNode(el);
              if (proxy && proxy !== el) {
                bindImageProxyEditor(proxy, el, item);
              }
            }
          });
        }

        document.addEventListener("paste", function(e){
          if (!activeText) return;
          e.preventDefault();
          var text = (e.clipboardData || window.clipboardData).getData("text");
          document.execCommand("insertText", false, text);
        }, true);
        document.addEventListener("keydown", function(e){
          if (!activeText) return;
          if (e.key === "Escape") {
            var el = activeText.closest(".fe-editable");
            if (el) exitEdit(el, true);
          }
          if ((e.ctrlKey || e.metaKey) && e.key === "Enter") {
            var el2 = activeText.closest(".fe-editable");
            if (el2) saveTextEdit(el2);
          }
        }, true);
        document.addEventListener("pointerdown", function(e){
          if (!activeWrap) return;
          var toolbar = activeWrap.querySelector(".fe-toolbar-inline");
          var inside = e.target && (activeWrap.contains(e.target) || (toolbar && toolbar.contains(e.target)));
          if (inside) return;
          exitEdit(activeWrap, true);
        }, true);

        function boot(){
          bindMediaModal();
          bindIconModal();
          bindTemplateUi();
          setMediaFilter("image");
          attachTemplateBlocks();
          attachManualTextEditors();
          attachTextEditors();
          attachIconEditors();
          attachManualImageEditors();
          attachImageEditors();
        }
        if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
        else boot();
      })();
    </script>
    <?php
}
