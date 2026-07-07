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
            ld.type_vehicule, ld.numero_piece, ld.statut_validation,
            (ld.piece_identite_path IS NOT NULL AND ld.piece_identite_path <> '') AS a_piece_identite,
            (ld.permis_path IS NOT NULL AND ld.permis_path <> '') AS a_permis,
            (ld.carte_grise_path IS NOT NULL AND ld.carte_grise_path <> '') AS a_carte_grise
     FROM livreur_details ld
     JOIN users u ON u.id = ld.user_id
     WHERE ld.statut_validation = 'en_attente'
     ORDER BY u.created_at ASC"
);

$livreurs = array_map(function (array $l): array {
    $l['a_piece_identite'] = (bool) $l['a_piece_identite'];
    $l['a_permis'] = (bool) $l['a_permis'];
    $l['a_carte_grise'] = (bool) $l['a_carte_grise'];
    return $l;
}, $stmt->fetchAll());

Response::success(['livreurs' => $livreurs]);
