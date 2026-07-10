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
    <h2>Dispatch automatique</h2>
    <div id="alert-zone-3"></div>
    <div class="grid grid-4">
        <div class="form-group">
            <label for="dispatch_offre_secondes">Delai de reponse (secondes)</label>
            <input type="number" id="dispatch_offre_secondes" min="10" max="300">
        </div>
        <div class="form-group">
            <label for="dispatch_rayon_max_km">Rayon max (km, 0 = illimite)</label>
            <input type="number" id="dispatch_rayon_max_km" min="0" step="0.5">
        </div>
        <div class="form-group">
            <label for="dispatch_poids_note">Poids de la note (km / point)</label>
            <input type="number" id="dispatch_poids_note" min="0" step="0.1">
        </div>
        <div class="form-group" style="display:flex;align-items:flex-end;">
            <button type="button" class="btn btn-block" onclick="enregistrerDispatch()">Enregistrer</button>
        </div>
    </div>
    <p class="text-muted">Le livreur choisi minimise : distance + (5 - note) x poids. Un poids de 0 = uniquement la distance.</p>
</div>

<div class="card mt-1">
    <h2>Tarification dynamique (surge)</h2>
    <div id="alert-zone-4"></div>
    <div class="form-group">
        <label><input type="checkbox" id="surge_actif" style="width:auto;display:inline-block;"> Activer la tarification dynamique</label>
    </div>
    <div class="grid grid-3">
        <div class="form-group">
            <label for="surge_manuel">Majoration manuelle (x)</label>
            <input type="number" id="surge_manuel" min="1" max="5" step="0.1">
        </div>
        <div class="form-group">
            <label for="surge_max">Majoration maximale (x)</label>
            <input type="number" id="surge_max" min="1" max="5" step="0.1">
        </div>
        <div class="form-group" style="display:flex;align-items:center;">
            <label><input type="checkbox" id="surge_auto" style="width:auto;display:inline-block;"> Majoration automatique</label>
        </div>
    </div>
    <div class="grid grid-4">
        <div class="form-group">
            <label for="surge_heures_pointe">Heures de pointe</label>
            <input type="text" id="surge_heures_pointe" placeholder="11-14,18-21">
        </div>
        <div class="form-group">
            <label for="surge_facteur_pointe">Facteur pointe (x)</label>
            <input type="number" id="surge_facteur_pointe" min="1" max="5" step="0.1">
        </div>
        <div class="form-group">
            <label for="surge_ratio_seuil">Seuil demande/livreur</label>
            <input type="number" id="surge_ratio_seuil" min="0" step="0.5">
        </div>
        <div class="form-group">
            <label for="surge_facteur_demande">Facteur demande (x)</label>
            <input type="number" id="surge_facteur_demande" min="1" max="5" step="0.1">
        </div>
    </div>
    <button type="button" class="btn" onclick="enregistrerSurge()">Enregistrer la tarification</button>
    <p class="text-muted mt-1">La majoration manuelle s'applique en permanence. La majoration automatique ajoute les heures de pointe et la forte demande (commandes en attente / livreurs en ligne &ge; seuil). Les facteurs ne se cumulent pas : le plus eleve est retenu, plafonne par la majoration maximale.</p>
</div>

<div class="card mt-1">
    <h2>Types de colis &amp; tarifs</h2>
    <p class="text-muted">Tarif de livraison = tarif de base + (tarif/km &times; distance) + supplement express eventuel. Modifiez une valeur pour l'enregistrer aussitot.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Tarif de base</th><th>Tarif / km</th><th>Supplement express</th><th>Actif</th><th></th></tr></thead>
            <tbody id="liste-types"><tr><td colspan="6" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
    <div id="alert-type" class="mt-1"></div>
    <div class="grid grid-4" style="align-items:end;">
        <div class="form-group"><label for="nt-nom">Nouveau type</label><input type="text" id="nt-nom" placeholder="Ex: Meubles"></div>
        <div class="form-group"><label for="nt-base">Tarif de base</label><input type="number" id="nt-base" value="500" min="0"></div>
        <div class="form-group"><label for="nt-km">Tarif / km</label><input type="number" id="nt-km" value="150" min="0"></div>
        <div class="form-group"><label for="nt-exp">Supplement express</label><input type="number" id="nt-exp" value="1000" min="0"></div>
    </div>
    <button type="button" class="btn" onclick="creerType()">Ajouter le type</button>
