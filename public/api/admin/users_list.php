<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();

$role = clean_str($_GET['role'] ?? '');
$statut = clean_str($_GET['statut'] ?? '');
$recherche = clean_str($_GET['q'] ?? '');

$sql = 'SELECT id, role, nom, prenom, email, telephone, statut, created_at, derniere_connexion FROM users WHERE 1=1';
$params = [];

if ($role !== '') {
    $sql .= ' AND role = :role';
    $params['role'] = $role;
}
if ($statut !== '') {
    $sql .= ' AND statut = :statut';
    $params['statut'] = $statut;
}
if ($recherche !== '') {
    $sql .= ' AND (nom LIKE :q OR prenom LIKE :q OR email LIKE :q OR telephone LIKE :q)';
    $params['q'] = '%' . $recherche . '%';
}

$sql .= ' ORDER BY created_at DESC LIMIT 200';

$stmt = $db->prepare($sql);
$stmt->execute($params);

Response::success(['utilisateurs' => $stmt->fetchAll()]);
