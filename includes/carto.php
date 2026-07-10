<?php
declare(strict_types=1);

/**
 * Cartographie collaborative : helpers partages.
 */

function carto_categories(): array
{
    return ['repere', 'commerce', 'carrefour', 'quartier', 'sante', 'education', 'service_public', 'autre'];
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
