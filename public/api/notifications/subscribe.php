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
    Response::error('Abonnement invalide (endpoint manquant).');
}

$keys = $body['keys'] ?? [];
$p256dh = is_array($keys) ? clean_str($keys['p256dh'] ?? '') : '';
$auth = is_array($keys) ? clean_str($keys['auth'] ?? '') : '';

$db = Database::getConnection();

// Un endpoint est unique : on met a jour le proprietaire et les cles si besoin.
$stmt = $db->prepare(
    'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth)
     VALUES (:user_id, :endpoint, :p256dh, :auth)
     ON DUPLICATE KEY UPDATE user_id = :user_id2, p256dh = :p256dh2, auth = :auth2'
);
$stmt->execute([
    'user_id' => $userId,
    'endpoint' => $endpoint,
    'p256dh' => $p256dh ?: null,
    'auth' => $auth ?: null,
    'user_id2' => $userId,
    'p256dh2' => $p256dh ?: null,
    'auth2' => $auth ?: null,
]);

Response::success([], 'Abonnement aux notifications enregistre.');
