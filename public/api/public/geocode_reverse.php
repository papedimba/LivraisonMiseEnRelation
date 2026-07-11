<?php
declare(strict_types=1);

/**
 * Geocodage inverse (coordonnees -> adresse) via Nominatim (OpenStreetMap).
 * Utilise quand le client place un point directement sur la carte : on propose
 * un libelle d'adresse pour le champ. Degrade proprement (adresse vide) hors
 * ligne ; le front applique alors un libelle base sur les coordonnees.
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/Response.php';

if (!isset($_GET['lat'], $_GET['lng']) || $_GET['lat'] === '' || $_GET['lng'] === '') {
    Response::error('Coordonnees requises.', 422);
}
$lat = (float) $_GET['lat'];
$lng = (float) $_GET['lng'];
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    Response::error('Coordonnees invalides.', 422);
}

// Cache fichier (30 jours) sur coordonnees arrondies (~11 m).
$cacheDir = STORAGE_PATH . '/cache/geocode';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$cle = 'rev_' . number_format($lat, 5, '.', '') . '_' . number_format($lng, 5, '.', '');
$cacheFile = $cacheDir . '/' . md5($cle) . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 2592000)) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached)) {
        Response::success($cached + ['source' => 'cache']);
    }
}

$params = [
    'format' => 'json',
    'lat' => $lat,
    'lon' => $lng,
    'zoom' => 18,
    'addressdetails' => 0,
    'accept-language' => 'fr',
];
$email = (string) env('NOMINATIM_EMAIL', '');
if ($email !== '') {
    $params['email'] = $email;
}
$url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query($params);

$adresse = '';
$adresseComplete = '';

$ua = 'CityHub225/1.0 (' . (PUBLIC_BASE_URL !== '' ? PUBLIC_BASE_URL : 'https://cityhub225') . ')';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_USERAGENT => $ua,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_FOLLOWLOCATION => true,
]);
$proxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy');
if ($proxy) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    if (is_file('/root/.ccr/ca-bundle.crt')) {
        curl_setopt($ch, CURLOPT_CAINFO, '/root/.ccr/ca-bundle.crt');
    }
}
$reponse = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($reponse !== false && $code === 200) {
    $data = json_decode((string) $reponse, true);
    if (is_array($data) && isset($data['display_name'])) {
        $adresseComplete = (string) $data['display_name'];
        $segments = array_map('trim', explode(',', $adresseComplete));
        $adresse = implode(', ', array_slice($segments, 0, 2));
    }
}

$resultat = ['adresse' => $adresse, 'adresse_complete' => $adresseComplete];
if ($adresse !== '') {
    @file_put_contents($cacheFile, json_encode($resultat, JSON_UNESCAPED_UNICODE));
}

Response::success($resultat + ['source' => 'nominatim']);
