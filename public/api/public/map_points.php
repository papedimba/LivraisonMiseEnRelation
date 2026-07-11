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

// Restriction geographique. Deux modes :
//   - bbox=minLat,minLng,maxLat,maxLng (cadre visible, page carte) ;
//   - lat,lng (+ rayon_km) : restreint aux repere de la ZONE / VILLE autour du
//     client (rayon "taille ville" par defaut) pour ne pas melanger les villes.
$latCentre = null;
$lngCentre = null;
$rayonKm = 0.0;

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
} elseif (isset($_GET['lat'], $_GET['lng']) && $_GET['lat'] !== '' && $_GET['lng'] !== '') {
    $latCentre = (float) $_GET['lat'];
    $lngCentre = (float) $_GET['lng'];
    $rayonKm = isset($_GET['rayon_km']) ? max(1.0, (float) $_GET['rayon_km']) : 40.0;
    // Pre-filtre par cadre englobant (utilise l'index), affine ensuite en PHP.
    $dLat = $rayonKm / 111.0;
    $dLng = $rayonKm / (111.0 * max(0.1, cos(deg2rad($latCentre))));
    $sql .= ' AND latitude BETWEEN :minlat AND :maxlat AND longitude BETWEEN :minlng AND :maxlng';
    $params['minlat'] = $latCentre - $dLat;
    $params['maxlat'] = $latCentre + $dLat;
    $params['minlng'] = $lngCentre - $dLng;
    $params['maxlng'] = $lngCentre + $dLng;
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
$points = $stmt->fetchAll();

// Affinage circulaire precis autour du centre (le pre-filtre SQL est un carre).
if ($latCentre !== null && $lngCentre !== null) {
    $points = array_values(array_filter($points, static function (array $p) use ($latCentre, $lngCentre, $rayonKm): bool {
        return haversine_distance_km($latCentre, $lngCentre, (float) $p['latitude'], (float) $p['longitude']) <= $rayonKm;
    }));
}

Response::success(['points' => $points, 'categories' => carto_categories()]);
