<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$userId = Auth::requireLogin();

$db = Database::getConnection();

// Notifications non lues recentes, pour affichage par le service worker.
$stmt = $db->prepare(
    'SELECT id, titre, message, type, lien, created_at
     FROM notifications
     WHERE user_id = :id AND lu = 0
     ORDER BY created_at DESC
     LIMIT 5'
);
$stmt->execute(['id' => $userId]);

Response::success(['notifications' => $stmt->fetchAll()]);
