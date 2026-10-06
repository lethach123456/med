<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/medical_doctor_directory_view.php';
function medical_public_entity_path(string $type, string $slug, string $locale): string {
    return ($locale === 'en' ? '/en' : '') . '/bac-si/' . rawurlencode($slug);
}
$checks=0;
$assert=static function(bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};
$item=['name'=>'Bác sĩ <Test>', 'slug'=>'test', 'specialty_text'=>'Mắt & trẻ em', 'city'=>'Hà Nội',
    'title_text'=>'BS. CKII', 'facility_name'=>'Bệnh viện <A>', 'hours'=>'09:00–17:00',
    'services'=>['Khám mắt','Tư vấn','Chăm sóc mắt','Theo dõi'], 'rating'=>4.3,'reviews_count'=>18,'verified'=>true];
$vi=doctor_directory_card($item,'vi');
$assert(str_contains($vi,'Bác sĩ &lt;Test&gt;') && !str_contains($vi,'Bác sĩ <Test>'),'Identity is escaped');
$assert(str_contains($vi,'Mắt &amp; trẻ em') && str_contains($vi,'Bệnh viện &lt;A&gt;'),'Specialty and workplace are escaped');
$assert(str_contains($vi,'href="/bac-si/test"'),'Vietnamese profile link');
$assert(str_contains($vi,'4.3<small>/5') && str_contains($vi,'18 đánh giá'),'Actual review score and count');
$assert(!str_contains($vi,'★★★★★'),'Never imply five stars for every score');
$assert(str_contains($vi,'doctor-card-footer') && str_contains($vi,'doctor-price'),'Data footer retained');
$assert(str_contains($vi,'doctor-media-empty') && !str_contains($vi,'src=""'),'Empty portrait fallback');
$assert(str_contains($vi,'data-extra-count="1"') && str_contains($vi,'aria-expanded="false"'),'Expandable service list');
$assert(str_contains($vi,'doctor-verified') && str_contains($vi,'Đã xác thực'),'Conditional verification label');
$assert(!str_contains(doctor_directory_card(array_replace($item,['verified'=>false]),'vi'),'doctor-verified'),'Unverified profile has no verification badge');
$assert(str_contains(doctor_directory_card(array_replace($item,['image'=>'/uploads/test.webp']),'vi'),'loading="lazy" decoding="async"'),'Portrait lazy loading');
$assert(str_contains(doctor_directory_card(array_replace($item,['rating'=>0,'reviews_count'=>0]),'vi'),'Chưa có đánh giá'),'Unrated state');
$assert(!str_contains(doctor_directory_card(array_replace($item,['reviews_count'=>0]),'vi'),'4.3<small>'),'No score without reviews');
$en=doctor_directory_card($item,'en');
foreach (['href="/en/bac-si/test"','View profile','Consultation fee','18 reviews','Contact for updates'] as $label) $assert(str_contains($en,$label),'English label/link: '.$label);
$assert(str_contains(doctor_directory_card(array_replace($item,['price'=>'250.000 VND']),'vi'),'250.000 VND'),'Published fee preserved');
$template=(string)file_get_contents(dirname(__DIR__).'/Tem/doctor-directory.php');
$js=(string)file_get_contents(dirname(__DIR__).'/assets/js/doctor-directory.js');
foreach (['doctorSearch','doctorList','doctorPagination','doctorDirectoryFilter','doctorFilterOptions','doctorCity','doctorSpecialty','doctorRating','doctorSort'] as $id) $assert(str_contains($template,'id="'.$id.'"'),'Existing control hook: '.$id);
foreach (['doctor-card-footer','doctor-media-empty','doctor-price','ph-arrow-right'] as $hook) $assert(str_contains($vi,$hook) && str_contains($js,$hook),'SSR/AJAX presentation parity: '.$hook);
$assert(str_contains($js,'/api/medical/doctors.php') && str_contains($js,"params.set('locale',"),'API/locale preserved');
$assert(str_contains($js,"event.key === 'Escape'") && str_contains($js,"toggle?.focus()"),'Filter keyboard close/focus');
$assert(str_contains($js,'event.metaKey') && str_contains($js,'data-doctor-specialty'),'Progressive enhancement preserves modified navigation');
$assert(strpos($template, '</header>') < strpos($template, 'id="doctorDirectoryFilter"'), 'Search spans the full directory below the hero');
$assert(str_contains($template, 'doctor-hero-note') && str_contains($template, '$number($reviewCount)'), 'Hero statistics retain real review data');
$assert(str_contains($template, 'doctor-city-art') && str_contains($template, 'data-doctor-open-filters'), 'Illustrated location card has a working filter shortcut');
$assert(str_contains($js, "focus({preventScroll:true})") && str_contains($js, "doctor-enhanced"), 'Shortcut focuses its real location control and only shows with enhancement');
$css=(string)file_get_contents(dirname(__DIR__).'/assets/css/pages/doctor-directory.css');
$assert(str_contains($css, 'doctor-art-float 4.4s ease-in-out 1') && str_contains($css, 'prefers-reduced-motion:reduce'), 'Gentle settling animation respects reduced motion');
echo "Doctor directory: {$checks} checks passed. No DB records modified.\n";
