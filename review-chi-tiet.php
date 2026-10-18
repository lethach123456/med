<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_directory.php';
require_once __DIR__ . '/medical_review_view.php';
if (function_exists('admin_front_session_boot')) admin_front_session_boot();
$GLOBALS['site_page_key'] = 'reviews';
$GLOBALS['site_language_links'] = ['current' => site_page_locale('reviews'), 'vi' => '/review', 'en' => '/en/review'];
$locale = site_page_locale('reviews');
$notFoundLocale = $locale;
site_language_response_header($locale);
$isEnglish = $locale === 'en';
$reviewBasePath = $isEnglish ? '/en/review' : '/review';
$reviewContactPath = front_editor_page_public_path($isEnglish ? 'contact-en' : 'contact');
$slug = mb_substr(review_view_string($_GET['slug'] ?? ''), 0, 191);
try {
    $reviewData = review_view_detail(db(), $slug, $locale);
} catch (Throwable $error) {
    error_log('Public review detail unavailable: ' . get_class($error));
    http_response_code(503);
    $notFoundTitle = $isEnglish ? 'This review could not be loaded' : 'Chưa tải được bài review';
    $notFoundDescription = $isEnglish ? 'Please try again shortly. The published content has not been changed.' : 'Vui lòng thử lại sau ít phút. Nội dung đã công bố vẫn được giữ nguyên.';
    require __DIR__ . '/Tem/public-404.php';
    exit;
}
if ($reviewData['review'] === null) {
    http_response_code(404);
    $notFoundTitle = $isEnglish ? 'Review not found' : 'Không tìm thấy bài review';
    $notFoundDescription = $isEnglish ? 'This review does not exist in this language or is no longer published.' : 'Bài review không tồn tại, đã bị ẩn hoặc không còn được xuất bản.';
    require __DIR__ . '/Tem/public-404.php';
    exit;
}
$review = $reviewData['review'];
$relatedReviews = $reviewData['related'];
$reviewFacility = $reviewData['facility'];
$title = $review['title'] . ' • MedReview';
$description = site_meta_description($review['excerpt'] ?: ($review['story'][0] ?? $review['title']));
$canonicalPath = review_view_detail_url($review['slug'], $locale);
$seoKeywords = '';
$reviewStylesheet = 'review-detail.css';
?><!doctype html>
<html lang="<?= $isEnglish ? 'en' : 'vi' ?>">
<head><?php require __DIR__ . '/Tem/review-head.php'; ?></head>
<body class="review-page">
<a class="rv-skip" href="#main-content"><?= $isEnglish ? 'Skip to review' : 'Đến nội dung review' ?></a>
<?php include __DIR__ . '/Tem/header.php'; ?>
<?php require __DIR__ . '/Tem/review-detail.php'; ?>
<?php include __DIR__ . '/Tem/footer.php'; ?>
</body></html>
