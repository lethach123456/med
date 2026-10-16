# Cẩm nang AI — contract v1

Hai endpoint mới dùng API key hiện có trong header `X-Medical-Api-Key`. Không đưa key, claim token hoặc revision vào prompt gửi cho AI.

- Lấy chủ đề: `GET /api/medical/handbooks-needing-content.php`.
- Nhận HTML + ảnh: `POST /api/medical/handbook-content-update.php`.
- Giữ bài/heartbeat/release/status: `POST` **chính endpoint lấy chủ đề**, không dùng `writer-claim.php` của bác sĩ/cơ sở.
- Lấy lại prompt theo ID: endpoint cũ `GET /api/medical/prompt.php?type=handbook&id=123`.

## Chuẩn bị và triển khai

1. Deploy các file PHP mới, file prompt cập nhật và tài liệu này. Chạy riêng trên **đúng DB của bản deploy**:

   ```sh
   php scripts/migrate_handbook_ai.php
   ```

   Migration thêm `posts.ai_writer_claim_json`, `posts.ai_content_json`, bảng ghép cặp `handbook_translations` và insert prompt `handbook` nếu chưa tồn tại; không xóa/sửa bài hay ghi đè prompt admin đã tùy chỉnh. Chỉ cần chức năng ghép bài thì chạy riêng `php scripts/migrate_handbook_translations.php`. Không có migration trong GET/POST runtime. `medical_ai_prompts` và core CMS phải tồn tại từ các migration trước.

2. PHP cần GD (JPEG/PNG/WebP), DOM/libxml và mbstring; tải ảnh URL còn cần **cURL** và kết nối HTTP(S) outbound tới nguồn ảnh công khai. Upload tree `uploads/handbook` phải cho PHP ghi. Ảnh là dữ liệu CMS runtime được Git ignore, cần backup cùng DB/uploads trên hosting, không trông chờ Git để khôi phục ảnh. Nên đặt `memory_limit=256M` trở lên và giới hạn request/proxy phù hợp 24 MiB JSON (ví dụ `post_max_size=32M`). Đây là khuyến nghị cấu hình, code **không tự thay cấu hình hosting**. `upload_max_filesize` không điều khiển JSON BASE64. PHP/server/WAF vẫn có thể chặn trước khi request vào code; không tắt bảo vệ bot toàn website để xử lý.

3. Tạo chủ đề trong Admin → Cẩm nang → **Thêm bài**: chọn **Ngôn ngữ** Việt/Anh ngay trong form; tùy chọn **Bài tiếng Anh/Việt liên kết** để nối với bản dịch đã có. Nhập tiêu đề, slug hợp lệ (editor tự sinh khi để trống), tùy chọn mô tả làm brief; để **draft**, **content rỗng**. Editor Cẩm nang không dùng template. Nếu thiếu chuyên mục `blog`/`blog-en`, **chỉ sau khi bấm Lưu và xác thực CSRF hợp lệ**, chuyên mục ngôn ngữ được chọn mới được tạo cùng bài trong một transaction; mở danh sách/form không ghi DB. Bài có nội dung, bài xuất bản, trang dịch vụ và ngôn ngữ khác không nằm trong hàng đợi.

   Trong form có **Liên kết bản dịch**: chọn một bài ngôn ngữ còn lại rồi Lưu. Một bài Việt chỉ ghép với một bài Anh; không đoán theo tên/slug, không lấy bài đã ghép với bài khác. Ghép/thay/gỡ liên kết cùng transaction lưu bài. Chỉ khi cả hai bài có nội dung và đã xuất bản, menu ngôn ngữ mới mở đúng bài tương ứng và có hreflang hai chiều; bản nháp không bị lộ. Chưa migrate bảng ghép cặp vẫn thêm/lưu bài thường được, riêng phần liên kết có hướng dẫn rõ.

4. Prompt có thể sửa ở Admin → Prompt AI → Viết bài Cẩm nang sau migration. Prompt mặc định có sẵn trong code để fallback read-only; quy tắc vận chuyển hiện hành luôn được bổ sung cho mẫu cũ. Các block quy tắc do hệ thống quản lý và câu giới hạn ảnh chỉ BASE64 của mẫu mặc định cũ được cập nhật khi render, không ghi DB hoặc thay nội dung biên tập riêng. Không cần tạo key/token riêng cho Cẩm nang.

Hỗ trợ ảnh URL là thay đổi **bổ sung, vẫn `contract_version=1`**: bài cũ chỉ có BASE64 tiếp tục hợp lệ; có thể gửi chỉ URL ảnh hoặc trộn URL và BASE64 trong cùng `content_html`. `limits.accepted_image_sources` là `["base64","public_http_url"]`; các giới hạn số lượng, bytes và kích thước ảnh không đổi.

