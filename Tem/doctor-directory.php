<main class="doctor-directory dd-redesign site-typo" data-locale="<?php echo $escape($locale); ?>" data-page="<?php echo (int) ($initial['paging']['page'] ?? 1); ?>" data-total-pages="<?php echo (int) ($initial['paging']['total_pages'] ?? 1); ?>">
  <section class="doctor-container">
    <nav class="doctor-breadcrumb" aria-label="Breadcrumb"><a href="<?php echo htmlspecialchars(site_localized_path('/', $locale), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $isEnglish ? 'Home' : 'Trang chủ'; ?></a><i class="ph ph-caret-right"></i><strong><?php echo $isEnglish ? 'Doctors' : 'Bác sĩ'; ?></strong></nav>
    <header class="doctor-hero">
      <div class="doctor-hero-copy">
        <span class="doctor-kicker"><i class="ph-fill ph-heartbeat"></i><?php echo $isEnglish ? 'Doctor profiles on MedReview' : 'Hồ sơ bác sĩ trên MedReview'; ?></span>
        <h1><?php echo $isEnglish ? 'The right doctor.<br><em>A little more peace of mind.</em>' : 'Tìm đúng bác sĩ.<br><em>Thêm một chút an tâm.</em>'; ?></h1>
        <p><?php echo $isEnglish ? 'Explore their expertise, practice locations and patient experiences. Understand your options before reaching out.' : 'Tìm hiểu chuyên môn, nơi khám và trải nghiệm được chia sẻ. Hiểu rõ lựa chọn trước khi liên hệ.'; ?></p>
        <form class="doctor-search" id="doctorDirectoryFilter" method="get" action="<?php echo $escape(site_localized_path('/bac-si.php', $locale)); ?>" novalidate>
          <div class="doctor-search-row">
            <label class="doctor-search-field" for="doctorSearch"><i class="ph ph-magnifying-glass"></i><input id="doctorSearch" name="q" value="<?php echo $escape($filters['q']); ?>" autocomplete="off" placeholder="<?php echo $isEnglish ? 'e.g. dermatologist in Hanoi...' : 'Ví dụ: bác sĩ da liễu Hà Nội...'; ?>" aria-label="<?php echo $isEnglish ? 'Search doctors by name, specialty or location' : 'Tìm bác sĩ theo tên, chuyên khoa hoặc khu vực'; ?>"></label>
            <button class="doctor-search-submit" type="submit" aria-label="<?php echo $isEnglish ? 'Search' : 'Tìm kiếm'; ?>" title="<?php echo $isEnglish ? 'Search' : 'Tìm kiếm'; ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 3-6.8 18-3.5-7.7L3 9.8 21 3Z"/><path d="m10.7 13.3 5.5-5.5"/></svg></button>
          </div>
        </form>
      </div>
      <div class="doctor-hero-art" aria-hidden="true">
        <div class="doctor-art-orbit"></div>
        <div class="doctor-art-card">
          <span class="doctor-art-cross"><i class="ph ph-first-aid"></i></span>
          <svg viewBox="0 0 220 240" fill="none"><circle cx="110" cy="83" r="37" fill="#dce9ff"/><path d="M58 215v-46c0-25 21-45 46-45h12c25 0 46 20 46 45v46" fill="#eaf2ff"/><path d="M79 140l31 37 31-37M110 177v37M76 142v33c0 19 17 19 17 0v-8M144 142v31" stroke="#6c96f4" stroke-width="4" stroke-linecap="round"/><circle cx="144" cy="183" r="10" stroke="#2563ff" stroke-width="4"/><path d="M90 81c5 4 13 6 20 6s15-2 20-6M94 102h32" stroke="#6c96f4" stroke-width="4" stroke-linecap="round"/></svg>
          <span class="doctor-art-line"></span><span class="doctor-art-line is-short"></span>
        </div>
        <span class="doctor-art-note"><i class="ph ph-stethoscope"></i><?php echo $isEnglish ? 'Understand their expertise' : 'Hiểu rõ chuyên môn'; ?></span>
        <span class="doctor-art-heart"><i class="ph ph-heart"></i></span>
      </div>
    </header>
    <div class="doctor-stats" aria-label="<?php echo $isEnglish ? 'Doctor directory statistics' : 'Thống kê danh sách bác sĩ'; ?>">
      <div class="doctor-stat"><i class="ph ph-user-circle" aria-hidden="true"></i><strong><?php echo $number($doctorCount); ?></strong><span><?php echo $isEnglish ? 'Doctor profiles' : 'Hồ sơ bác sĩ'; ?></span></div>
      <div class="doctor-stat"><i class="ph ph-map-pin" aria-hidden="true"></i><strong><?php echo $number(count($cityCounts)); ?></strong><span><?php echo $isEnglish ? 'Locations' : 'Tỉnh, thành'; ?></span></div>
      <div class="doctor-stat"><i class="ph ph-chat-circle-text" aria-hidden="true"></i><strong><?php echo $number($reviewCount); ?></strong><span><?php echo $isEnglish ? 'Reviews' : 'Đánh giá'; ?></span></div>
    </div>
    <?php if ($specialties !== []): ?><div class="doctor-explore"><span><?php echo $isEnglish ? 'Explore by specialty' : 'Bạn quan tâm chuyên khoa nào?'; ?></span><div class="doctor-specialty-links"><?php foreach (array_slice($specialties, 0, 5) as $specialty): ?><a href="<?php echo $escape(site_localized_path('/bac-si', $locale) . '?' . http_build_query(['specialty' => $specialty])); ?>" data-doctor-specialty="<?php echo $escape($specialty); ?>"><?php echo $escape($specialty); ?><i class="ph ph-arrow-up-right" aria-hidden="true"></i></a><?php endforeach; ?></div></div><?php endif; ?>

    <div class="doctor-content">
      <section class="doctor-results" aria-label="<?php echo $isEnglish ? 'Doctor directory' : 'Danh sách bác sĩ'; ?>">
        <div class="doctor-result-topline">
          <div class="doctor-result-meta">
            <p class="doctor-result-count" id="doctorResultCount"><strong><?php echo $number((int) ($initial['paging']['total'] ?? 0)); ?></strong> <?php echo $isEnglish ? 'matching doctors' : 'bác sĩ phù hợp'; ?></p>
            <span class="doctor-source"><?php echo $isEnglish ? 'Find out more before you choose' : 'Thêm thông tin trước khi lựa chọn'; ?></span>
          </div>
          <div class="doctor-loading" id="doctorLoading" role="status" aria-live="polite"><i class="ph ph-spinner-gap"></i><?php echo $isEnglish ? 'Updating results' : 'Đang cập nhật danh sách'; ?></div>
          <div class="doctor-filter-panel">
            <button class="doctor-filter-toggle" type="button" id="doctorFilterToggle" aria-expanded="false" aria-controls="doctorFilterOptions"><i class="ph ph-sliders-horizontal"></i><span><?php echo $isEnglish ? 'Filters' : 'Bộ lọc'; ?></span><b id="doctorFilterCount" hidden>0</b></button>
            <div class="doctor-filter-options" id="doctorFilterOptions" hidden>
              <div class="doctor-filter-grid">
                <div class="doctor-filter-field"><label for="doctorCity"><?php echo $isEnglish ? 'Location' : 'Khu vực'; ?></label><div class="doctor-select-wrap"><i class="ph ph-map-pin"></i><select id="doctorCity" name="city" form="doctorDirectoryFilter" data-doctor-filter><option value=""><?php echo $isEnglish ? 'All locations' : 'Tất cả khu vực'; ?></option><?php foreach ($cities as $city): ?><option value="<?php echo $escape($city); ?>"<?php echo $filters['city'] === $city ? ' selected' : ''; ?>><?php echo $escape($city); ?></option><?php endforeach; ?></select></div></div>
                <div class="doctor-filter-field"><label for="doctorSpecialty"><?php echo $isEnglish ? 'Specialty' : 'Chuyên khoa'; ?></label><div class="doctor-select-wrap"><i class="ph ph-stethoscope"></i><select id="doctorSpecialty" name="specialty" form="doctorDirectoryFilter" data-doctor-filter><option value=""><?php echo $isEnglish ? 'All specialties' : 'Tất cả chuyên khoa'; ?></option><?php foreach ($specialties as $specialty): ?><option value="<?php echo $escape($specialty); ?>"<?php echo $filters['specialty'] === $specialty ? ' selected' : ''; ?>><?php echo $escape($specialty); ?></option><?php endforeach; ?></select></div></div>
                <div class="doctor-filter-field"><label for="doctorRating"><?php echo $isEnglish ? 'Rating' : 'Đánh giá'; ?></label><div class="doctor-select-wrap"><i class="ph ph-star"></i><select id="doctorRating" name="min_rating" form="doctorDirectoryFilter" data-doctor-filter><option value=""><?php echo $isEnglish ? 'Any rating' : 'Mọi mức điểm'; ?></option><option value="4"<?php echo $filters['min_rating'] === '4' ? ' selected' : ''; ?>><?php echo $isEnglish ? '4.0 stars and up' : 'Từ 4.0 sao'; ?></option><option value="4.5"<?php echo $filters['min_rating'] === '4.5' ? ' selected' : ''; ?>><?php echo $isEnglish ? '4.5 stars and up' : 'Từ 4.5 sao'; ?></option></select></div></div>
                <div class="doctor-filter-field"><label for="doctorSort"><?php echo $isEnglish ? 'Sort by' : 'Sắp xếp'; ?></label><div class="doctor-select-wrap"><i class="ph ph-arrows-down-up"></i><select id="doctorSort" name="sort" form="doctorDirectoryFilter" data-doctor-filter><option value="recommended"<?php echo $filters['sort'] === 'recommended' ? ' selected' : ''; ?>><?php echo $isEnglish ? 'Recommended' : 'Phù hợp nhất'; ?></option><option value="newest"<?php echo $filters['sort'] === 'newest' ? ' selected' : ''; ?>><?php echo $isEnglish ? 'Recently updated' : 'Mới cập nhật'; ?></option><option value="rating"<?php echo $filters['sort'] === 'rating' ? ' selected' : ''; ?>><?php echo $isEnglish ? 'Highest rated' : 'Điểm cao nhất'; ?></option><option value="reviews"<?php echo $filters['sort'] === 'reviews' ? ' selected' : ''; ?>><?php echo $isEnglish ? 'Most reviewed' : 'Nhiều đánh giá'; ?></option></select></div></div>
                <button class="doctor-filter-reset" type="button" id="doctorFilterReset"><?php echo $isEnglish ? 'Clear filters' : 'Xóa bộ lọc'; ?></button>
              </div>
            </div>
          </div>
        </div>
        <div class="doctor-list" id="doctorList">
          <?php if (($initial['items'] ?? []) !== []): foreach ($initial['items'] as $item) echo doctor_directory_card($item); else: ?><div class="doctor-empty"><i class="ph ph-magnifying-glass"></i><h2><?php echo $isEnglish ? 'No matching doctors found' : 'Chưa tìm thấy bác sĩ phù hợp'; ?></h2><p><?php echo $isEnglish ? 'Try another keyword or remove some filters.' : 'Thử đổi từ khóa hoặc bỏ bớt điều kiện lọc.'; ?></p></div><?php endif; ?>
        </div>
        <nav class="doctor-pagination" id="doctorPagination" aria-label="<?php echo $isEnglish ? 'Doctor directory pagination' : 'Phân trang danh sách bác sĩ'; ?>"></nav>
      </section>
      <aside class="doctor-aside">
        <?php if ($cities !== []): ?><section class="doctor-city-card"><span class="doctor-aside-eyebrow"><?php echo $isEnglish ? 'Near you' : 'Gần bạn hơn'; ?></span><h2><?php echo $isEnglish ? 'Find by location' : 'Tìm theo khu vực'; ?></h2><div class="doctor-city-links"><?php foreach (array_slice($cities, 0, 4) as $city): ?><a href="<?php echo $escape(site_localized_path('/bac-si', $locale) . '?' . http_build_query(['city' => $city])); ?>" data-doctor-city="<?php echo $escape($city); ?>"><i class="ph ph-map-pin" aria-hidden="true"></i><span><?php echo $escape($city); ?></span><small><?php echo $number((int) ($cityCounts[$city] ?? 0)); ?></small></a><?php endforeach; ?></div></section><?php endif; ?>
        <section class="doctor-aside-card"><h2><i class="ph ph-lightbulb"></i><?php echo $isEnglish ? 'Choose the right doctor' : 'Chọn bác sĩ phù hợp'; ?></h2><p><?php echo $isEnglish ? 'Doctor profiles provide useful context before you book an appointment.' : 'Thông tin trên hồ sơ giúp bạn có thêm cơ sở tham khảo trước khi đặt lịch.'; ?></p><ul class="doctor-guide"><li><i class="ph ph-check-circle"></i><span><?php echo $isEnglish ? 'Match the specialty to your healthcare needs.' : 'Đối chiếu chuyên khoa với vấn đề bạn cần tư vấn.'; ?></span></li><li><i class="ph ph-check-circle"></i><span><?php echo $isEnglish ? 'Review workplace and contact information.' : 'Tham khảo nơi công tác và thông tin liên hệ.'; ?></span></li><li><i class="ph ph-check-circle"></i><span><?php echo $isEnglish ? 'Use reviews as a reference, not a substitute for medical advice.' : 'Đọc đánh giá như nguồn tham khảo, không thay thế tư vấn y khoa.'; ?></span></li></ul></section>
        <section class="doctor-source-card"><strong><i class="ph-fill ph-shield-check"></i><?php echo $isEnglish ? 'Transparent information' : 'Thông tin minh bạch'; ?></strong><span><?php echo $isEnglish ? 'Only doctor profiles published on MedReview appear in this directory.' : 'Danh sách chỉ hiển thị hồ sơ bác sĩ đã được công bố trên MedReview.'; ?></span></section>
      </aside>
    </div>
  </section>
</main>
