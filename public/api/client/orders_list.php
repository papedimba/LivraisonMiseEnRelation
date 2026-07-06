<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$clientId = Auth::requireRole('client');

$db = Database::getConnection();

$statut = $_GET['statut'] ?? null;
$sql = "SELECT c.*, tl.nom AS type_nom, tl.icone,
               u.nom AS livreur_nom, u.prenom AS livreur_prenom, u.telephone AS livreur_telephone,
               ld.note_moyenne AS livreur_note
        FROM commandes c
        JOIN types_livraison tl ON tl.id = c.type_livraison_id
        LEFT JOIN users u ON u.id = c.livreur_id
        LEFT JOIN livreur_details ld ON ld.user_id = c.livreur_id
        WHERE c.client_id = :client_id";

$params = ['client_id' => $clientId];

if ($statut) {
    $sql .= ' AND c.statut = :statut';
    $params['statut'] = $statut;
}

$sql .= ' ORDER BY c.created_at DESC LIMIT 100';

$stmt = $db->prepare($sql);
$stmt->execute($params);

Response::success(['commandes' => $stmt->fetchAll()]);
