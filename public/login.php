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
        <p style="font-size:0.75rem;color:#888;">Version : <strong>DIAG-8</strong> &middot; <span id="etat">chargement...</span></p>
        <div id="err-globale" style="color:#dc2626;font-size:0.8rem;white-space:pre-wrap;"></div>
        <div id="alert-zone"></div>

        <!-- Pas de <form> : on evite toute soumission native qui rechargerait la page. -->
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" autocomplete="email">
        </div>
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" autocomplete="current-password">
        </div>
        <button type="button" id="btn-login" class="btn btn-block" onclick="loginNow()">Se connecter</button>
    </div>
</div>
<script>
// Capteur d'erreurs global : affiche a l'ecran toute erreur JS non capturee.
window.onerror = function (message, source, ligne, colonne) {
    var z = document.getElementById('err-globale');
    if (z) {
        z.textContent = 'ERREUR JS : ' + message + '  (' + source + ' ligne ' + ligne + ')';
    }
    return false;
};

function loginNow() {
    var etat = document.getElementById('etat');
    if (etat) { etat.textContent = 'fonction loginNow appelee'; }
    var alertZone = document.getElementById('alert-zone');
    var btn = document.getElementById('btn-login');

    function afficherErreur(msg) {
        alertZone.innerHTML = '';
        var div = document.createElement('div');
        div.className = 'alert alert-erreur';
        div.textContent = msg;
        alertZone.appendChild(div);
    }

    var email = document.getElementById('email').value.trim();
    var password = document.getElementById('password').value;

    if (!email || !password) {
        afficherErreur('Veuillez saisir votre email et votre mot de passe.');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Connexion...';
    if (etat) { etat.textContent = 'envoi de la requete...'; }

    fetch('/api/auth/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ email: email, password: password })
    }).then(function (res) {
        if (etat) { etat.textContent = 'reponse HTTP ' + res.status; }
        return res.text().then(function (texte) {
            var body;
            try {
                body = JSON.parse(texte);
            } catch (err) {
                throw new Error('Reponse inattendue du serveur (HTTP ' + res.status + ').');
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
            admin: '/admin/dashboard.php'
        };
        window.location.href = chemins[body.data.role] || '/';
    }).catch(function (err) {
        afficherErreur(err.message);
        btn.disabled = false;
        btn.textContent = 'Se connecter';
    });
}

// Permet aussi de valider avec la touche Entree depuis les champs.
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        var a = document.activeElement;
        if (a && (a.id === 'email' || a.id === 'password')) {
            e.preventDefault();
            loginNow();
        }
    }
});

// Confirme que ce script s'est bien execute jusqu'au bout.
(function () {
    var etat = document.getElementById('etat');
    if (etat) { etat.textContent = 'pret (typeof loginNow = ' + (typeof loginNow) + ')'; }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
