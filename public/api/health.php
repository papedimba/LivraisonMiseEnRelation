<?php
declare(strict_types=1);

/**
 * Diagnostic de deploiement (a supprimer ou proteger apres mise en service).
 * Ouvrez cette URL dans le navigateur : https://votre-domaine.com/api/health.php
 * Elle indique si le fichier .env est charge, si la connexion MySQL fonctionne
 * et si le schema est importe -- sans exposer de mot de passe.
 */

require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$diag = [
    'php_version' => PHP_VERSION,
    'php_ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'env_charge' => is_file(APP_ROOT . '/.env'),
    'db_host' => DB_HOST,
    'db_name' => DB_NAME,
    'db_user' => DB_USER,
    'db_connexion' => false,
    'db_erreur' => null,
    'tables' => 0,
    'compte_admin' => false,
];

try {
    require_once __DIR__ . '/../../config/database.php';
    $db = Database::getConnection();
    $diag['db_connexion'] = true;

    $diag['tables'] = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
    )->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $diag['compte_admin'] = ((int) $stmt->fetchColumn()) > 0;
} catch (Throwable $e) {
    $diag['db_erreur'] = $e->getMessage();
}

$diag['statut'] = ($diag['db_connexion'] && $diag['tables'] > 0 && $diag['compte_admin'])
    ? 'PRET'
    : 'CONFIGURATION INCOMPLETE';

echo json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
