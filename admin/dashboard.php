<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();

/**
 * Dashboard only reads optional medical tables. A fresh installation can still
 * open this page before Setup has created every newer medical table.
 */
function medreview_dashboard_table_exists(?PDO $pdo, string $table): bool
{
    static $known = [];
    if (!$pdo) {
        return false;
    }
    if (array_key_exists($table, $known)) {
        return $known[$table];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
        );
        $stmt->execute([':table' => $table]);
        $known[$table] = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $known[$table] = false;
    }

    return $known[$table];
}

function medreview_dashboard_count(?PDO $pdo, string $table, string $where = ''): int
{
    $allowed = ['medical_facilities', 'medical_doctors', 'medical_reviews', 'medical_toplists', 'medical_media_jobs'];
    if (!$pdo || !in_array($table, $allowed, true) || !medreview_dashboard_table_exists($pdo, $table)) {
        return 0;
    }

    try {
        $sql = 'SELECT COUNT(*) FROM ' . $table . ($where !== '' ? ' WHERE ' . $where : '');
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function medreview_dashboard_series(?PDO $pdo, string $table): array
{
    if (!$pdo || !medreview_dashboard_table_exists($pdo, $table)) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total
             FROM {$table}
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY month_key
             ORDER BY month_key ASC"
        );
        $stmt->execute();
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) ($row['month_key'] ?? '')] = (int) ($row['total'] ?? 0);
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function medreview_dashboard_ratio(int $value, int $total): int
{
    if ($total < 1) {
        return 0;
    }
    return max(0, min(100, (int) round(($value / $total) * 100)));
}

function medreview_dashboard_datetime(string $value): string
{
    $time = strtotime($value);
    return $time === false ? 'Chưa có dữ liệu' : date('d/m/Y · H:i', $time);
}

function medreview_dashboard_recent(?PDO $pdo): array
{
    if (!$pdo) {
        return [];
    }

    $sources = [
        [
            'table' => 'medical_facilities', 'type' => 'Cơ sở y tế', 'icon' => 'fa-hospital', 'url' => 'medical_facility_edit.php?id=',
            'sql' => "SELECT id, name AS title, CONCAT_WS(' · ', NULLIF(category, ''), NULLIF(city, '')) AS meta, status, updated_at FROM medical_facilities ORDER BY updated_at DESC, id DESC LIMIT 4",
        ],
        [
            'table' => 'medical_doctors', 'type' => 'Bác sĩ', 'icon' => 'fa-user-doctor', 'url' => 'medical_doctor_edit.php?id=',
            'sql' => "SELECT id, name AS title, CONCAT_WS(' · ', NULLIF(specialty_text, ''), NULLIF(city, '')) AS meta, status, updated_at FROM medical_doctors ORDER BY updated_at DESC, id DESC LIMIT 4",
        ],
        [
            'table' => 'medical_reviews', 'type' => 'Đánh giá', 'icon' => 'fa-star', 'url' => 'medical_review_edit.php?id=',
            'sql' => "SELECT id, title, NULLIF(facility_name, '') AS meta, status, updated_at FROM medical_reviews ORDER BY updated_at DESC, id DESC LIMIT 4",
        ],
        [
            'table' => 'medical_toplists', 'type' => 'Toplist', 'icon' => 'fa-ranking-star', 'url' => 'medical_toplist_edit.php?id=',
            'sql' => "SELECT id, title, NULLIF(excerpt, '') AS meta, status, updated_at FROM medical_toplists ORDER BY updated_at DESC, id DESC LIMIT 4",
        ],
    ];

    $items = [];
    foreach ($sources as $source) {
        if (!medreview_dashboard_table_exists($pdo, $source['table'])) {
            continue;
        }
        try {
            foreach ($pdo->query($source['sql'])->fetchAll() as $row) {
                $row['type'] = $source['type'];
                $row['icon'] = $source['icon'];
                $row['url'] = $source['url'] . (int) ($row['id'] ?? 0);
                $items[] = $row;
            }
        } catch (Throwable $e) {
            // Keep the rest of the dashboard usable during a schema migration.
        }
    }

    usort($items, static function (array $left, array $right): int {
        return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
    });
    return array_slice($items, 0, 7);
}

