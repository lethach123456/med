# API Fix ảnh cơ sở y tế — contract v1

## Cài đặt

Chạy riêng một lần sau khi đưa code lên máy chủ:

```sh
php scripts/migrate_facility_image_fix.php
```

Migration thêm `medical_facilities.image_fix_json`, mở rộng `image_url` thành TEXT để
lưu URL Google dài, và `INSERT IGNORE` prompt `facility_image_fix` vào
`medical_ai_prompts`. Không seed dữ liệu; prompt tùy chỉnh đã lưu được giữ lại.
Migration y tế tổng cũng bao gồm nâng cấp này. API không tự chạy migration.

Ảnh ngoài được kiểm tra, tải và nén/lưu trực tiếp trong request nhận Fix ảnh,
không enqueue và không cần cron worker. Receiver báo `image_processing=completed`
hoặc `partial`, kèm `images_imported`, `images_failed`, `image_results` từng bài.
Ảnh lỗi giữ URL nguồn, có thể retry cùng payload/token để tải phần còn thiếu.
Request sẽ lâu hơn trước tùy số ảnh và tốc độ nguồn; tiện ích cần timeout đủ dài.

## Các cổng

Mọi request cần header `X-Medical-Api-Key` hiện có. Tiện ích gọi API trong background
service worker với host permission MedReview; không đưa API key/claim token vào AI.

| Method | Endpoint | Chức năng |
| --- | --- | --- |
| GET | `/api/medical/facilities-needing-image-fix.php?limit=10` | Hàng đợi kiểm tra ảnh, không yêu cầu bài phải trống content |
| GET | `/api/medical/facilities-needing-image-fix.php?ids=71` | Lấy lại ảnh hiện tại của ID cụ thể, kể cả đã kiểm tra |
| POST | `/api/medical/facility-image-fix-update.php` | Nhận kết quả kiểm tra/xóa/bổ sung ảnh |
| POST | `/api/medical/writer-claim.php` | Giữ bài với `type=facility`, `client.task=facility_image_fix` |
| GET | `/api/medical/prompt.php?type=facility_image_fix&id=71` | Prompt đang lưu trong Admin + contract và ảnh mới nhất |

Đây là luồng riêng, không sử dụng `facilities-needing-ai-images.php` hoặc
`facility-ai-image-update.php` (hai cổng đó dùng tạo ảnh AI).

Queue mặc định: published, tiếng Việt, chưa kiểm tra hoặc lần kiểm tra cách ít nhất
30 ngày. `language=en` chọn bản tiếng Anh. `only_pending=0` lấy cả bản mới kiểm tra;
`recheck_days=1..365`, `target_images=5..12` (mặc định 6, giá trị dưới 5 được nâng lên 5), `limit=1..50`, `page` hoặc
`after_id` để phân trang. Khi cấp `ids`/`id`, bỏ điều kiện thời điểm kiểm tra nhưng vẫn
lọc published/ngôn ngữ. Queue không tự gọi từng host ảnh hay suy đoán URL nào hỏng.

Mỗi item trả ảnh bìa `image_url`, ảnh riêng `ai_image_url`, `gallery_json` dạng array,
và `images` gộp URL trùng. Mỗi image có `url`, `inspection_url` (ảnh nội bộ đổi thành
URL tuyệt đối để AI xem), `fields` (các vị trí nguồn) và metadata. `images_revision`
là SHA-256 của các trường ảnh và thông tin nhận diện cơ sở; heartbeat không thay nó.
`writer_claimed`/`writer_claim` không chứa token. `prompt` là prompt đã render từ tab
**Fix ảnh cơ sở y tế** trong Admin > Prompt AI y tế. `output_template` cung cấp đủ
mọi URL cần đánh giá. Không bỏ qua item đã có nội dung bài viết.
Nếu prompt admin bỏ `{{source_json}}`, API vẫn tự bổ sung danh tính chi nhánh,
danh sách ảnh và số lượng mong muốn để AI không mất ngữ cảnh điều tra.
Contract hiện hành luôn được nối vào cuối prompt, ưu tiên hơn chỉ dẫn cũ về URL,
loại ảnh và tìm ảnh thay thế; không ghi đè mẫu tùy chỉnh đã lưu trong Admin.

## Tiêu chí ảnh trong prompt

Ảnh thật được xác minh đúng chi nhánh có thể giới thiệu không gian, thiết bị hoặc
đội ngũ bác sĩ. Ảnh bác sĩ đang làm việc, ảnh tập thể và ảnh chân dung gốc vẫn hợp lệ
dù không có mặt tiền, lễ tân hay thiết bị. Không nhận logo riêng, banner, poster,
đồ họa quảng cáo, ảnh stock/AI; không suy đoán loại ảnh chỉ từ tên file.

