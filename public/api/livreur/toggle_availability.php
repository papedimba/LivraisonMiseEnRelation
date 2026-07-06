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
$disponibilite = clean_str(input($body, 'disponibilite', ''));
if (!in_array($disponibilite, ['en_ligne', 'hors_ligne'], true)) {
    Response::error('Valeur de disponibilite invalide.');
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT statut_validation FROM livreur_details WHERE user_id = :id");
$stmt->execute(['id' => $livreurId]);
$livreur = $stmt->fetch();

if (!$livreur) {
    Response::notFound('Profil livreur introuvable.');
}

if ($livreur['statut_validation'] !== 'valide' && $disponibilite === 'en_ligne') {
    Response::forbidden('Votre compte livreur n\'est pas encore valide. Vous ne pouvez pas passer en ligne.');
}

$db->prepare('UPDATE livreur_details SET disponibilite = :dispo WHERE user_id = :id')
    ->execute(['dispo' => $disponibilite, 'id' => $livreurId]);

Response::success(['disponibilite' => $disponibilite], 'Statut mis a jour.');
