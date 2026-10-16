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

Luồng AI trong trình duyệt dùng **JSON gọn + file BASE64 `.txt` riêng** để tránh chuỗi dài bị sai/cắt khi in trong chat. Đây chỉ là định dạng trung gian giữa AI và tiện ích: `image_files`, mã `link1`… và link tải TXT **không mở rộng định dạng ảnh mà API nhận**. Tiện ích phải đọc/kiểm tra file rồi thay mã ảnh bằng data URI thật, loại `image_files` trước POST. Payload cũ chứa sẵn BASE64 hoặc URL ảnh HTTP(S) công khai trực tiếp vẫn tương thích.

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

Response chứa `ok`, `type=handbook`, `contract_version=1`, `page/limit/total/pages`, `claim_required=true`, `claim_endpoint`, `receive_endpoint`, `lease_seconds=600`, `heartbeat_interval_seconds=45`, `max_body_bytes`, `limits`, `save_status=published`, `items`.

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

AI phải đợi công cụ tạo ảnh và các file TXT hoàn tất, sau đó trả một object JSON gọn trong **đúng một block code `json` ngay trong chat**, gồm:

```json
{
  "id": 123,
  "language": "vi",
  "title": "Tiêu đề theo đúng chủ đề",
  "excerpt": "Mô tả ngắn cho người đọc.",
  "seo_title": "Tiêu đề SEO",
  "seo_description": "Mô tả SEO",
  "content_html": "<h2>Mục nội dung</h2><p>Nội dung đầy đủ...</p><figure><img src=\"link1\" alt=\"Mô tả hình minh hoạ thứ nhất\"><figcaption>Minh hoạ ... (ảnh tạo bằng AI).</figcaption></figure>",
  "image_files": [
    {
      "key": "link1",
      "filename": "link1_base64.txt",
      "mime": "image/webp",
      "bytes": 20392,
      "sha256": "<64-lowercase-hex-of-real-decoded-image-bytes>"
    }
  ],
  "sources_json": [{"url":"https://www.who.int/health-topics","title":"Tên tài liệu thực sự đã đọc"}],
  "suggested_facilities_json": [{"name":"Tên cơ sở đã xác minh","address":"Địa chỉ đầy đủ","city":"Hà Nội","category":"Nha khoa","website":"https://clinic.example","evidence_url":"https://clinic.example/about"}],
  "suggested_doctors_json": [{"name":"Tên bác sĩ đã xác minh","specialty":"Chuyên khoa","facility_name":"Nơi công tác","address":"Địa chỉ nơi khám","city":"Hà Nội","evidence_url":"https://hospital.example/doctors/profile"}]
}
```

Hai mảng gợi ý có thể rỗng, tối đa 8 mục mỗi loại. Chỉ thêm hồ sơ có nguồn công khai đã kiểm tra; URL bằng chứng nằm trong `evidence_url`. Chỉ gửi `facility_id`/`doctor_id` khi đã xác minh đúng ID MedReview. Server chuẩn hoá tên, so tên cùng địa chỉ/chuyên môn/nơi công tác; kết quả từ khoảng 90–95% trở lên có ngữ cảnh khớp được nối vào hồ sơ hiện có. Bản ghi gần giống nhưng mơ hồ không tự chọn. Hồ sơ mới chỉ được tạo khi có bằng chứng và thông tin nhận diện đủ; chúng được đánh dấu chưa xác minh, để nội dung trống và đi vào hàng đợi nghiên cứu hiện có. Kết quả ghép/tạo cùng ID, slug và độ khớp được lưu trong `ai_content_json` và trả về API.

Ví dụ trên **chỉ mô tả cấu trúc**; giá trị `bytes` minh hoạ và placeholder SHA-256 không phải metadata hợp lệ để gửi. Công cụ phải thực sự tạo file PNG/JPEG/WebP, mở kiểm tra ảnh, mã hoá bytes file bằng chương trình rồi ghi **toàn bộ data URI BASE64** vào file UTF-8 `link1_base64.txt`. Tạo `link2_base64.txt`… tương tự khi có nhiều ảnh. Tính `bytes` và SHA-256 trên **bytes ảnh gốc đã giải mã**, không phải bytes file TXT hay chuỗi BASE64. Không bịa BASE64, hash, số bytes hoặc link tải.

