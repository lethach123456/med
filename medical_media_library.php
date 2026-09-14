<?php
declare(strict_types=1);

/**
 * Shared storage for images that are imported into MedReview.  Keeping this
 * independent from the admin session lets the authenticated content API use
 * exactly the same /uploads/library tree as the Media Library.
 */

if (!function_exists('medical_media_project_root')) {
    function medical_media_project_root(): string
    {
        static $root = null;
        if (is_string($root)) return $root;
        $resolved = realpath(__DIR__);
        $root = is_string($resolved) && $resolved !== '' ? $resolved : __DIR__;
        return $root;
    }

    function medical_media_library_dir(): string
    {
        return medical_media_project_root() . '/uploads/library';
    }

    function medical_media_folder_slug(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        if (function_exists('slugify')) {
            $slug = (string) slugify($value);
        } else {
            $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            $value = strtr($value, [
                'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
                'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
                'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
                'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
                'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
                'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d',
            ]);
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            $value = is_string($ascii) && $ascii !== '' ? $ascii : $value;
            $value = strtolower($value);
            $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', $value), '-');
        }
        $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');
        return substr($slug, 0, 96);
    }

    /** Returns a safe path relative to uploads/library, or null when invalid. */
    function medical_media_normalize_folder(?string $folder): ?string
    {
        $folder = trim(str_replace('\\', '/', (string) $folder));
        $folder = trim($folder, '/');
        if ($folder === '') return '';
        if (str_contains($folder, "\0") || str_contains($folder, '..')) return null;

        $segments = array_values(array_filter(explode('/', $folder), static fn(string $part): bool => trim($part) !== ''));
        if ($segments === [] || count($segments) > 6) return null;

        $safe = [];
        foreach ($segments as $segment) {
            $slug = medical_media_folder_slug($segment);
            if ($slug === '') return null;
            $safe[] = $slug;
        }
        return implode('/', $safe);
    }

    /** @return array{folder:string,path:string,url:string}|null */
    function medical_media_create_folder(string $folder): ?array
    {
        $folder = medical_media_normalize_folder($folder);
        if ($folder === null) return null;

        $library = medical_media_library_dir();
        $target = $library . ($folder !== '' ? '/' . $folder : '');
        if (!is_dir($target) && !@mkdir($target, 0755, true)) return null;
        if (!is_dir($target)) return null;

        return [
            'folder' => $folder,
            'path' => $target,
            'url' => '/uploads/library' . ($folder !== '' ? '/' . $folder : ''),
        ];
    }

    function medical_media_local_library_path(string $url): ?string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if (!str_starts_with($path, '/uploads/library/') || str_contains($path, '..') || str_contains($path, "\0")) return null;
        $candidate = realpath(medical_media_project_root() . $path);
        $library = medical_media_library_dir();
        if (!is_string($candidate) || !is_file($candidate) || !str_starts_with($candidate, $library . DIRECTORY_SEPARATOR)) return null;
        return $candidate;
    }

    function medical_media_is_public_ip(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /** Blocks loopback/private targets before cURL makes the request. */
    function medical_media_is_public_host(string $host): bool
    {
        $host = trim(strtolower($host));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) return false;
        if (filter_var($host, FILTER_VALIDATE_IP)) return medical_media_is_public_ip($host);

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (!is_array($records) || $records === []) return false;
        foreach ($records as $record) {
            $ip = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
            if ($ip === '' || !medical_media_is_public_ip($ip)) return false;
        }
        return true;
    }

    function medical_media_redirect_url(string $current, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') return null;
        if (preg_match('#^https?://#i', $location)) return $location;

        $parts = parse_url($current);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');
        if ($host === '') return null;
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        if (str_starts_with($location, '/')) return $scheme . '://' . $host . $port . $location;
        $path = (string) ($parts['path'] ?? '/');
        return $scheme . '://' . $host . $port . rtrim(str_replace('\\', '/', dirname($path)), '/') . '/' . $location;
    }

    /**
     * Converts cURL's cumulative phase values into milliseconds. `ttfb_ms` is
     * cURL's conventional start-transfer time (from request start to first
     * byte); the smaller `server_wait_ms` makes slow origin responses visible
     * after DNS/TCP/TLS have completed.
     *
     * @return array{dns_ms:int,tcp_ms:int,tls_ms:int,ttfb_ms:int,server_wait_ms:int,download_ms:int,total_ms:int,http_status:int}
     */
    function medical_media_curl_timing(mixed $curl, int $httpStatus = 0): array
    {
        $seconds = static function (string $constant) use ($curl): float {
            if (!defined($constant)) return 0.0;
            $value = curl_getinfo($curl, constant($constant));
            return is_numeric($value) ? max(0.0, (float) $value) : 0.0;
        };
        $nameLookup = $seconds('CURLINFO_NAMELOOKUP_TIME');
        $connect = max($nameLookup, $seconds('CURLINFO_CONNECT_TIME'));
        $appConnect = $seconds('CURLINFO_APPCONNECT_TIME');
        $handshakeEnd = $appConnect > 0 ? max($connect, $appConnect) : $connect;
        $startTransfer = max($handshakeEnd, $seconds('CURLINFO_STARTTRANSFER_TIME'));
        $total = max($startTransfer, $seconds('CURLINFO_TOTAL_TIME'));

        return [
            'dns_ms' => (int) round($nameLookup * 1000),
            'tcp_ms' => (int) round(max(0.0, $connect - $nameLookup) * 1000),
            'tls_ms' => (int) round(max(0.0, $handshakeEnd - $connect) * 1000),
            'ttfb_ms' => (int) round($startTransfer * 1000),
            'server_wait_ms' => (int) round(max(0.0, $startTransfer - $handshakeEnd) * 1000),
            'download_ms' => (int) round(max(0.0, $total - $startTransfer) * 1000),
            'total_ms' => (int) round($total * 1000),
            'http_status' => max(0, $httpStatus),
        ];
    }

    /** @return array<string,mixed> */
    function medical_media_timing_merge(array $timing, array $hop, string $url = ''): array
    {
        $keys = ['dns_ms', 'tcp_ms', 'tls_ms', 'ttfb_ms', 'server_wait_ms', 'download_ms', 'total_ms'];
        if (!isset($timing['hops']) || !is_array($timing['hops'])) {
            $timing = array_fill_keys($keys, 0);
            $timing['http_status'] = 0;
            $timing['hops'] = [];
        }
        foreach ($keys as $key) $timing[$key] = (int) ($timing[$key] ?? 0) + (int) ($hop[$key] ?? 0);
        $timing['http_status'] = (int) ($hop['http_status'] ?? $timing['http_status'] ?? 0);
        $hop['url'] = $url;
        $timing['hops'][] = $hop;
        $timing['redirects'] = max(0, count($timing['hops']) - 1);
        return $timing;
    }

    /** Joins two completed timing traces, used when curl_multi hands a redirect to the safe single-URL downloader. */
    function medical_media_timing_join(array $first, array $second): array
    {
        $keys = ['dns_ms', 'tcp_ms', 'tls_ms', 'ttfb_ms', 'server_wait_ms', 'download_ms', 'total_ms'];
        $result = array_fill_keys($keys, 0);
        foreach ($keys as $key) $result[$key] = (int) ($first[$key] ?? 0) + (int) ($second[$key] ?? 0);
        $secondStatus = (int) ($second['http_status'] ?? 0);
        $result['http_status'] = $secondStatus > 0 ? $secondStatus : (int) ($first['http_status'] ?? 0);
        $firstHops = isset($first['hops']) && is_array($first['hops']) ? $first['hops'] : [];
        $secondHops = isset($second['hops']) && is_array($second['hops']) ? $second['hops'] : [];
        $result['hops'] = array_values(array_merge($firstHops, $secondHops));
        $result['redirects'] = max(0, count($result['hops']) - 1);
        return $result;
    }

    /**
     * @return array{ok:bool,bytes?:string,mime?:string,error?:string,http_status?:int,curl_error?:string,timing?:array<string,mixed>}
     */
    function medical_media_download_remote_image(string $url, int $maxSeconds = 18): array
    {
        if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'Máy chủ chưa bật cURL để tải ảnh.'];
        $current = trim($url);
        $maxBytes = 12 * 1024 * 1024;
        $maxSeconds = min(18, max(1, $maxSeconds));
        $deadline = microtime(true) + $maxSeconds;
        $timing = [];

        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $remaining = (int) ceil($deadline - microtime(true));
            if ($remaining < 1) return ['ok' => false, 'error' => 'Tải ảnh quá thời gian cho phép.', 'timing' => $timing];
            $parts = parse_url($current);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = (string) ($parts['host'] ?? '');
            if (!in_array($scheme, ['http', 'https'], true) || !medical_media_is_public_host($host)) {
                return ['ok' => false, 'error' => 'URL ảnh không hợp lệ hoặc trỏ tới mạng nội bộ.', 'timing' => $timing];
            }

            $bytes = '';
            $location = '';
            $tooLarge = false;
            $curl = curl_init($current);
            if ($curl === false) return ['ok' => false, 'error' => 'Không khởi tạo được kết nối tải ảnh.', 'timing' => $timing];
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => min(3, $remaining),
                // Redirects share one deadline; they cannot multiply a slow
                // image request into many 18-second waits.
                CURLOPT_TIMEOUT => $remaining,
                CURLOPT_USERAGENT => 'MedReview Image Importer/1.0',
                CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/jpeg,image/png,image/gif;q=0.9,*/*;q=0.1'],
                CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$location): int {
                    if (stripos($header, 'Location:') === 0) $location = trim(substr($header, 9));
                    return strlen($header);
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$bytes, &$tooLarge, $maxBytes): int {
                    if (strlen($bytes) + strlen($chunk) > $maxBytes) {
                        $tooLarge = true;
                        return 0;
                    }
                    $bytes .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $mime = strtolower(trim((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE)));
            $error = (string) curl_error($curl);
            $timing = medical_media_timing_merge($timing, medical_media_curl_timing($curl, $status), $current);
            curl_close($curl);

            if ($tooLarge) return ['ok' => false, 'error' => 'Ảnh vượt quá giới hạn 12MB.', 'http_status' => $status, 'timing' => $timing];
            if ($status >= 300 && $status < 400 && $location !== '') {
                $next = medical_media_redirect_url($current, $location);
                if ($next === null) return ['ok' => false, 'error' => 'Redirect ảnh không hợp lệ.', 'http_status' => $status, 'timing' => $timing];
                $current = $next;
                continue;
            }
            if ($ok === false || $status < 200 || $status >= 300 || $bytes === '') {
                $message = $status > 0
                    ? 'Máy chủ ảnh trả về HTTP ' . $status . '.'
                    : 'Không thể kết nối tới máy chủ ảnh.';
                if ($error !== '') $message .= ' ' . $error;
                return ['ok' => false, 'error' => $message, 'http_status' => $status, 'curl_error' => $error, 'timing' => $timing];
            }
            return ['ok' => true, 'bytes' => $bytes, 'mime' => strtok($mime, ';') ?: '', 'http_status' => $status, 'timing' => $timing];
        }
        return ['ok' => false, 'error' => 'Ảnh chuyển hướng quá nhiều lần.', 'timing' => $timing];
    }

    /**
     * Re-encodes an imported image as a progressive JPEG no larger than 100 KB.
     * A source that cannot reach that limit is rejected instead of leaking a
     * large original file into the public media library.
     *
     * @return array{ok:bool,url?:string,width?:int,height?:int,size?:int,error?:string}
     */
    function medical_media_store_compressed_jpeg(string $bytes, string $folder): array
    {
        $imageInfo = @getimagesizefromstring($bytes);
        if (!is_array($imageInfo)) return ['ok' => false, 'error' => 'Dữ liệu tải về không phải ảnh hợp lệ.'];
        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        // A decoded GD image consumes roughly four bytes per pixel before any
        // working canvas is created. Keep this comfortably below the PHP
        // memory ceiling while still accepting normal 12 MP phone photos.
        if ($width < 1 || $height < 1 || $width * $height > 12_000_000) {
            return ['ok' => false, 'error' => 'Kích thước ảnh không hợp lệ hoặc quá lớn.'];
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return ['ok' => false, 'error' => 'Máy chủ chưa bật GD để nén ảnh.'];
        }
        $source = @imagecreatefromstring($bytes);
        if (!$source) return ['ok' => false, 'error' => 'Không đọc được dữ liệu ảnh.'];

        // Images from external websites can be several megabytes. The old
        // implementation repeatedly encoded a 1,800px canvas at many quality
        // levels, which could spend the whole article request on its first
        // gallery image. 1,280px is still sharp in the gallery/lightbox and
        // makes the promised <100 KB result practical for a full batch.
        $maxSide = 1280;
        $targetSize = (100 * 1024) - 1; // Strictly below 100 KB.
        $minSide = 256;
        $qualitySteps = [80, 68, 56, 44, 34];
        $ratio = min(1, $maxSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));
        $minWidth = min($minSide, $targetWidth);
        $saved = '';
        $savedWidth = $targetWidth;
        $savedHeight = $targetHeight;

        // Lower JPEG quality first; when that is not enough, progressively
        // reduce dimensions. This retains as much visual detail as possible
        // while guaranteeing the file-size contract.
        while ($targetWidth >= 1 && $targetHeight >= 1) {
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$canvas) {
                imagedestroy($source);
                return ['ok' => false, 'error' => 'Không tạo được ảnh nén.'];
            }
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            if (function_exists('imageinterlace')) imageinterlace($canvas, true);

            foreach ($qualitySteps as $quality) {
                ob_start();
                $written = @imagejpeg($canvas, null, $quality);
                $candidate = (string) ob_get_clean();
                if (!$written || $candidate === '') continue;
                if (strlen($candidate) <= $targetSize) {
                    $saved = $candidate;
                    $savedWidth = $targetWidth;
                    $savedHeight = $targetHeight;
                    break;
                }
            }
            imagedestroy($canvas);

            if ($saved !== '') break;
            if ($targetWidth <= $minWidth) break;
            $nextWidth = max($minWidth, (int) floor($targetWidth * 0.75));
            if ($nextWidth >= $targetWidth) $nextWidth = $targetWidth - 1;
            $scale = $nextWidth / $targetWidth;
            $targetWidth = $nextWidth;
            $targetHeight = max(1, (int) round($targetHeight * $scale));
        }
        imagedestroy($source);
        if ($saved === '') return ['ok' => false, 'error' => 'Không thể nén ảnh xuống dưới 100 KB.'];

        $destination = medical_media_create_folder($folder);
        if ($destination === null) return ['ok' => false, 'error' => 'Không tạo được folder ảnh.'];
        $hash = substr(hash('sha256', $bytes), 0, 20);
        $filename = 'image-' . $hash . '.jpg';
        $target = $destination['path'] . '/' . $filename;
        clearstatcache(true, $target);
        // Replacing a same-source legacy file is important: its previous
        // compression settings may have allowed it to exceed the new 100 KB
        // limit. Files already below the limit are safely reused.
        if (!is_file($target) || (int) (filesize($target) ?: 0) > $targetSize) {
            $temp = $target . '.tmp-' . bin2hex(random_bytes(4));
            if (@file_put_contents($temp, $saved, LOCK_EX) === false || !@rename($temp, $target)) {
                @unlink($temp);
                return ['ok' => false, 'error' => 'Không lưu được ảnh đã nén.'];
            }
            @chmod($target, 0644);
        }
        clearstatcache(true, $target);
        return [
            'ok' => true,
            'url' => $destination['url'] . '/' . $filename,
            'width' => $savedWidth,
            'height' => $savedHeight,
            'size' => (int) (filesize($target) ?: strlen($saved)),
        ];
    }

    /**
     * Stores an already-downloaded image without re-encoding it. The type is
     * derived from the binary payload rather than the remote URL or HTTP
     * content type, so a remote server cannot make us publish an unsafe file
     * extension inside the media library.
     *
     * @return array{ok:bool,url?:string,width?:int,height?:int,size?:int,format?:string,error?:string}
     */
    function medical_media_store_downloaded_original(string $bytes, string $folder): array
    {
        $maxBytes = 12 * 1024 * 1024;
        if ($bytes === '' || strlen($bytes) > $maxBytes) {
            return ['ok' => false, 'error' => 'Ảnh vượt quá giới hạn 12MB.'];
        }

        $imageInfo = @getimagesizefromstring($bytes);
        if (!is_array($imageInfo)) {
            return ['ok' => false, 'error' => 'Dữ liệu tải về không phải ảnh hợp lệ.'];
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        // Validate dimensions before storing so malformed image metadata and
        // decompression-bomb-like sources cannot enter the public library.
        if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 || ($width * $height) > 12_000_000) {
            return ['ok' => false, 'error' => 'Kích thước ảnh không hợp lệ hoặc quá lớn.'];
        }

        $type = (int) ($imageInfo[2] ?? 0);
        $formats = [
            IMAGETYPE_JPEG => ['extension' => 'jpg', 'format' => 'jpeg'],
            IMAGETYPE_PNG => ['extension' => 'png', 'format' => 'png'],
            IMAGETYPE_GIF => ['extension' => 'gif', 'format' => 'gif'],
        ];
        if (defined('IMAGETYPE_WEBP')) {
            $formats[(int) constant('IMAGETYPE_WEBP')] = ['extension' => 'webp', 'format' => 'webp'];
        }
        // AVIF support depends on the PHP/libgd build. Only accept it when
        // this runtime can identify it as an image type.
        if (defined('IMAGETYPE_AVIF')) {
            $formats[(int) constant('IMAGETYPE_AVIF')] = ['extension' => 'avif', 'format' => 'avif'];
        }
        if (!isset($formats[$type])) {
            return ['ok' => false, 'error' => 'Định dạng ảnh chưa được hỗ trợ.'];
        }

        $destination = medical_media_create_folder($folder);
        if ($destination === null) {
            return ['ok' => false, 'error' => 'Không tạo được folder ảnh.'];
        }

        $hash = substr(hash('sha256', $bytes), 0, 20);
        $extension = (string) $formats[$type]['extension'];
        $filename = 'source-' . $hash . '.' . $extension;
        $target = $destination['path'] . '/' . $filename;
        clearstatcache(true, $target);

        if (!is_file($target)) {
            try {
                $temp = $target . '.tmp-' . bin2hex(random_bytes(4));
            } catch (Throwable $exception) {
                return ['ok' => false, 'error' => 'Không tạo được tên tạm để lưu ảnh.'];
            }
            if (@file_put_contents($temp, $bytes, LOCK_EX) === false || !@rename($temp, $target)) {
                @unlink($temp);
                return ['ok' => false, 'error' => 'Không lưu được ảnh tải về.'];
            }
            @chmod($target, 0644);
        }

        clearstatcache(true, $target);
        return [
            'ok' => true,
            'url' => $destination['url'] . '/' . $filename,
            'width' => $width,
            'height' => $height,
            'size' => (int) (filesize($target) ?: strlen($bytes)),
            'format' => (string) $formats[$type]['format'],
        ];
    }

    /**
     * Re-encodes an existing local library image as a JPEG no larger than
     * 200 KB. This is shared by the Library's manual compressor and uploads,
     * so images added directly into any folder receive the same treatment.
     *
     * @return array{ok:bool,status?:int,message?:string,error?:string,file?:array<string,mixed>}
     */
    function medical_media_compress_local_image(string $sourcePath, bool $removeConvertedSource = false): array
    {
        $libraryDir = realpath(medical_media_library_dir());
        $projectRoot = medical_media_project_root();
        $source = realpath($sourcePath);
        if (!is_string($source) || $source === '' || !is_file($source)) {
            return ['ok' => false, 'status' => 404, 'error' => 'File không tồn tại.'];
        }
        if (!is_string($libraryDir) || $libraryDir === '' || !str_starts_with($source, $libraryDir . DIRECTORY_SEPARATOR)) {
            return ['ok' => false, 'status' => 403, 'error' => 'Chỉ cho phép nén file trong uploads/library.'];
        }
        if (!str_starts_with($source, $projectRoot . DIRECTORY_SEPARATOR)) {
            return ['ok' => false, 'status' => 403, 'error' => 'File không thuộc thư mục dự án.'];
        }

        $extension = strtolower((string) pathinfo($source, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return ['ok' => false, 'status' => 422, 'error' => 'Chỉ hỗ trợ nén jpg/png/webp.'];
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($source);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return ['ok' => false, 'status' => 422, 'error' => 'Định dạng ảnh không hỗ trợ.'];
        }
        $oldSize = (int) (filesize($source) ?: 0);
        if ($oldSize <= 0) {
            return ['ok' => false, 'status' => 500, 'error' => 'Không đọc được dung lượng file.'];
        }

        $imageInfo = @getimagesize($source);
        $sourceWidth = is_array($imageInfo) ? (int) ($imageInfo[0] ?? 0) : 0;
        $sourceHeight = is_array($imageInfo) ? (int) ($imageInfo[1] ?? 0) : 0;
        if ($sourceWidth < 1 || $sourceHeight < 1 || $sourceWidth > 12000 || $sourceHeight > 12000 || ($sourceWidth * $sourceHeight) > 12_000_000) {
            return ['ok' => false, 'status' => 422, 'error' => 'Kích thước ảnh không hợp lệ hoặc quá lớn để nén an toàn.'];
        }

        $canGd = function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
            && function_exists('imagejpeg')
            && (function_exists('imagecreatefromjpeg') || function_exists('imagecreatefrompng') || function_exists('imagecreatefromwebp'));
        if (!$canGd) {
            return ['ok' => false, 'status' => 500, 'error' => 'Server chưa bật GD để nén ảnh.'];
        }

        $rotation = 0;
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            try {
                $exif = @exif_read_data($source);
                $orientation = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
                if ($orientation === 3) $rotation = 180;
                if ($orientation === 6) $rotation = -90;
                if ($orientation === 8) $rotation = 90;
            } catch (Throwable $exception) {
            }
        }

        $image = null;
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) $image = @imagecreatefromjpeg($source);
        if ($mime === 'image/png' && function_exists('imagecreatefrompng')) $image = @imagecreatefrompng($source);
        if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $image = @imagecreatefromwebp($source);
        if (!$image) {
            return ['ok' => false, 'status' => 422, 'error' => 'Không đọc được ảnh.'];
        }

        if ($rotation !== 0 && function_exists('imagerotate')) {
            $rotated = @imagerotate($image, $rotation, 0);
            if ($rotated) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxSide = 1800;
        // Keep the resulting image strictly below 200 KB. The one-byte margin
        // avoids edge cases where an exact 200 KiB output is shown as 200 KB
        // by browsers or CDNs.
        $targetBytes = (200 * 1024) - 1;
        $minSide = 320;
        $qualitySteps = [82, 76, 70, 64, 58, 52, 46, 40, 34, 28];
        $scale = min(1, $maxSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $saved = '';
        $savedWidth = $targetWidth;
        $savedHeight = $targetHeight;

        while ($targetWidth >= 1 && $targetHeight >= 1) {
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$canvas) {
                imagedestroy($image);
                return ['ok' => false, 'status' => 500, 'error' => 'Không tạo được ảnh trung gian để nén.'];
            }
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
            imagealphablending($canvas, true);
            imagesavealpha($canvas, false);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            if (function_exists('imageinterlace')) imageinterlace($canvas, true);

            foreach ($qualitySteps as $quality) {
                ob_start();
                $written = @imagejpeg($canvas, null, $quality);
                $candidate = (string) ob_get_clean();
                if (!$written || $candidate === '') continue;
                if (strlen($candidate) <= $targetBytes) {
                    $saved = $candidate;
                    $savedWidth = $targetWidth;
                    $savedHeight = $targetHeight;
                    break;
                }
            }
            imagedestroy($canvas);

            if ($saved !== '') break;
            $largestSide = max($targetWidth, $targetHeight);
            if ($largestSide <= $minSide) break;
            $nextLargest = max($minSide, (int) floor($largestSide * 0.9));
            if ($nextLargest >= $largestSide) $nextLargest = $largestSide - 1;
            $nextScale = $nextLargest / $largestSide;
            $targetWidth = max(1, (int) round($targetWidth * $nextScale));
            $targetHeight = max(1, (int) round($targetHeight * $nextScale));
        }
        imagedestroy($image);

        if ($saved === '') {
            return ['ok' => false, 'status' => 422, 'error' => 'Không thể nén ảnh xuống dưới 200KB với cấu hình hiện tại.'];
        }

        $sourceUrl = '/' . ltrim(str_replace('\\', '/', substr($source, strlen($projectRoot))), '/');
        $replacedOriginal = in_array($extension, ['jpg', 'jpeg'], true);
        $target = $replacedOriginal ? $source : ((string) preg_replace('/\.[^.]+$/', '.jpg', $source));
        $targetUrl = $replacedOriginal ? $sourceUrl : ((string) preg_replace('/\.[^.]+$/', '.jpg', $sourceUrl));
        if ($target === '' || $targetUrl === '') {
            return ['ok' => false, 'status' => 500, 'error' => 'Không xác định được file ảnh nén.'];
        }

        try {
            $temporary = $target . '.tmp-' . bin2hex(random_bytes(4));
        } catch (Throwable $exception) {
            return ['ok' => false, 'status' => 500, 'error' => 'Không tạo được file tạm để nén ảnh.'];
        }
        if (@file_put_contents($temporary, $saved, LOCK_EX) === false || !@rename($temporary, $target)) {
            @unlink($temporary);
            return ['ok' => false, 'status' => 500, 'error' => 'Ghi file nén thất bại.'];
        }
        @chmod($target, 0644);
        clearstatcache(true, $target);

        $sourceRemoved = false;
        $warning = '';
        if (!$replacedOriginal && $removeConvertedSource) {
            if (@unlink($source)) {
                $sourceRemoved = true;
            } else {
                $warning = 'Đã tạo JPG nén nhưng chưa xoá được file gốc.';
            }
        }

        $newSize = (int) (filesize($target) ?: strlen($saved));
        $message = $replacedOriginal
            ? 'Đã nén ảnh JPG dưới 200KB (ghi đè file cũ).'
            : 'Đã tạo bản JPG nén dưới 200KB.';
        if ($warning !== '') $message .= ' ' . $warning;

        return [
            'ok' => true,
            'message' => $message,
            'file' => [
                'url' => $targetUrl,
                'source_url' => $sourceUrl,
                'mime' => 'image/jpeg',
                'old_size' => $oldSize,
                'new_size' => $newSize,
                'width' => $savedWidth,
                'height' => $savedHeight,
                'replaced_original' => $replacedOriginal,
                'source_removed' => $sourceRemoved,
                'warning' => $warning,
            ],
        ];
    }

    /**
     * Imports a batch into an own folder for a facility. Remote URLs never get
     * returned to the caller; only locally-owned /uploads/library URLs do.
     *
     * @return array{folder:string,urls:array<int,string>,map:array<string,string>,errors:array<int,array<string,string>>}
     */
    function medical_media_import_facility_images(int $facilityId, string $facilitySlug, array $sources): array
    {
        $slug = medical_media_folder_slug($facilitySlug);
        if ($slug === '') $slug = 'co-so-y-te';
        $folder = 'co-so-y-te/facility-' . max(1, $facilityId) . '-' . $slug;
        $result = ['folder' => $folder, 'urls' => [], 'map' => [], 'errors' => []];
        $seen = [];
        // The extension waits for a finished response and PHP-FPM permits a
        // little under two minutes. Use most of that allowance for a complete
        // gallery instead of silently saving just the first image. The faster
        // compressor above keeps normal 6–12 image galleries well below this.
        $deadline = microtime(true) + 85;
        $uniqueSources = [];
        foreach ($sources as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || isset($uniqueSources[$candidate])) continue;
            if (count($uniqueSources) >= 12) {
                $result['errors'][] = ['url' => $candidate, 'message' => 'Chỉ nhập tối đa 12 ảnh mỗi lần.'];
                continue;
            }
            $uniqueSources[$candidate] = true;
        }
        $sourceQueue = array_keys($uniqueSources);

        foreach ($sourceQueue as $sourceIndex => $source) {
            if (isset($seen[$source])) continue;
            $seen[$source] = true;

            $local = medical_media_local_library_path($source);
            if ($local !== null) {
                $bytes = @file_get_contents($local);
                $download = is_string($bytes) && $bytes !== '' ? ['ok' => true, 'bytes' => $bytes] : ['ok' => false, 'error' => 'Không đọc được ảnh nội bộ.'];
            } else {
                $remaining = (int) floor($deadline - microtime(true));
                if ($remaining < 1) {
                    $result['errors'][] = ['url' => $source, 'message' => 'Bỏ qua ảnh do đã hết thời gian xử lý lô ảnh.'];
                    continue;
                }
                // Leave a fair share of the batch for every remaining URL.
                // This prevents one slow host from consuming the whole job.
                $left = max(1, count($sourceQueue) - $sourceIndex);
                $downloadTimeout = max(3, min(8, (int) floor($remaining / $left)));
                $download = medical_media_download_remote_image($source, min($downloadTimeout, $remaining));
            }
            if (!($download['ok'] ?? false) || !isset($download['bytes'])) {
                $error = ['url' => $source, 'message' => (string) ($download['error'] ?? 'Không tải được ảnh.')];
                foreach (['http_status', 'curl_error'] as $key) {
                    if (array_key_exists($key, $download) && $download[$key] !== '' && $download[$key] !== 0) $error[$key] = $download[$key];
                }
                $result['errors'][] = $error;
                continue;
            }
            $stored = medical_media_store_compressed_jpeg((string) $download['bytes'], $folder);
            if (!($stored['ok'] ?? false) || empty($stored['url'])) {
                $result['errors'][] = ['url' => $source, 'message' => (string) ($stored['error'] ?? 'Không lưu được ảnh.')];
                continue;
            }
            $localUrl = (string) $stored['url'];
            $result['map'][$source] = $localUrl;
            if (!in_array($localUrl, $result['urls'], true)) $result['urls'][] = $localUrl;
        }
        return $result;
    }
}
