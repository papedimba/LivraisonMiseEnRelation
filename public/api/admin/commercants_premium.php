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
$missing = require_fields($body, ['user_id', 'premium']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$userId = (int) $body['user_id'];
$premium = (bool) $body['premium'] ? 1 : 0;
$dureeMois = (int) input($body, 'duree_mois', 1);

$db = Database::getConnection();

$stmt = $db->prepare('SELECT user_id FROM commercant_details WHERE user_id = :id');
$stmt->execute(['id' => $userId]);
if (!$stmt->fetch()) {
    Response::notFound('Commercant introuvable.');
}

if ($premium === 1) {
    $expire = date('Y-m-d H:i:s', strtotime("+{$dureeMois} months"));
    $db->prepare('UPDATE commercant_details SET abonnement_premium = 1, abonnement_expire_at = :exp WHERE user_id = :id')
        ->execute(['exp' => $expire, 'id' => $userId]);
    creer_notification($db, $userId, 'Abonnement Premium active',
        "Votre boutique est desormais Premium jusqu'au {$expire}. Elle est mise en avant aupres des clients.", 'compte');
    Response::success(['expire_at' => $expire], 'Premium active.');
}

$db->prepare('UPDATE commercant_details SET abonnement_premium = 0, abonnement_expire_at = NULL WHERE user_id = :id')
    ->execute(['id' => $userId]);
creer_notification($db, $userId, 'Abonnement Premium desactive',
    'Votre abonnement Premium a ete desactive.', 'compte');
Response::success([], 'Premium desactive.');
