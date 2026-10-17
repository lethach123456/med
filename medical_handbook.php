<?php
declare(strict_types=1);

/** Read-only handbook data and presentation helpers; safe to load without a database. */
function handbook_view_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function handbook_view_locale(string $locale): string
{
    return $locale === 'en' ? 'en' : 'vi';
}

/** Legacy blanks mean Vietnamese; explicit language values remain authoritative. */
function handbook_content_locale(?string $value): string
{
    $value = strtolower(trim((string) $value));
    return $value === '' ? 'vi' : $value;
}

function handbook_view_path(string $locale = 'vi', string $slug = ''): string
{
    $path = ($locale === 'en' ? '/en' : '') . '/cam-nang';
    return $slug === '' ? $path : $path . '/' . rawurlencode(trim($slug, "/ \t\n\r\0\x0B"));
}

function handbook_view_filters(array $query): array
{
    $value = $query['q'] ?? '';
    $value = is_string($value) ? trim($value) : '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return ['q' => mb_substr($value, 0, 120, 'UTF-8')];
}

function handbook_view_requested_page(array $query): int
{
    $value = $query['page'] ?? 1;
    if (!is_int($value) && !is_string($value)) return 1;
    $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $page === false ? 1 : $page;
}

function handbook_view_page_url(string $locale, array $filters = [], int $page = 1): string
{
    $query = [];
    $filters = handbook_view_filters($filters);
    if ($filters['q'] !== '') $query['q'] = $filters['q'];
    if ($page > 1) $query['page'] = $page;
    return handbook_view_path($locale) . ($query !== [] ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}

function handbook_view_paging(int $total, int $requestedPage = 1, int $perPage = 9): array
{
    $total = max(0, $total);
    $perPage = min(36, max(1, $perPage));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $requestedPage), $pages);
    $offset = ($page - 1) * $perPage;
    return ['total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage,
        'offset' => $offset, 'start' => $total > 0 ? $offset + 1 : 0, 'end' => min($offset + $perPage, $total)];
}

/** Shared scope deliberately excludes standalone pages, services, drafts and the other locale. */
function handbook_query_scope(string $locale, array $filters = []): array
{
    $locale = handbook_view_locale($locale);
    $filters = handbook_view_filters($filters);
    $sql = "p.status='published' AND c.slug IN ('blog','blog-en')
        AND COALESCE(NULLIF(LOWER(TRIM(p.language)),''),'vi')=:locale
        AND CHAR_LENGTH(TRIM(COALESCE(p.content,'')))>0 AND CHAR_LENGTH(TRIM(p.slug))>0";
    $parameters = [':locale' => $locale];
    if ($filters['q'] !== '') {
        // A literal keyword: visitors cannot turn % or _ into unbounded wildcard searches.
        $keyword = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
        $sql .= " AND (p.title LIKE :title_query ESCAPE '!' OR p.excerpt LIKE :excerpt_query ESCAPE '!' OR p.content LIKE :content_query ESCAPE '!')";
        $parameters += [':title_query' => $keyword, ':excerpt_query' => $keyword, ':content_query' => $keyword];
    }
    return ['sql' => $sql, 'parameters' => $parameters];
}

function handbook_row_is_article(array $row, string $locale): bool
{
    $locale = handbook_view_locale($locale);
    return ($row['status'] ?? '') === 'published'
        && in_array($row['category_slug'] ?? '', ['blog', 'blog-en'], true)
        && handbook_content_locale(isset($row['language']) ? (string) $row['language'] : null) === $locale
        && trim((string) ($row['content'] ?? '')) !== ''
        && trim((string) ($row['slug'] ?? '')) !== '';
}

