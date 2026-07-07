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
$action = clean_str(input($body, 'action', ''));

$db = Database::getConnection();

switch ($action) {
    case 'creer_ville':
        $nom = clean_str(input($body, 'nom', ''));
        if ($nom === '') {
            Response::error('Le nom de la ville est requis.');
        }
        $stmt = $db->prepare('INSERT INTO villes (nom) VALUES (:nom)');
        $stmt->execute(['nom' => $nom]);
        Response::created(['id' => (int) $db->lastInsertId()], 'Ville ajoutee.');
        // no break (Response::* termine le script)

    case 'creer_quartier':
        $villeId = (int) input($body, 'ville_id', 0);
        $nom = clean_str(input($body, 'nom', ''));
        if ($villeId <= 0 || $nom === '') {
            Response::error('Ville et nom du quartier requis.');
        }
        $check = $db->prepare('SELECT id FROM villes WHERE id = :id');
        $check->execute(['id' => $villeId]);
        if (!$check->fetch()) {
            Response::error('Ville introuvable.');
        }
        $stmt = $db->prepare('INSERT INTO quartiers (ville_id, nom) VALUES (:ville_id, :nom)');
        $stmt->execute(['ville_id' => $villeId, 'nom' => $nom]);
        Response::created(['id' => (int) $db->lastInsertId()], 'Quartier ajoute.');

    case 'toggle_ville':
        $id = (int) input($body, 'id', 0);
        $actif = (bool) input($body, 'actif', true) ? 1 : 0;
        $db->prepare('UPDATE villes SET actif = :actif WHERE id = :id')->execute(['actif' => $actif, 'id' => $id]);
        // Desactiver une ville desactive aussi ses quartiers.
        if ($actif === 0) {
            $db->prepare('UPDATE quartiers SET actif = 0 WHERE ville_id = :id')->execute(['id' => $id]);
        }
        Response::success([], 'Ville mise a jour.');

    case 'toggle_quartier':
        $id = (int) input($body, 'id', 0);
        $actif = (bool) input($body, 'actif', true) ? 1 : 0;
        $db->prepare('UPDATE quartiers SET actif = :actif WHERE id = :id')->execute(['actif' => $actif, 'id' => $id]);
        Response::success([], 'Quartier mis a jour.');

    default:
        Response::error('Action inconnue.');
}
