<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/Response.php';

$db = Database::getConnection();

$stmt = $db->query('SELECT id, code, nom, icone, tarif_base, tarif_km, supplement_express FROM types_livraison WHERE actif = 1');

Response::success(['types' => $stmt->fetchAll()]);
