<?php
declare(strict_types=1);

/**
 * Enregistre le reglement (partiel ou total) de la dette de commission d'un
 * livreur, ex. lorsqu'il remet en especes a l'agence la commission accumulee
 * sur ses courses payees cash. Trace l'operation dans reglements_dette pour
 * garder un historique verifiable.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$adminId = Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['livreur_id', 'montant']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$livreurId = (int) $body['livreur_id'];
$montant = (float) $body['montant'];
$note = clean_str(input($body, 'note', ''));

if ($montant <= 0) {
    Response::error('Le montant doit etre positif.', 422);
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare('SELECT dette_commission FROM livreur_details WHERE user_id = :id FOR UPDATE');
    $stmt->execute(['id' => $livreurId]);
    $livreur = $stmt->fetch();

    if (!$livreur) {
        $db->rollBack();
        Response::notFound('Livreur introuvable.');
    }

    $detteActuelle = (float) $livreur['dette_commission'];
    if ($montant > $detteActuelle) {
        $db->rollBack();
        Response::error("Le montant depasse la dette actuelle ({$detteActuelle} FCFA).", 422);
    }

    $db->prepare('UPDATE livreur_details SET dette_commission = dette_commission - :montant WHERE user_id = :id')
        ->execute(['montant' => $montant, 'id' => $livreurId]);

    $db->prepare(
        'INSERT INTO reglements_dette (livreur_id, montant, admin_id, note) VALUES (:livreur_id, :montant, :admin_id, :note)'
    )->execute([
        'livreur_id' => $livreurId,
        'montant' => $montant,
        'admin_id' => $adminId,
        'note' => $note !== '' ? $note : null,
    ]);

    creer_notification(
        $db,
        $livreurId,
        'Dette de commission reglee',
        "Un reglement de {$montant} FCFA a ete enregistre sur votre dette de commission.",
        'paiement'
    );

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    Response::error('Erreur lors de l\'enregistrement du reglement.', 422);
}

Response::success([], 'Reglement enregistre.');
