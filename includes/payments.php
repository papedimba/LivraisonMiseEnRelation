<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Confirme le paiement le plus recent d'une commande identifiee par SA reference
 * (order_id / client_reference / externalId renvoye par l'operateur dans le
 * webhook). Plus robuste que la reference de transaction, qui differe selon les
 * operateurs.
 */
function confirmer_paiement_par_commande(PDO $db, string $referenceCommande, string $statut, array $payload = []): bool
{
    $stmt = $db->prepare(
        'SELECT p.id FROM paiements p
         JOIN commandes c ON c.id = p.commande_id
         WHERE c.reference = :ref
         ORDER BY p.id DESC LIMIT 1'
    );
    $stmt->execute(['ref' => $referenceCommande]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    return confirmer_paiement_par_id($db, (int) $row['id'], $statut, $payload);
}

/**
 * Met a jour un paiement et le statut de paiement de sa commande a partir d'une
 * confirmation d'operateur (webhook). Retourne true si un paiement a ete trouve.
 *
 * @param string $statut 'reussi' | 'echec' | 'rembourse'
 */
function confirmer_paiement(PDO $db, string $referenceTransaction, string $statut, array $payload = []): bool
{
    $stmt = $db->prepare('SELECT id FROM paiements WHERE reference_transaction = :ref LIMIT 1');
    $stmt->execute(['ref' => $referenceTransaction]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }
    return confirmer_paiement_par_id($db, (int) $row['id'], $statut, $payload);
}

function confirmer_paiement_par_id(PDO $db, int $paiementId, string $statut, array $payload = []): bool
{
    $stmt = $db->prepare('SELECT id, commande_id, statut FROM paiements WHERE id = :id');
    $stmt->execute(['id' => $paiementId]);
    $paiement = $stmt->fetch();

    if (!$paiement) {
        return false;
    }

    // Idempotence : si le paiement est deja au statut final, on ne refait rien.
    if ($paiement['statut'] === $statut) {
        return true;
    }

    $db->beginTransaction();
    try {
        $db->prepare('UPDATE paiements SET statut = :statut, payload_json = :payload WHERE id = :id')
            ->execute([
                'statut' => $statut,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'id' => $paiement['id'],
            ]);

        $statutCommande = match ($statut) {
            'reussi' => 'paye',
            'echec' => 'echec',
            'rembourse' => 'rembourse',
            default => 'en_attente',
        };
        $db->prepare('UPDATE commandes SET statut_paiement = :sp WHERE id = :id')
            ->execute(['sp' => $statutCommande, 'id' => $paiement['commande_id']]);

        // Notifier le client.
        $stmtC = $db->prepare('SELECT reference, client_id FROM commandes WHERE id = :id');
        $stmtC->execute(['id' => $paiement['commande_id']]);
        $commande = $stmtC->fetch();

        if ($commande) {
            if ($statut === 'reussi') {
                creer_notification($db, (int) $commande['client_id'], 'Paiement confirme',
                    "Le paiement de la commande {$commande['reference']} a ete confirme.", 'paiement');
            } elseif ($statut === 'echec') {
                creer_notification($db, (int) $commande['client_id'], 'Paiement echoue',
                    "Le paiement de la commande {$commande['reference']} a echoue. Vous pouvez reessayer.", 'paiement');
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('confirmer_paiement error: ' . $e->getMessage());
        return false;
    }

    return true;
}
