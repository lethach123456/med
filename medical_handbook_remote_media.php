<?php
declare(strict_types=1);

/** Public image imports only. Every redirect is revalidated and DNS is pinned. */

function handbook_ai_remote_ip_in_range(string $address, string $network, int $bits): bool
{
    $ip = @inet_pton($address); $base = @inet_pton($network);
    if ($ip === false || $base === false || strlen($ip) !== strlen($base)) return false;
    $whole = intdiv($bits, 8); $tail = $bits % 8;
    return substr($ip, 0, $whole) === substr($base, 0, $whole)
        && ($tail === 0 || ((ord($ip[$whole]) ^ ord($base[$whole])) & (0xff << (8 - $tail))) === 0);
}

function handbook_ai_remote_public_ip(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        foreach ([['0.0.0.0',8],['10.0.0.0',8],['100.64.0.0',10],['127.0.0.0',8],['169.254.0.0',16],
            ['172.16.0.0',12],['192.0.0.0',24],['192.0.2.0',24],['192.88.99.0',24],['192.168.0.0',16],
            ['198.18.0.0',15],['198.51.100.0',24],['203.0.113.0',24],['224.0.0.0',3]] as [$network,$bits]) {
            if (handbook_ai_remote_ip_in_range($ip, $network, $bits)) return false;
        }
        return true;
    }
    // Global-unicast only: excludes mapped IPv4, NAT64, link/site-local and multicast.
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        || !handbook_ai_remote_ip_in_range($ip, '2000::', 3)) return false;
    foreach ([['2001::',23],['2001:db8::',32],['2002::',16],['3fff::',20]] as [$network,$bits]) {
        if (handbook_ai_remote_ip_in_range($ip, $network, $bits)) return false;
    }
    return true;
}

/** @return array{url:string,host:string,port:int,literal:bool} */
function handbook_ai_remote_image_url(string $url): array
{
    if ($url === '' || strlen($url) > 8000 || !preg_match('//u', $url)
        || preg_match('/[\x00-\x20\x7f\\\\]|\s/u', $url)) {
        throw new InvalidArgumentException('URL ảnh phải là URL HTTP/HTTPS công khai, không có khoảng trắng hoặc ký tự điều khiển.');
    }
    $parts = parse_url($url);
    if (!is_array($parts) || filter_var($url, FILTER_VALIDATE_URL) === false
        || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http','https'], true)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
        throw new InvalidArgumentException('URL ảnh không được chứa thông tin đăng nhập, fragment hoặc giao thức khác HTTP/HTTPS.');
    }
    $scheme = strtolower($parts['scheme']); $host = strtolower((string) ($parts['host'] ?? ''));
    $port = $scheme === 'https' ? 443 : 80;
    if (isset($parts['port']) && $parts['port'] !== $port) {
        throw new InvalidArgumentException('URL ảnh chỉ được dùng cổng HTTP/HTTPS tiêu chuẩn.');
    }
    $literal = false;
    if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
        $host = substr($host, 1, -1); $literal = true;
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) $host = '';
    } elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $literal = true;
    } else {
        // In particular, never let cURL interpret decimal/octal/hex IPv4 forms.
        $labels = explode('.', $host);
        if (strlen($host) > 253 || count($labels) < 2 || preg_match('/^[0-9]+$/D', end($labels))
            || preg_match('/\A(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*\z/iD', $host)
            || preg_match('/(?:^|\.)(?:localhost|local|internal|home|lan|test|invalid)$/D', $host)) $host = '';
        foreach ($labels as $label) {
            if (!preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D', $label)) $host = '';
        }
    }
    if ($host === '' || ($literal && !handbook_ai_remote_public_ip($host))) {
        throw new InvalidArgumentException('URL ảnh không được trỏ tới địa chỉ nội bộ, dự trữ hoặc địa chỉ IP không chuẩn.');
    }
    foreach (preg_split('/[&;]/', (string) ($parts['query'] ?? '')) ?: [] as $parameter) {
        $key = rawurldecode(explode('=', $parameter, 2)[0]);
        if (preg_match('/\A(?:api[_-]?key|access[_-]?token|token|secret|password|auth|authorization|signature)\z/iD', $key)) {
            throw new InvalidArgumentException('URL ảnh công khai không được chứa token hoặc khóa riêng tư.');
        }
    }
    $authority = str_contains($host, ':') ? '[' . $host . ']' : $host;
    return ['url' => $scheme . '://' . $authority . ($parts['path'] ?? '/')
        . (isset($parts['query']) ? '?' . $parts['query'] : ''), 'host' => $host, 'port' => $port, 'literal' => $literal];
}

