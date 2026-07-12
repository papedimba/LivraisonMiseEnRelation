<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/payments.php';

// Webhook Wave Checkout. Appele par Wave (pas d'authentification par session).
// URL a declarer chez Wave : https://votre-domaine.com/api/payments/webhook_wave.php

$raw = file_get_contents('php://input') ?: '';

// Verification de la signature HMAC si un secret est configure.
$secret = MOBILE_MONEY_CONFIG['wave']['webhook_secret'] ?? '';
if ($secret !== '') {
    $entete = $_SERVER['HTTP_WAVE_SIGNATURE'] ?? '';
    // Wave envoie "t=timestamp, v1=signature". On verifie v1 = HMAC_SHA256(timestamp.raw).
    $timestamp = '';
    $signatureRecue = '';
    foreach (explode(',', $entete) as $partie) {
        $partie = trim($partie);
        if (str_starts_with($partie, 't=')) {
            $timestamp = substr($partie, 2);
        } elseif (str_starts_with($partie, 'v1=')) {
            $signatureRecue = substr($partie, 3);
        }
    }
    $attendue = hash_hmac('sha256', $timestamp . $raw, $secret);
    if (!hash_equals($attendue, $signatureRecue)) {
        http_response_code(401);
        echo 'signature invalide';
        exit;
    }
}

$event = json_decode($raw, true);
if (!is_array($event)) {
    http_response_code(400);
    exit;
}

$data = $event['data'] ?? [];
$type = $event['type'] ?? '';
$referenceCommande = $data['client_reference'] ?? '';

$statut = match ($type) {
    'checkout.session.completed', 'checkout.session.payment_succeeded' => 'reussi',
    'checkout.session.payment_failed' => 'echec',
    default => null,
};

if ($statut !== null && $referenceCommande !== '') {
    $db = Database::getConnection();
    if (!confirmer_paiement_par_commande($db, (string) $referenceCommande, $statut, $event)) {
        confirmer_reglement_dette($db, (string) $referenceCommande, $statut, $event);
    }
}

http_response_code(200);
echo 'ok';
