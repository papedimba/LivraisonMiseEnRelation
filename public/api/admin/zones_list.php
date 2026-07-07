<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

$villes = $db->query(
    'SELECT v.id, v.nom, v.actif,
            (SELECT COUNT(*) FROM quartiers q WHERE q.ville_id = v.id) AS nb_quartiers
     FROM villes v ORDER BY v.nom'
)->fetchAll();

$quartiers = $db->query(
    'SELECT q.id, q.ville_id, q.nom, q.actif FROM quartiers q ORDER BY q.nom'
)->fetchAll();

Response::success(['villes' => $villes, 'quartiers' => $quartiers]);
