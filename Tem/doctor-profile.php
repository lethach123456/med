<?php
declare(strict_types=1);
$english = $doctorLanguage === 'en';
$labels = $english ? [
    'home'=>'Home','doctors'=>'Doctors','profile'=>'DOCTOR PROFILE','reference'=>'Public-source information','verified'=>'Profile reviewed',
    'intro'=>'About the doctor','expertise'=>'Clinical expertise','education'=>'Education & qualifications','experience'=>'Professional experience',
    'locations'=>'Practice locations','fees'=>'Schedule & consultation fees','research'=>'Research & professional activities','sources'=>'Sources & updates',
    'degree'=>'Qualifications','since'=>'Practising since','languages'=>'Languages','patients'=>'Patient groups','city'=>'Location',
    'book'=>'Book an appointment','places'=>'View practice locations','call'=>'Call for information','noRating'=>'No ratings yet',
    'ratings'=>'ratings','ratingNote'=>'This is the rating recorded for this doctor, not the facility rating.',
    'updated'=>'Research updated','sourceCount'=>'reference sources','readMore'=>'Read full profile','readLess'=>'Show less',
    'emptyIntro'=>'Detailed information is being updated. Please confirm the doctor’s expertise and current practice information with the provider.',
    'notice'=>'Information is compiled from public sources. Confirm current appointments, fees and services directly with the provider.',
    'insufficient'=>'Some profile information still needs additional evidence. This page does not certify professional licensing or provide individual medical advice.',
    'services'=>'Services & clinical interests','conditions'=>'Conditions covered','license'=>'Published practice licence information',
    'document'=>'Document','number'=>'Licence number','issuer'=>'Issuing organisation','issued'=>'Issue date','scope'=>'Scope of practice',
    'licenseNote'=>'The published details below do not constitute a confirmation of current licence validity.',
    'certificates'=>'Certificates & training','memberships'=>'Professional memberships','publications'=>'Research & publications','awards'=>'Awards',
    'primary'=>'Primary practice','address'=>'Address','phone'=>'Public contact number','website'=>'Website','email'=>'Email',
    'directions'=>'Directions','facility'=>'View facility profile','schedule'=>'Published schedule','price'=>'Reference fees',
    'confirmSchedule'=>'Contact this practice to confirm the doctor’s availability. Facility opening hours are not the doctor’s personal schedule.',
    'confirmFee'=>'Fees may vary by appointment and required services. Confirm the total cost before your visit.',
    'generalSchedule'=>'Doctor’s published schedule','generalFees'=>'Doctor’s reference fees','plan'=>'Plan your visit',
    'planNote'=>'Contact the practice directly to confirm the doctor, appointment time and fees.',
    'noContact'=>'No direct booking or contact details have been published for this profile.',
    'confirm'=>'Confirm before visiting','confirm1'=>'The doctor and practice location','confirm2'=>'Available appointment times','confirm3'=>'Consultation fees and required documents',
    'related'=>'Explore other doctor profiles','allDoctors'=>'Find a doctor','profileLink'=>'View profile',
    'gallery'=>'Photos & professional media','videos'=>'Videos from published sources','video'=>'View video','social'=>'Professional channels',
    'sourceNote'=>'References are provided for checking individual profile facts; they are not a substitute for a professional consultation.',
    'accessed'=>'Accessed','feedback'=>'Suggest a correction','close'=>'Close image viewer','previous'=>'Previous image','next'=>'Next image','photos'=>'photos',
    'nav'=>'Doctor profile sections','present'=>'Current role','onPage'=>'ON THIS PROFILE','overview'=>'Profile at a glance',
] : [
    'home'=>'Trang chủ','doctors'=>'Bác sĩ','profile'=>'HỒ SƠ BÁC SĨ','reference'=>'Thông tin từ nguồn công khai','verified'=>'Hồ sơ đã duyệt',
    'intro'=>'Giới thiệu','expertise'=>'Chuyên môn','education'=>'Đào tạo & bằng cấp','experience'=>'Quá trình công tác',
    'locations'=>'Nơi khám','fees'=>'Lịch khám & chi phí','research'=>'Nghiên cứu & hoạt động','sources'=>'Nguồn tham khảo',
    'degree'=>'Học vị','since'=>'Hành nghề từ','languages'=>'Ngôn ngữ','patients'=>'Nhóm người bệnh','city'=>'Khu vực',
    'book'=>'Đặt lịch khám','places'=>'Xem nơi khám','call'=>'Gọi xác nhận','noRating'=>'Chưa có đánh giá',
    'ratings'=>'đánh giá','ratingNote'=>'Điểm đánh giá được ghi nhận cho bác sĩ, không phải điểm của cơ sở công tác.',
    'updated'=>'Cập nhật nghiên cứu','sourceCount'=>'nguồn tham khảo','readMore'=>'Đọc hồ sơ đầy đủ','readLess'=>'Thu gọn',
    'emptyIntro'=>'Thông tin chuyên sâu đang được cập nhật. Bạn nên xác nhận chuyên môn và nơi công tác hiện tại của bác sĩ trước khi thăm khám.',
    'notice'=>'Thông tin được tổng hợp từ nguồn công khai. Liên hệ trực tiếp nơi khám để xác nhận lịch, dịch vụ và chi phí hiện tại.',
    'insufficient'=>'Một số thông tin hồ sơ cần bổ sung bằng chứng. Nội dung không thay thế xác nhận giấy phép hành nghề hoặc tư vấn y tế cá nhân.',
    'services'=>'Dịch vụ & thế mạnh chuyên môn','conditions'=>'Bệnh lý được đề cập','license'=>'Thông tin hành nghề công khai',
    'document'=>'Loại giấy tờ','number'=>'Số giấy phép','issuer'=>'Đơn vị cấp','issued'=>'Ngày cấp','scope'=>'Phạm vi hành nghề',
    'licenseNote'=>'Thông tin công khai bên dưới không phải xác nhận giấy phép còn hiệu lực tại thời điểm bạn thăm khám.',
    'certificates'=>'Chứng chỉ & đào tạo bổ sung','memberships'=>'Hiệp hội & tổ chức','publications'=>'Nghiên cứu & công bố','awards'=>'Giải thưởng',
    'primary'=>'Nơi khám chính','address'=>'Địa chỉ','phone'=>'Số liên hệ công khai','website'=>'Website','email'=>'Email',
    'directions'=>'Chỉ đường','facility'=>'Xem hồ sơ cơ sở','schedule'=>'Lịch được công bố','price'=>'Chi phí tham khảo',
    'confirmSchedule'=>'Liên hệ nơi khám để xác nhận lịch của bác sĩ. Giờ mở cửa cơ sở không đồng nghĩa với lịch khám riêng của bác sĩ.',
    'confirmFee'=>'Chi phí có thể thay đổi theo buổi khám và dịch vụ thực hiện. Hãy xác nhận tổng chi phí trước khi đến.',
    'generalSchedule'=>'Lịch khám của bác sĩ','generalFees'=>'Chi phí được công bố','plan'=>'Chuẩn bị cho buổi khám',
    'planNote'=>'Liên hệ trực tiếp nơi khám để xác nhận bác sĩ, thời gian hẹn và chi phí.',
    'noContact'=>'Hồ sơ chưa công bố thông tin đặt lịch hoặc liên hệ trực tiếp.',
    'confirm'=>'Trước khi đến, hãy xác nhận','confirm1'=>'Bác sĩ và địa điểm thăm khám','confirm2'=>'Khung giờ nhận bệnh','confirm3'=>'Chi phí và giấy tờ cần mang theo',
    'related'=>'Khám phá hồ sơ bác sĩ khác','allDoctors'=>'Tìm bác sĩ','profileLink'=>'Xem hồ sơ',
    'gallery'=>'Hình ảnh & tư liệu','videos'=>'Video từ nguồn công khai','video'=>'Xem video','social'=>'Kênh thông tin nghề nghiệp',
    'sourceNote'=>'Các nguồn giúp bạn đối chiếu từng thông tin trong hồ sơ, không thay thế việc tư vấn trực tiếp với người hành nghề.',
    'accessed'=>'Truy cập','feedback'=>'Góp ý thông tin','close'=>'Đóng trình xem ảnh','previous'=>'Ảnh trước','next'=>'Ảnh tiếp theo','photos'=>'ảnh',
    'nav'=>'Các phần hồ sơ bác sĩ','present'=>'Đang công tác','onPage'=>'TRONG HỒ SƠ NÀY','overview'=>'Thông tin nổi bật',
];
$label = static fn(string $key): string => $labels[$key] ?? $key;
$icon = static fn(string $name): string => '<i class="ph ph-' . $name . '" aria-hidden="true"></i>';
$refs = static function (array $entry) use ($profile, $escape, $english): string {
    $html = ''; $seen = [];
    foreach ((array) ($entry['source_ids'] ?? []) as $id) {
        $source = $profile['source_index'][medical_doctor_profile_text($id)] ?? null;
        if (!$source || isset($seen[$source['number']])) continue;
        $seen[$source['number']] = true;
        $html .= '<a class="dp-ref" href="#' . $escape($source['anchor']) . '" aria-label="' . ($english ? 'Source ' : 'Nguồn ') . $source['number'] . ': ' . $escape($source['title']) . '">[' . $source['number'] . ']</a>';
    }
    return $html !== '' ? '<span class="dp-refs">' . $html . '</span>' : '';
};
$join = static fn(array $parts): string => implode(' · ', array_filter(array_map('medical_doctor_profile_text', $parts), static fn($item) => $item !== ''));
$license=(array) ($profile['practice_license_json'] ?? []);
$licenseFields=['document_type'=>'document','number'=>'number','issuer'=>'issuer','issued_date'=>'issued','scope'=>'scope'];
$licenseValues=array_filter(array_intersect_key($license,$licenseFields),static fn($value)=>medical_doctor_profile_text($value)!=='');
$hasTraining = $profile['education_json'] !== [] || $profile['certifications_json'] !== [] || $licenseValues !== [];
$hasExpertise = $profile['services_json'] !== [] || $profile['conditions_treated_json'] !== [] || $profile['specialties'] !== [];
$hasResearch = $profile['publications_json'] !== [] || $profile['memberships_json'] !== [] || $profile['awards_json'] !== [];
$hasFees = $profile['schedule_json'] !== [] || $profile['fees_json'] !== [] || trim((string) ($profile['hours_text'] ?? '')) !== '' || trim((string) ($profile['price_text'] ?? '')) !== '';
$sections = ['gioi-thieu'=>'intro'];
if ($hasExpertise) $sections['chuyen-mon'] = 'expertise';
if ($hasTraining) $sections['dao-tao'] = 'education';
if ($profile['experience_json'] !== []) $sections['cong-tac'] = 'experience';
if ($profile['locations'] !== []) $sections['noi-kham'] = 'locations';
if ($hasFees) $sections['lich-chi-phi'] = 'fees';
if ($hasResearch) $sections['nghien-cuu'] = 'research';
if ($profile['gallery'] !== [] || $profile['video_urls'] !== [] || $profile['social_links'] !== []) $sections['tu-lieu'] = 'gallery';
if ($profile['sources'] !== []) $sections['nguon-tham-khao'] = 'sources';
$contactPath = site_localized_path('/lien-he', $doctorLanguage);
$primary=$profile['locations'][0] ?? [];
?>
<main class="doctor-profile site-typo">
  <div class="container dp-shell">
    <nav class="dp-breadcrumb" aria-label="Breadcrumb"><a href="<?= $escape(site_localized_path('/', $doctorLanguage)) ?>"><?= $escape($label('home')) ?></a><span>›</span><a href="<?= $escape(site_localized_path('/bac-si.php', $doctorLanguage)) ?>"><?= $escape($label('doctors')) ?></a><span>›</span><span aria-current="page"><?= $escape($profile['name']) ?></span></nav>

    <section class="dp-hero" aria-labelledby="doctor-name">
      <div class="dp-identity">
        <div class="dp-eyebrow"><?= $icon('stethoscope') ?><?= $escape($label('profile')) ?><?php if ($profile['city'] !== ''): ?><span class="dp-city"><?= $escape($profile['city']) ?></span><?php endif; ?></div>
        <h1 id="doctor-name"><?= $escape($profile['name']) ?></h1>
        <?php if ($profile['title_text'] !== ''): ?><p class="dp-title"><?= $escape($profile['title_text']) ?></p><?php endif; ?>
        <?php if ($profile['specialty_text'] !== ''): ?><span class="dp-specialty"><?= $icon('first-aid-kit') ?><?= $escape($profile['specialty_text']) ?></span><?php endif; ?>
        <?php if ($profile['subtitle'] !== '' && $profile['subtitle'] !== $profile['title_text']): ?><p class="dp-subtitle"><?= $escape($profile['subtitle']) ?></p><?php endif; ?>
        <div class="dp-status">
          <?php if (!empty($profile['is_verified']) && ($profile['verification_status'] ?? '') === 'reviewed'): ?><span class="dp-reviewed"><?= $icon('seal-check') ?><?= $escape($label('verified')) ?></span><?php else: ?><span><?= $icon('files') ?><?= $escape($label('reference')) ?></span><?php endif; ?>
          <?php if ($profile['has_rating']): ?><span class="dp-rating"><?= $icon('star') ?><strong><?= number_format($profile['rating'], 1) ?>/5</strong> · <?= number_format((int) $profile['reviews_count'], 0, ',', '.') ?> <?= $escape($label('ratings')) ?></span><?php else: ?><span><?= $icon('star') ?><?= $escape($label('noRating')) ?></span><?php endif; ?>
        </div>
        <div class="dp-actions">
          <?php if ($profile['booking_url'] !== ''): ?><a class="dp-button dp-primary" href="<?= $escape($profile['booking_url']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= $icon('calendar-check') ?><?= $escape($label('book')) ?><?= $icon('arrow-up-right') ?></a><?php elseif ($profile['locations'] !== []): ?><a class="dp-button dp-primary" href="#noi-kham"><?= $icon('map-pin') ?><?= $escape($label('places')) ?><?= $icon('arrow-down') ?></a><?php endif; ?>
          <?php if ($profile['phone_href'] !== ''): ?><a class="dp-button" href="<?= $escape($profile['phone_href']) ?>"><?= $icon('phone') ?><?= $escape($label('call')) ?></a><?php endif; ?>
        </div>
      </div>
      <div class="dp-hero-visual">
        <svg class="dp-medical-art" viewBox="0 0 420 360" fill="none" aria-hidden="true" focusable="false">
          <path d="M56 62C112 7 226 4 302 52c64 40 105 125 78 206-24 70-104 86-181 80C113 331 18 281 21 194c2-45 7-104 35-132Z" fill="#DCE8FF"/>
          <circle cx="326" cy="260" r="72" fill="#CDEDE3"/>
          <circle cx="73" cy="81" r="35" fill="#FFF3D7"/>
          <path d="M351 70h24m-12-12v24M44 254h24m-12-12v24" stroke="#97B1E7" stroke-width="4" stroke-linecap="round"/>
          <path d="m79 308 44-19M287 31l12 18" stroke="#B7CEF8" stroke-width="3" stroke-linecap="round"/>
          <circle cx="382" cy="156" r="6" fill="#84C6B4"/>
          <circle cx="38" cy="158" r="5" fill="#95B4F5"/>
        </svg>
        <div class="dp-portrait<?= $profile['image_url'] === '' ? ' is-placeholder' : '' ?>">
          <span class="dp-avatar" aria-hidden="true"><?= $escape($profile['initials']) ?></span>
          <?php if ($profile['image_url'] !== ''): ?><img src="<?= $escape($profile['image_url']) ?>" alt="<?= $escape($profile['name']) ?>" width="320" height="380" fetchpriority="high" decoding="async" data-dp-photo><?php endif; ?>
        </div>
        <span class="dp-visual-symbol" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none"><path d="M12 9v10a10 10 0 0 0 20 0V9M9 9h6m14 0h6M22 29v5a8 8 0 0 0 16 0v-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><circle cx="38" cy="24" r="5" stroke="currentColor" stroke-width="2.5"/></svg></span>
      </div>
      <?php if ($profile['degree_text'] !== '' || !empty($profile['experience_start_year']) || $profile['languages_supported_json'] !== [] || $primary !== []): ?>
      <dl class="dp-facts" aria-label="<?= $escape($label('overview')) ?>">
        <?php if ($profile['degree_text'] !== ''): ?><div><dt><?= $icon('graduation-cap') ?><?= $escape($label('degree')) ?></dt><dd><?= $escape($profile['degree_text']) ?></dd></div><?php endif; ?>
        <?php if (!empty($profile['experience_start_year'])): ?><div><dt><?= $icon('briefcase') ?><?= $escape($label('since')) ?></dt><dd><?= $escape($profile['experience_start_year']) ?></dd></div><?php endif; ?>
        <?php if ($profile['languages_supported_json'] !== []): ?><div><dt><?= $icon('translate') ?><?= $escape($label('languages')) ?></dt><dd><?= $escape($join(array_column($profile['languages_supported_json'], 'name'))) ?></dd></div><?php endif; ?>
        <?php if ($primary !== []): ?><div class="dp-fact-place"><dt><?= $icon('buildings') ?><?= $escape($label(!empty($primary['is_primary']) ? 'primary' : 'locations')) ?></dt><dd><a href="#noi-kham"><?= $escape($primary['facility_name']) ?><?= $icon('arrow-up-right') ?></a></dd></div><?php endif; ?>
      </dl>
      <?php endif; ?>
    </section>
    <p class="dp-disclosure"><?= $icon('info') ?><span><?= $escape($label(!empty($profile['insufficient_data']) ? 'insufficient' : 'notice')) ?></span></p>

    <nav class="dp-nav" aria-label="<?= $escape($label('nav')) ?>">
      <?php foreach ($sections as $id=>$key): ?><a href="#<?= $escape($id) ?>"<?= $id === 'gioi-thieu' ? ' class="is-active" aria-current="location"' : '' ?>><?= $escape($label($key)) ?></a><?php endforeach; ?>
    </nav>
    <div class="dp-layout">
      <div class="dp-main">
        <section class="dp-section" id="gioi-thieu">
          <div class="dp-section-head"><span class="dp-section-icon"><?= $icon('user') ?></span><h2><?= $escape($label('intro')) ?></h2></div>
          <div class="dp-article" id="doctor-article" data-dp-article>
            <?php if (trim(strip_tags($profile['content'])) !== ''): ?><?= $profile['content'] ?><?php elseif ($profile['bio'] !== []): ?><?php foreach ($profile['bio'] as $paragraph): ?><p><?= $escape($paragraph) ?></p><?php endforeach; ?><?php else: ?><p><?= $escape($label('emptyIntro')) ?></p><?php endif; ?>
          </div>
          <button class="dp-read-more" type="button" aria-controls="doctor-article" aria-expanded="false" data-dp-read-more data-more="<?= $escape($label('readMore')) ?>" data-less="<?= $escape($label('readLess')) ?>" hidden><?= $escape($label('readMore')) ?><?= $icon('caret-down') ?></button>
          <?php if ($profile['patient_groups_json'] !== []): ?><div class="dp-subsection"><h3><?= $escape($label('patients')) ?></h3><div class="dp-chips"><?php foreach ($profile['patient_groups_json'] as $entry): ?><span><?= $escape($entry['name']) ?><?= $refs($entry) ?></span><?php endforeach; ?></div></div><?php endif; ?>
        </section>

        <?php if ($hasExpertise): ?>
        <section class="dp-section" id="chuyen-mon">
          <div class="dp-section-head"><span class="dp-section-icon"><?= $icon('stethoscope') ?></span><h2><?= $escape($label('expertise')) ?></h2></div>
          <?php if ($profile['specialties'] !== []): ?><div class="dp-chips dp-specialties"><?php foreach ($profile['specialties'] as $specialty): ?><span><?= $escape($specialty) ?></span><?php endforeach; ?></div><?php endif; ?>
          <?php if ($profile['services_json'] !== []): ?><div class="dp-service-list"><?php foreach ($profile['services_json'] as $index=>$entry): ?><article><span class="dp-service-number"><?= str_pad((string) ($index+1), 2, '0', STR_PAD_LEFT) ?></span><div><h3><?= $escape($entry['name']) ?></h3><?php if (!empty($entry['description'])): ?><p><?= $escape($entry['description']) ?></p><?php endif; ?><?= $refs($entry) ?></div></article><?php endforeach; ?></div><?php endif; ?>
          <?php if ($profile['conditions_treated_json'] !== []): ?><div class="dp-subsection"><h3><?= $escape($label('conditions')) ?></h3><ul class="dp-condition-list"><?php foreach ($profile['conditions_treated_json'] as $entry): ?><li><?= $icon('check-circle') ?><span><?= $escape($entry['name']) ?><?= $refs($entry) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($hasTraining): ?>
        <section class="dp-section" id="dao-tao">
          <div class="dp-section-head"><span class="dp-section-icon"><?= $icon('graduation-cap') ?></span><h2><?= $escape($label('education')) ?></h2></div>
          <?php if ($profile['education_json'] !== []): ?><ol class="dp-timeline"><?php foreach ($profile['education_json'] as $entry): ?><li><span class="dp-timeline-dot"></span><div><?php $period=medical_doctor_profile_period($entry,$english); if ($period !== ''): ?><span class="dp-period"><?= $escape($period) ?></span><?php endif; ?><h3><?= $escape(medical_doctor_profile_text($entry['degree'] ?? '') ?: $entry['institution'] ?? '') ?></h3><p><?= $escape($join([$entry['institution'] ?? '',$entry['specialty'] ?? ''])) ?></p><?= $refs($entry) ?></div></li><?php endforeach; ?></ol><?php endif; ?>
          <?php if ($profile['certifications_json'] !== []): ?><div class="dp-subsection"><h3><?= $escape($label('certificates')) ?></h3><div class="dp-records"><?php foreach ($profile['certifications_json'] as $entry): ?><article><?= $icon('certificate') ?><div><h4><?= $escape($entry['name']) ?></h4><p><?= $escape($join([$entry['issuer'] ?? '',$entry['year'] ?? ''])) ?></p><?= $refs($entry) ?></div></article><?php endforeach; ?></div></div><?php endif; ?>
          <?php if ($licenseValues !== []): ?>
          <details class="dp-license"><summary><?= $icon('identification-card') ?><?= $escape($label('license')) ?><?= $icon('caret-down') ?></summary><dl><?php foreach ($licenseFields as $field=>$key): if (medical_doctor_profile_text($license[$field] ?? '')==='') continue; ?><div><dt><?= $escape($label($key)) ?></dt><dd><?= $escape($license[$field]) ?></dd></div><?php endforeach; ?></dl><?= $refs($license) ?><p class="dp-note"><?= $escape($label('licenseNote')) ?></p></details>
          <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($profile['experience_json'] !== []): ?>
        <section class="dp-section" id="cong-tac">
          <div class="dp-section-head"><span class="dp-section-icon"><?= $icon('briefcase') ?></span><h2><?= $escape($label('experience')) ?></h2></div>
          <ol class="dp-timeline"><?php foreach ($profile['experience_json'] as $entry): ?><li><span class="dp-timeline-dot"></span><div><div class="dp-timeline-meta"><?php $period=medical_doctor_profile_period($entry,$english); if ($period !== ''): ?><span class="dp-period"><?= $escape($period) ?></span><?php endif; ?><?php if (!empty($entry['is_current'])): ?><span class="dp-current"><?= $escape($label('present')) ?></span><?php endif; ?></div><h3><?= $escape(medical_doctor_profile_text($entry['role'] ?? '') ?: $entry['facility_name'] ?? '') ?></h3><p><?= $escape($join([$entry['facility_name'] ?? '',$entry['department'] ?? ''])) ?></p><?= $refs($entry) ?></div></li><?php endforeach; ?></ol>
        </section>
        <?php endif; ?>

        <?php if ($profile['locations'] !== []): ?>
        <section class="dp-section" id="noi-kham">
          <div class="dp-section-head"><span class="dp-section-icon"><?= $icon('buildings') ?></span><h2><?= $escape($label('locations')) ?></h2><span class="dp-count"><?= count($profile['locations']) ?></span></div>
          <div class="dp-locations">
            <?php foreach ($profile['locations'] as $index=>$location): ?>
            <article class="dp-location">
              <div class="dp-location-head"><span class="dp-location-number"><?= $index+1 ?></span><div><?php if (!empty($location['is_primary'])): ?><span class="dp-primary-place"><?= $escape($label('primary')) ?></span><?php endif; ?><h3><?= $escape($location['facility_name']) ?></h3><?php if ($join([$location['role_text'] ?? '',$location['department_text'] ?? ''])!==''): ?><p><?= $escape($join([$location['role_text'] ?? '',$location['department_text'] ?? ''])) ?></p><?php endif; ?></div></div>
              <?php if (!empty($location['address_text'])): ?><p class="dp-location-address"><?= $icon('map-pin') ?><span><?= $escape($location['address_text']) ?></span></p><?php endif; ?>
              <?php if ($location['phone_href'] !== ''): ?><a class="dp-location-phone" href="<?= $escape($location['phone_href']) ?>"><?= $icon('phone') ?><?= $escape($location['phone_text']) ?></a><?php endif; ?>
              <?= $refs($location) ?>
              <?php if ($location['schedule_json'] !== []): ?><div class="dp-place-detail"><h4><?= $icon('clock') ?><?= $escape($label('schedule')) ?></h4><?php foreach ($location['schedule_json'] as $entry): ?><div class="dp-schedule-row"><strong><?= $escape($entry['day'] ?? '') ?></strong><span><?= $escape($entry['time_text'] ?? '') ?><?= $refs($entry) ?></span></div><?php endforeach; ?></div><?php else: ?><p class="dp-note"><?= $escape($label('confirmSchedule')) ?></p><?php endif; ?>
              <?php if ($location['fees_json'] !== []): ?><div class="dp-place-detail"><h4><?= $escape($label('price')) ?></h4><?php foreach ($location['fees_json'] as $entry): ?><div class="dp-fee-row"><div><strong><?= $escape($entry['service']) ?></strong><?php if (!empty($entry['notes'])): ?><p><?= $escape($entry['notes']) ?></p><?php endif; ?><?= $refs($entry) ?></div><span><?= $escape(medical_doctor_profile_fee($entry,$english)) ?></span></div><?php endforeach; ?></div><?php endif; ?>
              <div class="dp-location-actions">
                <?php if ($location['booking_url'] !== ''): ?><a href="<?= $escape($location['booking_url']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= $icon('calendar-check') ?><?= $escape($label('book')) ?></a><?php endif; ?>
                <?php if ($location['website_url'] !== ''): ?><a href="<?= $escape($location['website_url']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= $icon('globe') ?><?= $escape($label('website')) ?></a><?php endif; ?>
                <?php if ($location['map_url'] !== ''): ?><a href="<?= $escape($location['map_url']) ?>" target="_blank" rel="noopener noreferrer"><?= $icon('arrow-up-right') ?><?= $escape($label('directions')) ?></a><?php endif; ?>
                <?php if (!empty($location['profile_url'])): ?><a href="<?= $escape($location['profile_url']) ?>"><?= $escape($label('facility')) ?><?= $icon('arrow-right') ?></a><?php endif; ?>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($hasFees): ?>
        <section class="dp-section" id="lich-chi-phi">
          <div class="dp-section-head"><span class="dp-section-icon"><?= $icon('calendar-blank') ?></span><h2><?= $escape($label('fees')) ?></h2></div>
          <?php if ($profile['schedule_json'] !== []): ?><div class="dp-schedule-list"><?php foreach ($profile['schedule_json'] as $entry): ?><div class="dp-schedule-row"><strong><?= $escape($entry['day'] ?? '') ?></strong><div><span><?= $escape($entry['time_text'] ?? '') ?></span><?php if (!empty($entry['location_name'])): ?><small><?= $escape($entry['location_name']) ?></small><?php endif; ?><?= $refs($entry) ?></div></div><?php endforeach; ?></div><?php elseif (!empty($profile['hours_text'])): ?><p><?= $escape($profile['hours_text']) ?></p><?php endif; ?>
          <?php if ($profile['fees_json'] !== []): ?><div class="dp-subsection"><h3><?= $escape($label('generalFees')) ?></h3><?php foreach ($profile['fees_json'] as $entry): ?><div class="dp-fee-row"><div><strong><?= $escape($entry['service']) ?></strong><?php if (!empty($entry['notes'])): ?><p><?= $escape($entry['notes']) ?></p><?php endif; ?><?= $refs($entry) ?></div><span><?= $escape(medical_doctor_profile_fee($entry,$english)) ?></span></div><?php endforeach; ?></div><?php elseif (!empty($profile['price_text'])): ?><p><?= $escape($profile['price_text']) ?></p><?php endif; ?>
          <p class="dp-note"><?= $escape($label('confirmFee')) ?></p>
        </section>
        <?php endif; ?>

        <?php if ($hasResearch): ?>
        <section class="dp-section" id="nghien-cuu">
          <div class="dp-section-head"><span class="dp-section-icon"><?= $icon('book-open') ?></span><h2><?= $escape($label('research')) ?></h2></div>
          <?php foreach (['publications_json'=>['publications','article'],'memberships_json'=>['memberships','users-three'],'awards_json'=>['awards','medal']] as $field=>[$key,$symbol]): if ($profile[$field]===[]) continue; ?>
          <div class="dp-subsection"><h3><?= $escape($label($key)) ?></h3><div class="dp-records"><?php foreach ($profile[$field] as $entry): ?><article><?= $icon($symbol) ?><div><h4><?= $escape($entry['title'] ?? $entry['name'] ?? '') ?></h4><?php $details=$join([$entry['role'] ?? '',$entry['issuer'] ?? '',$entry['year'] ?? '']); if ($details!==''): ?><p><?= $escape($details) ?></p><?php endif; ?><?php $url=medical_doctor_profile_url($entry['url'] ?? ''); if ($url!==''): ?><a class="dp-text-link" href="<?= $escape($url) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= $escape((string) parse_url($url,PHP_URL_HOST)) ?><?= $icon('arrow-up-right') ?></a><?php endif; ?><?php if (!empty($entry['doi'])): ?><p class="dp-note">DOI: <?= $escape($entry['doi']) ?></p><?php endif; ?><?= $refs($entry) ?></div></article><?php endforeach; ?></div></div>
          <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if ($profile['gallery'] !== [] || $profile['video_urls'] !== [] || $profile['social_links'] !== []): ?>
        <section class="dp-section" id="tu-lieu">
          <div class="dp-section-head"><span class="dp-section-icon"><?= $icon('images') ?></span><h2><?= $escape($label('gallery')) ?></h2></div>
          <?php if ($profile['gallery'] !== []): ?><div class="dp-gallery"><?php foreach ($profile['gallery'] as $index=>$image): $photoMeta=$profile['gallery_meta'][$image] ?? []; $caption=medical_doctor_profile_text($photoMeta['caption'] ?? ''); ?><figure><button type="button" data-dp-gallery="<?= $index ?>" aria-label="<?= $escape($profile['name']) ?> · <?= $index+1 ?>/<?= count($profile['gallery']) ?>"><img src="<?= $escape($image) ?>" alt="<?= $escape($caption ?: $profile['name'].' · '.($index+1)) ?>" width="360" height="240" loading="lazy" decoding="async" data-dp-gallery-photo></button><?php if ($caption!=='' || !empty($photoMeta['source_ids'])): ?><figcaption><?= $escape($caption) ?><?= $refs($photoMeta) ?></figcaption><?php endif; ?></figure><?php endforeach; ?></div><?php endif; ?>
          <?php if ($profile['video_urls'] !== []): ?><div class="dp-media-links"><?php foreach ($profile['video_urls'] as $index=>$url): ?><a href="<?= $escape($url) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= $icon('play-circle') ?><?= $escape($label('video')) ?> <?= $index+1 ?><?= $icon('arrow-up-right') ?></a><?php endforeach; ?></div><?php endif; ?>
          <?php if ($profile['social_links'] !== []): ?><div class="dp-media-links"><?php foreach ($profile['social_links'] as $platform=>$url): ?><a href="<?= $escape($url) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= $icon('globe') ?><?= $escape(ucfirst($platform)) ?><?= $icon('arrow-up-right') ?></a><?php endforeach; ?></div><?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($profile['sources'] !== []): ?>
        <section class="dp-section dp-sources-section" id="nguon-tham-khao">
          <div class="dp-section-head"><span class="dp-section-icon"><?= $icon('files') ?></span><h2><?= $escape($label('sources')) ?></h2><span class="dp-count"><?= count($profile['sources']) ?></span></div>
          <p class="dp-note"><?= $escape($label('sourceNote')) ?></p>
          <?php if ($profile['updated_label'] !== ''): ?><p class="dp-updated"><?= $icon('clock-clockwise') ?><?= $escape($label('updated')) ?>: <?= $escape($profile['updated_label']) ?></p><?php endif; ?>
          <ol class="dp-sources"><?php foreach ($profile['sources'] as $source): ?><li id="<?= $escape($source['anchor']) ?>"><span class="dp-source-number"><?= $source['number'] ?></span><div><a href="<?= $escape($source['url']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= $escape($source['title']) ?><?= $icon('arrow-up-right') ?></a><p><?= $escape($source['publisher'] ?? parse_url($source['url'],PHP_URL_HOST)) ?><?php $accessed=medical_doctor_profile_date($source['accessed_at'] ?? ''); if ($accessed!==''): ?><span> · <?= $escape($label('accessed')) ?> <?= $escape($accessed) ?></span><?php endif; ?></p></div></li><?php endforeach; ?></ol>
          <a class="dp-text-link" href="<?= $escape($contactPath.'?topic=feedback#gui-lien-he') ?>"><?= $escape($label('feedback')) ?><?= $icon('arrow-right') ?></a>
        </section>
        <?php endif; ?>
      </div>

      <aside class="dp-sidebar" aria-label="<?= $escape($label('plan')) ?>">
        <section class="dp-contact-card">
          <span class="dp-contact-icon"><?= $icon('calendar-check') ?></span><h2><?= $escape($label('plan')) ?></h2><p><?= $escape($label('planNote')) ?></p>
          <?php if ($primary !== []): ?><div class="dp-sidebar-place"><?= $icon('buildings') ?><div><strong><?= $escape($primary['facility_name']) ?></strong><?php if (!empty($primary['address_text'])): ?><span><?= $escape($primary['address_text']) ?></span><?php endif; ?></div></div><?php endif; ?>
          <?php if ($profile['phone_href'] !== ''): ?><a class="dp-contact-number" href="<?= $escape($profile['phone_href']) ?>"><?= $icon('phone') ?><span><small><?= $escape($label('phone')) ?></small><strong><?= $escape($profile['phone_text']) ?></strong></span></a><?php endif; ?>
          <?php if ($profile['booking_url'] !== ''): ?><a class="dp-button dp-primary" href="<?= $escape($profile['booking_url']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= $escape($label('book')) ?><?= $icon('arrow-up-right') ?></a><?php elseif ($profile['locations'] !== []): ?><a class="dp-button dp-primary" href="#noi-kham"><?= $escape($label('places')) ?><?= $icon('arrow-right') ?></a><?php endif; ?>
          <?php if ($profile['email_text'] !== ''): ?><a class="dp-text-link" href="mailto:<?= $escape($profile['email_text']) ?>"><?= $icon('envelope') ?><?= $escape($profile['email_text']) ?></a><?php endif; ?>
          <?php if ($profile['website_url'] !== ''): ?><a class="dp-text-link" href="<?= $escape($profile['website_url']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= $icon('globe') ?><?= $escape($label('website')) ?><?= $icon('arrow-up-right') ?></a><?php endif; ?>
          <?php if ($primary === [] && $profile['phone_href'] === '' && $profile['booking_url'] === '' && $profile['email_text'] === '' && $profile['website_url'] === ''): ?><p class="dp-note"><?= $escape($label('noContact')) ?></p><?php endif; ?>
        </section>
        <section class="dp-checklist"><h3><?= $escape($label('confirm')) ?></h3><ul><?php foreach (['confirm1','confirm2','confirm3'] as $key): ?><li><?= $icon('check-circle') ?><span><?= $escape($label($key)) ?></span></li><?php endforeach; ?></ul></section>
        <?php if ($profile['has_rating']): ?><p class="dp-note dp-rating-note"><?= $escape($label('ratingNote')) ?></p><?php endif; ?>
      </aside>
    </div>

    <?php if ($relatedDoctors !== []): ?>
    <section class="dp-related"><div class="dp-related-head"><h2><?= $escape($label('related')) ?></h2><a class="dp-text-link" href="<?= $escape(site_localized_path('/bac-si.php',$doctorLanguage)) ?>"><?= $escape($label('allDoctors')) ?><?= $icon('arrow-right') ?></a></div><div class="dp-related-grid">
      <?php foreach ($relatedDoctors as $other): $otherPhoto=medical_doctor_profile_url($other['image_url'] ?? '',true); ?><a class="dp-related-card" href="<?= $escape(medical_public_entity_path('doctor',$other['slug'],$other['language_code'])) ?>"><span class="dp-related-avatar"><span aria-hidden="true"><?= $icon('user') ?></span><?php if ($otherPhoto!==''): ?><img src="<?= $escape($otherPhoto) ?>" alt="<?= $escape($other['name']) ?>" width="72" height="72" loading="lazy" decoding="async" data-dp-photo><?php endif; ?></span><div><h3><?= $escape($other['name']) ?></h3><p><?= $escape($join([$other['specialty_text'],$other['city']])) ?></p><span class="dp-text-link"><?= $escape($label('profileLink')) ?><?= $icon('arrow-up-right') ?></span></div></a><?php endforeach; ?>
    </div></section>
    <?php endif; ?>
  </div>
  <?php if ($profile['gallery'] !== []): ?><dialog class="dp-lightbox" data-dp-lightbox aria-label="<?= $escape($label('gallery')) ?>"><button class="dp-viewer-close" type="button" data-dp-close aria-label="<?= $escape($label('close')) ?>"><?= $icon('x') ?></button><span class="dp-viewer-count" data-dp-count></span><button class="dp-viewer-prev" type="button" data-dp-prev aria-label="<?= $escape($label('previous')) ?>"><?= $icon('caret-left') ?></button><img alt="<?= $escape($profile['name']) ?>" data-dp-viewer-image><button class="dp-viewer-next" type="button" data-dp-next aria-label="<?= $escape($label('next')) ?>"><?= $icon('caret-right') ?></button></dialog><?php endif; ?>
</main>