Các trường giới hạn mới phân biệt hai nhánh lưu: `preserve_webp_below_bytes=102400`, `preserved_webp_max_side=8192`, `compressed_max_side=1280`. `stored_max_side=1280` được giữ tương thích cho **nhánh nén JPEG**, không áp đặt resize lên WebP nhỏ được giữ nguyên; `stored_max_bytes=102399` vẫn là giới hạn bytes file kết quả của cả hai nhánh. Giới hạn đầu vào `max_image_pixels=12000000`/`max_image_dimension=8192` không thay đổi.

### Nhập chủ đề hàng loạt trong admin

Admin → Cẩm nang → **Nhập JSON** → chọn ngôn ngữ mặc định → dán mảng → **Tạo bài nháp**:

```json
[
  "Cách chăm sóc răng miệng hằng ngày",
  {"title":"Khi nào nên đi khám mắt?","language":"vi"},
  {"title":"Daily dental care tips","language":"en"}
]
```

Tối đa 100 tiêu đề, 128 KB JSON/lần; title 1–160 ký tự văn bản. Chuỗi dùng ngôn ngữ mặc định, đối tượng có thể đặt `language` riêng. Chỉ nhận `title`/`language`, không nhập content/status hay tự ghép bản dịch. Tự sinh slug duy nhất; bỏ qua tiêu đề trùng cùng ngôn ngữ trong Cẩm nang (kể cả bài đã xuất bản và mục trùng trong batch), không sửa bài có sẵn. Tất cả bài mới là **draft**, **content rỗng**, template tắt và sẵn sàng cho hàng đợi AI. Lỗi định dạng ở bất kỳ mục nào chặn toàn bộ batch trước khi ghi; lỗi DB rollback transaction. Form yêu cầu đăng nhập, CSRF và chuyển hướng về danh sách sau khi nhập thành công.

## 1. Lấy chủ đề cần viết

```http
GET /api/medical/handbooks-needing-content.php?limit=10&language=vi
X-Medical-Api-Key: <configured-key>
```

Query tùy chọn: `page` nguyên dương, `limit` 1–25 (mặc định 10), `language=vi|en`, `ids=1,2,3` (tối đa 25 ID). Bỏ language để lấy cả hai; không trộn nội dung trong một bài. Danh sách loại các claim còn hiệu lực, thứ tự ID tăng dần. `posts.language` là nguồn ngôn ngữ chính; null/rỗng cũ được hiểu là vi.

Response chứa `ok`, `type=handbook`, `contract_version=1`, `page/limit/total/pages`, `claim_required=true`, `claim_endpoint`, `receive_endpoint`, `lease_seconds=600`, `heartbeat_interval_seconds=45`, `max_body_bytes`, `limits`, `save_status=draft`, `items`.

Mỗi item có `id,title,topic,slug,language,source_revision,prompt,output_template,prompt_label,prompt_source`. Đây là danh sách chủ đề, **không phải đã nhận quyền viết**. Không có token cá nhân trong item. Lấy lại danh sách không thay DB, không tạo ảnh, không làm migration.

### Giữ bài trước khi gọi AI

```json
{
  "action": "claim",
  "type": "handbook",
  "id": 123,
  "source_revision": "<64-hex-from-item>",
  "request_id": "stable-uuid-for-this-job",
  "client": {
    "instance_id": "stable-device-install-id",
    "worker_id": "stable-tab-worker-id",
    "instance_label": "Máy làm việc",
    "account_label": "Tài khoản A",
    "provider": "gemini",
    "model": "configured-model",
    "task": "handbook_article"
  }
}
```

Gửi JSON vào `claim_endpoint`, kèm API key và `Content-Type: application/json`. `source_revision` **bắt buộc cả lúc claim**: queue/prompt cũ sau khi admin sửa chủ đề sẽ bị từ chối. Thành công trả token ngẫu nhiên riêng, revision, thời điểm server và hạn lease. Chỉ lúc này mới gọi AI. Retry request claim bị mất response phải giữ nguyên instance/worker/request/revision để lấy cùng token, không sinh job mới.

Trong khi viết/tạo ảnh/gửi web, gửi heartbeat khoảng 45 giây:

```json
{"action":"heartbeat","id":123,"claim_token":"<private-token>"}
```

