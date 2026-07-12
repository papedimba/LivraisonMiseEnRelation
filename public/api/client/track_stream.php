<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$clientId = Auth::requireRole('client');

$reference = clean_str($_GET['reference'] ?? '');
if ($reference === '') {
    Response::error('Reference de commande requise.');
}

$db = Database::getConnection();

// On verifie une premiere fois que la commande appartient bien au client.
$stmt = $db->prepare('SELECT id FROM commandes WHERE reference = :ref AND client_id = :client_id');
$stmt->execute(['ref' => $reference, 'client_id' => $clientId]);
$commande = $stmt->fetch();
if (!$commande) {
    Response::notFound('Commande introuvable.');
}
$commandeId = (int) $commande['id'];

// IMPORTANT (hebergement mutualise + PHP) : liberer le verrou de session avant
// la boucle longue, sinon toutes les autres requetes du meme utilisateur
// resteraient bloquees en attente du verrou tant que le flux est ouvert.
session_write_close();

// Preparation du flux SSE.
@set_time_limit(0);
ignore_user_abort(false);
while (ob_get_level() > 0) {
    ob_end_flush();
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
// Desactive la mise en tampon de nginx (utile derriere un reverse proxy).
header('X-Accel-Buffering: no');

$requete = $db->prepare(
    'SELECT c.id, c.reference, c.statut, c.statut_paiement, c.mode_paiement,
            c.adresse_depart, c.lat_depart, c.lng_depart,
            c.adresse_arrivee, c.lat_arrivee, c.lng_arrivee,
            c.distance_km, c.montant_estime, c.code_livraison, c.livreur_id,
            tl.nom AS type_nom,
            u.nom AS livreur_nom, u.prenom AS livreur_prenom, u.telephone AS livreur_telephone,
            ld.latitude AS livreur_lat, ld.longitude AS livreur_lng
     FROM commandes c
     JOIN types_livraison tl ON tl.id = c.type_livraison_id
     LEFT JOIN users u ON u.id = c.livreur_id
     LEFT JOIN livreur_details ld ON ld.user_id = c.livreur_id
     WHERE c.id = :id'
);

function sse_envoyer(string $event, array $data): void
{
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    @ob_flush();
    @flush();
}

$dureeMax = 300;          // le flux vit 5 min max, puis le client se reconnecte
$intervalle = 3;          // rafraichissement cote serveur (secondes)
$debut = time();
$dernierEnvoi = null;     // pour n'emettre que lorsque quelque chose change

// Indique au client le delai de reconnexion automatique (ms) en cas de coupure.
echo "retry: 3000\n\n";
@ob_flush();
@flush();

while (true) {
    if (connection_aborted()) {
        break;
    }
    if (time() - $debut > $dureeMax) {
        // Fin propre : le navigateur (EventSource) se reconnectera tout seul.
        sse_envoyer('reconnect', ['message' => 'Renouvellement de la connexion']);
        break;
    }

    $requete->execute(['id' => $commandeId]);
    $c = $requete->fetch();

    if ($c) {
        // Empreinte des donnees susceptibles de changer : on n'envoie que si ca bouge.
        $signature = $c['statut'] . '|' . $c['statut_paiement'] . '|' . ($c['livreur_lat'] ?? '') . '|' . ($c['livreur_lng'] ?? '');
        if ($signature !== $dernierEnvoi) {
            sse_envoyer('update', ['commande' => $c]);
            $dernierEnvoi = $signature;
        }

        // Commande terminee : on envoie un dernier evenement et on ferme.
        if (in_array($c['statut'], ['livree', 'annulee'], true)) {
            sse_envoyer('final', ['commande' => $c]);
            break;
        }
    }

    // Commentaire de maintien de connexion (keep-alive).
    echo ": keepalive\n\n";
    @ob_flush();
    @flush();

    sleep($intervalle);
}
