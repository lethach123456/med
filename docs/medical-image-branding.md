# Gắn logo hàng loạt cho ảnh y tế

Mở **Admin → Cài đặt → Gắn logo ảnh**, hoặc nút **Gắn logo ảnh hàng loạt** trong Thư viện. Chỉ tài khoản quản trị viên được dùng. Công cụ không tự chạy khi mở trang.

1. Bấm **Quét ảnh** để lập danh sách từ hồ sơ cơ sở y tế/bác sĩ ở mọi ngôn ngữ, cùng các thư mục ảnh y tế `co-so-y-te`, `bac-si`, `co-so-y-te-ai` trong thư viện. Các folder tháng/năm khác chỉ được đưa vào khi ảnh có liên kết trong hồ sơ. Không quét ảnh Cẩm nang, tài sản giao diện, favicon/logo hoặc bản gốc `source-*` không được dùng trong DB.
2. Chọn tất cả, cơ sở hoặc bác sĩ rồi bấm **Gắn logo**. Mỗi request xử lý một ảnh, trình duyệt tự gọi lượt tiếp theo. **Tạm dừng** ngừng sau ảnh đang xử lý; để trang mở khi chạy. Đóng trang/reload không mất kết quả, bấm chạy để tiếp tục ảnh chờ.
3. Số **Đã gắn** dựa trên sổ theo dõi của công cụ; **Chưa gắn** gồm ảnh chờ, đang xử lý và ảnh lỗi. Đây không phải nhận diện logo có sẵn bằng OCR. Ảnh đã có logo được thêm từ nguồn khác chưa từng xử lý không thể được tự nhận biết.
4. Ảnh lỗi ghi lý do. Bấm **Thử lại ảnh lỗi** sau khi nguồn/quyền máy chủ được sửa. Quét lại bổ sung ảnh mới và nhận URL gốc được khôi phục trong DB; không xóa sổ theo dõi. Nếu yêu cầu cũ bị ngắt và còn trạng thái **Đang xử lý**, bấm **Quét danh sách ảnh** để khôi phục hàng chờ. Bản có logo bị mất cũng được đưa vào hàng chờ để tạo lại và sửa đúng liên kết cũ.

## Ảnh và liên kết

Wordmark chữ **MedReview**, không `.vn`, không icon/hộp nền: `Med` navy `#142D50`, `Review` royal blue `#2D63FF`, Inter ExtraBold theo mẫu. Asset PNG trong suốt và SVG đường nét nằm ở `assets/brand/`; server không cần cài font. Logo rộng 22% ảnh và cách mép trên/phải 3%, co theo ảnh nếu ảnh quá thấp. Renderer giữ hướng EXIF của JPEG, tỷ lệ/kích thước và alpha PNG/WebP.

Ảnh gốc không bị ghi đè. Ảnh tải ngoài còn được giữ nguyên bytes trong `storage/image-branding/originals/` (không truy cập công khai). Bản có logo được lưu với URL mới tại `/uploads/library/medreview-branded/`, rồi cập nhật ảnh đại diện, `ai_image_url`, gallery, ảnh dịch vụ/đội ngũ/review và `img src` trong nội dung của hồ sơ đang còn dùng đúng URL nguồn. Caption, alt và các trường không phải ảnh được giữ nguyên. Dữ liệu lịch sử `full_json`, nguồn chứng minh, giấy phép, claim và bản scan/fix ảnh không bị sửa. Tất cả liên kết dùng chung cùng URL nguồn được xử lý trong một transaction; ảnh bị biên tập xoá trong lúc chạy không được thêm lại.

Ảnh từ HTTP(S) công khai được tải về, kiểm tra DNS/IP từng redirect, giải mã raster và chèn logo; file nội bộ phải nằm thật trong `uploads/library`. JPEG/PNG/WebP hợp lệ, tối đa 12MP và 12MB với ảnh nội bộ; tải ngoài giới hạn 5MiB/18 giây theo downloader hiện có. Máy chủ cần GD, cURL (ảnh ngoài), quyền ghi `storage` và `uploads/library`. File quá lớn hoặc thiếu khả năng xử lý sẽ báo lỗi riêng thay vì làm hỏng ảnh.

## Lưu trạng thái và triển khai

Không cần cột DB hoặc migration mới. Sổ JSON và khóa thao tác nằm trong `storage/image-branding/`, được bảo vệ bởi `storage/.htaccess`, không commit lên Git. Bản đã render và hash được ghi lại trước khi sửa DB để lần thử lại dùng lại file có logo. Một khóa thao tác chặn hai trình duyệt xử lý cùng lúc; request trạng thái vẫn đọc được khi ảnh đang chạy. URL ảnh mới giúp tránh cache cũ; cache tìm kiếm được đánh dấu cập nhật sau commit.

Deploy cả PHP, JS/CSS, PNG/SVG wordmark và font license/provenance. Không xoá sổ theo dõi hoặc folder ảnh đã gắn trên hosting khi deploy. Công cụ chưa chạy trên ảnh thật chỉ vì code được triển khai; quản trị viên chủ động quét/chạy bằng nút.
