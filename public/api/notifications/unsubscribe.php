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
$endpoint = clean_str(input($body, 'endpoint', ''));
if ($endpoint === '') {
    Response::error('Endpoint manquant.');
}

$db = Database::getConnection();
$db->prepare('DELETE FROM push_subscriptions WHERE endpoint = :endpoint AND user_id = :user_id')
    ->execute(['endpoint' => $endpoint, 'user_id' => $userId]);

Response::success([], 'Desabonnement effectue.');
