<?php
declare(strict_types=1);

require_once __DIR__ . '/Response.php';

/**
 * Envoie au navigateur un document livreur stocke hors de la racine web.
 * Le nom de fichier est nettoye avec basename() pour eviter toute traversee
 * de repertoire.
 */
function servir_document_livreur(string $nomFichier): never
{
    $nomFichier = basename($nomFichier);
    $chemin = STORAGE_PATH . '/uploads/livreurs/' . $nomFichier;

    if ($nomFichier === '' || !is_file($chemin)) {
        Response::notFound('Document introuvable.');
    }

    $extension = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
    $types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ];
    $contentType = $types[$extension] ?? 'application/octet-stream';

    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($chemin));
    header('Content-Disposition: inline; filename="' . $nomFichier . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($chemin);
    exit;
}

/**
 * Retourne le nom de colonne correspondant au type de document demande.
 */
function colonne_document(string $type): ?string
{
    $map = [
        'piece_identite' => 'piece_identite_path',
        'permis' => 'permis_path',
        'carte_grise' => 'carte_grise_path',
    ];
    return $map[$type] ?? null;
}
