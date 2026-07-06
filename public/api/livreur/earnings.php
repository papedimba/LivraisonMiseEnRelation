<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$livreurId = Auth::requireRole('livreur');

$db = Database::getConnection();

$stmt = $db->prepare('SELECT solde, note_moyenne, nombre_courses FROM livreur_details WHERE user_id = :id');
$stmt->execute(['id' => $livreurId]);
$livreur = $stmt->fetch();

function periode_gain(PDO $db, int $livreurId, string $interval): float
{
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(montant_estime - commission_montant), 0) AS total
         FROM commandes
         WHERE livreur_id = :livreur_id AND statut = 'livree' AND delivered_at >= (NOW() - INTERVAL {$interval})"
    );
    $stmt->execute(['livreur_id' => $livreurId]);
    return (float) $stmt->fetch()['total'];
}

Response::success([
    'solde_disponible' => (float) ($livreur['solde'] ?? 0),
    'note_moyenne' => (float) ($livreur['note_moyenne'] ?? 5),
    'nombre_courses' => (int) ($livreur['nombre_courses'] ?? 0),
    'gains_jour' => periode_gain($db, $livreurId, '1 DAY'),
    'gains_semaine' => periode_gain($db, $livreurId, '7 DAY'),
    'gains_mois' => periode_gain($db, $livreurId, '30 DAY'),
]);
