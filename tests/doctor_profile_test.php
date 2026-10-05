<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && !(PHP_SAPI === 'cli-server' && defined('DOCTOR_PROFILE_FIXTURE_ONLY')
    && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true))) {
    http_response_code(404); exit;
}
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/medical_doctor_profile.php';

/** Synthetic fixtures for rendering tests only; never persisted to the database. */
function doctor_profile_test_fixture(string $locale = 'vi'): array
{
    $row = ['id'=>900001,'slug'=>'test-profile','language_code'=>$locale,'name'=>'Bác sĩ Kiểm Thử','title_text'=>'Consultant physician',
        'specialty_text'=>'Ophthalmology','city'=>'Hà Nội','subtitle'=>'Profile fixture for layout verification.',
        'content'=>'<h2>Professional profile</h2><p>Known professional information.</p>','degree_text'=>'MD, PhD',
        'experience_start_year'=>2010,'phone_text'=>'024 1234 5678','email_text'=>'doctor@example.org',
        'website_url'=>'https://example.org/profile','booking_url'=>'https://example.org/book','rating'=>4.3,'reviews_count'=>8,
        'verified'=>1,'verification_status'=>'reviewed','last_researched_at'=>'2026-10-03 12:00:00',
        'education_json'=>[['institution'=>'Medical university','degree'=>'MD','end_year'=>2010,'source_ids'=>['s1']]],
        'experience_json'=>[['facility_name'=>'Hospital A','role'=>'Consultant','start_year'=>2012,'is_current'=>true,'source_ids'=>['s1']]],
        'certifications_json'=>[['name'=>'Training certificate','issuer'=>'University','year'=>2020,'source_ids'=>['s1']]],
        'practice_license_json'=>['document_type'=>'Practice licence','number'=>'TEST-123','issuer'=>'Health authority','scope'=>'Ophthalmology','source_ids'=>['s1']],
        'services_json'=>[['name'=>'Eye consultation','description'=>'Clinical assessment.','source_ids'=>['s1']]],
        'conditions_treated_json'=>[['name'=>'Myopia','source_ids'=>['s1']]],
        'languages_supported_json'=>[['name'=>'English','code'=>'en','source_ids'=>['s1']]],
        'patient_groups_json'=>[['name'=>'Adults','source_ids'=>['s1']]],
        'memberships_json'=>[['name'=>'Professional association','role'=>'Member','source_ids'=>['s1']]],
        'publications_json'=>[['title'=>'Research paper','url'=>'https://example.org/research','doi'=>'10.0000/example','year'=>2022,'source_ids'=>['s1']]],
        'awards_json'=>[['name'=>'Research award','issuer'=>'University','year'=>2021,'source_ids'=>['s1']]],
        'sources_json'=>[['id'=>'s1','url'=>'https://example.org/profile','title'=>'Official profile','publisher'=>'Hospital A','accessed_at'=>'2026-10-03']],
        'schedule_json'=>[['day'=>'Monday','time_text'=>'09:00–11:00','location_name'=>'Hospital A','source_ids'=>['s1']]],
        'fees_json'=>[['service'=>'Consultation','amount_min'=>250000,'amount_max'=>400000,'currency'=>'VND','unit'=>'visit','source_ids'=>['s1']]],
        'locations_json'=>[
            ['facility_id'=>null,'facility_name'=>'Clinic B','address_text'=>'Address B','is_primary'=>false,'schedule_json'=>[['day'=>'Friday','time_text'=>'15:00–17:00']]],
            ['facility_id'=>null,'facility_name'=>'Hospital A','address_text'=>'Address A','is_primary'=>true,'phone_text'=>'024 1234 5678','website_url'=>'https://example.org',
                'schedule_json'=>[['day'=>'Monday','time_text'=>'09:00–11:00','source_ids'=>['s1']]],'fees_json'=>[['service'=>'Consultation','amount_min'=>250000,'amount_max'=>400000,'currency'=>'VND']], 'source_ids'=>['s1']],
        ],
        'social_links_json'=>['zalo'=>'https://zalo.me/84123456789'], 'video_urls_json'=>['https://example.org/video'],
        'gallery_json'=>[['url'=>'https://example.org/portrait.jpg','caption'=>'Published portrait','source_ids'=>['s1']]],
    ];
    foreach ($row as $key=>$value) if (str_ends_with($key,'_json')) $row[$key]=json_encode($value,JSON_UNESCAPED_UNICODE);
    return medical_directory_doctor_from_row($row);
}

