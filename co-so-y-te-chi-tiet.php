<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_directory.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }

$incomingFacilitySlug = trim((string) ($_GET['slug'] ?? ''));
medical_redirect_legacy_path(
  '/co-so-y-te-chi-tiet.php',
  medical_public_facility_path($incomingFacilitySlug),
  ['slug']
);

$facilities = [
  [
    'slug' => 'top-dental-clinic',
    'category' => 'Nha khoa',
    'name' => 'Top Dental Clinic',
    'verified' => true,
    'subtitle' => 'Nha khoa thẩm mỹ công nghệ cao - Nụ cười tự tin, tỏa sáng',
    'rating' => '4.9',
    'reviews' => '658 đánh giá',
    'followers' => '12.648 lượt quan tâm',
    'tags' => ['Nha khoa', 'Đà Nẵng', '10+ năm kinh nghiệm'],
    'address' => '46 Trần Tống, P. Thanh Khê, Q. Thanh Khê, Đà Nẵng',
    'phone' => '0935 678 910',
    'website' => 'https://topdentaldanang.com',
    'hours' => '08:00 - 20:00 (T2 - CN)',
    'hero_image' => 'https://images.unsplash.com/photo-1629909615184-74f495363b67?auto=format&fit=crop&w=1400&q=80',
    'gallery' => [
      'https://images.unsplash.com/photo-1629909615184-74f495363b67?auto=format&fit=crop&w=1200&q=80',
      'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=1200&q=80',
      'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=1200&q=80',
      'https://images.unsplash.com/photo-1643489184648-3f6d0f1e1d14?auto=format&fit=crop&w=1200&q=80',
      'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=1200&q=80',
    ],
    'intro' => [
      'Top Dental Clinic là hệ thống nha khoa thẩm mỹ uy tín tại Đà Nẵng, chuyên sâu về răng sứ thẩm mỹ, implant, niềng răng và các dịch vụ nha khoa tổng quát.',
      'Với đội ngũ bác sĩ giàu kinh nghiệm, trang thiết bị hiện đại và vật liệu chính hãng, chúng tôi cam kết mang đến nụ cười khỏe mạnh, chuẩn thẩm mỹ, tự nhiên và bền vững cho từng khách hàng.',
    ],
    'stats' => [
      ['icon' => 'clock-3', 'value' => '10+', 'label' => 'Năm kinh nghiệm'],
      ['icon' => 'users', 'value' => '20.000+', 'label' => 'Khách hàng hài lòng'],
      ['icon' => 'shield-check', 'value' => '100%', 'label' => 'Vật liệu chính hãng'],
      ['icon' => 'sparkles', 'value' => '50.000+', 'label' => 'Ca điều trị thành công'],
    ],
    'services' => [
      [
        'title' => 'Răng sứ thẩm mỹ',
        'subtitle' => 'Dáng răng tự nhiên, tôn sáng',
        'rating' => '4.9',
        'reviews' => '(658)',
        'price' => 'Từ 1.000.000đ',
        'image' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=900&q=80',
      ],
      [
        'title' => 'Trồng răng Implant',
        'subtitle' => 'Cấy ghép trụ Implant chính hãng',
        'rating' => '4.9',
        'reviews' => '(658)',
        'price' => 'Từ 12.000.000đ',
        'image' => 'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=900&q=80',
      ],
      [
        'title' => 'Niềng răng',
        'subtitle' => 'Niềng mắc cài, trong suốt',
        'rating' => '4.9',
        'reviews' => '(658)',
        'price' => 'Từ 25.000.000đ',
        'image' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=900&q=80',
      ],
      [
        'title' => 'Tẩy trắng răng',
        'subtitle' => 'Công nghệ Laser Whitening',
        'rating' => '4.9',
        'reviews' => '(658)',
        'price' => 'Từ 2.500.000đ',
        'image' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=900&q=80',
      ],
    ],
    'review_summary' => [
      'rating' => '4.9',
      'reviews' => '658 đánh giá',
      'breakdown' => [
        ['label' => '5 sao', 'value' => 92],
        ['label' => '4 sao', 'value' => 7],
        ['label' => '3 sao', 'value' => 1],
        ['label' => '2 sao', 'value' => 0],
        ['label' => '1 sao', 'value' => 0],
      ],
    ],
    'reviews_list' => [
      [
        'author' => 'Nguyễn Thị Lan',
        'verified' => 'Đã xác thực',
        'date' => '2 ngày trước',
        'service' => 'Răng sứ thẩm mỹ',
        'content' => 'Mình làm răng sứ ở đây rất ưng ý, bác sĩ tư vấn tận tâm, răng làm xong tự nhiên lắm!',
        'likes' => 12,
        'comments' => 1,
        'images' => [
          'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=400&q=80',
          'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=400&q=80',
          'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=400&q=80',
        ],
      ],
      [
        'author' => 'Trần Văn Minh',
        'verified' => 'Đã xác thực',
        'date' => '1 tuần trước',
        'service' => 'Trồng răng Implant',
        'content' => 'Trồng răng Implant không đau, bác sĩ rất nhẹ tay. Phòng khám sạch sẽ, lịch hẹn rõ ràng.',
        'likes' => 8,
        'comments' => 0,
        'images' => [
          'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=400&q=80',
          'https://images.unsplash.com/photo-1629909615184-74f495363b67?auto=format&fit=crop&w=400&q=80',
          'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=400&q=80',
        ],
      ],
      [
        'author' => 'Lê Thu Hương',
        'verified' => 'Đã xác thực',
        'date' => '2 tuần trước',
        'service' => 'Niềng răng',
        'content' => 'Niềng răng ở đây được 1 năm, răng đều đẹp lên mỗi ngày. Cảm ơn bác sĩ nhiều!',
        'likes' => 6,
        'comments' => 0,
        'images' => [
          'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=400&q=80',
          'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=400&q=80',
        ],
      ],
    ],
    'utilities' => [
      'Chỗ đậu ô tô',
      'Wifi miễn phí',
      'Thanh toán thẻ',
      'Trả góp 0% lãi suất',
    ],
    'features' => [
      ['icon' => 'badge-check', 'title' => 'Đánh giá xác thực', 'text' => 'Tất cả đánh giá đều từ khách hàng đã sử dụng dịch vụ thực tế'],
      ['icon' => 'shield-check', 'title' => 'Kiểm duyệt nghiêm ngặt', 'text' => 'Nội dung được kiểm duyệt để đảm bảo minh bạch và khách quan'],
      ['icon' => 'lock', 'title' => 'Bảo mật thông tin', 'text' => 'Thông tin cá nhân của bạn được bảo vệ tuyệt đối'],
      ['icon' => 'refresh-cw', 'title' => 'Cập nhật liên tục', 'text' => 'Đánh giá mới nhất được cập nhật mỗi ngày'],
    ],
  ],
  [
    'slug' => 'nha-khoa-rang-ngoi',
    'category' => 'Nha khoa',
    'name' => 'Nha Khoa Răng Ngời',
    'verified' => true,
    'subtitle' => 'Nụ cười rạng ngời - Tự tin tỏa sáng',
    'rating' => '4.8',
    'reviews' => '512 đánh giá',
    'followers' => '8.342 lượt quan tâm',
    'tags' => ['Nha khoa', 'Đà Nẵng', 'Thẩm mỹ'],
    'address' => '82 Lê Đình Lý, Q. Thanh Khê, Đà Nẵng',
    'phone' => '0905 123 456',
    'website' => 'https://nhakhoarangngoi.vn',
    'hours' => '08:00 - 19:30 (T2 - CN)',
    'hero_image' => 'https://images.unsplash.com/photo-1588776814546-daab30f310ce?auto=format&fit=crop&w=1400&q=80',
  ],
  [
    'slug' => 'smile-dental-clinic',
    'category' => 'Nha khoa',
    'name' => 'Smile Dental Clinic',
    'verified' => true,
    'subtitle' => 'Công nghệ hiện đại - Chuẩn quốc tế',
    'rating' => '4.7',
    'reviews' => '438 đánh giá',
    'followers' => '6.105 lượt quan tâm',
    'tags' => ['Nha khoa', 'Quốc tế', 'Implant'],
    'address' => '157 Nguyễn Văn Linh, Q. Hải Châu, Đà Nẵng',
    'phone' => '0898 765 432',
    'website' => 'https://smiledental.vn',
    'hours' => '08:30 - 20:00 (T2 - CN)',
    'hero_image' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=1400&q=80',
  ],
  [
    'slug' => 'happy-dental',
    'category' => 'Nha khoa',
    'name' => 'Happy Dental',
    'verified' => true,
    'subtitle' => 'Nha khoa chuẩn mực - Nụ cười hạnh phúc',
    'rating' => '4.6',
    'reviews' => '367 đánh giá',
    'followers' => '4.987 lượt quan tâm',
    'tags' => ['Nha khoa', 'Gia đình', 'Tổng quát'],
    'address' => '129 Hoàng Diệu, Q. Hải Châu, Đà Nẵng',
    'phone' => '0911 246 810',
    'website' => 'https://happydental.vn',
    'hours' => '08:00 - 19:00 (T2 - CN)',
    'hero_image' => 'https://images.unsplash.com/photo-1643489184648-3f6d0f1e1d14?auto=format&fit=crop&w=1400&q=80',
  ],
];

function facility_detail_normalize(array $item): array
{
  $heroImage = trim((string) ($item['hero_image'] ?? $item['image_url'] ?? $item['image'] ?? ''));

  // The stored gallery may contain {url, angle, caption, source}. The gallery
  // UI deliberately consumes URLs, while the metadata remains intact in DB.
  $gallery = medical_directory_gallery_urls((array) ($item['gallery'] ?? []));
  // Ảnh đại diện cũng là một ảnh của cơ sở: luôn đưa vào gallery nếu chưa có.
  // Không tạo ảnh thay thế để người xem chỉ thấy ảnh thực tế đã lưu.
  if ($heroImage !== '' && !in_array($heroImage, $gallery, true)) {
    array_unshift($gallery, $heroImage);
  }
  $gallery = array_values(array_unique($gallery));
  if ($gallery === [] && $heroImage !== '') {
    $gallery[] = $heroImage;
  }
  if ($heroImage === '' && isset($gallery[0])) {
    $heroImage = (string) $gallery[0];
  }

  $services = [];
  foreach ((array) ($item['services_detail'] ?? $item['services'] ?? []) as $service) {
    if (!is_array($service)) {
      $title = trim((string) $service);
      if ($title === '') {
        continue;
      }
      $services[] = [
        'title' => $title,
        'subtitle' => '',
        'rating' => (string) ($item['rating'] ?? '0.0'),
        'reviews' => '',
        'price' => '',
        'image' => $heroImage,
      ];
      continue;
    }

    $services[] = [
      'title' => trim((string) ($service['title'] ?? 'Dịch vụ y tế')),
      'subtitle' => trim((string) ($service['subtitle'] ?? '')),
      'rating' => trim((string) ($service['rating'] ?? ($item['rating'] ?? '0.0'))),
      'reviews' => trim((string) ($service['reviews'] ?? '')),
      'price' => trim((string) ($service['price'] ?? '')),
      'image' => trim((string) ($service['image'] ?? $heroImage)),
    ];
  }

  $reviewSummary = (array) ($item['review_summary'] ?? []);
  $reviewSummary['rating'] = (string) ($reviewSummary['rating'] ?? ($item['rating'] ?? '0.0'));
  $reviewSummary['reviews'] = (string) ($reviewSummary['reviews'] ?? ($item['reviews'] ?? '0 đánh giá'));
  if (!isset($reviewSummary['breakdown']) || !is_array($reviewSummary['breakdown'])) {
    $reviewSummary['breakdown'] = [
      ['label' => '5 sao', 'value' => 92],
      ['label' => '4 sao', 'value' => 7],
      ['label' => '3 sao', 'value' => 1],
      ['label' => '2 sao', 'value' => 0],
      ['label' => '1 sao', 'value' => 0],
    ];
  }

  $reviewsList = [];
  foreach ((array) ($item['reviews_list'] ?? []) as $review) {
    if (!is_array($review)) {
      continue;
    }
    $images = [];
    foreach ((array) ($review['images'] ?? []) as $image) {
      $image = trim((string) $image);
      if ($image !== '') {
        $images[] = $image;
      }
    }
    $reviewsList[] = [
      'author' => trim((string) ($review['author'] ?? 'Khách hàng')),
      'verified' => trim((string) ($review['verified'] ?? '')),
      'is_verified' => !empty($review['is_verified']) || !empty($review['verified']),
      'location' => trim((string) ($review['location'] ?? '')),
      'date' => trim((string) ($review['date'] ?? '')),
      'rating' => (string) ($review['rating'] ?? '0.0'),
      'service' => trim((string) ($review['service'] ?? '')),
      'source' => trim((string) ($review['source'] ?? '')),
      'content' => trim((string) ($review['content'] ?? '')),
      'likes' => (int) ($review['likes'] ?? 0),
      'comments' => (int) ($review['comments'] ?? 0),
      'images' => $images,
      'slug' => trim((string) ($review['slug'] ?? '')),
    ];
  }

  $stats = [];
  foreach ((array) ($item['stats'] ?? []) as $stat) {
    if (!is_array($stat)) {
      continue;
    }
    $stats[] = [
      'icon' => trim((string) ($stat['icon'] ?? 'badge-check')),
      'value' => trim((string) ($stat['value'] ?? '')),
      'label' => trim((string) ($stat['label'] ?? '')),
    ];
  }

  $features = [];
  foreach ((array) ($item['features'] ?? []) as $feature) {
    if (!is_array($feature)) {
      continue;
    }
    $features[] = [
      'icon' => trim((string) ($feature['icon'] ?? 'shield-check')),
      'title' => trim((string) ($feature['title'] ?? 'Ưu điểm')),
      'text' => trim((string) ($feature['text'] ?? '')),
    ];
  }

  return array_merge($item, [
    'verified' => !empty($item['verified']) || !empty($item['is_verified']),
    'hero_image' => $heroImage,
    'gallery' => $gallery,
    'services' => $services,
    'intro' => array_values(array_filter(array_map('trim', (array) ($item['intro'] ?? [])), static fn(string $value): bool => $value !== '')),
    'tags' => array_values(array_filter(array_map('trim', (array) ($item['tags'] ?? [])), static fn(string $value): bool => $value !== '')),
    'utilities' => array_values(array_filter(array_map('trim', (array) ($item['utilities'] ?? [])), static fn(string $value): bool => $value !== '')),
    'stats' => $stats,
    'review_summary' => $reviewSummary,
    'reviews_list' => $reviewsList,
    'features' => $features,
    'images_label' => trim((string) ($item['images_label'] ?? $item['images'] ?? '25+ ảnh')),
    'reviews' => (string) ($item['reviews'] ?? '0 đánh giá'),
    'followers' => (string) ($item['followers'] ?? '0 lượt quan tâm'),
    'address' => (string) ($item['address'] ?? $item['address_text'] ?? ''),
    'phone' => (string) ($item['phone'] ?? $item['phone_text'] ?? ''),
    'website' => (string) ($item['website'] ?? $item['website_url'] ?? ''),
    'hours' => (string) ($item['hours'] ?? $item['hours_text'] ?? ''),
  ]);
}

function facility_detail_is_logo_asset(string $url): bool
{
  $path = (string) (parse_url($url, PHP_URL_PATH) ?? $url);
  return preg_match('/(?:^|[\\/_-])(logo|logotype|brand)(?:[\\/_.-]|$)/i', $path) === 1;
}

function facility_detail_preferred_cover_image(array $item): string
{
  $gallery = (array) ($item['gallery'] ?? []);
  $frontImage = '';
  $otherImage = '';

  foreach ($gallery as $entry) {
    $url = is_array($entry)
      ? trim((string) ($entry['url'] ?? $entry['src'] ?? ''))
      : trim((string) $entry);
    if ($url === '' || facility_detail_is_logo_asset($url)) {
      continue;
    }
    $meta = is_array($entry)
      ? mb_strtolower(trim((string) ($entry['angle'] ?? '') . ' ' . (string) ($entry['caption'] ?? '')), 'UTF-8')
      : '';
    if ($frontImage === '' && (str_contains($meta, 'mặt tiền') || str_contains($meta, 'biển hiệu'))) {
      $frontImage = $url;
    }
    if ($otherImage === '') {
      $otherImage = $url;
    }
  }

  if ($frontImage !== '') {
    return $frontImage;
  }

  $imageUrl = trim((string) ($item['image_url'] ?? $item['image'] ?? ''));
  if ($imageUrl !== '' && !facility_detail_is_logo_asset($imageUrl)) {
    return $imageUrl;
  }

  if ($otherImage !== '') {
    return $otherImage;
  }

  $aiImage = trim((string) ($item['ai_image_url'] ?? ''));
  if ($aiImage !== '') {
    return $aiImage;
  }

  return $imageUrl;
}

function facility_detail_string_list(mixed $values): array
{
  if (!is_array($values)) {
    $values = [$values];
  }
  $result = [];
  foreach ($values as $value) {
    $value = trim(is_scalar($value) ? (string) $value : '');
    if ($value !== '' && !in_array($value, $result, true)) {
      $result[] = $value;
    }
  }
  return $result;
}

function facility_detail_safe_url(string $url): string
{
  $url = trim($url);
  return filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url) ? $url : '';
}

/**
 * Remove presentation-only spacing from AI/editor supplied price HTML so
 * inline heights, cell padding, and blank spacer rows cannot stretch mobile
 * tables. Keep content and non-layout formatting intact.
 */
