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

$champs = [
    'piece_identite' => 'piece_identite_path',
    'permis' => 'permis_path',
    'carte_grise' => 'carte_grise_path',
];

$extensionsAutorisees = ['jpg', 'jpeg', 'png', 'pdf'];
$tailleMax = 5 * 1024 * 1024; // 5 Mo

$dossier = STORAGE_PATH . '/uploads/livreurs';
if (!is_dir($dossier)) {
    @mkdir($dossier, 0755, true);
}
if (!is_writable($dossier)) {
    Response::serverError('Le dossier de stockage des documents n\'est pas accessible en ecriture.');
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT user_id FROM livreur_details WHERE user_id = :id');
$stmt->execute(['id' => $livreurId]);
if (!$stmt->fetch()) {
    Response::notFound('Profil livreur introuvable.');
}

$misAJour = [];

foreach ($champs as $champFichier => $colonne) {
    if (!isset($_FILES[$champFichier]) || $_FILES[$champFichier]['error'] === UPLOAD_ERR_NO_FILE) {
        continue;
    }

    $fichier = $_FILES[$champFichier];

    if ($fichier['error'] !== UPLOAD_ERR_OK) {
        Response::error("Erreur lors de l'envoi du fichier {$champFichier}.");
    }
    if ($fichier['size'] > $tailleMax) {
        Response::error("Le fichier {$champFichier} depasse la taille maximale de 5 Mo.");
    }

    $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extensionsAutorisees, true)) {
        Response::error("Format non autorise pour {$champFichier} (accepte : jpg, png, pdf).");
    }

    $nomFichier = 'livreur' . $livreurId . '_' . $champFichier . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $dossier . '/' . $nomFichier;

    if (!move_uploaded_file($fichier['tmp_name'], $destination)) {
        Response::serverError("Impossible d'enregistrer le fichier {$champFichier}.");
    }

    // On stocke uniquement le nom de fichier ; les documents sont servis via un
    // script protege (jamais accessibles directement par URL) car ce sont des
    // pieces d'identite.
    $db->prepare("UPDATE livreur_details SET {$colonne} = :nom WHERE user_id = :id")
        ->execute(['nom' => $nomFichier, 'id' => $livreurId]);

    $misAJour[$champFichier] = true;
}

if (empty($misAJour)) {
    Response::error('Aucun fichier recu.');
}

// Repasse le dossier de validation en attente si le livreur avait ete rejete.
$db->prepare(
    "UPDATE livreur_details SET statut_validation = 'en_attente', motif_rejet = NULL
     WHERE user_id = :id AND statut_validation = 'rejete'"
)->execute(['id' => $livreurId]);

Response::success(['documents' => $misAJour], 'Documents envoyes. Ils seront verifies par un administrateur.');
