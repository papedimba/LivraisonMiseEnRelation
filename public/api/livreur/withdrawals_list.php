<?php
declare(strict_types=1);

/**
 * Historique des retraits du livreur (manuels + automatiques, deposes a
 * chaque livraison payee en Mobile Money). Permet de voir ce qui a ete
 * demande sans action de sa part et d'en suivre le statut.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$livreurId = Auth::requireRole('livreur');

$db = Database::getConnection();

$stmt = $db->prepare(
    'SELECT id, montant, methode, numero_reception, statut, reference, created_at
     FROM retraits
     WHERE livreur_id = :livreur_id
     ORDER BY created_at DESC
     LIMIT 50'
);
$stmt->execute(['livreur_id' => $livreurId]);

Response::success(['retraits' => $stmt->fetchAll()]);
