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

Bộ lọc tùy chọn: `language=vi|en`, `id=393`, hoặc `ids=393,394` (tối đa 25 ID), `limit=1..25`, `include_existing=0|1`. ID là **ID cơ sở**, không phải ID bài review. GET chỉ đọc, không tự tạo bản nháp hoặc nhận quyền viết.

Hàng đợi không lọc ID mặc định chỉ lấy bài chưa có nội dung, tránh viết lại liên
tục. Khi chỉ định `id`/`ids`, mặc định lấy cả bài đã có nội dung để cập nhật;
có thể gửi `include_existing=0` để chỉ lấy bài chưa viết. Muốn lấy cả bài cũ
trong một lô không lọc ID, dùng `include_existing=1` một cách chủ động.

Điều kiện:

- Cơ sở đang xuất bản, đúng ngôn ngữ.
- Hơn 40 đánh giá từ nguồn tổng hợp hợp lệ đã phân loại **hoặc** hơn 40 bản ghi đánh giá đã xuất bản trong hệ thống. Không dùng bộ đếm legacy chưa rõ nguồn.
- `facility_insights_json` hợp lệ và có nội dung phân tích, không chỉ `{}`/thông tin phiên bản.
- Chưa có tác vụ khác giữ quyền còn hạn. Điều kiện đã có nội dung chỉ áp dụng
  cho hàng đợi mặc định, không chặn lượt cập nhật được chọn chủ động.

Mỗi item trả `entity_id`, `language_code`, `source_revision`, `source`, `evidence_catalog`, `prompt`, `output_template`; hỗ trợ prompt `review_article` tùy biến trong admin theo danh mục/ngôn ngữ. Prompt tùy biến có thể đặt `{{evidence_catalog}}`; nếu bỏ, API vẫn bổ sung danh mục và quy tắc đối chiếu ở cuối.

Thông tin cập nhật gồm `article_id`, `article_status`, `has_content` trong item;
response có `updates_supported:true` và `save_status:"published"`.

Nguồn AI gồm dữ liệu kinh doanh công khai, số đánh giá được chuẩn hóa, mẫu phản hồi đã bỏ tác giả/che thông tin liên hệ và phân tích `facility_insights_json`. Không gửi `full_json`, ảnh/ghi chú bệnh nhân, token, claim hoặc ghi chú riêng. Việc che thông tin không bảo đảm văn bản tự do hoàn toàn vô danh; prompt chỉ cho phép tổng hợp chủ đề, không lặp lại chi tiết nhận diện cá nhân.

`review_metrics` là tổng hợp nguồn; `stored_review_metrics` là bản ghi hệ thống; `sample_count` là số mẫu thực sự được cung cấp (tối đa 40), **không phải tổng đánh giá nguồn**. Đây là dữ liệu nội bộ để chọn và đối chiếu nguồn, không phải các con số cần đưa vào bài. Nếu insights chỉ là báo cáo rà soát ảnh, không suy ra chất lượng chuyên môn hoặc ý kiến khách hàng từ ảnh.

### Đối chiếu đầy đủ dữ liệu cơ sở

`facility.content` là khung nội dung, không phải bản gốc luôn đúng. AI cần đối chiếu
với các trường cụ thể cùng chủ đề để bổ sung hoặc hiệu chỉnh chi tiết đã cũ:

| Chủ đề | Các trường nguồn chính |
| --- | --- |
| Giá dịch vụ | `price_table_html`, `price_text`, giá/đơn vị trong `services_json`, `price_source_scope`. |
| Máy móc, không gian | `equipment_mentioned_json`, `utilities_json` và nội dung quét liên quan. |
| Địa chỉ, vị trí | `address_text`, `city`, `google_maps_url`, `nearby_landmarks`, `parking_info`; tọa độ hợp lệ chỉ đối chiếu nội bộ. |
| Dịch vụ, nhân sự | `featured_services_json`, `services_json`, `doctors_json`, nội dung hồ sơ và kết quả quét đúng chủ đề. |
| Thông tin thực tế | `hours_text`, `booking_url`, bảo hiểm, thanh toán, ngôn ngữ, bảo hành và `branch_count` khi có. |

