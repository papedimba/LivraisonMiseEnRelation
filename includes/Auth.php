<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

final class Auth
{
    public const ROLES = ['client', 'livreur', 'commercant', 'admin'];

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nom_complet'] = $user['prenom'] . ' ' . $user['nom'];
        $_SESSION['logged_at'] = time();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    public static function requireLogin(): int
    {
        if (!self::check()) {
            Response::unauthorized('Vous devez etre connecte.');
        }
        return self::id();
    }

    public static function requireRole(string ...$roles): int
    {
        $userId = self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            Response::forbidden('Acces reserve a : ' . implode(', ', $roles));
        }
        return $userId;
    }

    public static function currentUser(): ?array
    {
        if (!self::check()) {
            return null;
        }
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, role, nom, prenom, email, telephone, photo, statut FROM users WHERE id = :id');
        $stmt->execute(['id' => self::id()]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function enregistrerTentative(PDO $db, ?int $userId, string $email, bool $succes): void
    {
        $stmt = $db->prepare(
            'INSERT INTO journal_connexions (user_id, email_tente, ip_address, user_agent, succes) VALUES (:user_id, :email, :ip, :ua, :succes)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'succes' => $succes ? 1 : 0,
        ]);
    }
}
