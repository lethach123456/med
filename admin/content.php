<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();

$adminPageTitle = 'Admin • Quản lý nội dung';
$adminHeaderTitle = 'Quản lý nội dung';
$adminHeaderSubtitle = 'Trang, chuyên mục, chuyên mục sản phẩm, chuyên mục dự án';
$adminActive = 'content';
require __DIR__ . '/_layout_start.php';

$pdo = db();
ensure_content_language_columns($pdo);
$categories = $pdo->query(
    'SELECT c.id, c.name, c.slug, c.language, c.updated_at,
            (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id) AS post_count
     FROM categories c
     ORDER BY c.updated_at DESC, c.id DESC'
)->fetchAll();

$posts = $pdo->query(
    "SELECT p.id, p.title, p.slug, p.language, p.status, p.updated_at,
            c.name AS category_name
     FROM posts p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.category_id IS NULL
     ORDER BY p.updated_at DESC, p.id DESC
     LIMIT 200"
)->fetchAll();

$productCategories = [];
$projectCategories = [];
try {
    $productCategories = $pdo->query(
        'SELECT c.id, c.name, c.slug, c.language, c.updated_at,
                (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS item_count
         FROM product_categories c
         ORDER BY c.updated_at DESC, c.id DESC'
    )->fetchAll();
    $projectCategories = $pdo->query(
        'SELECT c.id, c.name, c.slug, c.language, c.updated_at,
                (SELECT COUNT(*) FROM projects p WHERE p.category_id = c.id) AS item_count
         FROM project_categories c
         ORDER BY c.updated_at DESC, c.id DESC'
    )->fetchAll();
} catch (Throwable $e) {
    $productCategories = [];
    $projectCategories = [];
}

function fmt_dt(?string $value): string
{
    $value = (string) ($value ?? '');
    if ($value === '') return '';
    $ts = strtotime($value);
    if ($ts === false) return $value;
    return date('d/m/Y H:i', $ts);
}

function fmt_lang(?string $value): string
{
    return normalize_content_language((string) $value) === 'en' ? 'EN' : 'VI';
}

function front_editor_content_meta(): array
{
    $catalog = function_exists('front_editor_page_catalog') ? front_editor_page_catalog() : [];
    $notes = [
        'home' => 'Trang gốc `/` chỉ hỗ trợ tối ưu SEO, không đổi slug công khai từ màn này.',
        'co-so-y-te' => 'Trang danh sách cơ sở y tế. Chỉnh metadata SEO tại đây; route và bộ lọc tìm kiếm được giữ nguyên.',
        'bac-si' => 'Trang danh sách bác sĩ. Chỉnh metadata SEO tại đây; route và chức năng tìm kiếm được giữ nguyên.',
        'review' => 'Trang danh sách review. Chỉnh metadata SEO tại đây; route và phân trang được giữ nguyên.',
        'danh-muc-y-te' => 'Trang danh mục y tế. Chỉnh metadata SEO tại đây; route công khai được giữ nguyên.',
        'toplist' => 'Trang danh sách Toplist. Chỉnh metadata SEO tại đây; route và dữ liệu danh sách được giữ nguyên.',
        'products' => 'Có thể đổi slug listing sang route khác không đuôi `.php`. Route cũ vẫn được chuyển hướng để giữ SEO.',
        'projects' => 'Có thể đổi slug listing sang route khác không đuôi `.php`. Route cũ vẫn được chuyển hướng để giữ SEO.',
        'blog' => 'Có thể đổi slug listing blog sang route khác không đuôi `.php`. Chi tiết bài viết vẫn giữ routing riêng.',
        'contact' => 'Có thể lưu SEO và đổi slug đẹp cho trang liên hệ.',
        'about' => 'Có thể lưu SEO và đổi slug đẹp cho trang giới thiệu.',
        'dich-vu' => 'Có thể lưu SEO và đổi slug đẹp cho trang dịch vụ.',
        'services-en' => 'Trang dịch vụ tiếng Anh, hỗ trợ SEO riêng và slug đẹp để chạy landing page hoặc menu EN.',
        'about-en' => 'Trang giới thiệu tiếng Anh, hỗ trợ SEO riêng và slug đẹp cho phiên bản EN.',
        'blog-en' => 'Trang tin tức tiếng Anh, hỗ trợ SEO riêng và slug đẹp cho route `/news`.',
        'contact-en' => 'Trang liên hệ tiếng Anh, hỗ trợ SEO riêng và slug đẹp cho route `/contact-us`.',
        'rang-su-dang-hot-girl-da-nang' => 'Landing page hỗ trợ SEO riêng và slug đẹp để chạy quảng cáo/SEO.',
        'porcelain-crowns-da-nang' => 'Landing page tiếng Anh hỗ trợ SEO riêng và slug đẹp để chạy quảng cáo/SEO.',
    ];
    $result = [];
    foreach ($catalog as $pageKey => $meta) {
        $result[$pageKey] = [
            'title' => (string) ($meta['title'] ?? $pageKey),
            'route' => (string) ($meta['default_route'] ?? '/'),
            'profile_supported' => true,
            'note' => (string) ($notes[$pageKey] ?? ''),
        ];
    }
    return $result;
}

$allRows = [];
if (is_array($posts)) {
    foreach ($posts as $p) {
        $allRows[] = [
            'type' => 'post',
            'id' => (int) ($p['id'] ?? 0),
            'title' => (string) ($p['title'] ?? ''),
            'slug' => (string) ($p['slug'] ?? ''),
            'language' => normalize_content_language((string) ($p['language'] ?? 'vi')),
            'status' => (string) ($p['status'] ?? 'draft'),
            'count' => null,
            'updated_at' => (string) ($p['updated_at'] ?? ''),
        ];
    }
}
if (is_array($categories)) {
    foreach ($categories as $c) {
        $allRows[] = [
            'type' => 'category',
            'id' => (int) ($c['id'] ?? 0),
            'title' => (string) ($c['name'] ?? ''),
            'slug' => (string) ($c['slug'] ?? ''),
            'language' => normalize_content_language((string) ($c['language'] ?? 'vi')),
            'status' => null,
            'count' => (int) ($c['post_count'] ?? 0),
            'updated_at' => (string) ($c['updated_at'] ?? ''),
        ];
    }
}
if (is_array($productCategories)) {
    foreach ($productCategories as $c) {
        $allRows[] = [
            'type' => 'product_category',
            'id' => (int) ($c['id'] ?? 0),
            'title' => (string) ($c['name'] ?? ''),
            'slug' => (string) ($c['slug'] ?? ''),
            'language' => normalize_content_language((string) ($c['language'] ?? 'vi')),
            'status' => null,
            'count' => (int) ($c['item_count'] ?? 0),
            'updated_at' => (string) ($c['updated_at'] ?? ''),
        ];
    }
}
if (is_array($projectCategories)) {
    foreach ($projectCategories as $c) {
        $allRows[] = [
            'type' => 'project_category',
            'id' => (int) ($c['id'] ?? 0),
            'title' => (string) ($c['name'] ?? ''),
            'slug' => (string) ($c['slug'] ?? ''),
            'language' => normalize_content_language((string) ($c['language'] ?? 'vi')),
            'status' => null,
            'count' => (int) ($c['item_count'] ?? 0),
            'updated_at' => (string) ($c['updated_at'] ?? ''),
        ];
    }
}

$frontEditorRows = [];
$frontEditorFiles = function_exists('front_editor_allowed_pages') ? front_editor_allowed_pages() : [];
$frontEditorPages = function_exists('front_editor_page_catalog') ? front_editor_page_catalog() : [];
$frontEditorMeta = front_editor_content_meta();
foreach ($frontEditorPages as $pageKey => $pageConfig) {
    $filePath = (string) ($frontEditorFiles[$pageKey] ?? '');
    $meta = $frontEditorMeta[$pageKey] ?? [];
    $profile = function_exists('front_editor_page_profile') ? front_editor_page_profile((string) $pageKey) : [];
    $route = function_exists('front_editor_page_public_path')
        ? front_editor_page_public_path((string) $pageKey)
        : (string) ($meta['route'] ?? ('/' . basename((string) $filePath)));
    $title = (string) ($meta['title'] ?? ucwords(str_replace(['-', '_'], ' ', (string) $pageKey)));
    $fileModifiedAt = (is_string($filePath) && $filePath !== '' && is_file($filePath)) ? @filemtime($filePath) : false;
    $updatedAt = is_array($profile) && trim((string) ($profile['updated_at'] ?? '')) !== ''
        ? (string) ($profile['updated_at'] ?? '')
        : (($fileModifiedAt !== false) ? date('Y-m-d H:i:s', (int) $fileModifiedAt) : '');
    $defaultRoute = (string) ($pageConfig['default_route'] ?? $route);
    $frontEditorRows[] = [
        'page_key' => (string) $pageKey,
        'title' => $title,
        'route' => $route,
        'file_path' => (string) $filePath,
        'default_route' => $defaultRoute,
        'profile_supported' => !empty($profile['supports_slug']) || ((string) $pageKey === 'home'),
        'profile' => is_array($profile) ? $profile : [],
        'updated_at' => $updatedAt,
        'note' => (string) ($meta['note'] ?? ''),
    ];
}
usort($allRows, static function (array $a, array $b): int {
    return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
});
?>

<div class="row g-3">
  <div class="col-12">
    <div class="card border-0 shadow-soft">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <div class="h5 mb-1">Quản lý nội dung</div>
            <div class="text-secondary">Tạo/sửa trang, chuyên mục và các chuyên mục con cho sản phẩm/dự án.</div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Thêm
            </button>
          </div>
        </div>

        <div class="border rounded-4 bg-white">
          <div class="p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="fw-semibold">Danh sách nội dung</div>
            <div class="text-secondary small">
              <?php
              echo
                (is_array($posts) ? count($posts) : 0)
                + (is_array($categories) ? count($categories) : 0)
                + (is_array($productCategories) ? count($productCategories) : 0)
                + (is_array($projectCategories) ? count($projectCategories) : 0);
              ?> mục
            </div>
          </div>
          <div class="px-3 pt-3">
            <ul class="nav nav-pills nav-sm flex-wrap gap-2" id="contentTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-all" data-bs-toggle="pill" data-bs-target="#pane-all" type="button" role="tab" aria-controls="pane-all" aria-selected="true">
                  <i class="fa-solid fa-layer-group me-2" aria-hidden="true"></i>Tất cả
                  <span class="badge text-bg-light text-dark ms-2"><?php echo is_array($allRows) ? count($allRows) : 0; ?></span>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-pages" data-bs-toggle="pill" data-bs-target="#pane-pages" type="button" role="tab" aria-controls="pane-pages" aria-selected="false">
                  <i class="fa-regular fa-file-lines me-2" aria-hidden="true"></i>Trang
                  <span class="badge text-bg-light text-dark ms-2"><?php echo is_array($posts) ? count($posts) : 0; ?></span>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-front-editor" data-bs-toggle="pill" data-bs-target="#pane-front-editor" type="button" role="tab" aria-controls="pane-front-editor" aria-selected="false">
                  <i class="fa-solid fa-wand-magic-sparkles me-2" aria-hidden="true"></i>Trang &amp; SEO
                  <span class="badge text-bg-light text-dark ms-2"><?php echo is_array($frontEditorRows) ? count($frontEditorRows) : 0; ?></span>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-cats" data-bs-toggle="pill" data-bs-target="#pane-cats" type="button" role="tab" aria-controls="pane-cats" aria-selected="false">
                  <i class="fa-regular fa-folder me-2" aria-hidden="true"></i>Chuyên mục
                  <span class="badge text-bg-light text-dark ms-2"><?php echo is_array($categories) ? count($categories) : 0; ?></span>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-pcats" data-bs-toggle="pill" data-bs-target="#pane-pcats" type="button" role="tab" aria-controls="pane-pcats" aria-selected="false">
                  <i class="fa-solid fa-tags me-2" aria-hidden="true"></i>Chuyên mục sản phẩm
                  <span class="badge text-bg-light text-dark ms-2"><?php echo is_array($productCategories) ? count($productCategories) : 0; ?></span>
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-prcats" data-bs-toggle="pill" data-bs-target="#pane-prcats" type="button" role="tab" aria-controls="pane-prcats" aria-selected="false">
                  <i class="fa-solid fa-diagram-project me-2" aria-hidden="true"></i>Chuyên mục dự án
                  <span class="badge text-bg-light text-dark ms-2"><?php echo is_array($projectCategories) ? count($projectCategories) : 0; ?></span>
                </button>
              </li>
            </ul>
          </div>

          <div class="tab-content">
            <div class="tab-pane fade show active" id="pane-all" role="tabpanel" aria-labelledby="tab-all" tabindex="0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 160px;">Loại</th>
                      <th>Tiêu đề</th>
                      <th style="width: 100px;">Language</th>
                      <th style="width: 180px;">Thông tin</th>
                      <th style="width: 160px;">Cập nhật</th>
                      <th style="width: 320px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!is_array($allRows) || count($allRows) === 0): ?>
                      <tr><td colspan="6" class="text-secondary">Chưa có nội dung.</td></tr>
                    <?php else: ?>
                      <?php foreach ($allRows as $r): ?>
                        <tr>
                          <td>
                            <?php if (($r['type'] ?? '') === 'post'): ?>
                              <span class="badge text-bg-secondary">page</span>
                            <?php elseif (($r['type'] ?? '') === 'category'): ?>
                              <span class="badge text-bg-primary">category</span>
                            <?php elseif (($r['type'] ?? '') === 'product_category'): ?>
                              <span class="badge text-bg-warning text-dark">product_cat</span>
                            <?php else: ?>
                              <span class="badge text-bg-info text-dark">project_cat</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="small text-secondary"><?php echo htmlspecialchars((string) ($r['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                          </td>
                          <td>
                            <span class="badge <?php echo (($r['language'] ?? 'vi') === 'en') ? 'text-bg-info' : 'text-bg-light text-dark'; ?>"><?php echo htmlspecialchars(fmt_lang((string) ($r['language'] ?? 'vi')), ENT_QUOTES, 'UTF-8'); ?></span>
                          </td>
                          <td>
                            <?php if (($r['type'] ?? '') === 'post'): ?>
                              <?php if (($r['status'] ?? '') === 'published'): ?>
                                <span class="badge text-bg-success">published</span>
                              <?php else: ?>
                                <span class="badge text-bg-secondary">draft</span>
                              <?php endif; ?>
                            <?php elseif (($r['type'] ?? '') === 'category'): ?>
                              <span class="badge text-bg-secondary"><?php echo (int) ($r['count'] ?? 0); ?> trang</span>
                            <?php elseif (($r['type'] ?? '') === 'product_category'): ?>
                              <span class="badge text-bg-secondary"><?php echo (int) ($r['count'] ?? 0); ?> sản phẩm</span>
                            <?php else: ?>
                              <span class="badge text-bg-secondary"><?php echo (int) ($r['count'] ?? 0); ?> dự án</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-secondary small"><?php echo htmlspecialchars(fmt_dt((string) ($r['updated_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                          <td class="text-end">
                            <div class="btn-group btn-group-sm me-1" role="group" aria-label="Language actions">
                              <button class="btn <?php echo (($r['language'] ?? 'vi') === 'vi') ? 'btn-primary' : 'btn-outline-secondary'; ?>" type="button"
                                      <?php echo (($r['language'] ?? 'vi') === 'vi') ? 'disabled' : ''; ?>
                                      data-clone-language-type="<?php echo htmlspecialchars((string) ($r['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                      data-clone-language-id="<?php echo (int) ($r['id'] ?? 0); ?>"
                                      data-clone-language-target="vi"
                                      data-clone-language-title="<?php echo htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                      title="Tạo hoặc mở bản tiếng Việt">
                                VI
                              </button>
                              <button class="btn <?php echo (($r['language'] ?? 'vi') === 'en') ? 'btn-primary' : 'btn-outline-secondary'; ?>" type="button"
                                      <?php echo (($r['language'] ?? 'vi') === 'en') ? 'disabled' : ''; ?>
                                      data-clone-language-type="<?php echo htmlspecialchars((string) ($r['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                      data-clone-language-id="<?php echo (int) ($r['id'] ?? 0); ?>"
                                      data-clone-language-target="en"
                                      data-clone-language-title="<?php echo htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                      title="Tạo hoặc mở bản tiếng Anh">
                                EN
                              </button>
                            </div>
                            <?php if (($r['type'] ?? '') === 'post'): ?>
                              <a class="btn btn-sm btn-primary" href="/admin/content_post_edit.php?id=<?php echo (int) ($r['id'] ?? 0); ?>" title="Sửa">
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                              </a>
                              <button class="btn btn-sm btn-outline-danger ms-1" type="button"
                                      data-delete-type="post"
                                      data-delete-id="<?php echo (int) ($r['id'] ?? 0); ?>"
                                      data-delete-title="<?php echo htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                      title="Xoá">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                              </button>
                            <?php elseif (($r['type'] ?? '') === 'category'): ?>
                              <a class="btn btn-sm btn-primary" href="/admin/content_category.php?id=<?php echo (int) ($r['id'] ?? 0); ?>" title="Quản lý">
                                <i class="fa-solid fa-list" aria-hidden="true"></i>
                              </a>
                              <a class="btn btn-sm btn-outline-secondary ms-1" href="/admin/content_category_edit.php?id=<?php echo (int) ($r['id'] ?? 0); ?>" title="Sửa">
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                              </a>
                              <button class="btn btn-sm btn-outline-danger ms-1" type="button"
                                      data-delete-type="category"
                                      data-delete-id="<?php echo (int) ($r['id'] ?? 0); ?>"
                                      data-delete-title="<?php echo htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                      title="Xoá">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                              </button>
                            <?php elseif (($r['type'] ?? '') === 'product_category'): ?>
                              <a class="btn btn-sm btn-primary" href="/admin/content_product_category.php?id=<?php echo (int) ($r['id'] ?? 0); ?>" title="Quản lý">
                                <i class="fa-solid fa-list" aria-hidden="true"></i>
                              </a>
                              <a class="btn btn-sm btn-outline-secondary ms-1" href="/admin/content_product_category_edit.php?id=<?php echo (int) ($r['id'] ?? 0); ?>" title="Sửa">
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                              </a>
                              <button class="btn btn-sm btn-outline-danger ms-1" type="button"
                                      data-delete-type="product_category"
                                      data-delete-id="<?php echo (int) ($r['id'] ?? 0); ?>"
                                      data-delete-title="<?php echo htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                      title="Xoá">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                              </button>
                            <?php else: ?>
                              <a class="btn btn-sm btn-primary" href="/admin/content_project_category.php?id=<?php echo (int) ($r['id'] ?? 0); ?>" title="Quản lý">
                                <i class="fa-solid fa-list" aria-hidden="true"></i>
                              </a>
                              <a class="btn btn-sm btn-outline-secondary ms-1" href="/admin/content_project_category_edit.php?id=<?php echo (int) ($r['id'] ?? 0); ?>" title="Sửa">
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                              </a>
                              <button class="btn btn-sm btn-outline-danger ms-1" type="button"
                                      data-delete-type="project_category"
                                      data-delete-id="<?php echo (int) ($r['id'] ?? 0); ?>"
                                      data-delete-title="<?php echo htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                      title="Xoá">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                              </button>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="tab-pane fade" id="pane-pages" role="tabpanel" aria-labelledby="tab-pages" tabindex="0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Tiêu đề</th>
                      <th style="width: 100px;">Language</th>
                      <th style="width: 130px;">Trạng thái</th>
                      <th style="width: 160px;">Cập nhật</th>
                      <th style="width: 280px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!is_array($posts) || count($posts) === 0): ?>
                      <tr><td colspan="5" class="text-secondary">Chưa có trang độc lập (không thuộc chuyên mục).</td></tr>
                    <?php else: ?>
                      <?php foreach ($posts as $p): ?>
                        <tr>
                          <td>
                            <div class="d-flex align-items-start gap-2">
                              <i class="fa-regular fa-file-lines text-secondary mt-1" aria-hidden="true"></i>
                              <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($p['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="small text-secondary"><?php echo htmlspecialchars((string) ($p['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                              </div>
                            </div>
                          </td>
                          <td>
                            <span class="badge <?php echo (($p['language'] ?? 'vi') === 'en') ? 'text-bg-info' : 'text-bg-light text-dark'; ?>"><?php echo htmlspecialchars(fmt_lang((string) ($p['language'] ?? 'vi')), ENT_QUOTES, 'UTF-8'); ?></span>
                          </td>
                          <td>
                            <?php if (($p['status'] ?? '') === 'published'): ?>
                              <span class="badge text-bg-success">published</span>
                            <?php else: ?>
                              <span class="badge text-bg-secondary">draft</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-secondary small"><?php echo htmlspecialchars(fmt_dt((string) ($p['updated_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                          <td class="text-end">
                            <div class="btn-group btn-group-sm me-1" role="group" aria-label="Language actions">
                              <button class="btn <?php echo (($p['language'] ?? 'vi') === 'vi') ? 'btn-primary' : 'btn-outline-secondary'; ?>" type="button"
                                      <?php echo (($p['language'] ?? 'vi') === 'vi') ? 'disabled' : ''; ?>
                                      data-clone-language-type="post"
                                      data-clone-language-id="<?php echo (int) ($p['id'] ?? 0); ?>"
                                      data-clone-language-target="vi"
                                      data-clone-language-title="<?php echo htmlspecialchars((string) ($p['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">VI</button>
                              <button class="btn <?php echo (($p['language'] ?? 'vi') === 'en') ? 'btn-primary' : 'btn-outline-secondary'; ?>" type="button"
                                      <?php echo (($p['language'] ?? 'vi') === 'en') ? 'disabled' : ''; ?>
                                      data-clone-language-type="post"
                                      data-clone-language-id="<?php echo (int) ($p['id'] ?? 0); ?>"
                                      data-clone-language-target="en"
                                      data-clone-language-title="<?php echo htmlspecialchars((string) ($p['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">EN</button>
                            </div>
                            <a class="btn btn-sm btn-primary" href="/admin/content_post_edit.php?id=<?php echo (int) ($p['id'] ?? 0); ?>" title="Sửa">
                              <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger ms-1" type="button"
                                    data-delete-type="post"
                                    data-delete-id="<?php echo (int) ($p['id'] ?? 0); ?>"
                                    data-delete-title="<?php echo htmlspecialchars((string) ($p['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Xoá">
                              <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="tab-pane fade" id="pane-front-editor" role="tabpanel" aria-labelledby="tab-front-editor" tabindex="0">
              <div class="p-3 border-bottom bg-light-subtle">
                <div class="small text-secondary">
                  Danh sách gồm trang chủ, các trang danh sách y tế và các trang front editor. Bấm nút cài đặt để chỉnh SEO Title, Description, Keywords; chỉ những trang hỗ trợ slug mới có thể đổi đường dẫn. Cấu hình này không thay đổi dữ liệu hay chức năng của trang.
                </div>
              </div>
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Trang</th>
                      <th style="width: 180px;">Route</th>
                      <th style="width: 320px;">Hồ sơ SEO</th>
                      <th style="width: 160px;">Cập nhật</th>
                      <th style="width: 280px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!is_array($frontEditorRows) || count($frontEditorRows) === 0): ?>
                      <tr><td colspan="5" class="text-secondary">Chưa có trang front editor nào.</td></tr>
                    <?php else: ?>
                      <?php foreach ($frontEditorRows as $row): ?>
                        <?php
                          $profile = is_array($row['profile'] ?? null) ? $row['profile'] : [];
                          $customSlug = trim((string) ($profile['slug'] ?? ''));
                          $supportsSlug = !empty($profile['supports_slug']);
                          $seoTitle = trim((string) ($profile['seo_title'] ?? ''));
                          $seoDescription = trim((string) ($profile['seo_description'] ?? ''));
                          $seoKeywords = trim((string) ($profile['seo_keywords'] ?? ''));
                        ?>
                        <tr>
                          <td>
                            <div class="d-flex align-items-start gap-2">
                              <i class="fa-solid fa-wand-magic-sparkles text-secondary mt-1" aria-hidden="true"></i>
                              <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="small text-secondary">
                                  key: <code><?php echo htmlspecialchars((string) ($row['page_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
                                  • <?php echo trim((string) ($row['file_path'] ?? '')) !== '' ? 'file: ' . htmlspecialchars(basename((string) $row['file_path']), ENT_QUOTES, 'UTF-8') : 'trang SEO'; ?>
                                </div>
                                <?php if (trim((string) ($row['note'] ?? '')) !== ''): ?>
                                  <div class="small text-secondary mt-1"><?php echo htmlspecialchars((string) ($row['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                              </div>
                            </div>
                          </td>
                          <td>
                            <div class="fw-semibold mono"><?php echo htmlspecialchars((string) ($row['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="small text-secondary">Route công khai hiện tại</div>
                          </td>
                          <td>
                            <?php if ($seoTitle !== '' || $seoDescription !== '' || $customSlug !== '' || $seoKeywords !== ''): ?>
                              <div class="fw-semibold"><?php echo htmlspecialchars($seoTitle !== '' ? $seoTitle : 'Đã có cấu hình SEO', ENT_QUOTES, 'UTF-8'); ?></div>
                              <div class="small text-secondary">
                                <?php if ($supportsSlug): ?>
                                  slug: <?php echo htmlspecialchars($customSlug !== '' ? $customSlug : trim((string) ($row['default_route'] ?? ''), '/'), ENT_QUOTES, 'UTF-8'); ?>
                                <?php else: ?>
                                  Trang này chỉ tối ưu SEO, không đổi slug.
                                <?php endif; ?>
                              </div>
                              <div class="d-flex flex-wrap gap-1 mt-2">
                                <?php if ($seoTitle !== ''): ?><span class="badge text-bg-success">SEO title</span><?php endif; ?>
                                <?php if ($seoDescription !== ''): ?><span class="badge text-bg-info">Description</span><?php endif; ?>
                                <?php if ($seoKeywords !== ''): ?><span class="badge text-bg-light text-dark">Keywords</span><?php endif; ?>
                                <?php if ($supportsSlug && $customSlug !== ''): ?><span class="badge text-bg-primary">Slug custom</span><?php endif; ?>
                              </div>
                            <?php else: ?>
                              <div class="fw-semibold text-secondary">Chưa có hồ sơ SEO</div>
                              <div class="small text-secondary">Có thể mở popup để lưu SEO title, description và keywords<?php echo $supportsSlug ? ', cùng slug riêng cho trang này.' : ' cho trang này.'; ?></div>
                            <?php endif; ?>
                          </td>
                          <td class="text-secondary small"><?php echo htmlspecialchars(fmt_dt((string) ($row['updated_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                          <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars((string) ($row['route'] ?? '/'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" title="Mở trang front editor">
                              <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                            </a>
                            <button class="btn btn-sm btn-primary ms-1" type="button"
                                    data-front-editor-seo="1"
                                    data-page-key="<?php echo htmlspecialchars((string) ($row['page_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-page-title="<?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-default-route="<?php echo htmlspecialchars((string) ($row['default_route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-current-route="<?php echo htmlspecialchars((string) ($row['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-supports-slug="<?php echo $supportsSlug ? '1' : '0'; ?>"
                                    data-slug="<?php echo htmlspecialchars($customSlug, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-seo-title="<?php echo htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-seo-description="<?php echo htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-seo-keywords="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"
                                    title="SEO/slug">
                              <i class="fa-solid fa-gear" aria-hidden="true"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="tab-pane fade" id="pane-cats" role="tabpanel" aria-labelledby="tab-cats" tabindex="0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Tên chuyên mục</th>
                      <th style="width: 100px;">Language</th>
                      <th style="width: 140px;">Số trang</th>
                      <th style="width: 160px;">Cập nhật</th>
                      <th style="width: 320px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!is_array($categories) || count($categories) === 0): ?>
                      <tr><td colspan="5" class="text-secondary">Chưa có chuyên mục.</td></tr>
                    <?php else: ?>
                      <?php foreach ($categories as $c): ?>
                        <tr>
                          <td>
                            <div class="d-flex align-items-start gap-2">
                              <i class="fa-regular fa-folder text-secondary mt-1" aria-hidden="true"></i>
                              <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="small text-secondary"><?php echo htmlspecialchars((string) ($c['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                              </div>
                            </div>
                          </td>
                          <td><span class="badge <?php echo (($c['language'] ?? 'vi') === 'en') ? 'text-bg-info' : 'text-bg-light text-dark'; ?>"><?php echo htmlspecialchars(fmt_lang((string) ($c['language'] ?? 'vi')), ENT_QUOTES, 'UTF-8'); ?></span></td>
                          <td><span class="badge text-bg-secondary"><?php echo (int) ($c['post_count'] ?? 0); ?></span></td>
                          <td class="text-secondary small"><?php echo htmlspecialchars(fmt_dt((string) ($c['updated_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                          <td class="text-end">
                            <div class="btn-group btn-group-sm me-1" role="group" aria-label="Language actions">
                              <button class="btn <?php echo (($c['language'] ?? 'vi') === 'vi') ? 'btn-primary' : 'btn-outline-secondary'; ?>" type="button"
                                      <?php echo (($c['language'] ?? 'vi') === 'vi') ? 'disabled' : ''; ?>
                                      data-clone-language-type="category"
                                      data-clone-language-id="<?php echo (int) ($c['id'] ?? 0); ?>"
                                      data-clone-language-target="vi"
                                      data-clone-language-title="<?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">VI</button>
                              <button class="btn <?php echo (($c['language'] ?? 'vi') === 'en') ? 'btn-primary' : 'btn-outline-secondary'; ?>" type="button"
                                      <?php echo (($c['language'] ?? 'vi') === 'en') ? 'disabled' : ''; ?>
                                      data-clone-language-type="category"
                                      data-clone-language-id="<?php echo (int) ($c['id'] ?? 0); ?>"
                                      data-clone-language-target="en"
                                      data-clone-language-title="<?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">EN</button>
                            </div>
                            <a class="btn btn-sm btn-primary" href="/admin/content_category.php?id=<?php echo (int) ($c['id'] ?? 0); ?>" title="Quản lý">
                              <i class="fa-solid fa-list" aria-hidden="true"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary ms-1" href="/admin/content_category_edit.php?id=<?php echo (int) ($c['id'] ?? 0); ?>" title="Sửa">
                              <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger ms-1" type="button"
                                    data-delete-type="category"
                                    data-delete-id="<?php echo (int) ($c['id'] ?? 0); ?>"
                                    data-delete-title="<?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Xoá">
                              <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="tab-pane fade" id="pane-pcats" role="tabpanel" aria-labelledby="tab-pcats" tabindex="0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Chuyên mục sản phẩm</th>
                      <th style="width: 100px;">Language</th>
                      <th style="width: 140px;">Số sản phẩm</th>
                      <th style="width: 160px;">Cập nhật</th>
                      <th style="width: 320px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!is_array($productCategories) || count($productCategories) === 0): ?>
                      <tr><td colspan="5" class="text-secondary">Chưa có chuyên mục sản phẩm.</td></tr>
                    <?php else: ?>
                      <?php foreach ($productCategories as $c): ?>
                        <tr>
                          <td>
                            <div class="d-flex align-items-start gap-2">
                              <i class="fa-solid fa-tags text-secondary mt-1" aria-hidden="true"></i>
                              <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="small text-secondary"><?php echo htmlspecialchars((string) ($c['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                              </div>
                            </div>
                          </td>
                          <td><span class="badge <?php echo (($c['language'] ?? 'vi') === 'en') ? 'text-bg-info' : 'text-bg-light text-dark'; ?>"><?php echo htmlspecialchars(fmt_lang((string) ($c['language'] ?? 'vi')), ENT_QUOTES, 'UTF-8'); ?></span></td>
                          <td><span class="badge text-bg-secondary"><?php echo (int) ($c['item_count'] ?? 0); ?></span></td>
                          <td class="text-secondary small"><?php echo htmlspecialchars(fmt_dt((string) ($c['updated_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                          <td class="text-end">
                            <div class="btn-group btn-group-sm me-1" role="group" aria-label="Language actions">
                              <button class="btn <?php echo (($c['language'] ?? 'vi') === 'vi') ? 'btn-primary' : 'btn-outline-secondary'; ?>" type="button"
                                      <?php echo (($c['language'] ?? 'vi') === 'vi') ? 'disabled' : ''; ?>
                                      data-clone-language-type="product_category"
                                      data-clone-language-id="<?php echo (int) ($c['id'] ?? 0); ?>"
                                      data-clone-language-target="vi"
                                      data-clone-language-title="<?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">VI</button>
                              <button class="btn <?php echo (($c['language'] ?? 'vi') === 'en') ? 'btn-primary' : 'btn-outline-secondary'; ?>" type="button"
                                      <?php echo (($c['language'] ?? 'vi') === 'en') ? 'disabled' : ''; ?>
                                      data-clone-language-type="product_category"
                                      data-clone-language-id="<?php echo (int) ($c['id'] ?? 0); ?>"
                                      data-clone-language-target="en"
                                      data-clone-language-title="<?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">EN</button>
                            </div>
                            <a class="btn btn-sm btn-primary" href="/admin/content_product_category.php?id=<?php echo (int) ($c['id'] ?? 0); ?>" title="Quản lý">
                              <i class="fa-solid fa-list" aria-hidden="true"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary ms-1" href="/admin/content_product_category_edit.php?id=<?php echo (int) ($c['id'] ?? 0); ?>" title="Sửa">
                              <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger ms-1" type="button"
                                    data-delete-type="product_category"
                                    data-delete-id="<?php echo (int) ($c['id'] ?? 0); ?>"
                                    data-delete-title="<?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Xoá">
                              <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="tab-pane fade" id="pane-prcats" role="tabpanel" aria-labelledby="tab-prcats" tabindex="0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Chuyên mục dự án</th>
                      <th style="width: 100px;">Language</th>
                      <th style="width: 140px;">Số dự án</th>
                      <th style="width: 160px;">Cập nhật</th>
                      <th style="width: 320px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!is_array($projectCategories) || count($projectCategories) === 0): ?>
                      <tr><td colspan="5" class="text-secondary">Chưa có chuyên mục dự án.</td></tr>
                    <?php else: ?>
                      <?php foreach ($projectCategories as $c): ?>
                        <tr>
                          <td>
                            <div class="d-flex align-items-start gap-2">
                              <i class="fa-solid fa-diagram-project text-secondary mt-1" aria-hidden="true"></i>
                              <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="small text-secondary"><?php echo htmlspecialchars((string) ($c['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                              </div>
                            </div>
                          </td>
                          <td><span class="badge <?php echo (($c['language'] ?? 'vi') === 'en') ? 'text-bg-info' : 'text-bg-light text-dark'; ?>"><?php echo htmlspecialchars(fmt_lang((string) ($c['language'] ?? 'vi')), ENT_QUOTES, 'UTF-8'); ?></span></td>
                          <td><span class="badge text-bg-secondary"><?php echo (int) ($c['item_count'] ?? 0); ?></span></td>
                          <td class="text-secondary small"><?php echo htmlspecialchars(fmt_dt((string) ($c['updated_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                          <td class="text-end">
                            <div class="btn-group btn-group-sm me-1" role="group" aria-label="Language actions">
                              <button class="btn <?php echo (($c['language'] ?? 'vi') === 'vi') ? 'btn-primary' : 'btn-outline-secondary'; ?>" type="button"
                                      <?php echo (($c['language'] ?? 'vi') === 'vi') ? 'disabled' : ''; ?>
                                      data-clone-language-type="project_category"
                                      data-clone-language-id="<?php echo (int) ($c['id'] ?? 0); ?>"
                                      data-clone-language-target="vi"
                                      data-clone-language-title="<?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">VI</button>
                              <button class="btn <?php echo (($c['language'] ?? 'vi') === 'en') ? 'btn-primary' : 'btn-outline-secondary'; ?>" type="button"
                                      <?php echo (($c['language'] ?? 'vi') === 'en') ? 'disabled' : ''; ?>
                                      data-clone-language-type="project_category"
                                      data-clone-language-id="<?php echo (int) ($c['id'] ?? 0); ?>"
                                      data-clone-language-target="en"
                                      data-clone-language-title="<?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">EN</button>
                            </div>
                            <a class="btn btn-sm btn-primary" href="/admin/content_project_category.php?id=<?php echo (int) ($c['id'] ?? 0); ?>" title="Quản lý">
                              <i class="fa-solid fa-list" aria-hidden="true"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary ms-1" href="/admin/content_project_category_edit.php?id=<?php echo (int) ($c['id'] ?? 0); ?>" title="Sửa">
                              <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger ms-1" type="button"
                                    data-delete-type="project_category"
                                    data-delete-id="<?php echo (int) ($c['id'] ?? 0); ?>"
                                    data-delete-title="<?php echo htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Xoá">
                              <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title fw-semibold" id="addModalLabel">Thêm nội dung</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="list-group">
          <div class="list-group-item d-flex align-items-center justify-content-between gap-3">
            <div>
              <div class="fw-semibold"><i class="fa-solid fa-file-lines me-2" aria-hidden="true"></i>Thêm trang</div>
              <div class="text-secondary small">Tạo trang với tiêu đề, nội dung và SEO</div>
            </div>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-secondary" type="button" data-open-editor="/admin/content_post_edit.php?modal=1&language=vi" data-editor-title="Thêm trang tiếng Việt">VI</button>
              <button class="btn btn-outline-secondary" type="button" data-open-editor="/admin/content_post_edit.php?modal=1&language=en" data-editor-title="Thêm trang tiếng Anh">EN</button>
            </div>
          </div>
          <div class="list-group-item d-flex align-items-center justify-content-between gap-3">
            <div>
              <div class="fw-semibold"><i class="fa-solid fa-folder-plus me-2" aria-hidden="true"></i>Thêm chuyên mục</div>
              <div class="text-secondary small">Nhóm các trang theo chuyên mục</div>
            </div>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-secondary" type="button" data-open-editor="/admin/content_category_edit.php?modal=1&language=vi" data-editor-title="Thêm chuyên mục tiếng Việt">VI</button>
              <button class="btn btn-outline-secondary" type="button" data-open-editor="/admin/content_category_edit.php?modal=1&language=en" data-editor-title="Thêm chuyên mục tiếng Anh">EN</button>
            </div>
          </div>
          <div class="list-group-item d-flex align-items-center justify-content-between gap-3">
            <div>
              <div class="fw-semibold"><i class="fa-solid fa-folder-plus me-2" aria-hidden="true"></i>Thêm chuyên mục sản phẩm</div>
              <div class="text-secondary small">Nhóm các sản phẩm theo chuyên mục</div>
            </div>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-secondary" type="button" data-open-editor="/admin/content_product_category_edit.php?modal=1&language=vi" data-editor-title="Thêm chuyên mục sản phẩm tiếng Việt">VI</button>
              <button class="btn btn-outline-secondary" type="button" data-open-editor="/admin/content_product_category_edit.php?modal=1&language=en" data-editor-title="Thêm chuyên mục sản phẩm tiếng Anh">EN</button>
            </div>
          </div>
          <div class="list-group-item d-flex align-items-center justify-content-between gap-3">
            <div>
              <div class="fw-semibold"><i class="fa-solid fa-folder-plus me-2" aria-hidden="true"></i>Thêm chuyên mục dự án</div>
              <div class="text-secondary small">Nhóm các dự án theo chuyên mục</div>
            </div>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-secondary" type="button" data-open-editor="/admin/content_project_category_edit.php?modal=1&language=vi" data-editor-title="Thêm chuyên mục dự án tiếng Việt">VI</button>
              <button class="btn btn-outline-secondary" type="button" data-open-editor="/admin/content_project_category_edit.php?modal=1&language=en" data-editor-title="Thêm chuyên mục dự án tiếng Anh">EN</button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editorModal" tabindex="-1" aria-labelledby="editorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title fw-semibold" id="editorModalLabel">—</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="editorModalBody"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="frontEditorSeoModal" tabindex="-1" aria-labelledby="frontEditorSeoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="modal-title fw-semibold" id="frontEditorSeoModalLabel">SEO/slug cho front editor</div>
          <div class="small text-secondary" id="frontEditorSeoModalSubLabel">—</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="frontEditorSeoForm">
        <div class="modal-body">
          <input type="hidden" name="page_key" id="frontEditorSeoPageKey">
          <div class="alert alert-light border mb-3 small" id="frontEditorSeoSlugNotice">
            Slug mới sẽ chạy không đuôi `.php`. Khi đã lưu slug riêng, route mặc định cũ của trang sẽ được chuyển hướng về route mới để tối ưu SEO.
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="frontEditorSeoSlug">Slug</label>
              <input type="text" class="form-control mono" id="frontEditorSeoSlug" name="slug" placeholder="vi-du: nha-khoa-rang-su-da-nang">
              <div class="form-text" id="frontEditorSeoSlugHint">Để trống để dùng route mặc định.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="frontEditorSeoCurrentRoute">Route hiện tại</label>
              <input type="text" class="form-control mono" id="frontEditorSeoCurrentRoute" value="" disabled>
            </div>
            <div class="col-12">
              <label class="form-label" for="frontEditorSeoTitle">SEO Title</label>
              <input type="text" class="form-control" id="frontEditorSeoTitle" name="seo_title" maxlength="160">
            </div>
            <div class="col-12">
              <label class="form-label" for="frontEditorSeoDescription">SEO Description</label>
              <textarea class="form-control" id="frontEditorSeoDescription" name="seo_description" rows="4" maxlength="300"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label" for="frontEditorSeoKeywords">SEO Keywords</label>
              <input type="text" class="form-control" id="frontEditorSeoKeywords" name="seo_keywords" maxlength="255" placeholder="tu-khoa-1, tu-khoa-2">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
          <button type="submit" class="btn btn-primary" id="frontEditorSeoSaveBtn">
            <i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Lưu SEO/slug
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    const addModalEl = document.getElementById("addModal");
    const editorModalEl = document.getElementById("editorModal");
    const editorTitleEl = document.getElementById("editorModalLabel");
    const editorBodyEl = document.getElementById("editorModalBody");
    const frontEditorSeoModalEl = document.getElementById("frontEditorSeoModal");
    const frontEditorSeoForm = document.getElementById("frontEditorSeoForm");
    const frontEditorSeoSubLabelEl = document.getElementById("frontEditorSeoModalSubLabel");
    const frontEditorSeoPageKeyEl = document.getElementById("frontEditorSeoPageKey");
    const frontEditorSeoSlugEl = document.getElementById("frontEditorSeoSlug");
    const frontEditorSeoSlugHintEl = document.getElementById("frontEditorSeoSlugHint");
    const frontEditorSeoSlugNoticeEl = document.getElementById("frontEditorSeoSlugNotice");
    const frontEditorSeoCurrentRouteEl = document.getElementById("frontEditorSeoCurrentRoute");
    const frontEditorSeoTitleEl = document.getElementById("frontEditorSeoTitle");
    const frontEditorSeoDescriptionEl = document.getElementById("frontEditorSeoDescription");
    const frontEditorSeoKeywordsEl = document.getElementById("frontEditorSeoKeywords");
    const frontEditorSeoSaveBtnEl = document.getElementById("frontEditorSeoSaveBtn");

    function escapeHtml(s) {
      return String(s ?? "").replace(/[&<>"']/g, (c) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#039;"
      }[c]));
    }

    async function loadEditor(url, title) {
      editorTitleEl.textContent = title || "—";
      editorBodyEl.innerHTML = `<div class="text-secondary">Đang tải...</div>`;

      const res = await fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } });
      const html = await res.text();
      if (!res.ok) {
        editorBodyEl.innerHTML = `<div class="alert alert-danger mb-0">Không tải được form.</div><pre class="mt-3 mb-0 small text-secondary">${escapeHtml(html)}</pre>`;
        return;
      }
      editorBodyEl.innerHTML = html;
      bindEditorForm();
      if (window.adminInitPostForms) {
        window.adminInitPostForms(editorBodyEl);
      }
    }

    function openFrontEditorSeoModal(btn) {
      const pageTitle = btn.getAttribute("data-page-title") || "Front editor";
      const pageKey = btn.getAttribute("data-page-key") || "";
      const defaultRoute = btn.getAttribute("data-default-route") || "/";
      const currentRoute = btn.getAttribute("data-current-route") || defaultRoute;
      const supportsSlug = btn.getAttribute("data-supports-slug") === "1";
      const slug = btn.getAttribute("data-slug") || "";
      const seoTitle = btn.getAttribute("data-seo-title") || "";
      const seoDescription = btn.getAttribute("data-seo-description") || "";
      const seoKeywords = btn.getAttribute("data-seo-keywords") || "";

      frontEditorSeoSubLabelEl.textContent = `${pageTitle} • key: ${pageKey}`;
      frontEditorSeoPageKeyEl.value = pageKey;
      frontEditorSeoCurrentRouteEl.value = currentRoute;
      frontEditorSeoSlugEl.value = slug;
      frontEditorSeoTitleEl.value = seoTitle;
      frontEditorSeoDescriptionEl.value = seoDescription;
      frontEditorSeoKeywordsEl.value = seoKeywords;
      frontEditorSeoSlugEl.disabled = !supportsSlug;
      frontEditorSeoSlugNoticeEl.textContent = supportsSlug
        ? "Slug mới sẽ chạy không đuôi .php. Route mặc định cũ sẽ được chuyển hướng về slug mới sau khi lưu."
        : "Trang này chỉ cập nhật SEO Title, Description và Keywords; route công khai được giữ nguyên.";
      if (supportsSlug) {
        const defaultSlug = defaultRoute.replace(/^\/+/, "");
        frontEditorSeoSlugEl.placeholder = defaultSlug || "vi-du: nha-khoa-rang-su-da-nang";
        frontEditorSeoSlugHintEl.textContent = `Để trống để dùng route mặc định ${defaultRoute}.`;
      } else {
        frontEditorSeoSlugHintEl.textContent = "Trang này chỉ hỗ trợ tối ưu SEO, không đổi slug công khai.";
      }
      bootstrap.Modal.getOrCreateInstance(frontEditorSeoModalEl).show();
    }

    frontEditorSeoForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const payload = {
        page_key: frontEditorSeoPageKeyEl.value,
        slug: frontEditorSeoSlugEl.value,
        seo_title: frontEditorSeoTitleEl.value,
        seo_description: frontEditorSeoDescriptionEl.value,
        seo_keywords: frontEditorSeoKeywordsEl.value
      };
      frontEditorSeoSaveBtnEl.disabled = true;
      frontEditorSeoSaveBtnEl.setAttribute("aria-busy", "true");
      try {
        const res = await fetch("/admin/api/front_editor_page/save.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest"
          },
          body: JSON.stringify(payload)
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
          window.adminToast("danger", (data && data.message) ? data.message : "Không lưu được cấu hình SEO/slug.", { icon: "fa-solid fa-triangle-exclamation" });
          return;
        }
        window.adminToast("success", data.message || "Đã lưu SEO/slug.", { icon: "fa-solid fa-circle-check" });
        bootstrap.Modal.getOrCreateInstance(frontEditorSeoModalEl).hide();
        window.location.reload();
      } finally {
        frontEditorSeoSaveBtnEl.disabled = false;
        frontEditorSeoSaveBtnEl.removeAttribute("aria-busy");
      }
    });

    function bindEditorForm() {
      const form = editorBodyEl.querySelector("form[data-modal-form='1']");
      if (!form) return;

      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const action = form.getAttribute("action") || window.location.pathname;
        form.querySelectorAll("[data-rich-editor]").forEach((wrap) => {
          const textarea = wrap.querySelector("textarea[data-rich-source]");
          const editor = wrap.querySelector("[data-rich-editable]");
          if (textarea && editor) {
            textarea.value = editor.innerHTML;
          }
        });
        if (window.__adminCkEditors) {
          form.querySelectorAll("textarea[data-ckeditor-source]").forEach((ta) => {
            const ed = window.__adminCkEditors.get(ta);
            if (ed) ta.value = ed.getData();
          });
        }
        const fd = new FormData(form);

        const btn = form.querySelector("button[type='submit']");
        if (btn) {
          btn.disabled = true;
          btn.setAttribute("aria-busy", "true");
        }

        try {
          const res = await fetch(action, {
            method: "POST",
            headers: { "X-Requested-With": "XMLHttpRequest" },
            body: fd
          });
          const data = await res.json().catch(() => ({}));

          if (!res.ok || !data.ok) {
            if (data && data.html) {
              editorBodyEl.innerHTML = data.html;
              bindEditorForm();
              return;
            }
            window.adminToast("danger", (data && data.message) ? data.message : "Lưu thất bại.", { icon: "fa-solid fa-triangle-exclamation" });
            return;
          }

          window.adminToast("success", data.message || "Đã lưu.", { icon: "fa-solid fa-circle-check" });
          bootstrap.Modal.getOrCreateInstance(editorModalEl).hide();
          window.location.reload();
        } finally {
          if (btn) {
            btn.disabled = false;
            btn.removeAttribute("aria-busy");
          }
        }
      }, { once: true });
    }

    addModalEl.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-open-editor]");
      if (!btn) return;
      const url = btn.getAttribute("data-open-editor") || "";
      const title = btn.getAttribute("data-editor-title") || "";

      bootstrap.Modal.getOrCreateInstance(addModalEl).hide();
      bootstrap.Modal.getOrCreateInstance(editorModalEl).show();
      await loadEditor(url, title);
    });

    document.addEventListener("click", async (e) => {
      const frontEditorSeoBtn = e.target.closest("button[data-front-editor-seo='1']");
      if (frontEditorSeoBtn) {
        openFrontEditorSeoModal(frontEditorSeoBtn);
        return;
      }

      const cloneBtn = e.target.closest("button[data-clone-language-type][data-clone-language-id][data-clone-language-target]");
      if (cloneBtn) {
        const type = cloneBtn.getAttribute("data-clone-language-type") || "";
        const id = parseInt(cloneBtn.getAttribute("data-clone-language-id") || "0", 10);
        const target = cloneBtn.getAttribute("data-clone-language-target") || "vi";
        const title = cloneBtn.getAttribute("data-clone-language-title") || "";
        if (!type || !id) return;
        cloneBtn.disabled = true;
        try {
          const res = await fetch("/admin/api/content/clone_language.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify({ type, id, target_language: target })
          });
          const data = await res.json().catch(() => ({}));
          if (!res.ok || !data.ok) {
            window.adminToast("danger", (data && data.message) ? data.message : `Không tạo được bản ${target.toUpperCase()} cho "${title}".`, { icon: "fa-solid fa-triangle-exclamation" });
            return;
          }
          window.adminToast("success", data.message || "Đã tạo bản ngôn ngữ.", { icon: "fa-solid fa-circle-check" });
          if (data && data.edit_url) {
            window.location.href = data.edit_url;
            return;
          }
          window.location.reload();
        } finally {
          cloneBtn.disabled = false;
        }
        return;
      }

      const btn = e.target.closest("button[data-delete-type][data-delete-id]");
      if (!btn) return;
      const type = btn.getAttribute("data-delete-type") || "";
      const id = parseInt(btn.getAttribute("data-delete-id") || "0", 10);
      const title = btn.getAttribute("data-delete-title") || "";
      if (!type || !id) return;

      const warning =
        (type === "category") ? "Xoá chuyên mục sẽ đưa các trang trong chuyên mục về không thuộc chuyên mục." :
        (type === "product_category") ? "Xoá chuyên mục sản phẩm sẽ đưa các sản phẩm trong chuyên mục về không thuộc chuyên mục." :
        (type === "project_category") ? "Xoá chuyên mục dự án sẽ đưa các dự án trong chuyên mục về không thuộc chuyên mục." :
        "Hành động này không thể hoàn tác.";

      const ok = window.confirm(`Xoá "${title}"?\n\n${warning}`);
      if (!ok) return;

      btn.disabled = true;
      try {
        const res = await fetch("/admin/api/content/delete.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest"
          },
          body: JSON.stringify({ type, id })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
          window.adminToast("danger", (data && data.message) ? data.message : "Xoá thất bại.", { icon: "fa-solid fa-triangle-exclamation" });
          return;
        }
        window.adminToast("success", data.message || "Đã xoá.", { icon: "fa-solid fa-circle-check" });
        window.location.reload();
      } finally {
        btn.disabled = false;
      }
    });
  })();
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
