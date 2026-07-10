<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/carto.php';

$userId = Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['point_id', 'type']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$pointId = (int) $body['point_id'];
$type = clean_str($body['type']);
if (!in_array($type, ['confirme', 'signale'], true)) {
    Response::error('Type de vote invalide.', 422);
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT id, statut FROM points_carte WHERE id = :id");
$stmt->execute(['id' => $pointId]);
$point = $stmt->fetch();
if (!$point || $point['statut'] !== 'valide') {
    Response::notFound('Point introuvable.');
}

// Un seul vote par utilisateur et par point (modifiable).
$stmt = $db->prepare(
    'INSERT INTO points_carte_votes (point_id, user_id, type)
     VALUES (:point_id, :user_id, :type_ins)
     ON DUPLICATE KEY UPDATE type = :type_upd'
);
$stmt->execute([
    'point_id' => $pointId,
    'user_id' => $userId,
    'type_ins' => $type,
    'type_upd' => $type,
]);

carto_recompter_votes($db, $pointId);

// Auto-moderation : un point trop signale repasse en attente de verification.
$stmt = $db->prepare('SELECT confirmations, signalements FROM points_carte WHERE id = :id');
$stmt->execute(['id' => $pointId]);
$compteurs = $stmt->fetch();
$remoderationRequise = ((int) $compteurs['signalements'] >= 5)
    && ((int) $compteurs['signalements'] > (int) $compteurs['confirmations']);
if ($remoderationRequise) {
    $db->prepare("UPDATE points_carte SET statut = 'en_attente' WHERE id = :id")->execute(['id' => $pointId]);
}

Response::success([
    'confirmations' => (int) $compteurs['confirmations'],
    'signalements' => (int) $compteurs['signalements'],
    'remis_en_moderation' => $remoderationRequise,
], 'Merci pour votre contribution.');
