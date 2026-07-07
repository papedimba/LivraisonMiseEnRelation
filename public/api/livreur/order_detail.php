<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$livreurId = Auth::requireRole('livreur');

$commandeId = (int) ($_GET['commande_id'] ?? 0);
if ($commandeId <= 0) {
    Response::error('commande_id requis.');
}

$db = Database::getConnection();

$stmt = $db->prepare(
    'SELECT c.id, c.reference, c.statut, c.instructions,
            c.adresse_depart, c.lat_depart, c.lng_depart,
            c.adresse_arrivee, c.lat_arrivee, c.lng_arrivee,
            c.montant_estime, c.mode_paiement,
            tl.nom AS type_nom,
            u.nom AS client_nom, u.prenom AS client_prenom, u.telephone AS client_telephone,
            comm.nom_boutique
     FROM commandes c
     JOIN types_livraison tl ON tl.id = c.type_livraison_id
     JOIN users u ON u.id = c.client_id
     LEFT JOIN commercant_details comm ON comm.user_id = c.commercant_id
     WHERE c.id = :id AND c.livreur_id = :livreur_id'
);
$stmt->execute(['id' => $commandeId, 'livreur_id' => $livreurId]);
$commande = $stmt->fetch();

if (!$commande) {
    Response::notFound('Course introuvable pour ce livreur.');
}

Response::success(['commande' => $commande]);
