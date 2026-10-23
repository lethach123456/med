<?php
declare(strict_types=1);

/** Pure presentation helpers for editorial articles, never patient feedback. */
function review_articles_view_escape(mixed $value): string
{
    return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function review_articles_view_text(string $vi, string $en, string $locale): string
{
    return $locale === 'en' ? $en : $vi;
}

function review_articles_view_string(mixed $value, int $limit = 0): string
{
    $value = is_string($value) ? trim($value) : '';
    return $limit > 0 ? mb_substr($value, 0, $limit, 'UTF-8') : $value;
}

function review_articles_view_filters(array $query): array
{
    return ['q' => review_articles_view_string($query['q'] ?? '', 120),
        'category' => review_articles_view_string($query['category'] ?? '', 120),
        'city' => review_articles_view_string($query['city'] ?? '', 120)];
}

function review_articles_view_page(mixed $value): int
{
    if (!is_int($value) && !is_string($value)) return 1;
    $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
    return $page === false ? 1 : $page;
}

function review_articles_view_path(string $locale = 'vi', string $slug = ''): string
{
    $base = $locale === 'en' ? '/en/review' : '/review';
    return $slug !== '' ? $base . '/' . rawurlencode($slug) : $base;
}

function review_articles_view_page_url(string $locale, array $filters, int $page = 1): string
{
    $query = array_filter(review_articles_view_filters($filters), static fn(string $value): bool => $value !== '');
    if ($page > 1) $query['page'] = $page;
    return review_articles_view_path($locale) . ($query !== [] ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}

function review_articles_view_number(int $value, string $locale): string
{
    return number_format(max(0, $value), 0, '.', $locale === 'en' ? ',' : '.');
}

function review_articles_view_date(mixed $value, string $locale): array
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?)?$/D', $value)) return [];
    try {
        // Article writers store naive DATETIME values in UTC. Explicit offsets
        // retain their meaning; convert only the visible presentation timezone.
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) return [];
        $date = $date->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
        return ['datetime' => $date->format(DATE_ATOM), 'label' => $date->format($locale === 'en' ? 'M j, Y' : 'd/m/Y')];
    } catch (Throwable) { return []; }
}

function review_articles_view_safe_url(mixed $value, bool $allowLocal = true): string
{
    $url = review_articles_view_string($value);
    if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || preg_match('/%(?:00|0a|0d)/i', $url)) return '';
    if ($allowLocal && str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (function_exists('site_social_http_url')) return site_social_http_url($url);
    $parts = parse_url($url);
    return is_array($parts) && filter_var($url, FILTER_VALIDATE_URL)
        && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) ? $url : '';
}

function review_articles_view_html(mixed $value): string
{
    $html = review_articles_view_string($value);
    // Sanitize at the public boundary as well as on import, including old DB rows.
    return function_exists('medical_review_articles_sanitize_html')
        ? medical_review_articles_sanitize_html($html)
        : '<p>' . review_articles_view_escape(strip_tags($html)) . '</p>';
}

function review_articles_view_list(mixed $value): array
{
    if (is_string($value)) $value = json_decode($value, true);
    if (!is_array($value) || !array_is_list($value)) return [];
    return array_values(array_filter(array_map(static fn(mixed $item): string => review_articles_view_string($item), $value), static fn(string $item): bool => $item !== ''));
}

/** Source numbering is shared by inline section references and the source list. */
function review_articles_view_sources(array $article): array
{
    $values = $article['sources_json'] ?? [];
    if (is_string($values)) $values = json_decode($values, true);
    $sources = [];
    $seen = [];
    foreach (is_array($values) ? $values : [] as $source) {
        if (!is_array($source)) continue;
        $url = review_articles_view_safe_url($source['url'] ?? '', false);
        $id = review_articles_view_string($source['id'] ?? '');
        if ($url === '' || $id === '' || isset($seen[$id])) continue;
        $seen[$id] = true;
        $number = count($sources) + 1;
        $sources[] = ['id' => $id, 'number' => $number, 'anchor' => 'review-source-' . $number,
            'title' => review_articles_view_string($source['title'] ?? '') ?: (string) (parse_url($url, PHP_URL_HOST) ?: $url),
            'url' => $url, 'platform' => review_articles_view_string($source['platform'] ?? '')];
        if (count($sources) >= 100) break;
    }
    return $sources;
}