Hủy bài: `action=release` cùng id/token; xem trạng thái: `action=status,id` (chỉ trả metadata chủ sở hữu công khai, không token). Lease hết hạn hoặc đã chuyển chủ thì 409; không được gửi đè hoặc tự cướp quyền để nộp JSON cũ. Revision thay đổi thì tải lại prompt và xử lý như một job mới. Claim/renew/write khóa hàng DB bằng transaction, timestamp vận hành không làm đổi revision biên tập.

## 2. AI viết và tạo minh hoạ

AI phải trả một object JSON trong một block code json, gồm:

```json
{
  "id": 123,
  "language": "vi",
  "title": "Tiêu đề theo đúng chủ đề",
  "excerpt": "Mô tả ngắn cho người đọc.",
  "seo_title": "Tiêu đề SEO",
  "seo_description": "Mô tả SEO",
  "content_html": "<h2>Mục nội dung</h2><p>Nội dung đầy đủ...</p><figure><img src=\"data:image/png;base64,<actual-file-base64>\" alt=\"Mô tả hình minh hoạ thứ nhất\"><figcaption>Minh hoạ ... (ảnh tạo bằng AI).</figcaption></figure><figure><img src=\"https://images.example.org/generated-illustration.webp\" alt=\"Mô tả hình minh hoạ thứ hai\"><figcaption>Minh hoạ ... (ảnh tạo bằng AI).</figcaption></figure>",
  "sources_json": [{"url":"https://www.who.int/health-topics","title":"Tên tài liệu thực sự đã đọc"}]
}
```

Ví dụ trên **chỉ mô tả cấu trúc ảnh trộn hai dạng**, `<actual-file-base64>` và URL tên miền `example.org` không phải ảnh đầu vào thật. Công cụ phải thực sự tạo file ảnh rồi mã hoá bytes file đó hoặc cung cấp URL HTTP(S) công khai trỏ trực tiếp tới file PNG/JPEG/WebP đã tạo. Model văn bản không thể bịa chuỗi BASE64 hoặc URL để thay cho công cụ tạo ảnh. Kết quả hoàn tất phải nằm đầy đủ trong đúng một block code `json` ngay trong chat; file/link “Tải JSON” không thay thế block code.

- Nên 2, tối thiểu 1, tối đa 4 thẻ ảnh; JPEG/PNG/WebP, mỗi ảnh giải mã hoặc tải về ≤5 MiB, tổng ảnh riêng biệt ≤12 MiB, ≤12 MP và ≤8192 px mỗi cạnh. Giới hạn này áp dụng cả ảnh URL, không chỉ độ dài URL trong JSON. Nên xuất 1024–1280 px, không cần 4K. Toàn bộ JSON tối đa **24 MiB**; đây là giới hạn bytes, không phải số ký tự. Với PHP ít RAM, ảnh lớn có thể bị từ chối an toàn sớm hơn.
- `content_html` chỉ là fragment có h2/h3/p/list/figure/figcaption/table đơn giản; không document wrapper/h1/CSS/script/form/iframe. API sanitize lại thay vì tin HTML đầu vào.
- Ảnh phải là ảnh **minh hoạ AI** và ghi rõ như vậy; không giả ảnh lâm sàng/cơ sở/bác sĩ thật, không logo, không khẳng định minh hoạ là bằng chứng điều trị. Mỗi `img src` nhận một trong hai dạng: data URI BASE64 chuẩn đầy đủ, không xuống dòng, MIME khớp file thật; hoặc URL HTTP(S) công khai trả trực tiếp bytes PNG/JPEG/WebP. URL không bắt buộc có đuôi file nếu response thực sự là ảnh raster hợp lệ. Không SVG/GIF, URL trang HTML, sandbox/blob, file cục bộ, URL chỉ mở được sau đăng nhập hay chứa khóa/token riêng tư. Không dùng link tải JSON làm URL ảnh.
- Nếu provider chỉ trả attachment: tiện ích thu **đúng file ảnh do công cụ tạo cho bài này**, mã hoá phía client, thay vào đúng vị trí HTML rồi mới POST. Khi tiện ích xác nhận file ảnh và chỉ định `handbook-image:N`, AI được dùng đúng mã đó ở bước trung gian trong chat; mã phải được thay bằng BASE64 thật **trước gửi API**. Nếu có URL ảnh công khai trực tiếp hợp lệ thì không bắt buộc mã hoá phía client. Không tự tìm ảnh bất kỳ trên giao diện, không gửi fake success hoặc BASE64 mẫu. Placeholder không được đến API nhận.
- `sources_json` cần 1–12 nguồn thật {url,title}. Không token/query riêng tư trong URL. Không chẩn đoán/kê liều/cam kết; bài là thông tin tham khảo. Không bịa nghiên cứu, review hoặc thành tích.
- `title/seo_title` ≤160 ký tự, excerpt ≤1200, seo_description ≤300. `id/language` giữ nguyên; không đổi slug/category/status. API kiểm tra ≥200 ký tự nội dung sau sanitize, nhưng prompt yêu cầu bài đầy đủ chứ không viết để vừa ngưỡng.

