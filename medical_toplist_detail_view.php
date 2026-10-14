<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_doctor_profile.php';

function medical_toplist_detail_labels(string $locale, bool $mixed): array
{
    $labels = $locale === 'en' ? [
        'home'=>'Home', 'article'=>'Doctor Toplist', 'count'=>'doctors', 'list'=>'Doctors in this list',
        'updated'=>'Updated', 'intro'=>'About this list', 'compare'=>'Quick comparison', 'toc'=>'Contents',
        'compareCopy'=>'Compare specialties, practice locations and available consultation fees before opening each profile.',
        'name'=>'Doctor', 'specialty'=>'Specialty', 'workplace'=>'Practice location', 'city'=>'City', 'category'=>'Provider type',
        'price'=>'Reference fee', 'rating'=>'Reviews', 'noReviews'=>'No reviews yet', 'reviews'=>'reviews',
        'doctor'=>'Doctor', 'facility'=>'Medical facility', 'profile'=>'View doctor profile', 'facilityProfile'=>'View facility profile',
        'verified'=>'Verified profile', 'address'=>'Address', 'phone'=>'Phone', 'website'=>'Website', 'hours'=>'Consultation hours',
        'fees'=>'Consultation fees', 'service'=>'Service', 'fee'=>'Reference fee', 'feeNote'=>'Reference information; confirm the current fees and availability directly with the provider.',
        'sources'=>'Sources', 'photos'=>'View photos', 'gallery'=>'Profile photos', 'close'=>'Close photo', 'previous'=>'Previous photo', 'next'=>'Next photo',
        'photoError'=>'This photo could not be loaded.', 'photo'=>'Photo', 'swipe'=>'Swipe the table to compare more information',
        'empty'=>'Doctor profiles are being added to this list.',
        'note'=>'This list is a starting point for research, not medical advice or a guarantee of treatment quality. Confirm credentials, fees and availability directly with the provider.',
    ] : [
        'home'=>'Trang chủ', 'article'=>'Toplist bác sĩ', 'count'=>'bác sĩ', 'list'=>'Bác sĩ trong danh sách',
        'updated'=>'Cập nhật', 'intro'=>'Giới thiệu danh sách', 'compare'=>'So sánh nhanh', 'toc'=>'Mục lục',
        'compareCopy'=>'Đối chiếu chuyên khoa, nơi công tác và chi phí tham khảo trước khi xem hồ sơ chi tiết.',
        'name'=>'Bác sĩ', 'specialty'=>'Chuyên khoa', 'workplace'=>'Nơi công tác', 'city'=>'Thành phố', 'category'=>'Nhóm cơ sở',
        'price'=>'Chi phí tham khảo', 'rating'=>'Đánh giá', 'noReviews'=>'Chưa có đánh giá', 'reviews'=>'đánh giá',
        'doctor'=>'Bác sĩ', 'facility'=>'Cơ sở y tế', 'profile'=>'Xem hồ sơ bác sĩ', 'facilityProfile'=>'Xem hồ sơ cơ sở',
        'verified'=>'Hồ sơ đã xác thực', 'address'=>'Địa chỉ', 'phone'=>'Điện thoại', 'website'=>'Website', 'hours'=>'Lịch khám',
        'fees'=>'Chi phí khám & dịch vụ', 'service'=>'Dịch vụ', 'fee'=>'Giá tham khảo', 'feeNote'=>'Thông tin tham khảo; xác nhận chi phí và lịch khám hiện tại trực tiếp với nơi khám.',
        'sources'=>'Nguồn tham khảo', 'photos'=>'Xem ảnh', 'gallery'=>'Thư viện ảnh hồ sơ', 'close'=>'Đóng ảnh', 'previous'=>'Ảnh trước', 'next'=>'Ảnh tiếp theo',
        'photoError'=>'Không thể tải ảnh này.', 'photo'=>'Ảnh', 'swipe'=>'Vuốt bảng để xem thêm thông tin so sánh',
        'empty'=>'Danh sách đang được bổ sung hồ sơ bác sĩ.',
        'note'=>'Danh sách là nguồn tham khảo, không thay thế tư vấn y khoa hoặc bảo đảm chất lượng điều trị. Hãy đối chiếu bằng cấp, chi phí và lịch khám trực tiếp với nơi công tác.',
    ];
    if ($mixed) {
        $labels['article'] = $locale === 'en' ? 'Healthcare Toplist' : 'Toplist y tế';
        $labels['count'] = $locale === 'en' ? 'providers' : 'hồ sơ';
        $labels['name'] = $locale === 'en' ? 'Provider' : 'Hồ sơ';
        $labels['list'] = $locale === 'en' ? 'Facilities & doctors in this list' : 'Cơ sở y tế & bác sĩ trong danh sách';
        $labels['empty'] = $locale === 'en' ? 'Provider profiles are being added to this list.' : 'Danh sách đang được bổ sung cơ sở y tế và bác sĩ.';
    }
    return $labels;
}

