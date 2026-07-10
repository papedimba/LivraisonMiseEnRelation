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

$id = (int) input($body, 'id', 0);

// Mise a jour partielle d'un moyen existant.
if ($id > 0) {
    $champs = [];
    $params = ['id' => $id];
    foreach (['nom', 'icone'] as $champ) {
        if (isset($body[$champ])) {
            $champs[] = "{$champ} = :{$champ}";
            $params[$champ] = clean_str($body[$champ]);
        }
    }
    if (isset($body['multiplicateur'])) {
        $mult = (float) $body['multiplicateur'];
        if ($mult <= 0 || $mult > 10) {
            Response::error('Le multiplicateur doit etre compris entre 0 et 10.', 422);
        }
        $champs[] = 'multiplicateur = :multiplicateur';
        $params['multiplicateur'] = $mult;
    }
    if (isset($body['actif'])) {
        $champs[] = 'actif = :actif';
        $params['actif'] = ((bool) $body['actif']) ? 1 : 0;
    }
    if (empty($champs)) {
        Response::error('Aucune donnee a mettre a jour.', 422);
    }
    $db->prepare('UPDATE moyens_transport SET ' . implode(', ', $champs) . ' WHERE id = :id')->execute($params);
    Response::success(['id' => $id], 'Moyen de transport mis a jour.');
}

// Creation.
$missing = require_fields($body, ['nom']);
if (!empty($missing)) {
    Response::error('Le nom est obligatoire.', 422, $missing);
}
$nom = clean_str($body['nom']);
$mult = isset($body['multiplicateur']) ? (float) $body['multiplicateur'] : 1.0;
if ($mult <= 0 || $mult > 10) {
    Response::error('Le multiplicateur doit etre compris entre 0 et 10.', 422);
}

// Code : fourni ou derive du nom (slug simple, unique).
$code = clean_str(input($body, 'code', ''));
if ($code === '') {
    $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $nom));
    $code = trim($code, '_') ?: 'transport';
}

$stmt = $db->prepare('SELECT id FROM moyens_transport WHERE code = :code');
$stmt->execute(['code' => $code]);
if ($stmt->fetch()) {
    Response::error('Un moyen de transport avec ce code existe deja.', 409);
}

$stmt = $db->prepare(
    'INSERT INTO moyens_transport (code, nom, icone, multiplicateur, actif)
     VALUES (:code, :nom, :icone, :multiplicateur, 1)'
);
$stmt->execute([
    'code' => $code,
    'nom' => $nom,
    'icone' => clean_str(input($body, 'icone', '')),
    'multiplicateur' => $mult,
]);

Response::created(['id' => (int) $db->lastInsertId(), 'code' => $code], 'Moyen de transport cree.');
