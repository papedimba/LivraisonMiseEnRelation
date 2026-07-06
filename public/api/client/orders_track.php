<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$clientId = Auth::requireRole('client');

$reference = clean_str($_GET['reference'] ?? '');
if ($reference === '') {
    Response::error('Reference de commande requise.');
}

$db = Database::getConnection();

$stmt = $db->prepare(
    'SELECT c.*, tl.nom AS type_nom,
            u.nom AS livreur_nom, u.prenom AS livreur_prenom, u.telephone AS livreur_telephone,
            ld.latitude AS livreur_lat, ld.longitude AS livreur_lng, ld.derniere_position_at
     FROM commandes c
     JOIN types_livraison tl ON tl.id = c.type_livraison_id
     LEFT JOIN users u ON u.id = c.livreur_id
     LEFT JOIN livreur_details ld ON ld.user_id = c.livreur_id
     WHERE c.reference = :reference AND c.client_id = :client_id'
);
$stmt->execute(['reference' => $reference, 'client_id' => $clientId]);
$commande = $stmt->fetch();

if (!$commande) {
    Response::notFound('Commande introuvable.');
}

$stmtTrajet = $db->prepare(
    'SELECT latitude, longitude, created_at FROM suivi_positions WHERE commande_id = :id ORDER BY created_at ASC LIMIT 500'
);
$stmtTrajet->execute(['id' => $commande['id']]);

Response::success([
    'commande' => $commande,
    'trajet' => $stmtTrajet->fetchAll(),
]);
