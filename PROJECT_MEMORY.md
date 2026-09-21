# Ghi nhớ dự án MedReview

## Mục đích

Website PHP thuần cho MedReview, tập trung vào việc giới thiệu và đánh giá cơ sở y tế, bác sĩ và các review y tế. Dự án cũng có các khu vực CMS cũ hơn cho bài viết, sản phẩm, dự án và trang dịch vụ.

## Công nghệ và chạy dự án

- PHP thuần, không dùng framework hay dependency manager trong thư mục hiện tại.
- MySQL, truy cập qua PDO trong `db.php`.
- Apache rewrite qua `.htaccess`.
- Tệp ảnh thư viện nằm tại `uploads/library/`.
- Cấu hình cơ sở dữ liệu ưu tiên lấy từ biến môi trường `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`; không lưu thông tin bí mật vào file này.

## Luồng public

- `.htaccess` chuyển URL một cấp (slug) sang `route.php`.
- `route.php` tìm trang trong cấu hình front editor; nếu không khớp thì hiển thị bài viết qua `post.php`.
- Header/footer dùng chung: `Tem/header.php`, `Tem/footer.php`.
- Trang chủ: `index.php` + `Tem/home.php`.
- Directory y tế:
  - Danh sách cơ sở: `co-so-y-te.php`; chi tiết: `co-so-y-te-chi-tiet.php`.
  - Danh sách bác sĩ: `bac-si.php`; chi tiết: `bac-si-chi-tiet.php`.
  - Danh sách review: `review.php`; chi tiết: `review-chi-tiet.php`.
  - Lớp dữ liệu/chuyển đổi/seed: `medical_directory.php`.
- Nội dung CMS: blog (`blog.php`, `post.php`), sản phẩm (`san-pham.php`), dự án (`du-an.php`).
- Có các trang Việt/Anh; mapping locale và liên kết chuyển ngôn ngữ nằm trong `db.php`.

## Quản trị

- Khu vực `/admin`, xác thực session trong `admin/_bootstrap.php`.
- CRUD nội dung, cơ sở y tế, bác sĩ, review, media library, cài đặt và dashboard.
- Các API quản trị phần lớn gọi `admin_require_login()`.
- `builder/builder.php` là page builder độc lập, cũng yêu cầu đăng nhập.

## Front editor

- Các hàm front editor nằm tập trung ở `db.php`.
- Hỗ trợ cấu hình slug/SEO trang, chỉnh text/ảnh/icon/block, template block, lưu source và lịch sử khôi phục.
- `front_editor_page_profiles`, `front_editor_history`, `front_editor_templates` được tạo khi cần.
- Các trang có thể render lớp chỉnh sửa qua `front_editor_render(<pageKey>)`.

## Bảng dữ liệu chính

- CMS: `categories`, `posts`, `product_categories`, `products`, `project_categories`, `projects`.
- Quản trị/cấu hình: `users`, `site_settings`, `visits`, `migration_log`.
- Front editor: `front_editor_history`, `front_editor_templates`, `front_editor_page_profiles`.
- Directory: `medical_facilities`, `medical_reviews`, `medical_doctors`.

## Lưu ý khi thay đổi

- Giữ tương thích với các helper trong `db.php`; đây là điểm trung tâm của slug, locale, SEO và front editor.
- Trang public thường chứa CSS/JS inline khá lớn; ưu tiên chỉnh đúng page/file hiện hữu thay vì tách lớn khi chưa có yêu cầu refactor.
- Không thấy Git repository hoặc README ở workspace tại thời điểm ghi nhớ này.
- Chạy `php -l <file>` sau thay đổi PHP; lần rà soát ban đầu, các file PHP đã không có lỗi cú pháp.

## Việc nên xử lý trước production

- Setup Admin đã được gỡ khỏi giao diện; `admin/api/setup.php`, `admin/api/test-db.php` và `admin/api/tables/list.php` hiện trả 410 để không còn khởi tạo hoặc dò DB qua web.
- Dùng biến môi trường thật cho kết nối DB, không dựa vào fallback trong mã nguồn.
- Giữ các tiện ích/debug ở `scripts/debug/` và seed ở `scripts/seeds/`; không expose chúng qua public web root khi triển khai production.
- Thêm bảo vệ CSRF cho các thao tác ghi/xóa ở admin nếu triển khai internet công khai.
