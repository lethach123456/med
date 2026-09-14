<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/medical_directory.php';
if (function_exists('admin_front_session_boot')) { admin_front_session_boot(); }

$reviews = [
  [
    'slug' => 'nieng-invisalign-sau-18-thang',
    'title' => 'Niềng Invisalign sau 18 tháng',
    'rating' => '4.9',
    'verified' => 'Đã xác minh',
    'author' => 'Nữ, 26 tuổi',
    'location' => 'Đà Nẵng',
    'date' => '18/05/2024',
    'likes' => 128,
    'comments' => 32,
    'shares' => 12,
    'facility' => [
      'name' => 'Top Dental Clinic',
      'rating' => '4.8',
      'reviews' => 1268,
      'location' => 'Đà Nẵng',
      'cta' => 'Xem phòng khám',
    ],
    'price' => '45.000.000đ',
    'service' => 'Niềng răng',
    'method' => 'Invisalign',
    'duration' => '18 tháng',
    'start_date' => '12/11/2022',
    'end_date' => '12/05/2024',
    'condition' => 'Khớp cắn sâu, răng chen chúc',
    'story' => [
      'Mình từng rất tự ti vì răng khấp khểnh, chen chúc và khớp cắn sâu. Sau khi tìm hiểu nhiều nơi, mình quyết định niềng Invisalign tại Top Dental Clinic.',
      'Quá trình niềng diễn ra nhẹ nhàng, không đau nhiều như mình nghĩ. Bác sĩ theo dõi rất sát, mỗi lần tái khám đều rất tận tình.',
      'Sau 18 tháng, mình có nụ cười hàm răng đều đẹp, khớp cắn chuẩn và nụ cười tự tin hơn rất nhiều.',
    ],
    'timeline' => [
      ['date' => '12/11/2022', 'label' => 'Khám và tư vấn'],
      ['date' => '26/11/2022', 'label' => 'Bắt đầu niềng'],
      ['date' => '12/02/2023', 'label' => 'Điều chỉnh lần 1'],
      ['date' => '12/08/2023', 'label' => 'Điều chỉnh lần 2'],
      ['date' => '12/05/2024', 'label' => 'Kết thúc niềng'],
    ],
    'hero_images' => [
      ['label' => 'Before', 'src' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=1200&q=80'],
      ['label' => 'After', 'src' => 'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=1200&q=80'],
    ],
    'thumbs' => [
      'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=400&q=80',
    ],
    'process_images' => [
      'before' => [
        'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=500&q=80',
      ],
      'during' => [
        'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=500&q=80',
      ],
      'after' => [
        'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=500&q=80',
      ],
    ],
  ],
  [
    'slug' => 'trong-2-rang-implant',
    'title' => 'Trồng 2 răng Implant',
    'rating' => '4.8',
    'verified' => 'Đã xác minh',
    'author' => 'Nam, 45 tuổi',
    'location' => 'Đà Nẵng',
    'date' => '08/04/2024',
    'likes' => 96,
    'comments' => 18,
    'shares' => 8,
    'facility' => [
      'name' => 'Nha Khoa Răng Xinh',
      'rating' => '4.7',
      'reviews' => 864,
      'location' => 'Đà Nẵng',
      'cta' => 'Xem phòng khám',
    ],
    'price' => '32.000.000đ',
    'service' => 'Implant',
    'method' => 'Cấy ghép Implant',
    'duration' => '6 tháng',
    'start_date' => '10/10/2023',
    'end_date' => '08/04/2024',
    'condition' => 'Mất 2 răng hàm',
    'story' => [
      'Mình bị mất 2 răng hàm nên ăn nhai rất khó. Sau khi tham khảo một số nơi, mình chọn Răng Xinh để cấy Implant.',
      'Quá trình điều trị được chia từng giai đoạn rõ ràng nên khá yên tâm. Sau khi hồi phục, ăn nhai cải thiện rõ rệt.',
      'Kết quả cuối cùng ổn định, nhìn tự nhiên và không còn cảm giác khó chịu khi ăn như trước.',
    ],
    'timeline' => [
      ['date' => '10/10/2023', 'label' => 'Khám tổng quát'],
      ['date' => '22/10/2023', 'label' => 'Cấy trụ Implant'],
      ['date' => '25/12/2023', 'label' => 'Tái khám'],
      ['date' => '14/02/2024', 'label' => 'Gắn mão tạm'],
      ['date' => '08/04/2024', 'label' => 'Hoàn thiện'],
    ],
    'hero_images' => [
      ['label' => 'Before', 'src' => 'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=1200&q=80'],
      ['label' => 'After', 'src' => 'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=1200&q=80'],
    ],
    'thumbs' => [
      'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=400&q=80',
    ],
    'process_images' => [
      'before' => [
        'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=500&q=80',
      ],
      'during' => [
        'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=500&q=80',
      ],
      'after' => [
        'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=500&q=80',
      ],
    ],
  ],
  [
    'slug' => 'rang-su-zirconia-tu-nhien',
    'title' => 'Răng sứ Zirconia tự nhiên',
    'rating' => '4.9',
    'verified' => 'Đã xác minh',
    'author' => 'Nữ, 32 tuổi',
    'location' => 'Đà Nẵng',
    'date' => '26/03/2024',
    'likes' => 76,
    'comments' => 15,
    'shares' => 6,
    'facility' => [
      'name' => 'Nha Khoa Paris Đà Nẵng',
      'rating' => '4.8',
      'reviews' => 1091,
      'location' => 'Đà Nẵng',
      'cta' => 'Xem phòng khám',
    ],
    'price' => '28.000.000đ',
    'service' => 'Răng sứ',
    'method' => 'Zirconia',
    'duration' => '10 ngày',
    'start_date' => '15/03/2024',
    'end_date' => '26/03/2024',
    'condition' => 'Răng xỉn màu, men yếu',
    'story' => [
      'Mình muốn làm răng sứ nhưng vẫn ưu tiên vẻ tự nhiên nên đã chọn dòng Zirconia. Sau khi tư vấn kỹ, mình quyết định làm 16 răng.',
      'Trong suốt quá trình làm, bác sĩ giải thích rất rõ từng bước, điều chỉnh form răng nhiều lần để đảm bảo nụ cười phù hợp khuôn mặt.',
      'Kết quả cuối cùng khá đẹp, màu răng sáng vừa phải và khi cười không bị giả.',
    ],
    'timeline' => [
      ['date' => '15/03/2024', 'label' => 'Khám và lên form'],
      ['date' => '18/03/2024', 'label' => 'Mài răng'],
      ['date' => '21/03/2024', 'label' => 'Đeo răng tạm'],
      ['date' => '24/03/2024', 'label' => 'Thử form'],
      ['date' => '26/03/2024', 'label' => 'Gắn hoàn thiện'],
    ],
    'hero_images' => [
      ['label' => 'Before', 'src' => 'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=1200&q=80'],
      ['label' => 'After', 'src' => 'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=1200&q=80'],
    ],
    'thumbs' => [
      'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1571772996211-2f02c9727629?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=400&q=80',
    ],
    'process_images' => [
      'before' => [
        'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=500&q=80',
      ],
      'during' => [
        'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=500&q=80',
      ],
      'after' => [
        'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1588776814546-ec7e59d8b90b?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1598256989800-fe5f95da9787?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1588774069410-84ae30757c8e?auto=format&fit=crop&w=500&q=80',
      ],
    ],
  ],
  [
    'slug' => 'tri-nam-sau-3-thang',
    'title' => 'Trị nám sau 3 tháng',
    'rating' => '4.7',
    'verified' => 'Đã xác minh',
    'author' => 'Nữ, 38 tuổi',
    'location' => 'Đà Nẵng',
    'date' => '10/02/2024',
    'likes' => 64,
    'comments' => 11,
    'shares' => 5,
    'facility' => [
      'name' => 'Thẩm Mỹ Viện Gangwhoo',
      'rating' => '4.7',
      'reviews' => 714,
      'location' => 'Đà Nẵng',
      'cta' => 'Xem phòng khám',
    ],
    'price' => '15.000.000đ',
    'service' => 'Da liễu',
    'method' => 'Laser trị nám',
    'duration' => '3 tháng',
    'start_date' => '12/11/2023',
    'end_date' => '10/02/2024',
    'condition' => 'Nám mảng, da không đều màu',
    'story' => [
      'Mình điều trị nám trong 3 tháng với mong muốn da đều màu hơn và tự tin hơn khi không trang điểm.',
      'Sau từng buổi, bác sĩ có theo dõi sát, dặn kỹ cách chăm da và chống nắng nên kết quả cải thiện khá ổn.',
      'Hiện da sáng hơn, mảng nám mờ đi rõ rệt và tổng thể gương mặt nhìn tươi hơn nhiều.',
    ],
    'timeline' => [
      ['date' => '12/11/2023', 'label' => 'Soi da và tư vấn'],
      ['date' => '20/11/2023', 'label' => 'Buổi laser 1'],
      ['date' => '18/12/2023', 'label' => 'Buổi laser 2'],
      ['date' => '15/01/2024', 'label' => 'Buổi laser 3'],
      ['date' => '10/02/2024', 'label' => 'Đánh giá kết quả'],
    ],
    'hero_images' => [
      ['label' => 'Before', 'src' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?auto=format&fit=crop&w=1200&q=80'],
      ['label' => 'After', 'src' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=1200&q=80'],
    ],
    'thumbs' => [
      'https://images.unsplash.com/photo-1515377905703-c4788e51af15?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1594824476967-48c8b964273f?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&w=400&q=80',
      'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?auto=format&fit=crop&w=400&q=80',
    ],
    'process_images' => [
      'before' => [
        'https://images.unsplash.com/photo-1515377905703-c4788e51af15?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1594824476967-48c8b964273f?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&w=500&q=80',
      ],
      'during' => [
        'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1515377905703-c4788e51af15?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1594824476967-48c8b964273f?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=500&q=80',
      ],
      'after' => [
        'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1594824476967-48c8b964273f?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1537368910025-700350fe46c7?auto=format&fit=crop&w=500&q=80',
      ],
    ],
  ],
];

function review_detail_normalize(array $item): array
{
  $beforeImage = trim((string) ($item['before_image_url'] ?? $item['before'] ?? ''));
  $afterImage = trim((string) ($item['after_image_url'] ?? $item['after'] ?? ''));

  $heroImages = [];
  foreach ((array) ($item['hero_images'] ?? []) as $image) {
    if (!is_array($image)) {
      continue;
    }
    $src = trim((string) ($image['src'] ?? ''));
    if ($src === '') {
      continue;
    }
    $heroImages[] = [
      'label' => trim((string) ($image['label'] ?? 'Ảnh')),
      'src' => $src,
    ];
  }
  if ($heroImages === []) {
    if ($beforeImage !== '') {
      $heroImages[] = ['label' => 'Before', 'src' => $beforeImage];
    }
    if ($afterImage !== '') {
      $heroImages[] = ['label' => 'After', 'src' => $afterImage];
    }
  }
  if (count($heroImages) === 1) {
    $heroImages[] = [
      'label' => 'After',
      'src' => $heroImages[0]['src'],
    ];
  }

  $thumbs = [];
  foreach ((array) ($item['thumbs'] ?? []) as $thumb) {
    $thumb = trim((string) $thumb);
    if ($thumb !== '') {
      $thumbs[] = $thumb;
    }
  }
  if ($thumbs === []) {
    foreach ($heroImages as $image) {
      $thumbs[] = (string) $image['src'];
    }
  }

  $existingProcess = is_array($item['process_images'] ?? null) ? (array) $item['process_images'] : [];
  $processImages = [
    'before' => array_values(array_filter(array_map('trim', (array) ($existingProcess['before'] ?? $item['process_before'] ?? [])), static fn(string $value): bool => $value !== '')),
    'during' => array_values(array_filter(array_map('trim', (array) ($existingProcess['during'] ?? $item['process_during'] ?? [])), static fn(string $value): bool => $value !== '')),
    'after' => array_values(array_filter(array_map('trim', (array) ($existingProcess['after'] ?? $item['process_after'] ?? [])), static fn(string $value): bool => $value !== '')),
  ];

  $facilityData = is_array($item['facility'] ?? null) ? (array) $item['facility'] : [];
  $facilityName = trim((string) ($facilityData['name'] ?? $item['facility_name'] ?? $item['facility'] ?? 'Cơ sở y tế'));
  $facilityHref = trim((string) ($facilityData['href'] ?? ''));
  $facilitySlug = trim((string) ($item['facility_slug'] ?? ''));
  if ($facilityHref === '') {
    $facilityHref = $facilitySlug !== ''
      ? '/co-so-y-te-chi-tiet.php?slug=' . rawurlencode($facilitySlug)
      : '/co-so-y-te.php';
  }

  return array_merge($item, [
    'verified' => !empty($item['verified']) ? (string) $item['verified'] : (!empty($item['is_verified']) ? 'Đã xác minh' : ''),
    'author' => (string) ($item['author'] ?? $item['author_text'] ?? ''),
    'location' => (string) ($item['location'] ?? $item['location_text'] ?? ''),
    'date' => (string) ($item['date'] ?? $item['review_date_text'] ?? ''),
    'price' => (string) ($item['price'] ?? $item['price_text'] ?? ''),
    'service' => (string) ($item['service'] ?? $item['service_text'] ?? ''),
    'method' => (string) ($item['method'] ?? $item['method_text'] ?? ''),
    'duration' => (string) ($item['duration'] ?? $item['duration_text'] ?? ''),
    'condition' => (string) ($item['condition'] ?? ''),
    'likes' => (int) ($item['likes'] ?? $item['likes_count'] ?? 0),
    'comments' => (int) ($item['comments'] ?? $item['comments_count'] ?? 0),
    'shares' => (int) ($item['shares'] ?? $item['shares_count'] ?? 0),
    'story' => array_values(array_filter(array_map('trim', (array) ($item['story'] ?? [])), static fn(string $value): bool => $value !== '')),
    'timeline' => array_values(array_filter((array) ($item['timeline'] ?? []), static fn($value): bool => is_array($value))),
    'hero_images' => $heroImages,
    'thumbs' => $thumbs,
    'process_images' => $processImages,
    'facility' => [
      'name' => $facilityName,
      'rating' => (string) ($facilityData['rating'] ?? '0.0'),
      'reviews' => (int) ($facilityData['reviews'] ?? 0),
      'location' => (string) ($facilityData['location'] ?? $item['location'] ?? $item['location_text'] ?? ''),
      'cta' => (string) ($facilityData['cta'] ?? 'Xem cơ sở y tế'),
      'href' => $facilityHref,
    ],
  ]);
}

$reviewIndex = [];
foreach ($reviews as $item) {
  $reviewIndex[$item['slug']] = $item;
}

$slug = trim((string) ($_GET['slug'] ?? ''));
$review = $slug !== '' && isset($reviewIndex[$slug]) ? $reviewIndex[$slug] : $reviews[0];

$relatedReviews = array_values(array_filter($reviews, static function (array $item) use ($review): bool {
  return $item['slug'] !== $review['slug'];
}));
$relatedReviews = array_slice($relatedReviews, 0, 3);

$dbReview = medical_directory_review_row_by_slug($slug, true);
if (is_array($dbReview) && $dbReview !== []) {
  $dbFacility = medical_directory_facility_row_by_slug((string) ($dbReview['facility_slug'] ?? ''), true);
  $dbReview['verified'] = !empty($dbReview['is_verified']) ? 'Đã xác minh' : '';
  $dbReview['hero_images'] = [
    ['label' => 'Before', 'src' => (string) ($dbReview['before_image_url'] ?? '')],
    ['label' => 'After', 'src' => (string) ($dbReview['after_image_url'] ?? '')],
  ];
  $dbReview['process_images'] = [
    'before' => (array) ($dbReview['process_before'] ?? []),
    'during' => (array) ($dbReview['process_during'] ?? []),
    'after' => (array) ($dbReview['process_after'] ?? []),
  ];
  $dbReview['facility'] = [
    'name' => (string) ($dbReview['facility_name'] ?? ($dbFacility['name'] ?? 'Cơ sở y tế')),
    'rating' => (string) ($dbFacility['rating'] ?? '0.0'),
    'reviews' => (int) ($dbFacility['reviews_count'] ?? 0),
    'location' => (string) ($dbFacility['city'] ?? ($dbReview['location_text'] ?? '')),
    'cta' => 'Xem phòng khám',
  ];
  $review = $dbReview;

  $dbReviewRows = medical_directory_review_rows(true);
  $relatedReviews = array_values(array_filter($dbReviewRows, static function (array $item) use ($review): bool {
    return (string) ($item['slug'] ?? '') !== (string) ($review['slug'] ?? '');
  }));
  $relatedReviews = array_slice($relatedReviews, 0, 3);
}

$review = review_detail_normalize($review);
$relatedReviews = array_slice(array_map('review_detail_normalize', $relatedReviews), 0, 3);

$seo = front_editor_page_seo('review-chi-tiet', [
  'title' => $review['title'] . ' • MedReview',
  'description' => $review['story'][0] ?? 'Xem review chi tiết trên MedReview.',
  'canonical_path' => '/review-chi-tiet.php?slug=' . rawurlencode((string) $review['slug']),
]);
$title = (string) ($seo['title'] ?? ($review['title'] . ' • MedReview'));
$description = (string) ($seo['description'] ?? '');
$canonicalPath = (string) ($seo['canonical_path'] ?? '/review-chi-tiet.php');
$seoKeywords = (string) ($seo['keywords'] ?? '');
?>
<!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($seoKeywords !== ''): ?><meta name="keywords" content="<?php echo htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalPath, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        font-family:"Plus Jakarta Sans",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
        color:var(--text);
        background:
          radial-gradient(900px 320px at 0% 0%, rgba(59,130,246,.045), transparent 60%),
          linear-gradient(180deg,#fafcff 0%, #f5f8fd 100%);
      }
      a{color:inherit;text-decoration:none}
      .container{width:min(100% - 32px, var(--max));margin:0 auto}
      .review-detail{padding:22px 0 64px}
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
      .crumb-sep{
        width:14px;height:14px;display:inline-flex;align-items:center;justify-content:center;color:#cbd5e1;
      }
      .crumb-sep svg{width:14px;height:14px;stroke-width:1.9}
      .page-head{
        display:flex;
        justify-content:space-between;
        gap:20px;
        margin-top:16px;
        align-items:flex-start;
      }
      .page-head h1{
        margin:0;
        font-size:2.65em;
        line-height:1.06;
        letter-spacing:-.045em;
      }
      .rating-row{
        display:flex;
        align-items:center;
        gap:12px;
        margin-top:10px;
        flex-wrap:wrap;
      }
      .stars{display:flex;gap:2px;color:var(--warning)}
      .stars svg{width:18px;height:18px;fill:currentColor;stroke:currentColor}
      .rating-value{font-size:1.02em;font-weight:700;color:#334155}
      .verified{
        display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;
        background:rgba(22,163,74,.08);color:var(--success);font-size:12px;font-weight:700;
      }
      .verified svg{width:14px;height:14px;stroke-width:2}
      .meta-row{
        display:flex;
        gap:14px;
        flex-wrap:wrap;
        margin-top:10px;
        color:#64748b;
        font-size:13px;
      }
      .meta-item{
        display:inline-flex;
        align-items:center;
        gap:7px;
      }
      .meta-item svg{width:16px;height:16px;stroke-width:2;color:#60a5fa}
      .page-actions{
        display:flex;
        align-items:center;
        gap:18px;
        color:#64748b;
        font-size:13px;
        padding-top:10px;
      }
      .page-actions span{
        display:inline-flex;
        align-items:center;
        gap:8px;
      }
      .page-actions svg{width:17px;height:17px;stroke-width:1.9}
      .content-grid{
        margin-top:18px;
        display:grid;
        grid-template-columns:minmax(0,1fr) 308px;
        gap:16px;
        align-items:start;
      }
      .panel{
        border:1px solid var(--border);
        border-radius:18px;
        background:#fff;
        box-shadow:0 8px 18px rgba(15,23,42,.035);
      }
      .main-panel{padding:0}
      .gallery-grid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:8px;
        padding:10px;
      }
      .gallery-card{
        position:relative;
        overflow:hidden;
        border-radius:14px;
        height:292px;
        background:#e2e8f0;
      }
      .gallery-card img{width:100%;height:100%;object-fit:cover}
      .gallery-label{
        position:absolute;
        left:10px;
        bottom:10px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:5px 9px;
        border-radius:10px;
        background:rgba(15,23,42,.82);
        color:#fff;
        font-size:11px;
        font-weight:700;
      }
      .gallery-nav{
        position:absolute;
        top:50%;
        transform:translateY(-50%);
        width:30px;
        height:30px;
        border-radius:10px;
        border:0;
        background:rgba(255,255,255,.94);
        color:#334155;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        box-shadow:0 10px 20px rgba(15,23,42,.12);
      }
      .gallery-nav svg{width:16px;height:16px;stroke-width:2.3}
      .gallery-nav.prev{left:10px}
      .gallery-nav.next{right:10px}
      .thumb-row{
        display:grid;
        grid-template-columns:repeat(6,minmax(0,1fr));
        gap:8px;
        padding:0 10px 12px;
      }
      .thumb{
        position:relative;
        overflow:hidden;
        height:72px;
        border-radius:12px;
        background:#e2e8f0;
        border:1px solid var(--border);
      }
      .thumb img{width:100%;height:100%;object-fit:cover}
      .thumb.more::after{
        content:"+6";
        position:absolute;
        inset:0;
        display:flex;
        align-items:center;
        justify-content:center;
        background:rgba(15,23,42,.45);
        color:#fff;
        font-size:18px;
        font-weight:800;
      }
      .section{
        padding:0 18px 18px;
      }
      .section h2{
        margin:14px 0 10px;
        font-size:1.22em;
      }
      .story{
        display:grid;
        gap:12px;
        color:#475569;
        font-size:14px;
        line-height:1.75;
      }
      .timeline{
        position:relative;
        display:grid;
        grid-template-columns:repeat(5,minmax(0,1fr));
        gap:10px;
        margin-top:18px;
      }
      .timeline::before{
        content:"";
        position:absolute;
        left:0;
        right:0;
        top:14px;
        height:2px;
        background:#dbe7fb;
      }
      .timeline-item{
        position:relative;
        display:grid;
        justify-items:center;
        text-align:center;
        gap:10px;
        z-index:1;
      }
      .timeline-dot{
        width:10px;
        height:10px;
        border-radius:999px;
        background:#2563eb;
        box-shadow:0 0 0 5px #f8fbff;
      }
      .timeline-date{
        color:#475569;
        font-size:12px;
        font-weight:700;
      }
      .timeline-label{
        color:#64748b;
        font-size:12px;
        line-height:1.5;
      }
      .process-block{margin-top:18px}
      .process-block h3{
        margin:0 0 10px;
        font-size:1em;
      }
      .process-grid{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:10px;
      }
      .process-card{
        overflow:hidden;
        border-radius:12px;
        height:94px;
        background:#e2e8f0;
        border:1px solid var(--border);
      }
      .process-card img{width:100%;height:100%;object-fit:cover}
      .comment-box{
        margin-top:18px;
        padding:18px;
        border-top:1px solid var(--border);
      }
      .comment-box h2{
        margin:0 0 12px;
        font-size:1.22em;
      }
      .comment-form{
        display:grid;
        grid-template-columns:minmax(0,1fr) 110px;
        gap:10px;
      }
      .comment-form textarea{
        min-height:52px;
        resize:vertical;
        border:1px solid var(--border);
        border-radius:12px;
        padding:14px 16px;
        font:inherit;
        color:var(--text);
        background:#fbfdff;
        outline:none;
      }
      .comment-form button{
        height:52px;
        border:0;
        border-radius:12px;
        background:linear-gradient(180deg,#3b82f6,#2563eb);
        color:#fff;
        font:inherit;
        font-weight:700;
      }
      .sidebar{
        display:grid;
        gap:14px;
        position:sticky;
        top:98px;
      }
      .side-card{
        padding:16px;
      }
      .clinic-card-head{
        display:flex;
        gap:12px;
        align-items:flex-start;
      }
      .clinic-logo{
        width:52px;
        height:52px;
        border-radius:16px;
        background:linear-gradient(180deg,#eff6ff,#dbeafe);
        color:#2563eb;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        font-size:10px;
        font-weight:800;
        text-align:center;
        line-height:1.15;
        border:1px solid rgba(37,99,235,.12);
        padding:6px;
      }
      .clinic-name{
        margin:0;
        font-size:1.05em;
      }
      .clinic-meta{
        margin-top:6px;
        display:flex;
        align-items:center;
        gap:8px;
        flex-wrap:wrap;
        color:#64748b;
        font-size:12px;
      }
      .clinic-meta .stars svg{width:14px;height:14px}
      .clinic-cta,
      .more-btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:100%;
        height:42px;
        margin-top:14px;
        border-radius:12px;
        background:#f8fbff;
        border:1px solid var(--border);
        color:#2563eb;
        font-size:13px;
        font-weight:700;
      }
      .side-card h3{
        margin:0 0 14px;
        font-size:1.02em;
      }
      .price-value{
        font-size:2em;
        line-height:1;
        font-weight:800;
        letter-spacing:-.03em;
      }
      .price-caption{
        margin-top:8px;
        color:#64748b;
        font-size:14px;
      }
      .info-list{
        display:grid;
        gap:12px;
      }
      .info-row{
        display:grid;
        grid-template-columns:110px minmax(0,1fr);
        gap:10px;
        color:#475569;
        font-size:13px;
      }
      .info-row strong{color:#94a3b8}
      .check-list{
        display:grid;
        gap:10px;
      }
      .check-item{
        display:flex;
        align-items:flex-start;
        gap:10px;
        color:#475569;
        font-size:13px;
      }
      .check-item svg{width:18px;height:18px;stroke-width:2;color:var(--success);flex:0 0 auto}
      .related-list{
        display:grid;
        gap:12px;
      }
      .related-item{
        display:grid;
        grid-template-columns:72px minmax(0,1fr);
        gap:10px;
        align-items:start;
      }
      .related-thumb{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:4px;
      }
      .related-thumb span{
        overflow:hidden;
        border-radius:10px;
        height:58px;
        background:#e2e8f0;
      }
      .related-thumb img{width:100%;height:100%;object-fit:cover}
      .related-copy h4{
        margin:0;
        font-size:13px;
        line-height:1.45;
      }
      .related-copy p{
        margin:4px 0 0;
        color:#94a3b8;
        font-size:12px;
      }
      .related-score{
        display:flex;
        align-items:center;
        gap:6px;
        margin-top:6px;
        color:#475569;
        font-size:12px;
        font-weight:700;
      }
      .related-score .stars svg{width:13px;height:13px}
      @media (max-width:1240px){
        .content-grid{grid-template-columns:1fr}
        .sidebar{position:static}
      }
      @media (max-width:980px){
        .page-head{display:grid}
        .content-grid{grid-template-columns:1fr}
        .gallery-grid{grid-template-columns:1fr}
        .gallery-card{height:260px}
        .thumb-row{grid-template-columns:repeat(3,minmax(0,1fr))}
        .timeline{grid-template-columns:repeat(2,minmax(0,1fr))}
        .timeline::before{display:none}
        .process-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
      }
      @media (max-width:720px){
        .container{width:min(100% - 24px, var(--max))}
        .review-detail{padding:18px 0 48px}
        .page-head h1{font-size:1.9em}
        .page-actions{gap:14px;flex-wrap:wrap}
        .thumb-row{grid-template-columns:repeat(2,minmax(0,1fr))}
        .process-grid{grid-template-columns:1fr 1fr}
        .comment-form{grid-template-columns:1fr}
        .info-row{grid-template-columns:1fr}
      }
    </style>
  </head>
  <body>
    <?php include __DIR__ . '/Tem/header.php'; ?>
    <main class="review-detail site-typo">
      <section class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="/">Trang chủ</a>
          <span class="crumb-sep" aria-hidden="true"><i data-lucide="chevron-right"></i></span>
          <a href="/review.php">Review</a>
          <span class="crumb-sep" aria-hidden="true"><i data-lucide="chevron-right"></i></span>
          <span class="active"><?php echo htmlspecialchars($review['title'], ENT_QUOTES, 'UTF-8'); ?></span>
        </nav>

        <div class="page-head">
          <div>
            <h1><?php echo htmlspecialchars($review['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="rating-row">
              <span class="stars"><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i></span>
              <span class="rating-value"><?php echo htmlspecialchars($review['rating'], ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="verified"><i data-lucide="badge-check"></i><?php echo htmlspecialchars($review['verified'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="meta-row">
              <span class="meta-item"><i data-lucide="user-round"></i><?php echo htmlspecialchars($review['author'], ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="meta-item"><i data-lucide="map-pin"></i><?php echo htmlspecialchars($review['location'], ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="meta-item"><i data-lucide="calendar-days"></i><?php echo htmlspecialchars($review['date'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
          <div class="page-actions">
            <span><i data-lucide="heart"></i><?php echo (int) $review['likes']; ?></span>
            <span><i data-lucide="message-circle"></i><?php echo (int) $review['comments']; ?> bình luận</span>
            <span><i data-lucide="share-2"></i>Chia sẻ</span>
          </div>
        </div>

        <div class="content-grid">
          <div class="panel main-panel">
            <div class="gallery-grid">
              <?php foreach ($review['hero_images'] as $index => $image): ?>
                <div class="gallery-card">
                  <img src="<?php echo htmlspecialchars($image['src'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($image['label'], ENT_QUOTES, 'UTF-8'); ?>">
                  <span class="gallery-label"><?php echo htmlspecialchars($image['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php if ($index === 0): ?><button class="gallery-nav prev" type="button" aria-label="Previous"><i data-lucide="chevron-left"></i></button><?php endif; ?>
                  <?php if ($index === 1): ?><button class="gallery-nav next" type="button" aria-label="Next"><i data-lucide="chevron-right"></i></button><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="thumb-row">
              <?php foreach ($review['thumbs'] as $thumbIndex => $thumb): ?>
                <div class="thumb<?php echo $thumbIndex === 5 ? ' more' : ''; ?>">
                  <img src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="Thumbnail <?php echo $thumbIndex + 1; ?>">
                </div>
              <?php endforeach; ?>
            </div>

            <div class="section">
              <h2>Câu chuyện của mình</h2>
              <div class="story">
                <?php foreach ($review['story'] as $paragraph): ?>
                  <p><?php echo htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="section">
              <h2>Quá trình điều trị</h2>
              <div class="timeline">
                <?php foreach ($review['timeline'] as $point): ?>
                  <div class="timeline-item">
                    <span class="timeline-dot"></span>
                    <div class="timeline-date"><?php echo htmlspecialchars($point['date'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="timeline-label"><?php echo htmlspecialchars($point['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="section">
              <h2>Hình ảnh quá trình</h2>

              <div class="process-block">
                <h3>Trước khi điều trị</h3>
                <div class="process-grid">
                  <?php foreach ($review['process_images']['before'] as $image): ?>
                    <div class="process-card"><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="Before process"></div>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="process-block">
                <h3>Trong quá trình điều trị</h3>
                <div class="process-grid">
                  <?php foreach ($review['process_images']['during'] as $image): ?>
                    <div class="process-card"><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="During process"></div>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="process-block">
                <h3>Sau khi điều trị</h3>
                <div class="process-grid">
                  <?php foreach ($review['process_images']['after'] as $image): ?>
                    <div class="process-card"><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="After process"></div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <div class="comment-box">
              <h2>Bình luận (<?php echo (int) $review['comments']; ?>)</h2>
              <form class="comment-form">
                <textarea placeholder="Viết bình luận của bạn..."></textarea>
                <button type="button">Gửi</button>
              </form>
            </div>
          </div>

          <aside class="sidebar">
            <section class="panel side-card">
              <div class="clinic-card-head">
                <span class="clinic-logo">TOP<br>CLINIC</span>
                <div>
                  <h3 class="clinic-name"><?php echo htmlspecialchars($review['facility']['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                  <div class="clinic-meta">
                    <span class="stars"><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i></span>
                    <span><?php echo htmlspecialchars($review['facility']['rating'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span>(<?php echo (int) $review['facility']['reviews']; ?> review)</span>
                  </div>
                  <div class="clinic-meta">
                    <span class="meta-item"><i data-lucide="map-pin"></i><?php echo htmlspecialchars($review['facility']['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                </div>
              </div>
              <a class="clinic-cta" href="<?php echo htmlspecialchars($review['facility']['href'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($review['facility']['cta'], ENT_QUOTES, 'UTF-8'); ?></a>
            </section>

            <section class="panel side-card">
              <h3>Chi phí</h3>
              <div class="price-value"><?php echo htmlspecialchars($review['price'], ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="price-caption"><?php echo htmlspecialchars($review['method'], ENT_QUOTES, 'UTF-8'); ?></div>
            </section>

            <section class="panel side-card">
              <h3>Thông tin review</h3>
              <div class="info-list">
                <div class="info-row"><strong>Dịch vụ</strong><span><?php echo htmlspecialchars($review['service'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="info-row"><strong>Phương pháp</strong><span><?php echo htmlspecialchars($review['method'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="info-row"><strong>Thời gian điều trị</strong><span><?php echo htmlspecialchars($review['duration'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="info-row"><strong>Ngày bắt đầu</strong><span><?php echo htmlspecialchars($review['start_date'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="info-row"><strong>Ngày kết thúc</strong><span><?php echo htmlspecialchars($review['end_date'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="info-row"><strong>Tình trạng</strong><span><?php echo htmlspecialchars($review['condition'], ENT_QUOTES, 'UTF-8'); ?></span></div>
              </div>
            </section>

            <section class="panel side-card">
              <h3>Review xác minh</h3>
              <div class="check-list">
                <div class="check-item"><i data-lucide="check-circle-2"></i><span>Đã xác minh danh tính</span></div>
                <div class="check-item"><i data-lucide="check-circle-2"></i><span>Có hóa đơn, chứng từ</span></div>
                <div class="check-item"><i data-lucide="check-circle-2"></i><span>Có hình ảnh trước/sau</span></div>
                <div class="check-item"><i data-lucide="check-circle-2"></i><span>Có timeline điều trị</span></div>
              </div>
            </section>

            <section class="panel side-card">
              <h3>Review liên quan</h3>
              <div class="related-list">
                <?php foreach ($relatedReviews as $item): ?>
                  <a class="related-item" href="/review-chi-tiet.php?slug=<?php echo rawurlencode((string) $item['slug']); ?>">
                    <span class="related-thumb">
                      <span><img src="<?php echo htmlspecialchars((string) ($item['hero_images'][0]['src'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="Before"></span>
                      <span><img src="<?php echo htmlspecialchars((string) ($item['hero_images'][1]['src'] ?? ($item['hero_images'][0]['src'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" alt="After"></span>
                    </span>
                    <span class="related-copy">
                      <h4><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                      <p><?php echo htmlspecialchars($item['author'], ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars($item['location'], ENT_QUOTES, 'UTF-8'); ?></p>
                      <span class="related-score">
                        <span class="stars"><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i><i data-lucide="star"></i></span>
                        <span><?php echo htmlspecialchars($item['rating'], ENT_QUOTES, 'UTF-8'); ?></span>
                      </span>
                    </span>
                  </a>
                <?php endforeach; ?>
              </div>
              <a class="more-btn" href="/review.php">Xem thêm review liên quan</a>
            </section>
          </aside>
        </div>
      </section>
    </main>
    <?php include __DIR__ . '/Tem/footer.php'; ?>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
      if (window.lucide) {
        window.lucide.createIcons();
      }
    </script>
  </body>
</html>
