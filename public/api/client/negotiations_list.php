<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

/**
 * Liste des negociations en cours pour une commande du client (une par
 * livreur qui a propose un prix), avec le nom/note du livreur.
 */

$clientId = Auth::requireRole('client');

$db = Database::getConnection();

$reference = clean_str(input($_GET, 'reference', ''));
if ($reference === '') {
    Response::error('reference requise.');
}

$stmt = $db->prepare("SELECT id FROM commandes WHERE reference = :ref AND client_id = :client_id");
$stmt->execute(['ref' => $reference, 'client_id' => $clientId]);
$commande = $stmt->fetch();
if (!$commande) {
    Response::notFound('Commande introuvable.');
}

$stmt = $db->prepare(
    "SELECT n.livreur_id, n.montant_propose, n.propose_par, n.updated_at,
            u.nom, u.prenom, ld.note_moyenne
     FROM negociations_prix n
     JOIN users u ON u.id = n.livreur_id
     LEFT JOIN livreur_details ld ON ld.user_id = n.livreur_id
     WHERE n.commande_id = :cid AND n.statut = 'en_attente'
     ORDER BY n.updated_at DESC"
);
$stmt->execute(['cid' => $commande['id']]);

Response::success(['negociations' => $stmt->fetchAll()]);
