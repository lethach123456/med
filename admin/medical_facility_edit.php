<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../medical_directory.php';

admin_require_login();

$pdo = db();
medical_directory_ensure_tables($pdo);
medical_directory_seed_defaults($pdo);

$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id'] ?? 0);
$isEdit = $id > 0;
$oldSlug = '';

function facility_pretty_json($value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return is_string($json) ? $json : '[]';
}

function facility_lines_to_text(array $items): string
{
    $lines = [];
    foreach ($items as $item) {
        $text = trim((string) $item);
        if ($text !== '') {
            $lines[] = $text;
        }
    }
    return implode("\n", $lines);
}

function facility_text_to_lines(string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
    $result = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line !== '') {
            $result[] = $line;
        }
    }
    return $result;
}

function facility_decode_json_field(string $label, string $raw, array &$errors, array $fallback = []): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return $fallback;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $errors[] = $label . ' phải là JSON hợp lệ.';
        return $fallback;
    }
    return $decoded;
}

$values = [
    'name' => '',
    'slug' => '',
    'category' => '',
    'city' => '',
    'subtitle' => '',
    'content' => '',
    'seo_title' => '',
    'seo_description' => '',
    'seo_keywords' => '',
    'verified' => '1',
    'rating' => '',
    'reviews_count' => '0',
    'followers_count' => '0',
    'hours_text' => '',
    'address_text' => '',
    'phone_text' => '',
    'website_url' => '',
    'price_text' => '',
    'price_table_html' => '',
    'image_url' => '',
    'images_label' => '',
    'featured_services_lines' => '',
    'tags_lines' => '',
    'gallery_lines' => '',
    'intro_lines' => '',
    'utilities_lines' => '',
    'status' => 'published',
    'display_order' => '0',
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM medical_facilities WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_toast_set('danger', 'Cơ sở y tế không tồn tại.', 'fa-solid fa-triangle-exclamation');
        header('Location: /admin/medical_facilities.php');
        exit;
    }
    $oldSlug = (string) ($row['slug'] ?? '');
    $item = medical_directory_facility_from_row($row);
    $values = [
        'name' => (string) $item['name'],
        'slug' => (string) $item['slug'],
        'category' => (string) $item['category'],
        'city' => (string) $item['city'],
        'subtitle' => (string) $item['subtitle'],
        'content' => (string) ($row['content'] ?? ''),
        'seo_title' => (string) ($row['seo_title'] ?? ''),
        'seo_description' => (string) ($row['seo_description'] ?? ''),
        'seo_keywords' => (string) ($row['seo_keywords'] ?? ''),
        'verified' => !empty($item['is_verified']) ? '1' : '0',
        'rating' => (string) $item['rating'],
        'reviews_count' => (string) $item['reviews_count'],
        'followers_count' => (string) $item['followers_count'],
        'hours_text' => (string) $item['hours_text'],
        'address_text' => (string) $item['address_text'],
        'phone_text' => (string) $item['phone_text'],
        'website_url' => (string) $item['website_url'],
        'price_text' => (string) $item['price_text'],
        'price_table_html' => (string) ($row['price_table_html'] ?? ''),
        'image_url' => (string) $item['image_url'],
        'images_label' => (string) $item['images_label'],
        'featured_services_lines' => facility_lines_to_text((array) $item['featured_services']),
        'tags_lines' => facility_lines_to_text((array) $item['tags']),
        'gallery_lines' => facility_lines_to_text((array) $item['gallery']),
        'intro_lines' => (string) ($row['content'] ?? ''),
        'utilities_lines' => facility_lines_to_text((array) $item['utilities']),
        'status' => (string) $item['status'],
        'display_order' => (string) $item['display_order'],
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $key) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    // Trường Giới thiệu là nội dung bài viết chính, lưu trực tiếp vào cột content.
    $values['content'] = $values['intro_lines'];

    if ($values['name'] === '') {
        $errors[] = 'Vui lòng nhập tên cơ sở y tế.';
    }
    if (mb_strlen($values['name']) > 160) {
        $errors[] = 'Tên cơ sở y tế quá dài.';
    }
    foreach ([
        'seo_title' => ['label' => 'SEO Title', 'max' => 160],
        'seo_description' => ['label' => 'SEO Description', 'max' => 300],
        'seo_keywords' => ['label' => 'Từ khóa SEO', 'max' => 255],
    ] as $seoField => $rule) {
        if (mb_strlen($values[$seoField]) > $rule['max']) {
            $errors[] = $rule['label'] . ' không được vượt quá ' . $rule['max'] . ' ký tự.';
        }
    }

    $values['status'] = in_array($values['status'], ['draft', 'published'], true) ? $values['status'] : 'draft';
    $values['verified'] = $values['verified'] === '1' ? '1' : '0';
    $values['display_order'] = (string) max(0, (int) $values['display_order']);

    $ratingFloat = (float) $values['rating'];
    if ($ratingFloat < 0 || $ratingFloat > 5) {
        $errors[] = 'Điểm đánh giá phải trong khoảng 0 - 5.';
    }

    $slugInput = $values['slug'] !== '' ? $values['slug'] : $values['name'];
    $values['slug'] = unique_slug($pdo, 'medical_facilities', $slugInput, $isEdit ? $id : null);

    $featuredServices = facility_text_to_lines($values['featured_services_lines']);
    $tags = facility_text_to_lines($values['tags_lines']);
    $gallery = facility_text_to_lines($values['gallery_lines']);
    $intro = facility_text_to_lines($values['intro_lines']);
    $utilities = facility_text_to_lines($values['utilities_lines']);

    if ($errors === []) {
        if ($isEdit) {
            $stmt = $pdo->prepare(
                "UPDATE medical_facilities SET
                    slug = :slug,
                    name = :name,
                    category = :category,
                    city = :city,
                    subtitle = :subtitle,
                    content = :content,
                    seo_title = :seo_title,
                    seo_description = :seo_description,
                    seo_keywords = :seo_keywords,
                    verified = :verified,
                    rating = :rating,
                    reviews_count = :reviews_count,
                    followers_count = :followers_count,
                    hours_text = :hours_text,
                    address_text = :address_text,
                    phone_text = :phone_text,
                    website_url = :website_url,
                    price_text = :price_text,
                    price_table_html = :price_table_html,
                    image_url = :image_url,
                    images_label = :images_label,
                    featured_services_json = :featured_services_json,
                    tags_json = :tags_json,
                    gallery_json = :gallery_json,
                    intro_json = :intro_json,
                    utilities_json = :utilities_json,
                    status = :status,
                    display_order = :display_order
                 WHERE id = :id"
            );
            $stmt->execute([
                ':slug' => $values['slug'],
                ':name' => $values['name'],
                ':category' => $values['category'],
                ':city' => $values['city'],
                ':subtitle' => $values['subtitle'],
                ':content' => $values['content'],
                ':seo_title' => $values['seo_title'] !== '' ? $values['seo_title'] : null,
                ':seo_description' => $values['seo_description'] !== '' ? $values['seo_description'] : null,
                ':seo_keywords' => $values['seo_keywords'] !== '' ? $values['seo_keywords'] : null,
                ':verified' => (int) $values['verified'],
                ':rating' => number_format($ratingFloat, 1, '.', ''),
                ':reviews_count' => max(0, (int) $values['reviews_count']),
                ':followers_count' => max(0, (int) $values['followers_count']),
                ':hours_text' => $values['hours_text'],
                ':address_text' => $values['address_text'],
                ':phone_text' => $values['phone_text'],
                ':website_url' => $values['website_url'],
                ':price_text' => $values['price_text'],
                ':price_table_html' => $values['price_table_html'],
                ':image_url' => $values['image_url'],
                ':images_label' => $values['images_label'],
                ':featured_services_json' => medical_directory_json_encode($featuredServices),
                ':tags_json' => medical_directory_json_encode($tags),
                ':gallery_json' => medical_directory_json_encode($gallery),
                ':intro_json' => medical_directory_json_encode($intro),
                ':utilities_json' => medical_directory_json_encode($utilities),
                ':status' => $values['status'],
                ':display_order' => (int) $values['display_order'],
                ':id' => $id,
            ]);
            // This facility row is already persisted. Invalidate before the
            // follow-up review synchronization so an exception there cannot
            // leave public search showing the old profile until TTL expiry.
            medical_search_cache_invalidate();

            if ($values['slug'] !== '' && $values['slug'] !== $oldSlug) {
                $stmt = $pdo->prepare('UPDATE medical_reviews SET facility_slug = :new_slug, facility_name = :facility_name WHERE facility_slug = :old_slug');
                $stmt->execute([
                    ':new_slug' => $values['slug'],
                    ':facility_name' => $values['name'],
                    ':old_slug' => $oldSlug,
                ]);
                medical_directory_refresh_facility_aggregates($pdo, $values['slug']);
            } elseif ($values['slug'] !== '') {
                $stmt = $pdo->prepare('UPDATE medical_reviews SET facility_name = :facility_name WHERE facility_slug = :facility_slug');
                $stmt->execute([
                    ':facility_name' => $values['name'],
                    ':facility_slug' => $values['slug'],
                ]);
                medical_directory_refresh_facility_aggregates($pdo, $values['slug']);
            }

            flash_toast_set('success', 'Đã lưu cơ sở y tế.', 'fa-solid fa-circle-check');
            header('Location: /admin/medical_facility_edit.php?id=' . $id);
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO medical_facilities (
                slug, name, category, city, subtitle, content, seo_title, seo_description, seo_keywords, verified, rating, reviews_count, followers_count,
                hours_text, address_text, phone_text, website_url, price_text, price_table_html, image_url, images_label,
                featured_services_json, tags_json, gallery_json, intro_json, utilities_json, status, display_order
            ) VALUES (
                :slug, :name, :category, :city, :subtitle, :content, :seo_title, :seo_description, :seo_keywords, :verified, :rating, :reviews_count, :followers_count,
                :hours_text, :address_text, :phone_text, :website_url, :price_text, :price_table_html, :image_url, :images_label,
                :featured_services_json, :tags_json, :gallery_json, :intro_json, :utilities_json, :status, :display_order
            )"
        );
        $stmt->execute([
            ':slug' => $values['slug'],
            ':name' => $values['name'],
            ':category' => $values['category'],
            ':city' => $values['city'],
            ':subtitle' => $values['subtitle'],
            ':content' => $values['content'],
            ':seo_title' => $values['seo_title'] !== '' ? $values['seo_title'] : null,
            ':seo_description' => $values['seo_description'] !== '' ? $values['seo_description'] : null,
            ':seo_keywords' => $values['seo_keywords'] !== '' ? $values['seo_keywords'] : null,
            ':verified' => (int) $values['verified'],
            ':rating' => number_format($ratingFloat, 1, '.', ''),
            ':reviews_count' => max(0, (int) $values['reviews_count']),
            ':followers_count' => max(0, (int) $values['followers_count']),
            ':hours_text' => $values['hours_text'],
            ':address_text' => $values['address_text'],
            ':phone_text' => $values['phone_text'],
            ':website_url' => $values['website_url'],
            ':price_text' => $values['price_text'],
            ':price_table_html' => $values['price_table_html'],
            ':image_url' => $values['image_url'],
            ':images_label' => $values['images_label'],
            ':featured_services_json' => medical_directory_json_encode($featuredServices),
            ':tags_json' => medical_directory_json_encode($tags),
            ':gallery_json' => medical_directory_json_encode($gallery),
            ':intro_json' => medical_directory_json_encode($intro),
            ':utilities_json' => medical_directory_json_encode($utilities),
            ':status' => $values['status'],
            ':display_order' => (int) $values['display_order'],
        ]);
        $newId = (int) $pdo->lastInsertId();
        medical_search_cache_invalidate();
        flash_toast_set('success', 'Đã tạo cơ sở y tế.', 'fa-solid fa-circle-check');
        header('Location: /admin/medical_facility_edit.php?id=' . $newId);
        exit;
    }
}

