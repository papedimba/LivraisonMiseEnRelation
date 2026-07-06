<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$adminId = Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['retrait_id', 'decision']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$retraitId = (int) $body['retrait_id'];
$decision = clean_str($body['decision']);
$reference = clean_str(input($body, 'reference', ''));

if (!in_array($decision, ['traite', 'rejete'], true)) {
    Response::error('Decision invalide.');
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM retraits WHERE id = :id AND statut = 'en_attente' FOR UPDATE");
    $stmt->execute(['id' => $retraitId]);
    $retrait = $stmt->fetch();

    if (!$retrait) {
        $db->rollBack();
        Response::notFound('Retrait introuvable ou deja traite.');
    }

    $db->prepare(
        'UPDATE retraits SET statut = :statut, reference = :reference, traite_par = :admin_id WHERE id = :id'
    )->execute([
        'statut' => $decision,
        'reference' => $reference,
        'admin_id' => $adminId,
        'id' => $retraitId,
    ]);

    if ($decision === 'rejete') {
        $db->prepare('UPDATE livreur_details SET solde = solde + :montant WHERE user_id = :id')
            ->execute(['montant' => $retrait['montant'], 'id' => $retrait['livreur_id']]);
    }

    $message = $decision === 'traite'
        ? "Votre retrait de {$retrait['montant']} FCFA a ete traite."
        : "Votre retrait de {$retrait['montant']} FCFA a ete rejete et le montant a ete recredite sur votre solde.";

    creer_notification($db, (int) $retrait['livreur_id'], 'Retrait de gains', $message, 'paiement');

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    Response::error('Erreur lors du traitement du retrait.', 422);
}

Response::success([], 'Retrait traite.');
