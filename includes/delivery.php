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

        // Paiement especes : le livreur encaisse le montant total en main
        // propre (son gain net est deja dans sa poche). Seule la commission
        // due a la plateforme est enregistree comme dette, a reverser
        // periodiquement (ex. a l'agence). On ne credite donc PAS le solde
        // (portefeuille retirable) pour ne pas payer deux fois le meme gain.
        $db->prepare('UPDATE livreur_details SET dette_commission = dette_commission + :dette, nombre_courses = nombre_courses + 1 WHERE user_id = :id')
            ->execute(['dette' => (float) $commande['commission_montant'], 'id' => $livreurId]);
    } else {
        // Paiement electronique : la plateforme encaisse le montant total et
        // doit reverser le gain net au livreur. Plutot que de le laisser
        // s'accumuler dans un solde a retirer manuellement, une demande de
        // retrait est deposee automatiquement pour ce montant, sur le meme
        // canal Mobile Money que celui utilise par le client pour payer, vers
        // le numero enregistre du livreur (traitement admin sous 24-48h,
        // comme un retrait manuel classique).
        $db->prepare('UPDATE livreur_details SET nombre_courses = nombre_courses + 1 WHERE user_id = :id')
            ->execute(['id' => $livreurId]);

        $gainLivreur = round((float) $commande['montant_estime'] - (float) $commande['commission_montant'], 0);

        $stmtTel = $db->prepare('SELECT telephone FROM users WHERE id = :id');
        $stmtTel->execute(['id' => $livreurId]);
        $telephoneLivreur = clean_str((string) ($stmtTel->fetch()['telephone'] ?? ''));

        if ($gainLivreur > 0 && is_valid_phone($telephoneLivreur)) {
            $db->prepare(
                'INSERT INTO retraits (livreur_id, montant, methode, numero_reception, statut)
                 VALUES (:livreur_id, :montant, :methode, :numero, :statut)'
            )->execute([
                'livreur_id' => $livreurId,
                'montant' => $gainLivreur,
                'methode' => $commande['mode_paiement'],
                'numero' => $telephoneLivreur,
                'statut' => 'en_attente',
            ]);

            creer_notification(
                $db,
                $livreurId,
                'Retrait automatique demande',
                "Suite a la livraison de {$commande['reference']}, un retrait de {$gainLivreur} FCFA a ete demande automatiquement vers votre numero {$telephoneLivreur}. Traitement sous 24-48h.",
                'paiement',
                '/livreur/earnings.php'
            );
        } else {
            // Filet de securite : pas de numero de telephone exploitable
            // (improbable, le champ est obligatoire a l'inscription). Le gain
            // reste alors sur le solde, retirable manuellement.
            $db->prepare('UPDATE livreur_details SET solde = solde + :gain WHERE user_id = :id')
                ->execute(['gain' => $gainLivreur, 'id' => $livreurId]);
        }
    }

    creer_notification(
        $db,
        (int) $commande['client_id'],
        'Livraison effectuee',
        "Votre commande {$commande['reference']} a ete livree. Merci d'evaluer votre livreur !",
        'commande',
        "/client/track.php?ref={$commande['reference']}"
    );
}
