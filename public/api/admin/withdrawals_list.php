<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

$statut = clean_str($_GET['statut'] ?? '');

$sql = "SELECT r.*, u.nom, u.prenom, u.telephone
        FROM retraits r
        JOIN users u ON u.id = r.livreur_id
        WHERE 1=1";
$params = [];

if ($statut !== '') {
    $sql .= ' AND r.statut = :statut';
    $params['statut'] = $statut;
}

$sql .= ' ORDER BY r.created_at DESC LIMIT 200';

$stmt = $db->prepare($sql);
$stmt->execute($params);

Response::success(['retraits' => $stmt->fetchAll()]);
