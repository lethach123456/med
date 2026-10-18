<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_search_cache.php';

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$ids = static fn(array $items): array => array_column($items, 'id');
$entity = static function (int $id, string $name, string $city, string $category, array $extra = []): array {
    return array_replace([
        'id' => $id, 'name' => $name, 'name_key' => medical_search_cache_normalize($name),
        'city' => $city, 'city_key' => medical_search_cache_normalize($city),
        'category' => $category, 'category_key' => medical_search_cache_normalize($category),
        'specialty_text' => $category, 'services' => [], 'has_content' => true,
        'slug' => 'profile-' . $id, 'url' => '/profile-' . $id, 'language_code' => 'vi',
        'rating' => 0, 'reviews_count' => 0,
        // These are deliberately misleading legacy body/citation tokens.
        'search_text' => 'nha khoa nhan khoa nguyen van huy ha noi hcm hn tim html href',
    ], $extra);
};
$toplist = static function (int $id, string $title, array $extra = []) use ($entity): array {
    return array_replace($entity($id, $title, '', ''), [
        'title' => $title, 'excerpt' => '',
        'search_text' => 'nha khoa ha noi hcm nguyen van huy bac si mat tim',
    ], $extra);
};
$facilities = [
    $entity(1, 'Nha Khoa Phú Gia', 'Hà Nội', 'Nha khoa', ['services' => ['Niềng răng', 'Bọc răng sứ'], 'rating' => 1]),
    $entity(2, 'Nha Khoa Phú Anh', 'Hà Nội', 'Nha khoa', ['rating' => 5, 'reviews_count' => 10000]),
    $entity(3, 'Nha Khoa Uy Tín', 'Hà Nội', 'Nha khoa'),
    $entity(4, 'Nha Khoa Sài Gòn', 'Đà Nẵng', 'Nha khoa'),
    $entity(5, 'Nha Khoa Xanh', 'TP. Hồ Chí Minh', 'Nha khoa'),
    $entity(6, 'Bệnh viện Mắt Hà Nội', 'Hà Nội', 'Nhãn khoa', ['services' => ['Khám mắt trẻ em', 'Phẫu thuật khúc xạ']]),
    $entity(7, 'Nha Khoa Bình Thu', 'Hà Nội', 'Răng hàm mặt', [
        'address' => '51 Nguyễn Văn Trỗi, huyện Gia Lâm',
        'services' => ['Khám và tư vấn răng hàm mặt', 'Huyết tương PRF'], 'rating' => 5,
    ]),
    $entity(8, 'Trung tâm Tim Mạch', 'Hà Nội', 'Tim mạch'),
    $entity(9, 'Seedtoken Facility', 'Hà Nội', 'Nha khoa', ['has_content' => false, 'rating' => 5]),
    $entity(10, 'Nha Khoa Thiên Minh', 'Hải Phòng', 'Nha khoa', ['services' => ['Niềng răng mắc cài', 'Bọc răng sứ']]),
    $entity(11, 'Nha Khoa Thiên Tâm', 'Hải Phòng', 'Nha khoa'),
    $entity(12, 'Saigon Dental', 'Ho Chi Minh City', 'Dentistry', ['language_code' => 'en']),
    $entity(13, 'Hanoi Eye Clinic', 'Hanoi', 'Ophthalmology', ['language_code' => 'en']),
    $entity(14, 'Nha Khoa Thiên Minh 2', 'Hải Phòng', 'Nha khoa', ['has_content' => null]),
    $entity(15, 'Nha khoa Dr Green', 'Hà Nội', 'Nha khoa'),
];
$doctors = [
    $entity(101, 'Nguyễn Văn Huy', 'Hà Nội', 'Nhãn khoa', ['services' => ['Khám mắt', 'Mắt trẻ em'], 'rating' => 1]),
    $entity(102, 'Nguyễn Văn Hữu', 'Hà Nội', 'Nhãn khoa', ['rating' => 5]),
    $entity(103, 'Phạm Thị Minh Châu', 'Hà Nội', 'Nhãn khoa'),
    $entity(104, 'Đỗ Gia Uy', 'Hà Nội', 'Nhãn khoa'),
    $entity(105, 'Seedtoken Doctor', 'Hà Nội', 'Nhãn khoa', ['has_content' => false]),
    $entity(106, 'Trần Minh Đức', 'TP. Hồ Chí Minh', 'Nha khoa', ['services' => ['Niềng răng', 'Cấy ghép Implant']]),
    $entity(107, 'Nguyễn Văn Huyền', 'Hải Phòng', 'Nhãn khoa'),
    $entity(108, 'John Smith', 'Hanoi', 'Ophthalmology', ['language_code' => 'en']),
    $entity(109, 'Bác sĩ Nhãn Khoa', 'TP. Hồ Chí Minh', 'Nhãn khoa', ['address' => 'Tòa nhà HYAT', 'services' => ['Khám mắt', 'Nhãn khoa']]),
];
$toplists = [
    $toplist(201, 'Top 5 nha khoa uy tín tại Hà Nội'),
    $toplist(202, 'Top nha khoa uy tín tại Bắc Kạn'),
    $toplist(203, 'Top bác sĩ mắt tại Hà Nội'),
    $toplist(204, 'Top nha khoa TP. Hồ Chí Minh'),
    $toplist(205, 'Các bác sĩ giàu kinh nghiệm', ['linked_search_text' => 'nguyen van huy nhan khoa']),
    $toplist(206, 'Best dentists in Hanoi', ['language_code' => 'en']),
];
$index = ['facilities' => $facilities, 'doctors' => $doctors, 'toplists' => $toplists,
    'cities' => medical_search_cache_cities($facilities, $doctors, [['city' => 'Bắc Kạn']])];
