<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Auth.php';

function require_page_role(string ...$roles): void
{
    if (!Auth::check()) {
        header('Location: /login.php');
        exit;
    }
    if (!in_array(Auth::role(), $roles, true)) {
        header('Location: /login.php?erreur=acces_refuse');
        exit;
    }
}

function redirect_to_dashboard(string $role): never
{
    $chemins = [
        'client' => '/client/dashboard.php',
        'livreur' => '/livreur/dashboard.php',
        'commercant' => '/commercant/dashboard.php',
        'admin' => '/admin/dashboard.php',
    ];
    header('Location: ' . ($chemins[$role] ?? '/login.php'));
    exit;
}
