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

$db = Database::getConnection();

$stmt = $db->prepare("SELECT statut_validation, disponibilite FROM livreur_details WHERE user_id = :id");
$stmt->execute(['id' => $livreurId]);
$livreur = $stmt->fetch();

if (!$livreur || $livreur['statut_validation'] !== 'valide') {
    Response::forbidden('Votre compte livreur n\'est pas valide.');
}
if ($livreur['disponibilite'] !== 'en_ligne') {
    Response::forbidden('Passez en ligne pour accepter des commandes.');
}

$body = request_body();
$commandeId = (int) input($body, 'commande_id', 0);
if ($commandeId <= 0) {
    Response::error('commande_id requis.');
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM commandes WHERE id = :id AND statut = 'en_attente' FOR UPDATE");
    $stmt->execute(['id' => $commandeId]);
    $commande = $stmt->fetch();

    if (!$commande) {
        $db->rollBack();
        Response::error('Cette commande n\'est plus disponible.', 409);
    }

    $db->prepare(
        "UPDATE commandes SET statut = 'acceptee', livreur_id = :livreur_id, accepted_at = NOW() WHERE id = :id"
    )->execute(['livreur_id' => $livreurId, 'id' => $commandeId]);

    creer_notification(
        $db,
        (int) $commande['client_id'],
        'Livreur trouve',
        "Votre commande {$commande['reference']} a ete acceptee par un livreur.",
        'commande',
        "/client/track.php?ref={$commande['reference']}"
    );

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    Response::error('Erreur lors de l\'acceptation de la commande.', 422);
}

Response::success(['commande_id' => $commandeId], 'Commande acceptee.');