Mọi ảnh đã xem và xác minh không hợp lệ đều cần tìm ảnh thật mới thay thế. Nếu toàn
bộ bộ ảnh cũ không hợp lệ, phải tìm lại toàn bộ bộ ảnh: tối thiểu 5 ảnh khác nhau,
mục tiêu mặc định 6. Thứ tự tìm là Google Maps đúng chi nhánh → website chính thức
→ Facebook/fanpage → nguồn công khai khác. Không kết thúc bằng xóa hết và
`added_images=[]` khi chưa rà đầy đủ các nhóm nguồn. Nếu thực sự vẫn thiếu, trả mọi
ảnh mới đã xác minh, `insufficient_images=true` và ghi nguồn đã rà/lý do trong
`notes`; không bịa ảnh để đủ số lượng. Ảnh chưa xem được vẫn `uncertain` và được giữ.

## Quy trình tiện ích bắt buộc

1. Thêm nguồn tác vụ riêng **Fix ảnh cơ sở y tế**, ví dụ `sourceType=facility_image_fix`.
   Dùng queue/receiver tương ứng; trạng thái, kết quả, start/stop và retry tách theo
   tác vụ/tab như các nguồn hiện tại. Có thể chạy Gemini, ChatGPT, Grok.
2. Lấy queue, hiển thị tên cơ sở, số ảnh và máy đang giữ bài. Queue không phải claim.
3. Ngay trước khi gửi AI, claim ID:

```json
{
  "action": "claim",
  "type": "facility",
  "id": 71,
  "request_id": "stable-unique-id-for-this-attempt",
  "client": {
    "instance_id": "stable-install-id",
    "instance_label": "Máy A",
    "worker_id": "worker-tab-id",
    "provider": "gemini",
    "model": "model label",
    "task": "facility_image_fix"
  }
}
```

4. Chỉ `claimed=true` mới chạy. 409 `already_claimed` bỏ qua; không gửi AI trước claim.
   Cùng request/instance/worker/task khi retry claim sẽ nhận lại cùng token. Fix ảnh
   dùng chung khóa DB với viết bài để tránh hai tác vụ sửa ảnh của một cơ sở cùng lúc.
   Khác với viết bài, claim Fix ảnh được phép cho cơ sở đã có content.
5. Sau khi claim, GET lại queue `?ids=71` để lấy ảnh/revision/prompt hiện tại. Nếu không
   còn item, release và bỏ qua. Dùng item.prompt nguyên bản; không dựng lại bằng
   prompt viết bài, dịch hoặc tạo ảnh. Giữ token trong tiện ích, không đưa vào prompt.
6. Heartbeat mỗi 45 giây trong toàn bộ thời gian AI chạy, parse và retry gửi. Lease
   180 giây. Body: `{"action":"heartbeat","type":"facility","id":71,"claim_token":"..."}`.
   Khi mất/hết lease, dừng gửi kết quả cũ và nhận lại trước khi tiếp tục.
7. AI phải điều tra ảnh với công cụ web/xem ảnh. Kết quả nằm trong một code block json.
   Parse block như luồng bài viết; yêu cầu ID/revision khớp nguồn, đủ inspected_images,
   không tự xóa/sửa revision và không chuyển mọi lỗi truy cập thành remove.
8. Gắn `writer_claim_token` bằng token riêng của tiện ích, rồi POST JSON object tới
   receiver. Có thể gửi `{ "items": [...] }`, tối đa 10 items/2MB mỗi request.
9. Thành công: receiver tự trả claim và lưu nhật ký, hàng đợi không nhận lại trong
   thời gian recheck. Dừng/hủy trước khi gửi thành công phải release claim bằng API.
   Release: `{"action":"release","type":"facility","id":71,"claim_token":"..."}`.

## JSON AI và body gửi về

Ví dụ minh họa; phải dùng ID, revision, URL nguồn thật từ request, không dùng các URL
example dưới đây làm dữ liệu thật:

```json
{
  "id": 71,
  "images_revision": "<64 hex ký tự từ API request>",
  "inspected_images": [
    {"url":"https://clinic.example/broken.jpg","decision":"remove","reason":"HTTP 404 đã kiểm tra","evidence_url":"https://clinic.example/broken.jpg","http_status":404},
    {"url":"/uploads/library/clinic/cover.jpg","decision":"keep","reason":"Ảnh đúng chi nhánh","evidence_url":"https://maps.google.com/?cid=123","http_status":200},
    {"url":"https://clinic.example/blocked.jpg","decision":"uncertain","reason":"Host trả 403, chưa kết luận ảnh hỏng","evidence_url":"","http_status":403}
  ],
  "added_images": [
    {"url":"https://lh3.googleusercontent.com/p/actual-photo-id=s1600","angle":"Mặt tiền","caption":"Biển hiệu chi nhánh đúng địa chỉ","source":"Google Maps","source_url":"https://maps.google.com/?cid=123"}
  ],
  "image_url": "/uploads/library/clinic/cover.jpg",
  "ai_image_url": "",
  "insufficient_images": false,
  "notes": "Đã đối chiếu chi nhánh theo địa chỉ.",
  "writer_claim_token": "<tiện ích tự gắn, AI không nhận hoặc xuất token>"
}
```

