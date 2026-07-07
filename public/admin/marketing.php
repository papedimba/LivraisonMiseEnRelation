<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Marketing';
require __DIR__ . '/../includes/header.php';
?>
<h1>Marketing</h1>
<p class="subtitle">Codes promo et abonnements Premium des commerçants.</p>

<div id="alert-zone"></div>

<div class="card mb-1">
    <h2>Nouveau code promo</h2>
    <div class="grid grid-3">
        <div class="form-group">
            <label for="p-code">Code</label>
            <input type="text" id="p-code" placeholder="BIENVENUE10">
        </div>
        <div class="form-group">
            <label for="p-type">Type</label>
            <select id="p-type">
                <option value="pourcentage">Pourcentage (%)</option>
                <option value="montant">Montant fixe (FCFA)</option>
            </select>
        </div>
        <div class="form-group">
            <label for="p-valeur">Valeur</label>
            <input type="number" id="p-valeur" min="1">
        </div>
    </div>
    <div class="grid grid-4">
        <div class="form-group">
            <label for="p-min">Montant minimum</label>
            <input type="number" id="p-min" min="0" value="0">
        </div>
        <div class="form-group">
            <label for="p-usage">Usages max (0 = illimite)</label>
            <input type="number" id="p-usage" min="0" value="0">
        </div>
        <div class="form-group">
            <label for="p-debut">Date debut</label>
            <input type="date" id="p-debut">
        </div>
        <div class="form-group">
            <label for="p-fin">Date fin</label>
            <input type="date" id="p-fin">
        </div>
    </div>
    <button type="button" class="btn" onclick="creerPromo()">Creer le code</button>
</div>

<div class="card mb-1">
    <h2>Codes promo existants</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Code</th><th>Reduction</th><th>Min</th><th>Usage</th><th>Validite</th><th>Statut</th><th></th></tr></thead>
            <tbody id="liste-promos"><tr><td colspan="7" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Boutiques Premium</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Boutique</th><th>Categorie</th><th>Premium</th><th>Expire</th><th></th></tr></thead>
            <tbody id="liste-commercants"><tr><td colspan="5" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
</div>

<script>
function message(txt, classe) {
    document.getElementById('alert-zone').innerHTML = '<div class="alert ' + classe + '">' + escapeHtml(txt) + '</div>';
}

async function chargerPromos() {
    const res = await Api.get('/api/admin/promos_list.php');
    const tbody = document.getElementById('liste-promos');
    const promos = res.data.promos;
    tbody.innerHTML = promos.length ? promos.map(function (p) {
        const reduction = p.type === 'pourcentage' ? (p.valeur + ' %') : formatMontant(p.valeur);
        const validite = (p.date_debut || '—') + ' → ' + (p.date_fin || '—');
        const usage = p.usage_count + (p.usage_max ? ' / ' + p.usage_max : '');
        return '<tr>'
            + '<td><strong>' + escapeHtml(p.code) + '</strong></td>'
            + '<td>' + reduction + '</td>'
            + '<td>' + formatMontant(p.montant_min) + '</td>'
            + '<td>' + usage + '</td>'
            + '<td>' + escapeHtml(validite) + '</td>'
            + '<td>' + (p.actif == 1 ? '<span class="tag tag-succes">Actif</span>' : '<span class="tag tag-danger">Inactif</span>') + '</td>'
            + '<td>'
            + '<button class="btn btn-sm btn-ghost" onclick="togglePromo(' + p.id + ',' + (p.actif == 1 ? 0 : 1) + ')">' + (p.actif == 1 ? 'Desactiver' : 'Activer') + '</button> '
            + '<button class="btn btn-sm btn-danger" onclick="supprimerPromo(' + p.id + ')">Suppr.</button>'
            + '</td></tr>';
    }).join('') : '<tr><td colspan="7" class="text-muted">Aucun code promo.</td></tr>';
}

async function creerPromo() {
    try {
        await Api.post('/api/admin/promos_save.php', {
            action: 'creer',
            code: document.getElementById('p-code').value.trim(),
            type: document.getElementById('p-type').value,
            valeur: document.getElementById('p-valeur').value,
            montant_min: document.getElementById('p-min').value,
            usage_max: document.getElementById('p-usage').value,
            date_debut: document.getElementById('p-debut').value,
            date_fin: document.getElementById('p-fin').value,
        });
        message('Code promo cree.', 'alert-succes');
        document.getElementById('p-code').value = '';
        document.getElementById('p-valeur').value = '';
        chargerPromos();
    } catch (err) { message(err.message, 'alert-erreur'); }
}

async function togglePromo(id, actif) {
    try { await Api.post('/api/admin/promos_save.php', { action: 'toggle', id: id, actif: actif }); chargerPromos(); }
    catch (err) { message(err.message, 'alert-erreur'); }
}

async function supprimerPromo(id) {
    if (!confirm('Supprimer ce code promo ?')) return;
    try { await Api.post('/api/admin/promos_save.php', { action: 'supprimer', id: id }); chargerPromos(); }
    catch (err) { message(err.message, 'alert-erreur'); }
}

async function chargerCommercants() {
    const res = await Api.get('/api/admin/commercants_list.php');
    const tbody = document.getElementById('liste-commercants');
    const commercants = res.data.commercants;
    tbody.innerHTML = commercants.length ? commercants.map(function (c) {
        return '<tr>'
            + '<td>' + escapeHtml(c.nom_boutique) + '</td>'
            + '<td>' + escapeHtml(c.categorie) + '</td>'
            + '<td>' + (c.abonnement_premium == 1 ? '⭐ Premium' : 'Standard') + '</td>'
            + '<td>' + (c.abonnement_expire_at ? escapeHtml(c.abonnement_expire_at) : '—') + '</td>'
            + '<td>'
            + (c.abonnement_premium == 1
                ? '<button class="btn btn-sm btn-ghost" onclick="setPremium(' + c.user_id + ',false)">Retirer Premium</button>'
                : '<button class="btn btn-sm" onclick="setPremium(' + c.user_id + ',true)">Activer Premium (1 mois)</button>')
            + '</td></tr>';
    }).join('') : '<tr><td colspan="5" class="text-muted">Aucun commercant valide.</td></tr>';
}

async function setPremium(userId, premium) {
    try {
        await Api.post('/api/admin/commercants_premium.php', { user_id: userId, premium: premium, duree_mois: 1 });
        message(premium ? 'Premium active.' : 'Premium retire.', 'alert-succes');
        chargerCommercants();
    } catch (err) { message(err.message, 'alert-erreur'); }
}

chargerPromos();
chargerCommercants();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
