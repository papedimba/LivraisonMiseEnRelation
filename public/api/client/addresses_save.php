<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$clientId = Auth::requireRole('client');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['libelle', 'adresse', 'latitude', 'longitude']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$libelle = clean_str($body['libelle']);
$adresse = clean_str($body['adresse']);
$lat = (float) $body['latitude'];
$lng = (float) $body['longitude'];

if (mb_strlen($libelle) > 80) {
    $libelle = mb_substr($libelle, 0, 80);
}
if ($adresse === '') {
    Response::error('Adresse invalide.', 422);
}

$db = Database::getConnection();

// Limite raisonnable pour eviter les abus.
$stmt = $db->prepare('SELECT COUNT(*) FROM adresses_favorites WHERE client_id = :client_id');
$stmt->execute(['client_id' => $clientId]);
if ((int) $stmt->fetchColumn() >= 20) {
    Response::error('Vous avez atteint le nombre maximum d\'adresses enregistrees.', 422);
}

$stmt = $db->prepare(
    'INSERT INTO adresses_favorites (client_id, libelle, adresse, latitude, longitude)
     VALUES (:client_id, :libelle, :adresse, :latitude, :longitude)'
);
$stmt->execute([
    'client_id' => $clientId,
    'libelle' => $libelle,
    'adresse' => $adresse,
    'latitude' => $lat,
    'longitude' => $lng,
]);

Response::created([
    'id' => (int) $db->lastInsertId(),
    'libelle' => $libelle,
    'adresse' => $adresse,
    'latitude' => $lat,
    'longitude' => $lng,
], 'Adresse enregistree.');
