<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$livreurId = Auth::requireRole('livreur');

$db = Database::getConnection();

// Offre en attente, non expiree, dont la commande est toujours disponible.
$stmt = $db->prepare(
    "SELECT o.id AS offre_id, o.distance_km, o.expires_at,
            c.id AS commande_id, c.reference, c.adresse_depart, c.adresse_arrivee,
            c.montant_estime, c.mode_paiement, tl.nom AS type_nom,
            comm.nom_boutique
     FROM dispatch_offres o
     JOIN commandes c ON c.id = o.commande_id
     JOIN types_livraison tl ON tl.id = c.type_livraison_id
     LEFT JOIN commercant_details comm ON comm.user_id = c.commercant_id
     WHERE o.livreur_id = :lid
       AND o.statut = 'en_attente'
       AND o.expires_at >= NOW()
       AND c.statut = 'en_attente'
     ORDER BY o.id DESC LIMIT 1"
);
$stmt->execute(['lid' => $livreurId]);
$offre = $stmt->fetch();

Response::success([
    'offre' => $offre ?: null,
    'secondes_restantes' => $offre ? max(0, strtotime($offre['expires_at']) - time()) : 0,
]);
