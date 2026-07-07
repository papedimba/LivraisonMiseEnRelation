<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('livreur');

$pageTitle = 'Mes documents';
require __DIR__ . '/../includes/header.php';
?>
<h1>Mes documents</h1>
<p class="subtitle">Deposez vos pieces justificatives pour la validation de votre compte livreur.</p>

<div id="statut-zone" class="mb-1"></div>

<div class="card">
    <div id="alert-zone"></div>

    <div class="form-group">
        <label for="piece_identite">Piece d'identite (CNI, passeport) <span id="etat-piece_identite" class="text-muted"></span></label>
        <input type="file" id="piece_identite" accept=".jpg,.jpeg,.png,.pdf">
    </div>
    <div class="form-group">
        <label for="permis">Permis de conduire <span id="etat-permis" class="text-muted"></span></label>
        <input type="file" id="permis" accept=".jpg,.jpeg,.png,.pdf">
    </div>
    <div class="form-group">
        <label for="carte_grise">Carte grise du vehicule <span id="etat-carte_grise" class="text-muted"></span></label>
        <input type="file" id="carte_grise" accept=".jpg,.jpeg,.png,.pdf">
    </div>
    <p class="text-muted">Formats acceptes : JPG, PNG, PDF. Taille maximale : 5 Mo par fichier.</p>

    <button type="button" id="btn-envoyer" class="btn" onclick="envoyerDocuments()">Envoyer mes documents</button>
</div>

<script>
var LABELS = {
    valide: ['Compte valide', 'tag-succes'],
    en_attente: ['En attente de validation', 'tag-attente'],
    rejete: ['Dossier rejete', 'tag-danger']
};

function chargerStatut() {
    fetch('/api/livreur/profile.php', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            var d = res.data;
            var info = LABELS[d.statut_validation] || ['-', 'tag-info'];
            var html = '<div class="card"><span class="tag ' + info[1] + '">' + info[0] + '</span>';
            if (d.statut_validation === 'rejete' && d.motif_rejet) {
                html += '<p class="text-muted mt-1">Motif : ' + escapeHtmlLocal(d.motif_rejet) + '</p>';
            }
            if (d.statut_validation === 'valide') {
                html += '<p class="text-muted mt-1">Votre compte est actif. Vous pouvez accepter des courses.</p>';
            }
            html += '</div>';
            document.getElementById('statut-zone').innerHTML = html;

            ['piece_identite', 'permis', 'carte_grise'].forEach(function (t) {
                var span = document.getElementById('etat-' + t);
                if (d.documents[t]) {
                    span.innerHTML = '&mdash; <a href="/api/livreur/document.php?type=' + t + '" target="_blank">deja envoye (voir)</a>';
                } else {
                    span.textContent = '(non fourni)';
                }
            });
        });
}

function escapeHtmlLocal(s) {
    var div = document.createElement('div');
    div.textContent = s == null ? '' : s;
    return div.innerHTML;
}

function envoyerDocuments() {
    var alertZone = document.getElementById('alert-zone');
    var btn = document.getElementById('btn-envoyer');

    function message(txt, classe) {
        alertZone.innerHTML = '';
        var div = document.createElement('div');
        div.className = 'alert ' + classe;
        div.textContent = txt;
        alertZone.appendChild(div);
    }

    var form = new FormData();
    var auMoinsUn = false;
    ['piece_identite', 'permis', 'carte_grise'].forEach(function (t) {
        var input = document.getElementById(t);
        if (input.files.length > 0) {
            form.append(t, input.files[0]);
            auMoinsUn = true;
        }
    });

    if (!auMoinsUn) {
        message('Veuillez selectionner au moins un document.', 'alert-erreur');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Envoi...';

    fetch('/api/livreur/upload_documents.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: form
    }).then(function (res) {
        return res.json().then(function (body) {
            if (!res.ok || !body.success) {
                throw new Error(body.message || 'Erreur');
            }
            return body;
        });
    }).then(function (body) {
        message(body.message, 'alert-succes');
        btn.disabled = false;
        btn.textContent = 'Envoyer mes documents';
        chargerStatut();
    }).catch(function (err) {
        message(err.message, 'alert-erreur');
        btn.disabled = false;
        btn.textContent = 'Envoyer mes documents';
    });
}

chargerStatut();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
