<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$clientId = Auth::requireRole('client');

$reference = clean_str($_GET['reference'] ?? '');
if ($reference === '') {
    Response::error('Reference de commande requise.');
}

$db = Database::getConnection();

$stmt = $db->prepare(
    'SELECT c.reference, c.statut_paiement, c.mode_paiement, c.montant_estime,
            p.statut AS statut_transaction
     FROM commandes c
     LEFT JOIN paiements p ON p.commande_id = c.id
     WHERE c.reference = :ref AND c.client_id = :client_id
     ORDER BY p.id DESC LIMIT 1'
);
$stmt->execute(['ref' => $reference, 'client_id' => $clientId]);
$row = $stmt->fetch();

if (!$row) {
    Response::notFound('Commande introuvable.');
}

Response::success([
    'reference' => $row['reference'],
    'statut_paiement' => $row['statut_paiement'],
    'statut_transaction' => $row['statut_transaction'],
    'mode_paiement' => $row['mode_paiement'],
    'montant' => (float) $row['montant_estime'],
]);
