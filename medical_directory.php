<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_search_cache.php';

function medical_directory_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
             LIMIT 1"
        );
        $stmt->execute([':table' => $table]);
        $cache[$table] = (bool) $stmt->fetchColumn();
        return $cache[$table];
    } catch (Throwable $e) {
        $cache[$table] = false;
        return false;
    }
}

function medical_directory_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column
             LIMIT 1"
        );
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function medical_directory_format_int(int $value): string
{
    return number_format($value, 0, ',', '.');
}

function medical_directory_json_encode($value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '[]';
}

function medical_directory_json_decode(?string $json, array $fallback = []): array
{
    $json = trim((string) ($json ?? ''));
    if ($json === '') {
        return $fallback;
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : $fallback;
}

/** Decode a JSON field that may intentionally be null, a scalar or an array. */
function medical_directory_json_value_decode(?string $json, mixed $fallback = null): mixed
{
    $json = trim((string) ($json ?? ''));
    if ($json === '') {
        return $fallback;
    }
    $decoded = json_decode($json, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
}

/**
 * Return the usable image URLs from a facility gallery.  The database stores
 * JSON as MEDIUMTEXT for backward compatibility, so callers must not assume
 * every saved value is a valid array of strings.
 *
 * @param string|array<mixed>|null $gallery
 * @return array<int,string>
 */
function medical_directory_gallery_urls($gallery, int $limit = 0): array
{
    $items = is_array($gallery) ? $gallery : medical_directory_json_decode(is_string($gallery) ? $gallery : null);
    $urls = [];
    foreach ($items as $item) {
        if (is_array($item)) {
            $item = $item['url'] ?? $item['src'] ?? '';
        }
        if (!is_string($item)) {
            continue;
        }
        $url = trim($item);
        if ($url === '' || isset($urls[$url])) {
            continue;
        }
        $urls[$url] = true;
        if ($limit > 0 && count($urls) >= $limit) {
            break;
        }
    }
    return array_keys($urls);
}

/**
 * Deterministic queue order for AI content generation.
 *
 * The public directory stays relevance-based. This is only for internal/API
 * work queues, where processing high-demand metro areas first is useful. The
 * expression contains no request-derived SQL and is safe to embed in ORDER BY.
 */
function medical_directory_major_city_priority_sql(string $column = 'city'): string
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $column)) {
        $column = 'city';
    }

    $city = "COALESCE({$column}, '') COLLATE utf8mb4_unicode_ci";
    return "CASE
        WHEN {$city} LIKE '%ho chi minh%' OR {$city} LIKE '%hcm%' OR {$city} LIKE '%sai gon%' THEN 1
        WHEN {$city} LIKE '%ha noi%' THEN 2
        WHEN {$city} LIKE '%da nang%' THEN 3
        WHEN {$city} LIKE '%hai phong%' THEN 4
        WHEN {$city} LIKE '%can tho%' THEN 5
        WHEN {$city} LIKE '%hue%' THEN 6
        ELSE 99
    END";
}

/** @return array<int,string> */
function medical_directory_major_city_priority_labels(): array
{
    return ['TP. Hồ Chí Minh', 'Hà Nội', 'Đà Nẵng', 'Hải Phòng', 'Cần Thơ', 'Huế'];
}

/** The shared default for the image-prompt workflow. */
function medical_directory_ai_image_prompt_default(): array
{
    return [
        'Tạo ảnh AI Prompt',
        "Bạn là art director cho nền tảng y tế MedReview. Dựa CHỈ trên dữ liệu của cơ sở '{{name}}' (ngành: {{category}}, thành phố: {{city}}, mô tả: {{subtitle}}) và các URL ảnh tham khảo bên dưới, hãy viết một image prompt bằng tiếng Anh để tạo 01 ảnh đại diện chân thực, chuyên nghiệp, tỷ lệ ngang 16:9. Ảnh phải phù hợp với cơ sở y tế thực tế, ánh sáng tự nhiên, sạch sẽ; không chèn chữ, logo, watermark, số điện thoại, người nổi tiếng hay tuyên bố y khoa không có dữ liệu. Nếu URL không truy cập được, chỉ dùng thông tin văn bản đã cung cấp, không bịa chi tiết kiến trúc/thương hiệu. URL ảnh tham khảo (tối đa 4): {{reference_images}}. Chỉ trả về JSON hợp lệ, không markdown: {\"id\":{{id}},\"image_prompt\":\"...\"}."
    ];
}

/** Adds the separate generated-image field without touching normal image_url. */
function medical_directory_ensure_facility_ai_image_column(PDO $pdo): void
{
    if (medical_directory_column_exists($pdo, 'medical_facilities', 'ai_image_url')) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE medical_facilities ADD COLUMN ai_image_url TEXT NULL AFTER image_url');
    } catch (Throwable $e) {
        // A concurrent request may have completed the same migration first.
        if (!medical_directory_column_exists($pdo, 'medical_facilities', 'ai_image_url')) {
            throw $e;
        }
    }
}

/**
 * The content API receives a richer editorial payload than the original
 * directory card schema. Keep its searchable/contact fields in dedicated
 * columns while retaining the raw payload in `full_json` for traceability.
 *
 * This is one combined ALTER on first deployment, not a DDL statement per
 * incoming field or per facility.
 */
function medical_directory_ensure_facility_content_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked || !medical_directory_table_exists($pdo, 'medical_facilities')) {
        return;
    }
    $checked = true;

    $definitions = [
        'email_text' => 'VARCHAR(255) NULL',
        'latitude' => 'DECIMAL(10,7) NULL',
        'longitude' => 'DECIMAL(10,7) NULL',
        'google_maps_url' => 'TEXT NULL',
        'parking_info' => 'TEXT NULL',
        'nearby_landmarks' => 'TEXT NULL',
        'emergency_hotline' => 'VARCHAR(80) NULL',
        'social_links_json' => 'MEDIUMTEXT NULL',
        'booking_url' => 'TEXT NULL',
        'business_license' => 'TEXT NULL',
        'medical_operation_license' => 'TEXT NULL',
        'established_year' => 'SMALLINT UNSIGNED NULL',
        'branch_count' => 'SMALLINT UNSIGNED NULL',
        'insurance_accepted_json' => 'MEDIUMTEXT NULL',
        'payment_methods_json' => 'MEDIUMTEXT NULL',
        'languages_supported_json' => 'MEDIUMTEXT NULL',
        'warranty_policy' => 'TEXT NULL',
        'equipment_mentioned_json' => 'MEDIUMTEXT NULL',
        'doctors_json' => 'MEDIUMTEXT NULL',
        'video_urls_json' => 'MEDIUMTEXT NULL',
        'aggregate_ratings_json' => 'MEDIUMTEXT NULL',
        'seo_title' => 'VARCHAR(160) NULL',
        'seo_description' => 'VARCHAR(300) NULL',
        'seo_keywords' => 'VARCHAR(255) NULL',
        'price_source_scope' => 'VARCHAR(40) NULL',
        'insufficient_data' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'notes_for_editor' => 'TEXT NULL',
    ];

    try {
        $columns = $pdo->query(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medical_facilities'"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $existing = array_fill_keys(array_map('strval', $columns), true);
        $additions = [];
        foreach ($definitions as $column => $definition) {
            if (!isset($existing[$column])) {
                $additions[] = 'ADD COLUMN `' . $column . '` ' . $definition;
            }
        }
        if ($additions !== []) {
            $pdo->exec('ALTER TABLE medical_facilities ' . implode(', ', $additions));
        }
    } catch (Throwable $e) {
        // The inbound API still keeps a complete copy in full_json. If a
        // deploy is racing a schema migration, do not discard that payload.
    }
}

/** Machine-oriented rules appended to facility prompts served through APIs. */
function medical_directory_facility_json_transport_rules(string $template): string
{
    if (str_contains($template, 'MEDREVIEW_JSON_TRANSPORT_RULES')) {
        return $template;
    }
    return rtrim($template) . "\n\nMEDREVIEW_JSON_TRANSPORT_RULES:\n"
        . "- Chỉ trả JSON hợp lệ theo JSON RFC 8259; không dùng markdown hoặc code fence.\n"
        . "- URL phải là chuỗi URL thô https://..., tuyệt đối không dùng dạng [text](url).\n"
        . "- gallery_json là mảng ảnh, mỗi ảnh dùng object {\"url\":\"https://...\",\"angle\":\"...\",\"caption\":\"...\",\"source\":\"...\"}; url là bắt buộc, các trường còn lại có thể để \"\".\n"
        . "- Mọi dấu ngoặc kép, xuống dòng hoặc ký tự đặc biệt trong content/notes_for_editor phải được JSON escape.\n"
        . "- Trường không có dữ liệu dùng null, [] hoặc \"\" đúng theo kiểu dữ liệu; không thay bằng lời giải thích.\n";
}

/** Inserts the image-prompt default once while preserving any admin edits. */
function medical_directory_ensure_ai_image_prompt(PDO $pdo): void
{
    if (!medical_directory_table_exists($pdo, 'medical_ai_prompts')) {
        return;
    }
    [$label, $template] = medical_directory_ai_image_prompt_default();
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO medical_ai_prompts (prompt_key, label, template)
         VALUES (:key, :label, :template)'
    );
    $stmt->execute([
        ':key' => 'facility_image_prompt',
        ':label' => $label,
        ':template' => $template,
    ]);
}

/**
 * Facility categories are currently free-form text. Store and compare a
 * predictable key so "Nha khoa", " nha   khoa " and "NHA KHOA" resolve to
 * the same custom AI prompt without changing the visible category label.
 */
function medical_directory_ai_prompt_normalize_category(string $category): string
{
    $category = trim((string) (preg_replace('/\s+/u', ' ', $category) ?? ''));
    if ($category === '') {
        return '';
    }

    return function_exists('mb_strtolower')
        ? mb_strtolower($category, 'UTF-8')
        : strtolower($category);
}

/** @param array<string, scalar|null> $values */
function medical_directory_ai_prompt_render_template(string $template, array $values): string
{
    $replacements = [];
    foreach ($values as $key => $value) {
        $replacements['{{' . $key . '}}'] = (string) ($value ?? '');
    }

    return strtr($template, $replacements);
}

/**
 * Resolve a prompt for an item. A facility category variant wins over the
 * shared prompt; unavailable, disabled and non-matching variants safely fall
 * back to the existing prompt in medical_ai_prompts.
 *
 * @return array{prompt_type:string,label:string,template:string,source:string,variant_id:?int,categories:array<int,string>,prompt_key_used:string}
 */
function medical_directory_resolve_ai_prompt(PDO $pdo, string $type, string $category = ''): array
{
    $type = trim($type);
    $categoryKey = medical_directory_ai_prompt_normalize_category($category);

    if ($type === 'facility' && $categoryKey !== '') {
        $variantStmt = $pdo->prepare(
            "SELECT v.id, v.label, v.template
             FROM medical_ai_prompt_variants v
             INNER JOIN medical_ai_prompt_variant_categories c ON c.variant_id = v.id
             WHERE v.prompt_type = :type
               AND v.is_active = 1
               AND c.category_key = :category_key
             ORDER BY v.priority ASC, v.updated_at DESC, v.id DESC
             LIMIT 1"
        );
        $variantStmt->execute([':type' => $type, ':category_key' => $categoryKey]);
        $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($variant)) {
            $categoriesStmt = $pdo->prepare(
                'SELECT category_label
                 FROM medical_ai_prompt_variant_categories
                 WHERE variant_id = :variant_id
                 ORDER BY category_label ASC'
            );
            $categoriesStmt->execute([':variant_id' => (int) $variant['id']]);
            $categories = array_values(array_filter(array_map(
                static fn($value): string => trim((string) $value),
                $categoriesStmt->fetchAll(PDO::FETCH_COLUMN) ?: []
            ), static fn(string $value): bool => $value !== ''));

            return [
                'prompt_type' => $type,
                'label' => (string) ($variant['label'] ?? ''),
                'template' => (string) ($variant['template'] ?? ''),
                'source' => 'category',
                'variant_id' => (int) $variant['id'],
                'categories' => $categories,
                'prompt_key_used' => 'facility-variant-' . (int) $variant['id'],
            ];
        }
    }

    $baseStmt = $pdo->prepare(
        'SELECT prompt_key, label, template
         FROM medical_ai_prompts
         WHERE prompt_key = :key
         LIMIT 1'
    );
    $baseStmt->execute([':key' => $type]);
    $base = $baseStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'prompt_type' => $type,
        'label' => (string) ($base['label'] ?? ''),
        'template' => (string) ($base['template'] ?? ''),
        'source' => 'default',
        'variant_id' => null,
        'categories' => [],
        'prompt_key_used' => (string) ($base['prompt_key'] ?? $type),
    ];
}

