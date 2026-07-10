<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

$stmt = $db->query(
    'SELECT id, code, nom, icone, tarif_base, tarif_km, supplement_express, actif
     FROM types_livraison ORDER BY nom ASC'
);

Response::success(['types' => $stmt->fetchAll()]);
