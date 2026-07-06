<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('client');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['type_livraison_id', 'lat_depart', 'lng_depart', 'lat_arrivee', 'lng_arrivee']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT * FROM types_livraison WHERE id = :id AND actif = 1');
$stmt->execute(['id' => (int) $body['type_livraison_id']]);
$type = $stmt->fetch();
if (!$type) {
    Response::error('Type de livraison invalide.');
}

$latDepart = (float) $body['lat_depart'];
$lngDepart = (float) $body['lng_depart'];
$latArrivee = (float) $body['lat_arrivee'];
$lngArrivee = (float) $body['lng_arrivee'];
$express = (bool) input($body, 'express', false);

$distanceKm = haversine_distance_km($latDepart, $lngDepart, $latArrivee, $lngArrivee);
$montant = estimer_cout((float) $type['tarif_base'], (float) $type['tarif_km'], $distanceKm, $express, (float) $type['supplement_express']);

Response::success([
    'distance_km' => $distanceKm,
    'montant_estime' => $montant,
    'devise' => 'FCFA',
    'type_livraison' => $type['nom'],
]);
