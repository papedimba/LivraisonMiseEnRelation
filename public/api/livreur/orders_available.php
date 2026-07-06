<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$livreurId = Auth::requireRole('livreur');

$db = Database::getConnection();

$stmt = $db->prepare("SELECT latitude, longitude FROM livreur_details WHERE user_id = :id");
$stmt->execute(['id' => $livreurId]);
$livreur = $stmt->fetch();

$stmt = $db->prepare(
    "SELECT c.*, tl.nom AS type_nom, tl.icone,
            comm.nom_boutique
     FROM commandes c
     JOIN types_livraison tl ON tl.id = c.type_livraison_id
     LEFT JOIN commercant_details comm ON comm.user_id = c.commercant_id
     WHERE c.statut = 'en_attente'
     ORDER BY c.created_at ASC
     LIMIT 50"
);
$stmt->execute();
$commandes = $stmt->fetchAll();

if ($livreur && $livreur['latitude'] !== null) {
    foreach ($commandes as &$commande) {
        $commande['distance_depuis_moi_km'] = haversine_distance_km(
            (float) $livreur['latitude'],
            (float) $livreur['longitude'],
            (float) $commande['lat_depart'],
            (float) $commande['lng_depart']
        );
    }
    unset($commande);
    usort($commandes, fn($a, $b) => $a['distance_depuis_moi_km'] <=> $b['distance_depuis_moi_km']);
}

Response::success(['commandes' => $commandes]);
