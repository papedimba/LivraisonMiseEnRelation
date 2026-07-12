<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/payments.php';

// Webhook Moov Money (callback_url).
// URL a declarer : https://votre-domaine.com/api/payments/webhook_moov.php

$raw = file_get_contents('php://input') ?: '';
$notif = json_decode($raw, true);
if (!is_array($notif)) {
    $notif = $_POST;
}

$referenceCommande = (string) ($notif['reference'] ?? '');
$statutMoov = strtoupper((string) ($notif['status'] ?? $notif['statut'] ?? ''));

$statut = match ($statutMoov) {
    'SUCCESS', 'SUCCESSFUL', 'PAID', 'COMPLETED' => 'reussi',
    'FAILED', 'CANCELLED', 'REJECTED' => 'echec',
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
