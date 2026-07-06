<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$commercantId = Auth::requireRole('commercant');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$db = Database::getConnection();

$champs = [];
$params = ['id' => $commercantId];

foreach (['nom_boutique', 'categorie', 'description', 'adresse'] as $champ) {
    if (isset($body[$champ])) {
        $champs[] = "{$champ} = :{$champ}";
        $params[$champ] = clean_str($body[$champ]);
    }
}

foreach (['latitude', 'longitude'] as $champ) {
    if (isset($body[$champ])) {
        $champs[] = "{$champ} = :{$champ}";
        $params[$champ] = (float) $body[$champ];
    }
}

if (empty($champs)) {
    Response::error('Aucune donnee a mettre a jour.');
}

$sql = 'UPDATE commercant_details SET ' . implode(', ', $champs) . ' WHERE user_id = :id';
$db->prepare($sql)->execute($params);

Response::success([], 'Boutique mise a jour.');
