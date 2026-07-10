<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$livreurId = Auth::requireRole('livreur');

$db = Database::getConnection();

$stmt = $db->prepare(
    "SELECT c.*, tl.nom AS type_nom,
            cl.prenom AS client_prenom, cl.nom AS client_nom, cl.note_client AS client_note
     FROM commandes c
     JOIN types_livraison tl ON tl.id = c.type_livraison_id
     JOIN users cl ON cl.id = c.client_id
     WHERE c.livreur_id = :livreur_id
     ORDER BY c.created_at DESC
     LIMIT 100"
);
$stmt->execute(['livreur_id' => $livreurId]);

Response::success(['commandes' => $stmt->fetchAll()]);
