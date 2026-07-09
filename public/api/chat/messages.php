<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/chat.php';

$userId = Auth::requireRole('client', 'livreur');
$role = Auth::role();

$commandeId = (int) ($_GET['commande_id'] ?? 0);
if ($commandeId <= 0) {
    Response::error('commande_id requis.', 422);
}
$after = (int) ($_GET['after'] ?? 0);

$db = Database::getConnection();

$commande = chat_commande_participant($db, $commandeId, $userId, (string) $role);
if ($commande === null) {
    Response::notFound('Commande introuvable.');
}

$sql = 'SELECT id, expediteur_id, expediteur_role, message, lu, created_at
        FROM messages_course
        WHERE commande_id = :commande_id';
$params = ['commande_id' => $commandeId];
if ($after > 0) {
    $sql .= ' AND id > :after';
    $params['after'] = $after;
}
$sql .= ' ORDER BY id ASC LIMIT 500';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Marque comme lus les messages recus de l'autre participant.
$stmtLu = $db->prepare(
    'UPDATE messages_course SET lu = 1
     WHERE commande_id = :commande_id AND expediteur_role <> :role AND lu = 0'
);
$stmtLu->execute(['commande_id' => $commandeId, 'role' => $role]);

Response::success([
    'commande_id' => $commandeId,
    'disponible' => chat_disponible($commande),
    'moi' => $role,
    'messages' => $messages,
]);
