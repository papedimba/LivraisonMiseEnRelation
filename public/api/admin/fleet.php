<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

// Livreurs "actifs" : en ligne ou en pause, ou engages sur une course active.
// La course active (au plus une par livreur) est recuperee via sous-requete.
$stmt = $db->query(
    "SELECT u.id, u.prenom, u.nom, u.telephone,
            ld.disponibilite, ld.latitude, ld.longitude, ld.derniere_position_at,
            ld.note_moyenne, ld.nombre_courses, ld.type_vehicule,
            ac.reference AS course_reference, ac.statut AS course_statut,
            ac.adresse_arrivee AS course_arrivee
     FROM livreur_details ld
     JOIN users u ON u.id = ld.user_id
     LEFT JOIN commandes ac ON ac.id = (
         SELECT c2.id FROM commandes c2
         WHERE c2.livreur_id = ld.user_id
           AND c2.statut IN ('acceptee','recuperee','en_cours')
         ORDER BY c2.id DESC LIMIT 1
     )
     WHERE ld.statut_validation = 'valide'
       AND (ld.disponibilite IN ('en_ligne','pause') OR ac.id IS NOT NULL)
     ORDER BY u.prenom, u.nom"
);

$maintenant = time();
$livreurs = [];
$compte = ['en_route' => 0, 'libre' => 0, 'pause' => 0];

foreach ($stmt->fetchAll() as $l) {
    // Statut derive : une course active prime toujours.
    if ($l['course_reference'] !== null) {
        $statut = 'en_route';
    } elseif ($l['disponibilite'] === 'pause') {
        $statut = 'pause';
    } else {
        $statut = 'libre';
    }
    $compte[$statut]++;

    // Fraicheur de la position (dernier point recu il y a moins de 3 minutes).
    $positionFraiche = false;
    if ($l['derniere_position_at'] !== null) {
        $positionFraiche = ($maintenant - strtotime((string) $l['derniere_position_at'])) <= 180;
    }

    $livreurs[] = [
        'id' => (int) $l['id'],
        'nom_complet' => $l['prenom'] . ' ' . $l['nom'],
        'telephone' => $l['telephone'],
        'type_vehicule' => $l['type_vehicule'],
        'note_moyenne' => (float) $l['note_moyenne'],
        'nombre_courses' => (int) $l['nombre_courses'],
        'statut' => $statut,
        'latitude' => $l['latitude'] !== null ? (float) $l['latitude'] : null,
        'longitude' => $l['longitude'] !== null ? (float) $l['longitude'] : null,
        'derniere_position_at' => $l['derniere_position_at'],
        'position_fraiche' => $positionFraiche,
        'course_reference' => $l['course_reference'],
        'course_statut' => $l['course_statut'],
        'course_arrivee' => $l['course_arrivee'],
    ];
}

Response::success([
    'livreurs' => $livreurs,
    'compte' => $compte,
    'total' => count($livreurs),
    'genere_at' => date('c'),
]);
