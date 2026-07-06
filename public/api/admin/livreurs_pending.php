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
            ld.type_vehicule, ld.numero_piece, ld.piece_identite_path, ld.permis_path, ld.statut_validation
     FROM livreur_details ld
     JOIN users u ON u.id = ld.user_id
     WHERE ld.statut_validation = 'en_attente'
     ORDER BY u.created_at ASC"
);

Response::success(['livreurs' => $stmt->fetchAll()]);