$adminPageTitle = $isEdit ? 'Admin • Sửa cơ sở y tế' : 'Admin • Thêm cơ sở y tế';
$adminHeaderTitle = $isEdit ? 'Sửa cơ sở y tế' : 'Thêm cơ sở y tế';
$adminHeaderSubtitle = 'Quản lý dữ liệu cơ sở y tế cho frontend MedReview';
$adminActive = 'medical-facilities';
require __DIR__ . '/_layout_start.php';

?>
<style>
  [data-ckeditor-wrap] { background:#f8fbff; border:1px solid #dbe7f5; border-radius:1rem; padding:1rem; }
  [data-ckeditor-target] { min-height:520px !important; }
  [data-ckeditor-target] .ck-editor__editable { min-height:470px; }
  div:has(> #rating), div:has(> #reviews_count), div:has(> #followers_count) { display:none; }
</style>
<?php

$mediaFieldId = 'medical_facility_image';
$mediaFieldLabel = 'Ảnh đại diện';
$mediaFieldName = 'image_url';
$mediaFieldValue = $values['image_url'];

$mediaGalleryId = 'medical_facility_gallery';
$mediaGalleryLabel = 'Gallery chi tiết';
$mediaGalleryName = 'gallery_lines';
$mediaGalleryValue = $values['gallery_lines'];
?>

<div class="row g-3">
  <div class="col-12">
    <div class="card border-0 shadow-soft">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <div class="h5 mb-1"><?php echo $isEdit ? 'Cập nhật cơ sở y tế' : 'Tạo cơ sở y tế'; ?></div>
            <div class="text-secondary">Các trường bên dưới bám theo đúng frontend `co-so-y-te.php` và `co-so-y-te-chi-tiet.php`.</div>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(admin_url('medical_facilities.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-left me-2"></i>Quay lại</a>
          </div>
        </div>

        <?php if ($errors !== []): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
              <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" class="row g-3">
          <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
          <?php endif; ?>

          <div class="col-12 col-lg-8">
            <div class="row g-3">
              <div class="col-12 col-md-8">
                <label class="form-label" for="name">Tên cơ sở y tế</label>
                <input id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($values['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="status">Trạng thái</label>
                <select id="status" name="status" class="form-select">
                  <option value="draft" <?php echo $values['status'] === 'draft' ? 'selected' : ''; ?>>draft</option>
                  <option value="published" <?php echo $values['status'] === 'published' ? 'selected' : ''; ?>>published</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="slug">Slug</label>
                <input id="slug" name="slug" class="form-control mono" value="<?php echo htmlspecialchars($values['slug'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="category">Nhóm</label>
                <input id="category" name="category" class="form-control" value="<?php echo htmlspecialchars($values['category'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="city">Khu vực</label>
                <input id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($values['city'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12">
                <label class="form-label" for="subtitle">Mô tả ngắn</label>
                <textarea id="subtitle" name="subtitle" class="form-control" rows="3"><?php echo htmlspecialchars($values['subtitle'], ENT_QUOTES, 'UTF-8'); ?></textarea>
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label" for="verified">Xác thực</label>
                <select id="verified" name="verified" class="form-select">
                  <option value="1" <?php echo $values['verified'] === '1' ? 'selected' : ''; ?>>Có</option>
                  <option value="0" <?php echo $values['verified'] === '0' ? 'selected' : ''; ?>>Không</option>
                </select>
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label" for="rating">Rating</label>
                <input id="rating" name="rating" class="form-control" value="<?php echo htmlspecialchars($values['rating'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label" for="reviews_count">Số review</label>
                <input id="reviews_count" name="reviews_count" class="form-control" value="<?php echo htmlspecialchars($values['reviews_count'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label" for="followers_count">Lượt quan tâm</label>
                <input id="followers_count" name="followers_count" class="form-control" value="<?php echo htmlspecialchars($values['followers_count'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label" for="hours_text">Giờ làm việc</label>
                <input id="hours_text" name="hours_text" class="form-control" value="<?php echo htmlspecialchars($values['hours_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label" for="price_text">Khoảng giá</label>
                <input id="price_text" name="price_text" class="form-control" value="<?php echo htmlspecialchars($values['price_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12" data-ckeditor-wrap>
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                  <label class="form-label mb-0">Bảng giá dịch vụ (HTML)</label>
                  <button type="button" class="btn btn-outline-primary btn-sm" data-ckeditor-insert-image="1"><i class="fa-solid fa-images me-2" aria-hidden="true"></i>Thêm ảnh</button>
                </div>
                <div class="border rounded-4 bg-white p-2" style="min-height:300px" data-ckeditor-target></div>
                <textarea name="price_table_html" class="d-none" data-ckeditor-source><?php echo htmlspecialchars($values['price_table_html'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="form-text">Có thể chỉnh bảng bằng công cụ Table của CKEditor.</div>
              </div>
              <div class="col-12">
                <label class="form-label" for="address_text">Địa chỉ</label>
                <input id="address_text" name="address_text" class="form-control" value="<?php echo htmlspecialchars($values['address_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="phone_text">Điện thoại</label>
                <input id="phone_text" name="phone_text" class="form-control" value="<?php echo htmlspecialchars($values['phone_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-5">
                <label class="form-label" for="website_url">Website</label>
                <input id="website_url" name="website_url" class="form-control mono" value="<?php echo htmlspecialchars($values['website_url'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label" for="images_label">Nhãn ảnh</label>
                <input id="images_label" name="images_label" class="form-control" value="<?php echo htmlspecialchars($values['images_label'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label" for="display_order">Thứ tự hiển thị</label>
                <input id="display_order" name="display_order" class="form-control" value="<?php echo htmlspecialchars($values['display_order'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4">
            <div class="border rounded-4 bg-white p-3">
              <div class="fw-semibold mb-2">Ảnh & media</div>
              <?php require __DIR__ . '/_media_image_field.php'; ?>
            </div>
            <div class="border rounded-4 bg-white p-3 mt-3" data-yoast-panel data-yoast-profile="facility" data-yoast-domain="" data-yoast-url-prefix="/co-so-y-te/">
              <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <div class="fw-semibold"><i class="fa-solid fa-chart-line me-2" aria-hidden="true"></i>SEO cơ sở</div>
                <div class="d-flex align-items-center gap-2">
                  <span data-yoast-title-count class="small text-secondary"></span>
                  <span data-yoast-desc-count class="small text-secondary"></span>
                </div>
              </div>
              <div class="border rounded-4 p-3 mb-3" style="background:#f8fafc;">
                <div class="fw-semibold" style="color:#1a0dab;" data-yoast-preview-title></div>
                <div class="small" style="color:#006621; overflow-wrap:anywhere;" data-yoast-preview-url></div>
                <div class="small text-secondary" data-yoast-preview-desc></div>
              </div>
              <div class="mb-3" data-yoast-checklist></div>
              <button class="btn btn-outline-secondary w-100 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#seoFieldsFacilityEdit" aria-expanded="false" aria-controls="seoFieldsFacilityEdit">
                <i class="fa-solid fa-sliders me-2" aria-hidden="true"></i>Cấu hình SEO
              </button>
              <div class="collapse mt-3" id="seoFieldsFacilityEdit">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label" for="seo_keywords">Từ khóa (từ khóa chính, phụ)</label>
                    <input id="seo_keywords" name="seo_keywords" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($values['seo_keywords'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="nha khoa uy tín Đà Nẵng, trồng implant">
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="seo_title">SEO Title</label>
                    <input id="seo_title" name="seo_title" class="form-control" maxlength="160" value="<?php echo htmlspecialchars($values['seo_title'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Để trống sẽ dùng tên cơ sở">
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="seo_description">SEO Description</label>
                    <textarea id="seo_description" name="seo_description" class="form-control" rows="4" maxlength="300" placeholder="Để trống sẽ dùng mô tả ngắn của cơ sở"><?php echo htmlspecialchars($values['seo_description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <div class="form-text">Độ dài khuyến nghị khoảng 120–160 ký tự. Để trống trường SEO sẽ tự lấy dữ liệu hồ sơ.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <?php require __DIR__ . '/_media_gallery_field.php'; ?>

          <div class="col-12 col-lg-6">
            <label class="form-label" for="featured_services_lines">Dịch vụ nổi bật trên list</label>
            <textarea id="featured_services_lines" name="featured_services_lines" class="form-control mono" rows="5"><?php echo htmlspecialchars($values['featured_services_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="form-text">Mỗi dòng là một dịch vụ.</div>
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="tags_lines">Tag hiển thị</label>
            <textarea id="tags_lines" name="tags_lines" class="form-control mono" rows="5"><?php echo htmlspecialchars($values['tags_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="form-text">Mỗi dòng là một tag.</div>
          </div>
          <div class="col-12">
            <div data-ckeditor-wrap>
              <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <label class="form-label mb-0" for="intro_lines">Giới thiệu</label>
                <button type="button" class="btn btn-outline-primary btn-sm" data-ckeditor-insert-image="1"><i class="fa-solid fa-images me-2" aria-hidden="true"></i>Thêm ảnh</button>
              </div>
              <div class="border rounded-4 bg-white p-2" style="min-height: 300px;" data-ckeditor-target></div>
              <textarea id="intro_lines" name="intro_lines" class="d-none" data-ckeditor-source><?php echo htmlspecialchars($values['intro_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
              <div class="form-text">Nội dung HTML 300–500 từ, lưu trong cột <code>content</code>.</div>
            </div>
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="utilities_lines">Tiện ích</label>
            <textarea id="utilities_lines" name="utilities_lines" class="form-control" rows="5"><?php echo htmlspecialchars($values['utilities_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="form-text">Mỗi dòng là một tiện ích.</div>
          </div>

          <div class="col-12 d-grid d-sm-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Lưu cơ sở y tế</button>
            <?php if ($isEdit): ?>
              <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(site_url('co-so-y-te-chi-tiet.php') . '?slug=' . rawurlencode($values['slug']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Xem frontend</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
