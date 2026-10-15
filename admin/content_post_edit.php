<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_cam_nang.php';

admin_require_login();

$pdo = db();

$isModal = (isset($_GET['modal']) && (string) $_GET['modal'] === '1') || (isset($_POST['modal']) && (string) $_POST['modal'] === '1');
$isHandbook = (isset($_GET['handbook']) && (string) $_GET['handbook'] === '1') || (isset($_POST['handbook']) && (string) $_POST['handbook'] === '1');
$backUrl = $isHandbook ? '/admin/cam_nang.php' : '/admin/content.php';
$handbookCategories = $isHandbook ? admin_handbook_categories($pdo) : [];
$hasFeaturedImage = db_has_column($pdo, 'posts', 'featured_image_url');
$hasTemplateMode = db_has_column($pdo, 'posts', 'template');

$id = $isHandbook && $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int) ($_POST['id'] ?? 0)
    : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
$isEdit = $id > 0;
$prefCategoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$defaultStatus = trim((string) ($_GET['status'] ?? 'draft'));
if (!in_array($defaultStatus, ['draft', 'published'], true)) {
    $defaultStatus = 'draft';
}

$values = [
    'category_id' => $prefCategoryId > 0 ? (string) $prefCategoryId : '',
    'title' => trim((string) ($_GET['title'] ?? '')),
    'slug' => trim((string) ($_GET['slug'] ?? '')),
    'language' => normalize_content_language((string) ($isHandbook && $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['language'] ?? 'vi') : ($_GET['language'] ?? 'vi'))),
    'featured_image_url' => '',
    'template' => ((string) ($_GET['template'] ?? '0') === '1') ? '1' : '0',
    'excerpt' => trim((string) ($_GET['excerpt'] ?? '')),
    'content' => '',
    'seo_title' => trim((string) ($_GET['seo_title'] ?? '')),
    'seo_description' => trim((string) ($_GET['seo_description'] ?? '')),
    'seo_keywords' => trim((string) ($_GET['seo_keywords'] ?? '')),
    'status' => $defaultStatus,
    'linked_post_id' => '0',
];

if ($isHandbook && !$isEdit) {
    // A new article always starts in the language's existing handbook category.
    $values['category_id'] = (string) admin_handbook_category_id($handbookCategories, $values['language']);
}

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row || ($isHandbook && !admin_handbook_contains_post($handbookCategories, $row))) {
        if ($isModal) {
            json_response(['ok' => false, 'message' => 'Trang không tồn tại.'], 404);
        }
        flash_toast_set('danger', 'Trang không tồn tại.', 'fa-solid fa-triangle-exclamation');
        header('Location: ' . $backUrl);
        exit;
    }
    $values['category_id'] = ($row['category_id'] !== null) ? (string) (int) $row['category_id'] : '';
    $values['title'] = (string) ($row['title'] ?? '');
    $values['slug'] = (string) ($row['slug'] ?? '');
    $values['language'] = normalize_content_language((string) ($row['language'] ?? 'vi'));
    $values['featured_image_url'] = $hasFeaturedImage ? (string) ($row['featured_image_url'] ?? '') : '';
    $values['template'] = $hasTemplateMode ? ((int) ($row['template'] ?? 0) === 1 ? '1' : '0') : '0';
    $values['excerpt'] = (string) ($row['excerpt'] ?? '');
    $values['content'] = (string) ($row['content'] ?? '');
    $values['seo_title'] = (string) ($row['seo_title'] ?? '');
    $values['seo_description'] = (string) ($row['seo_description'] ?? '');
    $values['seo_keywords'] = (string) ($row['seo_keywords'] ?? '');
    $values['status'] = (string) ($row['status'] ?? 'draft');
    if ($isHandbook) {
        $linked = handbook_translation_current($pdo,$id);
        $values['linked_post_id'] = (string) ($linked['id'] ?? 0);
    }
}

$handbookCategoryId = (int) $values['category_id'];
$handbookLanguage = $values['language'];
// Missing handbook categories must not block opening the add form. A valid,
// explicit save creates the chosen language category and article together.
if ($isHandbook) $values['template'] = '0';

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$errors = [];

