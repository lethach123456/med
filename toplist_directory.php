<?php
declare(strict_types=1);

require_once __DIR__ . '/medical_search_cache.php';

function toplist_directory_ensure_tables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS medical_toplists (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(220) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            language_code VARCHAR(5) NOT NULL DEFAULT 'vi',
            translation_of_id INT UNSIGNED NULL,
            entity_type VARCHAR(12) NOT NULL DEFAULT 'facility',
            excerpt TEXT NULL,
            content LONGTEXT NULL,
            featured_image_url VARCHAR(500) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_medical_toplists_slug (slug),
            UNIQUE KEY idx_medical_toplists_translation_parent (translation_of_id),
            KEY idx_medical_toplists_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    medreview_ensure_translation_columns($pdo, 'medical_toplists');
    if (!$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='medical_toplists' AND column_name='entity_type'")->fetchColumn()) {
        try { $pdo->exec("ALTER TABLE medical_toplists ADD COLUMN entity_type VARCHAR(12) NOT NULL DEFAULT 'facility'"); }
        catch (Throwable $e) {
            if (!$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='medical_toplists' AND column_name='entity_type'")->fetchColumn()) throw $e;
        }
    }

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
    $pdo->exec("CREATE TABLE IF NOT EXISTS medical_toplist_doctors (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        toplist_id INT UNSIGNED NOT NULL, doctor_id INT UNSIGNED NOT NULL,
        rank_order INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id), UNIQUE KEY uniq_toplist_doctor (toplist_id, doctor_id),
        KEY idx_toplist_doctors_order (toplist_id, rank_order),
        CONSTRAINT fk_toplist_doctors_toplist FOREIGN KEY (toplist_id) REFERENCES medical_toplists(id) ON DELETE CASCADE,
        CONSTRAINT fk_toplist_doctors_doctor FOREIGN KEY (doctor_id) REFERENCES medical_doctors(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function toplist_directory_sync_facilities(PDO $pdo, int $toplistId, array $facilityIds): void
{
    toplist_directory_sync_members($pdo, $toplistId, 'facility', $facilityIds);
}

function toplist_directory_entity_type(array $payload, string $fallback = 'facility'): string
{
    $type = $payload['entity_type'] ?? $payload['list_type'] ?? null;
    if ($type === null) {
        $hasDoctors = array_key_exists('doctors', $payload) || array_key_exists('bac_si', $payload);
        $hasFacilities = array_key_exists('facilities', $payload) || array_key_exists('co_so', $payload) || array_key_exists('co_so_y_te', $payload);
        if ($hasDoctors && $hasFacilities) throw new InvalidArgumentException('Một Toplist chỉ xếp hạng cơ sở hoặc bác sĩ; hãy chọn một danh sách.');
        $type = $hasDoctors ? 'doctor' : ($hasFacilities ? 'facility' : $fallback);
    }
    if (!in_array($type, ['facility', 'doctor'], true)) throw new InvalidArgumentException('entity_type phải là facility hoặc doctor.');
    $other = $type === 'doctor' ? ($payload['facilities'] ?? $payload['co_so'] ?? $payload['co_so_y_te'] ?? []) : ($payload['doctors'] ?? $payload['bac_si'] ?? []);
    if ($other !== []) throw new InvalidArgumentException('Danh sách JSON không khớp loại Toplist đã chọn.');
    return $type;
}

function toplist_directory_member_config(string $type): array
{
    if ($type === 'doctor') return ['table' => 'medical_doctors', 'links' => 'medical_toplist_doctors', 'key' => 'doctor_id', 'label' => 'bác sĩ'];
    if ($type === 'facility') return ['table' => 'medical_facilities', 'links' => 'medical_toplist_facilities', 'key' => 'facility_id', 'label' => 'cơ sở'];
    throw new InvalidArgumentException('Loại liên kết Toplist không hợp lệ.');
}

/** Atomic replacement; IDs, publication and language are validated before any link is removed. */
function toplist_directory_sync_members(PDO $pdo, int $toplistId, string $type, array $memberIds): void
{
    $config = toplist_directory_member_config($type);
    $ids = [];
    foreach ($memberIds as $value) {
        if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) || (int) $value <= 0) throw new InvalidArgumentException('ID liên kết phải là số nguyên dương.');
        if (!in_array((int) $value, $ids, true)) $ids[] = (int) $value;
    }
    if (count($ids) > 1000) throw new InvalidArgumentException('Toplist tối đa 1.000 hồ sơ.');
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT language_code FROM medical_toplists WHERE id=:id FOR UPDATE');
        $lock->execute([':id' => $toplistId]); $locale = $lock->fetchColumn();
        if ($locale === false) throw new InvalidArgumentException('Không tìm thấy Toplist.');
        $lookup = $pdo->prepare("SELECT id FROM {$config['table']} WHERE id=:id AND status='published' AND language_code=:locale");
        foreach ($ids as $id) {
            $lookup->execute([':id' => $id, ':locale' => $locale]);
            if (!$lookup->fetchColumn()) throw new InvalidArgumentException('Hồ sơ ' . $config['label'] . ' #' . $id . ' không tồn tại, chưa xuất bản hoặc sai ngôn ngữ.');
        }
        foreach (['medical_toplist_facilities', 'medical_toplist_doctors'] as $table) {
            $pdo->prepare("DELETE FROM {$table} WHERE toplist_id=:id")->execute([':id' => $toplistId]);
        }
        $insert = $pdo->prepare("INSERT INTO {$config['links']} (toplist_id,{$config['key']},rank_order) VALUES (:toplist_id,:member_id,:rank)");
        foreach ($ids as $rank => $id) $insert->execute([':toplist_id' => $toplistId, ':member_id' => $id, ':rank' => $rank + 1]);
        $pdo->prepare('UPDATE medical_toplists SET entity_type=:type, updated_at=CURRENT_TIMESTAMP WHERE id=:id')->execute([':type' => $type, ':id' => $toplistId]);
        if ($ownsTransaction) { $pdo->commit(); medical_search_cache_invalidate(); }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function toplist_directory_linked_rows(PDO $pdo, array $toplist, int $limit = 0): array
{
    $config = toplist_directory_member_config(toplist_directory_entity_type($toplist));
    $limitSql = $limit > 0 ? ' LIMIT ' . min(1000, $limit) : '';
    $stmt = $pdo->prepare("SELECT m.*, r.rank_order FROM {$config['links']} r JOIN {$config['table']} m ON m.id=r.{$config['key']}
        WHERE r.toplist_id=:id AND m.status='published' AND m.language_code=:locale ORDER BY r.rank_order,r.id{$limitSql}");
    $stmt->execute([':id' => (int) $toplist['id'], ':locale' => $toplist['language_code'] ?? 'vi']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function toplist_directory_unique_slug(PDO $pdo, string $table, string $value): string
{
    if (!in_array($table, ['medical_toplists', 'medical_facilities', 'medical_doctors'], true)) throw new InvalidArgumentException('Bảng slug không hợp lệ.');
    $base = substr(slugify($value), 0, 170) ?: bin2hex(random_bytes(6));
    $candidate = $base; $suffix = 2;
    $check = $pdo->prepare("SELECT 1 FROM {$table} WHERE slug=:slug LIMIT 1");
    while (true) {
        $check->execute([':slug' => $candidate]);
        if (!$check->fetchColumn()) return $candidate;
        $candidate = $base . '-' . $suffix++;
    }
}

/** Link an existing profile without editing it, or create a minimal unverified Vietnamese profile. */
function toplist_directory_resolve_member(PDO $pdo, string $type, array $item, string $locale = 'vi'): array
{
    $config = toplist_directory_member_config($type);
    $id = $item[$config['key']] ?? $item['id'] ?? 0;
    if ((!is_int($id) && !(is_string($id) && ctype_digit($id))) || (int) $id < 0) throw new InvalidArgumentException($config['key'] . ' phải là số nguyên không âm.');
    $text = static function (string $key, int $limit, array $aliases = []) use ($item): string {
        $value = $item[$key] ?? null;
        foreach ($aliases as $alias) if ($value === null) $value = $item[$alias] ?? null;
        if ($value === null) return '';
        if (!is_string($value) || mb_strlen($value) > $limit) throw new InvalidArgumentException($key . ' phải là chuỗi tối đa ' . $limit . ' ký tự.');
        return trim($value);
    };
    $name = $text('name', 160, ['ten']);
    if ((int) $id > 0) {
        $lookup = $pdo->prepare("SELECT id,name FROM {$config['table']} WHERE id=:id AND status='published' AND language_code=:locale");
        $lookup->execute([':id' => $id, ':locale' => $locale]); $row = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($name !== '' && mb_strtolower($name) !== mb_strtolower(trim($row['name'])))) throw new InvalidArgumentException('ID/tên/ngôn ngữ ' . $config['label'] . ' không khớp hồ sơ đã xuất bản.');
        return ['id' => (int) $id, 'created' => false];
    }
    if ($locale !== 'vi') throw new InvalidArgumentException('Toplist tiếng Anh chỉ liên kết ID hồ sơ tiếng Anh đã xuất bản; không tạo hồ sơ dịch giả.');
    if ($name === '') throw new InvalidArgumentException('Hồ sơ cần name hoặc ID có sẵn.');
    $city = $text('city', 120, ['province', 'tinh_thanh']);
    $address = $text('address_text', 255, ['address', 'dia_chi']);
    $phone = $text('phone_text', 80, ['phone']);
    $website = $text('website_url', 4000, ['website']);
    $image = $text('image_url', 255, ['image']);
    $website = medical_doctor_http_url($website) ?? '';
    $image = preg_match('#^/(?!/)[a-zA-Z0-9/_ .%-]+$#D', $image) ? $image : (medical_doctor_http_url($image) ?? '');
    $fields = ['name' => $name, 'city' => $city, 'address_text' => $address, 'phone_text' => $phone, 'website_url' => $website, 'image_url' => $image];
    if ($type === 'doctor') {
        $fields['title_text'] = $text('title_text', 190, ['title']);
        $fields['specialty_text'] = $text('specialty_text', 160, ['specialty', 'chuyen_khoa']);
        $fields['facility_name'] = $text('facility_name', 160, ['co_so_cong_tac']);
        $fields['facility_slug'] = $text('facility_slug', 191);
        if ($fields['facility_slug'] !== '') {
            $facility = $pdo->prepare("SELECT name FROM medical_facilities WHERE slug=:slug AND status='published' AND language_code='vi'");
            $facility->execute([':slug' => $fields['facility_slug']]); $facilityName = $facility->fetchColumn();
            if (!$facilityName || ($fields['facility_name'] !== '' && $fields['facility_name'] !== $facilityName)) throw new InvalidArgumentException('Cơ sở công tác không khớp facility_slug.');
            $fields['facility_name'] = $facilityName;
        }
        if ($fields['specialty_text'] === '' || ($city === '' && $fields['facility_name'] === '')) throw new InvalidArgumentException('Bác sĩ mới cần chuyên khoa và thành phố hoặc nơi công tác để tránh trùng người.');
        $lookup = $pdo->prepare("SELECT id FROM medical_doctors WHERE name=:name AND specialty_text=:specialty AND city=:city AND facility_name=:facility AND language_code='vi' LIMIT 2");
        $lookup->execute([':name' => $name, ':specialty' => $fields['specialty_text'], ':city' => $city, ':facility' => $fields['facility_name']]);
    } else {
        $fields['category'] = $text('category', 120, ['group', 'nhom']) ?: 'Cơ sở y tế';
        $fields['price_text'] = $text('price_text', 120, ['price']);
        // Preserve the old facility API's name+city/address/website matching, but reject ambiguous identities.
        $lookup = $pdo->prepare("SELECT id FROM medical_facilities WHERE name=:name AND city=:city AND language_code='vi' LIMIT 2");
        $lookup->execute([':name' => $name, ':city' => $city]);
        $matchesByCity = $city === '' ? [] : $lookup->fetchAll(PDO::FETCH_COLUMN);
        if ($matchesByCity !== []) {
            if (count($matchesByCity) > 1) throw new InvalidArgumentException('Có nhiều cơ sở cùng tên trong thành phố; hãy chỉ định facility_id.');
            return toplist_directory_resolve_member($pdo, 'facility', ['facility_id' => (int) $matchesByCity[0], 'name' => $name], $locale);
        }
        $clue = $address !== '' ? 'address_text' : 'website_url';
        $lookup = $pdo->prepare("SELECT id FROM medical_facilities WHERE name=:name AND COALESCE({$clue},'')=:clue AND language_code='vi' LIMIT 2");
        $lookup->execute([':name' => $name, ':clue' => $address !== '' ? $address : $website]);
    }
    $matches = $lookup->fetchAll(PDO::FETCH_COLUMN);
    if (count($matches) > 1) throw new InvalidArgumentException('Có nhiều hồ sơ trùng thông tin; hãy chỉ định ID chính xác.');
    if ($matches !== []) {
        $status = $pdo->prepare("SELECT status FROM {$config['table']} WHERE id=:id"); $status->execute([':id' => $matches[0]]);
        if ($status->fetchColumn() !== 'published') throw new InvalidArgumentException('Hồ sơ đã có nhưng chưa xuất bản; không tạo bản trùng.');
        return ['id' => (int) $matches[0], 'created' => false];
    }
    $fields['slug'] = toplist_directory_unique_slug($pdo, $config['table'], $name . '-' . ($type === 'doctor' ? $fields['specialty_text'] . '-' . $city : $address));
    $fields['status'] = 'published';
    $fields['verified'] = 0;
    $keys = array_keys($fields);
    $pdo->prepare("INSERT INTO {$config['table']} (`" . implode('`,`', $keys) . "`) VALUES (:" . implode(',:', $keys) . ')')->execute($fields);
    return ['id' => (int) $pdo->lastInsertId(), 'created' => true];
}

function toplist_directory_import_members(PDO $pdo, int $toplistId, string $type, array $items): array
{
    if (!$pdo->inTransaction()) throw new LogicException('Import danh sách cần chạy trong transaction.');
    if (!array_is_list($items) || count($items) > 1000) throw new InvalidArgumentException('Danh sách cần là JSON array tối đa 1.000 hồ sơ.');
    $stmt = $pdo->prepare('SELECT language_code,entity_type FROM medical_toplists WHERE id=:id FOR UPDATE');
    $stmt->execute([':id' => $toplistId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new InvalidArgumentException('Không tìm thấy Toplist.');
    $ranked = []; $created = [];
    foreach ($items as $index => $item) {
        if (!is_array($item) || array_is_list($item)) throw new InvalidArgumentException('Mục #' . ($index + 1) . ' phải là object.');
        $rank = $item['rank_order'] ?? $item['rank'] ?? $index + 1;
        if ((!is_int($rank) && !(is_string($rank) && ctype_digit($rank))) || (int) $rank < 1) throw new InvalidArgumentException('rank_order phải là số nguyên dương.');
        $resolved = toplist_directory_resolve_member($pdo, $type, $item, $row['language_code']);
        $ranked[] = ['id' => $resolved['id'], 'rank' => (int) $rank, 'index' => $index];
        if ($resolved['created']) $created[] = $resolved['id'];
    }
    usort($ranked, static fn(array $a, array $b): int => ($a['rank'] <=> $b['rank']) ?: ($a['index'] <=> $b['index']));
    $ids = array_values(array_unique(array_column($ranked, 'id')));
    toplist_directory_sync_members($pdo, $toplistId, $type, $ids);
    return ['ids' => $ids, 'created_ids' => $created];
}

function toplist_directory_import_article(PDO $pdo, array $payload, string $fallbackType = 'facility', bool $titleOnly = false): array
{
    $title = $payload['title'] ?? $payload['tieu_de'] ?? '';
    if (!is_string($title) || trim($title) === '' || mb_strlen($title) > 220) throw new InvalidArgumentException('title là bắt buộc và tối đa 220 ký tự.');
    $type = toplist_directory_entity_type($payload, $fallbackType);
    $key = $type === 'doctor' ? 'doctors' : 'facilities';
    $items = $type === 'doctor' ? ($payload['doctors'] ?? $payload['bac_si'] ?? null) : ($payload['facilities'] ?? $payload['co_so'] ?? $payload['co_so_y_te'] ?? null);
    if (!$titleOnly && (!is_array($items) || !array_is_list($items))) throw new InvalidArgumentException('JSON đầy đủ cần danh sách ' . $key . '.');
    $values = ['title' => trim($title), 'entity_type' => $type, 'status' => $titleOnly ? 'draft' : (($payload['status'] ?? 'draft') === 'published' ? 'published' : 'draft')];
    foreach (['excerpt' => ['excerpt', 'mo_ta'], 'content' => ['content', 'noi_dung'], 'featured_image_url' => ['featured_image_url', 'image_url', 'anh']] as $field => $aliases) {
        $value = '';
        if (!$titleOnly) foreach ($aliases as $alias) if (isset($payload[$alias])) { $value = $payload[$alias]; break; }
        if (!is_string($value)) throw new InvalidArgumentException($field . ' phải là chuỗi.');
        $values[$field] = $value;
    }
    $slug = $payload['slug'] ?? $title;
    if (!is_string($slug)) throw new InvalidArgumentException('slug phải là chuỗi.');
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $values['slug'] = toplist_directory_unique_slug($pdo, 'medical_toplists', $slug);
        $pdo->prepare('INSERT INTO medical_toplists (title,slug,entity_type,excerpt,content,featured_image_url,status) VALUES (:title,:slug,:entity_type,:excerpt,:content,:featured_image_url,:status)')->execute($values);
        $id = (int) $pdo->lastInsertId();
        $result = $titleOnly ? ['ids' => [], 'created_ids' => []] : toplist_directory_import_members($pdo, $id, $type, $items);
        if ($ownsTransaction) { $pdo->commit(); medical_search_cache_invalidate(); }
        return ['id' => $id, 'entity_type' => $type] + $result;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function toplist_directory_default_prompt(): string
{
    return "Bạn là biên tập viên MedReview. Tìm danh sách {{entity_label}} phù hợp cho Toplist '{{title}}' (ID {{id}}).\n"
        . "Nội dung hiện có: {{excerpt}} {{content}}\n"
        . "Đối chiếu nguồn chính thức, chỉ chọn đúng chuyên khoa/địa phương/tiêu chí của bài. Không bịa danh tính, bằng cấp, nơi công tác, liên hệ, đánh giá hoặc giá. Không xem thứ hạng là khẳng định chất lượng điều trị.\n"
        . "Loại bài: {{entity_type}}; khóa danh sách: {{member_key}}. Dùng ID hồ sơ được cấp; chưa biết ID thì 0, không tự tạo ID. Chỉ trả JSON trong đúng một block code json.\nKhung JSON:\n{{output_template}}";
}

function toplist_directory_research_prompt(string $template, array $toplist): string
{
    $type = toplist_directory_entity_type($toplist);
    $doctor = $type === 'doctor';
    $key = $doctor ? 'doctors' : 'facilities';
    $example = $doctor
        ? ['doctor_id' => 0, 'name' => '', 'title_text' => '', 'specialty_text' => '', 'city' => '', 'facility_name' => '', 'address' => '', 'phone' => '', 'website' => '', 'rank_order' => 1]
        : ['facility_id' => 0, 'name' => '', 'category' => 'Cơ sở y tế', 'city' => '', 'address' => '', 'phone' => '', 'website' => '', 'rank_order' => 1];
    $output = medical_directory_json_encode(['toplist_id' => (int) $toplist['id'], 'entity_type' => $type, $key => [$example]]);
    // An old facility-only template cannot drive a doctor Toplist.
    if (trim($template) === '' || ($doctor && !str_contains($template, '{{entity_type}}') && !str_contains($template, '{{member_key}}'))) $template = toplist_directory_default_prompt();
    $rendered = medical_directory_ai_prompt_render_template($template, [
        'id' => (string) $toplist['id'], 'toplist_id' => (string) $toplist['id'], 'title' => $toplist['title'], 'name' => $toplist['title'],
        'excerpt' => $toplist['excerpt'] ?? '', 'content' => $toplist['content'] ?? '', 'entity_type' => $type,
        'entity_label' => $doctor ? 'bác sĩ' : 'cơ sở y tế', 'member_key' => $key, 'output_template' => $output,
    ]);
    return $rendered . "\n\nMEDREVIEW_TOPLIST_MEMBERS_CONTRACT_V1:\nGiữ toplist_id, entity_type={$type}; chỉ trả danh sách {$key}, không đổi sang loại khác. Không tạo rating/verified.\n"
        . ($doctor ? "Bác sĩ mới cần name, specialty_text và city hoặc facility_name; bác sĩ trùng tên phải đối chiếu nơi công tác. Không dùng giờ mở cửa cơ sở làm lịch bác sĩ.\n" : '')
        . "rank_order nguyên dương bắt đầu từ 1. Các URL phải HTTP(S) thô, không Markdown. ID hồ sơ đã có phải đúng người/cơ sở và cùng ngôn ngữ của bài.\n"
        . "Không tìm được hồ sơ đáng tin cậy thì trả {$key}:[], không bịa cho đủ số lượng; hệ thống không lưu danh sách rỗng.\nKhung JSON bắt buộc:\n{$output}\n"
        . "BẮT BUỘC trả đúng một JSON object trong block code ```json ... ```; không thêm lời dẫn. Nhắc lại: JSON phải nằm trong block code json.";
}
