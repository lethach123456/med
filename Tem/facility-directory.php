<?php
$e = 'facility_directory_escape';
$directoryPath = site_localized_path(medical_public_facility_path(), $locale);
$filterFields = [
    ['city', 'filterCity', $isEnglish ? 'Location' : 'Khu vực', $isEnglish ? 'All locations' : 'Tất cả khu vực', $cities, 'map-pin'],
    ['category', 'filterCategory', $isEnglish ? 'Facility type' : 'Nhóm cơ sở', $isEnglish ? 'All types' : 'Tất cả nhóm cơ sở', $categories, 'buildings'],
    ['service', 'filterService', $isEnglish ? 'Service' : 'Dịch vụ', $isEnglish ? 'All services' : 'Tất cả dịch vụ', $services, 'stethoscope'],
];
?>
<main class="fd-directory site-typo" data-locale="<?= $e($locale) ?>">
  <div class="fd-container">
    <nav class="fd-breadcrumb" aria-label="<?= $isEnglish ? 'Breadcrumb' : 'Đường dẫn' ?>"><a href="<?= $e(site_localized_path('/', $locale)) ?>"><?= $isEnglish ? 'Home' : 'Trang chủ' ?></a><i class="ph ph-caret-right" aria-hidden="true"></i><span aria-current="page"><?= $isEnglish ? 'Healthcare facilities' : 'Cơ sở y tế' ?></span></nav>
    <header class="fd-hero">
      <div class="fd-hero-art" aria-hidden="true"><i class="ph ph-first-aid"></i><span></span></div>
      <div class="fd-hero-copy">
        <span class="fd-kicker"><span aria-hidden="true"></span><?= $isEnglish ? 'YOUR HEALTHCARE DIRECTORY' : 'KHÁM PHÁ CƠ SỞ Y TẾ' ?></span>
        <h1><?= $isEnglish ? 'Find the right care.<br><em>Choose with confidence.</em>' : 'Tìm cơ sở phù hợp.<br><em>An tâm lựa chọn.</em>' ?></h1>
        <p><?= $isEnglish ? 'Compare profiles, services and reviews. Get to know your options before making a choice.' : 'Đối chiếu hồ sơ, dịch vụ và đánh giá. Hiểu rõ hơn trước khi chọn nơi chăm sóc sức khỏe.' ?></p>
      </div>
      <div class="fd-hero-note">
        <span class="fd-note-accent" aria-hidden="true"><i class="ph ph-map-pin-line"></i></span>
        <div class="fd-note-icon" aria-hidden="true"><i class="ph ph-heartbeat"></i></div>
        <p><?= $isEnglish ? 'A clearer view.<br>A more informed choice.' : 'Thêm thông tin.<br><strong>Thêm an tâm.</strong>' ?></p>
        <div class="fd-stats"><div><strong><?= facility_directory_number($directoryStats['facilities'], $locale) ?></strong><span><?= $isEnglish ? 'facility profiles' : 'hồ sơ cơ sở' ?></span></div><div><strong><?= facility_directory_number($directoryStats['reviews'], $locale) ?></strong><span><?= $isEnglish ? 'reviews' : 'đánh giá' ?></span></div></div>
      </div>
    </header>
    <section class="fd-search-area" aria-label="<?= $isEnglish ? 'Search facilities' : 'Tìm cơ sở y tế' ?>">
      <form class="fd-search" id="facilityDirectoryFilter" method="get" action="<?= $e($directoryPath) ?>" role="search">
        <label class="fd-search-label" for="facilitySearch"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><span class="fd-sr-only"><?= $isEnglish ? 'Search healthcare facilities' : 'Tìm cơ sở y tế' ?></span></label>
        <input type="search" id="facilitySearch" name="q" value="<?= $e($filters['q']) ?>" maxlength="120" autocomplete="off" enterkeyhint="search" placeholder="<?= $isEnglish ? 'Facility, service or city…' : 'Tên cơ sở, dịch vụ, thành phố…' ?>">
        <button type="submit" aria-label="<?= $isEnglish ? 'Search' : 'Tìm kiếm' ?>"><i class="ph ph-paper-plane-tilt" aria-hidden="true"></i></button>
      </form>
      <?php if ($categories !== []): ?><div class="fd-quick-links" aria-label="<?= $isEnglish ? 'Browse by facility type' : 'Tìm theo nhóm cơ sở' ?>"><span><?= $isEnglish ? 'Explore:' : 'Khám phá:' ?></span><a href="<?= $e(facility_directory_page_url($directoryPath, array_replace($filters, ['category' => '']), 1)) ?>" data-category=""<?= $filters['category'] === '' ? ' aria-current="true"' : '' ?>><i class="ph ph-squares-four" aria-hidden="true"></i><?= $isEnglish ? 'All' : 'Tất cả' ?></a><?php foreach (array_slice($categories, 0, 3) as $category): ?><?php $categoryIcon = preg_match('/nha khoa|dental|dentistry/iu', $category) ? 'tooth' : (preg_match('/spa|thẩm mỹ|beauty/iu', $category) ? 'sparkle' : 'hospital'); ?><a href="<?= $e(facility_directory_page_url($directoryPath, array_replace($filters, ['category' => $category]), 1)) ?>" data-category="<?= $e($category) ?>"<?= $filters['category'] === $category ? ' aria-current="true"' : '' ?>><i class="ph ph-<?= $categoryIcon ?>" aria-hidden="true"></i><?= $e($category) ?></a><?php endforeach; ?></div><?php endif; ?>
    </section>
    <div class="fd-layout">
      <aside class="fd-sidebar">
        <section class="fd-explore" aria-labelledby="fdExploreHeading">
          <div class="fd-explore-art" aria-hidden="true"><span class="fd-explore-orbit"></span><span class="fd-explore-building"><i class="ph ph-hospital"></i></span><span class="fd-explore-heart"><i class="ph-fill ph-heart"></i></span><span class="fd-explore-pin"><i class="ph ph-map-pin"></i></span></div>
          <span class="fd-explore-kicker"><?= $isEnglish ? 'A PLACE TO START' : 'BẮT ĐẦU TỪ ĐÂY' ?></span>
          <h2 id="fdExploreHeading"><?= $isEnglish ? 'Explore care<br>around you.' : 'Khám phá<br>nơi bạn quan tâm.' ?></h2>
          <p><?= $isEnglish ? 'Choose an area. Get to know your options.' : 'Chọn khu vực, tìm hiểu những nơi phù hợp với bạn.' ?></p>
          <?php if ($cities !== []): ?><div class="fd-city-links" aria-label="<?= $isEnglish ? 'Browse by location' : 'Khám phá theo khu vực' ?>"><?php foreach (array_slice($cities, 0, 3) as $city): ?><a href="<?= $e(facility_directory_page_url($directoryPath, array_replace($filters, ['city' => $city]), 1)) ?>" data-city="<?= $e($city) ?>"<?= $filters['city'] === $city ? ' aria-current="true"' : '' ?>><i class="ph ph-map-pin" aria-hidden="true"></i><span><strong><?= $e($city) ?></strong><small><?= facility_directory_number((int) ($cityCounts[$city] ?? 0), $locale) ?> <?= $isEnglish ? 'facilities' : 'cơ sở' ?></small></span><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a><?php endforeach; ?></div><?php endif; ?>
          <button class="fd-explore-more" type="button" data-open-filters><?= $isEnglish ? 'Choose another area' : 'Chọn khu vực khác' ?><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
        </section>
        <section class="fd-guide"><span class="fd-guide-icon" aria-hidden="true"><i class="ph ph-lightbulb"></i></span><h2><?= $isEnglish ? 'Before you choose' : 'Một chút rõ ràng,<br>thêm nhiều an tâm.' ?></h2><ul><li><i class="ph ph-identification-card" aria-hidden="true"></i><?= $isEnglish ? 'Get to know the profile' : 'Hiểu rõ hồ sơ cơ sở' ?></li><li><i class="ph ph-chat-circle-text" aria-hidden="true"></i><?= $isEnglish ? 'Read shared experiences' : 'Đọc trải nghiệm thực tế' ?></li><li><i class="ph ph-phone" aria-hidden="true"></i><?= $isEnglish ? 'Confirm services and costs' : 'Xác nhận dịch vụ và chi phí' ?></li></ul><a href="<?= $e(site_localized_path('/ve-chung-toi.php', $locale)) ?>"><?= $isEnglish ? 'How MedReview works' : 'Cách MedReview hoạt động' ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a></section>
      </aside>
      <section class="fd-results" aria-labelledby="fdResultsHeading">
        <div class="fd-results-top" id="fdResultsHeading" tabindex="-1">
          <div><h2><?= $isEnglish ? 'Your options' : 'Cơ sở dành cho bạn' ?></h2><p id="facilityResultCount"><strong><?= facility_directory_number($initial['total'], $locale) ?></strong> <?= $isEnglish ? 'matching facilities' : 'cơ sở phù hợp' ?></p></div>
          <div class="fd-result-tools">
          <div class="fd-sort"><label for="filterSort"><?= $isEnglish ? 'Sort by' : 'Sắp xếp' ?></label><select id="filterSort" name="sort" form="facilityDirectoryFilter" data-facility-filter-control><?php foreach (['recommended' => $isEnglish ? 'Recommended' : 'Phù hợp nhất', 'newest' => $isEnglish ? 'Recently updated' : 'Mới cập nhật', 'rating' => $isEnglish ? 'Highest rated' : 'Điểm cao nhất', 'reviews' => $isEnglish ? 'Most reviewed' : 'Nhiều đánh giá'] as $value => $label): ?><option value="<?= $value ?>"<?= $filters['sort'] === $value ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
          <details class="fd-filter-disclosure" id="facilityFilterDisclosure">
          <summary class="fd-filter-toggle" id="facilityFilterToggle" aria-controls="facilityFilterOptions"><i class="ph ph-sliders-horizontal" aria-hidden="true"></i><span><?= $isEnglish ? 'Filters' : 'Bộ lọc' ?></span><b id="facilityFilterCount" hidden>0</b><i class="ph ph-caret-down fd-toggle-chevron" aria-hidden="true"></i></summary>
        <section class="fd-filter-panel" id="facilityFilterOptions" aria-labelledby="fdFilterHeading">
          <div class="fd-filter-heading"><h2 id="fdFilterHeading"><i class="ph ph-sliders-horizontal" aria-hidden="true"></i><?= $isEnglish ? 'Refine your search' : 'Lọc theo nhu cầu' ?></h2><div><button type="button" id="facilityFilterReset"><?= $isEnglish ? 'Reset' : 'Đặt lại' ?></button><button type="button" class="fd-filter-close" id="facilityFilterClose" aria-label="<?= $isEnglish ? 'Close filters' : 'Đóng bộ lọc' ?>"><i class="ph ph-x" aria-hidden="true"></i></button></div></div>
          <?php foreach ($filterFields as [$field, $id, $label, $all, $options, $icon]): ?>
            <div class="fd-filter-field"><label for="<?= $id ?>"><?= $label ?></label><div class="fd-select-wrap"><i class="ph ph-<?= $icon ?>" aria-hidden="true"></i><select id="<?= $id ?>" name="<?= $field ?>" form="facilityDirectoryFilter" data-facility-filter-control><option value=""><?= $all ?></option><?php if ($filters[$field] !== '' && !in_array($filters[$field], $options, true)): ?><option value="<?= $e($filters[$field]) ?>" selected><?= $e($filters[$field]) ?></option><?php endif; ?><?php foreach ($options as $option): ?><option value="<?= $e($option) ?>"<?= $filters[$field] === $option ? ' selected' : '' ?>><?= $e($option) ?></option><?php endforeach; ?></select><i class="ph ph-caret-down" aria-hidden="true"></i></div></div>
          <?php endforeach; ?>
          <div class="fd-filter-field"><label for="filterRating"><?= $isEnglish ? 'Minimum rating' : 'Điểm đánh giá' ?></label><div class="fd-select-wrap"><i class="ph ph-star" aria-hidden="true"></i><select id="filterRating" name="min_rating" form="facilityDirectoryFilter" data-facility-filter-control><option value=""><?= $isEnglish ? 'Any rating' : 'Mọi mức điểm' ?></option><option value="4"<?= $filters['min_rating'] === '4' ? ' selected' : '' ?>><?= $isEnglish ? '4.0 and above' : 'Từ 4.0 sao' ?></option><option value="4.5"<?= $filters['min_rating'] === '4.5' ? ' selected' : '' ?>><?= $isEnglish ? '4.5 and above' : 'Từ 4.5 sao' ?></option></select><i class="ph ph-caret-down" aria-hidden="true"></i></div></div>
          <button class="fd-apply" type="submit" form="facilityDirectoryFilter"><?= $isEnglish ? 'Show results' : 'Xem kết quả' ?><i class="ph ph-arrow-right" aria-hidden="true"></i></button>
        </section>
          </details>
          </div>
        </div>
        <div class="fd-active-filters" id="facilityActiveFilters" hidden></div>
        <p class="fd-status fd-sr-only" id="facilityDirectoryStatus" role="status" aria-live="polite" aria-atomic="true"></p>
        <div class="fd-list" id="facilityList" aria-busy="false">
          <?php if ($initial['items'] !== []): foreach ($initial['items'] as $item) echo facility_directory_card($item, $locale); else: ?>
            <div class="fd-empty"><span aria-hidden="true"><i class="ph ph-magnifying-glass"></i></span><h3><?= $isEnglish ? 'No matching facilities' : 'Chưa tìm thấy cơ sở phù hợp' ?></h3><p><?= $isEnglish ? 'Try a different keyword or remove some filters.' : 'Thử từ khóa khác hoặc bỏ bớt bộ lọc nhé.' ?></p><a href="<?= $e($directoryPath) ?>" data-clear-filters><?= $isEnglish ? 'View all facilities' : 'Xem tất cả cơ sở' ?></a></div>
          <?php endif; ?>
        </div>
        <nav class="fd-pagination" id="facilityPagination" aria-label="<?= $isEnglish ? 'Facility directory pagination' : 'Phân trang cơ sở y tế' ?>"><?= facility_directory_pagination($directoryPath, $filters, $initial['page'], $initial['total_pages'], $locale) ?></nav>
        <p class="fd-page-note"><?= $isEnglish ? 'Information is for reference. Please confirm details directly with the facility.' : 'Thông tin mang tính tham khảo. Vui lòng xác nhận trực tiếp với cơ sở trước khi sử dụng dịch vụ.' ?></p>
      </section>
    </div>
  </div>
</main>
<script type="application/json" id="facilityDirectoryInitial"><?= json_encode(['page' => $initial['page'], 'total_pages' => $initial['total_pages']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
