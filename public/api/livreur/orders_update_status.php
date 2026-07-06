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

$transitionsAutorisees = [
    'acceptee' => 'recuperee',
    'recuperee' => 'en_cours',
    'en_cours' => 'livree',
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

    $champDate = match ($nouveauStatut) {
        'recuperee' => 'recovered_at',
        'livree' => 'delivered_at',
        default => null,
    };

    $sql = "UPDATE commandes SET statut = :statut";
    if ($champDate) {
        $sql .= ", {$champDate} = NOW()";
    }
    if ($nouveauStatut === 'livree') {
        $sql .= ", montant_final = montant_estime";
    }
    $sql .= " WHERE id = :id";
    $db->prepare($sql)->execute(['statut' => $nouveauStatut, 'id' => $commandeId]);

    if ($nouveauStatut === 'livree') {
        if ($commande['mode_paiement'] === 'especes') {
            $db->prepare("UPDATE commandes SET statut_paiement = 'paye' WHERE id = :id")->execute(['id' => $commandeId]);
        }

        $gainLivreur = round((float) $commande['montant_estime'] - (float) $commande['commission_montant'], 0);

        $db->prepare('UPDATE livreur_details SET solde = solde + :gain, nombre_courses = nombre_courses + 1 WHERE user_id = :id')
            ->execute(['gain' => $gainLivreur, 'id' => $livreurId]);

        creer_notification(
            $db,
            (int) $commande['client_id'],
            'Livraison effectuee',
            "Votre commande {$commande['reference']} a ete livree. Merci d'evaluer votre livreur !",
            'commande',
            "/client/track.php?ref={$commande['reference']}"
        );
    } elseif ($nouveauStatut === 'en_cours') {
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
