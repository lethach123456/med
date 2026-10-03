<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_directory.php';

/** Additive upgrades to legacy content fields. Only explicit maintenance may call DDL. */
function medreview_migrate_content_columns(PDO $pdo): void
{
    if (!medreview_schema_migration_allowed()) return;
    foreach (['categories', 'posts', 'product_categories', 'products', 'project_categories', 'projects'] as $table) {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
        $stmt->execute([':table' => $table]);
        $columns = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if ($columns === []) throw new RuntimeException('Missing content table: ' . $table);
        $additions = [];
        if (!isset($columns['language'])) $additions[] = "ADD COLUMN language VARCHAR(5) NOT NULL DEFAULT 'vi'";
        if ($table === 'posts') {
            if (!isset($columns['featured_image_url'])) $additions[] = 'ADD COLUMN featured_image_url VARCHAR(255) NULL';
            if (!isset($columns['template'])) $additions[] = 'ADD COLUMN template TINYINT(1) NOT NULL DEFAULT 0';
        }
        if (in_array($table, ['categories', 'posts'], true) && strtolower((string) ($columns['slug'] ?? '')) !== 'varchar(191)') {
            $additions[] = 'MODIFY COLUMN slug VARCHAR(191) NOT NULL';
        }
        if ($additions !== []) $pdo->exec('ALTER TABLE ' . $table . ' ' . implode(', ', $additions));
    }
}

/** Baseline tables formerly created by the admin migration. Never seeds editorial data. */
function medreview_migrate_core_tables(PDO $pdo): void
{
    if (!medreview_schema_migration_allowed()) return;
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','staff') NOT NULL DEFAULT 'admin',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_users_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(64) NOT NULL,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS migration_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            run_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notes TEXT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS visits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            path VARCHAR(255) NOT NULL,
            referrer VARCHAR(255) NULL,
            ip VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_visits_path (path),
            KEY idx_visits_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS front_editor_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_key VARCHAR(32) NOT NULL,
            element_id VARCHAR(32) NOT NULL,
            admin_user_id INT UNSIGNED NULL,
            action VARCHAR(16) NOT NULL DEFAULT 'update',
            old_text TEXT NULL,
            new_text TEXT NULL,
            content_before MEDIUMTEXT NOT NULL,
            content_after MEDIUMTEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_feh_created (created_at),
            KEY idx_feh_page (page_key),
            KEY idx_feh_user (admin_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );


    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            language VARCHAR(5) NOT NULL DEFAULT 'vi',
            description TEXT NULL,
            seo_title VARCHAR(160) NULL,
            seo_description VARCHAR(300) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_categories_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS posts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id INT UNSIGNED NULL,
            title VARCHAR(160) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            language VARCHAR(5) NOT NULL DEFAULT 'vi',
            featured_image_url VARCHAR(255) NULL,
            template TINYINT(1) NOT NULL DEFAULT 0,
            excerpt TEXT NULL,
            content MEDIUMTEXT NULL,
            seo_title VARCHAR(160) NULL,
            seo_description VARCHAR(300) NULL,
            seo_keywords VARCHAR(255) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_posts_slug (slug),
            KEY idx_posts_category (category_id),
            CONSTRAINT fk_posts_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS product_categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            language VARCHAR(5) NOT NULL DEFAULT 'vi',
            description TEXT NULL,
            seo_title VARCHAR(160) NULL,
            seo_description VARCHAR(300) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_product_categories_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id INT UNSIGNED NULL,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            language VARCHAR(5) NOT NULL DEFAULT 'vi',
            featured_image_url VARCHAR(255) NULL,
            short_description TEXT NULL,
            content MEDIUMTEXT NULL,
            price_int INT NULL,
            price_text VARCHAR(60) NULL,
            gallery_json MEDIUMTEXT NULL,
            seo_title VARCHAR(160) NULL,
            seo_description VARCHAR(300) NULL,
            seo_keywords VARCHAR(255) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_products_slug (slug),
            KEY idx_products_category (category_id),
            CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS project_categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            language VARCHAR(5) NOT NULL DEFAULT 'vi',
            description TEXT NULL,
            seo_title VARCHAR(160) NULL,
            seo_description VARCHAR(300) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_project_categories_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS projects (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id INT UNSIGNED NULL,
            title VARCHAR(160) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            language VARCHAR(5) NOT NULL DEFAULT 'vi',
            featured_image_url VARCHAR(255) NULL,
            short_description TEXT NULL,
            content MEDIUMTEXT NULL,
            project_type VARCHAR(80) NULL,
            style VARCHAR(80) NULL,
            location VARCHAR(120) NULL,
            year VARCHAR(10) NULL,
            area VARCHAR(40) NULL,
            materials VARCHAR(255) NULL,
            gallery_json MEDIUMTEXT NULL,
            seo_title VARCHAR(160) NULL,
            seo_description VARCHAR(300) NULL,
            seo_keywords VARCHAR(255) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_projects_slug (slug),
            KEY idx_projects_category (category_id),
            CONSTRAINT fk_projects_category FOREIGN KEY (category_id) REFERENCES project_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );


    ensure_front_editor_page_profiles_table($pdo);
    front_editor_templates_ensure_table($pdo);
    medreview_migrate_content_columns($pdo);
}

/** Single explicit entry point shared by CLI deployment and the admin maintenance button. */
function medreview_run_all_schema_migrations(PDO $pdo): void
{
    medreview_with_schema_migration(static function () use ($pdo): void {
        medreview_migrate_core_tables($pdo);
        medical_directory_run_schema_migrations($pdo);
    });
}
