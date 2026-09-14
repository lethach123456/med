<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();

$pdo = db();
ensure_content_language_columns($pdo);

$isModal = (isset($_GET['modal']) && (string) $_GET['modal'] === '1') || (isset($_POST['modal']) && (string) $_POST['modal'] === '1');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $id > 0;

$values = [
    'name' => '',
    'slug' => '',
    'language' => normalize_content_language((string) ($_GET['language'] ?? 'vi')),
    'description' => '',
    'seo_title' => '',
    'seo_description' => '',
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT id, name, slug, language, description, seo_title, seo_description FROM product_categories WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        if ($isModal) {
            json_response(['ok' => false, 'message' => 'Chuyên mục sản phẩm không tồn tại.'], 404);
        }
        flash_toast_set('danger', 'Chuyên mục sản phẩm không tồn tại.', 'fa-solid fa-triangle-exclamation');
        header('Location: /admin/content.php');
        exit;
    }
    foreach ($values as $k => $_) {
        $values[$k] = (string) ($row[$k] ?? '');
    }
}

$errors = [];

function render_product_category_modal_form(array $values, array $errors, bool $isEdit, int $id): string
{
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

      <form method="post" action="/admin/content_product_category_edit.php?modal=1" class="row g-3" data-modal-form="1">
        <input type="hidden" name="modal" value="1">
        <?php if ($isEdit): ?>
          <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
        <?php endif; ?>
        <input type="hidden" name="language" value="<?php echo htmlspecialchars(normalize_content_language((string) ($values['language'] ?? 'vi')), ENT_QUOTES, 'UTF-8'); ?>">

        <div class="col-12">
          <label class="form-label" for="name">Tên chuyên mục sản phẩm</label>
          <input id="name" name="name" class="form-control" value="<?php echo htmlspecialchars((string) ($values['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="col-12">
          <label class="form-label" for="slug">Slug</label>
          <input id="slug" name="slug" class="form-control mono" value="<?php echo htmlspecialchars((string) ($values['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="tu-dong-theo-ten-neu-de-trong">
        </div>
        <div class="col-12">
          <div class="small text-secondary">Ngôn ngữ: <span class="badge text-bg-light text-dark"><?php echo normalize_content_language((string) ($values['language'] ?? 'vi')) === 'en' ? 'English' : 'Tiếng Việt'; ?></span></div>
        </div>

        <div class="col-12">
          <label class="form-label" for="description">Mô tả</label>
          <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($values['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="col-12">
          <div class="h6 mb-2">SEO</div>
        </div>

        <div class="col-12">
          <label class="form-label" for="seo_title">SEO Title</label>
          <input id="seo_title" name="seo_title" class="form-control" value="<?php echo htmlspecialchars((string) ($values['seo_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12">
          <label class="form-label" for="seo_description">SEO Description</label>
          <textarea id="seo_description" name="seo_description" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($values['seo_description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
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

    $values['name'] = trim((string) ($_POST['name'] ?? ''));
    $values['slug'] = trim((string) ($_POST['slug'] ?? ''));
    $values['language'] = normalize_content_language((string) ($_POST['language'] ?? 'vi'));
    $values['description'] = trim((string) ($_POST['description'] ?? ''));
    $values['seo_title'] = trim((string) ($_POST['seo_title'] ?? ''));
    $values['seo_description'] = trim((string) ($_POST['seo_description'] ?? ''));

    if ($values['name'] === '') {
        $errors[] = 'Vui lòng nhập tên chuyên mục sản phẩm.';
    }
    if (mb_strlen($values['name']) > 120) {
        $errors[] = 'Tên chuyên mục quá dài (tối đa 120 ký tự).';
    }

    $slugInput = $values['slug'] !== '' ? $values['slug'] : $values['name'];
    $values['slug'] = unique_slug($pdo, 'product_categories', $slugInput, $isEditPost ? $postId : null);

    if (mb_strlen($values['seo_title']) > 160) {
        $errors[] = 'SEO title quá dài (tối đa 160 ký tự).';
    }
    if (mb_strlen($values['seo_description']) > 300) {
        $errors[] = 'SEO description quá dài (tối đa 300 ký tự).';
    }

    if (count($errors) === 0) {
        if ($isEditPost) {
            $stmt = $pdo->prepare(
                'UPDATE product_categories
                 SET name = :name, slug = :slug, language = :language, description = :description, seo_title = :seo_title, seo_description = :seo_description
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name' => $values['name'],
                ':slug' => $values['slug'],
                ':language' => $values['language'],
                ':description' => ($values['description'] !== '') ? $values['description'] : null,
                ':seo_title' => ($values['seo_title'] !== '') ? $values['seo_title'] : null,
                ':seo_description' => ($values['seo_description'] !== '') ? $values['seo_description'] : null,
                ':id' => $postId,
            ]);
            if ($isModal) {
                json_response(['ok' => true, 'message' => 'Đã lưu chuyên mục sản phẩm.', 'id' => $postId]);
            }
            flash_toast_set('success', 'Đã lưu chuyên mục sản phẩm.', 'fa-solid fa-circle-check');
            header('Location: /admin/content_product_category_edit.php?id=' . $postId);
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO product_categories (name, slug, language, description, seo_title, seo_description)
             VALUES (:name, :slug, :language, :description, :seo_title, :seo_description)'
        );
        $stmt->execute([
            ':name' => $values['name'],
            ':slug' => $values['slug'],
            ':language' => $values['language'],
            ':description' => ($values['description'] !== '') ? $values['description'] : null,
            ':seo_title' => ($values['seo_title'] !== '') ? $values['seo_title'] : null,
            ':seo_description' => ($values['seo_description'] !== '') ? $values['seo_description'] : null,
        ]);
        $newId = (int) $pdo->lastInsertId();
        if ($isModal) {
            json_response(['ok' => true, 'message' => 'Đã tạo chuyên mục sản phẩm.', 'id' => $newId]);
        }
        flash_toast_set('success', 'Đã tạo chuyên mục sản phẩm.', 'fa-solid fa-circle-check');
        header('Location: /admin/content_product_category_edit.php?id=' . $newId);
        exit;
    }

    if ($isModal) {
        json_response([
            'ok' => false,
            'message' => 'Dữ liệu không hợp lệ.',
            'html' => render_product_category_modal_form($values, $errors, $isEditPost, $postId),
        ], 422);
    }
}

if ($isModal) {
    echo render_product_category_modal_form($values, [], $isEdit, $id);
    exit;
}

$adminPageTitle = $isEdit ? 'Admin • Sửa chuyên mục sản phẩm' : 'Admin • Thêm chuyên mục sản phẩm';
$adminHeaderTitle = $isEdit ? 'Sửa chuyên mục sản phẩm' : 'Thêm chuyên mục sản phẩm';
$adminHeaderSubtitle = 'Thông tin và SEO';
$adminActive = 'content';
require __DIR__ . '/_layout_start.php';
?>

<div class="row g-3">
  <div class="col-12 col-xl-8">
    <div class="card border-0 shadow-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
          <div class="h5 mb-0"><?php echo htmlspecialchars($adminHeaderTitle, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="/admin/content.php"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>Quay lại</a>
            <?php if ($isEdit): ?>
              <a class="btn btn-outline-primary" href="/admin/content_product_category.php?id=<?php echo $id; ?>"><i class="fa-solid fa-list me-2" aria-hidden="true"></i>Quản lý sản phẩm</a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (count($errors) > 0): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
              <div><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" class="row g-3">
          <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
          <?php endif; ?>
          <input type="hidden" name="language" value="<?php echo htmlspecialchars(normalize_content_language((string) ($values['language'] ?? 'vi')), ENT_QUOTES, 'UTF-8'); ?>">

          <div class="col-12">
            <label class="form-label" for="name">Tên chuyên mục sản phẩm</label>
            <input id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($values['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>

          <div class="col-12">
            <label class="form-label" for="slug">Slug</label>
            <input id="slug" name="slug" class="form-control mono" value="<?php echo htmlspecialchars($values['slug'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="tu-dong-theo-ten-neu-de-trong">
          </div>
          <div class="col-12">
            <div class="small text-secondary">Ngôn ngữ: <span class="badge text-bg-light text-dark"><?php echo normalize_content_language((string) ($values['language'] ?? 'vi')) === 'en' ? 'English' : 'Tiếng Việt'; ?></span></div>
          </div>

          <div class="col-12">
            <label class="form-label" for="description">Mô tả</label>
            <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($values['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <div class="col-12">
            <div class="h6 mb-2">SEO</div>
          </div>

          <div class="col-12">
            <label class="form-label" for="seo_title">SEO Title</label>
            <input id="seo_title" name="seo_title" class="form-control" value="<?php echo htmlspecialchars($values['seo_title'], ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div class="col-12">
            <label class="form-label" for="seo_description">SEO Description</label>
            <textarea id="seo_description" name="seo_description" class="form-control" rows="3"><?php echo htmlspecialchars($values['seo_description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <div class="col-12 d-grid d-sm-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Lưu</button>
            <?php if ($isEdit): ?>
              <span class="text-secondary small align-self-center">ID: <?php echo $id; ?></span>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
