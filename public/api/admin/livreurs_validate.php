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
$missing = require_fields($body, ['user_id', 'decision']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$userId = (int) $body['user_id'];
$decision = clean_str($body['decision']);
$motif = clean_str(input($body, 'motif', ''));

if (!in_array($decision, ['valide', 'rejete'], true)) {
    Response::error('Decision invalide.');
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT user_id FROM livreur_details WHERE user_id = :id');
$stmt->execute(['id' => $userId]);
if (!$stmt->fetch()) {
    Response::notFound('Livreur introuvable.');
}

try {
    $db->beginTransaction();

    $db->prepare(
        'UPDATE livreur_details SET statut_validation = :statut, motif_rejet = :motif, valide_par = :admin_id, valide_at = NOW()
         WHERE user_id = :id'
    )->execute([
        'statut' => $decision,
        'motif' => $decision === 'rejete' ? $motif : null,
        'admin_id' => $adminId,
        'id' => $userId,
    ]);

    $nouveauStatutCompte = $decision === 'valide' ? 'actif' : 'suspendu';
    $db->prepare('UPDATE users SET statut = :statut WHERE id = :id')->execute(['statut' => $nouveauStatutCompte, 'id' => $userId]);

    $message = $decision === 'valide'
        ? 'Felicitations, votre compte livreur a ete valide. Vous pouvez desormais accepter des commandes.'
        : "Votre compte livreur a ete rejete. Motif : {$motif}";

    creer_notification($db, $userId, 'Validation de votre compte livreur', $message, 'compte');

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    Response::error('Erreur lors de la validation.', 422);
}

Response::success([], 'Decision enregistree.');
