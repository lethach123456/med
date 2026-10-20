<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && !(PHP_SAPI === 'cli-server' && defined('TOPLIST_DETAIL_FIXTURE_ONLY') && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true))) { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/medical_toplist_detail_view.php';

function toplist_detail_test_rows(): array
{
    $doctor = ['id'=>90001,'member_type'=>'doctor','rank_order'=>1,'name'=>'Bác sĩ Kiểm Thử','slug'=>'bac-si-kiem-thu','language_code'=>'vi',
        'title_text'=>'TS.BS · Chuyên khoa mắt','specialty_text'=>'Nhãn khoa','city'=>'Hà Nội','facility_name'=>'Bệnh viện Kiểm Thử',
        'address_text'=>'Địa chỉ dữ liệu mẫu','phone_text'=>'024 1234 5678','hours_text'=>'Thứ 2–6, 08:00–17:00','website_url'=>'https://example.org/doctor',
        'content'=>'<h2>Chuyên môn & quá trình công tác</h2>'.str_repeat('<p>Nội dung hồ sơ đầy đủ cần giữ nguyên trong bài Toplist.</p>',12),
        'rating'=>4.5,'reviews_count'=>8,'verified'=>1,'image_url'=>'/uploads/test-portrait.jpg',
        'specialties_json'=>'["Nhãn khoa","Mắt trẻ em","Phẫu thuật lác"]',
        'gallery_json'=>'[{"url":"/uploads/test-room.jpg","caption":"Phòng khám","alt":"Không gian phòng khám"},{"url":"/uploads/test-portrait.jpg","caption":"Bác sĩ","alt":"Chân dung bác sĩ"}]',
        'fees_json'=>'[{"service":"Khám mắt","amount_min":200000,"amount_max":350000,"currency":"VNĐ","unit":"lượt"}]',
        'sources_json'=>'[{"id":"s1","url":"https://example.org/profile","title":"Hồ sơ chính thức"}]',
        'notes_for_editor'=>'PRIVATE TEST NOTES','ai_writer_claim_json'=>'PRIVATE TEST CLAIM',
    ];
    $second = array_replace($doctor,['id'=>90002,'rank_order'=>2,'name'=>'Bác sĩ Dữ Liệu Mẫu','slug'=>'bac-si-du-lieu-mau','rating'=>0,'reviews_count'=>0,'verified'=>0,'image_url'=>'','gallery_json'=>'[]','fees_json'=>'[]','sources_json'=>'[]']);
    return [$doctor,$second];
}
function toplist_detail_test_render(array $rows, string $locale = 'vi', string $type = 'doctor'): string
{
    $toplistLanguage=$locale; $toplistEntityType=$type;
    $title=$locale === 'en' ? 'Doctor Toplist — preview' : 'Top bác sĩ mắt — bản kiểm thử';
    $description=$locale === 'en' ? 'Synthetic fixtures, not published medical data.' : 'Dữ liệu mẫu để kiểm tra bố cục, không xuất bản hồ sơ.';
    $heroImage='';
    $toplist=['content'=>'<h2>Giới thiệu</h2><p>Thông tin tham khảo trước khi lựa chọn.</p>','updated_at'=>'2026-10-13 12:00:00'];
    $tdLabels=medical_toplist_detail_labels($locale,$type === 'mixed');
    $tdMembers=medical_toplist_detail_members($rows,$locale);
    ob_start(); require dirname(__DIR__).'/Tem/toplist-doctor-detail-content.php'; return (string)ob_get_clean();
}
if (PHP_SAPI !== 'cli' || defined('TOPLIST_DETAIL_FIXTURE_ONLY')) return;
$checks=0;
$assert=static function(bool $condition,string $label) use (&$checks): void {$checks++; if (!$condition) throw new RuntimeException($label);};
$rows=toplist_detail_test_rows();
$members=medical_toplist_detail_members($rows,'vi');
$assert(array_column($members,'id') === [90001,90002], 'Stored ranking order preserved');
$assert($members[0]['profile_url'] === '/bac-si/bac-si-kiem-thu', 'Doctor profile URL uses clean public route');
$assert(strlen($members[0]['content']) > 260 && substr_count($members[0]['content'],'<p>') === 12, 'Full article is never reduced to a meta-description');
$assert($members[0]['gallery'][0]['caption'] === 'Bác sĩ' && $members[0]['gallery'][0]['alt'] === 'Chân dung bác sĩ', 'Cover retains caption and alt after gallery reorder');
$assert(count($members[0]['gallery']) === 2, 'Cover URL is deduplicated against gallery');
$angle=medical_toplist_detail_members([array_replace($rows[0],['gallery_json'=>'[{"url":"/uploads/test-portrait.jpg","caption":"","alt":"","angle":"Phòng khám"}]'])],'vi')[0];
$assert($angle['gallery'][0]['caption'] === 'Phòng khám' && $angle['gallery'][0]['alt'] === 'Phòng khám', 'Legacy angle remains a caption and alt fallback');
$assert($members[0]['has_rating'] && !$members[1]['has_rating'], 'Zero reviews does not invent a score');
$assert($members[0]['verified'] && !$members[1]['verified'], 'Verification only follows existing flags');
$assert($members[0]['price'] === '200.000 – 350.000 VNĐ / lượt', 'Known fee range is formatted from structured DB data');
$assert($members[1]['fees'] === [] && $members[1]['price'] === '', 'Unknown fees remain empty');
$unsafe=medical_toplist_detail_members([array_replace($rows[0],['content'=>'<script>alert(1)</script><p onclick="bad()">Safe text</p>','website_url'=>'javascript:alert(1)','image_url'=>'javascript:alert(1)','gallery_json'=>'[]'])],'vi')[0];
$assert($unsafe['website'] === '' && $unsafe['image'] === '' && !str_contains($unsafe['content'],'script') && !str_contains($unsafe['content'],'onclick'), 'Unsafe HTML and URL schemes removed');
$table=medical_toplist_detail_html('<table style="height:1000px"><tr><th colspan="2">Giá</th></tr><tr><td>Khám</td><td>200.000</td></tr></table><iframe src="bad"></iframe>');
$assert(str_contains($table,'<table>') && str_contains($table,'colspan="2"') && !str_contains($table,'height') && !str_contains($table,'iframe'), 'Existing article tables preserved without unsafe markup or whitespace styles');
$photos=medical_toplist_detail_html('<img src="/uploads/real.jpg" alt="Photo" onerror="bad()"><img src="javascript:alert(1)">');
$assert(substr_count($photos,'<img') === 1 && str_contains($photos,'alt="Photo"') && !str_contains($photos,'onerror'), 'Existing article images and alt remain, unsafe sources do not');
$fallback=medical_toplist_detail_members([array_replace($rows[0],['content'=>'','bio_json'=>'["Known bio paragraph"]'])],'vi')[0];
$assert(str_contains($fallback['content'],'Known bio paragraph'), 'Legacy biography shown when article is empty');
$ranks=medical_toplist_detail_members([array_replace($rows[0],['rank_order'=>0]),array_replace($rows[1],['rank_order'=>0])],'vi');
$assert(array_column($ranks,'rank') === [1,2], 'Legacy duplicate/zero ranks have unique anchors');
$html=toplist_detail_test_render($rows);
foreach (['td-comparison','td-article-content','td-fees','td-gallery','td-sidebar','td-mobile-toc','td-lightbox','tdGalleryData'] as $role) $assert(str_contains($html,$role), 'Facility-like detail role '.$role);
$assert(substr_count($html,'Nội dung hồ sơ đầy đủ') === 24, 'Both doctors retain their complete article content');
$assert(str_contains($html,'Phòng khám') && str_contains($html,'Chân dung bác sĩ'), 'Gallery metadata reaches lightbox data');
$assert(str_contains($html,'Chưa có đánh giá') && !str_contains($html,'0.0/5'), 'Honest review empty state');
$assert(!str_contains($html,'PRIVATE TEST') && !str_contains($html,'ai_writer_claim_json'), 'Private editorial/claim fields excluded');
$assert(str_contains($html,'href="#rank-2"') && str_contains($html,'id="rank-2"'), 'Comparison and contents link to real anchors');
$assert(str_contains($html,'scope="col"') && str_contains($html,'scope="row"') && str_contains($html,'role="region"'), 'Comparison table semantic and keyboard-scrollable');
$en=toplist_detail_test_render($rows,'en');
$assert(str_contains($en,'/en/bac-si/bac-si-kiem-thu') && str_contains($en,'Consultation fees') && str_contains($en,'Quick comparison') && !str_contains($en,'Chưa có đánh giá'), 'English interface and routes retained');
$facility=['id'=>90003,'member_type'=>'facility','rank_order'=>2,'name'=>'Cơ sở Kiểm Thử','slug'=>'co-so-kiem-thu','category'=>'Nha khoa','city'=>'Huế','content'=>'<p>Known facility article.</p>','verified'=>1];
$mixed=toplist_detail_test_render([$rows[0],$facility],'vi','mixed');
$assert(str_contains($mixed,'/bac-si/bac-si-kiem-thu') && str_contains($mixed,'/co-so-y-te/co-so-kiem-thu'), 'Mixed ranking keeps both typed profile links');
$assert(str_contains($mixed,'Cơ sở y tế &amp; bác sĩ') && str_contains($mixed,'/ho-so-da-xac-thuc'), 'Mixed labels and facility verification link retained');
$empty=toplist_detail_test_render([]);
$assert(str_contains($empty,'Danh sách đang được bổ sung') && !str_contains($empty,'class="td-comparison"') && !str_contains($empty,'class="td-sidebar"'), 'Empty lists show no fabricated providers or comparison');
$badNames=toplist_detail_test_render([array_replace($rows[0],['name'=>'<script>evil</script>','gallery_json'=>'[{"url":"/uploads/a.jpg","caption":"</script><script>bad()</script>"}]'])]);
$assert(!str_contains($badNames,'<script>evil') && !str_contains($badNames,'<script>bad()') && str_contains($badNames,'\\u003C'), 'Identity and embedded gallery JSON are escaped');
$css=(string)file_get_contents(dirname(__DIR__).'/assets/css/pages/toplist-doctors.css');
$js=(string)file_get_contents(dirname(__DIR__).'/assets/js/toplist-doctor-detail.js');
$assert(str_contains($css,'1320px') && str_contains($css,'calc(100% - 16px)') && str_contains($css,'max-width:72ch') && !str_contains($css,'max-width:none!important'), 'Shared desktop width, narrow mobile gutters and a readable prose measure');
$assert(str_contains($css,'background:transparent') && str_contains($css,'backdrop-filter:blur(24px)'), 'Lightbox is just the image over a blurred page');
$assert(str_contains($css,':focus-visible') && str_contains($css,'prefers-reduced-motion:reduce') && str_contains($js,'current !== token'), 'Focus, reduced motion and stale image loading safety');
echo "Toplist detail: {$checks} checks passed. No database or network used.\n";
