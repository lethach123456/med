# Ảnh bệnh nhân và ghi chú tổng quan riêng

Hai bảng `medical_facilities` và `medical_doctors` có hai cột tùy chọn:
`patient_images_json MEDIUMTEXT NULL` chứa danh sách ảnh và
`patient_images_notes TEXT NULL` chứa ghi chú tổng quan về nội dung các ảnh.
Cả hai tách biệt với `image_url`/`gallery_json` công khai của hồ sơ.

## Cài đặt một lần

Chạy trên đúng database của hosting, từ thư mục dự án:

```sh
php scripts/migrate_patient_images.php --check
php scripts/migrate_patient_images.php --apply
php scripts/migrate_patient_images.php --check
```

`--check` chỉ đọc trạng thái. `--apply` thêm cột còn thiếu, chạy lại không xóa hay
ghi đè dữ liệu đã có. Không có migration/seed tự động trong lượt xem trang hoặc
gọi API. Cài đặt đã có riêng `patient_images_json` vẫn chạy lại lệnh này để thêm
`patient_images_notes` còn thiếu. Không seed hoặc sửa các dòng dữ liệu đang có.
Client cũ không gửi trường mới vẫn hoạt động khi cột chưa được cài đặt; mỗi trường
chỉ kiểm tra cột tương ứng khi được gửi, không yêu cầu cả hai luôn tồn tại.

## Cấu trúc

```json
{
  "patient_images_notes": "Phần lớn ảnh ghi lại vùng răng trước và sau chỉnh nha. Cần đối chiếu thời điểm chụp và nội dung từng ảnh trước khi sử dụng.",
  "patient_images_json": [
    {
      "url": "/uploads/patient-media/case-001-before.webp",
      "caption": "Tình trạng trước điều trị",
      "alt": "Ảnh vùng điều trị trước can thiệp",
      "stage": "before",
      "consent_confirmed": false,
      "source_url": "https://clinic.example.org/consented-case"
    },
    {
      "url": "https://clinic.example.org/media/case-001-after.webp",
      "caption": "Tình trạng sau điều trị",
      "stage": "after",
      "consent_confirmed": false
    }
  ]
}
```

Địa chỉ và ca bệnh trong ví dụ là dữ liệu giả. API nhận mảng object trực tiếp hoặc
chuỗi JSON mã hóa cùng mảng đó.

`patient_images_notes` là chuỗi riêng bên cạnh mảng ảnh, tối đa 4.000 ký tự Unicode.
Có thể ghi nhiều dòng; đầu/cuối được trim và xuống dòng CRLF được chuẩn hóa thành
LF. Ghi chú này do người dùng nhập để ghi nhận ảnh chủ yếu đang thể hiện gì và việc
cần đối chiếu, không phải kết quả hệ thống/AI tự phân tích ảnh. Không đưa thông tin
nhận diện hoặc dữ liệu bệnh án riêng tư vào ghi chú.

| Trường | Quy tắc |
| --- | --- |
| `url` | Bắt buộc; URL HTTP(S) tuyệt đối hoặc đường dẫn `/uploads/...` an toàn, tối đa 4.000 ký tự. Không nhận `data:`, Base64, `blob:`, URL có tài khoản/mật khẩu, đường dẫn vượt thư mục. |
| `caption`, `alt` | Chuỗi tùy chọn, tối đa 500 ký tự mỗi trường; mặc định `""`. Không ghi tên, mã bệnh án hoặc thông tin nhận diện không cần thiết. |
| `stage` | `before`, `during`, `after` hoặc `other`; mặc định `other`. |
| `consent_confirmed` | Boolean thật `true`/`false`; mặc định `false`. Không nhận chuỗi `"true"` hoặc số `1`. |
| `source_url` | Tùy chọn; cùng chính sách URL với `url`. Nếu chưa có thì bỏ khóa, không gửi chuỗi rỗng/null. |

Tối đa 100 ảnh, tổng JSON tối đa 1 MiB. URL trùng, object chứa khóa không hỗ trợ,
kiểu dữ liệu sai hoặc ký tự điều khiển sẽ bị từ chối. Thứ tự ảnh được giữ nguyên.

- Không gửi khóa `patient_images_json`: giữ nguyên danh sách đang lưu.
- Gửi `patient_images_json: []` hoặc chuỗi `"[]"`: xóa danh sách metadata đã lưu.
- `null`, chuỗi rỗng hoặc object đơn không phải lệnh xóa và bị từ chối.
- Không gửi `patient_images_notes`: giữ nguyên ghi chú. Gửi chuỗi rỗng `""`: xóa
  ghi chú. `null`, số, boolean, array/object hoặc chuỗi vượt 4.000 ký tự bị từ chối.
- Hai trường lưu độc lập: đổi ghi chú không thay danh sách ảnh; xóa danh sách ảnh
  không tự xóa ghi chú và ngược lại.
- API chỉ lưu metadata, không tải, nén, chuyển Base64, tạo ảnh hoặc sao chép file.
  Xóa metadata không xóa file ảnh gốc.

## Ghi từ admin/API

Trang sửa cơ sở y tế và sửa bác sĩ có ô JSON riêng cho ảnh bệnh nhân và ô ghi chú
tổng quan. Việc lưu vẫn tuân theo quyền quản trị và CSRF của biểu mẫu hiện tại.

API dùng lại các cổng nhận nội dung đang có, với header `X-Medical-Api-Key`:

- `POST /api/medical/facility-content-update.php`: gửi thêm trường trong object
  cơ sở đang cập nhật; vẫn cần `id` hoặc `slug` hợp lệ và quyền giữ bài nếu bài đã
  được một worker nhận.
