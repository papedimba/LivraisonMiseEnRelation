<?php
declare(strict_types=1);

// Definition de secours pour app_log() (voir la definition canonique dans
// config/config.php) : ce fichier est requis separement et explicitement par
// la quasi-totalite des points d'entree, y compris quand un cache d'opcode
// (OPcache) sur un hebergement mutualise sert encore une version anterieure
// de config.php sans cette fonction. Si config.php a deja pu la definir, ce
// bloc ne fait rien (function_exists()).
if (!function_exists('app_log')) {
    function app_log(string $message): void
    {
        $chemin = defined('LOG_PATH') ? LOG_PATH : (defined('STORAGE_PATH') ? STORAGE_PATH . '/logs/app.log' : null);
        if ($chemin === null) {
            return;
        }
        $dossier = dirname($chemin);
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }
        $ligne = '[' . date('d-M-Y H:i:s') . ' ' . date_default_timezone_get() . '] ' . $message . PHP_EOL;
        @file_put_contents($chemin, $ligne, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Recupere le corps JSON de la requete (fallback sur $_POST).
 */
function request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST;
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return $_POST;
    }

    return $decoded;
}

function input(array $body, string $key, $default = null)
{
    return $body[$key] ?? $default;
}

function require_fields(array $body, array $fields): array
{
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($body[$field]) || (is_string($body[$field]) && trim($body[$field]) === '')) {
            $missing[] = $field;
        }
    }
    return $missing;
}

function clean_str(?string $value): string
{
    return trim(strip_tags((string) $value));
}

function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function is_valid_phone(string $phone): bool
{
    return (bool) preg_match('/^[0-9+ ]{8,15}$/', $phone);
}

/**
 * Distance en kilometres entre deux points GPS (formule de Haversine).
 */
function haversine_distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadiusKm = 6371.0;

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return round($earthRadiusKm * $c, 2);
}

/**
 * Estime le cout d'une livraison selon le type, la distance et l'option express.
 */
function estimer_cout(float $tarifBase, float $tarifKm, float $distanceKm, bool $express, float $supplementExpress): float
{
    $cout = $tarifBase + ($tarifKm * $distanceKm);
    if ($express) {
        $cout += $supplementExpress;
    }
    return round($cout, 0);
}

function generer_reference_commande(): string
{
    return 'CMD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Code de livraison a 4 chiffres, communique au client et demande au livreur
 * a la remise comme preuve de livraison.
 */
function generer_code_livraison(): string
{
    return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

function parametre(PDO $db, string $cle, ?string $defaut = null): ?string
{
    $stmt = $db->prepare('SELECT valeur FROM parametres WHERE cle = :cle');
    $stmt->execute(['cle' => $cle]);
    $row = $stmt->fetch();
    return $row ? $row['valeur'] : $defaut;
}

function json_response_ok(bool $ok): bool
{
    return $ok;
}

function creer_notification(PDO $db, int $userId, string $titre, string $message, string $type = 'info', ?string $lien = null): void
{
    $stmt = $db->prepare(
        'INSERT INTO notifications (user_id, titre, message, type, lien) VALUES (:user_id, :titre, :message, :type, :lien)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'titre' => $titre,
        'message' => $message,
        'type' => $type,
        'lien' => $lien,
    ]);

    // Envoi Web Push best-effort (ne bloque jamais la creation de la notification).
    if (is_file(__DIR__ . '/WebPush.php')) {
        try {
            require_once __DIR__ . '/WebPush.php';
            if (WebPush::isConfigured()) {
                WebPush::envoyerAUtilisateur($db, $userId);
            }
        } catch (Throwable $e) {
            app_log('Push notification error: ' . $e->getMessage());
        }
    }
}

/**
 * URL d'un asset avec cache-busting : ajoute ?v=<date de modification> pour que
 * le navigateur recharge le fichier des qu'il change (evite les JS/CSS obsoletes
 * en cache apres une mise a jour).
 */
function asset_url(string $chemin): string
{
    $abs = APP_ROOT . '/public' . $chemin;
    $version = is_file($abs) ? filemtime($abs) : date('Ymd');
    return $chemin . '?v=' . $version;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Lit les derniers octets d'un fichier (tail simplifie), pour afficher un
 * journal sans devoir le charger entierement en memoire s'il est volumineux.
 */
function lire_fin_fichier(string $chemin, int $maxOctets = 200000): string
{
    if (!is_file($chemin) || !is_readable($chemin)) {
        return '';
    }
    $taille = filesize($chemin) ?: 0;
    $handle = fopen($chemin, 'r');
    if ($handle === false) {
        return '';
    }
    if ($taille > $maxOctets) {
        fseek($handle, -$maxOctets, SEEK_END);
        // Ignore la premiere ligne (probablement tronquee) apres le seek.
        fgets($handle);
    }
    $contenu = stream_get_contents($handle);
    fclose($handle);
    return (string) $contenu;
}