/** Stable anchors are generated here; arbitrary incoming section IDs are not trusted. */
function review_articles_view_sections(array $article, string $locale): array
{
    $sections = [];
    $sourcesById = [];
    foreach (review_articles_view_sources($article) as $source) $sourcesById[$source['id']] = $source;
    $values = $article['analysis_sections_json'] ?? [];
    if (is_string($values)) $values = json_decode($values, true);
    foreach (is_array($values) ? $values : [] as $section) {
        if (!is_array($section)) continue;
        $title = review_articles_view_string($section['title'] ?? '');
        $html = review_articles_view_html($section['content_html'] ?? '');
        if ($title === '' || trim(strip_tags($html)) === '') continue;
        $references = [];
        foreach (review_articles_view_list($section['source_ids'] ?? []) as $sourceId) {
            if (isset($sourcesById[$sourceId])) $references[$sourceId] = $sourcesById[$sourceId];
        }
        $sections[] = ['id' => 'review-analysis-' . (count($sections) + 1), 'title' => $title,
            'content_html' => $html, 'sources' => array_values($references)];
        if (count($sections) >= 30) break;
    }
    if ($sections === []) {
        $html = review_articles_view_html($article['content_html'] ?? '');
        if (trim(strip_tags($html)) !== '') $sections[] = ['id' => 'review-analysis',
            'title' => review_articles_view_text('Phân tích chi tiết', 'Detailed analysis', $locale), 'content_html' => $html, 'sources' => []];
    }
    return $sections;
}

function review_articles_view_toc(array $article, array $sections, string $locale): array
{
    $toc = [];
    if (review_articles_view_string($article['verdict'] ?? '') !== '') $toc[] = ['id' => 'review-overview', 'title' => review_articles_view_text('Góc nhìn tổng quan', 'At a glance', $locale)];
    foreach ($sections as $section) $toc[] = ['id' => $section['id'], 'title' => $section['title']];
    if (review_articles_view_list($article['strengths_json'] ?? []) !== [] || review_articles_view_list($article['considerations_json'] ?? []) !== []) {
        $toc[] = ['id' => 'review-observations', 'title' => review_articles_view_text('Điểm nổi bật & cần cân nhắc', 'Strengths & considerations', $locale)];
    }
    if (review_articles_view_list($article['suitable_for_json'] ?? []) !== []) $toc[] = ['id' => 'review-suitability', 'title' => review_articles_view_text('Phù hợp với nhu cầu nào?', 'Who may find it useful?', $locale)];
    return $toc;
}

/** Only explicitly consented customer media may cross into the public reading page. */
function review_articles_view_customer_images(mixed $value): array
{
    require_once __DIR__ . '/medical_patient_images.php';
    if (is_string($value)) $value = strlen($value) <= 1048576 ? json_decode($value, true, 32) : null;
    if (!is_array($value) || !array_is_list($value)) return [];
    $images = []; $seen = [];
    foreach (array_slice($value, 0, 100) as $item) {
        if (!is_array($item) || ($item['consent_confirmed'] ?? false) !== true) continue;
        try { $url = medical_patient_images_url($item['url'] ?? null, 'url'); }
        catch (InvalidArgumentException) { continue; }
        $key = preg_replace('/#.*$/s', '', $url);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $images[] = ['url' => $url,
            'alt' => review_articles_view_string($item['alt'] ?? '', 500),
            'caption' => review_articles_view_string($item['caption'] ?? '', 500)];
        if (count($images) >= 24) break;
    }
    return $images;
}

