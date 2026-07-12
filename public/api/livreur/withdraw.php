<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$livreurId = Auth::requireRole('livreur');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['montant', 'methode', 'numero_reception']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$montant = (float) $body['montant'];
$methode = clean_str($body['methode']);
$numero = clean_str($body['numero_reception']);

$methodesValides = ['orange_money', 'mtn_money', 'moov_money', 'wave', 'especes'];
if (!in_array($methode, $methodesValides, true)) {
    Response::error('Methode de retrait invalide.');
}
if ($montant <= 0) {
    Response::error('Le montant doit etre positif.');
}
if (!is_valid_phone($numero)) {
    Response::error('Numero de reception invalide.');
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare('SELECT solde, dette_commission FROM livreur_details WHERE user_id = :id FOR UPDATE');
    $stmt->execute(['id' => $livreurId]);
    $livreur = $stmt->fetch();

    // Le solde disponible au retrait est net de la dette de commission
    // (courses payees en especes dont la commission n'a pas encore ete
    // reversee a la plateforme).
    $disponible = $livreur ? max(0, (float) $livreur['solde'] - (float) $livreur['dette_commission']) : 0;

    if (!$livreur || $disponible < $montant) {
        $db->rollBack();
        $message = ((float) ($livreur['dette_commission'] ?? 0)) > 0
            ? "Solde insuffisant (une dette de commission de {$livreur['dette_commission']} FCFA sur vos courses especes reduit votre solde retirable)."
            : 'Solde insuffisant.';
        Response::error($message, 422);
    }

    $db->prepare('UPDATE livreur_details SET solde = solde - :montant WHERE user_id = :id')
        ->execute(['montant' => $montant, 'id' => $livreurId]);

    $stmt = $db->prepare(
        'INSERT INTO retraits (livreur_id, montant, methode, numero_reception, statut)
         VALUES (:livreur_id, :montant, :methode, :numero, :statut)'
    );
    $stmt->execute([
        'livreur_id' => $livreurId,
        'montant' => $montant,
        'methode' => $methode,
        'numero' => $numero,
        'statut' => 'en_attente',
    ]);
    $retraitId = (int) $db->lastInsertId();

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    Response::error('Erreur lors de la demande de retrait.', 422);
}

Response::created(['retrait_id' => $retraitId], 'Demande de retrait enregistree, traitement sous 24-48h.');
