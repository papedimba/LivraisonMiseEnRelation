<?php
declare(strict_types=1);

/**
 * Heatmap des zones les plus desservies : agrege les points de depart et
 * d'arrivee des commandes livrees en cellules d'une grille (~550 m), avec un
 * poids = nombre de livraisons touchant la cellule.
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireLogin();

$db = Database::getConnection();

// Taille de cellule en degres (~0.005 deg ≈ 550 m a cette latitude).
$cellule = 0.005;

// On additionne les extremites depart et arrivee des courses livrees.
$sql = "SELECT clat, clng, SUM(n) AS poids FROM (
            SELECT ROUND(lat_depart / :c1) * :c2 AS clat, ROUND(lng_depart / :c3) * :c4 AS clng, COUNT(*) AS n
            FROM commandes WHERE statut = 'livree' GROUP BY clat, clng
            UNION ALL
            SELECT ROUND(lat_arrivee / :c5) * :c6 AS clat, ROUND(lng_arrivee / :c7) * :c8 AS clng, COUNT(*) AS n
            FROM commandes WHERE statut = 'livree' GROUP BY clat, clng
        ) t
        GROUP BY clat, clng
        ORDER BY poids DESC
        LIMIT 3000";

$stmt = $db->prepare($sql);
$stmt->execute([
    'c1' => $cellule, 'c2' => $cellule, 'c3' => $cellule, 'c4' => $cellule,
    'c5' => $cellule, 'c6' => $cellule, 'c7' => $cellule, 'c8' => $cellule,
]);

$cellules = [];
$max = 0;
foreach ($stmt->fetchAll() as $row) {
    $poids = (int) $row['poids'];
    if ($poids > $max) {
        $max = $poids;
    }
    $cellules[] = [
        'lat' => (float) $row['clat'],
        'lng' => (float) $row['clng'],
        'poids' => $poids,
    ];
}

Response::success([
    'cellules' => $cellules,
    'poids_max' => $max,
    'taille_cellule' => $cellule,
]);
