<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['email', 'password']);
if (!empty($missing)) {
    Response::error('Email et mot de passe requis.', 422, $missing);
}

$email = strtolower(clean_str($body['email']));
$password = (string) $body['password'];

$db = Database::getConnection();

$attempts = $db->prepare(
    'SELECT COUNT(*) AS nb FROM journal_connexions
     WHERE email_tente = :email AND succes = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)'
);
$attempts->execute(['email' => $email]);
if ((int) $attempts->fetch()['nb'] >= 10) {
    Response::error('Trop de tentatives echouees. Reessayez plus tard.', 429);
}

$stmt = $db->prepare('SELECT * FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (!$user || !Auth::verifyPassword($password, $user['password_hash'])) {
    Auth::enregistrerTentative($db, $user['id'] ?? null, $email, false);
    Response::error('Identifiants incorrects.', 401);
}

if ($user['statut'] === 'suspendu') {
    Auth::enregistrerTentative($db, $user['id'], $email, false);
    Response::forbidden('Ce compte est suspendu. Contactez le support.');
}

// Les livreurs et commercants en attente peuvent se connecter afin de deposer
// leurs documents et suivre l'etat de leur validation. Leur espace reste
// restreint tant qu'ils ne sont pas valides. Les autres comptes en attente
// (cas anormal pour un client) restent bloques.
if ($user['statut'] === 'en_attente' && !in_array($user['role'], ['livreur', 'commercant'], true)) {
    Auth::enregistrerTentative($db, $user['id'], $email, false);
    Response::forbidden('Ce compte est en attente de validation par un administrateur.');
}

Auth::enregistrerTentative($db, $user['id'], $email, true);
Auth::login($user);

$db->prepare('UPDATE users SET derniere_connexion = NOW() WHERE id = :id')->execute(['id' => $user['id']]);

Response::success([
    'user_id' => (int) $user['id'],
    'role' => $user['role'],
    'nom' => $user['nom'],
    'prenom' => $user['prenom'],
], 'Connexion reussie.');
