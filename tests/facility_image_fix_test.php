<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/medical_media_library.php';
require_once dirname(__DIR__) . '/medical_media_worker.php';

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
};
$reject = static function (callable $operation, string $message, ?string $reason = null) use ($assert): void {
    try { $operation(); } catch (InvalidArgumentException|MedicalFacilityImageFixConflict $e) {
        $assert($reason === null || ($e instanceof MedicalFacilityImageFixConflict && $e->reason === $reason), $message . ': reason');
        return;
    }
    $assert(false, $message . ': should reject');
};
$token = str_repeat('a', 64);
$row = ['id' => 71, 'name' => 'Nha khoa kiểm thử', 'slug' => 'test-clinic', 'status' => 'published', 'language_code' => 'vi',
    'address_text' => 'Địa chỉ chi nhánh A', 'website_url' => 'https://clinic.example', 'google_maps_url' => 'https://maps.google.com/?cid=71',
    'image_url' => 'https://clinic.example/broken.jpg', 'ai_image_url' => '/uploads/library/ai.jpg',
    'content' => '<p>Existing article must stay intact.</p>',
    'gallery_json' => json_encode(['https://clinic.example/broken.jpg', ['url' => 'https://clinic.example/good.jpg', 'caption' => 'Caption cũ', 'source' => 'Website', 'custom' => 123], 'https://clinic.example/blocked.jpg']),
    'ai_writer_claim_json' => json_encode(['task' => 'facility_image_fix', 'claim_token' => $token, 'expires_at' => time() + 180, 'provider' => 'gemini']),
    'image_fix_json' => null];
$inventory = medical_facility_image_fix_inventory($row);
$assert(count($inventory) === 4, 'deduplicate cover/gallery, include separate AI image');
$assert(count($inventory[0]['fields']) === 2, 'inventory preserves all source slots');
$assert($inventory[1]['inspection_url'] === 'https://medreview.vn/uploads/library/ai.jpg', 'AI can inspect local image via public absolute URL');
$exactLocalUrl = '/uploads/library/clinic%20branch.jpg?v=2&crop=wide';
$exactRemoteUrl = 'https://CDN.clinic.example/Photo%20Team.JPG?width=1600&fit=cover&v=7';
$exactUrlRow = array_replace($row, ['image_url' => $exactLocalUrl, 'ai_image_url' => '',
    'gallery_json' => json_encode([['url' => $exactRemoteUrl, 'caption' => 'Đội ngũ đã xác minh', 'custom' => 'preserve']])]);
$exactUrlInventory = medical_facility_image_fix_inventory($exactUrlRow);
$assert(array_column($exactUrlInventory, 'url') === [$exactLocalUrl, $exactRemoteUrl], 'inventory preserves source local paths, encoded paths, host case and query order');
$assert($exactUrlInventory[0]['inspection_url'] === 'https://medreview.vn' . $exactLocalUrl, 'inspection URL only expands local source for viewing');
$exactUrlItem = medical_facility_image_fix_output_template($exactUrlRow);
$assert(array_column($exactUrlItem['inspected_images'], 'url') === [$exactLocalUrl, $exactRemoteUrl], 'output template copies source URLs, never expanded inspection URLs');
$exactUrlPatch = medical_facility_image_fix_patch($exactUrlRow, $exactUrlItem);
$assert($exactUrlPatch['image_url'] === $exactLocalUrl && $exactUrlPatch['inspected_images'][1]['url'] === $exactRemoteUrl, 'source URLs roundtrip without path, host or query normalization');
$assert(json_decode($exactUrlPatch['gallery_json'], true) === json_decode($exactUrlRow['gallery_json'], true), 'exact source gallery URL and metadata remain unchanged');
foreach ([
    ['index' => 0, 'url' => $exactUrlInventory[0]['inspection_url'], 'case' => 'local inspection URL substitution'],
    ['index' => 0, 'url' => '/uploads/library/clinic%20branch.jpg?crop=wide&v=2', 'case' => 'local query reorder'],
    ['index' => 1, 'url' => strtolower($exactRemoteUrl), 'case' => 'host/path case normalization'],
    ['index' => 1, 'url' => str_replace('%20', ' ', $exactRemoteUrl), 'case' => 'encoded path normalization'],
    ['index' => 1, 'url' => preg_replace('/\?.*$/', '', $exactRemoteUrl), 'case' => 'query stripping'],
] as $changedUrl) {
    $invalidExactUrl = $exactUrlItem;
    $invalidExactUrl['inspected_images'][$changedUrl['index']]['url'] = $changedUrl['url'];
    $reject(fn() => medical_facility_image_fix_patch($exactUrlRow, $invalidExactUrl), 'inspected source identity rejects ' . $changedUrl['case']);
}
foreach ([$exactLocalUrl, $exactRemoteUrl] as $index => $sourceUrl) {
    foreach (['[' . $sourceUrl . '](' . $sourceUrl . ')', '`' . $sourceUrl . '`', '<a href="' . $sourceUrl . '">' . $sourceUrl . '</a>'] as $wrappedUrl) {
        $invalidExactUrl = $exactUrlItem;
        $invalidExactUrl['inspected_images'][$index]['url'] = $wrappedUrl;
        $reject(fn() => medical_facility_image_fix_patch($exactUrlRow, $invalidExactUrl), 'formatted inspected source remains rejected: ' . $wrappedUrl);
    }
}
$revision = medical_facility_image_fix_revision($row);
$assert(medical_facility_image_fix_revision($row + ['updated_at' => 'changed-by-heartbeat']) === $revision, 'lease heartbeat does not invalidate images');
$assert(medical_facility_image_fix_revision(array_replace($row, ['address_text' => 'branch B'])) !== $revision, 'identity change invalidates old image investigation');
$item = medical_facility_image_fix_output_template($row);
$item['writer_claim_token'] = $token;
foreach ($item['inspected_images'] as &$review) {
    if ($review['url'] === $row['image_url']) {
        $review['decision'] = 'remove'; $review['reason'] = 'HTTP 404'; $review['http_status'] = 404; $review['evidence_url'] = $row['image_url'];
    } elseif (str_contains($review['url'], 'good.jpg')) $review['decision'] = 'keep';
    else { $review['decision'] = 'uncertain'; $review['reason'] = 'Cannot inspect'; }
}
unset($review);
$item['added_images'] = [['url' => 'https://lh3.googleusercontent.com/p/' . str_repeat('x', 300) . '=s1600', 'source' => 'Google Maps',
    'source_url' => 'https://maps.google.com/?cid=71', 'caption' => 'Mặt tiền đúng chi nhánh']];