$vi = medical_search_cache_filter_locale($index, 'vi');
$original = $vi;
$find = static fn(string $q, int $limit = 24): array => medical_search_cache_search($vi, $q, $limit);

foreach (['Nha Khoa Phú Gia', 'nha khoa phu gia', 'NHA KHOA PHÚ GIA'] as $query) {
    $assert($ids($find($query)['groups']['facilities']) === [1], 'Accents/case and Phú/Gia name tokens: ' . $query);
}
$assert($ids($find('Nha Khoa Uy Tín')['groups']['facilities']) === [3], 'Exact Uy Tín brand is not stripped into a generic query');
$assert($ids($find('Đỗ Gia Uy')['groups']['doctors']) === [104], 'Gia/Uy remain part of a doctor name');
$assert($ids($find('Nha khoa Dr Green')['groups']['facilities']) === [15], 'Dr inside a facility brand is retained as part of its name');
$assert($find('Nha khoa Dr Green')['meta']['group_order'][0] === 'facilities', 'A Dr facility brand does not become a doctor intent');
$assert($ids($find('nha khoa thien mi')['groups']['facilities']) === [10], 'Only last unfinished token gets prefix matching');
$assert($find('nha kho thien minh')['groups']['facilities'] === [], 'An earlier incomplete word cannot silently match a different word');
$assert($find('khoa nho')['groups']['facilities'] === [], 'Suffixes in an unrelated word do not count as word matches');
$assert($ids($find('tim')['groups']['facilities']) === [8], 'A generic narrative occurrence cannot create a cardiology hit');

