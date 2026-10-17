<?php
declare(strict_types=1);
require_once __DIR__ . '/../medical_handbook_translations.php';

/** Pure request normalization, shared by the list and its database-free tests. */
function admin_handbook_filters(array $query): array
{
    $scalar = static fn (string $key): string => isset($query[$key]) && is_scalar($query[$key]) ? trim((string) $query[$key]) : '';
    $status = $scalar('status');
    $language = $scalar('language');
    $page = $scalar('page');
    return [
        'q' => mb_substr($scalar('q'), 0, 160),
        'status' => in_array($status, ['draft', 'published'], true) ? $status : '',
        'language' => in_array($language, ['vi', 'en'], true) ? $language : '',
        'page' => preg_match('/^[1-9][0-9]{0,8}$/D', $page) === 1 ? (int) $page : 1,
    ];
}

function admin_handbook_categories(PDO $pdo): array
{
    $rows = $pdo->query("SELECT id, name, slug FROM categories WHERE slug IN ('blog', 'blog-en') ORDER BY id ASC")->fetchAll();
    $categories = [];
    foreach ($rows as $row) {
        $slug = (string) ($row['slug'] ?? '');
        if (!isset($categories[$slug])) $categories[$slug] = $row;
    }
    return $categories;
}

function admin_handbook_category_id(array $categories, string $language): int
{
    return (int) ($categories[$language === 'en' ? 'blog-en' : 'blog']['id'] ?? 0);
}

function admin_handbook_contains_post(array $categories, array $post): bool
{
    $language = strtolower(trim((string) ($post['language'] ?? '')));
    if ($language !== '' && !in_array($language, ['vi', 'en'], true)) return false;
    $categoryId = (int) ($post['category_id'] ?? 0);
    foreach ($categories as $category) {
        if ($categoryId > 0 && $categoryId === (int) ($category['id'] ?? 0)) return true;
    }
    return false;
}

