<?php
declare(strict_types=1);

/**
 * In-request import of AI-generated handbook illustrations. No network I/O.
 * HTML and every image are validated and re-encoded before a file is created.
 */

function handbook_ai_media_text(string $value, int $limit): string
{
    $value = trim((string) preg_replace('/\s+/u', ' ', $value));
    if (preg_match('/data\s*:|base64\s*,/i', $value)) {
        throw new InvalidArgumentException('Không được đặt dữ liệu BASE64 trong văn bản hoặc chú thích.');
    }
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit);
}

function handbook_ai_media_link(string $value): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $value)) return '';
    $parts = parse_url($value);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https', 'http'], true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || filter_var($value, FILTER_VALIDATE_URL) === false) return '';
    return $value;
}

/** @return array{bytes:string,width:int,height:int,mime:string} */
function handbook_ai_media_decode(string $source): array
{
    // Canonical, unwrapped BASE64 only: no SVG, URL fetching, parameters or whitespace.
    if (strlen($source) > (int) ceil(5 * 1024 * 1024 / 3) * 4 + 40
        || !preg_match('~\Adata:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/]*={0,2})\z~iD', $source, $matches)) {
        throw new InvalidArgumentException('Ảnh phải là data URI JPEG, PNG hoặc WebP BASE64 hợp lệ, tối đa 5 MiB/ảnh.');
    }
    $bytes = base64_decode($matches[2], true);
    if (!is_string($bytes) || $bytes === '' || strlen($bytes) > 5 * 1024 * 1024 || base64_encode($bytes) !== $matches[2]) {
        throw new InvalidArgumentException('Dữ liệu BASE64 của ảnh không hợp lệ hoặc vượt quá 5 MiB.');
    }
    $info = @getimagesizefromstring($bytes);
    $mime = strtolower($matches[1]);
    $types = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP];
    if (!is_array($info) || (int) ($info[2] ?? 0) !== $types[$mime] || ($info['mime'] ?? '') !== $mime) {
        throw new InvalidArgumentException('Loại MIME khai báo không khớp với dữ liệu ảnh raster.');
    }
    $width = (int) $info[0]; $height = (int) $info[1];
    if ($width < 1 || $height < 1 || $width > 8192 || $height > 8192 || $width * $height > 12_000_000) {
        throw new InvalidArgumentException('Ảnh vượt quá 12 megapixel hoặc cạnh dài 8192 px.');
    }
    return ['bytes' => $bytes, 'width' => $width, 'height' => $height, 'mime' => $mime];
}

/** @return array{bytes:string,width:int,height:int,size:int} */
function handbook_ai_media_compress(array $image): array
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        throw new RuntimeException('Máy chủ chưa bật GD để nén ảnh minh họa.');
    }
    $limit = trim((string) ini_get('memory_limit'));
    if ($limit !== '' && $limit !== '-1') {
        $multiplier = match (strtolower(substr($limit, -1))) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };
        $available = (int) ((float) $limit * $multiplier) - memory_get_usage(true);
        $scale = min(1, 1280 / max($image['width'], $image['height']));
        $canvasPixels = max(1, (int) round($image['width'] * $scale)) * max(1, (int) round($image['height'] * $scale));
        if ($available < $image['width'] * $image['height'] * 8 + $canvasPixels * 8 + 8 * 1024 * 1024) {
            throw new RuntimeException('Không đủ bộ nhớ để nén ảnh an toàn; cần giảm kích thước ảnh nguồn.');
        }
    }
    $source = @imagecreatefromstring($image['bytes']);
    if ($source === false) throw new InvalidArgumentException('Không giải mã được ảnh raster.');
    try {
        $ratio = min(1, 1280 / max($image['width'], $image['height']));
        $width = max(1, (int) round($image['width'] * $ratio));
        $height = max(1, (int) round($image['height'] * $ratio));
        $minimumWidth = min(256, $width);
        while ($width >= 1 && $height >= 1) {
            $canvas = imagecreatetruecolor($width, $height);
            if ($canvas === false) throw new RuntimeException('Không tạo được ảnh nén.');
            try {
                $white = imagecolorallocate($canvas, 255, 255, 255);
                imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
                imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $image['width'], $image['height']);
                imageinterlace($canvas, true);
                foreach ([80, 68, 56, 44, 34] as $quality) {
                    ob_start();
                    try { $ok = @imagejpeg($canvas, null, $quality); $bytes = (string) ob_get_contents(); }
                    finally { ob_end_clean(); }
                    if ($ok && $bytes !== '' && strlen($bytes) < 100 * 1024) {
                        return ['bytes' => $bytes, 'width' => $width, 'height' => $height, 'size' => strlen($bytes)];
                    }
                }
            } finally { imagedestroy($canvas); }
            if ($width <= $minimumWidth) break;
            $next = max($minimumWidth, (int) floor($width * 0.75));
            $height = max(1, (int) round($height * ($next / $width)));
            $width = $next;
        }
    } finally { imagedestroy($source); }
    throw new InvalidArgumentException('Không thể nén ảnh xuống dưới 100 KiB.');
}