foreach (['Hà Nội', 'ha noi', 'HN', 'Hanoi', 'TP. Hà Nội'] as $city) {
    $result = $find('nha khoa ' . $city);
    $assert($result['groups']['facilities'] !== [], 'Hanoi alias returns facilities: ' . $city);
    $assert(array_unique(array_column($result['groups']['facilities'], 'city')) === ['Hà Nội'], 'Hanoi alias excludes other locations: ' . $city);
    $assert($ids($result['groups']['toplists']) === [201], 'Toplist location comes from title, not a body mention: ' . $city);
}
foreach (['HCM', 'TPHCM', 'TP HCM', 'Hồ Chí Minh', 'TP. Hồ Chí Minh', 'Ho Chi Minh City', 'Saigon', 'Sài Gòn'] as $city) {
    $result = $find('nha khoa tại ' . $city);
    $assert($ids($result['groups']['facilities']) === [5], 'Saigon alias resolves complete city phrase: ' . $city);
    $assert($ids($result['groups']['toplists']) === [204], 'Toplist accepts matching Saigon city alias: ' . $city);
}
$assert($find('nha khoa sai gon')['groups']['facilities'][0]['id'] === 4, 'An exact brand containing a city is not hidden by inferred location');
$assert($ids($find('nha khoa tại sai gon')['groups']['facilities']) === [5], 'An explicit city takes priority over the same words in a brand');
$assert($ids($find('nha khoa sai gon tại hà nội')['groups']['facilities']) === [], 'Explicit location after a brand is not mistaken for the brand city');

foreach (['Nguyễn Văn Huy', 'BS Nguyễn Văn Huy', 'bác sĩ Nguyễn Văn Huy', 'Dr Nguyễn Văn Huy', 'TS.BS Nguyễn Văn Huy', 'BS.CKII Nguyễn Văn Huy'] as $query) {
    $result = $find($query);
    $assert($result['groups']['doctors'][0]['id'] === 101, 'Exact doctor identity precedes similarly spelled names: ' . $query);
    $assert(!in_array(102, $ids($result['groups']['doctors']), true), 'Huy does not match Hữu via unrelated biography tokens: ' . $query);
    $assert($result['groups']['facilities'] === [], 'Doctor name is not assembled from street, tư vấn and huyện: ' . $query);
    $assert($result['meta']['group_order'][0] === 'doctors', 'Doctor identity determines first suggestion group: ' . $query);
}
$prefixNames = $ids($find('Nguyễn Văn Hu')['groups']['doctors']);
$assert(count($prefixNames) === 3 && in_array(101, $prefixNames, true) && in_array(102, $prefixNames, true), 'Incomplete final name word still autocompletes');
$assert($find('nha khoa')['groups']['doctors'][0]['id'] === 106, 'Dentistry finds a dentist rather than matching nha inside nhãn');
$assert(count($find('nha khoa')['groups']['doctors']) === 1, 'Nhãn khoa does not satisfy nha khoa word boundaries');
$assert($ids($find('nha khoa TPHCM')['groups']['doctors']) === [106], 'Nha khoa cannot be assembled from toa nha address plus nhan khoa specialty');
$assert($find('bs nguyen')['groups']['facilities'] === [], 'An explicit doctor query does not suggest facilities from a city or street containing Nguyen');
$assert($find('bs nguyen')['groups']['doctors'] !== [], 'Doctor intent retains matching doctors');
$assert($ids($find('Nguyễn Văn Trỗi')['groups']['facilities']) === [7], 'A complete street name remains searchable without the word duong');
$assert($find('bs Nguyễn Văn Trỗi')['groups']['facilities'] === [], 'An explicit doctor name is not treated as an address search');
foreach (['khám mắt Hà Nội', 'nhãn khoa HN', 'bác sĩ mắt Hà Nội'] as $query) {
    $result = $find($query);
    $assert($result['groups']['facilities'] === [] || $ids($result['groups']['facilities']) === [6], 'Eye intent excludes răng hàm mặt dentistry: ' . $query);
    $assert($result['groups']['doctors'] !== [] && !in_array(106, $ids($result['groups']['doctors']), true), 'Eye intent selects actual ophthalmologists: ' . $query);
}
$assert($find('bác sĩ mắt Hà Nội')['meta']['group_order'][0] === 'doctors', 'Explicit doctor eye search keeps doctors first');
$assert($ids($find('bác sĩ mắt Hà Nội')['groups']['toplists']) === [203], 'A specialty query can also suggest its relevant doctor toplist');
$assert($find('tìm bác sĩ Nguyễn tại HN')['groups']['doctors'] !== [], 'Search command is removed before doctor intent words');
$assert($ids($find('tìm phòng khám mắt tại HN')['groups']['facilities']) === [6], 'Search command and clinic words do not hide matching eye facilities');
$assert($find('top nha khoa Hà Nội')['meta']['group_order'][0] === 'toplists', 'Explicit toplist search keeps articles first');
$assert($ids($find('Niềng răng')['groups']['facilities']) === [1, 10], 'Service relevance is searchable on profile cards');
$assert($find('niềng răng')['groups']['facilities'][0]['service_text'] === 'Niềng răng', 'Most relevant service is returned for the card label');