</div>

<div class="card mt-1">
    <h2>Moyens de transport</h2>
    <p class="text-muted">Le client choisit un moyen de transport a la commande. Le prix de livraison est multiplie par le coefficient (ex: voiture &times;1.5).</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Icone</th><th>Multiplicateur (&times;)</th><th>Actif</th><th></th></tr></thead>
            <tbody id="liste-transport"><tr><td colspan="5" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
    <div id="alert-transport" class="mt-1"></div>
    <div class="grid grid-4" style="align-items:end;">
        <div class="form-group"><label for="nm-nom">Nouveau moyen</label><input type="text" id="nm-nom" placeholder="Ex: Camion"></div>
        <div class="form-group"><label for="nm-icone">Icone (texte)</label><input type="text" id="nm-icone" placeholder="camion"></div>
        <div class="form-group"><label for="nm-mult">Multiplicateur</label><input type="number" id="nm-mult" value="1.0" min="0.1" max="10" step="0.1"></div>
        <div class="form-group" style="display:flex;align-items:flex-end;"><button type="button" class="btn btn-block" onclick="creerTransport()">Ajouter</button></div>
    </div>
</div>

<script>
async function chargerParametres() {
    const res = await Api.get('/api/admin/settings.php');
    const p = res.data.parametres;
    document.getElementById('commission_taux_defaut').value = p.commission_taux_defaut ?? 15;
    document.getElementById('app_nom').value = p.app_nom ?? '';
    document.getElementById('ville_defaut').value = p.ville_defaut ?? '';
    document.getElementById('dispatch_offre_secondes').value = p.dispatch_offre_secondes ?? 45;
    document.getElementById('dispatch_rayon_max_km').value = p.dispatch_rayon_max_km ?? 10;
    document.getElementById('dispatch_poids_note').value = p.dispatch_poids_note ?? 0.5;

    document.getElementById('surge_actif').checked = p.surge_actif === '1';
    document.getElementById('surge_auto').checked = p.surge_auto === '1';
    document.getElementById('surge_manuel').value = p.surge_manuel ?? 1;
    document.getElementById('surge_max').value = p.surge_max ?? 2;
    document.getElementById('surge_heures_pointe').value = p.surge_heures_pointe ?? '11-14,18-21';
    document.getElementById('surge_facteur_pointe').value = p.surge_facteur_pointe ?? 1.2;
    document.getElementById('surge_ratio_seuil').value = p.surge_ratio_seuil ?? 2;
    document.getElementById('surge_facteur_demande').value = p.surge_facteur_demande ?? 1.3;
}

