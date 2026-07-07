<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/dispatch.php';

$livreurId = Auth::requireRole('livreur');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$offreId = (int) input($body, 'offre_id', 0);
$decision = clean_str(input($body, 'decision', ''));

if ($offreId <= 0 || !in_array($decision, ['accepter', 'refuser'], true)) {
    Response::error('Parametres invalides.');
}

$db = Database::getConnection();

// --- REFUS ----------------------------------------------------------------
if ($decision === 'refuser') {
    $stmt = $db->prepare("SELECT commande_id FROM dispatch_offres WHERE id = :id AND livreur_id = :lid AND statut = 'en_attente'");
    $stmt->execute(['id' => $offreId, 'lid' => $livreurId]);
    $offre = $stmt->fetch();
    if (!$offre) {
        Response::notFound('Offre introuvable ou deja traitee.');
    }
    $db->prepare("UPDATE dispatch_offres SET statut = 'refusee' WHERE id = :id")->execute(['id' => $offreId]);
    // Proposer immediatement au livreur suivant.
    dispatcher_commande($db, (int) $offre['commande_id']);
    Response::success([], 'Course refusee.');
}

// --- ACCEPTATION ----------------------------------------------------------
try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM dispatch_offres WHERE id = :id AND livreur_id = :lid AND statut = 'en_attente' FOR UPDATE");
    $stmt->execute(['id' => $offreId, 'lid' => $livreurId]);
    $offre = $stmt->fetch();
    if (!$offre) {
        $db->rollBack();
        Response::error('Cette offre n\'est plus valide.', 409);
    }

    $stmt = $db->prepare("SELECT * FROM commandes WHERE id = :id AND statut = 'en_attente' FOR UPDATE");
    $stmt->execute(['id' => $offre['commande_id']]);
    $commande = $stmt->fetch();
    if (!$commande) {
        $db->prepare("UPDATE dispatch_offres SET statut = 'expiree' WHERE id = :id")->execute(['id' => $offreId]);
        $db->commit();
        Response::error('Cette course n\'est plus disponible.', 409);
    }

    // Verifier que le livreur n'a pas deja une course active.
    $stmt = $db->prepare("SELECT id FROM commandes WHERE livreur_id = :lid AND statut IN ('acceptee','recuperee','en_cours') LIMIT 1");
    $stmt->execute(['lid' => $livreurId]);
    if ($stmt->fetch()) {
        $db->rollBack();
        Response::error('Vous avez deja une course en cours.', 409);
    }

    $db->prepare("UPDATE commandes SET statut = 'acceptee', livreur_id = :lid, accepted_at = NOW() WHERE id = :id")
        ->execute(['lid' => $livreurId, 'id' => $offre['commande_id']]);

    $db->prepare("UPDATE dispatch_offres SET statut = 'acceptee' WHERE id = :id")->execute(['id' => $offreId]);
    // Fermer les autres offres en attente pour cette commande.
    $db->prepare("UPDATE dispatch_offres SET statut = 'expiree' WHERE commande_id = :cid AND statut = 'en_attente' AND id <> :id")
        ->execute(['cid' => $offre['commande_id'], 'id' => $offreId]);

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
    Response::error('Erreur lors de l\'acceptation.', 422);
}

Response::success(['commande_id' => (int) $offre['commande_id']], 'Course acceptee.');
