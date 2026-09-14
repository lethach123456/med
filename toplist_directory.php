<?php
declare(strict_types=1);

function toplist_directory_ensure_tables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS medical_toplists (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(220) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            excerpt TEXT NULL,
            content LONGTEXT NULL,
            featured_image_url VARCHAR(500) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_medical_toplists_slug (slug),
            KEY idx_medical_toplists_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS medical_toplist_facilities (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            toplist_id INT UNSIGNED NOT NULL,
            facility_id INT UNSIGNED NOT NULL,
            rank_order INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_toplist_facility (toplist_id, facility_id),
            KEY idx_toplist_facilities_order (toplist_id, rank_order),
            CONSTRAINT fk_toplist_facilities_toplist FOREIGN KEY (toplist_id) REFERENCES medical_toplists(id) ON DELETE CASCADE,
            CONSTRAINT fk_toplist_facilities_facility FOREIGN KEY (facility_id) REFERENCES medical_facilities(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function toplist_directory_sync_facilities(PDO $pdo, int $toplistId, array $facilityIds): void
{
    $ids = [];
    foreach ($facilityIds as $facilityId) {
        $facilityId = (int) $facilityId;
        if ($facilityId > 0 && !in_array($facilityId, $ids, true)) {
            $ids[] = $facilityId;
        }
    }

    $pdo->prepare('DELETE FROM medical_toplist_facilities WHERE toplist_id = :toplist_id')->execute([':toplist_id' => $toplistId]);
    if ($ids === []) {
        return;
    }

    $validStmt = $pdo->prepare('SELECT id FROM medical_facilities WHERE id = :id LIMIT 1');
    $insertStmt = $pdo->prepare('INSERT INTO medical_toplist_facilities (toplist_id, facility_id, rank_order) VALUES (:toplist_id, :facility_id, :rank_order)');
    foreach ($ids as $rank => $facilityId) {
        $validStmt->execute([':id' => $facilityId]);
        if (!$validStmt->fetchColumn()) {
            continue;
        }
        $insertStmt->execute([':toplist_id' => $toplistId, ':facility_id' => $facilityId, ':rank_order' => $rank + 1]);
    }
}
