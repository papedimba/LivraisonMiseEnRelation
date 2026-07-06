<?php
/** @var string $pageTitle */
$role = Auth::role();
$nomComplet = $_SESSION['nom_complet'] ?? '';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? APP_NOM) ?> - <?= htmlspecialchars(APP_NOM) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<script src="/assets/js/api.js"></script>
<script src="/assets/js/app.js"></script>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= $role ? '#' : '/' ?>">🚚 <?= htmlspecialchars(APP_NOM) ?></a>
        <?php if ($role): ?>
        <nav class="topnav">
            <?php if ($role === 'client'): ?>
                <a href="/client/dashboard.php">Accueil</a>
                <a href="/client/new-order.php">Nouvelle commande</a>
                <a href="/client/history.php">Historique</a>
                <a href="/client/support.php">Assistance</a>
            <?php elseif ($role === 'livreur'): ?>
                <a href="/livreur/dashboard.php">Courses disponibles</a>
                <a href="/livreur/history.php">Historique</a>
                <a href="/livreur/earnings.php">Mes gains</a>
            <?php elseif ($role === 'commercant'): ?>
                <a href="/commercant/dashboard.php">Boutique</a>
                <a href="/commercant/products.php">Produits</a>
                <a href="/commercant/orders.php">Commandes</a>
                <a href="/commercant/stats.php">Statistiques</a>
            <?php elseif ($role === 'admin'): ?>
                <a href="/admin/dashboard.php">Tableau de bord</a>
                <a href="/admin/users.php">Utilisateurs</a>
                <a href="/admin/validations.php">Validations</a>
                <a href="/admin/orders.php">Commandes</a>
                <a href="/admin/complaints.php">Reclamations</a>
                <a href="/admin/settings.php">Parametres</a>
            <?php endif; ?>
        </nav>
        <div class="topbar-user">
            <button id="notif-bell" class="icon-btn" title="Notifications">🔔<span id="notif-count" class="badge hidden">0</span></button>
            <span class="user-name"><?= htmlspecialchars($nomComplet) ?></span>
            <a href="/logout.php" class="btn btn-ghost btn-sm">Deconnexion</a>
        </div>
        <?php endif; ?>
    </div>
</header>
<div id="notif-panel" class="notif-panel hidden"></div>
<main class="page-content">
