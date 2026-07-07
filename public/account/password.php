<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client', 'livreur', 'commercant', 'admin');

$pageTitle = 'Changer mon mot de passe';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
    <div class="card">
        <h1>Changer mon mot de passe</h1>
        <p class="subtitle">Choisissez un mot de passe d'au moins 8 caracteres.</p>
        <div id="alert-zone"></div>

        <div class="form-group">
            <label for="actuel">Mot de passe actuel</label>
            <input type="password" id="actuel" autocomplete="current-password">
        </div>
        <div class="form-group">
            <label for="nouveau">Nouveau mot de passe</label>
            <input type="password" id="nouveau" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="confirmation">Confirmer le nouveau mot de passe</label>
            <input type="password" id="confirmation" autocomplete="new-password">
        </div>
        <button type="button" id="btn-changer" class="btn btn-block" onclick="changerMotDePasse()">Enregistrer</button>
    </div>
</div>
<script>
function changerMotDePasse() {
    var alertZone = document.getElementById('alert-zone');
    var btn = document.getElementById('btn-changer');

    function message(txt, classe) {
        alertZone.innerHTML = '';
        var div = document.createElement('div');
        div.className = 'alert ' + classe;
        div.textContent = txt;
        alertZone.appendChild(div);
    }

    var actuel = document.getElementById('actuel').value;
    var nouveau = document.getElementById('nouveau').value;
    var confirmation = document.getElementById('confirmation').value;

    if (!actuel || !nouveau) {
        message('Veuillez remplir tous les champs.', 'alert-erreur');
        return;
    }
    if (nouveau.length < 8) {
        message('Le nouveau mot de passe doit contenir au moins 8 caracteres.', 'alert-erreur');
        return;
    }
    if (nouveau !== confirmation) {
        message('La confirmation ne correspond pas au nouveau mot de passe.', 'alert-erreur');
        return;
    }

    btn.disabled = true;

    fetch('/api/auth/change_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ mot_de_passe_actuel: actuel, nouveau_mot_de_passe: nouveau })
    }).then(function (res) {
        return res.json().then(function (body) {
            if (!res.ok || !body.success) {
                throw new Error(body.message || 'Erreur');
            }
            return body;
        });
    }).then(function () {
        message('Mot de passe modifie avec succes.', 'alert-succes');
        document.getElementById('actuel').value = '';
        document.getElementById('nouveau').value = '';
        document.getElementById('confirmation').value = '';
        btn.disabled = false;
    }).catch(function (err) {
        message(err.message, 'alert-erreur');
        btn.disabled = false;
    });
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
