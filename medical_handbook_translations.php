<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_handbook.php';

/** Explicit pairing, never inferred from a similar title or a matching slug. */
function handbook_translations_ready(PDO $pdo): bool
{
    try { $pdo->query('SELECT vi_post_id,en_post_id FROM handbook_translations LIMIT 0'); return true; }
    catch (PDOException $error) {
        if ($error->getCode() === '42S02' || ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' && str_contains($error->getMessage(), 'no such table'))) return false;
        throw $error;
    }
}

function handbook_translation_scoped(array $row): bool
{
    return in_array($row['category_slug'] ?? '', ['blog','blog-en'], true)
        && in_array(handbook_content_locale($row['language'] ?? null), ['vi','en'], true);
}

function handbook_translation_rows(PDO $pdo, array $ids, bool $lock = false): array
{
    $ids = array_values(array_unique(array_map('intval',$ids))); sort($ids);
    if ($ids === []) return [];
    $sql = 'SELECT p.*,c.slug AS category_slug FROM posts p INNER JOIN categories c ON c.id=p.category_id WHERE p.id IN ('
        . implode(',', array_fill(0,count($ids),'?')) . ') ORDER BY p.id';
    if ($lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') $sql .= ' FOR UPDATE';
    $stmt=$pdo->prepare($sql); $stmt->execute($ids); $rows=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!handbook_translation_scoped($row)) continue;
        $row['language']=handbook_content_locale($row['language'] ?? null); $rows[(int)$row['id']]=$row;
    }
    return $rows;
}

function handbook_translation_current(PDO $pdo, int $postId, bool $publicOnly = false): ?array
{
    if ($postId < 1 || !handbook_translations_ready($pdo)) return null;
    $stmt=$pdo->prepare('SELECT vi_post_id,en_post_id FROM handbook_translations WHERE vi_post_id=:vi OR en_post_id=:en LIMIT 1');
    $stmt->execute([':vi'=>$postId,':en'=>$postId]); $pair=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pair) return null;
    $vi=(int)$pair['vi_post_id']; $en=(int)$pair['en_post_id']; $rows=handbook_translation_rows($pdo,[$vi,$en]);
    if (!isset($rows[$vi],$rows[$en]) || $rows[$vi]['language']!=='vi' || $rows[$en]['language']!=='en') return null;
    if ($publicOnly && (!handbook_row_is_article($rows[$vi],'vi') || !handbook_row_is_article($rows[$en],'en'))) return null;
    return $rows[$postId===$vi?$en:$vi] ?? null;
}

function handbook_translation_candidates(PDO $pdo, string $targetLanguage, int $currentId = 0): array
{
    if (!in_array($targetLanguage,['vi','en'],true) || !handbook_translations_ready($pdo)) return [];
    $column=$targetLanguage==='vi'?'vi_post_id':'en_post_id'; $other=$targetLanguage==='vi'?'en_post_id':'vi_post_id';
    $stmt=$pdo->prepare("SELECT p.id,p.title,p.slug,p.status,p.updated_at,l.{$other} AS linked_to_id,
        CASE WHEN l.{$other} IS NULL OR l.{$other}=:current THEN 1 ELSE 0 END AS is_available
        FROM posts p INNER JOIN categories c ON c.id=p.category_id LEFT JOIN handbook_translations l ON l.{$column}=p.id
        WHERE c.slug IN ('blog','blog-en') AND COALESCE(NULLIF(LOWER(TRIM(p.language)),''),'vi')=:language
        ORDER BY p.updated_at DESC,p.id DESC LIMIT 250");
    $stmt->execute([':current'=>$currentId,':language'=>$targetLanguage]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $current=$currentId>0?handbook_translation_current($pdo,$currentId):null;
    if ($current && $current['language']===$targetLanguage && !in_array((int)$current['id'],array_map('intval',array_column($rows,'id')),true)) {
        array_unshift($rows,$current+['linked_to_id'=>$currentId,'is_available'=>1]); $rows=array_slice($rows,0,250);
    }
    return $rows;
}

/** Participates in the caller's article-save transaction without committing it. */
function handbook_translation_set(PDO $pdo, int $sourceId, ?int $counterpartId): void
{
    if (!handbook_translations_ready($pdo)) throw new InvalidArgumentException('Chưa có liên kết ngôn ngữ. Chạy php scripts/migrate_handbook_translations.php trước.');
    if ($sourceId<1 || ($counterpartId!==null && ($counterpartId<1 || $counterpartId===$sourceId))) throw new InvalidArgumentException('Bài liên kết không hợp lệ.');
    $own=!$pdo->inTransaction();
    if ($own) $pdo->beginTransaction(); else $pdo->exec('SAVEPOINT handbook_pair_save');
    try {
        $rows=handbook_translation_rows($pdo,$counterpartId===null?[$sourceId]:[$sourceId,$counterpartId],true);
        if (!isset($rows[$sourceId])) throw new InvalidArgumentException('Bài nguồn không thuộc Cẩm nang.');
        if ($counterpartId!==null && (!isset($rows[$counterpartId]) || $rows[$sourceId]['language']===$rows[$counterpartId]['language'])) {
            throw new InvalidArgumentException('Chỉ liên kết hai bài Cẩm nang khác ngôn ngữ Việt và Anh.');
        }
        if ($counterpartId!==null) {
            $stmt=$pdo->prepare('SELECT vi_post_id,en_post_id FROM handbook_translations WHERE vi_post_id=:vi OR en_post_id=:en'
                . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE'));
            $stmt->execute([':vi'=>$counterpartId,':en'=>$counterpartId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pair) {
                if ((int)$pair['vi_post_id']!==$sourceId && (int)$pair['en_post_id']!==$sourceId) {
                    throw new InvalidArgumentException('Bài được chọn đã liên kết với bài khác. Hãy gỡ liên kết cũ trước.');
                }
            }
        }
        $pdo->prepare('DELETE FROM handbook_translations WHERE vi_post_id=:vi OR en_post_id=:en')->execute([':vi'=>$sourceId,':en'=>$sourceId]);
        if ($counterpartId!==null) {
            $vi=$rows[$sourceId]['language']==='vi'?$sourceId:$counterpartId;
            $en=$rows[$sourceId]['language']==='en'?$sourceId:$counterpartId;
            $pdo->prepare('INSERT INTO handbook_translations(vi_post_id,en_post_id) VALUES(:vi,:en)')->execute([':vi'=>$vi,':en'=>$en]);
        }
        if ($own) $pdo->commit(); else $pdo->exec('RELEASE SAVEPOINT handbook_pair_save');
    } catch (Throwable $error) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        elseif (!$own && $pdo->inTransaction()) { $pdo->exec('ROLLBACK TO SAVEPOINT handbook_pair_save'); $pdo->exec('RELEASE SAVEPOINT handbook_pair_save'); }
        throw $error;
    }
}

function handbook_translations_migrate(PDO $pdo): void
{
    if (!function_exists('medreview_schema_migration_allowed') || !medreview_schema_migration_allowed()) throw new LogicException('Chỉ migrate liên kết Cẩm nang trong maintenance scope.');
    $pdo->exec('CREATE TABLE IF NOT EXISTS handbook_translations (
        vi_post_id INT UNSIGNED NOT NULL, en_post_id INT UNSIGNED NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(vi_post_id), UNIQUE KEY uniq_handbook_en_post(en_post_id),
        CONSTRAINT fk_handbook_vi FOREIGN KEY(vi_post_id) REFERENCES posts(id) ON DELETE CASCADE,
        CONSTRAINT fk_handbook_en FOREIGN KEY(en_post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}