function handbook_ai_remote_check_deadline(float $deadline): void
{
    if (!is_finite($deadline) || microtime(true) >= $deadline) {
        throw new InvalidArgumentException('Đã hết thời gian tải ảnh công khai; hãy thử lại hoặc dùng BASE64.');
    }
}

/** @return list<string> */
function handbook_ai_remote_resolve(string $host): array
{
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    $addresses = [];
    foreach (is_array($records) ? $records : [] as $record) {
        if (($record['type'] ?? '') === 'A' && isset($record['ip'])) $addresses[] = $record['ip'];
        if (($record['type'] ?? '') === 'AAAA' && isset($record['ipv6'])) $addresses[] = $record['ipv6'];
    }
    return array_values(array_unique($addresses));
}

/** @return array{url:string,host:string,port:int,literal:bool,ip:string,resolve:list<string>} */
function handbook_ai_remote_target(string $url, callable $resolver, float $deadline): array
{
    $target = handbook_ai_remote_image_url($url);
    handbook_ai_remote_check_deadline($deadline);
    $addresses = $target['literal'] ? [$target['host']] : $resolver($target['host']);
    handbook_ai_remote_check_deadline($deadline);
    if (!is_array($addresses) || $addresses === [] || count($addresses) > 64) {
        throw new InvalidArgumentException('Không phân giải được địa chỉ công khai của máy chủ ảnh.');
    }
    foreach ($addresses as $address) {
        if (!is_string($address) || !handbook_ai_remote_public_ip($address)) {
            throw new InvalidArgumentException('Máy chủ ảnh có bản ghi DNS nội bộ hoặc không an toàn.');
        }
    }
    $ip = (string) inet_ntop(inet_pton($addresses[0]));
    $target['ip'] = $ip;
    $target['resolve'] = $target['literal'] ? [] : [$target['host'] . ':' . $target['port'] . ':'
        . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)];
    return $target;
}

/** Resolve a Location without relaxing URL policy, including relative redirects. */
function handbook_ai_remote_redirect_url(string $base, string $location): string
{
    if ($location === '' || strlen($location) > 8000 || preg_match('/[\x00-\x20\x7f\\\\]|\s/u', $location)) {
        throw new InvalidArgumentException('Chuyển hướng ảnh không có URL hợp lệ.');
    }
    $baseParts = parse_url($base);
    if (preg_match('/\A[a-z][a-z0-9+.-]*:/i', $location)) return handbook_ai_remote_image_url($location)['url'];
    if (str_starts_with($location, '//')) return handbook_ai_remote_image_url($baseParts['scheme'] . ':' . $location)['url'];
    $origin = $baseParts['scheme'] . '://' . $baseParts['host'];
    if (str_starts_with($location, '?')) {
        return handbook_ai_remote_image_url($origin . ($baseParts['path'] ?? '/') . $location)['url'];
    }
    $relative = parse_url($location);
    if (!is_array($relative) || isset($relative['fragment'])) throw new InvalidArgumentException('URL chuyển hướng ảnh không hợp lệ.');
    $path = $relative['path'] ?? '';
    if (!str_starts_with($path, '/')) {
        $basePath = $baseParts['path'] ?? '/';
        $path = substr($basePath, 0, (int) strrpos($basePath, '/') + 1) . $path;
    }
    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '..') { array_pop($segments); continue; }
        if ($segment === '.') continue;
        $segments[] = $segment;
    }
    $path = implode('/', $segments);
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
    return handbook_ai_remote_image_url($origin . $path . (isset($relative['query']) ? '?' . $relative['query'] : ''))['url'];
}

