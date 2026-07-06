<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$userId = Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$notifId = isset($body['notification_id']) ? (int) $body['notification_id'] : null;

$db = Database::getConnection();

if ($notifId) {
    $stmt = $db->prepare('UPDATE notifications SET lu = 1 WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $notifId, 'user_id' => $userId]);
} else {
    $stmt = $db->prepare('UPDATE notifications SET lu = 1 WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
}

Response::success([], 'Notifications marquees comme lues.');
