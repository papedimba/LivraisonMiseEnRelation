<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';

Auth::requireRole('admin');

$db = Database::getConnection();
$promos = $db->query('SELECT * FROM codes_promo ORDER BY created_at DESC')->fetchAll();

Response::success(['promos' => $promos]);
