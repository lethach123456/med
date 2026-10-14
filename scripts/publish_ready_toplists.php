<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_toplist_readiness.php';

$options = getopt('', ['apply', 'ids:', 'backup:', 'details']);
$apply = isset($options['apply']);
$ids = [];
if (isset($options['ids'])) {
    if (!is_string($options['ids']) || !preg_match('/^[1-9]\d*(?:,[1-9]\d*)*$/', $options['ids'])) {
        fwrite(STDERR, "--ids needs comma-separated positive IDs.\n"); exit(1);
    }
    $ids = array_values(array_unique(array_map('intval', explode(',', $options['ids']))));
}
if ($apply && ($ids === [] || !isset($options['backup']))) {
    fwrite(STDERR, "Run a dry check first. Apply requires --ids=... --backup=/absolute/new-file.json.\n"); exit(1);
}
$backup = $options['backup'] ?? '';
if ($apply && (!is_string($backup) || !str_starts_with($backup, '/') || is_file($backup) || !is_dir(dirname($backup)))) {
    fwrite(STDERR, "Backup must be a new file in an existing absolute directory.\n"); exit(1);
}
try {
    $stage = 'connect';
    $pdo = db();
    $stage = 'begin_transaction';
    if (!$apply) $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    // English copies can still contain Vietnamese text and incomplete translated links.
    // This one-time backfill intentionally handles original Vietnamese drafts only.
    $sql = "SELECT id,title,slug,entity_type,language_code,status,updated_at FROM medical_toplists
        WHERE status='draft' AND language_code='vi' AND translation_of_id IS NULL";
    if ($ids !== []) $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    $sql .= ' ORDER BY id' . ($apply ? ' FOR UPDATE' : '');
    $stage = 'select_toplists';
    $stmt = $pdo->prepare($sql); $stmt->execute($ids);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $grouped = [];
    $articleIds = array_column($articles, 'id');
    if ($articleIds !== []) {
        $marks = implode(',', array_fill(0, count($articleIds), '?'));
        foreach (['facility' => ['medical_toplist_facilities', 'medical_facilities', 'facility_id'],
            'doctor' => ['medical_toplist_doctors', 'medical_doctors', 'doctor_id']] as $type => [$links, $table, $key]) {
            // Lock the raw links and profiles while applying so writers cannot change readiness mid-update.
            $stage = 'select_' . $type . '_members';
            $members = $pdo->prepare("SELECT r.toplist_id,r.{$key} AS member_id,m.id,m.status,m.language_code,m.content
                FROM {$links} r LEFT JOIN {$table} m ON m.id=r.{$key} WHERE r.toplist_id IN ({$marks})"
                . ($apply ? ' LOCK IN SHARE MODE' : ''));
            $members->execute($articleIds);
            while ($member = $members->fetch(PDO::FETCH_ASSOC)) {
                $grouped[(int) $member['toplist_id']][] = ['member_type' => $type] + $member;
            }
        }
    }
    $eligible = []; $rejected = [];
    foreach ($articles as $article) {
        $result = medical_toplist_readiness($article, $grouped[(int) $article['id']] ?? []);
        if ($result['ready']) $eligible[] = $article;
        else $rejected[] = ['id' => (int) $article['id']] + $result;
    }
    $readyIds = array_map('intval', array_column($eligible, 'id'));
    $skipCounts = [];
    foreach ($rejected as $rejection) {
        $why = $rejection['reasons'];
        foreach ($rejection['blocked'] as $member) $why = array_merge($why, $member['reasons']);
        foreach (array_unique($why) as $reason) $skipCounts[$reason] = ($skipCounts[$reason] ?? 0) + 1;
    }
    $changes = [];
    if ($apply && $eligible !== []) {
        $stage = 'backup';
        // Write the complete pre-change status snapshot before the first UPDATE.
        $handle = fopen($backup, 'x');
        if (!$handle) throw new RuntimeException('Unable to create backup; no status changed.');
        try {
            if (!chmod($backup, 0600)) throw new RuntimeException('Unable to secure backup file.');
            $snapshot = json_encode(['created_at' => gmdate('c'), 'database_hash' => hash('sha256', (string) $pdo->query('SELECT DATABASE()')->fetchColumn()),
                'before' => $eligible], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (fwrite($handle, $snapshot) !== strlen($snapshot) || !fflush($handle)) throw new RuntimeException('Unable to save backup; no status changed.');
        } finally { fclose($handle); }
        $publish = $pdo->prepare("UPDATE medical_toplists SET status='published',updated_at=CURRENT_TIMESTAMP
            WHERE id=:id AND status='draft' AND language_code='vi' AND translation_of_id IS NULL");
        foreach ($readyIds as $id) {
            $stage = 'publish';
            $publish->execute([':id' => $id]);
            if ($publish->rowCount() !== 1) throw new RuntimeException('Concurrent change detected; entire batch rolled back.');
            $changes[] = $id;
        }
    }
    $stage = 'commit';
    $pdo->commit();
    $stage = 'cache';
    if ($changes !== []) medical_search_cache_invalidate();
    echo json_encode(['mode' => $apply ? 'apply' : 'dry-run', 'scope' => 'Vietnamese original draft Toplists',
        'inspected' => count($articles), 'eligible_count' => count($eligible), 'ready_ids' => $readyIds,
        'published_count' => count($changes), 'published_ids' => $changes,
        'skipped_count' => count($rejected), 'skipped_reasons' => $skipCounts,
        'skipped' => isset($options['details']) ? $rejected : array_slice($rejected, 0, 5),
        'backup' => $changes !== [] ? $backup : null], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    // Avoid displaying connection details or credentials from driver exceptions.
    $resource = '';
    if ($e instanceof PDOException && preg_match('/\b(max_user_connections|max_connections_per_hour|max_queries_per_hour|max_questions|max_updates)\b/', $e->getMessage(), $match)) {
        $resource = '; limit=' . $match[1];
        if (preg_match('/current value:\s*(\d+)/i', $e->getMessage(), $limit)) $resource .= ', value=' . $limit[1];
    }
    $message = $e instanceof PDOException
        ? 'Database operation failed at ' . ($stage ?? 'unknown') . ' (SQLSTATE ' . $e->getCode() . ', driver ' . ($e->errorInfo[1] ?? 'unknown') . $resource . '); uncommitted changes rolled back.'
        : $e->getMessage();
    fwrite(STDERR, $message . "\n");
    exit(1);
}
