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

2. PHP cần GD (JPEG/PNG/WebP), DOM/libxml và mbstring; upload tree `uploads/handbook` phải cho PHP ghi. Ảnh là dữ liệu CMS runtime được Git ignore, cần backup cùng DB/uploads trên hosting, không trông chờ Git để khôi phục ảnh. Nên đặt `memory_limit=256M` trở lên và giới hạn request/proxy phù hợp 24 MiB JSON (ví dụ `post_max_size=32M`). Đây là khuyến nghị cấu hình, code **không tự thay cấu hình hosting**. `upload_max_filesize` không điều khiển JSON BASE64. PHP/server/WAF vẫn có thể chặn trước khi request vào code; không tắt bảo vệ bot toàn website để xử lý.

3. Tạo chủ đề trong Admin → Cẩm nang → **Thêm bài**: chọn **Ngôn ngữ** Việt/Anh ngay trong form; tùy chọn **Bài tiếng Anh/Việt liên kết** để nối với bản dịch đã có. Nhập tiêu đề, slug hợp lệ (editor tự sinh khi để trống), tùy chọn mô tả làm brief; để **draft**, **content rỗng**. Editor Cẩm nang không dùng template. Nếu thiếu chuyên mục `blog`/`blog-en`, **chỉ sau khi bấm Lưu và xác thực CSRF hợp lệ**, chuyên mục ngôn ngữ được chọn mới được tạo cùng bài trong một transaction; mở danh sách/form không ghi DB. Bài có nội dung, bài xuất bản, trang dịch vụ và ngôn ngữ khác không nằm trong hàng đợi.

   Trong form có **Liên kết bản dịch**: chọn một bài ngôn ngữ còn lại rồi Lưu. Một bài Việt chỉ ghép với một bài Anh; không đoán theo tên/slug, không lấy bài đã ghép với bài khác. Ghép/thay/gỡ liên kết cùng transaction lưu bài. Chỉ khi cả hai bài có nội dung và đã xuất bản, menu ngôn ngữ mới mở đúng bài tương ứng và có hreflang hai chiều; bản nháp không bị lộ. Chưa migrate bảng ghép cặp vẫn thêm/lưu bài thường được, riêng phần liên kết có hướng dẫn rõ.

4. Prompt có thể sửa ở Admin → Prompt AI → Viết bài Cẩm nang sau migration. Prompt mặc định có sẵn trong code để fallback read-only; quy tắc vận chuyển hiện hành luôn được bổ sung cho mẫu cũ. Không cần tạo key/token riêng cho Cẩm nang.

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
  "content_html": "<h2>Mục nội dung</h2><p>Nội dung đầy đủ...</p><figure><img src=\"data:image/png;base64,<actual-file-base64>\" alt=\"Mô tả hình minh hoạ\"><figcaption>Minh hoạ ... (ảnh tạo bằng AI).</figcaption></figure>",
  "sources_json": [{"url":"https://www.who.int/health-topics","title":"Tên tài liệu thực sự đã đọc"}]
}
```

Ví dụ trên **chỉ mô tả cấu trúc**, `<actual-file-base64>` không phải payload hợp lệ. Công cụ phải thực sự tạo file ảnh rồi mã hoá bytes file đó. Model văn bản không thể bịa chuỗi BASE64 để thay cho công cụ tạo ảnh.

- Nên 2, tối thiểu 1, tối đa 4 thẻ ảnh; JPEG/PNG/WebP, mỗi ảnh decoded ≤5 MiB, tổng ảnh riêng biệt ≤12 MiB, ≤12 MP và ≤8192 px mỗi cạnh. Nên xuất 1024–1280 px, không cần 4K. Toàn bộ JSON tối đa **24 MiB**; đây là giới hạn bytes, không phải số ký tự. Với PHP ít RAM, ảnh lớn có thể bị từ chối an toàn sớm hơn.
- `content_html` chỉ là fragment có h2/h3/p/list/figure/figcaption/table đơn giản; không document wrapper/h1/CSS/script/form/iframe. API sanitize lại thay vì tin HTML đầu vào.
- Ảnh phải là ảnh **minh hoạ AI** và ghi rõ như vậy; không giả ảnh lâm sàng/cơ sở/bác sĩ thật, không logo, không khẳng định minh hoạ là bằng chứng điều trị. BASE64 chuẩn không rút gọn, không xuống dòng trong data URI, MIME phải khớp file thật. Không SVG/GIF/remote URL/blob.
- Nếu provider trả attachment thay vì chuỗi: tiện ích thu **đúng file ảnh do công cụ tạo cho bài này**, mã hoá phía client, thay vào đúng vị trí HTML rồi mới POST. Không tự tìm ảnh bất kỳ trên giao diện. Nếu không lấy được bytes, báo `missing_images`/khả năng provider cho người dùng; không gửi fake success hoặc BASE64 mẫu. Placeholder không được đến API nhận.
- `sources_json` cần 1–12 nguồn thật {url,title}. Không token/query riêng tư trong URL. Không chẩn đoán/kê liều/cam kết; bài là thông tin tham khảo. Không bịa nghiên cứu, review hoặc thành tích.
- `title/seo_title` ≤160 ký tự, excerpt ≤1200, seo_description ≤300. `id/language` giữ nguyên; không đổi slug/category/status. API kiểm tra ≥200 ký tự nội dung sau sanitize, nhưng prompt yêu cầu bài đầy đủ chứ không viết để vừa ngưỡng.

## 3. Nhận HTML và BASE64, lưu file ngay

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
  "content_html": "<actual-complete-html-with-real-base64-images>",
  "sources_json": [{"url":"https://www.who.int/health-topics","title":"..."}]
}
```

