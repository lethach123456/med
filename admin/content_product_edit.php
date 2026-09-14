<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();

$pdo = db();

$isModal = (isset($_GET['modal']) && (string) $_GET['modal'] === '1') || (isset($_POST['modal']) && (string) $_POST['modal'] === '1');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $id > 0;

$initialCategoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$lockCategory = (!$isEdit && $initialCategoryId > 0);

$values = [
    'category_id' => $initialCategoryId > 0 ? (string) $initialCategoryId : '',
    'name' => '',
    'slug' => '',
    'featured_image_url' => '',
    'short_description' => '',
    'content' => '',
    'price_int' => '',
    'price_text' => '',
    'gallery_lines' => '',
    'seo_title' => '',
    'seo_description' => '',
    'seo_keywords' => '',
    'status' => 'draft',
];

if ($isEdit) {
    $stmt = $pdo->prepare(
        'SELECT id, category_id, name, slug, featured_image_url, short_description, content, price_int, price_text, gallery_json,
                seo_title, seo_description, seo_keywords, status
         FROM products
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        if ($isModal) {
            json_response(['ok' => false, 'message' => 'Sản phẩm không tồn tại.'], 404);
        }
        flash_toast_set('danger', 'Sản phẩm không tồn tại.', 'fa-solid fa-triangle-exclamation');
        header('Location: /admin/content.php');
        exit;
    }
    $values['category_id'] = ($row['category_id'] !== null) ? (string) (int) $row['category_id'] : '';
    $values['name'] = (string) ($row['name'] ?? '');
    $values['slug'] = (string) ($row['slug'] ?? '');
    $values['featured_image_url'] = (string) ($row['featured_image_url'] ?? '');
    $values['short_description'] = (string) ($row['short_description'] ?? '');
    $values['content'] = (string) ($row['content'] ?? '');
    $values['price_int'] = ($row['price_int'] !== null) ? (string) (int) $row['price_int'] : '';
    $values['price_text'] = (string) ($row['price_text'] ?? '');
    $gallery = (string) ($row['gallery_json'] ?? '');
    if ($gallery !== '') {
        $decoded = json_decode($gallery, true);
        if (is_array($decoded)) {
            $lines = [];
            foreach ($decoded as $u) {
                if (is_string($u) && trim($u) !== '') $lines[] = trim($u);
            }
            $values['gallery_lines'] = implode("\n", $lines);
        }
    }
    $values['seo_title'] = (string) ($row['seo_title'] ?? '');
    $values['seo_description'] = (string) ($row['seo_description'] ?? '');
    $values['seo_keywords'] = (string) ($row['seo_keywords'] ?? '');
    $values['status'] = (string) ($row['status'] ?? 'draft');
}

$categories = $pdo->query('SELECT id, name FROM product_categories ORDER BY name ASC, id ASC')->fetchAll();

$errors = [];

