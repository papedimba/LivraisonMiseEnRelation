<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

$statut = clean_str($_GET['statut'] ?? '');
$reference = clean_str($_GET['reference'] ?? '');

$sql = "SELECT c.*, tl.nom AS type_nom,
               client.nom AS client_nom, client.prenom AS client_prenom,
               livreur.nom AS livreur_nom, livreur.prenom AS livreur_prenom,
               cd.nom_boutique
        FROM commandes c
        JOIN types_livraison tl ON tl.id = c.type_livraison_id
        JOIN users client ON client.id = c.client_id
        LEFT JOIN users livreur ON livreur.id = c.livreur_id
        LEFT JOIN commercant_details cd ON cd.user_id = c.commercant_id
        WHERE 1=1";
$params = [];

if ($statut !== '') {
    $sql .= ' AND c.statut = :statut';
    $params['statut'] = $statut;
}
if ($reference !== '') {
    $sql .= ' AND c.reference LIKE :reference';
    $params['reference'] = '%' . $reference . '%';
}

$sql .= ' ORDER BY c.created_at DESC LIMIT 200';

$stmt = $db->prepare($sql);
$stmt->execute($params);

Response::success(['commandes' => $stmt->fetchAll()]);