/** Bounded streaming callbacks; also exposed to deterministic transport tests. */
function handbook_ai_remote_http_sink(float $deadline): array
{
    $headers = []; $body = ''; $headerBytes = 0; $error = '';
    $header = static function ($handle, string $line) use (&$headers,&$headerBytes,&$error,$deadline): int {
        $length = strlen($line); $headerBytes += $length;
        if (microtime(true) >= $deadline || $headerBytes > 32768 || $length > 8192) { $error = 'Giới hạn thời gian hoặc kích thước header ảnh bị vượt quá.'; return 0; }
        if (preg_match('~\AHTTP/\S+\s+\d{3}(?:\s|\r|\n|$)~i', $line)) { $headers = []; return $length; }
        $value = rtrim($line, "\r\n");
        if ($value === '') return $length;
        if (!preg_match('/\A([A-Za-z0-9!#$%&\x27*+.^_`|~-]+):[\t ]*([^\x00-\x08\x0a-\x1f\x7f]*)\z/D', $value, $matches)) {
            $error = 'Header ảnh không hợp lệ.'; return 0;
        }
        $key = strtolower($matches[1]); $value = trim($matches[2]);
        if (count($headers[$key] ?? []) >= 8) { $error = 'Header ảnh bị lặp quá nhiều.'; return 0; }
        $headers[$key][] = $value;
        if ($key === 'content-length' && (!ctype_digit($value) || strlen($value) > 10 || (int) $value > 5 * 1024 * 1024)) {
            $error = 'Ảnh nguồn vượt quá 5 MiB hoặc có kích thước không hợp lệ.'; return 0;
        }
        return $length;
    };
    $write = static function ($handle, string $chunk) use (&$body,&$error,$deadline): int {
        $length = strlen($chunk);
        if (microtime(true) >= $deadline || strlen($body) + $length > 5 * 1024 * 1024) {
            $error = 'Ảnh nguồn vượt quá 5 MiB hoặc hết thời gian tải.'; return 0;
        }
        $body .= $chunk; return $length;
    };
    $response = static function (int $status, string $ip) use (&$headers,&$body,&$error): array {
        if ($error !== '') throw new InvalidArgumentException($error);
        return ['status' => $status, 'headers' => $headers, 'body' => $body, 'primary_ip' => $ip];
    };
    return ['header' => $header, 'write' => $write, 'response' => $response];
}

function handbook_ai_remote_curl_options(array $target, float $deadline, array $sink): array
{
    handbook_ai_remote_check_deadline($deadline);
    $remaining = max(1, (int) floor(($deadline - microtime(true)) * 1000));
    return [CURLOPT_URL => $target['url'], CURLOPT_HTTPGET => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_MAXREDIRS => 0, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*',
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_NETRC => CURL_NETRC_IGNORED,
        CURLOPT_HTTPAUTH => CURLAUTH_NONE, CURLOPT_UNRESTRICTED_AUTH => false, CURLOPT_AUTOREFERER => false,
        CURLOPT_CONNECTTIMEOUT_MS => min(5000, $remaining), CURLOPT_TIMEOUT_MS => $remaining,
        CURLOPT_LOW_SPEED_LIMIT => 1024, CURLOPT_LOW_SPEED_TIME => 5, CURLOPT_NOSIGNAL => true,
        CURLOPT_FRESH_CONNECT => true, CURLOPT_FORBID_REUSE => true, CURLOPT_RETURNTRANSFER => false,
        CURLOPT_RESOLVE => $target['resolve'], CURLOPT_IPRESOLVE => str_contains($target['ip'], ':') ? CURL_IPRESOLVE_V6 : CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER => ['Accept: image/png, image/jpeg, image/webp', 'Accept-Encoding: identity'],
        CURLOPT_USERAGENT => 'MedReview-Handbook-Image-Import/1.0', CURLOPT_HEADER => false,
        CURLOPT_HEADERFUNCTION => $sink['header'], CURLOPT_WRITEFUNCTION => $sink['write']];
}

