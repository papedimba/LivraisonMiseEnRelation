<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$clientId = Auth::requireRole('client');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['commande_id', 'note']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$commandeId = (int) $body['commande_id'];
$note = (int) $body['note'];
$commentaire = clean_str(input($body, 'commentaire', ''));

if ($note < 1 || $note > 5) {
    Response::error('La note doit etre comprise entre 1 et 5.');
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT * FROM commandes WHERE id = :id AND client_id = :client_id AND statut = 'livree'");
$stmt->execute(['id' => $commandeId, 'client_id' => $clientId]);
$commande = $stmt->fetch();

if (!$commande) {
    Response::notFound('Commande livree introuvable pour ce client.');
}

$stmt = $db->prepare('SELECT id FROM evaluations WHERE commande_id = :id');
$stmt->execute(['id' => $commandeId]);
if ($stmt->fetch()) {
    Response::error('Cette commande a deja ete evaluee.', 409);
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare(
        'INSERT INTO evaluations (commande_id, client_id, livreur_id, commercant_id, note, commentaire)
         VALUES (:commande_id, :client_id, :livreur_id, :commercant_id, :note, :commentaire)'
    );
    $stmt->execute([
        'commande_id' => $commandeId,
        'client_id' => $clientId,
        'livreur_id' => $commande['livreur_id'],
        'commercant_id' => $commande['commercant_id'],
        'note' => $note,
        'commentaire' => $commentaire,
    ]);

    if ($commande['livreur_id']) {
        $stmt = $db->prepare(
            'UPDATE livreur_details
             SET note_moyenne = (
                 SELECT ROUND(AVG(e.note), 2) FROM evaluations e WHERE e.livreur_id = :livreur_id
             )
             WHERE user_id = :livreur_id'
        );
        $stmt->execute(['livreur_id' => $commande['livreur_id']]);
    }

    if ($commande['commercant_id']) {
        $stmt = $db->prepare(
            'UPDATE commercant_details
             SET note_moyenne = (
                 SELECT ROUND(AVG(e.note), 2) FROM evaluations e WHERE e.commercant_id = :commercant_id
             )
             WHERE user_id = :commercant_id'
        );
        $stmt->execute(['commercant_id' => $commande['commercant_id']]);
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    Response::error('Erreur lors de l\'enregistrement de l\'evaluation.', 422);
}

Response::success([], 'Merci pour votre evaluation.');
