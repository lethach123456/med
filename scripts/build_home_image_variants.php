<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_media_remote_variants.php';

/** IP range comparison covers CGNAT and IPv6 special-use ranges missed by some PHP versions. */
function medical_home_image_ip_in_range(string $ip, string $range): bool
{
    [$network, $bits] = explode('/', $range, 2);
    $addressBytes = @inet_pton($ip);
    $networkBytes = @inet_pton($network);
    if (!is_string($addressBytes) || !is_string($networkBytes) || strlen($addressBytes) !== strlen($networkBytes)) return false;
    $prefix = (int) $bits;
    $wholeBytes = intdiv($prefix, 8);
    if (substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) return false;
    $remainder = $prefix % 8;
    return $remainder === 0 || ((ord($addressBytes[$wholeBytes]) ^ ord($networkBytes[$wholeBytes])) & (255 << (8 - $remainder))) === 0;
}

function medical_home_image_is_public_ip(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return false;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        foreach (['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
            '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'] as $range) {
            if (medical_home_image_ip_in_range($ip, $range)) return false;
        }
        return true;
    }
    if (!medical_home_image_ip_in_range($ip, '2000::/3')) return false;
    foreach (['2001::/23', '2001:db8::/32', '2002::/16'] as $range) {
        if (medical_home_image_ip_in_range($ip, $range)) return false;
    }
    return true;
}

/** Redirects may stay on the selected source host only, using HTTPS and no credentials. */
function medical_home_image_is_safe_target(string $url, string $sourceUrl): bool
{
    if (!in_array($sourceUrl, medical_media_remote_variant_sources(), true)
        || strlen($url) > 2048 || preg_match('/[\x00-\x20\\\\]/', $url)
        || filter_var($url, FILTER_VALIDATE_URL) === false) return false;
    $parts = parse_url($url);
    $source = parse_url($sourceUrl);
    return is_array($parts) && is_array($source)
        && ($parts['scheme'] ?? '') === 'https'
        && strtolower((string) ($parts['host'] ?? '')) === $source['host']
        && (int) ($parts['port'] ?? 443) === 443
        && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment']);
}

function medical_home_image_redirect_url(string $current, string $location): ?string
{
    if ($location === '' || strlen($location) > 2048 || preg_match('/[\x00-\x20\\\\]/', $location)) return null;
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $location)) return $location;
    if (str_starts_with($location, '//')) return 'https:' . $location;
    $parts = parse_url($current);
    if (!is_array($parts)) return null;
    $origin = 'https://' . $parts['host'];
    if (str_starts_with($location, '/')) return $origin . $location;
    if (str_starts_with($location, '?')) return $origin . ($parts['path'] ?? '/') . $location;
    return $origin . rtrim(dirname((string) ($parts['path'] ?? '/')), '/') . '/' . $location;
}

/** @return list<string> */
function medical_home_image_public_addresses(string $host): array
{
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    if (!is_array($records) || $records === [] || count($records) > 32) throw new RuntimeException('Cannot safely resolve source host.');
    $addresses = [];
    foreach ($records as $record) {
        $ip = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
        if (!medical_home_image_is_public_ip($ip)) throw new RuntimeException('Source DNS returned a non-public address.');
        $addresses[$ip] = $ip;
    }
    // Prefer IPv4 while allowing validated public IPv6 when the host has no A record.
    usort($addresses, static fn(string $a, string $b): int => (int) str_contains($a, ':') <=> (int) str_contains($b, ':'));
    return array_values($addresses);
}