/** Safe editorial HTML, retaining tables already present in Toplist/facility articles. */
function medical_toplist_detail_html(string $html): string
{
    if (!class_exists('DOMDocument')) return medical_doctor_sanitize_html($html);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="toplist-safe-root">' . $html . '</div>', LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    $root = $dom->getElementById('toplist-safe-root');
    if (!$root) return '';
    $allowed = ['p','div','span','h2','h3','h4','ul','ol','li','strong','b','em','i','br','a','blockquote','img','figure','figcaption',
        'table','caption','thead','tbody','tfoot','tr','th','td'];
    $walk = static function (DOMNode $node) use (&$walk, $allowed): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) continue;
            if (!$child instanceof DOMElement) { $node->removeChild($child); continue; }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script','style','iframe','object','embed','svg','math','form','input','button'], true)) { $node->removeChild($child); continue; }
            $walk($child);
            if (!in_array($tag, $allowed, true)) {
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child); continue;
            }
            $href = $tag === 'a' ? medical_doctor_profile_url($child->getAttribute('href')) : '';
            $image = $tag === 'img' ? medical_doctor_profile_url($child->getAttribute('src'), true) : '';
            $alt = $tag === 'img' ? $child->getAttribute('alt') : '';
            $span = [];
            if (in_array($tag, ['th','td'], true)) foreach (['colspan','rowspan'] as $key) {
                $value = $child->getAttribute($key);
                if (ctype_digit($value) && (int) $value >= 1 && (int) $value <= 20) $span[$key] = $value;
            }
            foreach (iterator_to_array($child->attributes) as $attribute) $child->removeAttribute($attribute->name);
            if ($tag === 'img') {
                if ($image === '') { $node->removeChild($child); continue; }
                $child->setAttribute('src', $image); $child->setAttribute('alt', $alt);
                $child->setAttribute('loading', 'lazy'); $child->setAttribute('decoding', 'async');
            }
            if ($href !== '') { $child->setAttribute('href', $href); $child->setAttribute('rel', 'nofollow noopener noreferrer'); }
            foreach ($span as $key=>$value) $child->setAttribute($key, $value);
        }
    };
    $walk($root); $result = '';
    foreach ($root->childNodes as $child) $result .= $dom->saveHTML($child);
    return trim($result);
}

