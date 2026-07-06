<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$commercantId = Auth::requireRole('commercant');

$db = Database::getConnection();

$stmt = $db->prepare('SELECT * FROM produits WHERE commercant_id = :id ORDER BY created_at DESC');
$stmt->execute(['id' => $commercantId]);

Response::success(['produits' => $stmt->fetchAll()]);
