# Ảnh bệnh nhân — metadata JSON riêng

Hai bảng `medical_facilities` và `medical_doctors` có cột tùy chọn
`patient_images_json MEDIUMTEXT NULL`. Đây là danh sách ảnh bệnh nhân tách biệt
với `image_url`/`gallery_json` của hồ sơ cơ sở và bác sĩ.

## Cài đặt một lần

Chạy trên đúng database của hosting, từ thư mục dự án:

```sh
php scripts/migrate_patient_images.php --check
php scripts/migrate_patient_images.php --apply
php scripts/migrate_patient_images.php --check
```

`--check` chỉ đọc trạng thái. `--apply` thêm cột còn thiếu, chạy lại không xóa hay
ghi đè dữ liệu đã có. Không có migration/seed tự động trong lượt xem trang hoặc
gọi API. Client cũ không gửi trường mới vẫn hoạt động khi cột chưa được cài đặt;
chỉ lượt ghi có `patient_images_json` mới yêu cầu cột này.

## Cấu trúc

```json
{
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
- API chỉ lưu metadata, không tải, nén, chuyển Base64, tạo ảnh hoặc sao chép file.
  Xóa metadata không xóa file ảnh gốc.

## Ghi từ admin/API

Trang sửa cơ sở y tế và sửa bác sĩ có ô JSON riêng cho ảnh bệnh nhân. Việc lưu vẫn
tuân theo quyền quản trị và CSRF của biểu mẫu hiện tại.

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

Giới hạn batch, lease và thông báo lỗi của từng endpoint vẫn áp dụng. Khi cột chưa
có, lượt ghi trường mới báo cần chạy `migrate_patient_images.php --apply`; hệ
thống không tự tạo cột trong request.

## Phạm vi riêng tư và công bố

Trường này **không tự xuất hiện** trong hồ sơ công khai, gallery/lightbox, Toplist,
JSON tìm kiếm, trang chủ hoặc prompt nghiên cứu AI. Dữ liệu cũng không được thêm
vào `full_json`/phản hồi nghiên cứu bác sĩ và danh sách trường ảnh của tác vụ gắn
logo chỉ vì được lưu trong `patient_images_json`.

`consent_confirmed` là khai báo của người gửi, **không phải bằng chứng hệ thống đã
kiểm chứng sự đồng ý**, không cấp quyền công bố và không chứng minh hiệu quả điều
trị. Không tự thu thập ảnh bệnh nhân từ mạng hoặc tạo ảnh bệnh nhân giả để điền
trường. Chỉ lưu ảnh có quyền sử dụng, đã được kiểm tra thủ công và giảm thông tin
nhận diện phù hợp.

Việc không xuất metadata công khai **không biến URL ảnh thành URL riêng tư**. File
đã nằm trong thư mục web công khai hoặc URL bên ngoài vẫn có thể truy cập trực
tiếp. Nếu cần bảo mật ảnh, phải dùng kho lưu trữ có xác thực/quyền truy cập riêng;
đợt này không tạo cơ chế phân quyền tải file. Không đặt ảnh nhạy cảm vào thư viện
ảnh công khai dùng chung. Nếu ảnh cũng được thêm riêng vào `gallery_json` hoặc
HTML bài viết, các nơi đó vẫn có thể công khai theo luồng cũ.

Mọi chức năng hiển thị ảnh bệnh nhân về sau cần quyết định công bố/kiểm duyệt riêng,
không được coi việc có URL hoặc `consent_confirmed: true` là đủ để tự công khai.
