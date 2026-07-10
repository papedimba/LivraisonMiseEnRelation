<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['id', 'decision']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$id = (int) $body['id'];
$decision = clean_str($body['decision']);
if (!in_array($decision, ['valide', 'rejete', 'supprimer'], true)) {
    Response::error('Decision invalide.', 422);
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT id, user_id, nom FROM points_carte WHERE id = :id');
$stmt->execute(['id' => $id]);
$point = $stmt->fetch();
if (!$point) {
    Response::notFound('Point introuvable.');
}

if ($decision === 'supprimer') {
    $db->prepare('DELETE FROM points_carte WHERE id = :id')->execute(['id' => $id]);
    Response::success([], 'Point supprime.');
}

$statut = $decision === 'valide' ? 'valide' : 'rejete';

// Un point revalide repart d'un historique de signalements propre.
if ($statut === 'valide') {
    $db->prepare("UPDATE points_carte SET statut = 'valide', signalements = 0 WHERE id = :id_up")->execute(['id_up' => $id]);
    $db->prepare("DELETE FROM points_carte_votes WHERE point_id = :id_v AND type = 'signale'")->execute(['id_v' => $id]);
} else {
    $db->prepare("UPDATE points_carte SET statut = 'rejete' WHERE id = :id")->execute(['id' => $id]);
}

// Notifie le contributeur de la decision (best-effort).
if ($point['user_id']) {
    creer_notification(
        $db,
        (int) $point['user_id'],
        'Contribution cartographie',
        $statut === 'valide'
            ? "Votre point \"{$point['nom']}\" a ete valide. Merci !"
            : "Votre point \"{$point['nom']}\" n'a pas ete retenu.",
        'carto'
    );
}

Response::success(['statut' => $statut], 'Point ' . ($statut === 'valide' ? 'valide' : 'rejete') . '.');
