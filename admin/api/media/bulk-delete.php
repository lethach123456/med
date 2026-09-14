<?php
declare(strict_types=1);

/**
 * Safe, two-step deletion for several media-library entries.
 *
 * The endpoint intentionally does not accept a broad folder path or a shell
 * command.  Every requested file/folder is resolved below uploads/library,
 * scanned without following symlinks, normalised, and only then deleted.
 */

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_media_library.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

// Keep irreversible actions on the signed-in admin host.  Browsers omit
// Origin for a few same-origin navigations, so only validate it when present.
$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
if ($origin !== '') {
    $originParts = parse_url($origin);
    $originHost = strtolower((string) ($originParts['host'] ?? ''));
    if (isset($originParts['port'])) {
        $originHost .= ':' . (int) $originParts['port'];
    }
    $requestHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $originScheme = strtolower((string) ($originParts['scheme'] ?? ''));
    $hasUnexpectedOriginPart = isset($originParts['user'], $originParts['pass'], $originParts['path'], $originParts['query'], $originParts['fragment']);
    if ($originHost === '' || $requestHost === '' || !in_array($originScheme, ['http', 'https'], true) || $hasUnexpectedOriginPart || !hash_equals($requestHost, $originHost)) {
        json_response(['ok' => false, 'message' => 'Nguồn gửi yêu cầu không hợp lệ.'], 403);
    }
}

/** @return array{files:int,folders:int,bytes:int} */
function medical_media_bulk_delete_empty_stats(): array
{
    return ['files' => 0, 'folders' => 0, 'bytes' => 0];
}

/** @param array{files:int,folders:int,bytes:int} $into @param array{files:int,folders:int,bytes:int} $addition */
function medical_media_bulk_delete_add_stats(array &$into, array $addition): void
{
    $into['files'] += max(0, (int) $addition['files']);
    $into['folders'] += max(0, (int) $addition['folders']);
    $into['bytes'] += max(0, (int) $addition['bytes']);
}

function medical_media_bulk_delete_is_inside(string $path, string $root): bool
{
    return str_starts_with($path, $root . DIRECTORY_SEPARATOR);
}

/**
 * Confirm every component is a real item, not a symlink.  Checking each
 * component means `realpath()` can never walk through a link outside root.
 *
 * @param list<string> $segments
 */
function medical_media_bulk_delete_assert_no_symlink(string $root, array $segments): void
{
    $walk = $root;
    foreach ($segments as $segment) {
        $walk .= DIRECTORY_SEPARATOR . $segment;
        if (is_link($walk)) {
            throw new RuntimeException('Phát hiện liên kết không an toàn trong Thư viện.');
        }
    }
}

/**
 * @return array{kind:'folder',folder:string,path:string}
 */
function medical_media_bulk_delete_resolve_folder(mixed $value, string $libraryRoot): array
{
    if (!is_string($value)) {
        throw new InvalidArgumentException('Đường dẫn folder không hợp lệ.');
    }

    $folder = medical_media_normalize_folder($value);
    if ($folder === null || $folder === '') {
        throw new InvalidArgumentException('Không được xoá folder gốc của Thư viện.');
    }

    $segments = explode('/', $folder);
    medical_media_bulk_delete_assert_no_symlink($libraryRoot, $segments);

    $target = $libraryRoot . DIRECTORY_SEPARATOR . $folder;
    $realTarget = realpath($target);
    if (!is_string($realTarget) || $realTarget === '' || !is_dir($realTarget)) {
        throw new RuntimeException('Một folder được chọn không còn tồn tại.');
    }
    if (!medical_media_bulk_delete_is_inside($realTarget, $libraryRoot) || is_link($realTarget)) {
        throw new RuntimeException('Folder được chọn nằm ngoài Thư viện hoặc không an toàn.');
    }

    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($realTarget, strlen($libraryRoot) + 1));
    if ($relative === '') {
        throw new InvalidArgumentException('Không được xoá folder gốc của Thư viện.');
    }

    return ['kind' => 'folder', 'folder' => $relative, 'path' => $realTarget];
}

