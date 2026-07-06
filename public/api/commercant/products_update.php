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

$stmt = $db->prepare('SELECT id FROM produits WHERE id = :id AND commercant_id = :commercant_id');
$stmt->execute(['id' => $produitId, 'commercant_id' => $commercantId]);
if (!$stmt->fetch()) {
    Response::notFound('Produit introuvable.');
}

$champs = [];
$params = ['id' => $produitId];

foreach (['nom', 'description', 'categorie'] as $champ) {
    if (isset($body[$champ])) {
        $champs[] = "{$champ} = :{$champ}";
        $params[$champ] = clean_str($body[$champ]);
    }
}
if (isset($body['prix'])) {
    $champs[] = 'prix = :prix';
    $params['prix'] = (float) $body['prix'];
}
if (isset($body['stock'])) {
    $champs[] = 'stock = :stock';
    $params['stock'] = (int) $body['stock'];
}
if (isset($body['disponible'])) {
    $champs[] = 'disponible = :disponible';
    $params['disponible'] = (bool) $body['disponible'] ? 1 : 0;
}

if (empty($champs)) {
    Response::error('Aucune donnee a mettre a jour.');
}

$sql = 'UPDATE produits SET ' . implode(', ', $champs) . ' WHERE id = :id';
$db->prepare($sql)->execute($params);

Response::success([], 'Produit mis a jour.');
