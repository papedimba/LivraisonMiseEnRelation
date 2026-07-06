<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Parametres';
require __DIR__ . '/../includes/header.php';
?>
<h1>Parametres</h1>

<div class="grid grid-2">
    <div class="card">
        <h2>Commission &amp; parametres generaux</h2>
        <div id="alert-zone-1"></div>
        <form id="settings-form">
            <div class="form-group">
                <label for="commission_taux_defaut">Taux de commission par defaut (%)</label>
                <input type="number" id="commission_taux_defaut" min="0" max="100" step="0.5">
            </div>
            <div class="form-group">
                <label for="app_nom">Nom de l'application</label>
                <input type="text" id="app_nom">
            </div>
            <div class="form-group">
                <label for="ville_defaut">Ville par defaut</label>
                <input type="text" id="ville_defaut">
            </div>
            <button type="submit" class="btn">Enregistrer</button>
        </form>
    </div>

    <div class="card">
        <h2>Envoyer une notification / promotion</h2>
        <div id="alert-zone-2"></div>
        <form id="broadcast-form">
            <div class="form-group">
                <label for="cible-role">Destinataires</label>
                <select id="cible-role">
                    <option value="">Tous les utilisateurs</option>
                    <option value="client">Clients uniquement</option>
                    <option value="livreur">Livreurs uniquement</option>
                    <option value="commercant">Commercants uniquement</option>
                </select>
            </div>
            <div class="form-group">
                <label for="titre">Titre</label>
                <input type="text" id="titre" required>
            </div>
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" required></textarea>
            </div>
            <button type="submit" class="btn btn-secondaire">Envoyer</button>
        </form>
    </div>
</div>

<div class="card mt-1">
    <h2>Types de livraison &amp; tarifs</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Tarif de base</th><th>Tarif / km</th><th>Supplement express</th><th>Actif</th></tr></thead>
            <tbody id="liste-types"><tr><td colspan="5" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
</div>

<script>
async function chargerParametres() {
    const res = await Api.get('/api/admin/settings.php');
    const p = res.data.parametres;
    document.getElementById('commission_taux_defaut').value = p.commission_taux_defaut ?? 15;
    document.getElementById('app_nom').value = p.app_nom ?? '';
    document.getElementById('ville_defaut').value = p.ville_defaut ?? '';
}

document.getElementById('settings-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertZone = document.getElementById('alert-zone-1');
    try {
        await Api.post('/api/admin/settings.php', {
            commission_taux_defaut: document.getElementById('commission_taux_defaut').value,
            app_nom: document.getElementById('app_nom').value.trim(),
            ville_defaut: document.getElementById('ville_defaut').value.trim(),
        });
        alertZone.innerHTML = '<div class="alert alert-succes">Parametres enregistres.</div>';
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

document.getElementById('broadcast-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertZone = document.getElementById('alert-zone-2');
    try {
        const res = await Api.post('/api/admin/notifications_broadcast.php', {
            role: document.getElementById('cible-role').value,
            titre: document.getElementById('titre').value.trim(),
            message: document.getElementById('message').value.trim(),
        });
        alertZone.innerHTML = `<div class="alert alert-succes">Notification envoyee a ${res.data.nombre_destinataires} utilisateur(s).</div>`;
        document.getElementById('broadcast-form').reset();
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

async function chargerTypes() {
    const tbody = document.getElementById('liste-types');
    const res = await Api.get('/api/public/delivery_types.php');
    tbody.innerHTML = res.data.types.map(t => `
        <tr>
            <td>${escapeHtml(t.nom)}</td>
            <td><input type="number" value="${t.tarif_base}" data-id="${t.id}" data-champ="tarif_base" style="width:100px;"></td>
            <td><input type="number" value="${t.tarif_km}" data-id="${t.id}" data-champ="tarif_km" style="width:100px;"></td>
            <td><input type="number" value="${t.supplement_express}" data-id="${t.id}" data-champ="supplement_express" style="width:100px;"></td>
            <td><input type="checkbox" ${t.actif == 1 ? 'checked' : ''} data-id="${t.id}" data-champ="actif" style="width:auto;"></td>
        </tr>
    `).join('');

    tbody.querySelectorAll('input').forEach(input => {
        input.addEventListener('change', async () => {
            const champ = input.dataset.champ;
            const valeur = champ === 'actif' ? input.checked : input.value;
            try {
                await Api.post('/api/admin/delivery_types_update.php', { id: input.dataset.id, [champ]: valeur });
            } catch (err) {
                alert(err.message);
            }
        });
    });
}

chargerParametres();
chargerTypes();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
