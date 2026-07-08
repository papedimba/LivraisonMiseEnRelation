<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Propose une commande au livreur en ligne le plus proche du point de retrait
 * (modele d'offre). Cree une offre en attente et notifie le livreur.
 *
 * Un livreur est eligible s'il est en ligne, valide, dispose d'une position
 * connue, n'est pas deja sur une course active, et n'a pas deja recu (ou refuse)
 * une offre pour cette commande.
 *
 * @return bool true si une offre a ete creee, false si aucun livreur eligible.
 */
function dispatcher_commande(PDO $db, int $commandeId): bool
{
    $stmt = $db->prepare("SELECT id, lat_depart, lng_depart, reference FROM commandes WHERE id = :id AND statut = 'en_attente'");
    $stmt->execute(['id' => $commandeId]);
    $commande = $stmt->fetch();
    if (!$commande) {
        return false;
    }

    // Candidats : en ligne, valides, avec position, libres, pas deja sollicites.
    $stmt = $db->prepare(
        "SELECT ld.user_id, ld.latitude, ld.longitude, ld.note_moyenne
         FROM livreur_details ld
         WHERE ld.disponibilite = 'en_ligne'
           AND ld.statut_validation = 'valide'
           AND ld.latitude IS NOT NULL AND ld.longitude IS NOT NULL
           AND ld.user_id NOT IN (
               SELECT livreur_id FROM commandes
               WHERE livreur_id IS NOT NULL AND statut IN ('acceptee','recuperee','en_cours')
           )
           AND ld.user_id NOT IN (
               SELECT livreur_id FROM dispatch_offres
               WHERE commande_id = :cid AND statut IN ('en_attente','refusee')
           )"
    );
    $stmt->execute(['cid' => $commandeId]);
    $candidats = $stmt->fetchAll();
    if (!$candidats) {
        return false;
    }

    // Parametres de scoring (parametrables par l'admin).
    $rayonMax = (float) parametre($db, 'dispatch_rayon_max_km', '10');
    $poidsNote = (float) parametre($db, 'dispatch_poids_note', '0.5');

    // Selection par SCORE (plus bas = meilleur) :
    //   score = distance_km + (5 - note_moyenne) * poids_note
    // A distance comparable, un livreur mieux note est prefere. Les livreurs
    // au-dela du rayon maximum sont ecartes.
    $latC = (float) $commande['lat_depart'];
    $lngC = (float) $commande['lng_depart'];
    $meilleur = null;
    $meilleureDistance = null;
    $meilleurScore = null;
    foreach ($candidats as $c) {
        $d = haversine_distance_km($latC, $lngC, (float) $c['latitude'], (float) $c['longitude']);
        if ($rayonMax > 0 && $d > $rayonMax) {
            continue;
        }
        $score = $d + (5 - (float) $c['note_moyenne']) * $poidsNote;
        if ($meilleurScore === null || $score < $meilleurScore) {
            $meilleurScore = $score;
            $meilleureDistance = $d;
            $meilleur = $c;
        }
    }

    // Aucun livreur dans le rayon.
    if ($meilleur === null) {
        return false;
    }

    $secondes = (int) parametre($db, 'dispatch_offre_secondes', '45');

    $stmt = $db->prepare(
        'INSERT INTO dispatch_offres (commande_id, livreur_id, distance_km, statut, expires_at)
         VALUES (:cid, :lid, :dist, :statut, (NOW() + INTERVAL :sec SECOND))'
    );
    $stmt->bindValue(':cid', $commandeId, PDO::PARAM_INT);
    $stmt->bindValue(':lid', (int) $meilleur['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':dist', $meilleureDistance);
    $stmt->bindValue(':statut', 'en_attente');
    $stmt->bindValue(':sec', $secondes, PDO::PARAM_INT);
    $stmt->execute();

    creer_notification(
        $db,
        (int) $meilleur['user_id'],
        'Nouvelle course proposee',
        "Une course ({$commande['reference']}) vous est proposee a {$meilleureDistance} km. Repondez vite !",
        'commande',
        '/livreur/dashboard.php'
    );

    return true;
}

/**
 * Fait avancer le cycle de dispatch d'une commande en attente :
 *  - si une offre en attente a expire -> la marque expiree et propose au suivant ;
 *  - si aucune offre active -> tente un dispatch.
 *
 * @return string 'en_cours' (offre active), 'dispatchee' (nouvelle offre),
 *                'aucun_livreur' (personne d'eligible)
 */
function avancer_dispatch(PDO $db, int $commandeId): string
{
    $stmt = $db->prepare(
        "SELECT id, statut, expires_at FROM dispatch_offres
         WHERE commande_id = :cid ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute(['cid' => $commandeId]);
    $offre = $stmt->fetch();

    if ($offre && $offre['statut'] === 'en_attente') {
        if (strtotime($offre['expires_at']) > time()) {
            return 'en_cours'; // on attend encore la reponse du livreur
        }
        // Offre expiree : on la ferme et on passe au suivant.
        $db->prepare("UPDATE dispatch_offres SET statut = 'expiree' WHERE id = :id")
            ->execute(['id' => $offre['id']]);
    }

    return dispatcher_commande($db, $commandeId) ? 'dispatchee' : 'aucun_livreur';
}
