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
$missing = require_fields($body, ['reclamation_id', 'reponse']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$reclamationId = (int) $body['reclamation_id'];
$reponse = clean_str($body['reponse']);
$statut = clean_str(input($body, 'statut', 'resolue'));

if (!in_array($statut, ['ouverte', 'en_cours', 'resolue', 'fermee'], true)) {
    Response::error('Statut invalide.');
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT * FROM reclamations WHERE id = :id');
$stmt->execute(['id' => $reclamationId]);
$reclamation = $stmt->fetch();

if (!$reclamation) {
    Response::notFound('Reclamation introuvable.');
}

$db->prepare(
    'UPDATE reclamations SET reponse = :reponse, statut = :statut, traite_par = :admin_id WHERE id = :id'
)->execute([
    'reponse' => $reponse,
    'statut' => $statut,
    'admin_id' => $adminId,
    'id' => $reclamationId,
]);

creer_notification(
    $db,
    (int) $reclamation['user_id'],
    'Reponse a votre reclamation',
    $reponse,
    'reclamation'
);

Response::success([], 'Reponse envoyee.');
