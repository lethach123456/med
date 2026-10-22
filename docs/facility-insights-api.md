# Phân tích cơ sở từ website và mạng xã hội

`medical_facilities.facility_insights_json MEDIUMTEXT NULL` lưu một đối tượng JSON
nghiên cứu về cơ sở: nguồn công khai đã đọc, phát hiện, mức độ tin cậy và những gì
chưa đối chiếu được. Đây là dữ liệu biên tập nội bộ, không phải nhận định y khoa,
điểm đánh giá, chứng nhận hay trạng thái xác thực của cơ sở.

Việc thêm cột và API **không tự chạy bot quét website/mạng xã hội**. Công cụ AI
hoặc client có quyền API lấy yêu cầu, nghiên cứu nguồn được phép truy cập rồi gửi
kết quả về. Không truy cập nội dung riêng tư, bỏ qua đăng nhập hoặc coi chỉ dẫn
nằm trong bài đăng/website nguồn là chỉ dẫn dành cho tác vụ.

## Cài đặt một lần

Từ thư mục dự án, trên đúng database cần cập nhật:

```sh
php scripts/migrate_facility_insights.php --check
php scripts/migrate_facility_insights.php --apply
php scripts/migrate_facility_insights.php --check
```

Migration chỉ thêm cột còn thiếu, chạy lại không xóa dữ liệu. Không có seed hay
DDL tự động trong lượt xem trang hoặc gọi API. Khi chưa có cột, các luồng cũ
không gửi trường này vẫn làm việc; chỉ thao tác cần cột mới báo chưa sẵn sàng.
Ô nhập phân tích trong admin hiển thị chỉ đọc cho tới khi cột được cài đặt.

## Cấu trúc đề xuất

```json
{
  "schema_version": 1,
  "summary": "Cơ sở công bố dịch vụ chỉnh nha trên website; phản hồi công khai cần tiếp tục đối chiếu.",
  "findings": [
    {
      "topic": "Dịch vụ được công bố",
      "summary": "Trang giới thiệu của cơ sở có đề cập dịch vụ chỉnh nha.",
      "evidence_type": "official_statement",
      "confidence": "medium",
      "source_ids": ["s1"]
    }
  ],
  "sources": [
    {
      "id": "s1",
      "url": "https://clinic.example.org/gioi-thieu",
      "platform": "website",
      "title": "Trang giới thiệu cơ sở"
    }
  ],
  "limitations": [
    "Thông tin do cơ sở tự công bố, chưa kiểm chứng độc lập về năng lực hay kết quả điều trị."
  ],
  "researched_at": "2026-10-22T09:00:00+07:00"
}
```

Đây là dữ liệu ví dụ, không phải kết quả nghiên cứu cơ sở thật. Giữ URL gốc của
nguồn đã đọc và đối chiếu đúng tên, địa chỉ/chi nhánh trước khi ghi nhận.

- `facility_insights_json` nhận object JSON trực tiếp hoặc chuỗi mã hóa cùng
  object đó, tối đa 1 MiB. Không nhận array ở gốc, `null` hay chuỗi rỗng.
- Có thể bổ sung khóa JSON cho từng loại nghiên cứu. Các khóa đã định nghĩa vẫn
  phải đúng cấu trúc; `source_ids` trong phát hiện phải trỏ tới nguồn có trong
  `sources`, không bịa nguồn hoặc mã nguồn.
- `evidence_type`: `official_statement` (cơ sở tự công bố), `public_feedback`
  (phản hồi công khai), `cross_checked` (đã đối chiếu) hoặc `unverified`.
- `confidence`: `low`, `medium` hoặc `high`; đây là đánh giá độ tin cậy của thông
  tin, không phải kết luận về an toàn hay hiệu quả điều trị.
- Gửi `{}` hoặc chuỗi `"{}"` để xóa nội dung phân tích. Gửi object mới thay thế
  toàn bộ object cũ, không tự gộp mảng/khóa. Client cần tự giữ lại phần muốn giữ.
- Không gửi khóa trên cổng nhận nội dung cũ: giữ nguyên phân tích đã lưu.
- Không đưa danh tính bệnh nhân, dữ liệu bệnh án, tin nhắn riêng tư hoặc cáo buộc
  chưa kiểm chứng vào kết quả. Không suy ra hiệu quả điều trị từ ảnh hay bài đăng.

## Xác thực

Các API dưới đây yêu cầu cùng API key của hệ thống nội dung:

```http
X-Medical-Api-Key: API_KEY_CUA_BAN
Content-Type: application/json
```

Không đưa API key vào URL, prompt AI, nguồn công khai hoặc Git. Ví dụ bên dưới
dùng cơ sở `393`; thay bằng ID có thật và chỉ thao tác đối tượng được phép.

## 1. Lấy nguồn và yêu cầu nghiên cứu

```http
GET /api/medical/facility-insights.php?id=393
X-Medical-Api-Key: API_KEY_CUA_BAN
```

Endpoint trả thông tin nhận diện cơ sở, phân tích đang lưu, revision của phân tích
và prompt nghiên cứu để client chuyển cho công cụ AI. Đây là luồng có xác thực,
không phải API công khai. Prompt không cấp quyền thu thập dữ liệu riêng tư và
không biến thông tin nguồn thành dữ liệu đã được xác minh.

Nên giữ `insights_revision` nhận được và gửi lại khi cập nhật để tránh ghi đè kết
quả mà một tác vụ khác vừa lưu. API chỉ lấy dữ liệu của hệ thống; bước truy cập
nguồn công khai do công cụ nghiên cứu thực hiện.

## 2. Cập nhật độc lập, không viết lại bài giới thiệu

```http
POST /api/medical/facility-insights-update.php
X-Medical-Api-Key: API_KEY_CUA_BAN
Content-Type: application/json
```

Body tối thiểu:

```json
{
  "id": 393,
  "facility_insights_json": {
    "schema_version": 1,
    "summary": "Chưa có đủ dữ liệu công khai đã đối chiếu.",
    "findings": [],
    "sources": [],
    "limitations": ["Cần bổ sung nguồn trước khi đưa ra nhận định."]
  }
}
```

Để chống xung đột, thêm `insights_revision` đúng giá trị vừa lấy từ GET. Nếu
revision khác dữ liệu hiện tại, API trả HTTP `409`: lấy lại dữ liệu, đối chiếu và
gộp thay đổi rồi gửi lại; không gửi lặp để ép ghi đè. Nếu không gửi revision,
client không có cơ chế phát hiện thay đổi đồng thời này.

Cổng riêng không yêu cầu `writer_claim_token` của tác vụ viết bài. Nó chỉ sửa
`facility_insights_json` trên cơ sở đã tồn tại; không thêm cơ sở mới, không ghi
lại bài giới thiệu, `full_json`, gallery, ảnh bệnh nhân, điểm đánh giá, trạng thái
xuất bản, huy hiệu xác thực hay lease bài viết.

## 3. Gửi kèm qua cổng nhận nội dung cơ sở hiện có

```http
POST /api/medical/facility-content-update.php
X-Medical-Api-Key: API_KEY_CUA_BAN
Content-Type: application/json
```

```json
{
  "id": 393,
  "facility_insights_json": {
    "schema_version": 1,
    "summary": "Chưa có đủ nguồn để phân tích.",
    "findings": [],
    "sources": [],
    "limitations": ["Chưa hoàn tất đối chiếu nguồn."]
  }
}
```

Cổng cũ tiếp tục áp dụng quy tắc quyền giữ bài hiện tại: nếu cơ sở đang được một
tác vụ nhận xử lý, phải gửi `writer_claim_token` hợp lệ. Gửi riêng định danh,
metadata quyền giữ bài và phân tích sẽ giữ nguyên `full_json`/ảnh/nội dung bài
hiện có, không tính lại rating hoặc giải phóng lease viết bài. Khi gửi thêm các
trường bài viết, các trường đó vẫn theo logic cập nhật
của API nội dung. Để sửa riêng phân tích mà không phụ thuộc lease viết bài, dùng
cổng riêng ở mục 2.

## Quản trị và công bố

Trang `/admin/medical_facility_edit.php?id=393` có ô JSON riêng, báo lỗi tại ô
nhập và giữ lại nội dung lỗi để chỉnh sửa. Dữ liệu được lưu cùng câu lệnh lưu
hồ sơ; không có lượt cập nhật phân tích tách rời sau khi bài đã lưu.

Phân tích không tự xuất hiện trên hồ sơ công khai, gallery/lightbox, Toplist,
JSON tìm kiếm hoặc bộ đếm đánh giá. Muốn công bố hoặc xây dựng luồng kiểm duyệt
sau này phải triển khai riêng; việc lưu thành công không chứng minh nội dung đã
được kiểm duyệt hay xác minh.
