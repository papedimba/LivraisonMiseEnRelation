<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireLogin();

Response::success([
    'public_key' => VAPID_PUBLIC_KEY,
    'active' => VAPID_PUBLIC_KEY !== '',
]);
