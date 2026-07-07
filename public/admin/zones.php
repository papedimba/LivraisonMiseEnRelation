<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Villes & quartiers';
require __DIR__ . '/../includes/header.php';
?>
<h1>Villes &amp; quartiers</h1>
<p class="subtitle">Gerez la couverture geographique de la plateforme.</p>

<div id="alert-zone"></div>

<div class="grid grid-2">
    <div class="card">
        <h2>Ajouter une ville</h2>
        <div class="flex">
            <input type="text" id="nouvelle-ville" placeholder="Nom de la ville">
            <button type="button" class="btn" onclick="creerVille()">Ajouter</button>
        </div>
    </div>
    <div class="card">
        <h2>Ajouter un quartier</h2>
        <div class="form-group">
            <select id="ville-quartier"></select>
        </div>
        <div class="flex">
            <input type="text" id="nouveau-quartier" placeholder="Nom du quartier">
            <button type="button" class="btn" onclick="creerQuartier()">Ajouter</button>
        </div>
    </div>
</div>

<div id="liste-zones" class="mt-1"></div>

<script>
function message(txt, classe) {
    document.getElementById('alert-zone').innerHTML = '<div class="alert ' + classe + '">' + escapeHtml(txt) + '</div>';
}

async function charger() {
    const res = await Api.get('/api/admin/zones_list.php');
    const villes = res.data.villes;
    const quartiers = res.data.quartiers;

    // Selecteur de ville pour l'ajout de quartier
    document.getElementById('ville-quartier').innerHTML = villes.map(function (v) {
        return '<option value="' + v.id + '">' + escapeHtml(v.nom) + '</option>';
    }).join('');

    // Liste des villes avec leurs quartiers
    const zone = document.getElementById('liste-zones');
    if (villes.length === 0) {
        zone.innerHTML = '<div class="card"><p class="text-muted">Aucune ville.</p></div>';
        return;
    }
    zone.innerHTML = villes.map(function (v) {
        const qs = quartiers.filter(function (q) { return q.ville_id == v.id; });
        const lignesQ = qs.length ? qs.map(function (q) {
            return '<tr><td>' + escapeHtml(q.nom) + '</td>'
                + '<td>' + (q.actif == 1 ? '<span class="tag tag-succes">Actif</span>' : '<span class="tag tag-danger">Inactif</span>') + '</td>'
                + '<td><button class="btn btn-sm btn-ghost" onclick="toggleQuartier(' + q.id + ',' + (q.actif == 1 ? 0 : 1) + ')">'
                + (q.actif == 1 ? 'Desactiver' : 'Activer') + '</button></td></tr>';
        }).join('') : '<tr><td colspan="3" class="text-muted">Aucun quartier.</td></tr>';

        return '<div class="card mb-1">'
            + '<div class="flex-between"><h2>' + escapeHtml(v.nom) + ' '
            + (v.actif == 1 ? '<span class="tag tag-succes">Active</span>' : '<span class="tag tag-danger">Inactive</span>')
            + '</h2>'
            + '<button class="btn btn-sm btn-ghost" onclick="toggleVille(' + v.id + ',' + (v.actif == 1 ? 0 : 1) + ')">'
            + (v.actif == 1 ? 'Desactiver la ville' : 'Activer la ville') + '</button></div>'
            + '<div class="table-wrap"><table><thead><tr><th>Quartier</th><th>Statut</th><th></th></tr></thead>'
            + '<tbody>' + lignesQ + '</tbody></table></div></div>';
    }).join('');
}

async function creerVille() {
    const nom = document.getElementById('nouvelle-ville').value.trim();
    if (!nom) { return; }
    try {
        await Api.post('/api/admin/zones_save.php', { action: 'creer_ville', nom: nom });
        document.getElementById('nouvelle-ville').value = '';
        message('Ville ajoutee.', 'alert-succes');
        charger();
    } catch (err) { message(err.message, 'alert-erreur'); }
}

async function creerQuartier() {
    const villeId = document.getElementById('ville-quartier').value;
    const nom = document.getElementById('nouveau-quartier').value.trim();
    if (!villeId || !nom) { return; }
    try {
        await Api.post('/api/admin/zones_save.php', { action: 'creer_quartier', ville_id: villeId, nom: nom });
        document.getElementById('nouveau-quartier').value = '';
        message('Quartier ajoute.', 'alert-succes');
        charger();
    } catch (err) { message(err.message, 'alert-erreur'); }
}

async function toggleVille(id, actif) {
    try {
        await Api.post('/api/admin/zones_save.php', { action: 'toggle_ville', id: id, actif: actif });
        charger();
    } catch (err) { message(err.message, 'alert-erreur'); }
}

async function toggleQuartier(id, actif) {
    try {
        await Api.post('/api/admin/zones_save.php', { action: 'toggle_quartier', id: id, actif: actif });
        charger();
    } catch (err) { message(err.message, 'alert-erreur'); }
}

charger();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
