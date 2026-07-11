<?php
declare(strict_types=1);

/**
 * Geocodage d'adresses via Nominatim (OpenStreetMap).
 *
 * Complete la recherche de reperes collaboratifs par une recherche libre sur
 * la vraie carte. Resultats mis en cache (fichier) pour limiter les appels et
 * respecter la politique d'usage de Nominatim (User-Agent obligatoire,
 * ~1 requete/seconde). Degrade proprement (liste vide) si le reseau echoue.
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';

$q = clean_str($_GET['q'] ?? '');
if (mb_strlen($q) < 3) {
    Response::error('Requete trop courte (3 caracteres minimum).', 422);
}

$limite = 6;

// -- Cache fichier (24 h) -----------------------------------------------------
$cacheDir = STORAGE_PATH . '/cache/geocode';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$cacheFile = $cacheDir . '/' . md5(mb_strtolower($q)) . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached)) {
        Response::success(['resultats' => $cached, 'source' => 'cache']);
    }
}

// -- Appel Nominatim ----------------------------------------------------------
$params = [
    'format' => 'json',
    'q' => $q,
    'limit' => $limite,
    'countrycodes' => 'ci',        // priorite Cote d'Ivoire
    'accept-language' => 'fr',
    'addressdetails' => 0,
];
// L'email de contact est recommande par la politique Nominatim.
$email = (string) env('NOMINATIM_EMAIL', '');
if ($email !== '') {
    $params['email'] = $email;
}
$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query($params);

$resultats = geocode_appel_nominatim($url);

// N'ecrit en cache que les reponses exploitables (evite de figer un echec).
if (!empty($resultats)) {
    @file_put_contents($cacheFile, json_encode($resultats, JSON_UNESCAPED_UNICODE));
}

Response::success(['resultats' => $resultats, 'source' => 'nominatim']);

/**
 * Interroge Nominatim et normalise la reponse. Retourne [] en cas d'echec.
 */
function geocode_appel_nominatim(string $url): array
{
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
    // Proxy sortant eventuel (environnements avec proxy d'agent).
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

    if ($reponse === false || $code !== 200) {
        return [];
    }

    $data = json_decode((string) $reponse, true);
    if (!is_array($data)) {
        return [];
    }

    $resultats = [];
    foreach ($data as $item) {
        if (!isset($item['lat'], $item['lon'], $item['display_name'])) {
            continue;
        }
        $nomComplet = (string) $item['display_name'];
        // Libelle court : les 2 premiers segments de l'adresse.
        $segments = array_map('trim', explode(',', $nomComplet));
        $nomCourt = implode(', ', array_slice($segments, 0, 2));
        $resultats[] = [
            'nom' => $nomCourt,
            'adresse_complete' => $nomComplet,
            'latitude' => (float) $item['lat'],
            'longitude' => (float) $item['lon'],
            'type' => (string) ($item['type'] ?? ''),
        ];
    }

    return $resultats;
}
