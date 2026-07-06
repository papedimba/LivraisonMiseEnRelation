<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$userId = Auth::requireLogin();

$db = Database::getConnection();

$stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = :id ORDER BY created_at DESC LIMIT 50');
$stmt->execute(['id' => $userId]);
$notifications = $stmt->fetchAll();

$nonLues = $db->prepare('SELECT COUNT(*) AS nb FROM notifications WHERE user_id = :id AND lu = 0');
$nonLues->execute(['id' => $userId]);

Response::success([
    'notifications' => $notifications,
    'non_lues' => (int) $nonLues->fetch()['nb'],
]);