/** Copy only semantic, inert markup into a fresh DOM. */
function handbook_ai_media_copy_node(DOMNode $node, DOMNode $parent, DOMDocument $output, array &$images, array &$imageNodes, int &$totalBytes, int $depth = 0): void
{
    if ($depth > 80) throw new InvalidArgumentException('HTML lồng quá nhiều cấp.');
    if ($node instanceof DOMText) { $parent->appendChild($output->createTextNode($node->nodeValue ?? '')); return; }
    if (!$node instanceof DOMElement) return;
    $tag = strtolower($node->tagName);
    $drop = ['script','style','iframe','object','embed','form','input','button','select','textarea','option','svg','math','canvas','video','audio','picture','source','noscript','template','meta','link','base','head','title','frameset','frame','applet'];
    if (in_array($tag, $drop, true) || str_contains($tag, ':')) return;
    $allowed = ['p','h2','h3','h4','ul','ol','li','strong','em','b','i','a','blockquote','figure','figcaption','img','table','thead','tbody','tfoot','tr','th','td','caption','colgroup','col','br','hr'];
    if (!in_array($tag, $allowed, true)) {
        foreach ($node->childNodes as $child) handbook_ai_media_copy_node($child, $parent, $output, $images, $imageNodes, $totalBytes, $depth + 1);
        return;
    }
    $element = $output->createElement($tag);
    if ($tag === 'img') {
        if (count($imageNodes) >= 4) throw new InvalidArgumentException('HTML chỉ nhận tối đa 4 thẻ ảnh minh họa.');
        $image = handbook_ai_media_decode($node->getAttribute('src'));
        $node->removeAttribute('src'); // Do not retain another BASE64 copy in the parsed DOM.
        $hash = hash('sha256', $image['bytes']);
        if (!isset($images[$hash])) {
            if (count($images) >= 4) throw new InvalidArgumentException('Bài cẩm nang chỉ nhận từ 1 đến 4 ảnh raster duy nhất.');
            $totalBytes += strlen($image['bytes']);
            if ($totalBytes > 12 * 1024 * 1024) throw new InvalidArgumentException('Tổng dữ liệu ảnh vượt quá 12 MiB.');
            // Keep only the small re-encoded result. Retaining four decoded
            // originals alongside the DOM would be unsafe on 128 MiB PHP.
            $images[$hash] = handbook_ai_media_compress($image);
        }
        $element->setAttribute('alt', handbook_ai_media_text($node->getAttribute('alt'), 240));
        $imageNodes[] = ['node' => $element, 'hash' => $hash];
    } elseif ($tag === 'a') {
        $href = handbook_ai_media_link($node->getAttribute('href'));
        if ($href !== '') {
            $element->setAttribute('href', $href);
            $element->setAttribute('rel', 'noopener noreferrer');
            if ($node->getAttribute('target') === '_blank') $element->setAttribute('target', '_blank');
        }
    } elseif (in_array($tag, ['td', 'th'], true)) {
        foreach (['colspan', 'rowspan'] as $attribute) {
            $value = $node->getAttribute($attribute);
            if (ctype_digit($value) && (int) $value >= 1 && (int) $value <= 20) $element->setAttribute($attribute, (string) (int) $value);
        }
        if ($tag === 'th' && in_array($node->getAttribute('scope'), ['row','col','rowgroup','colgroup'], true)) {
            $element->setAttribute('scope', $node->getAttribute('scope'));
        }
    } elseif ($tag === 'ol') {
        $start = $node->getAttribute('start');
        if (ctype_digit($start) && (int) $start >= 1 && (int) $start <= 1000) $element->setAttribute('start', (string) (int) $start);
    }
    $parent->appendChild($element);
    foreach ($node->childNodes as $child) handbook_ai_media_copy_node($child, $element, $output, $images, $imageNodes, $totalBytes, $depth + 1);
}