/** SELECT only: no migration, seeding, cache write, transaction or remote request. */
function handbook_directory_page(PDO $pdo, string $locale, array $filters = [], int $requestedPage = 1): array
{
    $scope = handbook_query_scope($locale);
    $from = ' FROM posts p INNER JOIN categories c ON c.id=p.category_id WHERE ';
    $count = $pdo->prepare('SELECT COUNT(*)' . $from . $scope['sql']);
    $count->execute($scope['parameters']);
    $publishedTotal = (int) $count->fetchColumn();
    $filters = handbook_view_filters($filters);
    $total = $publishedTotal;
    if ($filters['q'] !== '') {
        $scope = handbook_query_scope($locale, $filters);
        $count = $pdo->prepare('SELECT COUNT(*)' . $from . $scope['sql']);
        $count->execute($scope['parameters']);
        $total = (int) $count->fetchColumn();
    }
    $paging = handbook_view_paging($total, $requestedPage);
    $statement = $pdo->prepare('SELECT p.id,p.title,p.slug,p.language,p.status,p.featured_image_url,p.excerpt,p.content,p.updated_at,c.slug AS category_slug'
        . $from . $scope['sql'] . ' ORDER BY p.updated_at DESC,p.id DESC LIMIT ' . $paging['per_page'] . ' OFFSET ' . $paging['offset']);
    $statement->execute($scope['parameters']);
    return $paging + ['published_total' => $publishedTotal, 'items' => $statement->fetchAll(PDO::FETCH_ASSOC)];
}

function handbook_view_plain_text(string $value): string
{
    $value = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', $value) ?? '';
    $value = preg_replace('~</(?:p|div|li|h[1-6])\s*>|<br\s*/?>~i', ' ', $value) ?? $value;
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $value) ?? '');
}

function handbook_view_excerpt(array $row, int $length = 160): string
{
    $text = handbook_view_plain_text((string) ($row['excerpt'] ?? ''));
    if ($text === '') $text = handbook_view_plain_text((string) ($row['content'] ?? ''));
    if (mb_strlen($text, 'UTF-8') <= $length) return $text;
    $short = mb_substr($text, 0, max(1, $length), 'UTF-8');
    $lastSpace = mb_strrpos($short, ' ', 0, 'UTF-8');
    if ($lastSpace !== false && $lastSpace > $length / 2) $short = mb_substr($short, 0, $lastSpace, 'UTF-8');
    return rtrim($short, " ,.;:!?") . '…';
}

/** Only display existing editorial URLs. This function never retrieves media. */
function handbook_view_image_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > 2048 || preg_match('/[\x00-\x20\\\\]/', $value)) return '';
    if (str_starts_with($value, '/') && !str_starts_with($value, '//')) return $value;
    $parts = parse_url($value);
    if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])
        || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        || !filter_var($value, FILTER_VALIDATE_URL)) return '';
    return $value;
}

function handbook_view_date(string $value, string $locale): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $match)) return [];
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $match[0]);
    if (!$date || $date->format('Y-m-d') !== $match[0]) return [];
    return ['datetime' => $date->format('Y-m-d'), 'label' => $date->format($locale === 'en' ? 'M j, Y' : 'd/m/Y')];
}