- `POST /api/medical/doctor-content-update.php`: gửi thêm trường trong payload
  nghiên cứu bác sĩ hiện có; các yêu cầu `id`, `writer_claim_token`, nguồn chứng
  minh, trạng thái đủ điều kiện và cấu trúc bài bác sĩ vẫn giữ nguyên. Đây không
  phải endpoint sửa độc lập mọi bác sĩ chỉ bằng `id` và danh sách ảnh.

Ví dụ trường bổ sung trong payload cơ sở:

```json
{
  "id": 12,
  "patient_images_notes": "Ảnh chủ yếu ghi nhận vùng điều trị trước/sau; cần xác nhận lại thời điểm chụp.",
  "patient_images_json": [
    {
      "url": "https://clinic.example.org/media/consented-case.webp",
      "caption": "Kết quả sau điều trị",
      "stage": "after",
      "consent_confirmed": false
    }
  ]
}
```

Chỉ cập nhật ghi chú của cơ sở, không thay nội dung bài hay danh sách ảnh:

```json
{
  "id": 12,
  "patient_images_notes": "Phần lớn là ảnh chụp trước/sau chỉnh nha; cần đối chiếu từng giai đoạn."
}
```

Lượt gửi cơ sở chỉ gồm định danh, metadata quyền giữ bài và hai trường ảnh/ghi chú
sẽ giữ nguyên `full_json` của bài hiện có. Đối với bác sĩ, ghi chú vẫn đi cùng
payload nghiên cứu đầy đủ và lease hợp lệ như đã nêu, không bỏ qua điều kiện này.

Giới hạn batch, lease và thông báo lỗi của từng endpoint vẫn áp dụng. Khi cột chưa
có, lượt ghi trường mới báo cần chạy `migrate_patient_images.php --apply`; hệ
thống không tự tạo cột trong request.

### Xác nhận đã lưu trên cổng nhận nội dung cơ sở — contract 2

`POST /api/medical/facility-content-update.php` ghi hai trường riêng vào đúng cột
DB, sau đó đọc lại **chỉ các cột đã gửi** và đối chiếu chính xác trước khi ghi nhận
item đã cập nhật thành công. Việc chỉ có dữ liệu trong `processed_items` hoặc
`full_json` không được coi là xác nhận đã lưu ảnh/ghi chú vào cột riêng.

Phiên bản mới có header `X-MedReview-Patient-Images-Contract: 2` và hai khóa phản
hồi sau trong cả kết quả thành công/lỗi xử lý:

```json
{
  "patient_images_contract_version": 2,
  "patient_images_updates": [
    {
      "id": 393,
      "saved_fields": ["patient_images_notes"],
      "verified": true
    }
  ]
}
```

Đây là receipt xác nhận đọc lại DB, không chứa URL ảnh, caption hoặc nội dung ghi
chú. `processed_items` không echo hai trường riêng tư. Nếu cột vẫn là `NULL`, dữ
liệu khác giá trị vừa ghi hoặc bản ghi không còn tồn tại, API báo lỗi, không thêm
receipt thành công cho item đó. Payload cũ không dùng hai trường trả danh sách
receipt rỗng, không phát sinh lượt đọc lại cột riêng.

Khi kiểm tra hosting, cần đối chiếu marker phiên bản và receipt đúng `id`/tên cột;
không chỉ nhìn `ok: true` từ bản API cũ. Thiếu marker này có thể là bản triển khai
chưa có logic mới. Batch vẫn xử lý theo từng item; xem thêm `updated_ids` và thông
báo lỗi, không suy ra toàn bộ batch đã được ghi chỉ từ một receipt.

## Phạm vi riêng tư và công bố

Hai trường này **không tự xuất hiện** trong hồ sơ công khai, gallery/lightbox,
Toplist, JSON tìm kiếm, trang chủ hoặc prompt nghiên cứu AI. Dữ liệu không được tự
chuyển cho AI để điều tra ảnh, không sinh thêm yêu cầu phân tích ảnh. Dữ liệu cũng không được thêm
vào `full_json`/phản hồi nghiên cứu bác sĩ và danh sách trường ảnh của tác vụ gắn
logo chỉ vì được lưu trong hai cột riêng này.

`consent_confirmed` là khai báo của người gửi, **không phải bằng chứng hệ thống đã
kiểm chứng sự đồng ý**, không cấp quyền công bố và không chứng minh hiệu quả điều
trị. Không tự thu thập ảnh bệnh nhân từ mạng hoặc tạo ảnh bệnh nhân giả để điền
trường. Chỉ lưu ảnh có quyền sử dụng, đã được kiểm tra thủ công và giảm thông tin
nhận diện phù hợp.

Ghi chú tổng quan chỉ là mô tả thủ công, không chứng minh kết quả điều trị, chẩn
đoán, độ an toàn hoặc hiệu quả của cơ sở/bác sĩ. Mọi kết luận về ảnh vẫn cần người
có thẩm quyền kiểm tra nguồn, sự đồng ý và bối cảnh cụ thể.

Việc không xuất metadata công khai **không biến URL ảnh thành URL riêng tư**. File
đã nằm trong thư mục web công khai hoặc URL bên ngoài vẫn có thể truy cập trực
tiếp. Nếu cần bảo mật ảnh, phải dùng kho lưu trữ có xác thực/quyền truy cập riêng;
đợt này không tạo cơ chế phân quyền tải file. Không đặt ảnh nhạy cảm vào thư viện
ảnh công khai dùng chung. Nếu ảnh cũng được thêm riêng vào `gallery_json` hoặc
HTML bài viết, các nơi đó vẫn có thể công khai theo luồng cũ.

Mọi chức năng hiển thị ảnh bệnh nhân về sau cần quyết định công bố/kiểm duyệt riêng,
không được coi việc có URL hoặc `consent_confirmed: true` là đủ để tự công khai.