/** Downloads only an exact allowlisted image, with the checked IP pinned at every redirect hop. */
function medical_home_image_download(string $sourceUrl, string $temporary): int
{
    if (!in_array($sourceUrl, medical_media_remote_variant_sources(), true)) throw new RuntimeException('Source URL is not explicitly allowlisted.');
    $stream = fopen($temporary, 'w+b');
    if ($stream === false) throw new RuntimeException('Cannot open temporary image.');
    $current = $sourceUrl;
    $deadline = microtime(true) + 35;
    $totalBytes = 0;
    $maxBytes = 12 * 1024 * 1024;
    try {
        for ($hop = 0; $hop <= 3; $hop++) {
            if (!medical_home_image_is_safe_target($current, $sourceUrl)) throw new RuntimeException('Redirect is not a safe same-host HTTPS target.');
            $host = (string) parse_url($current, PHP_URL_HOST);
            $pinnedIp = medical_home_image_public_addresses($host)[0];
            $remaining = (int) ceil($deadline - microtime(true));
            if ($remaining < 1) throw new RuntimeException('Image download deadline exceeded.');
            ftruncate($stream, 0);
            rewind($stream);
            $location = '';
            $headersBytes = 0;
            $tooLarge = false;
            $curl = curl_init($current);
            if ($curl === false) throw new RuntimeException('Cannot initialize cURL.');
            curl_setopt_array($curl, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROXY => '', // Environment proxies must not bypass the pinned DNS result.
                CURLOPT_RESOLVE => [$host . ':443:' . (str_contains($pinnedIp, ':') ? '[' . $pinnedIp . ']' : $pinnedIp)],
                CURLOPT_IPRESOLVE => str_contains($pinnedIp, ':') ? CURL_IPRESOLVE_V6 : CURL_IPRESOLVE_V4,
                CURLOPT_CONNECTTIMEOUT => min(5, $remaining),
                CURLOPT_TIMEOUT => $remaining,
                CURLOPT_HTTPAUTH => CURLAUTH_NONE,
                CURLOPT_UNRESTRICTED_AUTH => false,
                CURLOPT_USERAGENT => 'MedReview Public Homepage Image Builder/1.0',
                CURLOPT_HTTPHEADER => ['Accept: image/webp,image/jpeg,image/png'],
                CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$location, &$headersBytes, &$tooLarge, $maxBytes): int {
                    $headersBytes += strlen($header);
                    if ($headersBytes > 65536) return 0;
                    if (stripos($header, 'Location:') === 0) $location = trim(substr($header, 9));
                    if (preg_match('/^Content-Length:\s*(\d+)/i', $header, $match) && (float) $match[1] > $maxBytes) {
                        $tooLarge = true;
                        return 0;
                    }
                    return strlen($header);
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $bytes) use ($stream, &$totalBytes, &$tooLarge, $maxBytes): int {
                    $totalBytes += strlen($bytes);
                    if ($totalBytes > $maxBytes) { $tooLarge = true; return 0; }
                    $written = fwrite($stream, $bytes);
                    return is_int($written) ? $written : 0;
                },
            ]);
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $connectedIp = (string) curl_getinfo($curl, CURLINFO_PRIMARY_IP);
            $mime = strtolower(trim(explode(';', (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE))[0]));
            $error = curl_error($curl);
            curl_close($curl);
            if ($tooLarge) throw new RuntimeException('Image exceeds the 12 MiB download limit.');
            if ($ok === false) throw new RuntimeException('Image download failed: ' . $error);
            if (inet_pton($connectedIp) !== inet_pton($pinnedIp)) throw new RuntimeException('Connection did not use the pinned public address.');
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $next = medical_home_image_redirect_url($current, $location);
                if ($hop === 3 || $next === null || !medical_home_image_is_safe_target($next, $sourceUrl)) throw new RuntimeException('Unsafe or excessive image redirect.');
                $current = $next;
                continue;
            }
            if ($status !== 200 || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) throw new RuntimeException('Source did not return a supported image with HTTP 200.');
            fflush($stream);
            $size = (int) fstat($stream)['size'];
            if ($size < 1 || $size > $maxBytes) throw new RuntimeException('Empty or oversized downloaded image.');
            return $size;
        }
        throw new RuntimeException('Image redirect limit exceeded.');
    } finally {
        fclose($stream);
    }
}

