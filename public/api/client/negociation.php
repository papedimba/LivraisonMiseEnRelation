<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/negociation.php';

/**
 * Marchandage cote client, en reponse a la proposition d'un livreur donne :
 *  - 'proposer' : contre-propose un nouveau montant a ce livreur.
 *  - 'accepter' : verrouille l'accord, la commande lui est attribuee.
 *  - 'refuser'  : clot ce fil de negociation (l'offre du livreur reste
 *                 visible aux autres livreurs, inchangee).
 */

$clientId = Auth::requireRole('client');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$commandeId = (int) input($body, 'commande_id', 0);
$livreurId = (int) input($body, 'livreur_id', 0);
$decision = clean_str(input($body, 'decision', ''));
$montant = (float) input($body, 'montant', 0);

if ($commandeId <= 0 || $livreurId <= 0 || !in_array($decision, ['proposer', 'accepter', 'refuser'], true)) {
    Response::error('Parametres invalides.');
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT * FROM commandes WHERE id = :id AND client_id = :client_id AND statut = 'en_attente'");
$stmt->execute(['id' => $commandeId, 'client_id' => $clientId]);
$commande = $stmt->fetch();
if (!$commande) {
    Response::error('Cette commande n\'est plus disponible.', 409);
}

$stmt = $db->prepare(
    "SELECT * FROM negociations_prix WHERE commande_id = :cid AND livreur_id = :lid AND propose_par = 'livreur' AND statut = 'en_attente'"
);
$stmt->execute(['cid' => $commandeId, 'lid' => $livreurId]);
$negociation = $stmt->fetch();
if (!$negociation) {
    Response::notFound('Aucune proposition de ce livreur a traiter.');
}

if ($decision === 'proposer') {
    if ($montant <= 0) {
        Response::error('Montant invalide.');
    }
    proposer_prix($db, $commandeId, $livreurId, $montant, 'client');
    creer_notification(
        $db,
        $livreurId,
        'Contre-proposition du client',
        "Le client propose maintenant {$montant} FCFA pour la commande {$commande['reference']}.",
        'commande',
        '/livreur/dashboard.php'
    );
    Response::success([], 'Contre-proposition envoyee.');
}

if ($decision === 'refuser') {
    $db->prepare("UPDATE negociations_prix SET statut = 'refusee' WHERE id = :id")->execute(['id' => $negociation['id']]);
    creer_notification(
        $db,
        $livreurId,
        'Proposition refusee',
        "Le client a decline votre offre pour la commande {$commande['reference']}.",
        'commande'
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
    $livreurId,
    'Proposition acceptee',
    "Le client a accepte votre offre de {$negociation['montant_propose']} FCFA. La commande {$commande['reference']} vous est attribuee.",
    'commande',
    '/livreur/dashboard.php'
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

Response::success(['commande_id' => $commandeId], 'Commande attribuee au livreur.');