$patch = medical_facility_image_fix_patch($row, $item);
$gallery = json_decode($patch['gallery_json'], true);
$assert(count($gallery) === 3 && count($patch['removed_images']) === 1 && count($patch['added_images']) === 1, 'remove broken, preserve uncertain, add verified-source candidate');
$assert($gallery[0]['custom'] === 123 && $gallery[0]['caption'] === 'Caption cũ', 'existing rich metadata preserved');
$assert($patch['image_url'] === 'https://clinic.example/good.jpg', 'removed cover replaced from surviving gallery');
$assert($patch['ai_image_url'] === $row['ai_image_url'], 'uncertain AI image remains');
$longCover = $item; $longCover['image_url'] = $item['added_images'][0]['url'];
$assert(strlen(medical_facility_image_fix_patch($row, $longCover)['image_url']) > 255, 'long Google URL supported without truncation');
$minimalAddition = $item;
$minimalAddition['added_images'] = [array_intersect_key($item['added_images'][0], array_flip(['url', 'source', 'source_url']))];
$minimalPatch = medical_facility_image_fix_patch($row, $minimalAddition);
$assert($minimalPatch['added_images'][0]['angle'] === '' && $minimalPatch['added_images'][0]['caption'] === '', 'new photo needs URL/source/source_url, optional caption and angle default empty');
$invalid = $item; array_pop($invalid['inspected_images']);
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'omitted source image cannot be deleted');
$invalid = $item; $invalid['inspected_images'][1] = $invalid['inspected_images'][0];
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'duplicate decisions rejected');
$invalid = $item; $invalid['inspected_images'][0]['url'] = 'https://other.example/unknown.jpg';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'unknown removal rejected');
$invalid = $item; $invalid['inspected_images'][0]['evidence_url'] = '';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'removal without evidence rejected');
foreach ([401, 403, 408, 429, 500, 503] as $status) {
    $invalid = $item; $invalid['inspected_images'][0]['http_status'] = $status;
    $reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'temporary access failure not proof of bad image: ' . $status);
}
foreach (['https://maps.google.com/?cid=71', 'https://clinic.example/page.html', 'http://127.0.0.1/a.jpg', 'http://[::1]/a.jpg', 'http://localhost/a.jpg', 'http://service.internal/a.jpg', 'file:///etc/passwd', 'data:image/png;base64,abc', 'https://user:pass@clinic.example/a.jpg', 'https://clinic.example/a.jpg?key=secret', 'https://clinic.example:3306/a.jpg'] as $url) {
    $invalid = $item; $invalid['added_images'][0]['url'] = $url;
    $reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'invalid new image URL rejected: ' . $url);
}
$invalid = $item; $invalid['added_images'][0]['source_url'] = '';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'new image requires source link');
$invalid = $item; $invalid['added_images'][0]['url'] = 'https://clinic.example/good.jpg';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'existing source cannot be added again');
$invalid = $item; $invalid['ai_image_url'] = '';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'cannot clear AI image without remove decision');
foreach (['null' => null, 'empty' => ''] as $sourceCase => $sourceAiUrl) {
    $emptyAiRow = array_replace($row, ['ai_image_url' => $sourceAiUrl]);
    $emptyAiItem = medical_facility_image_fix_output_template($emptyAiRow);
    $assert($emptyAiItem['ai_image_url'] === '', 'source AI ' . $sourceCase . ' renders an empty string in output template');
    $assert(count($emptyAiItem['inspected_images']) === count($inventory) - 1
        && !in_array('', array_column($emptyAiItem['inspected_images'], 'url'), true), 'source AI ' . $sourceCase . ' creates no empty inspection entry');
    $emptyAiPatch = medical_facility_image_fix_patch($emptyAiRow, $emptyAiItem);
    $emptyAiItem['ai_image_url'] = null;
    $nullAiPatch = medical_facility_image_fix_patch($emptyAiRow, $emptyAiItem);
    $assert($nullAiPatch === $emptyAiPatch && $nullAiPatch['ai_image_url'] === '', 'returned AI null and empty are equivalent when source is ' . $sourceCase);
    $emptyAiItem['added_images'] = $item['added_images'];
    $emptyAiItem['ai_image_url'] = $item['added_images'][0]['url'];
    $reject(fn() => medical_facility_image_fix_patch($emptyAiRow, $emptyAiItem), 'new photo cannot populate AI field when source is ' . $sourceCase);
}
foreach (['keep', 'uncertain'] as $aiDecision) {
    $retainedAiItem = medical_facility_image_fix_output_template($row);
    $aiReviewIndex = array_search($row['ai_image_url'], array_column($retainedAiItem['inspected_images'], 'url'), true);
    $retainedAiItem['inspected_images'][$aiReviewIndex]['decision'] = $aiDecision;
    $assert(medical_facility_image_fix_patch($row, $retainedAiItem)['ai_image_url'] === $row['ai_image_url'], 'nonempty AI ' . $aiDecision . ' preserves original URL');
    foreach (['null' => null, 'empty' => ''] as $returnCase => $returnAiUrl) {
        $invalidRetainedAi = $retainedAiItem;
        $invalidRetainedAi['ai_image_url'] = $returnAiUrl;
        $reject(fn() => medical_facility_image_fix_patch($row, $invalidRetainedAi), 'AI ' . $aiDecision . ' cannot be cleared by returned ' . $returnCase);
    }
}
$removedAiItem = medical_facility_image_fix_output_template($row);
$aiReviewIndex = array_search($row['ai_image_url'], array_column($removedAiItem['inspected_images'], 'url'), true);
$removedAiItem['inspected_images'][$aiReviewIndex] = ['url' => $row['ai_image_url'], 'decision' => 'remove',
    'reason' => 'Ảnh đã xem và xác minh không hợp lệ', 'evidence_url' => $row['ai_image_url'], 'http_status' => 200];
