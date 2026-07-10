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

// Refuse la suppression si des commandes referencent ce moyen (integrite).
$stmt = $db->prepare('SELECT COUNT(*) FROM commandes WHERE moyen_transport_id = :id');
$stmt->execute(['id' => $id]);
if ((int) $stmt->fetchColumn() > 0) {
    Response::error('Ce moyen de transport est utilise par des commandes. Desactivez-le plutot que de le supprimer.', 409);
}

$stmt = $db->prepare('DELETE FROM moyens_transport WHERE id = :id');
$stmt->execute(['id' => $id]);
if ($stmt->rowCount() === 0) {
    Response::notFound('Moyen de transport introuvable.');
}

Response::success([], 'Moyen de transport supprime.');
