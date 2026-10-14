<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_directory.php';
require_once __DIR__ . '/medical_review_view.php';
if (function_exists('admin_front_session_boot')) admin_front_session_boot();
$locale = site_page_locale('reviews');
$isEnglish = $locale === 'en';
$reviewBasePath = $isEnglish ? '/en/review' : '/review';
$reviewContactPath = front_editor_page_public_path($isEnglish ? 'contact-en' : 'contact');
$reviewFilters = review_view_filters($_GET);
$reviewUnavailable = false;
try {
    $reviewData = review_view_directory(db(), $reviewFilters, review_view_page($_GET), $locale);
} catch (Throwable $error) {
    error_log('Public review directory unavailable: ' . get_class($error));
    http_response_code(503);
    $reviewUnavailable = true;
    $reviewData = ['items' => [], 'summary' => review_view_empty_summary(), 'pagination' => ['total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 8, 'start' => 0, 'end' => 0]];
}
$reviewRows = $reviewData['items'];
$reviewSummary = $reviewData['summary'];
$reviewPagination = $reviewData['pagination'];
$seo = front_editor_page_seo('review', [
    'title' => 'Review & trải nghiệm y tế | MedReview',
    'description' => 'Đọc những chia sẻ về dịch vụ, chi phí và hành trình điều trị. Thêm một góc nhìn trước khi lựa chọn cơ sở y tế.',
    'canonical_path' => '/review',
]);
$title = $isEnglish ? 'Healthcare Reviews & Experiences | MedReview' : (string) $seo['title'];
$description = $isEnglish ? 'Explore shared experiences of healthcare services, costs and treatment journeys. Get another perspective before choosing a facility.' : (string) $seo['description'];
if ($reviewPagination['page'] > 1) $title .= ($isEnglish ? ' — Page ' : ' — Trang ') . $reviewPagination['page'];
$canonicalPath = review_view_url($reviewBasePath, $reviewFilters, $reviewPagination['page']);
$seoKeywords = $isEnglish ? '' : (string) ($seo['keywords'] ?? '');
$reviewStylesheet = 'review-directory.css';
?><!doctype html>
<html lang="<?= $isEnglish ? 'en' : 'vi' ?>">
<head><?php require __DIR__ . '/Tem/review-head.php'; ?></head>
<body class="review-page">
<a class="rv-skip" href="#main-content"><?= $isEnglish ? 'Skip to reviews' : 'Đến nội dung review' ?></a>
<?php include __DIR__ . '/Tem/header.php'; ?>
<?php require __DIR__ . '/Tem/review-directory.php'; ?>
<?php include __DIR__ . '/Tem/footer.php'; ?>
</body></html>
