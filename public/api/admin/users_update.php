<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

$adminId = Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['user_id']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$userId = (int) $body['user_id'];

$db = Database::getConnection();

$stmt = $db->prepare('SELECT id, role, nom, prenom, email, telephone, statut FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    Response::notFound('Utilisateur introuvable.');
}

// Valeurs finales (par defaut : inchangees).
$nom = isset($body['nom']) ? clean_str($body['nom']) : $user['nom'];
$prenom = isset($body['prenom']) ? clean_str($body['prenom']) : $user['prenom'];
$email = isset($body['email']) ? strtolower(clean_str($body['email'])) : $user['email'];
$telephone = isset($body['telephone']) ? clean_str($body['telephone']) : $user['telephone'];
$role = isset($body['role']) ? clean_str($body['role']) : $user['role'];
$statut = isset($body['statut']) ? clean_str($body['statut']) : $user['statut'];
$password = isset($body['password']) ? (string) $body['password'] : '';

if (!in_array($role, ['client', 'livreur', 'commercant', 'admin'], true)) {
    Response::error('Role invalide.', 422);
}
if (!in_array($statut, ['actif', 'suspendu', 'en_attente'], true)) {
    Response::error('Statut invalide.', 422);
}
if ($nom === '' || $prenom === '') {
    Response::error('Nom et prenom requis.', 422);
}
if (!is_valid_email($email)) {
    Response::error('Email invalide.', 422);
}
if (!is_valid_phone($telephone)) {
    Response::error('Numero de telephone invalide.', 422);
}
if ($password !== '' && strlen($password) < 8) {
    Response::error('Le mot de passe doit contenir au moins 8 caracteres.', 422);
}

// Garde-fous : un admin ne peut pas se retirer ses propres droits ni se
// suspendre, pour eviter de se verrouiller hors de l'administration.
if ($userId === $adminId) {
    if ($role !== 'admin') {
        Response::error('Vous ne pouvez pas retirer votre propre role administrateur.', 409);
    }
    if ($statut !== 'actif') {
        Response::error('Vous ne pouvez pas suspendre votre propre compte.', 409);
    }
}

// Unicite email / telephone (hors utilisateur courant).
$stmt = $db->prepare('SELECT id FROM users WHERE (email = :email OR telephone = :telephone) AND id <> :id');
$stmt->execute(['email' => $email, 'telephone' => $telephone, 'id' => $userId]);
if ($stmt->fetch()) {
    Response::error('Un autre compte utilise deja cet email ou ce telephone.', 409);
}

// Si l'on passe a commercant sans fiche existante, il faut un nom de boutique.
$roleChange = $role !== $user['role'];
$nomBoutique = clean_str(input($body, 'nom_boutique', ''));
if ($roleChange && $role === 'commercant') {
    $stmt = $db->prepare('SELECT user_id FROM commercant_details WHERE user_id = :id');
    $stmt->execute(['id' => $userId]);
    if (!$stmt->fetch() && $nomBoutique === '') {
        Response::error('Un nom de boutique est requis pour donner le role commercant.', 422);
    }
}

try {
    $db->beginTransaction();

    $champs = 'nom = :nom, prenom = :prenom, email = :email, telephone = :telephone, role = :role, statut = :statut';
    $params = [
        'nom' => $nom, 'prenom' => $prenom, 'email' => $email,
        'telephone' => $telephone, 'role' => $role, 'statut' => $statut, 'id' => $userId,
    ];
    if ($password !== '') {
        $champs .= ', password_hash = :password_hash';
        $params['password_hash'] = Auth::hashPassword($password);
    }
    $db->prepare("UPDATE users SET {$champs} WHERE id = :id")->execute($params);

    // Changement de role : creer la fiche de details manquante pour le nouveau role.
    if ($roleChange) {
        if ($role === 'livreur') {
            $stmt = $db->prepare('SELECT user_id FROM livreur_details WHERE user_id = :id');
            $stmt->execute(['id' => $userId]);
            if (!$stmt->fetch()) {
                $typeVehicule = clean_str(input($body, 'type_vehicule', 'moto'));
                if (!in_array($typeVehicule, ['moto', 'velo', 'voiture', 'tricycle', 'a_pied'], true)) {
                    $typeVehicule = 'moto';
                }
                $db->prepare(
                    'INSERT INTO livreur_details (user_id, type_vehicule, statut_validation)
                     VALUES (:user_id, :type_vehicule, :statut_validation)'
                )->execute([
                    'user_id' => $userId,
                    'type_vehicule' => $typeVehicule,
                    'statut_validation' => $statut === 'actif' ? 'valide' : 'en_attente',
                ]);
            }
        }
        if ($role === 'commercant') {
            $stmt = $db->prepare('SELECT user_id FROM commercant_details WHERE user_id = :id');
            $stmt->execute(['id' => $userId]);
            if (!$stmt->fetch()) {
                $categorie = clean_str(input($body, 'categorie', 'autre'));
                if (!in_array($categorie, ['restaurant', 'maquis', 'supermarche', 'pharmacie', 'boutique', 'fleuriste', 'autre'], true)) {
                    $categorie = 'autre';
                }
                $db->prepare(
                    'INSERT INTO commercant_details (user_id, nom_boutique, categorie, statut_validation)
                     VALUES (:user_id, :nom_boutique, :categorie, :statut_validation)'
                )->execute([
                    'user_id' => $userId,
                    'nom_boutique' => $nomBoutique,
                    'categorie' => $categorie,
                    'statut_validation' => $statut === 'actif' ? 'valide' : 'en_attente',
                ]);
            }
        }
    }

    creer_notification(
        $db,
        $userId,
        'Compte mis a jour',
        'Les informations de votre compte ont ete modifiees par un administrateur.',
        'compte'
    );

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    Response::error('Erreur lors de la mise a jour : ' . $e->getMessage(), 422);
}

Response::success([
    'user_id' => $userId,
    'role' => $role,
    'statut' => $statut,
    'mot_de_passe_change' => $password !== '',
], 'Utilisateur mis a jour.');
