<?php
declare(strict_types=1);

/**
 * Routes reellement parcourues par les livreurs (crowdsourced), reconstituees a
 * partir des traces GPS des livraisons terminees. Renvoie des polylignes
 * regroupees par commande, dans le cadre geographique demande.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireLogin();

$db = Database::getConnection();

$sql = "SELECT sp.commande_id, sp.latitude, sp.longitude
        FROM suivi_positions sp
        JOIN commandes c ON c.id = sp.commande_id AND c.statut = 'livree'";
$params = [];

$bbox = clean_str($_GET['bbox'] ?? '');
if ($bbox !== '') {
    $p = array_map('floatval', explode(',', $bbox));
    if (count($p) === 4) {
        $sql .= ' WHERE sp.latitude BETWEEN :minlat AND :maxlat AND sp.longitude BETWEEN :minlng AND :maxlng';
        $params['minlat'] = min($p[0], $p[2]);
        $params['maxlat'] = max($p[0], $p[2]);
        $params['minlng'] = min($p[1], $p[3]);
        $params['maxlng'] = max($p[1], $p[3]);
    }
}

// Borne le volume (petite ville : largement suffisant).
$sql .= ' ORDER BY sp.commande_id, sp.created_at ASC LIMIT 8000';

$stmt = $db->prepare($sql);
$stmt->execute($params);

// Regroupe les positions en polylignes par commande.
$parCommande = [];
foreach ($stmt->fetchAll() as $row) {
    $parCommande[(int) $row['commande_id']][] = [(float) $row['latitude'], (float) $row['longitude']];
}

$routes = [];
foreach ($parCommande as $commandeId => $points) {
    if (count($points) < 2) {
        continue; // une polyligne exige au moins deux points
    }
    $routes[] = ['commande_id' => $commandeId, 'points' => $points];
}

Response::success(['routes' => $routes, 'nombre' => count($routes)]);
