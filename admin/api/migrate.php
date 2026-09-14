<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../medical_directory.php';

admin_require_login();

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
        "CREATE TABLE IF NOT EXISTS front_editor_templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            page_key VARCHAR(32) NULL,
            source_element_id VARCHAR(32) NULL,
            tag_name VARCHAR(24) NULL,
            preview_text VARCHAR(255) NULL,
            html_content MEDIUMTEXT NOT NULL,
            admin_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_fet_created (created_at),
            KEY idx_fet_page (page_key),
            KEY idx_fet_user (admin_user_id)
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

    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN template TINYINT(1) NOT NULL DEFAULT 0 AFTER featured_image_url");
    } catch (Throwable $e) {
    }

    foreach (['categories', 'posts', 'product_categories', 'products', 'project_categories', 'projects'] as $table) {
        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN language VARCHAR(5) NOT NULL DEFAULT 'vi'");
        } catch (Throwable $e) {
        }
    }

    medical_directory_ensure_tables($pdo);
    medical_directory_seed_defaults($pdo);

    $seedHome = 'skipped';
    try {
        $st = $pdo->prepare("SELECT id FROM posts WHERE slug = 'home' LIMIT 1");
        $st->execute();
        $homeId = (int) ($st->fetchColumn() ?: 0);
        if ($homeId <= 0) {
            $homeContent = '';
            $temPath = __DIR__ . '/../../Tem/tem.php';
            if (is_file($temPath)) {
                require_once $temPath;
                if (isset($Home) && is_string($Home)) {
                    $homeContent = $Home;
                }
            }

            $ins = $pdo->prepare(
                "INSERT INTO posts (category_id, title, slug, excerpt, content, status)
                 VALUES (NULL, :title, :slug, NULL, :content, 'published')"
            );
            $ins->execute([
                ':title' => 'Home',
                ':slug' => 'home',
                ':content' => $homeContent,
            ]);
            $seedHome = 'created';
        }
    } catch (Throwable $e) {
        $seedHome = 'failed';
    }

    $seedProduct = 'skipped';
    $seedProject = 'skipped';
    $seedBlog = 'skipped';
    try {
        $findIdBySlug = static function (PDO $pdo, string $table, string $slug): int {
            $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE slug = :s LIMIT 1");
            $stmt->execute([':s' => $slug]);
            return (int) ($stmt->fetchColumn() ?: 0);
        };

        $ensureCategory = static function (PDO $pdo, string $table, string $name, string $slug, ?string $description = null): int {
            $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE slug = :s LIMIT 1");
            $stmt->execute([':s' => $slug]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
            $ins = $pdo->prepare("INSERT INTO {$table} (name, slug, description) VALUES (:n, :s, :d)");
            $ins->execute([':n' => $name, ':s' => $slug, ':d' => $description]);
            return (int) $pdo->lastInsertId();
        };

        $productCatIds = [];
        $productCatSeeds = [
            ['name' => 'Bồn tắm', 'slug' => 'bon-tam', 'description' => 'Danh mục bồn tắm cao cấp.'],
            ['name' => 'Lavabo', 'slug' => 'lavabo', 'description' => 'Danh mục lavabo đa dạng.'],
            ['name' => 'Phụ kiện phòng tắm', 'slug' => 'phu-kien-phong-tam', 'description' => 'Phụ kiện đồng bộ cho phòng tắm.'],
            ['name' => 'Sen tắm', 'slug' => 'sen-tam', 'description' => 'Bộ sen tắm và phụ kiện.'],
            ['name' => 'Gương & tủ gương', 'slug' => 'guong-tu-guong', 'description' => 'Gương, tủ gương và giải pháp ánh sáng.'],
        ];
        foreach ($productCatSeeds as $c) {
            $productCatIds[$c['slug']] = $ensureCategory($pdo, 'product_categories', $c['name'], $c['slug'], $c['description']);
        }

        $productSeeds = [
            ['cid_slug' => 'bon-tam', 'name' => 'Bồn tắm đá tự nhiên HM-01', 'slug' => 'bon-tam-da-tu-nhien-hm-01', 'price_int' => 24500000],
            ['cid_slug' => 'bon-tam', 'name' => 'Bồn tắm terrazzo hiện đại', 'slug' => 'bon-tam-terrazzo-hien-dai', 'price_int' => 18900000],
            ['cid_slug' => 'lavabo', 'name' => 'Lavabo đặt bàn terrazzo', 'slug' => 'lavabo-dat-ban-terrazzo', 'price_int' => 5900000],
            ['cid_slug' => 'phu-kien-phong-tam', 'name' => 'Vòi lavabo cổ cao', 'slug' => 'voi-lavabo-co-cao', 'price_int' => 3200000],
            ['cid_slug' => 'sen-tam', 'name' => 'Bộ sen tắm âm tường', 'slug' => 'bo-sen-tam-am-tuong', 'price_int' => null],
        ];

        $pstmt = $pdo->prepare(
            "INSERT INTO products (category_id, name, slug, short_description, content, price_int, price_text, featured_image_url, status)
             VALUES (:cid, :name, :slug, :short, :content, :price_int, :price_text, :img, 'published')"
        );
        $createdCount = 0;
        foreach ($productSeeds as $p) {
            $exists = $findIdBySlug($pdo, 'products', $p['slug']);
            if ($exists > 0) continue;
            $cid = (int) ($productCatIds[$p['cid_slug']] ?? 0);
            if ($cid <= 0) continue;
            $pstmt->execute([
                ':cid' => $cid,
                ':name' => $p['name'],
                ':slug' => $p['slug'],
                ':short' => 'Sản phẩm cao cấp, thiết kế tinh gọn và bền vững.',
                ':content' => '<p>Thông tin sản phẩm mẫu. Có thể chỉnh sửa nội dung, hình ảnh và SEO trong admin.</p>',
                ':price_int' => $p['price_int'],
                ':price_text' => $p['price_int'] === null ? 'Liên hệ báo giá' : null,
                ':img' => null,
            ]);
            $createdCount++;
        }

        $catRows = $pdo->query("SELECT id, name, slug FROM product_categories ORDER BY id ASC")->fetchAll();
        foreach ($catRows as $c) {
            $catId = (int) ($c['id'] ?? 0);
            $catName = (string) ($c['name'] ?? '');
            $catSlug = (string) ($c['slug'] ?? '');
            if ($catId <= 0 || $catSlug === '') continue;

            $st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
            $st->execute([':id' => $catId]);
            $count = (int) ($st->fetchColumn() ?: 0);

            $i = 1;
            while ($count < 5 && $i <= 30) {
                $slug = $catSlug . '-mau-' . $i;
                $exists = $findIdBySlug($pdo, 'products', $slug);
                if ($exists > 0) {
                    $i++;
                    continue;
                }

                $priceInt = null;
                if ($i % 2 === 0) {
                    $priceInt = 1500000 + ($i * 350000);
                }
                $priceText = $priceInt === null ? 'Liên hệ báo giá' : null;

                $pstmt->execute([
                    ':cid' => $catId,
                    ':name' => ($catName !== '' ? $catName : 'Sản phẩm') . ' mẫu ' . $i,
                    ':slug' => $slug,
                    ':short' => 'Mẫu sản phẩm trong chuyên mục ' . ($catName !== '' ? $catName : $catSlug) . '.',
                    ':content' => '<p>Nội dung sản phẩm mẫu. Bạn có thể cập nhật mô tả, gallery và SEO.</p>',
                    ':price_int' => $priceInt,
                    ':price_text' => $priceText,
                    ':img' => null,
                ]);
                $createdCount++;
                $count++;
                $i++;
            }
        }

        $seedProduct = $createdCount > 0 ? ('created +' . $createdCount) : 'ok';
    } catch (Throwable $e) {
        $seedProduct = 'failed';
    }

    try {
        $findIdBySlug = static function (PDO $pdo, string $table, string $slug): int {
            $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE slug = :s LIMIT 1");
            $stmt->execute([':s' => $slug]);
            return (int) ($stmt->fetchColumn() ?: 0);
        };

        $ensureCategory = static function (PDO $pdo, string $table, string $name, string $slug, ?string $description = null): int {
            $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE slug = :s LIMIT 1");
            $stmt->execute([':s' => $slug]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
            $ins = $pdo->prepare("INSERT INTO {$table} (name, slug, description) VALUES (:n, :s, :d)");
            $ins->execute([':n' => $name, ':s' => $slug, ':d' => $description]);
            return (int) $pdo->lastInsertId();
        };

        $projectCatIds = [];
        $projectCatSeeds = [
            ['name' => 'Villa', 'slug' => 'villa', 'description' => 'Dự án villa.'],
            ['name' => 'Resort', 'slug' => 'resort', 'description' => 'Dự án resort.'],
            ['name' => 'Khách sạn', 'slug' => 'khach-san', 'description' => 'Dự án khách sạn.'],
            ['name' => 'Showroom', 'slug' => 'showroom', 'description' => 'Dự án showroom.'],
            ['name' => 'Căn hộ', 'slug' => 'can-ho', 'description' => 'Dự án căn hộ.'],
        ];
        foreach ($projectCatSeeds as $c) {
            $projectCatIds[$c['slug']] = $ensureCategory($pdo, 'project_categories', $c['name'], $c['slug'], $c['description']);
        }

        $projectSeeds = [
            [
                'cid_slug' => 'villa',
                'title' => 'Villa Bathroom • Concept A',
                'slug' => 'villa-bathroom-concept-a',
                'project_type' => 'Villa',
                'style' => 'Modern',
                'location' => 'TP.HCM',
                'year' => '2026',
                'area' => '18–24m²',
                'materials' => 'Đá tự nhiên • Terrazzo • Kim loại mờ',
            ],
            [
                'cid_slug' => 'resort',
                'title' => 'Resort Bathroom • Concept B',
                'slug' => 'resort-bathroom-concept-b',
                'project_type' => 'Resort',
                'style' => 'Natural',
                'location' => 'Phú Quốc',
                'year' => '2026',
                'area' => '16–20m²',
                'materials' => 'Đá sáng • Gỗ chống ẩm • Kính',
            ],
            [
                'cid_slug' => 'khach-san',
                'title' => 'Hotel Bathroom • Concept C',
                'slug' => 'hotel-bathroom-concept-c',
                'project_type' => 'Khách sạn',
                'style' => 'Standard',
                'location' => 'Đà Nẵng',
                'year' => '2026',
                'area' => '10–14m²',
                'materials' => 'Gốm cao cấp • Kính • Inox',
            ],
            [
                'cid_slug' => 'can-ho',
                'title' => 'Apartment Bathroom • Concept D',
                'slug' => 'apartment-bathroom-concept-d',
                'project_type' => 'Căn hộ',
                'style' => 'Compact',
                'location' => 'Hà Nội',
                'year' => '2026',
                'area' => '8–12m²',
                'materials' => 'Đá vân mịn • Kính • Phụ kiện đồng bộ',
            ],
            [
                'cid_slug' => 'showroom',
                'title' => 'Showroom • Concept E',
                'slug' => 'showroom-concept-e',
                'project_type' => 'Showroom',
                'style' => 'Premium',
                'location' => 'TP.HCM',
                'year' => '2026',
                'area' => '60–90m²',
                'materials' => 'Đá • Gỗ chống ẩm • Kim loại mờ',
            ],
        ];

        $pr = $pdo->prepare(
            "INSERT INTO projects (category_id, title, slug, short_description, content, project_type, style, location, year, area, materials, featured_image_url, status)
             VALUES (:cid, :title, :slug, :short, :content, :ptype, :style, :loc, :year, :area, :mat, :img, 'published')"
        );
        $createdCount = 0;
        foreach ($projectSeeds as $p) {
            $exists = $findIdBySlug($pdo, 'projects', $p['slug']);
            if ($exists > 0) continue;
            $cid = (int) ($projectCatIds[$p['cid_slug']] ?? 0);
            if ($cid <= 0) continue;
            $pr->execute([
                ':cid' => $cid,
                ':title' => $p['title'],
                ':slug' => $p['slug'],
                ':short' => 'Dự án mẫu, có thể chỉnh sửa thông tin chi tiết theo thực tế.',
                ':content' => '<p>Nội dung dự án mẫu. Có thể bổ sung mô tả, hình ảnh gallery, SEO theo trang chi tiết.</p>',
                ':ptype' => $p['project_type'],
                ':style' => $p['style'],
                ':loc' => $p['location'],
                ':year' => $p['year'],
                ':area' => $p['area'],
                ':mat' => $p['materials'],
                ':img' => null,
            ]);
            $createdCount++;
        }

        $stylePool = ['Modern', 'Natural', 'Spa', 'Compact', 'Premium'];
        $locPool = ['TP.HCM', 'Hà Nội', 'Đà Nẵng', 'Phú Quốc', 'Nha Trang'];
        $areaPool = ['8–12m²', '10–14m²', '16–20m²', '18–24m²', '60–90m²'];
        $matPool = [
            'Đá tự nhiên • Terrazzo • Kim loại mờ',
            'Đá sáng • Gỗ chống ẩm • Kính',
            'Gốm cao cấp • Kính • Inox',
            'Đá vân mịn • Kính • Phụ kiện đồng bộ',
            'Đá • Gỗ chống ẩm • Kim loại mờ',
        ];

        $catRows = $pdo->query("SELECT id, name, slug FROM project_categories ORDER BY id ASC")->fetchAll();
        foreach ($catRows as $c) {
            $catId = (int) ($c['id'] ?? 0);
            $catName = (string) ($c['name'] ?? '');
            $catSlug = (string) ($c['slug'] ?? '');
            if ($catId <= 0 || $catSlug === '') continue;

            $st = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE category_id = :id");
            $st->execute([':id' => $catId]);
            $count = (int) ($st->fetchColumn() ?: 0);

            $i = 1;
            while ($count < 5 && $i <= 30) {
                $slug = 'du-an-' . $catSlug . '-mau-' . $i;
                $exists = $findIdBySlug($pdo, 'projects', $slug);
                if ($exists > 0) {
                    $i++;
                    continue;
                }

                $style = $stylePool[($i - 1) % count($stylePool)];
                $loc = $locPool[($i - 1) % count($locPool)];
                $area = $areaPool[($i - 1) % count($areaPool)];
                $mat = $matPool[($i - 1) % count($matPool)];

                $pr->execute([
                    ':cid' => $catId,
                    ':title' => ($catName !== '' ? $catName : 'Dự án') . ' • Dự án mẫu ' . $i,
                    ':slug' => $slug,
                    ':short' => 'Dự án mẫu trong chuyên mục ' . ($catName !== '' ? $catName : $catSlug) . '.',
                    ':content' => '<p>Nội dung dự án mẫu. Bạn có thể cập nhật mô tả, thông tin (loại/phong cách/vị trí/năm/diện tích/vật liệu) và gallery.</p>',
                    ':ptype' => ($catName !== '' ? $catName : null),
                    ':style' => $style,
                    ':loc' => $loc,
                    ':year' => '2026',
                    ':area' => $area,
                    ':mat' => $mat,
                    ':img' => null,
                ]);
                $createdCount++;
                $count++;
                $i++;
            }
        }

        $seedProject = $createdCount > 0 ? ('created +' . $createdCount) : 'ok';
    } catch (Throwable $e) {
        $seedProject = 'failed';
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = :s LIMIT 1");
        $stmt->execute([':s' => 'blog']);
        $blogCatId = (int) ($stmt->fetchColumn() ?: 0);
        if ($blogCatId <= 0) {
            $ins = $pdo->prepare("INSERT INTO categories (name, slug, description) VALUES (:n, :s, :d)");
            $ins->execute([':n' => 'Blog', ':s' => 'blog', ':d' => 'Chuyên mục blog.']);
            $blogCatId = (int) $pdo->lastInsertId();
        }

        $blogSeeds = [
            ['title' => 'Bọc răng sứ Đà Nẵng giá bao nhiêu? Bảng giá, yếu tố ảnh hưởng và cách chọn nha khoa', 'slug' => 'boc-rang-su-da-nang-gia-bao-nhieu'],
            ['title' => 'Răng sứ dáng hot girl Đà Nẵng là gì? Ai phù hợp và cách chọn form đẹp tự nhiên', 'slug' => 'rang-su-dang-hot-girl-da-nang-la-gi'],
            ['title' => 'Làm răng sứ Đà Nẵng ở đâu đẹp? 7 tiêu chí chọn nha khoa uy tín trước khi quyết định', 'slug' => 'lam-rang-su-da-nang-o-dau-dep'],
            ['title' => 'Các dáng răng sứ đẹp được hỏi nhiều tại Đà Nẵng: tự nhiên, sang và hot girl', 'slug' => 'cac-dang-rang-su-dep-duoc-hoi-nhieu-tai-da-nang'],
            ['title' => 'Kinh nghiệm làm răng sứ Đà Nẵng: 6 câu hỏi nên hỏi trước khi quyết định', 'slug' => 'kinh-nghiem-lam-rang-su-da-nang-6-cau-hoi'],
        ];

        $pstmt = $pdo->prepare(
            "INSERT INTO posts (category_id, title, slug, excerpt, content, status)
             VALUES (:cid, :title, :slug, :excerpt, :content, 'published')"
        );
        $createdAny = false;
        foreach ($blogSeeds as $b) {
            $stmt = $pdo->prepare("SELECT id FROM posts WHERE slug = :s LIMIT 1");
            $stmt->execute([':s' => $b['slug']]);
            $exists = (int) ($stmt->fetchColumn() ?: 0);
            if ($exists > 0) continue;
            $pstmt->execute([
                ':cid' => $blogCatId,
                ':title' => $b['title'],
                ':slug' => $b['slug'],
                ':excerpt' => 'Bài viết blog về răng sứ Đà Nẵng, tập trung các chủ đề đang được tìm kiếm như chi phí, dáng răng hot girl và kinh nghiệm chọn nha khoa.',
                ':content' => '<p>Nội dung mẫu cho blog răng sứ. Bạn có thể dùng CKEditor để soạn thảo bài viết và tối ưu SEO theo các từ khóa mục tiêu.</p>',
            ]);
            $createdAny = true;
        }
        $seedBlog = $createdAny ? 'created' : 'ok';
    } catch (Throwable $e) {
        $seedBlog = 'failed';
    }

    try {
        $up = $pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = setting_value"
        );
        $defaults = [
            'site_title' => 'RB Concept • Thiết kế phòng tắm',
            'site_description' => 'Giải pháp phòng tắm cao cấp',
            'site_hotline' => '0988 123 456',
            'site_email' => 'info@rbconcept.vn',
            'site_address' => '123 Đường ABC, Quận 1, TP. Hồ Chí Minh',
            'site_facebook' => 'https://facebook.com/',
            'site_zalo' => 'https://zalo.me/',
            'site_instagram' => 'https://instagram.com/',
            'site_home_gallery_lines' => '',
        ];
        foreach ($defaults as $k => $v) {
            $up->execute([':k' => $k, ':v' => $v]);
        }
        $seedSettings = 'ok';
    } catch (Throwable $e) {
        $seedSettings = 'failed';
    }

    try {
        $notes = sprintf(
            "users/site_settings/categories/posts/product_categories/products/project_categories/projects; seed_home=%s; seed_product=%s; seed_project=%s; seed_blog=%s; seed_settings=%s",
            $seedHome ?? 'skipped',
            $seedProduct ?? 'skipped',
            $seedProject ?? 'skipped',
            $seedBlog ?? 'skipped',
            $seedSettings ?? 'skipped'
        );
        $insLog = $pdo->prepare("INSERT INTO migration_log (notes) VALUES (:n)");
        $insLog->execute([':n' => $notes]);
    } catch (Throwable $e) {
        // ignore logging error
    }

    json_response([
        'ok' => true,
        'message' => 'Đã tạo/cập nhật bảng users, site_settings, categories, posts, product_categories, products, project_categories, projects. Seed home: ' . $seedHome . ' • Seed product: ' . $seedProduct . ' • Seed project: ' . $seedProject . ' • Seed blog: ' . $seedBlog . ' • Seed settings: ' . ($seedSettings ?? 'skipped'),
        'db_name' => getenv('DB_NAME') ?: 'at',
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'message' => 'Migrate thất bại: ' . $e->getMessage(),
    ], 500);
}
