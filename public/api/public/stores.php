<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireLogin();

$categorie = clean_str($_GET['categorie'] ?? '');

$db = Database::getConnection();

$sql = "SELECT cd.*, u.nom, u.prenom
        FROM commercant_details cd
        JOIN users u ON u.id = cd.user_id
        WHERE cd.statut_validation = 'valide'";
$params = [];

if ($categorie !== '') {
    $sql .= ' AND cd.categorie = :categorie';
    $params['categorie'] = $categorie;
}

$sql .= ' ORDER BY cd.abonnement_premium DESC, cd.note_moyenne DESC LIMIT 100';

$stmt = $db->prepare($sql);
$stmt->execute($params);

Response::success(['boutiques' => $stmt->fetchAll()]);