function handbook_ai_remote_curl_transport(array $target, float $deadline): array
{
    if (!function_exists('curl_init') || !defined('CURLOPT_RESOLVE')) throw new RuntimeException('Máy chủ chưa bật cURL có hỗ trợ ghim DNS để tải ảnh an toàn.');
    $handle = curl_init();
    if ($handle === false) throw new RuntimeException('Không khởi tạo được cURL để tải ảnh.');
    $sink = handbook_ai_remote_http_sink($deadline);
    try {
        if (!curl_setopt_array($handle, handbook_ai_remote_curl_options($target, $deadline, $sink))) {
            throw new InvalidArgumentException('Không thiết lập được kết nối tải ảnh an toàn.');
        }
        $ok = curl_exec($handle);
        $response = ($sink['response'])((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($handle, CURLINFO_PRIMARY_IP));
        if ($ok === false) throw new InvalidArgumentException('Không tải được ảnh công khai; có thể kết nối/TLS lỗi hoặc quá thời gian.');
        return $response;
    } finally { curl_close($handle); }
}

/** @return array<string,list<string>> */
function handbook_ai_remote_response_headers(mixed $input): array
{
    if (!is_array($input)) throw new InvalidArgumentException('Phản hồi máy chủ ảnh không hợp lệ.');
    $headers = []; $length = 0;
    foreach ($input as $key => $values) {
        if (!is_string($key) || !preg_match('/\A[A-Za-z0-9-]+\z/D', $key)) throw new InvalidArgumentException('Header ảnh không hợp lệ.');
        foreach (is_array($values) ? $values : [$values] as $value) {
            if (!is_string($value) || preg_match('/[\x00-\x08\x0a-\x1f\x7f]/', $value)) throw new InvalidArgumentException('Header ảnh không hợp lệ.');
            $length += strlen($key) + strlen($value) + 4;
            if ($length > 32768 || strlen($value) > 8192) throw new InvalidArgumentException('Header ảnh quá lớn.');
            $headers[strtolower($key)][] = trim($value);
        }
    }
    foreach (['location','content-type','content-length','content-encoding'] as $key) {
        if (count($headers[$key] ?? []) > 1) throw new InvalidArgumentException('Máy chủ ảnh trả về header không rõ ràng.');
    }
    return $headers;
}

/** Check resource availability before GD allocates a full decoded pixel buffer. */
function handbook_ai_remote_decode_budget(int $width, int $height, ?string $memoryLimit = null, ?int $memoryUsage = null): void
{
    $limit = trim($memoryLimit ?? (string) ini_get('memory_limit'));
    if ($limit === '' || $limit === '-1') return;
    if (!preg_match('/\A([0-9]+)([kmg]?)\z/iD', $limit, $parts)) {
        throw new RuntimeException('Không xác định được giới hạn bộ nhớ để kiểm tra ảnh an toàn.');
    }
    $multiplier = match (strtolower($parts[2])) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };
    $available = (float) $parts[1] * $multiplier - ($memoryUsage ?? memory_get_usage(true));
    if ($available < $width * $height * 8 + 8 * 1024 * 1024) {
        throw new RuntimeException('Không đủ bộ nhớ để giải mã ảnh công khai an toàn; cần giảm kích thước ảnh nguồn.');
    }
}

function handbook_ai_remote_image_mime(string $bytes): string
{
    $info = @getimagesizefromstring($bytes);
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    if (!in_array($mime, ['image/png','image/jpeg','image/webp'], true)) {
        throw new InvalidArgumentException('URL phải trả về file PNG, JPEG hoặc WebP thật, không phải HTML/SVG/GIF.');
    }
    if (($mime === 'image/jpeg' && !str_ends_with($bytes, "\xff\xd9"))
        || ($mime === 'image/png' && !str_ends_with($bytes, "\x00\x00\x00\x00IEND\xae\x42\x60\x82"))
        || ($mime === 'image/webp' && (strlen($bytes) < 12 || unpack('Vsize', substr($bytes, 4, 4))['size'] + 8 !== strlen($bytes)))) {
        throw new InvalidArgumentException('File ảnh tải về bị thiếu dữ liệu hoặc không hoàn chỉnh.');
    }
    // A bounded full decode rejects superficially valid headers with corrupt pixels.
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > 8192 || $info[1] > 8192 || $info[0] * $info[1] > 12_000_000) {
        throw new InvalidArgumentException('Ảnh vượt quá 12 megapixel hoặc cạnh dài 8192 px.');
    }
    if (function_exists('imagecreatefromstring')) {
        handbook_ai_remote_decode_budget((int) $info[0], (int) $info[1]);
        $warning = false;
        set_error_handler(static function (int $severity) use (&$warning): bool { $warning = true; return true; });
        try { $image = imagecreatefromstring($bytes); }
        finally { restore_error_handler(); }
        if ($image === false || $warning) {
            if ($image !== false) imagedestroy($image);
            throw new InvalidArgumentException('Không giải mã được đầy đủ file ảnh tải về.');
        }
        imagedestroy($image);
    }
    return $mime;
}

