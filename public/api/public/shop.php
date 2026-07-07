<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireLogin();

$commercantId = (int) ($_GET['id'] ?? 0);
if ($commercantId <= 0) {
    Response::error('Identifiant de boutique requis.');
}

$db = Database::getConnection();

$stmt = $db->prepare(
    "SELECT cd.user_id, cd.nom_boutique, cd.categorie, cd.description, cd.adresse,
            cd.latitude, cd.longitude, cd.note_moyenne
     FROM commercant_details cd
     WHERE cd.user_id = :id AND cd.statut_validation = 'valide'"
);
$stmt->execute(['id' => $commercantId]);
$boutique = $stmt->fetch();

if (!$boutique) {
    Response::notFound('Boutique introuvable ou non disponible.');
}

$stmt = $db->prepare(
    'SELECT id, nom, description, prix, categorie, image, stock
     FROM produits
     WHERE commercant_id = :id AND disponible = 1
     ORDER BY categorie, nom'
);
$stmt->execute(['id' => $commercantId]);

Response::success([
    'boutique' => $boutique,
    'produits' => $stmt->fetchAll(),
]);