function render_post_modal_form(array $values, array $errors, array $categories, bool $isEdit, int $id, bool $isHandbook = false): string
{
    ob_start();
    $mediaFieldId = 'post_featured_image_modal';
    $mediaFieldLabel = 'Hình ảnh';
    $mediaFieldName = 'featured_image_url';
    $mediaFieldValue = (string) ($values['featured_image_url'] ?? '');
    $templateMode = ((string) ($values['template'] ?? '0') === '1');
    $postSlug = trim((string) ($values['slug'] ?? ''));
    $templateEditUrl = $postSlug !== '' ? '/' . rawurlencode($postSlug) . '?edit_template=1' : '';
    ?>
    <div class="p-2 p-sm-0">
      <?php if (count($errors) > 0): ?>
        <div class="alert alert-danger" role="alert" tabindex="-1">
          <?php foreach ($errors as $e): ?>
            <div><?php echo htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="/admin/content_post_edit.php?modal=1<?php echo $isHandbook ? '&amp;handbook=1' : ''; ?>" class="row g-3" data-modal-form="1" data-post-form="1">
        <input type="hidden" name="modal" value="1">
        <?php if ($isHandbook): ?>
          <input type="hidden" name="handbook" value="1">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <?php if ($isEdit): ?>
          <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
        <?php endif; ?>
        <input type="hidden" name="category_id" value="<?php echo htmlspecialchars((string) ($values['category_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (!$isHandbook): ?><input type="hidden" name="language" value="<?php echo htmlspecialchars(normalize_content_language((string) ($values['language'] ?? 'vi')), ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
        <input type="hidden" name="template" value="<?php echo $templateMode ? '1' : '0'; ?>">

        <div class="col-12">
          <div class="row g-3">
            <div class="col-12 col-md-8">
              <label class="form-label" for="title">Tiêu đề</label>
              <input id="title" name="title" class="form-control" value="<?php echo htmlspecialchars((string) ($values['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label" for="status">Trạng thái</label>
              <select id="status" name="status" class="form-select">
                <option value="draft" <?php echo (($values['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>draft</option>
                <option value="published" <?php echo (($values['status'] ?? '') === 'published') ? 'selected' : ''; ?>>published</option>
              </select>
            </div>
            <div class="col-12<?php echo $isHandbook && !$isEdit ? ' col-md-4' : ''; ?>">
              <?php if ($isHandbook): echo admin_handbook_language_field($values,$id); else: ?>
                <div class="small text-secondary">Ngôn ngữ: <span class="badge text-bg-light text-dark"><?php echo normalize_content_language((string) ($values['language'] ?? 'vi')) === 'en' ? 'English' : 'Tiếng Việt'; ?></span></div>
              <?php endif; ?>
            </div>
            <?php if ($isHandbook) echo admin_handbook_translation_field(db(),$values,$id); ?>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label" for="excerpt">Mô tả ngắn</label>
          <textarea id="excerpt" name="excerpt" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($values['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <?php require __DIR__ . '/_media_image_field.php'; ?>

        <div class="col-12 col-lg-8">
          <?php if ($templateMode): ?>
            <div class="border rounded-4 bg-white p-4">
              <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                <label class="form-label mb-0">Nội dung</label>
                <span class="badge text-bg-primary">Template mode</span>
              </div>
              <p class="text-secondary mb-3">Bài viết này đang dùng template. Nội dung sẽ được chỉnh sửa ngoài trang hiển thị thay vì CKEditor.</p>
              <?php if ($isEdit && $templateEditUrl !== ''): ?>
                <a class="btn btn-primary" href="<?php echo htmlspecialchars($templateEditUrl, ENT_QUOTES, 'UTF-8'); ?>">
                  <i class="fa-solid fa-wand-magic-sparkles me-2" aria-hidden="true"></i>Edit bằng template
                </a>
              <?php else: ?>
                <div class="alert alert-warning mb-0">Cần lưu bài viết và có `slug` trước khi mở trang template.</div>
              <?php endif; ?>
              <textarea name="content" class="d-none" data-ckeditor-source><?php echo htmlspecialchars((string) ($values['content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
          <?php else: ?>
            <div class="col-12" data-ckeditor-wrap>
              <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <label class="form-label mb-0">Nội dung</label>
                <button type="button" class="btn btn-outline-primary btn-sm" data-ckeditor-insert-image="1">
                  <i class="fa-solid fa-images me-2" aria-hidden="true"></i>Thêm ảnh từ thư viện
                </button>
              </div>
              <div class="border rounded-4 bg-white p-2" style="min-height: 260px;" data-ckeditor-target></div>
              <textarea name="content" class="d-none" data-ckeditor-source><?php echo htmlspecialchars((string) ($values['content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
          <?php endif; ?>
        </div>
        <div class="col-12 col-lg-4">
          <div class="border rounded-4 bg-white p-3" data-yoast-panel data-yoast-domain=""<?php if($isHandbook):?> data-yoast-url-prefix="<?php echo($values['language']??'vi')==='en'?'/en/cam-nang/':'/cam-nang/';?>"<?php endif;?>>
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
            <button class="btn btn-outline-secondary w-100 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#seoFieldsPostModal" aria-expanded="false" aria-controls="seoFieldsPostModal">
              <i class="fa-solid fa-sliders me-2" aria-hidden="true"></i>Cấu hình SEO
            </button>
            <div class="collapse mt-3" id="seoFieldsPostModal">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label" for="slug">Slug</label>
                  <input id="slug" name="slug" class="form-control mono" value="<?php echo htmlspecialchars((string) ($values['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="tu-dong-theo-tieu-de-neu-de-trong">
                </div>
                <div class="col-12">
                  <label class="form-label" for="seo_keywords">Từ khóa (focus + phụ)</label>
                  <input id="seo_keywords" name="seo_keywords" class="form-control" value="<?php echo htmlspecialchars((string) ($values['seo_keywords'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="tu-khoa-chinh, tu-khoa-phu">
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
    $values['title'] = trim((string) ($_POST['title'] ?? ''));
    $values['slug'] = trim((string) ($_POST['slug'] ?? ''));
    $values['language'] = normalize_content_language((string) ($_POST['language'] ?? 'vi'));
    $values['featured_image_url'] = trim((string) ($_POST['featured_image_url'] ?? ''));
    $values['template'] = ((string) ($_POST['template'] ?? '0') === '1' || isset($_POST['enable_template'])) ? '1' : '0';
    $values['excerpt'] = trim((string) ($_POST['excerpt'] ?? ''));
    $values['content'] = trim((string) ($_POST['content'] ?? ''));
    $values['seo_title'] = trim((string) ($_POST['seo_title'] ?? ''));
    $values['seo_description'] = trim((string) ($_POST['seo_description'] ?? ''));
    $values['seo_keywords'] = trim((string) ($_POST['seo_keywords'] ?? ''));
    $values['status'] = trim((string) ($_POST['status'] ?? 'draft'));

    if ($isHandbook) {
        $values['template'] = '0';
        if (!is_string($_POST['language'] ?? null) || !in_array($_POST['language'],['vi','en'],true)) $errors[] = 'Ngôn ngữ bài Cẩm nang phải là vi hoặc en.';
        $submittedLink = $_POST['linked_post_id'] ?? '0';
        if (!is_string($submittedLink) || preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$submittedLink)!==1) {
            $errors[] = 'Bài liên kết cần ID hợp lệ từ danh sách bản dịch.';
            $submittedLink = '0';
        }
        $values['linked_post_id'] = $submittedLink;
        $expectedToken = (string) ($_SESSION['admin_csrf_token'] ?? '');
        $providedToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            $errors[] = 'Yêu cầu đã hết hạn hoặc không hợp lệ. Tải lại trang rồi thử lại.';
        }
        // New forms may retain the old hidden category after choosing another
        // language. Derive it server-side; existing articles remain immutable.
        if ($isEdit && ((int) $values['category_id'] !== $handbookCategoryId || $values['language'] !== $handbookLanguage)) {
            $errors[] = 'Không thể thay đổi chuyên mục hoặc ngôn ngữ của bài viết trong màn Cẩm nang.';
        }
        $values['category_id'] = (string) $handbookCategoryId;
        $values['language'] = $handbookLanguage;
    }

    if ($values['title'] === '') {
        $errors[] = 'Vui lòng nhập tiêu đề trang.';
    }
    if (mb_strlen($values['title']) > 160) {
        $errors[] = 'Tiêu đề quá dài (tối đa 160 ký tự).';
    }

    $allowedStatus = ['draft', 'published'];
    if (!in_array($values['status'], $allowedStatus, true)) {
        $values['status'] = 'draft';
    }

    $catId = null;
    if ($values['category_id'] !== '') {
        $candidate = (int) $values['category_id'];
        if ($candidate > 0) {
            $st = $pdo->prepare('SELECT 1 FROM categories WHERE id = :id LIMIT 1');
            $st->execute([':id' => $candidate]);
            if ($st->fetchColumn()) {
                $catId = $candidate;
            } else {
                $errors[] = 'Chuyên mục không hợp lệ.';
            }
        }
    }

    $slugInput = $values['slug'] !== '' ? $values['slug'] : $values['title'];
    $values['slug'] = unique_slug($pdo, 'posts', $slugInput, $isEditPost ? $postId : null);

    if (mb_strlen($values['seo_title']) > 160) {
        $errors[] = 'SEO title quá dài (tối đa 160 ký tự).';
    }
    if (mb_strlen($values['seo_description']) > 300) {
        $errors[] = 'SEO description quá dài (tối đa 300 ký tự).';
    }
    if (mb_strlen($values['seo_keywords']) > 255) {
        $errors[] = 'SEO keywords quá dài (tối đa 255 ký tự).';
    }

    if (count($errors) === 0 && $isHandbook) {
        try {
            $savedId = admin_handbook_save($pdo,$values,$postId,[
                'has_featured_image'=>$hasFeaturedImage,'has_template'=>$hasTemplateMode,
                'update_link'=>array_key_exists('linked_post_id',$_POST),'linked_post_id'=>(int)$values['linked_post_id']]);
            if ($isModal) json_response(['ok'=>true,'message'=>'Đã lưu bài Cẩm nang.','id'=>$savedId]);
            flash_toast_set('success','Đã lưu bài Cẩm nang.','fa-solid fa-circle-check');
            header('Location: /admin/content_post_edit.php?id='.$savedId.'&handbook=1');
            exit;
        } catch (InvalidArgumentException $error) {
            $errors[] = $error->getMessage();
        } catch (Throwable $error) {
            error_log('Handbook editor save failed ('.get_class($error).').');
            $errors[] = 'Không lưu được bài. Vui lòng tải lại danh sách để kiểm tra slug/chuyên mục và thử lại; các nội dung đã nhập vẫn được giữ bên dưới.';
        }
    }

    if (count($errors) === 0 && !$isHandbook) {
        if ($isEditPost) {
            $sql = "UPDATE posts
                    SET category_id = :category_id,
                        title = :title,
                        slug = :slug,
                        language = :language,";
            if ($hasFeaturedImage) {
                $sql .= " featured_image_url = :featured_image_url,";
            }
            $sql .= " excerpt = :excerpt,
                      content = :content,
                      seo_title = :seo_title,
                      seo_description = :seo_description,
                      seo_keywords = :seo_keywords,
                      status = :status" . ($hasTemplateMode ? ",
                      template = :template" : '') . "
                    WHERE id = :id";

            $params = [
                ':category_id' => $catId,
                ':title' => $values['title'],
                ':slug' => $values['slug'],
                ':language' => $values['language'],
                ':excerpt' => ($values['excerpt'] !== '') ? $values['excerpt'] : null,
                ':content' => ($values['content'] !== '') ? $values['content'] : null,
                ':seo_title' => ($values['seo_title'] !== '') ? $values['seo_title'] : null,
                ':seo_description' => ($values['seo_description'] !== '') ? $values['seo_description'] : null,
                ':seo_keywords' => ($values['seo_keywords'] !== '') ? $values['seo_keywords'] : null,
                ':status' => $values['status'],
                ':id' => $postId,
            ];
            if ($hasTemplateMode) {
                $params[':template'] = ($values['template'] === '1') ? 1 : 0;
            }
            if ($hasFeaturedImage) {
                $params[':featured_image_url'] = ($values['featured_image_url'] !== '') ? $values['featured_image_url'] : null;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($isModal) {
                json_response(['ok' => true, 'message' => 'Đã lưu trang.', 'id' => $postId]);
            }
            flash_toast_set('success', 'Đã lưu trang.', 'fa-solid fa-circle-check');
            header('Location: /admin/content_post_edit.php?id=' . $postId . ($isHandbook ? '&handbook=1' : ''));
            exit;
        }

        if ($hasFeaturedImage) {
            $stmt = $pdo->prepare(
                "INSERT INTO posts (category_id, title, slug, language, featured_image_url, excerpt, content, seo_title, seo_description, seo_keywords, status" . ($hasTemplateMode ? ", template" : '') . ")
                 VALUES (:category_id, :title, :slug, :language, :featured_image_url, :excerpt, :content, :seo_title, :seo_description, :seo_keywords, :status" . ($hasTemplateMode ? ", :template" : '') . ")"
            );
            $params = [
                ':category_id' => $catId,
                ':title' => $values['title'],
                ':slug' => $values['slug'],
                ':language' => $values['language'],
                ':featured_image_url' => ($values['featured_image_url'] !== '') ? $values['featured_image_url'] : null,
                ':excerpt' => ($values['excerpt'] !== '') ? $values['excerpt'] : null,
                ':content' => ($values['content'] !== '') ? $values['content'] : null,
                ':seo_title' => ($values['seo_title'] !== '') ? $values['seo_title'] : null,
                ':seo_description' => ($values['seo_description'] !== '') ? $values['seo_description'] : null,
                ':seo_keywords' => ($values['seo_keywords'] !== '') ? $values['seo_keywords'] : null,
                ':status' => $values['status'],
            ];
            if ($hasTemplateMode) {
                $params[':template'] = ($values['template'] === '1') ? 1 : 0;
            }
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO posts (category_id, title, slug, language, excerpt, content, seo_title, seo_description, seo_keywords, status" . ($hasTemplateMode ? ", template" : '') . ")
                 VALUES (:category_id, :title, :slug, :language, :excerpt, :content, :seo_title, :seo_description, :seo_keywords, :status" . ($hasTemplateMode ? ", :template" : '') . ")"
            );
            $params = [
                ':category_id' => $catId,
                ':title' => $values['title'],
                ':slug' => $values['slug'],
                ':language' => $values['language'],
                ':excerpt' => ($values['excerpt'] !== '') ? $values['excerpt'] : null,
                ':content' => ($values['content'] !== '') ? $values['content'] : null,
                ':seo_title' => ($values['seo_title'] !== '') ? $values['seo_title'] : null,
                ':seo_description' => ($values['seo_description'] !== '') ? $values['seo_description'] : null,
                ':seo_keywords' => ($values['seo_keywords'] !== '') ? $values['seo_keywords'] : null,
                ':status' => $values['status'],
            ];
            if ($hasTemplateMode) {
                $params[':template'] = ($values['template'] === '1') ? 1 : 0;
            }
            $stmt->execute($params);
        }
        $newId = (int) $pdo->lastInsertId();
        if ($isModal) {
            json_response(['ok' => true, 'message' => 'Đã tạo trang.', 'id' => $newId]);
        }
        flash_toast_set('success', 'Đã tạo trang.', 'fa-solid fa-circle-check');
        header('Location: /admin/content_post_edit.php?id=' . $newId . ($isHandbook ? '&handbook=1' : ''));
        exit;
    }

    if ($isModal) {
        json_response([
            'ok' => false,
            'message' => 'Dữ liệu không hợp lệ.',
            'html' => render_post_modal_form($values, $errors, is_array($categories) ? $categories : [], $isEditPost, $postId, $isHandbook),
        ], 422);
    }
}

if ($isModal) {
    echo render_post_modal_form($values, [], is_array($categories) ? $categories : [], $isEdit, $id, $isHandbook);
    exit;
}

$adminPageTitle = $isEdit ? 'Admin • Sửa trang' : 'Admin • Thêm trang';
$adminHeaderTitle = $isEdit ? 'Sửa trang' : 'Thêm trang';
if ($isHandbook) {
    $adminPageTitle = $isEdit ? 'Admin • Sửa bài Cẩm nang' : 'Admin • Thêm bài Cẩm nang';
    $adminHeaderTitle = $isEdit ? 'Sửa bài Cẩm nang' : 'Thêm bài Cẩm nang';
}
$adminHeaderSubtitle = 'Tiêu đề, nội dung và SEO';
$adminActive = $isHandbook ? 'cam-nang' : 'content';
require __DIR__ . '/_layout_start.php';
?>

<div class="row g-3">
  <div class="col-12">
    <div class="card border-0 shadow-soft">
      <div class="card-body <?php echo $isHandbook ? 'p-3 p-md-4' : 'p-4'; ?>">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
          <div class="h5 mb-0"><?php echo htmlspecialchars($adminHeaderTitle, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary text-nowrap" href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>Quay lại</a>
            <?php if (!$isHandbook && $hasTemplateMode && $values['template'] !== '1'): ?>
              <button class="btn btn-outline-primary" type="submit" form="postEditForm" name="enable_template" value="1">
                <i class="fa-solid fa-wand-magic-sparkles me-2" aria-hidden="true"></i>Sử dụng template
              </button>
            <?php elseif ($hasTemplateMode && $isEdit && $values['template'] === '1' && trim((string) ($values['slug'] ?? '')) !== ''): ?>
              <a class="btn btn-outline-primary" href="/<?php echo rawurlencode(trim((string) $values['slug'])); ?>?edit_template=1">
                <i class="fa-solid fa-pen-ruler me-2" aria-hidden="true"></i>Edit bằng template
              </a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (count($errors) > 0): ?>
          <div class="alert alert-danger" role="alert" tabindex="-1" id="post-form-errors">
            <?php foreach ($errors as $e): ?>
              <div><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php
        $mediaFieldId = 'post_featured_image';
        $mediaFieldLabel = 'Hình ảnh';
        $mediaFieldName = 'featured_image_url';
        $mediaFieldValue = (string) ($values['featured_image_url'] ?? '');
        ?>
        <form method="post" class="row g-3" data-post-form="1" id="postEditForm">
          <?php if ($isHandbook): ?>
            <input type="hidden" name="handbook" value="1">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
          <?php endif; ?>
          <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
          <?php endif; ?>
          <input type="hidden" name="category_id" value="<?php echo htmlspecialchars((string) ($values['category_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
          <?php if (!$isHandbook): ?><input type="hidden" name="language" value="<?php echo htmlspecialchars(normalize_content_language((string) ($values['language'] ?? 'vi')), ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
          <input type="hidden" name="template" value="<?php echo ($hasTemplateMode && $values['template'] === '1') ? '1' : '0'; ?>">

          <div class="col-12 col-xl-8">
            <div<?php if (!$isHandbook): ?> class="border rounded-4 bg-white p-3"<?php endif; ?>>
              <div class="row g-3">
                <div class="col-12 col-md-8">
                  <label class="form-label" for="title">Tiêu đề</label>
                  <input id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($values['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label" for="status">Trạng thái</label>
                  <select id="status" name="status" class="form-select">
                    <option value="draft" <?php echo ($values['status'] === 'draft') ? 'selected' : ''; ?>>draft</option>
                    <option value="published" <?php echo ($values['status'] === 'published') ? 'selected' : ''; ?>>published</option>
                  </select>
                </div>
                <div class="col-12<?php echo $isHandbook && !$isEdit ? ' col-md-4' : ''; ?>">
                  <?php if ($isHandbook): echo admin_handbook_language_field($values,$id); else: ?>
                    <div class="small text-secondary">Ngôn ngữ: <span class="badge text-bg-light text-dark"><?php echo normalize_content_language((string) ($values['language'] ?? 'vi')) === 'en' ? 'English' : 'Tiếng Việt'; ?></span></div>
                  <?php endif; ?>
                </div>
                <?php if ($isHandbook) echo admin_handbook_translation_field($pdo,$values,$id); ?>
                <div class="col-12">
                  <label class="form-label" for="excerpt">Mô tả ngắn</label>
                  <textarea id="excerpt" name="excerpt" class="form-control" rows="3"><?php echo htmlspecialchars($values['excerpt'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
              </div>
            </div>

            <div class="mt-3">
              <?php require __DIR__ . '/_media_image_field.php'; ?>
            </div>

            <?php $templateMode = $hasTemplateMode && $values['template'] === '1'; ?>
            <?php $templateEditUrl = trim((string) ($values['slug'] ?? '')) !== '' ? '/' . rawurlencode(trim((string) $values['slug'])) . '?edit_template=1' : ''; ?>
            <?php if ($templateMode): ?>
              <div class="mt-3 border rounded-4 bg-white p-4">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                  <label class="form-label mb-0">Nội dung</label>
                  <span class="badge text-bg-primary">Template mode</span>
                </div>
                <p class="text-secondary mb-3">CKEditor đã được tắt cho bài viết này. Hãy mở trang theo `slug` để chỉnh sửa bằng template.</p>
                <?php if ($isEdit && $templateEditUrl !== ''): ?>
                  <a class="btn btn-primary" href="<?php echo htmlspecialchars($templateEditUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa-solid fa-pen-ruler me-2" aria-hidden="true"></i>Edit bằng template
                  </a>
                  <div class="small text-secondary mt-3">Link template: <a href="<?php echo htmlspecialchars($templateEditUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($templateEditUrl, ENT_QUOTES, 'UTF-8'); ?></a></div>
                <?php else: ?>
                  <div class="alert alert-warning mb-0">Cần lưu bài viết và có `slug` trước khi mở trang template.</div>
                <?php endif; ?>
                <textarea name="content" class="d-none" data-ckeditor-source><?php echo htmlspecialchars((string) $values['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
              </div>
            <?php else: ?>
              <div class="mt-3" data-ckeditor-wrap>
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                  <label class="form-label mb-0">Nội dung</label>
                  <button type="button" class="btn btn-outline-primary btn-sm" data-ckeditor-insert-image="1">
                    <i class="fa-solid fa-images me-2" aria-hidden="true"></i>Thêm ảnh từ thư viện
                  </button>
                </div>
                <div class="border rounded-4 bg-white p-2" style="min-height: 420px;" data-ckeditor-target></div>
                <textarea name="content" class="d-none" data-ckeditor-source><?php echo htmlspecialchars((string) $values['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
              </div>
            <?php endif; ?>
          </div>

          <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-soft">
              <div class="card-body p-3" data-yoast-panel data-yoast-domain=""<?php if($isHandbook):?> data-yoast-url-prefix="<?php echo($values['language']??'vi')==='en'?'/en/cam-nang/':'/cam-nang/';?>"<?php endif;?>>
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
                <button class="btn btn-outline-secondary w-100 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#seoFieldsPost" aria-expanded="false" aria-controls="seoFieldsPost">
                  <i class="fa-solid fa-sliders me-2" aria-hidden="true"></i>Cấu hình SEO
                </button>
                <div class="collapse mt-3" id="seoFieldsPost">
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label" for="slug">Slug</label>
                      <input id="slug" name="slug" class="form-control mono" value="<?php echo htmlspecialchars($values['slug'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="tu-dong-theo-tieu-de-neu-de-trong">
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="seo_keywords">Từ khóa (focus + phụ)</label>
                      <input id="seo_keywords" name="seo_keywords" class="form-control" value="<?php echo htmlspecialchars($values['seo_keywords'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="tu-khoa-chinh, tu-khoa-phu">
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="seo_title">SEO Title</label>
                      <input id="seo_title" name="seo_title" class="form-control" value="<?php echo htmlspecialchars($values['seo_title'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="seo_description">SEO Description</label>
                      <textarea id="seo_description" name="seo_description" class="form-control" rows="3"><?php echo htmlspecialchars($values['seo_description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 d-grid d-sm-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Lưu</button>
            <?php if ($isEdit): ?>
              <span class="text-secondary small align-self-center">ID: <?php echo $id; ?></span>
            <?php endif; ?>
          </div>
        </form>
        <script>
          window.addEventListener("DOMContentLoaded", function () {
            if (window.adminInitPostForms) window.adminInitPostForms(document);
            var errors = document.getElementById('post-form-errors');
            if (errors) errors.focus();
          });
        </script>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