$removedAiItem['ai_image_url'] = '';
$removedAiPatch = medical_facility_image_fix_patch($row, $removedAiItem);
$removedAiItem['ai_image_url'] = null;
$assert(medical_facility_image_fix_patch($row, $removedAiItem) === $removedAiPatch && $removedAiPatch['ai_image_url'] === '',
    'null clears nonempty AI source only with an evidenced remove decision, same as empty');
$removedAiItem['inspected_images'][$aiReviewIndex]['evidence_url'] = '';
$reject(fn() => medical_facility_image_fix_patch($row, $removedAiItem), 'null cannot clear AI source when remove evidence is missing');
$invalid = $item; $invalid['ai_image_url'] = $item['added_images'][0]['url'];
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'new photo cannot replace a nonempty AI field');
$invalid = $item; $invalid['image_url'] = 'https://clinic.example/unknown.jpg';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'cover must be a known surviving image');
$invalid = $item; $invalid['insufficient_images'] = 'false';
$reject(fn() => medical_facility_image_fix_patch($row, $invalid), 'boolean schema enforced');
$badGallery = array_replace($row, ['gallery_json' => '{bad-json']);
$reject(fn() => medical_facility_image_fix_inventory($badGallery), 'malformed gallery never silently treated as empty');
$public = medical_facility_image_fix_public_claim($row['ai_writer_claim_json']);
$assert($public !== null && !isset($public['claim_token']), 'queue never exposes lease secret');
[, $default] = medical_facility_image_fix_prompt_default();
$prompt = medical_facility_image_fix_prompt($default, $row);
$assert(str_contains($prompt, 'BLOCK CODE') && str_contains($prompt, 'Google Maps') && str_contains($prompt, $revision), 'prompt includes exact revision, evidence investigation and code-block contract');
$assert(!str_contains($prompt, $token), 'writer claim token excluded from AI prompt');
$customPrompt = medical_facility_image_fix_prompt('Admin edited prompt', $row, 9);
$assert(str_contains($customPrompt, 'ƯU TIÊN NGUỒN ẢNH THẬT') && str_contains($customPrompt, 'ảnh người dùng chụp thực tế'), 'saved custom templates also prioritize real Google Maps photos');
$assert(str_contains($customPrompt, 'tối thiểu 5 ảnh') && str_contains($customPrompt, 'Facebook/fanpage') && str_contains($customPrompt, 'nguồn công khai khác trên mạng'), 'saved prompts get minimum five and multi-source fallback');
$assert(str_contains($customPrompt, 'không tính ảnh AI, ảnh trùng hoặc uncertain') && str_contains($customPrompt, 'insufficient_images=true'), 'minimum does not invent or count unverified images');
$assert(str_contains($default, 'GIỚI THIỆU KHÔNG GIAN THỰC TẾ') && str_contains($default, 'ảnh bìa website'), 'default prompt is a real facility photo gallery, not branding');
$assert(str_contains($customPrompt, 'KHÔNG lấy logo đứng riêng') && str_contains($customPrompt, 'poster khuyến mãi') && str_contains($customPrompt, 'không tính vào số tối thiểu'), 'saved custom prompts also exclude logos covers and designed adverts');
$assert(str_contains($customPrompt, 'Logo/biển hiệu xuất hiện tự nhiên') && str_contains($customPrompt, 'nếu không xem được thì uncertain'), 'actual signage photos and unverified originals are preserved');
$minimumPrompt = medical_facility_image_fix_prompt('Target {{target_images}}', $row, 1);
$assert(str_contains($minimumPrompt, 'Target 5') && str_contains($minimumPrompt, '"target_images":5'), 'minimum clamp agrees between rendered prompt and source JSON');
$assert(str_contains(medical_facility_image_fix_prompt('Target {{target_images}}', $row, 99), 'Target 12'), 'upper image bound is retained');
$localOnly = medical_facility_image_fix_import_now(new PDO('sqlite::memory:'), 71, ['image_url' => ['/uploads/library/existing.jpg'], 'gallery_json' => []]);
$assert($localOnly === ['imported' => 0, 'failed' => 0, 'items' => []], 'local images do not need network or worker schema');
$assert(str_contains($customPrompt, 'inspected_images'), 'edited admin prompt retains required current JSON contract');
$assert(str_contains($customPrompt, $row['name']) && str_contains($customPrompt, $row['address_text']) && str_contains($customPrompt, $row['google_maps_url']), 'edited prompt cannot omit branch identification and Maps source');
$assert(str_contains($customPrompt, 'inspection_url') && str_contains($customPrompt, '"target_images":9'), 'edited prompt still carries source inventory metadata and target count');
$hostileTemplate = 'Mẫu cũ: luôn trả ai_image_url:null kể cả đang có URL; bọc mọi URL bằng Markdown [URL](URL), đổi đường dẫn /uploads/ sang inspection_url, '
    . 'chỉ lấy ảnh có không gian/thiết bị và loại mọi ảnh bác sĩ. Nếu tất cả ảnh không hợp lệ, xóa hết rồi trả added_images=[]; không tìm ảnh thay thế.';
