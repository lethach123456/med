<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_directory.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }

medical_redirect_legacy_path('/co-so-y-te.php', medical_public_facility_path());

$seo = front_editor_page_seo('co-so-y-te', [
    'title' => 'Cơ sở y tế • MedReview',
    'description' => 'Khám phá, tìm kiếm và so sánh cơ sở y tế trên MedReview.',
    'canonical_path' => medical_public_facility_path(),
]);
$title = (string) ($seo['title'] ?? 'Cơ sở y tế • MedReview');
$description = (string) ($seo['description'] ?? '');
$canonicalPath = medical_public_facility_path();
$seoKeywords = (string) ($seo['keywords'] ?? '');

function facility_page_list_values(?string $value): array
{
    $decoded = json_decode((string) $value, true);
    if (!is_array($decoded)) return [];
    $values = [];
    foreach ($decoded as $item) {
        if (is_array($item)) $item = $item['name'] ?? $item['title'] ?? '';
        $item = trim((string) $item);
        if ($item !== '') $values[] = $item;
    }
    return array_values(array_unique($values));
}

function facility_page_item_from_row(array $row): array
{
    $gallery = medical_directory_gallery_urls((string) ($row['gallery_json'] ?? ''));
    $image = trim((string) ($row['image_url'] ?? ''));
    if ($image === '' && $gallery !== []) $image = (string) $gallery[0];
    $services = facility_page_list_values((string) ($row['featured_services_json'] ?? ''));
    if ($services === []) $services = facility_page_list_values((string) ($row['services_json'] ?? ''));

    return [
        'slug' => (string) ($row['slug'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'category' => (string) ($row['category'] ?? 'Cơ sở y tế'),
        'city' => (string) ($row['city'] ?? ''),
        'subtitle' => trim((string) ($row['subtitle'] ?? '')),
        'address' => trim((string) ($row['address_text'] ?? '')),
        'hours' => trim((string) ($row['hours_text'] ?? '')),
        'rating' => number_format((float) ($row['rating'] ?? 0), 1, '.', ''),
        'reviews_count' => (int) ($row['reviews_count'] ?? 0),
        'followers_count' => (int) ($row['followers_count'] ?? 0),
        'verified' => (bool) ((int) ($row['verified'] ?? 0)),
        'price' => trim((string) ($row['price_text'] ?? '')),
        'image' => $image,
        'image_count' => count($gallery),
        'images_label' => trim((string) ($row['images_label'] ?? '')),
        'services' => array_slice($services, 0, 4),
    ];
}

function facility_page_filters(): array
{
    $rating = (string) ($_GET['min_rating'] ?? '');
    return [
        'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120, 'UTF-8'),
        'city' => mb_substr(trim((string) ($_GET['city'] ?? '')), 0, 120, 'UTF-8'),
        'category' => mb_substr(trim((string) ($_GET['category'] ?? '')), 0, 120, 'UTF-8'),
        'service' => mb_substr(trim((string) ($_GET['service'] ?? '')), 0, 120, 'UTF-8'),
        'min_rating' => in_array($rating, ['4', '4.5'], true) ? $rating : '',
        'sort' => in_array((string) ($_GET['sort'] ?? ''), ['recommended', 'newest', 'rating', 'reviews'], true)
            ? (string) $_GET['sort']
            : 'recommended',
    ];
}

function facility_page_query(PDO $pdo, array $filters, int $page = 1, int $limit = 12): array
{
    $where = ["status = 'published'"];
    $params = [];
    $queryCity = '';
    if ($filters['q'] !== '' && $filters['city'] === '') {
        $cityCandidates = $pdo->query("SELECT city FROM medical_facilities WHERE status = 'published' AND city <> '' GROUP BY city ORDER BY CHAR_LENGTH(city) DESC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($cityCandidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && mb_stripos($filters['q'], $candidate, 0, 'UTF-8') !== false) {
                $queryCity = $candidate;
                break;
            }
        }
    }
    if ($queryCity !== '') {
        $where[] = 'city = :query_city_exact';
        $params[':query_city_exact'] = $queryCity;
    }
    if ($filters['q'] !== '') {
        $terms = preg_split('/[^\p{L}\p{N}]+/u', $filters['q']) ?: [];
        $terms = array_values(array_unique(array_filter($terms, static fn(string $term): bool => mb_strlen($term, 'UTF-8') >= 2)));
        foreach (array_slice($terms, 0, 6) as $index => $term) {
            $placeholder = ':search_term_' . $index;
            $where[] = "CONCAT_WS(' ', name, subtitle, category, city, address_text, services_json, featured_services_json, slug) LIKE {$placeholder}";
            $params[$placeholder] = '%' . $term . '%';
        }
    }
    foreach (['city', 'category'] as $field) {
        if ($filters[$field] !== '') {
            $where[] = $field . ' = :' . $field;
            $params[':' . $field] = $filters[$field];
        }
    }
    if ($filters['service'] !== '') {
        $where[] = '(COALESCE(featured_services_json, \'\') LIKE :service_featured OR COALESCE(services_json, \'\') LIKE :service_all)';
        $serviceLike = '%' . $filters['service'] . '%';
        $params[':service_featured'] = $serviceLike;
        $params[':service_all'] = $serviceLike;
    }
    if ($filters['min_rating'] !== '') {
        $where[] = 'rating >= :min_rating';
        $params[':min_rating'] = (float) $filters['min_rating'];
    }
    $orderBy = match ($filters['sort']) {
        'newest' => 'updated_at DESC, id DESC',
        'rating' => 'rating DESC, reviews_count DESC, id DESC',
        'reviews' => 'reviews_count DESC, rating DESC, id DESC',
        default => 'rating DESC, reviews_count DESC, display_order ASC, id DESC',
    };
    $whereSql = implode(' AND ', $where);
    $count = $pdo->prepare("SELECT COUNT(*) FROM medical_facilities WHERE {$whereSql}");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $limit));
    $page = min(max(1, $page), $pages);
    $offset = ($page - 1) * $limit;
    $stmt = $pdo->prepare(
        "SELECT slug, name, category, city, subtitle, verified, rating, reviews_count, followers_count,
                hours_text, address_text, price_text, image_url, images_label, featured_services_json, services_json, gallery_json
         FROM medical_facilities WHERE {$whereSql}
         ORDER BY {$orderBy} LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return [
        'items' => array_map('facility_page_item_from_row', $stmt->fetchAll(PDO::FETCH_ASSOC)),
        'total' => $total,
        'page' => $page,
        'total_pages' => $pages,
    ];
}

function facility_page_number(int $number): string
{
    return number_format($number, 0, ',', '.');
}

$filters = facility_page_filters();
$requestedPage = max(1, (int) ($_GET['page'] ?? 1));
$initial = ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
$cities = [];
$categories = [];
$services = [];
$directoryStats = ['facilities' => 0, 'reviews' => 0];

