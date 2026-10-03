<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--demo', $argv, true)) {
    fwrite(STDERR, "Legacy demo seed is opt-in. For test installations only, after php scripts/migrate_all.php: php scripts/seeds/seed_legacy_content.php --demo\nExisting records will not be reset.\n");
    exit(1);
}
require_once dirname(__DIR__, 2) . '/db.php';

// Legacy home/blog/products/projects/settings demos. Never invoked by migrations.
try {
    $pdo = db();
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


    $summary = ['home' => $seedHome, 'products' => $seedProduct, 'projects' => $seedProject,
        'blog' => $seedBlog, 'settings' => $seedSettings ?? 'failed'];
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(in_array('failed', $summary, true) ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Legacy demo seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
