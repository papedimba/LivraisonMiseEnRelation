<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    Response::error('user_id requis.', 422);
}

$db = Database::getConnection();

$stmt = $db->prepare(
    'SELECT id, role, nom, prenom, email, telephone, statut, adresse, quartier_id, created_at, derniere_connexion
     FROM users WHERE id = :id'
);
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    Response::notFound('Utilisateur introuvable.');
}

if ($user['role'] === 'livreur') {
    $stmt = $db->prepare('SELECT type_vehicule, numero_piece, statut_validation FROM livreur_details WHERE user_id = :id');
    $stmt->execute(['id' => $userId]);
    $user['livreur'] = $stmt->fetch() ?: null;
}
if ($user['role'] === 'commercant') {
    $stmt = $db->prepare('SELECT nom_boutique, categorie, description, statut_validation FROM commercant_details WHERE user_id = :id');
    $stmt->execute(['id' => $userId]);
    $user['commercant'] = $stmt->fetch() ?: null;
}

Response::success(['utilisateur' => $user]);