/**
 * Resolver/transport injection is for isolated tests; request data never chooses them.
 * Resolver: fn(string $host): list<string>.
 * Transport: fn(array $pinnedTarget, float $deadline): array{status:int,headers:array,body:string,primary_ip?:string}.
 * @return array{bytes:string,mime:string}
 */
function handbook_ai_remote_image_download(string $url, ?callable $resolver = null, ?callable $transport = null, ?float $deadline = null): array
{
    $now = microtime(true);
    $deadline = $deadline === null ? $now + 30 : min($deadline, $now + 30);
    handbook_ai_remote_check_deadline($deadline);
    $resolver ??= 'handbook_ai_remote_resolve'; $transport ??= 'handbook_ai_remote_curl_transport';
    $visited = [];
    for ($redirects = 0; $redirects <= 3; $redirects++) {
        $target = handbook_ai_remote_target($url, $resolver, $deadline);
        if (isset($visited[$target['url']])) throw new InvalidArgumentException('URL ảnh bị lặp chuyển hướng.');
        $visited[$target['url']] = true;
        $response = $transport($target, $deadline);
        handbook_ai_remote_check_deadline($deadline);
        if (!is_array($response) || !is_int($response['status'] ?? null) || !is_string($response['body'] ?? null)) {
            throw new InvalidArgumentException('Phản hồi tải ảnh không hợp lệ.');
        }
        if (array_key_exists('primary_ip', $response)
            && (!is_string($response['primary_ip']) || @inet_pton($response['primary_ip']) !== inet_pton($target['ip']))) {
            throw new InvalidArgumentException('Kết nối ảnh không dùng địa chỉ IP đã được kiểm tra.');
        }
        $headers = handbook_ai_remote_response_headers($response['headers'] ?? []);
        $body = $response['body'];
        if (strlen($body) > 5 * 1024 * 1024) throw new InvalidArgumentException('Ảnh nguồn vượt quá 5 MiB.');
        $contentLength = $headers['content-length'][0] ?? null;
        if ($contentLength !== null && (!ctype_digit($contentLength) || strlen($contentLength) > 10 || (int) $contentLength > 5 * 1024 * 1024
            || (int) $contentLength !== strlen($body))) throw new InvalidArgumentException('Kích thước ảnh tải về không hợp lệ hoặc bị thiếu dữ liệu.');
        if (in_array($response['status'], [301,302,303,307,308], true)) {
            if ($redirects === 3) throw new InvalidArgumentException('URL ảnh chuyển hướng quá 3 lần.');
            $url = handbook_ai_remote_redirect_url($target['url'], $headers['location'][0] ?? '');
            continue;
        }
        if ($response['status'] !== 200 || $body === '') throw new InvalidArgumentException('URL ảnh không trả về file ảnh HTTP 200 hợp lệ.');
        if (isset($headers['content-encoding'][0]) && strtolower($headers['content-encoding'][0]) !== 'identity') {
            throw new InvalidArgumentException('Máy chủ ảnh trả về kiểu nén HTTP không được hỗ trợ.');
        }
        $mime = handbook_ai_remote_image_mime($body);
        $declared = strtolower(trim(explode(';', $headers['content-type'][0] ?? '', 2)[0]));
        if ($declared === 'image/jpg') $declared = 'image/jpeg';
        if (!in_array($declared, ['', 'application/octet-stream','binary/octet-stream',$mime], true)) {
            throw new InvalidArgumentException('Loại MIME HTTP không khớp file ảnh thực tế.');
        }
        return ['bytes' => $body, 'mime' => $mime];
    }
    throw new InvalidArgumentException('Không tải được ảnh công khai.');
}
