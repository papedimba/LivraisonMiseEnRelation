<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

$stmt = $db->query(
    "SELECT u.id, u.nom, u.prenom, u.email, u.telephone, u.created_at,
            cd.nom_boutique, cd.categorie, cd.description, cd.adresse, cd.statut_validation
     FROM commercant_details cd
     JOIN users u ON u.id = cd.user_id
     WHERE cd.statut_validation = 'en_attente'
     ORDER BY u.created_at ASC"
);

Response::success(['commercants' => $stmt->fetchAll()]);