function handbook_ai_media_storage_root(?string $storageRoot): string
{
    $path = $storageRoot ?? __DIR__;
    if ($path === '' || str_contains($path, "\0") || is_link($path)) throw new RuntimeException('Thư mục lưu ảnh không hợp lệ.');
    $root = realpath($path);
    if (!is_string($root) || $root === DIRECTORY_SEPARATOR || !is_dir($root)) throw new RuntimeException('Thư mục lưu ảnh không tồn tại.');
    return rtrim($root, DIRECTORY_SEPARATOR);
}

/** Never follow a symlink in the writable upload tree. */
function handbook_ai_media_directory(string $root, int $postId, bool $create): ?string
{
    $current = $root;
    foreach (['uploads', 'handbook', (string) $postId] as $segment) {
        $current .= '/' . $segment;
        clearstatcache(true, $current);
        if (!file_exists($current) && !is_link($current)) {
            if (!$create || (!@mkdir($current, 0755) && !is_dir($current))) return null;
        }
        if (is_link($current) || !is_dir($current) || realpath($current) !== $current) {
            if ($create) throw new RuntimeException('Không được lưu ảnh qua liên kết thư mục.');
            return null;
        }
    }
    return $current;
}

/** @return array<string,array{dev:int,ino:int}> */
function &handbook_ai_media_created_registry(): array
{
    static $registry = [];
    return $registry;
}

/**
 * @return array{content:string,images:array,created_files:array<string>}
 * @throws InvalidArgumentException for invalid article/image data.
 * @throws RuntimeException for missing server capabilities/storage failures.
 */
