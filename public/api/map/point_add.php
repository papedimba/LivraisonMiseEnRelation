<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/carto.php';

$userId = Auth::requireLogin();
$role = Auth::role();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['nom', 'latitude', 'longitude']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$nom = clean_str($body['nom']);
if ($nom === '' || mb_strlen($nom) > 150) {
    Response::error('Nom invalide (1 a 150 caracteres).', 422);
}

$categorie = clean_str(input($body, 'categorie', 'repere'));
if (!in_array($categorie, carto_categories(), true)) {
    $categorie = 'repere';
}

$description = clean_str(input($body, 'description', ''));
if (mb_strlen($description) > 500) {
    $description = mb_substr($description, 0, 500);
}

$lat = (float) $body['latitude'];
$lng = (float) $body['longitude'];
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    Response::error('Coordonnees invalides.', 422);
}

$db = Database::getConnection();

// Un point cree par l'admin est publie directement ; sinon il est modere.
$statut = $role === 'admin' ? 'valide' : 'en_attente';

$stmt = $db->prepare(
    'INSERT INTO points_carte (user_id, nom, categorie, description, latitude, longitude, statut)
     VALUES (:user_id, :nom, :categorie, :description, :latitude, :longitude, :statut)'
);
$stmt->execute([
    'user_id' => $userId,
    'nom' => $nom,
    'categorie' => $categorie,
    'description' => $description !== '' ? $description : null,
    'latitude' => $lat,
    'longitude' => $lng,
    'statut' => $statut,
]);

Response::created([
    'id' => (int) $db->lastInsertId(),
    'statut' => $statut,
], $statut === 'valide'
    ? 'Point ajoute et publie.'
    : 'Merci ! Votre point sera visible apres validation.');
