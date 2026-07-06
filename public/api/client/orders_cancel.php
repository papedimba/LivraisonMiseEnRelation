<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$clientId = Auth::requireRole('client');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$commandeId = (int) input($body, 'commande_id', 0);
$motif = clean_str(input($body, 'motif', 'Annulee par le client'));

if ($commandeId <= 0) {
    Response::error('commande_id requis.');
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT * FROM commandes WHERE id = :id AND client_id = :client_id');
$stmt->execute(['id' => $commandeId, 'client_id' => $clientId]);
$commande = $stmt->fetch();

if (!$commande) {
    Response::notFound('Commande introuvable.');
}

if (in_array($commande['statut'], ['livree', 'annulee'], true)) {
    Response::error('Cette commande ne peut plus etre annulee.');
}

$db->prepare("UPDATE commandes SET statut = 'annulee', motif_annulation = :motif, cancelled_at = NOW() WHERE id = :id")
    ->execute(['motif' => $motif, 'id' => $commandeId]);

if ($commande['livreur_id']) {
    creer_notification(
        $db,
        (int) $commande['livreur_id'],
        'Commande annulee',
        "La commande {$commande['reference']} a ete annulee par le client.",
        'commande'
    );
}

Response::success([], 'Commande annulee.');
