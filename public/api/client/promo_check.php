<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/promo.php';

Auth::requireRole('client');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$code = clean_str(input($body, 'code', ''));
$montant = (float) input($body, 'montant', 0);

if ($code === '') {
    Response::error('Code requis.');
}

$db = Database::getConnection();

try {
    [$codeApplique, $reduction] = appliquer_code_promo($db, $code, $montant);
} catch (PromoException $e) {
    Response::error($e->getMessage(), 422);
}

Response::success([
    'code' => $codeApplique,
    'reduction' => $reduction,
    'nouveau_montant' => max(0, $montant - $reduction),
], 'Code promo valide.');
