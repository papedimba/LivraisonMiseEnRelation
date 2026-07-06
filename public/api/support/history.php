<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$userId = Auth::requireLogin();

$conversationId = clean_str($_GET['conversation_id'] ?? '');
if ($conversationId === '') {
    Response::error('conversation_id requis.');
}

$db = Database::getConnection();

$stmt = $db->prepare(
    'SELECT role, message, created_at FROM messages_support WHERE conversation_id = :conv AND user_id = :user_id ORDER BY created_at ASC'
);
$stmt->execute(['conv' => $conversationId, 'user_id' => $userId]);

Response::success(['messages' => $stmt->fetchAll()]);
