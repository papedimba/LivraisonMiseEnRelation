<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireLogin();

$q = clean_str($_GET['q'] ?? '');
$categorie = clean_str($_GET['categorie'] ?? '');

$db = Database::getConnection();

if ($q !== '') {
    // Le meme placeholder ne peut pas etre reutilise plusieurs fois avec des
    // requetes preparees non emulees : on utilise deux parametres distincts.
    $sql = "SELECT p.*, cd.nom_boutique, cd.categorie AS boutique_categorie,
                   MATCH(p.nom, p.description) AGAINST (:q_select IN NATURAL LANGUAGE MODE) AS pertinence
            FROM produits p
            JOIN commercant_details cd ON cd.user_id = p.commercant_id
            WHERE p.disponible = 1 AND cd.statut_validation = 'valide'
              AND MATCH(p.nom, p.description) AGAINST (:q_where IN NATURAL LANGUAGE MODE)";
    $params = ['q_select' => $q, 'q_where' => $q];

    if ($categorie !== '') {
        $sql .= ' AND cd.categorie = :categorie';
        $params['categorie'] = $categorie;
    }

    $sql .= ' ORDER BY pertinence DESC LIMIT 50';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $produits = $stmt->fetchAll();

    if (empty($produits)) {
        $sqlLike = "SELECT p.*, cd.nom_boutique, cd.categorie AS boutique_categorie
                    FROM produits p
                    JOIN commercant_details cd ON cd.user_id = p.commercant_id
                    WHERE p.disponible = 1 AND cd.statut_validation = 'valide'
                      AND (p.nom LIKE :like_nom OR p.description LIKE :like_desc)
                    LIMIT 50";
        $stmt = $db->prepare($sqlLike);
        $stmt->execute(['like_nom' => '%' . $q . '%', 'like_desc' => '%' . $q . '%']);
        $produits = $stmt->fetchAll();
    }
} else {
    $sql = "SELECT p.*, cd.nom_boutique, cd.categorie AS boutique_categorie
            FROM produits p
            JOIN commercant_details cd ON cd.user_id = p.commercant_id
            WHERE p.disponible = 1 AND cd.statut_validation = 'valide'";
    $params = [];
    if ($categorie !== '') {
        $sql .= ' AND cd.categorie = :categorie';
        $params['categorie'] = $categorie;
    }
    $sql .= ' ORDER BY p.created_at DESC LIMIT 50';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $produits = $stmt->fetchAll();
}

Response::success(['produits' => $produits]);