Ngay **dưới block JSON**, cung cấp các link tải thật đến `link1_base64.txt`, `link2_base64.txt`… của chính câu trả lời đó. Không in BASE64 dài trong block JSON hoặc trong lời giải thích. Link tải file TXT có thể là attachment của provider (kể cả `sandbox:/mnt/data/...` hiển thị bởi công cụ), nhưng chỉ để tiện ích mở/đọc file; không dùng làm `img src` hay gửi cho server tải. File/link “Tải JSON” không thay thế block JSON trong chat.

### Định dạng file và nối ảnh trong tiện ích

- `image_files` có 1–4 mục, mỗi `key` là `link1`…`link4`, `filename` đúng dạng `{key}_base64.txt`, `mime` là `image/png`, `image/jpeg` hoặc `image/webp`, `bytes` là số nguyên dương và `sha256` là 64 ký tự hex chữ thường. Các mã ảnh trong `content_html` và manifest phải khớp; không có mã thiếu file, mục thừa, khóa hay tên file trùng.
- Mỗi TXT chỉ chứa một data URI chuẩn đầy đủ, ví dụ cấu trúc `data:image/webp;base64,<complete-encoded-file>`, không phải đường dẫn ảnh, JSON, Markdown hoặc một đoạn BASE64 mẫu. Không xuống dòng trong chuỗi BASE64. Không dùng dấu `...`, placeholder hoặc nhờ model chép lại chuỗi dài. BOM UTF-8/khoảng trắng ở hai đầu không phải dữ liệu ảnh.
- Tiện ích chỉ lấy file đính kèm được liên kết với **đúng lượt trả lời hiện tại của bài đang chạy**; không quét ảnh/file bài khác hoặc lấy một file cùng tên ở lịch sử chat. Không đoán URL sandbox/API tải riêng của provider và không chuyển API key hay claim token sang nguồn tải file.
- Sau khi đọc đủ file, tiện ích giải mã BASE64, đối chiếu MIME, số bytes và SHA-256 với manifest, kiểm tra chữ ký file/kích thước và giải mã raster thực tế. Header có kích thước đúng hoặc Base64 giải mã thành bytes chưa đủ chứng minh ảnh hợp lệ. Một file thiếu, hỏng, hash/size sai hoặc không mở được đều chặn việc gửi toàn bộ bài; không thay bằng ảnh khác hoặc bỏ ảnh âm thầm.
- Chỉ sau khi xác minh đủ, tiện ích thay đúng `img src="linkN"` bằng data URI của file tương ứng, **xóa trường `image_files`**, kiểm tra lại payload cuối rồi POST API như mục 3. Manifest/TXT là dữ liệu trung gian, không phải nguồn ảnh mà API tự tải.

