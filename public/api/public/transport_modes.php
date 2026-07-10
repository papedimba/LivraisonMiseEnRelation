<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/Response.php';

$db = Database::getConnection();

$stmt = $db->query(
    'SELECT id, code, nom, icone, multiplicateur FROM moyens_transport WHERE actif = 1 ORDER BY multiplicateur ASC, nom ASC'
);

Response::success(['moyens' => $stmt->fetchAll()]);
