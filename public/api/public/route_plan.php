<?php
declare(strict_types=1);

/**
 * Itineraire routier prevu entre un point de depart et un point d'arrivee, cale
 * sur les rues (OSRM). Utilise pour tracer le trajet des la confirmation d'une
 * commande (page de suivi). Degrade proprement (route vide) hors ligne : le
 * front trace alors une ligne droite entre les deux points.
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/osrm.php';

Auth::requireLogin();

foreach (['lat_depart', 'lng_depart', 'lat_arrivee', 'lng_arrivee'] as $champ) {
    if (!isset($_GET[$champ]) || $_GET[$champ] === '') {
        Response::error('Coordonnees de depart et d\'arrivee requises.', 422);
    }
}
$latD = (float) $_GET['lat_depart'];
$lngD = (float) $_GET['lng_depart'];
$latA = (float) $_GET['lat_arrivee'];
$lngA = (float) $_GET['lng_arrivee'];
foreach ([$latD, $latA] as $la) {
    if ($la < -90 || $la > 90) {
        Response::error('Coordonnees invalides.', 422);
    }
}
foreach ([$lngD, $lngA] as $lo) {
    if ($lo < -180 || $lo > 180) {
        Response::error('Coordonnees invalides.', 422);
    }
}

// Cache fichier (7 jours) sur coordonnees arrondies (~11 m) des deux extremites.
$cacheDir = STORAGE_PATH . '/cache/routes';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$cle = number_format($latD, 5, '.', '') . '_' . number_format($lngD, 5, '.', '')
     . '__' . number_format($latA, 5, '.', '') . '_' . number_format($lngA, 5, '.', '');
$cacheFile = $cacheDir . '/' . md5($cle) . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 604800)) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached)) {
        Response::success(['points' => $cached, 'source' => 'cache']);
    }
}

$trace = osrm_route([$latD, $lngD], [$latA, $lngA]);

if ($trace === null) {
    // Hors ligne / OSRM indisponible : le front tracera une ligne droite.
    Response::success(['points' => [], 'source' => 'indisponible']);
}

@file_put_contents($cacheFile, json_encode($trace));
Response::success(['points' => $trace, 'source' => 'osrm']);
