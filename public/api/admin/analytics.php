<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

// Periode analysee : 7, 30 ou 90 jours (liste blanche -> valeur injectee en clair
// car un placeholder n'est pas accepte dans INTERVAL n DAY).
$jours = (int) ($_GET['jours'] ?? 30);
if (!in_array($jours, [7, 30, 90], true)) {
    $jours = 30;
}

// -- Resume de la periode -----------------------------------------------------
$resume = $db->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(statut = 'livree'), 0) AS livrees,
        COALESCE(SUM(statut = 'annulee'), 0) AS annulees,
        COALESCE(SUM(CASE WHEN statut = 'livree' THEN montant_estime ELSE 0 END), 0) AS ca,
        COALESCE(SUM(CASE WHEN statut = 'livree' THEN commission_montant ELSE 0 END), 0) AS commissions,
        COALESCE(AVG(CASE WHEN statut = 'livree' THEN montant_estime END), 0) AS panier_moyen,
        COALESCE(AVG(CASE WHEN statut = 'livree' THEN distance_km END), 0) AS distance_moy,
        COALESCE(AVG(CASE WHEN delivered_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, delivered_at) END), 0) AS delai_livraison_s
     FROM commandes
     WHERE created_at >= (NOW() - INTERVAL {$jours} DAY)"
)->fetch();

$total = (int) $resume['total'];
$livrees = (int) $resume['livrees'];
$annulees = (int) $resume['annulees'];

// -- Taux d'acceptation du dispatch -------------------------------------------
$offres = $db->query(
    "SELECT COUNT(*) AS total, COALESCE(SUM(statut = 'acceptee'), 0) AS acceptees
     FROM dispatch_offres
     WHERE created_at >= (NOW() - INTERVAL {$jours} DAY)"
)->fetch();
$offresTotal = (int) $offres['total'];

// -- Serie journaliere (jours sans commande completes a zero) -----------------
$brut = $db->query(
    "SELECT DATE(created_at) AS jour, COUNT(*) AS nb,
            COALESCE(SUM(CASE WHEN statut = 'livree' THEN montant_estime ELSE 0 END), 0) AS ca
     FROM commandes
     WHERE created_at >= (NOW() - INTERVAL {$jours} DAY)
     GROUP BY DATE(created_at)"
)->fetchAll();

$parJour = [];
foreach ($brut as $r) {
    $parJour[$r['jour']] = ['nb' => (int) $r['nb'], 'ca' => (float) $r['ca']];
}
$serie = [];
$debut = new DateTimeImmutable("-" . ($jours - 1) . " days");
for ($i = 0; $i < $jours; $i++) {
    $jour = $debut->modify("+{$i} days")->format('Y-m-d');
    $serie[] = [
        'jour' => $jour,
        'nb' => $parJour[$jour]['nb'] ?? 0,
        'ca' => $parJour[$jour]['ca'] ?? 0,
    ];
}

// -- Repartitions -------------------------------------------------------------
$parPaiement = $db->query(
    "SELECT mode_paiement AS cle, COUNT(*) AS nb
     FROM commandes
     WHERE created_at >= (NOW() - INTERVAL {$jours} DAY)
     GROUP BY mode_paiement ORDER BY nb DESC"
)->fetchAll();

$parType = $db->query(
    "SELECT tl.nom AS cle, COUNT(*) AS nb
     FROM commandes c
     JOIN types_livraison tl ON tl.id = c.type_livraison_id
     WHERE c.created_at >= (NOW() - INTERVAL {$jours} DAY)
     GROUP BY tl.id ORDER BY nb DESC LIMIT 8"
)->fetchAll();

// -- Meilleurs livreurs sur la periode (par courses livrees) ------------------
$topLivreurs = $db->query(
    "SELECT u.prenom, u.nom, COUNT(*) AS livrees,
            COALESCE(SUM(c.montant_estime), 0) AS ca, ld.note_moyenne
     FROM commandes c
     JOIN users u ON u.id = c.livreur_id
     LEFT JOIN livreur_details ld ON ld.user_id = c.livreur_id
     WHERE c.statut = 'livree' AND c.delivered_at >= (NOW() - INTERVAL {$jours} DAY)
     GROUP BY c.livreur_id ORDER BY livrees DESC LIMIT 8"
)->fetchAll();

$pourcent = static fn (int $n, int $d): float => $d > 0 ? round($n / $d * 100, 1) : 0.0;

Response::success([
    'jours' => $jours,
    'resume' => [
        'total' => $total,
        'livrees' => $livrees,
        'annulees' => $annulees,
        'chiffre_affaires' => (float) $resume['ca'],
        'commissions' => (float) $resume['commissions'],
        'panier_moyen' => round((float) $resume['panier_moyen']),
        'distance_moyenne' => round((float) $resume['distance_moy'], 1),
        'delai_livraison_min' => round(((float) $resume['delai_livraison_s']) / 60),
        'taux_livraison' => $pourcent($livrees, $total),
        'taux_annulation' => $pourcent($annulees, $total),
        'taux_acceptation' => $pourcent((int) $offres['acceptees'], $offresTotal),
    ],
    'serie' => $serie,
    'par_paiement' => $parPaiement,
    'par_type' => $parType,
    'top_livreurs' => $topLivreurs,
]);
