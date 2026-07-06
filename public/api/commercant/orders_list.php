<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$commercantId = Auth::requireRole('commercant');

$db = Database::getConnection();

$statut = $_GET['statut'] ?? null;

$sql = "SELECT c.*, tl.nom AS type_nom, u.nom AS client_nom, u.prenom AS client_prenom, u.telephone AS client_telephone
        FROM commandes c
        JOIN types_livraison tl ON tl.id = c.type_livraison_id
        JOIN users u ON u.id = c.client_id
        WHERE c.commercant_id = :commercant_id";
$params = ['commercant_id' => $commercantId];

if ($statut) {
    $sql .= ' AND c.statut = :statut';
    $params['statut'] = $statut;
}

$sql .= ' ORDER BY c.created_at DESC LIMIT 100';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

foreach ($commandes as &$commande) {
    $stmtLignes = $db->prepare(
        'SELECT cp.quantite, cp.prix_unitaire, p.nom
         FROM commande_produits cp
         JOIN produits p ON p.id = cp.produit_id
         WHERE cp.commande_id = :id'
    );
    $stmtLignes->execute(['id' => $commande['id']]);
    $commande['produits'] = $stmtLignes->fetchAll();
}
unset($commande);

Response::success(['commandes' => $commandes]);
