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
            Version de la page : <strong>DIAG-7</strong> &middot;
            <span id="js-check" style="color:#dc2626;">JavaScript INACTIF</span>
        </p>
        <div id="diag" style="font-size:0.8rem;color:#2563eb;margin-bottom:0.5rem;"></div>
        <div id="alert-zone"></div>
        <form id="login-form" onsubmit="return loginNow();">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" id="btn-login" class="btn btn-block" onclick="return loginNow();">Se connecter</button>
        </form>
    </div>
</div>
<script>
// MOUCHARD : rapporte a l'ecran l'element reellement clique, pour tout clic
// n'importe ou sur la page. Permet de voir si le clic atteint le bouton.
document.addEventListener('click', function (e) {
    var d = document.getElementById('diag');
    var t = e.target;
    if (d) {
        d.textContent = 'CLIC sur <' + t.tagName + '> id="' + (t.id || '(aucun)') + '" classe="' + (t.className || '(aucune)') + '"';
    }
}, true);

// Fonction globale appelee via attribut onclick/onsubmit inline.
function loginNow() {
    var diagZone = document.getElementById('diag');
    var alertZone = document.getElementById('alert-zone');

    function diag(msg) { if (diagZone) { diagZone.textContent = msg; } }
    function afficherErreur(msg) {
        if (!alertZone) { return; }
        var div = document.createElement('div');
        div.className = 'alert alert-erreur';
        div.textContent = msg;
        alertZone.innerHTML = '';
        alertZone.appendChild(div);
    }

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

    return false; // empeche la soumission native du formulaire
}

// Confirme simplement que le JavaScript s'execute sur la page.
(function () {
    var check = document.getElementById('js-check');
    if (check) {
        check.textContent = 'JavaScript actif';
        check.style.color = '#16a34a';
    }
    var diagZone = document.getElementById('diag');
    if (diagZone) {
        diagZone.textContent = 'Pret. Cliquez sur "Se connecter".';
    }
})();

// Ecouteur delegue au niveau de document (phase de capture). document n'est
// jamais reconstruit par une extension, donc ce declencheur survit meme si le
// bouton lui-meme est recree/remplace et perd son attribut onclick.
document.addEventListener('click', function (e) {
    var el = e.target;
    while (el) {
        if (el.id === 'btn-login') {
            e.preventDefault();
            loginNow();
            return;
        }
        el = el.parentElement;
    }
}, true);

// Filet supplementaire : soumission du formulaire par la touche Entree.
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        var actif = document.activeElement;
        if (actif && (actif.id === 'email' || actif.id === 'password')) {
            e.preventDefault();
            loginNow();
        }
    }
}, true);
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