function handbook_view_copy(string $locale): array
{
    return $locale === 'en' ? [
        'home' => 'Home', 'name' => 'Health handbook', 'kicker' => 'READ. UNDERSTAND. TAKE CARE.',
        'title' => 'A little more understanding.', 'accent' => 'A clearer path to better care.',
        'intro' => 'Explore health articles and practical information to help you prepare for conversations about your care.',
        'published' => 'published articles', 'search' => 'Search the handbook', 'placeholder' => 'Search article titles or content…',
        'searchButton' => 'Search', 'results' => 'Articles to explore', 'searchResults' => 'Search results', 'matches' => 'articles found',
        'order' => 'Most recently updated', 'updated' => 'Updated', 'read' => 'Read article', 'article' => 'Health handbook',
        'clear' => 'View all articles', 'query' => 'Search keyword:',
        'emptyTitle' => 'No articles published yet', 'emptyCopy' => 'Published articles will appear here when they are available.',
        'noMatchTitle' => 'No matching articles', 'noMatchCopy' => 'Try a shorter keyword or browse all published articles.',
        'errorTitle' => 'Articles are temporarily unavailable', 'errorCopy' => 'Please try again in a moment.', 'retry' => 'Try again',
        'noteTitle' => 'Information to help you prepare', 'noteCopy' => 'Articles are for general reference and do not replace a diagnosis or advice from a qualified healthcare professional.',
        'pagination' => 'Handbook pagination', 'previous' => 'Previous page', 'next' => 'Next page', 'page' => 'Page ',
        'range' => 'Showing %d–%d of %d articles',
        'seoTitle' => 'Health handbook | MedReview', 'seoDescription' => 'Explore published health articles on MedReview. Find information to help you understand your options and prepare for conversations about your care.',
    ] : [
        'home' => 'Trang chủ', 'name' => 'Cẩm nang', 'kicker' => 'THÊM HIỂU BIẾT. THÊM AN TÂM.',
        'title' => 'Hiểu thêm về sức khỏe.', 'accent' => 'Chủ động hơn khi chăm sóc.',
        'intro' => 'Khám phá các bài viết và thông tin chăm sóc sức khỏe. Thêm kiến thức tham khảo trước mỗi cuộc trao đổi với bác sĩ.',
        'published' => 'bài viết đã xuất bản', 'search' => 'Tìm trong cẩm nang', 'placeholder' => 'Tìm theo tiêu đề hoặc nội dung bài viết…',
        'searchButton' => 'Tìm kiếm', 'results' => 'Bài viết để bạn khám phá', 'searchResults' => 'Kết quả tìm kiếm', 'matches' => 'bài viết phù hợp',
        'order' => 'Mới cập nhật trước', 'updated' => 'Cập nhật', 'read' => 'Đọc bài viết', 'article' => 'Cẩm nang sức khỏe',
        'clear' => 'Xem tất cả bài viết', 'query' => 'Từ khóa:',
        'emptyTitle' => 'Chưa có bài viết được xuất bản', 'emptyCopy' => 'Các bài viết sẽ xuất hiện tại đây khi có nội dung được xuất bản.',
        'noMatchTitle' => 'Chưa tìm thấy bài viết phù hợp', 'noMatchCopy' => 'Thử từ khóa ngắn hơn hoặc khám phá tất cả bài viết đã xuất bản.',
        'errorTitle' => 'Tạm thời chưa thể tải bài viết', 'errorCopy' => 'Bạn vui lòng thử lại sau ít phút.', 'retry' => 'Thử lại',
        'noteTitle' => 'Thông tin để bạn tham khảo', 'noteCopy' => 'Bài viết cung cấp thông tin tham khảo, không thay thế chẩn đoán hoặc tư vấn từ người có chuyên môn y tế.',
        'pagination' => 'Phân trang cẩm nang', 'previous' => 'Trang trước', 'next' => 'Trang sau', 'page' => 'Trang ',
        'range' => 'Hiển thị %d–%d / %d bài viết',
        'seoTitle' => 'Cẩm nang sức khỏe | MedReview', 'seoDescription' => 'Khám phá các bài viết sức khỏe đã xuất bản trên MedReview. Tìm thông tin tham khảo để hiểu thêm và chủ động trao đổi về việc chăm sóc sức khỏe.',
    ];
}