$hostilePrompt = medical_facility_image_fix_prompt($hostileTemplate, $row);
$assert(str_starts_with($hostilePrompt, $hostileTemplate), 'saved custom template remains intact while current policy is appended');
$mandatoryPolicy = static function (string $rendered): string {
    $marker = strrpos($rendered, 'CONTRACT API FIX ẢNH');
    if ($marker === false) throw new RuntimeException('Mandatory image-fix contract missing from rendered prompt.');
    return substr($rendered, $marker);
};
$assertPromptPolicy = static function (string $policy, string $label) use ($assert): void {
    $assert(str_contains($policy, 'URL THUẦN') && str_contains($policy, 'Markdown [URL](URL)')
        && str_contains($policy, 'HTML') && str_contains($policy, 'backtick'), $label . ': URL fields exclude Markdown, HTML and backticks');
    $assert(str_contains($policy, 'inspected_images[].url') && str_contains($policy, 'CHÍNH XÁC source.images[].url')
        && str_contains($policy, '/uploads/') && str_contains($policy, 'tên miền kể cả www')
        && str_contains($policy, 'mã hóa') && str_contains($policy, 'query string')
        && str_contains($policy, 'không đổi sang inspection_url'), $label . ': inspected URL copies exact source path, host, encoding and query');
    $assert(str_contains($policy, 'đội ngũ bác sĩ') && str_contains($policy, 'ảnh tập thể')
        && str_contains($policy, 'ảnh chân dung gốc') && str_contains($policy, 'đã xác minh bác sĩ thuộc đúng cơ sở')
        && str_contains($policy, 'vẫn hợp lệ dù không') && str_contains($policy, 'không loại ảnh bác sĩ chỉ vì'),
        $label . ': verified team and original portraits are allowed without premises or equipment');
    $assert(preg_match('/KHÔNG lấy logo đứng riêng/iu', $policy) === 1 && str_contains($policy, 'poster')
        && str_contains($policy, 'ảnh stock/AI'), $label . ': team allowance does not permit branding, adverts, stock or AI');
    $assert(str_contains($policy, 'Không suy đoán loại ảnh chỉ từ tên file') || str_contains($policy, 'không đoán từ tên file/URL'),
        $label . ': image classification must not rely on filename');
    $assert(str_contains($policy, 'TÌM ẢNH THAY THẾ') && str_contains($policy, 'thay thế toàn bộ ảnh không hợp lệ')
        && str_contains($policy, 'Nếu toàn bộ ảnh cũ không phù hợp')
        && str_contains($policy, 'thay thế toàn bộ bộ ảnh bằng ảnh mới đã xác minh')
        && str_contains($policy, 'tối thiểu 5 ảnh'), $label . ': every invalid photo requires active replacement, including an all-invalid set');
    $assert(preg_match('/Google Maps[^\n]*Facebook\/fanpage[^\n]*website chính thức[^\n]*nguồn công khai khác/u', $policy) === 1,
        $label . ': new-photo search proceeds Maps, Facebook, official website, then public sources');
    $assert(str_contains($policy, 'chủ động tìm bộ 5–7 ảnh thật') && str_contains($policy, 'không chỉ kiểm tra ảnh cũ rồi dừng')
        && str_contains($policy, 'ảnh chưa có trong danh sách URL nguồn'), $label . ': new verified Maps photo set is the primary objective');
    $assert(str_contains($policy, 'không trả toàn bộ remove kèm added_images=[] khi chưa rà hết')
        || str_contains($policy, 'Không trả toàn bộ remove kèm added_images=[] khi chưa rà hết'),
        $label . ': cannot stop at remove-all and empty additions before searching every source group');
    $assert(str_contains($policy, 'sau khi rà đầy đủ') && str_contains($policy, 'insufficient_images=true')
        && str_contains($policy, 'notes') && preg_match('/không bịa/iu', $policy) === 1,
        $label . ': exhausted search can truthfully report insufficient verified photos without fabricating replacements');
    $assert(str_contains($policy, 'uncertain') && str_contains($policy, 'giữ ảnh'), $label . ': unviewable source photos remain uncertain and retained');
    $assert(str_contains($policy, 'ai_image_url trong kết quả bắt buộc là chuỗi, không trả null')
        && str_contains($policy, 'source.ai_image_url là null, rỗng hoặc không có, trả "ai_image_url":""'),
        $label . ': absent AI source returns an empty string, never null');
    $assert(str_contains($policy, 'giữ nguyên URL đó khi decision là keep/uncertain')
        && str_contains($policy, 'decision=remove trong inspected_images, kèm reason và evidence_url hợp lệ'),
        $label . ': nonempty AI source is retained unless its exact inspection has evidenced removal');
    $assert(str_contains($policy, 'Không đưa ảnh mới vào ai_image_url'), $label . ': new real photos never populate the AI field');
    $first = strpos($policy, 'BƯỚC 1 — MỞ FILE VÀ XEM ẢNH');
    $second = strpos($policy, 'BƯỚC 2 — PHÂN LOẠI VÀ LOẠI BỎ');
    $third = strpos($policy, 'BƯỚC 3 — TÌM VÀ BỔ SUNG');
    $assert($first !== false && $second !== false && $third !== false && $first < $second && $second < $third,
        $label . ': ordered workflow opens files, classifies removals, then supplements');
    $assert(str_contains($policy, 'max(0, 5-K)') && str_contains($policy, '2 remove + 5 uncertain thì K=0')
        && str_contains($policy, 'phải tìm tối thiểu 5 ảnh mới'), $label . ': uncertain photos do not satisfy the verified minimum');
    $assert(str_contains($policy, 'Mở và xem từng file ảnh mới') && str_contains($policy, 'tổng ảnh đạt chuẩn')
        && str_contains($policy, 'số còn thiếu'), $label . ': new photos need visual verification and honest shortage accounting');
    $assert(str_contains($policy, 'cơ sở vật chất hoặc đội ngũ bác sĩ')
        && str_contains($policy, 'kết quả trước–sau') && str_contains($policy, 'chân dung khách hàng/người nổi tiếng')
        && str_contains($policy, 'thumbnail phỏng vấn'), $label . ': excludes unrelated clinical, customer and promotional imagery after inspection');
    $assert(str_contains($policy, 'Không coi caption/tên file là bằng chứng nội dung')
        && str_contains($policy, 'không thêm ảnh mới chưa xác minh')
        && str_contains($policy, 'Không loại ảnh hợp lệ chỉ vì có người bệnh'), $label . ': visual verification preserves legitimate facility photos');
    $assert(str_contains($policy, 'truy vấn tên cơ sở + địa chỉ chi nhánh')
        && str_contains($policy, 'không dùng truy vấn dịch vụ chung'), $label . ': replacement search is branch-specific rather than generic service imagery');
    $assert(str_contains($policy, 'Nếu Maps đã có đủ ảnh phù hợp thì không lấy ảnh website')
        && str_contains($policy, 'Khi Maps thiếu ảnh hoặc không truy cập/xác minh được'), $label . ': Maps photos remain first choice, website is fallback');
    $assert(str_contains($policy, 'Giới thiệu / Về chúng tôi (About / About us)')
        && str_contains($policy, 'trang giới thiệu chi nhánh') && str_contains($policy, 'cơ sở vật chất / thư viện ảnh'), $label . ': website fallback prioritizes introduction and branch pages');
    $assert(str_contains($policy, 'Không lấy ảnh từ bài kiến thức, bài SEO dịch vụ, tin khuyến mãi')
        && str_contains($policy, 'source_url phải trỏ tới chính trang chứa ảnh')
        && str_contains($policy, 'không mặc định ảnh trang Về chúng tôi của toàn hệ thống thuộc chi nhánh'), $label . ': website images need exact-page branch attribution, not generic service illustrations');
};
$assertPromptPolicy($default, 'default template');
$assertPromptPolicy($mandatoryPolicy($prompt), 'rendered default mandatory policy');
$assertPromptPolicy($mandatoryPolicy($customPrompt), 'saved custom template mandatory policy');
$assertPromptPolicy($mandatoryPolicy($hostilePrompt), 'hostile old template mandatory policy');
foreach ([$prompt, $customPrompt, $hostilePrompt] as $captionPrompt) {
    $assert(str_contains($mandatoryPolicy($captionPrompt), 'CAPTION NGẮN GỌN')
        && str_contains($mandatoryPolicy($captionPrompt), 'Không ghi nguồn, URL')
        && str_contains($mandatoryPolicy($captionPrompt), 'source, source_url, evidence_url')
        && str_contains($mandatoryPolicy($captionPrompt), 'Chỉ ghi tên bác sĩ khi đã xác minh'),
        'short captions override saved templates while retaining separate source evidence');
}
$assert(str_contains($mandatoryPolicy($hostilePrompt), 'ưu tiên hơn mẫu cũ')
    && str_contains($mandatoryPolicy($hostilePrompt), 'cố gắng đạt 6 ảnh'), 'mandatory policy overrides conflicting saved rules and keeps the default target of six');