function handbook_ai_prepare_article_html(string $html, int $postId, ?string $storageRoot = null): array
{
    if ($postId < 1) throw new InvalidArgumentException('ID bài cẩm nang không hợp lệ.');
    if ($html === '' || strlen($html) > 24 * 1024 * 1024 || !preg_match('//u', $html)) {
        throw new InvalidArgumentException('HTML phải là chuỗi UTF-8, tối đa 24 MiB.');
    }
    // A short tag repeated millions of times can exhaust DOM memory despite a small body.
    if (substr_count($html, '<') > 4000) throw new InvalidArgumentException('HTML có quá nhiều thẻ; cần rút gọn cấu trúc bài.');
    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $html)) throw new InvalidArgumentException('HTML không được chứa DTD hoặc khai báo ENTITY.');
    if (!class_exists('DOMDocument')) throw new RuntimeException('Máy chủ chưa bật DOM để kiểm tra HTML.');
    $memoryLimit = trim((string) ini_get('memory_limit'));
    if ($memoryLimit !== '' && $memoryLimit !== '-1') {
        $multiplier = match (strtolower(substr($memoryLimit, -1))) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };
        if ((int) ((float) $memoryLimit * $multiplier) - memory_get_usage(true) < strlen($html) * 3 + 16 * 1024 * 1024) {
            throw new RuntimeException('Không đủ bộ nhớ để kiểm tra HTML và ảnh an toàn; cần giảm dung lượng ảnh nguồn.');
        }
    }
    $root = handbook_ai_media_storage_root($storageRoot);
    $source = new DOMDocument('1.0', 'UTF-8');
    $previousErrors = libxml_use_internal_errors(true);
    try {
        $loaded = $source->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
    } finally { libxml_clear_errors(); libxml_use_internal_errors($previousErrors); }
    unset($html);
    if (!$loaded) throw new InvalidArgumentException('Không đọc được HTML của bài cẩm nang.');
    $body = $source->getElementsByTagName('body')->item(0);
    if (!$body instanceof DOMElement) throw new InvalidArgumentException('HTML của bài cẩm nang không có nội dung.');
    $output = new DOMDocument('1.0', 'UTF-8');
    $container = $output->createElement('div'); $output->appendChild($container);
    $images = []; $imageNodes = []; $totalBytes = 0;
    // Snapshot because sanitization releases each processed src attribute.
    foreach (iterator_to_array($body->childNodes) as $node) handbook_ai_media_copy_node($node, $container, $output, $images, $imageNodes, $totalBytes);
    unset($source, $body);
    if ($images === []) throw new InvalidArgumentException('Bài cẩm nang cần từ 1 đến 4 ảnh minh họa BASE64.');
    if (trim((string) preg_replace('/\s+/u', ' ', $container->textContent)) === '') throw new InvalidArgumentException('Bài cẩm nang cần có nội dung văn bản.');
    foreach ($images as $hash => $image) {
        $filename = 'ai-' . bin2hex(random_bytes(16)) . '.jpg';
        $images[$hash] = $image + ['filename' => $filename, 'url' => '/uploads/handbook/' . $postId . '/' . $filename, 'alt' => '', 'caption' => '', 'ai_generated' => true];
    }
    unset($image);
    foreach ($imageNodes as $entry) {
        /** @var DOMElement $node */
        $node = $entry['node']; $hash = $entry['hash']; $image = &$images[$hash];
        if ($image['alt'] === '') $image['alt'] = $node->getAttribute('alt');
        for ($ancestor = $node->parentNode; $ancestor instanceof DOMElement && $ancestor !== $container; $ancestor = $ancestor->parentNode) {
            if ($ancestor->tagName !== 'figure') continue;
            foreach ($ancestor->childNodes as $sibling) {
                if ($sibling instanceof DOMElement && $sibling->tagName === 'figcaption') {
                    $caption = handbook_ai_media_text($sibling->textContent, 300);
                    if ($image['caption'] === '') $image['caption'] = $caption;
                    break;
                }
            }
            break;
        }
        foreach (['src' => $image['url'], 'width' => (string) $image['width'], 'height' => (string) $image['height'], 'loading' => 'lazy', 'decoding' => 'async'] as $key => $value) $node->setAttribute($key, $value);
        unset($image);
    }
    $content = '';
    foreach ($container->childNodes as $node) $content .= $output->saveHTML($node);
    if (preg_match('/data\s*:|base64\s*,/i', $content)) throw new InvalidArgumentException('HTML còn chứa dữ liệu BASE64 bên ngoài ảnh minh họa.');
    $created = []; $metadata = [];
    try {
        $directory = handbook_ai_media_directory($root, $postId, true);
        if ($directory === null) throw new RuntimeException('Không tạo được thư mục ảnh cẩm nang.');
        foreach ($images as $image) {
            $target = $directory . '/' . $image['filename'];
            $handle = @fopen($target, 'xb'); // Exclusive: existing media is never overwritten.
            if ($handle === false) throw new RuntimeException('Không tạo được tệp ảnh cẩm nang.');
            $stat = fstat($handle);
            $registry = &handbook_ai_media_created_registry();
            $registry[$target] = ['dev' => (int) ($stat['dev'] ?? -1), 'ino' => (int) ($stat['ino'] ?? -1)];
            $created[] = $target;
            try {
                if (handbook_ai_media_directory($root, $postId, false) !== $directory || is_link($target)) throw new RuntimeException('Đường dẫn lưu ảnh đã thay đổi.');
                $remaining = $image['bytes'];
                while ($remaining !== '') {
                    $written = fwrite($handle, $remaining);
                    if ($written === false || $written === 0) throw new RuntimeException('Không ghi được ảnh cẩm nang.');
                    $remaining = substr($remaining, $written);
                }
                if (!fflush($handle)) throw new RuntimeException('Không hoàn tất được tệp ảnh cẩm nang.');
                @chmod($target, 0644);
            } finally { fclose($handle); }
            $metadata[] = array_intersect_key($image, array_flip(['url','width','height','size','alt','caption','ai_generated']));
        }
    } catch (Throwable $error) {
        handbook_ai_cleanup_created_files($created, $root);
        throw $error;
    }
    return ['content' => $content, 'images' => $metadata, 'created_files' => $created];
}

/** Delete only exact regular files created by this importer in this request. */
function handbook_ai_cleanup_created_files(array $files, ?string $storageRoot = null): void
{
    try { $root = handbook_ai_media_storage_root($storageRoot); } catch (Throwable) { return; }
    $registry = &handbook_ai_media_created_registry();
    $pattern = '~\A' . preg_quote($root, '~') . '/uploads/handbook/([1-9][0-9]*)/ai-[a-f0-9]{32}\.jpg\z~D';
    foreach ($files as $file) {
        if (!is_string($file) || !isset($registry[$file]) || !preg_match($pattern, $file, $matches)) continue;
        $directory = handbook_ai_media_directory($root, (int) $matches[1], false);
        if ($directory === null || dirname($file) !== $directory) continue;
        clearstatcache(true, $file);
        $stat = @lstat($file);
        if (!is_array($stat) || ($stat['mode'] & 0170000) !== 0100000 || is_link($file)
            || (int) $stat['dev'] !== $registry[$file]['dev'] || (int) $stat['ino'] !== $registry[$file]['ino']) continue;
        if (@unlink($file)) unset($registry[$file]);
    }
}
