<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

$stmt = $db->query('SELECT id, code, nom, icone, multiplicateur, actif FROM moyens_transport ORDER BY multiplicateur ASC, nom ASC');

Response::success(['moyens' => $stmt->fetchAll()]);
