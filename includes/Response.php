<?php
declare(strict_types=1);

final class Response
{
    public static function json(array $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(array $data = [], string $message = 'OK'): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], 200);
    }

    public static function created(array $data = [], string $message = 'Cree'): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], 201);
    }

    public static function error(string $message, int $statusCode = 400, array $errors = []): never
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $statusCode);
    }

    public static function unauthorized(string $message = 'Non autorise'): never
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Acces interdit'): never
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Ressource introuvable'): never
    {
        self::error($message, 404);
    }

    public static function methodNotAllowed(string $message = 'Methode non autorisee'): never
    {
        self::error($message, 405);
    }

    public static function serverError(string $message = 'Erreur serveur'): never
    {
        self::error($message, 500);
    }
}