function facility_detail_compact_price_html(string $html): string
{
  $html = trim($html);
  if ($html === '') {
    return '';
  }

  $html = preg_replace('~<style\b[^>]*>.*?</style\s*>~is', '', $html) ?? $html;
  $html = preg_replace_callback('/<([a-z][a-z0-9:-]*)\b([^>]*)>/iu', static function (array $match): string {
    $tag = strtolower($match[1]);
    $attributes = $match[2];

    if (in_array($tag, ['table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'], true)) {
      $attributes = preg_replace('/\s+(?:height|width)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $attributes) ?? $attributes;
    }

    $attributes = preg_replace_callback('/\sstyle\s*=\s*(["\'])(.*?)\1/isu', static function (array $styleMatch) use ($tag): string {
      $keptDeclarations = [];
      foreach (explode(';', $styleMatch[2]) as $declaration) {
        $declaration = trim($declaration);
        if ($declaration === '' || !str_contains($declaration, ':')) {
          continue;
        }
        [$property] = explode(':', $declaration, 2);
        $property = strtolower(trim($property));
        $isTableStructure = in_array($tag, ['table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'], true);
        $isTableLayoutOverride = $isTableStructure && preg_match('/^(?:display|position|top|right|bottom|left|inset|vertical-align)$/i', $property);
        if ($isTableLayoutOverride || preg_match('/^(?:height|min-height|max-height|line-height|padding(?:-[a-z-]+)?|margin(?:-[a-z-]+)?)$/i', $property)) {
          continue;
        }
        $keptDeclarations[] = $declaration;
      }

      if ($keptDeclarations === []) {
        return '';
      }
      $quote = $styleMatch[1];
      return ' style=' . $quote . implode(';', $keptDeclarations) . $quote;
    }, $attributes) ?? $attributes;

    return '<' . $tag . $attributes . '>';
  }, $html) ?? $html;

  // These bounded substitutions do not scan from one cell start to a later
  // closing tag, so malformed/unclosed cells cannot make this quadratic.
  $html = preg_replace('~<p\b[^>]*>(?:\s|&nbsp;|&#0*160;|&#x0*a0;|<br\b[^>]*>)*</p\s*>~iu', '', $html) ?? $html;
  $html = preg_replace('~(?:\s*<br\b[^>]*>\s*){2,}~iu', '<br>', $html) ?? $html;

  // AI/editor HTML can contain spacer wrappers such as <div><br></div> or
  // entire blank table rows. CSS height:auto cannot shrink those structures,
  // so remove only nodes with no text/media content using the HTML parser.
  if (class_exists(DOMDocument::class) && preg_match('~<table\b~i', $html)) {
    $previousLibxmlState = libxml_use_internal_errors(true);
    try {
      $document = new DOMDocument('1.0', 'UTF-8');
      $loaded = $document->loadHTML(
        '<?xml encoding="UTF-8"><!doctype html><html><head><meta charset="utf-8"></head><body>' .
        '<div id="facility-price-fragment">' . $html . '</div></body></html>',
        LIBXML_NONET | LIBXML_HTML_NODEFDTD
      );
      $fragment = $loaded ? $document->getElementById('facility-price-fragment') : null;

      if ($fragment instanceof DOMElement) {
        $meaningfulContentCache = [];
        $hasMeaningfulContent = null;
        $hasMeaningfulContent = static function (DOMNode $node) use (&$hasMeaningfulContent, &$meaningfulContentCache): bool {
          $nodeId = spl_object_id($node);
          if (array_key_exists($nodeId, $meaningfulContentCache)) {
            return $meaningfulContentCache[$nodeId];
          }
          if ($node instanceof DOMText || $node instanceof DOMCdataSection) {
            $text = str_replace(["\xc2\xa0", "\xe2\x80\x8b", "\xef\xbb\xbf"], '', $node->nodeValue ?? '');
            return $meaningfulContentCache[$nodeId] = trim($text) !== '';
          }
          if (!$node instanceof DOMElement) {
            return $meaningfulContentCache[$nodeId] = false;
          }

          if (in_array(strtolower($node->tagName), ['img', 'svg', 'video', 'audio', 'iframe', 'object', 'canvas', 'input'], true)) {
            return $meaningfulContentCache[$nodeId] = true;
          }
          foreach ($node->childNodes as $child) {
            if ($hasMeaningfulContent($child)) {
              return $meaningfulContentCache[$nodeId] = true;
            }
          }
          return $meaningfulContentCache[$nodeId] = false;
        };

        $nodes = [];
        foreach ($fragment->getElementsByTagName('*') as $node) {
          $nodes[] = $node;
        }
        for ($index = count($nodes) - 1; $index >= 0; $index--) {
          $node = $nodes[$index];
          if (!$node instanceof DOMElement || !$node->parentNode) {
            continue;
          }
          $tag = strtolower($node->tagName);
          $isEmptyWrapper = in_array($tag, ['div', 'p', 'span', 'small', 'font'], true);
          if (($isEmptyWrapper || $tag === 'tr') && !$hasMeaningfulContent($node)) {
            $node->parentNode->removeChild($node);
          }
        }

        // Preserve source headings and the actual column count so mobile keeps
        // the original HTML table with equal-width columns.
        $tables = [];
        foreach ($fragment->getElementsByTagName('table') as $table) {
          $tables[] = $table;
        }
        $fallbackLabels = ['Nhóm dịch vụ', 'Hạng mục', 'Giá', 'Ghi chú'];
        foreach ($tables as $table) {
          $headerRow = null;
          $bodyRows = [];
          foreach ($table->childNodes as $section) {
            if (!$section instanceof DOMElement) {
              continue;
            }
            $sectionTag = strtolower($section->tagName);
            if ($sectionTag === 'thead' && $headerRow === null) {
              foreach ($section->childNodes as $row) {
                if ($row instanceof DOMElement && strtolower($row->tagName) === 'tr') {
                  $headerRow = $row;
                  break;
                }
              }
            } elseif ($sectionTag === 'tbody') {
              foreach ($section->childNodes as $row) {
                if ($row instanceof DOMElement && strtolower($row->tagName) === 'tr') {
                  $bodyRows[] = $row;
                }
              }
            } elseif ($sectionTag === 'tr') {
              $bodyRows[] = $section;
            }
          }

          if ($headerRow === null) {
            foreach ($bodyRows as $rowIndex => $row) {
              foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && strtolower($cell->tagName) === 'th') {
                  $headerRow = $row;
                  array_splice($bodyRows, $rowIndex, 1);
                  break 2;
                }
              }
            }
          }

          $labels = [];
          $columnCount = 0;
          if ($headerRow instanceof DOMElement) {
            foreach ($headerRow->childNodes as $cell) {
              if (!$cell instanceof DOMElement || !in_array(strtolower($cell->tagName), ['th', 'td'], true)) {
                continue;
              }
              $label = trim(preg_replace('/\s+/u', ' ', $cell->textContent ?? '') ?? '');
              $labels[] = $label !== '' ? $label : ($fallbackLabels[count($labels)] ?? ('Thông tin ' . (count($labels) + 1)));
              $columnCount += max(1, (int) $cell->getAttribute('colspan'));
            }
          }
          if ($labels === []) {
            foreach ($bodyRows as $row) {
              $rowColumnCount = 0;
              foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['th', 'td'], true)) {
                  $rowColumnCount += max(1, (int) $cell->getAttribute('colspan'));
                }
              }
              $columnCount = max($columnCount, $rowColumnCount);
            }
            $labels = array_slice($fallbackLabels, 0, max(1, $columnCount));
          }
          $table->setAttribute('data-mobile-columns', (string) max(1, $columnCount ?: count($labels)));

          foreach ($bodyRows as $row) {
            $cellIndex = 0;
            foreach ($row->childNodes as $cell) {
              if (!$cell instanceof DOMElement || !in_array(strtolower($cell->tagName), ['th', 'td'], true)) {
                continue;
              }
              $cell->setAttribute('data-mobile-label', $labels[$cellIndex] ?? ('Thông tin ' . ($cellIndex + 1)));
              $cellIndex++;
            }
          }
        }

        $serialized = '';
        foreach ($fragment->childNodes as $child) {
          $serialized .= $document->saveHTML($child);
        }
        if ($serialized !== '') {
          $html = $serialized;
        }
      }
    } catch (Throwable) {
      // Keep the sanitized source if a malformed fragment defeats libxml.
    } finally {
      libxml_clear_errors();
      libxml_use_internal_errors($previousLibxmlState);
    }
  }

  return $html;
}

$facilityIndex = [];
foreach ($facilities as $item) {
  $facilityIndex[$item['slug']] = $item;
}

$slug = trim((string) ($_GET['slug'] ?? ''));
$facility = $slug !== '' && isset($facilityIndex[$slug]) ? $facilityIndex[$slug] : $facilities[0];

$defaults = $facilities[0];
$facility = array_replace_recursive($defaults, $facility);
$relatedFacilities = array_values(array_filter($facilities, static function (array $item) use ($facility): bool {
  return $item['slug'] !== $facility['slug'];
}));

$dbFacility = medical_directory_facility_row_by_slug($slug, true);
if (is_array($dbFacility) && $dbFacility !== []) {
  $dbFacility['verified'] = !empty($dbFacility['is_verified']);
  // Logo is useful as an identity image but makes a weak cover. Prefer a real
  // facility photo (frontage when available), then use the local AI visual only
  // as a last fallback.
  $dbFacility['hero_image'] = facility_detail_preferred_cover_image($dbFacility);
  // Use services supplied for this facility. `services_detail` is an old
  // presentation fallback and must not seed review filters with sample data.
  $dbFacility['services'] = (array) ($dbFacility['services'] ?? []);
  $facility = $dbFacility;
  // Không dùng các nhãn mẫu của hồ sơ mặc định cho cơ sở lấy từ DB.
  $facility['tags'] = [];
  $facility['images_label'] = count((array) ($facility['gallery'] ?? [])) . ' ảnh';
  $dbFacilityRows = medical_directory_facility_rows(true);
  $relatedFacilities = array_values(array_filter($dbFacilityRows, static function (array $item) use ($facility): bool {
    return (string) ($item['slug'] ?? '') !== (string) ($facility['slug'] ?? '');
  }));
}

  $facility = facility_detail_normalize($facility);
  $facility['images_label'] = count((array) ($facility['gallery'] ?? [])) . ' ảnh';
  if (trim((string) ($facility['content'] ?? '')) !== '') {
    $facility['intro'] = [(string) $facility['content']];
  }
  // Đồng bộ điểm và số lượt đánh giá từ dữ liệu review thực tế.
  if (is_array($facility['review_summary'] ?? null)) {
    $facility['rating'] = (string) ($facility['review_summary']['rating'] ?? $facility['rating'] ?? '0.0');
    $facility['reviews'] = (string) ($facility['review_summary']['reviews'] ?? $facility['reviews'] ?? '0 đánh giá');
  }
$relatedFacilities = array_map('facility_detail_normalize', $relatedFacilities);
$facilityPriceTableHtml = facility_detail_compact_price_html((string) ($facility['price_table_html'] ?? ''));

$facilityReviewSummary = (array) ($facility['review_summary'] ?? []);
$facilityReviewCount = (int) ($facilityReviewSummary['count'] ?? 0);
$facilityVerifiedReviewCount = (int) ($facilityReviewSummary['verified_count'] ?? 0);
$facilityVerifiedReviewPercent = (int) ($facilityReviewSummary['verified_percent'] ?? 0);
$facilityReviewServices = [];
foreach ((array) ($facility['reviews_list'] ?? []) as $facilityReview) {
  $service = trim((string) ($facilityReview['service'] ?? ''));
  if ($service !== '' && !in_array($service, $facilityReviewServices, true)) {
    $facilityReviewServices[] = $service;
  }
}

$facilityLegalFacts = [];
if (trim((string) ($facility['medical_operation_license'] ?? '')) !== '') {
  $facilityLegalFacts[] = ['label' => 'Giấy phép hoạt động', 'value' => (string) $facility['medical_operation_license'], 'icon' => 'badge-check'];
}
if (trim((string) ($facility['business_license'] ?? '')) !== '') {
  $facilityLegalFacts[] = ['label' => 'Thông tin pháp nhân', 'value' => (string) $facility['business_license'], 'icon' => 'building-2'];
}
if (!empty($facility['established_year'])) {
  $facilityLegalFacts[] = ['label' => 'Năm thành lập', 'value' => (string) $facility['established_year'], 'icon' => 'calendar-days'];
}
if (!empty($facility['branch_count'])) {
  $facilityLegalFacts[] = ['label' => 'Số chi nhánh', 'value' => (string) $facility['branch_count'], 'icon' => 'git-branch'];
}

$facilityVisitFacts = [];
if (trim((string) ($facility['parking_info'] ?? '')) !== '') {
  $facilityVisitFacts[] = ['label' => 'Gửi xe', 'value' => (string) $facility['parking_info'], 'icon' => 'car-front'];
}
if (trim((string) ($facility['nearby_landmarks'] ?? '')) !== '') {
  $facilityVisitFacts[] = ['label' => 'Khu vực lân cận', 'value' => (string) $facility['nearby_landmarks'], 'icon' => 'map-pinned'];
}
if (trim((string) ($facility['emergency_hotline'] ?? '')) !== '') {
  $facilityVisitFacts[] = ['label' => 'Hotline hỗ trợ', 'value' => (string) $facility['emergency_hotline'], 'icon' => 'phone-call'];
}

$facilityInsurance = $facility['insurance_accepted'] ?? '';
if (is_array($facilityInsurance)) {
  $facilityInsurance = implode(' · ', facility_detail_string_list($facilityInsurance));
}
$facilityInsurance = trim((string) $facilityInsurance);
$facilityPaymentMethods = facility_detail_string_list($facility['payment_methods'] ?? []);
$facilityLanguages = facility_detail_string_list($facility['languages_supported'] ?? []);
$facilityWarranty = trim((string) ($facility['warranty_policy'] ?? ''));
$facilityEquipment = facility_detail_string_list($facility['equipment_mentioned'] ?? []);

$facilityDoctors = [];
foreach ((array) ($facility['doctors'] ?? []) as $doctor) {
  if (!is_array($doctor)) {
    continue;
  }
  $name = trim((string) ($doctor['name'] ?? ''));
  if ($name === '') {
    continue;
  }
  $facilityDoctors[] = [
    'name' => $name,
    'title' => trim((string) ($doctor['title'] ?? '')),
    'specialty' => trim((string) ($doctor['specialty'] ?? $doctor['specialties'] ?? '')),
  ];
}

$facilityRatingSources = [];
foreach ((array) ($facility['aggregate_ratings'] ?? []) as $ratingSource) {
  if (!is_array($ratingSource) || trim((string) ($ratingSource['source'] ?? '')) === '') {
    continue;
  }
  $facilityRatingSources[] = [
    'source' => trim((string) $ratingSource['source']),
    'score' => is_numeric($ratingSource['score'] ?? null) ? number_format((float) $ratingSource['score'], 1, '.', '') : '',
    'count' => is_numeric($ratingSource['count'] ?? null) ? (int) $ratingSource['count'] : 0,
    'recommend_percent' => is_numeric($ratingSource['recommend_percent'] ?? null) ? (int) $ratingSource['recommend_percent'] : 0,
  ];
}

$facilityMapUrl = facility_detail_safe_url((string) ($facility['google_maps_url'] ?? ''));
if ($facilityMapUrl === '' && trim((string) ($facility['address'] ?? '')) !== '') {
  $facilityMapUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string) $facility['name'] . ' ' . (string) $facility['address']);
}
$facilityBookingUrl = facility_detail_safe_url((string) ($facility['booking_url'] ?? ''));
$facilityVideos = [];
foreach (facility_detail_string_list($facility['video_urls'] ?? []) as $videoUrl) {
  $safeUrl = facility_detail_safe_url($videoUrl);
  if ($safeUrl !== '') {
    $facilityVideos[] = $safeUrl;
  }
}
$facilitySocialLinks = [];
$socialLabels = ['facebook' => 'Facebook', 'zalo' => 'Zalo', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'instagram' => 'Instagram'];
foreach ((array) ($facility['social_links'] ?? []) as $network => $link) {
  $safeUrl = facility_detail_safe_url((string) $link);
  if ($safeUrl !== '') {
    $facilitySocialLinks[] = ['label' => $socialLabels[(string) $network] ?? ucfirst((string) $network), 'url' => $safeUrl];
  }
}

$facilityRatingValue = max(0, min(5, (float) ($facility['rating'] ?? 0)));
$facilityRatingStars = str_repeat('★', (int) round($facilityRatingValue)) . str_repeat('☆', 5 - (int) round($facilityRatingValue));
$facilitySummaryRatingValue = max(0, min(5, (float) ($facilityReviewSummary['rating'] ?? 0)));
$facilitySummaryStars = str_repeat('★', (int) round($facilitySummaryRatingValue)) . str_repeat('☆', 5 - (int) round($facilitySummaryRatingValue));

$seo = front_editor_page_seo('co-so-y-te-chi-tiet', [
  'title' => $facility['name'] . ' • MedReview',
  'description' => $facility['subtitle'],
  'canonical_path' => medical_public_facility_path((string) $facility['slug']),
]);
$title = trim((string) ($facility['seo_title'] ?? '')) !== ''
  ? (string) $facility['seo_title']
  : (string) ($seo['title'] ?? ($facility['name'] . ' • MedReview'));
$description = trim((string) ($facility['seo_description'] ?? '')) !== ''
  ? (string) $facility['seo_description']
  : (string) ($seo['description'] ?? '');
$canonicalPath = medical_public_facility_path((string) $facility['slug']);
$requestHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
$forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
$requestIsHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') || $forwardedProto === 'https';
$canonicalUrl = preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/', $requestHost)
  ? (($requestIsHttps ? 'https://' : 'http://') . $requestHost . $canonicalPath)
  : $canonicalPath;
$seoKeywords = trim((string) ($facility['seo_keywords'] ?? '')) !== ''
  ? (string) $facility['seo_keywords']
  : (string) ($seo['keywords'] ?? '');