function admin_handbook_page(PDO $pdo, array $filters, int $perPage = 20): array
{
    // Match the public handbook: category-bound articles in either supported language.
    $languageSql = "COALESCE(NULLIF(LOWER(TRIM(p.language)), ''), 'vi')";
    $where = ["c.slug IN ('blog', 'blog-en')", $languageSql . " IN ('vi', 'en')"];
    $params = [];
    if ($filters['q'] !== '') {
        $where[] = "(p.title LIKE :search_title ESCAPE '!' OR p.slug LIKE :search_slug ESCAPE '!' OR p.excerpt LIKE :search_excerpt ESCAPE '!')";
        $pattern = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $params[':search_title'] = $pattern;
        $params[':search_slug'] = $pattern;
        $params[':search_excerpt'] = $pattern;
    }
    foreach (['status', 'language'] as $key) {
        if ($filters[$key] !== '') {
            $where[] = ($key === 'language' ? $languageSql : 'p.status') . ' = :' . $key;
            $params[':' . $key] = $filters[$key];
        }
    }
    $from = ' FROM posts p INNER JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*)' . $from);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $perPage = max(1, min(100, $perPage));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($pages, (int) $filters['page']));
    $offset = ($page - 1) * $perPage;
    $statement = $pdo->prepare('SELECT p.id, p.title, p.slug, ' . $languageSql . ' AS language, p.status, p.updated_at, c.name AS category_name,
        CASE WHEN LENGTH(TRIM(COALESCE(p.content, \'\'))) > 0 THEN 1 ELSE 0 END AS has_public_content'
        . $from . ' ORDER BY p.updated_at DESC, p.id DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) $statement->bindValue($key, $value, PDO::PARAM_STR);
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    return [
        'items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages,
        'start' => $total > 0 ? $offset + 1 : 0, 'end' => min($total, $offset + $perPage),
    ];
}

function admin_handbook_page_url(array $filters, int $page): string
{
    $query = [];
    foreach (['q', 'status', 'language'] as $key) {
        if (($filters[$key] ?? '') !== '') $query[$key] = $filters[$key];
    }
    if ($page > 1) $query['page'] = $page;
    return '/admin/cam_nang.php' . ($query ? '?' . http_build_query($query) : '');
}

function admin_handbook_public_url(array $post): string
{
    return ((string) ($post['language'] ?? 'vi') === 'en' ? '/en' : '') . '/cam-nang/' . rawurlencode((string) ($post['slug'] ?? ''));
}

/** Only invoked after an authenticated, CSRF-valid POST, inside the save transaction. */
function admin_handbook_ensure_category(PDO $pdo, string $language, bool $lock = false): int
{
    if (!in_array($language,['vi','en'],true)) throw new InvalidArgumentException('Ngôn ngữ Cẩm nang không hợp lệ.');
    $slug=$language==='en'?'blog-en':'blog';
    $find=$pdo->prepare('SELECT id FROM categories WHERE slug=:slug LIMIT 1'.($lock&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite'?' FOR UPDATE':''));$find->execute([':slug'=>$slug]);
    $id=(int)$find->fetchColumn(); if($id>0)return$id;
    try {
        $pdo->prepare('INSERT INTO categories(name,slug,language) VALUES(:name,:slug,:language)')->execute([
            ':name'=>$language==='en'?'Health handbook':'Cẩm nang sức khỏe',':slug'=>$slug,':language'=>$language]);
    } catch (PDOException $error) {
        // A second authenticated save may have created the same unique slug.
        if (!in_array((string)$error->getCode(),['23000','23505'],true)) throw$error;
        $find->execute([':slug'=>$slug]);$id=(int)$find->fetchColumn();if($id<1)throw$error;return$id;
    }
    return(int)$pdo->lastInsertId();
}

/** Save the article and its explicit language pairing atomically; never called by GET. */
function admin_handbook_save(PDO $pdo, array $values, int $postId, array $options=[]): int
{
    if ($pdo->inTransaction()) throw new LogicException('Article save requires its own transaction.');
    $language=(string)($values['language']??'');
    if(!in_array($language,['vi','en'],true))throw new InvalidArgumentException('Ngôn ngữ không hợp lệ.');
    $pdo->beginTransaction();
    try {
        if($postId>0){
            $lock=$pdo->prepare('SELECT p.*,c.slug AS category_slug FROM posts p INNER JOIN categories c ON c.id=p.category_id WHERE p.id=:id'
                .($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE'));
            $lock->execute([':id'=>$postId]);$current=$lock->fetch(PDO::FETCH_ASSOC);
            if(!$current||!handbook_translation_scoped($current))throw new InvalidArgumentException('Bài không thuộc Cẩm nang.');
            if(handbook_content_locale($current['language']??null)!==$language)throw new InvalidArgumentException('Không được đổi ngôn ngữ bài hiện có.');
            $categoryId=(int)$current['category_id'];
        }else{$categoryId=admin_handbook_ensure_category($pdo,$language);}
        $fields=['category_id'=>$categoryId,'title'=>$values['title'],'slug'=>$values['slug'],'language'=>$language,
            'excerpt'=>$values['excerpt']!==''?$values['excerpt']:null,'content'=>$values['content']!==''?$values['content']:null,
            'seo_title'=>$values['seo_title']!==''?$values['seo_title']:null,'seo_description'=>$values['seo_description']!==''?$values['seo_description']:null,
            'seo_keywords'=>$values['seo_keywords']!==''?$values['seo_keywords']:null,'status'=>$values['status']];
        if($options['has_featured_image']??true)$fields['featured_image_url']=$values['featured_image_url']!==''?$values['featured_image_url']:null;
        if($options['has_template']??true)$fields['template']=0;
        $params=[];foreach($fields as$key=>$value)$params[':'.$key]=$value;
        if($postId>0){
            $sets=[];foreach(array_keys($fields)as$key)$sets[]=$key.'=:'.$key;
            $params[':id']=$postId;$sql='UPDATE posts SET '.implode(',',$sets).' WHERE id=:id';
        }else{$sql='INSERT INTO posts('.implode(',',array_keys($fields)).') VALUES('.implode(',',array_keys($params)).')';}
        $pdo->prepare($sql)->execute($params);if($postId<1)$postId=(int)$pdo->lastInsertId();
        if($options['update_link']??false)handbook_translation_set($pdo,$postId,($options['linked_post_id']??0)>0?(int)$options['linked_post_id']:null);
        $pdo->commit();return$postId;
    }catch(Throwable$error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}
}

function admin_handbook_translation_field(PDO $pdo, array $values, int $id): string
{
    ob_start();$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $target=($values['language']??'vi')==='en'?'vi':'en';$label=$target==='en'?'tiếng Anh':'tiếng Việt';
    ?>
    <section class="col-12<?php echo $id>0?'':' col-md-8'; ?>" aria-labelledby="handbook-translation-heading">
      <div>
        <h2 id="handbook-translation-heading" class="visually-hidden">Liên kết bản dịch</h2>
        <?php if(!handbook_translations_ready($pdo)): ?>
          <p class="small text-secondary mb-0">Chưa bật liên kết Việt–Anh. Chạy <code>php scripts/migrate_handbook_translations.php</code>. Bạn vẫn thêm/lưu bài bình thường.</p>
        <?php else:$selected=(int)($values['linked_post_id']??0); ?>
          <label class="form-label" for="handbook-linked-post" data-handbook-link-label>Bài <?php echo$label;?> liên kết</label>
          <select id="handbook-linked-post" name="linked_post_id" class="form-select" aria-describedby="handbook-translation-help">
            <option value="0">Không liên kết</option>
            <?php foreach($id>0?[$target]:['en','vi']as$choiceLanguage):?>
              <?php foreach(handbook_translation_candidates($pdo,$choiceLanguage,$id)as$candidate):$candidateId=(int)$candidate['id'];$available=(int)$candidate['is_available']===1;$visible=$choiceLanguage===$target;?>
                <option value="<?php echo$candidateId;?>" data-handbook-target-language="<?php echo$choiceLanguage;?>" data-handbook-link-available="<?php echo$available?'1':'0';?>" <?php echo$candidateId===$selected&&$visible?'selected':'';?> <?php echo!$visible?'hidden':'';?> <?php echo!$available||!$visible?'disabled':'';?>>#<?php echo$candidateId;?> — <?php echo$escape($candidate['title']);?><?php echo!$available?' · Đã liên kết':'';?></option>
              <?php endforeach;?>
            <?php endforeach;?>
          </select>
          <p id="handbook-translation-help" class="small text-secondary mt-1 mb-0">Không bắt buộc. Chọn bản dịch tương ứng để nối hai bài Việt–Anh.</p>
          <?php if($selected>0):?><a class="btn btn-sm btn-outline-primary mt-2" href="/admin/content_post_edit.php?handbook=1&amp;id=<?php echo$selected;?>"><i class="fa-solid fa-arrow-up-right-from-square me-1" aria-hidden="true"></i>Mở bản <?php echo$label;?></a><?php endif;?>
        <?php endif;?>
      </div>
    </section>
    <?php return(string)ob_get_clean();
}

function admin_handbook_language_field(array $values, int $id): string
{
    $language=($values['language']??'vi')==='en'?'en':'vi';
    if($id>0)return '<input type="hidden" name="language" value="'.$language.'"><div class="small text-secondary">Ngôn ngữ: <strong>'.($language==='en'?'English':'Tiếng Việt').'</strong></div>';
    return '<label class="form-label" for="handbook-article-language">Ngôn ngữ</label><select id="handbook-article-language" name="language" class="form-select" data-handbook-language="1"><option value="vi"'.($language==='vi'?' selected':'').'>Tiếng Việt</option><option value="en"'.($language==='en'?' selected':'').'>English</option></select>';
}

/**
 * Render an inspection card in Admin for posts.ai_content_json
 * (suggested_facilities_json, suggested_doctors_json, sources_json).
 */
function admin_handbook_ai_metadata_panel(?array $row): string
{
    if (!is_array($row)) return '';
    $raw = trim((string) ($row['ai_content_json'] ?? ''));
    $decoded = $raw !== '' ? json_decode($raw, true) : null;
    $facilities = is_array($decoded['suggested_facilities_json'] ?? null) ? $decoded['suggested_facilities_json'] : [];
    $doctors = is_array($decoded['suggested_doctors_json'] ?? null) ? $decoded['suggested_doctors_json'] : [];
    $sources = is_array($decoded['sources_json'] ?? null) ? $decoded['sources_json'] : [];
    $escape = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $locale = (($row['language'] ?? 'vi') === 'en') ? 'en' : 'vi';

    ob_start();
    ?>
    <div class="card border rounded-4 bg-white mt-3" id="handbook-ai-metadata-panel">
      <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
          <div class="fw-semibold">
            <i class="fa-solid fa-hospital-user text-primary me-2" aria-hidden="true"></i>JSON Cơ sở y tế &amp; Bác sĩ đi kèm bài Cẩm nang
          </div>
          <div class="d-flex flex-wrap gap-1">
            <span class="badge <?php echo count($facilities) > 0 ? 'text-bg-primary' : 'text-bg-secondary'; ?>"><?php echo count($facilities); ?> cơ sở y tế</span>
            <span class="badge <?php echo count($doctors) > 0 ? 'text-bg-info' : 'text-bg-secondary'; ?>"><?php echo count($doctors); ?> bác sĩ</span>
            <span class="badge text-bg-light text-dark border"><?php echo count($sources); ?> nguồn y khoa</span>
          </div>
        </div>

        <?php if (!is_array($decoded)): ?>
          <div class="small text-secondary">Bài viết này chưa có dữ liệu <code>ai_content_json</code> (hoặc được tạo thủ công, chưa qua API AI Cẩm nang).</div>
        <?php else: ?>
          <div class="row g-3 mt-1">
            <div class="col-12 col-lg-6">
              <div class="small fw-bold text-secondary mb-2">CƠ SỞ Y TẾ GỢI Ý (<code>suggested_facilities_json</code>: <?php echo count($facilities); ?>)</div>
              <?php if ($facilities === []): ?>
                <div class="small text-muted fst-italic border rounded-3 p-2 bg-light">Mảng <code>suggested_facilities_json</code> đang trống (<code>[]</code>).</div>
              <?php else: ?>
                <div class="d-grid gap-2">
                  <?php foreach ($facilities as $fac): if (!is_array($fac)) continue;
                    $fid = (int) ($fac['facility_id'] ?? 0);
                    $fSlug = trim((string) ($fac['slug'] ?? ''));
                    $res = (string) ($fac['resolution'] ?? '');
                    $badgeClass = $res === 'matched' ? 'text-bg-success' : ($res === 'created' ? 'text-bg-warning' : 'text-bg-secondary');
                    $badgeText = $res === 'matched' ? 'Đã khớp #' . $fid : ($res === 'created' ? 'Tạo mới #' . $fid : 'Chưa khớp (' . ($fac['resolution_reason'] ?? 'unresolved') . ')');
                  ?>
                    <div class="border rounded-3 p-2 bg-light">
                      <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="fw-semibold small"><?php echo $escape($fac['name'] ?? '—'); ?></div>
                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $escape($badgeText); ?></span>
                      </div>
                      <div class="small text-secondary mt-1">
                        <?php echo $escape(implode(' · ', array_filter([(string) ($fac['category'] ?? ''), (string) ($fac['city'] ?? ''), (string) ($fac['phone'] ?? '')]))); ?>
                      </div>
                      <?php if (!empty($fac['address'])): ?>
                        <div class="small text-muted"><?php echo $escape($fac['address']); ?></div>
                      <?php endif; ?>
                      <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php if ($fid > 0): ?>
                          <a class="btn btn-xs btn-outline-primary py-0 px-2 small" href="/admin/medical_facility_edit.php?id=<?php echo $fid; ?>" target="_blank" rel="noopener">Mở Admin #<?php echo $fid; ?></a>
                        <?php endif; ?>
                        <?php if ($fSlug !== ''): ?>
                          <a class="btn btn-xs btn-outline-secondary py-0 px-2 small" href="<?php echo $escape(($locale === 'en' ? '/en/co-so-y-te/' : '/co-so-y-te/') . rawurlencode($fSlug)); ?>" target="_blank" rel="noopener">Xem trang</a>
                        <?php endif; ?>
                        <?php if (!empty($fac['evidence_url'])): ?>
                          <a class="btn btn-xs btn-outline-dark py-0 px-2 small" href="<?php echo $escape($fac['evidence_url']); ?>" target="_blank" rel="noopener noreferrer">Nguồn</a>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="col-12 col-lg-6">
              <div class="small fw-bold text-secondary mb-2">BÁC SĨ GỢI Ý (<code>suggested_doctors_json</code>: <?php echo count($doctors); ?>)</div>
              <?php if ($doctors === []): ?>
                <div class="small text-muted fst-italic border rounded-3 p-2 bg-light">Mảng <code>suggested_doctors_json</code> đang trống (<code>[]</code>).</div>
              <?php else: ?>
                <div class="d-grid gap-2">
                  <?php foreach ($doctors as $doc): if (!is_array($doc)) continue;
                    $did = (int) ($doc['doctor_id'] ?? 0);
                    $dSlug = trim((string) ($doc['slug'] ?? ''));
                    $res = (string) ($doc['resolution'] ?? '');
                    $badgeClass = $res === 'matched' ? 'text-bg-success' : ($res === 'created' ? 'text-bg-warning' : 'text-bg-secondary');
                    $badgeText = $res === 'matched' ? 'Đã khớp #' . $did : ($res === 'created' ? 'Tạo mới #' . $did : 'Chưa khớp (' . ($doc['resolution_reason'] ?? 'unresolved') . ')');
                  ?>
                    <div class="border rounded-3 p-2 bg-light">
                      <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="fw-semibold small"><?php echo $escape($doc['name'] ?? '—'); ?></div>
                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $escape($badgeText); ?></span>
                      </div>
                      <div class="small text-secondary mt-1">
                        <?php echo $escape(implode(' · ', array_filter([(string) ($doc['specialty'] ?? ''), (string) ($doc['facility_name'] ?? ''), (string) ($doc['city'] ?? '')]))); ?>
                      </div>
                      <?php if (!empty($doc['address'])): ?>
                        <div class="small text-muted"><?php echo $escape($doc['address']); ?></div>
                      <?php endif; ?>
                      <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php if ($did > 0): ?>
                          <a class="btn btn-xs btn-outline-primary py-0 px-2 small" href="/admin/medical_doctor_edit.php?id=<?php echo $did; ?>" target="_blank" rel="noopener">Mở Admin #<?php echo $did; ?></a>
                        <?php endif; ?>
                        <?php if ($dSlug !== ''): ?>
                          <a class="btn btn-xs btn-outline-secondary py-0 px-2 small" href="<?php echo $escape(($locale === 'en' ? '/en/bac-si/' : '/bac-si/') . rawurlencode($dSlug)); ?>" target="_blank" rel="noopener">Xem trang</a>
                        <?php endif; ?>
                        <?php if (!empty($doc['evidence_url'])): ?>
                          <a class="btn btn-xs btn-outline-dark py-0 px-2 small" href="<?php echo $escape($doc['evidence_url']); ?>" target="_blank" rel="noopener noreferrer">Nguồn</a>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <details class="mt-3">
            <summary class="small text-primary fw-semibold" style="cursor:pointer;">Xem toàn bộ JSON gốc (<code>posts.ai_content_json</code>)</summary>
            <pre class="small bg-light border rounded-3 p-2 mt-2 mb-0" style="max-height:320px;overflow:auto;white-space:pre-wrap;"><?php echo $escape(json_encode([
                'suggested_facilities_json' => $facilities,
                'suggested_doctors_json' => $doctors,
                'sources_json' => $sources,
                'images' => $decoded['images'] ?? [],
                'processed_at' => $decoded['processed_at'] ?? null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
          </details>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