`facility_insights_json` là dữ liệu quét được ưu tiên cao nhất khi nội dung có
thật, liên quan đúng mục, có căn cứ và thời điểm phù hợp. Ví dụ: quét máy móc
bổ sung phần thiết bị; quét bảng giá bổ sung giá và điều kiện áp dụng. Không
dùng một summary chỉ rà soát ảnh để suy ra giá, địa chỉ mới, nhân sự hoặc pháp
lý; thiếu mục nào trong insights thì vẫn khai thác các trường hồ sơ tương ứng.
Nếu nguồn không thống nhất, chọn thông tin cụ thể, đúng chi nhánh, có căn cứ và
mốc thời gian phù hợp; không tự hợp nhất các dữ kiện mâu thuẫn.

Bảng giá nguồn được làm sạch nội dung chủ động, giữ quan hệ hàng/ô và phạm vi
giá. Không lấy giá “từ”, giá ưu đãi hay giá tham khảo toàn hệ thống làm tổng phí
cho mọi trường hợp tại một chi nhánh. Bài có thể dùng mức giá thực khi rõ dịch
vụ, đơn vị và phạm vi; không tự gán nhãn “rẻ/đắt” bằng suy đoán.

Các cột này đã có trong bảng cơ sở, không cần migration mới. Nguồn chỉ lấy dữ
liệu công khai theo whitelist; `features_json` legacy chứa thông điệp về kiểm
duyệt/xác thực của nền tảng nên không được coi là đặc điểm cơ sở. Không đưa
`full_json`, ảnh hay thông tin nhận dạng bệnh nhân vào prompt.

Thay đổi bảng giá hoặc thông tin vị trí làm đổi `source_revision` như các dữ
kiện nguồn khác; tác vụ đang giữ nguồn cũ cần tải lại và nhận quyền theo nguồn mới.

### Đối chiếu bằng chứng trước khi viết

Prompt hướng dẫn AI làm **hai bước trong cùng một lượt viết**, không phải hai
lần gọi API hoặc hai bài trả về:

1. Đọc `source.facility.content` làm nền, bổ sung bằng các trường hồ sơ, từng phản hồi thực tế và insights; lập các nhận định ngắn
   có tham chiếu. Phân biệt thông tin cơ sở công bố, phản hồi đơn lẻ, chủ đề
   xuất hiện trong nhiều phản hồi và điều chưa thống nhất giữa các nguồn.
2. Viết lại nội dung nền thành bài review hoàn chỉnh về cơ sở, lồng ghép nhận định
   và phản hồi vào đúng chủ đề. Thông tin chưa rõ thì bỏ hoặc diễn đạt có giới hạn
   tự nhiên; không đưa câu báo cáo kiểm chứng vào bài. Không chép bảng đối chiếu, mã tham chiếu hoặc số liệu
   nội bộ vào lời văn dành cho người đọc.

AI chỉ trả **một JSON cuối cùng** chứa bài viết và, nếu có,
`editorial_evidence_json`. Tiện ích vẫn dùng luồng GET → claim/heartbeat → POST
hiện có, không cần một cổng phân tích riêng.

Danh mục tham chiếu bằng chứng do server tạo từ chính dữ liệu nguồn của lượt
viết. Mỗi item có `id`, `kind` (`profile`, `feedback`, `insight`), `path` và
`evidence_type`. Danh mục chỉ chứa đường dẫn/loại dữ liệu, không sao chép lại
văn bản nguồn; AI phải đọc nội dung tại đường dẫn tương ứng trong `source`.
Ví dụ mã `p-name` tham chiếu tên trong hồ sơ, `r-1` tham chiếu một phản hồi,
`i-1` hoặc `i-summary` tham chiếu nhận định/tóm tắt insights khi chúng thực sự
có trong danh mục. Chỉ dùng ID được server cấp cho cùng `source_revision`;
không tự suy đoán ID, thêm URL hay truy cập trường ngoài danh mục.

