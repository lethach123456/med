<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_search_cache.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }

$seo = front_editor_page_seo('bac-si', [
    'title' => 'Bác sĩ • MedReview',
    'description' => 'Tìm kiếm bác sĩ theo chuyên khoa, khu vực và đánh giá thực tế trên MedReview.',
    'canonical_path' => '/bac-si.php',
]);
$title = (string) ($seo['title'] ?? 'Bác sĩ • MedReview');
$description = (string) ($seo['description'] ?? '');
$canonicalPath = (string) ($seo['canonical_path'] ?? '/bac-si.php');
$seoKeywords = (string) ($seo['keywords'] ?? '');

$ratingFilter = (string) ($_GET['min_rating'] ?? '');
$sortFilter = (string) ($_GET['sort'] ?? 'recommended');
$filters = [
    'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120, 'UTF-8'),
    'city' => mb_substr(trim((string) ($_GET['city'] ?? '')), 0, 120, 'UTF-8'),
    'specialty' => mb_substr(trim((string) ($_GET['specialty'] ?? '')), 0, 120, 'UTF-8'),
    'min_rating' => in_array($ratingFilter, ['4', '4.5'], true) ? $ratingFilter : '',
    'sort' => in_array($sortFilter, ['recommended', 'newest', 'rating', 'reviews'], true) ? $sortFilter : 'recommended',
];
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$number = static fn(int $value): string => number_format(max(0, $value), 0, ',', '.');

$index = ['doctors' => [], 'cities' => [], 'counts' => []];
$initial = ['items' => [], 'paging' => ['page' => 1, 'total' => 0, 'total_pages' => 1]];
$cities = [];
$specialties = [];
$doctorCount = 0;
$reviewCount = 0;
$averageRating = 0.0;
try {
    // The doctor directory uses the same shared JSON TTL snapshot as search,
    // keeping normal page views and filter requests off MySQL.
    $cache = medical_search_cache_index();
    $index = $cache['index'];
    $result = medical_search_cache_doctor_directory_search(
        $index,
        $filters + ['page' => max(1, (int) ($_GET['page'] ?? 1)), 'limit' => 12]
    );
    $initial = ['items' => $result['items'], 'paging' => $result['paging']];

    $cityCounts = [];
    $specialtySet = [];
    $weightedRating = 0.0;
    foreach ((array) ($index['doctors'] ?? []) as $doctor) {
        if (!is_array($doctor)) continue;
        $doctorCount++;
        $reviews = max(0, (int) ($doctor['reviews_count'] ?? 0));
        $reviewCount += $reviews;
        $weightedRating += (float) ($doctor['rating'] ?? 0) * $reviews;
        $city = trim((string) ($doctor['city'] ?? ''));
        if ($city !== '') $cityCounts[$city] = ($cityCounts[$city] ?? 0) + 1;
        foreach ((array) ($doctor['services'] ?? []) as $specialty) {
            $specialty = trim((string) $specialty);
            if ($specialty !== '') $specialtySet[$specialty] = true;
        }
        $mainSpecialty = trim((string) ($doctor['specialty_text'] ?? ''));
        if ($mainSpecialty !== '') $specialtySet[$mainSpecialty] = true;
    }
    $averageRating = $reviewCount > 0 ? $weightedRating / $reviewCount : 0.0;
    arsort($cityCounts);
    $cities = array_slice(array_keys($cityCounts), 0, 60);
    $specialties = array_keys($specialtySet);
    natcasesort($specialties);
    $specialties = array_slice(array_values($specialties), 0, 60);
} catch (Throwable $e) {
    error_log('doctor directory page failed: ' . $e->getMessage());
}

