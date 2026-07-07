<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

define('APP_NOM', env('APP_NOM', 'LivraisonCI'));
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_URL', env('APP_URL', ''));
define('APP_ROOT', dirname(__DIR__));
define('STORAGE_PATH', APP_ROOT . '/storage');
define('UPLOADS_URL_BASE', '/storage/uploads');

define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 7200));

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'livraison_ci'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

define('ANTHROPIC_API_KEY', env('ANTHROPIC_API_KEY', ''));
define('ANTHROPIC_MODEL', env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'));

define('MOBILE_MONEY_CONFIG', [
    'orange_money' => [
        'api_key' => env('ORANGE_MONEY_API_KEY', ''),
        'merchant_id' => env('ORANGE_MONEY_MERCHANT_ID', ''),
        'secret' => env('ORANGE_MONEY_SECRET', ''),
    ],
    'mtn_money' => [
        'api_key' => env('MTN_MOMO_API_KEY', ''),
        'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY', ''),
        'user_id' => env('MTN_MOMO_USER_ID', ''),
    ],
    'moov_money' => [
        'api_key' => env('MOOV_MONEY_API_KEY', ''),
        'merchant_id' => env('MOOV_MONEY_MERCHANT_ID', ''),
    ],
    'wave' => [
        'api_key' => env('WAVE_API_KEY', ''),
        'secret' => env('WAVE_SECRET', ''),
    ],
]);

if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

date_default_timezone_set('Africa/Abidjan');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        // Le flag Secure doit refleter le VRAI protocole de la connexion, pas
        // l'environnement : en HTTP (dev local, Laragon) un cookie Secure serait
        // rejete par le navigateur et la session perdue apres la connexion.
        'secure' => (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443)
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('livraison_ci_session');
    session_start();
}
