<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/toplist_directory.php';
try {
    $pdo = db();
    $before = [];
    foreach (['medical_toplists', 'medical_toplist_facilities', 'medical_doctors'] as $table) {
        if (medical_directory_table_exists($pdo, $table)) $before[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
    if (!medical_directory_table_exists($pdo, 'medical_doctors') || !medical_directory_table_exists($pdo, 'medical_facilities')) medical_directory_ensure_tables($pdo);
    toplist_directory_ensure_tables($pdo);
    foreach ($before as $table => $count) {
        $after = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        echo "{$table}: {$count} before, {$after} after.\n";
    }
    medical_search_cache_invalidate();
    echo "Toplist entity_type and medical_toplist_doctors ready. No editorial content or existing links rewritten.\n";
} catch (Throwable $e) { fwrite(STDERR, 'Toplist migration failed: ' . $e->getMessage() . "\n"); exit(1); }
