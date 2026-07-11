<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Cartographie collaborative : helpers partages.
 */

function carto_categories(): array
{
    return ['repere', 'commerce', 'carrefour', 'quartier', 'sante', 'education', 'service_public', 'autre'];
}

/**
 * Auto-alimentation : a la livraison confirmee, transforme les points de depart
 * et d'arrivee de la commande en reperes collaboratifs (points reellement
 * desservis par un livreur, donc fiables). Best-effort, ne bloque jamais.
 *
 * @param array $commande ligne commandes (adresse_/lat_/lng_ depart & arrivee, client_id)
 */
function carto_auto_alimenter(PDO $db, array $commande): void
{
    if (parametre($db, 'carto_auto_alimentation', '1') !== '1') {
        return;
    }

    $rayonM = (float) parametre($db, 'carto_auto_rayon_m', '40');
    $clientId = isset($commande['client_id']) ? (int) $commande['client_id'] : 0;

    $extremites = [
        ['adresse_depart', 'lat_depart', 'lng_depart'],
        ['adresse_arrivee', 'lat_arrivee', 'lng_arrivee'],
    ];
    foreach ($extremites as [$champNom, $champLat, $champLng]) {
        $nom = trim((string) ($commande[$champNom] ?? ''));
        // On ignore un libelle vide ou un repli purement coordonnees.
        if ($nom === '' || str_starts_with($nom, 'Point (')) {
            continue;
        }
        carto_enregistrer_point_auto(
            $db,
            $clientId,
            $nom,
            (float) $commande[$champLat],
            (float) $commande[$champLng],
            $rayonM
        );
    }
}

/**
 * Cree un repere valide a partir d'un point desservi, ou renforce
 * (confirmation) un repere existant deja proche (deduplication par distance).
 */
function carto_enregistrer_point_auto(PDO $db, int $clientId, string $nom, float $lat, float $lng, float $rayonM): void
{
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return;
    }

    // Pre-filtre par cadre englobant (index), puis distance precise.
    $rayonKm = $rayonM / 1000;
    $dLat = $rayonKm / 111.0;
    $dLng = $rayonKm / (111.0 * max(0.1, cos(deg2rad($lat))));
    $stmt = $db->prepare(
        "SELECT id, latitude, longitude FROM points_carte
         WHERE statut IN ('valide','en_attente')
           AND latitude BETWEEN :minlat AND :maxlat
           AND longitude BETWEEN :minlng AND :maxlng"
    );
    $stmt->execute([
        'minlat' => $lat - $dLat, 'maxlat' => $lat + $dLat,
        'minlng' => $lng - $dLng, 'maxlng' => $lng + $dLng,
    ]);
    foreach ($stmt->fetchAll() as $p) {
        if (haversine_distance_km($lat, $lng, (float) $p['latitude'], (float) $p['longitude']) * 1000 <= $rayonM) {
            // Repere deja connu a cet endroit : on le renforce.
            $db->prepare('UPDATE points_carte SET confirmations = confirmations + 1 WHERE id = :id')
                ->execute(['id' => (int) $p['id']]);
            return;
        }
    }

    // Nouveau repere, valide directement (source fiable : livraison effectuee).
    $stmt = $db->prepare(
        "INSERT INTO points_carte (user_id, nom, categorie, latitude, longitude, statut, confirmations)
         VALUES (:user_id, :nom, 'repere', :latitude, :longitude, 'valide', 1)"
    );
    $stmt->execute([
        'user_id' => $clientId > 0 ? $clientId : null,
        'nom' => mb_substr($nom, 0, 150),
        'latitude' => $lat,
        'longitude' => $lng,
    ]);
}

/**
 * Recalcule les compteurs de confirmations / signalements d'un point a partir
 * de la table des votes (placeholders distincts : PDO non emule).
 */
function carto_recompter_votes(PDO $db, int $pointId): void
{
    $stmt = $db->prepare(
        "UPDATE points_carte SET
            confirmations = (SELECT COUNT(*) FROM points_carte_votes WHERE point_id = :pid_c AND type = 'confirme'),
            signalements  = (SELECT COUNT(*) FROM points_carte_votes WHERE point_id = :pid_s AND type = 'signale')
         WHERE id = :pid_w"
    );
    $stmt->execute(['pid_c' => $pointId, 'pid_s' => $pointId, 'pid_w' => $pointId]);
}
