# Gắn logo hàng loạt cho ảnh y tế

Mở **Admin → Cài đặt → Gắn logo ảnh**, hoặc nút **Gắn logo ảnh hàng loạt** trong Thư viện. Chỉ tài khoản quản trị viên được dùng. Công cụ không tự chạy khi mở trang.

1. Bấm **Quét ảnh** để lập danh sách từ hồ sơ cơ sở y tế/bác sĩ ở mọi ngôn ngữ, cùng các thư mục ảnh y tế `co-so-y-te`, `bac-si`, `co-so-y-te-ai` trong thư viện. Các folder tháng/năm khác chỉ được đưa vào khi ảnh có liên kết trong hồ sơ. Không quét ảnh Cẩm nang, tài sản giao diện, favicon/logo hoặc bản gốc `source-*` không được dùng trong DB.
2. Chọn tất cả, cơ sở hoặc bác sĩ rồi bấm **Gắn logo & ghi đè**. Xác nhận phạm vi và số ảnh trước khi chạy: **tệp ảnh hiện tại sẽ bị ghi đè, không lưu bản gốc hoặc bản sao dự phòng, không thể hoàn tác bằng công cụ**. Mỗi request xử lý một ảnh, trình duyệt tự gọi lượt tiếp theo, không hỏi lại cho từng ảnh. **Tạm dừng** ngừng sau ảnh đang xử lý; để trang mở khi chạy. Đóng trang/reload không mất kết quả, bấm chạy và xác nhận để tiếp tục ảnh chờ.
3. Số **Đã gắn** dựa trên sổ theo dõi của công cụ; **Chưa gắn** gồm ảnh chờ, đang xử lý và ảnh lỗi. Đây không phải nhận diện logo có sẵn bằng OCR. Ảnh đã có logo được thêm từ nguồn khác chưa từng xử lý không thể được tự nhận biết.
4. Ảnh lỗi ghi lý do. Bấm **Thử lại ảnh lỗi** sau khi nguồn/quyền máy chủ được sửa. Quét lại làm mới danh sách nguồn hiện tại; ảnh không còn trong hồ sơ lẫn thư viện y tế được bỏ khỏi thống kê và hàng chờ. Lịch sử hash vẫn giữ riêng trong sổ để tránh gắn logo chồng nếu ảnh được đưa vào lại. Nếu yêu cầu cũ bị ngắt và còn trạng thái **Đang xử lý**, bấm **Quét danh sách ảnh** để khôi phục hàng chờ.

## Ảnh và liên kết

Wordmark chữ **MedReview**, không `.vn`, không icon/hộp nền: `Med` navy `#142D50`, `Review` royal blue `#2D63FF`, Inter ExtraBold theo mẫu. Asset PNG trong suốt và SVG đường nét nằm ở `assets/brand/`; server không cần cài font. Logo rộng 22% ảnh và cách mép trên/phải 3%, co theo ảnh nếu ảnh quá thấp. Renderer giữ hướng EXIF của JPEG, tỷ lệ/kích thước và alpha PNG/WebP.

Ảnh trên hosting được gắn logo rồi **ghi đè tại đúng thư mục và tên tệp hiện tại**, không đổi URL hoặc phần mở rộng trong DB. Không tạo thư mục bản có logo và không giữ bytes ảnh gốc. Tệp kết quả được kiểm tra trước khi thay thế; đây không phải bản sao để khôi phục ảnh gốc. Caption, alt, gallery và các dữ liệu nguồn/pháp lý không đổi. Các hồ sơ dùng chung một URL cùng hiển thị ảnh đã ghi đè.

Chỉ ảnh nội bộ nằm thật trong `uploads/library` mới có thể ghi đè. **Ảnh HTTP(S) ở website bên ngoài bị từ chối**, vì hệ thống không có quyền thay thế tệp trên máy chủ nguồn. Nếu cần xử lý, lưu ảnh về thư viện và cập nhật hồ sơ về URL trên hosting trước, rồi quét lại. JPEG/PNG/WebP hợp lệ, tối đa 12MP và 12MB. Máy chủ cần GD và quyền ghi `storage`, thư mục và tệp ảnh tương ứng. File quá lớn hoặc thiếu khả năng xử lý sẽ báo lỗi riêng thay vì làm hỏng ảnh.

