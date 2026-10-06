<?php
declare(strict_types=1);

function home_next_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function home_next_region(string $city): string
{
    $city = mb_strtolower(trim($city), 'UTF-8');
    if (str_contains($city, 'hồ chí minh') || str_contains($city, 'ho chi minh') || str_contains($city, 'sài gòn')) return 'hcm';
    if (str_contains($city, 'hà nội') || str_contains($city, 'ha noi') || str_contains($city, 'hanoi')) return 'hanoi';
    if (str_contains($city, 'đà nẵng') || str_contains($city, 'da nang') || str_contains($city, 'danang')) return 'danang';
    return 'other';
}

function home_next_summary(mixed $value, string $fallback, int $limit = 110): string
{
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    if ($text === '') return $fallback;
    return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)) . '…' : $text;
}

function medical_home_next_copy(string $locale): array
{
    return $locale === 'en' ? [
        'pageTitle'=>'MedReview · A new homepage to explore', 'preview'=>'New homepage preview', 'old'=>'View current homepage',
        'eyebrow'=>'A LITTLE MORE CLARITY. A LITTLE MORE CONFIDENCE.', 'title'=>'Your health.', 'accent'=>'A more informed choice.',
        'heroCopy'=>'Find care, understand your options and learn from shared experiences. A clearer starting point for your health in Vietnam.',
        'photoAlt'=>'A family in a welcoming healthcare space — illustrative image', 'illustration'=>'Illustrative image', 'noteTitle'=>'Understand before you choose', 'noteMeta'=>'Profiles · Services · Experiences',
        'photoLabel'=>'Care starts with understanding.', 'search'=>'What are you looking for?', 'placeholder'=>'Facility, doctor, service or city…', 'searchButton'=>'Search', 'try'=>'Try:',
        'terms'=>['Dentist in Hanoi','Dermatology','Eye care'], 'facilitiesStat'=>'published facilities', 'doctorsStat'=>'doctor profiles', 'sources'=>'More information for your choice',
        'specialties'=>'What matters to you?', 'seeMore'=>'Explore more', 'facilityKicker'=>'PLACES TO GET TO KNOW', 'facilityTitle'=>'Find care that feels right.',
        'facilityCopy'=>'Explore profiles, services and shared experiences before reaching out.', 'allFacilities'=>'All facilities', 'all'=>'All', 'profile'=>'View profile', 'reviews'=>'reviews',
        'noRating'=>'Not rated yet', 'save'=>'Save on this device', 'fallback'=>'See services and details in the full profile.', 'facilityEmpty'=>'No featured facilities in this area yet. Explore the full directory.',
        'doctorKicker'=>'PEOPLE BEHIND YOUR CARE', 'doctorTitle'=>'Get to know the right expertise.', 'doctorCopy'=>'Learn about specialties and practice locations before making an appointment.', 'allDoctors'=>'All doctors', 'doctorFallback'=>'Get to know their expertise and practice locations.',
        'toplistKicker'=>'A CURATED STARTING POINT', 'toplistTitle'=>'Less searching. More perspective.', 'toplistCopy'=>'Focused lists to help you explore care by specialty and location.', 'allToplists'=>'All Toplists', 'listCta'=>'Explore list', 'profiles'=>'profiles', 'listFallback'=>'Explore the profiles in this list.',
        'stepsTitle'=>'From a question to a clearer choice.', 'steps'=>[['magnifying-glass','Find what you need','Start with a specialty, service or location.'],['identification-card','Understand your options','Read profiles and use reviews as a reference.'],['chat-circle-dots','Connect directly','Confirm services, availability and costs with the provider.']],
        'communityKicker'=>'GOOD INFORMATION STARTS WITH SHARING', 'communityTitle'=>'Your experience can help someone else.', 'communityCopy'=>'An honest review. A clearer profile. One more perspective for the next person looking for care.', 'communityCta'=>'Explore community experiences',
        'communityQuestion'=>'What helped you feel more at ease?', 'communityChips'=>['Attentive care','Clear information','Transparent costs'], 'communityFoot'=>'A small story. A meaningful difference.', 'disclaimer'=>'MedReview is a source of information, not a substitute for medical advice.',
        'specialtyItems'=>[['Dental','A healthier smile','dental','tooth'],['Beauty & Spa','Feel more like you','spa','sparkle'],['Dermatology','Care for your skin','dermatology','leaf'],['General care','Know your health','general','stethoscope'],['Women’s health','Care through every stage','gynecology','heart'],['Eye care','See life clearly','eye','eye']],
    ] : [
        'pageTitle'=>'MedReview · Trang chủ mới', 'preview'=>'Bản thiết kế trang chủ mới', 'old'=>'Xem trang chủ hiện tại',
        'eyebrow'=>'THÊM THÔNG TIN. THÊM MỘT CHÚT AN TÂM.', 'title'=>'Sức khỏe của bạn.', 'accent'=>'Lựa chọn có cơ sở.',
        'heroCopy'=>'Tìm nơi chăm sóc, hiểu rõ lựa chọn và tham khảo trải nghiệm thực tế. Cùng bạn bắt đầu một hành trình sức khỏe an tâm hơn.',
        'photoAlt'=>'Gia đình trong không gian y tế thân thiện — ảnh minh họa', 'illustration'=>'Ảnh minh họa', 'noteTitle'=>'Hiểu rõ trước khi lựa chọn', 'noteMeta'=>'Hồ sơ · Dịch vụ · Trải nghiệm',
        'photoLabel'=>'An tâm bắt đầu từ sự thấu hiểu.', 'search'=>'Bạn đang tìm điều gì?', 'placeholder'=>'Cơ sở, bác sĩ, dịch vụ, thành phố…', 'searchButton'=>'Tìm kiếm', 'try'=>'Thử tìm:',
        'terms'=>['Nha khoa Hà Nội','Da liễu','Khám mắt'], 'facilitiesStat'=>'cơ sở đã xuất bản', 'doctorsStat'=>'hồ sơ bác sĩ', 'sources'=>'Có thêm góc nhìn trước khi chọn',
        'specialties'=>'Bạn quan tâm điều gì?', 'seeMore'=>'Xem thêm', 'facilityKicker'=>'NHỮNG ĐỊA CHỈ ĐỂ TÌM HIỂU', 'facilityTitle'=>'Tìm nơi chăm sóc phù hợp.',
        'facilityCopy'=>'Khám phá hồ sơ, dịch vụ và những trải nghiệm được chia sẻ trước khi liên hệ.', 'allFacilities'=>'Tất cả cơ sở', 'all'=>'Tất cả', 'profile'=>'Xem hồ sơ', 'reviews'=>'đánh giá',
        'noRating'=>'Chưa có đánh giá', 'save'=>'Lưu trên thiết bị này', 'fallback'=>'Xem dịch vụ và thông tin trong hồ sơ chi tiết.', 'facilityEmpty'=>'Chưa có cơ sở nổi bật tại khu vực này. Bạn có thể khám phá toàn bộ danh bạ.',
        'doctorKicker'=>'NHỮNG NGƯỜI ĐỒNG HÀNH', 'doctorTitle'=>'Hiểu chuyên môn. Chọn phù hợp.', 'doctorCopy'=>'Tìm hiểu chuyên khoa và nơi công tác trước khi đặt lịch thăm khám.', 'allDoctors'=>'Tất cả bác sĩ', 'doctorFallback'=>'Tìm hiểu chuyên môn và nơi công tác trong hồ sơ.',
        'toplistKicker'=>'GỢI Ý CÓ CHỌN LỌC', 'toplistTitle'=>'Bớt tìm kiếm. Thêm góc nhìn.', 'toplistCopy'=>'Những danh sách theo chuyên khoa và khu vực để bạn bắt đầu tìm hiểu.', 'allToplists'=>'Tất cả Toplist', 'listCta'=>'Khám phá danh sách', 'profiles'=>'hồ sơ', 'listFallback'=>'Tìm hiểu những hồ sơ có trong danh sách.',
        'stepsTitle'=>'Từ một câu hỏi, đến lựa chọn rõ ràng hơn.', 'steps'=>[['magnifying-glass','Tìm đúng nhu cầu','Bắt đầu từ chuyên khoa, dịch vụ hoặc khu vực.'],['identification-card','Hiểu rõ lựa chọn','Đọc hồ sơ, tham khảo trải nghiệm đã chia sẻ.'],['chat-circle-dots','Chủ động kết nối','Xác nhận dịch vụ, lịch khám và chi phí trực tiếp.']],
        'communityKicker'=>'THÔNG TIN TỐT BẮT ĐẦU TỪ SẺ CHIA', 'communityTitle'=>'Trải nghiệm của bạn. An tâm cho nhiều người.', 'communityCopy'=>'Một chia sẻ chân thật. Một hồ sơ rõ ràng. Thêm một góc nhìn cho người đang tìm nơi chăm sóc sức khỏe.', 'communityCta'=>'Khám phá trải nghiệm cộng đồng',
        'communityQuestion'=>'Điều gì giúp bạn cảm thấy an tâm hơn?', 'communityChips'=>['Sự tận tâm','Thông tin rõ ràng','Chi phí minh bạch'], 'communityFoot'=>'Một chia sẻ nhỏ, một giá trị lớn.', 'disclaimer'=>'MedReview là nguồn tham khảo, không thay thế tư vấn y khoa.',
        'specialtyItems'=>[['Nha khoa','Chăm chút nụ cười','nha khoa','tooth'],['Thẩm mỹ & Spa','Tự tin là chính mình','spa thẩm mỹ','sparkle'],['Da liễu','Yêu làn da khỏe','da liễu','leaf'],['Khám tổng quát','Hiểu cơ thể hơn','đa khoa','stethoscope'],['Sản phụ khoa','Đồng hành yêu thương','sản phụ khoa','heart'],['Chuyên khoa mắt','Nhìn cuộc sống rõ hơn','mắt','eye']],
    ];
}
