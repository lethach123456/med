<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

json_response([
    'ok' => false,
    'message' => 'Chức năng Setup đã được gỡ khỏi Admin.',
], 410);
