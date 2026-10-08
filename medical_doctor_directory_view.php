<?php
declare(strict_types=1);

// Presentation only: no database or cache bootstrap.
function doctor_directory_card(array $item, ?string $locale = null): string
{
    $locale ??= function_exists('site_page_locale') ? site_page_locale('doctors') : 'vi';
    $isEnglish = $locale === 'en';
    $escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $slug = (string) ($item['slug'] ?? '');
    $url = (string) ($item['url'] ?? medical_public_entity_path('doctor', $slug, $locale));
    $image = trim((string) ($item['image'] ?? ''));
    $rating = min(5.0, max(0.0, (float) ($item['rating'] ?? 0)));
    $reviewCount = max(0, (int) ($item['reviews_count'] ?? 0));
    $services = array_values(array_filter(array_map('strval', (array) ($item['services'] ?? []))));
    $showServiceDisclosure = count($services) > 3 || count(array_filter($services, static fn(string $s): bool => mb_strlen($s, 'UTF-8') > 24)) > 0;
    ob_start(); ?>
    <article class="doctor-card">
      <a class="doctor-media" href="<?php echo $escape($url); ?>" aria-label="<?php echo $isEnglish ? 'View profile of ' : 'Xem hồ sơ '; ?><?php echo $escape($item['name'] ?? 'doctor'); ?>">
        <?php if ($image !== ''): ?><img src="<?php echo $escape($image); ?>" alt="<?php echo $escape($item['name'] ?? ''); ?>" loading="lazy" decoding="async"><?php else: ?><span class="doctor-media-empty"><i class="ph ph-user-circle"></i></span><?php endif; ?>
      </a>
      <div class="doctor-profile">
        <div class="doctor-eyebrow">
          <?php if (!empty($item['specialty_text'])): ?><span><?php echo $escape($item['specialty_text']); ?></span><?php endif; ?>
          <?php if (!empty($item['city'])): ?><i>·</i><span><?php echo $escape($item['city']); ?></span><?php endif; ?>
        </div>
        <div class="doctor-name-row">
          <h2><a href="<?php echo $escape($url); ?>"><?php echo $escape($item['name'] ?? ''); ?></a></h2>
          <?php if (!empty($item['verified'])): ?><span class="doctor-verified" title="<?php echo $isEnglish ? 'Verified profile' : 'Hồ sơ đã xác thực'; ?>"><i class="ph-fill ph-seal-check"></i><span><?php echo $isEnglish ? 'Verified' : 'Đã xác thực'; ?></span></span><?php endif; ?>
        </div>
        <?php if (!empty($item['title_text'])): ?><p class="doctor-subtitle"><?php echo $escape($item['title_text']); ?></p><?php endif; ?>
        <div class="doctor-details">
          <?php if (!empty($item['facility_name'])): ?><span><i class="ph ph-hospital"></i><?php echo $escape($item['facility_name']); ?></span><?php endif; ?>
          <?php if (!empty($item['hours'])): ?><span><i class="ph ph-clock"></i><?php echo $escape($item['hours']); ?></span><?php endif; ?>
        </div>
        <?php if ($services !== []): ?><div class="doctor-tags<?= $showServiceDisclosure ? ' is-collapsible' : '' ?>" data-doctor-tags><?php foreach ($services as $index => $service): ?><span class="doctor-tag<?php echo $index > 2 ? ' is-extra' : ''; ?>" title="<?= $escape($service) ?>"><i class="ph ph-stethoscope" aria-hidden="true"></i><span><?php echo $escape($service); ?></span></span><?php endforeach; ?><?php if ($showServiceDisclosure): ?><button type="button" class="doctor-tags-more" data-doctor-tags-more data-extra-count="<?php echo max(0, count($services) - 3); ?>" aria-expanded="false" aria-label="<?php echo $isEnglish ? 'Show full specialties' : 'Xem đầy đủ chuyên môn'; ?>"><span data-tags-label><?= count($services) > 3 ? '+' . (count($services) - 3) : ($isEnglish ? 'Details' : 'Xem đủ') ?></span><i class="ph ph-caret-down" aria-hidden="true"></i></button><?php endif; ?></div><?php endif; ?>
      </div>
      <div class="doctor-card-footer">
        <div class="doctor-score">
          <?php if ($rating > 0 && $reviewCount > 0): ?><i class="ph-fill ph-star" aria-hidden="true"></i><strong><?php echo number_format($rating, 1); ?><small>/5</small></strong><span><?php echo number_format($reviewCount, 0, $isEnglish ? '.' : ',', $isEnglish ? ',' : '.'); ?> <?php echo $isEnglish ? 'reviews' : 'đánh giá'; ?></span><?php else: ?><i class="ph ph-star" aria-hidden="true"></i><span><?php echo $isEnglish ? 'Not rated yet' : 'Chưa có đánh giá'; ?></span><?php endif; ?>
        </div>
      <div class="doctor-price"><span><?php echo $isEnglish ? 'Consultation fee' : 'Chi phí khám'; ?></span><strong><?php echo !empty($item['price']) ? $escape($item['price']) : ($isEnglish ? 'Contact for updates' : 'Liên hệ cập nhật'); ?></strong></div>
        <a class="doctor-action" href="<?php echo $escape($url); ?>"><?php echo $isEnglish ? 'View profile' : 'Xem hồ sơ'; ?><i class="ph ph-arrow-right" aria-hidden="true"></i></a>
      </div>
    </article>
    <?php return trim((string) ob_get_clean());
}
