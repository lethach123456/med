# API bài review phân tích cơ sở y tế

Đây là **bài viết biên tập tổng hợp**, khác với từng phản hồi bệnh nhân trong `medical_reviews`. Bảng `medical_review_articles` hỗ trợ liên kết cơ sở hoặc bác sĩ; giai đoạn này API chỉ nhận `entity_type: "facility"`. Không tạo đánh giá bệnh nhân, không sửa điểm sao, số review, thông tin/ảnh cơ sở hoặc nhãn xác thực.

## Xác thực và giới hạn

Gửi `X-Medical-Api-Key` theo cấu hình đang dùng trên website. Không đặt khóa trong prompt AI, URL, ảnh chụp hoặc JSON bài viết. Các yêu cầu POST có `Content-Type: application/json`. Cổng nhận bài tối đa 2 MiB, cổng claim tối đa 64 KiB. Không nhận Base64/ảnh trong bài review; giao diện sử dụng ảnh công khai của hồ sơ cơ sở hiện có.

Migration chạy riêng, không tự chạy khi gọi API:

```sh
php scripts/migrate_review_articles.php --check
php scripts/migrate_review_articles.php --apply
```

## 1. Lấy hàng đợi và prompt

`GET /api/medical/review-articles-needing-content.php?language=vi&page=1&limit=10`

Bộ lọc tùy chọn: `language=vi|en`, `id=393`, hoặc `ids=393,394` (tối đa 25 ID), `limit=1..25`. ID là **ID cơ sở**, không phải ID bài review. GET chỉ đọc, không tự tạo bản nháp hoặc nhận quyền viết.

Điều kiện:

- Cơ sở đang xuất bản, đúng ngôn ngữ.
- Hơn 40 đánh giá từ nguồn tổng hợp hợp lệ đã phân loại **hoặc** hơn 40 bản ghi đánh giá đã xuất bản trong hệ thống. Không dùng bộ đếm legacy chưa rõ nguồn.
- `facility_insights_json` hợp lệ và có nội dung phân tích, không chỉ `{}`/thông tin phiên bản.
- Chưa có bài viết có nội dung hoặc bài đã xuất bản; chưa có tác vụ khác giữ quyền còn hạn.

Mỗi item trả `entity_id`, `language_code`, `source_revision`, `source`, `prompt`, `output_template`; hỗ trợ prompt `review_article` tùy biến trong admin theo danh mục/ngôn ngữ.

Nguồn AI gồm dữ liệu kinh doanh công khai, số đánh giá được chuẩn hóa, mẫu phản hồi đã bỏ tác giả/che thông tin liên hệ và phân tích `facility_insights_json`. Không gửi `full_json`, ảnh/ghi chú bệnh nhân, token, claim hoặc ghi chú riêng. Việc che thông tin không bảo đảm văn bản tự do hoàn toàn vô danh; prompt chỉ cho phép tổng hợp chủ đề, không lặp lại chi tiết nhận diện cá nhân.

`review_metrics` là tổng hợp nguồn; `stored_review_metrics` là bản ghi hệ thống; `sample_count` là số mẫu thực sự được đọc (tối đa 40), **không phải tổng đánh giá nguồn**. Nếu insights chỉ là báo cáo rà soát ảnh, AI phải nêu hạn chế, không suy ra chất lượng chuyên môn.

## 2. Nhận quyền viết

`POST /api/medical/review-articles-needing-content.php`

```json
{
  "action": "claim",
  "entity_type": "facility",
  "entity_id": 393,
  "language_code": "vi",
  "source_revision": "SHA256_64_KY_TU_TU_GET",
  "request_id": "unique-operation-393-20261022",
  "client": {
    "instance_id": "extension-installation-id",
    "worker_id": "tab-worker-id",
    "provider": "ChatGPT",
    "model": "selected-model"
  }
}
```

Lưu `claim_token` ngay trong tiện ích, **không đưa vào prompt**. DB chỉ giữ hash của token. Claim tạo bản nháp trống một lần, khóa theo cơ sở + ngôn ngữ; không thay đổi hồ sơ nguồn. Giữ quyền 900 giây, heartbeat mỗi 60 giây. Không dùng `request_id` của tác vụ cũ cho dữ liệu khác.

Các thao tác cùng endpoint:

```json
{"action":"heartbeat","entity_id":393,"language_code":"vi","claim_token":"TOKEN_DA_NHAN"}
```

`release` cùng dạng để trả quyền. `status` chỉ cần entity/ngôn ngữ; trả trạng thái và thông tin worker công khai, không trả token/hash. Gửi lại `claim` đang còn hạn chỉ được cùng `instance_id`, `worker_id`, `request_id` và **token đã giữ**; máy/tab khác không chiếm quyền. Nếu lần trả token đầu tiên bị mất, chờ hết hạn rồi claim lại, không đoán hoặc lấy token của tab khác.

Nếu thông tin cơ sở, đánh giá hoặc insights thay đổi, heartbeat/POST báo `source_revision_changed`; GET lại nguồn, nghiên cứu lại và dùng lượt claim mới. Quyền hết hạn báo `lease_lost`, không tự ghi đè kết quả tác vụ khác.