function doctor_directory_card(array $item): string
{
    $escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $slug = (string) ($item['slug'] ?? '');
    $url = '/bac-si-chi-tiet.php?slug=' . rawurlencode($slug);
    $image = trim((string) ($item['image'] ?? ''));
    $rating = (float) ($item['rating'] ?? 0);
    $services = array_values(array_filter(array_map('strval', (array) ($item['services'] ?? []))));
    ob_start(); ?>
    <article class="doctor-card">
      <a class="doctor-media" href="<?php echo $escape($url); ?>" aria-label="Xem hồ sơ <?php echo $escape($item['name'] ?? 'bác sĩ'); ?>">
        <?php if ($image !== ''): ?><img src="<?php echo $escape($image); ?>" alt="<?php echo $escape($item['name'] ?? ''); ?>" loading="lazy"><?php else: ?><span class="doctor-media-empty"><i class="ph ph-user-circle"></i></span><?php endif; ?>
      </a>
      <div class="doctor-profile">
        <div class="doctor-eyebrow">
          <?php if (!empty($item['specialty_text'])): ?><span><?php echo $escape($item['specialty_text']); ?></span><?php endif; ?>
          <?php if (!empty($item['city'])): ?><i>·</i><span><?php echo $escape($item['city']); ?></span><?php endif; ?>
        </div>
        <div class="doctor-name-row">
          <h2><a href="<?php echo $escape($url); ?>"><?php echo $escape($item['name'] ?? ''); ?></a></h2>
          <?php if (!empty($item['verified'])): ?><span class="doctor-verified" title="Hồ sơ đã xác thực"><i class="ph-fill ph-seal-check"></i><span>Đã xác thực</span></span><?php endif; ?>
        </div>
        <?php if (!empty($item['title_text'])): ?><p class="doctor-subtitle"><?php echo $escape($item['title_text']); ?></p><?php endif; ?>
        <div class="doctor-details">
          <?php if (!empty($item['facility_name'])): ?><span><i class="ph ph-hospital"></i><?php echo $escape($item['facility_name']); ?></span><?php endif; ?>
          <?php if (!empty($item['hours'])): ?><span><i class="ph ph-clock"></i><?php echo $escape($item['hours']); ?></span><?php endif; ?>
        </div>
        <?php if ($services !== []): ?><div class="doctor-tags" data-doctor-tags><?php foreach ($services as $index => $service): ?><span class="doctor-tag<?php echo $index > 2 ? ' is-extra' : ''; ?>"><?php echo $escape($service); ?></span><?php endforeach; ?><?php if (count($services) > 3): ?><button type="button" class="doctor-tags-more" data-doctor-tags-more data-extra-count="<?php echo count($services) - 3; ?>" aria-expanded="false">+<?php echo count($services) - 3; ?></button><?php endif; ?></div><?php endif; ?>
      </div>
      <div class="doctor-score">
        <?php if ($rating > 0): ?><strong><?php echo number_format($rating, 1); ?><small>/5</small></strong><span class="doctor-stars" aria-label="<?php echo number_format($rating, 1); ?> trên 5">★★★★★</span><span><?php echo number_format((int) ($item['reviews_count'] ?? 0), 0, ',', '.'); ?> đánh giá</span><?php else: ?><strong class="score-empty">—</strong><span>Chưa có đánh giá</span><?php endif; ?>
      </div>
      <div class="doctor-price"><span>Chi phí khám</span><strong><?php echo !empty($item['price']) ? $escape($item['price']) : 'Liên hệ cập nhật'; ?></strong></div>
      <a class="doctor-action" href="<?php echo $escape($url); ?>">Xem hồ sơ<i class="ph ph-arrow-up-right"></i></a>
    </article>
    <?php return trim((string) ob_get_clean());
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $escape($title); ?></title>
  <meta name="description" content="<?php echo $escape($description); ?>">
  <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo $escape($seoKeywords); ?>"><?php endif; ?>
  <link rel="canonical" href="<?php echo $escape($canonicalPath); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
  <style>
    :root{--doctor-ink:#10203a;--doctor-muted:#64748b;--doctor-line:#e2eaf5;--doctor-blue:#2563eb;--doctor-gold:#f59e0b;--doctor-green:#159947;--doctor-max:1320px}
    *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:linear-gradient(180deg,#f8fbff 0,#f4f7fc 58%,#fff 100%);color:var(--doctor-ink);font-family:"Inter",system-ui,-apple-system,Segoe UI,sans-serif}a{color:inherit;text-decoration:none}
    .doctor-directory{padding:22px 0 68px}.doctor-container{width:min(calc(100% - 36px),var(--doctor-max));margin:0 auto;min-width:0}.doctor-breadcrumb{display:flex;align-items:center;gap:8px;margin:0 0 16px;color:#8ba0bd;font-size:12px;font-weight:700}.doctor-breadcrumb a{color:inherit}.doctor-breadcrumb strong{color:var(--doctor-blue)}
    .doctor-hero{position:relative;display:grid;grid-template-columns:minmax(0,1.2fr) minmax(260px,.8fr);align-items:center;gap:36px;overflow:hidden;padding:30px 34px;border:1px solid #dce8f8;border-radius:25px;background:linear-gradient(115deg,#fff 0%,#f7fbff 56%,#edf5ff 100%);box-shadow:0 18px 44px rgba(30,64,175,.055)}.doctor-hero:after{position:absolute;top:-150px;right:-70px;width:270px;height:270px;border-radius:50%;background:radial-gradient(circle,rgba(59,130,246,.14),transparent 69%);content:"";pointer-events:none}.doctor-hero-copy,.doctor-stats{position:relative;z-index:1;min-width:0}.doctor-kicker{display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:999px;background:#eaf3ff;color:#1d5fce;font-size:10px;font-weight:800;letter-spacing:.025em;text-transform:uppercase}.doctor-kicker i{font-size:15px}.doctor-hero h1{max-width:680px;margin:12px 0 8px;font-size:clamp(27px,3vw,38px);line-height:1.16;letter-spacing:-.045em}.doctor-hero p{max-width:640px;margin:0;color:#61738f;font-size:13px;line-height:1.7}
    .doctor-search{margin-top:18px;padding:7px;border:1px solid #d6e4f8;border-radius:16px;background:rgba(255,255,255,.94);box-shadow:0 12px 28px rgba(30,64,175,.08)}.doctor-search-row{display:flex;gap:8px}.doctor-search-field{display:flex;min-width:0;flex:1;align-items:center;gap:10px;height:48px;padding:0 13px;border:1px solid #dce6f3;border-radius:12px;background:#fff;transition:border-color .18s,box-shadow .18s}.doctor-search-field:focus-within{border-color:#83aff5;box-shadow:0 0 0 3px rgba(37,99,235,.09)}.doctor-search-field i{color:#4380e7;font-size:19px}.doctor-search-field input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:var(--doctor-ink);font:600 13px "Inter",sans-serif}.doctor-search-field input::placeholder{color:#94a3b8;font-weight:500}.doctor-search-submit{display:grid;width:48px;height:48px;flex:0 0 auto;place-items:center;border:0;border-radius:12px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;font-size:19px;cursor:pointer;box-shadow:0 9px 20px rgba(37,99,235,.2)}
    .doctor-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.doctor-stat{padding:15px;border:1px solid #dce8f8;border-radius:16px;background:rgba(255,255,255,.82);box-shadow:0 8px 22px rgba(30,64,175,.04)}.doctor-stat strong{display:block;color:#1d5fce;font-size:22px;line-height:1.1;letter-spacing:-.04em}.doctor-stat span{display:block;margin-top:5px;color:#6b7d98;font-size:10px;font-weight:700}
    .doctor-content{display:grid;grid-template-columns:minmax(0,1fr) 258px;gap:18px;margin-top:20px;align-items:start}.doctor-results{min-width:0}.doctor-result-topline{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 2px 12px}.doctor-result-meta{display:flex;align-items:center;flex-wrap:wrap;gap:9px;min-width:0}.doctor-result-count{margin:0;color:#64748b;font-size:12px}.doctor-result-count strong{color:#182b49;font-size:16px}.doctor-source{color:#8aa0bf;font-size:10px;font-weight:700}.doctor-loading{display:none;align-items:center;gap:7px;color:#6681a7;font-size:11px;font-weight:700}.doctor-loading.is-visible{display:flex}.doctor-loading i{animation:doctor-spin .8s linear infinite;font-size:16px}@keyframes doctor-spin{to{transform:rotate(360deg)}}
    .doctor-filter-panel{position:relative;margin-left:auto}.doctor-filter-toggle,.doctor-filter-reset,.doctor-action,.doctor-pagination button{font-family:"Inter",sans-serif;cursor:pointer}.doctor-filter-toggle{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 13px;border:1px solid #d7e3f4;border-radius:12px;background:#f8fbff;color:#466586;font-size:11px;font-weight:800}.doctor-filter-toggle:hover,.doctor-filter-toggle[aria-expanded="true"]{border-color:#9cc0fa;background:#eff6ff;color:var(--doctor-blue)}.doctor-filter-toggle i{font-size:16px}.doctor-filter-toggle b{display:grid;min-width:17px;height:17px;place-items:center;padding:0 4px;border-radius:999px;background:var(--doctor-blue);color:#fff;font-size:9px}.doctor-filter-toggle b[hidden]{display:none}.doctor-filter-options{position:absolute;top:calc(100% + 8px);right:0;z-index:5;width:min(780px,calc(100vw - 40px));padding:12px;border:1px solid var(--doctor-line);border-radius:16px;background:#fff;box-shadow:0 18px 44px rgba(15,23,42,.14)}.doctor-filter-options[hidden]{display:none}.doctor-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) auto;gap:9px;align-items:end}.doctor-filter-field{min-width:0}.doctor-filter-field label{display:block;margin:0 0 6px 2px;color:#71819a;font-size:9px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.doctor-select-wrap{position:relative}.doctor-select-wrap i{position:absolute;top:50%;left:10px;z-index:1;transform:translateY(-50%);color:#6580aa;font-size:15px;pointer-events:none}.doctor-select-wrap select{width:100%;height:39px;padding:0 8px 0 31px;overflow:hidden;border:1px solid #dbe5f3;border-radius:10px;outline:0;background:#fff;color:#34445f;font:600 11px "Inter",sans-serif;text-overflow:ellipsis}.doctor-select-wrap select:focus{border-color:#7caaf7}.doctor-filter-reset{height:39px;padding:0 9px;border:0;border-radius:9px;background:transparent;color:#6380aa;font-size:10px;font-weight:800}.doctor-filter-reset:hover{background:#f3f7fd;color:var(--doctor-blue)}
    .doctor-list{display:grid;grid-template-columns:minmax(0,1fr);gap:11px;transition:opacity .18s,filter .18s}.doctor-list[aria-busy="true"]{opacity:.58;filter:saturate(.8)}.doctor-card{display:grid;grid-template-columns:158px minmax(0,1fr) 98px 142px 108px;gap:14px;align-items:center;min-width:0;padding:10px;border:1px solid var(--doctor-line);border-radius:19px;background:#fff;box-shadow:0 10px 26px rgba(15,23,42,.035);transition:transform .18s,border-color .18s,box-shadow .18s}.doctor-card:hover{transform:translateY(-2px);border-color:#c8daf5;box-shadow:0 16px 34px rgba(30,64,175,.08)}.doctor-media{position:relative;display:grid;height:144px;overflow:hidden;place-items:center;border-radius:14px;background:linear-gradient(140deg,#edf4ff,#e2ebf8);color:#76a0df}.doctor-media img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .25s}.doctor-card:hover .doctor-media img{transform:scale(1.035)}.doctor-media-empty{display:grid;width:100%;height:100%;place-items:center;font-size:46px}.doctor-profile{min-width:0}.doctor-eyebrow{display:flex;gap:6px;overflow:hidden;color:#6d82a0;font-size:9px;font-weight:800;letter-spacing:.035em;text-transform:uppercase;white-space:nowrap}.doctor-eyebrow i{font-style:normal;color:#afc0d9}.doctor-name-row{display:flex;align-items:center;gap:8px;margin-top:5px;min-width:0}.doctor-name-row h2{min-width:0;margin:0;overflow:hidden;font-size:16px;line-height:1.32;letter-spacing:-.025em;text-overflow:ellipsis;white-space:nowrap}.doctor-name-row h2 a:hover{color:var(--doctor-blue)}.doctor-verified{display:inline-flex;flex:0 0 auto;align-items:center;gap:4px;padding:4px 7px;border-radius:999px;background:#edf9f1;color:var(--doctor-green);font-size:9px;font-weight:800;white-space:nowrap}.doctor-verified i{font-size:13px}.doctor-subtitle{display:-webkit-box;overflow:hidden;margin:5px 0 0;color:#61738f;font-size:10.5px;line-height:1.5;-webkit-box-orient:vertical;-webkit-line-clamp:2}.doctor-details{display:grid;gap:4px;margin-top:8px}.doctor-details span{display:flex;gap:5px;overflow:hidden;color:#73849e;font-size:10px;line-height:1.4;text-overflow:ellipsis}.doctor-details i{flex:0 0 auto;color:#6c95d8;font-size:13px}.doctor-tags{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}.doctor-tag{max-width:150px;overflow:hidden;padding:4px 7px;border-radius:6px;background:#f2f6fc;color:#526783;font-size:9px;font-weight:700;text-overflow:ellipsis;white-space:nowrap}.doctor-tags-more{display:none;align-items:center;justify-content:center;height:23px;padding:0 7px;border:1px dashed #aac7f7;border-radius:7px;background:#f8fbff;color:#2563eb;font:800 9px "Inter",sans-serif;cursor:pointer}.doctor-tags.is-collapsible .doctor-tag.is-extra{display:none}.doctor-tags.is-collapsible.is-expanded .doctor-tag.is-extra{display:inline-flex}.doctor-tags.is-collapsible .doctor-tags-more{display:inline-flex}
    .doctor-score,.doctor-price{display:flex;min-width:0;align-self:stretch;flex-direction:column;justify-content:center;padding-left:13px;border-left:1px solid #e5edf8}.doctor-score strong{color:#14233e;font-size:22px;line-height:1;letter-spacing:-.04em}.doctor-score strong small{margin-left:2px;color:#7789a5;font-size:9px;letter-spacing:0}.doctor-score .score-empty{color:#a0afc3}.doctor-stars{margin:5px 0 3px;color:var(--doctor-gold);font-size:12px;letter-spacing:0}.doctor-score>span:last-child{color:#74849a;font-size:9px;line-height:1.4}.doctor-price span{color:#8292a9;font-size:9px;font-weight:800;text-transform:uppercase}.doctor-price strong{display:-webkit-box;overflow:hidden;margin-top:5px;color:#255ab6;font-size:10px;line-height:1.45;-webkit-box-orient:vertical;-webkit-line-clamp:3}.doctor-action{display:inline-flex;align-items:center;justify-content:center;gap:5px;min-width:0;height:37px;padding:0 8px;border:1px solid #cfe0fc;border-radius:11px;background:#f8fbff;color:#2864cf;font-size:10px;font-weight:800;white-space:nowrap}.doctor-action:hover{border-color:#3b82f6;background:var(--doctor-blue);color:#fff}.doctor-action i{font-size:13px}
    .doctor-aside{position:sticky;top:100px;display:grid;gap:12px}.doctor-aside-card{padding:17px;border:1px solid var(--doctor-line);border-radius:18px;background:rgba(255,255,255,.9);box-shadow:0 10px 26px rgba(15,23,42,.035)}.doctor-aside-card h2{display:flex;align-items:center;gap:8px;margin:0 0 10px;color:#213650;font-size:13px}.doctor-aside-card h2 i{color:#3b82f6;font-size:18px}.doctor-aside-card p{margin:0;color:#71819a;font-size:10px;line-height:1.65}.doctor-guide{display:grid;gap:10px;margin:13px 0 0;padding:0;list-style:none}.doctor-guide li{display:flex;gap:8px;color:#536783;font-size:10px;line-height:1.5}.doctor-guide i{color:#2563eb;font-size:14px}.doctor-source-card{padding:13px 15px;border:1px solid #d9e8ff;border-radius:16px;background:linear-gradient(145deg,#f9fcff,#edf5ff)}.doctor-source-card strong{display:block;color:#2459b7;font-size:11px}.doctor-source-card span{display:block;margin-top:5px;color:#6980a4;font-size:9px;line-height:1.55}.doctor-source-card i{margin-right:5px}.doctor-empty{padding:42px 20px;border:1px dashed #cad9ee;border-radius:18px;background:rgba(255,255,255,.7);text-align:center}.doctor-empty>i{display:inline-grid;width:44px;height:44px;place-items:center;border-radius:15px;background:#eaf3ff;color:#3b82f6;font-size:24px}.doctor-empty h2{margin:11px 0 5px;font-size:16px}.doctor-empty p{margin:0;color:#71819a;font-size:11px}.doctor-pagination{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:18px}.doctor-pagination button{min-width:34px;height:34px;padding:0 9px;border:1px solid #d8e4f4;border-radius:9px;background:#fff;color:#527095;font-size:11px;font-weight:800}.doctor-pagination button:hover:not(:disabled),.doctor-pagination button.is-current{border-color:var(--doctor-blue);background:var(--doctor-blue);color:#fff}.doctor-pagination button:disabled{cursor:not-allowed;opacity:.45}.doctor-pagination .pagination-gap{color:#91a4bd;font-size:11px}
    @media(max-width:1180px){.doctor-content{grid-template-columns:minmax(0,1fr)}.doctor-aside{position:static;grid-template-columns:repeat(2,minmax(0,1fr))}.doctor-aside-card:not(:first-child){display:none}.doctor-card{grid-template-columns:128px minmax(0,1fr) 94px 112px}.doctor-price{display:none}.doctor-action{grid-column:4}.doctor-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr)) auto}}
    @media(max-width:900px){.doctor-hero{grid-template-columns:minmax(0,1fr);gap:18px;padding:24px}.doctor-stats{max-width:520px}.doctor-card{grid-template-columns:112px minmax(0,1fr) auto;grid-template-areas:"media profile profile" "score score action";gap:10px 14px;padding:13px}.doctor-media{grid-area:media;height:112px}.doctor-profile{grid-area:profile}.doctor-score{grid-area:score;flex-direction:row;align-items:center;gap:7px;padding:9px 0 0;border-top:1px solid #edf2f8;border-left:0}.doctor-score strong{font-size:19px}.doctor-stars{margin:0}.doctor-score>span:last-child{font-size:11px}.doctor-action{grid-area:action;min-width:104px}.doctor-name-row h2{font-size:16px;white-space:normal;overflow-wrap:anywhere}.doctor-verified{padding:4px}.doctor-verified span{display:none}.doctor-details span{font-size:11px}.doctor-tag{font-size:10px}.doctor-filter-options{width:min(720px,calc(100vw - 36px))}.doctor-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr)) auto}.doctor-result-topline{flex-wrap:wrap}.doctor-source{display:none}}
    @media(max-width:560px){.doctor-container{width:calc(100% - 20px)}.doctor-directory{padding:9px 0 32px}.doctor-breadcrumb{margin-bottom:9px;font-size:10px}.doctor-hero{gap:12px;padding:15px 13px;border-radius:17px}.doctor-kicker{padding:4px 8px;font-size:9px}.doctor-hero h1{margin:7px 0 5px;font-size:22px;line-height:1.22}.doctor-hero p{font-size:11px;line-height:1.5}.doctor-search{margin-top:12px;padding:5px;border-radius:13px}.doctor-search-field{height:42px;padding:0 9px;gap:7px}.doctor-search-field input{font-size:12px}.doctor-search-submit{width:42px;height:42px;border-radius:10px}.doctor-stats{gap:6px}.doctor-stat{padding:9px 10px;border-radius:12px}.doctor-stat strong{font-size:17px}.doctor-stat span{font-size:9px}.doctor-content{gap:10px;margin-top:13px}.doctor-result-topline{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px;align-items:center;margin-bottom:9px}.doctor-result-meta{grid-column:1;grid-row:1;gap:0}.doctor-result-count{overflow:hidden;font-size:11px;white-space:nowrap;text-overflow:ellipsis}.doctor-result-count strong{font-size:14px}.doctor-filter-panel{grid-column:2;grid-row:1}.doctor-filter-toggle{height:35px;padding:0 9px;font-size:10px}.doctor-filter-options{position:absolute;right:0;width:calc(100vw - 20px);margin-top:7px;padding:9px;border-radius:12px}.doctor-filter-grid{grid-template-columns:minmax(0,1fr);gap:8px}.doctor-filter-field label{font-size:9px}.doctor-select-wrap select{height:37px;font-size:12px}.doctor-filter-reset{height:34px;justify-self:start}.doctor-loading{grid-column:1/-1;font-size:10px}.doctor-card{grid-template-columns:78px minmax(0,1fr) auto;grid-template-areas:"media profile profile" "score score action";gap:8px 9px;padding:9px;border-radius:14px}.doctor-media{height:78px;border-radius:10px}.doctor-media-empty{font-size:31px}.doctor-eyebrow{gap:4px;font-size:8px}.doctor-name-row{gap:4px;margin-top:3px}.doctor-name-row h2{font-size:13px;line-height:1.3}.doctor-verified{padding:3px 4px;font-size:8px}.doctor-verified i{font-size:11px}.doctor-subtitle{display:none}.doctor-details{gap:3px;margin-top:5px}.doctor-details span{font-size:9px;line-height:1.35}.doctor-details i{font-size:11px}.doctor-tags{gap:4px;margin-top:5px}.doctor-tag{max-width:110px;padding:3px 5px;font-size:8px}.doctor-tags-more{height:20px;padding:0 5px;font-size:8px}.doctor-score{gap:5px;padding-top:7px}.doctor-score strong{font-size:16px}.doctor-score strong small{font-size:8px}.doctor-stars{font-size:10px}.doctor-score>span:last-child{font-size:9px}.doctor-action{min-width:83px;height:32px;padding:0 7px;border-radius:9px;font-size:9px}.doctor-action i{font-size:11px}.doctor-aside{display:none}.doctor-pagination{gap:4px;margin-top:13px}.doctor-pagination button{min-width:32px;height:32px;padding:0 7px;font-size:10px}.doctor-empty{padding:24px 12px}.doctor-empty h2{font-size:14px}}
    @media(max-width:360px){.doctor-card{grid-template-columns:68px minmax(0,1fr) auto}.doctor-media{height:68px}.doctor-action{min-width:76px;padding:0 5px;font-size:8px}.doctor-tags{display:none}.doctor-hero h1{font-size:20px}}
    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.doctor-card,.doctor-action,.doctor-filter-toggle,.doctor-pagination button{transition:none}.doctor-loading i{animation:none}}
  </style>
</head>
<body>
<?php include __DIR__ . '/Tem/header.php'; ?>
<main class="doctor-directory site-typo">
  <section class="doctor-container">
    <nav class="doctor-breadcrumb" aria-label="Breadcrumb"><a href="/">Trang chủ</a><i class="ph ph-caret-right"></i><strong>Bác sĩ</strong></nav>
    <header class="doctor-hero">
      <div class="doctor-hero-copy">
        <span class="doctor-kicker"><i class="ph-fill ph-heartbeat"></i>Hồ sơ bác sĩ trên MedReview</span>
        <h1>Tìm bác sĩ phù hợp với nhu cầu của bạn</h1>
        <p>Tìm theo tên, chuyên khoa hoặc thành phố; xem thông tin hành nghề, nơi công tác và đánh giá trước khi liên hệ.</p>
        <form class="doctor-search" id="doctorDirectoryFilter" method="get" action="/bac-si.php" novalidate>
          <div class="doctor-search-row">
            <label class="doctor-search-field" for="doctorSearch"><i class="ph ph-magnifying-glass"></i><input id="doctorSearch" name="q" value="<?php echo $escape($filters['q']); ?>" autocomplete="off" placeholder="Ví dụ: bác sĩ da liễu Hà Nội..." aria-label="Tìm bác sĩ theo tên, chuyên khoa hoặc khu vực"></label>
            <button class="doctor-search-submit" type="submit" aria-label="Tìm kiếm"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></button>
          </div>
        </form>
      </div>
      <div class="doctor-stats" aria-label="Thống kê bác sĩ">
        <div class="doctor-stat"><strong><?php echo $number($doctorCount); ?></strong><span>Bác sĩ có hồ sơ</span></div>
        <div class="doctor-stat"><strong><?php echo $number($reviewCount); ?></strong><span>Đánh giá · điểm TB <?php echo number_format($averageRating, 1); ?>/5</span></div>
      </div>
    </header>

    <div class="doctor-content">
      <section class="doctor-results" aria-label="Danh sách bác sĩ">
        <div class="doctor-result-topline">
          <div class="doctor-result-meta">
            <p class="doctor-result-count" id="doctorResultCount"><strong><?php echo $number((int) ($initial['paging']['total'] ?? 0)); ?></strong> bác sĩ phù hợp</p>
            <span class="doctor-source">Hồ sơ được chọn lọc từ MedReview</span>
          </div>
          <div class="doctor-loading" id="doctorLoading"><i class="ph ph-spinner-gap"></i>Đang cập nhật danh sách</div>
          <div class="doctor-filter-panel">
            <button class="doctor-filter-toggle" type="button" id="doctorFilterToggle" aria-expanded="false" aria-controls="doctorFilterOptions"><i class="ph ph-sliders-horizontal"></i><span>Bộ lọc</span><b id="doctorFilterCount" hidden>0</b></button>
            <div class="doctor-filter-options" id="doctorFilterOptions" hidden>
              <div class="doctor-filter-grid">
                <div class="doctor-filter-field"><label for="doctorCity">Khu vực</label><div class="doctor-select-wrap"><i class="ph ph-map-pin"></i><select id="doctorCity" name="city" form="doctorDirectoryFilter" data-doctor-filter><option value="">Tất cả khu vực</option><?php foreach ($cities as $city): ?><option value="<?php echo $escape($city); ?>"<?php echo $filters['city'] === $city ? ' selected' : ''; ?>><?php echo $escape($city); ?></option><?php endforeach; ?></select></div></div>
                <div class="doctor-filter-field"><label for="doctorSpecialty">Chuyên khoa</label><div class="doctor-select-wrap"><i class="ph ph-stethoscope"></i><select id="doctorSpecialty" name="specialty" form="doctorDirectoryFilter" data-doctor-filter><option value="">Tất cả chuyên khoa</option><?php foreach ($specialties as $specialty): ?><option value="<?php echo $escape($specialty); ?>"<?php echo $filters['specialty'] === $specialty ? ' selected' : ''; ?>><?php echo $escape($specialty); ?></option><?php endforeach; ?></select></div></div>
                <div class="doctor-filter-field"><label for="doctorRating">Đánh giá</label><div class="doctor-select-wrap"><i class="ph ph-star"></i><select id="doctorRating" name="min_rating" form="doctorDirectoryFilter" data-doctor-filter><option value="">Mọi mức điểm</option><option value="4"<?php echo $filters['min_rating'] === '4' ? ' selected' : ''; ?>>Từ 4.0 sao</option><option value="4.5"<?php echo $filters['min_rating'] === '4.5' ? ' selected' : ''; ?>>Từ 4.5 sao</option></select></div></div>
                <div class="doctor-filter-field"><label for="doctorSort">Sắp xếp</label><div class="doctor-select-wrap"><i class="ph ph-arrows-down-up"></i><select id="doctorSort" name="sort" form="doctorDirectoryFilter" data-doctor-filter><option value="recommended"<?php echo $filters['sort'] === 'recommended' ? ' selected' : ''; ?>>Phù hợp nhất</option><option value="newest"<?php echo $filters['sort'] === 'newest' ? ' selected' : ''; ?>>Mới cập nhật</option><option value="rating"<?php echo $filters['sort'] === 'rating' ? ' selected' : ''; ?>>Điểm cao nhất</option><option value="reviews"<?php echo $filters['sort'] === 'reviews' ? ' selected' : ''; ?>>Nhiều đánh giá</option></select></div></div>
                <button class="doctor-filter-reset" type="button" id="doctorFilterReset">Xóa bộ lọc</button>
              </div>
            </div>
          </div>
        </div>
        <div class="doctor-list" id="doctorList">
          <?php if (($initial['items'] ?? []) !== []): foreach ($initial['items'] as $item) echo doctor_directory_card($item); else: ?><div class="doctor-empty"><i class="ph ph-magnifying-glass"></i><h2>Chưa tìm thấy bác sĩ phù hợp</h2><p>Thử đổi từ khóa hoặc bỏ bớt điều kiện lọc.</p></div><?php endif; ?>
        </div>
        <nav class="doctor-pagination" id="doctorPagination" aria-label="Phân trang danh sách bác sĩ"></nav>
      </section>
      <aside class="doctor-aside">
        <section class="doctor-aside-card"><h2><i class="ph ph-lightbulb"></i>Chọn bác sĩ phù hợp</h2><p>Thông tin trên hồ sơ giúp bạn có thêm cơ sở tham khảo trước khi đặt lịch.</p><ul class="doctor-guide"><li><i class="ph ph-check-circle"></i><span>Đối chiếu chuyên khoa với vấn đề bạn cần tư vấn.</span></li><li><i class="ph ph-check-circle"></i><span>Tham khảo nơi công tác và thông tin liên hệ.</span></li><li><i class="ph ph-check-circle"></i><span>Đọc đánh giá như nguồn tham khảo, không thay thế tư vấn y khoa.</span></li></ul></section>
        <section class="doctor-source-card"><strong><i class="ph-fill ph-shield-check"></i>Thông tin minh bạch</strong><span>Danh sách chỉ hiển thị hồ sơ bác sĩ đã được công bố trên MedReview.</span></section>
      </aside>
    </div>
  </section>
</main>
<?php include __DIR__ . '/Tem/footer.php'; ?>
<script>
(() => {
  const form = document.getElementById('doctorDirectoryFilter');
  const list = document.getElementById('doctorList');
  const pager = document.getElementById('doctorPagination');
  const count = document.getElementById('doctorResultCount');
  const loading = document.getElementById('doctorLoading');
  const toggle = document.getElementById('doctorFilterToggle');
  const options = document.getElementById('doctorFilterOptions');
  const reset = document.getElementById('doctorFilterReset');
  const filterCount = document.getElementById('doctorFilterCount');
  const selectFilters = [...document.querySelectorAll('[data-doctor-filter]')];
  if (!form || !list || !pager || !count) return;
  const motionReduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const scrollToResults = () => {
    const top = count.getBoundingClientRect().top + window.scrollY - 100;
    window.scrollTo({top: Math.max(0, top), behavior: motionReduced ? 'auto' : 'smooth'});
  };
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
  const format = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0));
  const prepareTags = () => list.querySelectorAll('[data-doctor-tags]').forEach(group => { if (group.querySelector('[data-doctor-tags-more]')) group.classList.add('is-collapsible'); });
  const card = item => {
    const detail = '/bac-si-chi-tiet.php?slug=' + encodeURIComponent(item.slug || '');
    const image = String(item.image || '').trim();
    const services = Array.isArray(item.services) ? item.services : [];
    const tags = services.map((value, index) => `<span class="doctor-tag${index > 2 ? ' is-extra' : ''}">${esc(value)}</span>`).join('');
    const more = services.length > 3 ? `<button type="button" class="doctor-tags-more" data-doctor-tags-more data-extra-count="${services.length - 3}" aria-expanded="false">+${services.length - 3}</button>` : '';
    const verified = item.verified ? '<span class="doctor-verified" title="Hồ sơ đã xác thực"><i class="ph-fill ph-seal-check"></i><span>Đã xác thực</span></span>' : '';
    const media = image ? `<img src="${esc(image)}" alt="${esc(item.name)}" loading="lazy">` : '<span class="doctor-media-empty"><i class="ph ph-user-circle"></i></span>';
    const rating = Number(item.rating || 0);
    const score = rating > 0 ? `<strong>${rating.toFixed(1)}<small>/5</small></strong><span class="doctor-stars" aria-label="${rating.toFixed(1)} trên 5">★★★★★</span><span>${format(item.reviews_count)} đánh giá</span>` : '<strong class="score-empty">—</strong><span>Chưa có đánh giá</span>';
    const details = `${item.facility_name ? `<span><i class="ph ph-hospital"></i>${esc(item.facility_name)}</span>` : ''}${item.hours ? `<span><i class="ph ph-clock"></i>${esc(item.hours)}</span>` : ''}`;
    return `<article class="doctor-card"><a class="doctor-media" href="${detail}" aria-label="Xem hồ sơ ${esc(item.name)}">${media}</a><div class="doctor-profile"><div class="doctor-eyebrow">${item.specialty_text ? `<span>${esc(item.specialty_text)}</span>` : ''}${item.city ? `<i>·</i><span>${esc(item.city)}</span>` : ''}</div><div class="doctor-name-row"><h2><a href="${detail}">${esc(item.name)}</a></h2>${verified}</div>${item.title_text ? `<p class="doctor-subtitle">${esc(item.title_text)}</p>` : ''}<div class="doctor-details">${details}</div>${services.length ? `<div class="doctor-tags" data-doctor-tags>${tags}${more}</div>` : ''}</div><div class="doctor-score">${score}</div><div class="doctor-price"><span>Chi phí khám</span><strong>${esc(item.price || 'Liên hệ cập nhật')}</strong></div><a class="doctor-action" href="${detail}">Xem hồ sơ<i class="ph ph-arrow-up-right"></i></a></article>`;
  };
  const empty = () => '<div class="doctor-empty"><i class="ph ph-magnifying-glass"></i><h2>Chưa tìm thấy bác sĩ phù hợp</h2><p>Thử đổi từ khóa hoặc bỏ bớt điều kiện lọc.</p></div>';
  const pageButton = (label, page, active = false, disabled = false, aria = '') => `<button type="button" data-page="${page}"${active ? ' class="is-current"' : ''}${disabled ? ' disabled' : ''}${aria ? ` aria-label="${aria}"` : ''}>${label}</button>`;
  const renderPager = paging => {
    const total = Number(paging.total_pages || 1), page = Number(paging.page || 1);
    if (total <= 1) { pager.innerHTML = ''; return; }
    const items = [pageButton('‹', page - 1, false, page <= 1, 'Trang trước')];
    const from = Math.max(1, page - 2), to = Math.min(total, page + 2);
    if (from > 1) { items.push(pageButton('1', 1)); if (from > 2) items.push('<span class="pagination-gap">…</span>'); }
    for (let n = from; n <= to; n++) items.push(pageButton(String(n), n, n === page));
    if (to < total) { if (to < total - 1) items.push('<span class="pagination-gap">…</span>'); items.push(pageButton(String(total), total)); }
    items.push(pageButton('›', page + 1, false, page >= total, 'Trang sau'));
    pager.innerHTML = items.join('');
  };
  const parameters = page => {
    const params = new URLSearchParams(new FormData(form));
    selectFilters.forEach(select => params.set(select.name, select.value));
    params.set('page', String(page));
    params.set('limit', '12');
    return params;
  };
  const syncFilterCount = () => {
    const active = selectFilters.filter(select => select.name !== 'sort' && select.value !== '').length;
    filterCount.hidden = active === 0;
    filterCount.textContent = String(active);
  };
  let requestId = 0;
  async function load(page = 1, updateUrl = true) {
    const id = ++requestId;
    loading?.classList.add('is-visible');
    list.setAttribute('aria-busy', 'true');
    try {
      const params = parameters(page);
      const response = await fetch(`/api/medical/doctors.php?${params.toString()}`, {headers:{Accept:'application/json'}});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tải dữ liệu.');
      if (id !== requestId) return;
      const paging = data.paging || {};
      list.innerHTML = (data.items || []).length ? data.items.map(card).join('') : empty();
      prepareTags();
      count.innerHTML = `<strong>${format(paging.total || 0)}</strong> bác sĩ phù hợp`;
      renderPager(paging);
      if (updateUrl) {
        const url = new URL(window.location.href);
        url.search = '';
        for (const [key, value] of parameters(Number(paging.page || 1))) if (value && key !== 'limit') url.searchParams.set(key, value);
        history.replaceState({}, '', url);
      }
    } catch (error) {
      if (id !== requestId) return;
      list.innerHTML = '<div class="doctor-empty"><i class="ph ph-warning-circle"></i><h2>Không thể tải danh sách</h2><p>Vui lòng thử lại sau ít phút.</p></div>';
      pager.innerHTML = '';
    } finally {
      if (id === requestId) { loading?.classList.remove('is-visible'); list.removeAttribute('aria-busy'); }
    }
  }
  prepareTags();
  renderPager({page: <?php echo (int) ($initial['paging']['page'] ?? 1); ?>, total_pages: <?php echo (int) ($initial['paging']['total_pages'] ?? 1); ?>});
  toggle?.addEventListener('click', () => { const open = options.hidden; options.hidden = !open; toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  selectFilters.forEach(select => select.addEventListener('change', () => { syncFilterCount(); load(1); }));
  let searchTimer;
  form.querySelector('[name="q"]')?.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => load(1), 380); });
  form.addEventListener('submit', event => { event.preventDefault(); void load(1).then(scrollToResults); });
  reset?.addEventListener('click', () => {
    form.querySelector('[name="q"]').value = '';
    selectFilters.forEach(select => { select.value = select.name === 'sort' ? 'recommended' : ''; });
    syncFilterCount();
    options.hidden = true;
    toggle?.setAttribute('aria-expanded', 'false');
    load(1);
  });
  list.addEventListener('click', event => {
    const button = event.target.closest('[data-doctor-tags-more]');
    if (!button) return;
    const group = button.closest('[data-doctor-tags]');
    const expanded = group.classList.toggle('is-expanded');
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    button.textContent = expanded ? 'Thu gọn' : `+${button.dataset.extraCount || 0}`;
  });
  pager.addEventListener('click', event => { const button = event.target.closest('button[data-page]'); if (!button || button.disabled) return; load(Number(button.dataset.page)); scrollToResults(); });
  syncFilterCount();
})();
</script>
</body>
</html>
