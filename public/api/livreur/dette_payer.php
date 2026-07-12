<?php
declare(strict_types=1);

/**
 * Permet au livreur de regler lui-meme (en libre-service, via Mobile Money) sa
 * dette de commission accumulee sur ses courses payees en especes, sans passer
 * par l'agence/l'admin. Reutilise la meme passerelle de paiement que les
 * commandes (includes/PaymentGateway.php) ; la confirmation definitive arrive
 * en general par le webhook de l'operateur (voir includes/payments.php ::
 * confirmer_reglement_dette), sauf en mode simulation (sans cle API) ou elle
 * est appliquee immediatement.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/payments.php';
require_once __DIR__ . '/../../../includes/PaymentGateway.php';

$livreurId = Auth::requireRole('livreur');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['montant', 'methode', 'numero_paiement']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$montant = (float) $body['montant'];
$methode = clean_str($body['methode']);
$numero = clean_str($body['numero_paiement']);

$methodesValides = ['orange_money', 'mtn_money', 'moov_money', 'wave'];
if (!in_array($methode, $methodesValides, true)) {
    Response::error('Methode de paiement invalide.', 422);
}
if ($montant <= 0) {
    Response::error('Le montant doit etre positif.', 422);
}
if (!is_valid_phone($numero)) {
    Response::error('Numero de paiement invalide.', 422);
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT dette_commission FROM livreur_details WHERE user_id = :id');
$stmt->execute(['id' => $livreurId]);
$livreur = $stmt->fetch();
$dette = (float) ($livreur['dette_commission'] ?? 0);

if ($dette <= 0) {
    Response::error('Vous n\'avez aucune dette de commission a regler.', 422);
}
if ($montant > $dette) {
    Response::error("Le montant depasse votre dette actuelle ({$dette} FCFA).", 422);
}

$reference = 'DETTE-' . strtoupper(bin2hex(random_bytes(6)));

$db->prepare(
    'INSERT INTO paiements_dette (livreur_id, methode, reference, montant, statut) VALUES (:livreur_id, :methode, :reference, :montant, :statut)'
)->execute([
    'livreur_id' => $livreurId,
    'methode' => $methode,
    'reference' => $reference,
    'montant' => $montant,
    'statut' => 'en_attente',
]);

$driver = PaymentGateway::driver($methode);
$resultat = $driver->initierPaiement($numero, $montant, $reference);

if (in_array($resultat['statut'], ['reussi', 'echec'], true)) {
    // Mode simulation (aucune cle API configuree) : pas de webhook a venir,
    // on applique/rejette immediatement.
    confirmer_reglement_dette($db, $reference, $resultat['statut'], $resultat['payload']);
}

$stmt = $db->prepare('SELECT dette_commission FROM livreur_details WHERE user_id = :id');
$stmt->execute(['id' => $livreurId]);
$detteApres = (float) ($stmt->fetch()['dette_commission'] ?? 0);

Response::success([
    'reference' => $reference,
    'statut' => $resultat['statut'],
    'redirect_url' => $resultat['redirect_url'],
    'instructions' => $resultat['instructions'],
    'dette_commission' => $detteApres,
], 'Paiement initie.');
