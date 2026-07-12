<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/payments.php';

// Webhook MTN MoMo Collection (X-Callback-Url).
// URL a declarer : https://votre-domaine.com/api/payments/webhook_mtn.php

$raw = file_get_contents('php://input') ?: '';
$notif = json_decode($raw, true);
if (!is_array($notif)) {
    http_response_code(400);
    exit;
}

// MTN renvoie externalId (notre reference de commande) et status.
$referenceCommande = (string) ($notif['externalId'] ?? '');
$statutMtn = strtoupper((string) ($notif['status'] ?? ''));

$statut = match ($statutMtn) {
    'SUCCESSFUL', 'SUCCESS' => 'reussi',
    'FAILED', 'REJECTED', 'TIMEOUT', 'EXPIRED' => 'echec',
    default => null,
};

if ($statut !== null && $referenceCommande !== '') {
    $db = Database::getConnection();
    if (!confirmer_paiement_par_commande($db, $referenceCommande, $statut, $notif)) {
        confirmer_reglement_dette($db, $referenceCommande, $statut, $notif);
    }
}

http_response_code(200);
echo 'ok';
