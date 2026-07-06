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
$missing = require_fields($body, ['nom', 'prix']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$prix = (float) $body['prix'];
if ($prix <= 0) {
    Response::error('Le prix doit etre positif.');
}

$db = Database::getConnection();

$stmt = $db->prepare(
    'INSERT INTO produits (commercant_id, nom, description, prix, categorie, stock, disponible)
     VALUES (:commercant_id, :nom, :description, :prix, :categorie, :stock, :disponible)'
);
$stmt->execute([
    'commercant_id' => $commercantId,
    'nom' => clean_str($body['nom']),
    'description' => clean_str(input($body, 'description', '')),
    'prix' => $prix,
    'categorie' => clean_str(input($body, 'categorie', '')),
    'stock' => isset($body['stock']) ? (int) $body['stock'] : null,
    'disponible' => (bool) input($body, 'disponible', true) ? 1 : 0,
]);

Response::created(['produit_id' => (int) $db->lastInsertId()], 'Produit ajoute.');
