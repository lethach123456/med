# Scripts nội bộ

`debug/` và `seeds/` chỉ dành cho môi trường phát triển hoặc tác vụ bảo trì qua CLI. Chúng không phải endpoint public.

Ví dụ chạy seed:

```bash
php scripts/seeds/seed_verified_facilities.php
```

`seed_medical_directory.php` có thể reset dữ liệu; đọc mã và sao lưu database trước khi chạy.
