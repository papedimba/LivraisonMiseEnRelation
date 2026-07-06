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
$missing = require_fields($body, ['user_id', 'statut']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$userId = (int) $body['user_id'];
$statut = clean_str($body['statut']);

if (!in_array($statut, ['actif', 'suspendu', 'en_attente'], true)) {
    Response::error('Statut invalide.');
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT id, role FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    Response::notFound('Utilisateur introuvable.');
}
if ($user['role'] === 'admin') {
    Response::forbidden('Impossible de modifier le statut d\'un administrateur via cet endpoint.');
}

$db->prepare('UPDATE users SET statut = :statut WHERE id = :id')->execute(['statut' => $statut, 'id' => $userId]);

creer_notification(
    $db,
    $userId,
    'Statut du compte mis a jour',
    "Votre compte est desormais : {$statut}.",
    'compte'
);

Response::success([], 'Statut utilisateur mis a jour.');
