<?php
declare(strict_types=1);

// Public review presentation and SELECT-only queries. No seeding or migrations.
function review_view_escape(mixed $value): string
{
    return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function review_view_text(string $vi, string $en, string $locale): string
{
    return $locale === 'en' ? $en : $vi;
}

function review_view_string(mixed $value): string
{
    return is_scalar($value) ? trim((string) $value) : '';
}

function review_view_safe_url(mixed $value, bool $local = true): string
{
    $url = review_view_string($value);
    if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) return '';
    if ($local && str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https', 'http'], true)
        && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) ? $url : '';
}

function review_view_array(mixed $value): array
{
    if (is_array($value)) return $value;
    $decoded = is_string($value) ? json_decode($value, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function review_view_row(array $row, string $locale = 'vi'): array
{
    $item = [];
    foreach (['slug', 'title', 'facility_slug', 'facility_name', 'excerpt'] as $key) $item[$key] = review_view_string($row[$key] ?? '');
    foreach (['author' => 'author_text', 'location' => 'location_text', 'date' => 'review_date_text', 'price' => 'price_text',
        'service' => 'service_text', 'method' => 'method_text', 'duration' => 'duration_text', 'start_date' => 'start_date_text',
        'end_date' => 'end_date_text', 'condition' => 'condition_text', 'source' => 'source_text'] as $key => $column) {
        $item[$key] = review_view_string($row[$column] ?? $row[$key] ?? '');
    }
    $item['id'] = max(0, (int) ($row['id'] ?? 0));
    $rating = is_numeric($row['rating'] ?? null) ? (float) $row['rating'] : 0;
    $item['rating'] = $rating > 0 && $rating <= 5 ? $rating : null;
    $item['is_verified'] = (int) ($row['verified'] ?? $row['is_verified'] ?? 0) === 1;
    $item['is_editorial'] = $item['condition'] === 'Nội dung biên tập, không phải đánh giá khách hàng'
        || ($item['author'] === 'Ban biên tập MedReview' && $item['method'] === 'Thông tin tổng hợp');
    $item['source_url'] = review_view_safe_url($item['source'], false);
    if ($item['source_url'] !== '') $item['source'] = (string) (parse_url($item['source_url'], PHP_URL_HOST) ?: $item['source']);
    $item['story'] = array_values(array_filter(array_map('review_view_string', review_view_array($row['story_json'] ?? $row['story'] ?? [])), static fn(string $text): bool => $text !== ''));
    $item['timeline'] = [];
    foreach (review_view_array($row['timeline_json'] ?? $row['timeline'] ?? []) as $point) {
        if (!is_array($point)) continue;
        $point = ['time' => review_view_string($point['date'] ?? $point['time'] ?? ''), 'title' => review_view_string($point['title'] ?? $point['label'] ?? ''),
            'text' => review_view_string($point['text'] ?? $point['description'] ?? ''), 'label' => review_view_string($point['label'] ?? '')];
        if (implode('', $point) !== '') $item['timeline'][] = $point;
    }
    $item['media'] = [];
    $seen = [];
    $add = static function (mixed $value, string $label) use (&$item, &$seen): void {
        if (count($item['media']) >= 18) return;
        $image = is_array($value) ? $value : ['url' => $value];
        $url = review_view_safe_url($image['url'] ?? $image['src'] ?? '');
        if ($url === '' || isset($seen[$url])) return;
        $seen[$url] = true;
        $caption = review_view_string($image['caption'] ?? $image['label'] ?? '') ?: $label;
        $item['media'][] = ['url' => $url, 'label' => $caption, 'alt' => review_view_string($image['alt'] ?? '') ?: $caption . ' — ' . $item['title']];
    };
    $add($row['before_image_url'] ?? $row['before'] ?? '', review_view_text('Trước điều trị', 'Before treatment', $locale));
    $add($row['after_image_url'] ?? $row['after'] ?? '', review_view_text('Sau điều trị', 'After treatment', $locale));
    foreach (['process_before' => ['Trước điều trị', 'Before treatment'], 'process_during' => ['Quá trình điều trị', 'During treatment'],
        'process_after' => ['Sau điều trị', 'After treatment'], 'thumbs' => ['Ảnh được chia sẻ', 'Shared photo']] as $key => $labels) {
        foreach (review_view_array($row[$key . '_json'] ?? $row[$key] ?? []) as $image) $add($image, review_view_text($labels[0], $labels[1], $locale));
    }
    return $item;
}

function review_view_filters(array $query): array
{
    $value = static fn(string $key): string => review_view_string($query[$key] ?? '');
    return ['q' => mb_substr($value('q'), 0, 120), 'rating' => in_array($value('rating'), ['4', '5'], true) ? $value('rating') : '',
        'media' => $value('media') === 'photos' ? 'photos' : '', 'sort' => $value('sort') === 'rating' ? 'rating' : 'newest'];
}

function review_view_page(array $query): int
{
    $value = $query['page'] ?? 1;
    if (!is_int($value) && !is_string($value)) return 1;
    $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $page === false ? 1 : $page;
}

function review_view_url(string $base, array $filters, int $page = 1): string
{
    $query = review_view_filters($filters);
    $query = array_filter($query, static fn(string $value, string $key): bool => $value !== '' && !($key === 'sort' && $value === 'newest'), ARRAY_FILTER_USE_BOTH);
    if ($page > 1) $query['page'] = $page;
    return $base . ($query !== [] ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}

function review_view_detail_url(string $slug, string $locale = 'vi'): string
{
    // Preserve existing indexed detail URLs and use the already-supported locale query.
    return '/review-chi-tiet.php?slug=' . rawurlencode($slug) . ($locale === 'en' ? '&lang=en' : '');
}

function review_view_icon(string $name): string
{
    $paths = [
        'search' => '<circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/>',
        'arrow-right' => '<path d="M4 12h16m-6-6 6 6-6 6"/>', 'arrow-left' => '<path d="M20 12H4m6-6-6 6 6 6"/>',
        'send' => '<path d="m21 3-6.8 18-3.5-7.7L3 9.8 21 3Z"/><path d="m10.7 13.3 5.5-5.5"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9Z"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>', 'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/>',
        'quote' => '<path d="M10 5H4v8h4c0 3-2 4-4 5m16-13h-6v8h4c0 3-2 4-4 5"/>',
        'images' => '<rect x="6" y="3" width="15" height="15" rx="2"/><path d="M3 7v12a2 2 0 0 0 2 2h12M6 14l4-4 5 5 3-3 3 3"/><circle cx="16" cy="7" r="1"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'building' => '<path d="M3 21h18M5 21V5h14v16M9 9h6m-3-3v6M9 21v-5h6v5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>', 'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'filter' => '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2"/><circle cx="16" cy="12" r="2"/><circle cx="10" cy="18" r="2"/>',
        'x' => '<path d="m6 6 12 12M6 18 18 6"/>', 'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10h.01"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'wallet' => '<path d="M20 7V5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h15V7H5m15 4h-6v6h6"/>',
        'expand' => '<path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/>',
    ];
    return '<svg class="rv-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['info']) . '</svg>';
}

function review_view_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    $stmt->execute([':table' => $table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function review_view_empty_summary(): array
{
    return ['total' => 0, 'rated_count' => 0, 'average' => 0.0, 'verified_count' => 0, 'photo_count' => 0, 'breakdown' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
}

function review_view_photo_sql(): string
{
    // Only explicit before/after photo fields are used for the directory photo filter.
    return "(TRIM(COALESCE(before_image_url,'')) REGEXP '^(https?://|/[^/])' OR TRIM(COALESCE(after_image_url,'')) REGEXP '^(https?://|/[^/])')";
}

function review_view_where(array $columns, string $locale): array
{
    $where = "status='published' AND TRIM(slug)<>'' AND TRIM(title)<>''";
    $params = [];
    if (in_array('language_code', $columns, true)) { $where .= ' AND language_code=:locale'; $params[':locale'] = $locale === 'en' ? 'en' : 'vi'; }
    elseif ($locale === 'en') $where .= ' AND 1=0'; // Legacy review rows are Vietnamese, not English translations.
    return [$where, $params];
}

function review_view_directory(PDO $pdo, array $filters, int $requestedPage, string $locale = 'vi', int $perPage = 8, bool $includeSummary = true): array
{
    $perPage = min(24, max(1, $perPage));
    $summary = review_view_empty_summary();
    $columns = review_view_columns($pdo, 'medical_reviews');
    if ($columns === []) return ['items' => [], 'summary' => $summary, 'pagination' => ['total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => $perPage, 'start' => 0, 'end' => 0]];
    [$where, $params] = review_view_where($columns, $locale);
    $photoSql = review_view_photo_sql();
    $ratingSql = 'rating>0 AND rating<=5';
    if ($includeSummary) {
        $statement = $pdo->prepare("SELECT COUNT(*) total, SUM({$ratingSql}) rated_count, AVG(CASE WHEN {$ratingSql} THEN rating END) average, SUM(verified=1) verified_count, SUM({$photoSql}) photo_count," .
            implode(',', array_map(static fn(int $score): string => "SUM(CASE WHEN {$ratingSql} AND GREATEST(1,LEAST(5,ROUND(rating)))={$score} THEN 1 ELSE 0 END) AS stars_{$score}", [1, 2, 3, 4, 5])) . " FROM medical_reviews WHERE {$where}");
        $statement->execute($params);
        $result = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['total', 'rated_count', 'verified_count', 'photo_count'] as $key) $summary[$key] = (int) ($result[$key] ?? 0);
        $summary['average'] = (float) ($result['average'] ?? 0);
        foreach ([1, 2, 3, 4, 5] as $score) $summary['breakdown'][$score] = (int) ($result['stars_' . $score] ?? 0);
    }
    if ($filters['q'] !== '') {
        $search = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $clauses = [];
        foreach (['title', 'facility_name', 'service_text', 'location_text', 'author_text', 'excerpt'] as $index => $column) {
            $clauses[] = "{$column} LIKE :q{$index} ESCAPE '!'"; $params[':q' . $index] = $search;
        }
        $where .= ' AND (' . implode(' OR ', $clauses) . ')';
    }
    if ($filters['rating'] !== '') { $where .= ' AND rating>=:rating AND rating<=5'; $params[':rating'] = (int) $filters['rating']; }
    if ($filters['media'] === 'photos') $where .= ' AND ' . $photoSql;
    $count = $pdo->prepare("SELECT COUNT(*) FROM medical_reviews WHERE {$where}");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $requestedPage), $pages);
    $offset = ($page - 1) * $perPage;
    $sort = $filters['sort'] === 'rating' ? 'rating DESC, created_at DESC, id DESC' : 'created_at DESC, id DESC';
    $stmt = $pdo->prepare("SELECT * FROM medical_reviews WHERE {$where} ORDER BY {$sort} LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    return ['items' => array_map(static fn(array $row): array => review_view_row($row, $locale), $stmt->fetchAll(PDO::FETCH_ASSOC)), 'summary' => $summary,
        'pagination' => ['total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'start' => $total ? $offset + 1 : 0, 'end' => min($offset + $perPage, $total)]];
}

function review_view_detail(PDO $pdo, string $slug, string $locale = 'vi'): array
{
    $empty = ['review' => null, 'related' => [], 'facility' => null];
    if ($slug === '') return $empty;
    $columns = review_view_columns($pdo, 'medical_reviews');
    if ($columns === []) return $empty;
    [$where, $params] = review_view_where($columns, $locale);
    $params[':slug'] = $slug;
    $stmt = $pdo->prepare("SELECT * FROM medical_reviews WHERE {$where} AND slug=:slug LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) return $empty;
    $review = review_view_row($row, $locale);
    $related = $pdo->prepare("SELECT * FROM medical_reviews WHERE {$where} AND slug<>:slug ORDER BY (facility_slug=:facility) DESC, created_at DESC, id DESC LIMIT 3");
    $related->execute($params + [':facility' => $review['facility_slug']]);
    $facility = $review['facility_name'] !== '' ? ['name' => $review['facility_name'], 'url' => '', 'location' => $review['location'], 'rating' => 0.0, 'review_count' => 0, 'image' => ''] : null;
    if ($review['facility_slug'] !== '') {
        $facilityColumns = review_view_columns($pdo, 'medical_facilities');
        if ($facilityColumns !== []) {
            $langWhere = in_array('language_code', $facilityColumns, true) ? ' AND language_code=:locale' : ($locale === 'en' ? ' AND 1=0' : '');
            $facilityStmt = $pdo->prepare("SELECT name,slug,city,address_text,rating,reviews_count,image_url FROM medical_facilities WHERE status='published' AND slug=:slug{$langWhere} LIMIT 1");
            $facilityStmt->execute([':slug' => $review['facility_slug']] + (str_contains($langWhere, ':locale') ? [':locale' => $locale] : []));
            $facilityRow = $facilityStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($facilityRow)) $facility = ['name' => review_view_string($facilityRow['name']),
                'url' => ($locale === 'en' ? '/en' : '') . '/co-so-y-te/' . rawurlencode($facilityRow['slug']),
                'location' => review_view_string($facilityRow['address_text'] ?: $facilityRow['city']), 'rating' => (float) $facilityRow['rating'],
                'review_count' => max(0, (int) $facilityRow['reviews_count']), 'image' => review_view_safe_url($facilityRow['image_url'])];
        }
    }
    return ['review' => $review, 'related' => array_map(static fn(array $value): array => review_view_row($value, $locale), $related->fetchAll(PDO::FETCH_ASSOC)), 'facility' => $facility];
}