- Nên 2, tối thiểu 1, tối đa 4 thẻ ảnh; JPEG/PNG/WebP, mỗi ảnh giải mã hoặc tải về ≤5 MiB, tổng ảnh riêng biệt ≤12 MiB, ≤12 MP và ≤8192 px mỗi cạnh. Giới hạn này áp dụng cả ảnh URL, không chỉ độ dài URL trong JSON. Nên xuất 1024–1280 px, không cần 4K. Toàn bộ JSON tối đa **24 MiB**; đây là giới hạn bytes, không phải số ký tự. Với PHP ít RAM, ảnh lớn có thể bị từ chối an toàn sớm hơn.
- `content_html` chỉ là fragment có h2/h3/p/list/figure/figcaption/table đơn giản; không document wrapper/h1/CSS/script/form/iframe. API sanitize lại thay vì tin HTML đầu vào.
- Ảnh phải là ảnh **minh hoạ AI** và ghi rõ như vậy; không giả ảnh lâm sàng/cơ sở/bác sĩ thật, không logo, không khẳng định minh hoạ là bằng chứng điều trị. Ở payload **cuối gửi API**, mỗi `img src` chỉ nhận data URI BASE64 chuẩn đầy đủ, không xuống dòng, MIME khớp file thật; hoặc URL HTTP(S) công khai trả trực tiếp bytes PNG/JPEG/WebP. URL không bắt buộc có đuôi file nếu response thực sự là ảnh raster hợp lệ. Không SVG/GIF, URL trang HTML, sandbox/blob, file cục bộ, URL chỉ mở được sau đăng nhập hay chứa khóa/token riêng tư. Không dùng link tải JSON/TXT làm URL ảnh. Mã `linkN` chỉ tồn tại ở JSON trung gian trong chat.
- Tương thích luồng cũ: nếu provider chỉ trả attachment ảnh và tiện ích đã xác nhận, gắn đúng file ảnh do công cụ tạo cho bài này, mã trung gian `handbook-image:N` phải được thay bằng BASE64 thật **trước gửi API**. Payload đã có BASE64 thật hoặc URL ảnh công khai trực tiếp hợp lệ vẫn dùng được. Không tự tìm ảnh bất kỳ trên giao diện, không gửi fake success hoặc BASE64 mẫu. Mọi placeholder đều không được đến API nhận.
- `sources_json` cần 1–12 nguồn thật {url,title}. Không token/query riêng tư trong URL. Không chẩn đoán/kê liều/cam kết; bài là thông tin tham khảo. Không bịa nghiên cứu, review hoặc thành tích.
- `title/seo_title` ≤160 ký tự, excerpt ≤1200, seo_description ≤300. `id/language` giữ nguyên; không đổi slug/category/status. API kiểm tra ≥200 ký tự nội dung sau sanitize, nhưng prompt yêu cầu bài đầy đủ chứ không viết để vừa ngưỡng.

## 3. Nhận HTML với BASE64 và/hoặc URL ảnh, lưu file ngay

POST `receive_endpoint` với JSON đầu ra đã gắn ảnh thật cộng **các trường tiện ích giữ ngoài prompt**:

Không gửi `image_files`, mã `linkN`, link `.txt`, `sandbox:` hoặc đường dẫn tải tạm vào API. Server không mở file attachment từ phiên chat thay tiện ích.

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

Lưu `posts.content` HTML sạch, ảnh đầu làm `featured_image_url`, excerpt/SEO/title/ngôn ngữ, `template=0`, **status=published**, xóa claim sau commit. Ảnh, bài và gợi ý hồ sơ cùng lưu trong transaction; lỗi ảnh, đối chiếu hoặc DB sẽ rollback, không xuất bản bài dở dang. `ai_content_json` giữ nguồn, kết quả gợi ý, URL/alt/caption/dimensions/size, dấu thời gian và fingerprint idempotency; không chứa BASE64, nội dung gốc, API key hay token thô. DB lỗi trước commit thì rollback và chỉ dọn các file chính lượt đó vừa tạo; không xóa ảnh cũ/ảnh bài khác. Nếu COMMIT bị mất phản hồi và chưa chứng minh được rollback, server giữ ảnh để tránh xóa file mà DB có thể đã tham chiếu, ghi log ID bài và để retry idempotent đối chiếu kết quả thực tế. Trường hợp hiếm này có thể cần rà file mồ côi, không tự xóa ảnh khi chưa rõ DB đã commit hay chưa.

