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
$missing = require_fields($body, ['id']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$db = Database::getConnection();

$stmt = $db->prepare('DELETE FROM adresses_favorites WHERE id = :id AND client_id = :client_id');
$stmt->execute(['id' => (int) $body['id'], 'client_id' => $clientId]);

if ($stmt->rowCount() === 0) {
    Response::notFound('Adresse introuvable.');
}

Response::success([], 'Adresse supprimee.');
