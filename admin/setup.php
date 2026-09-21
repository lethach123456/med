<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
admin_require_login();

header('Location: ' . admin_url('dashboard.php'), true, 302);
exit;