Danh mục này khác danh mục URL trong `sources_json`: `evidence_refs` và
`opposing_refs` dùng các mã bằng chứng như `p-name`/`r-1`; `source_ids` của
`analysis_sections_json` vẫn dùng ID nguồn URL như `s1`. Không đổi lẫn hai loại.

## Giọng văn và các trường bài viết

Bài review lấy `facility.content` làm trục nội dung: giới thiệu cơ sở, đội ngũ,
dịch vụ, cơ sở vật chất và quá trình chăm sóc. Các trường hồ sơ bổ sung chỗ còn
thiếu; phản hồi, chỉ số và `facility_insights_json` làm phong phú từng chủ đề,
không thay thế toàn bộ bài bằng phân tích đánh giá hoặc lời dặn trước khi đến.
Nếu content thiếu thì xây dựng bài từ những trường có dữ liệu, không tự bịa.
Không phải báo cáo thống kê,
kiểm toán hay bài tự nhận đã trải nghiệm. Không đưa điểm sao, tổng số review,
số mẫu, tỷ lệ hoặc bảng số liệu vào tiêu đề, tóm tắt, nội dung hay các nhận xét.

| Trường hiện có | Nội dung dành cho người đọc |
| --- | --- |
| `excerpt` | Giới thiệu ngắn, định vị cơ sở. |
| `content_html` | Viết lại content cơ sở thành bài hoàn chỉnh, bổ sung thông tin hồ sơ, phản hồi và insights vào đúng chủ đề. |
| `strengths_json` | Thế mạnh, dịch vụ hoặc điểm nổi bật có cơ sở đối chiếu. |
| `analysis_sections_json` | Các phần giới thiệu, đội ngũ, dịch vụ, không gian và trải nghiệm; lồng phản hồi vào đúng mục, lưu ý gọn khi cần. |
| `considerations_json` | Lưu ý thực tế trước khi đặt lịch hoặc sử dụng dịch vụ. |
| `suitable_for_json` | Nhu cầu nào có thể tìm hiểu cơ sở này. |
| `verdict` | Nhận xét chung cân bằng, không bảo đảm kết quả điều trị. |

Không cần thêm cột hoặc xóa cột DB cho cách viết này. `sources_json`,
`evidence_limitations_json`, snapshot, revision và số liệu nguồn tiếp tục được
lưu để đối chiếu; không biến chúng thành nội dung báo cáo trong bài. Quy tắc
viết mới được API bổ sung cho cả prompt mặc định và prompt tùy biến đã lưu.

Phần phản hồi tổng hợp chủ đề thực sự có trong các nhận xét được cung cấp.
Khi nội dung phản hồi nhất quán, có thể nhận xét tự nhiên rằng các phản hồi hiện
có thiên về tích cực và nêu cụ thể điểm được đánh giá tốt. Không lấy điểm sao
để tự suy ra tay nghề, hiệu quả điều trị hay sự hài lòng của tất cả khách hàng;
một nhận xét đơn lẻ không thành xu hướng phổ biến. Không cố lặp mọi lời chê để
cân bằng cơ học, cũng không che giấu vấn đề quan trọng. Lưu ý thực tế như lịch
hẹn hoặc báo giá chỉ chiếm phần vừa đủ, không lấn át nội dung giới thiệu-review.

Không chèn những đoạn như “chưa đủ cơ sở để kết luận”, “chưa có đủ tài liệu để
đối chiếu” hay “trong tập nhận xét được lưu” vào lời văn công khai. Thông tin
thiếu thì bỏ, không bịa và không lấp bài bằng lời cảnh báo. Ghi chú nguồn và
giới hạn thông tin giữ ngắn ở phần riêng, không lặp trong từng đoạn.

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

