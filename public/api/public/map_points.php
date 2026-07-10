<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/carto.php';

$db = Database::getConnection();

$sql = "SELECT id, nom, categorie, description, latitude, longitude, confirmations, signalements
        FROM points_carte WHERE statut = 'valide'";
$params = [];

// Filtre par cadre geographique (bbox=minLat,minLng,maxLat,maxLng).
$bbox = clean_str($_GET['bbox'] ?? '');
if ($bbox !== '') {
    $p = array_map('floatval', explode(',', $bbox));
    if (count($p) === 4) {
        $sql .= ' AND latitude BETWEEN :minlat AND :maxlat AND longitude BETWEEN :minlng AND :maxlng';
        $params['minlat'] = min($p[0], $p[2]);
        $params['maxlat'] = max($p[0], $p[2]);
        $params['minlng'] = min($p[1], $p[3]);
        $params['maxlng'] = max($p[1], $p[3]);
    }
}

$categorie = clean_str($_GET['categorie'] ?? '');
if ($categorie !== '' && in_array($categorie, carto_categories(), true)) {
    $sql .= ' AND categorie = :categorie';
    $params['categorie'] = $categorie;
}

$q = clean_str($_GET['q'] ?? '');
if ($q !== '') {
    // PDO non emule : un meme placeholder ne peut pas apparaitre deux fois.
    $sql .= ' AND (nom LIKE :q_nom OR description LIKE :q_desc)';
    $params['q_nom'] = '%' . $q . '%';
    $params['q_desc'] = '%' . $q . '%';
}

$sql .= ' ORDER BY confirmations DESC, id DESC LIMIT 500';

$stmt = $db->prepare($sql);
$stmt->execute($params);

Response::success(['points' => $stmt->fetchAll(), 'categories' => carto_categories()]);
