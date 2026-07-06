<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$commercantId = Auth::requireRole('commercant');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$produitId = (int) input($body, 'produit_id', 0);
if ($produitId <= 0) {
    Response::error('produit_id requis.');
}

$db = Database::getConnection();

$stmt = $db->prepare('DELETE FROM produits WHERE id = :id AND commercant_id = :commercant_id');
$stmt->execute(['id' => $produitId, 'commercant_id' => $commercantId]);

if ($stmt->rowCount() === 0) {
    Response::notFound('Produit introuvable.');
}

Response::success([], 'Produit supprime.');
