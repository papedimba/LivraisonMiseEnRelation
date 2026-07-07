<?php
declare(strict_types=1);

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
            error_log('Push notification error: ' . $e->getMessage());
        }
    }
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
