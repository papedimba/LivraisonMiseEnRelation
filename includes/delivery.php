<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Finalise une livraison : passe la commande a "livree", enregistre la preuve,
 * confirme le paiement especes, credite le gain du livreur et notifie le client.
 * A appeler DANS une transaction ouverte par l'appelant.
 *
 * @param array  $commande   Ligne de la commande (deja verifiee, statut 'en_cours')
 * @param string $preuveType 'code' | 'photo' | 'livreur'
 */
function finaliser_livraison(PDO $db, array $commande, int $livreurId, string $preuveType, ?string $preuvePhoto = null): void
{
    $commandeId = (int) $commande['id'];

    $db->prepare(
        "UPDATE commandes
         SET statut = 'livree', delivered_at = NOW(), montant_final = montant_estime,
             preuve_type = :ptype, preuve_photo = :pphoto
         WHERE id = :id"
    )->execute([
        'ptype' => $preuveType,
        'pphoto' => $preuvePhoto,
        'id' => $commandeId,
    ]);

    if ($commande['mode_paiement'] === 'especes') {
        $db->prepare("UPDATE commandes SET statut_paiement = 'paye' WHERE id = :id")->execute(['id' => $commandeId]);
    }

    $gainLivreur = round((float) $commande['montant_estime'] - (float) $commande['commission_montant'], 0);
    $db->prepare('UPDATE livreur_details SET solde = solde + :gain, nombre_courses = nombre_courses + 1 WHERE user_id = :id')
        ->execute(['gain' => $gainLivreur, 'id' => $livreurId]);

    creer_notification(
        $db,
        (int) $commande['client_id'],
        'Livraison effectuee',
        "Votre commande {$commande['reference']} a ete livree. Merci d'evaluer votre livreur !",
        'commande',
        "/client/track.php?ref={$commande['reference']}"
    );
}
