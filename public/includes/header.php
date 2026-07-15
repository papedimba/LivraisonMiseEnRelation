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

$libellesRoles = [
    'client' => 'Client',
    'livreur' => 'Livreur',
    'commercant' => 'Commercant',
    'admin' => 'Administrateur',
];

function nav_actif(string $href, string $courant): bool
{
    return basename($href) === $courant;
}

function initiales_nom(string $nomComplet): string
{
    $mots = preg_split('/\s+/', trim($nomComplet)) ?: [];
    $mots = array_filter($mots);
    if (!$mots) {
        return '?';
    }
    $init = '';
    foreach (array_slice($mots, 0, 2) as $mot) {
        $init .= mb_strtoupper(mb_substr($mot, 0, 1));
    }
    return $init;
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#4f46e5">
<title><?= htmlspecialchars($pageTitle ?? APP_NOM) ?> - <?= htmlspecialchars(APP_NOM) ?></title>
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" type="image/png" href="/assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(APP_NOM) ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/style.css') ?>">
<script>
(function () {
    try {
        var theme = localStorage.getItem('theme');
        if (theme === 'dark' || theme === 'light') {
            document.documentElement.setAttribute('data-theme', theme);
        }
    } catch (e) {}
})();
</script>
<script src="<?= asset_url('/assets/js/api.js') ?>"></script>
<script src="<?= asset_url('/assets/js/app.js') ?>"></script>
</head>
<body>
<?php if ($role): ?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <?php if (is_file(APP_ROOT . '/public/assets/logo.png')): ?>
            <img src="/assets/logo.png" alt="<?= htmlspecialchars(APP_NOM) ?>" class="brand-logo">
        <?php else: ?>
            🛵 <?= htmlspecialchars(APP_NOM) ?>
        <?php endif; ?>
    </div>
    <div class="sidebar-user">
        <span class="avatar"><?= htmlspecialchars(initiales_nom($nomComplet)) ?></span>
        <div class="infos">
            <span class="nom"><?= htmlspecialchars($nomComplet) ?></span>
            <span class="role"><?= htmlspecialchars($libellesRoles[$role] ?? $role) ?></span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($items as [$href, $libelle, $icone]): ?>
            <a href="<?= $href ?>" class="<?= nav_actif($href, $courant) ? 'actif' : '' ?>">
                <span class="ic"><?= $icone ?></span>
                <span><?= htmlspecialchars($libelle) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
        <a href="/account/password.php" title="Mon compte">⚙️ Mon compte</a>
        <a href="/logout.php">🚪 Quitter</a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
<?php endif; ?>
<header class="topbar">
    <div class="topbar-inner">
        <?php if ($role): ?>
        <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Menu">☰</button>
        <?php endif; ?>
        <a class="brand" href="<?= $role ? '#' : '/' ?>">
            <?php if (is_file(APP_ROOT . '/public/assets/logo.png')): ?>
                <img src="/assets/logo.png" alt="<?= htmlspecialchars(APP_NOM) ?>" class="brand-logo">
            <?php else: ?>
                🛵 <?= htmlspecialchars(APP_NOM) ?>
            <?php endif; ?>
        </a>
        <?php if ($role): ?>
        <div class="topbar-user">
            <button type="button" id="theme-toggle" class="icon-btn" title="Mode sombre / clair">🌓</button>
            <button id="notif-bell" class="icon-btn" title="Notifications">🔔<span id="notif-count" class="badge hidden">0</span></button>
            <span class="avatar" title="<?= htmlspecialchars($nomComplet) ?>"><?= htmlspecialchars(initiales_nom($nomComplet)) ?></span>
        </div>
        <?php endif; ?>
    </div>
</header>
<div id="notif-panel" class="notif-panel hidden"></div>
<main class="page-content<?= $role ? ' contenu-avec-sidebar' : '' ?>">
