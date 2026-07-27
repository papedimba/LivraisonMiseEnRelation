<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/negociation.php';

/**
 * Marchandage cote livreur, sur une commande payee en especes :
 *  - 'proposer' : soumet (ou met a jour) son propre prix pour cette commande.
 *  - 'accepter' / 'refuser' : reagit a la derniere contre-offre du client.
 */

$livreurId = Auth::requireRole('livreur');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$commandeId = (int) input($body, 'commande_id', 0);
$decision = clean_str(input($body, 'decision', ''));
$montant = (float) input($body, 'montant', 0);

if ($commandeId <= 0 || !in_array($decision, ['proposer', 'accepter', 'refuser'], true)) {
    Response::error('Parametres invalides.');
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT statut_validation, disponibilite FROM livreur_details WHERE user_id = :id");
$stmt->execute(['id' => $livreurId]);
$livreur = $stmt->fetch();
if (!$livreur || $livreur['statut_validation'] !== 'valide') {
    Response::forbidden('Votre compte livreur n\'est pas valide.');
}
if ($livreur['disponibilite'] !== 'en_ligne') {
    Response::forbidden('Passez en ligne pour negocier une commande.');
}

$stmt = $db->prepare("SELECT * FROM commandes WHERE id = :id AND statut = 'en_attente'");
$stmt->execute(['id' => $commandeId]);
$commande = $stmt->fetch();
if (!$commande) {
    Response::error('Cette commande n\'est plus disponible.', 409);
}
if ($commande['mode_paiement'] !== 'especes') {
    Response::error('Le marchandage n\'est disponible que pour les commandes payees en especes a la livraison.', 422);
}

if ($decision === 'proposer') {
    if ($montant <= 0) {
        Response::error('Montant invalide.');
    }
    proposer_prix($db, $commandeId, $livreurId, $montant, 'livreur');
    creer_notification(
        $db,
        (int) $commande['client_id'],
        'Nouvelle proposition de prix',
        "Un livreur propose {$montant} FCFA pour votre commande {$commande['reference']}.",
        'commande',
        "/client/track.php?ref={$commande['reference']}"
    );
    Response::success([], 'Proposition envoyee.');
}

// --- Reponse a la contre-offre du client -----------------------------------
$stmt = $db->prepare(
    "SELECT * FROM negociations_prix WHERE commande_id = :cid AND livreur_id = :lid AND propose_par = 'client' AND statut = 'en_attente'"
);
$stmt->execute(['cid' => $commandeId, 'lid' => $livreurId]);
$negociation = $stmt->fetch();
if (!$negociation) {
    Response::notFound('Aucune proposition du client a traiter.');
}

if ($decision === 'refuser') {
    $db->prepare("UPDATE negociations_prix SET statut = 'refusee' WHERE id = :id")->execute(['id' => $negociation['id']]);
    creer_notification(
        $db,
        (int) $commande['client_id'],
        'Proposition refusee',
        "Le livreur a decline votre offre pour la commande {$commande['reference']}.",
        'commande',
        "/client/track.php?ref={$commande['reference']}"
    );
    Response::success([], 'Proposition refusee.');
}

// --- Acceptation : verrouille l'accord --------------------------------------
$resultat = conclure_negociation($db, $commandeId, $livreurId, (float) $negociation['montant_propose']);
if (!$resultat['ok']) {
    Response::error($resultat['message'], 409);
}

creer_notification(
    $db,
    (int) $commande['client_id'],
    'Livreur trouve',
    "Votre commande {$commande['reference']} a ete acceptee par un livreur a {$negociation['montant_propose']} FCFA.",
    'commande',
    "/client/track.php?ref={$commande['reference']}"
);
foreach ($resultat['autres_livreurs'] as $autreLivreurId) {
    creer_notification(
        $db,
        (int) $autreLivreurId,
        'Commande attribuee',
        "La commande {$commande['reference']} a ete attribuee a un autre livreur.",
        'commande'
    );
}

Response::success(['commande_id' => $commandeId], 'Course acceptee.');
