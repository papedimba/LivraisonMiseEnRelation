<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Marchandage de prix (courses especes uniquement, voir orders_create.php et
 * negociation_*.php) : une ligne evolutive par paire commande/livreur dans
 * negociations_prix, mise a jour a chaque tour (propose_par + montant_propose),
 * jusqu'a acceptation ou refus par l'une des deux parties.
 */

/**
 * Cree ou met a jour la proposition de prix pour une paire (commande, livreur).
 */
function proposer_prix(PDO $db, int $commandeId, int $livreurId, float $montant, string $proposePar): void
{
    $db->prepare(
        'INSERT INTO negociations_prix (commande_id, livreur_id, montant_propose, propose_par, statut)
         VALUES (:cid, :lid, :montant, :propose_par, "en_attente")
         ON DUPLICATE KEY UPDATE montant_propose = :montant2, propose_par = :propose_par2, statut = "en_attente"'
    )->execute([
        'cid' => $commandeId,
        'lid' => $livreurId,
        'montant' => $montant,
        'propose_par' => $proposePar,
        'montant2' => $montant,
        'propose_par2' => $proposePar,
    ]);
}

/**
 * Verrouille l'accord : attribue la commande a ce livreur au montant convenu,
 * recalcule la commission sur cette base, ferme les autres negociations en
 * attente pour cette commande et neutralise le dispatch automatique en cours.
 *
 * @return array{ok:bool, message?:string, commande?:array, autres_livreurs?:array}
 */
function conclure_negociation(PDO $db, int $commandeId, int $livreurId, float $montant): array
{
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM commandes WHERE id = :id AND statut = 'en_attente' FOR UPDATE");
        $stmt->execute(['id' => $commandeId]);
        $commande = $stmt->fetch();
        if (!$commande) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Cette commande n\'est plus disponible.'];
        }

        $stmt = $db->prepare("SELECT id FROM commandes WHERE livreur_id = :lid AND statut IN ('acceptee','recuperee','en_cours') LIMIT 1");
        $stmt->execute(['lid' => $livreurId]);
        if ($stmt->fetch()) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Vous avez deja une course en cours.'];
        }

        $commissionMontant = round($montant * (float) $commande['commission_taux'] / 100, 0);

        $db->prepare(
            "UPDATE commandes
             SET statut = 'acceptee', livreur_id = :lid, accepted_at = NOW(),
                 montant_estime = :montant, commission_montant = :commission
             WHERE id = :id"
        )->execute(['lid' => $livreurId, 'montant' => $montant, 'commission' => $commissionMontant, 'id' => $commandeId]);

        $db->prepare("UPDATE negociations_prix SET statut = 'acceptee' WHERE commande_id = :cid AND livreur_id = :lid")
            ->execute(['cid' => $commandeId, 'lid' => $livreurId]);

        $stmt = $db->prepare("SELECT livreur_id FROM negociations_prix WHERE commande_id = :cid AND livreur_id <> :lid AND statut = 'en_attente'");
        $stmt->execute(['cid' => $commandeId, 'lid' => $livreurId]);
        $autresLivreurs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $db->prepare("UPDATE negociations_prix SET statut = 'refusee' WHERE commande_id = :cid AND livreur_id <> :lid AND statut = 'en_attente'")
            ->execute(['cid' => $commandeId, 'lid' => $livreurId]);

        $db->prepare("UPDATE dispatch_offres SET statut = 'expiree' WHERE commande_id = :cid AND statut = 'en_attente'")
            ->execute(['cid' => $commandeId]);

        $db->commit();

        return ['ok' => true, 'commande' => $commande, 'autres_livreurs' => $autresLivreurs];
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'message' => 'Erreur lors de la confirmation de l\'accord.'];
    }
}