/**
 * @return array{kind:'file',url:string,relative:string,path:string,size:int}
 */
function medical_media_bulk_delete_resolve_file(mixed $value, string $libraryRoot): array
{
    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException('URL file không hợp lệ.');
    }

    $parsedPath = parse_url(trim($value), PHP_URL_PATH);
    if (!is_string($parsedPath) || !str_starts_with($parsedPath, '/uploads/library/')) {
        throw new InvalidArgumentException('Chỉ được xoá file trong /uploads/library/.');
    }

    $encodedRelative = substr($parsedPath, strlen('/uploads/library/'));
    if ($encodedRelative === '') {
        throw new InvalidArgumentException('URL file không hợp lệ.');
    }

    $rawSegments = explode('/', $encodedRelative);
    $segments = [];
    foreach ($rawSegments as $rawSegment) {
        $segment = rawurldecode($rawSegment);
        if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, "\0") || str_contains($segment, '/') || str_contains($segment, '\\')) {
            throw new InvalidArgumentException('URL file có đường dẫn không hợp lệ.');
        }
        $segments[] = $segment;
    }

    medical_media_bulk_delete_assert_no_symlink($libraryRoot, $segments);
    $target = $libraryRoot . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    $realTarget = realpath($target);
    if (!is_string($realTarget) || $realTarget === '' || !is_file($realTarget)) {
        throw new RuntimeException('Một file được chọn không còn tồn tại.');
    }
    if (!medical_media_bulk_delete_is_inside($realTarget, $libraryRoot) || is_link($realTarget)) {
        throw new RuntimeException('File được chọn nằm ngoài Thư viện hoặc không an toàn.');
    }

    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($realTarget, strlen($libraryRoot) + 1));
    $urlSegments = array_map(static fn(string $segment): string => rawurlencode($segment), explode('/', $relative));
    return [
        'kind' => 'file',
        'url' => '/uploads/library/' . implode('/', $urlSegments),
        'relative' => $relative,
        'path' => $realTarget,
        'size' => max(0, (int) filesize($realTarget)),
    ];
}

/** @return array{files:int,folders:int,bytes:int} */
function medical_media_bulk_delete_folder_stats(string $directory): array
{
    // The selected root is itself a folder to be deleted.
    $stats = ['files' => 0, 'folders' => 1, 'bytes' => 0];

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }
            $path = $item->getPathname();
            if ($item->isLink() || is_link($path)) {
                throw new RuntimeException('Folder có liên kết không an toàn nên không thể xoá tự động.');
            }
            if ($item->isFile()) {
                $stats['files']++;
                $stats['bytes'] += max(0, (int) $item->getSize());
                continue;
            }
            if ($item->isDir()) {
                $stats['folders']++;
                continue;
            }
            throw new RuntimeException('Folder có loại tệp không hỗ trợ nên không thể xoá tự động.');
        }
    } catch (RuntimeException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        throw new RuntimeException('Không thể đọc đầy đủ nội dung folder để xoá an toàn.', 0, $exception);
    }

    return $stats;
}

/**
 * Delete an already-audited folder tree.  The running counter is deliberately
 * updated after each successful unlink/rmdir, so an unexpected failure can be
 * reported precisely rather than claiming that the whole tree was removed.
 *
 * @param array{files:int,folders:int,bytes:int} $deleted
 */
