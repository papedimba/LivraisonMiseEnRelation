<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    redirect_to_dashboard(Auth::role());
}

$pageTitle = 'Accueil';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <h1>La livraison rapide et fiable a Bouake</h1>
    <p>
        Repas, courses, medicaments, colis, documents, fleurs et cadeaux : commandez en quelques clics
        et suivez votre livreur en temps reel. Payez en especes ou via Mobile Money.
    </p>
    <div class="hero-actions">
        <a class="btn" href="/register.php?role=client">Je suis client</a>
        <a class="btn btn-secondaire" href="/register.php?role=livreur">Devenir livreur</a>
        <a class="btn btn-ghost" href="/register.php?role=commercant">Je suis commercant</a>
    </div>
</section>

<section class="features">
    <div class="grid grid-4">
        <div class="card">
            <h2>🍽️ Repas &amp; courses</h2>
            <p class="text-muted">Commandez chez vos restaurants, maquis, supermarches et pharmacies preferes.</p>
        </div>
        <div class="card">
            <h2>📦 Colis &amp; documents</h2>
            <p class="text-muted">Envoyez un colis ou un document administratif partout a Bouake, rapidement.</p>
        </div>
        <div class="card">
            <h2>📍 Suivi en temps reel</h2>
            <p class="text-muted">Suivez la position de votre livreur du depart jusqu'a la livraison.</p>
        </div>
        <div class="card">
            <h2>💳 Paiement flexible</h2>
            <p class="text-muted">Especes, Orange Money, MTN Mobile Money, Moov Money ou Wave.</p>
        </div>
    </div>
</section>

<section class="mt-1">
    <div class="card flex-between">
        <div>
            <h2>Vous etes deja inscrit ?</h2>
            <p class="text-muted">Connectez-vous pour acceder a votre espace.</p>
        </div>
        <a class="btn" href="/login.php">Se connecter</a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
