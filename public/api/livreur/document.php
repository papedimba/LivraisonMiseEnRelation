<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/documents.php';

$livreurId = Auth::requireRole('livreur');

$type = clean_str($_GET['type'] ?? '');
$colonne = colonne_document($type);
if ($colonne === null) {
    Response::error('Type de document invalide.');
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT {$colonne} AS fichier FROM livreur_details WHERE user_id = :id");
$stmt->execute(['id' => $livreurId]);
$row = $stmt->fetch();

if (!$row || empty($row['fichier'])) {
    Response::notFound('Aucun document de ce type.');
}

servir_document_livreur($row['fichier']);