function render_product_modal_form(array $values, array $errors, bool $isEdit, int $id, array $categories, bool $lockCategory): string
{
    $mediaFieldName = 'featured_image_url';
    $mediaFieldValue = (string) ($values['featured_image_url'] ?? '');
    $collapseId = $isEdit ? 'seoFieldsProductModalEdit' : 'seoFieldsProductModalCreate';

    ob_start();
    ?>
    <div class="p-2 p-sm-0">
      <?php if (count($errors) > 0): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $e): ?>
            <div><?php echo htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="/admin/content_product_edit.php?modal=1" class="row g-3" data-modal-form="1" data-post-form="1">
        <input type="hidden" name="modal" value="1">
        <?php if ($isEdit): ?>
          <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
        <?php endif; ?>

        <?php if ($lockCategory): ?>
          <input type="hidden" name="category_id" value="<?php echo htmlspecialchars((string) ($values['category_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <?php else: ?>
          <div class="col-12">
            <label class="form-label" for="category_id">Chuyên mục (tuỳ chọn)</label>
            <select id="category_id" name="category_id" class="form-select">
              <option value="">— Không chọn —</option>
              <?php foreach ($categories as $c): ?>
                <?php $cid = (int) ($c['id'] ?? 0); ?>
                <option value="<?php echo $cid; ?>" <?php echo ((string) ($values['category_id'] ?? '') === (string) $cid) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="col-12">
          <div class="row g-3">
            <div class="col-12 col-md-8">
              <label class="form-label" for="name">Tên sản phẩm</label>
              <input id="name" name="name" class="form-control" value="<?php echo htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label" for="status">Trạng thái</label>
              <select id="status" name="status" class="form-select">
                <option value="draft" <?php echo (($values['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>draft</option>
                <option value="published" <?php echo (($values['status'] ?? '') === 'published') ? 'selected' : ''; ?>>published</option>
              </select>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label" for="price_int">Giá (VND)</label>
              <input id="price_int" name="price_int" class="form-control mono" value="<?php echo htmlspecialchars((string) ($values['price_int'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="24500000">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="price_text">Giá chữ (tuỳ chọn)</label>
              <input id="price_text" name="price_text" class="form-control" value="<?php echo htmlspecialchars((string) ($values['price_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Liên hệ báo giá">
            </div>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label" for="short_description">Mô tả ngắn</label>
          <textarea id="short_description" name="short_description" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($values['short_description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <?php require __DIR__ . '/_media_image_field.php'; ?>

        <div class="col-12 col-lg-8">
          <div class="col-12" data-ckeditor-wrap>
            <label class="form-label">Nội dung</label>
            <div class="border rounded-4 bg-white p-2" style="min-height: 240px;" data-ckeditor-target></div>
            <textarea name="content" class="d-none" data-ckeditor-source><?php echo htmlspecialchars((string) ($values['content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
        </div>
        <div class="col-12 col-lg-4">
          <div class="border rounded-4 bg-white p-3" data-yoast-panel data-yoast-domain="">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div class="fw-semibold"><i class="fa-solid fa-chart-line me-2" aria-hidden="true"></i>SEO</div>
              <div class="d-flex align-items-center gap-2">
                <span data-yoast-title-count class="small text-secondary"></span>
                <span data-yoast-desc-count class="small text-secondary"></span>
              </div>
            </div>
            <div class="border rounded-4 p-3 mb-3" style="background:#f8fafc;">
              <div class="fw-semibold" style="color:#1a0dab;" data-yoast-preview-title></div>
              <div class="small" style="color:#006621;" data-yoast-preview-url></div>
              <div class="small text-secondary" data-yoast-preview-desc></div>
            </div>
            <div class="mb-3" data-yoast-checklist></div>
            <button class="btn btn-outline-secondary w-100 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>">
              <i class="fa-solid fa-sliders me-2" aria-hidden="true"></i>Cấu hình SEO
            </button>
            <div class="collapse mt-3" id="<?php echo $collapseId; ?>">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label" for="slug">Slug</label>
                  <input id="slug" name="slug" class="form-control mono" value="<?php echo htmlspecialchars((string) ($values['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="tu-dong-theo-ten-neu-de-trong">
                </div>
                <div class="col-12">
                  <label class="form-label" for="seo_keywords">Từ khóa</label>
                  <input id="seo_keywords" name="seo_keywords" class="form-control" value="<?php echo htmlspecialchars((string) ($values['seo_keywords'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12">
                  <label class="form-label" for="seo_title">SEO Title</label>
                  <input id="seo_title" name="seo_title" class="form-control" value="<?php echo htmlspecialchars((string) ($values['seo_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12">
                  <label class="form-label" for="seo_description">SEO Description</label>
                  <textarea id="seo_description" name="seo_description" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($values['seo_description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label" for="gallery_lines">Gallery (mỗi dòng 1 URL)</label>
          <textarea id="gallery_lines" name="gallery_lines" class="form-control mono" rows="4" placeholder="/uploads/library/..."><?php echo htmlspecialchars((string) ($values['gallery_lines'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="col-12 d-grid d-sm-flex gap-2">
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Lưu</button>
          <?php if ($isEdit): ?>
            <span class="text-secondary small align-self-center">ID: <?php echo (int) $id; ?></span>
          <?php endif; ?>
        </div>
      </form>
    </div>
    <?php
    return (string) ob_get_clean();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $isEditPost = $postId > 0;

    $values['category_id'] = trim((string) ($_POST['category_id'] ?? ''));
    $values['name'] = trim((string) ($_POST['name'] ?? ''));
    $values['slug'] = trim((string) ($_POST['slug'] ?? ''));
    $values['featured_image_url'] = trim((string) ($_POST['featured_image_url'] ?? ''));
    $values['short_description'] = trim((string) ($_POST['short_description'] ?? ''));
    $values['content'] = trim((string) ($_POST['content'] ?? ''));
    $values['price_int'] = trim((string) ($_POST['price_int'] ?? ''));
    $values['price_text'] = trim((string) ($_POST['price_text'] ?? ''));
    $values['gallery_lines'] = trim((string) ($_POST['gallery_lines'] ?? ''));
    $values['seo_title'] = trim((string) ($_POST['seo_title'] ?? ''));
    $values['seo_description'] = trim((string) ($_POST['seo_description'] ?? ''));
    $values['seo_keywords'] = trim((string) ($_POST['seo_keywords'] ?? ''));
    $values['status'] = trim((string) ($_POST['status'] ?? 'draft'));

    if ($values['name'] === '') {
        $errors[] = 'Vui lòng nhập tên sản phẩm.';
    }
    if (mb_strlen($values['name']) > 160) {
        $errors[] = 'Tên sản phẩm quá dài (tối đa 160 ký tự).';
    }

    $allowedStatus = ['draft', 'published'];
    if (!in_array($values['status'], $allowedStatus, true)) {
        $values['status'] = 'draft';
    }

    $catId = null;
    if ($values['category_id'] !== '') {
        $candidate = (int) $values['category_id'];
        if ($candidate > 0) {
            $st = $pdo->prepare('SELECT 1 FROM product_categories WHERE id = :id LIMIT 1');
            $st->execute([':id' => $candidate]);
            if ($st->fetchColumn()) {
                $catId = $candidate;
            } else {
                $errors[] = 'Chuyên mục sản phẩm không hợp lệ.';
            }
        }
    }

    $slugInput = $values['slug'] !== '' ? $values['slug'] : $values['name'];
    $values['slug'] = unique_slug($pdo, 'products', $slugInput, $isEditPost ? $postId : null);

    $priceInt = null;
    if ($values['price_int'] !== '') {
        if (!ctype_digit($values['price_int'])) {
            $errors[] = 'Giá (VND) phải là số nguyên.';
        } else {
            $priceInt = (int) $values['price_int'];
            if ($priceInt < 0) $priceInt = 0;
        }
    }

    if (mb_strlen($values['price_text']) > 60) {
        $errors[] = 'Giá chữ quá dài (tối đa 60 ký tự).';
    }

    if (mb_strlen($values['seo_title']) > 160) {
        $errors[] = 'SEO title quá dài (tối đa 160 ký tự).';
    }
    if (mb_strlen($values['seo_description']) > 300) {
        $errors[] = 'SEO description quá dài (tối đa 300 ký tự).';
    }
    if (mb_strlen($values['seo_keywords']) > 255) {
        $errors[] = 'SEO keywords quá dài (tối đa 255 ký tự).';
    }

    $galleryJson = null;
    if ($values['gallery_lines'] !== '') {
        $lines = preg_split('/\r\n|\r|\n/', $values['gallery_lines']) ?: [];
        $urls = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') continue;
            $urls[] = $line;
        }
        if (count($urls) > 0) {
            $galleryJson = json_encode($urls, JSON_UNESCAPED_UNICODE);
        }
    }

    if (count($errors) === 0) {
        if ($isEditPost) {
            $stmt = $pdo->prepare(
                "UPDATE products
                 SET category_id = :category_id,
                     name = :name,
                     slug = :slug,
                     featured_image_url = :featured_image_url,
                     short_description = :short_description,
                     content = :content,
                     price_int = :price_int,
                     price_text = :price_text,
                     gallery_json = :gallery_json,
                     seo_title = :seo_title,
                     seo_description = :seo_description,
                     seo_keywords = :seo_keywords,
                     status = :status
                 WHERE id = :id"
            );
            $stmt->execute([
                ':category_id' => $catId,
                ':name' => $values['name'],
                ':slug' => $values['slug'],
                ':featured_image_url' => ($values['featured_image_url'] !== '') ? $values['featured_image_url'] : null,
                ':short_description' => ($values['short_description'] !== '') ? $values['short_description'] : null,
                ':content' => ($values['content'] !== '') ? $values['content'] : null,
                ':price_int' => $priceInt,
                ':price_text' => ($values['price_text'] !== '') ? $values['price_text'] : null,
                ':gallery_json' => $galleryJson,
                ':seo_title' => ($values['seo_title'] !== '') ? $values['seo_title'] : null,
                ':seo_description' => ($values['seo_description'] !== '') ? $values['seo_description'] : null,
                ':seo_keywords' => ($values['seo_keywords'] !== '') ? $values['seo_keywords'] : null,
                ':status' => $values['status'],
                ':id' => $postId,
            ]);
            if ($isModal) {
                json_response(['ok' => true, 'message' => 'Đã lưu sản phẩm.', 'id' => $postId]);
            }
            flash_toast_set('success', 'Đã lưu sản phẩm.', 'fa-solid fa-circle-check');
            header('Location: /admin/content_product_edit.php?id=' . $postId);
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO products (category_id, name, slug, featured_image_url, short_description, content, price_int, price_text, gallery_json, seo_title, seo_description, seo_keywords, status)
             VALUES (:category_id, :name, :slug, :featured_image_url, :short_description, :content, :price_int, :price_text, :gallery_json, :seo_title, :seo_description, :seo_keywords, :status)"
        );
        $stmt->execute([
            ':category_id' => $catId,
            ':name' => $values['name'],
            ':slug' => $values['slug'],
            ':featured_image_url' => ($values['featured_image_url'] !== '') ? $values['featured_image_url'] : null,
            ':short_description' => ($values['short_description'] !== '') ? $values['short_description'] : null,
            ':content' => ($values['content'] !== '') ? $values['content'] : null,
            ':price_int' => $priceInt,
            ':price_text' => ($values['price_text'] !== '') ? $values['price_text'] : null,
            ':gallery_json' => $galleryJson,
            ':seo_title' => ($values['seo_title'] !== '') ? $values['seo_title'] : null,
            ':seo_description' => ($values['seo_description'] !== '') ? $values['seo_description'] : null,
            ':seo_keywords' => ($values['seo_keywords'] !== '') ? $values['seo_keywords'] : null,
            ':status' => $values['status'],
        ]);
        $newId = (int) $pdo->lastInsertId();
        if ($isModal) {
            json_response(['ok' => true, 'message' => 'Đã tạo sản phẩm.', 'id' => $newId]);
        }
        flash_toast_set('success', 'Đã tạo sản phẩm.', 'fa-solid fa-circle-check');
        header('Location: /admin/content_product_edit.php?id=' . $newId);
        exit;
    }

    if ($isModal) {
        json_response([
            'ok' => false,
            'message' => 'Dữ liệu không hợp lệ.',
            'html' => render_product_modal_form($values, $errors, $isEditPost, $postId, $categories, $lockCategory),
        ], 422);
    }
}

if ($isModal) {
    echo render_product_modal_form($values, [], $isEdit, $id, $categories, $lockCategory);
    exit;
}

$adminPageTitle = $isEdit ? 'Admin • Sửa sản phẩm' : 'Admin • Thêm sản phẩm';
$adminHeaderTitle = $isEdit ? 'Sửa sản phẩm' : 'Thêm sản phẩm';
$adminHeaderSubtitle = 'Thông tin, giá và SEO';
$adminActive = 'content';
require __DIR__ . '/_layout_start.php';
?>

<div class="row g-3">
  <div class="col-12">
    <div class="card border-0 shadow-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
          <div class="h5 mb-0"><?php echo htmlspecialchars($adminHeaderTitle, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="/admin/content.php"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>Quay lại</a>
            <?php if ($values['category_id'] !== ''): ?>
              <a class="btn btn-outline-primary" href="/admin/content_product_category.php?id=<?php echo (int) $values['category_id']; ?>"><i class="fa-solid fa-list me-2" aria-hidden="true"></i>Chuyên mục</a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (count($errors) > 0): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
              <div><?php echo htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" class="row g-3" data-post-form="1">
          <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
          <?php endif; ?>

          <div class="col-12">
            <label class="form-label" for="category_id">Chuyên mục (tuỳ chọn)</label>
            <select id="category_id" name="category_id" class="form-select">
              <option value="">— Không chọn —</option>
              <?php foreach ($categories as $c): ?>
                <?php $cid = (int) ($c['id'] ?? 0); ?>
                <option value="<?php echo $cid; ?>" <?php echo ((string) ($values['category_id'] ?? '') === (string) $cid) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12">
            <div class="row g-3">
              <div class="col-12 col-md-8">
                <label class="form-label" for="name">Tên sản phẩm</label>
                <input id="name" name="name" class="form-control" value="<?php echo htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="status">Trạng thái</label>
                <select id="status" name="status" class="form-select">
                  <option value="draft" <?php echo (($values['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>draft</option>
                  <option value="published" <?php echo (($values['status'] ?? '') === 'published') ? 'selected' : ''; ?>>published</option>
                </select>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label" for="price_int">Giá (VND)</label>
                <input id="price_int" name="price_int" class="form-control mono" value="<?php echo htmlspecialchars((string) ($values['price_int'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="24500000">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label" for="price_text">Giá chữ (tuỳ chọn)</label>
                <input id="price_text" name="price_text" class="form-control" value="<?php echo htmlspecialchars((string) ($values['price_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Liên hệ báo giá">
              </div>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label" for="short_description">Mô tả ngắn</label>
            <textarea id="short_description" name="short_description" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($values['short_description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <?php
          $mediaFieldName = 'featured_image_url';
          $mediaFieldValue = (string) ($values['featured_image_url'] ?? '');
          require __DIR__ . '/_media_image_field.php';
          ?>

          <div class="col-12 col-lg-8">
            <div class="col-12" data-ckeditor-wrap>
              <label class="form-label">Nội dung</label>
              <div class="border rounded-4 bg-white p-2" style="min-height: 260px;" data-ckeditor-target></div>
              <textarea name="content" class="d-none" data-ckeditor-source><?php echo htmlspecialchars((string) ($values['content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
          </div>
          <div class="col-12 col-lg-4">
            <div class="border rounded-4 bg-white p-3" data-yoast-panel data-yoast-domain="">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="fw-semibold"><i class="fa-solid fa-chart-line me-2" aria-hidden="true"></i>SEO</div>
                <div class="d-flex align-items-center gap-2">
                  <span data-yoast-title-count class="small text-secondary"></span>
                  <span data-yoast-desc-count class="small text-secondary"></span>
                </div>
              </div>
              <div class="border rounded-4 p-3 mb-3" style="background:#f8fafc;">
                <div class="fw-semibold" style="color:#1a0dab;" data-yoast-preview-title></div>
                <div class="small" style="color:#006621;" data-yoast-preview-url></div>
                <div class="small text-secondary" data-yoast-preview-desc></div>
              </div>
              <div class="mb-3" data-yoast-checklist></div>
              <button class="btn btn-outline-secondary w-100 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#seoFieldsProduct" aria-expanded="false" aria-controls="seoFieldsProduct">
                <i class="fa-solid fa-sliders me-2" aria-hidden="true"></i>Cấu hình SEO
              </button>
              <div class="collapse mt-3" id="seoFieldsProduct">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label" for="slug">Slug</label>
                    <input id="slug" name="slug" class="form-control mono" value="<?php echo htmlspecialchars((string) ($values['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="tu-dong-theo-ten-neu-de-trong">
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="seo_keywords">Từ khóa</label>
                    <input id="seo_keywords" name="seo_keywords" class="form-control" value="<?php echo htmlspecialchars((string) ($values['seo_keywords'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="seo_title">SEO Title</label>
                    <input id="seo_title" name="seo_title" class="form-control" value="<?php echo htmlspecialchars((string) ($values['seo_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="seo_description">SEO Description</label>
                    <textarea id="seo_description" name="seo_description" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($values['seo_description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <?php
          $mediaGalleryId = 'product_gallery';
          $mediaGalleryLabel = 'Gallery';
          $mediaGalleryName = 'gallery_lines';
          $mediaGalleryValue = (string) ($values['gallery_lines'] ?? '');
          require __DIR__ . '/_media_gallery_field.php';
          ?>

          <div class="col-12 d-grid d-sm-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Lưu</button>
            <?php if ($isEdit): ?>
              <span class="text-secondary small align-self-center">ID: <?php echo (int) $id; ?></span>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
