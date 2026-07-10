<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

// Points en attente de validation + points valides mais fortement signales.
$stmt = $db->query(
    "SELECT p.id, p.nom, p.categorie, p.description, p.latitude, p.longitude,
            p.statut, p.confirmations, p.signalements, p.created_at,
            u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.role AS auteur_role
     FROM points_carte p
     LEFT JOIN users u ON u.id = p.user_id
     WHERE p.statut = 'en_attente' OR (p.statut = 'valide' AND p.signalements >= 3)
     ORDER BY (p.statut = 'en_attente') DESC, p.signalements DESC, p.created_at ASC
     LIMIT 200"
);

Response::success(['points' => $stmt->fetchAll()]);
