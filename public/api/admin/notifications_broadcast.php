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

$body = request_body();
$missing = require_fields($body, ['titre', 'message']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$titre = clean_str($body['titre']);
$message = clean_str($body['message']);
$role = clean_str(input($body, 'role', ''));

$db = Database::getConnection();

$sql = 'SELECT id FROM users WHERE statut = \'actif\'';
$params = [];
if (in_array($role, ['client', 'livreur', 'commercant'], true)) {
    $sql .= ' AND role = :role';
    $params['role'] = $role;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$utilisateurs = $stmt->fetchAll();

$stmtInsert = $db->prepare(
    "INSERT INTO notifications (user_id, titre, message, type) VALUES (:user_id, :titre, :message, 'promotion')"
);
foreach ($utilisateurs as $utilisateur) {
    $stmtInsert->execute(['user_id' => $utilisateur['id'], 'titre' => $titre, 'message' => $message]);
}

Response::success(['nombre_destinataires' => count($utilisateurs)], 'Notification envoyee.');
