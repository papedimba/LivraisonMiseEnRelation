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
$missing = require_fields($body, ['commande_id', 'statut']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$commandeId = (int) $body['commande_id'];
$nouveauStatut = clean_str($body['statut']);

// La transition finale vers "livree" passe par confirm_delivery.php (preuve de
// livraison obligatoire : code client ou photo).
$transitionsAutorisees = [
    'acceptee' => 'recuperee',
    'recuperee' => 'en_cours',
];

$db = Database::getConnection();

$stmt = $db->prepare('SELECT * FROM commandes WHERE id = :id AND livreur_id = :livreur_id');
$stmt->execute(['id' => $commandeId, 'livreur_id' => $livreurId]);
$commande = $stmt->fetch();

if (!$commande) {
    Response::notFound('Commande introuvable pour ce livreur.');
}

if (!isset($transitionsAutorisees[$commande['statut']]) || $transitionsAutorisees[$commande['statut']] !== $nouveauStatut) {
    Response::error("Transition de statut invalide : {$commande['statut']} -> {$nouveauStatut}");
}

try {
    $db->beginTransaction();

    $champDate = $nouveauStatut === 'recuperee' ? ', recovered_at = NOW()' : '';
    $db->prepare("UPDATE commandes SET statut = :statut{$champDate} WHERE id = :id")
        ->execute(['statut' => $nouveauStatut, 'id' => $commandeId]);

    if ($nouveauStatut === 'en_cours') {
        creer_notification(
            $db,
            (int) $commande['client_id'],
            'Livreur en route',
            "Votre livreur est en route vers vous pour la commande {$commande['reference']}.",
            'commande'
        );
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    Response::error('Erreur lors de la mise a jour du statut.', 422);
}

Response::success(['statut' => $nouveauStatut], 'Statut mis a jour.');