Response 200: `ok,type,id,status=published,idempotent,images_count,images,suggested_facilities_json,suggested_doctors_json,message`. Mỗi mục gợi ý phản hồi có thêm ID/slug, `resolution=matched|created|unresolved`, `match_score` và lý do nếu còn mơ hồ. `mime` ảnh là định dạng file đã lưu (`image/webp` hoặc `image/jpeg`); kết quả cũ có thể chưa có trường này. Trong `limits`, `stored_max_side=1280` là giới hạn encoder cũ, tương đương `compressed_max_side`; WebP giữ nguyên theo `preserve_webp_below_bytes=102400`, `preserved_webp_max_side=8192` và giới hạn 12MP đầu vào. Retry mất response phải gửi **cùng request_id, token, revision và nguyên payload**. Server nhận diện kết quả đã lưu, trả `idempotent=true`, không ghi DB/tạo ảnh lần nữa kể cả lease đã hết hạn. Đổi payload nhưng tái dùng request_id →409. Bài admin đã sửa/có nội dung/đã xuất bản không bị ghi đè.

API tự xuất bản ngay khi bài, ảnh và dữ liệu gợi ý hợp lệ đã commit thành công. Gợi ý thiếu bằng chứng hoặc mơ hồ được trả `unresolved`; nội dung bài vẫn có thể xuất bản và người quản trị có thể rà soát hồ sơ đó sau.

## Tiện ích và lỗi

- Source mới `handbook`, nhãn “Viết bài Cẩm nang”; lấy prompt server, dùng claim endpoint riêng. Không nhầm sang luồng doctor/facility.
- Tiến trình chính: lấy chủ đề → claim → viết/tạo ảnh thật → công cụ ghi TXT BASE64 và tính metadata → AI trả JSON gọn + link TXT → tiện ích đọc đúng file, kiểm tra bytes/hash/raster, thay mã ảnh và bỏ manifest → gửi → server kiểm tra/nén/lưu mọi ảnh, đối chiếu/tạo hồ sơ gợi ý trong cùng transaction → xuất bản. Tiếp tục heartbeat trong lúc đợi tạo ảnh/file và chuẩn bị payload; không gửi yêu cầu sửa JSON khi provider còn đang tạo ảnh.
- Không lưu BASE64 trong `chrome.storage.sync`, list item, toast/log/telemetry hoặc hàng đợi nhẹ. Chỉ response URL sau thành công. Nếu phải giữ payload retry, dùng kho phù hợp file/Blob/IndexedDB với giới hạn/cleanup, không dẫn đến lỗi QuotaBytes.
- Thiếu TXT, chưa tạo file xong, tải lỗi, manifest không khớp hoặc ảnh không giải mã được: báo lỗi chuẩn bị ảnh và giữ bài để kiểm tra, không báo đã gửi thành công. Yêu cầu AI bổ sung/định dạng lại phải giữ luồng JSON gọn + file thật, không bắt in BASE64 dài trở lại. Tải lại tab/khôi phục tác vụ phải tiếp tục đối chiếu file với đúng lượt phản hồi của bài, không tái sử dụng attachment chưa xác minh.
- 401: cấu hình API key. 400: JSON sai; 405: method; 413: body quá lớn; 415: Content-Type sai; 422: HTML/ảnh/MIME/nguồn/trường sai, URL ảnh không an toàn/không truy cập được hoặc không trả file ảnh hợp lệ; 409: `already_claimed`, `lease_lost`, `not_eligible`, `source_revision_changed`, `idempotency_conflict`; 503: schema chưa migrate hoặc server thiếu cURL/GD/khả năng nén/lưu ảnh; 500: lỗi server/DB không lộ nội dung riêng tư. Lỗi nguồn URL không được biến thành thành công thiếu ảnh.
- Chỉ retry network/5xx có kiểm soát, giữ payload/job identity. Không tự gửi lại 409/422 vô hạn. Giữ bản lỗi để đối chiếu không có nghĩa được overwrite.
- Kiểm thử repo: `php tests/handbook_ai_test.php`, `php tests/handbook_media_test.php`, `php tests/handbook_api_test.php`. Fixtures SQLite/tạm, không tác động DB và ảnh hosting thật.

Các file và test không xác nhận cấu hình, migration hoặc deployment production đã được chạy. Cần kiểm tra trên bản hosting được triển khai trước khi cho tiện ích chạy hàng loạt.