## 3. Nhận JSON bài viết

`POST /api/medical/review-article-content-update.php`

AI trả một object trong một khối mã `json`. Tiện ích ghép `request_id`/`claim_token` đã giữ vào JSON trước khi gửi. Các trường:

```json
{
  "entity_type": "facility",
  "entity_id": 393,
  "language_code": "vi",
  "source_revision": "SHA256_64_KY_TU_TU_GET",
  "claim_token": "TOKEN_DA_NHAN",
  "request_id": "unique-operation-393-20261022",
  "status": "draft",
  "title": "Review tên cơ sở: dịch vụ, tín hiệu đánh giá và điều cần cân nhắc",
  "excerpt": "Tóm tắt trung lập dựa trên dữ liệu công khai hiện có.",
  "verdict": "Kết luận cân bằng, không bảo đảm kết quả điều trị.",
  "content_html": "<h2>Tổng quan</h2><p>Toàn bộ nội dung bài viết thật, ít nhất 300 ký tự văn bản sau khi làm sạch HTML.</p>",
  "strengths_json": ["Điểm tích cực có cơ sở đối chiếu"],
  "considerations_json": ["Điều người đọc cần xác nhận trước khi đặt lịch"],
  "suitable_for_json": ["Nhu cầu có thể tìm hiểu thêm tại cơ sở"],
  "analysis_sections_json": [],
  "sources_json": [{"id":"s1","title":"Giữ nguồn từ danh mục GET","url":"https://medreview.vn/co-so-y-te/slug-that","platform":"MedReview"}],
  "evidence_limitations_json": ["Số phản hồi mẫu ít hơn tổng số đánh giá; không xác minh độc lập kết quả điều trị."],
  "seo_title": "Tiêu đề SEO",
  "seo_description": "Mô tả SEO trung lập",
  "researched_at": "2026-10-22"
}
```

Mẫu trên chỉ mô tả cấu trúc, không phải payload thử hợp lệ: lấy ID, revision, token, URL thực tế và nội dung đầy đủ từ tác vụ thật. `slug` tùy chọn được chuẩn hóa từ tiêu đề nếu bỏ trống. Nội dung HTML được làm sạch, không script/CSS/form/embed/ảnh. `sources_json` phải có ít nhất một nguồn, **giữ nguyên cặp ID/URL trong danh mục nguồn GET**; không tự bịa hoặc thêm URL ngoài dữ liệu. Nguồn tham chiếu không đồng nghĩa xác minh độc lập.

`analysis_sections_json` tùy chọn có dạng:

```json
[{"id":"co-so-vat-chat","title":"Cơ sở vật chất","content_html":"<p>Phân tích có bằng chứng.</p>","source_ids":["s1"]}]
```

Nếu có sections, chúng bao phủ cùng nội dung bài đầy đủ trong `content_html`; giao diện hiển thị một phiên bản, không lặp hai bài. Mỗi section cần nguồn tồn tại. Các danh sách nhận định tối đa 30 mục, nguồn tối đa 100, sections tối đa 20. `evidence_limitations_json` bắt buộc không trống. API không nhận các trường sửa dữ liệu cơ sở, đánh giá, bệnh nhân hoặc trạng thái xác thực.

Mặc định lưu **draft** để biên tập viên duyệt trong admin. Chỉ khi chủ API chủ động gửi `status:"published"` mới xuất bản; không coi AI tự viết là đã được duyệt. API kiểm tra quyền + revision, lưu toàn bộ bài viết và snapshot nguồn riêng trong giao dịch, đọc lại các cột để xác nhận trước khi trả `saved:true, storage_verified:true`. Đây là xác nhận lưu DB, không phải xác nhận chất lượng y khoa.

Gửi lại đúng request/token/payload trả `idempotent:true`, không tạo bài mới. Dữ liệu khác dùng lại request ID báo 409; bản thảo đã được biên tập sau lần gửi đầu không bị ghi đè. Khi đã có nội dung, API không dùng tác vụ mới để ghi đè bài; chỉnh sửa trong admin.

## Mã lỗi cần xử lý

| HTTP | reason | Xử lý |
| --- | --- | --- |
| 401 | Khóa không hợp lệ | Kiểm tra khóa API, không sửa prompt để vượt xác thực. |
| 409 | already_claimed | Worker khác đang giữ bài; không tự chiếm. |
| 409 | lease_lost | Kiểm tra trạng thái, nhận quyền lại rồi đối chiếu nguồn. |
| 409 | source_revision_changed | Lấy và nghiên cứu nguồn mới. |
| 409 | already_written / article_changed | Bài đã có nội dung/đã được biên tập; không gửi đè. |
| 409 | idempotency_conflict | Không tái sử dụng request ID với dữ liệu khác. |
| 422 | unknown_source / invalid_payload | Sửa JSON/nguồn theo danh mục và hợp đồng. |
| 503 | schema_unavailable | Chạy migration riêng trên đúng DB; tiện ích không tự migration. |

API này không gọi dịch vụ AI hoặc quét mạng xã hội thay người dùng. Nó cung cấp nguồn/prompt, giữ quyền viết và nhận kết quả để biên tập/xuất bản bài phân tích.
