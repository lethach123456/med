<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../medical_review_article_admin.php';
admin_require_login();
$pdo = db();
$escape = static fn(mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$pageUrl = admin_url('medical_review_articles.php');
if (!is_string($_SESSION['review_articles_csrf'] ?? null)) $_SESSION['review_articles_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['review_articles_csrf'];
$ready = medical_review_articles_schema_ready($pdo);
$errors = []; $row = null; $input = [];
$id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
if ($ready && $id > 0) {
    $statement = $pdo->prepare('SELECT * FROM medical_review_articles WHERE id=:id LIMIT 1');
    $statement->execute([':id' => $id]); $row = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($row === null) { http_response_code(404); $errors[] = 'Không tìm thấy bài review.'; }
    else $input = $row;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ready) $errors[] = 'Chưa có bảng bài review. Chạy migration trước khi lưu.';
    elseif (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($csrf, $_POST['csrf_token'])) $errors[] = 'Phiên làm việc hết hạn. Tải lại trang và thử lại.';
    elseif ($row === null) $errors[] = 'Không tìm thấy bài review để sửa.';
    else {
        $body = [];
        foreach (array_merge(MEDICAL_REVIEW_ARTICLE_CONTENT_FIELDS, ['status']) as $field) {
            $body[$field] = $_POST[$field] ?? ($row[$field] ?? '');
            if (!is_string($body[$field])) $errors[] = $field . ' phải là chuỗi.';
        }
        $input = array_replace($row, $body);
        if ($errors === []) {
            try {
                medical_review_article_admin_save($pdo, $id, $body, is_string($_POST['version'] ?? null) ? $_POST['version'] : '');
                flash_toast_set('success', $body['status'] === 'published' ? 'Đã lưu và xuất bản bài review.' : 'Đã lưu bài review.', 'fa-solid fa-circle-check');
                header('Location: ' . $pageUrl . '?id=' . $id); exit;
            } catch (InvalidArgumentException | RuntimeException $error) {
                if ($error instanceof PDOException) { error_log('Review article admin save failed.'); $errors[] = 'Chưa lưu được bài. Kiểm tra slug trùng hoặc thử lại sau.'; }
                else $errors[] = $error->getMessage();
            }
        }
    }
}
$query = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 120) : '';
$status = is_string($_GET['status'] ?? null) && in_array($_GET['status'], ['draft','published','archived'], true) ? $_GET['status'] : '';
$page = max(1, min(100000, (int) ($_GET['page'] ?? 1))); $perPage = 20; $total = 0; $rows = [];
if ($ready && $id === 0) {
    $where = '1=1'; $params = [];
    if ($status !== '') { $where .= ' AND a.status=:status'; $params[':status'] = $status; }
    if ($query !== '') { $where .= " AND (a.title LIKE :q1 ESCAPE '!' OR f.name LIKE :q2 ESCAPE '!')"; $params[':q1'] = $params[':q2'] = '%' . strtr($query, ['!'=>'!!','%'=>'!%','_'=>'!_']) . '%'; }
    $from = " FROM medical_review_articles a LEFT JOIN medical_facilities f ON a.entity_type='facility' AND f.id=a.entity_id WHERE " . $where;
    $statement = $pdo->prepare('SELECT COUNT(*)' . $from); $statement->execute($params); $total = (int) $statement->fetchColumn();
    $page = min($page, max(1, (int) ceil($total / $perPage)));
    $statement = $pdo->prepare('SELECT a.id,a.title,a.slug,a.status,a.language_code,a.updated_at,a.source_review_count,f.name AS facility_name' . $from . ' ORDER BY a.updated_at DESC,a.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page-1)*$perPage));
    $statement->execute($params); $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
}
$adminPageTitle = 'Admin • Bài review cơ sở y tế';
$adminHeaderTitle = $id > 0 ? 'Biên tập bài review' : 'Bài review cơ sở y tế';
$adminHeaderSubtitle = 'Bài giới thiệu-review cơ sở theo góc nhìn khách hàng: thế mạnh, phản hồi và lưu ý';
$adminActive = 'medical-review-articles';
require __DIR__ . '/_layout_start.php';
?>
<?php if (!$ready): ?><div class="alert alert-warning" role="status">Chưa có bảng bài review. Chạy <code>php scripts/migrate_review_articles.php --apply</code>. Không tự chạy migration khi mở trang.</div><?php endif; ?>
<?php if ($errors !== []): ?><div class="alert alert-danger" role="alert" tabindex="-1" id="article-errors"><strong>Chưa lưu được bài</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= $escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-outline-secondary" href="<?= $escape($pageUrl) ?>">Danh sách bài review</a>
  <a class="btn btn-outline-primary" href="<?= $escape(admin_url('medical_ai_prompts.php') . '?type=review_article') ?>">Prompt AI bài review</a>
  <a class="btn btn-outline-secondary" href="<?= $escape(admin_url('medical_reviews.php')) ?>">Đánh giá người dùng gốc</a>
</div>
<?php if ($row !== null): ?>
<form method="post" class="card border-0 shadow-soft" action="<?= $escape($pageUrl . '?id=' . $id) ?>">
  <div class="card-body p-3 p-lg-4">
    <input type="hidden" name="csrf_token" value="<?= $escape($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="version" value="<?= $escape(is_string($_POST['version'] ?? null) && $errors !== [] ? $_POST['version'] : medical_review_article_admin_version($row)) ?>">
    <p class="small text-secondary">Thông tin nội bộ: cơ sở #<?= (int) $row['entity_id'] ?> · <?= $escape(strtoupper($row['language_code'])) ?> · <?= (int) $row['source_review_count'] ?> đánh giá theo dữ liệu nguồn lúc viết. Không chép số liệu này vào bài. Bài review không thêm đánh giá, không thay rating hoặc hồ sơ cơ sở.</p>
    <div class="row g-3">
    <?php foreach (['title'=>'Tiêu đề','slug'=>'Slug URL','excerpt'=>'Tóm tắt giới thiệu','verdict'=>'Nhận xét chung dành cho khách hàng','content_html'=>'Nội dung giới thiệu-review · HTML','seo_title'=>'Tiêu đề SEO','seo_description'=>'Mô tả SEO','researched_at'=>'Ngày biên soạn · UTC'] as $field=>$label): ?>
      <div class="<?= in_array($field, ['slug','seo_title','researched_at'], true) ? 'col-12 col-md-6' : 'col-12' ?>">
        <label class="form-label" for="article-<?= $field ?>"><?= $escape($label) ?></label>
        <?php if (in_array($field, ['excerpt','verdict','content_html','seo_description'], true)): ?>
          <textarea class="form-control<?= $field === 'content_html' ? ' mono' : '' ?>" id="article-<?= $field ?>" name="<?= $field ?>" rows="<?= $field === 'content_html' ? 14 : 3 ?>" <?= in_array($field,['excerpt','verdict','content_html'],true) ? 'required' : '' ?>><?= $escape($input[$field] ?? '') ?></textarea>
        <?php else: ?><input class="form-control" id="article-<?= $field ?>" name="<?= $field ?>" value="<?= $escape($input[$field] ?? '') ?>" <?= in_array($field,['title','slug'],true) ? 'required' : '' ?>><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="col-12"><details><summary class="fw-semibold text-primary">Các phần bài viết và thông tin tham khảo · JSON</summary>
      <p class="small text-secondary mt-2">Các mục bài viết có thể là giới thiệu, thế mạnh/dịch vụ, khách hàng nhận xét gì và lưu ý trước khi đến. Nếu có các mục này, trang chi tiết hiển thị chúng thay phần HTML dài để tránh lặp; sửa cả hai phần khi nội dung thay đổi. Không chèn thống kê, ảnh hoặc thông tin nhận dạng bệnh nhân.</p>
      <div class="row g-3"><?php foreach (['strengths_json'=>'Thế mạnh / điểm nổi bật','considerations_json'=>'Lưu ý trước khi đến','suitable_for_json'=>'Phù hợp với nhu cầu nào','analysis_sections_json'=>'Các mục giới thiệu và nhận xét của khách hàng','sources_json'=>'Nguồn đối chiếu','evidence_limitations_json'=>'Lưu ý về thông tin tham khảo'] as $field=>$label): ?>
        <div class="col-12 col-lg-6"><label class="form-label mt-2" for="article-<?= $field ?>"><?= $escape($label) ?></label><textarea class="form-control mono" id="article-<?= $field ?>" name="<?= $field ?>" rows="6" spellcheck="false"><?= $escape($input[$field] ?? '[]') ?></textarea></div>
      <?php endforeach; ?></div>
    </details></div>
    <div class="col-12 col-sm-6"><label class="form-label" for="article-status">Trạng thái</label><select class="form-select" name="status" id="article-status"><?php foreach (['draft'=>'Bản nháp','published'=>'Xuất bản','archived'=>'Ẩn / lưu trữ'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($input['status'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
    <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit">Lưu bài review</button><?php if ($row['status'] === 'published'): ?><a class="btn btn-outline-primary" href="<?= $escape(medical_review_articles_path($row['language_code'], $row['slug'])) ?>" target="_blank" rel="noopener">Xem bài đã xuất bản</a><?php endif; ?></div>
    </div>
  </div>
</form>
<?php elseif ($id === 0): ?>
<div class="card border-0 shadow-soft"><div class="card-body p-3 p-lg-4">
  <h2 class="h5">Bài review cơ sở y tế</h2><p class="text-secondary">AI viết lại hồ sơ cơ sở thành bài dễ đọc, kết hợp thế mạnh, phản hồi khách hàng và lưu ý từ dữ liệu đã có; không đưa thống kê vào bài. Bài được gửi về dưới dạng nháp để bạn xem và xuất bản. Các đánh giá gốc và hồ sơ cơ sở được giữ nguyên.</p>
  <details class="mb-3"><summary class="small text-primary">Cổng dành cho AI / tiện ích</summary><code class="d-block mt-2 text-break">GET /api/medical/review-articles-needing-content.php</code><code class="d-block text-break">POST /api/medical/review-article-content-update.php</code><p class="small text-secondary mt-2 mb-0">Dùng API key hiện có. Nhận quyền viết bài trước khi gửi kết quả; xem hướng dẫn trong docs/review-articles-api.md.</p></details>
  <form method="get" class="row g-2 align-items-end mb-3"><div class="col-12 col-md-6"><label class="form-label small" for="article-search">Tiêu đề hoặc cơ sở</label><input class="form-control" id="article-search" name="q" value="<?= $escape($query) ?>"></div><div class="col-8 col-md-3"><label class="form-label small" for="article-status-filter">Trạng thái</label><select class="form-select" id="article-status-filter" name="status"><option value="">Tất cả</option><?php foreach (['draft'=>'Bản nháp','published'=>'Xuất bản','archived'=>'Ẩn'] as $value=>$label): ?><option value="<?= $value ?>" <?= $status===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div><div class="col-4 col-md-3"><button class="btn btn-primary w-100" type="submit">Lọc bài</button></div></form>
  <div class="table-responsive"><table class="table align-middle"><caption class="visually-hidden">Danh sách bài review phân tích</caption><thead><tr><th>Bài viết</th><th>Cơ sở</th><th>Ngôn ngữ</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead><tbody>
  <?php foreach ($rows as $item): ?><tr><td class="fw-semibold"><?= $escape($item['title']) ?></td><td><?= $escape($item['facility_name'] ?? '') ?></td><td><?= $escape(strtoupper($item['language_code'])) ?></td><td><span class="badge <?= $item['status']==='published'?'text-bg-success':'text-bg-secondary' ?>"><?= $escape($item['status']) ?></span></td><td class="small text-secondary"><?= $escape($item['updated_at']) ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= $escape($pageUrl . '?id=' . $item['id']) ?>">Biên tập</a></td></tr><?php endforeach; ?>
  <?php if ($rows === []): ?><tr><td colspan="6" class="text-center text-secondary py-4">Chưa có bài review theo bộ lọc này. API sẽ tạo bản nháp khi AI nhận cơ sở đủ điều kiện.</td></tr><?php endif; ?>
  </tbody></table></div>
  <?php $pages=max(1,(int)ceil($total/$perPage)); if ($pages>1): ?><nav class="d-flex flex-wrap gap-2 mt-3" aria-label="Phân trang bài review"><?php foreach (array_unique([1,max(1,$page-1),$page,min($pages,$page+1),$pages]) as $number): ?><a class="btn btn-sm <?= $number===$page?'btn-primary':'btn-outline-secondary' ?>" <?= $number===$page?'aria-current="page"':'' ?> href="<?= $escape($pageUrl.'?'.http_build_query(['q'=>$query,'status'=>$status,'page'=>$number])) ?>"><?= $number ?></a><?php endforeach; ?></nav><?php endif; ?>
</div></div>
<?php endif; ?>
<?php if ($errors !== []): ?><script>document.getElementById('article-errors')?.focus();</script><?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>