## 3. Nhận HTML với BASE64 và/hoặc URL ảnh, lưu file ngay

POST `receive_endpoint` với JSON đầu ra đã gắn ảnh thật cộng **các trường tiện ích giữ ngoài prompt**:

```json
{
  "id": 123,
  "claim_token": "<private-token>",
  "source_revision": "<64-hex-from-claim>",
  "request_id": "stable-uuid-for-this-job",
  "language": "vi",
  "title": "...",
  "excerpt": "...",
  "seo_title": "...",
  "seo_description": "...",
  "content_html": "<actual-complete-html-with-base64-and-or-public-image-urls>",
  "sources_json": [{"url":"https://www.who.int/health-topics","title":"..."}]
}
```

API kiểm tra claim/revision trong transaction **trước khi ghi file**, giải mã BASE64 hoặc tải URL ảnh công khai rồi xác minh từng file bằng magic/dimensions và giải mã raster đầy đủ. Sau đó chọn cách lưu theo **định dạng bytes thực tế**, không tin đuôi URL:

- **WebP hợp lệ có dung lượng `<102400` bytes** (nhỏ hơn 100 KiB, không phải `<=`): ghi nguyên bytes vào `/uploads/handbook/{postId}/ai-{random}.webp`, không nén lại hoặc resize. SHA-256 của bytes file lưu phải bằng SHA-256 file đầu vào. Kích thước gốc có thể lớn hơn 1280px nhưng vẫn phải **≤12MP và ≤8192px mỗi cạnh**.
- **WebP có dung lượng `>=102400` bytes**, kể cả đúng 102400 bytes, và mọi ảnh JPEG/PNG: re-encode JPEG progressive, cạnh dài tối đa 1280px và **dưới 100 KiB/ảnh**, lưu `/uploads/handbook/{postId}/ai-{random}.jpg`.

File nhỏ, file vài byte chỉ có header, MIME giả hoặc WebP thiếu dữ liệu/không giải mã được vẫn bị từ chối; ngưỡng dung lượng **không bỏ qua kiểm tra ảnh thật**. Toàn bộ src, dù đầu vào BASE64 hay URL, được thay bằng URL `.webp`/`.jpg` nội bộ. Không hotlink hoặc worker chờ. Nhánh JPEG không sao chép EXIF không cần thiết; nhánh giữ WebP bảo toàn nguyên file nên không chỉnh metadata bên trong. Alt/caption được giữ; metadata có `ai_generated=true`. Cùng bytes trong một bài dùng chung một file.

### Tải URL ảnh an toàn, không mở cổng SSRF

- Chỉ tải HTTP(S) công khai; không `file:`, `data:` qua downloader, `blob:`, `sandbox:`, mạng loopback/nội bộ/link-local/địa chỉ dành riêng hay endpoint metadata. BASE64 đi theo bộ giải mã riêng, không gọi mạng.
- Phân giải DNS trước khi kết nối, kiểm tra IP đích là công khai và **ghim DNS/IP cho chính kết nối cURL**; không chỉ kiểm tra chuỗi hostname rồi cho cURL tự phân giải lại. HTTPS vẫn xác minh chứng chỉ và hostname. Redirect chỉ được theo khi URL/IP đích vượt qua cùng kiểm tra; không cho redirect bỏ qua kiểm tra IP.
- Không chuyển API key, claim token, cookie hay phiên đăng nhập của tiện ích/hosting sang nguồn ảnh. Ngân sách tải mạng của cả bài là **30 giây**, không làm mới cho mỗi ảnh/hop; mỗi ảnh tối đa **3 redirect**, body **5 MiB** và headers **32 KiB**. Cắt response vượt giới hạn, không tin riêng `Content-Length`, phần mở rộng URL hoặc nhãn MIME. Bytes tải về vẫn phải giải mã được thành PNG/JPEG/WebP thật trước khi nén/lưu.
- Link chết, response HTML/challenge, redirect bị chặn, DNS/IP không an toàn, ảnh hỏng/không đúng MIME hoặc vượt giới hạn đều làm **toàn bộ bài thất bại**, không chấp nhận một phần rồi bỏ các ảnh lỗi. Không ghi nội dung/ảnh vào DB khi chưa kiểm tra đủ các ảnh; file `.webp` và `.jpg` do lượt thất bại tạo sẽ được dọn theo cùng quy tắc rollback, không xóa ảnh từ lượt khác. Người dùng cần sửa nguồn ảnh rồi tạo payload hợp lệ; không đổi payload dưới cùng `request_id` của một kết quả đã lưu.
- Các URL trong `sources_json` là liên kết trích dẫn, không được downloader ảnh tải tự động.

