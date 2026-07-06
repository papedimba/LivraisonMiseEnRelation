<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();

$role = clean_str(input($body, 'role', 'client'));
if (!in_array($role, ['client', 'livreur', 'commercant'], true)) {
    Response::error('Role invalide.');
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
$quartierId = isset($body['quartier_id']) ? (int) $body['quartier_id'] : null;
$adresse = isset($body['adresse']) ? clean_str($body['adresse']) : null;

if (!is_valid_email($email)) {
    Response::error('Email invalide.');
}
if (!is_valid_phone($telephone)) {
    Response::error('Numero de telephone invalide.');
}
if (strlen($password) < 8) {
    Response::error('Le mot de passe doit contenir au moins 8 caracteres.');
}

$db = Database::getConnection();

$stmt = $db->prepare('SELECT id FROM users WHERE email = :email OR telephone = :telephone');
$stmt->execute(['email' => $email, 'telephone' => $telephone]);
if ($stmt->fetch()) {
    Response::error('Un compte existe deja avec cet email ou ce telephone.', 409);
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare(
        'INSERT INTO users (role, nom, prenom, email, telephone, password_hash, quartier_id, adresse, statut)
         VALUES (:role, :nom, :prenom, :email, :telephone, :password_hash, :quartier_id, :adresse, :statut)'
    );
    $statutInitial = $role === 'client' ? 'actif' : 'en_attente';
    $stmt->execute([
        'role' => $role,
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => $email,
        'telephone' => $telephone,
        'password_hash' => Auth::hashPassword($password),
        'quartier_id' => $quartierId,
        'adresse' => $adresse,
        'statut' => $statutInitial,
    ]);
    $userId = (int) $db->lastInsertId();

    if ($role === 'livreur') {
        $typeVehicule = clean_str(input($body, 'type_vehicule', 'moto'));
        $numeroPiece = clean_str(input($body, 'numero_piece', ''));
        $stmt = $db->prepare(
            'INSERT INTO livreur_details (user_id, type_vehicule, numero_piece, statut_validation)
             VALUES (:user_id, :type_vehicule, :numero_piece, :statut_validation)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type_vehicule' => $typeVehicule,
            'numero_piece' => $numeroPiece,
            'statut_validation' => 'en_attente',
        ]);
    }

    if ($role === 'commercant') {
        $missingBoutique = require_fields($body, ['nom_boutique', 'categorie']);
        if (!empty($missingBoutique)) {
            throw new RuntimeException('Informations boutique manquantes.');
        }
        $stmt = $db->prepare(
            'INSERT INTO commercant_details (user_id, nom_boutique, categorie, description, adresse, statut_validation)
             VALUES (:user_id, :nom_boutique, :categorie, :description, :adresse, :statut_validation)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'nom_boutique' => clean_str($body['nom_boutique']),
            'categorie' => clean_str($body['categorie']),
            'description' => clean_str(input($body, 'description', '')),
            'adresse' => $adresse,
            'statut_validation' => 'en_attente',
        ]);
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    Response::error('Erreur lors de la creation du compte : ' . $e->getMessage(), 422);
}

if ($role === 'client') {
    $stmt = $db->prepare('SELECT id, role, nom, prenom FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    Auth::login($user);

    Response::created([
        'user_id' => $userId,
        'role' => $role,
        'connecte' => true,
    ], 'Compte cree avec succes.');
}

Response::created([
    'user_id' => $userId,
    'role' => $role,
    'connecte' => false,
], 'Compte cree. Il sera active apres validation par un administrateur.');