$pdo = null;
try {
    $pdo = db();
} catch (Throwable $e) {
    $pdo = null;
}

$facilityTotal = medreview_dashboard_count($pdo, 'medical_facilities');
$facilityPublished = medreview_dashboard_count($pdo, 'medical_facilities', "status = 'published'");
$facilityWithContent = medreview_dashboard_count($pdo, 'medical_facilities', "status = 'published' AND content IS NOT NULL AND TRIM(content) <> ''");
$facilityWithImages = medreview_dashboard_count($pdo, 'medical_facilities', "status = 'published' AND ((image_url IS NOT NULL AND TRIM(image_url) <> '') OR (gallery_json IS NOT NULL AND TRIM(gallery_json) NOT IN ('', '[]', 'null')))" );
$doctorTotal = medreview_dashboard_count($pdo, 'medical_doctors');
$doctorPublished = medreview_dashboard_count($pdo, 'medical_doctors', "status = 'published'");
$reviewTotal = medreview_dashboard_count($pdo, 'medical_reviews');
$reviewPublished = medreview_dashboard_count($pdo, 'medical_reviews', "status = 'published'");
$reviewVerified = medreview_dashboard_count($pdo, 'medical_reviews', "status = 'published' AND verified = 1");
$toplistTotal = medreview_dashboard_count($pdo, 'medical_toplists');
$toplistPublished = medreview_dashboard_count($pdo, 'medical_toplists', "status = 'published'");
$mediaRemote = medreview_dashboard_count($pdo, 'medical_media_jobs', "status IN ('pending', 'processing', 'failed')");
$mediaDownloaded = medreview_dashboard_count($pdo, 'medical_media_jobs', "status = 'downloaded'");

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-{$i} months"));
}
$facilitySeries = medreview_dashboard_series($pdo, 'medical_facilities');
$reviewSeries = medreview_dashboard_series($pdo, 'medical_reviews');
$toplistSeries = medreview_dashboard_series($pdo, 'medical_toplists');
$activityData = [
    'labels' => array_map(static fn (string $month): string => date('m/Y', strtotime($month . '-01')), $months),
    'facilities' => array_map(static fn (string $month): int => (int) ($facilitySeries[$month] ?? 0), $months),
    'reviews' => array_map(static fn (string $month): int => (int) ($reviewSeries[$month] ?? 0), $months),
    'toplists' => array_map(static fn (string $month): int => (int) ($toplistSeries[$month] ?? 0), $months),
];

try {
    $visitCounts = visits_daily_counts(14);
    $visitData = [
        'labels' => array_map(static fn (string $day): string => date('d/m', strtotime($day)), $visitCounts['labels'] ?? []),
        'series' => array_map('intval', $visitCounts['series'] ?? []),
    ];
} catch (Throwable $e) {
    $visitData = ['labels' => [], 'series' => []];
}

$recentItems = medreview_dashboard_recent($pdo);
$contentCoverage = medreview_dashboard_ratio($facilityWithContent, $facilityPublished);
$imageCoverage = medreview_dashboard_ratio($facilityWithImages, $facilityPublished);
$verifiedCoverage = medreview_dashboard_ratio($reviewVerified, $reviewPublished);

$adminPageTitle = 'MedReview Admin · Tổng quan';
$adminHeaderTitle = 'Trung tâm điều hành MedReview';
$adminHeaderSubtitle = 'Theo dõi dữ liệu y tế, nội dung và các tác vụ vận hành';
$adminActive = 'dashboard';
require __DIR__ . '/_layout_start.php';
?>

