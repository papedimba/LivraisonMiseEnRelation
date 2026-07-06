<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$commercantId = Auth::requireRole('commercant');

$db = Database::getConnection();

$stmt = $db->prepare(
    'SELECT cd.*, u.nom, u.prenom, u.email, u.telephone
     FROM commercant_details cd
     JOIN users u ON u.id = cd.user_id
     WHERE cd.user_id = :id'
);
$stmt->execute(['id' => $commercantId]);
$boutique = $stmt->fetch();

if (!$boutique) {
    Response::notFound('Boutique introuvable.');
}

Response::success(['boutique' => $boutique]);
