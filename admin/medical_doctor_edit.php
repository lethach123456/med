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

function doctor_lines_to_text(array $items): string
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

function doctor_text_to_lines(string $value): array
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

$facilities = $pdo->query("SELECT slug, name FROM medical_facilities ORDER BY display_order ASC, id DESC")->fetchAll();

$values = [
    'name' => '',
    'slug' => '',
    'title_text' => '',
    'specialty_text' => '',
    'city' => '',
    'facility_slug' => '',
    'facility_name' => '',
    'verified' => '1',
    'rating' => '',
    'reviews_count' => '0',
    'followers_count' => '0',
    'hours_text' => '',
    'price_text' => '',
    'image_url' => '',
    'tags_lines' => '',
    'specialties_lines' => '',
    'gallery_lines' => '',
    'bio_lines' => '',
    'status' => 'published',
    'display_order' => '0',
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM medical_doctors WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_toast_set('danger', 'Bác sĩ không tồn tại.', 'fa-solid fa-triangle-exclamation');
        header('Location: ' . admin_url('medical_doctors.php'));
        exit;
    }
    $item = medical_directory_doctor_from_row($row);
    $values = [
        'name' => (string) $item['name'],
        'slug' => (string) $item['slug'],
        'title_text' => (string) $item['title_text'],
        'specialty_text' => (string) $item['specialty_text'],
        'city' => (string) $item['city'],
        'facility_slug' => (string) $item['facility_slug'],
        'facility_name' => (string) $item['facility_name'],
        'verified' => !empty($item['is_verified']) ? '1' : '0',
        'rating' => (string) $item['rating'],
        'reviews_count' => (string) $item['reviews_count'],
        'followers_count' => (string) $item['followers_count'],
        'hours_text' => (string) $item['hours_text'],
        'price_text' => (string) $item['price_text'],
        'image_url' => (string) $item['image_url'],
        'tags_lines' => doctor_lines_to_text((array) $item['tags']),
        'specialties_lines' => doctor_lines_to_text((array) $item['specialties']),
        'gallery_lines' => doctor_lines_to_text((array) $item['gallery']),
        'bio_lines' => doctor_lines_to_text((array) $item['bio']),
        'status' => (string) $item['status'],
        'display_order' => (string) $item['display_order'],
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $key) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if ($values['name'] === '') {
        $errors[] = 'Vui lòng nhập tên bác sĩ.';
    }
    if (mb_strlen($values['name']) > 160) {
        $errors[] = 'Tên bác sĩ quá dài.';
    }

    $values['status'] = in_array($values['status'], ['draft', 'published'], true) ? $values['status'] : 'draft';
    $values['verified'] = $values['verified'] === '1' ? '1' : '0';
    $values['display_order'] = (string) max(0, (int) $values['display_order']);

    $ratingFloat = (float) $values['rating'];
    if ($ratingFloat < 0 || $ratingFloat > 5) {
        $errors[] = 'Điểm đánh giá phải trong khoảng 0 - 5.';
    }

    $slugInput = $values['slug'] !== '' ? $values['slug'] : $values['name'];
    $values['slug'] = unique_slug($pdo, 'medical_doctors', $slugInput, $isEdit ? $id : null);

    foreach ($facilities as $facility) {
        if ((string) ($facility['slug'] ?? '') === $values['facility_slug']) {
            $values['facility_name'] = (string) ($facility['name'] ?? $values['facility_name']);
            break;
        }
    }

    $tags = doctor_text_to_lines($values['tags_lines']);
    $specialties = doctor_text_to_lines($values['specialties_lines']);
    $gallery = doctor_text_to_lines($values['gallery_lines']);
    $bio = doctor_text_to_lines($values['bio_lines']);

    if ($errors === []) {
        $params = [
            ':slug' => $values['slug'],
            ':name' => $values['name'],
            ':title_text' => $values['title_text'],
            ':specialty_text' => $values['specialty_text'],
            ':city' => $values['city'],
            ':facility_slug' => $values['facility_slug'],
            ':facility_name' => $values['facility_name'],
            ':verified' => (int) $values['verified'],
            ':rating' => number_format($ratingFloat, 1, '.', ''),
            ':reviews_count' => max(0, (int) $values['reviews_count']),
            ':followers_count' => max(0, (int) $values['followers_count']),
            ':hours_text' => $values['hours_text'],
            ':price_text' => $values['price_text'],
            ':image_url' => $values['image_url'],
            ':tags_json' => medical_directory_json_encode($tags),
            ':specialties_json' => medical_directory_json_encode($specialties),
            ':gallery_json' => medical_directory_json_encode($gallery),
            ':bio_json' => medical_directory_json_encode($bio),
            ':status' => $values['status'],
            ':display_order' => (int) $values['display_order'],
        ];

        if ($isEdit) {
            $stmt = $pdo->prepare(
                "UPDATE medical_doctors SET
                    slug = :slug,
                    name = :name,
                    title_text = :title_text,
                    specialty_text = :specialty_text,
                    city = :city,
                    facility_slug = :facility_slug,
                    facility_name = :facility_name,
                    verified = :verified,
                    rating = :rating,
                    reviews_count = :reviews_count,
                    followers_count = :followers_count,
                    hours_text = :hours_text,
                    price_text = :price_text,
                    image_url = :image_url,
                    tags_json = :tags_json,
                    specialties_json = :specialties_json,
                    gallery_json = :gallery_json,
                    bio_json = :bio_json,
                    status = :status,
                    display_order = :display_order
                 WHERE id = :id"
            );
            $params[':id'] = $id;
            $stmt->execute($params);
            flash_toast_set('success', 'Đã lưu bác sĩ.', 'fa-solid fa-circle-check');
            header('Location: ' . admin_url('medical_doctor_edit.php') . '?id=' . $id);
            exit;
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
        $stmt->execute($params);
        $newId = (int) $pdo->lastInsertId();
        flash_toast_set('success', 'Đã tạo bác sĩ.', 'fa-solid fa-circle-check');
        header('Location: ' . admin_url('medical_doctor_edit.php') . '?id=' . $newId);
        exit;
    }
}

