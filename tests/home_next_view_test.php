<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/medical_home_next_view.php';
require dirname(__DIR__) . '/medical_home_next_data.php';

// Presentation-only tests: no bootstrap, sessions, migration, or DB connection.
function site_localized_path(string $path, string $locale): string { return ($locale === 'en' ? '/en' : '') . $path; }
function medical_public_entity_path(string $type, string $slug, string $locale): string {
    return site_localized_path(['facility'=>'/co-so-y-te/', 'doctor'=>'/bac-si/', 'toplist'=>'/toplist/'][$type] . rawurlencode($slug), $locale);
}
function render_home_next(array $data, string $locale = 'vi'): string {
    $homeNextData = $data;
    $homeNextCopy = medical_home_next_copy($locale);
    $heroImage = '/uploads/example.jpg';
    ob_start();
    require dirname(__DIR__) . '/Tem/home-next.php';
    return (string) ob_get_clean();
}
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException($label);
};
$assert(home_next_region('Hanoi') === 'hanoi' && home_next_region('Hà Nội') === 'hanoi', 'Vietnamese and English Hanoi');
$assert(home_next_region('Danang') === 'danang' && home_next_region('Ho Chi Minh City') === 'hcm', 'English city filters');
$assert(home_next_region('Huế') === 'other', 'Unknown city is not silently misclassified');
$assert(home_next_summary('<p>A &amp; B</p>', 'Fallback') === 'A & B', 'Plain text summary');
$assert(home_next_summary('', 'Fallback') === 'Fallback', 'Empty summary fallback');
$assert(mb_strlen(home_next_summary(str_repeat('đ', 150), '', 30)) === 30, 'Unicode truncation stays bounded');
$vi = medical_home_next_copy('vi');
$en = medical_home_next_copy('en');
$assert(array_keys($vi) === array_keys($en), 'Matching VI/EN UI dictionary');
$assert(count($vi['specialtyItems']) === 6 && count($en['specialtyItems']) === 6, 'Six accessible specialties in each language');
$assert($en['specialtyItems'][0][2] === 'dental' && $en['specialtyItems'][2][2] === 'dermatology', 'English directory searches use translated terms');
$empty = render_home_next(medical_home_next_empty());
$assert(substr_count($empty, '<h1 ') === 1, 'One homepage H1');
$assert(str_contains($empty, 'data-medical-search') && str_contains($empty, 'hero-search-shell'), 'Shared search integration hooks');
$assert(str_contains($empty, 'type="button" class="send-button"') && str_contains($empty, 'data-medical-search-results hidden'), 'In-place send and initially hidden results');
$assert(str_contains($empty, 'href="/"') && str_contains($empty, 'Xem trang chủ hiện tại'), 'Old homepage remains separately accessible');
$assert(!str_contains($empty, 'class="hn-facility hn-reveal"'), 'Empty database does not fabricate facilities');
$assert(str_contains($empty, 'data-home-next-empty') && str_contains($empty, 'hn-directory-invite'), 'Graceful empty directory states');
$english = render_home_next(medical_home_next_empty(), 'en');
$assert(str_contains($english, 'data-locale="en"') && str_contains($english, 'href="/en/co-so-y-te?q=dental"'), 'Localized directory links');
$assert(str_contains($english, 'Illustrative image') && str_contains($english, 'Your health.'), 'English hero and image disclosure');
$data = medical_home_next_empty();
$facility = medical_home_next_facility_row(['id'=>7,'slug'=>'clinic','name'=>'Clinic <script>alert(1)</script>','city'=>'Hanoi','subtitle'=>'<p>Plain text</p>','rating'=>4.7,'reviews_count'=>9,'verified'=>1,'image_url'=>'/uploads/a.jpg']);
$data['facilities'] = array_fill(0, 4, $facility);
$data['doctors'] = [medical_home_next_doctor_row(['id'=>8,'slug'=>'doctor','name'=>'Doctor <b>name</b>','specialty_text'=>'Eyes','title_text'=>'Specialist'])];
$data['toplists'] = [medical_home_next_toplist_row(['id'=>9,'slug'=>'list','title'=>'List <img src=x onerror=alert(1)>','excerpt'=>'<p>Real details</p>','entity_type'=>'mixed','facility_count'=>1,'doctor_count'=>1])];
$rendered = render_home_next($data);
$assert(!str_contains($rendered, '<script>alert') && str_contains($rendered, '&lt;script&gt;'), 'Facility database text is escaped');
$assert(str_contains($rendered, '&lt;b&gt;name&lt;/b&gt;') && str_contains($rendered, '&lt;img src=x onerror=alert(1)&gt;'), 'Doctor and Toplist titles are escaped');
$assert(substr_count($rendered, 'loading="lazy"') === 4 && str_contains($rendered, 'fetchpriority="high"'), 'Below-fold photos lazy load; hero is prioritized');
$assert(str_contains($rendered, 'role="img" aria-label="Hồ sơ đã xác thực"') && str_contains($rendered, '(9 đánh giá)'), 'Real verification and review information');
$assert(str_contains($rendered, 'data-facility-id="7" hidden') && str_contains($rendered, 'data-region="hanoi"'), 'Initial facilities limited to three with region hooks');
$data['facilities'][0]['reviews_count'] = 0;
$data['facilities'] = [$data['facilities'][0]];
$unrated = render_home_next($data);
$assert(str_contains($unrated, 'Chưa có đánh giá') && !str_contains($unrated, '<strong>4.7</strong>'), 'No rating rendered without reviews');
$entry = file_get_contents(dirname(__DIR__) . '/homepage-new.php');
$assert(str_contains($entry, 'noindex,follow') && str_contains($entry, 'X-Robots-Tag: noindex, follow'), 'Preview is excluded from indexing');
$assert(!str_contains($entry, "'/index.php'") && !str_contains($entry, "'/Tem/home.php'"), 'Preview does not replace/include old homepage');
echo "Home next view: {$checks} checks passed. No DB connection or records modified.\n";
