<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

/**
 * Liste des negociations en cours du livreur connecte (toutes commandes
 * confondues), avec le montant courant et qui doit repondre.
 */

$livreurId = Auth::requireRole('livreur');

$db = Database::getConnection();

$stmt = $db->prepare(
    "SELECT n.commande_id, n.montant_propose, n.propose_par, n.updated_at,
            c.reference, c.montant_estime AS montant_initial, c.adresse_depart, c.adresse_arrivee
     FROM negociations_prix n
     JOIN commandes c ON c.id = n.commande_id
     WHERE n.livreur_id = :lid AND n.statut = 'en_attente' AND c.statut = 'en_attente'
     ORDER BY n.updated_at DESC"
);
$stmt->execute(['lid' => $livreurId]);

Response::success(['negociations' => $stmt->fetchAll()]);
