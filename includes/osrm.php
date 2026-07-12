<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Map-matching OSRM : cale une trace GPS brute sur le reseau routier.
 *
 * Utilise le service /match d'OSRM (serveur public par defaut). Retourne la
 * geometrie calee sous forme de tableau de points [lat, lng], ou null en cas
 * d'echec (le front se rabat alors sur la trace brute).
 *
 * @param array $points liste de [lat, lng] dans l'ordre du trajet
 * @return array<int, array{0: float, 1: float}>|null
 */
function osrm_match_trace(array $points): ?array
{
    if (count($points) < 2) {
        return null;
    }
    // OSRM /match accepte un nombre limite de points : on echantillonne si besoin.
    if (count($points) > 100) {
        $points = osrm_echantillonner($points, 100);
    }

    $base = getenv('OSRM_BASE_URL') ?: 'https://router.project-osrm.org';
    $coords = implode(';', array_map(static fn ($p) => $p[1] . ',' . $p[0], $points)); // lon,lat
    $url = rtrim($base, '/') . '/match/v1/driving/' . $coords . '?geometries=geojson&overview=full&tidy=true';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'CityHub225/1.0',
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

    if ($reponse === false || $code !== 200) {
        return null;
    }
    $data = json_decode((string) $reponse, true);
    if (!is_array($data) || ($data['code'] ?? '') !== 'Ok' || empty($data['matchings'][0]['geometry']['coordinates'])) {
        return null;
    }

    $coordonnees = $data['matchings'][0]['geometry']['coordinates'];
    $trace = [];
    foreach ($coordonnees as $c) {
        // GeoJSON : [lon, lat] -> on stocke [lat, lng].
        $trace[] = [(float) $c[1], (float) $c[0]];
    }
    return count($trace) >= 2 ? $trace : null;
}

/**
 * Itineraire routier entre deux points via le service /route d'OSRM.
 *
 * Retourne la geometrie de l'itineraire (depart -> arrivee) cale sur les rues
 * sous forme de tableau de points [lat, lng], ou null en cas d'echec (le front
 * se rabat alors sur une ligne droite entre les deux points).
 *
 * @param array{0: float, 1: float} $depart  [lat, lng]
 * @param array{0: float, 1: float} $arrivee [lat, lng]
 * @return array<int, array{0: float, 1: float}>|null
 */
function osrm_route(array $depart, array $arrivee): ?array
{
    $base = getenv('OSRM_BASE_URL') ?: 'https://router.project-osrm.org';
    // OSRM attend lon,lat.
    $coords = $depart[1] . ',' . $depart[0] . ';' . $arrivee[1] . ',' . $arrivee[0];
    $url = rtrim($base, '/') . '/route/v1/driving/' . $coords . '?overview=full&geometries=geojson';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'CityHub225/1.0',
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

    if ($reponse === false || $code !== 200) {
        return null;
    }
    $data = json_decode((string) $reponse, true);
    if (!is_array($data) || ($data['code'] ?? '') !== 'Ok' || empty($data['routes'][0]['geometry']['coordinates'])) {
        return null;
    }

    $trace = [];
    foreach ($data['routes'][0]['geometry']['coordinates'] as $c) {
        // GeoJSON : [lon, lat] -> [lat, lng].
        $trace[] = [(float) $c[1], (float) $c[0]];
    }
    return count($trace) >= 2 ? $trace : null;
}

/**
 * Reduit une liste de points a $max elements en conservant premier/dernier.
 */
function osrm_echantillonner(array $points, int $max): array
{
    $n = count($points);
    if ($n <= $max) {
        return $points;
    }
    $pas = ($n - 1) / ($max - 1);
    $out = [];
    for ($i = 0; $i < $max; $i++) {
        $out[] = $points[(int) round($i * $pas)];
    }
    return $out;
}

/**
 * Cale la trace d'une commande livree et met le resultat en cache
 * (table routes_matchees). Best-effort : ne leve jamais d'exception bloquante.
 */
function route_matcher_commande(PDO $db, int $commandeId): void
{
    if (parametre($db, 'route_matching', '1') !== '1') {
        return;
    }
    // Deja calee ?
    $stmt = $db->prepare('SELECT commande_id FROM routes_matchees WHERE commande_id = :id');
    $stmt->execute(['id' => $commandeId]);
    if ($stmt->fetch()) {
        return;
    }

    $stmt = $db->prepare(
        'SELECT latitude, longitude FROM suivi_positions WHERE commande_id = :id ORDER BY created_at ASC'
    );
    $stmt->execute(['id' => $commandeId]);
    $points = array_map(static fn ($r) => [(float) $r['latitude'], (float) $r['longitude']], $stmt->fetchAll());

    $trace = osrm_match_trace($points);
    if ($trace === null) {
        return;
    }

    $db->prepare('INSERT INTO routes_matchees (commande_id, geojson) VALUES (:id, :geojson)')
        ->execute(['id' => $commandeId, 'geojson' => json_encode($trace)]);
}