Danh sách Admin chỉ có **Xem ảnh** cho ảnh ghi đè cùng URL; thumbnail dùng phiên bản hash để tránh hiển thị cache cũ. Với kết quả từ phiên bản công cụ cũ còn lưu bản có logo riêng, liên kết được ghi rõ **Ảnh hiện tại** và **Bản có logo cũ**; đây không phải chức năng hoàn tác cho các lượt ghi đè mới. Các cache/CDN bên ngoài có thể cần được làm mới sau khi ghi đè vì URL ảnh không đổi.

## Lưu trạng thái và triển khai

Không cần cột DB hoặc migration mới. Sổ JSON và khóa thao tác nằm trong `storage/image-branding/`, được bảo vệ bởi `storage/.htaccess`, không commit lên Git. Sổ theo dõi hash/kết quả giúp nhận biết ảnh đã xử lý để tránh gắn logo lặp. Một khóa thao tác chặn hai trình duyệt xử lý cùng lúc; request trạng thái vẫn đọc được khi ảnh đang chạy. Giao diện xác nhận trước mỗi lần bắt đầu một hàng chờ; việc quét và xem thống kê không thay đổi ảnh.

Mỗi lần quét mới đọc các trường chứa ảnh của mỗi bảng `medical_facilities` và `medical_doctors` bằng một truy vấn dữ liệu, kèm truy vấn metadata cột và cấu hình ảnh cần loại trừ. MySQL dùng cursor không buffer toàn bảng để giới hạn RAM; cấu hình PDO được khôi phục cả khi quét lỗi. Danh sách URL, liên kết hồ sơ và cấu hình loại trừ được lưu vào `state.json`. Thu thập nguồn và kiểm tra hash diễn ra ngoài khóa JSON; chỉ khóa khi đọc hoặc lưu trạng thái. Nếu đọc DB thất bại, snapshot trước vẫn nguyên vẹn.

Các bước quét thư viện và xử lý ảnh ghi đè cùng URL dùng JSON/file ảnh, không truy vấn các bảng hồ sơ, không mở transaction và không làm hết hạn cache tìm kiếm. API vẫn xác minh quyền admin qua bảng `users` ở mỗi yêu cầu. Ngoại lệ cho dữ liệu từ chế độ sao chép cũ: công cụ đối chiếu đúng URL bản sao từng được ghi trong sổ, dựng lại liên kết hồ sơ từ DB lúc quét, rồi sửa URL bằng transaction khi xử lý; chỉ khi URL DB thực sự đổi mới làm hết hạn cache. Snapshot cũ hoặc chưa quét xong phải quét lại/hoàn tất trước khi nút gắn logo được bật.

Ảnh mới được render vào file tạm cùng thư mục và giải mã raster kiểm tra đầy đủ. Hash kết quả được ghi vào sổ **trước** atomic rename thay ảnh hiện tại; nếu request bị ngắt sau đó, lần chạy lại nhận ra bytes đã có logo và không đóng thêm. File tạm không phải bản lưu ảnh gốc và được dọn khi yêu cầu kết thúc. Nếu còn kết quả từ chế độ sao chép cũ, quét lại sẽ chuyển các job đó sang ghi đè file gốc và khôi phục đúng liên kết DB về URL gốc; không tự xóa hàng loạt các bản sao/backup lịch sử.

Deploy cả PHP, JS/CSS, PNG/SVG wordmark và font license/provenance. Không xoá sổ theo dõi hoặc folder ảnh đã gắn trên hosting khi deploy. Công cụ chưa chạy trên ảnh thật chỉ vì code được triển khai; quản trị viên chủ động quét/chạy bằng nút.
