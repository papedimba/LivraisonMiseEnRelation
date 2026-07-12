<?php
declare(strict_types=1);

/**
 * Liste des livreurs ayant une dette de commission en cours (courses payees en
 * especes dont la commission n'a pas encore ete reversee a la plateforme).
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

$stmt = $db->query(
    "SELECT u.id, u.prenom, u.nom, u.telephone, ld.dette_commission, ld.solde
     FROM livreur_details ld
     JOIN users u ON u.id = ld.user_id
     WHERE ld.dette_commission > 0
     ORDER BY ld.dette_commission DESC"
);

$livreurs = array_map(static fn ($l) => [
    'id' => (int) $l['id'],
    'nom_complet' => $l['prenom'] . ' ' . $l['nom'],
    'telephone' => $l['telephone'],
    'dette_commission' => (float) $l['dette_commission'],
    'solde' => (float) $l['solde'],
], $stmt->fetchAll());

Response::success([
    'livreurs' => $livreurs,
    'total_dette' => array_sum(array_column($livreurs, 'dette_commission')),
]);