$phraseIndex = ['facilities' => [
    $entity(301, 'Cơ sở dịch vụ chính xác', 'Hà Nội', 'Nha khoa', ['services' => ['Bọc răng sứ'], 'rating' => 1]),
    $entity(302, 'Cơ sở từ khóa rời rạc', 'Hà Nội', 'Nha khoa', ['services' => ['Niềng răng mắc cài sứ'], 'rating' => 5, 'reviews_count' => 10000]),
], 'doctors' => [], 'toplists' => [], 'cities' => []];
$assert($ids(medical_search_cache_search($phraseIndex, 'răng sứ', 24)['groups']['facilities']) === [301, 302], 'A full service phrase outranks scattered service tokens despite lower rating');
$phraseIndex['facilities'][1]['services'][] = 'Niềng răng mắc cài sứ';
$assert($ids(medical_search_cache_search($phraseIndex, 'răng sứ', 24)['groups']['facilities']) === [301, 302], 'Repeating a service cannot overtake the exact phrase match');

$brandIndex = ['facilities' => [
    $entity(401, 'Nha Khoa Mặt Trời', 'Hà Nội', 'Nha khoa'),
], 'doctors' => [], 'toplists' => [], 'cities' => []];
$assert($ids(medical_search_cache_search($brandIndex, 'Nha Khoa Mặt Trời', 4)['groups']['facilities']) === [401], 'An exact dental brand cannot be hidden by inferred khoa mat specialty');
$assert($ids(medical_search_cache_search($brandIndex, 'nha khoa mat troi', 4)['groups']['facilities']) === [401], 'Exact accent-free brand also wins over inferred specialty');

$assert($find('Seedtoken')['groups']['facilities'] === [] && $find('Seedtoken')['groups']['doctors'] === [], 'Blank profiles are hidden in both groups');
$assert(!in_array(14, $ids($find('thiên minh')['groups']['facilities']), true), 'Missing content eligibility fails closed');
$limited = $find('nha khoa', 1);
$assert(count($limited['groups']['facilities']) === 1 && $limited['meta']['totals']['facilities'] > 1, 'Total counts are calculated before suggestion limit');
$assert($find('')['groups'] === ['facilities' => [], 'doctors' => [], 'toplists' => []], 'Empty query remains an empty suggestion result');
$assert($find('bác sĩ')['groups']['facilities'] === [], 'A doctor-only intent is not expanded to all facilities');

$en = medical_search_cache_filter_locale($index, 'en');
$english = medical_search_cache_search($en, 'eye doctor Hanoi', 24);
$assert($ids($english['groups']['doctors']) === [108], 'English doctor/specialty/city intent works without Vietnamese content');
$assert($english['meta']['group_order'][0] === 'doctors', 'English doctor intent places the correct group first');
$assert(medical_search_cache_search($en, 'nha khoa', 24)['groups']['facilities'] === [], 'Vietnamese profiles do not leak into English suggestions');
$assert($vi === $original, 'Search does not mutate the shared cached snapshot');

// Directory-specific semantics remain independent from the stricter autocomplete.
$directory = medical_search_cache_directory_search($vi, ['q' => 'nha khoa uy tín Hà Nội', 'limit' => 24]);
$assert($directory['paging']['total'] > 0, 'Existing directory natural query still works');
$assert(!in_array(9, $ids($directory['items']), true), 'Directory continues to hide unwritten profiles');

echo "Public medical search: {$checks} checks passed. No DB connection, cache writes or network requests.\n";