<style>
  .medical-dashboard { --dash-ink:#14213d; --dash-muted:#667995; --dash-blue:#2167ef; --dash-border:#e1eafa; }
  .medical-dashboard .dashboard-hero { position:relative; overflow:hidden; border:1px solid rgba(101,150,255,.24); border-radius:1.45rem; padding:clamp(1.25rem,3vw,2rem); background:linear-gradient(135deg,#0b4fd4 0%,#246ef2 47%,#38a4f5 100%); box-shadow:0 20px 48px rgba(25,85,195,.22); color:#fff; }
  .medical-dashboard .dashboard-hero::after { content:''; position:absolute; right:-120px; top:-145px; width:460px; height:460px; border-radius:50%; background:radial-gradient(circle,rgba(255,255,255,.27) 0,rgba(255,255,255,0) 69%); pointer-events:none; }
  .medical-dashboard .dashboard-eyebrow { display:inline-flex; align-items:center; gap:.5rem; border:1px solid rgba(255,255,255,.3); border-radius:99px; padding:.38rem .7rem; color:rgba(255,255,255,.95); background:rgba(5,39,116,.18); font-size:.78rem; font-weight:800; letter-spacing:.035em; text-transform:uppercase; }
  .medical-dashboard .dashboard-hero h1 { font-size:clamp(1.45rem,3vw,2.15rem); letter-spacing:-.045em; margin:.85rem 0 .45rem; font-weight:750; }
  .medical-dashboard .dashboard-hero p { color:rgba(255,255,255,.78); max-width:650px; margin-bottom:0; }
  .medical-dashboard .dashboard-hero .btn-light { color:#1552c7; font-weight:700; box-shadow:0 8px 18px rgba(6,35,103,.14); }
  .medical-dashboard .dashboard-hero .btn-outline-light { font-weight:650; }
  .medical-dashboard .hero-stat { min-height:100%; position:relative; z-index:1; border:1px solid rgba(255,255,255,.25); border-radius:1rem; background:rgba(8,40,113,.17); padding:.9rem 1rem; backdrop-filter:blur(7px); }
  .medical-dashboard .hero-stat strong { display:block; font-size:1.35rem; letter-spacing:-.04em; }
  .medical-dashboard .hero-stat span { color:rgba(255,255,255,.74); display:block; font-size:.78rem; margin-top:.16rem; }
  .medical-dashboard .metric-card,.medical-dashboard .panel-card { height:100%; background:rgba(255,255,255,.92); border:1px solid var(--dash-border); border-radius:1.15rem; box-shadow:0 13px 32px rgba(24,55,104,.055); }
  .medical-dashboard .metric-card { position:relative; overflow:hidden; transition:transform .2s ease,box-shadow .2s ease; }
  .medical-dashboard .metric-card:hover { transform:translateY(-2px); box-shadow:0 18px 35px rgba(24,55,104,.1); }
  .medical-dashboard .metric-card::after { content:''; position:absolute; width:120px; height:120px; border-radius:50%; right:-47px; top:-56px; background:var(--metric-glow,rgba(33,103,239,.09)); }
  .medical-dashboard .metric-icon { width:44px; height:44px; flex:0 0 44px; display:inline-flex; align-items:center; justify-content:center; border-radius:.9rem; color:var(--metric,var(--dash-blue)); background:var(--metric-soft,#edf4ff); font-size:1.08rem; }
  .medical-dashboard .metric-label { color:var(--dash-muted); font-size:.82rem; font-weight:700; }
  .medical-dashboard .metric-value { color:var(--dash-ink); font-size:1.65rem; line-height:1; letter-spacing:-.055em; font-weight:800; margin:.75rem 0 .38rem; }
  .medical-dashboard .metric-meta { color:var(--dash-muted); font-size:.79rem; }.medical-dashboard .metric-meta strong { color:var(--dash-ink); }
  .medical-dashboard .panel-card .card-body { padding:clamp(1.05rem,2vw,1.45rem); }
  .medical-dashboard .section-title { color:var(--dash-ink); font-size:1.02rem; font-weight:780; letter-spacing:-.025em; }.medical-dashboard .section-subtitle { color:var(--dash-muted); font-size:.8rem; }
  .medical-dashboard .health-card { display:flex; align-items:flex-start; gap:.75rem; height:100%; border:1px solid var(--dash-border); border-radius:1rem; padding:.95rem; background:linear-gradient(135deg,#fff,#f8fbff); }
  .medical-dashboard .health-card .health-icon { display:inline-flex; width:36px; height:36px; align-items:center; justify-content:center; flex:0 0 36px; color:var(--health,#2167ef); border-radius:.72rem; background:var(--health-soft,#edf4ff); }
  .medical-dashboard .health-card strong { color:var(--dash-ink); display:block; font-size:1.25rem; line-height:1; letter-spacing:-.04em; }.medical-dashboard .health-card span { color:var(--dash-muted); display:block; font-size:.76rem; line-height:1.35; margin-top:.22rem; }.medical-dashboard .health-card a { color:inherit; text-decoration:none; }.medical-dashboard .health-card a.health-copy:hover { color:#1454ca; }
  .medical-dashboard .coverage-row + .coverage-row { margin-top:1rem; }.medical-dashboard .coverage-heading { color:#354766; font-size:.82rem; font-weight:700; }.medical-dashboard .coverage-value { color:var(--dash-muted); font-size:.76rem; }.medical-dashboard .coverage-progress { height:.45rem; border-radius:99px; background:#edf2fb; overflow:hidden; }.medical-dashboard .coverage-progress > span { display:block; height:100%; border-radius:inherit; background:linear-gradient(90deg,#4c94ff,#1d61e8); }.medical-dashboard .coverage-progress.green > span { background:linear-gradient(90deg,#1bbf8c,#058967); }.medical-dashboard .coverage-progress.amber > span { background:linear-gradient(90deg,#ffc653,#f09210); }
  .medical-dashboard .quick-action { position:relative; display:flex; align-items:center; min-height:92px; gap:.78rem; padding:.9rem; color:#2b3c59; border:1px solid var(--dash-border); border-radius:1rem; text-decoration:none; background:#fff; transition:.18s ease; }.medical-dashboard .quick-action:hover { color:#145ad9; transform:translateY(-2px); border-color:#aecdff; box-shadow:0 12px 24px rgba(33,103,239,.1); }.medical-dashboard .quick-action .quick-icon { width:40px; height:40px; display:inline-flex; align-items:center; justify-content:center; flex:0 0 40px; color:#1e63e8; border-radius:.8rem; background:#edf4ff; }.medical-dashboard .quick-action strong { display:block; font-size:.85rem; }.medical-dashboard .quick-action span { display:block; color:var(--dash-muted); font-size:.73rem; margin-top:.12rem; line-height:1.3; }
  .medical-dashboard .recent-item { display:flex; align-items:center; gap:.72rem; padding:.7rem 0; border-bottom:1px solid #edf1f7; }.medical-dashboard .recent-item:last-child { border-bottom:0; padding-bottom:0; }.medical-dashboard .recent-icon { width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; flex:0 0 36px; border-radius:.75rem; color:#2367e9; background:#edf4ff; }.medical-dashboard .recent-title { color:var(--dash-ink); display:block; overflow:hidden; font-size:.86rem; font-weight:720; text-decoration:none; text-overflow:ellipsis; white-space:nowrap; }.medical-dashboard .recent-title:hover { color:#165bdc; }.medical-dashboard .recent-meta { color:var(--dash-muted); font-size:.73rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.medical-dashboard .recent-date { color:#7d8da7; flex:0 0 auto; font-size:.69rem; text-align:right; }
  .medical-dashboard .status-dot { display:inline-flex; align-items:center; gap:.3rem; color:#70819a; font-size:.7rem; }.medical-dashboard .status-dot::before { content:''; width:.42rem; height:.42rem; border-radius:50%; background:#9aa9bd; }.medical-dashboard .status-dot.published::before { background:#10a66d; box-shadow:0 0 0 3px rgba(16,166,109,.11); }.medical-dashboard .min-w-0 { min-width:0; }.medical-dashboard .trend-chart { min-height:260px; }.medical-dashboard .visits-chart { min-height:210px; }.medical-dashboard .dashboard-note { color:var(--dash-muted); font-size:.73rem; }
  @media (max-width:767.98px) { .medical-dashboard .dashboard-hero { border-radius:1.1rem; }.medical-dashboard .dashboard-hero .btn { flex:1 1 auto; }.medical-dashboard .hero-stat { padding:.8rem; }.medical-dashboard .metric-value { font-size:1.45rem; }.medical-dashboard .recent-date { display:none; }.medical-dashboard .trend-chart { min-height:230px; } }
  @media (prefers-reduced-motion:reduce) { .medical-dashboard .metric-card,.medical-dashboard .quick-action { transition:none; } }
</style>

<div class="medical-dashboard">
  <section class="dashboard-hero mb-3 mb-lg-4">
    <div class="row align-items-center g-4 position-relative" style="z-index:1;">
      <div class="col-12 col-xl-7">
        <div class="dashboard-eyebrow"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i> MedReview · Trung tâm vận hành</div>
        <h1>Dữ liệu y tế rõ ràng, vận hành chủ động.</h1>
        <p>Quản lý hồ sơ cơ sở, bác sĩ, đánh giá và nội dung AI tại một nơi. Các số liệu dưới đây lấy trực tiếp từ dữ liệu hiện có của hệ thống.</p>
        <div class="d-flex flex-wrap gap-2 mt-4"><a class="btn btn-light" href="<?php echo htmlspecialchars(admin_url('medical_facility_edit.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-plus me-2" aria-hidden="true"></i>Thêm cơ sở</a><a class="btn btn-outline-light" href="<?php echo htmlspecialchars(admin_url('medical_media_worker.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-arrows-rotate me-2" aria-hidden="true"></i>Mở Worker ảnh</a></div>
      </div>
      <div class="col-12 col-xl-5"><div class="row g-2"><div class="col-4"><div class="hero-stat"><strong><?php echo number_format($facilityPublished, 0, ',', '.'); ?></strong><span>cơ sở công khai</span></div></div><div class="col-4"><div class="hero-stat"><strong><?php echo number_format($reviewPublished, 0, ',', '.'); ?></strong><span>đánh giá hiển thị</span></div></div><div class="col-4"><div class="hero-stat"><strong><?php echo number_format($toplistPublished, 0, ',', '.'); ?></strong><span>toplist công khai</span></div></div><div class="col-12"><div class="small text-white-50 pt-1"><i class="fa-regular fa-clock me-1" aria-hidden="true"></i>Cập nhật trực tiếp khi mở trang · <?php echo date('d/m/Y H:i'); ?></div></div></div></div>
    </div>
  </section>

  <section class="row g-3 mb-3 mb-lg-4" aria-label="Chỉ số tổng quan MedReview">
    <div class="col-12 col-sm-6 col-xxl-3"><article class="metric-card" style="--metric:#2167ef;--metric-soft:#edf4ff;--metric-glow:rgba(33,103,239,.11);"><div class="card-body p-3 p-lg-4"><div class="d-flex align-items-center justify-content-between gap-2"><span class="metric-label">Cơ sở y tế</span><span class="metric-icon"><i class="fa-solid fa-hospital" aria-hidden="true"></i></span></div><div class="metric-value"><?php echo number_format($facilityTotal, 0, ',', '.'); ?></div><div class="metric-meta"><strong><?php echo number_format($facilityPublished, 0, ',', '.'); ?></strong> hồ sơ đang công khai</div></div></article></div>
    <div class="col-12 col-sm-6 col-xxl-3"><article class="metric-card" style="--metric:#008b67;--metric-soft:#eaf9f4;--metric-glow:rgba(0,139,103,.11);"><div class="card-body p-3 p-lg-4"><div class="d-flex align-items-center justify-content-between gap-2"><span class="metric-label">Bác sĩ</span><span class="metric-icon"><i class="fa-solid fa-user-doctor" aria-hidden="true"></i></span></div><div class="metric-value"><?php echo number_format($doctorTotal, 0, ',', '.'); ?></div><div class="metric-meta"><strong><?php echo number_format($doctorPublished, 0, ',', '.'); ?></strong> hồ sơ đang công khai</div></div></article></div>
    <div class="col-12 col-sm-6 col-xxl-3"><article class="metric-card" style="--metric:#e88b12;--metric-soft:#fff6e6;--metric-glow:rgba(232,139,18,.11);"><div class="card-body p-3 p-lg-4"><div class="d-flex align-items-center justify-content-between gap-2"><span class="metric-label">Đánh giá y tế</span><span class="metric-icon"><i class="fa-solid fa-star" aria-hidden="true"></i></span></div><div class="metric-value"><?php echo number_format($reviewTotal, 0, ',', '.'); ?></div><div class="metric-meta"><strong><?php echo number_format($reviewVerified, 0, ',', '.'); ?></strong> đánh giá đã xác thực</div></div></article></div>
    <div class="col-12 col-sm-6 col-xxl-3"><article class="metric-card" style="--metric:#7a4ee6;--metric-soft:#f2edff;--metric-glow:rgba(122,78,230,.11);"><div class="card-body p-3 p-lg-4"><div class="d-flex align-items-center justify-content-between gap-2"><span class="metric-label">Bài viết Toplist</span><span class="metric-icon"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i></span></div><div class="metric-value"><?php echo number_format($toplistTotal, 0, ',', '.'); ?></div><div class="metric-meta"><strong><?php echo number_format($toplistPublished, 0, ',', '.'); ?></strong> bài đã xuất bản</div></div></article></div>
  </section>

  <section class="panel-card card mb-3 mb-lg-4" aria-labelledby="healthTitle"><div class="card-body"><div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3"><div><h2 class="section-title mb-1" id="healthTitle">Việc cần lưu ý</h2><div class="section-subtitle">Các hồ sơ và tác vụ cần hoàn thiện để dữ liệu hiển thị tốt hơn.</div></div><a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(admin_url('medical_facilities.php'), ENT_QUOTES, 'UTF-8'); ?>">Quản lý hồ sơ<i class="fa-solid fa-arrow-right ms-2" aria-hidden="true"></i></a></div><div class="row g-2 g-lg-3">
    <div class="col-12 col-md-4"><div class="health-card" style="--health:#d67514;--health-soft:#fff4e5;"><span class="health-icon"><i class="fa-solid fa-file-circle-xmark" aria-hidden="true"></i></span><a class="health-copy" href="<?php echo htmlspecialchars(admin_url('medical_facilities.php'), ENT_QUOTES, 'UTF-8'); ?>"><strong><?php echo number_format(max(0, $facilityPublished - $facilityWithContent), 0, ',', '.'); ?></strong><span>cơ sở công khai chưa có bài giới thiệu</span></a></div></div>
    <div class="col-12 col-md-4"><div class="health-card" style="--health:#7757dc;--health-soft:#f3efff;"><span class="health-icon"><i class="fa-solid fa-images" aria-hidden="true"></i></span><a class="health-copy" href="<?php echo htmlspecialchars(admin_url('medical_media_worker.php'), ENT_QUOTES, 'UTF-8'); ?>"><strong><?php echo number_format(max(0, $facilityPublished - $facilityWithImages), 0, ',', '.'); ?></strong><span>cơ sở công khai chưa có ảnh đại diện hoặc gallery</span></a></div></div>
    <div class="col-12 col-md-4"><div class="health-card" style="--health:#1568dd;--health-soft:#edf4ff;"><span class="health-icon"><i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i></span><a class="health-copy" href="<?php echo htmlspecialchars(admin_url('medical_media_worker.php'), ENT_QUOTES, 'UTF-8'); ?>"><strong><?php echo number_format($mediaRemote + $mediaDownloaded, 0, ',', '.'); ?></strong><span>ảnh đang chờ tải về hoặc nén trong Worker</span></a></div></div>
  </div></div></section>

  <section class="row g-3 mb-3 mb-lg-4"><div class="col-12 col-xl-8"><article class="panel-card card"><div class="card-body"><div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3"><div><h2 class="section-title mb-1">Tăng trưởng dữ liệu</h2><div class="section-subtitle">Số hồ sơ và bài đánh giá được tạo mới trong 6 tháng gần đây.</div></div><span class="badge rounded-pill text-bg-primary-subtle text-primary-emphasis px-3 py-2">Dữ liệu thực</span></div><div class="trend-chart"><canvas id="medicalActivityChart" aria-label="Biểu đồ tăng trưởng dữ liệu y tế" role="img"></canvas></div></div></article></div>
    <div class="col-12 col-xl-4"><article class="panel-card card"><div class="card-body"><div class="d-flex align-items-start justify-content-between gap-2 mb-4"><div><h2 class="section-title mb-1">Mức độ hoàn thiện</h2><div class="section-subtitle">Đo theo các hồ sơ đang công khai.</div></div><i class="fa-solid fa-chart-pie text-primary fs-5" aria-hidden="true"></i></div>
      <div class="coverage-row"><div class="d-flex justify-content-between gap-2 mb-2"><span class="coverage-heading">Bài giới thiệu cơ sở</span><span class="coverage-value"><?php echo $contentCoverage; ?>%</span></div><div class="coverage-progress"><span style="width:<?php echo $contentCoverage; ?>%"></span></div></div>
      <div class="coverage-row"><div class="d-flex justify-content-between gap-2 mb-2"><span class="coverage-heading">Ảnh đại diện hoặc gallery</span><span class="coverage-value"><?php echo $imageCoverage; ?>%</span></div><div class="coverage-progress green"><span style="width:<?php echo $imageCoverage; ?>%"></span></div></div>
      <div class="coverage-row"><div class="d-flex justify-content-between gap-2 mb-2"><span class="coverage-heading">Đánh giá đã xác thực</span><span class="coverage-value"><?php echo $verifiedCoverage; ?>%</span></div><div class="coverage-progress amber"><span style="width:<?php echo $verifiedCoverage; ?>%"></span></div></div>
      <div class="border-top mt-4 pt-3 dashboard-note"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Tỷ lệ 0% có thể là chưa có dữ liệu tương ứng, không phải lỗi hệ thống.</div></div></article></div></section>

  <section class="row g-3 mb-3 mb-lg-4"><div class="col-12 col-xl-7"><article class="panel-card card"><div class="card-body"><div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3"><div><h2 class="section-title mb-1">Cập nhật gần đây</h2><div class="section-subtitle">Các bản ghi vừa được chỉnh sửa trong dữ liệu MedReview.</div></div><a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars(admin_url('content.php'), ENT_QUOTES, 'UTF-8'); ?>">Quản lý nội dung</a></div>
    <?php if ($recentItems !== []): ?><div><?php foreach ($recentItems as $item): ?><div class="recent-item"><span class="recent-icon"><i class="fa-solid <?php echo htmlspecialchars((string) ($item['icon'] ?? 'fa-file-lines'), ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span><div class="min-w-0 flex-grow-1"><a class="recent-title" href="<?php echo htmlspecialchars(admin_url((string) ($item['url'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($item['title'] ?? 'Chưa có tiêu đề'), ENT_QUOTES, 'UTF-8'); ?></a><div class="recent-meta"><?php echo htmlspecialchars((string) ($item['type'] ?? 'Dữ liệu y tế'), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($item['meta']) ? ' · ' . htmlspecialchars((string) $item['meta'], ENT_QUOTES, 'UTF-8') : ''; ?></div></div><div class="recent-date"><span class="status-dot <?php echo (($item['status'] ?? '') === 'published') ? 'published' : ''; ?>"><?php echo (($item['status'] ?? '') === 'published') ? 'Công khai' : 'Nháp'; ?></span><div class="mt-1"><?php echo htmlspecialchars(medreview_dashboard_datetime((string) ($item['updated_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div></div></div><?php endforeach; ?></div><?php else: ?><div class="text-secondary small py-4 text-center">Chưa có bản ghi y tế để hiển thị.</div><?php endif; ?>
  </div></article></div>
    <div class="col-12 col-xl-5"><article class="panel-card card"><div class="card-body"><div class="d-flex align-items-start justify-content-between gap-2 mb-3"><div><h2 class="section-title mb-1">Truy cập website</h2><div class="section-subtitle">14 ngày gần đây, nếu tracking đã được bật.</div></div><i class="fa-solid fa-chart-line text-primary fs-5" aria-hidden="true"></i></div><div class="visits-chart"><canvas id="medicalVisitsChart" aria-label="Biểu đồ lượt truy cập website" role="img"></canvas></div></div></article></div></section>

  <section class="panel-card card"><div class="card-body"><div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3"><div><h2 class="section-title mb-1">Thao tác nhanh</h2><div class="section-subtitle">Đi thẳng tới những phần quản trị thường dùng.</div></div></div><div class="row g-2 g-lg-3">
    <div class="col-12 col-md-6 col-xxl-3"><a class="quick-action" href="<?php echo htmlspecialchars(admin_url('medical_facilities.php'), ENT_QUOTES, 'UTF-8'); ?>"><span class="quick-icon"><i class="fa-solid fa-hospital" aria-hidden="true"></i></span><span><strong>Quản lý cơ sở y tế</strong><span>Hồ sơ, nội dung, giá và gallery</span></span><i class="fa-solid fa-arrow-right ms-auto text-primary small" aria-hidden="true"></i></a></div>
    <div class="col-12 col-md-6 col-xxl-3"><a class="quick-action" href="<?php echo htmlspecialchars(admin_url('medical_reviews.php'), ENT_QUOTES, 'UTF-8'); ?>"><span class="quick-icon"><i class="fa-solid fa-star-half-stroke" aria-hidden="true"></i></span><span><strong>Kiểm duyệt đánh giá</strong><span>Điểm số, nguồn và trạng thái review</span></span><i class="fa-solid fa-arrow-right ms-auto text-primary small" aria-hidden="true"></i></a></div>
    <div class="col-12 col-md-6 col-xxl-3"><a class="quick-action" href="<?php echo htmlspecialchars(admin_url('medical_toplists.php'), ENT_QUOTES, 'UTF-8'); ?>"><span class="quick-icon"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i></span><span><strong>Soạn bài Toplist</strong><span>Danh sách cơ sở và bài so sánh</span></span><i class="fa-solid fa-arrow-right ms-auto text-primary small" aria-hidden="true"></i></a></div>
    <div class="col-12 col-md-6 col-xxl-3"><a class="quick-action" href="<?php echo htmlspecialchars(admin_url('medical_ai_prompts.php'), ENT_QUOTES, 'UTF-8'); ?>"><span class="quick-icon"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span><span><strong>Prompt AI &amp; Worker ảnh</strong><span>Tạo nội dung và tối ưu thư viện ảnh</span></span><i class="fa-solid fa-arrow-right ms-auto text-primary small" aria-hidden="true"></i></a></div>
  </div></div></section>
</div>

<script>
  (function () {
    var activity = <?php echo json_encode($activityData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var visits = <?php echo json_encode($visitData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    function chartOptions() { return { responsive:true, maintainAspectRatio:false, interaction:{intersect:false,mode:'index'}, plugins:{legend:{position:'bottom',labels:{boxWidth:9,boxHeight:9,usePointStyle:true,padding:18,font:{size:11,family:'system-ui'}}},tooltip:{padding:10,cornerRadius:9}}, scales:{x:{grid:{display:false},border:{display:false},ticks:{color:'#72829a',font:{size:10}}},y:{beginAtZero:true,grid:{color:'rgba(132,154,190,.14)'},border:{display:false},ticks:{precision:0,color:'#72829a',font:{size:10}}}} }; }
    function renderCharts() {
      if (!window.Chart) return;
      var activityCanvas = document.getElementById('medicalActivityChart'); var visitsCanvas = document.getElementById('medicalVisitsChart');
      if (activityCanvas) new Chart(activityCanvas.getContext('2d'), { type:'bar', data:{ labels:activity.labels, datasets:[{label:'Cơ sở',data:activity.facilities,backgroundColor:'#2d72f4',borderRadius:6,borderSkipped:false,maxBarThickness:30},{label:'Đánh giá',data:activity.reviews,backgroundColor:'#15a87a',borderRadius:6,borderSkipped:false,maxBarThickness:30},{label:'Toplist',data:activity.toplists,backgroundColor:'#9970ea',borderRadius:6,borderSkipped:false,maxBarThickness:30}] }, options:chartOptions() });
      if (visitsCanvas) { var opts = chartOptions(); opts.plugins.legend.display = false; new Chart(visitsCanvas.getContext('2d'), { type:'line', data:{labels:visits.labels,datasets:[{label:'Truy cập',data:visits.series,borderColor:'#2167ef',backgroundColor:'rgba(33,103,239,.12)',pointBackgroundColor:'#2167ef',pointRadius:2.5,pointHoverRadius:4,fill:true,tension:.36,borderWidth:2.4}]}, options:opts }); }
    }
    function loadChart() { if (window.Chart) { renderCharts(); return; } var script = document.createElement('script'); script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js'; script.async = true; script.onload = renderCharts; document.head.appendChild(script); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', loadChart); else loadChart();
  })();
</script>

<?php require __DIR__ . '/_layout_end.php'; ?>
