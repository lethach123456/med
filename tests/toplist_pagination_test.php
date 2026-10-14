<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/medical_toplist_directory_view.php';

function medical_public_entity_path(string $type, string $slug, string $locale): string
{ return ($locale === 'en' ? '/en' : '') . '/toplist/' . rawurlencode($slug); }
function medical_public_toplist_path(): string { return '/toplist'; }
function medical_public_facility_path(): string { return '/co-so-y-te'; }
function site_localized_path(string $path, string $locale): string
{ return ($locale === 'en' ? '/en' : '') . $path; }

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};
$rows = [];
for ($id = 1; $id <= 31; $id++) {
    $rows[] = ['id' => $id, 'title' => 'Bác sĩ mắt Hà Nội ' . $id, 'slug' => 'danh-sach-' . $id,
        'entity_type' => $id % 2 === 0 ? 'doctor' : 'facility', 'excerpt' => 'Thông tin để tham khảo',
        'member_count' => $id, 'updated_at' => '2026-10-13 12:00:00'];
}
$filters = toplist_view_filters([]);
foreach ([[], ['page' => 0], ['page' => -2], ['page' => 'bad'], ['page' => []], ['page' => '1.5'], ['page' => '1e3']] as $query) {
    $assert(toplist_view_requested_page($query) === 1, 'Invalid page falls back safely');
}
$assert(toplist_view_requested_page(['page' => '2']) === 2, 'Scalar page is accepted');
$pageOne = toplist_view_paginate($rows, $filters);
$pageTwo = toplist_view_paginate($rows, $filters, 2);
$pageThree = toplist_view_paginate($rows, $filters, 999999);
$assert($pageOne['total'] === 31 && $pageOne['pages'] === 3 && $pageOne['start'] === 1 && $pageOne['end'] === 12, 'First page range and total');
$assert(count($pageTwo['items']) === 12 && $pageTwo['start'] === 13 && $pageTwo['end'] === 24, 'Second page range');
$assert(count($pageThree['items']) === 7 && $pageThree['page'] === 3 && $pageThree['end'] === 31, 'Out of range clamps to final page');
$ids = array_merge(array_column($pageOne['items'], 'id'), array_column($pageTwo['items'], 'id'), array_column($pageThree['items'], 'id'));
$assert($ids === range(31, 1) && count(array_unique($ids)) === 31, 'Stable pages have no lost or duplicate cards');
$doctorFilters = toplist_view_filters(['type' => 'doctor', 'q' => 'bac si mat', 'sort' => 'members']);
$doctors = toplist_view_paginate($rows, $doctorFilters, 2);
$assert($doctors['total'] === 15 && count($doctors['items']) === 3 && $doctors['items'][0]['id'] === 6, 'Global filtering and sorting precede paging');
$none = toplist_view_paginate($rows, toplist_view_filters(['q' => 'no such title']), 3);
$assert($none['total'] === 0 && $none['page'] === 1 && $none['start'] === 0 && $none['end'] === 0 && $none['items'] === [], 'Empty matches have no invalid range');
$assert(toplist_view_paginate([], $filters, 999)['pages'] === 1, 'Empty collection remains valid');
$assert(count(toplist_view_paginate($rows, $filters, 1, 0)['items']) === 1, 'Per-page value cannot divide by zero');
$url = toplist_view_page_url('/en/toplist', $doctorFilters, 2);
$assert($url === '/en/toplist?q=bac+si+mat&type=doctor&sort=members&page=2', 'Page link retains locale, query, type and sort');
$assert(!str_contains(toplist_view_page_url('/toplist', $filters, 1), '?'), 'Page one omits default query');
$assert(!str_contains(toplist_view_page_url('/toplist', $doctorFilters + ['page' => 3]), 'page='), 'Filter changes discard stale page');
$pager = toplist_view_pagination('/en/toplist', $doctorFilters, $doctors, 'en');
$assert(str_contains($pager, 'Showing 13–15 of 15 lists') && str_contains($pager, 'aria-current="page"'), 'Range and current page accessible');
$assert(str_contains($pager, 'aria-label="Previous page"') && !str_contains($pager, 'aria-label="Next page"'), 'Final page does not link beyond available pages');
$assert(str_contains($pager, '/en/toplist?q=bac+si+mat&amp;type=doctor&amp;sort=members'), 'Pager escapes query ampersands');
$assert(toplist_view_pagination('/toplist', $filters, $none, 'vi') === '', 'No pager when nothing matches');
$single = toplist_view_paginate([$rows[0]], $filters);
$assert(!str_contains(toplist_view_pagination('/toplist', $filters, $single, 'vi'), 'data-toplist-page'), 'Single page has range only, no fake navigation');
$many = toplist_view_pagination('/toplist', $filters, ['total' => 200, 'start' => 85, 'end' => 96, 'page' => 8, 'pages' => 17], 'vi');
$assert(str_contains($many, 'tl-page-gap') && substr_count($many, 'data-toplist-page') <= 6, 'Large collections use bounded page controls');

$locale = 'vi';
$toplistFilters = $filters;
$toplistPaging = $pageTwo;
$toplistPageItems = $pageTwo['items'];
ob_start();
require dirname(__DIR__) . '/Tem/toplist-directory.php';
$html = (string) ob_get_clean();
$assert(substr_count($html, 'data-toplist-card') === 12 && !str_contains($html, 'data-id="31"'), 'SSR emits only current page cards');
$assert(str_contains($html, 'data-page="2" data-pages="3"') && str_contains($html, 'id="toplistPagination"'), 'SSR page metadata and pagination wrapper');
$assert(str_contains($html, '<strong>31</strong>') && str_contains($html, 'Hiển thị 13–24 / 31 danh sách'), 'Global result total and current range remain distinct');
$assert(str_contains($html, 'href="/toplist?page=3" data-toplist-page="3"'), 'Native links work without JavaScript');
$main = (string) file_get_contents(dirname(__DIR__) . '/toplist.php');
$assert(strpos($main, 'toplist_view_paginate(') < strpos($main, 'toplist_directory_linked_rows('), 'Only current page triggers collage lookups');
echo "Toplist pagination: {$checks} checks passed. No database or network used.\n";
