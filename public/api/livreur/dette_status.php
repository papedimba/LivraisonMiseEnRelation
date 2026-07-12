<?php
declare(strict_types=1);

/**
 * Statut d'un paiement de dette initie par le livreur (pour le polling cote
 * front pendant l'attente de confirmation Mobile Money).
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$livreurId = Auth::requireRole('livreur');

$reference = clean_str($_GET['reference'] ?? '');
if ($reference === '') {
    Response::error('Reference requise.', 422);
}

$db = Database::getConnection();

$stmt = $db->prepare(
    'SELECT statut, montant FROM paiements_dette WHERE reference = :ref AND livreur_id = :livreur_id'
);
$stmt->execute(['ref' => $reference, 'livreur_id' => $livreurId]);
$paiement = $stmt->fetch();

if (!$paiement) {
    Response::notFound('Paiement introuvable.');
}

$stmt = $db->prepare('SELECT dette_commission FROM livreur_details WHERE user_id = :id');
$stmt->execute(['id' => $livreurId]);
$dette = (float) ($stmt->fetch()['dette_commission'] ?? 0);

Response::success([
    'statut' => $paiement['statut'],
    'montant' => (float) $paiement['montant'],
    'dette_commission' => $dette,
]);