/** Detail-only SELECT: never add patient metadata to AI sources, lists or snapshots. */
function review_articles_view_load_customer_images(PDO $pdo, int $facilityId, string $locale): array
{
    require_once __DIR__ . '/medical_patient_images.php';
    if ($facilityId <= 0 || !medical_patient_images_has_column($pdo, 'facility')) return [];
    $query = $pdo->prepare("SELECT patient_images_json FROM medical_facilities WHERE id=:id AND status='published'
        AND COALESCE(NULLIF(LOWER(TRIM(language_code)),''),'vi')=:locale LIMIT 1");
    $query->execute([':id' => $facilityId, ':locale' => $locale === 'en' ? 'en' : 'vi']);
    return review_articles_view_customer_images($query->fetchColumn());
}

function review_articles_view_toc_html(array $toc): string
{
    $html = '<ol class="ra-toc-list">';
    foreach ($toc as $index => $section) $html .= '<li><a href="#' . review_articles_view_escape($section['id']) . '"><span aria-hidden="true">' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '</span><span>' . review_articles_view_escape($section['title']) . '</span></a></li>';
    return $html . '</ol>';
}

function review_articles_view_card(array $article, string $locale): string
{
    $e = 'review_articles_view_escape';
    $facility = is_array($article['facility'] ?? null) ? $article['facility'] : [];
    $title = review_articles_view_string($article['title'] ?? '');
    $url = review_articles_view_path($locale, review_articles_view_string($article['slug'] ?? ''));
    $image = review_articles_view_safe_url($article['cover_image_url'] ?? '');
    $date = review_articles_view_date($article['published_at'] ?? $article['updated_at'] ?? '', $locale);
    $minutes = max(1, min(120, (int) ($article['reading_minutes'] ?? 1)));
    $category = review_articles_view_string($facility['category'] ?? $article['category'] ?? '');
    $city = review_articles_view_string($facility['city'] ?? $article['city'] ?? '');
    $entityType = ($article['entity_type'] ?? 'facility') === 'doctor' ? 'doctor' : 'facility';
    $category = $category !== '' ? $category : review_articles_view_text($entityType === 'doctor' ? 'Bác sĩ' : 'Cơ sở y tế', $entityType === 'doctor' ? 'Doctor' : 'Healthcare facility', $locale);
    $html = '<article class="ra-card"><a class="ra-card-media" href="' . $e($url) . '" tabindex="-1" aria-hidden="true">';
    if ($image !== '') {
        $attributes = function_exists('medical_media_image_attributes')
            ? medical_media_image_attributes($image, '(max-width:620px) 100px, 190px', [240, 400, 640], 400)
            : 'src="' . $e($image) . '"';
        $html .= '<img ' . $attributes . ' width="400" height="300" alt="" loading="lazy" decoding="async">';
    } else $html .= '<span class="ra-media-fallback' . ($entityType === 'doctor' ? ' ra-media-fallback--doctor' : '') . '"><i class="ph ph-' . ($entityType === 'doctor' ? 'stethoscope' : 'hospital') . '"></i></span>';
    $html .= '</a><div class="ra-card-body"><div class="ra-card-copy"><div class="ra-card-category"><span>' . $e($category) . '</span>' . ($city !== '' ? '<span>' . $e($city) . '</span>' : '') . '</div><h3><a href="' . $e($url) . '">' . $e($title) . '</a></h3></div>';
    $excerpt = review_articles_view_string($article['excerpt'] ?? '');
    if ($excerpt !== '') $html .= '<p class="ra-card-excerpt">' . $e($excerpt) . '</p>';
    $html .= '<div class="ra-card-footer"><div class="ra-card-meta">';
    if ($date !== []) $html .= '<time datetime="' . $e($date['datetime']) . '"><i class="ph ph-calendar-blank" aria-hidden="true"></i>' . $e($date['label']) . '</time>';
    $html .= '<span><i class="ph ph-clock" aria-hidden="true"></i>' . $minutes . ' ' . review_articles_view_text('phút đọc', 'min read', $locale) . '</span></div><a class="ra-read-link" href="' . $e($url) . '" aria-label="' . $e(review_articles_view_text('Đọc bài: ', 'Read: ', $locale) . $title) . '">' . review_articles_view_text('Đọc bài', 'Read article', $locale) . '<i class="ph ph-arrow-right" aria-hidden="true"></i></a></div></div></article>';
    return $html;
}

function review_articles_view_pagination(array $paging, array $filters, string $locale): string
{
    $total = max(0, (int) ($paging['total'] ?? 0));
    $pages = max(1, (int) ($paging['pages'] ?? 1));
    $page = min($pages, max(1, (int) ($paging['page'] ?? 1)));
    if ($total === 0 || $pages === 1) return '';
    $e = 'review_articles_view_escape';
    $url = static fn(int $target): string => review_articles_view_page_url($locale, $filters, $target);
    $html = '<nav class="ra-pagination" aria-label="' . review_articles_view_text('Phân trang bài review', 'Review article pagination', $locale) . '"><p>' . review_articles_view_number((int) ($paging['start'] ?? 1), $locale) . '–' . review_articles_view_number((int) ($paging['end'] ?? $total), $locale) . ' / ' . review_articles_view_number($total, $locale) . ' ' . review_articles_view_text('bài viết', 'articles', $locale) . '</p><div class="ra-page-links">';
    if ($page > 1) $html .= '<a href="' . $e($url($page - 1)) . '" rel="prev" aria-label="' . review_articles_view_text('Trang trước', 'Previous page', $locale) . '"><i class="ph ph-caret-left" aria-hidden="true"></i></a>';
    $numbers = array_unique(array_filter([1, $page - 1, $page, $page + 1, $pages], static fn(int $n): bool => $n >= 1 && $n <= $pages));
    sort($numbers);
    $last = 0;
    foreach ($numbers as $number) {
        if ($last > 0 && $number > $last + 1) $html .= '<span class="ra-page-gap" aria-hidden="true">…</span>';
        $label = review_articles_view_text('Trang ', 'Page ', $locale) . $number;
        $html .= $number === $page ? '<span aria-current="page" aria-label="' . $e($label) . '">' . $number . '</span>'
            : '<a href="' . $e($url($number)) . '" aria-label="' . $e($label) . '">' . $number . '</a>';
        $last = $number;
    }
    if ($page < $pages) $html .= '<a href="' . $e($url($page + 1)) . '" rel="next" aria-label="' . review_articles_view_text('Trang sau', 'Next page', $locale) . '"><i class="ph ph-caret-right" aria-hidden="true"></i></a>';
    return $html . '</div></nav>';
}