$adminPageTitle = $isEdit ? 'Admin • Sửa bác sĩ' : 'Admin • Thêm bác sĩ';
$adminHeaderTitle = $isEdit ? 'Sửa bác sĩ' : 'Thêm bác sĩ';
$adminHeaderSubtitle = 'Quản lý dữ liệu bác sĩ cho `bac-si.php` và `bac-si-chi-tiet.php`';
$adminActive = 'medical-doctors';
require __DIR__ . '/_layout_start.php';

$mediaFieldId = 'medical_doctor_image';
$mediaFieldLabel = 'Ảnh bác sĩ';
$mediaFieldName = 'image_url';
$mediaFieldValue = $values['image_url'];

$mediaGalleryId = 'medical_doctor_gallery';
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
            <div class="h5 mb-1"><?php echo $isEdit ? 'Cập nhật bác sĩ' : 'Tạo bác sĩ'; ?></div>
            <div class="text-secondary">Biểu mẫu bám theo giao diện danh sách bác sĩ và trang hồ sơ bác sĩ chi tiết.</div>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(admin_url('medical_doctors.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-left me-2"></i>Quay lại</a>
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
                <label class="form-label" for="name">Tên bác sĩ</label>
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
                <label class="form-label" for="specialty_text">Chuyên khoa</label>
                <input id="specialty_text" name="specialty_text" class="form-control" value="<?php echo htmlspecialchars($values['specialty_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="city">Khu vực</label>
                <input id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($values['city'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12">
                <label class="form-label" for="title_text">Chức danh / mô tả ngắn</label>
                <textarea id="title_text" name="title_text" class="form-control" rows="3"><?php echo htmlspecialchars($values['title_text'], ENT_QUOTES, 'UTF-8'); ?></textarea>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label" for="facility_slug">Cơ sở y tế liên kết</label>
                <select id="facility_slug" name="facility_slug" class="form-select">
                  <option value="">-- Chọn cơ sở --</option>
                  <?php foreach ($facilities as $facility): ?>
                    <?php $slug = (string) ($facility['slug'] ?? ''); ?>
                    <option value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $values['facility_slug'] === $slug ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars((string) ($facility['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label" for="facility_name">Tên cơ sở hiển thị</label>
                <input id="facility_name" name="facility_name" class="form-control" value="<?php echo htmlspecialchars($values['facility_name'], ENT_QUOTES, 'UTF-8'); ?>">
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
                <label class="form-label" for="hours_text">Lịch khám</label>
                <input id="hours_text" name="hours_text" class="form-control" value="<?php echo htmlspecialchars($values['hours_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label" for="price_text">Chi phí khám</label>
                <input id="price_text" name="price_text" class="form-control" value="<?php echo htmlspecialchars($values['price_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label" for="display_order">Thứ tự hiển thị</label>
                <input id="display_order" name="display_order" class="form-control" value="<?php echo htmlspecialchars($values['display_order'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4">
            <div class="border rounded-4 bg-white p-3">
              <div class="fw-semibold mb-2">Ảnh đại diện</div>
              <?php require __DIR__ . '/_media_image_field.php'; ?>
            </div>
          </div>

          <?php require __DIR__ . '/_media_gallery_field.php'; ?>

          <div class="col-12 col-lg-6">
            <label class="form-label" for="specialties_lines">Chuyên môn nổi bật</label>
            <textarea id="specialties_lines" name="specialties_lines" class="form-control mono" rows="6"><?php echo htmlspecialchars($values['specialties_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="form-text">Mỗi dòng là một chuyên môn.</div>
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="tags_lines">Tag hiển thị</label>
            <textarea id="tags_lines" name="tags_lines" class="form-control mono" rows="6"><?php echo htmlspecialchars($values['tags_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="form-text">Mỗi dòng là một tag.</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="bio_lines">Giới thiệu bác sĩ</label>
            <textarea id="bio_lines" name="bio_lines" class="form-control" rows="7"><?php echo htmlspecialchars($values['bio_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="form-text">Mỗi dòng là một đoạn giới thiệu.</div>
          </div>

          <div class="col-12 d-grid d-sm-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Lưu bác sĩ</button>
            <?php if ($isEdit): ?>
              <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(site_url('bac-si-chi-tiet.php') . '?slug=' . rawurlencode($values['slug']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Xem frontend</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
