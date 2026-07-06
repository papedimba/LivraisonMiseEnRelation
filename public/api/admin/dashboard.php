<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

$utilisateurs = $db->query(
    "SELECT role, statut, COUNT(*) AS nb FROM users GROUP BY role, statut"
)->fetchAll();

$commandes = $db->query(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) AS en_attente,
        SUM(CASE WHEN statut IN ('acceptee','recuperee','en_cours') THEN 1 ELSE 0 END) AS en_cours,
        SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) AS livrees,
        SUM(CASE WHEN statut = 'annulee' THEN 1 ELSE 0 END) AS annulees,
        COALESCE(SUM(CASE WHEN statut = 'livree' THEN montant_estime ELSE 0 END), 0) AS chiffre_affaires_total,
        COALESCE(SUM(CASE WHEN statut = 'livree' THEN commission_montant ELSE 0 END), 0) AS commissions_totales
     FROM commandes"
)->fetch();

$commandes30j = $db->query(
    "SELECT DATE(created_at) AS jour, COUNT(*) AS nb,
            COALESCE(SUM(CASE WHEN statut = 'livree' THEN montant_estime ELSE 0 END), 0) AS montant
     FROM commandes
     WHERE created_at >= (NOW() - INTERVAL 30 DAY)
     GROUP BY DATE(created_at)
     ORDER BY jour ASC"
)->fetchAll();

$topLivreurs = $db->query(
    "SELECT u.id, u.nom, u.prenom, ld.nombre_courses, ld.note_moyenne, ld.solde
     FROM livreur_details ld
     JOIN users u ON u.id = ld.user_id
     ORDER BY ld.nombre_courses DESC
     LIMIT 10"
)->fetchAll();

$reclamationsOuvertes = $db->query(
    "SELECT COUNT(*) AS nb FROM reclamations WHERE statut IN ('ouverte','en_cours')"
)->fetch();

$retraitsEnAttente = $db->query(
    "SELECT COUNT(*) AS nb, COALESCE(SUM(montant), 0) AS montant FROM retraits WHERE statut = 'en_attente'"
)->fetch();

Response::success([
    'utilisateurs_par_role' => $utilisateurs,
    'commandes' => $commandes,
    'evolution_30_jours' => $commandes30j,
    'top_livreurs' => $topLivreurs,
    'reclamations_ouvertes' => (int) $reclamationsOuvertes['nb'],
    'retraits_en_attente' => [
        'nombre' => (int) $retraitsEnAttente['nb'],
        'montant' => (float) $retraitsEnAttente['montant'],
    ],
]);