try {
    // The page shell, facets and first page use the same JSON snapshot as the
    // AJAX endpoint. MySQL is only touched when the TTL has expired or an
    // editor/API update invalidates the snapshot.
    $cache = medical_search_cache_index();
    $index = $cache['index'];
    $directory = medical_search_cache_directory_search($index, $filters + ['page' => $requestedPage, 'limit' => 12]);
    $initial = [
        'items' => $directory['items'],
        'total' => (int) $directory['paging']['total'],
        'page' => (int) $directory['paging']['page'],
        'total_pages' => (int) $directory['paging']['total_pages'],
    ];

    $cityCounts = [];
    $categoryCounts = [];
    foreach ((array) ($index['facilities'] ?? []) as $facility) {
        if (!is_array($facility)) continue;
        $directoryStats['facilities']++;
        $directoryStats['reviews'] += max(0, (int) ($facility['reviews_count'] ?? 0));
        $city = trim((string) ($facility['city'] ?? ''));
        $category = trim((string) ($facility['category'] ?? ''));
        if ($city !== '') $cityCounts[$city] = ($cityCounts[$city] ?? 0) + 1;
        if ($category !== '') $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
        foreach ((array) ($facility['services'] ?? []) as $service) {
            $service = trim((string) $service);
            if ($service !== '') $services[$service] = true;
        }
    }
    arsort($cityCounts); arsort($categoryCounts);
    $cities = array_slice(array_keys($cityCounts), 0, 60);
    $categories = array_slice(array_keys($categoryCounts), 0, 40);
    $services = array_slice(array_keys($services), 0, 50);
    natcasesort($services);
    $services = array_values($services);
} catch (Throwable $e) {
    // Render the page shell even if cache storage and DB are both unavailable.
}

