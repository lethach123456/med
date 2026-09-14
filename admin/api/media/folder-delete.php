<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_media_library.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

// The existing media endpoints are session-protected. This extra check keeps
// this irreversible operation limited to requests originating from this exact
// admin host when browsers send an Origin header.
$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
if ($origin !== '') {
    $originParts = parse_url($origin);
    $originHost = strtolower((string) ($originParts['host'] ?? ''));
    if (isset($originParts['port'])) {
        $originHost .= ':' . (int) $originParts['port'];
    }
    $requestHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($originHost === '' || $requestHost === '' || !hash_equals($requestHost, $originHost)) {
        json_response(['ok' => false, 'message' => 'Nguồn gửi yêu cầu không hợp lệ.'], 403);
    }
}

$body = read_json_body();
$folder = medical_media_normalize_folder((string) ($body['folder'] ?? ''));
$recursive = ($body['recursive'] ?? false) === true;
$deleteConfirmation = (string) ($body['confirmation'] ?? '');

/**
 * Read a folder without following symlinks.  A symlink in the library is
 * treated as unsafe rather than being deleted or traversed: it might point
 * outside of /uploads/library.
 *
 * @return array{files:int,folders:int,bytes:int}
 */
function medical_media_folder_delete_stats(string $directory): array
{
    $stats = ['files' => 0, 'folders' => 0, 'bytes' => 0];

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) continue;
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

/** Deletes only the already-audited target tree; it never follows symlinks. */
function medical_media_folder_delete_recursive(string $directory): void
{
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) continue;
            $path = $item->getPathname();
            if ($item->isLink() || is_link($path)) {
                throw new RuntimeException('Phát hiện liên kết không an toàn; đã dừng xoá folder.');
            }
            if ($item->isFile()) {
                if (!@unlink($path)) throw new RuntimeException('Không thể xoá một tệp trong folder.');
                continue;
            }
            if ($item->isDir()) {
                if (!@rmdir($path)) throw new RuntimeException('Không thể xoá một folder con.');
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
}

// The media-library root must never be a delete target.
if ($folder === null || $folder === '') {
    json_response(['ok' => false, 'message' => 'Chỉ được xoá một folder con trong Thư viện.'], 422);
}

$libraryDir = realpath(medical_media_library_dir());
if (!is_string($libraryDir) || $libraryDir === '' || !is_dir($libraryDir)) {
    json_response(['ok' => false, 'message' => 'Không xác định được thư mục Thư viện.'], 500);
}

$segments = explode('/', $folder);
$walk = $libraryDir;
foreach ($segments as $segment) {
    $walk .= DIRECTORY_SEPARATOR . $segment;
    // Never resolve through a symlink; a library symlink could otherwise
    // point outside the project tree.
    if (is_link($walk)) {
        json_response(['ok' => false, 'message' => 'Không thể xoá folder liên kết.'], 403);
    }
}

$target = $libraryDir . DIRECTORY_SEPARATOR . $folder;
$realTarget = realpath($target);
if (!is_string($realTarget) || $realTarget === '' || !is_dir($realTarget)) {
    json_response(['ok' => false, 'message' => 'Folder không tồn tại.'], 404);
}

if (!str_starts_with($realTarget, $libraryDir . DIRECTORY_SEPARATOR)) {
    json_response(['ok' => false, 'message' => 'Không được xoá ngoài Thư viện.'], 403);
}

try {
    $stats = medical_media_folder_delete_stats($realTarget);
} catch (RuntimeException $exception) {
    json_response(['ok' => false, 'message' => $exception->getMessage()], 409);
}

$hasContents = $stats['files'] > 0 || $stats['folders'] > 0;
if ($hasContents && !$recursive) {
    json_response([
        'ok' => false,
        'code' => 'folder_not_empty',
        'message' => 'Folder còn nội dung. Xác nhận thêm một lần để xoá toàn bộ.',
        'counts' => $stats,
    ], 409);
}

if ($hasContents && $deleteConfirmation !== 'DELETE_FOLDER_CONTENTS') {
    json_response([
        'ok' => false,
        'message' => 'Chưa có xác nhận xoá toàn bộ nội dung folder.',
        'counts' => $stats,
    ], 422);
}

try {
    if ($hasContents) {
        medical_media_folder_delete_recursive($realTarget);
    } elseif (!@rmdir($realTarget)) {
        throw new RuntimeException('Không thể xoá folder trống.');
    }
} catch (RuntimeException $exception) {
    $message = $exception->getMessage();
    if ($hasContents) {
        $message .= ' Một phần nội dung có thể đã bị xoá; hãy tải lại Thư viện để kiểm tra.';
    }
    json_response([
        'ok' => false,
        'message' => $message,
        'counts' => $stats,
    ], 500);
}

json_response([
    'ok' => true,
    'message' => $hasContents ? 'Đã xoá folder và toàn bộ nội dung.' : 'Đã xoá folder trống.',
    'folder' => $folder,
    'deleted' => $stats,
]);
