<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

$statut = clean_str($_GET['statut'] ?? '');
$methode = clean_str($_GET['methode'] ?? '');

$sql = "SELECT p.*, c.reference AS commande_reference, c.client_id
        FROM paiements p
        JOIN commandes c ON c.id = p.commande_id
        WHERE 1=1";
$params = [];

if ($statut !== '') {
    $sql .= ' AND p.statut = :statut';
    $params['statut'] = $statut;
}
if ($methode !== '') {
    $sql .= ' AND p.methode = :methode';
    $params['methode'] = $methode;
}

$sql .= ' ORDER BY p.created_at DESC LIMIT 200';

$stmt = $db->prepare($sql);
$stmt->execute($params);

Response::success(['paiements' => $stmt->fetchAll()]);
