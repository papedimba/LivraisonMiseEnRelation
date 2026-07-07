<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$userId = Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['mot_de_passe_actuel', 'nouveau_mot_de_passe']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$actuel = (string) $body['mot_de_passe_actuel'];
$nouveau = (string) $body['nouveau_mot_de_passe'];

if (strlen($nouveau) < 8) {
    Response::error('Le nouveau mot de passe doit contenir au moins 8 caracteres.');
}
if ($nouveau === $actuel) {
    Response::error('Le nouveau mot de passe doit etre different de l\'ancien.');
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if (!$user || !Auth::verifyPassword($actuel, $user['password_hash'])) {
    Response::error('Le mot de passe actuel est incorrect.', 403);
}

$db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
    ->execute(['hash' => Auth::hashPassword($nouveau), 'id' => $userId]);

Response::success([], 'Mot de passe modifie avec succes.');
