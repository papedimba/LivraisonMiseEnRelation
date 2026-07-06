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
// Version autonome : ne depend d'aucun fichier JS externe, pour fonctionner
// meme si /assets/js/*.js ne se charge pas (mauvais document root, etc.).
(function () {
    function afficherErreur(message) {
        var zone = document.getElementById('alert-zone');
        var div = document.createElement('div');
        div.className = 'alert alert-erreur';
        div.textContent = message;
        zone.innerHTML = '';
        zone.appendChild(div);
    }

    function attacher() {
        var form = document.getElementById('login-form');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var data = {
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
            };

            fetch('/api/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(data),
            }).then(function (res) {
                return res.text().then(function (texte) {
                    var body;
                    try {
                        body = JSON.parse(texte);
                    } catch (err) {
                        // La reponse n'est pas du JSON : on affiche un extrait pour diagnostic.
                        throw new Error('Reponse inattendue du serveur (HTTP ' + res.status + '). Verifiez la connexion a la base de donnees. Debut de la reponse : ' + texte.slice(0, 200));
                    }
                    if (!res.ok || !body.success) {
                        throw new Error(body.message || ('Erreur HTTP ' + res.status));
                    }
                    return body;
                });
            }).then(function (body) {
                var chemins = {
                    client: '/client/dashboard.php',
                    livreur: '/livreur/dashboard.php',
                    commercant: '/commercant/dashboard.php',
                    admin: '/admin/dashboard.php',
                };
                window.location.href = chemins[body.data.role] || '/';
            }).catch(function (err) {
                console.error('Erreur de connexion :', err);
                afficherErreur(err.message);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attacher);
    } else {
        attacher();
    }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
