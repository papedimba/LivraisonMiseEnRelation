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
$id = (int) input($body, 'id', 0);
if ($id <= 0) {
    Response::error('id requis.', 422);
}

$db = Database::getConnection();

// type_livraison_id est NOT NULL sur commandes : on refuse la suppression si
// des commandes l'utilisent (proposer la desactivation a la place).
$stmt = $db->prepare('SELECT COUNT(*) FROM commandes WHERE type_livraison_id = :id');
$stmt->execute(['id' => $id]);
if ((int) $stmt->fetchColumn() > 0) {
    Response::error('Ce type est utilise par des commandes. Desactivez-le plutot que de le supprimer.', 409);
}

$stmt = $db->prepare('DELETE FROM types_livraison WHERE id = :id');
$stmt->execute(['id' => $id]);
if ($stmt->rowCount() === 0) {
    Response::notFound('Type de colis introuvable.');
}

Response::success([], 'Type de colis supprime.');
