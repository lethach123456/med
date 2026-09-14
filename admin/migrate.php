<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

admin_require_login();

flash_toast_set('info', 'Mở Thiết lập hệ thống để kiểm tra và khởi tạo dữ liệu.', 'fa-solid fa-circle-info');
header('Location: /admin/dashboard.php#section-db');
exit;
