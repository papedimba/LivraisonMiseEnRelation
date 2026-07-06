<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = request_body();
    foreach ($body as $cle => $valeur) {
        $cle = clean_str((string) $cle);
        if ($cle === '') {
            continue;
        }
        $stmt = $db->prepare(
            'INSERT INTO parametres (cle, valeur) VALUES (:cle, :valeur)
             ON DUPLICATE KEY UPDATE valeur = :valeur'
        );
        $stmt->execute(['cle' => $cle, 'valeur' => (string) $valeur]);
    }
    Response::success([], 'Parametres mis a jour.');
}

$stmt = $db->query('SELECT cle, valeur FROM parametres');
$parametres = [];
foreach ($stmt->fetchAll() as $row) {
    $parametres[$row['cle']] = $row['valeur'];
}

Response::success(['parametres' => $parametres]);
