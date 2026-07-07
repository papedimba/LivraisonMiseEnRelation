<?php
declare(strict_types=1);

/**
 * Traitement periodique des commandes (a lancer via une tache cron, ex. toutes
 * les minutes) :
 *   - RELANCE des commandes en attente non prises : re-notifie les livreurs en
 *     ligne.
 *   - REATTRIBUTION : une commande acceptee mais restee inactive trop longtemps
 *     est remise dans le pool (le livreur ne progresse pas).
 *   - ANNULATION AUTO : une commande jamais prise au bout d'un long delai est
 *     annulee et le client notifie.
 *
 * Cron cPanel/o2switch (toutes les minutes) :
 *   * * * * * php /chemin/vers/scripts/process_orders.php >> /dev/null 2>&1
 *
 * Peut aussi etre declenche par URL securisee :
 *   https://votre-domaine.com/scripts/process_orders.php?token=VOTRE_CRON_TOKEN
 * (definir CRON_TOKEN dans .env). En CLI, aucun token requis.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/dispatch.php';

$enCli = PHP_SAPI === 'cli';
if (!$enCli) {
    $token = (string) ($_GET['token'] ?? '');
    $attendu = (string) env('CRON_TOKEN', '');
    if ($attendu === '' || !hash_equals($attendu, $token)) {
        http_response_code(403);
        echo 'interdit';
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$db = Database::getConnection();

$relanceMin = (int) parametre($db, 'relance_commande_minutes', '3');
$annulMin = (int) parametre($db, 'annulation_auto_minutes', '20');
$reattribMin = (int) parametre($db, 'reattribution_acceptee_minutes', '10');

$resume = ['relancees' => 0, 'reattribuees' => 0, 'annulees' => 0, 'dispatchees' => 0];

// ---------------------------------------------------------------------------
// 1) REATTRIBUTION : commandes acceptees mais bloquees (livreur inactif).
// ---------------------------------------------------------------------------
$stmt = $db->prepare(
    "SELECT id, reference, client_id, livreur_id
     FROM commandes
     WHERE statut = 'acceptee'
       AND accepted_at IS NOT NULL
       AND accepted_at < (NOW() - INTERVAL :min MINUTE)"
);
$stmt->bindValue(':min', $reattribMin, PDO::PARAM_INT);
$stmt->execute();
foreach ($stmt->fetchAll() as $c) {
    $db->prepare(
        "UPDATE commandes
         SET statut = 'en_attente', livreur_id = NULL, accepted_at = NULL,
             relance_at = NOW(), nombre_relances = nombre_relances + 1
         WHERE id = :id"
    )->execute(['id' => $c['id']]);

    if ($c['livreur_id']) {
        creer_notification($db, (int) $c['livreur_id'], 'Course reattribuee',
            "La course {$c['reference']} vous a ete retiree pour inactivite.", 'commande');
    }
    creer_notification($db, (int) $c['client_id'], 'Recherche d\'un nouveau livreur',
        "Nous cherchons un nouveau livreur pour votre commande {$c['reference']}.", 'commande');
    $resume['reattribuees']++;
}

// ---------------------------------------------------------------------------
// 2) ANNULATION AUTO : commandes jamais prises apres un long delai.
// ---------------------------------------------------------------------------
$stmt = $db->prepare(
    "SELECT id, reference, client_id
     FROM commandes
     WHERE statut = 'en_attente'
       AND created_at < (NOW() - INTERVAL :min MINUTE)"
);
$stmt->bindValue(':min', $annulMin, PDO::PARAM_INT);
$stmt->execute();
foreach ($stmt->fetchAll() as $c) {
    $db->prepare(
        "UPDATE commandes SET statut = 'annulee',
             motif_annulation = 'Aucun livreur disponible', cancelled_at = NOW()
         WHERE id = :id"
    )->execute(['id' => $c['id']]);
    creer_notification($db, (int) $c['client_id'], 'Commande annulee',
        "Votre commande {$c['reference']} a ete annulee faute de livreur disponible. Vous pouvez reessayer.", 'commande');
    $resume['annulees']++;
}

// ---------------------------------------------------------------------------
// 3) DISPATCH : faire avancer les offres (expirees -> livreur suivant) pour
//    toutes les commandes en attente encore dans la fenetre.
// ---------------------------------------------------------------------------
$stmt = $db->prepare(
    "SELECT id, reference FROM commandes
     WHERE statut = 'en_attente' AND created_at >= (NOW() - INTERVAL :annul MINUTE)"
);
$stmt->bindValue(':annul', $annulMin, PDO::PARAM_INT);
$stmt->execute();
$enAttente = $stmt->fetchAll();

foreach ($enAttente as $c) {
    $etat = avancer_dispatch($db, (int) $c['id']);
    if ($etat === 'dispatchee') {
        $resume['dispatchees']++;
    } elseif ($etat === 'aucun_livreur') {
        // Repli : aucune position/livreur eligible -> diffusion large, throttlee.
        $stmt2 = $db->prepare(
            "SELECT id FROM commandes WHERE id = :id
             AND (relance_at IS NULL OR relance_at < (NOW() - INTERVAL :relance MINUTE))"
        );
        $stmt2->bindValue(':id', (int) $c['id'], PDO::PARAM_INT);
        $stmt2->bindValue(':relance', $relanceMin, PDO::PARAM_INT);
        $stmt2->execute();
        if ($stmt2->fetch()) {
            $livreurs = $db->query(
                "SELECT ld.user_id FROM livreur_details ld
                 WHERE ld.disponibilite = 'en_ligne' AND ld.statut_validation = 'valide'"
            )->fetchAll();
            foreach ($livreurs as $l) {
                creer_notification($db, (int) $l['user_id'], 'Course disponible',
                    "Une course ({$c['reference']}) attend un livreur.", 'commande', '/livreur/dashboard.php');
            }
            $db->prepare('UPDATE commandes SET relance_at = NOW(), nombre_relances = nombre_relances + 1 WHERE id = :id')
                ->execute(['id' => $c['id']]);
            $resume['relancees']++;
        }
    }
}

echo sprintf(
    "[%s] dispatchees=%d relancees=%d reattribuees=%d annulees=%d\n",
    date('Y-m-d H:i:s'),
    $resume['dispatchees'],
    $resume['relancees'],
    $resume['reattribuees'],
    $resume['annulees']
);
