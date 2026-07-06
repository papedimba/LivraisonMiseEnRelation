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
$id = (int) input($body, 'id', 0);
if ($id <= 0) {
    Response::error('id requis.');
}

$db = Database::getConnection();

$champs = [];
$params = ['id' => $id];

foreach (['nom', 'icone'] as $champ) {
    if (isset($body[$champ])) {
        $champs[] = "{$champ} = :{$champ}";
        $params[$champ] = clean_str($body[$champ]);
    }
}
foreach (['tarif_base', 'tarif_km', 'supplement_express'] as $champ) {
    if (isset($body[$champ])) {
        $champs[] = "{$champ} = :{$champ}";
        $params[$champ] = (float) $body[$champ];
    }
}
if (isset($body['actif'])) {
    $champs[] = 'actif = :actif';
    $params['actif'] = (bool) $body['actif'] ? 1 : 0;
}

if (empty($champs)) {
    Response::error('Aucune donnee a mettre a jour.');
}

$sql = 'UPDATE types_livraison SET ' . implode(', ', $champs) . ' WHERE id = :id';
$db->prepare($sql)->execute($params);

Response::success([], 'Type de livraison mis a jour.');