function handbook_view_card(array $row, string $locale): string
{
    $e = 'handbook_view_escape';
    $copy = handbook_view_copy($locale);
    $url = handbook_view_path($locale, (string) ($row['slug'] ?? ''));
    $image = handbook_view_image_url((string) ($row['featured_image_url'] ?? ''));
    $date = handbook_view_date((string) ($row['updated_at'] ?? ''), $locale);
    $html = '<article class="hb-card' . ($image === '' ? ' hb-card--text' : '') . '">';
    if ($image !== '') {
        $attributes = function_exists('medical_media_image_attributes')
            ? medical_media_image_attributes($image, '(max-width: 620px) calc(100vw - 32px), (max-width: 960px) calc((100vw - 64px)/2), 400px', [320, 480, 640, 960], 640)
            : 'src="' . $e($image) . '"';
        $html .= '<a class="hb-card-media" href="' . $e($url) . '" aria-hidden="true" tabindex="-1"><img ' . $attributes . ' alt="" width="640" height="360" loading="lazy" decoding="async"></a>';
    }
    $html .= '<div class="hb-card-body"><span class="hb-card-category">' . $e($copy['article']) . '</span><h3><a href="' . $e($url) . '">' . $e($row['title'] ?? '') . '</a></h3>';
    $excerpt = handbook_view_excerpt($row);
    if ($excerpt !== '') $html .= '<p class="hb-card-excerpt">' . $e($excerpt) . '</p>';
    $html .= '<div class="hb-card-footer">';
    if ($date !== []) $html .= '<span class="hb-card-date">' . $e($copy['updated']) . ' <time datetime="' . $e($date['datetime']) . '">' . $e($date['label']) . '</time></span>';
    $html .= '<a class="hb-card-read" href="' . $e($url) . '" aria-label="' . $e($copy['read'] . ': ' . (string) ($row['title'] ?? '')) . '">' . $e($copy['read']) . '<i class="ph ph-arrow-up-right" aria-hidden="true"></i></a></div></div></article>';
    return $html;
}

function handbook_view_pagination(string $locale, array $filters, array $paging): string
{
    if ((int) $paging['total'] === 0) return '';
    $e = 'handbook_view_escape';
    $copy = handbook_view_copy($locale);
    $page = (int) $paging['page']; $pages = (int) $paging['pages'];
    $html = '<nav class="hb-pagination" aria-label="' . $e($copy['pagination']) . '"><p>'
        . $e(sprintf($copy['range'], $paging['start'], $paging['end'], $paging['total'])) . '</p>';
    if ($pages <= 1) return $html . '</nav>';
    $link = static fn(int $target, string $label, string $content, string $rel = ''): string => '<a href="'
        . $e(handbook_view_page_url($locale, $filters, $target)) . '" aria-label="' . $e($label) . '"'
        . ($rel !== '' ? ' rel="' . $rel . '"' : '') . '>' . $content . '</a>';
    $html .= '<div class="hb-page-links">';
    if ($page > 1) $html .= $link($page - 1, $copy['previous'], '<i class="ph ph-arrow-left" aria-hidden="true"></i>', 'prev');
    $slots = array_values(array_unique(array_filter([1, $page - 1, $page, $page + 1, $pages], static fn(int $number): bool => $number >= 1 && $number <= $pages)));
    sort($slots); $last = 0;
    foreach ($slots as $number) {
        if ($last > 0 && $number - $last > 1) $html .= '<span class="hb-page-gap" aria-hidden="true">…</span>';
        $label = $copy['page'] . $number;
        $html .= $number === $page ? '<span class="hb-page-current" aria-current="page" aria-label="' . $e($label) . '">' . $number . '</span>'
            : $link($number, $label, (string) $number);
        $last = $number;
    }
    if ($page < $pages) $html .= $link($page + 1, $copy['next'], '<i class="ph ph-arrow-right" aria-hidden="true"></i>', 'next');
    return $html . '</div></nav>';
}

/**
 * Safely read AI-populated suggested facilities, doctors, and sources from posts.ai_content_json
 * and enrich linked facility/doctor IDs with their live public routes when available.
 *
 * @return array{facilities:list<array<string,mixed>>,doctors:list<array<string,mixed>>,sources:list<array<string,mixed>>}
 */
