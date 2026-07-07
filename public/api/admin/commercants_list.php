<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

$commercants = $db->query(
    "SELECT cd.user_id, cd.nom_boutique, cd.categorie, cd.statut_validation,
            cd.abonnement_premium, cd.abonnement_expire_at, cd.note_moyenne,
            u.nom, u.prenom, u.telephone
     FROM commercant_details cd
     JOIN users u ON u.id = cd.user_id
     WHERE cd.statut_validation = 'valide'
     ORDER BY cd.abonnement_premium DESC, cd.nom_boutique"
)->fetchAll();

Response::success(['commercants' => $commercants]);
