<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$commercantId = Auth::requireRole('commercant');

$db = Database::getConnection();

$stmt = $db->prepare(
    "SELECT
        COUNT(*) AS total_commandes,
        SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) AS commandes_livrees,
        SUM(CASE WHEN statut = 'annulee' THEN 1 ELSE 0 END) AS commandes_annulees,
        COALESCE(SUM(CASE WHEN statut = 'livree' THEN montant_estime ELSE 0 END), 0) AS chiffre_affaires
     FROM commandes
     WHERE commercant_id = :id"
);
$stmt->execute(['id' => $commercantId]);
$global = $stmt->fetch();

$stmt = $db->prepare(
    "SELECT DATE(created_at) AS jour, COUNT(*) AS nb, COALESCE(SUM(montant_estime), 0) AS montant
     FROM commandes
     WHERE commercant_id = :id AND created_at >= (NOW() - INTERVAL 30 DAY)
     GROUP BY DATE(created_at)
     ORDER BY jour ASC"
);
$stmt->execute(['id' => $commercantId]);
$parJour = $stmt->fetchAll();

$stmt = $db->prepare(
    "SELECT p.id, p.nom, COUNT(cp.id) AS nb_ventes, COALESCE(SUM(cp.quantite), 0) AS quantite_vendue
     FROM produits p
     LEFT JOIN commande_produits cp ON cp.produit_id = p.id
     WHERE p.commercant_id = :id
     GROUP BY p.id, p.nom
     ORDER BY quantite_vendue DESC
     LIMIT 10"
);
$stmt->execute(['id' => $commercantId]);
$topProduits = $stmt->fetchAll();

Response::success([
    'global' => $global,
    'evolution_30_jours' => $parJour,
    'top_produits' => $topProduits,
]);
