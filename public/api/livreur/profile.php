<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$livreurId = Auth::requireRole('livreur');

$db = Database::getConnection();

$stmt = $db->prepare(
    'SELECT type_vehicule, numero_piece, statut_validation, motif_rejet,
            piece_identite_path, permis_path, carte_grise_path,
            disponibilite, note_moyenne, nombre_courses, solde
     FROM livreur_details WHERE user_id = :id'
);
$stmt->execute(['id' => $livreurId]);
$details = $stmt->fetch();

if (!$details) {
    Response::notFound('Profil livreur introuvable.');
}

Response::success([
    'type_vehicule' => $details['type_vehicule'],
    'numero_piece' => $details['numero_piece'],
    'statut_validation' => $details['statut_validation'],
    'motif_rejet' => $details['motif_rejet'],
    'disponibilite' => $details['disponibilite'],
    'note_moyenne' => (float) $details['note_moyenne'],
    'nombre_courses' => (int) $details['nombre_courses'],
    'solde' => (float) $details['solde'],
    'documents' => [
        'piece_identite' => !empty($details['piece_identite_path']),
        'permis' => !empty($details['permis_path']),
        'carte_grise' => !empty($details['carte_grise_path']),
    ],
]);
