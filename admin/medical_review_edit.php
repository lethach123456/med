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
$oldFacilitySlug = '';

function review_pretty_json($value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return is_string($json) ? $json : '[]';
}

function review_lines_to_text(array $items): string
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

function review_text_to_lines(string $value): array
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

function review_decode_json_field(string $label, string $raw, array &$errors, array $fallback = []): array
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

$facilities = $pdo->query("SELECT slug, name FROM medical_facilities ORDER BY display_order ASC, id DESC")->fetchAll();

$values = [
    'title' => '',
    'slug' => '',
    'facility_slug' => '',
    'facility_name' => '',
    'verified' => '1',
    'rating' => '',
    'author_text' => '',
    'location_text' => '',
    'review_date_text' => '',
    'price_text' => '',
    'excerpt' => '',
    'before_image_url' => '',
    'after_image_url' => '',
    'likes_count' => '0',
    'comments_count' => '0',
    'shares_count' => '0',
    'service_text' => '',
    'method_text' => '',
    'duration_text' => '',
    'start_date_text' => '',
    'end_date_text' => '',
    'condition_text' => '',
    'story_lines' => '',
    'timeline_json' => '[]',
    'thumbs_lines' => '',
    'process_before_lines' => '',
    'process_during_lines' => '',
    'process_after_lines' => '',
    'status' => 'published',
    'display_order' => '0',
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM medical_reviews WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash_toast_set('danger', 'Review không tồn tại.', 'fa-solid fa-triangle-exclamation');
        header('Location: /admin/medical_reviews.php');
        exit;
    }
    $oldFacilitySlug = (string) ($row['facility_slug'] ?? '');
    $item = medical_directory_review_from_row($row);
    $values = [
        'title' => (string) $item['title'],
        'slug' => (string) $item['slug'],
        'facility_slug' => (string) $item['facility_slug'],
        'facility_name' => (string) $item['facility_name'],
        'verified' => !empty($item['is_verified']) ? '1' : '0',
        'rating' => (string) $item['rating'],
        'author_text' => (string) $item['author_text'],
        'location_text' => (string) $item['location_text'],
        'review_date_text' => (string) $item['review_date_text'],
        'price_text' => (string) $item['price_text'],
        'excerpt' => (string) $item['excerpt'],
        'before_image_url' => (string) $item['before_image_url'],
        'after_image_url' => (string) $item['after_image_url'],
        'likes_count' => (string) $item['likes_count'],
        'comments_count' => (string) $item['comments_count'],
        'shares_count' => (string) $item['shares_count'],
        'service_text' => (string) $item['service_text'],
        'method_text' => (string) $item['method_text'],
        'duration_text' => (string) $item['duration_text'],
        'start_date_text' => (string) $item['start_date'],
        'end_date_text' => (string) $item['end_date'],
        'condition_text' => (string) $item['condition'],
        'story_lines' => review_lines_to_text((array) $item['story']),
        'timeline_json' => review_pretty_json($item['timeline']),
        'thumbs_lines' => review_lines_to_text((array) $item['thumbs']),
        'process_before_lines' => review_lines_to_text((array) $item['process_before']),
        'process_during_lines' => review_lines_to_text((array) $item['process_during']),
        'process_after_lines' => review_lines_to_text((array) $item['process_after']),
        'status' => (string) $item['status'],
        'display_order' => (string) $item['display_order'],
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $key) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if ($values['title'] === '') {
        $errors[] = 'Vui lòng nhập tiêu đề review.';
    }
    if (mb_strlen($values['title']) > 190) {
        $errors[] = 'Tiêu đề review quá dài.';
    }

    $values['status'] = in_array($values['status'], ['draft', 'published'], true) ? $values['status'] : 'draft';
    $values['verified'] = $values['verified'] === '1' ? '1' : '0';
    $values['display_order'] = (string) max(0, (int) $values['display_order']);

    $ratingFloat = (float) $values['rating'];
    if ($ratingFloat < 0 || $ratingFloat > 5) {
        $errors[] = 'Điểm đánh giá phải trong khoảng 0 - 5.';
    }

    if ($values['facility_slug'] !== '') {
        $facilityName = '';
        foreach ($facilities as $facility) {
            if ((string) ($facility['slug'] ?? '') === $values['facility_slug']) {
                $facilityName = (string) ($facility['name'] ?? '');
                break;
            }
        }
        if ($facilityName !== '') {
            $values['facility_name'] = $facilityName;
        }
    }

    $slugInput = $values['slug'] !== '' ? $values['slug'] : $values['title'];
    $values['slug'] = unique_slug($pdo, 'medical_reviews', $slugInput, $isEdit ? $id : null);

    $story = review_text_to_lines($values['story_lines']);
    $timeline = review_decode_json_field('Timeline điều trị', $values['timeline_json'], $errors, []);
    $thumbs = review_text_to_lines($values['thumbs_lines']);
    $processBefore = review_text_to_lines($values['process_before_lines']);
    $processDuring = review_text_to_lines($values['process_during_lines']);
    $processAfter = review_text_to_lines($values['process_after_lines']);

    if ($errors === []) {
        $params = [
            ':slug' => $values['slug'],
            ':facility_slug' => $values['facility_slug'],
            ':facility_name' => $values['facility_name'],
            ':title' => $values['title'],
            ':verified' => (int) $values['verified'],
            ':rating' => number_format($ratingFloat, 1, '.', ''),
            ':author_text' => $values['author_text'],
            ':location_text' => $values['location_text'],
            ':review_date_text' => $values['review_date_text'],
            ':price_text' => $values['price_text'],
            ':excerpt' => $values['excerpt'],
            ':before_image_url' => $values['before_image_url'],
            ':after_image_url' => $values['after_image_url'],
            ':likes_count' => max(0, (int) $values['likes_count']),
            ':comments_count' => max(0, (int) $values['comments_count']),
            ':shares_count' => max(0, (int) $values['shares_count']),
            ':service_text' => $values['service_text'],
            ':method_text' => $values['method_text'],
            ':duration_text' => $values['duration_text'],
            ':start_date_text' => $values['start_date_text'],
            ':end_date_text' => $values['end_date_text'],
            ':condition_text' => $values['condition_text'],
            ':story_json' => medical_directory_json_encode($story),
            ':timeline_json' => medical_directory_json_encode($timeline),
            ':thumbs_json' => medical_directory_json_encode($thumbs),
            ':process_before_json' => medical_directory_json_encode($processBefore),
            ':process_during_json' => medical_directory_json_encode($processDuring),
            ':process_after_json' => medical_directory_json_encode($processAfter),
            ':status' => $values['status'],
            ':display_order' => (int) $values['display_order'],
        ];

        if ($isEdit) {
            $params[':id'] = $id;
            $stmt = $pdo->prepare(
                "UPDATE medical_reviews SET
                    slug = :slug,
                    facility_slug = :facility_slug,
                    facility_name = :facility_name,
                    title = :title,
                    verified = :verified,
                    rating = :rating,
                    author_text = :author_text,
                    location_text = :location_text,
                    review_date_text = :review_date_text,
                    price_text = :price_text,
                    excerpt = :excerpt,
                    before_image_url = :before_image_url,
                    after_image_url = :after_image_url,
                    likes_count = :likes_count,
                    comments_count = :comments_count,
                    shares_count = :shares_count,
                    service_text = :service_text,
                    method_text = :method_text,
                    duration_text = :duration_text,
                    start_date_text = :start_date_text,
                    end_date_text = :end_date_text,
                    condition_text = :condition_text,
                    story_json = :story_json,
                    timeline_json = :timeline_json,
                    thumbs_json = :thumbs_json,
                    process_before_json = :process_before_json,
                    process_during_json = :process_during_json,
                    process_after_json = :process_after_json,
                    status = :status,
                    display_order = :display_order
                 WHERE id = :id"
            );
            $stmt->execute($params);
            if ($values['facility_slug'] !== '') {
                medical_directory_refresh_facility_aggregates($pdo, $values['facility_slug']);
            }
            if ($oldFacilitySlug !== '' && $oldFacilitySlug !== $values['facility_slug']) {
                medical_directory_refresh_facility_aggregates($pdo, $oldFacilitySlug);
            }
            flash_toast_set('success', 'Đã lưu review.', 'fa-solid fa-circle-check');
            header('Location: /admin/medical_review_edit.php?id=' . $id);
            exit;
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
        $stmt->execute($params);
        $newId = (int) $pdo->lastInsertId();
        if ($values['facility_slug'] !== '') {
            medical_directory_refresh_facility_aggregates($pdo, $values['facility_slug']);
        }
        flash_toast_set('success', 'Đã tạo review.', 'fa-solid fa-circle-check');
        header('Location: /admin/medical_review_edit.php?id=' . $newId);
        exit;
    }
}