function medical_media_bulk_delete_remove_folder(string $directory, array &$deleted): void
{
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }
            $path = $item->getPathname();
            if ($item->isLink() || is_link($path)) {
                throw new RuntimeException('Phát hiện liên kết không an toàn; đã dừng xoá folder.');
            }
            if ($item->isFile()) {
                $size = max(0, (int) $item->getSize());
                if (!@unlink($path)) {
                    throw new RuntimeException('Không thể xoá một tệp trong folder.');
                }
                $deleted['files']++;
                $deleted['bytes'] += $size;
                continue;
            }
            if ($item->isDir()) {
                if (!@rmdir($path)) {
                    throw new RuntimeException('Không thể xoá một folder con.');
                }
                $deleted['folders']++;
                continue;
            }
            throw new RuntimeException('Folder có loại tệp không hỗ trợ nên đã dừng xoá.');
        }
    } catch (RuntimeException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        throw new RuntimeException('Không thể xoá toàn bộ nội dung folder.', 0, $exception);
    }

    if (!@rmdir($directory)) {
        throw new RuntimeException('Đã xoá nội dung nhưng không thể xoá folder gốc.');
    }
    $deleted['folders']++;
}

function medical_media_bulk_delete_same_or_child(string $value, string $parent): bool
{
    return $value === $parent || str_starts_with($value, $parent . '/');
}

/** @param array<string,mixed> $target @return array<string,mixed> */
function medical_media_bulk_delete_public_target(array $target): array
{
    if (($target['kind'] ?? '') === 'folder') {
        return ['kind' => 'folder', 'folder' => (string) $target['folder']];
    }
    return ['kind' => 'file', 'url' => (string) $target['url']];
}

$body = read_json_body();
$rawItems = $body['items'] ?? null;
if (!is_array($rawItems) || $rawItems === []) {
    json_response(['ok' => false, 'message' => 'Hãy chọn ít nhất một file hoặc folder.'], 422);
}
if (count($rawItems) > 500) {
    json_response(['ok' => false, 'message' => 'Mỗi lần chỉ được xoá tối đa 500 mục.'], 422);
}

$libraryRoot = realpath(medical_media_library_dir());
if (!is_string($libraryRoot) || $libraryRoot === '' || !is_dir($libraryRoot) || is_link($libraryRoot)) {
    json_response(['ok' => false, 'message' => 'Không xác định được thư mục Thư viện.'], 500);
}

$resolvedFolders = [];
$resolvedFiles = [];
try {
    foreach ($rawItems as $rawItem) {
        if (!is_array($rawItem)) {
            throw new InvalidArgumentException('Có mục được chọn không hợp lệ.');
        }
        $kind = (string) ($rawItem['kind'] ?? '');
        if ($kind === 'folder') {
            $target = medical_media_bulk_delete_resolve_folder($rawItem['folder'] ?? null, $libraryRoot);
            $resolvedFolders[$target['folder']] = $target;
            continue;
        }
        if ($kind === 'file') {
            $target = medical_media_bulk_delete_resolve_file($rawItem['url'] ?? null, $libraryRoot);
            $resolvedFiles[$target['relative']] = $target;
            continue;
        }
        throw new InvalidArgumentException('Loại mục được chọn không hợp lệ.');
    }
} catch (InvalidArgumentException $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 422);
} catch (RuntimeException $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 409);
}

// Keep only highest-level folders.  A selected parent fully owns every child
// folder/file, preventing duplicate counting and double removal.
$folderTargets = array_values($resolvedFolders);
usort($folderTargets, static function (array $a, array $b): int {
    $depthA = substr_count((string) $a['folder'], '/');
    $depthB = substr_count((string) $b['folder'], '/');
    return ($depthA <=> $depthB) ?: strcmp((string) $a['folder'], (string) $b['folder']);
});

$keptFolders = [];
$ignoredDescendants = 0;
foreach ($folderTargets as $target) {
    $covered = false;
    foreach ($keptFolders as $parentFolder) {
        if (medical_media_bulk_delete_same_or_child((string) $target['folder'], (string) $parentFolder['folder'])) {
            $covered = true;
            $ignoredDescendants++;
            break;
        }
    }
    if (!$covered) {
        $keptFolders[] = $target;
    }
}

