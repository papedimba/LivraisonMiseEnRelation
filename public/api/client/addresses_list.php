<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$clientId = Auth::requireRole('client');

$db = Database::getConnection();

$stmt = $db->prepare(
    'SELECT id, libelle, adresse, latitude, longitude, created_at
     FROM adresses_favorites WHERE client_id = :client_id ORDER BY created_at ASC'
);
$stmt->execute(['client_id' => $clientId]);

Response::success(['adresses' => $stmt->fetchAll()]);