async function enregistrerSurge() {
    const alertZone = document.getElementById('alert-zone-4');
    try {
        await Api.post('/api/admin/settings.php', {
            surge_actif: document.getElementById('surge_actif').checked ? '1' : '0',
            surge_auto: document.getElementById('surge_auto').checked ? '1' : '0',
            surge_manuel: document.getElementById('surge_manuel').value,
            surge_max: document.getElementById('surge_max').value,
            surge_heures_pointe: document.getElementById('surge_heures_pointe').value.trim(),
            surge_facteur_pointe: document.getElementById('surge_facteur_pointe').value,
            surge_ratio_seuil: document.getElementById('surge_ratio_seuil').value,
            surge_facteur_demande: document.getElementById('surge_facteur_demande').value,
        });
        alertZone.innerHTML = '<div class="alert alert-succes">Tarification dynamique enregistree.</div>';
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
}

async function enregistrerDispatch() {
    const alertZone = document.getElementById('alert-zone-3');
    try {
        await Api.post('/api/admin/settings.php', {
            dispatch_offre_secondes: document.getElementById('dispatch_offre_secondes').value,
            dispatch_rayon_max_km: document.getElementById('dispatch_rayon_max_km').value,
            dispatch_poids_note: document.getElementById('dispatch_poids_note').value,
        });
        alertZone.innerHTML = '<div class="alert alert-succes">Parametres de dispatch enregistres.</div>';
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
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
    const res = await Api.get('/api/admin/delivery_types_list.php');
    tbody.innerHTML = res.data.types.map(t => `
        <tr>
            <td>${escapeHtml(t.nom)}</td>
            <td><input type="number" value="${t.tarif_base}" data-id="${t.id}" data-champ="tarif_base" style="width:100px;"></td>
            <td><input type="number" value="${t.tarif_km}" data-id="${t.id}" data-champ="tarif_km" style="width:100px;"></td>
            <td><input type="number" value="${t.supplement_express}" data-id="${t.id}" data-champ="supplement_express" style="width:100px;"></td>
            <td><input type="checkbox" ${t.actif == 1 ? 'checked' : ''} data-id="${t.id}" data-champ="actif" style="width:auto;"></td>
            <td><button class="btn btn-ghost btn-sm" data-del="${t.id}">Supprimer</button></td>
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
    tbody.querySelectorAll('button[data-del]').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Supprimer ce type de colis ?')) { return; }
            try {
                await Api.post('/api/admin/delivery_types_delete.php', { id: btn.dataset.del });
                chargerTypes();
            } catch (err) {
                document.getElementById('alert-type').innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
            }
        });
    });
}

async function creerType() {
    const zone = document.getElementById('alert-type');
    const nom = document.getElementById('nt-nom').value.trim();
    if (!nom) { zone.innerHTML = '<div class="alert alert-erreur">Indiquez un nom.</div>'; return; }
    try {
        await Api.post('/api/admin/delivery_types_create.php', {
            nom: nom,
            tarif_base: document.getElementById('nt-base').value,
            tarif_km: document.getElementById('nt-km').value,
            supplement_express: document.getElementById('nt-exp').value,
        });
        document.getElementById('nt-nom').value = '';
        zone.innerHTML = '<div class="alert alert-succes">Type ajoute.</div>';
        chargerTypes();
    } catch (err) {
        zone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
}

async function chargerTransport() {
    const tbody = document.getElementById('liste-transport');
    const res = await Api.get('/api/admin/transport_list.php');
    tbody.innerHTML = res.data.moyens.length ? res.data.moyens.map(m => `
        <tr>
            <td><input type="text" value="${escapeHtml(m.nom)}" data-id="${m.id}" data-champ="nom" style="width:130px;"></td>
            <td><input type="text" value="${escapeHtml(m.icone || '')}" data-id="${m.id}" data-champ="icone" style="width:90px;"></td>
            <td><input type="number" value="${m.multiplicateur}" data-id="${m.id}" data-champ="multiplicateur" min="0.1" max="10" step="0.1" style="width:90px;"></td>
            <td><input type="checkbox" ${m.actif == 1 ? 'checked' : ''} data-id="${m.id}" data-champ="actif" style="width:auto;"></td>
            <td><button class="btn btn-ghost btn-sm" data-del="${m.id}">Supprimer</button></td>
        </tr>
    `).join('') : '<tr><td colspan="5" class="text-muted">Aucun moyen de transport.</td></tr>';

    tbody.querySelectorAll('input').forEach(input => {
        input.addEventListener('change', async () => {
            const champ = input.dataset.champ;
            const valeur = champ === 'actif' ? input.checked : input.value;
            try {
                await Api.post('/api/admin/transport_save.php', { id: input.dataset.id, [champ]: valeur });
            } catch (err) {
                document.getElementById('alert-transport').innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
            }
        });
    });
    tbody.querySelectorAll('button[data-del]').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Supprimer ce moyen de transport ?')) { return; }
            try {
                await Api.post('/api/admin/transport_delete.php', { id: btn.dataset.del });
                chargerTransport();
            } catch (err) {
                document.getElementById('alert-transport').innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
            }
        });
    });
}

async function creerTransport() {
    const zone = document.getElementById('alert-transport');
    const nom = document.getElementById('nm-nom').value.trim();
    if (!nom) { zone.innerHTML = '<div class="alert alert-erreur">Indiquez un nom.</div>'; return; }
    try {
        await Api.post('/api/admin/transport_save.php', {
            nom: nom,
            icone: document.getElementById('nm-icone').value.trim(),
            multiplicateur: document.getElementById('nm-mult').value,
        });
        document.getElementById('nm-nom').value = '';
        document.getElementById('nm-icone').value = '';
        zone.innerHTML = '<div class="alert alert-succes">Moyen de transport ajoute.</div>';
        chargerTransport();
    } catch (err) {
        zone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
}

chargerParametres();
chargerTypes();
chargerTransport();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