/** Read-only models for doctor and mixed Toplists. Keep the raw ranking order. */
function medical_toplist_detail_members(array $rows, string $locale): array
{
    $members = []; $ranks = [];
    foreach ($rows as $index => $row) {
        $doctor = ($row['member_type'] ?? 'doctor') === 'doctor';
        $profile = $doctor ? medical_doctor_profile_model(medical_directory_doctor_from_row($row)) : null;
        $rank = max(1, (int) ($row['rank_order'] ?? $index + 1));
        while (isset($ranks[$rank])) $rank++;
        $ranks[$rank] = true;
        $gallery = []; $seen = [];
        $append = static function (mixed $value) use (&$gallery, &$seen): void {
            $entry = is_array($value) ? $value : ['url' => $value];
            $url = medical_doctor_profile_url($entry['url'] ?? $entry['src'] ?? '', true);
            if ($url === '') return;
            $url = site_absolute_media_url($url);
            if ($url === '' || isset($seen[$url])) return;
            $seen[$url] = true;
            $caption = medical_doctor_profile_text($entry['caption'] ?? '') ?: medical_doctor_profile_text($entry['angle'] ?? '');
            $gallery[] = ['url' => $url, 'caption' => $caption,
                'alt' => medical_doctor_profile_text($entry['alt'] ?? '') ?: $caption];
        };
        // Rich gallery metadata takes precedence over the URL-only cover entry.
        $rawGallery = $row['gallery_json'] ?? [];
        if (is_string($rawGallery)) $rawGallery = json_decode($rawGallery, true);
        foreach (is_array($rawGallery) && array_is_list($rawGallery) ? $rawGallery : [] as $entry) $append($entry);
        $cover = medical_doctor_profile_url($row['image_url'] ?? '', true);
        if ($cover !== '') {
            $cover = site_absolute_media_url($cover);
            $append($cover);
            usort($gallery, static fn(array $a, array $b): int => ($b['url'] === $cover ? 1 : 0) <=> ($a['url'] === $cover ? 1 : 0));
        }
        if ($cover === '') $cover = $gallery[0]['url'] ?? '';
        $tags = $doctor ? $profile['specialties'] : medical_directory_json_decode((string) ($row['services_json'] ?? ''), []);
        if ($doctor && $tags === []) $tags = array_column($profile['services_json'], 'name');
        $tags = array_slice(array_values(array_unique(array_filter(array_map('medical_doctor_profile_text', (array) $tags)))), 0, 6);
        $locations = $profile['locations'] ?? [];
        $workplace = medical_doctor_profile_text($row['facility_name'] ?? '') ?: medical_doctor_profile_text($locations[0]['facility_name'] ?? '');
        $content = medical_toplist_detail_html((string) ($row['content'] ?? ''));
        if (trim(strip_tags($content)) === '' && $doctor) {
            $content = implode('', array_map(static fn(string $text): string => '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>', $profile['bio']));
        }
        $rating = max(0, min(5, (float) ($row['rating'] ?? 0)));
        $reviews = max(0, (int) ($row['reviews_count'] ?? 0));
        $fees = $profile['fees_json'] ?? [];
        $price = medical_doctor_profile_text($row['price_text'] ?? '');
        if ($price === '' && $fees !== []) $price = medical_doctor_profile_fee($fees[0], $locale === 'en');
        $members[] = [
            'id' => (int) ($row['id'] ?? 0), 'rank' => $rank, 'type' => $doctor ? 'doctor' : 'facility',
            'name' => medical_doctor_profile_text($row['name'] ?? ''),
            'profile_url' => medical_public_entity_path($doctor ? 'doctor' : 'facility', (string) ($row['slug'] ?? ''), $locale),
            'title' => medical_doctor_profile_text($doctor ? ($row['title_text'] ?? '') : ($row['subtitle'] ?? '')),
            'specialty' => medical_doctor_profile_text($doctor ? ($row['specialty_text'] ?? '') : ($row['category'] ?? '')),
            'workplace' => $workplace, 'city' => medical_doctor_profile_text($row['city'] ?? ''),
            'address' => medical_doctor_profile_text($row['address_text'] ?? '') ?: medical_doctor_profile_text($locations[0]['address_text'] ?? ''),
            'phone' => medical_doctor_profile_text($row['phone_text'] ?? ''), 'phone_url' => medical_doctor_profile_phone($row['phone_text'] ?? ''),
            'website' => medical_doctor_profile_url($row['website_url'] ?? ''),
            'hours' => medical_doctor_profile_text($row['hours_text'] ?? ''),
            'price' => $price, 'fees' => $fees, 'tags' => $tags, 'content' => $content,
            'image' => $cover, 'gallery' => $gallery, 'verified' => (int) ($row['verified'] ?? 0) === 1,
            'rating' => $rating, 'reviews' => $reviews, 'has_rating' => $reviews > 0 && $rating > 0,
            'sources' => $profile['sources'] ?? [],
        ];
    }
    return $members;
}

function medical_toplist_detail_icon(string $name): string
{
    $paths = [
        'arrow' => '<path d="M4 12h16m-6-6 6 6-6 6"/>', 'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'left' => '<path d="m15 6-6 6 6 6"/>', 'right' => '<path d="m9 6 6 6-6 6"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4M17 3v4M3 11h18"/>',
        'person' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>', 'photo' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1"/><path d="m3 18 6-6 4 4 4-5 4 5"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? $paths['info']) . '</svg>';
}
