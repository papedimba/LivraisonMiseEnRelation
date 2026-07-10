<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$livreurId = Auth::requireRole('livreur');

$db = Database::getConnection();

// Derniere course livree par ce livreur qui n'a pas encore ete evaluee par lui.
$stmt = $db->prepare(
    "SELECT c.id, c.reference, u.prenom AS client_prenom, u.nom AS client_nom,
            u.note_client, u.nombre_evaluations_client
     FROM commandes c
     JOIN users u ON u.id = c.client_id
     LEFT JOIN evaluations_client ec ON ec.commande_id = c.id
     WHERE c.livreur_id = :livreur_id AND c.statut = 'livree' AND ec.id IS NULL
     ORDER BY c.delivered_at DESC
     LIMIT 1"
);
$stmt->execute(['livreur_id' => $livreurId]);
$commande = $stmt->fetch();

Response::success(['a_noter' => $commande ?: null]);
