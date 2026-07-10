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

$db = Database::getConnection();
$body = request_body();

$missing = require_fields($body, ['nom']);
if (!empty($missing)) {
    Response::error('Le nom est obligatoire.', 422, $missing);
}
$nom = clean_str($body['nom']);

$code = clean_str(input($body, 'code', ''));
if ($code === '') {
    $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $nom));
    $code = trim($code, '_') ?: 'type';
}

$stmt = $db->prepare('SELECT id FROM types_livraison WHERE code = :code');
$stmt->execute(['code' => $code]);
if ($stmt->fetch()) {
    Response::error('Un type avec ce code existe deja.', 409);
}

$tarifBase = (float) input($body, 'tarif_base', 500);
$tarifKm = (float) input($body, 'tarif_km', 150);
$supExpress = (float) input($body, 'supplement_express', 1000);
if ($tarifBase < 0 || $tarifKm < 0 || $supExpress < 0) {
    Response::error('Les tarifs ne peuvent pas etre negatifs.', 422);
}

$stmt = $db->prepare(
    'INSERT INTO types_livraison (code, nom, icone, tarif_base, tarif_km, supplement_express, actif)
     VALUES (:code, :nom, :icone, :tarif_base, :tarif_km, :supplement_express, 1)'
);
$stmt->execute([
    'code' => $code,
    'nom' => $nom,
    'icone' => clean_str(input($body, 'icone', '')),
    'tarif_base' => $tarifBase,
    'tarif_km' => $tarifKm,
    'supplement_express' => $supExpress,
]);

Response::created(['id' => (int) $db->lastInsertId(), 'code' => $code], 'Type de colis cree.');