if (PHP_SAPI !== 'cli' || defined('DOCTOR_PROFILE_FIXTURE_ONLY')) return;
$checks=0;
$assert=static function(bool $condition,string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    $checks++;
};
$doctor=doctor_profile_test_fixture();
$model=medical_doctor_profile_model($doctor);
$assert($model['locations'][0]['facility_name']==='Hospital A','primary location first');
$assert($model['locations'][0]['schedule_json'][0]['day']==='Monday' && $model['locations'][1]['schedule_json'][0]['day']==='Friday','schedules stay at their own location');
$assert($model['locations'][1]['fees_json']===[],'never copy fees to another practice');
$assert($model['phone_href']==='tel:02412345678','phone dial target');
$assert(medical_doctor_profile_phone('024 1234 5678 / 090 123 4567')==='tel:02412345678','multiple numbers do not concatenate');
$assert(medical_doctor_profile_phone('unknown')==='','invalid phone has no action');
$assert($model['has_rating'] && $model['rating']===4.3,'actual rating preserved');
$assert(!medical_doctor_profile_model(array_replace($doctor,['reviews_count'=>0]))['has_rating'],'zero reviews means no rating');
$assert($model['source_index']['s1']['anchor']==='doctor-source-1','source references mapped');
$assert($model['gallery_meta']['https://example.org/portrait.jpg']['caption']==='Published portrait','rich gallery caption retained');
$assert($model['gallery_meta']['https://example.org/portrait.jpg']['source_ids']===['s1'],'gallery source relationship retained');
$assert(medical_doctor_profile_url('javascript:alert(1)')==='','unsafe contact URL omitted');
$assert(medical_doctor_profile_url('/uploads/photo.webp',true)==='/uploads/photo.webp','uploaded photo supported');
$assert(medical_doctor_profile_url('//evil.example',true)==='','protocol-relative image omitted');
$assert(medical_doctor_profile_date('2026-02-31')==='','invalid dates omitted');
$assert(medical_doctor_profile_period(['start_year'=>2010],true)==='2010','unknown end year is not marked current');
$assert(medical_doctor_profile_fee(['amount_min'=>250000,'amount_max'=>400000,'currency'=>'VND'],false)==='250.000 – 400.000 VND','fee range formatting');
$assert(medical_doctor_profile_fee(['amount_min'=>0,'amount_max'=>0,'currency'=>'VND'],true)==='0 VND','real zero fee kept');
$assert(medical_doctor_profile_fee([],false)==='Liên hệ xác nhận giá','unknown fee not invented');
$render=static function(array $doctor,string $doctorLanguage): string {
    $profile=medical_doctor_profile_model($doctor);
    $escape=static fn(mixed $value)=>htmlspecialchars(medical_doctor_profile_text($value),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $relatedDoctors=[];
    ob_start(); require dirname(__DIR__).'/Tem/doctor-profile.php'; return ob_get_clean();
};
$html=$render($doctor,'vi');
foreach(['dao-tao','cong-tac','chuyen-mon','noi-kham','lich-chi-phi','nghien-cuu','nguon-tham-khao'] as $id) $assert(str_contains($html,'id="'.$id.'"'),'render '.$id);
$assert(str_contains($html,'href="tel:02412345678"') && str_contains($html,'https://example.org/book'),'real contact actions');
$assert(str_contains($html,'href="#doctor-source-1"') && str_contains($html,'TEST-123'),'source and licence visible');
$assert(!str_contains($html,'src=""') && !str_contains($html,'href="#"'),'no broken placeholder attributes');
$assert(!str_contains($html,'notes_for_editor') && !str_contains($html,'ai_writer_claim_json'),'no private editorial/worker fields');
$assert(str_contains($html,'Published portrait'),'gallery caption rendered');
$assert(!str_contains($render(array_replace($doctor,['verification_status'=>'unreviewed']),'vi'),'Hồ sơ đã duyệt'),'unreviewed profile never shown as reviewed');
$en=$render(doctor_profile_test_fixture('en'),'en');
foreach(['Education &amp; qualifications','Practice locations','Schedule &amp; consultation fees','Sources &amp; updates','Book an appointment'] as $label) $assert(str_contains($en,$label),'English label '.$label);
$empty=medical_directory_doctor_from_row(['name'=>'Doctor <script>Test</script>','slug'=>'empty','rating'=>0,'reviews_count'=>0,'image_url'=>'']);
$html=$render($empty,'vi');
$assert(str_contains($html,'Chưa có đánh giá'),'empty rating state');
$assert(str_contains($html,'is-placeholder') && !str_contains($html,'src=""'),'empty portrait fallback');
$assert(!str_contains($html,'id="dao-tao"') && !str_contains($html,'id="noi-kham"') && !str_contains($html,'id="nghien-cuu"'),'empty sections omitted');
$assert(!str_contains($render(array_replace($empty,['practice_license_json'=>['source_ids'=>['unknown']]]),'vi'),'id="dao-tao"'),'empty licence does not create an empty section');
$assert(!str_contains($html,'<script>Test') && str_contains($html,'&lt;script&gt;Test'),'identity escaped');
$legacy=medical_doctor_profile_model($empty,['name'=>'Legacy hospital','slug'=>'legacy','address_text'=>'Known address']);
$assert($legacy['locations'][0]['facility_name']==='Legacy hospital','legacy workplace without fictional fallback');
echo "Doctor profile: {$checks} checks passed. No DB records modified.\n";