$keptFiles = [];
foreach ($resolvedFiles as $target) {
    $covered = false;
    foreach ($keptFolders as $parentFolder) {
        if (medical_media_bulk_delete_same_or_child((string) $target['relative'], (string) $parentFolder['folder'])) {
            $covered = true;
            $ignoredDescendants++;
            break;
        }
    }
    if (!$covered) {
        $keptFiles[] = $target;
    }
}

$targets = array_merge($keptFiles, $keptFolders);
if ($targets === []) {
    json_response(['ok' => false, 'message' => 'Không còn mục hợp lệ để xoá.'], 422);
}

$planned = medical_media_bulk_delete_empty_stats();
$previewItems = [];
try {
    foreach ($targets as $target) {
        if ($target['kind'] === 'file') {
            $stats = ['files' => 1, 'folders' => 0, 'bytes' => (int) $target['size']];
        } else {
            $stats = medical_media_bulk_delete_folder_stats((string) $target['path']);
        }
        medical_media_bulk_delete_add_stats($planned, $stats);
        $previewItems[] = array_merge(medical_media_bulk_delete_public_target($target), ['stats' => $stats]);
    }
} catch (RuntimeException $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 409);
}

$selection = [
    'requested_items' => count($rawItems),
    'normalized_items' => count($targets),
    'ignored_descendants' => $ignoredDescendants,
];

// Preview is always read-only, even when a client accidentally includes the
// confirmation token.  The caller must make a second explicit request.
if (($body['preview'] ?? false) === true) {
    json_response([
        'ok' => true,
        'preview' => true,
        'message' => 'Đã kiểm tra các mục được chọn. Chưa có dữ liệu nào bị xoá.',
        'selection' => $selection,
        'stats' => $planned,
        'items' => $previewItems,
    ]);
}

if ((string) ($body['confirmation'] ?? '') !== 'DELETE_SELECTED_ITEMS') {
    json_response([
        'ok' => false,
        'code' => 'bulk_confirmation_required',
        'message' => 'Cần xác nhận xoá các mục đã chọn.',
        'selection' => $selection,
        'stats' => $planned,
        'items' => $previewItems,
    ], 409);
}

$deleted = medical_media_bulk_delete_empty_stats();
$completedItems = [];
$failure = null;
$remainingItems = [];

foreach ($targets as $index => $target) {
    try {
        if ($target['kind'] === 'file') {
            $size = max(0, (int) $target['size']);
            if (is_link((string) $target['path']) || !is_file((string) $target['path']) || !@unlink((string) $target['path'])) {
                throw new RuntimeException('Không thể xoá file đã chọn.');
            }
            $deleted['files']++;
            $deleted['bytes'] += $size;
        } else {
            medical_media_bulk_delete_remove_folder((string) $target['path'], $deleted);
        }
        $completedItems[] = medical_media_bulk_delete_public_target($target);
    } catch (Throwable $exception) {
        $failure = [
            'item' => medical_media_bulk_delete_public_target($target),
            'message' => $exception->getMessage(),
        ];
        foreach (array_slice($targets, $index + 1) as $remainingTarget) {
            $remainingItems[] = medical_media_bulk_delete_public_target($remainingTarget);
        }
        break;
    }
}

if ($failure !== null) {
    json_response([
        'ok' => false,
        'code' => 'partial_delete',
        'message' => 'Việc xoá dừng giữa chừng. Một phần dữ liệu có thể đã bị xoá; hãy tải lại Thư viện để kiểm tra.',
        'planned' => $planned,
        'deleted' => $deleted,
        'completed_items' => $completedItems,
        'failed' => $failure,
        'remaining_items' => $remainingItems,
    ], 500);
}

json_response([
    'ok' => true,
    'preview' => false,
    'message' => 'Đã xoá các mục đã chọn.',
    'selection' => $selection,
    'deleted' => $deleted,
    'items' => $completedItems,
]);
