<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../medical_directory.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = db();

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
        "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(191) NOT NULL,
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
            featured_image_url VARCHAR(255) NULL,
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

    medical_directory_ensure_tables($pdo);
    medical_directory_seed_defaults($pdo);

    try {
        $pdo->exec("ALTER TABLE categories MODIFY slug VARCHAR(191) NOT NULL");
    } catch (Throwable $e) {
    }

    try {
        $pdo->exec("ALTER TABLE posts MODIFY slug VARCHAR(191) NOT NULL");
    } catch (Throwable $e) {
    }

    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN featured_image_url VARCHAR(255) NULL");
    } catch (Throwable $e) {
    }

    $defaults = [
        'site_title' => 'Nha Khoa An Tâm',
        'site_description' => 'Nha khoa An Tâm – khám, tư vấn và điều trị răng miệng theo tiêu chuẩn hiện đại.',
        'site_icon_href' => "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cpath fill='%230d6efd' d='M32 6c-10.4 0-18 8-18 18 0 7.4 4 14.2 5.2 23.1.5 3.8 3.6 6.9 7.4 6.9 1.7 0 3.2-1 3.8-2.5l1.6-4.2 1.6 4.2c.6 1.5 2.1 2.5 3.8 2.5 3.8 0 6.9-3.1 7.4-6.9C46 38.2 50 31.4 50 24 50 14 42.4 6 32 6z'/%3E%3C/svg%3E",
    ];
    $stmtSettings = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = setting_value'
    );
    foreach ($defaults as $k => $v) {
        $stmtSettings->execute([':k' => $k, ':v' => $v]);
    }

    $count = (int) $pdo->query("SELECT COUNT(*) AS c FROM users")->fetch()['c'];

    $createdDefault = false;
    $defaultUsername = null;
    $defaultPassword = null;

    if ($count === 0) {
        $defaultUsername = getenv('ADMIN_DEFAULT_USERNAME') ?: 'admin';
        $defaultPassword = getenv('ADMIN_DEFAULT_PASSWORD') ?: 'admin123';

        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (:u, :p, :r)');
        $stmt->execute([
            ':u' => $defaultUsername,
            ':p' => password_hash($defaultPassword, PASSWORD_DEFAULT),
            ':r' => 'admin',
        ]);

        $createdDefault = true;
    }

    json_response([
        'ok' => true,
        'message' => $createdDefault
            ? 'Đã tạo bảng hệ thống, bảng quản lý cơ sở y tế/review và tạo user admin mặc định.'
            : 'Đã tạo bảng hệ thống và bảng quản lý cơ sở y tế/review (nếu chưa có).',
        'default_user_created' => $createdDefault,
        'default_username' => $defaultUsername,
        'default_password' => $defaultPassword,
        'db_name' => getenv('DB_NAME') ?: 'at',
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'message' => 'Setup thất bại: ' . $e->getMessage(),
    ], 500);
}
