<?php
declare(strict_types=1);

// Presentation only: no DB queries, schema changes or cache rebuilding.
function facility_directory_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function facility_directory_safe_url(string $value, string $fallback = ''): string
{
    $value = trim($value);
    if (preg_match('/[\x00-\x20\\\\]/', $value)) return $fallback;
    if (preg_match('~^/(?!/)~', $value) || preg_match('~^https?://[^/]+~i', $value)) return $value;
    return $fallback;
}

function facility_directory_number(int $value, string $locale): string
{
    return number_format(max(0, $value), 0, '.', $locale === 'en' ? ',' : '.');
}

function facility_directory_card(array $item, string $locale = 'vi'): string
{
    $en = $locale === 'en';
    $e = 'facility_directory_escape';
    $name = trim((string) ($item['name'] ?? ''));
    $path = ($en ? '/en' : '') . '/co-so-y-te/' . rawurlencode((string) ($item['slug'] ?? ''));
    $url = facility_directory_safe_url((string) ($item['url'] ?? ''), $path);
    $image = facility_directory_safe_url((string) ($item['image'] ?? ''));
    $rating = max(0.0, min(5.0, (float) ($item['rating'] ?? 0)));
    $reviews = facility_directory_number((int) ($item['reviews_count'] ?? 0), $locale);
    $services = array_values(array_filter(array_map('strval', (array) ($item['services'] ?? [])), static fn(string $s): bool => trim($s) !== ''));
    $showServiceDisclosure = count($services) > 3 || count(array_filter($services, static fn(string $s): bool => mb_strlen($s, 'UTF-8') > 24)) > 0;
    ob_start(); ?>
    <article class="fd-card">
      <a class="fd-media" href="<?= $e($url) ?>" aria-label="<?= $e(($en ? 'View ' : 'Xem ') . $name) ?>">
        <span class="fd-media-fallback" aria-hidden="true"><i class="ph ph-hospital"></i><span>MedReview</span></span>
        <?php if ($image !== ''): ?><img src="<?= $e($image) ?>" alt="<?= $e($name) ?>" width="360" height="300" loading="lazy" decoding="async"><?php endif; ?>
      </a>
      <div class="fd-card-content">
        <div class="fd-card-category"><?= $e($item['category'] ?? ($en ? 'Healthcare facility' : 'Cơ sở y tế')) ?><?php if (!empty($item['city'])): ?><span aria-hidden="true">·</span><?= $e($item['city']) ?><?php endif; ?></div>
        <h2><a href="<?= $e($url) ?>"><?= $e($name) ?></a><?php if (!empty($item['verified'])): ?><span class="fd-verified" role="img" aria-label="<?= $en ? 'Verified profile' : 'Hồ sơ đã xác thực' ?>" title="<?= $en ? 'Verified profile' : 'Hồ sơ đã xác thực' ?>"><i class="ph-fill ph-seal-check" aria-hidden="true"></i></span><?php endif; ?></h2>
        <?php if (!empty($item['address'])): ?><p class="fd-address"><i class="ph ph-map-pin" aria-hidden="true"></i><span><?= $e($item['address']) ?></span></p><?php endif; ?>
        <?php if (!empty($item['subtitle'])): ?><p class="fd-summary"><?= $e($item['subtitle']) ?></p><?php endif; ?>
        <?php if ($services !== []): ?><div class="fd-services" data-service-tags>
          <?php foreach ($services as $i => $service): ?><span class="fd-service<?= $i > 2 ? ' fd-service-extra' : '' ?>"<?= $i > 2 ? ' hidden' : '' ?> title="<?= $e($service) ?>"><i class="ph ph-stethoscope" aria-hidden="true"></i><span><?= $e($service) ?></span></span><?php endforeach; ?>
          <?php if ($showServiceDisclosure): ?><button class="fd-service-more" type="button" data-service-tags-more data-extra-count="<?= max(0, count($services) - 3) ?>" aria-expanded="false" aria-label="<?= $en ? 'Show full services' : 'Xem đầy đủ dịch vụ' ?>"><?= count($services) > 3 ? '+' . (count($services) - 3) : ($en ? 'Details' : 'Xem đủ') ?><i class="ph ph-caret-down" aria-hidden="true"></i></button><?php endif; ?>
        </div><?php endif; ?>
        <div class="fd-card-footer">
          <div class="fd-rating"><?php if ($rating > 0): ?><i class="ph-fill ph-star" aria-hidden="true"></i><strong><?= number_format($rating, 1, '.', '') ?><span>/5</span></strong><span class="fd-review-count">(<?= $reviews ?> <?= $en ? 'reviews' : 'đánh giá' ?>)</span><?php else: ?><span class="fd-unrated"><?= $en ? 'Not rated yet' : 'Chưa có đánh giá' ?></span><?php endif; ?></div>
          <a class="fd-profile-link" href="<?= $e($url) ?>"><?= $en ? 'View profile' : 'Xem hồ sơ' ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        </div>
        <?php if (!empty($item['price'])): ?><p class="fd-price"><?= $en ? 'Reference price: ' : 'Giá tham khảo: ' ?><?= $e($item['price']) ?></p><?php endif; ?>
      </div>
    </article>
    <?php return trim((string) ob_get_clean());
}

function facility_directory_page_url(string $path, array $filters, int $page): string
{
    $query = array_filter($filters, static fn($v): bool => $v !== '' && $v !== 'recommended');
    if ($page > 1) $query['page'] = $page;
    return $path . ($query !== [] ? '?' . http_build_query($query) : '');
}

function facility_directory_pagination(string $path, array $filters, int $page, int $pages, string $locale): string
{
    if ($pages <= 1) return '';
    $e = 'facility_directory_escape';
    $en = $locale === 'en';
    $link = static function (int $target, string $label, string $content, bool $current = false) use ($e, $path, $filters): string {
        return '<a href="' . $e(facility_directory_page_url($path, $filters, $target)) . '" data-page="' . $target . '" aria-label="' . $e($label) . '"' . ($current ? ' aria-current="page"' : '') . '>' . $content . '</a>';
    };
    $html = $page > 1 ? $link($page - 1, $en ? 'Previous page' : 'Trang trước', '<i class="ph ph-arrow-left" aria-hidden="true"></i>') : '';
    $last = 0;
    for ($i = 1; $i <= $pages; $i++) {
        if ($i !== 1 && $i !== $pages && abs($i - $page) > 1) continue;
        if ($last > 0 && $i - $last > 1) $html .= '<span class="fd-page-gap" aria-hidden="true">…</span>';
        $html .= $link($i, ($en ? 'Page ' : 'Trang ') . $i, (string) $i, $i === $page);
        $last = $i;
    }
    return $html . ($page < $pages ? $link($page + 1, $en ? 'Next page' : 'Trang sau', '<i class="ph ph-arrow-right" aria-hidden="true"></i>') : '');
}
