<?php
declare(strict_types=1);

// Read-only presentation helpers. No database connection or schema work.
function toplist_view_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function toplist_view_fold(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');
    $groups = ['a'=>'àáạảãâầấậẩẫăằắặẳẵ','e'=>'èéẹẻẽêềếệểễ','i'=>'ìíịỉĩ',
        'o'=>'òóọỏõôồốộổỗơờớợởỡ','u'=>'ùúụủũưừứựửữ','y'=>'ỳýỵỷỹ','d'=>'đ'];
    foreach ($groups as $letter => $characters) {
        $value = str_replace(mb_str_split($characters), $letter, $value);
    }
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function toplist_view_filters(array $query): array
{
    $scalar = static fn(string $key): string => is_scalar($query[$key] ?? '') ? (string) ($query[$key] ?? '') : '';
    return ['q'=>mb_substr(trim($scalar('q')), 0, 120),
        'type'=>in_array($scalar('type'), ['facility','doctor','mixed'], true) ? $scalar('type') : '',
        'sort'=>in_array($scalar('sort'), ['members','title'], true) ? $scalar('sort') : 'updated'];
}

function toplist_view_matches(array $row, array $filters): bool
{
    if ($filters['type'] !== '' && ($row['entity_type'] ?? 'facility') !== $filters['type']) return false;
    return $filters['q'] === '' || str_contains(toplist_view_fold((string) ($row['title'] ?? '') . ' ' . (string) ($row['excerpt'] ?? '')), toplist_view_fold($filters['q']));
}

function toplist_view_sort(array $rows, string $sort): array
{
    usort($rows, static function (array $a, array $b) use ($sort): int {
        $recent = (strtotime((string) ($b['updated_at'] ?? '')) ?: 0) <=> (strtotime((string) ($a['updated_at'] ?? '')) ?: 0);
        $order = match ($sort) {
            'members' => (int) ($b['member_count'] ?? 0) <=> (int) ($a['member_count'] ?? 0),
            'title' => strcmp(toplist_view_fold((string) ($a['title'] ?? '')), toplist_view_fold((string) ($b['title'] ?? ''))),
            default => $recent,
        };
        return $order ?: ($recent ?: ((int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0)));
    });
    return $rows;
}

function toplist_view_page_url(string $path, array $filters): string
{
    $query = array_filter($filters, static fn($value, $key): bool => $value !== '' && !($key === 'sort' && $value === 'updated'), ARRAY_FILTER_USE_BOTH);
    return $path . ($query !== [] ? '?' . http_build_query($query) : '');
}

function toplist_view_copy(string $locale): array
{
    return $locale === 'en' ? [
        'home'=>'Home','kicker'=>'A CLEARER PLACE TO START','title'=>'Curated lists.','titleAccent'=>'More perspective for your choice.',
        'intro'=>'Explore healthcare facilities and doctors through focused lists. Compare their profiles before reaching out.',
        'note'=>'A shorter list.<br><strong>A clearer starting point.</strong>','articles'=>'published lists','profiles'=>'profiles across lists',
        'search'=>'Search Toplists','placeholder'=>'Specialty, doctor or location…','all'=>'All lists','facility'=>'Healthcare facilities','doctor'=>'Doctors','mixed'=>'Facilities & doctors',
        'resultTitle'=>'Lists to explore','matches'=>'matching lists','sort'=>'Sort by','updated'=>'Recently updated','members'=>'Most profiles','titleSort'=>'Title A–Z',
        'details'=>'Explore list','updatedPrefix'=>'Updated','member'=>'profiles','fallback'=>'Get to know the profiles, services and shared experiences in this list.',
        'sideKicker'=>'TAKE A CLOSER LOOK','sideTitle'=>'Less searching.<br>More understanding.','sideCopy'=>'A list is a starting point. Get to know each profile before deciding.',
        'browseFacilities'=>'Explore facilities','browseDoctors'=>'Explore doctors','guideTitle'=>'Before you choose','guideOne'=>'Match the specialty to your needs.','guideTwo'=>'Read profiles and shared experiences.','guideThree'=>'Confirm services and costs directly.',
        'about'=>'How MedReview works','emptyTitle'=>'No matching lists yet','emptyCopy'=>'Try another keyword or explore all published lists.','clear'=>'View all lists',
        'disclaimer'=>'Lists provide a starting point, not medical advice or a guarantee of treatment quality.',
    ] : [
        'home'=>'Trang chủ','kicker'=>'GÓC NHÌN CHỌN LỌC','title'=>'Một danh sách ngắn.','titleAccent'=>'Khởi đầu cho lựa chọn tốt.',
        'intro'=>'Khám phá cơ sở y tế và bác sĩ theo chuyên khoa, nhu cầu và khu vực. Có thêm góc nhìn trước khi lựa chọn.',
        'note'=>'Bớt tìm kiếm.<br><strong>Thêm góc nhìn.</strong>','articles'=>'danh sách đã xuất bản','profiles'=>'hồ sơ trong các danh sách',
        'search'=>'Tìm bài Toplist','placeholder'=>'Chuyên khoa, bác sĩ, thành phố…','all'=>'Tất cả','facility'=>'Cơ sở y tế','doctor'=>'Bác sĩ','mixed'=>'Cơ sở & bác sĩ',
        'resultTitle'=>'Gợi ý để bạn khám phá','matches'=>'danh sách phù hợp','sort'=>'Sắp xếp','updated'=>'Mới cập nhật','members'=>'Nhiều hồ sơ','titleSort'=>'Tiêu đề A–Z',
        'details'=>'Xem danh sách','updatedPrefix'=>'Cập nhật','member'=>'hồ sơ','fallback'=>'Tìm hiểu hồ sơ, dịch vụ và những trải nghiệm được chia sẻ trong danh sách.',
        'sideKicker'=>'HIỂU RÕ TRƯỚC KHI CHỌN','sideTitle'=>'Đọc danh sách.<br>Hiểu lựa chọn.','sideCopy'=>'Bắt đầu từ một gợi ý, tìm hiểu từng hồ sơ để chọn nơi phù hợp với bạn.',
        'browseFacilities'=>'Khám phá cơ sở y tế','browseDoctors'=>'Tìm hiểu bác sĩ','guideTitle'=>'Một chút rõ ràng,<br>thêm nhiều an tâm.','guideOne'=>'Đối chiếu chuyên khoa với nhu cầu.','guideTwo'=>'Đọc hồ sơ và trải nghiệm thực tế.','guideThree'=>'Xác nhận dịch vụ, chi phí trực tiếp.',
        'about'=>'Cách MedReview hoạt động','emptyTitle'=>'Chưa có danh sách phù hợp','emptyCopy'=>'Thử từ khóa khác hoặc khám phá tất cả danh sách đã xuất bản.','clear'=>'Xem tất cả danh sách',
        'disclaimer'=>'Toplist là nguồn tham khảo, không thay thế tư vấn y khoa hoặc bảo đảm chất lượng điều trị.',
    ];
}

function toplist_view_card(array $row, string $locale, bool $visible = true): string
{
    $e = 'toplist_view_escape';
    $copy = toplist_view_copy($locale);
    $type = in_array($row['entity_type'] ?? '', ['facility','doctor','mixed'], true) ? $row['entity_type'] : 'facility';
    $icon = ['facility'=>'hospital','doctor'=>'stethoscope','mixed'=>'squares-four'][$type];
    $url = medical_public_entity_path('toplist', (string) ($row['slug'] ?? ''), $locale);
    $images = array_values(array_unique(array_filter((array) ($row['collage_images'] ?? []), static fn($src): bool => is_string($src) && preg_match('~^(?:https?://|/(?!/))~i', $src) === 1)));
    if ($images === [] && preg_match('~^(?:https?://|/(?!/))~i', (string) ($row['featured_image_url'] ?? ''))) $images[] = $row['featured_image_url'];
    $images = array_slice($images, 0, 4);
    $updated = strtotime((string) ($row['updated_at'] ?? '')) ?: 0;
    ob_start(); ?>
    <article class="tl-card" data-toplist-card data-type="<?= $type ?>" data-search="<?= $e(toplist_view_fold((string) ($row['title'] ?? '') . ' ' . (string) ($row['excerpt'] ?? ''))) ?>" data-title="<?= $e(toplist_view_fold((string) ($row['title'] ?? ''))) ?>" data-updated="<?= $updated ?>" data-members="<?= max(0, (int) ($row['member_count'] ?? 0)) ?>" data-id="<?= (int) ($row['id'] ?? 0) ?>"<?= !$visible ? ' hidden' : '' ?>>
      <a class="tl-media" href="<?= $e($url) ?>" aria-label="<?= $e($row['title'] ?? '') ?>">
        <span class="tl-media-fallback" aria-hidden="true"><i class="ph ph-<?= $icon ?>"></i><span>MedReview · Toplist</span></span>
        <?php if ($images !== []): ?><span class="tl-collage" data-count="<?= count($images) ?>"><?php foreach ($images as $image): ?><span class="tl-collage-cell"><img src="<?= $e($image) ?>" alt="" loading="lazy" decoding="async"></span><?php endforeach; ?></span><?php endif; ?>
        <span class="tl-type"><i class="ph ph-<?= $icon ?>" aria-hidden="true"></i><?= $e($copy[$type]) ?></span><span class="tl-media-arrow" aria-hidden="true"><i class="ph ph-arrow-up-right"></i></span>
      </a>
      <div class="tl-card-body"><h3><a href="<?= $e($url) ?>"><?= $e($row['title'] ?? '') ?></a></h3><p class="tl-excerpt"><?= $e(trim((string) ($row['excerpt'] ?? '')) ?: $copy['fallback']) ?></p>
        <?php if ($updated > 0): ?><p class="tl-updated"><i class="ph ph-clock" aria-hidden="true"></i><?= $e($copy['updatedPrefix']) ?> <time datetime="<?= date('Y-m-d', $updated) ?>"><?= date('d/m/Y', $updated) ?></time></p><?php endif; ?>
        <div class="tl-card-footer"><span><i class="ph ph-list-bullets" aria-hidden="true"></i><strong><?= max(0, (int) ($row['member_count'] ?? 0)) ?></strong> <?= $e($copy['member']) ?></span><a class="tl-button" href="<?= $e($url) ?>"><?= $e($copy['details']) ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a></div>
      </div>
    </article>
    <?php return (string) ob_get_clean();
}