Lưu `claim_token` ngay trong tiện ích, **không đưa vào prompt**. DB chỉ giữ hash của token. Với bài mới, claim tạo bản nháp trống nội bộ một lần; với bài đã có, claim giữ nguyên nội dung, trạng thái và URL để người dùng tiếp tục đọc. Khóa theo cơ sở + ngôn ngữ; không thay đổi hồ sơ nguồn. Giữ quyền 900 giây, heartbeat mỗi 60 giây. Mỗi lượt cập nhật phải có `request_id` mới; không dùng lại request đã lưu.

Server giữ phiên bản nội dung bài tại thời điểm claim. Heartbeat không làm
đổi phiên bản này. Nếu bài được biên tập trong lúc AI viết, heartbeat/POST trả
`article_changed`; không ghi đè phần đã sửa. Trả quyền cũ, tải lại và claim mới
để tiếp tục cập nhật theo phiên bản hiện tại.

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
  "status": "published",
  "title": "Review tên cơ sở: dịch vụ, tín hiệu đánh giá và điều cần cân nhắc",
  "excerpt": "Tóm tắt trung lập dựa trên dữ liệu công khai hiện có.",
  "verdict": "Kết luận cân bằng, không bảo đảm kết quả điều trị.",
  "content_html": "<h2>Tổng quan</h2><p>Toàn bộ nội dung bài viết thật, ít nhất 300 ký tự văn bản sau khi làm sạch HTML.</p>",
  "strengths_json": ["Điểm tích cực có cơ sở đối chiếu"],
  "considerations_json": ["Điều người đọc cần xác nhận trước khi đặt lịch"],
  "suitable_for_json": ["Nhu cầu có thể tìm hiểu thêm tại cơ sở"],
  "analysis_sections_json": [],
  "sources_json": [{"id":"s1","title":"Giữ nguồn từ danh mục GET","url":"https://medreview.vn/co-so-y-te/slug-that","platform":"MedReview"}],
  "evidence_limitations_json": ["Thông tin được tổng hợp từ nguồn công khai; hãy xác nhận trực tiếp dịch vụ và chi phí với cơ sở."],
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

### Trường đối chiếu nội bộ tùy chọn

Có thể bổ sung `editorial_evidence_json` vào **cùng object bài viết** gửi POST:

```json
{
  "editorial_evidence_json": {
    "schema_version": 1,
    "claims": [
      {
        "id": "claim-profile-name",
        "topic": "other",
        "statement": "Cơ sở được nhận diện theo tên trong hồ sơ.",
        "evidence_refs": ["p-name"],
        "feedback_scope": "none",
        "opposing_refs": [],
        "disposition": "use"
      },
      {
        "id": "claim-reception",
        "topic": "reception",
        "statement": "Có phản hồi đề cập việc hướng dẫn lịch hẹn.",
        "evidence_refs": ["r-1"],
        "feedback_scope": "single",
        "opposing_refs": [],
        "disposition": "qualify"
      }
    ]
  }
}
```

Đây chỉ là phần JSON bổ sung để giải thích cấu trúc, không phải request đầy
đủ hay kết luận về một cơ sở thật. Chỉ dùng ví dụ phản hồi nếu `r-1` thực sự
có trong danh mục và nội dung phản hồi hỗ trợ nhận định đó.

- `schema_version` là số nguyên `1`; `claims` là danh sách tối đa 30 mục.
  Mỗi mục phải có đủ bảy trường trong ví dụ, ID duy nhất tối đa 80 ký tự,
  `statement` là văn bản UTF-8 thuần tối đa 800 ký tự. Toàn bộ phần đối chiếu
  không vượt 64 KiB; không nhận các trường tùy ý ngoài hợp đồng.
