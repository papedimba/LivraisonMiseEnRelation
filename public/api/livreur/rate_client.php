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

// La commande doit avoir ete livree par ce livreur.
$stmt = $db->prepare("SELECT id, client_id FROM commandes WHERE id = :id AND livreur_id = :livreur_id AND statut = 'livree'");
$stmt->execute(['id' => $commandeId, 'livreur_id' => $livreurId]);
$commande = $stmt->fetch();
if (!$commande) {
    Response::notFound('Commande livree introuvable pour ce livreur.');
}

$stmt = $db->prepare('SELECT id FROM evaluations_client WHERE commande_id = :id');
$stmt->execute(['id' => $commandeId]);
if ($stmt->fetch()) {
    Response::error('Vous avez deja evalue ce client pour cette course.', 409);
}

$clientId = (int) $commande['client_id'];

try {
    $db->beginTransaction();

    $stmt = $db->prepare(
        'INSERT INTO evaluations_client (commande_id, livreur_id, client_id, note, commentaire)
         VALUES (:commande_id, :livreur_id, :client_id, :note, :commentaire)'
    );
    $stmt->execute([
        'commande_id' => $commandeId,
        'livreur_id' => $livreurId,
        'client_id' => $clientId,
        'note' => $note,
        'commentaire' => $commentaire,
    ]);

    // Recalcule la note moyenne du client (placeholders distincts : PDO non emule).
    $stmt = $db->prepare(
        'UPDATE users SET
            note_client = (SELECT ROUND(AVG(ec.note), 2) FROM evaluations_client ec WHERE ec.client_id = :cid_avg),
            nombre_evaluations_client = (SELECT COUNT(*) FROM evaluations_client ec WHERE ec.client_id = :cid_cnt)
         WHERE id = :cid_where'
    );
    $stmt->execute(['cid_avg' => $clientId, 'cid_cnt' => $clientId, 'cid_where' => $clientId]);

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    Response::error('Erreur lors de l\'enregistrement de l\'evaluation.', 422);
}

Response::success([], 'Merci, votre evaluation du client a ete enregistree.');