$exactUrlPrompt = medical_facility_image_fix_prompt('Custom {{source_json}}', $exactUrlRow);
$assert(str_contains($exactUrlPrompt, '"url":"' . $exactLocalUrl . '"') && str_contains($exactUrlPrompt, '"url":"' . $exactRemoteUrl . '"')
    && str_contains($exactUrlPrompt, '"inspection_url":"https://medreview.vn' . $exactLocalUrl . '"'), 'rendered source JSON distinguishes original exact URLs from viewing URLs');
foreach (['null' => null, 'empty' => ''] as $sourceCase => $sourceAiUrl) {
    $emptyAiRow = array_replace($row, ['ai_image_url' => $sourceAiUrl]);
    $emptyAiPrompt = medical_facility_image_fix_prompt('Admin edited prompt', $emptyAiRow);
    $outputMarker = strrpos($emptyAiPrompt, 'Khung kết quả: ');
    $assert($outputMarker !== false && str_contains(substr($emptyAiPrompt, $outputMarker), '"ai_image_url":""'),
        'saved template mandatory output normalizes AI source ' . $sourceCase . ' to empty string');
}

final class ImageFixStatement extends PDOStatement
{
    public function __construct(private ImageFixPDO $database, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if (str_starts_with($this->sql, 'UPDATE medical_facilities')) {
            $this->database->writes++;
            $this->database->row = array_replace($this->database->row, ['image_url' => $params[':cover'], 'ai_image_url' => $params[':ai'],
                'gallery_json' => $params[':gallery'], 'image_fix_json' => $params[':audit'], 'ai_writer_claim_json' => null]);
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->database->row; }
}
final class ImageFixPDO extends PDO
{
    public int $writes = 0; public int $rollbacks = 0; public int $commits = 0; private bool $transaction = false;
    public function __construct(public array $row) {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new ImageFixStatement($this, $query); }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; $this->commits++; return true; }
    public function rollBack(): bool { $this->transaction = false; $this->rollbacks++; return true; }
    public function inTransaction(): bool { return $this->transaction; }
}
$db = new ImageFixPDO($row);
$result = medical_facility_image_fix_save($db, $item);
$assert($result['added_count'] === 1 && $db->writes === 1 && $db->commits === 1, 'valid result saved atomically');
$assert($db->row['content'] === $row['content'] && $db->row['status'] === 'published', 'article and publication unchanged');
$assert($db->row['ai_writer_claim_json'] === null, 'lease cleared only after successful save');
$audit = json_decode($db->row['image_fix_json'], true);
$assert($audit['before']['gallery_json'] === $row['gallery_json'] && $audit['source_revision'] === $revision, 'audit retains recoverable prior URLs and revision');
$assert(!str_contains($db->row['image_fix_json'], $token), 'audit stores token hash, never raw secret');
$retry = medical_facility_image_fix_save($db, $item);
$assert($retry['already_processed'] === true && $db->writes === 1, 'same result retry is idempotent after lease cleared');
$assert(isset($retry['queue_sources']['gallery_json']), 'idempotent retry can recover interrupted download enqueue');
foreach (['stale' => 'images_changed', 'expired' => 'lease_lost', 'wrong_owner' => 'lease_lost', 'wrong_task' => 'lease_lost', 'draft' => 'not_published'] as $case => $reason) {
    $changed = $row;
    if ($case === 'stale') $changed['gallery_json'] = '[]';
    elseif ($case === 'draft') $changed['status'] = 'draft';
    else {
        $claim = json_decode($changed['ai_writer_claim_json'], true);
        if ($case === 'expired') $claim['expires_at'] = time() - 1;
        if ($case === 'wrong_owner') $claim['claim_token'] = str_repeat('b', 64);
        if ($case === 'wrong_task') $claim['task'] = 'facility_article';
        $changed['ai_writer_claim_json'] = json_encode($claim);
    }
    $db = new ImageFixPDO($changed);
    $reject(fn() => medical_facility_image_fix_save($db, $item), 'concurrent/lease conflict ' . $case, $reason);
    $assert($db->writes === 0 && $db->rollbacks === 1, 'no partial write on conflict ' . $case);
}
$db = new ImageFixPDO($row); $invalid = $item; $invalid['inspected_images'] = [];
$reject(fn() => medical_facility_image_fix_save($db, $invalid), 'invalid payload rejected inside lock');
$assert($db->writes === 0 && $db->row['ai_writer_claim_json'] === $row['ai_writer_claim_json'], 'failed validation preserves images and lease');
$empty = array_replace($row, ['image_url' => '', 'ai_image_url' => '', 'gallery_json' => '[]']);
$emptyItem = medical_facility_image_fix_output_template($empty); $emptyItem['added_images'] = $item['added_images'];
$assert(medical_facility_image_fix_patch($empty, $emptyItem)['image_url'] === $item['added_images'][0]['url'], 'empty facility can receive new real images');