function facility_page_card(array $item): string
{
    $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $image = trim((string) ($item['image'] ?? ''));
    $imageLabel = trim((string) ($item['images_label'] ?? ''));
    if ($imageLabel === '' && (int) ($item['image_count'] ?? 0) > 0) $imageLabel = (int) $item['image_count'] . ' ảnh';
    $rating = (float) ($item['rating'] ?? 0);
    $stars = $rating > 0 ? '★★★★★' : '☆☆☆☆☆';
    ob_start(); ?>
    <article class="facility-card">
      <a class="facility-media" href="<?php echo $escape(medical_public_facility_path((string) $item['slug'])); ?>" aria-label="Xem <?php echo $escape($item['name']); ?>">
        <?php if ($image !== ''): ?>
          <img src="<?php echo $escape($image); ?>" alt="<?php echo $escape($item['name']); ?>" loading="lazy">
        <?php else: ?>
          <span class="facility-media-empty"><i class="ph ph-hospital"></i></span>
        <?php endif; ?>
        <?php if ($imageLabel !== ''): ?><span class="media-label"><i class="ph ph-images"></i><?php echo $escape($imageLabel); ?></span><?php endif; ?>
      </a>
      <div class="facility-body">
        <div class="facility-eyebrow"><span><?php echo $escape($item['category']); ?></span><?php if (!empty($item['city'])): ?><span class="eyebrow-dot">•</span><span><?php echo $escape($item['city']); ?></span><?php endif; ?></div>
        <div class="facility-name-row">
          <h2><a href="<?php echo $escape(medical_public_facility_path((string) $item['slug'])); ?>"><?php echo $escape($item['name']); ?></a></h2>
          <?php if (!empty($item['verified'])): ?><span class="verified-badge" title="Hồ sơ đã xác thực"><i class="ph-fill ph-seal-check"></i><span>Đã xác thực</span></span><?php endif; ?>
        </div>
        <?php if (!empty($item['subtitle'])): ?><p class="facility-subtitle"><?php echo $escape($item['subtitle']); ?></p><?php endif; ?>
        <div class="facility-details">
          <?php if (!empty($item['address'])): ?><span><i class="ph ph-map-pin"></i><?php echo $escape($item['address']); ?></span><?php endif; ?>
          <?php if (!empty($item['hours'])): ?><span><i class="ph ph-clock"></i><?php echo $escape($item['hours']); ?></span><?php endif; ?>
        </div>
        <?php $serviceItems = array_values((array) ($item['services'] ?? [])); ?>
        <?php if ($serviceItems !== []): ?>
          <div class="service-tags" data-service-tags>
            <?php foreach ($serviceItems as $index => $service): ?><span class="service-tag<?php echo $index > 0 ? ' is-extra' : ''; ?>"><?php echo $escape($service); ?></span><?php endforeach; ?>
            <?php if (count($serviceItems) > 1): ?><button class="service-tags-more" type="button" data-service-tags-more data-extra-count="<?php echo count($serviceItems) - 1; ?>" aria-expanded="false">+<?php echo count($serviceItems) - 1; ?><i class="ph ph-caret-down" aria-hidden="true"></i></button><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="facility-score">
        <?php if ($rating > 0): ?>
          <div class="score-number"><?php echo number_format($rating, 1); ?><small>/5</small></div>
          <div class="score-stars" aria-label="<?php echo number_format($rating, 1); ?> trên 5"><?php echo $stars; ?></div>
          <span><?php echo facility_page_number((int) $item['reviews_count']); ?> đánh giá</span>
        <?php else: ?>
          <div class="score-number score-pending">—</div><span>Chưa có đánh giá</span>
        <?php endif; ?>
      </div>
      <div class="facility-price">
        <span>Giá tham khảo</span>
        <strong><?php echo !empty($item['price']) ? $escape($item['price']) : 'Liên hệ cập nhật'; ?></strong>
      </div>
      <a class="detail-button" href="<?php echo $escape(medical_public_facility_path((string) $item['slug'])); ?>">Xem hồ sơ<i class="ph ph-arrow-up-right"></i></a>
    </article>
    <?php return trim((string) ob_get_clean());
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
  <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
  <link rel="canonical" href="<?php echo htmlspecialchars($canonicalPath, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
  <style>
    :root{--page:#f5f8fc;--ink:#10203a;--muted:#64748b;--line:#e2eaf5;--blue:#2563eb;--blue-soft:#eff6ff;--green:#159947;--gold:#f59e0b;--surface:#fff;--max:1320px}
    *{box-sizing:border-box} html{scroll-behavior:smooth} body{margin:0;background:linear-gradient(180deg,#f8fbff 0,#f5f8fc 58%,#fff 100%);color:var(--ink);font-family:"Plus Jakarta Sans",system-ui,-apple-system,Segoe UI,sans-serif}.facility-directory{padding:24px 0 72px}.directory-container{width:min(calc(100% - 36px),var(--max));margin:0 auto}.directory-breadcrumb{display:flex;gap:8px;align-items:center;margin-bottom:18px;color:#8ba0bd;font-size:12px;font-weight:700}.directory-breadcrumb a{color:inherit;text-decoration:none}.directory-breadcrumb strong{color:var(--blue)}.directory-breadcrumb i{font-size:13px}
    .directory-hero{position:relative;overflow:hidden;display:flex;justify-content:space-between;gap:36px;padding:30px 34px;border:1px solid #dce8f8;border-radius:26px;background:linear-gradient(115deg,#fff 0%,#f7fbff 54%,#eef5ff 100%);box-shadow:0 18px 44px rgba(30,64,175,.06)}.directory-hero:after{content:"";position:absolute;width:270px;height:270px;right:-70px;top:-145px;border-radius:50%;background:radial-gradient(circle,rgba(59,130,246,.16),transparent 69%);pointer-events:none}.directory-kicker{display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:999px;background:#eaf3ff;color:#1d5fce;font-size:11px;font-weight:800;letter-spacing:.025em;text-transform:uppercase}.directory-kicker i{font-size:15px}.directory-hero h1{max-width:660px;margin:13px 0 9px;font-size:clamp(28px,3vw,39px);line-height:1.15;letter-spacing:-.045em}.directory-hero p{max-width:630px;margin:0;color:#61738f;font-size:14px;line-height:1.7}.directory-stats{z-index:1;display:grid;grid-template-columns:repeat(2,minmax(120px,1fr));gap:10px;align-self:center;min-width:305px}.directory-stat{padding:14px 16px;border:1px solid #dce8f8;border-radius:16px;background:rgba(255,255,255,.82);box-shadow:0 8px 22px rgba(30,64,175,.045)}.directory-stat strong{display:block;color:#1d5fce;font-size:22px;line-height:1.1;letter-spacing:-.04em}.directory-stat span{display:block;margin-top:5px;color:#6b7d98;font-size:11px;font-weight:700}
    .directory-filter{position:relative;z-index:3;margin-top:16px;padding:12px;border:1px solid var(--line);border-radius:20px;background:rgba(255,255,255,.94);box-shadow:0 14px 32px rgba(15,23,42,.045)}.filter-search-row{display:flex;align-items:center;gap:9px}.filter-search{position:relative;display:flex;align-items:center;flex:1;min-width:0;height:50px;padding:0 15px;border:1px solid #cfdcf0;border-radius:14px;background:#fff;transition:.18s border-color,.18s box-shadow}.filter-search:focus-within{border-color:#75a6fc;box-shadow:0 0 0 4px rgba(37,99,235,.1)}.filter-search>i{margin-right:10px;color:#3b82f6;font-size:19px}.filter-search input{min-width:0;flex:1;border:0;outline:0;background:transparent;color:var(--ink);font:600 13px/1 "Plus Jakarta Sans",sans-serif}.filter-search input::placeholder{color:#94a3b8;font-weight:500}.filter-submit,.filter-toggle,.filter-reset,.detail-button{border:0;cursor:pointer;font:800 13px/1 "Plus Jakarta Sans",sans-serif;text-decoration:none}.filter-submit{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-width:130px;height:50px;padding:0 18px;border-radius:13px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;box-shadow:0 12px 22px rgba(37,99,235,.22)}.filter-submit i{font-size:17px}.filter-toggle{position:relative;display:inline-flex;align-items:center;justify-content:center;gap:7px;flex:0 0 auto;height:50px;padding:0 14px;border:1px solid #d7e3f4;border-radius:13px;background:#f8fbff;color:#466586;font-size:12px}.filter-toggle:hover,.filter-toggle[aria-expanded="true"]{border-color:#9cc0fa;background:#eff6ff;color:#2563eb}.filter-toggle i{font-size:17px}.filter-toggle b{display:inline-flex;align-items:center;justify-content:center;min-width:17px;height:17px;padding:0 4px;border-radius:999px;background:#2563eb;color:#fff;font-size:9px}.filter-options{margin-top:10px;padding:13px;border:1px solid #e2eaf5;border-radius:15px;background:#f9fbff}.filter-options[hidden]{display:none!important}.filter-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr)) auto;gap:10px}.filter-field{min-width:0}.filter-field label{display:block;margin:0 0 6px 2px;color:#64748b;font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.filter-select-wrap{position:relative}.filter-select-wrap i{position:absolute;left:12px;top:50%;z-index:1;transform:translateY(-50%);color:#6580aa;font-size:16px;pointer-events:none}.filter-select-wrap select{width:100%;height:42px;padding:0 28px 0 35px;overflow:hidden;border:1px solid #dbe5f3;border-radius:12px;outline:0;background:#fff;color:#34445f;font:600 12px/1 "Plus Jakarta Sans",sans-serif;white-space:nowrap;text-overflow:ellipsis;appearance:auto}.filter-select-wrap select:focus{border-color:#7caaf7}.filter-reset{align-self:end;height:42px;padding:0 12px;border:1px solid transparent;border-radius:12px;background:transparent;color:#6380aa;font-size:12px}.filter-reset:hover{background:#f3f7fd;color:var(--blue)}
    .filter-toggle b[hidden]{display:none!important}
    .directory-content{display:grid;grid-template-columns:minmax(0,1fr) 258px;gap:18px;margin-top:20px;align-items:start}.result-topline{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:0 2px 12px}.result-count{margin:0;font-size:13px;color:#64748b}.result-count strong{color:#182b49;font-size:16px}.result-sort-copy{color:#8aa0bf;font-size:11px;font-weight:700}.facility-list{display:grid;gap:12px}.facility-card{display:grid;grid-template-columns:172px minmax(230px,1fr) 100px 145px 112px;gap:16px;align-items:center;padding:10px;border:1px solid var(--line);border-radius:20px;background:var(--surface);box-shadow:0 10px 26px rgba(15,23,42,.035);transition:.18s transform,.18s box-shadow,.18s border-color}.facility-card:hover{transform:translateY(-2px);border-color:#c8daf5;box-shadow:0 16px 34px rgba(30,64,175,.09)}.facility-media{position:relative;display:block;height:142px;overflow:hidden;border-radius:14px;background:linear-gradient(140deg,#edf4ff,#e2ebf8)}.facility-media img{width:100%;height:100%;object-fit:cover;transition:transform .25s}.facility-card:hover .facility-media img{transform:scale(1.035)}.facility-media-empty{display:grid;width:100%;height:100%;place-items:center;color:#78a3e7;font-size:38px}.media-label{position:absolute;left:8px;bottom:8px;display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:999px;background:rgba(15,31,55,.72);color:#fff;font-size:10px;font-weight:800;backdrop-filter:blur(8px)}.media-label i{font-size:13px}.facility-body{min-width:0}.facility-eyebrow{display:flex;gap:6px;overflow:hidden;color:#6d82a0;font-size:10px;font-weight:800;letter-spacing:.035em;text-transform:uppercase;white-space:nowrap;text-overflow:ellipsis}.eyebrow-dot{color:#afc0d9}.facility-name-row{display:flex;align-items:center;gap:8px;margin-top:6px}.facility-name-row h2{min-width:0;margin:0;overflow:hidden;font-size:17px;line-height:1.3;letter-spacing:-.027em;text-overflow:ellipsis;white-space:nowrap}.facility-name-row h2 a{color:#16243b;text-decoration:none}.facility-name-row h2 a:hover{color:var(--blue)}.verified-badge{display:inline-flex;flex:0 0 auto;align-items:center;gap:4px;padding:4px 7px;border-radius:999px;background:#edf9f1;color:#119447;font-size:10px;font-weight:800;white-space:nowrap}.verified-badge i{font-size:13px}.facility-subtitle{display:-webkit-box;overflow:hidden;margin:6px 0 0;color:#61738f;font-size:11px;line-height:1.55;-webkit-box-orient:vertical;-webkit-line-clamp:2}.facility-details{display:grid;gap:4px;margin-top:8px}.facility-details span{display:flex;align-items:flex-start;gap:5px;overflow:hidden;color:#73849e;font-size:10.5px;line-height:1.42}.facility-details i{flex:0 0 auto;margin-top:1px;color:#6c95d8;font-size:14px}.service-tags{display:flex;gap:5px;flex-wrap:wrap;margin-top:9px}.service-tags span{max-width:145px;overflow:hidden;padding:4px 7px;border-radius:6px;background:#f2f6fc;color:#526783;font-size:9.5px;font-weight:700;text-overflow:ellipsis;white-space:nowrap}.facility-score{align-self:stretch;display:flex;flex-direction:column;justify-content:center;padding-left:15px;border-left:1px solid #e5edf8}.score-number{color:#14233e;font-size:23px;font-weight:800;letter-spacing:-.04em}.score-number small{margin-left:2px;color:#7789a5;font-size:10px;letter-spacing:0}.score-pending{color:#a0afc3}.score-stars{margin:4px 0 3px;color:var(--gold);font-size:12px;letter-spacing:0}.facility-score>span{color:#74849a;font-size:10px;line-height:1.45}.facility-price{align-self:stretch;display:flex;flex-direction:column;justify-content:center;padding-left:15px;border-left:1px solid #e5edf8}.facility-price span{color:#8292a9;font-size:9.5px;font-weight:800;text-transform:uppercase}.facility-price strong{margin-top:6px;color:#255ab6;font-size:11px;line-height:1.45}.detail-button{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:38px;padding:0 10px;border:1px solid #cfe0fc;border-radius:11px;background:#f8fbff;color:#2864cf;font-size:11px;white-space:nowrap}.detail-button:hover{border-color:#3b82f6;background:#2563eb;color:#fff}.detail-button i{font-size:14px}
    .directory-aside{position:sticky;top:100px;display:grid;gap:12px}.aside-card{padding:18px;border:1px solid var(--line);border-radius:19px;background:rgba(255,255,255,.88);box-shadow:0 10px 26px rgba(15,23,42,.035)}.aside-card h2{display:flex;align-items:center;gap:8px;margin:0 0 11px;color:#213650;font-size:14px;letter-spacing:-.02em}.aside-card h2 i{color:#3b82f6;font-size:19px}.aside-card p{margin:0;color:#71819a;font-size:11px;line-height:1.65}.guide-list{display:grid;gap:11px;margin:15px 0 0;padding:0;list-style:none}.guide-list li{display:flex;gap:9px;color:#536783;font-size:11px;line-height:1.5}.guide-list i{color:#2563eb;font-size:16px}.directory-source{padding:14px 16px;border:1px solid #d9e8ff;border-radius:17px;background:linear-gradient(145deg,#f9fcff,#edf5ff)}.directory-source strong{display:block;color:#2459b7;font-size:12px}.directory-source span{display:block;margin-top:5px;color:#6980a4;font-size:10px;line-height:1.55}.directory-source i{margin-right:5px}.empty-state{padding:48px 22px;border:1px dashed #cad9ee;border-radius:20px;background:rgba(255,255,255,.65);text-align:center}.empty-state i{display:inline-grid;width:46px;height:46px;place-items:center;border-radius:16px;background:#eaf3ff;color:#3b82f6;font-size:25px}.empty-state h2{margin:12px 0 5px;font-size:17px}.empty-state p{margin:0;color:#71819a;font-size:12px}.pagination{display:flex;align-items:center;justify-content:center;gap:7px;margin-top:22px}.pagination button{min-width:36px;height:36px;padding:0 10px;border:1px solid #d8e4f4;border-radius:10px;background:#fff;color:#527095;font:700 12px/1 "Plus Jakarta Sans",sans-serif;cursor:pointer}.pagination button:hover:not(:disabled),.pagination button.is-current{border-color:#2563eb;background:#2563eb;color:#fff}.pagination button:disabled{cursor:not-allowed;opacity:.45}.pagination .pagination-gap{color:#91a4bd;font-size:12px}.directory-loading{display:none;align-items:center;gap:8px;color:#6681a7;font-size:12px;font-weight:700}.directory-loading.is-visible{display:flex}.directory-loading i{animation:directory-spin .8s linear infinite;font-size:18px}@keyframes directory-spin{to{transform:rotate(360deg)}}
    /* Allow nested grid items to shrink inside the available page width. */
    .directory-hero > div,
    .directory-content > *,
    .facility-list,
    .facility-card,
    .facility-card > * { min-width: 0; }
    .directory-hero > :first-child { flex: 1; }
    .facility-list { grid-template-columns: minmax(0, 1fr); }
    .facility-media { text-decoration: none; }
    .facility-score { flex-wrap: wrap; }
    .service-tags-more {
      display: none;
      align-items: center;
      justify-content: center;
      gap: 2px;
      height: 25px;
      padding: 0 7px;
      border: 1px dashed #aac7f7;
      border-radius: 7px;
      background: #f8fbff;
      color: #2563eb;
      font: 800 10px/1 "Plus Jakarta Sans", sans-serif;
      cursor: pointer;
    }
    .service-tags-more i { font-size: 12px; transition: transform .16s ease; }
    .service-tags.is-expanded .service-tags-more i { transform: rotate(180deg); }
    .medical-header .container,
    .medical-footer .container {
      width: min(calc(100% - 36px), var(--max));
      min-width: 0;
      margin-inline: auto;
    }

    @media (max-width: 1180px) {
      .filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) auto; }
      .facility-card { grid-template-columns: 150px minmax(0, 1fr) 100px 115px; }
      .detail-button { grid-column: 3 / -1; }
      .directory-content { grid-template-columns: minmax(0, 1fr); }
      .directory-aside {
        position: static;
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 980px) {
      .facility-directory { padding-top: 16px; }
      .directory-hero {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 20px;
        padding: 24px;
      }
      .directory-hero h1 { font-size: 30px; }
      .directory-stats {
        width: 100%;
        min-width: 0;
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .filter-reset { justify-self: start; }
      .facility-card {
        grid-template-columns: 120px minmax(0, 1fr) auto;
        grid-template-areas: "photo info info" "score score action";
        gap: 10px 16px;
        align-items: start;
        padding: 14px;
        border-color: #dfe8f5;
        box-shadow: 0 10px 24px rgba(30, 64, 175, .055);
      }
      .facility-media { grid-area: photo; height: 120px; }
      .facility-body { grid-area: info; }
      .facility-score {
        grid-area: score;
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        gap: 6px;
        padding: 0;
        border: 0;
      }
      .score-number { font-size: 20px; }
      .score-stars { margin: 0; }
      .facility-score > span { font-size: 12px; }
      .facility-name-row { align-items: flex-start; }
      .facility-name-row h2 {
        font-size: 17px;
        white-space: normal;
        overflow-wrap: anywhere;
      }
      .verified-badge { padding: 4px; }
      .verified-badge span,
      .facility-price,
      .facility-details span:nth-child(2),
      .result-sort-copy { display: none; }
      .media-label {
        left: 6px;
        bottom: 6px;
        gap: 4px;
        padding: 4px 7px;
        font-size: 9px;
      }
      .media-label i { font-size: 11px; }
      .detail-button {
        display: inline-flex;
        grid-area: action;
        width: auto;
        min-width: 106px;
        height: 38px;
        margin: 0;
        padding: 0 12px;
        border-color: #cfe0fc;
        border-radius: 11px;
        background: #f8fbff;
        color: #2563eb;
        font-size: 12px;
      }
      .facility-eyebrow { font-size: 11px; flex-wrap: wrap; white-space: normal; }
      .facility-subtitle { font-size: 13px; }
      .facility-details span { font-size: 12px; overflow-wrap: anywhere; }
      .service-tags .service-tag { max-width: 100%; font-size: 11px; }
      .service-tags.is-collapsible .service-tag.is-extra { display: none; }
      .service-tags.is-collapsible.is-expanded .service-tag.is-extra { display: inline-flex; }
      .service-tags.is-collapsible .service-tags-more { display: inline-flex; }
      .directory-aside { grid-template-columns: minmax(0, 1fr); }
      .aside-card:not(:first-child) { display: none; }
      .result-topline { flex-wrap: wrap; gap: 8px; }
      .pagination { flex-wrap: wrap; }
      .pagination button { min-width: 40px; height: 40px; }
    }

    @media (max-width: 560px) {
      .directory-container,
      .medical-header .container,
      .medical-footer .container { width: calc(100% - 20px); }
      .facility-directory { padding: 8px 0 28px; }
      .directory-breadcrumb { font-size: 11px; margin-bottom: 8px; }
      .directory-hero { padding: 12px 10px; gap: 10px; border-radius: 14px; }
      .directory-hero h1 { font-size: 20px; line-height: 1.22; margin: 6px 0 4px; }
      .directory-hero p { font-size: 12px; line-height: 1.5; }
      .directory-kicker { font-size: 9.5px; padding: 4px 8px; }
      .directory-kicker i { flex-shrink: 0; }
      .directory-stats { display: flex; gap: 6px; margin-top: 4px; }
      .directory-stat { flex: 1; padding: 7px 10px; border-radius: 10px; }
      .directory-stat strong { font-size: 17px; }
      .directory-stat span { font-size: 10px; line-height: 1.35; }

      .directory-filter { margin-top: 10px; padding: 7px; border-radius: 14px; }
      .filter-search-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 6px;
      }
      .filter-search { grid-column: 1 / -1; width: 100%; height: 40px; padding: 0 10px; }
      .filter-search input { width: 100%; font-size: 14px; }
      .filter-toggle { justify-self: start; height: 38px; padding: 0 10px; font-size: 12px; }
      .filter-submit { min-width: 0; height: 38px; padding: 0 14px; font-size: 12px; }
      .filter-options { padding: 10px; border-radius: 10px; }
      .filter-grid { grid-template-columns: minmax(0, 1fr); gap: 8px; }
      .filter-field label { font-size: 10.5px; }
      .filter-select-wrap select { height: 38px; font-size: 14px; }
      .filter-reset { height: 38px; font-size: 12px; }

      .directory-content { margin-top: 12px; gap: 12px; }
      .result-count { font-size: 12px; }
      .facility-list { gap: 10px; }
      .facility-card {
        grid-template-columns: 82px minmax(0, 1fr) auto;
        grid-template-areas: "photo info info" "score score action";
        gap: 9px;
        padding: 10px;
        border-radius: 14px;
      }
      .facility-card:hover { transform: none; }
      .facility-media { height: 82px; border-radius: 10px; }
      .facility-media img { display: block; }
      .media-label { left: 4px; bottom: 4px; padding: 3px 5px; font-size: 8px; }
      .media-label i { font-size: 9px; }
      .facility-eyebrow { gap: 4px; font-size: 9.5px; }
      .facility-name-row { gap: 4px; margin-top: 3px; }
      .facility-name-row h2 { font-size: 15px; line-height: 1.35; }
      .facility-subtitle { display: none; }
      .facility-details { margin-top: 5px; }
      .facility-details span { font-size: 11.5px; line-height: 1.45; }
      .facility-details i { font-size: 13px; }
      .service-tags { margin-top: 6px; }
      .service-tags .service-tag { padding: 3px 5px; font-size: 9.5px; }
      .service-tags-more { height: 22px; padding: 0 6px; font-size: 9.5px; }
      .facility-score { padding-top: 8px; border-top: 1px solid #edf2f8; gap: 6px; }
      .score-number { font-size: 17px; }
      .score-stars { font-size: 12px; }
      .facility-score > span { font-size: 11px; }
      .detail-button {
        min-width: 86px;
        height: 34px;
        margin: 0;
        padding: 0 8px;
        border-color: #bfd5fb;
        border-radius: 10px;
        background: linear-gradient(135deg, #f8fbff, #eef5ff);
        box-shadow: 0 5px 12px rgba(37, 99, 235, .08);
        font-size: 12px;
      }
      .detail-button i { font-size: 14px; }
      .pagination { gap: 4px; margin-top: 14px; }
      .empty-state { padding: 24px 12px; }
    }

    @media (max-width: 360px) {
      .directory-hero h1 { font-size: 24px; }
      .facility-card { grid-template-columns: 72px minmax(0, 1fr) auto; gap: 10px; }
      .facility-media { height: 72px; }
      .detail-button { min-width: 84px; height: 35px; padding-inline: 7px; font-size: 10px; }
      .detail-button i { font-size: 13px; }
      .service-tags { display: none; }
      .pagination button { min-width: 34px; padding-inline: 7px; }
    }

    /* Lightweight interaction layer: keeps the directory responsive while
       making filter changes and card navigation feel intentional. */
    @view-transition { navigation: auto; }
    ::view-transition-old(root) { animation: directory-page-out .16s ease both; }
    ::view-transition-new(root) { animation: directory-page-in .24s cubic-bezier(.2,.8,.2,1) both; }
    @keyframes directory-page-out { to { opacity: 0; transform: translateY(-3px); } }
    @keyframes directory-page-in { from { opacity: 0; transform: translateY(8px); } }
    @keyframes directory-surface-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes directory-filter-in { from { opacity: 0; transform: translateY(-5px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .directory-hero,
    .directory-filter,
    .directory-content { animation: directory-surface-in .42s cubic-bezier(.2,.8,.2,1) both; }
    .directory-filter { animation-delay: .055s; }
    .directory-content { animation-delay: .1s; }
    .filter-options:not([hidden]) { animation: directory-filter-in .2s cubic-bezier(.2,.8,.2,1) both; transform-origin: top center; }
    .facility-list { transition: opacity .18s ease, filter .18s ease; }
    .facility-list[aria-busy="true"] { opacity: .58; filter: saturate(.78); }
    .facility-list.is-revealing .facility-card {
      animation: directory-surface-in .34s cubic-bezier(.2,.8,.2,1) both;
      animation-delay: calc(min(var(--reveal-index, 0), 6) * 42ms);
    }
    .facility-card,
    .detail-button,
    .filter-submit,
    .filter-toggle,
    .filter-reset,
    .pagination button,
    .service-tags-more { transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease, color .18s ease; }
    .facility-card:focus-within { border-color: #a9c8f7; box-shadow: 0 16px 34px rgba(30,64,175,.1); }
    .filter-search:focus-within { transform: translateY(-1px); }
    .filter-submit:focus-visible,
    .filter-toggle:focus-visible,
    .filter-reset:focus-visible,
    .detail-button:focus-visible,
    .pagination button:focus-visible,
    .service-tags-more:focus-visible { outline: 3px solid rgba(37,99,235,.23); outline-offset: 3px; }
    @media (hover:hover) {
      .filter-submit:hover,
      .detail-button:hover { transform: translateY(-1px); box-shadow: 0 10px 20px rgba(37,99,235,.18); }
      .filter-toggle:hover,
      .filter-reset:hover,
      .pagination button:hover:not(:disabled),
      .service-tags-more:hover { transform: translateY(-1px); }
    }
    @media (hover:none) {
      .filter-submit:active,
      .filter-toggle:active,
      .filter-reset:active,
      .detail-button:active,
      .pagination button:active,
      .service-tags-more:active { transform: scale(.975); }
    }
    @media (prefers-reduced-motion: reduce) {
      html { scroll-behavior: auto; }
      .directory-hero,
      .directory-filter,
      .directory-content,
      .filter-options:not([hidden]),
      .facility-list.is-revealing .facility-card,
      .directory-loading i { animation: none !important; }
      .facility-list,
      .facility-card,
      .detail-button,
      .filter-submit,
      .filter-toggle,
      .filter-reset,
      .pagination button,
      .service-tags-more,
      .filter-search { transition-duration: .01ms !important; }
    }
  </style>
  <style id="directory-search-placement">
    /* Search stays with the directory introduction; the expandable controls
       live beside the result source line so the list remains the visual focus. */
    .directory-search{width:100%;margin:18px 0 0}
    .result-meta{display:flex;align-items:center;flex-wrap:wrap;gap:10px;min-width:0}
    .directory-filter-panel{position:relative;min-width:0;margin:0}
    .directory-filter-panel .filter-toggle{height:42px}
    .directory-filter-panel .filter-options{position:absolute;top:100%;right:0;z-index:20;width:min(900px,calc(100vw - 48px));margin-top:8px}
    .directory-filter-panel .filter-grid{align-items:end}
    .result-topline > .directory-filter-panel{margin-left:auto}
    .result-topline > .directory-loading{margin-left:auto}
    @media (max-width:980px){
      .result-topline{align-items:flex-start}
      .result-meta{gap:7px 10px}
      .result-sort-copy{display:block;color:#8aa0bf;font-size:10px;line-height:1.45}
      .result-topline > .directory-filter-panel{margin-left:auto}
    }
    @media (max-width:560px){
      .directory-search{margin:14px 0 0}
      .directory-search .filter-search-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}
      .directory-search .filter-search{grid-column:1;grid-row:1;height:46px;padding:0 11px}
      .directory-search .filter-submit{grid-column:2;grid-row:1;min-width:96px;height:46px;padding:0 12px}
      .directory-search .filter-submit span{font-size:12px}
      /* Keep the result count and the filter on one calm, compact line.
         The provenance copy is useful on desktop, but redundant on a phone. */
      .result-topline{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:8px}
      .result-meta{grid-column:1;grid-row:1;gap:0;min-width:0}
      .result-sort-copy{display:none}
      .result-count{overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
      .result-topline > .directory-loading{grid-column:1 / -1;grid-row:2;margin-left:0}
      .result-topline > .directory-filter-panel{grid-column:2;grid-row:1;margin-left:0;display:flex;flex-wrap:wrap;justify-content:flex-end;width:auto}
      .directory-filter-panel{margin:0}
      .directory-filter-panel .filter-options{position:static;flex:0 0 100%;width:100%}
      .directory-filter-panel .filter-options{padding:10px}
    }
  </style>
  <style id="directory-hero-polish">
    /* Keep the search field visually inside the hero while preserving a
       compact two-column balance for the live statistics. */
    .directory-hero {
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) minmax(270px, .8fr);
      align-items: center;
      gap: 48px;
      min-height: 300px;
      padding: 34px 38px;
    }
    .directory-hero > :first-child {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: flex-start;
    }
    .directory-hero h1 { max-width: 700px; margin-top: 12px; }
    .directory-hero p { max-width: 680px; }
    .directory-search {
      max-width: 720px;
      align-self: stretch;
      margin-top: 20px;
      padding: 0;
      border: 0;
      border-radius: 0;
      background: transparent;
      box-shadow: none;
      -webkit-backdrop-filter: none;
      backdrop-filter: none;
    }
    .directory-search .filter-search-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 132px;
      gap: 6px;
      padding: 5px;
      border: 1px solid rgba(191, 219, 254, .85);
      border-radius: 17px;
      background: #fff;
      box-shadow: 0 16px 34px rgba(15, 23, 42, .09);
      transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }
    .directory-search .filter-search-row:focus-within {
      border-color: #79aaf8;
      box-shadow: 0 18px 38px rgba(37, 99, 235, .16);
      transform: translateY(-1px);
    }
    .directory-search .filter-search {
      height: 50px;
      padding: 0 14px;
      border: 0;
      border-radius: 0;
      background: transparent;
      box-shadow: none;
    }
    .directory-search .filter-search:focus-within { border: 0; box-shadow: none; }
    .directory-search .filter-search > i { margin-right: 10px; font-size: 21px; }
    .directory-search .filter-search input { height: 50px; font-size: 14px; }
    .directory-search .filter-submit {
      min-width: 132px;
      height: 50px;
      border-radius: 13px;
      font-size: 14px;
      font-weight: 800;
    }
    .directory-stats {
      width: 100%;
      max-width: 365px;
      justify-self: end;
    }
    .directory-stat {
      min-height: 86px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    @media (max-width: 1100px) {
      .directory-hero { gap: 30px; padding-inline: 30px; }
      .directory-hero h1 { font-size: clamp(28px, 3.2vw, 36px); }
      .directory-stats { max-width: 330px; }
    }
    @media (max-width: 980px) {
      .directory-hero {
        grid-template-columns: minmax(0, 1fr);
        gap: 22px;
        min-height: 0;
        padding: 24px;
      }
      .directory-stats { max-width: none; justify-self: stretch; }
    }
    @media (max-width: 560px) {
      .directory-hero { gap: 16px; padding: 18px; }
      .directory-search { margin-top: 14px; }
      .directory-search .filter-search-row { gap: 6px; padding: 5px; }
      .directory-search .filter-search { height: 44px; padding: 0 10px; }
      .directory-search .filter-search input { height: 44px; font-size: 12.5px; }
      .directory-search .filter-submit { min-width: 96px; height: 44px; }
      .directory-stats { max-width: none; }
      .directory-stat { min-height: 76px; }
    }
    @media (min-width: 1181px) {
      .directory-search .filter-search-row { grid-template-columns: minmax(0, 1fr) 48px; }
      .directory-search .filter-submit { display: grid; width: 48px; min-width: 48px; height: 48px; place-items: center; gap: 0; padding: 0; }
      .directory-search .filter-submit i { font-size: 19px; }
      .directory-search .filter-submit span { display: none; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/Tem/header.php'; ?>
<main class="facility-directory site-typo">
  <section class="directory-container">
    <nav class="directory-breadcrumb" aria-label="Breadcrumb"><a href="/">Trang chủ</a><i class="ph ph-caret-right"></i><strong>Cơ sở y tế</strong></nav>
    <header class="directory-hero">
      <div>
        <span class="directory-kicker"><i class="ph-fill ph-heartbeat"></i>Dữ liệu được cập nhật</span>
        <h1>Tìm cơ sở y tế phù hợp với nhu cầu của bạn</h1>
        <p>Khám phá hồ sơ, dịch vụ, bảng giá tham khảo và đánh giá thực tế để có thêm thông tin trước khi lựa chọn.</p>
        <form class="directory-filter directory-search" id="facilityDirectoryFilter" method="get" action="/co-so-y-te" novalidate>
          <div class="filter-search-row">
            <label class="filter-search" for="facilitySearch"><i class="ph ph-magnifying-glass"></i><input id="facilitySearch" name="q" value="<?php echo htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" placeholder="Ví dụ: nha khoa Đà Nẵng, spa Huế..." aria-label="Tìm cơ sở y tế"></label>
            <button class="filter-submit" type="submit" aria-label="Tìm kiếm"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><span>Tìm kiếm</span></button>
          </div>
        </form>
      </div>
      <div class="directory-stats" aria-label="Thống kê MedReview">
        <div class="directory-stat"><strong><?php echo facility_page_number($directoryStats['facilities']); ?></strong><span>Cơ sở đã công bố</span></div>
        <div class="directory-stat"><strong><?php echo facility_page_number($directoryStats['reviews']); ?></strong><span>Đánh giá thực tế</span></div>
      </div>
    </header>
    <div class="directory-content">
      <div>
        <div class="result-topline">
          <div class="result-meta">
            <p class="result-count" id="facilityResultCount"><strong><?php echo facility_page_number($initial['total']); ?></strong> cơ sở phù hợp</p>
            <span class="result-sort-copy">Dữ liệu hồ sơ được chọn lọc từ MedReview</span>
          </div>
          <div class="directory-loading" id="facilityDirectoryLoading"><i class="ph ph-spinner-gap"></i>Đang cập nhật danh sách</div>
          <div class="directory-filter-panel" id="facilityFilterPanel">
            <button class="filter-toggle" type="button" id="facilityFilterToggle" aria-expanded="false" aria-controls="facilityFilterOptions"><i class="ph ph-sliders-horizontal"></i><span>Bộ lọc</span><b id="facilityFilterCount" hidden>0</b></button>
            <div class="filter-options" id="facilityFilterOptions" hidden>
              <div class="filter-grid">
                <div class="filter-field"><label for="filterCity">Khu vực</label><div class="filter-select-wrap"><i class="ph ph-map-pin"></i><select id="filterCity" name="city" form="facilityDirectoryFilter" data-facility-filter-control><option value="">Tất cả khu vực</option><?php foreach ($cities as $city): ?><option value="<?php echo htmlspecialchars((string) $city, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['city'] === (string) $city ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $city, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div></div>
                <div class="filter-field"><label for="filterCategory">Nhóm cơ sở</label><div class="filter-select-wrap"><i class="ph ph-buildings"></i><select id="filterCategory" name="category" form="facilityDirectoryFilter" data-facility-filter-control><option value="">Tất cả nhóm cơ sở</option><?php foreach ($categories as $category): ?><option value="<?php echo htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['category'] === (string) $category ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div></div>
                <div class="filter-field"><label for="filterService">Dịch vụ</label><div class="filter-select-wrap"><i class="ph ph-stethoscope"></i><select id="filterService" name="service" form="facilityDirectoryFilter" data-facility-filter-control><option value="">Tất cả dịch vụ</option><?php foreach ($services as $service): ?><option value="<?php echo htmlspecialchars((string) $service, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filters['service'] === (string) $service ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $service, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div></div>
                <div class="filter-field"><label for="filterRating">Điểm đánh giá</label><div class="filter-select-wrap"><i class="ph ph-star"></i><select id="filterRating" name="min_rating" form="facilityDirectoryFilter" data-facility-filter-control><option value="">Mọi mức điểm</option><option value="4"<?php echo $filters['min_rating'] === '4' ? ' selected' : ''; ?>>Từ 4.0 sao</option><option value="4.5"<?php echo $filters['min_rating'] === '4.5' ? ' selected' : ''; ?>>Từ 4.5 sao</option></select></div></div>
                <div class="filter-field"><label for="filterSort">Sắp xếp</label><div class="filter-select-wrap"><i class="ph ph-arrows-down-up"></i><select id="filterSort" name="sort" form="facilityDirectoryFilter" data-facility-filter-control><option value="recommended"<?php echo $filters['sort'] === 'recommended' ? ' selected' : ''; ?>>Phù hợp nhất</option><option value="newest"<?php echo $filters['sort'] === 'newest' ? ' selected' : ''; ?>>Mới cập nhật</option><option value="rating"<?php echo $filters['sort'] === 'rating' ? ' selected' : ''; ?>>Điểm cao nhất</option><option value="reviews"<?php echo $filters['sort'] === 'reviews' ? ' selected' : ''; ?>>Nhiều đánh giá</option></select></div></div>
                <button class="filter-reset" type="button" id="facilityFilterReset">Xóa bộ lọc</button>
              </div>
            </div>
          </div>
        </div>
        <div class="facility-list" id="facilityList">
          <?php if ($initial['items'] !== []): foreach ($initial['items'] as $item) echo facility_page_card($item); else: ?>
            <div class="empty-state"><i class="ph ph-magnifying-glass"></i><h2>Chưa tìm thấy cơ sở phù hợp</h2><p>Thử đổi từ khóa hoặc bỏ bớt điều kiện lọc.</p></div>
          <?php endif; ?>
        </div>
        <nav class="pagination" id="facilityPagination" aria-label="Phân trang danh sách cơ sở"></nav>
      </div>
      <aside class="directory-aside">
        <section class="aside-card"><h2><i class="ph ph-lightbulb"></i>Chọn cơ sở dễ hơn</h2><p>Đừng chỉ nhìn vào điểm số. Hãy đối chiếu thông tin phù hợp với nhu cầu điều trị của bạn.</p><ul class="guide-list"><li><i class="ph ph-check-circle"></i><span>Xem dịch vụ nổi bật và bảng giá được công bố.</span></li><li><i class="ph ph-check-circle"></i><span>Đọc nhiều đánh giá có nguồn trước khi quyết định.</span></li><li><i class="ph ph-check-circle"></i><span>Liên hệ trực tiếp để xác nhận lịch hẹn và chi phí.</span></li></ul></section>
        <section class="directory-source"><strong><i class="ph-fill ph-shield-check"></i>Thông tin minh bạch</strong><span>Hồ sơ và đánh giá được cập nhật từ dữ liệu do MedReview quản lý.</span></section>
      </aside>
    </div>
  </section>
</main>
<?php include __DIR__ . '/Tem/footer.php'; ?>
<script>
(() => {
  const form = document.getElementById('facilityDirectoryFilter');
  const list = document.getElementById('facilityList');
  const pager = document.getElementById('facilityPagination');
  const count = document.getElementById('facilityResultCount');
  const loading = document.getElementById('facilityDirectoryLoading');
  const reset = document.getElementById('facilityFilterReset');
  const filterToggle = document.getElementById('facilityFilterToggle');
  const filterOptions = document.getElementById('facilityFilterOptions');
  const filterCount = document.getElementById('facilityFilterCount');
  if (!form || !list || !pager || !count) return;
  const motionReduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const resultsAnchor = document.querySelector('.result-topline') || list;
  const scrollToResults = () => {
    const header = document.querySelector('.medical-header');
    const headerOffset = header ? Math.ceil(header.getBoundingClientRect().height) : 76;
    const top = resultsAnchor.getBoundingClientRect().top + window.scrollY - headerOffset - 16;
    window.scrollTo({ top: Math.max(0, top), behavior: motionReduced ? 'auto' : 'smooth' });
  };
  const revealCards = () => {
    list.classList.remove('is-revealing');
    list.querySelectorAll(':scope > .facility-card').forEach((item, index) => {
      item.style.setProperty('--reveal-index', String(index));
    });
    if (motionReduced || !list.querySelector('.facility-card')) return;
    // Restart only the short card cascade; no layout or network work is added.
    void list.offsetWidth;
    list.classList.add('is-revealing');
  };
  // A missing photo should use the same neutral placeholder as an empty gallery.
  const showMissingPhoto = image => {
    if (!(image instanceof HTMLImageElement) || !image.closest('.facility-media')) return;
    const placeholder = document.createElement('span');
    placeholder.className = 'facility-media-empty';
    placeholder.innerHTML = '<i class="ph ph-hospital" aria-hidden="true"></i>';
    image.replaceWith(placeholder);
  };
  list.addEventListener('error', event => showMissingPhoto(event.target), true);
  list.querySelectorAll('.facility-media img').forEach(image => {
    if (image.complete && image.naturalWidth === 0) showMissingPhoto(image);
  });
  const prepareServiceTags = scope => {
    scope.querySelectorAll('[data-service-tags]').forEach(group => {
      if (group.querySelector('[data-service-tags-more]')) group.classList.add('is-collapsible');
    });
  };
  const setServiceTagsExpanded = (group, expanded) => {
    const button = group.querySelector('[data-service-tags-more]');
    if (!button) return;
    group.classList.toggle('is-expanded', expanded);
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    const extraCount = Number(button.dataset.extraCount || 0);
    button.innerHTML = expanded
      ? 'Thu gọn<i class="ph ph-caret-down" aria-hidden="true"></i>'
      : `+${extraCount}<i class="ph ph-caret-down" aria-hidden="true"></i>`;
  };
  prepareServiceTags(list);
  revealCards();
  list.addEventListener('click', event => {
    const button = event.target.closest('[data-service-tags-more]');
    if (!button) return;
    const group = button.closest('[data-service-tags]');
    if (!group) return;
    event.preventDefault();
    setServiceTagsExpanded(group, !group.classList.contains('is-expanded'));
  });
  const endpoint = '/api/medical/facilities.php';
  let currentPage = <?php echo (int) $initial['page']; ?>;
  let requestId = 0;
  // Filter selects are visually placed below the result source line, but stay
  // associated with the search form through the HTML `form` attribute.
  const selectFilters = [...document.querySelectorAll('[data-facility-filter-control]')];
  const setFilterPanel = open => {
    if (!filterToggle || !filterOptions) return;
    filterOptions.hidden = !open;
    filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  };
  const syncFilterCount = () => {
    const active = selectFilters.filter(select => select.name !== 'sort' && select.value !== '').length;
    if (filterCount) { filterCount.hidden = active === 0; filterCount.textContent = String(active); }
  };
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
  const format = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const card = item => {
    const detail = '/co-so-y-te/' + encodeURIComponent(item.slug || '');
    const image = String(item.image || '').trim();
    const label = String(item.images_label || '').trim() || (Number(item.image_count || 0) > 0 ? `${format(item.image_count)} ảnh` : '');
    const services = Array.isArray(item.services) ? item.services : [];
    const serviceMarkup = services.length ? `<div class="service-tags" data-service-tags>${services.map((service, index) => `<span class="service-tag${index > 0 ? ' is-extra' : ''}">${esc(service)}</span>`).join('')}${services.length > 1 ? `<button class="service-tags-more" type="button" data-service-tags-more data-extra-count="${services.length - 1}" aria-expanded="false">+${services.length - 1}<i class="ph ph-caret-down" aria-hidden="true"></i></button>` : ''}</div>` : '';
    const rating = Number(item.rating || 0);
    const verified = item.verified ? '<span class="verified-badge" title="Hồ sơ đã xác thực"><i class="ph-fill ph-seal-check"></i><span>Đã xác thực</span></span>' : '';
    const media = image ? `<img src="${esc(image)}" alt="${esc(item.name)}" loading="lazy">` : '<span class="facility-media-empty"><i class="ph ph-hospital"></i></span>';
    const ratingContent = rating > 0 ? `<div class="score-number">${rating.toFixed(1)}<small>/5</small></div><div class="score-stars" aria-label="${rating.toFixed(1)} trên 5">★★★★★</div><span>${format(item.reviews_count)} đánh giá</span>` : '<div class="score-number score-pending">—</div><span>Chưa có đánh giá</span>';
    return `<article class="facility-card"><a class="facility-media" href="${detail}" aria-label="Xem ${esc(item.name)}">${media}${label ? `<span class="media-label"><i class="ph ph-images"></i>${esc(label)}</span>` : ''}</a><div class="facility-body"><div class="facility-eyebrow"><span>${esc(item.category || 'Cơ sở y tế')}</span>${item.city ? `<span class="eyebrow-dot">•</span><span>${esc(item.city)}</span>` : ''}</div><div class="facility-name-row"><h2><a href="${detail}">${esc(item.name)}</a></h2>${verified}</div>${item.subtitle ? `<p class="facility-subtitle">${esc(item.subtitle)}</p>` : ''}<div class="facility-details">${item.address ? `<span><i class="ph ph-map-pin"></i>${esc(item.address)}</span>` : ''}${item.hours ? `<span><i class="ph ph-clock"></i>${esc(item.hours)}</span>` : ''}</div>${serviceMarkup}</div><div class="facility-score">${ratingContent}</div><div class="facility-price"><span>Giá tham khảo</span><strong>${esc(item.price || 'Liên hệ cập nhật')}</strong></div><a class="detail-button" href="${detail}">Xem hồ sơ<i class="ph ph-arrow-up-right"></i></a></article>`;
  };
  const empty = () => '<div class="empty-state"><i class="ph ph-magnifying-glass"></i><h2>Chưa tìm thấy cơ sở phù hợp</h2><p>Thử đổi từ khóa hoặc bỏ bớt điều kiện lọc.</p></div>';
  const pageButton = (label, page, active = false, disabled = false, aria = '') => `<button type="button" data-page="${page}"${active ? ' class="is-current"' : ''}${disabled ? ' disabled' : ''}${aria ? ` aria-label="${aria}"` : ''}>${label}</button>`;
  const renderPager = paging => {
    const total = Number(paging.total_pages || 1), page = Number(paging.page || 1);
    if (total <= 1) { pager.innerHTML = ''; return; }
    const items = [pageButton('‹', page - 1, false, page <= 1, 'Trang trước')];
    const from = Math.max(1, page - 2), to = Math.min(total, page + 2);
    if (from > 1) { items.push(pageButton('1', 1)); if (from > 2) items.push('<span class="pagination-gap">…</span>'); }
    for (let item = from; item <= to; item++) items.push(pageButton(String(item), item, item === page));
    if (to < total) { if (to < total - 1) items.push('<span class="pagination-gap">…</span>'); items.push(pageButton(String(total), total)); }
    items.push(pageButton('›', page + 1, false, page >= total, 'Trang sau'));
    pager.innerHTML = items.join('');
  };
  const parameters = page => {
    const params = new URLSearchParams(new FormData(form));
    // Keep this explicit for older browsers that omit externally-associated
    // controls from FormData(form).
    selectFilters.forEach(select => params.set(select.name, select.value));
    params.set('page', String(page));
    params.set('limit', '12');
    return params;
  };
  async function load(page = 1, updateUrl = true) {
    const id = ++requestId;
    loading?.classList.add('is-visible');
    list.setAttribute('aria-busy', 'true');
    try {
      const params = parameters(page);
      const response = await fetch(`${endpoint}?${params.toString()}`, {headers:{Accept:'application/json'}});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tải dữ liệu.');
      if (id !== requestId) return;
      const paging = data.paging || {};
      currentPage = Number(paging.page || 1);
      list.innerHTML = (data.items || []).length ? data.items.map(card).join('') : empty();
      prepareServiceTags(list);
      revealCards();
      count.innerHTML = `<strong>${format(paging.total || 0)}</strong> cơ sở phù hợp`;
      renderPager(paging);
      if (updateUrl) {
        const url = new URL(window.location.href);
        url.search = '';
        for (const [key, value] of parameters(currentPage)) if (value && key !== 'limit') url.searchParams.set(key, value);
        history.replaceState({}, '', url);
      }
    } catch (error) {
      if (id !== requestId) return;
      list.innerHTML = '<div class="empty-state"><i class="ph ph-warning-circle"></i><h2>Không thể tải danh sách</h2><p>Vui lòng thử lại sau ít phút.</p></div>';
      revealCards();
      pager.innerHTML = '';
    } finally {
      if (id === requestId) { loading?.classList.remove('is-visible'); list.removeAttribute('aria-busy'); }
    }
  }
  form.addEventListener('submit', event => {
    event.preventDefault();
    // A deliberate search should reveal its refreshed list immediately,
    // while typing and changing a filter remain non-disruptive.
    void load(1).then(scrollToResults);
  });
  filterToggle?.addEventListener('click', () => setFilterPanel(filterOptions?.hidden));
  selectFilters.forEach(select => select.addEventListener('change', () => { syncFilterCount(); load(1); }));
  let searchTimer;
  form.querySelector('[name="q"]').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => load(1), 380); });
  reset.addEventListener('click', () => { form.reset(); syncFilterCount(); setFilterPanel(false); load(1); });
  pager.addEventListener('click', event => { const button = event.target.closest('button[data-page]'); if (!button || button.disabled) return; load(Number(button.dataset.page)); window.scrollTo({top: form.getBoundingClientRect().top + window.scrollY - 92, behavior:'smooth'}); });
  syncFilterCount();
  renderPager({page: currentPage, total_pages: <?php echo (int) $initial['total_pages']; ?>});
})();
</script>
</body>
</html>