function medical_directory_ensure_tables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS medical_facilities (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            name VARCHAR(160) NOT NULL,
            category VARCHAR(120) NOT NULL DEFAULT 'Cơ sở y tế',
            city VARCHAR(120) NOT NULL DEFAULT '',
            subtitle TEXT NULL,
            verified TINYINT(1) NOT NULL DEFAULT 1,
            rating DECIMAL(3,1) NOT NULL DEFAULT 0.0,
            reviews_count INT UNSIGNED NOT NULL DEFAULT 0,
            followers_count INT UNSIGNED NOT NULL DEFAULT 0,
            seo_title VARCHAR(160) NULL,
            seo_description VARCHAR(300) NULL,
            seo_keywords VARCHAR(255) NULL,
            hours_text TEXT NULL,
            address_text VARCHAR(255) NULL,
            phone_text VARCHAR(80) NULL,
            website_url VARCHAR(255) NULL,
            email_text VARCHAR(255) NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            google_maps_url TEXT NULL,
            parking_info TEXT NULL,
            nearby_landmarks TEXT NULL,
            emergency_hotline VARCHAR(80) NULL,
            social_links_json MEDIUMTEXT NULL,
            booking_url TEXT NULL,
            business_license TEXT NULL,
            medical_operation_license TEXT NULL,
            established_year SMALLINT UNSIGNED NULL,
            branch_count SMALLINT UNSIGNED NULL,
            insurance_accepted_json MEDIUMTEXT NULL,
            payment_methods_json MEDIUMTEXT NULL,
            languages_supported_json MEDIUMTEXT NULL,
            warranty_policy TEXT NULL,
            equipment_mentioned_json MEDIUMTEXT NULL,
            doctors_json MEDIUMTEXT NULL,
            video_urls_json MEDIUMTEXT NULL,
            aggregate_ratings_json MEDIUMTEXT NULL,
            price_text VARCHAR(120) NULL,
            image_url VARCHAR(255) NULL,
            ai_image_url TEXT NULL,
            images_label VARCHAR(60) NULL,
            featured_services_json MEDIUMTEXT NULL,
            tags_json MEDIUMTEXT NULL,
            gallery_json MEDIUMTEXT NULL,
            intro_json MEDIUMTEXT NULL,
            stats_json MEDIUMTEXT NULL,
            utilities_json MEDIUMTEXT NULL,
            services_json MEDIUMTEXT NULL,
            review_summary_json MEDIUMTEXT NULL,
            reviews_list_json MEDIUMTEXT NULL,
            features_json MEDIUMTEXT NULL,
            highlights_json MEDIUMTEXT NULL,
            price_source_scope VARCHAR(40) NULL,
            insufficient_data TINYINT(1) NOT NULL DEFAULT 0,
            notes_for_editor TEXT NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            display_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_medical_facilities_slug (slug),
            KEY idx_medical_facilities_status (status),
            KEY idx_medical_facilities_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    try { $pdo->exec("ALTER TABLE medical_facilities ADD COLUMN price_table_html MEDIUMTEXT NULL AFTER price_text"); } catch (Throwable $e) { /* column already exists */ }
    try { $pdo->exec("ALTER TABLE medical_facilities ADD COLUMN content LONGTEXT NULL AFTER subtitle"); } catch (Throwable $e) { /* column already exists */ }
    try { $pdo->exec("ALTER TABLE medical_facilities ADD COLUMN full_json LONGTEXT NULL AFTER content"); } catch (Throwable $e) { /* column already exists */ }
    try { $pdo->exec("ALTER TABLE medical_facilities ADD COLUMN highlights_json MEDIUMTEXT NULL AFTER features_json"); } catch (Throwable $e) { /* column already exists */ }
    medical_directory_ensure_facility_ai_image_column($pdo);
    medical_directory_ensure_facility_content_columns($pdo);
    // Older installs used VARCHAR(120), which rejects complete weekly
    // schedules received from the content API. Migrate once, while keeping
    // normal page requests free of repeated ALTER TABLE statements.
    try {
        $hoursType = strtolower((string) $pdo->query(
            "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'medical_facilities'
               AND COLUMN_NAME = 'hours_text'
             LIMIT 1"
        )->fetchColumn());
        if ($hoursType !== 'text') {
            $pdo->exec('ALTER TABLE medical_facilities MODIFY COLUMN hours_text TEXT NULL AFTER followers_count');
        }
    } catch (Throwable $e) { /* keep the directory usable if schema changes are temporarily unavailable */ }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS medical_reviews (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            facility_slug VARCHAR(191) NULL,
            facility_name VARCHAR(160) NOT NULL DEFAULT '',
            title VARCHAR(190) NOT NULL,
            verified TINYINT(1) NOT NULL DEFAULT 1,
            rating DECIMAL(3,1) NOT NULL DEFAULT 0.0,
            author_text VARCHAR(120) NOT NULL DEFAULT '',
            location_text VARCHAR(120) NOT NULL DEFAULT '',
            review_date_text VARCHAR(40) NOT NULL DEFAULT '',
            price_text VARCHAR(120) NOT NULL DEFAULT '',
            excerpt TEXT NULL,
            before_image_url VARCHAR(255) NULL,
            after_image_url VARCHAR(255) NULL,
            likes_count INT UNSIGNED NOT NULL DEFAULT 0,
            comments_count INT UNSIGNED NOT NULL DEFAULT 0,
            shares_count INT UNSIGNED NOT NULL DEFAULT 0,
            service_text VARCHAR(120) NOT NULL DEFAULT '',
            method_text VARCHAR(160) NOT NULL DEFAULT '',
            duration_text VARCHAR(80) NOT NULL DEFAULT '',
            start_date_text VARCHAR(40) NOT NULL DEFAULT '',
            end_date_text VARCHAR(40) NOT NULL DEFAULT '',
            condition_text VARCHAR(255) NOT NULL DEFAULT '',
            source_text VARCHAR(255) NOT NULL DEFAULT '',
            story_json MEDIUMTEXT NULL,
            timeline_json MEDIUMTEXT NULL,
            thumbs_json MEDIUMTEXT NULL,
            process_before_json MEDIUMTEXT NULL,
            process_during_json MEDIUMTEXT NULL,
            process_after_json MEDIUMTEXT NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            display_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_medical_reviews_slug (slug),
            KEY idx_medical_reviews_status (status),
            KEY idx_medical_reviews_facility (facility_slug),
            KEY idx_medical_reviews_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    try { $pdo->exec("ALTER TABLE medical_reviews ADD COLUMN facility_id INT UNSIGNED NULL AFTER facility_slug"); } catch (Throwable $e) { /* column already exists */ }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS medical_doctors (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(191) NOT NULL,
            name VARCHAR(160) NOT NULL,
            title_text VARCHAR(190) NOT NULL DEFAULT '',
            specialty_text VARCHAR(160) NOT NULL DEFAULT '',
            city VARCHAR(120) NOT NULL DEFAULT '',
            facility_slug VARCHAR(191) NULL,
            facility_name VARCHAR(160) NOT NULL DEFAULT '',
            verified TINYINT(1) NOT NULL DEFAULT 1,
            rating DECIMAL(3,1) NOT NULL DEFAULT 0.0,
            reviews_count INT UNSIGNED NOT NULL DEFAULT 0,
            followers_count INT UNSIGNED NOT NULL DEFAULT 0,
            hours_text VARCHAR(120) NULL,
            price_text VARCHAR(120) NULL,
            image_url VARCHAR(255) NULL,
            tags_json MEDIUMTEXT NULL,
            specialties_json MEDIUMTEXT NULL,
            gallery_json MEDIUMTEXT NULL,
            bio_json MEDIUMTEXT NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'published',
            display_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_medical_doctors_slug (slug),
            KEY idx_medical_doctors_status (status),
            KEY idx_medical_doctors_facility (facility_slug),
            KEY idx_medical_doctors_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS medical_ai_prompts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, prompt_key VARCHAR(40) NOT NULL, label VARCHAR(120) NOT NULL,
        template TEXT NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uniq_medical_ai_prompt_key (prompt_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Custom facility prompts are intentionally separate from the global
    // prompt table: one prompt can serve many categories and the original
    // `facility` key remains the reliable fallback for every other facility.
    $pdo->exec("CREATE TABLE IF NOT EXISTS medical_ai_prompt_variants (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        prompt_type VARCHAR(40) NOT NULL DEFAULT 'facility',
        label VARCHAR(120) NOT NULL,
        template MEDIUMTEXT NOT NULL,
        priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_medical_ai_prompt_variants_lookup (prompt_type, is_active, priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS medical_ai_prompt_variant_categories (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        variant_id INT UNSIGNED NOT NULL,
        category_key VARCHAR(120) NOT NULL,
        category_label VARCHAR(120) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_medical_ai_prompt_variant_category (variant_id, category_key),
        KEY idx_medical_ai_prompt_variant_category_lookup (category_key, variant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $defaults = [
        'facility' => ['Cơ sở y tế', "Hãy viết bài giới thiệu chuyên nghiệp 300-500 từ về cơ sở y tế '{{name}}' - địa chỉ: {{address}} - số điện thoại: {{phone}} - website: {{website}} - giờ làm việc: {{hours}}. Nêu rõ dịch vụ, thế mạnh và trải nghiệm khách hàng. Chỉ dùng thông tin được cung cấp, không tự bịa dữ liệu. Ưu tiên lấy ảnh gallery từ website chính thức của cơ sở, không dùng ảnh không xác minh. Chỉ trả về JSON hợp lệ, không markdown, theo mẫu: {\"id\":{{id}},\"name\":\"{{name}}\",\"subtitle\":\"...\",\"content\":\"HTML 300-500 từ\",\"services\":[\"...\"],\"price_table_html\":\"&lt;table&gt;...&lt;/table&gt;\",\"address\":\"{{address}}\",\"phone\":\"{{phone}}\",\"website\":\"{{website}}\",\"hours\":\"{{hours}}\",\"gallery_json\":[{\"url\":\"https://...\",\"angle\":\"Mặt tiền / biển hiệu\",\"caption\":\"Mô tả ngắn chính xác\",\"source\":\"Google Maps hoặc website chính thức\"}]}. Ghi chú trường: id là ID cơ sở, phải giữ nguyên; name là tên phòng khám/cơ sở; subtitle là mô tả ngắn; content là bài HTML 300-500 từ; services là danh sách dịch vụ; price_table_html là bảng giá HTML có cột Dịch vụ và Khoảng giá; address là địa chỉ; phone là số điện thoại; website là website; hours là giờ làm việc, giữ nguyên thông tin được cung cấp; gallery_json là mảng object ảnh, url là URL thô bắt buộc còn angle/caption/source ghi chính xác khi có dữ liệu. Không tự bịa dữ liệu."],
        'facility_image_prompt' => medical_directory_ai_image_prompt_default(),
        'toplist' => ['Danh sách cơ sở cho Toplist', "Hãy lập danh sách cơ sở y tế phù hợp cho bài Toplist '{{title}}'. Thông tin hiện có của bài: {{excerpt}} {{content}}. Tìm và chỉ chọn các cơ sở thực sự phù hợp với tiêu chí của tiêu đề; ưu tiên website chính thức hoặc nguồn đáng tin cậy để đối chiếu. Không tự bịa tên, địa chỉ, số điện thoại hoặc website. Chỉ trả về JSON hợp lệ, không markdown, theo mẫu: {\"toplist_id\":{{id}},\"facilities\":[{\"facility_id\":0,\"name\":\"Tên cơ sở\",\"category\":\"Cơ sở y tế\",\"city\":\"Tỉnh/thành\",\"address\":\"Địa chỉ\",\"phone\":\"Số điện thoại nếu có\",\"website\":\"Website chính thức nếu có\",\"rank_order\":1}]}. Ghi chú trường: toplist_id là ID bài Toplist, phải giữ nguyên để cập nhật đúng bài; facilities là danh sách cơ sở theo thứ hạng; facility_id chỉ dùng khi biết chắc ID cơ sở đã có trong hệ thống, không biết thì để 0; name là tên cơ sở; category là nhóm cơ sở; city là tỉnh/thành; address là địa chỉ; phone là số điện thoại; website là website chính thức; rank_order là thứ hạng bắt đầu từ 1. Không đưa cơ sở không đủ thông tin nhận diện."],
        'doctor' => ['Bác sĩ', "Hãy viết bài giới thiệu chuyên môn về bác sĩ \"{{name}}\". Trình bày chuyên khoa, kinh nghiệm, dịch vụ và điểm nổi bật bằng giọng văn đáng tin cậy. Chỉ dùng thông tin được cung cấp, không bịa chứng chỉ hoặc thành tích. Chỉ trả về JSON hợp lệ theo mẫu: {\"name\":\"{{name}}\",\"title_text\":\"...\",\"specialty_text\":\"...\",\"bio\":\"HTML 300-500 từ\",\"services\":[\"...\"],\"address\":\"...\",\"phone\":\"...\",\"website\":\"...\"}"],
        'review' => ['Review y tế', "Hãy viết một bài review khách quan về \"{{name}}\". Nêu ưu điểm, điểm cần lưu ý, dịch vụ, chi phí tham khảo và trải nghiệm thực tế. Không khẳng định tuyệt đối và không bịa đánh giá. Chỉ trả về JSON hợp lệ theo mẫu: {\"facility_id\":{{id}},\"facility_slug\":\"...\",\"facility_name\":\"{{name}}\",\"title\":\"...\",\"rating\":0,\"service_text\":\"...\",\"price_text\":\"...\",\"address\":\"{{address}}\",\"phone\":\"{{phone}}\",\"website\":\"{{website}}\",\"excerpt\":\"...\",\"content\":\"HTML 300-500 từ\"}"],
    ];
    $check = $pdo->prepare('SELECT id FROM medical_ai_prompts WHERE prompt_key = :k LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO medical_ai_prompts (prompt_key,label,template) VALUES (:k,:l,:t)');
    foreach ($defaults as $key => [$label, $template]) {
        $check->execute([':k' => $key]);
        if (!$check->fetchColumn()) {
            $insert->execute([':k'=>$key,':l'=>$label,':t'=>$template]);
        }
    }
    medical_directory_ensure_ai_image_prompt($pdo);
}

function medical_directory_default_facility_base(): array
{
    return [
        'slug' => 'top-dental-clinic',
        'category' => 'Nha khoa',
        'city' => 'Đà Nẵng',
        'name' => 'Top Dental Clinic',
        'verified' => 1,
        'subtitle' => 'Nha khoa thẩm mỹ công nghệ cao - Nụ cười tự tin, tỏa sáng',
        'rating' => '4.9',
        'reviews_count' => 658,
        'followers_count' => 12648,
        'hours_text' => '08:00 - 20:00 (T2 - CN)',
        'address_text' => '46 Trần Tống, P. Thanh Khê, Q. Thanh Khê, Đà Nẵng',
        'phone_text' => '0935 678 910',
        'website_url' => 'https://topdentaldanang.com',
        'price_text' => '1.000.000đ - 100.000.000đ+',
        'image_url' => 'https://images.unsplash.com/photo-1629909615184-74f495363b67?auto=format&fit=crop&w=900&q=80',
        'images_label' => '25+ ảnh',
        'featured_services' => ['Răng sứ thẩm mỹ', 'Trồng răng implant', 'Niềng răng', 'Tẩy trắng răng'],
        'tags' => ['Nha khoa', 'Đà Nẵng', '10+ năm kinh nghiệm'],
        'gallery' => [
            'https://images.unsplash.com/photo-1629909615184-74f495363b67?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1643489184648-3f6d0f1e1d14?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=1200&q=80',
        ],
        'intro' => [
            'Top Dental Clinic là hệ thống nha khoa thẩm mỹ uy tín tại Đà Nẵng, chuyên sâu về răng sứ thẩm mỹ, implant, niềng răng và các dịch vụ nha khoa tổng quát.',
            'Với đội ngũ bác sĩ giàu kinh nghiệm, trang thiết bị hiện đại và vật liệu chính hãng, chúng tôi cam kết mang đến nụ cười khỏe mạnh, chuẩn thẩm mỹ, tự nhiên và bền vững cho từng khách hàng.',
        ],
        'stats' => [
            ['icon' => 'clock-3', 'value' => '10+', 'label' => 'Năm kinh nghiệm'],
            ['icon' => 'users', 'value' => '20.000+', 'label' => 'Khách hàng hài lòng'],
            ['icon' => 'shield-check', 'value' => '100%', 'label' => 'Vật liệu chính hãng'],
            ['icon' => 'sparkles', 'value' => '50.000+', 'label' => 'Ca điều trị thành công'],
        ],
        'utilities' => [
            'Chỗ đậu ô tô',
            'Wifi miễn phí',
            'Thanh toán thẻ',
            'Trả góp 0% lãi suất',
        ],
        'services' => [
            [
                'title' => 'Răng sứ thẩm mỹ',
                'subtitle' => 'Dáng răng tự nhiên, tôn sáng',
                'rating' => '4.9',
                'reviews' => '(658)',
                'price' => 'Từ 1.000.000đ',
                'image' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            ],
            [
                'title' => 'Trồng răng Implant',
                'subtitle' => 'Cấy ghép trụ Implant chính hãng',
                'rating' => '4.9',
                'reviews' => '(658)',
                'price' => 'Từ 12.000.000đ',
                'image' => 'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=900&q=80',
            ],
            [
                'title' => 'Niềng răng',
                'subtitle' => 'Niềng mắc cài, trong suốt',
                'rating' => '4.9',
                'reviews' => '(658)',
                'price' => 'Từ 25.000.000đ',
                'image' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            ],
            [
                'title' => 'Tẩy trắng răng',
                'subtitle' => 'Công nghệ Laser Whitening',
                'rating' => '4.9',
                'reviews' => '(658)',
                'price' => 'Từ 2.500.000đ',
                'image' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            ],
        ],
        'review_summary' => [
            'rating' => '4.9',
            'reviews' => '658 đánh giá',
            'breakdown' => [
                ['label' => '5 sao', 'value' => 92],
                ['label' => '4 sao', 'value' => 7],
                ['label' => '3 sao', 'value' => 1],
                ['label' => '2 sao', 'value' => 0],
                ['label' => '1 sao', 'value' => 0],
            ],
        ],
        'reviews_list' => [
            [
                'author' => 'Nguyễn Thị Lan',
                'verified' => 'Đã xác thực',
                'date' => '2 ngày trước',
                'service' => 'Răng sứ thẩm mỹ',
                'content' => 'Mình làm răng sứ ở đây rất ưng ý, bác sĩ tư vấn tận tâm, răng làm xong tự nhiên lắm!',
                'likes' => 12,
                'comments' => 1,
                'images' => [
                    'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=400&q=80',
                    'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=400&q=80',
                    'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=400&q=80',
                ],
            ],
            [
                'author' => 'Trần Văn Minh',
                'verified' => 'Đã xác thực',
                'date' => '1 tuần trước',
                'service' => 'Trồng răng Implant',
                'content' => 'Trồng răng Implant không đau, bác sĩ rất nhẹ tay. Phòng khám sạch sẽ, lịch hẹn rõ ràng.',
                'likes' => 8,
                'comments' => 0,
                'images' => [
                    'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=400&q=80',
                    'https://images.unsplash.com/photo-1629909615184-74f495363b67?auto=format&fit=crop&w=400&q=80',
                    'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=400&q=80',
                ],
            ],
            [
                'author' => 'Lê Thu Hương',
                'verified' => 'Đã xác thực',
                'date' => '2 tuần trước',
                'service' => 'Niềng răng',
                'content' => 'Niềng răng ở đây được 1 năm, răng đều đẹp lên mỗi ngày. Cảm ơn bác sĩ nhiều!',
                'likes' => 6,
                'comments' => 0,
                'images' => [
                    'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=400&q=80',
                    'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=400&q=80',
                ],
            ],
        ],
        'features' => [
            ['icon' => 'badge-check', 'title' => 'Đánh giá xác thực', 'text' => 'Tất cả đánh giá đều từ khách hàng đã sử dụng dịch vụ thực tế'],
            ['icon' => 'shield-check', 'title' => 'Kiểm duyệt nghiêm ngặt', 'text' => 'Nội dung được kiểm duyệt để đảm bảo minh bạch và khách quan'],
            ['icon' => 'lock', 'title' => 'Bảo mật thông tin', 'text' => 'Thông tin cá nhân của bạn được bảo vệ tuyệt đối'],
            ['icon' => 'refresh-cw', 'title' => 'Cập nhật liên tục', 'text' => 'Đánh giá mới nhất được cập nhật mỗi ngày'],
        ],
        'status' => 'published',
        'display_order' => 1,
    ];
}

function medical_directory_default_facilities(): array
{
    $base = medical_directory_default_facility_base();
    return [
        $base,
        array_replace($base, [
            'slug' => 'nha-khoa-rang-ngoi',
            'name' => 'Nha Khoa Răng Ngời',
            'subtitle' => 'Nụ cười rạng ngời - Tự tin tỏa sáng',
            'rating' => '4.8',
            'reviews_count' => 512,
            'followers_count' => 8342,
            'hours_text' => '08:00 - 19:30 (T2 - CN)',
            'address_text' => '82 Lê Đình Lý, Q. Thanh Khê, Đà Nẵng',
            'phone_text' => '0905 123 456',
            'website_url' => 'https://nhakhoarangngoi.vn',
            'price_text' => '800.000đ - 80.000.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=900&q=80',
            'images_label' => '18+ ảnh',
            'featured_services' => ['Răng sứ thẩm mỹ', 'Trồng răng Implant', 'Niềng răng', 'Nha khoa tổng quát'],
            'tags' => ['Nha khoa', 'Đà Nẵng', 'Thẩm mỹ'],
            'display_order' => 2,
        ]),
        array_replace($base, [
            'slug' => 'smile-dental-clinic',
            'name' => 'Smile Dental Clinic',
            'subtitle' => 'Công nghệ hiện đại - Chuẩn quốc tế',
            'rating' => '4.7',
            'reviews_count' => 438,
            'followers_count' => 6105,
            'hours_text' => '08:30 - 20:00 (T2 - CN)',
            'address_text' => '157 Nguyễn Văn Linh, Q. Hải Châu, Đà Nẵng',
            'phone_text' => '0898 765 432',
            'website_url' => 'https://smiledental.vn',
            'price_text' => '900.000đ - 90.000.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'images_label' => '20+ ảnh',
            'tags' => ['Nha khoa', 'Quốc tế', 'Implant'],
            'display_order' => 3,
        ]),
        array_replace($base, [
            'slug' => 'happy-dental',
            'name' => 'Happy Dental',
            'subtitle' => 'Nha khoa chuẩn mực - Nụ cười hạnh phúc',
            'rating' => '4.6',
            'reviews_count' => 367,
            'followers_count' => 4987,
            'hours_text' => '08:00 - 19:00 (T2 - CN)',
            'address_text' => '129 Hoàng Diệu, Q. Hải Châu, Đà Nẵng',
            'phone_text' => '0911 246 810',
            'website_url' => 'https://happydental.vn',
            'price_text' => '700.000đ - 70.000.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1643489184648-3f6d0f1e1d14?auto=format&fit=crop&w=900&q=80',
            'images_label' => '15+ ảnh',
            'featured_services' => ['Răng sứ thẩm mỹ', 'Trồng răng Implant', 'Niềng răng', 'Nha khoa tổng quát'],
            'tags' => ['Nha khoa', 'Gia đình', 'Tổng quát'],
            'display_order' => 4,
        ]),
        array_replace($base, [
            'slug' => 'nha-khoa-dr-anh',
            'name' => 'Nha Khoa Dr.Anh',
            'subtitle' => 'Bác sĩ chuyên sâu Implant hơn 12 năm',
            'rating' => '4.8',
            'reviews_count' => 324,
            'followers_count' => 4120,
            'hours_text' => '08:00 - 20:00 (T2 - CN)',
            'address_text' => '235 Nguyễn Chí Thanh, Q. Hải Châu, Đà Nẵng',
            'phone_text' => '0902 333 555',
            'website_url' => 'https://dranhdental.vn',
            'price_text' => '1.200.000đ - 85.000.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=900&q=80',
            'images_label' => '22+ ảnh',
            'featured_services' => ['Trồng răng Implant', 'Niềng răng', 'Răng sứ', 'Nha chu'],
            'tags' => ['Nha khoa', 'Chuyên sâu', 'Implant'],
            'display_order' => 5,
        ]),
        array_replace($base, [
            'slug' => 'dental-plus-clinic',
            'name' => 'Dental Plus Clinic',
            'subtitle' => 'Phục hình toàn hàm - Giải pháp hoàn chỉnh',
            'rating' => '4.7',
            'reviews_count' => 287,
            'followers_count' => 3856,
            'hours_text' => '09:00 - 20:00 (T2 - CN)',
            'address_text' => '47 Hàm Nghi, Q. Thanh Khê, Đà Nẵng',
            'phone_text' => '0918 777 222',
            'website_url' => 'https://dentalplus.vn',
            'price_text' => '1.000.000đ - 95.000.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=900&q=80',
            'images_label' => '16+ ảnh',
            'featured_services' => ['Implant toàn hàm', 'Răng sứ thẩm mỹ', 'Niềng trong suốt', 'Tẩy trắng'],
            'tags' => ['Nha khoa', 'Phục hình', 'Cao cấp'],
            'display_order' => 6,
        ]),
        array_replace($base, [
            'slug' => 'nha-khoa-bao-son',
            'name' => 'Nha Khoa Bảo Sơn',
            'subtitle' => 'Mạng lưới nhiều chi nhánh - Tiết kiệm chi phí',
            'rating' => '4.5',
            'reviews_count' => 410,
            'followers_count' => 5230,
            'hours_text' => '07:30 - 19:30 (T2 - CN)',
            'address_text' => '889 Ngo Quyen, Q. Sơn Trà, Đà Nẵng',
            'phone_text' => '0909 888 666',
            'website_url' => 'https://baosondental.vn',
            'price_text' => '500.000đ - 60.000.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'images_label' => '12+ ảnh',
            'featured_services' => ['Nha khoa tổng quát', 'Trám răng', 'Nhổ răng', 'Lấy cao răng'],
            'tags' => ['Nha khoa', 'Gia đình', 'Chi nhánh'],
            'display_order' => 7,
        ]),
        array_replace($base, [
            'slug' => 'victoria-dental',
            'name' => 'Victoria Dental Clinic',
            'subtitle' => 'Thẩm mỹ nụ cười Pháp - Sang trọng & tinh tế',
            'rating' => '4.9',
            'reviews_count' => 198,
            'followers_count' => 3420,
            'hours_text' => '09:00 - 19:00 (T2 - T7)',
            'address_text' => '18 Võ Nguyên Giáp, Q. Sơn Trà, Đà Nẵng',
            'phone_text' => '0988 111 999',
            'website_url' => 'https://victoriadental.vn',
            'price_text' => '2.000.000đ - 120.000.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=900&q=80',
            'images_label' => '30+ ảnh',
            'featured_services' => ['Răng sứ Emax', 'Niềng Invisalign', 'Design nụ cười', 'Thẩm mỹ cao cấp'],
            'tags' => ['Nha khoa', 'Cao cấp', 'Pháp'],
            'display_order' => 8,
        ]),
        array_replace($base, [
            'slug' => 'greenlife-dental',
            'name' => 'GreenLife Dental',
            'subtitle' => 'Nha khoa thân thiện môi trường - Vật liệu sinh học',
            'rating' => '4.6',
            'reviews_count' => 156,
            'followers_count' => 2180,
            'hours_text' => '08:30 - 18:30 (T2 - CN)',
            'address_text' => '312 Điện Biên Phủ, Q. Hải Châu, Đà Nẵng',
            'phone_text' => '0934 555 777',
            'website_url' => 'https://greenlifedental.vn',
            'price_text' => '800.000đ - 75.000.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1629909613654-28e377c37b09?auto=format&fit=crop&w=900&q=80',
            'images_label' => '14+ ảnh',
            'featured_services' => ['Niềng răng mắc cài', 'Lấy cao răng', 'Tẩy trắng', 'Chữa tủy'],
            'tags' => ['Nha khoa', 'Sinh học', 'Thân thiện'],
            'display_order' => 9,
        ]),
        array_replace($base, [
            'slug' => 'dental-hub-da-nang',
            'name' => 'Dental Hub Đà Nẵng',
            'subtitle' => 'Trung tâm Implant & Thẩm mỹ nha khoa miền Trung',
            'rating' => '4.7',
            'reviews_count' => 267,
            'followers_count' => 3540,
            'hours_text' => '08:00 - 21:00 (T2 - CN)',
            'address_text' => '256 Trường Chinh, Q. Liên Chiểu, Đà Nẵng',
            'phone_text' => '0977 222 444',
            'website_url' => 'https://dentalhubdanang.vn',
            'price_text' => '1.500.000đ - 110.000.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1609207825181-52d3214556a3?auto=format&fit=crop&w=900&q=80',
            'images_label' => '28+ ảnh',
            'featured_services' => ['All-on-4 Implant', 'Niềng răng', 'Răng sứ Zirconia', 'Phẫu thuật'],
            'tags' => ['Nha khoa', 'Trung tâm', 'Implant'],
            'display_order' => 10,
        ]),
    ];
}

function medical_directory_default_review_base(): array
{
    return [
        'slug' => 'nieng-invisalign-sau-18-thang',
        'facility_slug' => 'top-dental-clinic',
        'facility_name' => 'Top Dental Clinic',
        'title' => 'Niềng Invisalign sau 18 tháng',
        'verified' => 1,
        'rating' => '4.9',
        'author_text' => 'Nữ, 26 tuổi',
        'location_text' => 'Đà Nẵng',
        'review_date_text' => '18/05/2024',
        'price_text' => '45.000.000đ',
        'excerpt' => 'Sau 18 tháng niềng Invisalign tại Top Dental Clinic, mình rất hài lòng với kết quả hiện tại. Răng đều đẹp, khớp cắn chuẩn và quá trình theo dõi rất kỹ.',
        'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
        'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
        'likes_count' => 128,
        'comments_count' => 32,
        'shares_count' => 12,
        'service_text' => 'Niềng răng',
        'method_text' => 'Invisalign',
        'duration_text' => '18 tháng',
        'start_date_text' => '12/11/2022',
        'end_date_text' => '12/05/2024',
        'condition_text' => 'Khớp cắn sâu, răng chen chúc',
        'story' => [
            'Mình từng rất tự ti vì răng khấp khểnh, chen chúc và khớp cắn sâu. Sau khi tìm hiểu nhiều nơi, mình quyết định niềng Invisalign tại Top Dental Clinic.',
            'Quá trình niềng diễn ra nhẹ nhàng, không đau nhiều như mình nghĩ. Bác sĩ theo dõi rất sát, mỗi lần tái khám đều rất tận tình.',
            'Sau 18 tháng, mình có nụ cười hàm răng đều đẹp, khớp cắn chuẩn và nụ cười tự tin hơn rất nhiều.',
        ],
        'timeline' => [
            ['date' => '12/11/2022', 'label' => 'Khám và tư vấn'],
            ['date' => '26/11/2022', 'label' => 'Bắt đầu niềng'],
            ['date' => '12/02/2023', 'label' => 'Điều chỉnh lần 1'],
            ['date' => '12/08/2023', 'label' => 'Điều chỉnh lần 2'],
            ['date' => '12/05/2024', 'label' => 'Kết thúc niềng'],
        ],
        'thumbs' => [
            'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=400&q=80',
            'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=400&q=80',
            'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=400&q=80',
            'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=400&q=80',
            'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=400&q=80',
            'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=400&q=80',
        ],
        'process_before' => [
            'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=500&q=80',
            'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=500&q=80',
            'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=500&q=80',
            'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=500&q=80',
        ],
        'process_during' => [
            'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=500&q=80',
            'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=500&q=80',
            'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=500&q=80',
            'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=500&q=80',
        ],
        'process_after' => [
            'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=500&q=80',
            'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=500&q=80',
            'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=500&q=80',
            'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=500&q=80',
        ],
        'status' => 'published',
        'display_order' => 1,
    ];
}

function medical_directory_default_reviews(): array
{
    $base = medical_directory_default_review_base();
    return [
        $base,
        array_replace($base, [
            'slug' => 'tay-trang-rang-sau-1-buoi',
            'facility_slug' => 'top-dental-clinic',
            'facility_name' => 'Top Dental Clinic',
            'title' => 'Tẩy trắng răng sau 1 buổi',
            'rating' => '4.8',
            'author_text' => 'Nữ, 24 tuổi',
            'review_date_text' => '02/06/2024',
            'price_text' => '2.500.000đ',
            'excerpt' => 'Tẩy trắng răng tại Top Dental Clinic nhanh, không ê buốt nhiều. Màu răng sáng tự nhiên và được hướng dẫn chăm sóc kỹ.',
            'before_image_url' => 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 54,
            'comments_count' => 8,
            'shares_count' => 3,
            'service_text' => 'Tẩy trắng răng',
            'method_text' => 'Laser Whitening',
            'duration_text' => '1 buổi',
            'start_date_text' => '02/06/2024',
            'end_date_text' => '02/06/2024',
            'condition_text' => 'Răng xỉn màu do cà phê',
            'story' => [
                'Mình hay uống cà phê nên răng bị xỉn màu. Mình chọn tẩy trắng tại Top Dental Clinic vì quy trình rõ ràng và phòng khám sạch.',
                'Bác sĩ kiểm tra men răng trước khi làm, giải thích các mức độ trắng phù hợp nên mình khá yên tâm.',
                'Sau buổi tẩy trắng, răng sáng hơn rõ rệt và nhìn vẫn tự nhiên.',
            ],
            'timeline' => [
                ['date' => '02/06/2024', 'label' => 'Khám & tư vấn'],
                ['date' => '02/06/2024', 'label' => 'Thực hiện tẩy trắng'],
                ['date' => '02/06/2024', 'label' => 'Dặn dò chăm sóc'],
            ],
            'display_order' => 2,
        ]),
        array_replace($base, [
            'slug' => 'boc-rang-su-16-rang',
            'facility_slug' => 'top-dental-clinic',
            'facility_name' => 'Top Dental Clinic',
            'title' => 'Bọc răng sứ 16 răng',
            'rating' => '4.9',
            'author_text' => 'Nam, 31 tuổi',
            'review_date_text' => '18/04/2024',
            'price_text' => '38.000.000đ',
            'excerpt' => 'Bọc sứ 16 răng, form răng đều và hợp mặt. Bác sĩ chỉnh form kỹ nên cười tự tin hơn.',
            'before_image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 112,
            'comments_count' => 21,
            'shares_count' => 9,
            'service_text' => 'Răng sứ',
            'method_text' => 'Zirconia',
            'duration_text' => '7 ngày',
            'start_date_text' => '11/04/2024',
            'end_date_text' => '18/04/2024',
            'condition_text' => 'Răng thưa nhẹ, màu không đều',
            'story' => [
                'Mình muốn cải thiện màu răng và form răng cho đều hơn. Bác sĩ tư vấn kỹ về kiểu dáng và màu phù hợp.',
                'Quá trình làm có đeo răng tạm, chỉnh form vài lần cho đúng ý.',
                'Kết quả cuối cùng rất hài hòa, ăn nhai bình thường và cảm giác tự nhiên.',
            ],
            'timeline' => [
                ['date' => '11/04/2024', 'label' => 'Khám & lấy dấu'],
                ['date' => '13/04/2024', 'label' => 'Mài răng & gắn tạm'],
                ['date' => '16/04/2024', 'label' => 'Thử form'],
                ['date' => '18/04/2024', 'label' => 'Gắn hoàn thiện'],
            ],
            'display_order' => 3,
        ]),
        array_replace($base, [
            'slug' => 'nho-rang-khon-khong-dau',
            'facility_slug' => 'top-dental-clinic',
            'facility_name' => 'Top Dental Clinic',
            'title' => 'Nhổ răng khôn không đau',
            'rating' => '4.7',
            'author_text' => 'Nữ, 27 tuổi',
            'review_date_text' => '09/03/2024',
            'price_text' => '1.200.000đ',
            'excerpt' => 'Nhổ răng khôn nhanh, bác sĩ nhẹ tay. Hồi phục ổn, không sưng nhiều.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 48,
            'comments_count' => 6,
            'shares_count' => 2,
            'service_text' => 'Nhổ răng',
            'method_text' => 'Tiểu phẫu răng khôn',
            'duration_text' => '30 phút',
            'start_date_text' => '09/03/2024',
            'end_date_text' => '09/03/2024',
            'condition_text' => 'Răng khôn mọc lệch',
            'story' => [
                'Mình lo lắng vì nghe nhổ răng khôn rất đau. Nhưng bác sĩ gây tê kỹ nên lúc làm gần như không cảm giác.',
                'Sau nhổ được hướng dẫn chăm sóc chi tiết, dùng thuốc đúng nên hồi phục nhanh.',
                'Tái khám ổn, không có biến chứng.',
            ],
            'timeline' => [
                ['date' => '09/03/2024', 'label' => 'Chụp phim & tư vấn'],
                ['date' => '09/03/2024', 'label' => 'Tiểu phẫu'],
                ['date' => '16/03/2024', 'label' => 'Tái khám'],
            ],
            'display_order' => 4,
        ]),
        array_replace($base, [
            'slug' => 'nieng-mac-cai-12-thang',
            'facility_slug' => 'top-dental-clinic',
            'facility_name' => 'Top Dental Clinic',
            'title' => 'Niềng mắc cài sau 12 tháng',
            'rating' => '4.8',
            'author_text' => 'Nam, 22 tuổi',
            'review_date_text' => '25/05/2024',
            'price_text' => '28.000.000đ',
            'excerpt' => 'Sau 12 tháng niềng mắc cài, răng đều rõ rệt. Lịch tái khám đúng hẹn và tư vấn dễ hiểu.',
            'before_image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 73,
            'comments_count' => 10,
            'shares_count' => 4,
            'service_text' => 'Niềng răng',
            'method_text' => 'Mắc cài kim loại',
            'duration_text' => '12 tháng',
            'start_date_text' => '25/05/2023',
            'end_date_text' => '25/05/2024',
            'condition_text' => 'Răng chen chúc nhẹ',
            'story' => [
                'Mình niềng mắc cài để chỉnh răng chen chúc. Tháng đầu hơi khó chịu nhưng sau quen dần.',
                'Bác sĩ theo dõi sát và điều chỉnh lực phù hợp nên không đau quá nhiều.',
                'Hiện răng đều hơn rõ, tự tin khi cười.',
            ],
            'timeline' => [
                ['date' => '25/05/2023', 'label' => 'Gắn mắc cài'],
                ['date' => '25/08/2023', 'label' => 'Điều chỉnh giai đoạn 1'],
                ['date' => '25/01/2024', 'label' => 'Điều chỉnh giai đoạn 2'],
                ['date' => '25/05/2024', 'label' => 'Kết thúc 12 tháng'],
            ],
            'display_order' => 5,
        ]),
        array_replace($base, [
            'slug' => 'trong-2-rang-implant',
            'facility_slug' => 'nha-khoa-rang-ngoi',
            'facility_name' => 'Nha Khoa Răng Ngời',
            'title' => 'Trồng 2 răng Implant',
            'rating' => '4.8',
            'author_text' => 'Nam, 45 tuổi',
            'review_date_text' => '08/04/2024',
            'price_text' => '32.000.000đ',
            'excerpt' => 'Mình bị mất 2 răng hàm, ăn nhai rất bất tiện. Sau khi trồng Implant tại Răng Xinh, cảm giác ăn nhai gần như răng thật và không đau nhiều như mình nghĩ.',
            'before_image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 96,
            'comments_count' => 18,
            'shares_count' => 8,
            'service_text' => 'Implant',
            'method_text' => 'Cấy ghép Implant',
            'duration_text' => '6 tháng',
            'start_date_text' => '10/10/2023',
            'end_date_text' => '08/04/2024',
            'condition_text' => 'Mất 2 răng hàm',
            'story' => [
                'Mình bị mất 2 răng hàm nên ăn nhai rất khó. Sau khi tham khảo một số nơi, mình chọn Răng Xinh để cấy Implant.',
                'Quá trình điều trị được chia từng giai đoạn rõ ràng nên khá yên tâm. Sau khi hồi phục, ăn nhai cải thiện rõ rệt.',
                'Kết quả cuối cùng ổn định, nhìn tự nhiên và không còn cảm giác khó chịu khi ăn như trước.',
            ],
            'timeline' => [
                ['date' => '10/10/2023', 'label' => 'Khám tổng quát'],
                ['date' => '22/10/2023', 'label' => 'Cấy trụ Implant'],
                ['date' => '25/12/2023', 'label' => 'Tái khám'],
                ['date' => '14/02/2024', 'label' => 'Gắn mão tạm'],
                ['date' => '08/04/2024', 'label' => 'Hoàn thiện'],
            ],
            'display_order' => 6,
        ]),
        array_replace($base, [
            'slug' => 'cay-implant-1-tru',
            'facility_slug' => 'nha-khoa-rang-ngoi',
            'facility_name' => 'Nha Khoa Răng Ngời',
            'title' => 'Cấy Implant 1 trụ',
            'rating' => '4.7',
            'author_text' => 'Nữ, 39 tuổi',
            'review_date_text' => '14/05/2024',
            'price_text' => '14.000.000đ',
            'excerpt' => 'Cấy 1 trụ Implant, bác sĩ tư vấn kỹ và theo dõi sát. Ăn nhai ổn dần sau hồi phục.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 41,
            'comments_count' => 5,
            'shares_count' => 2,
            'service_text' => 'Implant',
            'method_text' => 'Cấy ghép Implant',
            'duration_text' => '4 tháng',
            'start_date_text' => '10/01/2024',
            'end_date_text' => '14/05/2024',
            'condition_text' => 'Mất 1 răng hàm',
            'story' => [
                'Mình mất 1 răng hàm nên ăn nhai bên đó yếu. Mình chọn cấy Implant để dùng lâu dài.',
                'Quá trình được chia giai đoạn rõ ràng, tái khám đúng lịch.',
                'Hiện ăn nhai ổn, cảm giác chắc chắn và tự nhiên.',
            ],
            'timeline' => [
                ['date' => '10/01/2024', 'label' => 'Khám tổng quát'],
                ['date' => '22/01/2024', 'label' => 'Cấy trụ'],
                ['date' => '10/03/2024', 'label' => 'Tái khám'],
                ['date' => '14/05/2024', 'label' => 'Gắn răng'],
            ],
            'display_order' => 7,
        ]),
        array_replace($base, [
            'slug' => 'tram-rang-sau-nhe',
            'facility_slug' => 'nha-khoa-rang-ngoi',
            'facility_name' => 'Nha Khoa Răng Ngời',
            'title' => 'Trám răng sâu nhẹ',
            'rating' => '4.6',
            'author_text' => 'Nam, 28 tuổi',
            'review_date_text' => '03/02/2024',
            'price_text' => '450.000đ',
            'excerpt' => 'Trám răng nhanh, không đau, màu trám tiệp. Bác sĩ làm kỹ và dặn dò cẩn thận.',
            'before_image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1629909615184-74f495363b67?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 19,
            'comments_count' => 1,
            'shares_count' => 0,
            'service_text' => 'Trám răng',
            'method_text' => 'Composite',
            'duration_text' => '45 phút',
            'start_date_text' => '03/02/2024',
            'end_date_text' => '03/02/2024',
            'condition_text' => 'Sâu răng nhẹ',
            'story' => [
                'Mình phát hiện sâu răng nhẹ nên đi trám sớm để khỏi đau.',
                'Bác sĩ làm sạch phần sâu và trám cẩn thận, màu trám tiệp nên nhìn rất tự nhiên.',
            ],
            'timeline' => [
                ['date' => '03/02/2024', 'label' => 'Khám & trám răng'],
            ],
            'display_order' => 8,
        ]),
        array_replace($base, [
            'slug' => 'tay-trang-rang-led-rang-ngoi',
            'facility_slug' => 'nha-khoa-rang-ngoi',
            'facility_name' => 'Nha Khoa Răng Ngời',
            'title' => 'Tẩy trắng răng bằng đèn LED',
            'rating' => '4.7',
            'author_text' => 'Nữ, 34 tuổi',
            'review_date_text' => '20/05/2024',
            'price_text' => '2.200.000đ',
            'excerpt' => 'Tẩy trắng răng LED, răng sáng lên rõ. Có hơi ê nhẹ nhưng hết nhanh.',
            'before_image_url' => 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 28,
            'comments_count' => 2,
            'shares_count' => 1,
            'service_text' => 'Tẩy trắng răng',
            'method_text' => 'LED Whitening',
            'duration_text' => '1 buổi',
            'start_date_text' => '20/05/2024',
            'end_date_text' => '20/05/2024',
            'condition_text' => 'Răng xỉn màu',
            'story' => [
                'Mình tẩy trắng răng vì màu răng hơi vàng. Quy trình làm nhanh và có hướng dẫn kiêng màu.',
                'Sau 1 buổi răng sáng hơn rõ, nhìn tự nhiên.',
            ],
            'timeline' => [
                ['date' => '20/05/2024', 'label' => 'Khám & tẩy trắng'],
            ],
            'display_order' => 9,
        ]),
        array_replace($base, [
            'slug' => 'nieng-mac-cai-rang-ngoi-15-thang',
            'facility_slug' => 'nha-khoa-rang-ngoi',
            'facility_name' => 'Nha Khoa Răng Ngời',
            'title' => 'Niềng mắc cài sau 15 tháng',
            'rating' => '4.8',
            'author_text' => 'Nữ, 20 tuổi',
            'review_date_text' => '01/06/2024',
            'price_text' => '30.000.000đ',
            'excerpt' => 'Sau 15 tháng niềng mắc cài, răng đều và khớp cắn cải thiện rõ. Tái khám đều và tư vấn kỹ.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 62,
            'comments_count' => 9,
            'shares_count' => 4,
            'service_text' => 'Niềng răng',
            'method_text' => 'Mắc cài kim loại',
            'duration_text' => '15 tháng',
            'start_date_text' => '01/03/2023',
            'end_date_text' => '01/06/2024',
            'condition_text' => 'Khớp cắn lệch nhẹ',
            'story' => [
                'Mình niềng vì răng lệch và khớp cắn chưa chuẩn. Lúc đầu hơi khó chịu nhưng sau quen.',
                'Bác sĩ theo dõi sát nên mỗi lần điều chỉnh đều nhẹ nhàng.',
                'Kết quả sau 15 tháng rất rõ, răng đều và cười tự tin hơn.',
            ],
            'timeline' => [
                ['date' => '01/03/2023', 'label' => 'Bắt đầu niềng'],
                ['date' => '01/09/2023', 'label' => 'Điều chỉnh giai đoạn 1'],
                ['date' => '01/03/2024', 'label' => 'Điều chỉnh giai đoạn 2'],
                ['date' => '01/06/2024', 'label' => 'Kết thúc 15 tháng'],
            ],
            'display_order' => 10,
        ]),
        array_replace($base, [
            'slug' => 'rang-su-zirconia-tu-nhien',
            'facility_slug' => 'smile-dental-clinic',
            'facility_name' => 'Smile Dental Clinic',
            'title' => 'Răng sứ Zirconia tự nhiên',
            'rating' => '4.7',
            'author_text' => 'Nữ, 32 tuổi',
            'review_date_text' => '26/03/2024',
            'price_text' => '28.000.000đ',
            'excerpt' => 'Làm 16 răng sứ Zirconia, màu sắc tự nhiên, không bị đục. Bác sĩ tư vấn kỹ, làm nhẹ nhàng, không đau và form răng nhìn rất hài hòa khi cười.',
            'before_image_url' => 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 76,
            'comments_count' => 15,
            'shares_count' => 6,
            'service_text' => 'Răng sứ',
            'method_text' => 'Zirconia',
            'duration_text' => '10 ngày',
            'start_date_text' => '15/03/2024',
            'end_date_text' => '26/03/2024',
            'condition_text' => 'Răng xỉn màu, men yếu',
            'story' => [
                'Mình muốn làm răng sứ nhưng vẫn ưu tiên vẻ tự nhiên nên đã chọn dòng Zirconia. Sau khi tư vấn kỹ, mình quyết định làm 16 răng.',
                'Trong suốt quá trình làm, bác sĩ giải thích rất rõ từng bước, điều chỉnh form răng nhiều lần để đảm bảo nụ cười phù hợp khuôn mặt.',
                'Kết quả cuối cùng khá đẹp, màu răng sáng vừa phải và khi cười không bị giả.',
            ],
            'timeline' => [
                ['date' => '15/03/2024', 'label' => 'Khám và lên form'],
                ['date' => '18/03/2024', 'label' => 'Mài răng'],
                ['date' => '21/03/2024', 'label' => 'Đeo răng tạm'],
                ['date' => '24/03/2024', 'label' => 'Thử form'],
                ['date' => '26/03/2024', 'label' => 'Gắn hoàn thiện'],
            ],
            'display_order' => 11,
        ]),
        array_replace($base, [
            'slug' => 'dan-su-veneer-10-rang',
            'facility_slug' => 'smile-dental-clinic',
            'facility_name' => 'Smile Dental Clinic',
            'title' => 'Dán sứ Veneer 10 răng',
            'rating' => '4.8',
            'author_text' => 'Nữ, 29 tuổi',
            'review_date_text' => '12/05/2024',
            'price_text' => '22.000.000đ',
            'excerpt' => 'Dán sứ Veneer nhẹ nhàng, ít mài. Màu răng đẹp và nhìn rất tự nhiên.',
            'before_image_url' => 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 58,
            'comments_count' => 7,
            'shares_count' => 3,
            'service_text' => 'Dán sứ',
            'method_text' => 'Veneer',
            'duration_text' => '5 ngày',
            'start_date_text' => '07/05/2024',
            'end_date_text' => '12/05/2024',
            'condition_text' => 'Răng hơi thưa, xỉn màu nhẹ',
            'story' => [
                'Mình chọn Veneer vì muốn hạn chế mài răng. Bác sĩ tư vấn kỹ về form răng và màu sắc.',
                'Trong quá trình làm có thử form trước, chỉnh lại cho phù hợp với khuôn mặt.',
                'Sau hoàn thiện, răng sáng và cười tự tin hơn.',
            ],
            'timeline' => [
                ['date' => '07/05/2024', 'label' => 'Khám & tư vấn'],
                ['date' => '09/05/2024', 'label' => 'Lấy dấu & làm tạm'],
                ['date' => '12/05/2024', 'label' => 'Gắn Veneer'],
            ],
            'display_order' => 12,
        ]),
        array_replace($base, [
            'slug' => 'cat-nuou-laser-1-lan',
            'facility_slug' => 'smile-dental-clinic',
            'facility_name' => 'Smile Dental Clinic',
            'title' => 'Cắt nướu laser 1 lần',
            'rating' => '4.7',
            'author_text' => 'Nam, 26 tuổi',
            'review_date_text' => '28/04/2024',
            'price_text' => '3.500.000đ',
            'excerpt' => 'Cắt nướu laser nhanh, ít chảy máu. Sau hồi phục, cười đẹp hơn.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 33,
            'comments_count' => 4,
            'shares_count' => 1,
            'service_text' => 'Nha chu',
            'method_text' => 'Laser',
            'duration_text' => '1 buổi',
            'start_date_text' => '28/04/2024',
            'end_date_text' => '28/04/2024',
            'condition_text' => 'Cười hở lợi nhẹ',
            'story' => [
                'Mình bị cười hở lợi nhẹ nên muốn xử lý cho tự tin hơn.',
                'Bác sĩ làm nhanh bằng laser, cảm giác nhẹ nhàng.',
                'Sau vài ngày hồi phục ổn, nướu gọn và cười tự nhiên hơn.',
            ],
            'timeline' => [
                ['date' => '28/04/2024', 'label' => 'Tư vấn & thực hiện'],
                ['date' => '05/05/2024', 'label' => 'Tái khám'],
            ],
            'display_order' => 13,
        ]),
        array_replace($base, [
            'slug' => 'nieng-trong-suot-9-thang',
            'facility_slug' => 'smile-dental-clinic',
            'facility_name' => 'Smile Dental Clinic',
            'title' => 'Niềng trong suốt sau 9 tháng',
            'rating' => '4.8',
            'author_text' => 'Nữ, 23 tuổi',
            'review_date_text' => '30/05/2024',
            'price_text' => '40.000.000đ',
            'excerpt' => 'Niềng trong suốt thẩm mỹ, dễ tháo lắp. Sau 9 tháng răng đều rõ.',
            'before_image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 67,
            'comments_count' => 9,
            'shares_count' => 4,
            'service_text' => 'Niềng răng',
            'method_text' => 'Aligner',
            'duration_text' => '9 tháng',
            'start_date_text' => '30/08/2023',
            'end_date_text' => '30/05/2024',
            'condition_text' => 'Răng thưa nhẹ',
            'story' => [
                'Mình chọn niềng trong suốt vì công việc giao tiếp nhiều. Khay đeo khá kín đáo.',
                'Bác sĩ lên phác đồ rõ và hướng dẫn cách đeo, vệ sinh kỹ.',
                'Sau 9 tháng răng đều hơn nhiều và nhìn tự nhiên.',
            ],
            'timeline' => [
                ['date' => '30/08/2023', 'label' => 'Bắt đầu niềng'],
                ['date' => '30/11/2023', 'label' => 'Theo dõi giai đoạn 1'],
                ['date' => '30/03/2024', 'label' => 'Theo dõi giai đoạn 2'],
                ['date' => '30/05/2024', 'label' => 'Kết thúc 9 tháng'],
            ],
            'display_order' => 14,
        ]),
        array_replace($base, [
            'slug' => 'lay-cao-rang-dinh-ky',
            'facility_slug' => 'smile-dental-clinic',
            'facility_name' => 'Smile Dental Clinic',
            'title' => 'Lấy cao răng định kỳ',
            'rating' => '4.6',
            'author_text' => 'Nam, 33 tuổi',
            'review_date_text' => '06/03/2024',
            'price_text' => '300.000đ',
            'excerpt' => 'Lấy cao răng sạch, không ê nhiều. Vệ sinh xong răng sáng và nhẹ miệng.',
            'before_image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1629909615184-74f495363b67?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 14,
            'comments_count' => 0,
            'shares_count' => 0,
            'service_text' => 'Vệ sinh răng',
            'method_text' => 'Siêu âm',
            'duration_text' => '30 phút',
            'start_date_text' => '06/03/2024',
            'end_date_text' => '06/03/2024',
            'condition_text' => 'Cao răng nhiều',
            'story' => [
                'Mình đi lấy cao răng định kỳ. Nhân viên làm nhẹ nhàng, sạch sẽ.',
                'Sau làm thấy răng sạch và dễ chịu hơn nhiều.',
            ],
            'timeline' => [
                ['date' => '06/03/2024', 'label' => 'Lấy cao răng'],
            ],
            'display_order' => 15,
        ]),
        array_replace($base, [
            'slug' => 'tri-nam-sau-3-thang',
            'facility_slug' => 'happy-dental',
            'facility_name' => 'Happy Dental',
            'title' => 'Trị nám sau 3 tháng',
            'rating' => '4.6',
            'author_text' => 'Nữ, 38 tuổi',
            'review_date_text' => '10/02/2024',
            'price_text' => '15.000.000đ',
            'excerpt' => 'Điều trị nám tại Gangwhoo được 3 tháng, da cải thiện rõ rệt, mờ nám, sáng đều màu hơn. Mình khá hài lòng vì có theo dõi và điều chỉnh theo từng giai đoạn.',
            'before_image_url' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 64,
            'comments_count' => 11,
            'shares_count' => 5,
            'service_text' => 'Da liễu',
            'method_text' => 'Laser trị nám',
            'duration_text' => '3 tháng',
            'start_date_text' => '12/11/2023',
            'end_date_text' => '10/02/2024',
            'condition_text' => 'Nám mảng, da không đều màu',
            'story' => [
                'Mình điều trị nám trong 3 tháng với mong muốn da đều màu hơn và tự tin hơn khi không trang điểm.',
                'Sau từng buổi, bác sĩ có theo dõi sát, dặn kỹ cách chăm da và chống nắng nên kết quả cải thiện khá ổn.',
                'Hiện da sáng hơn, mảng nám mờ đi rõ rệt và tổng thể gương mặt nhìn tươi hơn nhiều.',
            ],
            'timeline' => [
                ['date' => '12/11/2023', 'label' => 'Soi da và tư vấn'],
                ['date' => '20/11/2023', 'label' => 'Buổi laser 1'],
                ['date' => '18/12/2023', 'label' => 'Buổi laser 2'],
                ['date' => '15/01/2024', 'label' => 'Buổi laser 3'],
                ['date' => '10/02/2024', 'label' => 'Đánh giá kết quả'],
            ],
            'display_order' => 16,
        ]),
        array_replace($base, [
            'slug' => 'tay-trang-rang-happy-1-buoi',
            'facility_slug' => 'happy-dental',
            'facility_name' => 'Happy Dental',
            'title' => 'Tẩy trắng răng tại Happy Dental',
            'rating' => '4.7',
            'author_text' => 'Nữ, 25 tuổi',
            'review_date_text' => '22/05/2024',
            'price_text' => '2.000.000đ',
            'excerpt' => 'Tẩy trắng răng xong nhìn sáng hơn và tự nhiên. Quy trình nhanh, tư vấn kỹ.',
            'before_image_url' => 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 37,
            'comments_count' => 4,
            'shares_count' => 1,
            'service_text' => 'Tẩy trắng răng',
            'method_text' => 'LED Whitening',
            'duration_text' => '1 buổi',
            'start_date_text' => '22/05/2024',
            'end_date_text' => '22/05/2024',
            'condition_text' => 'Răng hơi vàng',
            'story' => [
                'Mình tẩy trắng răng để tự tin hơn khi cười. Bác sĩ kiểm tra trước khi làm và hướng dẫn kiêng màu.',
                'Sau buổi làm răng sáng lên thấy rõ, ê nhẹ 1-2 ngày rồi hết.',
            ],
            'timeline' => [
                ['date' => '22/05/2024', 'label' => 'Tư vấn & tẩy trắng'],
            ],
            'display_order' => 17,
        ]),
        array_replace($base, [
            'slug' => 'tram-rang-tham-my-happy',
            'facility_slug' => 'happy-dental',
            'facility_name' => 'Happy Dental',
            'title' => 'Trám răng thẩm mỹ',
            'rating' => '4.6',
            'author_text' => 'Nam, 30 tuổi',
            'review_date_text' => '18/03/2024',
            'price_text' => '600.000đ',
            'excerpt' => 'Trám răng thẩm mỹ màu tiệp. Làm xong nhìn gần như không thấy vết trám.',
            'before_image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1629909615184-74f495363b67?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 21,
            'comments_count' => 1,
            'shares_count' => 0,
            'service_text' => 'Trám răng',
            'method_text' => 'Composite',
            'duration_text' => '45 phút',
            'start_date_text' => '18/03/2024',
            'end_date_text' => '18/03/2024',
            'condition_text' => 'Mẻ răng nhẹ',
            'story' => [
                'Mình bị mẻ răng nhẹ nên muốn trám lại cho đẹp.',
                'Bác sĩ chọn màu trám tiệp và làm khá nhanh, không đau.',
            ],
            'timeline' => [
                ['date' => '18/03/2024', 'label' => 'Trám răng'],
            ],
            'display_order' => 18,
        ]),
        array_replace($base, [
            'slug' => 'nho-rang-khon-happy',
            'facility_slug' => 'happy-dental',
            'facility_name' => 'Happy Dental',
            'title' => 'Nhổ răng khôn tại Happy Dental',
            'rating' => '4.7',
            'author_text' => 'Nữ, 28 tuổi',
            'review_date_text' => '12/04/2024',
            'price_text' => '1.100.000đ',
            'excerpt' => 'Nhổ răng khôn nhanh và bác sĩ nhẹ tay. Sau nhổ được hướng dẫn chăm sóc kỹ.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 29,
            'comments_count' => 3,
            'shares_count' => 1,
            'service_text' => 'Nhổ răng',
            'method_text' => 'Tiểu phẫu',
            'duration_text' => '30 phút',
            'start_date_text' => '12/04/2024',
            'end_date_text' => '12/04/2024',
            'condition_text' => 'Răng khôn mọc lệch',
            'story' => [
                'Mình nhổ răng khôn vì hay đau nhức. Bác sĩ tư vấn rõ và làm nhanh.',
                'Sau nhổ sưng nhẹ vài ngày rồi ổn, tái khám đúng lịch.',
            ],
            'timeline' => [
                ['date' => '12/04/2024', 'label' => 'Nhổ răng'],
                ['date' => '19/04/2024', 'label' => 'Tái khám'],
            ],
            'display_order' => 19,
        ]),
        array_replace($base, [
            'slug' => 'nieng-mac-cai-happy-10-thang',
            'facility_slug' => 'happy-dental',
            'facility_name' => 'Happy Dental',
            'title' => 'Niềng mắc cài sau 10 tháng',
            'rating' => '4.8',
            'author_text' => 'Nam, 19 tuổi',
            'review_date_text' => '10/06/2024',
            'price_text' => '26.000.000đ',
            'excerpt' => 'Sau 10 tháng niềng mắc cài, răng đều rõ. Nhân viên nhiệt tình và lịch hẹn rõ ràng.',
            'before_image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 45,
            'comments_count' => 6,
            'shares_count' => 2,
            'service_text' => 'Niềng răng',
            'method_text' => 'Mắc cài kim loại',
            'duration_text' => '10 tháng',
            'start_date_text' => '10/08/2023',
            'end_date_text' => '10/06/2024',
            'condition_text' => 'Răng chen chúc',
            'story' => [
                'Mình niềng mắc cài vì răng chen chúc. Mỗi lần tái khám được tư vấn kỹ nên yên tâm.',
                'Sau 10 tháng răng đều hơn rõ và cảm giác ăn nhai dễ hơn.',
            ],
            'timeline' => [
                ['date' => '10/08/2023', 'label' => 'Bắt đầu niềng'],
                ['date' => '10/12/2023', 'label' => 'Điều chỉnh giai đoạn 1'],
                ['date' => '10/04/2024', 'label' => 'Điều chỉnh giai đoạn 2'],
                ['date' => '10/06/2024', 'label' => 'Mốc 10 tháng'],
            ],
            'display_order' => 20,
        ]),
        array_replace($base, [
            'slug' => 'implant-dr-anh-1-tru',
            'facility_slug' => 'nha-khoa-dr-anh',
            'facility_name' => 'Nha Khoa Dr.Anh',
            'title' => 'Trồng Implant 1 trụ sau 3 tháng',
            'rating' => '4.9',
            'author_text' => 'Nam, 52 tuổi',
            'review_date_text' => '05/07/2024',
            'price_text' => '18.000.000đ',
            'excerpt' => 'Implant tại Dr.Anh bác sĩ tư vấn rất kỹ, gắn trụ không đau. Hiện ăn nhai rất ổn, cảm giác như răng thật.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 58,
            'comments_count' => 8,
            'shares_count' => 3,
            'service_text' => 'Cấy ghép Implant',
            'method_text' => '1 trụ Straumann',
            'duration_text' => '3 tháng',
            'start_date_text' => '02/04/2024',
            'end_date_text' => '05/07/2024',
            'condition_text' => 'Mất răng hàm dưới',
            'story' => [
                'Mình mất răng hàm dưới lâu ngày, ăn nhai khó khăn. Dr.Anh tư vấn gắn 1 trụ Implant Straumann.',
                'Quá trình gắn nhẹ nhàng, không đau. Sau 3 tháng gắn mão sứ, ăn nhai hoàn toàn bình thường.',
            ],
            'timeline' => [
                ['date' => '02/04/2024', 'label' => 'Khám & chụp CT'],
                ['date' => '12/04/2024', 'label' => 'Gắn trụ Implant'],
                ['date' => '12/06/2024', 'label' => 'Lấy dấu và gắn mão'],
                ['date' => '05/07/2024', 'label' => 'Tái khám cuối'],
            ],
            'display_order' => 21,
        ]),
        array_replace($base, [
            'slug' => 'rang-su-dr-anh-16-rang',
            'facility_slug' => 'nha-khoa-dr-anh',
            'facility_name' => 'Nha Khoa Dr.Anh',
            'title' => 'Bọc răng sứ 16 răng sau 2 tuần',
            'rating' => '4.8',
            'author_text' => 'Nữ, 36 tuổi',
            'review_date_text' => '15/06/2024',
            'price_text' => '68.000.000đ',
            'excerpt' => 'Răng ố vàng lâu năm, sau khi làm răng sứ 16 răng mình tự tin hơn rất nhiều. Màu sắc rất tự nhiên.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 72,
            'comments_count' => 11,
            'shares_count' => 5,
            'service_text' => 'Bọc răng sứ',
            'method_text' => 'Sứ Zirconia 16 răng',
            'duration_text' => '2 tuần',
            'start_date_text' => '02/06/2024',
            'end_date_text' => '15/06/2024',
            'condition_text' => 'Răng ố vàng, mòn bờ cắn',
            'story' => [
                'Răng mình bị ố vàng mòn bờ cắn do uống cà phê nhiều năm, muốn cải thiện thẩm mỹ.',
                'Dr.Anh làm sứ Zirconia 2 tuần là xong. Màu sứ rất tự nhiên, không bị đen viền nướu.',
            ],
            'timeline' => [
                ['date' => '02/06/2024', 'label' => 'Mài răng & lấy dấu tạm'],
                ['date' => '08/06/2024', 'label' => 'Thử khung sứ'],
                ['date' => '15/06/2024', 'label' => 'Dán sứ hoàn thiện'],
            ],
            'display_order' => 22,
        ]),
        array_replace($base, [
            'slug' => 'nieng-trong-suot-dr-anh',
            'facility_slug' => 'nha-khoa-dr-anh',
            'facility_name' => 'Nha Khoa Dr.Anh',
            'title' => 'Niềng trong suốt sau 14 tháng',
            'rating' => '4.8',
            'author_text' => 'Nữ, 28 tuổi',
            'review_date_text' => '22/05/2024',
            'price_text' => '52.000.000đ',
            'excerpt' => 'Niềng trong suốt 14 tháng, răng đều và thẳng hàng. Lực kéo nhẹ, không đau nhức nhiều.',
            'before_image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 64,
            'comments_count' => 9,
            'shares_count' => 4,
            'service_text' => 'Niềng răng trong suốt',
            'method_text' => 'Clear Aligner 48 khay',
            'duration_text' => '14 tháng',
            'start_date_text' => '10/03/2023',
            'end_date_text' => '22/05/2024',
            'condition_text' => 'Khoảng hở răng cửa, răng lệch nhẹ',
            'story' => [
                'Vì công việc giao tiếp nhiều, mình chọn niềng trong suốt để tự tin.',
                '14 tháng răng đều như ý, khớp cắn cải thiện rõ, không ảnh hưởng công việc.',
            ],
            'timeline' => [
                ['date' => '10/03/2023', 'label' => 'Quét 3D & lập kế hoạch'],
                ['date' => '22/04/2023', 'label' => 'Nhận khay đầu tiên'],
                ['date' => '10/10/2023', 'label' => 'Gắn buttons điều chỉnh'],
                ['date' => '22/05/2024', 'label' => 'Hoàn tất niềng + giữ hàm'],
            ],
            'display_order' => 23,
        ]),
        array_replace($base, [
            'slug' => 'nho-rang-khon-dr-anh',
            'facility_slug' => 'nha-khoa-dr-anh',
            'facility_name' => 'Nha Khoa Dr.Anh',
            'title' => 'Nhổ 4 răng khôn một lần',
            'rating' => '4.7',
            'author_text' => 'Nam, 26 tuổi',
            'review_date_text' => '30/06/2024',
            'price_text' => '3.200.000đ',
            'excerpt' => 'Nhổ 4 răng khôn cùng lúc, tiểu phẫu nhanh 45 phút. Hướng dẫn chăm sóc rất chi tiết.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 38,
            'comments_count' => 5,
            'shares_count' => 2,
            'service_text' => 'Nhổ răng khôn',
            'method_text' => 'Tiểu phẫu gây mê',
            'duration_text' => '45 phút',
            'start_date_text' => '30/06/2024',
            'end_date_text' => '07/07/2024',
            'condition_text' => '4 răng khôn mọc ngầm sâu',
            'story' => [
                'Răng khôn mọc ngầm hay sưng đau, mình nhổ hết 4 cái cùng lúc cho xong.',
                'Bác sĩ nhẹ tay, chỉ hơi sưng 2-3 ngày rồi ổn. Cầm máu tốt lắm.',
            ],
            'timeline' => [
                ['date' => '30/06/2024', 'label' => 'Chụp CT & nhổ 4 răng'],
                ['date' => '02/07/2024', 'label' => 'Cắt chỉ vết thương'],
                ['date' => '07/07/2024', 'label' => 'Tái khám phục hồi'],
            ],
            'display_order' => 24,
        ]),
        array_replace($base, [
            'slug' => 'tay-trang-dr-anh',
            'facility_slug' => 'nha-khoa-dr-anh',
            'facility_name' => 'Nha Khoa Dr.Anh',
            'title' => 'Tẩy trắng răng Laser 1 buổi',
            'rating' => '4.7',
            'author_text' => 'Nữ, 31 tuổi',
            'review_date_text' => '18/07/2024',
            'price_text' => '2.800.000đ',
            'excerpt' => 'Tẩy trắng Laser trắng rõ 7-8 tone, không quá nhạy cảm như mình lo. Kết quả kéo dài tốt.',
            'before_image_url' => 'https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 41,
            'comments_count' => 6,
            'shares_count' => 2,
            'service_text' => 'Tẩy trắng răng',
            'method_text' => 'Laser Whitening',
            'duration_text' => '60 phút',
            'start_date_text' => '18/07/2024',
            'end_date_text' => '18/07/2024',
            'condition_text' => 'Răng ố vàng do trà/cà phê',
            'story' => [
                'Do uống nhiều cà phê mỗi ngày, răng mình ố vàng rõ. Tẩy trắng tại Dr.Anh trắng ngay 7-8 tone.',
                'Có hơi nhạy 1 ngày đầu rồi ổn. 2 tháng nay vẫn giữ được màu trắng đẹp.',
            ],
            'timeline' => [
                ['date' => '18/07/2024', 'label' => 'Tẩy trắng Laser + Fluor'],
            ],
            'display_order' => 25,
        ]),
        array_replace($base, [
            'slug' => 'all-on-4-dental-plus',
            'facility_slug' => 'dental-plus-clinic',
            'facility_name' => 'Dental Plus Clinic',
            'title' => 'Phục hình toàn hàm All-on-4',
            'rating' => '4.9',
            'author_text' => 'Nam, 64 tuổi',
            'review_date_text' => '08/07/2024',
            'price_text' => '185.000.000đ',
            'excerpt' => 'Mất răng toàn hàm nhiều năm, All-on-4 chỉ 1 ngày là có răng cố định. Ăn uống thoải mái ngay.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1629909615184-74f495363b67?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 96,
            'comments_count' => 18,
            'shares_count' => 8,
            'service_text' => 'Implant toàn hàm',
            'method_text' => 'All-on-4 2 hàm',
            'duration_text' => '3 tháng',
            'start_date_text' => '02/04/2024',
            'end_date_text' => '08/07/2024',
            'condition_text' => 'Mất răng toàn hàm, tiêu xương hàm',
            'story' => [
                'Hàm giả loại thường gây khó chịu lâu năm, mình nghe đến All-on-4 chọn Dental Plus.',
                'Chỉ 1 ngày đã có răng cố định ăn được cơm ngay. Sau 3 tháng hoàn thiện thật tuyệt vời.',
            ],
            'timeline' => [
                ['date' => '02/04/2024', 'label' => 'CT & lập kế hoạch'],
                ['date' => '12/04/2024', 'label' => 'Cấy 8 trụ + răng tạm'],
                ['date' => '08/07/2024', 'label' => 'Lắp răng sứ hoàn thiện'],
            ],
            'display_order' => 26,
        ]),
        array_replace($base, [
            'slug' => 'phuc-hinh-6-rang-dental-plus',
            'facility_slug' => 'dental-plus-clinic',
            'facility_name' => 'Dental Plus Clinic',
            'title' => 'Phục hình 6 răng cửa sau tai nạn',
            'rating' => '4.8',
            'author_text' => 'Nam, 35 tuổi',
            'review_date_text' => '12/06/2024',
            'price_text' => '42.000.000đ',
            'excerpt' => 'Gãy 6 răng cửa do tai nạn xe. Dental Plus phục hồi Implant + sứ, thẩm mỹ như răng mới.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 82,
            'comments_count' => 14,
            'shares_count' => 6,
            'service_text' => 'Phục hình thẩm mỹ',
            'method_text' => 'Implant + Sứ Zirconia',
            'duration_text' => '4 tháng',
            'start_date_text' => '05/02/2024',
            'end_date_text' => '12/06/2024',
            'condition_text' => 'Gãy 6 răng cửa do tai nạn',
            'story' => [
                'Tai nạn xe làm gãy 6 răng cửa, mình rất xấu hổ khi cười. Dental Plus tư vấn Implant + sứ.',
                '4 tháng phục hồi hoàn toàn, răng mới trắng đều, khớp cắn chuẩn. Cảm ơn bác sĩ rất nhiều!',
            ],
            'timeline' => [
                ['date' => '05/02/2024', 'label' => 'CT & nhổ gốc'],
                ['date' => '22/02/2024', 'label' => 'Cấy 3 trụ Implant'],
                ['date' => '10/05/2024', 'label' => 'Lấy dấu sứ 6 răng'],
                ['date' => '12/06/2024', 'label' => 'Dán hoàn thiện & khớp cắn'],
            ],
            'display_order' => 27,
        ]),
        array_replace($base, [
            'slug' => 'nieng-invisalign-dental-plus',
            'facility_slug' => 'dental-plus-clinic',
            'facility_name' => 'Dental Plus Clinic',
            'title' => 'Niềng Invisalign sau 16 tháng',
            'rating' => '4.7',
            'author_text' => 'Nữ, 29 tuổi',
            'review_date_text' => '28/06/2024',
            'price_text' => '88.000.000đ',
            'excerpt' => 'Niềng Invisalign tại Dental Plus 16 tháng, răng đều như ý. Kế hoạch rõ ràng từng bước.',
            'before_image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 77,
            'comments_count' => 12,
            'shares_count' => 5,
            'service_text' => 'Niềng răng',
            'method_text' => 'Invisalign Full',
            'duration_text' => '16 tháng',
            'start_date_text' => '20/02/2023',
            'end_date_text' => '28/06/2024',
            'condition_text' => 'Răng hô, chen chúc',
            'story' => [
                'Răng hô lâu năm muốn niềng thẩm mỹ, chọn Invisalign vì công việc cần giao tiếp.',
                '16 tháng kết quả đẹp hơn mong đợi. Răng đều, hô lui rõ, nụ cười rất tự nhiên.',
            ],
            'timeline' => [
                ['date' => '20/02/2023', 'label' => 'iTero 3D & ClinCheck'],
                ['date' => '15/03/2023', 'label' => 'Nhận khay 1-20 + attachments'],
                ['date' => '10/11/2023', 'label' => 'Refine giai đoạn 2'],
                ['date' => '28/06/2024', 'label' => 'Hoàn tất + giữ hàm Vivera'],
            ],
            'display_order' => 28,
        ]),
        array_replace($base, [
            'slug' => 'rang-su-emax-dental-plus',
            'facility_slug' => 'dental-plus-clinic',
            'facility_name' => 'Dental Plus Clinic',
            'title' => 'Dán sứ Veneer Emax 10 răng',
            'rating' => '4.8',
            'author_text' => 'Nữ, 33 tuổi',
            'review_date_text' => '05/07/2024',
            'price_text' => '45.000.000đ',
            'excerpt' => 'Dán sứ Veneer Emax thẩm mỹ cao, không phải mài răng nhiều. Tone trắng sáng vừa phải.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 69,
            'comments_count' => 10,
            'shares_count' => 4,
            'service_text' => 'Dán sứ Veneer',
            'method_text' => 'Emax Press 10 răng',
            'duration_text' => '10 ngày',
            'start_date_text' => '22/06/2024',
            'end_date_text' => '05/07/2024',
            'condition_text' => 'Răng ngắn, màu xỉu, khoảng cách nhẹ',
            'story' => [
                'Răng mình ngắn và xỉu màu, muốn nụ cười tươi sáng hơn mà không muốn mài nhiều.',
                'Dán sứ Veneer Emax chỉ 10 ngày kết quả rất ưng ý, ăn uống không bị gì.',
            ],
            'timeline' => [
                ['date' => '22/06/2024', 'label' => 'Mockup & lên màu'],
                ['date' => '28/06/2024', 'label' => 'Mài nhẹ & dán tạm'],
                ['date' => '05/07/2024', 'label' => 'Dán sứ hoàn thiện'],
            ],
            'display_order' => 29,
        ]),
        array_replace($base, [
            'slug' => 'tram-rang-sau-dental-plus',
            'facility_slug' => 'dental-plus-clinic',
            'facility_name' => 'Dental Plus Clinic',
            'title' => 'Trám răng sâu sứ thẩm mỹ',
            'rating' => '4.6',
            'author_text' => 'Nữ, 24 tuổi',
            'review_date_text' => '10/07/2024',
            'price_text' => '950.000đ',
            'excerpt' => 'Răng sâu hàm trên, trám sứ màu tiệp hoàn toàn với răng thật. Không đau và nhanh.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 22,
            'comments_count' => 3,
            'shares_count' => 1,
            'service_text' => 'Trám răng thẩm mỹ',
            'method_text' => 'Composite 4 lớp',
            'duration_text' => '40 phút',
            'start_date_text' => '10/07/2024',
            'end_date_text' => '10/07/2024',
            'condition_text' => 'Răng sâu lớp men hàm trên',
            'story' => [
                'Răng sâu nhẹ không đau nhưng cần xử lý kịp thời.',
                'Bác sĩ trám composite 4 lớp màu rất chuẩn, nhìn không biết răng đã được trám.',
            ],
            'timeline' => [
                ['date' => '10/07/2024', 'label' => 'Làm sạch sâu & trám'],
            ],
            'display_order' => 30,
        ]),
        array_replace($base, [
            'slug' => 'kham-tong-quat-bao-son',
            'facility_slug' => 'nha-khoa-bao-son',
            'facility_name' => 'Nha Khoa Bảo Sơn',
            'title' => 'Khám tổng quát gia đình 4 người',
            'rating' => '4.6',
            'author_text' => 'Nữ, 38 tuổi',
            'review_date_text' => '20/06/2024',
            'price_text' => '1.800.000đ',
            'excerpt' => 'Cả nhà đi khám tổng quát, bác sĩ rất nhẹ nhàng với trẻ em. Chi phí rõ ràng, nhiều ưu đãi gia đình.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1609207825181-52d3214556a3?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 49,
            'comments_count' => 7,
            'shares_count' => 3,
            'service_text' => 'Khám tổng quát',
            'method_text' => 'Gói gia đình',
            'duration_text' => '2 giờ',
            'start_date_text' => '20/06/2024',
            'end_date_text' => '20/06/2024',
            'condition_text' => 'Khám định kỳ',
            'story' => [
                'Mình dẫn cả nhà 4 người đi khám định kỳ tại Bảo Sơn.',
                'Bác sĩ chơi với con rất chu đáo, con mình không còn sợ nha khoa nữa. Giá cả hợp lý.',
            ],
            'timeline' => [
                ['date' => '20/06/2024', 'label' => 'Khám + cạo vôi + fluor 4 người'],
            ],
            'display_order' => 31,
        ]),
        array_replace($base, [
            'slug' => 'nho-rang-tre-em-bao-son',
            'facility_slug' => 'nha-khoa-bao-son',
            'facility_name' => 'Nha Khoa Bảo Sơn',
            'title' => 'Nhổ răng sữa cho bé 7 tuổi',
            'rating' => '4.5',
            'author_text' => 'Nữ, 34 tuổi',
            'review_date_text' => '02/07/2024',
            'price_text' => '280.000đ',
            'excerpt' => 'Con gái 7 tuổi răng sữa lung lay nhưng không rụng. Bác sĩ nhổ rất nhanh, con không biết đau.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 34,
            'comments_count' => 5,
            'shares_count' => 2,
            'service_text' => 'Nhổ răng sữa',
            'method_text' => 'Không gây mê',
            'duration_text' => '5 phút',
            'start_date_text' => '02/07/2024',
            'end_date_text' => '02/07/2024',
            'condition_text' => 'Răng sữa lung lay, răng vĩnh cửu mọc sau',
            'story' => [
                'Con bị răng sữa không rụng mà răng vĩnh cửu đã nhú lên.',
                'Bác sĩ kể chuyện làm con hết sợ, nhổ xong con cười tươi ăn kem liền.',
            ],
            'timeline' => [
                ['date' => '02/07/2024', 'label' => 'Nhổ răng sữa + kẹo cao su biếu'],
            ],
            'display_order' => 32,
        ]),
        array_replace($base, [
            'slug' => 'cao-rang-bao-son',
            'facility_slug' => 'nha-khoa-bao-son',
            'facility_name' => 'Nha Khoa Bảo Sơn',
            'title' => 'Lấy cao răng + tẩy nhẹ tại nhà',
            'rating' => '4.5',
            'author_text' => 'Nam, 42 tuổi',
            'review_date_text' => '15/06/2024',
            'price_text' => '450.000đ',
            'excerpt' => 'Lấy cao răng toàn hàm, nướu mình chảy máu ít sau đó. Hướng dẫn chải răng đúng cách rất chu đáo.',
            'before_image_url' => 'https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 28,
            'comments_count' => 4,
            'shares_count' => 1,
            'service_text' => 'Lấy cao răng',
            'method_text' => 'Siêu âm + polishing',
            'duration_text' => '30 phút',
            'start_date_text' => '15/06/2024',
            'end_date_text' => '15/06/2024',
            'condition_text' => 'Cao răng + mảng bám',
            'story' => [
                'Mình chải răng kỹ nhưng vẫn bị cao răng ở nướu dưới.',
                'Lấy cao + tẩy trắng nhẹ 30 phút, hơi nhạy nướu 1 ngày rồi ổn. Thở mát mẻ lắm.',
            ],
            'timeline' => [
                ['date' => '15/06/2024', 'label' => 'Siêu âm + polishing + fluor'],
            ],
            'display_order' => 33,
        ]),
        array_replace($base, [
            'slug' => 'tram-rang-be-bao-son',
            'facility_slug' => 'nha-khoa-bao-son',
            'facility_name' => 'Nha Khoa Bảo Sơn',
            'title' => 'Trám răng sâu cho bé 9 tuổi',
            'rating' => '4.4',
            'author_text' => 'Nam, 40 tuổi',
            'review_date_text' => '08/07/2024',
            'price_text' => '500.000đ',
            'excerpt' => 'Con trai bị sâu răng hàm. Bác sĩ trám màu tiệp, giải thích dễ hiểu để bé hợp tác.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 26,
            'comments_count' => 3,
            'shares_count' => 1,
            'service_text' => 'Trám răng trẻ em',
            'method_text' => 'Composite màu sữa',
            'duration_text' => '25 phút',
            'start_date_text' => '08/07/2024',
            'end_date_text' => '08/07/2024',
            'condition_text' => 'Răng hàm sâu nhẹ',
            'story' => [
                'Con bị sâu răng hàm, hay kêu đau đêm.',
                'Bác sĩ dùng tiếng dễ hiểu cho bé, bé hợp tác tốt. Trám xong không đau nữa.',
            ],
            'timeline' => [
                ['date' => '08/07/2024', 'label' => 'Vệ sinh & trám 1 răng hàm'],
            ],
            'display_order' => 34,
        ]),
        array_replace($base, [
            'slug' => 'tay-trang-nhe-bao-son',
            'facility_slug' => 'nha-khoa-bao-son',
            'facility_name' => 'Nha Khoa Bảo Sơn',
            'title' => 'Tẩy trắng răng ngắn ngày giá mềm',
            'rating' => '4.6',
            'author_text' => 'Nữ, 22 tuổi',
            'review_date_text' => '25/06/2024',
            'price_text' => '1.200.000đ',
            'excerpt' => 'Làm đẹp trước đám cưới bạn, tẩy trắng tại Bảo Sơn hợp lý giá cả, kết quả trắng rõ.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 31,
            'comments_count' => 4,
            'shares_count' => 2,
            'service_text' => 'Tẩy trắng răng',
            'method_text' => 'Whitening home kit + chairside',
            'duration_text' => '7 ngày',
            'start_date_text' => '18/06/2024',
            'end_date_text' => '25/06/2024',
            'condition_text' => 'Răng ố nhẹ do hút thuốc lâu năm',
            'story' => [
                'Do hút thuốc răng hơi xỉu, muốn tẩy trắng trước đám cưới bạn.',
                'Làm tại phòng 1 lần + đeo khay tại nhà 7 ngày. Trắng hơn 5-6 tone liền.',
            ],
            'timeline' => [
                ['date' => '18/06/2024', 'label' => 'Tẩy chairside + lấy dấu khay'],
                ['date' => '25/06/2024', 'label' => 'Tái khám kết quả'],
            ],
            'display_order' => 35,
        ]),
        array_replace($base, [
            'slug' => 'nieng-mac-cai-victoria',
            'facility_slug' => 'victoria-dental',
            'facility_name' => 'Victoria Dental Clinic',
            'title' => 'Niềng mắc cài cao cấp 24 tháng',
            'rating' => '5.0',
            'author_text' => 'Nữ, 27 tuổi',
            'review_date_text' => '12/07/2024',
            'price_text' => '98.000.000đ',
            'excerpt' => 'Niềng mắc cài cao cấp, dịch vụ Pháp rất sang trọng. Bác sĩ đầu tiên tư vấn rất kỹ, chi phí cao nhưng xứng đáng.',
            'before_image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 112,
            'comments_count' => 21,
            'shares_count' => 9,
            'service_text' => 'Niềng răng',
            'method_text' => 'Mắc cài khóa tự đóng',
            'duration_text' => '24 tháng',
            'start_date_text' => '12/07/2022',
            'end_date_text' => '12/07/2024',
            'condition_text' => 'Răng hô, khớp cắn chéo',
            'story' => [
                'Mình muốn dịch vụ cao cấp và chuyên nghiệp nên chọn Victoria.',
                '24 tháng răng đẹp tuyệt vời, không chỉ đều mà còn tôn đường nét khuôn mặt nữa.',
            ],
            'timeline' => [
                ['date' => '12/07/2022', 'label' => 'Kiểm tra toàn diện & 3D plan'],
                ['date' => '05/08/2022', 'label' => 'Gắn mắc cài'],
                ['date' => '10/02/2024', 'label' => 'Tháo mắc cài'],
                ['date' => '12/07/2024', 'label' => '6 tháng tái khám giữ hàm'],
            ],
            'display_order' => 36,
        ]),
        array_replace($base, [
            'slug' => 'design-nu-cuoi-victoria',
            'facility_slug' => 'victoria-dental',
            'facility_name' => 'Victoria Dental Clinic',
            'title' => 'Thiết kế nụ cười DSD 8 răng',
            'rating' => '4.9',
            'author_text' => 'Nữ, 32 tuổi',
            'review_date_text' => '28/06/2024',
            'price_text' => '168.000.000đ',
            'excerpt' => 'DSD thiết kế nụ cười trước - sau trên máy tính. Sứ Emax 8 răng kết quả đẹp như Hollywood.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 135,
            'comments_count' => 28,
            'shares_count' => 12,
            'service_text' => 'Thiết kế nụ cười',
            'method_text' => 'DSD Digital 8 răng Emax',
            'duration_text' => '3 tuần',
            'start_date_text' => '05/06/2024',
            'end_date_text' => '28/06/2024',
            'condition_text' => 'Dáng răng ngắn, lệch nhẹ',
            'story' => [
                'Mình đi làm MC cần nụ cười thật đẹp. Victoria thiết kế DSD cho mình xem kết quả trước.',
                'Dán sứ 8 răng Emax sau 3 tuần, nhìn vào gương mình không dám tin mắt - đẹp quá xứng đáng!',
            ],
            'timeline' => [
                ['date' => '05/06/2024', 'label' => 'Chụp ảnh 3D & DSD plan'],
                ['date' => '12/06/2024', 'label' => 'Mockup thử nụ cười'],
                ['date' => '20/06/2024', 'label' => 'Mài & răng tạm'],
                ['date' => '28/06/2024', 'label' => 'Dán sứ hoàn thiện'],
            ],
            'display_order' => 37,
        ]),
        array_replace($base, [
            'slug' => 'invisalign-victoria',
            'facility_slug' => 'victoria-dental',
            'facility_name' => 'Victoria Dental Clinic',
            'title' => 'Invisalign Diamond Provider',
            'rating' => '4.9',
            'author_text' => 'Nữ, 30 tuổi',
            'review_date_text' => '05/07/2024',
            'price_text' => '125.000.000đ',
            'excerpt' => 'Victoria là Diamond Provider Invisalign. Niềng 18 tháng nụ cười tươi sáng. Y tá hỗ trợ tiếng Pháp tốt.',
            'before_image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 89,
            'comments_count' => 15,
            'shares_count' => 7,
            'service_text' => 'Niềng Invisalign',
            'method_text' => 'Invisalign Go',
            'duration_text' => '18 tháng',
            'start_date_text' => '02/01/2023',
            'end_date_text' => '05/07/2024',
            'condition_text' => 'Khoảng cách răng cửa',
            'story' => [
                'Công ty mình có HQ ở Pháp, cần nha khoa hỗ trợ ngôn ngữ tốt. Victoria rất phù hợp.',
                'Invisalign 18 tháng, nụ cười đẹp, khớp cắn chuẩn. Thời gian luôn đúng lịch.',
            ],
            'timeline' => [
                ['date' => '02/01/2023', 'label' => 'Scan iTero & plan ClinCheck'],
                ['date' => '20/01/2023', 'label' => 'Nhận khay đầu tiên'],
                ['date' => '05/07/2024', 'label' => 'Hoàn tất & retainer'],
            ],
            'display_order' => 38,
        ]),
        array_replace($base, [
            'slug' => 'dan-veneer-victoria',
            'facility_slug' => 'victoria-dental',
            'facility_name' => 'Victoria Dental Clinic',
            'title' => 'Dán Veneer 6 răng cửa Emax',
            'rating' => '4.9',
            'author_text' => 'Nam, 40 tuổi',
            'review_date_text' => '18/06/2024',
            'price_text' => '78.000.000đ',
            'excerpt' => 'Dán Veneer Emax 6 răng cửa. Không mài nhiều răng thật, màu trắng sáng tự nhiên Pháp.',
            'before_image_url' => 'https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 73,
            'comments_count' => 11,
            'shares_count' => 6,
            'service_text' => 'Dán sứ thẩm mỹ',
            'method_text' => 'Emax Veneer 6 răng',
            'duration_text' => '2 tuần',
            'start_date_text' => '05/06/2024',
            'end_date_text' => '18/06/2024',
            'condition_text' => 'Mẻ khuyết, răng ố',
            'story' => [
                'Do uống rượu vang thường xuyên răng bị ố, mẻ bờ. Muốn nụ cười trẻ hơn.',
                'Victoria làm Veneer 6 răng Emax không mài nhiều. Kết quả trắng mà không bị giả.',
            ],
            'timeline' => [
                ['date' => '05/06/2024', 'label' => 'Chụp & lên màu A1'],
                ['date' => '12/06/2024', 'label' => 'Mài nhẹ + tạm'],
                ['date' => '18/06/2024', 'label' => 'Dán hoàn thiện'],
            ],
            'display_order' => 39,
        ]),
        array_replace($base, [
            'slug' => 'tay-trang-cao-cap-victoria',
            'facility_slug' => 'victoria-dental',
            'facility_name' => 'Victoria Dental Clinic',
            'title' => 'Tẩy trắng cao cấp Spa Whitening',
            'rating' => '4.8',
            'author_text' => 'Nữ, 35 tuổi',
            'review_date_text' => '22/06/2024',
            'price_text' => '3.800.000đ',
            'excerpt' => 'Tẩy trắng Spa Whitening 90 phút có massage chân. Trắng 8-9 tone, cảm giác thư giãn như spa.',
            'before_image_url' => 'https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 66,
            'comments_count' => 9,
            'shares_count' => 4,
            'service_text' => 'Tẩy trắng',
            'method_text' => 'Spa Whitening 90 phút',
            'duration_text' => '90 phút',
            'start_date_text' => '22/06/2024',
            'end_date_text' => '22/06/2024',
            'condition_text' => 'Răng ố do rượu vang, trà',
            'story' => [
                'Nữa mình làm dịch vụ tại Victoria như ở spa: massage chân + trà hoa hồng.',
                'Sau 90 phút trắng 9 tone. Thời gian nào mình cũng cảm thấy thư giãn, không đau nhức.',
            ],
            'timeline' => [
                ['date' => '22/06/2024', 'label' => 'Tẩy trắng + massage + fluor'],
            ],
            'display_order' => 40,
        ]),
        array_replace($base, [
            'slug' => 'nieng-mac-cai-greenlife',
            'facility_slug' => 'greenlife-dental',
            'facility_name' => 'GreenLife Dental',
            'title' => 'Niềng mắc cài 18 tháng vật liệu sinh học',
            'rating' => '4.7',
            'author_text' => 'Nữ, 25 tuổi',
            'review_date_text' => '08/07/2024',
            'price_text' => '38.000.000đ',
            'excerpt' => 'Vật liệu composite sinh học an toàn. Niềng 18 tháng, môi và nướu không bị đen. Mỗi tái khám đều được hướng dẫn.',
            'before_image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 52,
            'comments_count' => 8,
            'shares_count' => 3,
            'service_text' => 'Niềng mắc cài',
            'method_text' => 'Mắc cài sinh học BPA-free',
            'duration_text' => '18 tháng',
            'start_date_text' => '10/01/2023',
            'end_date_text' => '08/07/2024',
            'condition_text' => 'Răng chen chúc khấp khểnh',
            'story' => [
                'Mình có cơ địa nhạy cảm với kim loại nên chọn vật liệu sinh học của GreenLife.',
                '18 tháng răng đều, không bị đen viền nướu như mình lo. Ăn uống thoải mái.',
            ],
            'timeline' => [
                ['date' => '10/01/2023', 'label' => 'Khám & tư vấn vật liệu'],
                ['date' => '25/01/2023', 'label' => 'Gắn mắc cài'],
                ['date' => '08/07/2024', 'label' => 'Tháo mắc cài + giữ hàm'],
            ],
            'display_order' => 41,
        ]),
        array_replace($base, [
            'slug' => 'cao-rang-sinh-hoc-greenlife',
            'facility_slug' => 'greenlife-dental',
            'facility_name' => 'GreenLife Dental',
            'title' => 'Lấy cao răng bằng baking soda sinh học',
            'rating' => '4.6',
            'author_text' => 'Nam, 32 tuổi',
            'review_date_text' => '15/06/2024',
            'price_text' => '550.000đ',
            'excerpt' => 'Lấy cao răng không dùng hóa chất mạnh. Dung dịch baking soda + muối, nướu không bị mài mòn.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 36,
            'comments_count' => 5,
            'shares_count' => 2,
            'service_text' => 'Lấy cao răng',
            'method_text' => 'Airflow baking soda sinh học',
            'duration_text' => '45 phút',
            'start_date_text' => '15/06/2024',
            'end_date_text' => '15/06/2024',
            'condition_text' => 'Cao răng + ố bám nhiều',
            'story' => [
                'Mình bị mảng bám nhiều do uống cà phê nhưng cơ địa nướu mỏng.',
                'Công nghệ airflow sinh học không làm nướu chảy máu nhiều. Hơi thở mát mãi sau.',
            ],
            'timeline' => [
                ['date' => '15/06/2024', 'label' => 'Airflow + polishing + fluor tự nhiên'],
            ],
            'display_order' => 42,
        ]),
        array_replace($base, [
            'slug' => 'tay-trang-than-thien-greenlife',
            'facility_slug' => 'greenlife-dental',
            'facility_name' => 'GreenLife Dental',
            'title' => 'Tẩy trắng răng than hoạt tính tự nhiên',
            'rating' => '4.5',
            'author_text' => 'Nữ, 29 tuổi',
            'review_date_text' => '25/06/2024',
            'price_text' => '2.200.000đ',
            'excerpt' => 'Tẩy trắng bằng gel thảo dược + than hoạt tính. Không peroxide, không nhạy cảm.',
            'before_image_url' => 'https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1629909613654-28e377c37b09?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 44,
            'comments_count' => 6,
            'shares_count' => 2,
            'service_text' => 'Tẩy trắng',
            'method_text' => 'Than hoạt tính + thảo dược',
            'duration_text' => '60 phút',
            'start_date_text' => '25/06/2024',
            'end_date_text' => '02/07/2024',
            'condition_text' => 'Răng ố nhạy cảm',
            'story' => [
                'Răng mình rất nhạy cảm, không dám tẩy trắng công nghệ mạnh.',
                'GreenLife dùng phương pháp tự nhiên, trắng 4-5 tone, răng không bị nhạy.',
            ],
            'timeline' => [
                ['date' => '25/06/2024', 'label' => 'Phương pháp tự nhiên 60 phút'],
                ['date' => '02/07/2024', 'label' => 'Tái khám & đánh giá'],
            ],
            'display_order' => 43,
        ]),
        array_replace($base, [
            'slug' => 'chua-tuy-greenlife',
            'facility_slug' => 'greenlife-dental',
            'facility_name' => 'GreenLife Dental',
            'title' => 'Chữa tủy răng hàm dưới',
            'rating' => '4.6',
            'author_text' => 'Nam, 38 tuổi',
            'review_date_text' => '02/07/2024',
            'price_text' => '2.400.000đ',
            'excerpt' => 'Chữa tủy bằng vật liệu sinh học, không gây độc. Bác sĩ làm 2 lần là xong, không đau sau đó.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 30,
            'comments_count' => 4,
            'shares_count' => 1,
            'service_text' => 'Chữa tủy',
            'method_text' => 'MTA sinh học 2 buổi',
            'duration_text' => '2 tuần',
            'start_date_text' => '18/06/2024',
            'end_date_text' => '02/07/2024',
            'condition_text' => 'Viêm tủy răng hàm dưới',
            'story' => [
                'Răng bị đau từng cơn đêm, đi khám bị viêm tủy.',
                'Chữa tủy vật liệu MTA sinh học an toàn, 2 buổi là ổn. Từ đó không bị đau nữa.',
            ],
            'timeline' => [
                ['date' => '18/06/2024', 'label' => 'Mở tủy & lấy tủy'],
                ['date' => '02/07/2024', 'label' => 'Lấp tủy MTA + trám vĩnh viễn'],
            ],
            'display_order' => 44,
        ]),
        array_replace($base, [
            'slug' => 'nho-rang-greenlife',
            'facility_slug' => 'greenlife-dental',
            'facility_name' => 'GreenLife Dental',
            'title' => 'Nhổ răng sâu hàm trên',
            'rating' => '4.6',
            'author_text' => 'Nữ, 45 tuổi',
            'review_date_text' => '18/06/2024',
            'price_text' => '650.000đ',
            'excerpt' => 'Nhổ răng sâu nhưng không đau. Thuốc tê không chứa chất bảo quản, mình không bị chóng mặt.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 25,
            'comments_count' => 3,
            'shares_count' => 1,
            'service_text' => 'Nhổ răng',
            'method_text' => 'Tiểu phẫu nhẹ',
            'duration_text' => '20 phút',
            'start_date_text' => '18/06/2024',
            'end_date_text' => '25/06/2024',
            'condition_text' => 'Răng sâu vỡ lớn',
            'story' => [
                'Bản thân mình dị ứng nhiều hóa chất nên chọn GreenLife.',
                'Nhổ xong chỉ sưng nhẹ, dùng thảo dược súc miệng hồi phục nhanh.',
            ],
            'timeline' => [
                ['date' => '18/06/2024', 'label' => 'Nhổ răng'],
                ['date' => '25/06/2024', 'label' => 'Tái khám lành thương'],
            ],
            'display_order' => 45,
        ]),
        array_replace($base, [
            'slug' => 'all-on-6-dental-hub',
            'facility_slug' => 'dental-hub-da-nang',
            'facility_name' => 'Dental Hub Đà Nẵng',
            'title' => 'All-on-6 toàn hàm 24h răng tạm',
            'rating' => '4.9',
            'author_text' => 'Nam, 68 tuổi',
            'review_date_text' => '05/07/2024',
            'price_text' => '210.000.000đ',
            'excerpt' => 'Trung tâm Implant miền Trung. All-on-6 2 hàm chỉ 1 ngày, bác sĩ Hàn Quốc thực hiện. Răng cố định ngay, ăn cháo liền.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 108,
            'comments_count' => 19,
            'shares_count' => 11,
            'service_text' => 'Implant All-on-6',
            'method_text' => '2 hàm 12 trụ',
            'duration_text' => '4 tháng',
            'start_date_text' => '02/03/2024',
            'end_date_text' => '05/07/2024',
            'condition_text' => 'Mất răng gần như toàn bộ, tiêu xương',
            'story' => [
                'Còn 4 răng thật, tiêu xương nặng. Dental Hub bác sĩ Hàn Quốc tư vấn All-on-6.',
                '1 ngày sau phẫu thuật mình có cả 2 hàm răng cố định ăn được cơm. 4 tháng hoàn thiện thật tuyệt.',
            ],
            'timeline' => [
                ['date' => '02/03/2024', 'label' => 'CBCT & Planmeca 3D'],
                ['date' => '15/03/2024', 'label' => 'Cấy 12 trụ + răng tạm 24h'],
                ['date' => '28/06/2024', 'label' => 'Lấy dấu răng sứ Zirconia'],
                ['date' => '05/07/2024', 'label' => 'Dán răng hoàn thiện'],
            ],
            'display_order' => 46,
        ]),
        array_replace($base, [
            'slug' => 'implant-4-tru-dental-hub',
            'facility_slug' => 'dental-hub-da-nang',
            'facility_name' => 'Dental Hub Đà Nẵng',
            'title' => 'Cấy 4 trụ Implant đồng loạt',
            'rating' => '4.8',
            'author_text' => 'Nam, 55 tuổi',
            'review_date_text' => '12/06/2024',
            'price_text' => '72.000.000đ',
            'excerpt' => 'Mất 4 răng hàm, cấy đồng loạt 4 trụ Implant cùng lúc. Máy hiện đại, chụp CT trong phòng.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 74,
            'comments_count' => 11,
            'shares_count' => 5,
            'service_text' => 'Implant 4 trụ',
            'method_text' => '4 trụ Dentium',
            'duration_text' => '3 tháng',
            'start_date_text' => '08/03/2024',
            'end_date_text' => '12/06/2024',
            'condition_text' => 'Mất 4 răng hàm',
            'story' => [
                'Hàm dưới mất 4 răng hàm, ăn nhai khó khăn. Dental Hub có máy CT trong phòng nên tiện.',
                'Cấy 4 trụ cùng lúc chỉ 90 phút. Sau 3 tháng gắn mão, ăn nhai đều như răng thật.',
            ],
            'timeline' => [
                ['date' => '08/03/2024', 'label' => 'CBCT + kế hoạch'],
                ['date' => '22/03/2024', 'label' => 'Cấy 4 trụ Implant'],
                ['date' => '05/06/2024', 'label' => 'Lấy dấu mão sứ'],
                ['date' => '12/06/2024', 'label' => 'Gắn mão hoàn thiện'],
            ],
            'display_order' => 47,
        ]),
        array_replace($base, [
            'slug' => 'nieng-12-thang-dental-hub',
            'facility_slug' => 'dental-hub-da-nang',
            'facility_name' => 'Dental Hub Đà Nẵng',
            'title' => 'Niềng răng 12 tháng chỉnh khớp cắn',
            'rating' => '4.7',
            'author_text' => 'Nữ, 23 tuổi',
            'review_date_text' => '20/06/2024',
            'price_text' => '32.000.000đ',
            'excerpt' => 'Chỉnh khớp cắn ngắn chỉ 12 tháng. Đội ngũ bác sĩ trẻ trung nhiệt tình, trả lời tin nhắn nhanh.',
            'before_image_url' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 58,
            'comments_count' => 9,
            'shares_count' => 4,
            'service_text' => 'Niềng răng mắc cài',
            'method_text' => 'Mắc cài thường tốc độ cao',
            'duration_text' => '12 tháng',
            'start_date_text' => '20/06/2023',
            'end_date_text' => '20/06/2024',
            'condition_text' => 'Khớp cắn sâu 2 hàm',
            'story' => [
                'Khớp cắn sâu làm hàm dưới bị lui, muốn chỉnh nhanh để đi làm xa.',
                'Niềng đúng 12 tháng là xong. Khớp cắn chuẩn và vùng cằm đầy đặn hơn.',
            ],
            'timeline' => [
                ['date' => '20/06/2023', 'label' => 'Khám 3D & lên plan'],
                ['date' => '10/07/2023', 'label' => 'Gắn mắc cài'],
                ['date' => '20/06/2024', 'label' => 'Tháo mắc cài & giữ hàm'],
            ],
            'display_order' => 48,
        ]),
        array_replace($base, [
            'slug' => 'rang-su-zirconia-dental-hub',
            'facility_slug' => 'dental-hub-da-nang',
            'facility_name' => 'Dental Hub Đà Nẵng',
            'title' => 'Răng sứ Zirconia 20 răng 1 tuần',
            'rating' => '4.8',
            'author_text' => 'Nữ, 44 tuổi',
            'review_date_text' => '01/07/2024',
            'price_text' => '120.000.000đ',
            'excerpt' => 'Sứ Zirconia tại Dental Hub có labo riêng. 20 răng chỉ 7 ngày, màu tiệp khớp tự nhiên.',
            'before_image_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 81,
            'comments_count' => 13,
            'shares_count' => 6,
            'service_text' => 'Bọc răng sứ Zirconia',
            'method_text' => '20 răng full Zirconia',
            'duration_text' => '7 ngày',
            'start_date_text' => '22/06/2024',
            'end_date_text' => '01/07/2024',
            'condition_text' => 'Răng cũ đen viền, mòn nặng',
            'story' => [
                'Bọc sứ nhiều năm trước đã đen viền nướu, muốn làm lại bằng sứ tốt.',
                'Dental Hub có labo riêng ở tòa nhà, chỉ 7 ngày là có răng mới Zirconia trắng đẹp.',
            ],
            'timeline' => [
                ['date' => '22/06/2024', 'label' => 'Tháo răng cũ & mài 20 răng'],
                ['date' => '26/06/2024', 'label' => 'Thử khung sứ Zirconia'],
                ['date' => '01/07/2024', 'label' => 'Dán hoàn thiện & chỉnh khớp cắn'],
            ],
            'display_order' => 49,
        ]),
        array_replace($base, [
            'slug' => 'phau-thuat-nang-nuoi-dental-hub',
            'facility_slug' => 'dental-hub-da-nang',
            'facility_name' => 'Dental Hub Đà Nẵng',
            'title' => 'Phẫu thuật nâng xoang + cấy Implant',
            'rating' => '4.7',
            'author_text' => 'Nam, 58 tuổi',
            'review_date_text' => '28/06/2024',
            'price_text' => '52.000.000đ',
            'excerpt' => 'Tiêu xương hàm trên nặng, cần nâng xoang trước khi cấy. Hồi phục 1 tuần ổn, kết quả Implant rất chắc.',
            'before_image_url' => 'https://images.unsplash.com/photo-1588774075506-58d5e31ebaa1?auto=format&fit=crop&w=900&q=80',
            'after_image_url' => 'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=900&q=80',
            'likes_count' => 65,
            'comments_count' => 10,
            'shares_count' => 4,
            'service_text' => 'Phẫu thuật nâng xoang',
            'method_text' => 'Sinus lift + Implant',
            'duration_text' => '6 tháng',
            'start_date_text' => '20/12/2023',
            'end_date_text' => '28/06/2024',
            'condition_text' => 'Tiêu xương xoang hàm trên',
            'story' => [
                'Hàm trên mất răng lâu năm, tiêu xương nặng không đủ trụ cấy.',
                'Dental Hub nâng xoang bằng xương nhân tạo, 6 tháng sau cấy 3 trụ Implant. Hiện ăn nhai đã ổn.',
            ],
            'timeline' => [
                ['date' => '20/12/2023', 'label' => 'Nâng xoang + ghép xương'],
                ['date' => '10/05/2024', 'label' => 'Cấy 3 trụ Implant'],
                ['date' => '28/06/2024', 'label' => 'Gắn mão sứ & tái khám'],
            ],
            'display_order' => 50,
        ]),
    ];
}

function medical_directory_default_doctor_base(): array
{
    return [
        'slug' => 'bs-nguyen-minh-anh',
        'name' => 'BS. Nguyễn Minh Anh',
        'title_text' => 'Bác sĩ Răng Hàm Mặt',
        'specialty_text' => 'Răng Hàm Mặt',
        'city' => 'Đà Nẵng',
        'facility_slug' => 'top-dental-clinic',
        'facility_name' => 'Top Dental Clinic',
        'verified' => 1,
        'rating' => '4.9',
        'reviews_count' => 482,
        'followers_count' => 9820,
        'hours_text' => 'Lịch khám: 08:00 - 17:30 (T2 - T7)',
        'price_text' => '300.000đ - 1.500.000đ+',
        'image_url' => 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=900&q=80',
        'tags' => ['Đã xác minh', 'Tư vấn rõ ràng', 'Thân thiện'],
        'specialties' => ['Răng sứ thẩm mỹ', 'Implant', 'Thiết kế nụ cười', 'Dán sứ veneer'],
        'gallery' => [],
        'bio' => [
            'Tập trung điều trị và thẩm mỹ nha khoa theo kế hoạch, ưu tiên trải nghiệm nhẹ nhàng và minh bạch thông tin.',
            'Kinh nghiệm thực hành lâm sàng trong các ca phục hình, implant và thẩm mỹ nụ cười.',
        ],
        'status' => 'published',
        'display_order' => 0,
    ];
}

function medical_directory_default_doctors(): array
{
    $base = medical_directory_default_doctor_base();
    return [
        $base,
        array_replace($base, [
            'slug' => 'bs-tran-hai-nam',
            'name' => 'BS. Trần Hải Nam',
            'title_text' => 'Bác sĩ chuyên Implant và phục hình',
            'specialty_text' => 'Implant - Phục hình',
            'facility_slug' => 'top-dental-clinic',
            'facility_name' => 'Top Dental Clinic',
            'rating' => '4.8',
            'reviews_count' => 356,
            'followers_count' => 7104,
            'hours_text' => 'Lịch khám: 08:30 - 18:00 (T2 - T7)',
            'price_text' => '400.000đ - 2.000.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=900&q=80',
            'tags' => ['Đã xác minh', 'Kế hoạch rõ ràng', 'Chuẩn lịch hẹn'],
            'specialties' => ['Cấy ghép Implant', 'Răng toàn hàm', 'Phục hình sứ', 'Tái khám sau cấy'],
            'bio' => [
                'Tập trung implant và phục hình theo kế hoạch điều trị chi tiết, theo dõi sát tiến trình.',
                'Ưu tiên giải thích dễ hiểu, chi phí minh bạch và tối ưu kết quả lâu dài.',
            ],
            'display_order' => 1,
        ]),
        array_replace($base, [
            'slug' => 'bs-le-thao-vy',
            'name' => 'BS. Lê Thảo Vy',
            'title_text' => 'Bác sĩ da liễu thẩm mỹ',
            'specialty_text' => 'Da liễu - Thẩm mỹ',
            'facility_slug' => 'smile-dental-clinic',
            'facility_name' => 'Smile Dental Clinic',
            'rating' => '4.7',
            'reviews_count' => 291,
            'followers_count' => 5886,
            'hours_text' => 'Lịch khám: 09:00 - 19:00 (T2 - CN)',
            'price_text' => '250.000đ - 1.200.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1594824476967-48c8b964273f?auto=format&fit=crop&w=900&q=80',
            'tags' => ['Đã xác minh', 'Tư vấn kỹ', 'Phác đồ cá nhân hóa'],
            'specialties' => ['Điều trị mụn', 'Laser sắc tố', 'Sẹo rỗ', 'Trẻ hóa da'],
            'bio' => [
                'Tập trung điều trị mụn, sẹo rỗ và trẻ hóa da theo phác đồ cá nhân hóa.',
                'Ưu tiên an toàn và theo dõi sát phản ứng da theo từng giai đoạn.',
            ],
            'display_order' => 2,
        ]),
        array_replace($base, [
            'slug' => 'bs-pham-quoc-hung',
            'name' => 'BS. Phạm Quốc Hưng',
            'title_text' => 'Bác sĩ Tai Mũi Họng',
            'specialty_text' => 'Tai Mũi Họng',
            'facility_slug' => 'happy-dental',
            'facility_name' => 'Happy Dental',
            'rating' => '4.6',
            'reviews_count' => 248,
            'followers_count' => 4312,
            'hours_text' => 'Lịch khám: 07:30 - 16:30 (T2 - T6)',
            'price_text' => '200.000đ - 800.000đ+',
            'image_url' => 'https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&w=900&q=80',
            'tags' => ['Đã xác minh', 'Khám kỹ', 'Nội soi rõ ràng'],
            'specialties' => ['Nội soi tai mũi họng', 'Viêm xoang', 'Viêm tai giữa', 'Hô hấp trên'],
            'bio' => [
                'Khám nội soi tai mũi họng, điều trị viêm xoang và các bệnh lý hô hấp trên.',
                'Tư vấn lộ trình điều trị rõ ràng, ưu tiên theo dõi tái khám đúng hẹn.',
            ],
            'display_order' => 3,
        ]),
    ];
}

function medical_directory_seed_defaults(PDO $pdo): void
{
    medical_directory_ensure_tables($pdo);

    $facilityCount = (int) $pdo->query("SELECT COUNT(*) FROM medical_facilities")->fetchColumn();
    if ($facilityCount === 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO medical_facilities (
                slug, name, category, city, subtitle, verified, rating, reviews_count, followers_count,
                hours_text, address_text, phone_text, website_url, price_text, image_url, images_label,
                featured_services_json, tags_json, gallery_json, intro_json, stats_json, utilities_json,
                services_json, review_summary_json, reviews_list_json, features_json, status, display_order
            ) VALUES (
                :slug, :name, :category, :city, :subtitle, :verified, :rating, :reviews_count, :followers_count,
                :hours_text, :address_text, :phone_text, :website_url, :price_text, :image_url, :images_label,
                :featured_services_json, :tags_json, :gallery_json, :intro_json, :stats_json, :utilities_json,
                :services_json, :review_summary_json, :reviews_list_json, :features_json, :status, :display_order
            )"
        );
        foreach (medical_directory_default_facilities() as $row) {
            $stmt->execute([
                ':slug' => (string) $row['slug'],
                ':name' => (string) $row['name'],
                ':category' => (string) $row['category'],
                ':city' => (string) $row['city'],
                ':subtitle' => (string) $row['subtitle'],
                ':verified' => (int) $row['verified'],
                ':rating' => (string) $row['rating'],
                ':reviews_count' => (int) $row['reviews_count'],
                ':followers_count' => (int) $row['followers_count'],
                ':hours_text' => (string) $row['hours_text'],
                ':address_text' => (string) $row['address_text'],
                ':phone_text' => (string) $row['phone_text'],
                ':website_url' => (string) $row['website_url'],
                ':price_text' => (string) $row['price_text'],
                ':image_url' => (string) $row['image_url'],
                ':images_label' => (string) $row['images_label'],
                ':featured_services_json' => medical_directory_json_encode($row['featured_services']),
                ':tags_json' => medical_directory_json_encode($row['tags']),
                ':gallery_json' => medical_directory_json_encode($row['gallery']),
                ':intro_json' => medical_directory_json_encode($row['intro']),
                ':stats_json' => medical_directory_json_encode($row['stats']),
                ':utilities_json' => medical_directory_json_encode($row['utilities']),
                ':services_json' => medical_directory_json_encode($row['services']),
                ':review_summary_json' => medical_directory_json_encode($row['review_summary']),
                ':reviews_list_json' => medical_directory_json_encode($row['reviews_list']),
                ':features_json' => medical_directory_json_encode($row['features']),
                ':status' => (string) $row['status'],
                ':display_order' => (int) $row['display_order'],
            ]);
        }
    }

    $existingReviewSlugs = [];
    if (medical_directory_table_exists($pdo, 'medical_reviews')) {
        $slugs = $pdo->query("SELECT slug FROM medical_reviews")->fetchAll(PDO::FETCH_COLUMN);
        if (is_array($slugs)) {
            foreach ($slugs as $slug) {
                $slug = (string) $slug;
                if ($slug !== '') {
                    $existingReviewSlugs[$slug] = true;
                }
            }
        }
    }

    $stmt = $pdo->prepare(
        "INSERT INTO medical_reviews (
            slug, facility_slug, facility_name, title, verified, rating, author_text, location_text,
            review_date_text, price_text, excerpt, before_image_url, after_image_url, likes_count,
            comments_count, shares_count, service_text, method_text, duration_text, start_date_text,
            end_date_text, condition_text, story_json, timeline_json, thumbs_json, process_before_json,
            process_during_json, process_after_json, status, display_order
        ) VALUES (
            :slug, :facility_slug, :facility_name, :title, :verified, :rating, :author_text, :location_text,
            :review_date_text, :price_text, :excerpt, :before_image_url, :after_image_url, :likes_count,
            :comments_count, :shares_count, :service_text, :method_text, :duration_text, :start_date_text,
            :end_date_text, :condition_text, :story_json, :timeline_json, :thumbs_json, :process_before_json,
            :process_during_json, :process_after_json, :status, :display_order
        )"
    );
    foreach (medical_directory_default_reviews() as $row) {
        $slug = (string) ($row['slug'] ?? '');
        if ($slug === '' || isset($existingReviewSlugs[$slug])) {
            continue;
        }
        $stmt->execute([
            ':slug' => $slug,
            ':facility_slug' => (string) ($row['facility_slug'] ?? ''),
            ':facility_name' => (string) ($row['facility_name'] ?? ''),
            ':title' => (string) ($row['title'] ?? ''),
            ':verified' => (int) ($row['verified'] ?? 0),
            ':rating' => (string) ($row['rating'] ?? '0.0'),
            ':author_text' => (string) ($row['author_text'] ?? ''),
            ':location_text' => (string) ($row['location_text'] ?? ''),
            ':review_date_text' => (string) ($row['review_date_text'] ?? ''),
            ':price_text' => (string) ($row['price_text'] ?? ''),
            ':excerpt' => (string) ($row['excerpt'] ?? ''),
            ':before_image_url' => (string) ($row['before_image_url'] ?? ''),
            ':after_image_url' => (string) ($row['after_image_url'] ?? ''),
            ':likes_count' => (int) ($row['likes_count'] ?? 0),
            ':comments_count' => (int) ($row['comments_count'] ?? 0),
            ':shares_count' => (int) ($row['shares_count'] ?? 0),
            ':service_text' => (string) ($row['service_text'] ?? ''),
            ':method_text' => (string) ($row['method_text'] ?? ''),
            ':duration_text' => (string) ($row['duration_text'] ?? ''),
            ':start_date_text' => (string) ($row['start_date_text'] ?? ''),
            ':end_date_text' => (string) ($row['end_date_text'] ?? ''),
            ':condition_text' => (string) ($row['condition_text'] ?? ''),
            ':story_json' => medical_directory_json_encode((array) ($row['story'] ?? [])),
            ':timeline_json' => medical_directory_json_encode((array) ($row['timeline'] ?? [])),
            ':thumbs_json' => medical_directory_json_encode((array) ($row['thumbs'] ?? [])),
            ':process_before_json' => medical_directory_json_encode((array) ($row['process_before'] ?? [])),
            ':process_during_json' => medical_directory_json_encode((array) ($row['process_during'] ?? [])),
            ':process_after_json' => medical_directory_json_encode((array) ($row['process_after'] ?? [])),
            ':status' => (string) ($row['status'] ?? 'published'),
            ':display_order' => (int) ($row['display_order'] ?? 0),
        ]);
        $existingReviewSlugs[$slug] = true;
    }

    $existingDoctorSlugs = [];
    if (medical_directory_table_exists($pdo, 'medical_doctors')) {
        $slugs = $pdo->query("SELECT slug FROM medical_doctors")->fetchAll(PDO::FETCH_COLUMN);
        if (is_array($slugs)) {
            foreach ($slugs as $slug) {
                $slug = (string) $slug;
                if ($slug !== '') {
                    $existingDoctorSlugs[$slug] = true;
                }
            }
        }
    }

    $stmt = $pdo->prepare(
        "INSERT INTO medical_doctors (
            slug, name, title_text, specialty_text, city, facility_slug, facility_name, verified, rating,
            reviews_count, followers_count, hours_text, price_text, image_url, tags_json, specialties_json,
            gallery_json, bio_json, status, display_order
        ) VALUES (
            :slug, :name, :title_text, :specialty_text, :city, :facility_slug, :facility_name, :verified, :rating,
            :reviews_count, :followers_count, :hours_text, :price_text, :image_url, :tags_json, :specialties_json,
            :gallery_json, :bio_json, :status, :display_order
        )"
    );
    foreach (medical_directory_default_doctors() as $row) {
        $slug = (string) ($row['slug'] ?? '');
        if ($slug === '' || isset($existingDoctorSlugs[$slug])) {
            continue;
        }
        $stmt->execute([
            ':slug' => $slug,
            ':name' => (string) ($row['name'] ?? ''),
            ':title_text' => (string) ($row['title_text'] ?? ''),
            ':specialty_text' => (string) ($row['specialty_text'] ?? ''),
            ':city' => (string) ($row['city'] ?? ''),
            ':facility_slug' => (string) ($row['facility_slug'] ?? ''),
            ':facility_name' => (string) ($row['facility_name'] ?? ''),
            ':verified' => (int) ($row['verified'] ?? 0),
            ':rating' => (string) ($row['rating'] ?? '0.0'),
            ':reviews_count' => (int) ($row['reviews_count'] ?? 0),
            ':followers_count' => (int) ($row['followers_count'] ?? 0),
            ':hours_text' => (string) ($row['hours_text'] ?? ''),
            ':price_text' => (string) ($row['price_text'] ?? ''),
            ':image_url' => (string) ($row['image_url'] ?? ''),
            ':tags_json' => medical_directory_json_encode((array) ($row['tags'] ?? [])),
            ':specialties_json' => medical_directory_json_encode((array) ($row['specialties'] ?? [])),
            ':gallery_json' => medical_directory_json_encode((array) ($row['gallery'] ?? [])),
            ':bio_json' => medical_directory_json_encode((array) ($row['bio'] ?? [])),
            ':status' => (string) ($row['status'] ?? 'published'),
            ':display_order' => (int) ($row['display_order'] ?? 0),
        ]);
        $existingDoctorSlugs[$slug] = true;
    }

    if (medical_directory_table_exists($pdo, 'medical_facilities')) {
        $facilitySlugs = $pdo->query("SELECT slug FROM medical_facilities")->fetchAll(PDO::FETCH_COLUMN);
        if (is_array($facilitySlugs)) {
            foreach ($facilitySlugs as $facilitySlug) {
                medical_directory_refresh_facility_aggregates($pdo, (string) $facilitySlug);
            }
        }
    }
}

function medical_directory_facility_from_row(array $row): array
{
    $reviewsCount = (int) ($row['reviews_count'] ?? 0);
    $followersCount = (int) ($row['followers_count'] ?? 0);
    $rating = number_format((float) ($row['rating'] ?? 0), 1, '.', '');
    $defaultBase = medical_directory_default_facility_base();
    $summary = [
        'rating' => $rating,
        'reviews' => medical_directory_format_int($reviewsCount) . ' đánh giá',
        'breakdown' => [
            ['label' => '5 sao', 'value' => 0],
            ['label' => '4 sao', 'value' => 0],
            ['label' => '3 sao', 'value' => 0],
            ['label' => '2 sao', 'value' => 0],
            ['label' => '1 sao', 'value' => 0],
        ],
    ];

    return [
        'id' => (int) ($row['id'] ?? 0),
        'slug' => (string) ($row['slug'] ?? ''),
        'rank' => (int) ($row['display_order'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'category' => (string) ($row['category'] ?? 'Cơ sở y tế'),
        'city' => (string) ($row['city'] ?? ''),
        'verified' => ((int) ($row['verified'] ?? 0) === 1) ? 'Đã xác thực' : '',
        'is_verified' => (int) ($row['verified'] ?? 0) === 1,
        'subtitle' => (string) ($row['subtitle'] ?? ''),
        'content' => (string) ($row['content'] ?? ''),
        'seo_title' => (string) ($row['seo_title'] ?? ''),
        'seo_description' => (string) ($row['seo_description'] ?? ''),
        'seo_keywords' => (string) ($row['seo_keywords'] ?? ''),
        'hours' => (string) ($row['hours_text'] ?? ''),
        'hours_text' => (string) ($row['hours_text'] ?? ''),
        'address' => (string) ($row['address_text'] ?? ''),
        'address_text' => (string) ($row['address_text'] ?? ''),
        'phone' => (string) ($row['phone_text'] ?? ''),
        'phone_text' => (string) ($row['phone_text'] ?? ''),
        'website' => (string) ($row['website_url'] ?? ''),
        'website_url' => (string) ($row['website_url'] ?? ''),
        'email' => (string) ($row['email_text'] ?? ''),
        'email_text' => (string) ($row['email_text'] ?? ''),
        'coordinates' => [
            'lat' => is_numeric($row['latitude'] ?? null) ? (float) $row['latitude'] : null,
            'lng' => is_numeric($row['longitude'] ?? null) ? (float) $row['longitude'] : null,
        ],
        'google_maps_url' => (string) ($row['google_maps_url'] ?? ''),
        'parking_info' => (string) ($row['parking_info'] ?? ''),
        'nearby_landmarks' => (string) ($row['nearby_landmarks'] ?? ''),
        'emergency_hotline' => (string) ($row['emergency_hotline'] ?? ''),
        'social_links' => medical_directory_json_decode((string) ($row['social_links_json'] ?? ''), []),
        'booking_url' => (string) ($row['booking_url'] ?? ''),
        'business_license' => (string) ($row['business_license'] ?? ''),
        'medical_operation_license' => (string) ($row['medical_operation_license'] ?? ''),
        'established_year' => is_numeric($row['established_year'] ?? null) ? (int) $row['established_year'] : null,
        'branch_count' => is_numeric($row['branch_count'] ?? null) ? (int) $row['branch_count'] : null,
        'insurance_accepted' => medical_directory_json_value_decode((string) ($row['insurance_accepted_json'] ?? ''), null),
        'payment_methods' => medical_directory_json_decode((string) ($row['payment_methods_json'] ?? ''), []),
        'languages_supported' => medical_directory_json_decode((string) ($row['languages_supported_json'] ?? ''), []),
        'warranty_policy' => (string) ($row['warranty_policy'] ?? ''),
        'equipment_mentioned' => medical_directory_json_decode((string) ($row['equipment_mentioned_json'] ?? ''), []),
        'doctors' => medical_directory_json_decode((string) ($row['doctors_json'] ?? ''), []),
        'video_urls' => medical_directory_json_decode((string) ($row['video_urls_json'] ?? ''), []),
        'rating' => $rating,
        'reviews' => medical_directory_format_int($reviewsCount) . ' đánh giá',
        'reviews_count' => $reviewsCount,
        'followers' => medical_directory_format_int($followersCount) . ' lượt quan tâm',
        'followers_count' => $followersCount,
        'services' => medical_directory_json_decode((string) (($row['services_json'] ?? '') !== '' ? $row['services_json'] : ($row['featured_services_json'] ?? '')), []),
        'featured_services' => medical_directory_json_decode((string) ($row['featured_services_json'] ?? ''), []),
        'price' => (string) ($row['price_text'] ?? ''),
        'price_text' => (string) ($row['price_text'] ?? ''),
        'images' => (string) ($row['images_label'] ?? ''),
        'images_label' => (string) ($row['images_label'] ?? ''),
        'image' => (string) ($row['image_url'] ?? ''),
        'image_url' => (string) ($row['image_url'] ?? ''),
        'ai_image_url' => (string) ($row['ai_image_url'] ?? ''),
        'tags' => medical_directory_json_decode((string) ($row['tags_json'] ?? ''), []),
        'gallery' => medical_directory_json_decode((string) ($row['gallery_json'] ?? ''), []),
        'price_table_html' => (string) ($row['price_table_html'] ?? ''),
        'price_source_scope' => (string) ($row['price_source_scope'] ?? ''),
        'intro' => medical_directory_json_decode((string) ($row['intro_json'] ?? ''), []),
        'stats' => (array) ($defaultBase['stats'] ?? []),
        'utilities' => medical_directory_json_decode((string) ($row['utilities_json'] ?? ''), []),
        'services_detail' => (array) ($defaultBase['services'] ?? []),
        'services_cards' => (array) ($defaultBase['services'] ?? []),
        'review_summary' => $summary,
        'reviews_list' => [],
        'features' => (array) ($defaultBase['features'] ?? []),
        'highlights' => medical_directory_json_decode((string) ($row['highlights_json'] ?? ''), []),
        'aggregate_ratings' => medical_directory_json_decode((string) ($row['aggregate_ratings_json'] ?? ''), []),
        'insufficient_data' => (int) ($row['insufficient_data'] ?? 0) === 1,
        'status' => (string) ($row['status'] ?? 'draft'),
        'display_order' => (int) ($row['display_order'] ?? 0),
    ];
}

function medical_directory_review_from_row(array $row): array
{
    $rating = number_format((float) ($row['rating'] ?? 0), 1, '.', '');
    return [
        'id' => (int) ($row['id'] ?? 0),
        'slug' => (string) ($row['slug'] ?? ''),
        'facility_slug' => (string) ($row['facility_slug'] ?? ''),
        'facility' => (string) ($row['facility_name'] ?? ''),
        'facility_name' => (string) ($row['facility_name'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'verified' => ((int) ($row['verified'] ?? 0) === 1) ? 'Đã xác thực' : '',
        'is_verified' => (int) ($row['verified'] ?? 0) === 1,
        'rating' => $rating,
        'author' => (string) ($row['author_text'] ?? ''),
        'author_text' => (string) ($row['author_text'] ?? ''),
        'location' => (string) ($row['location_text'] ?? ''),
        'location_text' => (string) ($row['location_text'] ?? ''),
        'date' => (string) ($row['review_date_text'] ?? ''),
        'review_date_text' => (string) ($row['review_date_text'] ?? ''),
        'price' => (string) ($row['price_text'] ?? ''),
        'price_text' => (string) ($row['price_text'] ?? ''),
        'excerpt' => (string) ($row['excerpt'] ?? ''),
        'before' => (string) ($row['before_image_url'] ?? ''),
        'before_image_url' => (string) ($row['before_image_url'] ?? ''),
        'after' => (string) ($row['after_image_url'] ?? ''),
        'after_image_url' => (string) ($row['after_image_url'] ?? ''),
        'likes' => (int) ($row['likes_count'] ?? 0),
        'likes_count' => (int) ($row['likes_count'] ?? 0),
        'comments' => (int) ($row['comments_count'] ?? 0),
        'comments_count' => (int) ($row['comments_count'] ?? 0),
        'shares' => (int) ($row['shares_count'] ?? 0),
        'shares_count' => (int) ($row['shares_count'] ?? 0),
        'service' => (string) ($row['service_text'] ?? ''),
        'service_text' => (string) ($row['service_text'] ?? ''),
        'method' => (string) ($row['method_text'] ?? ''),
        'method_text' => (string) ($row['method_text'] ?? ''),
        'duration' => (string) ($row['duration_text'] ?? ''),
        'duration_text' => (string) ($row['duration_text'] ?? ''),
        'start_date' => (string) ($row['start_date_text'] ?? ''),
        'end_date' => (string) ($row['end_date_text'] ?? ''),
        'condition' => (string) ($row['condition_text'] ?? ''),
        'source' => (string) ($row['source_text'] ?? $row['condition_text'] ?? ''),
        'story' => medical_directory_json_decode((string) ($row['story_json'] ?? ''), []),
        'timeline' => medical_directory_json_decode((string) ($row['timeline_json'] ?? ''), []),
        'thumbs' => medical_directory_json_decode((string) ($row['thumbs_json'] ?? ''), []),
        'process_before' => medical_directory_json_decode((string) ($row['process_before_json'] ?? ''), []),
        'process_during' => medical_directory_json_decode((string) ($row['process_during_json'] ?? ''), []),
        'process_after' => medical_directory_json_decode((string) ($row['process_after_json'] ?? ''), []),
        'status' => (string) ($row['status'] ?? 'draft'),
        'display_order' => (int) ($row['display_order'] ?? 0),
    ];
}

function medical_directory_doctor_from_row(array $row): array
{
    $reviewsCount = (int) ($row['reviews_count'] ?? 0);
    $followersCount = (int) ($row['followers_count'] ?? 0);
    $rating = number_format((float) ($row['rating'] ?? 0), 1, '.', '');
    $image = (string) ($row['image_url'] ?? '');
    $gallery = medical_directory_json_decode((string) ($row['gallery_json'] ?? ''), (array) ($row['gallery'] ?? []));
    if ($image !== '' && !in_array($image, $gallery, true)) {
        array_unshift($gallery, $image);
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'slug' => (string) ($row['slug'] ?? ''),
        'rank' => (int) ($row['display_order'] ?? 0) + 1,
        'name' => (string) ($row['name'] ?? ''),
        'title_text' => (string) ($row['title_text'] ?? ''),
        'specialty_text' => (string) ($row['specialty_text'] ?? ''),
        'subtitle' => (string) ($row['title_text'] ?? ''),
        'city' => (string) ($row['city'] ?? ''),
        'facility_slug' => (string) ($row['facility_slug'] ?? ''),
        'facility_name' => (string) ($row['facility_name'] ?? ''),
        'verified' => ((int) ($row['verified'] ?? 0) === 1) ? 'Đã xác thực' : '',
        'is_verified' => (int) ($row['verified'] ?? 0) === 1,
        'rating' => $rating,
        'reviews' => medical_directory_format_int($reviewsCount) . ' đánh giá',
        'reviews_count' => $reviewsCount,
        'followers' => medical_directory_format_int($followersCount) . ' lượt quan tâm',
        'followers_count' => $followersCount,
        'hours' => (string) ($row['hours_text'] ?? ''),
        'hours_text' => (string) ($row['hours_text'] ?? ''),
        'price' => (string) ($row['price_text'] ?? ''),
        'price_text' => (string) ($row['price_text'] ?? ''),
        'images' => 'Hồ sơ',
        'image' => $image,
        'image_url' => $image,
        'tags' => medical_directory_json_decode((string) ($row['tags_json'] ?? ''), (array) ($row['tags'] ?? [])),
        'services' => medical_directory_json_decode((string) ($row['specialties_json'] ?? ''), (array) ($row['specialties'] ?? [])),
        'specialties' => medical_directory_json_decode((string) ($row['specialties_json'] ?? ''), (array) ($row['specialties'] ?? [])),
        'gallery' => $gallery,
        'bio' => medical_directory_json_decode((string) ($row['bio_json'] ?? ''), (array) ($row['bio'] ?? [])),
        'status' => (string) ($row['status'] ?? 'draft'),
        'display_order' => (int) ($row['display_order'] ?? 0),
    ];
}

function medical_directory_facility_rows(bool $publishedOnly = true): array
{
    $pdo = db();
    if (!medical_directory_table_exists($pdo, 'medical_facilities')) {
        $items = medical_directory_default_facilities();
        return array_map(static function (array $facility) use ($publishedOnly): array {
            return medical_directory_facility_with_linked_reviews($facility, $publishedOnly);
        }, $items);
    }

    $sql = "SELECT * FROM medical_facilities";
    if ($publishedOnly) {
        $sql .= " WHERE status = 'published'";
    }
    $sql .= ' ORDER BY display_order ASC, id DESC';
    $rows = $pdo->query($sql)->fetchAll();
    if (!is_array($rows) || $rows === []) {
        $items = medical_directory_default_facilities();
        return array_map(static function (array $facility) use ($publishedOnly): array {
            return medical_directory_facility_with_linked_reviews($facility, $publishedOnly);
        }, $items);
    }
    return array_map('medical_directory_facility_from_row', $rows);
}

function medical_directory_reviews_for_facility_slug(string $facilitySlug, bool $publishedOnly = true): array
{
    $facilitySlug = trim($facilitySlug);
    if ($facilitySlug === '') {
        return [];
    }

    $pdo = db();
    if (!medical_directory_table_exists($pdo, 'medical_reviews')) {
        $items = medical_directory_default_reviews();
        $rows = array_values(array_filter($items, static function (array $item) use ($facilitySlug): bool {
            return (string) ($item['facility_slug'] ?? '') === $facilitySlug;
        }));
        if ($publishedOnly) {
            $rows = array_values(array_filter($rows, static function (array $item): bool {
                return (string) ($item['status'] ?? 'published') === 'published';
            }));
        }
        return array_map('medical_directory_review_from_row', $rows);
    }

    $sql = "SELECT * FROM medical_reviews WHERE facility_slug = :facility_slug";
    if ($publishedOnly) {
        $sql .= " AND status = 'published'";
    }
    $sql .= ' ORDER BY display_order ASC, id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':facility_slug' => $facilitySlug]);
    $rows = $stmt->fetchAll();
    if (!is_array($rows) || $rows === []) {
        return [];
    }
    return array_map('medical_directory_review_from_row', $rows);
}

function medical_directory_facility_review_summary_from_reviews(array $reviews): array
{
    // Editorial information entries use rating 0 and must never be presented as customer reviews.
    $ratedReviews = array_values(array_filter($reviews, static function (array $review): bool {
        return (float) ($review['rating'] ?? 0) > 0;
    }));
    $count = count($ratedReviews);
    if ($count === 0) {
        return [
            'rating' => '0.0',
            'reviews' => '0 đánh giá',
            'count' => 0,
            'verified_count' => 0,
            'verified_percent' => 0,
            'breakdown' => [
                ['label' => '5 sao', 'value' => 0],
                ['label' => '4 sao', 'value' => 0],
                ['label' => '3 sao', 'value' => 0],
                ['label' => '2 sao', 'value' => 0],
                ['label' => '1 sao', 'value' => 0],
            ],
        ];
    }

    $sum = 0.0;
    $starsCount = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $verifiedCount = 0;
    foreach ($ratedReviews as $review) {
        $rating = (float) ($review['rating'] ?? 0);
        $sum += $rating;
        $star = (int) round($rating);
        $star = max(1, min(5, $star));
        $starsCount[$star] = (int) ($starsCount[$star] ?? 0) + 1;
        if (!empty($review['is_verified']) || !empty($review['verified'])) {
            $verifiedCount++;
        }
    }

    $avg = number_format($sum / max(1, $count), 1, '.', '');
    $breakdown = [];
    for ($star = 5; $star >= 1; $star--) {
        $percent = (int) round(($starsCount[$star] / max(1, $count)) * 100);
        $breakdown[] = ['label' => $star . ' sao', 'value' => $percent];
    }

    return [
        'rating' => $avg,
        'reviews' => medical_directory_format_int($count) . ' đánh giá',
        'count' => $count,
        'verified_count' => $verifiedCount,
        'verified_percent' => (int) round(($verifiedCount / max(1, $count)) * 100),
        'breakdown' => $breakdown,
    ];
}

function medical_directory_facility_reviews_list_from_reviews(array $reviews, int $limit = 3): array
{
    $items = [];
    foreach (array_slice($reviews, 0, max(0, $limit)) as $review) {
        $content = (string) ($review['excerpt'] ?? '');
        if ($content === '') {
            $story = (array) ($review['story'] ?? []);
            $content = (string) ($story[0] ?? '');
        }
        $content = trim($content);

        $images = [];
        foreach ((array) ($review['thumbs'] ?? []) as $img) {
            $img = trim((string) $img);
            if ($img !== '') {
                $images[] = $img;
            }
        }
        if ($images === []) {
            $before = trim((string) ($review['before'] ?? $review['before_image_url'] ?? ''));
            $after = trim((string) ($review['after'] ?? $review['after_image_url'] ?? ''));
            if ($before !== '') {
                $images[] = $before;
            }
            if ($after !== '') {
                $images[] = $after;
            }
        }
        $images = array_slice($images, 0, 3);

        $items[] = [
            'author' => (string) ($review['author'] ?? $review['author_text'] ?? 'Khách hàng'),
            'verified' => (string) ($review['verified'] ?? ''),
            'is_verified' => !empty($review['is_verified']) || !empty($review['verified']),
            'location' => (string) ($review['location'] ?? $review['location_text'] ?? ''),
            'date' => (string) ($review['date'] ?? $review['review_date_text'] ?? ''),
            'rating' => (string) ($review['rating'] ?? '5.0'),
            'service' => (string) ($review['service'] ?? $review['service_text'] ?? ''),
            'source' => (string) ($review['source'] ?? $review['source_text'] ?? $review['condition_text'] ?? ''),
            'content' => $content,
            'likes' => (int) ($review['likes'] ?? $review['likes_count'] ?? 0),
            'comments' => (int) ($review['comments'] ?? $review['comments_count'] ?? 0),
            'images' => $images,
            'slug' => (string) ($review['slug'] ?? ''),
        ];
    }
    return $items;
}

function medical_directory_facility_with_linked_reviews(array $facility, bool $publishedOnly = true): array
{
    $facilityId = (int) ($facility['id'] ?? 0);
    $slug = trim((string) ($facility['slug'] ?? ''));
    if ($facilityId <= 0 && $slug === '') {
        return $facility;
    }
    $pdo = db();
    if (medical_directory_table_exists($pdo, 'medical_reviews')) {
        $sql = 'SELECT * FROM medical_reviews WHERE (facility_id = :facility_id OR (facility_id IS NULL AND facility_slug = :facility_slug))';
        if ($publishedOnly) $sql .= " AND status = 'published'";
        $sql .= ' ORDER BY display_order ASC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':facility_id' => $facilityId, ':facility_slug' => $slug]);
        $reviews = array_map('medical_directory_review_from_row', $stmt->fetchAll());
    } else {
        $reviews = medical_directory_reviews_for_facility_slug($slug, $publishedOnly);
    }
    $facility['review_summary'] = medical_directory_facility_review_summary_from_reviews($reviews);
    $facility['reviews_list'] = medical_directory_facility_reviews_list_from_reviews($reviews, 3);
    return $facility;
}

function medical_directory_facility_row_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        $items = medical_directory_facility_rows($publishedOnly);
        $item = $items[0] ?? null;
        return is_array($item) ? medical_directory_facility_with_linked_reviews($item, $publishedOnly) : null;
    }
    $pdo = db();
    if (!medical_directory_table_exists($pdo, 'medical_facilities')) {
        $items = medical_directory_facility_rows($publishedOnly);
        foreach ($items as $item) {
            if ((string) ($item['slug'] ?? '') === $slug) {
                return medical_directory_facility_with_linked_reviews($item, $publishedOnly);
            }
        }
        $fallback = $items[0] ?? null;
        return is_array($fallback) ? medical_directory_facility_with_linked_reviews($fallback, $publishedOnly) : null;
    }
    $sql = "SELECT * FROM medical_facilities WHERE slug = :slug";
    if ($publishedOnly) {
        $sql .= " AND status = 'published'";
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        return medical_directory_facility_with_linked_reviews(medical_directory_facility_from_row($row), $publishedOnly);
    }
    $items = medical_directory_facility_rows($publishedOnly);
    $item = $items[0] ?? null;
    return is_array($item) ? medical_directory_facility_with_linked_reviews($item, $publishedOnly) : null;
}

function medical_directory_review_rows(bool $publishedOnly = true, ?int $limit = null): array
{
    $pdo = db();
    if (!medical_directory_table_exists($pdo, 'medical_reviews')) {
        $items = medical_directory_default_reviews();
        if ($limit !== null && $limit > 0) {
            $items = array_slice($items, 0, $limit);
        }
        return array_map('medical_directory_review_from_row', $items);
    }
    $sql = "SELECT * FROM medical_reviews";
    if ($publishedOnly) {
        $sql .= " WHERE status = 'published'";
    }
    $sql .= ' ORDER BY display_order ASC, id DESC';
    if ($limit !== null && $limit > 0) {
        // The limit is an integer controlled by the caller; interpolating the
        // cast value keeps this helper compatible with MySQL's LIMIT syntax.
        $sql .= ' LIMIT ' . (int) $limit;
    }
    $rows = $pdo->query($sql)->fetchAll();
    if (!is_array($rows) || $rows === []) {
        return array_map('medical_directory_review_from_row', medical_directory_default_reviews());
    }
    return array_map('medical_directory_review_from_row', $rows);
}

function medical_directory_review_row_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        $items = medical_directory_review_rows($publishedOnly);
        return $items[0] ?? null;
    }
    $pdo = db();
    if (!medical_directory_table_exists($pdo, 'medical_reviews')) {
        $items = medical_directory_review_rows($publishedOnly);
        foreach ($items as $item) {
            if ((string) ($item['slug'] ?? '') === $slug) {
                return $item;
            }
        }
        return $items[0] ?? null;
    }
    $sql = "SELECT * FROM medical_reviews WHERE slug = :slug";
    if ($publishedOnly) {
        $sql .= " AND status = 'published'";
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        return medical_directory_review_from_row($row);
    }
    $items = medical_directory_review_rows($publishedOnly);
    return $items[0] ?? null;
}

function medical_directory_doctor_rows(bool $publishedOnly = true): array
{
    $pdo = db();
    if (!medical_directory_table_exists($pdo, 'medical_doctors')) {
        return array_map('medical_directory_doctor_from_row', medical_directory_default_doctors());
    }

    $sql = "SELECT * FROM medical_doctors";
    if ($publishedOnly) {
        $sql .= " WHERE status = 'published'";
    }
    $sql .= ' ORDER BY display_order ASC, id DESC';
    $rows = $pdo->query($sql)->fetchAll();
    if (!is_array($rows) || $rows === []) {
        return array_map('medical_directory_doctor_from_row', medical_directory_default_doctors());
    }
    return array_map('medical_directory_doctor_from_row', $rows);
}

function medical_directory_doctor_row_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        $items = medical_directory_doctor_rows($publishedOnly);
        return $items[0] ?? null;
    }

    $pdo = db();
    if (!medical_directory_table_exists($pdo, 'medical_doctors')) {
        $items = medical_directory_doctor_rows($publishedOnly);
        foreach ($items as $item) {
            if ((string) ($item['slug'] ?? '') === $slug) {
                return $item;
            }
        }
        return $items[0] ?? null;
    }

    $sql = "SELECT * FROM medical_doctors WHERE slug = :slug";
    if ($publishedOnly) {
        $sql .= " AND status = 'published'";
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        return medical_directory_doctor_from_row($row);
    }
    $items = medical_directory_doctor_rows($publishedOnly);
    return $items[0] ?? null;
}

function medical_directory_refresh_facility_aggregates(PDO $pdo, string $facilitySlug): void
{
    $facilitySlug = trim($facilitySlug);
    if ($facilitySlug === '') {
        return;
    }
    if (!medical_directory_table_exists($pdo, 'medical_facilities') || !medical_directory_table_exists($pdo, 'medical_reviews')) {
        return;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c, AVG(rating) AS avg_rating FROM medical_reviews WHERE facility_slug = :slug AND status = 'published' AND rating > 0");
    $stmt->execute([':slug' => $facilitySlug]);
    $row = $stmt->fetch();

    $count = 0;
    $avg = 0.0;
    if (is_array($row)) {
        $count = (int) ($row['c'] ?? 0);
        $avg = (float) ($row['avg_rating'] ?? 0);
    }
    $rating = number_format($avg, 1, '.', '');

    $stmt = $pdo->prepare("UPDATE medical_facilities SET reviews_count = :reviews_count, rating = :rating WHERE slug = :slug LIMIT 1");
    $stmt->execute([
        ':reviews_count' => $count,
        ':rating' => $rating,
        ':slug' => $facilitySlug,
    ]);
    medical_search_cache_invalidate();
}
