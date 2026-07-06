<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$livreurId = Auth::requireRole('livreur');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['latitude', 'longitude']);
if (!empty($missing)) {
    Response::error('latitude et longitude requises.');
}

$lat = (float) $body['latitude'];
$lng = (float) $body['longitude'];

$db = Database::getConnection();

$db->prepare(
    'UPDATE livreur_details SET latitude = :lat, longitude = :lng, derniere_position_at = NOW() WHERE user_id = :id'
)->execute(['lat' => $lat, 'lng' => $lng, 'id' => $livreurId]);

$commandeId = isset($body['commande_id']) ? (int) $body['commande_id'] : null;
if ($commandeId) {
    $stmt = $db->prepare(
        "SELECT id FROM commandes WHERE id = :id AND livreur_id = :livreur_id AND statut IN ('acceptee','recuperee','en_cours')"
    );
    $stmt->execute(['id' => $commandeId, 'livreur_id' => $livreurId]);
    if ($stmt->fetch()) {
        $db->prepare(
            'INSERT INTO suivi_positions (commande_id, livreur_id, latitude, longitude) VALUES (:commande_id, :livreur_id, :lat, :lng)'
        )->execute(['commande_id' => $commandeId, 'livreur_id' => $livreurId, 'lat' => $lat, 'lng' => $lng]);
    }
}

Response::success([], 'Position mise a jour.');
