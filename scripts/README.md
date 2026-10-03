# Scripts nội bộ

`debug/` và `seeds/` chỉ dành cho môi trường phát triển hoặc tác vụ bảo trì qua CLI. Chúng không phải endpoint public.

## Migration toàn hệ thống (không seed)

Chạy một lần khi triển khai/nâng cấp, **không đặt lệnh này trong cron chạy thường xuyên**:

```bash
php scripts/migrate_all.php
```

Bao gồm bảng nền tảng, cột ngôn ngữ/template bài viết, cấu hình SEO/template của trình sửa giao diện, schema y tế và hàng chờ ảnh. Nút migration trong admin gọi cùng logic này và chỉ ghi log bảo trì, không tạo Home/blog/sản phẩm/dự án/cấu hình demo nữa. Nội dung, tài khoản, prompt và dữ liệu hiện có không bị reset.

Mọi request web và cả CLI cron/worker đều không được tự chạy DDL. Chỉ phạm vi bảo trì rõ ràng mới mở quyền migration; quyền được đóng lại kể cả khi có lỗi. Kiểm tra cột/ngôn ngữ trong runtime chỉ đọc; API/worker chưa có schema sẽ báo cần chạy migration thay vì tự ALTER/CREATE.

## Migration y tế (không thêm dữ liệu mẫu)

Chạy một lần khi triển khai/nâng cấp, bằng PHP CLI trên hosting:

```bash
php scripts/migrate_medical_directory.php
```

Lệnh tạo/bổ sung bảng, cột, liên kết, prompt mặc định và schema hàng chờ ảnh còn thiếu. Không xoá hồ sơ, không tính lại đánh giá toàn bộ, không ghi đè prompt đã sửa. Các helper migration cũ được giữ để tương thích nhưng không thực hiện SQL trong runtime. API bác sĩ kiểm tra schema chỉ đọc và báo 503 nếu chưa sẵn sàng.

## Seed dữ liệu riêng

Seed demo là tùy chọn cho môi trường test, **không chạy trên dữ liệu thật chỉ để tăng tốc**:

```bash
php scripts/seeds/seed_medical_directory.php --demo
```

Không còn reset/TRUNCATE tự động. Chạy không có `--demo` hoặc truy cập script qua HTTP sẽ không seed. Không mở/lưu/xóa bài nào tự gọi seed nữa. Nếu seed thêm review mẫu, chỉ tính lại cơ sở liên quan và đánh dấu cache một lần; không quét toàn bộ cơ sở.

Demo cũ Home/blog/sản phẩm/dự án/cấu hình phòng tắm đã chuyển hoàn toàn ra khỏi migration:

```bash
php scripts/seeds/seed_legacy_content.php --demo
```

Lệnh này chỉ dành cho test bản mẫu cũ, **không cần chạy trên website MedReview thật**. Không gọi từ admin hoặc cron. Không chạy seed nào để nâng cấp schema hay tăng tốc.

Trang bài viết và trình sửa giao diện chỉ đọc/hiển thị nội dung dự phòng khi template còn trống; không còn tự UPDATE nội dung khi xem trang. Nếu muốn lưu sẵn nội dung dự phòng vào template trống, chạy chủ động:

```bash
php scripts/seeds/seed_post_templates.php --initialize-empty
```

Không thay nội dung đã có, kể cả khi người khác vừa điền nội dung trong lúc lệnh chạy. Lệnh này không được gọi từ migration, trang xem bài hay cron.

Seed nhập danh sách cơ sở có nguồn (chạy migration trước; đọc mã và sao lưu trước khi nhập):

```bash
php scripts/seeds/seed_verified_facilities.php
```

JSON tìm kiếm tiếp tục được đánh dấu cũ sau thay đổi dữ liệu. Có thể dựng lại riêng bằng cron/lệnh `php cron/medical-search-cache.php`; không build JSON đồng bộ trong thao tác lưu/xóa quản trị.

Kiểm tra guard HTTP/CLI, không có DDL/seed trong entry point và rà toàn bộ PHP runtime (DB giả, không kết nối DB thật):

```bash
php tests/medical_maintenance_test.php
```