Mọi trường URL phải là chuỗi URL/đường dẫn thuần, không bọc Markdown `[URL](URL)`,
HTML hay backtick. `inspected_images[].url` phải sao chép chính xác
`source.images[].url`, giữ nguyên `/uploads/`, tên miền, mã hóa và toàn bộ query
string. `inspection_url` chỉ dùng để mở xem, không thay cho URL nguồn trong kết quả.

`inspected_images` phải có MỌI URL trong source.images, mỗi URL đúng một lần.
`keep`/`uncertain` giữ ảnh, `remove` loại URL khỏi các trường đang chứa nó. Omission
không bao giờ ngầm xóa ảnh. Remove cần reason/evidence_url; receiver từ chối remove
với HTTP 401/403/408/429/5xx. Sau khi rà đủ nguồn mà không tìm được ảnh mới đáng tin,
dùng added_images=[] và insufficient_images=true. Không xóa file vật lý trên thư
viện; nhật ký giữ URL cũ.

`added_images` tối đa 12 ảnh trực tiếp, khác các ảnh nguồn, có source/source_url.
`angle` và `caption` không bắt buộc, mặc định chuỗi rỗng; nếu có, tối đa 120/500 ký tự.
Receiver từ chối URL nội bộ/credentials/API key, data/blob và trang HTML/Google Maps
thay cho file ảnh. Worker có kiểm tra host, redirects, giới hạn tải, dữ liệu ảnh và
nén/lưu thư viện. Ảnh mới không được coi là đã tải thành công chỉ vì POST thành công.

`image_url` chọn ảnh nguồn được giữ hoặc added_images. Nếu bìa đã remove, tự chọn ảnh
gallery còn lại; nếu không còn ảnh, để trống. `ai_image_url` chỉ giữ nguyên hoặc xóa
khi ảnh nguồn tương ứng có quyết định remove. Không sinh ảnh minh họa mới trong luồng
này. Giữ nguyên mọi query string của URL Google; không tự chế image ID.

## Kết quả và xử lý lỗi

- 200: `ok=true` nghĩa là JSON đã lưu; xem thêm `image_processing=completed|partial`, `warnings` và `updated[].images_failed` để biết ảnh đã tải đủ chưa. `images_queued=0`.
- 207: batch có item thành công và thất bại; kiểm tra từng ID, chỉ retry item lỗi.
- 409 `images_changed`: ảnh/nhận diện đã thay đổi (có thể do worker vừa tải ảnh).
  GET nguồn lại và chạy AI lại; không thay revision cũ bằng revision mới để gửi đè.
- 409 `lease_lost`: token sai, hết hạn hoặc task không phải Fix ảnh. Dừng gửi và claim lại.
- 409 `not_published`: bỏ qua.
- 422: JSON/URL/decision sai contract. Giữ kết quả để sửa/gửi lại, heartbeat lease.
- 503: schema/prompt chưa sẵn sàng. Chạy migration riêng nêu trên sau deploy.
- Retry đúng cùng payload/token sau mất response trả `already_processed=true`, không
  thêm ảnh trùng hoặc chạy lại update. Nếu JSON bị thay đổi thì không coi là retry cũ.

Receiver khóa row bằng transaction và kiểm tra claim + revision ngay trong lock.
Chỉ cập nhật `image_url`, `ai_image_url`, `gallery_json`, `images_label`, `image_fix_json`
và trả `ai_writer_claim_json` về NULL. Content, giá, đánh giá và trạng thái xuất bản
không bị thay đổi. Nhật ký lưu thời điểm, quyết định/bằng chứng, nguồn ảnh bổ sung,
snapshot ảnh cũ và metadata máy thực hiện; không lưu token thô. Worker dùng điều kiện
so sánh dữ liệu gốc để không khôi phục ảnh vừa bị người dùng/receiver loại bỏ.

## Kiểm tra phát triển

```sh
php tests/facility_image_fix_test.php
```

Bộ kiểm tra dùng PDO giả lập: đủ ảnh nguồn, bảo toàn metadata/ảnh uncertain, URL sai,
URL nguồn chính xác và không bọc Markdown, quy tắc prompt mặc định/mẫu tùy chỉnh
cũ, stale revision, lease sai/hết hạn, rollback, idempotent retry và không sửa nội dung.

Kiểm tra thêm SQL thật (tùy chọn):

```sh
php tests/facility_image_fix_test.php --mysql-temporary
```

Lệnh này kết nối DB cấu hình, chỉ ghi bảng TEMPORARY cùng tên trên connection riêng.
Test xác nhận bảng tạm trước khi ghi; không thay ảnh/hồ sơ thật. Kiểm tra URL dài,
snapshot ảnh cũ, rollback khi ảnh thay đổi và gửi lại không thêm ảnh trùng.
