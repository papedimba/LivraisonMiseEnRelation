<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();

$role = clean_str(input($body, 'role', 'client'));
if (!in_array($role, ['client', 'livreur', 'commercant', 'admin'], true)) {
    Response::error('Role invalide.', 422);
}

$missing = require_fields($body, ['nom', 'prenom', 'email', 'telephone', 'password']);
if (!empty($missing)) {
    Response::error('Champs obligatoires manquants.', 422, $missing);
}

$nom = clean_str($body['nom']);
$prenom = clean_str($body['prenom']);
$email = strtolower(clean_str($body['email']));
$telephone = clean_str($body['telephone']);
$password = (string) $body['password'];
$quartierId = isset($body['quartier_id']) && $body['quartier_id'] !== '' ? (int) $body['quartier_id'] : null;
$adresse = isset($body['adresse']) ? clean_str($body['adresse']) : null;

$statut = clean_str(input($body, 'statut', 'actif'));
if (!in_array($statut, ['actif', 'suspendu', 'en_attente'], true)) {
    Response::error('Statut invalide.', 422);
}

if (!is_valid_email($email)) {
    Response::error('Email invalide.', 422);
}
if (!is_valid_phone($telephone)) {
    Response::error('Numero de telephone invalide.', 422);
}
if (strlen($password) < 8) {
    Response::error('Le mot de passe doit contenir au moins 8 caracteres.', 422);
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT id FROM users WHERE email = :email OR telephone = :telephone');
$stmt->execute(['email' => $email, 'telephone' => $telephone]);
if ($stmt->fetch()) {
    Response::error('Un compte existe deja avec cet email ou ce telephone.', 409);
}

// Un livreur/commercant cree en "actif" par l'admin est directement valide.
$statutValidation = $statut === 'actif' ? 'valide' : 'en_attente';

try {
    $db->beginTransaction();

    $stmt = $db->prepare(
        'INSERT INTO users (role, nom, prenom, email, telephone, password_hash, quartier_id, adresse, statut, email_verifie)
         VALUES (:role, :nom, :prenom, :email, :telephone, :password_hash, :quartier_id, :adresse, :statut, 1)'
    );
    $stmt->execute([
        'role' => $role,
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => $email,
        'telephone' => $telephone,
        'password_hash' => Auth::hashPassword($password),
        'quartier_id' => $quartierId,
        'adresse' => $adresse,
        'statut' => $statut,
    ]);
    $userId = (int) $db->lastInsertId();

    if ($role === 'livreur') {
        $typeVehicule = clean_str(input($body, 'type_vehicule', 'moto'));
        if (!in_array($typeVehicule, ['moto', 'velo', 'voiture', 'tricycle', 'a_pied'], true)) {
            $typeVehicule = 'moto';
        }
        $stmt = $db->prepare(
            'INSERT INTO livreur_details (user_id, type_vehicule, numero_piece, statut_validation)
             VALUES (:user_id, :type_vehicule, :numero_piece, :statut_validation)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type_vehicule' => $typeVehicule,
            'numero_piece' => clean_str(input($body, 'numero_piece', '')),
            'statut_validation' => $statutValidation,
        ]);
    }

    if ($role === 'commercant') {
        $missingBoutique = require_fields($body, ['nom_boutique', 'categorie']);
        if (!empty($missingBoutique)) {
            throw new RuntimeException('Nom de boutique et categorie requis pour un commercant.');
        }
        $categorie = clean_str($body['categorie']);
        if (!in_array($categorie, ['restaurant', 'maquis', 'supermarche', 'pharmacie', 'boutique', 'fleuriste', 'autre'], true)) {
            $categorie = 'autre';
        }
        $stmt = $db->prepare(
            'INSERT INTO commercant_details (user_id, nom_boutique, categorie, description, adresse, statut_validation)
             VALUES (:user_id, :nom_boutique, :categorie, :description, :adresse, :statut_validation)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'nom_boutique' => clean_str($body['nom_boutique']),
            'categorie' => $categorie,
            'description' => clean_str(input($body, 'description', '')),
            'adresse' => $adresse,
            'statut_validation' => $statutValidation,
        ]);
    }

    creer_notification(
        $db,
        $userId,
        'Bienvenue sur ' . APP_NOM,
        'Votre compte a ete cree par un administrateur.',
        'compte'
    );

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    Response::error('Erreur lors de la creation du compte : ' . $e->getMessage(), 422);
}

Response::created([
    'user_id' => $userId,
    'role' => $role,
    'statut' => $statut,
], 'Compte cree avec succes.');