$adminPageTitle = $isEdit ? 'Admin • Sửa review' : 'Admin • Thêm review';
$adminHeaderTitle = $isEdit ? 'Sửa review' : 'Thêm review';
$adminHeaderSubtitle = 'Quản lý dữ liệu review dùng cho `review.php` và `review-chi-tiet.php`';
$adminActive = 'medical-reviews';
require __DIR__ . '/_layout_start.php';

$mediaFieldId = 'medical_review_before';
$mediaFieldLabel = 'Ảnh before';
$mediaFieldName = 'before_image_url';
$mediaFieldValue = $values['before_image_url'];

$afterFieldId = 'medical_review_after';
$afterFieldLabel = 'Ảnh after';
$afterFieldName = 'after_image_url';
$afterFieldValue = $values['after_image_url'];

$mediaGalleryId = 'medical_review_thumbs';
$mediaGalleryLabel = 'Thumbnail gallery';
$mediaGalleryName = 'thumbs_lines';
$mediaGalleryValue = $values['thumbs_lines'];
?>

<div class="row g-3">
  <div class="col-12">
    <div class="card border-0 shadow-soft">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <div class="h5 mb-1"><?php echo $isEdit ? 'Cập nhật review' : 'Tạo review'; ?></div>
            <div class="text-secondary">Biểu mẫu bám theo đúng frontend danh sách review và trang review chi tiết.</div>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(admin_url('medical_reviews.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-left me-2"></i>Quay lại</a>
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
                <label class="form-label" for="title">Tiêu đề review</label>
                <input id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($values['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
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
                <label class="form-label" for="facility_slug">Cơ sở y tế</label>
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
              <div class="col-12 col-md-4">
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
                <label class="form-label" for="review_date_text">Ngày review</label>
                <input id="review_date_text" name="review_date_text" class="form-control" value="<?php echo htmlspecialchars($values['review_date_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label" for="display_order">Thứ tự</label>
                <input id="display_order" name="display_order" class="form-control" value="<?php echo htmlspecialchars($values['display_order'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="author_text">Người review</label>
                <input id="author_text" name="author_text" class="form-control" value="<?php echo htmlspecialchars($values['author_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="location_text">Khu vực</label>
                <input id="location_text" name="location_text" class="form-control" value="<?php echo htmlspecialchars($values['location_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="price_text">Chi phí</label>
                <input id="price_text" name="price_text" class="form-control" value="<?php echo htmlspecialchars($values['price_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="service_text">Dịch vụ</label>
                <input id="service_text" name="service_text" class="form-control" value="<?php echo htmlspecialchars($values['service_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="method_text">Phương pháp</label>
                <input id="method_text" name="method_text" class="form-control" value="<?php echo htmlspecialchars($values['method_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="duration_text">Thời gian điều trị</label>
                <input id="duration_text" name="duration_text" class="form-control" value="<?php echo htmlspecialchars($values['duration_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="start_date_text">Ngày bắt đầu</label>
                <input id="start_date_text" name="start_date_text" class="form-control" value="<?php echo htmlspecialchars($values['start_date_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="end_date_text">Ngày kết thúc</label>
                <input id="end_date_text" name="end_date_text" class="form-control" value="<?php echo htmlspecialchars($values['end_date_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="condition_text">Tình trạng</label>
                <input id="condition_text" name="condition_text" class="form-control" value="<?php echo htmlspecialchars($values['condition_text'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-4">
                <label class="form-label" for="likes_count">Likes</label>
                <input id="likes_count" name="likes_count" class="form-control" value="<?php echo htmlspecialchars($values['likes_count'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-4">
                <label class="form-label" for="comments_count">Comments</label>
                <input id="comments_count" name="comments_count" class="form-control" value="<?php echo htmlspecialchars($values['comments_count'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-4">
                <label class="form-label" for="shares_count">Shares</label>
                <input id="shares_count" name="shares_count" class="form-control" value="<?php echo htmlspecialchars($values['shares_count'], ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-12">
                <label class="form-label" for="excerpt">Mô tả ngắn</label>
                <textarea id="excerpt" name="excerpt" class="form-control" rows="4"><?php echo htmlspecialchars($values['excerpt'], ENT_QUOTES, 'UTF-8'); ?></textarea>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4">
            <div class="border rounded-4 bg-white p-3 mb-3">
              <div class="fw-semibold mb-2">Ảnh before</div>
              <?php require __DIR__ . '/_media_image_field.php'; ?>
            </div>
            <div class="border rounded-4 bg-white p-3">
              <div class="fw-semibold mb-2">Ảnh after</div>
              <?php
              $mediaFieldId = $afterFieldId;
              $mediaFieldLabel = $afterFieldLabel;
              $mediaFieldName = $afterFieldName;
              $mediaFieldValue = $afterFieldValue;
              require __DIR__ . '/_media_image_field.php';
              ?>
            </div>
          </div>

          <?php require __DIR__ . '/_media_gallery_field.php'; ?>

          <div class="col-12 col-lg-6">
            <label class="form-label" for="story_lines">Câu chuyện</label>
            <textarea id="story_lines" name="story_lines" class="form-control" rows="7"><?php echo htmlspecialchars($values['story_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div class="form-text">Mỗi dòng là một đoạn.</div>
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="timeline_json">Timeline JSON</label>
            <textarea id="timeline_json" name="timeline_json" class="form-control mono" rows="7"><?php echo htmlspecialchars($values['timeline_json'], ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label" for="process_before_lines">Hình trước điều trị</label>
            <textarea id="process_before_lines" name="process_before_lines" class="form-control mono" rows="7"><?php echo htmlspecialchars($values['process_before_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label" for="process_during_lines">Hình trong điều trị</label>
            <textarea id="process_during_lines" name="process_during_lines" class="form-control mono" rows="7"><?php echo htmlspecialchars($values['process_during_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label" for="process_after_lines">Hình sau điều trị</label>
            <textarea id="process_after_lines" name="process_after_lines" class="form-control mono" rows="7"><?php echo htmlspecialchars($values['process_after_lines'], ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <div class="col-12 d-grid d-sm-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Lưu review</button>
            <?php if ($isEdit): ?>
              <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(site_url('review-chi-tiet.php') . '?slug=' . rawurlencode($values['slug']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Xem frontend</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
