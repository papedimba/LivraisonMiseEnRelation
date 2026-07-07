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
$action = clean_str(input($body, 'action', 'creer'));
$db = Database::getConnection();

if ($action === 'toggle') {
    $id = (int) input($body, 'id', 0);
    $actif = (bool) input($body, 'actif', true) ? 1 : 0;
    $db->prepare('UPDATE codes_promo SET actif = :actif WHERE id = :id')->execute(['actif' => $actif, 'id' => $id]);
    Response::success([], 'Code promo mis a jour.');
}

if ($action === 'supprimer') {
    $id = (int) input($body, 'id', 0);
    $db->prepare('DELETE FROM codes_promo WHERE id = :id')->execute(['id' => $id]);
    Response::success([], 'Code promo supprime.');
}

// action = creer
$missing = require_fields($body, ['code', 'type', 'valeur']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$code = strtoupper(clean_str($body['code']));
$type = clean_str($body['type']);
$valeur = (float) $body['valeur'];

if (!in_array($type, ['pourcentage', 'montant'], true)) {
    Response::error('Type invalide.');
}
if ($valeur <= 0) {
    Response::error('La valeur doit etre positive.');
}
if ($type === 'pourcentage' && $valeur > 100) {
    Response::error('Un pourcentage ne peut pas depasser 100.');
}

$existe = $db->prepare('SELECT id FROM codes_promo WHERE code = :code');
$existe->execute(['code' => $code]);
if ($existe->fetch()) {
    Response::error('Ce code existe deja.', 409);
}

$stmt = $db->prepare(
    'INSERT INTO codes_promo (code, type, valeur, montant_min, usage_max, date_debut, date_fin, actif)
     VALUES (:code, :type, :valeur, :montant_min, :usage_max, :date_debut, :date_fin, 1)'
);
$stmt->execute([
    'code' => $code,
    'type' => $type,
    'valeur' => $valeur,
    'montant_min' => (float) input($body, 'montant_min', 0),
    'usage_max' => ($u = (int) input($body, 'usage_max', 0)) > 0 ? $u : null,
    'date_debut' => ($d = clean_str(input($body, 'date_debut', ''))) !== '' ? $d : null,
    'date_fin' => ($f = clean_str(input($body, 'date_fin', ''))) !== '' ? $f : null,
]);

Response::created(['id' => (int) $db->lastInsertId()], 'Code promo cree.');
