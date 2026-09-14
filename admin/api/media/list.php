<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../../medical_media_library.php';

admin_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

// The media library can grow to thousands of imported images.  Keep the
// legacy, unscoped response for existing media pickers, but let the Finder
// request just the folder tree first and fetch files only after a folder is
// opened.
$input = read_json_body();
$readInput = static function (string $key, mixed $default = null) use ($input): mixed {
    if (array_key_exists($key, $input)) return $input[$key];
    if (array_key_exists($key, $_POST)) return $_POST[$key];
    return $default;
};
$toBool = static function (mixed $value): bool {
    if (is_bool($value)) return $value;
    if (is_int($value) || is_float($value)) return (int) $value === 1;
    if (!is_string($value)) return false;
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
};

$scope = strtolower(trim((string) $readInput('scope', '')));
$foldersOnly = $scope === 'folders';
$rootFoldersOnly = $toBool($readInput('root_folders_only', false));
$hasFolderFilter = array_key_exists('folder', $input) || array_key_exists('folder', $_POST);
$requestedFolder = '';
if ($hasFolderFilter) {
    $requestedFolder = medical_media_normalize_folder((string) $readInput('folder', ''));
    if ($requestedFolder === null) {
        json_response(['ok' => false, 'message' => 'Folder không hợp lệ.'], 422);
    }
}

$baseDir = medical_media_project_root();
if (!is_string($baseDir) || $baseDir === '') {
    json_response(['ok' => false, 'message' => 'Không xác định được thư mục dự án.'], 500);
}

$libraryDir = medical_media_library_dir();
if (!is_dir($libraryDir)) {
    json_response([
        'ok' => true,
        'scope' => $foldersOnly ? 'folders' : ($hasFolderFilter ? 'folder' : 'all'),
        'folder' => $hasFolderFilter ? $requestedFolder : null,
        'files' => [],
        'folders' => [],
        'folder_file_counts' => [],
        'total_files' => 0,
        'folder_total_files' => 0,
        'returned_files' => 0,
    ]);
}

$allowedExt = [
    'jpg' => 'image',
    'jpeg' => 'image',
    'png' => 'image',
    'webp' => 'image',
    'gif' => 'image',
    'mp4' => 'video',
];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($libraryDir, FilesystemIterator::SKIP_DOTS));
$items = [];
$folders = [];
$folderFileCounts = [];
$totalFiles = 0;
$folderTotalFiles = 0;

foreach ($rii as $f) {
    if (!$f instanceof SplFileInfo || !$f->isFile()) {
        continue;
    }

    $ext = strtolower((string) $f->getExtension());
    if (!array_key_exists($ext, $allowedExt)) {
        continue;
    }

    $abs = (string) $f->getRealPath();
    if ($abs === '' || strncmp($abs, $libraryDir, strlen($libraryDir)) !== 0) {
        continue;
    }

    $relativeToLibrary = ltrim(substr($abs, strlen($libraryDir)), '/');
    $folder = dirname($relativeToLibrary);
    $folder = $folder === '.' ? '' : str_replace('\\', '/', $folder);
    if ($folder !== '') $folders[$folder] = true;

    $totalFiles++;
    $folderFileCounts[$folder] = (int) ($folderFileCounts[$folder] ?? 0) + 1;

    $isRequestedFolder = !$hasFolderFilter || $folder === $requestedFolder;
    if ($isRequestedFolder) $folderTotalFiles++;

    // In folder-only mode we intentionally avoid building metadata for every
    // image.  This is the important part of the lightweight initial load.
    if ($foldersOnly || !$isRequestedFolder) {
        continue;
    }

    $rel = ltrim(str_replace($baseDir, '', $abs), '/');
    $url = '/' . $rel;

    $items[] = [
        'name' => $f->getFilename(),
        'url' => $url,
        'folder' => $folder,
        'type' => $allowedExt[$ext],
        'ext' => $ext,
        'size' => (int) $f->getSize(),
        'mtime' => (int) $f->getMTime(),
    ];
}

$directoryIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($libraryDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($directoryIterator as $directory) {
    if (!$directory instanceof SplFileInfo || !$directory->isDir()) continue;
    $absolute = (string) $directory->getRealPath();
    if ($absolute === '' || $absolute === $libraryDir || !str_starts_with($absolute, $libraryDir . DIRECTORY_SEPARATOR)) continue;
    $folder = ltrim(substr($absolute, strlen($libraryDir)), '/');
    if ($folder !== '') $folders[str_replace('\\', '/', $folder)] = true;
}

foreach ($folders as $folder => $_) {
    if (!array_key_exists($folder, $folderFileCounts)) {
        $folderFileCounts[$folder] = 0;
    }
}

if ($rootFoldersOnly) {
    $rootFolders = [];
    $rootFolderFileCounts = [];
    foreach ($folders as $folder => $_) {
        // Numeric directory names such as "2026" become integer array keys
        // in PHP, but path helpers must always receive strings.
        $folder = (string) $folder;
        $root = explode('/', $folder, 2)[0] ?? '';
        if ($root === '') continue;
        $rootFolders[$root] = true;
        // For a root folder, the useful count is all media below it, not just
        // files directly at the root level.
        $rootFolderFileCounts[$root] = (int) ($rootFolderFileCounts[$root] ?? 0)
            + (int) ($folderFileCounts[$folder] ?? 0);
    }
    $folders = $rootFolders;
    $folderFileCounts = $rootFolderFileCounts;
}

usort($items, static function (array $a, array $b): int {
    return ($b['mtime'] <=> $a['mtime']);
});

ksort($folders, SORT_NATURAL | SORT_FLAG_CASE);
ksort($folderFileCounts, SORT_NATURAL | SORT_FLAG_CASE);

$returnedItems = array_slice($items, 0, 200);

json_response([
    'ok' => true,
    'scope' => $foldersOnly ? 'folders' : ($hasFolderFilter ? 'folder' : 'all'),
    'folder' => $hasFolderFilter ? $requestedFolder : null,
    'root_folders_only' => $rootFoldersOnly,
    'files' => $returnedItems,
    'total_files' => $totalFiles,
    'folder_total_files' => $hasFolderFilter ? $folderTotalFiles : $totalFiles,
    'returned_files' => count($returnedItems),
    'folders' => array_values(array_keys($folders)),
    'folder_file_counts' => $folderFileCounts,
]);
