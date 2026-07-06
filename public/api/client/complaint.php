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
$missing = require_fields($body, ['sujet', 'message']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$commandeId = isset($body['commande_id']) && $body['commande_id'] !== '' ? (int) $body['commande_id'] : null;

$db = Database::getConnection();

$stmt = $db->prepare(
    'INSERT INTO reclamations (user_id, commande_id, sujet, message) VALUES (:user_id, :commande_id, :sujet, :message)'
);
$stmt->execute([
    'user_id' => $userId,
    'commande_id' => $commandeId,
    'sujet' => clean_str($body['sujet']),
    'message' => clean_str($body['message']),
]);

Response::created([], 'Votre reclamation a bien ete enregistree. Notre equipe vous repondra sous peu.');