function handbook_article_ai_metadata(PDO $pdo, int $postId, string $locale = 'vi'): array
{
    $empty = ['facilities' => [], 'doctors' => [], 'sources' => []];
    if ($postId < 1) return $empty;
    $locale = handbook_view_locale($locale);
    try {
        $stmt = $pdo->prepare('SELECT ai_content_json FROM posts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $postId]);
        $raw = $stmt->fetchColumn();
    } catch (Throwable) {
        return $empty;
    }
    if (!is_string($raw) || trim($raw) === '') return $empty;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return $empty;

    $facilities = is_array($decoded['suggested_facilities_json'] ?? null) ? array_values($decoded['suggested_facilities_json']) : [];
    $doctors = is_array($decoded['suggested_doctors_json'] ?? null) ? array_values($decoded['suggested_doctors_json']) : [];
    $sources = is_array($decoded['sources_json'] ?? null) ? array_values($decoded['sources_json']) : [];

    $facilityIds = [];
    foreach ($facilities as $item) {
        if (is_array($item) && isset($item['facility_id']) && (int) $item['facility_id'] > 0) {
            $facilityIds[(int) $item['facility_id']] = (int) $item['facility_id'];
        }
    }
    $facilityMap = [];
    if ($facilityIds !== []) {
        try {
            $in = implode(',', array_fill(0, count($facilityIds), '?'));
            $fStmt = $pdo->prepare("SELECT id, slug, name, category, city, address_text, phone_text, image_url, status, verified FROM medical_facilities WHERE id IN ({$in})");
            $fStmt->execute(array_values($facilityIds));
            foreach ($fStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $fRow) {
                $facilityMap[(int) $fRow['id']] = $fRow;
            }
        } catch (Throwable) {
        }
    }

    $normalizedFacilities = [];
    foreach ($facilities as $item) {
        if (!is_array($item)) continue;
        $fid = isset($item['facility_id']) ? (int) $item['facility_id'] : 0;
        $live = $fid > 0 ? ($facilityMap[$fid] ?? null) : null;
        $slug = trim((string) ($live['slug'] ?? $item['slug'] ?? ''));
        $status = (string) ($live['status'] ?? 'published');
        $publicUrl = ($slug !== '' && $status === 'published' && function_exists('medical_public_entity_path'))
            ? medical_public_entity_path('facility', $slug, $locale)
            : ($slug !== '' ? ($locale === 'en' ? '/en/co-so-y-te/' : '/co-so-y-te/') . rawurlencode($slug) : '');
        $normalizedFacilities[] = [
            'facility_id' => $fid > 0 ? $fid : null,
            'name' => trim((string) ($live['name'] ?? $item['name'] ?? '')),
            'category' => trim((string) ($live['category'] ?? $item['category'] ?? '')),
            'address' => trim((string) ($live['address_text'] ?? $item['address'] ?? '')),
            'city' => trim((string) ($live['city'] ?? $item['city'] ?? '')),
            'phone' => trim((string) ($live['phone_text'] ?? $item['phone'] ?? '')),
            'image_url' => handbook_view_image_url((string) ($live['image_url'] ?? '')),
            'verified' => (int) ($live['verified'] ?? 0) === 1,
            'website' => handbook_view_image_url((string) ($item['website'] ?? '')),
            'evidence_url' => handbook_view_image_url((string) ($item['evidence_url'] ?? '')),
            'public_url' => $publicUrl,
            'resolution' => (string) ($item['resolution'] ?? ''),
        ];
    }

    $doctorIds = [];
    foreach ($doctors as $item) {
        if (is_array($item) && isset($item['doctor_id']) && (int) $item['doctor_id'] > 0) {
            $doctorIds[(int) $item['doctor_id']] = (int) $item['doctor_id'];
        }
    }
    $doctorMap = [];
    if ($doctorIds !== []) {
        try {
            $in = implode(',', array_fill(0, count($doctorIds), '?'));
            $dStmt = $pdo->prepare("SELECT id, slug, name, specialty_text, facility_name, address_text, city, image_url, status, verified FROM medical_doctors WHERE id IN ({$in})");
            $dStmt->execute(array_values($doctorIds));
            foreach ($dStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $dRow) {
                $doctorMap[(int) $dRow['id']] = $dRow;
            }
        } catch (Throwable) {
        }
    }

    $normalizedDoctors = [];
    foreach ($doctors as $item) {
        if (!is_array($item)) continue;
        $did = isset($item['doctor_id']) ? (int) $item['doctor_id'] : 0;
        $live = $did > 0 ? ($doctorMap[$did] ?? null) : null;
        $slug = trim((string) ($live['slug'] ?? $item['slug'] ?? ''));
        $status = (string) ($live['status'] ?? 'published');
        $publicUrl = ($slug !== '' && $status === 'published' && function_exists('medical_public_entity_path'))
            ? medical_public_entity_path('doctor', $slug, $locale)
            : ($slug !== '' ? ($locale === 'en' ? '/en/bac-si/' : '/bac-si/') . rawurlencode($slug) : '');
        $normalizedDoctors[] = [
            'doctor_id' => $did > 0 ? $did : null,
            'name' => trim((string) ($live['name'] ?? $item['name'] ?? '')),
            'specialty' => trim((string) ($live['specialty_text'] ?? $item['specialty'] ?? '')),
            'facility_name' => trim((string) ($live['facility_name'] ?? $item['facility_name'] ?? '')),
            'address' => trim((string) ($live['address_text'] ?? $item['address'] ?? '')),
            'city' => trim((string) ($live['city'] ?? $item['city'] ?? '')),
            'image_url' => handbook_view_image_url((string) ($live['image_url'] ?? '')),
            'verified' => (int) ($live['verified'] ?? 0) === 1,
            'website' => handbook_view_image_url((string) ($item['website'] ?? '')),
            'evidence_url' => handbook_view_image_url((string) ($item['evidence_url'] ?? '')),
            'public_url' => $publicUrl,
            'resolution' => (string) ($item['resolution'] ?? ''),
        ];
    }

    return [
        'facilities' => array_values(array_filter($normalizedFacilities, static fn(array $r): bool => $r['name'] !== '')),
        'doctors' => array_values(array_filter($normalizedDoctors, static fn(array $r): bool => $r['name'] !== '')),
        'sources' => $sources,
    ];
}

function handbook_view_related_section(array $related, string $locale = 'vi'): string
{
    $facilities = is_array($related['facilities'] ?? null) ? $related['facilities'] : [];
    $doctors = is_array($related['doctors'] ?? null) ? $related['doctors'] : [];
    if ($facilities === [] && $doctors === []) return '';

    $e = 'handbook_view_escape';
    $isEn = handbook_view_locale($locale) === 'en';
    $facTitle = $isEn ? 'Recommended Healthcare Facilities' : 'Danh sách cơ sở y tế gợi ý';
    $facSub = $isEn ? 'Facilities referenced for this health topic' : 'Các cơ sở y tế phù hợp được đối chiếu theo chủ đề bài viết';
    $docTitle = $isEn ? 'Recommended Specialists & Doctors' : 'Danh sách bác sĩ gợi ý';
    $docSub = $isEn ? 'Specialists and doctors referenced for this topic' : 'Các bác sĩ chuyên khoa phù hợp được đối chiếu theo chủ đề bài viết';
    $viewProfileLabel = $isEn ? 'View profile' : 'Xem hồ sơ';
    $viewSourceLabel = $isEn ? 'Official source' : 'Nguồn tham khảo';

    $html = '<aside class="hb-suggest-wrap" aria-label="' . $e($isEn ? 'Recommended facilities and doctors' : 'Danh sách cơ sở y tế và bác sĩ gợi ý') . '">';

    if ($doctors !== []) {
        $html .= '<div class="hb-suggest-group hb-suggest-group--doctor">';
        $html .= '<div class="hb-suggest-group-head">';
        $html .= '<div class="hb-suggest-title-wrap">';
        $html .= '<span class="hb-suggest-head-icon" aria-hidden="true"><i class="ph ph-stethoscope"></i></span>';
        $html .= '<div><h2 class="hb-suggest-group-title">' . $e($docTitle) . '</h2><p class="hb-suggest-group-sub">' . $e($docSub) . '</p></div>';
        $html .= '</div>';
        $html .= '<span class="hb-suggest-count">' . count($doctors) . ' ' . $e($isEn ? 'doctors' : 'bác sĩ') . '</span>';
        $html .= '</div>';
        $html .= '<div class="hb-suggest-grid">';
        foreach ($doctors as $doc) {
            $primaryUrl = (string) ($doc['public_url'] ?? '');
            $extUrl = (string) (($doc['website'] ?? '') !== '' ? $doc['website'] : ($doc['evidence_url'] ?? ''));
            $titleHref = $primaryUrl !== '' ? $primaryUrl : $extUrl;
            $img = (string) ($doc['image_url'] ?? '');
            $verified = !empty($doc['verified']);
            $specialty = (string) ($doc['specialty'] ?? '');
            $facilityName = (string) ($doc['facility_name'] ?? '');
            $locationText = trim((string) ($doc['address'] ?? ''));
            if ($locationText === '') $locationText = trim((string) ($doc['city'] ?? ''));
            elseif (!empty($doc['city']) && !str_contains(mb_strtolower($locationText, 'UTF-8'), mb_strtolower((string) $doc['city'], 'UTF-8'))) {
                $locationText .= ', ' . $doc['city'];
            }

            $html .= '<article class="hb-suggest-card">';
            $html .= '<div class="hb-suggest-card-top">';
            $html .= '<div class="hb-suggest-avatar" aria-hidden="true">'
                . ($img !== '' ? '<img src="' . $e($img) . '" alt="" width="44" height="44" loading="lazy" decoding="async">' : '<i class="ph ph-user-circle"></i>')
                . '</div>';
            $html .= '<div class="hb-suggest-identity">';
            $html .= '<h3 class="hb-suggest-name">'
                . ($titleHref !== '' ? '<a href="' . $e($titleHref) . '"' . ($primaryUrl === '' ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . $e($doc['name']) . '</a>' : $e($doc['name']))
                . ($verified ? '<span class="hb-suggest-verified" title="' . $e($isEn ? 'Verified profile' : 'Hồ sơ đã xác thực') . '"><i class="ph ph-seal-check" aria-hidden="true"></i></span>' : '')
                . '</h3>';
            if ($specialty !== '') {
                $html .= '<div class="hb-suggest-tags"><span class="hb-suggest-tag"><i class="ph ph-first-aid" aria-hidden="true"></i>' . $e($specialty) . '</span></div>';
            }
            $html .= '</div></div>';

            if ($facilityName !== '' || $locationText !== '') {
                $html .= '<ul class="hb-suggest-meta">';
                if ($facilityName !== '') {
                    $html .= '<li><i class="ph ph-buildings" aria-hidden="true"></i><span>' . $e($facilityName) . '</span></li>';
                }
                if ($locationText !== '') {
                    $html .= '<li><i class="ph ph-map-pin" aria-hidden="true"></i><span>' . $e($locationText) . '</span></li>';
                }
                $html .= '</ul>';
            }

            if ($primaryUrl !== '' || $extUrl !== '') {
                $html .= '<div class="hb-suggest-footer">';
                if ($primaryUrl !== '') {
                    $html .= '<a class="hb-suggest-link is-primary" href="' . $e($primaryUrl) . '"><i class="ph ph-arrow-up-right" aria-hidden="true"></i><span>' . $e($viewProfileLabel) . '</span></a>';
                }
                if ($extUrl !== '') {
                    $html .= '<a class="hb-suggest-link is-secondary" href="' . $e($extUrl) . '" target="_blank" rel="noopener noreferrer"><i class="ph ph-globe" aria-hidden="true"></i><span>' . $e($viewSourceLabel) . '</span></a>';
                }
                $html .= '</div>';
            }
            $html .= '</article>';
        }
        $html .= '</div></div>';
    }

    if ($facilities !== []) {
        $html .= '<div class="hb-suggest-group hb-suggest-group--facility">';
        $html .= '<div class="hb-suggest-group-head">';
        $html .= '<div class="hb-suggest-title-wrap">';
        $html .= '<span class="hb-suggest-head-icon" aria-hidden="true"><i class="ph ph-buildings"></i></span>';
        $html .= '<div><h2 class="hb-suggest-group-title">' . $e($facTitle) . '</h2><p class="hb-suggest-group-sub">' . $e($facSub) . '</p></div>';
        $html .= '</div>';
        $html .= '<span class="hb-suggest-count">' . count($facilities) . ' ' . $e($isEn ? 'facilities' : 'cơ sở') . '</span>';
        $html .= '</div>';
        $html .= '<div class="hb-suggest-grid">';
        foreach ($facilities as $fac) {
            $primaryUrl = (string) ($fac['public_url'] ?? '');
            $extUrl = (string) (($fac['website'] ?? '') !== '' ? $fac['website'] : ($fac['evidence_url'] ?? ''));
            $titleHref = $primaryUrl !== '' ? $primaryUrl : $extUrl;
            $img = (string) ($fac['image_url'] ?? '');
            $verified = !empty($fac['verified']);
            $category = (string) ($fac['category'] ?? '');
            $phone = (string) ($fac['phone'] ?? '');
            $locationText = trim((string) ($fac['address'] ?? ''));
            if ($locationText === '') $locationText = trim((string) ($fac['city'] ?? ''));
            elseif (!empty($fac['city']) && !str_contains(mb_strtolower($locationText, 'UTF-8'), mb_strtolower((string) $fac['city'], 'UTF-8'))) {
                $locationText .= ', ' . $fac['city'];
            }

            $html .= '<article class="hb-suggest-card">';
            $html .= '<div class="hb-suggest-card-top">';
            $html .= '<div class="hb-suggest-avatar" aria-hidden="true">'
                . ($img !== '' ? '<img src="' . $e($img) . '" alt="" width="44" height="44" loading="lazy" decoding="async">' : '<i class="ph ph-buildings"></i>')
                . '</div>';
            $html .= '<div class="hb-suggest-identity">';
            $html .= '<h3 class="hb-suggest-name">'
                . ($titleHref !== '' ? '<a href="' . $e($titleHref) . '"' . ($primaryUrl === '' ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . $e($fac['name']) . '</a>' : $e($fac['name']))
                . ($verified ? '<span class="hb-suggest-verified" title="' . $e($isEn ? 'Verified facility' : 'Cơ sở đã xác thực') . '"><i class="ph ph-seal-check" aria-hidden="true"></i></span>' : '')
                . '</h3>';
            if ($category !== '') {
                $html .= '<div class="hb-suggest-tags"><span class="hb-suggest-tag"><i class="ph ph-first-aid" aria-hidden="true"></i>' . $e($category) . '</span></div>';
            }
            $html .= '</div></div>';

            if ($locationText !== '' || $phone !== '') {
                $html .= '<ul class="hb-suggest-meta">';
                if ($locationText !== '') {
                    $html .= '<li><i class="ph ph-map-pin" aria-hidden="true"></i><span>' . $e($locationText) . '</span></li>';
                }
                if ($phone !== '') {
                    $html .= '<li><i class="ph ph-phone" aria-hidden="true"></i><span>' . $e($phone) . '</span></li>';
                }
                $html .= '</ul>';
            }

            if ($primaryUrl !== '' || $extUrl !== '') {
                $html .= '<div class="hb-suggest-footer">';
                if ($primaryUrl !== '') {
                    $html .= '<a class="hb-suggest-link is-primary" href="' . $e($primaryUrl) . '"><i class="ph ph-arrow-up-right" aria-hidden="true"></i><span>' . $e($viewProfileLabel) . '</span></a>';
                }
                if ($extUrl !== '') {
                    $html .= '<a class="hb-suggest-link is-secondary" href="' . $e($extUrl) . '" target="_blank" rel="noopener noreferrer"><i class="ph ph-globe" aria-hidden="true"></i><span>' . $e($viewSourceLabel) . '</span></a>';
                }
                $html .= '</div>';
            }
            $html .= '</article>';
        }
        $html .= '</div></div>';
    }

    $html .= '</aside>';
    return $html;
}