// Simulate the receiver removing a photo between a worker's SELECT and UPDATE.
final class ImageFixWorkerStatement extends PDOStatement
{
    public function __construct(private ImageFixWorkerPDO $database, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if (str_starts_with($this->sql, 'UPDATE ')) {
            $this->database->updates++;
            $this->database->compareAndSwap = str_contains($this->sql, 'image_url <=> :original_cover AND gallery_json <=> :original_gallery');
            if ($this->database->removeDuringUpdate) $this->database->row = ['image_url' => '', 'gallery_json' => '[]'];
        }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed { return 1; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $this->database->reads++;
        return $this->database->row;
    }
    public function rowCount(): int { return 0; }
}
final class ImageFixWorkerPDO extends PDO
{
    public int $reads = 0; public int $updates = 0; public bool $compareAndSwap = false;
    public function __construct(public array $row, public bool $removeDuringUpdate = true) {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new ImageFixWorkerStatement($this, $query); }
}
$workerDb = new ImageFixWorkerPDO(['image_url' => $row['image_url'], 'gallery_json' => $row['gallery_json']]);
$job = ['entity_type' => 'facility', 'entity_id' => 71, 'source_url' => $row['image_url']];
$workerResult = medical_media_jobs_apply_local_url($workerDb, $job, '/uploads/library/new.jpg');
$assert($workerDb->compareAndSwap, 'worker UPDATE checks exact cover and gallery snapshot');
$assert(!$workerResult['changed'] && $workerDb->reads === 2 && $workerDb->updates === 1, 'worker rereads concurrent removal and never restores deleted photo');
$assert($workerDb->row === ['image_url' => '', 'gallery_json' => '[]'], 'receiver removal remains authoritative');
$workerDb = new ImageFixWorkerPDO(['image_url' => $row['image_url'], 'gallery_json' => $row['gallery_json']], false);
$workerResult = medical_media_jobs_apply_local_url($workerDb, $job, '/uploads/library/new.jpg');
$assert(($workerResult['reason'] ?? '') === 'images_changed' && $workerDb->updates === 3, 'worker contention retry is bounded');
$assert(isset($workerResult['error']), 'contention is retryable, never reported as a finished download rewrite');

if (in_array('--mysql-temporary', $argv, true)) {
    $mysql = db();
    $temporaryCreated = false;
    try {
        // A same-name TEMPORARY table shadows the real table on this connection.
        // Never write unless SHOW CREATE confirms that the shadow is active.
        $create = (string) $mysql->query('SHOW CREATE TABLE medical_facilities')->fetch(PDO::FETCH_NUM)[1];
        $create = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create, 1);
        $create = preg_replace('/^\s*CONSTRAINT[^\n]*\n/m', '', $create);
        $create = preg_replace('/,\n\)/', "\n)", $create);
        $mysql->exec($create);
        $temporaryCreated = true;
        $shadow = (string) $mysql->query('SHOW CREATE TABLE medical_facilities')->fetch(PDO::FETCH_NUM)[1];
        $assert(str_starts_with($shadow, 'CREATE TEMPORARY TABLE'), 'MySQL test writes only to a confirmed temporary shadow');
        medical_facility_image_fix_require_schema($mysql);
        $keys = array_keys($row);
        $insert = $mysql->prepare('INSERT INTO medical_facilities (`' . implode('`,`', $keys) . '`) VALUES (' . implode(',', array_fill(0, count($keys), '?')) . ')');
        $insert->execute(array_values($row));
        $read = static function () use ($mysql): array {
            return $mysql->query('SELECT * FROM medical_facilities WHERE id=71')->fetch(PDO::FETCH_ASSOC);
        };
        $storedRow = $read();
        $mysqlItem = $item;
        $mysqlItem['images_revision'] = medical_facility_image_fix_revision($storedRow);
        $mysqlItem['image_url'] = $item['added_images'][0]['url'];
        $mysqlResult = medical_facility_image_fix_save($mysql, $mysqlItem);
        $saved = $read();
        $assert(strlen($saved['image_url']) > 255 && $saved['image_url'] === $mysqlItem['image_url'], 'MySQL preserves full long Google cover URL');
        $assert($saved['content'] === $row['content'] && $saved['status'] === 'published' && $saved['ai_writer_claim_json'] === null, 'MySQL saves images only and releases completed claim');
        $assert($mysqlResult['removed_count'] === 1 && $mysqlResult['added_count'] === 1 && $saved['images_label'] === '3 ảnh', 'MySQL returns correct image changes and count');
        $savedAudit = json_decode($saved['image_fix_json'], true);
        $assert(($savedAudit['before']['gallery_json'] ?? '') === $storedRow['gallery_json'], 'MySQL audit preserves prior gallery');
        $replayed = medical_facility_image_fix_save($mysql, $mysqlItem);
        $assert($replayed['already_processed'] === true && $read()['gallery_json'] === $saved['gallery_json'], 'MySQL repeat POST is idempotent');

        $reset = $mysql->prepare('UPDATE medical_facilities SET image_url=?,gallery_json=?,ai_writer_claim_json=?,image_fix_json=NULL WHERE id=71');
        $reset->execute([$row['image_url'], $row['gallery_json'], $row['ai_writer_claim_json']]);
        $fresh = $read();
        $stale = $item; $stale['images_revision'] = medical_facility_image_fix_revision($fresh);
        $mysql->exec("UPDATE medical_facilities SET gallery_json='[]' WHERE id=71");
        $beforeFailure = $read();
        $reject(fn() => medical_facility_image_fix_save($mysql, $stale), 'MySQL stale image result rejected', 'images_changed');
        $assert($read() === $beforeFailure && !$mysql->inTransaction(), 'MySQL conflict rolls back without changing images or claim');

        $reset->execute([$row['image_url'], $row['gallery_json'], $row['ai_writer_claim_json']]);
        $mysql->exec("UPDATE medical_facilities SET language_code='en' WHERE id=71");
        $english = $item; $english['images_revision'] = medical_facility_image_fix_revision($read());
        medical_facility_image_fix_save($mysql, $english);
        $assert($read()['images_label'] === '3 photos', 'MySQL English image count remains localized');
    } finally {
        if ($mysql->inTransaction()) $mysql->rollBack();
        if ($temporaryCreated) $mysql->exec('DROP TEMPORARY TABLE medical_facilities');
    }
}
echo "Facility image fix: {$checks} checks passed. " . (in_array('--mysql-temporary', $argv, true)
    ? 'MySQL writes used a connection-scoped temporary shadow only; real facility images unchanged.'
    : 'Mocked PDO only; no network or DB records modified.') . "\n";
