<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/Response.php';

$db = Database::getConnection();

$stmt = $db->query(
    'SELECT q.id, q.nom, v.nom AS ville
     FROM quartiers q
     JOIN villes v ON v.id = q.ville_id
     WHERE q.actif = 1
     ORDER BY v.nom, q.nom'
);

Response::success(['quartiers' => $stmt->fetchAll()]);
