<?php
/** @var string $pageTitle */
$role = Auth::role();
$nomComplet = $_SESSION['nom_complet'] ?? '';
$courant = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

// Elements de navigation par role : [href, libelle, icone].
$menus = [
    'client' => [
        ['/client/dashboard.php', 'Accueil', '🏠'],
        ['/client/new-order.php', 'Commander', '➕'],
        ['/client/shops.php', 'Boutiques', '🛍️'],
        ['/client/history.php', 'Courses', '🕒'],
        ['/client/support.php', 'Aide', '💬'],
    ],
    'livreur' => [
        ['/livreur/dashboard.php', 'Courses', '🛵'],
        ['/livreur/documents.php', 'Documents', '📄'],
        ['/livreur/history.php', 'Historique', '🕒'],
        ['/livreur/earnings.php', 'Gains', '💰'],
    ],
    'commercant' => [
        ['/commercant/dashboard.php', 'Boutique', '🏪'],
        ['/commercant/products.php', 'Produits', '📦'],
        ['/commercant/orders.php', 'Commandes', '🧾'],
        ['/commercant/stats.php', 'Stats', '📊'],
    ],
    'admin' => [
        ['/admin/dashboard.php', 'Tableau', '📊'],
        ['/admin/users.php', 'Users', '👥'],
        ['/admin/fleet.php', 'Flotte', '🛰️'],
        ['/admin/validations.php', 'Valider', '✅'],
        ['/admin/orders.php', 'Commandes', '🧾'],
        ['/admin/marketing.php', 'Marketing', '🎯'],
        ['/admin/zones.php', 'Zones', '📍'],
        ['/admin/settings.php', 'Tarifs', '⚙️'],
    ],
];
$items = $menus[$role] ?? [];

function nav_actif(string $href, string $courant): bool
{
    return basename($href) === $courant;
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#f26522">
<title><?= htmlspecialchars($pageTitle ?? APP_NOM) ?> - <?= htmlspecialchars(APP_NOM) ?></title>
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(APP_NOM) ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/style.css') ?>">
<script src="<?= asset_url('/assets/js/api.js') ?>"></script>
<script src="<?= asset_url('/assets/js/app.js') ?>"></script>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= $role ? '#' : '/' ?>">
            <?php if (is_file(APP_ROOT . '/public/assets/logo.png')): ?>
                <img src="/assets/logo.png" alt="<?= htmlspecialchars(APP_NOM) ?>" class="brand-logo">
            <?php else: ?>
                🛵 <?= htmlspecialchars(APP_NOM) ?>
            <?php endif; ?>
        </a>
        <?php if ($role): ?>
        <nav class="topnav">
            <?php foreach ($items as [$href, $libelle, $icone]): ?>
                <a href="<?= $href ?>" class="<?= nav_actif($href, $courant) ? 'actif' : '' ?>"><?= htmlspecialchars($libelle) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="topbar-user">
            <button id="notif-bell" class="icon-btn" title="Notifications">🔔<span id="notif-count" class="badge hidden">0</span></button>
            <a href="/account/password.php" class="user-name" title="Mon compte"><?= htmlspecialchars($nomComplet) ?></a>
            <a href="/logout.php" class="btn btn-ghost btn-sm">Quitter</a>
        </div>
        <?php endif; ?>
    </div>
</header>
<div id="notif-panel" class="notif-panel hidden"></div>
<main class="page-content">