- `topic` là `services`, `staff`, `reception`, `waiting_time`, `costs`,
  `facilities`, `access`, `suitability` hoặc `other`.
- `feedback_scope: "none"` khi không khái quát phản hồi;
  `"single"` cho phản hồi đơn lẻ; `"recurring"` chỉ dùng khi có nhiều phản
  hồi riêng biệt hỗ trợ cùng chủ đề. Lặp một ID hoặc dẫn các bản sao cùng
  nội dung không biến một phản hồi thành xu hướng chung. `recurring` cần ít
  nhất hai nội dung phản hồi trực tiếp khác nhau sau chuẩn hóa; một nhận định
  trong insights không được tính thành phản hồi trực tiếp bổ sung.
- `opposing_refs` chỉ dẫn bằng chứng có thật đang không thống nhất với nhận
  định. Không tự bịa mâu thuẫn; tổng đánh giá bên ngoài và số mẫu được lưu
  không phải hai con số mâu thuẫn vì chúng mô tả hai phạm vi khác nhau.
- `evidence_refs` cần ít nhất một ID, tối đa 20 ID duy nhất;
  `opposing_refs` có thể trống, tối đa 20 ID duy nhất. Không đưa cùng một ID
  vào cả hai danh sách; tổng hai danh sách của một nhận định tối đa 20 ID.
  Các ID đều phải thuộc danh mục bằng chứng của nguồn
  hiện tại.
- `disposition` là `use` (có thể dùng), `qualify` (cần giới hạn cách diễn đạt)
  hoặc `omit` (không đưa nhận định vào bài). Đây là quyết định biên tập của
  AI, không phải trạng thái xác minh của cơ sở. Khi có `opposing_refs` hoặc
  nhận định dựa vào insight chưa xác minh, chỉ dùng `qualify`/`omit`, không
  chọn `use`.

Trường này **không bắt buộc với payload cũ**: bỏ hẳn trường vẫn được nhận
như trước. Nếu gửi thì phải đúng hợp đồng; không gửi `null` hoặc một danh
sách thay cho object. Không chèn khóa API, token, dữ liệu nhận dạng bệnh nhân,
ảnh hoặc bản sao tùy ý của toàn bộ nguồn vào phần đối chiếu.

Server lưu phần đối chiếu đã chuẩn hóa **bên trong `ai_content_json`**, cùng
metadata vận hành; không tạo cột mới và không đưa nó vào bài viết, danh sách
review, trang chi tiết hay dữ liệu công khai. Nội dung bài đọc vẫn nằm trong
các trường hiện có. Khi gửi lại cùng request, phần đối chiếu cũng phải giữ
nguyên như những nội dung khác của payload.

Kiểm tra tham chiếu chỉ xác nhận cấu trúc, ID nguồn có tồn tại và điều kiện
tham chiếu cho phản hồi đơn lẻ/lặp lại. Nó **không tự chứng minh** câu viết
được nguồn hỗ trợ về ngữ nghĩa, các nguồn độc lập với nhau hoặc thông tin là
đúng về mặt y khoa. Phần đối chiếu không làm tăng nhãn xác thực; việc API xuất
bản tự động bên dưới không đồng nghĩa nội dung đã được xác minh chuyên môn.

Mỗi lượt lưu mới qua API, gồm **tạo bài và cập nhật bài đã có nội dung**, tự
chuyển sang **published** sau khi kiểm tra và lưu thành công. Có thể bỏ `status`
hoặc gửi `published`; để tương thích, payload từ mẫu cũ gửi `draft` vẫn được
nhận nhưng cũng xuất bản. API không dùng trường này để đưa bài về nháp hoặc
ẩn bài; nếu cần thao tác đó, dùng admin.

