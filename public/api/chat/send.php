<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/chat.php';

$userId = Auth::requireRole('client', 'livreur');
$role = Auth::role();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['commande_id', 'message']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$message = clean_str($body['message']);
if ($message === '') {
    Response::error('Message vide.', 422);
}
if (mb_strlen($message) > 1000) {
    $message = mb_substr($message, 0, 1000);
}

$db = Database::getConnection();

$commande = chat_commande_participant($db, (int) $body['commande_id'], $userId, (string) $role);
if ($commande === null) {
    Response::notFound('Commande introuvable.');
}
if (!chat_disponible($commande)) {
    Response::error('La messagerie n\'est pas disponible pour cette commande.', 409);
}

$stmt = $db->prepare(
    'INSERT INTO messages_course (commande_id, expediteur_id, expediteur_role, message)
     VALUES (:commande_id, :expediteur_id, :expediteur_role, :message)'
);
$stmt->execute([
    'commande_id' => $commande['id'],
    'expediteur_id' => $userId,
    'expediteur_role' => $role,
    'message' => $message,
]);
$messageId = (int) $db->lastInsertId();

// Notifier l'autre participant (best-effort).
$autreId = chat_autre_participant($commande, (string) $role);
if ($autreId !== null) {
    $lien = $role === 'client'
        ? '/livreur/dashboard.php'
        : '/client/track.php?ref=' . rawurlencode($commande['reference']);
    $apercu = mb_strlen($message) > 80 ? mb_substr($message, 0, 80) . '...' : $message;
    creer_notification($db, $autreId, 'Nouveau message', $apercu, 'message', $lien);
}

Response::created([
    'id' => $messageId,
    'commande_id' => $commande['id'],
    'expediteur_role' => $role,
    'message' => $message,
], 'Message envoye.');
