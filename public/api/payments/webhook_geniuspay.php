<?php
declare(strict_types=1);

/**
 * Webhook GeniusPay (agregateur Mobile Money Orange/MTN/Wave).
 * Doc : http://pay.genius.ci/docs/api
 *
 * Verification de signature : HMAC-SHA256(corps_brut, webhook_secret), fourni
 * dans l'en-tete X-GeniusPay-Signature.
 *
 * GeniusPay ne relaie pas notre propre reference de commande/dette (il genere
 * la sienne, "MTX-..."). On l'a donc transmise a la creation du paiement dans
 * `metadata.order_id` (voir GeniusPayDriver::initierPaiement) ; c'est cette
 * valeur qu'on relit ici pour retrouver la commande ou le reglement de dette
 * correspondant.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/payments.php';

$raw = file_get_contents('php://input') ?: '';

$secret = MOBILE_MONEY_CONFIG['geniuspay']['webhook_secret'] ?? '';
if ($secret !== '') {
    $signatureRecue = $_SERVER['HTTP_X_GENIUSPAY_SIGNATURE'] ?? '';
    $attendue = hash_hmac('sha256', $raw, $secret);
    if ($signatureRecue === '' || !hash_equals($attendue, $signatureRecue)) {
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

$type = (string) ($event['event'] ?? '');
$reference = (string) ($event['data']['transaction']['metadata']['order_id'] ?? '');

// On ne fait transiter vers nos fonctions de confirmation que les statuts
// qu'elles savent traiter (reussi/echec) : 'refunded' impliquerait de
// reprendre un paiement deja applique (solde/dette), ce qui n'est pas gere
// automatiquement ici et necessite une verification manuelle.
$statut = match ($type) {
    'payment.success' => 'reussi',
    'payment.failed', 'payment.cancelled' => 'echec',
    default => null, // payment.initiated / payment.refunded : pas de transition automatique
};

if ($statut !== null && $reference !== '') {
    $db = Database::getConnection();
    // La reference peut correspondre a une commande OU au reglement d'une
    // dette de commission initie par un livreur (references distinctes).
    if (!confirmer_paiement_par_commande($db, $reference, $statut, $event)) {
        confirmer_reglement_dette($db, $reference, $statut, $event);
    }
}

if ($type === 'payment.refunded') {
    error_log('Webhook GeniusPay : remboursement recu, verification manuelle requise. Payload : ' . substr($raw, 0, 2000));
}

http_response_code(200);
echo 'ok';
