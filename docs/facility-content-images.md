# Ảnh trong API nhận hồ sơ cơ sở y tế

`POST /api/medical/facility-content-update.php` xử lý ảnh bìa và gallery ngay
trong lượt gửi, không cần worker hoặc migration mới. Header phản hồi:
`X-MedReview-Facility-Images-Contract: 2`.

- Ảnh bìa: `image_url` hoặc `featured_image_url`.
- Gallery: `gallery_json`, `gallery` hoặc `images`; nhận URL hoặc object có
  `url`/`src`, `caption`, `angle`, `source` như trước.
- Tải URL công khai bằng bộ tải ảnh có chặn địa chỉ nội bộ/redirect không an
  toàn. Đường dẫn nội bộ chỉ đọc file có thật trong `/uploads/library/`.
- Kiểm tra PNG/JPEG/WebP thật, tối thiểu 200×200, tối đa 12 MB/12 megapixel.
- Đóng wordmark MedReview ở góc trên bên phải bằng renderer hiện có, rồi nén
  thành JPEG **dưới 150.000 bytes**, cạnh dài tối đa 1280px.
- Lưu trong `/uploads/library/facilities/{id}/content-branded-v1/`, thay URL
  trong `image_url`, `gallery_json`, `full_json` và `processed_items`; giữ các
  chú thích/metadata gallery. Chỉ công bố file đã hoàn thành xử lý.

Một URL dùng cho cả bìa/gallery chỉ xử lý một lần trong item. Gửi lại chính
URL ảnh đã tạo trong thư mục phiên bản này sẽ tái sử dụng file nếu raster và
giới hạn dung lượng còn hợp lệ, không đóng logo lần hai. Không thay file gốc
hoặc đóng logo hàng loạt các ảnh không có trong request.

Giới hạn 12 URL khác nhau mỗi cơ sở; toàn request dùng chung ngân sách tải/xử
lý 25 giây, từng lần tải tối đa 8 giây. Nên gửi từng cơ sở hoặc lô nhỏ. Nếu
tải/đóng logo/nén một ảnh thất bại, item đó không ghi bài hoặc thay ảnh/giải
phóng claim, phản hồi có `errors` và `image_errors`; có thể gửi lại. Các item
đã lưu trước trong cùng lô vẫn được báo trong `updated_ids`. Câu SQL cuối vẫn
kiểm tra quyền giữ bài, tránh ghi nhầm nếu quyền thay đổi trong lúc tải ảnh.

Phản hồi giữ các trường đếm cũ nhưng `images_queued` và
`gallery_images_queued` bằng 0; `image_processing` là `synchronous`.
`images_imported`, `images_branded`, `gallery_images_imported` đếm ảnh đã
được xác nhận có bản local đúng yêu cầu (bao gồm bản tái sử dụng). Kiểm tra
`ok`, `errors`, `image_errors` thay vì suy thành công chỉ từ bộ đếm.

Không gửi trường ảnh thì không quét/xử lý lại ảnh cũ. Gallery `[]` vẫn xóa
gallery; trường bìa rỗng giữ hành vi xóa bìa như trước. Các trường
`patient_images_json`, `patient_images_notes`, `facility_insights_json` không
bị đổi luồng hoặc tự đóng logo. Máy chủ cần GD, cURL và asset wordmark PNG đã
có trong repo như các API thay ảnh hiện tại, không cần FreeType/font cài trên
server. Không gọi API trực tiếp để kiểm thử trên cơ sở thật nếu chưa
muốn cập nhật dữ liệu; test ảnh dùng raster giả và không truy cập DB/mạng.
