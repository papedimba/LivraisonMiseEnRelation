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
        <p style="font-size:0.75rem;color:#888;">
            Version de la page : <strong>DIAG-4</strong> &middot;
            <span id="js-check" style="color:#dc2626;">JavaScript INACTIF</span>
        </p>
        <div id="diag" style="font-size:0.8rem;color:#2563eb;margin-bottom:0.5rem;"></div>
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
            <button type="button" id="btn-login" class="btn btn-block">Se connecter</button>
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

    function diag(message) {
        var zone = document.getElementById('diag');
        if (zone) {
            zone.textContent = message;
        }
    }

    function attacher() {
        var check = document.getElementById('js-check');
        if (check) {
            check.textContent = 'JavaScript actif';
            check.style.color = '#16a34a';
        }

        var btn = document.getElementById('btn-login');
        if (!btn) {
            diag('ERREUR : bouton introuvable dans le DOM.');
            return;
        }

        diag('Pret. Cliquez sur "Se connecter".');

        btn.addEventListener('click', function () {
            diag('1/3 - Clic recu, envoi de la requete...');

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
                diag('2/3 - Reponse recue : HTTP ' + res.status);
                return res.text().then(function (texte) {
                    var body;
                    try {
                        body = JSON.parse(texte);
                    } catch (err) {
                        throw new Error('Reponse non-JSON (HTTP ' + res.status + '). Debut : ' + texte.slice(0, 200));
                    }
                    if (!res.ok || !body.success) {
                        throw new Error(body.message || ('Erreur HTTP ' + res.status));
                    }
                    return body;
                });
            }).then(function (body) {
                diag('3/3 - Connexion reussie, redirection...');
                var chemins = {
                    client: '/client/dashboard.php',
                    livreur: '/livreur/dashboard.php',
                    commercant: '/commercant/dashboard.php',
                    admin: '/admin/dashboard.php',
                };
                window.location.href = chemins[body.data.role] || '/';
            }).catch(function (err) {
                diag('Echec.');
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
