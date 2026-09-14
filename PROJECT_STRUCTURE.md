# Cấu trúc dự án MedReview

## Public pages

- Các file PHP ở thư mục gốc là entry point public đang được route hoặc liên kết từ giao diện.
- `Tem/` chứa layout dùng chung: header, footer và trang chủ.
- `api/medical/` chứa API public cho directory, tìm kiếm, review và toplist.

## Giao diện

- `assets/css/core/`
  - `ui-2026.css`: giao diện dùng chung, header/footer và responsive.
  - `shared-typography.css`: typography dùng cho trang dữ liệu.
- `assets/css/pages/`
  - CSS chỉ áp dụng cho một trang chi tiết cụ thể.

## Nội bộ

- `admin/`: quản trị và API quản trị.
- `cron/`: entry point chạy định kỳ.
- `scripts/debug/`: tiện ích chẩn đoán, chỉ chạy qua CLI.
- `scripts/seeds/`: seed dữ liệu, chỉ chạy qua CLI.

## Legacy

- `legacy/pages/`: bản trang cũ không còn được route công khai. Không dùng làm nguồn phát triển mới.

Khi thêm CSS mới, đặt vào `assets/css/core` nếu được dùng từ hai trang trở lên; nếu chỉ dùng một trang, đặt vào `assets/css/pages`.