function medical_home_image_build(array $arguments): int
{
    if (in_array('--help', $arguments, true)) {
        echo "Usage: php scripts/build_home_image_variants.php [--force] [exact-allowlisted-URL ...]\n";
        echo "Defaults to the explicitly approved homepage image sources; originals exist only in temporary files.\n";
        foreach (medical_media_remote_variant_sources() as $url) echo $url . "\n";
        return 0;
    }
    try {
        if (!extension_loaded('curl') || !extension_loaded('gd') || !function_exists('imagewebp')) throw new RuntimeException('cURL and GD with WebP are required.');
        $force = false;
        $urls = [];
        foreach ($arguments as $argument) {
            if ($argument === '--force') { $force = true; continue; }
            if (!in_array($argument, medical_media_remote_variant_sources(), true)) throw new RuntimeException('Only exact allowlisted source URLs are accepted.');
            $urls[$argument] = $argument;
        }
        if ($urls === []) $urls = medical_media_remote_variant_sources();
        $root = realpath(dirname(__DIR__));
        if (!is_string($root)) throw new RuntimeException('Project root cannot be resolved.');
        $directory = $root . '/assets/media/variants';
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('Cannot create local variant directory.');
        if (realpath($directory) !== $directory) throw new RuntimeException('Variant directory must not be symlinked.');
        $manifestPath = $root . '/assets/media/home-image-variants.json';
        if (is_link($manifestPath)) throw new RuntimeException('Manifest must not be symlinked.');
        $old = medical_media_remote_variant_manifest();
        $manifest = ['version' => 1, 'encoder' => 'webp-q78-v1', 'images' => []];
        foreach (medical_media_remote_variant_sources() as $url) {
            if (medical_media_remote_image_from_manifest($url, $old) !== null) $manifest['images'][$url] = $old['images'][$url];
        }
        foreach ($urls as $url) {
            if (!$force && isset($manifest['images'][$url])) {
                echo $url . " (valid variants already built; use --force to refresh)\n";
                continue;
            }
            $temporary = tempnam(sys_get_temp_dir(), 'medreview-home-image-');
            if (!is_string($temporary)) throw new RuntimeException('Cannot create temporary original.');
            try {
                echo 'Downloading allowlisted public image: ' . $url . "\n";
                $bytes = medical_home_image_download($url, $temporary);
                $info = @getimagesize($temporary);
                if (!is_array($info) || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
                    || $info[0] < 320 || $info[0] > 12000 || $info[1] < 1 || $info[1] > 12000 || $info[0] * $info[1] > 12000000) {
                    throw new RuntimeException('Downloaded image failed type/dimension validation (max 12 megapixels).');
                }
                $original = match ($info[2]) {
                    IMAGETYPE_JPEG => @imagecreatefromjpeg($temporary),
                    IMAGETYPE_PNG => @imagecreatefrompng($temporary),
                    IMAGETYPE_WEBP => @imagecreatefromwebp($temporary),
                };
                if (!$original instanceof GdImage) throw new RuntimeException('GD cannot decode downloaded image.');
                $entry = ['source_bytes' => $bytes, 'source_sha256' => hash_file('sha256', $temporary), 'width' => $info[0], 'height' => $info[1], 'variants' => []];
                try {
                    foreach ([320, 640, 960] as $width) {
                        if ($width > $info[0]) continue;
                        $height = max(1, (int) round($info[1] * $width / $info[0]));
                        $canvas = imagecreatetruecolor($width, $height);
                        imagealphablending($canvas, false);
                        imagesavealpha($canvas, true);
                        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
                        imagecopyresampled($canvas, $original, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
                        $encodedFile = tempnam($directory, '.building-remote-');
                        if (!is_string($encodedFile)) { imagedestroy($canvas); throw new RuntimeException('Cannot create temporary variant.'); }
                        try {
                            if (!imagewebp($canvas, $encodedFile, 78)) throw new RuntimeException('WebP encoding failed.');
                            $encodedInfo = @getimagesize($encodedFile);
                            if (!is_array($encodedInfo) || $encodedInfo[0] !== $width || $encodedInfo[1] !== $height || $encodedInfo[2] !== IMAGETYPE_WEBP) throw new RuntimeException('Generated image failed validation.');
                            $fingerprint = substr((string) hash_file('sha256', $encodedFile), 0, 24);
                            $variantUrl = '/assets/media/variants/remote-' . $fingerprint . '-' . $width . '.webp';
                            $destination = $root . $variantUrl;
                            $variantBytes = (int) filesize($encodedFile);
                            if (is_link($destination)) throw new RuntimeException('Refusing symlinked variant output.');
                            if (is_file($destination)) {
                                if (hash_file('sha256', $destination) !== hash_file('sha256', $encodedFile)) throw new RuntimeException('Variant fingerprint collision.');
                            } elseif (!chmod($encodedFile, 0644) || !rename($encodedFile, $destination)) {
                                throw new RuntimeException('Cannot atomically publish variant.');
                            }
                            $entry['variants'][] = ['url' => $variantUrl, 'width' => $width, 'height' => $height, 'bytes' => $variantBytes];
                            echo $variantUrl . ' ' . $width . 'x' . $height . ' ' . $variantBytes . ' B (' . round(100 * (1 - $variantBytes / $bytes), 1) . "% smaller)\n";
                        } finally {
                            imagedestroy($canvas);
                            if (is_file($encodedFile)) unlink($encodedFile);
                        }
                    }
                } finally {
                    imagedestroy($original);
                }
                $manifest['images'][$url] = $entry;
            } finally {
                if (is_file($temporary)) unlink($temporary);
            }
        }
        $manifestTemporary = tempnam(dirname($manifestPath), '.building-home-manifest-');
        if (!is_string($manifestTemporary)) throw new RuntimeException('Cannot create temporary manifest.');
        try {
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (file_put_contents($manifestTemporary, $json) !== strlen($json) || !chmod($manifestTemporary, 0644)
                || !rename($manifestTemporary, $manifestPath)) throw new RuntimeException('Cannot atomically publish manifest.');
        } finally {
            if (is_file($manifestTemporary)) unlink($manifestTemporary);
        }
        echo "Built local variants and manifest; no DB/gallery changes; temporary originals removed.\n";
        return 0;
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        return 1;
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(medical_home_image_build(array_slice($argv, 1)));
}