$seoImage = trim((string) ($facility['hero_image'] ?? ''));
if ($seoImage === '') {
  $seoImage = trim((string) ($facility['image_url'] ?? ''));
}
?>
<!doctype html>
<html lang="vi">
  <head>
    <?php echo site_favicon_tags(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoImage !== ''): ?><meta property="og:image" content="<?php echo htmlspecialchars($seoImage, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?php echo $seoImage !== '' ? 'summary_large_image' : 'summary'; ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoImage !== ''): ?><meta name="twitter:image" content="<?php echo htmlspecialchars($seoImage, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/core/shared-typography.css">
    <style>
      :root{
        --bg:#f6f8fc;
        --surface:#ffffff;
        --surface-soft:#f6f9ff;
        --border:#e7eef8;
        --text:#0f172a;
        --muted:#64748b;
        --brand:#2563eb;
        --brand-soft:rgba(37,99,235,.10);
        --success:#16a34a;
        --warning:#f59e0b;
        --max:1320px;
      }
      *{box-sizing:border-box}
      html{height:100%}
      body{
        min-height:100%;
        margin:0;
        font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
        color:var(--text);
        background:
          radial-gradient(900px 320px at 0% 0%, rgba(59,130,246,.045), transparent 60%),
          linear-gradient(180deg,#fafcff 0%, #f5f8fd 100%);
      }
      a{color:inherit;text-decoration:none}
      .container{width:min(100% - 32px, var(--max));margin:0 auto}
      .facility-detail{padding:22px 0 64px}
      .breadcrumb{
        display:flex;
        align-items:center;
        gap:10px;
        color:#94a3b8;
        font-size:13px;
        font-weight:700;
      }
      .breadcrumb a{color:#94a3b8}
      .breadcrumb .active{color:var(--brand)}
      .crumb-sep{width:14px;height:14px;display:inline-flex;align-items:center;justify-content:center;color:#cbd5e1}
      .crumb-sep svg{width:14px;height:14px;stroke-width:1.9}
      .hero-grid{
        margin-top:14px;
        display:grid;
        grid-template-columns:minmax(0,1fr) 294px;
        gap:18px;
        align-items:start;
      }
      .hero-main{
        display:grid;
        grid-template-columns:272px minmax(0,1fr);
        gap:18px;
        padding:14px;
      }
      .panel{
        border:1px solid var(--border);
        border-radius:22px;
        background:#fff;
        box-shadow:0 10px 30px rgba(15,23,42,.05);
      }
      .hero-gallery{
        display:grid;
        gap:10px;
      }
      .hero-image{
        position:relative;
        height:268px;
        border-radius:18px;
        overflow:hidden;
        background:#e2e8f0;
        border:1px solid var(--border);
      }
      .hero-image img{width:100%;height:100%;object-fit:cover}
      .gallery-trigger{appearance:none;font:inherit;text-align:left;cursor:zoom-in;isolation:isolate;transition:transform .3s cubic-bezier(.2,.9,.25,1),filter .3s ease}
      .hero-image.gallery-trigger{display:block;width:100%;padding:0}
      .gallery-trigger::after{content:"";position:absolute;inset:0;z-index:1;pointer-events:none;border-radius:inherit;background:linear-gradient(135deg,rgba(255,255,255,.2),transparent 35%,rgba(15,23,42,.14));opacity:0;transition:opacity .28s ease}
      .gallery-trigger:hover::after,.gallery-trigger:focus-visible::after{opacity:1}
      .gallery-trigger:hover{filter:brightness(1.035)}
      .gallery-trigger:active{transform:scale(.982);transition-duration:.12s}
      .gallery-trigger:focus-visible{outline:3px solid rgba(59,130,246,.8);outline-offset:3px}
      .gallery-lightbox{--gallery-origin-x:0px;--gallery-origin-y:18px;position:fixed;isolation:isolate;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:24px;color:#fff;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .34s ease,visibility 0s linear .34s}
      .gallery-lightbox.is-open{opacity:1;visibility:visible;pointer-events:auto;transition:opacity .34s ease}
      .gallery-lightbox__backdrop{position:absolute;inset:0;background:radial-gradient(110% 95% at -8% -12%,rgba(96,165,250,.18),transparent 55%),radial-gradient(88% 85% at 112% 108%,rgba(129,140,248,.13),transparent 58%),linear-gradient(145deg,rgba(15,23,42,.66),rgba(2,6,23,.72));backdrop-filter:blur(0) saturate(1.04) brightness(.94);-webkit-backdrop-filter:blur(0) saturate(1.04) brightness(.94);transition:background .42s ease,backdrop-filter .42s ease,-webkit-backdrop-filter .42s ease}
      .gallery-lightbox.is-open .gallery-lightbox__backdrop{background:radial-gradient(112% 96% at -8% -12%,rgba(96,165,250,.33),transparent 55%),radial-gradient(90% 88% at 112% 108%,rgba(129,140,248,.24),transparent 58%),linear-gradient(145deg,rgba(15,23,42,.79),rgba(2,6,23,.88));backdrop-filter:blur(20px) saturate(1.22) brightness(.78);-webkit-backdrop-filter:blur(20px) saturate(1.22) brightness(.78)}
      .gallery-lightbox__dialog{position:relative;z-index:1;display:flex;width:min(100%,1180px);height:min(100%,820px);min-height:260px;opacity:0;filter:drop-shadow(0 34px 74px rgba(0,0,0,.28));transform:translate3d(var(--gallery-origin-x),var(--gallery-origin-y),0) scale(.86);transform-origin:center;will-change:transform,opacity;transition:opacity .38s cubic-bezier(.22,1,.36,1),transform .58s cubic-bezier(.16,1,.3,1),filter .46s ease}
      .gallery-lightbox.is-open .gallery-lightbox__dialog{opacity:1;transform:translate3d(0,0,0) scale(1)}
      .gallery-lightbox__topbar{position:absolute;z-index:3;top:0;left:0;right:0;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px 0;pointer-events:none}
      .gallery-lightbox__counter{padding:8px 12px;border:1px solid rgba(255,255,255,.22);border-radius:999px;background:linear-gradient(145deg,rgba(255,255,255,.18),rgba(30,41,59,.48));box-shadow:0 10px 28px rgba(0,0,0,.16),inset 0 1px 0 rgba(255,255,255,.24);font-size:13px;font-weight:800;letter-spacing:.01em;text-shadow:0 1px 1px rgba(0,0,0,.18);backdrop-filter:blur(20px) saturate(1.45);-webkit-backdrop-filter:blur(20px) saturate(1.45)}
      .gallery-lightbox__control{position:relative;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.24);color:#fff;background:linear-gradient(145deg,rgba(255,255,255,.17),rgba(30,41,59,.54));box-shadow:0 10px 28px rgba(0,0,0,.18),inset 0 1px 0 rgba(255,255,255,.24);text-shadow:0 1px 1px rgba(0,0,0,.2);backdrop-filter:blur(20px) saturate(1.45);-webkit-backdrop-filter:blur(20px) saturate(1.45);cursor:pointer;transition:transform .22s cubic-bezier(.2,.9,.25,1),background .22s ease,border-color .22s ease,box-shadow .22s ease}
      .gallery-lightbox__control::before{content:"";position:absolute;inset:0 0 auto;height:48%;pointer-events:none;background:linear-gradient(180deg,rgba(255,255,255,.14),transparent);opacity:.85}
      .gallery-lightbox__control:hover{background:linear-gradient(145deg,rgba(255,255,255,.27),rgba(51,65,85,.74));border-color:rgba(255,255,255,.48);box-shadow:0 14px 32px rgba(0,0,0,.26),inset 0 1px 0 rgba(255,255,255,.3);transform:translateY(-2px) scale(1.035)}
      .gallery-lightbox__control:active{transform:translateY(0) scale(.91);transition-duration:.1s}
      .gallery-lightbox__control:focus-visible{outline:3px solid #bfdbfe;outline-offset:3px}
      .gallery-lightbox__close{width:44px;height:44px;border-radius:50%;font-size:29px;line-height:1;font-weight:300;pointer-events:auto}
      .gallery-lightbox__stage{position:relative;display:flex;flex:1;align-items:center;justify-content:center;width:100%;min-height:0;padding:54px 78px 22px;border:1px solid rgba(255,255,255,.2);border-radius:28px;background:radial-gradient(120% 90% at 50% -18%,rgba(148,163,184,.22),transparent 50%),linear-gradient(145deg,rgba(51,65,85,.68),rgba(15,23,42,.58) 48%,rgba(2,6,23,.56));box-shadow:0 34px 100px rgba(0,0,0,.42),inset 0 1px 0 rgba(255,255,255,.2),inset 0 -1px 0 rgba(15,23,42,.26);overflow:hidden}
      .gallery-lightbox__stage::before{content:"";position:absolute;inset:-28%;pointer-events:none;background:radial-gradient(ellipse at 50% 0,rgba(191,219,254,.2),transparent 42%),radial-gradient(ellipse at 8% 100%,rgba(96,165,250,.15),transparent 38%),radial-gradient(ellipse at 100% 90%,rgba(129,140,248,.13),transparent 42%);opacity:.96}
      .gallery-lightbox__stage::after{content:"";position:absolute;inset:1px;pointer-events:none;border:1px solid rgba(255,255,255,.08);border-radius:inherit;box-shadow:inset 0 0 0 1px rgba(15,23,42,.09)}
      .gallery-lightbox__image{position:relative;z-index:1;display:block;max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;border:1px solid rgba(255,255,255,.15);border-radius:16px;box-shadow:0 24px 62px rgba(0,0,0,.44),0 2px 12px rgba(0,0,0,.22);opacity:1;transform:translate3d(0,0,0) scale(1);will-change:transform,opacity;transition:opacity .28s ease,transform .42s cubic-bezier(.16,1,.3,1),filter .28s ease}
      .gallery-lightbox.is-loading .gallery-lightbox__image,.gallery-lightbox__image.is-switching{opacity:0;filter:blur(2px);transform:translate3d(0,0,0) scale(.972)}
      .gallery-lightbox__image.is-switching.is-enter-from-next{transform:translate3d(34px,0,0) scale(.972)}
      .gallery-lightbox__image.is-switching.is-enter-from-prev{transform:translate3d(-34px,0,0) scale(.972)}
      .gallery-lightbox__status{position:absolute;left:50%;bottom:20px;z-index:2;max-width:calc(100% - 150px);padding:7px 12px;border:1px solid rgba(255,255,255,.19);border-radius:999px;background:linear-gradient(145deg,rgba(255,255,255,.13),rgba(15,23,42,.57));box-shadow:0 6px 18px rgba(0,0,0,.14),inset 0 1px 0 rgba(255,255,255,.15);font-size:12px;font-weight:700;line-height:1.35;text-align:center;text-shadow:0 1px 1px rgba(0,0,0,.18);transform:translateX(-50%);backdrop-filter:blur(16px) saturate(1.25);-webkit-backdrop-filter:blur(16px) saturate(1.25)}
      .gallery-lightbox__nav{position:absolute;z-index:2;top:50%;width:50px;height:50px;border-radius:50%;font-size:38px;line-height:.8;font-weight:300;transform:translateY(-50%)}
      .gallery-lightbox__nav:hover{transform:translateY(-50%) scale(1.05)}
      .gallery-lightbox__prev{left:16px}
      .gallery-lightbox__next{right:16px}
      .gallery-lightbox__nav:disabled{visibility:hidden;pointer-events:none}
      body.gallery-lightbox-open{overflow:hidden}
      @media (max-width:640px){
        .gallery-lightbox{padding:10px}
        .gallery-lightbox__dialog{width:100%;height:min(100%,720px);min-height:220px}
        .gallery-lightbox__topbar{padding:7px 7px 0}
        .gallery-lightbox__counter{font-size:12px;padding:7px 10px}
        .gallery-lightbox__close{width:42px;height:42px}
        .gallery-lightbox__stage{padding:56px 54px 38px;border-radius:22px}
        .gallery-lightbox__nav{width:42px;height:42px;font-size:32px}
        .gallery-lightbox__prev{left:8px}
        .gallery-lightbox__next{right:8px}
        .gallery-lightbox__status{bottom:13px;max-width:calc(100% - 32px);font-size:11px}
      }
      @media (prefers-reduced-motion:reduce){
        .gallery-trigger,.gallery-trigger::after,.gallery-lightbox,.gallery-lightbox__backdrop,.gallery-lightbox__dialog,.gallery-lightbox__image,.gallery-lightbox__control{transition:none!important}
      }
      .view-all{
        position:absolute;
        z-index:2;
        left:12px;
        bottom:12px;
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:8px 12px;
        border-radius:999px;
        background:rgba(15,23,42,.78);
        color:#fff;
        font-size:12px;
        font-weight:700;
        backdrop-filter:blur(8px);
      }
      .view-all svg{width:15px;height:15px;stroke-width:2}
      .thumb-strip{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:8px;
        padding:0;
      }
      .thumb{
        overflow:hidden;
        height:58px;
        border-radius:14px;
        background:#e2e8f0;
        border:1px solid var(--border);
        position:relative;
      }
      .thumb img{width:100%;height:100%;object-fit:cover}
      .thumb.more-thumb{
        display:flex;
        align-items:center;
        justify-content:center;
        background:linear-gradient(180deg,#f3f7ff,#e8f0ff);
        color:#1d4ed8;
        font-size:12px;
        font-weight:800;
      }
      .hero-copy{
        padding:4px 2px 2px;
      }
      .hero-top{
        display:flex;
        justify-content:space-between;
        gap:18px;
        align-items:flex-start;
      }
      .hero-head{display:grid;gap:10px}
      .hero-top h1{
        margin:0;
        font-size:2.45em;
        line-height:1.04;
        letter-spacing:-.04em;
      }
      .verified-mark{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:24px;
        height:24px;
        margin-left:8px;
        border-radius:999px;
        background:rgba(37,99,235,.12);
        color:#2563eb;
        vertical-align:middle;
      }
      .verified-mark svg{width:14px;height:14px;stroke-width:2.4}
      .hero-actions{
        display:flex;
        gap:10px;
        flex-wrap:wrap;
        justify-content:flex-end;
      }
      .icon-btn{
        display:inline-flex;
        align-items:center;
        gap:8px;
        height:40px;
        padding:0 14px;
        border-radius:999px;
        border:1px solid var(--border);
        background:#fbfdff;
        color:#334155;
        font-size:12px;
        font-weight:700;
      }
      .icon-btn svg{width:15px;height:15px;stroke-width:2}
      .subtitle{
        margin:0;
        color:#6b7280;
        font-size:14px;
        line-height:1.75;
        max-width:700px;
      }
      .rating-line{
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
        margin-top:4px;
      }
      .rating-main{
        font-size:1.32em;
        font-weight:800;
      }
      .stars{
        display:flex;
        gap:2px;
        color:var(--warning);
      }
      .stars svg{width:15px;height:15px;fill:currentColor;stroke:currentColor}
      .rating-meta{
        color:#64748b;
        font-size:13px;
      }
      .rating-pill{
        display:inline-flex;
        align-items:center;
        gap:10px;
        padding:10px 14px;
        border-radius:16px;
        background:#fbfdff;
        border:1px solid var(--border);
      }
      .tag-row{
        display:flex;
        gap:8px;
        flex-wrap:wrap;
        margin-top:16px;
      }
      .tag{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:8px 12px;
        border-radius:999px;
        background:#f4f7fd;
        color:#334155;
        font-size:12px;
        font-weight:700;
      }
      .tag svg{width:15px;height:15px;stroke-width:2;color:#60a5fa}
      .contact-list{
        display:grid;
        gap:12px;
        margin-top:18px;
      }
      .contact-item{
        display:flex;
        align-items:flex-start;
        gap:10px;
        padding:12px 14px;
        border-radius:16px;
        border:1px solid #edf2fa;
        background:#fbfdff;
        color:#334155;
        font-size:14px;
        line-height:1.6;
      }
      .contact-item svg{
        width:17px;height:17px;stroke-width:2;color:#2563eb;flex:0 0 auto;margin-top:2px;
        padding:7px;border-radius:999px;background:rgba(37,99,235,.10);box-sizing:content-box;
      }
      .contact-item a{color:#2563eb;font-weight:700;word-break:break-all}
      .contact-item strong{
        display:block;
        margin-bottom:2px;
        font-size:11px;
        letter-spacing:.02em;
        text-transform:uppercase;
        color:#94a3b8;
      }
      .contact-item span,
      .contact-item a{display:block}
      .cta-row{
        display:flex;
        gap:12px;
        margin-top:20px;
      }
      .btn-primary,
      .btn-secondary{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:168px;
        height:48px;
        padding:0 20px;
        border-radius:14px;
        font-size:14px;
        font-weight:700;
      }
      .btn-primary{
        background:linear-gradient(180deg,#3b82f6,#2563eb);
        color:#fff;
        box-shadow:0 12px 24px rgba(37,99,235,.16);
      }
      .btn-secondary{
        background:#fff;
        color:#2563eb;
        border:1px solid rgba(37,99,235,.28);
      }
      .sidebar{
        display:grid;
        gap:14px;
        position:sticky;
        top:98px;
      }
      .side-card{padding:18px}
      .map-box{
        position:relative;
        height:194px;
        border-radius:18px;
        overflow:hidden;
        background:
          linear-gradient(90deg, transparent 0 24%, rgba(255,255,255,.85) 24% 26%, transparent 26% 48%, rgba(255,255,255,.88) 48% 50%, transparent 50% 100%),
          linear-gradient(0deg, transparent 0 28%, rgba(255,255,255,.85) 28% 30%, transparent 30% 60%, rgba(255,255,255,.9) 60% 62%, transparent 62% 100%),
          linear-gradient(180deg,#eef4fb,#e4edf8);
        border:1px solid var(--border);
      }
      .map-label{
        position:absolute;
        color:#94a3b8;
        font-size:12px;
        font-weight:700;
      }
      .map-label.one{left:34px;top:110px;transform:rotate(-52deg)}
      .map-label.two{right:26px;top:44px;transform:rotate(-30deg)}
      .map-pin{
        position:absolute;
        right:42px;
        top:58px;
        width:26px;
        height:26px;
        border-radius:999px;
        background:#ef4444;
        box-shadow:0 12px 24px rgba(239,68,68,.25);
      }
      .map-pin::after{
        content:"";
        position:absolute;
        left:8px;
        top:8px;
        width:10px;
        height:10px;
        border-radius:999px;
        background:#fff;
      }
      .map-btn{
        position:absolute;
        left:50%;
        bottom:14px;
        transform:translateX(-50%);
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:132px;
        height:36px;
        padding:0 14px;
        border-radius:12px;
        background:#fff;
        border:1px solid var(--border);
        color:#2563eb;
        font-size:12px;
        font-weight:700;
        box-shadow:0 8px 18px rgba(148,163,184,.18);
      }
      .side-card h3{
        margin:0 0 14px;
        font-size:1.06em;
      }
      .schedule-grid{
        display:grid;
        gap:12px;
        color:#475569;
        font-size:14px;
      }
      .schedule-row{
        display:grid;
        grid-template-columns:1fr auto;
        gap:10px;
        padding:10px 0;
        border-bottom:1px solid #eff4fb;
      }
      .schedule-row:last-child{padding-bottom:0;border-bottom:0}
      .schedule-row strong{color:#334155}
      .utility-list{
        display:grid;
        gap:10px;
      }
      .utility-item{
        display:flex;
        align-items:flex-start;
        gap:10px;
        padding:10px 0;
        color:#475569;
        font-size:14px;
        border-bottom:1px solid #eff4fb;
      }
      .utility-item:last-child{padding-bottom:0;border-bottom:0}
      .utility-item svg{width:18px;height:18px;stroke-width:2;color:#60a5fa;flex:0 0 auto}
      .section{
        margin-top:16px;
        padding:18px;
      }
      .section h2{
        margin:0 0 12px;
        font-size:1.34em;
      }
      .section-copy{
        display:grid;
        gap:12px;
        color:#475569;
        font-size:14px;
        line-height:1.8;
      }
      .stats-row{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
        margin-top:18px;
      }
      .stat-box{
        padding:14px 16px;
        border-radius:16px;
        background:#f8fbff;
        border:1px solid var(--border);
        display:grid;
        gap:6px;
      }
      .stat-top{
        display:flex;
        align-items:center;
        gap:10px;
        color:#2563eb;
      }
      .stat-top svg{width:18px;height:18px;stroke-width:2}
      .stat-value{
        color:#2563eb;
        font-size:1.7em;
        line-height:1;
        font-weight:800;
      }
      .stat-label{
        color:#64748b;
        font-size:13px;
      }
      .section-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
        margin-bottom:14px;
      }
      .section-link{
        color:#2563eb;
        font-size:13px;
        font-weight:700;
      }
      .service-grid{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
      }
      .service-card{
        overflow:hidden;
        border-radius:16px;
        border:1px solid var(--border);
        background:#fff;
      }
      .service-image{
        height:148px;
        background:#e2e8f0;
      }
      .service-image img{width:100%;height:100%;object-fit:cover}
      .service-copy{
        padding:12px;
        display:grid;
        gap:8px;
      }
      .service-copy h3{
        margin:0;
        font-size:1.02em;
      }
      .service-copy p{
        margin:0;
        color:#64748b;
        font-size:13px;
        line-height:1.6;
      }
      .service-meta{
        display:flex;
        justify-content:space-between;
        gap:10px;
        align-items:center;
        color:#64748b;
        font-size:12px;
        font-weight:700;
      }
      .service-rating{
        display:flex;
        align-items:center;
        gap:6px;
      }
      .service-rating .stars svg{width:13px;height:13px}
      .price{
        color:#2563eb;
      }
      .review-shell{
        display:grid;
        grid-template-columns:248px minmax(0,1fr);
        gap:16px;
        align-items:start;
      }
      .summary-box{
        padding:18px 16px;
        border-radius:16px;
        border:1px solid var(--border);
        background:#fff;
      }
      .summary-box h3{
        margin:0;
        font-size:15px;
      }
      .summary-box p{
        margin:6px 0 0;
        color:#98a2b3;
        font-size:11px;
        line-height:1.6;
      }
      .summary-score{
        margin-top:16px;
        display:flex;
        align-items:flex-end;
        gap:6px;
      }
      .summary-score strong{
        font-size:52px;
        line-height:1;
        font-weight:800;
        color:var(--brand);
      }
      .summary-score small{padding-bottom:8px;font-size:15px;color:#475569;font-weight:800}
      .summary-box .stars{margin-top:8px}
      .summary-caption{
        margin-top:10px;
        color:#667085;
        font-size:13px;
        font-weight:700;
      }
      .rating-bars{
        display:grid;
        gap:8px;
        margin-top:16px;
      }
      .rating-row{
        display:grid;
        grid-template-columns:40px 1fr 30px;
        gap:8px;
        align-items:center;
        color:#64748b;
        font-size:11px;
      }
      .bar{
        height:5px;
        border-radius:999px;
        overflow:hidden;
        background:#e9eff8;
      }
      .bar span{
        display:block;
        height:100%;
        border-radius:999px;
        background:linear-gradient(90deg,#60a5fa,#2563eb);
      }
      .summary-verified{
        margin-top:14px;
        padding:14px;
        border:1px solid var(--border);
        border-radius:12px;
        background:var(--surface-soft);
      }
      .summary-verified strong{
        display:block;
        color:var(--brand);
        font-size:28px;
        line-height:1;
      }
      .summary-verified span{
        display:block;
        margin-top:6px;
        color:#475569;
        font-size:12px;
        font-weight:800;
      }
      .summary-verified small{
        display:block;
        margin-top:6px;
        color:#98a2b3;
        font-size:11px;
        line-height:1.6;
      }
      .review-feed{
        border:1px solid var(--border);
        border-radius:16px;
        background:#fff;
        overflow:hidden;
      }
      .review-feed-toolbar{
        display:flex;
        justify-content:space-between;
        gap:12px;
        align-items:center;
        padding:14px 16px;
        border-bottom:1px solid var(--border);
      }
      .review-toolbar-group{
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
      }
      .review-filter-pill,
      .review-sort-pill{
        display:inline-flex;
        align-items:center;
        gap:8px;
        min-height:34px;
        padding:0 12px;
        border:1px solid var(--border);
        border-radius:10px;
        background:#fff;
        color:#475569;
        font-size:11px;
        font-weight:800;
      }
      .review-switch{
        display:inline-flex;
        align-items:center;
        gap:10px;
        color:#667085;
        font-size:11px;
        font-weight:700;
      }
      .review-switch-toggle{
        width:34px;
        height:20px;
        border-radius:999px;
        background:var(--brand);
        position:relative;
      }
      .review-switch-toggle::after{
        content:"";
        position:absolute;
        top:3px;
        right:3px;
        width:14px;
        height:14px;
        border-radius:999px;
        background:#fff;
      }
      .review-list{display:grid}
      .review-row{
        display:grid;
        grid-template-columns:190px minmax(0,1fr) 214px 78px;
        gap:14px;
        align-items:start;
        padding:16px;
        border-top:1px solid var(--border);
      }
      .review-row:first-child{border-top:0}
      .review-author{
        display:flex;
        gap:10px;
        align-items:flex-start;
      }
      .avatar{
        width:46px;
        height:46px;
        border-radius:999px;
        background:linear-gradient(180deg,#dbeafe,#bfdbfe);
        overflow:hidden;
        flex:0 0 auto;
        display:flex;
        align-items:center;
        justify-content:center;
        color:#31538d;
        font-size:15px;
        font-weight:800;
      }
      .avatar img{width:100%;height:100%;object-fit:cover}
      .review-author strong{
        display:block;
        font-size:13px;
      }
      .review-author .meta-line{
        display:block;
        margin-top:4px;
        color:#98a2b3;
        font-size:11px;
      }
      .review-badge{
        display:inline-flex;
        align-items:center;
        gap:5px;
        margin-top:8px;
        color:#16a34a;
        font-size:11px;
        font-weight:800;
      }
      .review-badge svg{width:13px;height:13px;stroke-width:2}
      .review-content-top{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
      }
      .review-content-top .stars svg{width:12px;height:12px}
      .review-content-top strong{font-size:13px}
      .review-date{
        color:#98a2b3;
        font-size:11px;
        font-weight:700;
      }
      .review-content{
        margin-top:8px;
        color:#475569;
        font-size:12px;
        line-height:1.7;
      }
      .review-service{
        margin-top:8px;
        color:#667085;
        font-size:11px;
      }
      .mini-gallery{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:8px;
      }
      .mini-gallery-item{
        position:relative;
      }
      .mini-gallery span{
        overflow:hidden;
        height:68px;
        border-radius:10px;
        background:#e2e8f0;
        border:1px solid var(--border);
      }
      .mini-gallery img{width:100%;height:100%;object-fit:cover}
      .mini-gallery-more{
        position:absolute;
        right:8px;
        bottom:8px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:24px;
        height:24px;
        padding:0 6px;
        border-radius:999px;
        background:rgba(15,23,42,.72);
        color:#fff;
        font-size:11px;
        font-weight:800;
      }
      .review-meta{
        display:flex;
        align-items:flex-start;
        justify-content:flex-end;
        gap:12px;
        color:#94a3b8;
        font-size:12px;
      }
      .review-meta span{
        display:inline-flex;
        align-items:center;
        gap:6px;
      }
      .review-more{
        display:flex;
        justify-content:center;
        padding:0 16px 16px;
      }
      .review-more a{
        display:inline-flex;
        align-items:center;
        gap:8px;
        min-height:38px;
        padding:0 16px;
        border-radius:999px;
        border:1px solid #d4def5;
        background:#fff;
        color:var(--brand);
        font-size:12px;
        font-weight:800;
      }
      .review-more{gap:10px;flex-wrap:wrap}
      .review-more .review-write-inline{border-color:#2563eb;background:#2563eb;color:#fff}
      .review-more .review-write-inline svg{width:15px;height:15px}
      .feature-row{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
        margin-top:16px;
      }
      .feature-item{
        padding:16px;
        border-radius:16px;
        border:1px solid var(--border);
        background:#fbfdff;
        display:grid;
        gap:8px;
      }
      .feature-icon{
        width:40px;
        height:40px;
        border-radius:12px;
        background:var(--brand-soft);
        color:#2563eb;
        display:inline-flex;
        align-items:center;
        justify-content:center;
      }
      .feature-icon svg{width:19px;height:19px;stroke-width:2}
      .feature-item strong{
        font-size:14px;
      }
      .feature-item p{
        margin:0;
        color:#64748b;
        font-size:13px;
        line-height:1.7;
      }
      .cta-banner{
        margin-top:16px;
        padding:22px 24px;
        border-radius:18px;
        background:linear-gradient(135deg,#0f3fbf,#123fa8 55%, #1d4ed8 100%);
        color:#fff;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:18px;
      }
      .cta-copy{
        display:flex;
        gap:16px;
        align-items:center;
      }
      .cta-icon{
        width:56px;
        height:56px;
        border-radius:16px;
        background:rgba(255,255,255,.14);
        display:inline-flex;
        align-items:center;
        justify-content:center;
      }
      .cta-icon svg{width:28px;height:28px;stroke-width:2}
      .cta-copy strong{
        display:block;
        font-size:1.3em;
        margin-bottom:6px;
      }
      .cta-copy span{
        color:rgba(255,255,255,.8);
        font-size:14px;
      }
      .cta-banner a{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:154px;
        height:46px;
        padding:0 18px;
        border-radius:12px;
        background:#fff;
        color:#1d4ed8;
        font-size:14px;
        font-weight:700;
      }
      @media (max-width:1240px){
        .hero-grid,
        .review-shell{grid-template-columns:1fr}
        .sidebar{position:static}
        .review-row{grid-template-columns:1fr}
        .review-meta{justify-content:flex-start}
      }
      @media (max-width:980px){
        .hero-main{grid-template-columns:1fr}
        .hero-top{display:grid}
        .stats-row,
        .service-grid,
        .feature-row{grid-template-columns:repeat(2,minmax(0,1fr))}
        .cta-banner{display:grid}
      }
      @media (max-width:720px){
        .container{width:min(100% - 24px, var(--max))}
        .facility-detail{padding:18px 0 48px}
        .hero-top h1{font-size:1.9em}
        .thumb-strip{grid-template-columns:repeat(3,minmax(0,1fr))}
        .stats-row,
        .service-grid,
        .feature-row{grid-template-columns:1fr}
        .cta-row{display:grid}
        .review-feed-toolbar{flex-direction:column;align-items:flex-start}
        .mini-gallery{grid-template-columns:repeat(2,minmax(0,1fr))}
      }

      /* Detail page refinement: clear hierarchy, stronger booking CTA and calmer clinical visual language. */
      .facility-detail{padding-top:28px}
      .container{max-width:1240px}
      .hero-grid{grid-template-columns:minmax(0,1fr) 316px;gap:22px;margin-top:18px}
      .panel{border-color:#dfe8f5;box-shadow:0 16px 40px rgba(15,23,42,.055)}
      .hero-main{grid-template-columns:330px minmax(0,1fr);gap:24px;padding:16px}
      .hero-image{height:326px;border-radius:16px}
      .thumb{height:52px;border-radius:12px}
      .hero-copy{padding:8px 4px 4px}
      .facility-eyebrow{
        display:inline-flex;align-items:center;gap:7px;width:max-content;margin-bottom:10px;
        color:#047857;font-size:11px;font-weight:800;letter-spacing:.055em;text-transform:uppercase;
      }
      .facility-eyebrow svg{width:15px;height:15px;stroke-width:2.5}
      .hero-top h1{font-size:2.25em;line-height:1.12;letter-spacing:-.045em}
      .subtitle{max-width:610px;color:#64748b}
      .hero-actions{gap:8px}
      .icon-btn{height:36px;padding:0 11px;background:#fff;transition:background .18s ease,border-color .18s ease,color .18s ease}
      .icon-btn:hover{border-color:#93c5fd;background:#eff6ff;color:#1d4ed8}
      .rating-pill{margin-top:18px;padding:11px 14px;background:linear-gradient(120deg,#fffbeb,#fff)}
      .rating-stars-text{color:#f59e0b;letter-spacing:2px;font-size:17px;line-height:1}
      .contact-list{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
      .contact-item{min-height:86px;padding:12px;background:#fff;transition:border-color .18s ease,box-shadow .18s ease}
      .contact-item:hover{border-color:#bfdbfe;box-shadow:0 8px 18px rgba(37,99,235,.07)}
      .cta-row{margin-top:18px}
      .btn-primary,.btn-secondary{min-width:0;flex:1;transition:transform .18s ease,box-shadow .18s ease}
      .btn-primary:hover{transform:translateY(-1px);box-shadow:0 16px 28px rgba(37,99,235,.24)}
      .sidebar{gap:16px;top:100px}
      .side-card{padding:18px;border-radius:20px}
      .side-card-title{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
      .side-card-title h3{margin:0}
      .open-status{display:inline-flex;align-items:center;gap:6px;color:#15803d;font-size:11px;font-weight:800}
      .open-status::before{content:"";width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.12)}
      .side-booking{padding:20px;background:linear-gradient(145deg,#eff6ff,#fff)}
      .side-booking h3{margin:0;font-size:16px}
      .side-booking p{margin:7px 0 16px;color:#64748b;font-size:13px;line-height:1.65}
      .side-booking .btn-primary{width:100%;height:46px}
      .detail-nav{display:flex;align-items:center;gap:8px;overflow:auto;margin-top:22px;padding:8px;border:1px solid var(--border);border-radius:16px;background:rgba(255,255,255,.86);box-shadow:0 8px 20px rgba(15,23,42,.035)}
      .detail-nav a{flex:0 0 auto;padding:9px 13px;border-radius:10px;color:#64748b;font-size:13px;font-weight:750}
      .detail-nav a:hover{background:#eff6ff;color:#1d4ed8}
      .section{margin-top:22px;padding:24px}
      .section h2{font-size:1.42em;letter-spacing:-.025em}
      .section-copy{max-width:880px;font-size:14px;line-height:1.85}
      .stats-row{margin-top:22px;gap:14px}
      .stat-box{padding:17px;background:linear-gradient(145deg,#f8fbff,#fff)}
      .service-card{transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}
      .service-card:hover{transform:translateY(-3px);border-color:#bfdbfe;box-shadow:0 14px 26px rgba(15,23,42,.08)}
      .service-image{height:164px}
      .review-feed,.summary-box{border-radius:18px}
      .feature-item{padding:18px;background:#fff}
      @media (max-width:1100px){
        .hero-grid{grid-template-columns:1fr}
        .sidebar{position:static;grid-template-columns:repeat(3,minmax(0,1fr));align-items:start}
        .side-booking{grid-column:span 3}
      }
      @media (max-width:820px){
        .hero-main{grid-template-columns:1fr}
        .hero-image{height:300px}
        .sidebar{grid-template-columns:1fr}
        .side-booking{grid-column:auto}
        .contact-list{grid-template-columns:1fr}
      }
      @media (max-width:560px){
        .facility-detail{padding-top:18px}
        .hero-main,.section{padding:16px}
        .hero-image{height:250px}
        .hero-actions{justify-content:flex-start}
        .icon-btn{font-size:11px}
        .cta-row{grid-template-columns:1fr}
      }

      /* Compact pass: make the page scan like a directory profile, not a landing page. */
      .facility-detail{padding:20px 0 48px}
      .container{max-width:1320px}
      .breadcrumb{font-size:12px;gap:7px}
      .hero-grid{grid-template-columns:minmax(0,1fr) 288px;gap:16px;margin-top:12px}
      .panel{border-radius:16px;box-shadow:0 8px 24px rgba(15,23,42,.045)}
      .hero-main{grid-template-columns:260px minmax(0,1fr);gap:18px;padding:12px}
      .hero-image{height:250px;border-radius:12px}
      .hero-main{align-items:start;background:linear-gradient(145deg,#fff 0%,#f8fbff 100%)}
      .hero-gallery{position:sticky;top:92px}
      .hero-copy{min-width:0;padding:4px 6px}
      .hero-top h1{margin-bottom:6px;font-size:clamp(1.55rem,2.3vw,2rem)}
      .contact-item{min-height:72px;padding:10px;border-radius:12px}
      .section#gioi-thieu{padding:26px 30px;background:linear-gradient(160deg,#fff 0%,#f8fbff 100%)}
      .section#gioi-thieu h2{margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid #e8eef7}
      .facility-detail .section#gioi-thieu h2.facility-price-heading{margin:12px 0 6px!important;padding:0!important;border:0!important;color:#17345f;scroll-margin-top:100px}
      .section#gioi-thieu .section-copy{max-width:none;display:block;color:#334155;font-size:15px;line-height:1.85}
      .section#gioi-thieu .section-copy p{margin:0 0 14px}
      .section#gioi-thieu .section-copy h2,.section#gioi-thieu .section-copy h3{margin:20px 0 8px;color:#123b78;font-size:1.08em}
      .facility-detail .facility-price-table{margin:0!important;padding-top:6px;border-top:1px solid #e8eef7}
      .facility-detail .facility-price-table table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border:1px solid #dbe7f5;border-radius:14px;background:#fff;color:#334155;font-size:12px;line-height:1.25}
      .facility-detail .facility-price-table table{display:table!important}
      .facility-detail .facility-price-table thead{display:table-header-group!important}
      .facility-detail .facility-price-table tbody{display:table-row-group!important}
      .facility-detail .facility-price-table tfoot{display:table-footer-group!important}
      .facility-detail .facility-price-table tr{display:table-row!important}
      .facility-detail .facility-price-table :is(th,td){display:table-cell!important}
      .facility-detail .facility-price-table table,.facility-detail .facility-price-table table :is(thead,tbody,tfoot,tr,th,td),.facility-detail .facility-price-table table *{height:auto!important;min-height:0!important;max-height:none!important}
      .facility-detail .facility-price-table td *{margin-block:0!important;padding-block:0!important;line-height:1.3!important}
      .facility-detail .facility-price-table thead th{padding:6px 10px!important;text-align:left;background:#eff6ff;color:#174ea6;font-size:10px;font-weight:800;letter-spacing:.035em;text-transform:uppercase;border-bottom:1px solid #dbe7f5}
      .facility-detail .facility-price-table :is(th,td){padding:5px 10px!important;vertical-align:top}
      .facility-detail .facility-price-table tbody td{border-bottom:1px solid #edf2f8}
      .facility-price-table tbody tr:last-child td{border-bottom:0}
      .facility-price-table tbody tr:nth-child(even) td{background:#f8fbff}
      .facility-price-table tbody td:nth-child(2){color:#1d4ed8;font-weight:750;white-space:nowrap}
      .facility-price-table tbody tr:hover td{background:#eef6ff}
      .facility-detail .facility-price-table>p{margin:7px 2px 0;color:#94a3b8;font-size:11px}
      .facility-detail .facility-price-table :is(th,td){overflow-wrap:anywhere}
      .facility-detail .facility-price-table td p{margin:0!important;padding:0;line-height:1.3}
      .facility-detail .facility-price-table td p+p{margin-top:2px!important}
      .facility-detail .facility-price-table td :is(ul,ol){margin:0;padding-left:16px}
      .facility-detail .facility-price-table td li{margin-bottom:2px!important}
      .facility-detail .facility-price-table td>:first-child{margin-top:0!important}
      .facility-detail .facility-price-table td>:last-child{margin-bottom:0!important}
      .facility-info-area{margin-top:22px}
      .facility-info-heading{display:flex;align-items:end;justify-content:space-between;gap:20px;margin:0 2px 12px}
      .facility-info-kicker{display:inline-flex;align-items:center;gap:6px;color:#0f766e;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
      .facility-info-kicker svg{width:14px;height:14px;stroke-width:2.5}
      .facility-info-heading h2{margin:5px 0 0;color:#13284a;font-size:1.34em;letter-spacing:-.03em}
      .facility-info-heading>p{max-width:400px;margin:0;color:#7a8ca7;font-size:12px;line-height:1.55;text-align:right}
      .facility-info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
      .facility-info-card{padding:18px;background:linear-gradient(145deg,#fff,#f8fbff)}
      .facility-info-card--wide{grid-column:1/-1}
      .facility-info-card-head{display:flex;align-items:flex-start;gap:10px;margin-bottom:16px}
      .facility-info-icon{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;flex:0 0 auto;border:1px solid #dceafe;border-radius:11px;color:#2563eb;background:linear-gradient(145deg,#eef6ff,#e4efff)}
      .facility-info-icon svg{width:18px;height:18px;stroke-width:2.15}
      .facility-info-card-head h3{margin:1px 0 3px;color:#1d3153;font-size:14px;letter-spacing:-.015em}
      .facility-info-card-head p{margin:0;color:#8292aa;font-size:11px;line-height:1.45}
      .facility-fact-list{display:grid;gap:0;margin:0}
      .facility-fact-list>div{padding:10px 0;border-top:1px solid #e8eff8}
      .facility-fact-list dt{display:flex;align-items:center;gap:6px;margin:0;color:#7b8da7;font-size:10px;font-weight:800;letter-spacing:.035em;text-transform:uppercase}
      .facility-fact-list dt svg{width:13px;height:13px;color:#4f8af5;stroke-width:2.2}
      .facility-fact-list dd{margin:5px 0 0;color:#405673;font-size:12px;line-height:1.55}
      .facility-fact-list--compact dd{max-width:100%}
      .facility-info-action{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:36px;margin-top:14px;padding:0 11px;border:1px solid #bfdbfe;border-radius:10px;background:#fff;color:#2165d7;font-size:11px;font-weight:800;transition:transform .18s ease,background .18s ease,box-shadow .18s ease}
      .facility-info-action svg{width:14px;height:14px;stroke-width:2.2}.facility-info-action svg:last-child{width:12px;height:12px}
      .facility-info-action:hover{background:#eff6ff;box-shadow:0 8px 18px rgba(37,99,235,.10);transform:translateY(-1px)}
      .facility-info-note{display:flex;align-items:flex-start;gap:9px;padding:10px 11px;border:1px solid #dbeafe;border-radius:11px;background:#f5f9ff;color:#48617e}
      .facility-info-note+.facility-info-group,.facility-info-group+.facility-info-group,.facility-info-group+.facility-info-note{margin-top:12px}
      .facility-info-note>svg{width:15px;height:15px;flex:0 0 auto;margin-top:1px;color:#3b82f6;stroke-width:2.1}
      .facility-info-note strong{display:block;color:#34557f;font-size:11px}.facility-info-note span{display:block;margin-top:3px;font-size:11px;line-height:1.55}
      .facility-info-note--soft{border-color:#e1e9f5;background:#fbfcff}
      .facility-info-group>strong,.facility-column-title{display:block;margin:0 0 7px;color:#657b99;font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
      .facility-chip-list{display:flex;flex-wrap:wrap;gap:6px}.facility-chip-list span{padding:5px 8px;border-radius:999px;background:#ecf5ff;color:#2962b7;font-size:10px;font-weight:750;line-height:1.35}.facility-chip-list--muted span{background:#f1f5f9;color:#596d87}
      .facility-link-row{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}.facility-link-row .facility-info-action{margin-top:0}
      .facility-social-link{display:inline-flex;align-items:center;gap:4px;min-height:36px;padding:0 10px;border:1px solid #e0e8f4;border-radius:10px;background:#fff;color:#58708f;font-size:10px;font-weight:800}.facility-social-link svg{width:11px;height:11px}
      .facility-source-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px}.facility-source-item{display:grid;gap:2px;padding:11px;border:1px solid #e1eaf7;border-radius:11px;background:#fff}.facility-source-item strong{color:#506683;font-size:10px}.facility-source-item b{color:#f59e0b;font-size:20px;line-height:1.15;letter-spacing:-.04em}.facility-source-item b small{margin-left:2px;color:#90a0b6;font-size:10px;letter-spacing:0}.facility-source-item span{color:#8190a7;font-size:10px}
      .facility-team-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.facility-team-column+.facility-team-column{padding-left:20px;border-left:1px solid #e7eef8}
      .facility-doctor-list{display:grid;gap:8px}.facility-doctor-list article{display:flex;align-items:flex-start;gap:9px;padding:10px;border:1px solid #e4ecf7;border-radius:11px;background:#fff}.facility-doctor-list article>span{display:inline-flex;align-items:center;justify-content:center;width:27px;height:27px;flex:0 0 auto;border-radius:9px;background:#eaf3ff;color:#2f78e7}.facility-doctor-list article>span svg{width:14px;height:14px}.facility-doctor-list article strong{display:block;color:#385170;font-size:12px}.facility-doctor-list article p{margin:2px 0 0;color:#6b7f99;font-size:10px;line-height:1.45}.facility-doctor-list article small{display:block;margin-top:3px;color:#3b82f6;font-size:10px;line-height:1.45}
      .facility-equipment-list{display:grid;gap:8px;margin:0;padding:0;list-style:none}.facility-equipment-list li{display:flex;gap:8px;color:#506783;font-size:11px;line-height:1.55}.facility-equipment-list svg{width:14px;height:14px;flex:0 0 auto;margin-top:1px;color:#4f8af5;stroke-width:2.2}.facility-video-links{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}.facility-video-links a{display:inline-flex;align-items:center;gap:5px;padding:7px 9px;border-radius:9px;background:#f1f6ff;color:#2864c4;font-size:10px;font-weight:800}.facility-video-links a svg{width:13px;height:13px}.facility-video-links a svg:last-child{width:10px;height:10px}
      @media (max-width:760px){.facility-info-heading{display:block;margin:0 0 10px}.facility-info-heading>p{max-width:none;margin-top:6px;text-align:left}.facility-info-grid{grid-template-columns:1fr}.facility-info-card--wide{grid-column:auto}.facility-team-grid{grid-template-columns:1fr;gap:14px}.facility-team-column+.facility-team-column{padding:14px 0 0;border-top:1px solid #e7eef8;border-left:0}}
      @media (max-width:560px){.facility-info-area{margin-top:12px}.facility-info-card{padding:12px;border-radius:12px}.facility-info-heading h2{font-size:1.15em}.facility-info-card-head{margin-bottom:10px}.facility-info-note{padding:8px 10px}.facility-source-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.facility-source-item{padding:8px 9px}}
      .thumb-strip{gap:6px}
      .thumb{height:42px;border-radius:9px}
      .view-all{left:8px;bottom:8px;padding:6px 9px;font-size:11px}
      .hero-copy{padding:2px 2px 0}
      .facility-eyebrow{margin-bottom:6px;font-size:10px}
      .hero-top{gap:12px}
      .hero-top h1{font-size:1.82em;line-height:1.18}
      .subtitle{font-size:13px;line-height:1.55}
      .hero-actions{gap:6px}
      .hero-actions{display:flex;flex-wrap:nowrap;align-items:center;white-space:nowrap}
      .hero-actions .icon-btn{flex:0 0 auto}
      .icon-btn{height:32px;padding:0 9px;border-radius:9px;font-size:11px}
      .icon-btn svg{width:14px;height:14px}
      .rating-pill{margin-top:12px;padding:8px 10px;border-radius:11px}
      .rating-main{font-size:1.12em}
      .rating-meta{font-size:11px}
      .tag-row{margin-top:10px;gap:6px}
      .tag{padding:6px 9px;font-size:11px}
      .contact-list{margin-top:12px;gap:8px}
      .contact-item{min-height:70px;padding:9px 10px;border-radius:11px;font-size:12px;line-height:1.45}
      .contact-list{grid-template-columns:1fr 1fr;gap:0 24px;padding-top:4px}
      .contact-item{min-height:58px;padding:10px 0;border:0;border-bottom:1px solid #edf2f7;border-radius:0;background:transparent;box-shadow:none!important}
      .contact-item:hover{background:transparent;border-color:#dbe7f5}
      .contact-item strong{font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em}
      .contact-item span,.contact-item a{margin-top:3px;font-size:12px;line-height:1.4}
      @media (max-width:560px){.contact-list{grid-template-columns:1fr;gap:0}}
      .contact-item svg{width:14px;height:14px;padding:5px}
      .contact-item strong{margin-bottom:1px;font-size:9px}
      .cta-row{margin-top:12px;gap:8px}
      .btn-primary,.btn-secondary{height:40px;padding:0 13px;border-radius:10px;font-size:12px}
      .sidebar{gap:10px;top:90px}
      .side-card{padding:14px;border-radius:16px}
      .side-booking{padding:15px}
      .side-booking h3{font-size:14px}
      .side-booking p{margin:5px 0 11px;font-size:12px;line-height:1.5}
      .side-booking .btn-primary{height:40px}
      .map-box{height:150px;border-radius:12px}
      .side-card h3{font-size:14px}
      .side-card-title{margin-bottom:9px}
      .open-status{font-size:10px;color:#64748b}
      .open-status::before{width:6px;height:6px;background:#94a3b8;box-shadow:none}
      .schedule-grid,.utility-list{gap:6px;font-size:12px}
      .schedule-row,.utility-item{padding:7px 0;font-size:12px}
      .utility-item svg{width:15px;height:15px}
      .detail-nav{margin-top:14px;padding:5px;border-radius:12px;box-shadow:none}
      .detail-nav a{padding:7px 10px;font-size:12px}
      .section{margin-top:14px;padding:18px}
      .section h2{margin-bottom:9px;font-size:1.2em}
      .section-copy{gap:8px;font-size:13px;line-height:1.7}
      .stats-row{margin-top:14px;gap:8px}
      .stat-box{padding:11px 12px;border-radius:12px}
      .stat-value{font-size:1.35em}
      .stat-label{font-size:11px}
      .section-head{margin-bottom:10px}
      .service-grid{gap:9px}
      .service-image{height:126px}
      .service-copy{padding:10px;gap:5px}
      .service-copy h3{font-size:13px}.service-copy p{font-size:11px}.service-meta{font-size:10px}
      .review-shell{gap:12px}.summary-box{padding:14px}.summary-score strong{font-size:42px}
      .review-feed-toolbar{padding:11px 13px}.review-row{padding:13px;gap:11px}
      .feature-row{margin-top:14px;gap:8px}.feature-item{padding:13px;gap:6px}.feature-item p{font-size:12px;line-height:1.55}
      .cta-banner{margin-top:14px;padding:17px 18px;border-radius:16px}.cta-copy strong{font-size:1.05em}.cta-copy span{font-size:12px}
      @media (max-width:1100px){.hero-grid{grid-template-columns:1fr}.sidebar{grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.side-booking{grid-column:span 3}}
      @media (max-width:820px){.hero-main{grid-template-columns:1fr}.hero-image{height:230px}.sidebar{grid-template-columns:1fr}.side-booking{grid-column:auto}.contact-list{grid-template-columns:1fr}}
      @media (max-width:560px){.facility-detail{padding-top:14px}.hero-main,.section{padding:12px}.hero-image{height:210px}.hero-top h1{font-size:1.55em}.hero-actions{display:flex;gap:4px}.hero-actions .icon-btn{padding:0 7px;font-size:10px}.thumb{height:36px}.stats-row,.service-grid,.feature-row{grid-template-columns:1fr}.cta-row{display:grid}}
      @media (max-width:560px){.hero-gallery{position:static}.section#gioi-thieu{padding:20px 16px}.section#gioi-thieu .section-copy{font-size:14px;line-height:1.75}}
      /* Reviews are data-led: text is the focus; optional photos live with
         their review instead of reserving a blank desktop column. */
      #danh-gia .review-shell{display:grid!important;grid-template-columns:248px minmax(0,1fr)!important;align-items:start!important}
      #danh-gia .review-feed{min-width:0!important;width:100%!important;overflow:hidden!important}
      #danh-gia .review-list{min-width:0!important;width:100%!important}
      #danh-gia .review-row{display:grid!important;grid-template-columns:190px minmax(0,1fr) auto!important;min-width:0!important;width:100%!important;align-items:start!important}
      #danh-gia .review-row>div{min-width:0!important}
      #danh-gia .review-body{min-width:0}
      #danh-gia .review-content{overflow-wrap:anywhere!important;word-break:normal!important}
      #danh-gia .review-rating{display:inline-flex;align-items:center;gap:6px;color:#f59e0b;font-size:13px;font-weight:800}
      #danh-gia .review-stars{letter-spacing:1px;font-size:15px;line-height:1}
      #danh-gia .review-unrated{color:#64748b;font-size:11px;font-weight:800}
      #danh-gia .review-detail-row{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
      #danh-gia :is(.review-service,.review-source){display:inline-flex;align-items:center;gap:5px;margin:0;padding:5px 8px;border:1px solid #e0e9f7;border-radius:999px;background:#f8fbff;color:#526783;font-size:10px;font-weight:700;line-height:1.35}
      #danh-gia .review-source{max-width:100%;background:#fbfcff;color:#64748b}
      #danh-gia :is(.review-service,.review-source) svg{width:12px;height:12px;flex:0 0 auto}
      #danh-gia .mini-gallery{min-width:0!important;width:auto!important;max-width:214px;margin-top:12px}
      #danh-gia .mini-gallery span{display:block}
      #danh-gia .review-meta{min-width:0;padding-top:2px}
      #danh-gia .review-meta:empty{display:none}
      #danh-gia .review-empty{display:grid;place-items:center;gap:7px;min-height:180px;padding:24px;color:#71809a;text-align:center;font-size:12px}
      #danh-gia .review-empty i{color:#83aaf0;font-size:28px}
      #danh-gia .review-empty strong{color:#314560;font-size:14px}
      #danh-gia .review-switch{position:relative;cursor:pointer;user-select:none}
      #danh-gia .review-switch input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
      #danh-gia .review-switch-toggle{background:#cbd5e1}
      #danh-gia .review-switch-toggle::after{right:auto;left:3px}
      #danh-gia .review-switch input:checked + .review-switch-toggle{background:var(--brand)}
      #danh-gia .review-switch input:checked + .review-switch-toggle::after{right:3px;left:auto}
      #danh-gia .summary-box{background:linear-gradient(145deg,#f3f8ff 0%,#ffffff 62%,#f8fbff 100%)!important;border-color:#cfe0ff!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.9)}
      #danh-gia .summary-verified{background:rgba(255,255,255,.72)!important;border-color:#dbe7f5!important}
      @media (max-width:1240px){#danh-gia .review-shell{grid-template-columns:1fr!important}}
      @media (max-width:820px){
        #danh-gia .review-feed-toolbar{display:grid;grid-template-columns:minmax(0,1fr);align-items:stretch;gap:10px}
        #danh-gia .review-toolbar-group{gap:8px}
        #danh-gia .review-filter-pill,#danh-gia .review-sort-pill{min-height:36px;padding-inline:9px}
        #danh-gia .review-switch{justify-content:space-between;width:100%;padding-top:10px;border-top:1px solid #edf2f8}
        #danh-gia .review-row{grid-template-columns:minmax(0,1fr) auto!important;grid-template-areas:"author meta" "body body";gap:12px!important;padding:14px!important}
        #danh-gia .review-author{grid-area:author}
        #danh-gia .review-body{grid-area:body}
        #danh-gia .review-meta{grid-area:meta;align-items:flex-start;gap:9px;padding-top:3px;font-size:11px}
        #danh-gia .review-content-top{gap:9px}
        #danh-gia .review-content{font-size:13px;line-height:1.68}
        #danh-gia .mini-gallery{grid-template-columns:repeat(3,minmax(0,1fr));max-width:none;margin-top:12px}
        #danh-gia .mini-gallery span{height:78px}
      }
      @media (max-width:420px){
        #danh-gia .review-feed-toolbar{padding:11px 12px}
        #danh-gia .review-toolbar-group{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));width:100%}
        #danh-gia .review-filter-pill,#danh-gia .review-sort-pill{justify-content:center;width:100%;overflow:hidden}
        #danh-gia .review-filter-pill select,#danh-gia .review-sort-pill select{min-width:0;max-width:100%}
        #danh-gia .review-author{gap:8px}
        #danh-gia .avatar{width:40px;height:40px;font-size:13px}
        #danh-gia .review-author strong{font-size:12px}
        #danh-gia .review-badge{margin-top:5px;font-size:10px}
        #danh-gia .mini-gallery span{height:66px}
        #danh-gia .review-stars{font-size:13px}
      }
    </style>
    <link rel="stylesheet" href="/assets/css/pages/facility-detail-layout.css?v=<?php echo filemtime(__DIR__ . '/assets/css/pages/facility-detail-layout.css'); ?>">
    <style id="facility-detail-motion-polish">
      /* Quiet, native-feeling feedback for the directory profile.  Everything
         here is scoped to the page so shared header/footer styles stay intact. */
      html{scroll-behavior:smooth}
      .facility-detail .hero-main,
      .facility-detail .section,
      .facility-detail .side-card,
      .facility-detail .feature-item{
        transition:border-color .22s ease,box-shadow .22s ease,background-color .22s ease;
      }
      .facility-detail :is(.icon-btn,.btn-primary,.btn-secondary,.detail-nav a,.section-link,.review-more a,.review-write-inline){
        -webkit-tap-highlight-color:transparent;
        touch-action:manipulation;
        transition:transform .2s cubic-bezier(.2,.78,.25,1),box-shadow .2s ease,background-color .2s ease,border-color .2s ease,color .2s ease;
      }
      .facility-detail :is(.contact-item,.service-card,.feature-item,.thumb,.mini-gallery-item,.stat-box){
        -webkit-tap-highlight-color:transparent;
        transition:transform .2s cubic-bezier(.2,.78,.25,1),box-shadow .2s ease,border-color .2s ease,background-color .2s ease;
      }
      .facility-detail :is(.hero-image img,.thumb img,.service-image img,.mini-gallery img){
        transition:transform .42s cubic-bezier(.16,1,.3,1),filter .28s ease;
      }
      .facility-detail .review-row{
        position:relative;
        transition:background-color .2s ease,box-shadow .2s ease;
      }
      .facility-detail .review-filter-pill:focus-within,
      .facility-detail .review-sort-pill:focus-within{
        border-color:#93c5fd;
        box-shadow:0 0 0 3px rgba(59,130,246,.12);
      }
      .facility-detail :is(a,button,select):focus-visible{
        outline:3px solid rgba(59,130,246,.58);
        outline-offset:3px;
      }
      .facility-detail .facility-price-table tbody tr{transition:background-color .18s ease}
      .facility-detail .facility-price-table tbody tr:hover td{background:#f2f7ff}
      /* Keep the compact two-column card layout on phones; give desktop tables
         a little more breathing room and a stable price/notes balance. */
      @media (min-width:981px){
        .facility-detail .facility-price-table :is(th,td){padding:8px 12px!important;line-height:1.4}
        .facility-detail .facility-price-table table:has(>thead th:nth-child(4)):not(:has(>thead th:nth-child(5))){table-layout:fixed}
        .facility-detail .facility-price-table table:has(>thead th:nth-child(4)):not(:has(>thead th:nth-child(5))) thead th:nth-child(1){width:16%}
        .facility-detail .facility-price-table table:has(>thead th:nth-child(4)):not(:has(>thead th:nth-child(5))) thead th:nth-child(2){width:38%}
        .facility-detail .facility-price-table table:has(>thead th:nth-child(4)):not(:has(>thead th:nth-child(5))) thead th:nth-child(3){width:16%}
        .facility-detail .facility-price-table table:has(>thead th:nth-child(4)):not(:has(>thead th:nth-child(5))) thead th:nth-child(4){width:30%}
        .facility-detail .facility-price-table table:has(>thead th:nth-child(4)):not(:has(>thead th:nth-child(5))) tbody td:nth-child(2){white-space:normal;overflow-wrap:break-word;word-break:normal}
        .facility-detail .facility-price-table table:has(>thead th:nth-child(4)):not(:has(>thead th:nth-child(5))) tbody td:nth-child(3){white-space:nowrap;overflow-wrap:normal}
        .facility-detail .facility-price-table table:has(>thead th:nth-child(4)):not(:has(>thead th:nth-child(5))) tbody td:nth-child(4){font-size:11px;line-height:1.45}
      }
      body.facility-page-exiting .facility-detail{opacity:0;transition:opacity .16s ease}
      @media (hover:hover) and (pointer:fine){
        .facility-detail .hero-main:hover{border-color:#cfe0ff;box-shadow:0 14px 34px rgba(37,99,235,.075)}
        .facility-detail .section:hover,.facility-detail .side-card:hover{border-color:#d3e2fb;box-shadow:0 12px 28px rgba(15,23,42,.055)}
        .facility-detail .contact-item:hover,.facility-detail .stat-box:hover,.facility-detail .feature-item:hover{transform:translateY(-2px)}
        .facility-detail .service-card:hover .service-image img,.facility-detail .mini-gallery-item:hover img{transform:scale(1.045)}
        .facility-detail .thumb:hover img{transform:scale(1.07)}
        .facility-detail .review-row:hover{background:linear-gradient(90deg,rgba(248,251,255,.92),rgba(255,255,255,.98));box-shadow:inset 3px 0 0 rgba(96,165,250,.72)}
        .facility-detail :is(.icon-btn,.btn-secondary,.detail-nav a,.section-link,.review-more a):hover{transform:translateY(-1px)}
        .facility-detail .btn-primary:hover{transform:translateY(-2px);box-shadow:0 15px 27px rgba(37,99,235,.25)}
      }
      .facility-detail :is(.icon-btn,.btn-primary,.btn-secondary,.detail-nav a,.section-link,.review-more a,.review-write-inline,.contact-item,.service-card,.feature-item,.thumb,.mini-gallery-item):active{transform:scale(.975);transition-duration:.1s}
      @media (prefers-reduced-motion:no-preference){
        .facility-detail .breadcrumb{animation:facilityDetailFadeIn .26s ease both}
        .facility-detail .hero-grid{animation:facilityDetailEnter .42s cubic-bezier(.16,1,.3,1) both}
        .facility-detail .detail-nav{animation:facilityDetailEnter .4s .04s cubic-bezier(.16,1,.3,1) both}
        .facility-detail .motion-reveal{opacity:0;transform:translate3d(0,12px,0)}
        .facility-detail .motion-reveal.is-visible{animation:facilityDetailEnter .42s cubic-bezier(.16,1,.3,1) both}
        .facility-detail .motion-row-in{animation:facilityDetailRowIn .32s cubic-bezier(.16,1,.3,1) both}
        .facility-detail .motion-reveal.is-visible .bar span{transform-origin:left center;animation:facilityDetailBar .52s .12s cubic-bezier(.16,1,.3,1) both}
      }
      @keyframes facilityDetailFadeIn{from{opacity:0}to{opacity:1}}
      @keyframes facilityDetailEnter{from{opacity:0;transform:translate3d(0,12px,0)}to{opacity:1;transform:none}}
      @keyframes facilityDetailRowIn{from{opacity:0;transform:translate3d(0,8px,0)}to{opacity:1;transform:none}}
      @keyframes facilityDetailBar{from{transform:scaleX(0)}to{transform:scaleX(1)}}
      @media (prefers-reduced-motion:reduce){
        html{scroll-behavior:auto}
        .facility-detail *,body.facility-page-exiting .facility-detail{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <main class="facility-detail site-typo">
      <section class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="/">Trang chủ</a>
          <span class="crumb-sep" aria-hidden="true"><i data-lucide="chevron-right"></i></span>
          <a href="/co-so-y-te.php"><?php echo htmlspecialchars($facility['category'], ENT_QUOTES, 'UTF-8'); ?></a>
          <span class="crumb-sep" aria-hidden="true"><i data-lucide="chevron-right"></i></span>
          <span class="active"><?php echo htmlspecialchars($facility['name'], ENT_QUOTES, 'UTF-8'); ?></span>
        </nav>

        <div class="hero-grid">
          <div class="panel hero-main">
            <div class="hero-copy">
                <div class="hero-top">
                  <div class="hero-head">
                  <span class="facility-eyebrow"><i data-lucide="<?php echo !empty($facility['verified']) ? 'badge-check' : 'building-2'; ?>"></i><?php echo !empty($facility['verified']) ? 'Hồ sơ cơ sở đã xác thực' : 'Thông tin cơ sở y tế'; ?></span>
                  <h1><?php echo htmlspecialchars($facility['name'], ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($facility['verified'])): ?> <span class="verified-mark"><i data-lucide="badge-check"></i></span><?php endif; ?></h1>
                  <p class="subtitle"><?php echo htmlspecialchars($facility['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="hero-actions">
                  <button class="icon-btn" type="button" aria-label="Lưu" title="Lưu"><i data-lucide="heart"></i>Lưu</button>
                  <button class="icon-btn" type="button" aria-label="Chia sẻ" title="Chia sẻ"><i data-lucide="share-2"></i>Chia sẻ</button>
                  <button class="icon-btn" type="button" aria-label="Báo cáo" title="Báo cáo"><i data-lucide="triangle-alert"></i>Báo cáo</button>
                </div>
              </div>

              <div class="rating-line rating-pill">
                <span class="rating-main"><?php echo htmlspecialchars($facility['rating'], ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="stars rating-stars-text" aria-label="<?php echo htmlspecialchars($facility['rating'], ENT_QUOTES, 'UTF-8'); ?> trên 5"><?php echo $facilityRatingStars; ?></span>
                <span class="rating-meta">(<?php echo htmlspecialchars($facility['reviews'], ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars($facility['followers'], ENT_QUOTES, 'UTF-8'); ?>)</span>
              </div>

              <div class="tag-row">
                <?php foreach ($facility['tags'] as $tag): ?>
                  <span class="tag"><i data-lucide="tag"></i><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endforeach; ?>
              </div>

            </div>
          </div>
            <div class="hero-gallery">
              <?php $heroGalleryIndex = array_search((string) $facility['hero_image'], (array) $facility['gallery'], true); $heroGalleryCount = count((array) $facility['gallery']); ?>
              <button class="hero-image gallery-trigger" type="button" data-hero-gallery-carousel data-gallery-index="<?php echo (int) ($heroGalleryIndex === false ? 0 : $heroGalleryIndex); ?>" aria-roledescription="Bộ sưu tập ảnh" aria-label="Xem ảnh <?php echo (int) (($heroGalleryIndex === false ? 0 : $heroGalleryIndex) + 1); ?> trong bộ sưu tập của <?php echo htmlspecialchars($facility['name'], ENT_QUOTES, 'UTF-8'); ?>">
                <img src="<?php echo htmlspecialchars($facility['hero_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($facility['name'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="view-all"><i data-lucide="images"></i><span class="hero-gallery-count" data-hero-gallery-counter><?php echo (int) (($heroGalleryIndex === false ? 0 : $heroGalleryIndex) + 1); ?> / <?php echo $heroGalleryCount; ?></span><span class="hero-gallery-label">Xem tất cả <?php echo htmlspecialchars($facility['images_label'] ?: '25+ ảnh', ENT_QUOTES, 'UTF-8'); ?></span><span class="hero-swipe-hint">Vuốt để xem</span></span>
              </button>
              <div class="thumb-strip">
                <?php foreach (array_slice($facility['gallery'], 0, 8, true) as $imageIndex => $image): ?>
                  <button class="thumb gallery-trigger" type="button" data-gallery-index="<?php echo (int) $imageIndex; ?>" aria-label="Xem ảnh <?php echo (int) ($imageIndex + 1); ?> trong bộ sưu tập">
                    <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="Ảnh <?php echo (int) ($imageIndex + 1); ?> của <?php echo htmlspecialchars($facility['name'], ENT_QUOTES, 'UTF-8'); ?>">
                  </button>
                <?php endforeach; ?>
              </div>
            </div>

        </div>

        <div class="facility-reading-layout">
          <aside class="sidebar facility-contact-sidebar">
            <section class="panel side-card">
              <h2 class="contact-heading">Thông tin liên hệ</h2>
              <div class="contact-list">
                <div class="contact-item">
                  <i data-lucide="map-pin"></i>
                  <div>
                    <strong>Địa chỉ</strong>
                    <span><?php echo htmlspecialchars($facility['address'], ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                </div>
                <div class="contact-item">
                  <i data-lucide="phone"></i>
                  <div>
                    <strong>Điện thoại</strong>
                    <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', (string) $facility['phone']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($facility['phone'], ENT_QUOTES, 'UTF-8'); ?></a>
                  </div>
                </div>
                <div class="contact-item">
                  <i data-lucide="globe"></i>
                  <div>
                    <strong>Website</strong>
                    <a href="<?php echo htmlspecialchars($facility['website'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($facility['website'], ENT_QUOTES, 'UTF-8'); ?></a>
                  </div>
                </div>
                <div class="contact-item">
                  <i data-lucide="clock-3"></i>
                  <div>
                    <strong>Giờ mở cửa</strong>
                    <span><?php echo htmlspecialchars($facility['hours'], ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                </div>
              </div>


              <div class="contact-actions">
                <?php if (trim($facility['phone']) !== ''): ?>
                <a class="btn-primary" href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $facility['phone']), ENT_QUOTES, 'UTF-8'); ?>"><i data-lucide="phone"></i>Gọi cơ sở</a>
                <?php endif; ?>
                <?php if (trim($facility['address']) !== ''): ?>
                <a class="btn-secondary" href="https://www.google.com/maps/search/?api=1&amp;query=<?php echo rawurlencode($facility['name'] . ' ' . $facility['address']); ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="map-pin"></i>Chỉ đường</a>
                <?php endif; ?>
              </div>
            </section>
          </aside>
          <div class="facility-reading-main">

        <nav class="detail-nav" aria-label="Điều hướng nội dung trang">
          <a href="#gioi-thieu">Giới thiệu</a>
          <a href="#dich-vu">Dịch vụ nổi bật</a>
          <a href="#bang-gia">Bảng giá</a>
          <a href="#danh-gia">Đánh giá khách hàng</a>
          <a href="#dat-lich">Đặt lịch tư vấn</a>
        </nav>

        <section class="panel section" id="gioi-thieu">
          <h2>Giới thiệu về <?php echo htmlspecialchars($facility['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
          <div class="section-copy">
            <?php foreach ($facility['intro'] as $paragraph): ?>
              <?php if (strpos($paragraph, '<') !== false): ?><?php echo $paragraph; ?><?php else: ?><p><?php echo htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <?php endforeach; ?>
          </div>

          <?php $featuredServices = array_filter((array) ($facility['featured_services'] ?? []), 'is_string'); ?>
          <?php if ($featuredServices): ?>
          <section class="facility-subsection" id="dich-vu">
            <h2>Dịch vụ nổi bật</h2>
            <div class="service-chips"><?php foreach ($featuredServices as $service): ?><span><?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?></span><?php endforeach; ?></div>
          </section>
          <?php endif; ?>
          <?php $highlights = array_filter((array) ($facility['highlights'] ?? []), 'is_string'); ?>
          <?php if ($highlights): ?>
          <section class="facility-subsection">
            <h2>Điểm đáng chú ý</h2>
            <ul><?php foreach ($highlights as $highlight): ?><li><?php echo htmlspecialchars($highlight, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul>
          </section>
          <?php endif; ?>

          <?php if ($facilityPriceTableHtml !== ''): ?>
            <h2 id="bang-gia" class="facility-price-heading">Bảng giá dịch vụ</h2>
            <div class="facility-price-table"><?php echo $facilityPriceTableHtml; ?></div>
          <?php endif; ?>

        </section>

          </div>
        </div>

        <?php if ($facilityLegalFacts !== [] || $facilityVisitFacts !== [] || $facilityInsurance !== '' || $facilityPaymentMethods !== [] || $facilityLanguages !== [] || $facilityWarranty !== '' || $facilityDoctors !== [] || $facilityEquipment !== [] || $facilityRatingSources !== []): ?>
          <section class="facility-info-area" aria-label="Thông tin chi tiết về cơ sở">
            <div class="facility-info-heading">
              <div>
                <span class="facility-info-kicker"><i data-lucide="circle-check-big"></i>Hồ sơ tham khảo</span>
                <h2>Thông tin thêm về <?php echo htmlspecialchars($facility['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
              </div>
              <p>Thông tin được tổng hợp từ dữ liệu công khai và hồ sơ do cơ sở cung cấp.</p>
            </div>

            <div class="facility-info-grid">
              <?php if ($facilityLegalFacts !== []): ?>
                <article class="panel facility-info-card" id="thong-tin-ho-so">
                  <div class="facility-info-card-head"><span class="facility-info-icon"><i data-lucide="shield-check"></i></span><div><h3>Hồ sơ &amp; pháp lý</h3><p>Thông tin nhận diện cơ sở</p></div></div>
                  <dl class="facility-fact-list">
                    <?php foreach ($facilityLegalFacts as $fact): ?>
                      <div><dt><i data-lucide="<?php echo htmlspecialchars($fact['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i><?php echo htmlspecialchars($fact['label'], ENT_QUOTES, 'UTF-8'); ?></dt><dd><?php echo htmlspecialchars($fact['value'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <?php endforeach; ?>
                  </dl>
                </article>
              <?php endif; ?>

              <?php if ($facilityVisitFacts !== [] || $facilityMapUrl !== ''): ?>
                <article class="panel facility-info-card" id="trai-nghiem-den-kham">
                  <div class="facility-info-card-head"><span class="facility-info-icon"><i data-lucide="map-pin"></i></span><div><h3>Đến khám thuận tiện</h3><p>Chỉ dẫn thực tế trước khi ghé cơ sở</p></div></div>
                  <?php if ($facilityVisitFacts !== []): ?><dl class="facility-fact-list facility-fact-list--compact"><?php foreach ($facilityVisitFacts as $fact): ?><div><dt><i data-lucide="<?php echo htmlspecialchars($fact['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i><?php echo htmlspecialchars($fact['label'], ENT_QUOTES, 'UTF-8'); ?></dt><dd><?php echo htmlspecialchars($fact['value'], ENT_QUOTES, 'UTF-8'); ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
                  <?php if ($facilityMapUrl !== ''): ?><a class="facility-info-action" href="<?php echo htmlspecialchars($facilityMapUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="navigation"></i>Mở chỉ đường<i data-lucide="arrow-up-right"></i></a><?php endif; ?>
                </article>
              <?php endif; ?>

              <?php if ($facilityInsurance !== '' || $facilityPaymentMethods !== [] || $facilityLanguages !== [] || $facilityWarranty !== '' || $facilityBookingUrl !== '' || $facilitySocialLinks !== []): ?>
                <article class="panel facility-info-card" id="thanh-toan-ho-tro">
                  <div class="facility-info-card-head"><span class="facility-info-icon"><i data-lucide="wallet-cards"></i></span><div><h3>Thanh toán &amp; hỗ trợ</h3><p>Những thông tin hữu ích trước khi đặt lịch</p></div></div>
                  <?php if ($facilityInsurance !== ''): ?><div class="facility-info-note"><i data-lucide="receipt-text"></i><div><strong>Bảo hiểm</strong><span><?php echo htmlspecialchars($facilityInsurance, ENT_QUOTES, 'UTF-8'); ?></span></div></div><?php endif; ?>
                  <?php if ($facilityPaymentMethods !== []): ?><div class="facility-info-group"><strong>Hình thức thanh toán</strong><div class="facility-chip-list"><?php foreach ($facilityPaymentMethods as $paymentMethod): ?><span><?php echo htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8'); ?></span><?php endforeach; ?></div></div><?php endif; ?>
                  <?php if ($facilityLanguages !== []): ?><div class="facility-info-group"><strong>Ngôn ngữ hỗ trợ</strong><div class="facility-chip-list facility-chip-list--muted"><?php foreach ($facilityLanguages as $language): ?><span><?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?></span><?php endforeach; ?></div></div><?php endif; ?>
                  <?php if ($facilityWarranty !== ''): ?><div class="facility-info-note facility-info-note--soft"><i data-lucide="award"></i><div><strong>Chính sách bảo hành</strong><span><?php echo htmlspecialchars($facilityWarranty, ENT_QUOTES, 'UTF-8'); ?></span></div></div><?php endif; ?>
                  <?php if ($facilityBookingUrl !== '' || $facilitySocialLinks !== []): ?><div class="facility-link-row"><?php if ($facilityBookingUrl !== ''): ?><a class="facility-info-action" href="<?php echo htmlspecialchars($facilityBookingUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="calendar-check-2"></i>Đặt lịch<i data-lucide="arrow-up-right"></i></a><?php endif; ?><?php foreach (array_slice($facilitySocialLinks, 0, 3) as $social): ?><a class="facility-social-link" href="<?php echo htmlspecialchars($social['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($social['label'], ENT_QUOTES, 'UTF-8'); ?><i data-lucide="arrow-up-right"></i></a><?php endforeach; ?></div><?php endif; ?>
                </article>
              <?php endif; ?>

              <?php if ($facilityRatingSources !== []): ?>
                <article class="panel facility-info-card">
                  <div class="facility-info-card-head"><span class="facility-info-icon"><i data-lucide="star"></i></span><div><h3>Điểm theo nguồn đánh giá</h3><p>Các nguồn được ghi nhận trong hồ sơ</p></div></div>
                  <div class="facility-source-grid">
                    <?php foreach ($facilityRatingSources as $source): ?>
                      <div class="facility-source-item"><strong><?php echo htmlspecialchars($source['source'], ENT_QUOTES, 'UTF-8'); ?></strong><?php if ($source['score'] !== ''): ?><b><?php echo htmlspecialchars($source['score'], ENT_QUOTES, 'UTF-8'); ?><small>/5</small></b><?php endif; ?><span><?php echo $source['count'] > 0 ? number_format($source['count'], 0, ',', '.') . ' đánh giá' : ($source['recommend_percent'] > 0 ? $source['recommend_percent'] . '% đề xuất' : 'Đang cập nhật'); ?></span></div>
                    <?php endforeach; ?>
                  </div>
                </article>
              <?php endif; ?>

              <?php if ($facilityDoctors !== [] || $facilityEquipment !== [] || $facilityVideos !== []): ?>
                <article class="panel facility-info-card facility-info-card--wide" id="doi-ngu-cong-nghe">
                  <div class="facility-info-card-head"><span class="facility-info-icon"><i data-lucide="stethoscope"></i></span><div><h3>Đội ngũ &amp; công nghệ</h3><p>Thông tin chuyên môn được cơ sở công bố</p></div></div>
                  <div class="facility-team-grid">
                    <?php if ($facilityDoctors !== []): ?><div class="facility-team-column"><strong class="facility-column-title">Đội ngũ bác sĩ</strong><div class="facility-doctor-list"><?php foreach ($facilityDoctors as $doctor): ?><article><span><i data-lucide="user-round"></i></span><div><strong><?php echo htmlspecialchars($doctor['name'], ENT_QUOTES, 'UTF-8'); ?></strong><?php if ($doctor['title'] !== ''): ?><p><?php echo htmlspecialchars($doctor['title'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?><?php if ($doctor['specialty'] !== ''): ?><small><?php echo htmlspecialchars($doctor['specialty'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?></div></article><?php endforeach; ?></div></div><?php endif; ?>
                    <?php if ($facilityEquipment !== [] || $facilityVideos !== []): ?><div class="facility-team-column"><strong class="facility-column-title">Thiết bị &amp; tài liệu</strong><?php if ($facilityEquipment !== []): ?><ul class="facility-equipment-list"><?php foreach ($facilityEquipment as $equipment): ?><li><i data-lucide="scan-line"></i><?php echo htmlspecialchars($equipment, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul><?php endif; ?><?php if ($facilityVideos !== []): ?><div class="facility-video-links"><?php foreach (array_slice($facilityVideos, 0, 3) as $videoUrl): ?><a href="<?php echo htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i data-lucide="play-circle"></i>Xem video giới thiệu<i data-lucide="arrow-up-right"></i></a><?php endforeach; ?></div><?php endif; ?></div><?php endif; ?>
                  </div>
                </article>
              <?php endif; ?>
            </div>
          </section>
        <?php endif; ?>

        <section class="panel section" id="danh-gia">
          <div class="section-head">
            <div>
              <h2>Đánh giá thực tế từ khách hàng</h2>
              <p style="margin:6px 0 0;color:#64748b;font-size:14px;"><?php echo $facilityReviewCount > 0 ? 'Dựa trên ' . htmlspecialchars((string) $facilityReviewSummary['reviews'], ENT_QUOTES, 'UTF-8') . ' đã công bố' : 'Chưa có đánh giá được công bố.'; ?></p>
            </div>
          </div>

          <div class="review-shell">
            <div class="summary-box">
              <h3>Đánh giá thực tế</h3>
              <p><?php echo $facilityReviewCount > 0 ? 'Tổng hợp từ ' . htmlspecialchars((string) $facilityReviewSummary['reviews'], ENT_QUOTES, 'UTF-8') : 'Chưa có dữ liệu điểm đánh giá'; ?></p>
              <div class="summary-score"><strong><?php echo htmlspecialchars((string) ($facilityReviewSummary['rating'] ?? '0.0'), ENT_QUOTES, 'UTF-8'); ?></strong><small>/5</small></div>
              <div class="stars summary-stars" aria-label="<?php echo htmlspecialchars((string) ($facilityReviewSummary['rating'] ?? '0.0'), ENT_QUOTES, 'UTF-8'); ?> trên 5"><?php echo $facilitySummaryStars; ?></div>
              <div class="summary-caption"><?php echo htmlspecialchars((string) ($facilityReviewSummary['reviews'] ?? '0 đánh giá'), ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="rating-bars">
                <?php foreach ((array) ($facilityReviewSummary['breakdown'] ?? []) as $row): ?>
                  <div class="rating-row">
                    <span><?php echo htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="bar"><span style="width:<?php echo (int) $row['value']; ?>%"></span></span>
                    <span><?php echo (int) $row['value']; ?>%</span>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if ($facilityReviewCount > 0): ?>
                <div class="summary-verified">
                  <strong><?php echo $facilityVerifiedReviewPercent; ?>%</strong>
                  <span>Đánh giá xác thực</span>
                  <small><?php echo $facilityVerifiedReviewCount; ?>/<?php echo $facilityReviewCount; ?> đánh giá có nhãn xác thực.</small>
                </div>
              <?php endif; ?>
            </div>

            <div class="review-feed">
              <div class="review-feed-toolbar">
                <div class="review-toolbar-group">
                  <?php if ($facilityReviewServices !== []): ?>
                    <label class="review-filter-pill"><i data-lucide="filter"></i><select id="reviewServiceFilter"><option value="">Tất cả dịch vụ</option><?php foreach ($facilityReviewServices as $service): ?><option value="<?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
                  <?php endif; ?>
                  <label class="review-sort-pill"><i data-lucide="arrow-down-wide-narrow"></i><select id="reviewSort"><option value="newest">Mới nhất</option><option value="highest">Điểm cao nhất</option></select></label>
                </div>
                <label class="review-switch" for="reviewImageOnly">
                  <span>Chỉ đánh giá có ảnh</span>
                  <input id="reviewImageOnly" type="checkbox">
                  <span class="review-switch-toggle" aria-hidden="true"></span>
                </label>
              </div>

              <div class="review-list" id="facilityReviewList" data-facility-id="<?php echo (int) ($facility['id'] ?? 0); ?>" data-facility-slug="<?php echo htmlspecialchars((string) ($facility['slug'] ?? $slug), ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ((array) ($facility['reviews_list'] ?? []) as $review): ?>
                  <?php
                    $reviewLocation = trim((string) ($review['location'] ?? ''));
                    $reviewDate = trim((string) ($review['date'] ?? ''));
                    $reviewLikes = (int) ($review['likes'] ?? 0);
                    $reviewComments = (int) ($review['comments'] ?? 0);
                    $reviewRating = max(0, min(5, (float) ($review['rating'] ?? 0)));
                    $reviewStars = str_repeat('★', (int) round($reviewRating)) . str_repeat('☆', 5 - (int) round($reviewRating));
                    $reviewImages = array_values(array_filter((array) ($review['images'] ?? []), static function ($image): bool {
                      return trim((string) $image) !== '';
                    }));
                    $reviewExtraImages = max(0, count($reviewImages) - 3);
                  ?>
                  <article class="review-row">
                    <div class="review-author">
                      <span class="avatar"><?php echo htmlspecialchars(mb_substr((string) ($review['author'] ?: 'K'), 0, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                      <div>
                        <strong><?php echo htmlspecialchars($review['author'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if ($reviewLocation !== ''): ?><span class="meta-line"><?php echo htmlspecialchars($reviewLocation, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                        <?php if (!empty($review['is_verified'])): ?><span class="review-badge"><i data-lucide="badge-check"></i>Đã xác thực</span><?php endif; ?>
                      </div>
                    </div>

                    <div class="review-body">
                      <div class="review-content-top">
                        <?php if ($reviewRating > 0): ?><div class="review-rating"><span class="review-stars" aria-label="<?php echo htmlspecialchars(number_format($reviewRating, 1, '.', ''), ENT_QUOTES, 'UTF-8'); ?> trên 5"><?php echo $reviewStars; ?></span><strong><?php echo htmlspecialchars(number_format($reviewRating, 1, '.', ''), ENT_QUOTES, 'UTF-8'); ?></strong></div><?php else: ?><span class="review-unrated">Chia sẻ trải nghiệm</span><?php endif; ?>
                        <?php if ($reviewDate !== ''): ?><span class="review-date"><?php echo htmlspecialchars($reviewDate, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                      </div>
                      <div class="review-content"><?php echo htmlspecialchars($review['content'], ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php if (trim((string) $review['service']) !== '' || trim((string) ($review['source'] ?? '')) !== ''): ?><div class="review-detail-row"><?php if (trim((string) $review['service']) !== ''): ?><span class="review-service"><i data-lucide="stethoscope"></i><?php echo htmlspecialchars($review['service'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?><?php if (trim((string) ($review['source'] ?? '')) !== ''): ?><span class="review-source"><i data-lucide="link"></i><?php echo htmlspecialchars($review['source'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></div><?php endif; ?>
                      <?php if ($reviewImages !== []): ?>
                        <div class="mini-gallery">
                          <?php foreach (array_slice($reviewImages, 0, 3) as $imageIndex => $image): ?>
                            <div class="mini-gallery-item">
                              <span><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="Ảnh đính kèm đánh giá" loading="lazy" onerror="this.closest('.mini-gallery-item').remove();"></span>
                              <?php if ($imageIndex === 2 && $reviewExtraImages > 0): ?><span class="mini-gallery-more">+<?php echo $reviewExtraImages; ?></span><?php endif; ?>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <div class="review-meta">
                      <?php if ($reviewLikes > 0): ?><span><i data-lucide="heart"></i><?php echo $reviewLikes; ?></span><?php endif; ?>
                      <?php if ($reviewComments > 0): ?><span><i data-lucide="message-circle"></i><?php echo $reviewComments; ?></span><?php endif; ?>
                    </div>
                  </article>
                <?php endforeach; ?>
                <?php if ((array) ($facility['reviews_list'] ?? []) === []): ?><div class="review-empty"><i data-lucide="message-circle"></i><strong>Chưa có đánh giá để hiển thị</strong><span>Đánh giá mới sẽ xuất hiện tại đây sau khi được công bố.</span></div><?php endif; ?>
              </div>

              <div class="review-more">
                <a href="#" id="loadMoreReviews" data-page="2"><i data-lucide="chevron-down"></i><span>Xem thêm đánh giá</span></a>
                <a class="review-write-inline" href="/review.php"><i data-lucide="pen-line"></i>Viết đánh giá</a>
              </div>
            </div>
          </div>
        </section>

        <section class="feature-row">
          <?php foreach ($facility['features'] as $feature): ?>
            <article class="feature-item">
              <span class="feature-icon"><i data-lucide="<?php echo htmlspecialchars($feature['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
              <strong><?php echo htmlspecialchars($feature['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
              <p><?php echo htmlspecialchars($feature['text'], ENT_QUOTES, 'UTF-8'); ?></p>
            </article>
          <?php endforeach; ?>
        </section>

        <section class="cta-banner" id="dat-lich">
          <div class="cta-copy">
            <span class="cta-icon"><i data-lucide="calendar-check-2"></i></span>
            <div>
              <strong>Đặt lịch khám ngay để được tư vấn miễn phí!</strong>
              <span>Đội ngũ bác sĩ chuyên môn cao luôn sẵn sàng đồng hành cùng bạn.</span>
            </div>
          </div>
          <a href="/lien-he.php">Đặt lịch ngay</a>
        </section>
      </section>
    </main>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
    <div class="gallery-lightbox" id="facilityGalleryLightbox" aria-hidden="true">
      <div class="gallery-lightbox__backdrop" data-gallery-lightbox-close aria-hidden="true"></div>
      <section class="gallery-lightbox__dialog" role="dialog" aria-modal="true" aria-label="Xem ảnh cơ sở y tế" tabindex="-1">
        <div class="gallery-lightbox__topbar">
          <span class="gallery-lightbox__counter" id="facilityGalleryCounter" aria-live="polite">1 / 1</span>
          <button class="gallery-lightbox__control gallery-lightbox__close" type="button" data-gallery-lightbox-close aria-label="Đóng trình xem ảnh" title="Đóng (Esc)">&times;</button>
        </div>
        <div class="gallery-lightbox__stage" id="facilityGalleryStage">
          <button class="gallery-lightbox__control gallery-lightbox__nav gallery-lightbox__prev" type="button" data-gallery-lightbox-prev aria-label="Ảnh trước" title="Ảnh trước (mũi tên trái)">&#8249;</button>
          <img class="gallery-lightbox__image" id="facilityGalleryImage" src="" alt="">
          <button class="gallery-lightbox__control gallery-lightbox__nav gallery-lightbox__next" type="button" data-gallery-lightbox-next aria-label="Ảnh tiếp theo" title="Ảnh tiếp theo (mũi tên phải)">&#8250;</button>
          <p class="gallery-lightbox__status" id="facilityGalleryStatus" aria-live="polite"></p>
        </div>
      </section>
    </div>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
      if (window.lucide) {
        window.lucide.createIcons();
      }
      (function () {
        const gallerySources = <?php echo json_encode(array_values((array) $facility['gallery']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]'; ?>;
        const galleryTriggers = Array.from(document.querySelectorAll('.gallery-trigger[data-gallery-index]'));
        const heroGalleryTrigger = document.querySelector('[data-hero-gallery-carousel]');
        const heroGalleryImage = heroGalleryTrigger?.querySelector('img');
        const heroGalleryCounter = heroGalleryTrigger?.querySelector('[data-hero-gallery-counter]');
        const galleryThumbs = Array.from(document.querySelectorAll('.thumb.gallery-trigger[data-gallery-index]'));
        const lightbox = document.getElementById('facilityGalleryLightbox');
        const lightboxImage = document.getElementById('facilityGalleryImage');
        const lightboxCounter = document.getElementById('facilityGalleryCounter');
        const lightboxStatus = document.getElementById('facilityGalleryStatus');
        const lightboxDialog = lightbox?.querySelector('.gallery-lightbox__dialog');
        const previousImage = lightbox?.querySelector('[data-gallery-lightbox-prev]');
        const nextImage = lightbox?.querySelector('[data-gallery-lightbox-next]');
        const closeImage = lightbox?.querySelector('.gallery-lightbox__close');
        const galleryName = <?php echo json_encode((string) $facility['name'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '"Cơ sở y tế"'; ?>;
        let activeGalleryIndex = 0;
        let lastGalleryTrigger = null;
        let galleryImageRequest = 0;
        let swipeStart = null;
        let heroGalleryIndex = Number(heroGalleryTrigger?.dataset.galleryIndex || 0);
        let heroSwipeStart = null;
        let heroSwipeSuppressClick = false;
        let heroImageRequest = 0;

        const galleryReady = lightbox && lightboxImage && lightboxCounter && lightboxStatus && gallerySources.length > 0;
        const galleryIsOpen = () => galleryReady && lightbox.classList.contains('is-open');
        const normalizeGalleryIndex = (index) => ((index % gallerySources.length) + gallerySources.length) % gallerySources.length;

        function syncHeroGallery(index, shouldRevealThumb = false) {
          if (!heroGalleryTrigger || !heroGalleryImage || gallerySources.length === 0) return;
          heroGalleryIndex = normalizeGalleryIndex(index);
          const requestId = ++heroImageRequest;
          const source = gallerySources[heroGalleryIndex];
          const preload = new Image();
          heroGalleryTrigger.classList.add('is-changing');
          preload.onload = () => {
            if (requestId !== heroImageRequest) return;
            heroGalleryImage.src = source;
            heroGalleryImage.alt = `Ảnh ${heroGalleryIndex + 1} của ${galleryName}`;
            heroGalleryTrigger.dataset.galleryIndex = String(heroGalleryIndex);
            heroGalleryTrigger.setAttribute('aria-label', `Xem ảnh ${heroGalleryIndex + 1} trong bộ sưu tập của ${galleryName}`);
            if (heroGalleryCounter) heroGalleryCounter.textContent = `${heroGalleryIndex + 1} / ${gallerySources.length}`;
            galleryThumbs.forEach((thumb) => {
              const isActive = Number(thumb.dataset.galleryIndex || 0) === heroGalleryIndex;
              thumb.classList.toggle('is-active', isActive);
              thumb.setAttribute('aria-current', isActive ? 'true' : 'false');
              if (isActive && shouldRevealThumb && window.matchMedia('(max-width: 767px)').matches) {
                thumb.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest', inline: 'center' });
              }
            });
            window.setTimeout(() => {
              if (requestId === heroImageRequest) heroGalleryTrigger.classList.remove('is-changing');
            }, 180);
          };
          preload.onerror = () => {
            if (requestId === heroImageRequest) heroGalleryTrigger.classList.remove('is-changing');
          };
          preload.src = source;
        }

        function moveHeroGallery(direction) {
          if (gallerySources.length < 2) return;
          syncHeroGallery(heroGalleryIndex + direction, true);
        }

        function updateGalleryControls() {
          if (!galleryReady) return;
          const count = gallerySources.length;
          lightboxCounter.textContent = `${activeGalleryIndex + 1} / ${count}`;
          previousImage.disabled = count < 2;
          nextImage.disabled = count < 2;
        }

        function finishGalleryImage(requestId, failed) {
          if (!galleryReady || requestId !== galleryImageRequest) return;
          window.requestAnimationFrame(() => {
            if (requestId !== galleryImageRequest) return;
            lightbox.classList.remove('is-loading');
            lightboxImage.classList.remove('is-switching', 'is-enter-from-next', 'is-enter-from-prev');
            lightboxStatus.textContent = failed
              ? 'Không thể tải ảnh này. Bạn có thể chuyển sang ảnh khác.'
              : `Ảnh ${activeGalleryIndex + 1} trong ${gallerySources.length}`;
          });
        }

        function showGalleryImage(index, direction = 0) {
          if (!galleryReady) return;
          activeGalleryIndex = normalizeGalleryIndex(index);
          const requestId = ++galleryImageRequest;
          const source = gallerySources[activeGalleryIndex];
          updateGalleryControls();
          lightbox.classList.add('is-loading');
          lightboxImage.classList.remove('is-enter-from-next', 'is-enter-from-prev');
          lightboxImage.classList.add('is-switching');
          if (direction > 0) lightboxImage.classList.add('is-enter-from-next');
          if (direction < 0) lightboxImage.classList.add('is-enter-from-prev');
          lightboxStatus.textContent = 'Đang tải ảnh…';
          lightboxImage.alt = `Ảnh ${activeGalleryIndex + 1} của ${galleryName}`;
          lightboxImage.onload = () => finishGalleryImage(requestId, false);
          lightboxImage.onerror = () => finishGalleryImage(requestId, true);
          lightboxImage.src = source;
          if (lightboxImage.complete) {
            window.setTimeout(() => finishGalleryImage(requestId, !lightboxImage.naturalWidth), 0);
          }
        }

        function setGalleryOrigin(trigger) {
          if (!galleryReady) return;
          const rect = trigger && typeof trigger.getBoundingClientRect === 'function'
            ? trigger.getBoundingClientRect()
            : null;
          if (!rect || !rect.width || !rect.height) {
            lightbox.style.setProperty('--gallery-origin-x', '0px');
            lightbox.style.setProperty('--gallery-origin-y', '18px');
            return;
          }
          const distanceX = (rect.left + (rect.width / 2)) - (window.innerWidth / 2);
          const distanceY = (rect.top + (rect.height / 2)) - (window.innerHeight / 2);
          const originX = Math.max(-150, Math.min(150, Math.round(distanceX * .28)));
          const originY = Math.max(-105, Math.min(105, Math.round(distanceY * .22)));
          lightbox.style.setProperty('--gallery-origin-x', `${originX}px`);
          lightbox.style.setProperty('--gallery-origin-y', `${originY}px`);
        }

        function openGallery(index, trigger) {
          if (!galleryReady) return;
          lastGalleryTrigger = trigger || document.activeElement;
          setGalleryOrigin(lastGalleryTrigger);
          showGalleryImage(index, 0);
          lightbox.setAttribute('aria-hidden', 'false');
          document.body.classList.add('gallery-lightbox-open');
          window.requestAnimationFrame(() => lightbox.classList.add('is-open'));
          window.setTimeout(() => closeImage?.focus({ preventScroll: true }), 0);
        }

        function closeGallery() {
          if (!galleryIsOpen()) return;
          lightbox.classList.remove('is-open');
          lightbox.setAttribute('aria-hidden', 'true');
          document.body.classList.remove('gallery-lightbox-open');
          const trigger = lastGalleryTrigger;
          window.setTimeout(() => {
            if (trigger && document.contains(trigger)) trigger.focus({ preventScroll: true });
          }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 230);
        }

        function moveGallery(direction) {
          if (galleryReady && gallerySources.length > 1) showGalleryImage(activeGalleryIndex + direction, direction);
        }

        function handleGalleryKeydown(event) {
          if (!galleryIsOpen()) return;
          if (event.key === 'Escape') {
            event.preventDefault();
            closeGallery();
          } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            moveGallery(-1);
          } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            moveGallery(1);
          } else if (event.key === 'Tab') {
            const focusable = [closeImage, previousImage, nextImage].filter((element) => element && !element.disabled);
            if (!focusable.length) return;
            const currentFocus = focusable.indexOf(document.activeElement);
            const nextFocus = event.shiftKey
              ? focusable[(currentFocus <= 0 ? focusable.length : currentFocus) - 1]
              : focusable[(currentFocus + 1) % focusable.length];
            event.preventDefault();
            nextFocus.focus();
          }
        }

        if (galleryReady) {
          syncHeroGallery(heroGalleryIndex, false);
          galleryTriggers.forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
              if (trigger === heroGalleryTrigger && heroSwipeSuppressClick) {
                event.preventDefault();
                return;
              }
              event.preventDefault();
              openGallery(Number(trigger.dataset.galleryIndex || 0), trigger);
            });
          });

          // On a phone, the cover photo acts as a lightweight carousel first.
          // A tap still opens the full viewer; a horizontal swipe only changes
          // the cover and never steals normal vertical page scrolling.
          heroGalleryTrigger?.addEventListener('touchstart', (event) => {
            if (gallerySources.length < 2 || event.touches.length !== 1) return;
            const touch = event.touches[0];
            heroSwipeStart = { x: touch.clientX, y: touch.clientY };
          }, { passive: true });
          heroGalleryTrigger?.addEventListener('touchend', (event) => {
            if (!heroSwipeStart || event.changedTouches.length !== 1) return;
            const touch = event.changedTouches[0];
            const deltaX = touch.clientX - heroSwipeStart.x;
            const deltaY = touch.clientY - heroSwipeStart.y;
            heroSwipeStart = null;
            if (Math.abs(deltaX) < 42 || Math.abs(deltaX) <= Math.abs(deltaY) * 1.25) return;
            heroSwipeSuppressClick = true;
            window.setTimeout(() => { heroSwipeSuppressClick = false; }, 420);
            moveHeroGallery(deltaX < 0 ? 1 : -1);
          }, { passive: true });
          heroGalleryTrigger?.addEventListener('touchcancel', () => { heroSwipeStart = null; }, { passive: true });

          previousImage.addEventListener('click', () => moveGallery(-1));
          nextImage.addEventListener('click', () => moveGallery(1));
          lightbox.querySelectorAll('[data-gallery-lightbox-close]').forEach((element) => element.addEventListener('click', closeGallery));
          document.addEventListener('keydown', handleGalleryKeydown);

          lightboxDialog?.addEventListener('touchstart', (event) => {
            if (event.touches.length !== 1) return;
            const touch = event.touches[0];
            swipeStart = { x: touch.clientX, y: touch.clientY };
          }, { passive: true });
          lightboxDialog?.addEventListener('touchend', (event) => {
            if (!swipeStart || event.changedTouches.length !== 1) return;
            const touch = event.changedTouches[0];
            const deltaX = touch.clientX - swipeStart.x;
            const deltaY = touch.clientY - swipeStart.y;
            swipeStart = null;
            if (Math.abs(deltaX) >= 42 && Math.abs(deltaX) > Math.abs(deltaY) * 1.25) moveGallery(deltaX < 0 ? 1 : -1);
          }, { passive: true });
          lightboxDialog?.addEventListener('touchcancel', () => { swipeStart = null; }, { passive: true });
        }
        const list = document.getElementById('facilityReviewList');
        const button = document.getElementById('loadMoreReviews');
        const serviceFilter = document.getElementById('reviewServiceFilter');
        const sortFilter = document.getElementById('reviewSort');
        const imageOnly = document.getElementById('reviewImageOnly');
        if (!list || !button) return;
        const esc = (value) => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const render = (r) => {
          const author = esc(r.author || 'Khách hàng');
          const text = esc(r.excerpt || r.content || '');
          const service = esc(r.service || '');
          const source = esc(r.source || '');
          const date = esc(r.date || '');
          const location = esc(r.location || '');
          const rating = Math.max(0, Math.min(5, Number(r.rating || 0)));
          const rounded = Math.round(rating);
          const stars = '★'.repeat(rounded) + '☆'.repeat(5 - rounded);
          const images = [...new Set([
            ...(Array.isArray(r.thumbs) ? r.thumbs : []),
            r.before_image_url || r.before || '',
            r.after_image_url || r.after || ''
          ].map(value => String(value || '').trim()).filter(Boolean))];
          const gallery = images.length ? `<div class="mini-gallery">${images.slice(0, 3).map((image, index) => `<div class="mini-gallery-item"><span><img src="${esc(image)}" alt="Ảnh đính kèm đánh giá" loading="lazy" onerror="this.closest('.mini-gallery-item').remove()"></span>${index === 2 && images.length > 3 ? `<span class="mini-gallery-more">+${images.length - 3}</span>` : ''}</div>`).join('')}</div>` : '';
          const details = service || source ? `<div class="review-detail-row">${service ? `<span class="review-service"><i data-lucide="stethoscope"></i>${service}</span>` : ''}${source ? `<span class="review-source"><i data-lucide="link"></i>${source}</span>` : ''}</div>` : '';
          const ratingMarkup = rating > 0 ? `<div class="review-rating"><span class="review-stars" aria-label="${rating.toFixed(1)} trên 5">${stars}</span><strong>${rating.toFixed(1)}</strong></div>` : '<span class="review-unrated">Chia sẻ trải nghiệm</span>';
          const meta = `${Number(r.likes || 0) > 0 ? `<span><i data-lucide="heart"></i>${Number(r.likes)}</span>` : ''}${Number(r.comments || 0) > 0 ? `<span><i data-lucide="message-circle"></i>${Number(r.comments)}</span>` : ''}`;
          return `<article class="review-row"><div class="review-author"><span class="avatar">${esc(author.charAt(0) || 'K')}</span><div><strong>${author}</strong>${location ? `<span class="meta-line">${location}</span>` : ''}${r.is_verified ? '<span class="review-badge"><i data-lucide="badge-check"></i>Đã xác thực</span>' : ''}</div></div><div class="review-body"><div class="review-content-top">${ratingMarkup}${date ? `<span class="review-date">${date}</span>` : ''}</div><div class="review-content">${text}</div>${details}${gallery}</div><div class="review-meta">${meta}</div></article>`;
        };
        const reviewParams = (page) => new URLSearchParams({ facility_id: list.dataset.facilityId || '0', facility_slug: list.dataset.facilitySlug || '', page: String(page), limit: '3', service: serviceFilter?.value || '', sort: sortFilter?.value || 'newest', has_images: imageOnly?.checked ? '1' : '' });
        button.addEventListener('click', async (event) => {
          event.preventDefault();
          if (button.dataset.loading === '1') return;
          button.dataset.loading = '1';
          button.querySelector('span').textContent = 'Đang tải…';
          const params = reviewParams(button.dataset.page || '2');
          try {
            const response = await fetch('/api/medical/facility-reviews.php?' + params.toString(), { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'Không thể tải đánh giá');
            list.insertAdjacentHTML('beforeend', (data.items || []).map(render).join(''));
            if (window.lucide) window.lucide.createIcons();
            button.dataset.page = String(Number(button.dataset.page || 2) + 1);
            if (!data.has_more || !(data.items || []).length) button.style.display = 'none';
            else button.querySelector('span').textContent = 'Xem thêm đánh giá';
          } catch (error) {
            button.querySelector('span').textContent = 'Thử lại';
            console.error(error);
          } finally { button.dataset.loading = '0'; }
        });
        const refreshReviews = async () => {
          list.querySelectorAll('.review-row').forEach((row, i) => { if (i >= 0) row.remove(); });
          button.dataset.page = '2'; button.style.display = '';
          const response = await fetch('/api/medical/facility-reviews.php?' + reviewParams(1).toString());
          const data = await response.json();
          const items = data.items || [];
          list.insertAdjacentHTML('afterbegin', items.length ? items.map(render).join('') : '<div class="review-empty"><i data-lucide="message-circle"></i><strong>Không có đánh giá phù hợp</strong><span>Hãy thử thay đổi bộ lọc.</span></div>');
          if (window.lucide) window.lucide.createIcons();
          if (!data.has_more) button.style.display = 'none';
        };
        serviceFilter?.addEventListener('change', refreshReviews); sortFilter?.addEventListener('change', refreshReviews); imageOnly?.addEventListener('change', refreshReviews);
      }());
    </script>
    <script id="facility-detail-motion-script">
      (() => {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const page = document.querySelector('.facility-detail');
        if (!page || reduceMotion.matches) return;

        const revealTargets = Array.from(page.querySelectorAll('.facility-reading-main > .section, #danh-gia, .feature-row, .cta-banner'));
        const show = (element) => element.classList.add('is-visible');
        const observer = 'IntersectionObserver' in window
          ? new IntersectionObserver((entries) => {
              entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                show(entry.target);
                observer.unobserve(entry.target);
              });
            }, { rootMargin: '0px 0px -9% 0px', threshold: 0.06 })
          : null;

        revealTargets.forEach((element) => {
          element.classList.add('motion-reveal');
          if (element.getBoundingClientRect().top < window.innerHeight * .88 || !observer) show(element);
          else observer.observe(element);
        });

        const reviewList = page.querySelector('#facilityReviewList');
        if (reviewList && 'MutationObserver' in window) {
          const reviewObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
              if (!(node instanceof Element) || !node.matches('.review-row')) return;
              node.classList.add('motion-row-in');
              window.setTimeout(() => node.classList.remove('motion-row-in'), 420);
            }));
          });
          reviewObserver.observe(reviewList, { childList: true });
        }

        document.addEventListener('click', (event) => {
          const link = event.target.closest('.facility-detail a[href]');
          if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
          if (link.target && link.target !== '_self' || link.hasAttribute('download') || link.dataset.noPageMotion !== undefined) return;
          let destination;
          try { destination = new URL(link.href, window.location.href); } catch (_) { return; }
          if (!/^https?:$/.test(destination.protocol) || destination.origin !== window.location.origin) return;
          if (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash) return;
          event.preventDefault();
          document.body.classList.add('facility-page-exiting');
          window.setTimeout(() => { window.location.href = destination.href; }, 155);
        });
      })();
    </script>
  </body>
</html>
