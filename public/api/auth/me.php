<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireLogin();

$user = Auth::currentUser();
if (!$user) {
    Response::notFound('Utilisateur introuvable.');
}

Response::success($user);
