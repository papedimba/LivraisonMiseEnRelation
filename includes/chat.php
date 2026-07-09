<?php
declare(strict_types=1);

/**
 * Messagerie in-app rattachee a une commande (client <-> livreur).
 *
 * Regles d'acces : seul le client proprietaire de la commande et le livreur
 * qui lui est attribue peuvent lire/ecrire. Le chat reste ouvert tant qu'un
 * livreur est attribue et que la commande n'est pas annulee.
 */

/**
 * Charge la commande si l'utilisateur courant (client ou livreur) en est un
 * participant legitime, sinon retourne null.
 *
 * @return array{id:int,client_id:int,livreur_id:?int,reference:string,statut:string}|null
 */
function chat_commande_participant(PDO $db, int $commandeId, int $userId, string $role): ?array
{
    if (!in_array($role, ['client', 'livreur'], true)) {
        return null;
    }
    $stmt = $db->prepare('SELECT id, client_id, livreur_id, reference, statut FROM commandes WHERE id = :id');
    $stmt->execute(['id' => $commandeId]);
    $commande = $stmt->fetch();
    if (!$commande) {
        return null;
    }
    if ($role === 'client' && (int) $commande['client_id'] !== $userId) {
        return null;
    }
    if ($role === 'livreur' && (int) ($commande['livreur_id'] ?? 0) !== $userId) {
        return null;
    }
    return $commande;
}

/**
 * Identifiant de l'autre participant (pour le notifier), ou null.
 */
function chat_autre_participant(array $commande, string $role): ?int
{
    if ($role === 'client') {
        return $commande['livreur_id'] !== null ? (int) $commande['livreur_id'] : null;
    }
    return (int) $commande['client_id'];
}

/**
 * Le chat est disponible tant qu'un livreur est attribue et que la commande
 * n'est pas annulee.
 */
function chat_disponible(array $commande): bool
{
    return $commande['livreur_id'] !== null && $commande['statut'] !== 'annulee';
}
