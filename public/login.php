<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    redirect_to_dashboard(Auth::role());
}

$pageTitle = 'Connexion';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-tabs">
        <a href="/login.php" class="active">Connexion</a>
        <a href="/register.php">Inscription</a>
    </div>
    <div class="card">
        <h1>Connexion</h1>
        <p class="subtitle">Accedez a votre espace client, livreur, commercant ou administrateur.</p>
        <div id="alert-zone"></div>
        <form id="login-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-block">Se connecter</button>
        </form>
    </div>
</div>
<script>
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertZone = document.getElementById('alert-zone');
    alertZone.innerHTML = '';

    const data = {
        email: document.getElementById('email').value.trim(),
        password: document.getElementById('password').value,
    };

    try {
        const res = await Api.post('/api/auth/login.php', data);
        const chemins = { client: '/client/dashboard.php', livreur: '/livreur/dashboard.php', commercant: '/commercant/dashboard.php', admin: '/admin/dashboard.php' };
        window.location.href = chemins[res.data.role] || '/';
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
