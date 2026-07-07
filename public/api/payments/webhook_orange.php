<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/payments.php';

// Webhook Orange Money Web Payment (notif_url).
// URL a declarer : https://votre-domaine.com/api/payments/webhook_orange.php

$raw = file_get_contents('php://input') ?: '';
$notif = json_decode($raw, true);
if (!is_array($notif)) {
    // Orange peut aussi poster en form-urlencoded.
    $notif = $_POST;
}

$referenceCommande = (string) ($notif['order_id'] ?? $notif['reference'] ?? '');
$statutOrange = strtoupper((string) ($notif['status'] ?? ''));

$statut = match ($statutOrange) {
    'SUCCESS', 'SUCCESSFUL', 'PAID' => 'reussi',
    'FAILED', 'EXPIRED', 'CANCELLED' => 'echec',
    default => null,
};

if ($statut !== null && $referenceCommande !== '') {
    confirmer_paiement_par_commande(Database::getConnection(), $referenceCommande, $statut, $notif);
}

http_response_code(200);
echo 'ok';
