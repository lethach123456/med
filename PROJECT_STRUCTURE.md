# Cấu trúc dự án MedReview

## Public pages

- Các file PHP ở thư mục gốc là entry point public; giữ nguyên vị trí để không đổi URL và các route hiện tại.
- Các module PHP dùng chung như `db.php`, `medical_directory.php`, `medical_search_cache.php` và `toplist_directory.php` cũng đang ở root vì nhiều entry point/API dùng đường dẫn `__DIR__` tới chúng.
- `Tem/` chứa layout dùng chung: header, footer và trang chủ.
- `api/medical/` chứa API public cho directory, tìm kiếm, review và toplist.

## Giao diện

- `assets/css/core/`
  - `ui-2026.css`: giao diện dùng chung, header/footer và responsive.
  - `shared-typography.css`: typography dùng cho trang dữ liệu.
- `assets/css/pages/`
  - CSS chỉ áp dụng cho một trang chi tiết cụ thể.
- `assets/js/`
  - JavaScript dùng chung phía frontend, hiện có `medical-global-search.js`.

## Tài liệu và tham khảo

- `docs/research/`: bài mẫu và ghi chú nguồn tham khảo.
- `docs/design/`: ảnh mockup và tài liệu thiết kế.
- `PROJECT_MEMORY.md`, `PROJECT_STRUCTURE.md`: ghi chú và sơ đồ cấu trúc dự án.

## Nội bộ

- `admin/`: quản trị và API quản trị.
- `cron/`: entry point chạy định kỳ.
- `scripts/debug/`: tiện ích chẩn đoán, chỉ chạy qua CLI.
- `scripts/seeds/`: seed dữ liệu, chỉ chạy qua CLI.

Các bản PHP legacy đã được gỡ khỏi cây dự án sau khi rà soát tham chiếu. Nếu cần khôi phục, có thể lấy lại từ lịch sử Git.

Khi thêm CSS mới, đặt vào `assets/css/core` nếu được dùng từ hai trang trở lên; nếu chỉ dùng một trang, đặt vào `assets/css/pages`.
