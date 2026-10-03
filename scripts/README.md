# Scripts nội bộ

`debug/` và `seeds/` chỉ dành cho môi trường phát triển hoặc tác vụ bảo trì qua CLI. Chúng không phải endpoint public.

## Migration y tế (không thêm dữ liệu mẫu)

Chạy một lần khi triển khai/nâng cấp, bằng PHP CLI trên hosting:

```bash
php scripts/migrate_medical_directory.php
```

Lệnh tạo/bổ sung bảng, cột, liên kết và prompt mặc định còn thiếu. Không xoá hồ sơ, không tính lại đánh giá toàn bộ, không ghi đè prompt đã sửa. Các helper migration cũ được giữ để tương thích nhưng không thực hiện SQL trong request web thường ngày. Nút migration hệ thống hiện có là hành động bảo trì riêng; phần y tế chỉ migrate, không seed demo. API bác sĩ kiểm tra schema chỉ đọc và báo 503 nếu chưa sẵn sàng.

## Seed dữ liệu riêng

Seed demo là tùy chọn cho môi trường test, **không chạy trên dữ liệu thật chỉ để tăng tốc**:

```bash
php scripts/seeds/seed_medical_directory.php --demo
```

Không còn reset/TRUNCATE tự động. Chạy không có `--demo` hoặc truy cập script qua HTTP sẽ không seed. Không mở/lưu/xóa bài nào tự gọi seed nữa. Nếu seed thêm review mẫu, chỉ tính lại cơ sở liên quan và đánh dấu cache một lần; không quét toàn bộ cơ sở.

Seed nhập danh sách cơ sở có nguồn (chạy migration trước; đọc mã và sao lưu trước khi nhập):

```bash
php scripts/seeds/seed_verified_facilities.php
```

JSON tìm kiếm tiếp tục được đánh dấu cũ sau thay đổi dữ liệu. Có thể dựng lại riêng bằng cron/lệnh `php cron/medical-search-cache.php`; không build JSON đồng bộ trong thao tác lưu/xóa quản trị.

Kiểm tra việc tách maintenance khỏi request web (DB giả, không kết nối DB thật):

```bash
php tests/medical_maintenance_test.php
```