API cập nhật trên cùng `article_id`, không tạo bài trùng. Khi cập nhật, bỏ
`slug` hoặc để chuỗi rỗng sẽ giữ URL cũ; chỉ đổi URL nếu gửi slug hợp lệ khác.
Giữ `created_at` và mốc `published_at` đầu tiên nếu đã có; bài chưa từng đăng
được gán mốc xuất bản khi lưu. Chọn cập nhật bài đang lưu trữ (`archived`) cũng
là yêu cầu đăng lại bài đó sau khi lưu thành công.

API kiểm tra quyền, source revision và phiên bản bài, lưu toàn bộ nội dung cùng
snapshot/metadata trong giao dịch, đọc lại các cột trước khi trả
`saved:true, storage_verified:true, status:"published"`. Receipt có
`updated_existing:true` nếu thay nội dung bài cũ. Đây là xác nhận lưu DB và
trạng thái đăng, không phải xác nhận chất lượng y khoa. Nếu kiểm tra hoặc lưu
thất bại, bản đang đăng trước đó không bị thay thế hoặc đưa về nháp.

Gửi lại đúng request/token/payload của lượt gần nhất trả `idempotent:true`, không
ghi lại bài. Để thay đổi nội dung, GET theo ID, claim với `request_id` mới rồi
POST bản hoàn chỉnh. Gửi payload khác bằng request đã dùng báo 409. Receipt cũ
được tạo dưới cơ chế lưu nháp vẫn giữ kết quả cũ khi retry nguyên request; muốn
đăng/cập nhật bài đó thì tạo lượt claim mới. Retry cũ không ghi đè nội dung đã
được biên tập sau lần gửi trước.

## Ảnh khách hàng trên trang bài review

Gallery đọc riêng từ `medical_facilities.patient_images_json` của cơ sở đã
xuất bản, đúng ngôn ngữ. `facility_insights_json` là dữ liệu phân tích, không
phải thư viện ảnh khách. Gallery hiển thị tất cả ảnh có URL hợp lệ trong danh
sách (tối đa 100 ảnh theo schema), loại URL lỗi và ảnh trùng. Không lọc theo
`consent_confirmed` và không sửa giá trị trường này trong DB. Quản trị viên chịu
trách nhiệm lựa chọn ảnh phù hợp để đưa vào danh sách công khai.

Không có ảnh hợp lệ thì không hiện khối rỗng. Gallery không đưa ghi chú
nội bộ, URL nguồn hay trạng thái riêng vào HTML, AI prompt, snapshot hoặc danh
sách bài viết. Khối nguồn/giới hạn được ẩn trên giao diện đọc; dữ liệu căn cứ
vẫn được lưu nội bộ, không bị xóa khỏi DB khi thay đổi giao diện.

## Mã lỗi cần xử lý

| HTTP | reason | Xử lý |
| --- | --- | --- |
| 401 | Khóa không hợp lệ | Kiểm tra khóa API, không sửa prompt để vượt xác thực. |
| 409 | already_claimed | Worker khác đang giữ bài; không tự chiếm. |
| 409 | lease_lost | Kiểm tra trạng thái, nhận quyền lại rồi đối chiếu nguồn. |
| 409 | source_revision_changed | Lấy và nghiên cứu nguồn mới. |
| 409 | article_changed | Bài đã được sửa sau khi claim; trả quyền cũ, tải lại và nhận quyền mới. |
| 409 | idempotency_conflict | Không tái sử dụng request ID với dữ liệu khác. |
| 422 | unknown_source / invalid_payload / invalid_evidence | Sửa JSON/nguồn hoặc bản ghi bằng chứng theo danh mục và hợp đồng. |
| 503 | schema_unavailable | Chạy migration riêng trên đúng DB; tiện ích không tự migration. |

API này không gọi dịch vụ AI hoặc quét mạng xã hội thay người dùng. Nó cung cấp nguồn/prompt, giữ quyền viết và nhận kết quả để biên tập/xuất bản bài phân tích.
