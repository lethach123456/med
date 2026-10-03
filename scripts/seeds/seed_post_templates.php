<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--initialize-empty', $argv, true)) {
    fwrite(STDERR, "Template initialization is opt-in: php scripts/seeds/seed_post_templates.php --initialize-empty\nOnly empty templates are initialized; existing content is never replaced.\n");
    exit(1);
}
require_once dirname(__DIR__, 2) . '/db.php';
try {
    $pdo = db();
    $rows = $pdo->query("SELECT id, slug, template, content FROM posts WHERE template=1 AND (content IS NULL OR TRIM(content)='') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $update = $pdo->prepare("UPDATE posts SET content=:content WHERE id=:id AND template=1 AND (content IS NULL OR TRIM(content)='')");
    $initialized = 0;
    foreach ($rows as $row) {
        $resolved = post_template_resolve_content($row, $pdo);
        if (trim((string) ($resolved['content'] ?? '')) === '') continue;
        // Another editor may have filled the row since the SELECT: don't overwrite it.
        $update->execute([':id' => (int) $row['id'], ':content' => $resolved['content']]);
        $initialized += $update->rowCount();
    }
    echo "Initialized {$initialized} empty templates. Existing content preserved.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Template initialization failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
