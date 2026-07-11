<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/delivery.php';

$livreurId = Auth::requireRole('livreur');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

// Peut recevoir du JSON (code seul) ou du multipart (avec photo).
$estMultipart = isset($_POST['commande_id']) || isset($_FILES['photo']);
if ($estMultipart) {
    $commandeId = (int) ($_POST['commande_id'] ?? 0);
    $code = trim((string) ($_POST['code_livraison'] ?? ''));
} else {
    $body = request_body();
    $commandeId = (int) ($body['commande_id'] ?? 0);
    $code = trim((string) ($body['code_livraison'] ?? ''));
}

if ($commandeId <= 0) {
    Response::error('commande_id requis.');
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT * FROM commandes WHERE id = :id AND livreur_id = :livreur_id');
$stmt->execute(['id' => $commandeId, 'livreur_id' => $livreurId]);
$commande = $stmt->fetch();

if (!$commande) {
    Response::notFound('Commande introuvable pour ce livreur.');
}
if ($commande['statut'] !== 'en_cours') {
    Response::error('La commande doit etre "en cours" pour etre marquee livree.');
}

// Determination de la preuve : code client correct OU photo.
$preuveType = null;
$preuvePhoto = null;

if ($code !== '') {
    if (!hash_equals((string) $commande['code_livraison'], $code)) {
        Response::error('Code de livraison incorrect.', 422);
    }
    $preuveType = 'code';
}

if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fichier = $_FILES['photo'];
    if ($fichier['error'] !== UPLOAD_ERR_OK) {
        Response::error('Erreur lors de l\'envoi de la photo.');
    }
    if ($fichier['size'] > 5 * 1024 * 1024) {
        Response::error('La photo depasse 5 Mo.');
    }
    $ext = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        Response::error('Format photo non autorise (jpg ou png).');
    }
    $dossier = STORAGE_PATH . '/uploads/livraisons';
    if (!is_dir($dossier)) {
        @mkdir($dossier, 0755, true);
    }
    $nomFichier = 'livraison' . $commandeId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($fichier['tmp_name'], $dossier . '/' . $nomFichier)) {
        Response::serverError('Impossible d\'enregistrer la photo.');
    }
    $preuvePhoto = $nomFichier;
    if ($preuveType === null) {
        $preuveType = 'photo';
    }
}

if ($preuveType === null) {
    Response::error('Preuve requise : saisissez le code de livraison du client ou joignez une photo.', 422);
}

try {
    $db->beginTransaction();
    finaliser_livraison($db, $commande, $livreurId, $preuveType, $preuvePhoto);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    Response::error('Erreur lors de la confirmation de livraison.', 422);
}

// Auto-alimentation de la carte collaborative avec les points reellement
// desservis (best-effort : n'affecte jamais la confirmation de livraison).
try {
    require_once __DIR__ . '/../../../includes/carto.php';
    carto_auto_alimenter($db, $commande);
} catch (Throwable $e) {
    error_log('Carto auto-feed error: ' . $e->getMessage());
}

// Map-matching OSRM : cale la trace de la course sur les rues (en cache).
try {
    require_once __DIR__ . '/../../../includes/osrm.php';
    route_matcher_commande($db, $commandeId);
} catch (Throwable $e) {
    error_log('OSRM match error: ' . $e->getMessage());
}

Response::success(['statut' => 'livree', 'preuve' => $preuveType], 'Livraison confirmee.');