Lưu `posts.content` HTML sạch, ảnh đầu làm `featured_image_url`, excerpt/SEO/title/ngôn ngữ, `template=0`, **status=draft**, xóa claim sau commit. `ai_content_json` chỉ chứa nguồn, URL/alt/caption/dimensions/size, dấu thời gian và fingerprint idempotency; không chứa BASE64, nội dung gốc, API key hay token thô. DB lỗi trước commit thì rollback và chỉ dọn các file chính lượt đó vừa tạo; không xóa ảnh cũ/ảnh bài khác. Nếu COMMIT bị mất phản hồi và chưa chứng minh được rollback, server giữ ảnh để tránh xóa file mà DB có thể đã tham chiếu, ghi log ID bài và để retry idempotent đối chiếu kết quả thực tế. Trường hợp hiếm này có thể cần rà file mồ côi, không tự xóa ảnh khi chưa rõ DB đã commit hay chưa.

Response 200: `ok,type,id,status=draft,idempotent,images_count,images:[{url,width,height,size,mime,alt,caption,ai_generated}],message`. `mime` là định dạng file đã lưu (`image/webp` hoặc `image/jpeg`); kết quả cũ có thể chưa có trường này. Trong `limits`, `stored_max_side=1280` là giới hạn encoder cũ, tương đương `compressed_max_side`; WebP giữ nguyên theo `preserve_webp_below_bytes=102400`, `preserved_webp_max_side=8192` và giới hạn 12MP đầu vào. Retry mất response phải gửi **cùng request_id, token, revision và nguyên payload**. Server nhận diện kết quả đã lưu, trả `idempotent=true`, không ghi DB/tạo ảnh lần nữa kể cả lease đã hết hạn. Đổi payload nhưng tái dùng request_id →409. Bài admin đã sửa/có nội dung/đã xuất bản không bị ghi đè.

Admin mở bài trong Cẩm nang để kiểm tra nội dung và hình rồi xuất bản. Trạng thái draft là chủ ý; nhận JSON thành công không đồng nghĩa đã xuất bản.

## Tiện ích và lỗi

- Source mới `handbook`, nhãn “Viết bài Cẩm nang”; lấy prompt server, dùng claim endpoint riêng. Không nhầm sang luồng doctor/facility.
- Tiến trình: lấy chủ đề → claim → viết/tạo ảnh → gắn BASE64 thật và/hoặc URL ảnh công khai trực tiếp → gửi → server tải/kiểm tra/nén/lưu mọi ảnh nội bộ → nháp đã lưu.
- Không lưu BASE64 trong `chrome.storage.sync`, list item, toast/log/telemetry hoặc hàng đợi nhẹ. Chỉ response URL sau thành công. Nếu phải giữ payload retry, dùng kho phù hợp file/Blob/IndexedDB với giới hạn/cleanup, không dẫn đến lỗi QuotaBytes.
- 401: cấu hình API key. 400: JSON sai; 405: method; 413: body quá lớn; 415: Content-Type sai; 422: HTML/ảnh/MIME/nguồn/trường sai, URL ảnh không an toàn/không truy cập được hoặc không trả file ảnh hợp lệ; 409: `already_claimed`, `lease_lost`, `not_eligible`, `source_revision_changed`, `idempotency_conflict`; 503: schema chưa migrate hoặc server thiếu cURL/GD/khả năng nén/lưu ảnh; 500: lỗi server/DB không lộ nội dung riêng tư. Lỗi nguồn URL không được biến thành thành công thiếu ảnh.
- Chỉ retry network/5xx có kiểm soát, giữ payload/job identity. Không tự gửi lại 409/422 vô hạn. Giữ bản lỗi để đối chiếu không có nghĩa được overwrite.
- Kiểm thử repo: `php tests/handbook_ai_test.php`, `php tests/handbook_media_test.php`, `php tests/handbook_api_test.php`. Fixtures SQLite/tạm, không tác động DB và ảnh hosting thật.

Các file và test không xác nhận cấu hình, migration hoặc deployment production đã được chạy. Cần kiểm tra trên bản hosting được triển khai trước khi cho tiện ích chạy hàng loạt.