API kiểm tra claim/revision trong transaction **trước khi ghi file**, xác minh từng file bằng magic/dimensions, re-encode JPEG progressive cạnh dài tối đa 1280px và **dưới 100 KiB/ảnh**, lưu `/uploads/handbook/{postId}/ai-{random}.jpg`, thay toàn bộ src BASE64 thành URL nội bộ. Không fetch URL ảnh, không giữ file gốc, không worker chờ, không EXIF cần thiết được sao chép. Alt/caption được giữ; metadata có `ai_generated=true`. Cùng bytes trong một bài dùng chung một file.

Lưu `posts.content` HTML sạch, ảnh đầu làm `featured_image_url`, excerpt/SEO/title/ngôn ngữ, `template=0`, **status=draft**, xóa claim sau commit. `ai_content_json` chỉ chứa nguồn, URL/alt/caption/dimensions/size, dấu thời gian và fingerprint idempotency; không chứa BASE64, nội dung gốc, API key hay token thô. DB lỗi trước commit thì rollback và chỉ dọn các file chính lượt đó vừa tạo; không xóa ảnh cũ/ảnh bài khác. Nếu COMMIT bị mất phản hồi và chưa chứng minh được rollback, server giữ ảnh để tránh xóa file mà DB có thể đã tham chiếu, ghi log ID bài và để retry idempotent đối chiếu kết quả thực tế. Trường hợp hiếm này có thể cần rà file mồ côi, không tự xóa ảnh khi chưa rõ DB đã commit hay chưa.

Response 200: `ok,type,id,status=draft,idempotent,images_count,images:[{url,width,height,size,alt,caption,ai_generated}],message`. Retry mất response phải gửi **cùng request_id, token, revision và nguyên payload**. Server nhận diện kết quả đã lưu, trả `idempotent=true`, không ghi DB/tạo ảnh lần nữa kể cả lease đã hết hạn. Đổi payload nhưng tái dùng request_id →409. Bài admin đã sửa/có nội dung/đã xuất bản không bị ghi đè.

Admin mở bài trong Cẩm nang để kiểm tra nội dung và hình rồi xuất bản. Trạng thái draft là chủ ý; nhận JSON thành công không đồng nghĩa đã xuất bản.

## Tiện ích và lỗi

- Source mới `handbook`, nhãn “Viết bài Cẩm nang”; lấy prompt server, dùng claim endpoint riêng. Không nhầm sang luồng doctor/facility.
- Tiến trình: lấy chủ đề → claim → viết/tạo ảnh → nhận bytes/mã hoá/gắn ảnh → gửi → nháp đã lưu.
- Không lưu BASE64 trong `chrome.storage.sync`, list item, toast/log/telemetry hoặc hàng đợi nhẹ. Chỉ response URL sau thành công. Nếu phải giữ payload retry, dùng kho phù hợp file/Blob/IndexedDB với giới hạn/cleanup, không dẫn đến lỗi QuotaBytes.
- 401: cấu hình API key. 400: JSON sai; 405: method; 413: body quá lớn; 415: Content-Type sai; 422: HTML/ảnh/MIME/nguồn/trường sai; 409: `already_claimed`, `lease_lost`, `not_eligible`, `source_revision_changed`, `idempotency_conflict`; 503: schema chưa migrate hoặc server chưa đủ khả năng nén/lưu ảnh; 500: lỗi server/DB không lộ nội dung riêng tư.
- Chỉ retry network/5xx có kiểm soát, giữ payload/job identity. Không tự gửi lại 409/422 vô hạn. Giữ bản lỗi để đối chiếu không có nghĩa được overwrite.
- Kiểm thử repo: `php tests/handbook_ai_test.php`, `php tests/handbook_media_test.php`, `php tests/handbook_api_test.php`. Fixtures SQLite/tạm, không tác động DB và ảnh hosting thật.

Các file và test không xác nhận cấu hình, migration hoặc deployment production đã được chạy. Cần kiểm tra trên bản hosting được triển khai trước khi cho tiện ích chạy hàng loạt.
