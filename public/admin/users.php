<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Gestion des utilisateurs';
require __DIR__ . '/../includes/header.php';
?>
<div class="flex-between">
    <h1>Gestion des utilisateurs</h1>
    <button id="btn-nouveau" class="btn">➕ Creer un utilisateur</button>
</div>

<div class="card mb-1">
    <div class="grid grid-3">
        <div class="form-group">
            <label for="filtre-role">Role</label>
            <select id="filtre-role">
                <option value="">Tous</option>
                <option value="client">Client</option>
                <option value="livreur">Livreur</option>
                <option value="commercant">Commercant</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div class="form-group">
            <label for="filtre-statut">Statut</label>
            <select id="filtre-statut">
                <option value="">Tous</option>
                <option value="actif">Actif</option>
                <option value="suspendu">Suspendu</option>
                <option value="en_attente">En attente</option>
            </select>
        </div>
        <div class="form-group">
            <label for="filtre-q">Recherche</label>
            <input type="text" id="filtre-q" placeholder="Nom, email, telephone...">
        </div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Role</th><th>Email</th><th>Telephone</th><th>Statut</th><th></th></tr></thead>
            <tbody id="liste-utilisateurs"><tr><td colspan="6" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- Modale creation / edition -->
<div id="modal-user" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="modal-titre">Creer un utilisateur</h2>
            <button type="button" class="modal-close" id="modal-fermer">&times;</button>
        </div>
        <div id="modal-alert"></div>
        <input type="hidden" id="f-user-id">
        <div class="grid grid-2">
            <div class="form-group">
                <label for="f-prenom">Prenom</label>
                <input type="text" id="f-prenom">
            </div>
            <div class="form-group">
                <label for="f-nom">Nom</label>
                <input type="text" id="f-nom">
            </div>
        </div>
        <div class="grid grid-2">
            <div class="form-group">
                <label for="f-email">Email</label>
                <input type="email" id="f-email">
            </div>
            <div class="form-group">
                <label for="f-telephone">Telephone</label>
                <input type="text" id="f-telephone" placeholder="07 00 00 00 00">
            </div>
        </div>
        <div class="grid grid-2">
            <div class="form-group">
                <label for="f-role">Role (droits)</label>
                <select id="f-role">
                    <option value="client">Client</option>
                    <option value="livreur">Livreur</option>
                    <option value="commercant">Commercant</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>
            <div class="form-group">
                <label for="f-statut">Statut</label>
                <select id="f-statut">
                    <option value="actif">Actif</option>
                    <option value="suspendu">Suspendu</option>
                    <option value="en_attente">En attente</option>
                </select>
            </div>
        </div>

        <!-- Champs livreur -->
        <div id="bloc-livreur" class="hidden">
            <div class="form-group">
                <label for="f-vehicule">Type de vehicule</label>
                <select id="f-vehicule">
                    <option value="moto">Moto</option>
                    <option value="velo">Velo</option>
                    <option value="voiture">Voiture</option>
                    <option value="tricycle">Tricycle</option>
                    <option value="a_pied">A pied</option>
                </select>
            </div>
        </div>

        <!-- Champs commercant -->
        <div id="bloc-commercant" class="hidden">
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="f-boutique">Nom de la boutique</label>
                    <input type="text" id="f-boutique">
                </div>
                <div class="form-group">
                    <label for="f-categorie">Categorie</label>
                    <select id="f-categorie">
                        <option value="restaurant">Restaurant</option>
                        <option value="maquis">Maquis</option>
                        <option value="supermarche">Supermarche</option>
                        <option value="pharmacie">Pharmacie</option>
                        <option value="boutique">Boutique</option>
                        <option value="fleuriste">Fleuriste</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="f-password" id="label-password">Mot de passe</label>
            <input type="password" id="f-password" placeholder="Au moins 8 caracteres">
            <div class="text-muted" id="hint-password" style="font-size:0.82rem;"></div>
        </div>

        <div class="flex" style="margin-top:0.5rem;">
            <button type="button" id="f-submit" class="btn">Enregistrer</button>
            <button type="button" id="f-annuler" class="btn btn-ghost">Annuler</button>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('modal-user');
const modalAlert = document.getElementById('modal-alert');
let modeEdition = false;

function majChampsRole() {
    const role = document.getElementById('f-role').value;
    document.getElementById('bloc-livreur').classList.toggle('hidden', role !== 'livreur');
    document.getElementById('bloc-commercant').classList.toggle('hidden', role !== 'commercant');
}
document.getElementById('f-role').addEventListener('change', majChampsRole);

function ouvrirModal(edition) {
    modeEdition = edition;
    modalAlert.innerHTML = '';
    document.getElementById('modal-titre').textContent = edition ? 'Modifier l\'utilisateur' : 'Creer un utilisateur';
    document.getElementById('label-password').textContent = edition ? 'Nouveau mot de passe' : 'Mot de passe';
    document.getElementById('hint-password').textContent = edition ? 'Laissez vide pour ne pas le changer.' : '';
    modal.classList.remove('hidden');
}
function fermerModal() { modal.classList.add('hidden'); }

function reinitFormulaire() {
    ['f-user-id', 'f-prenom', 'f-nom', 'f-email', 'f-telephone', 'f-boutique', 'f-password'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('f-role').value = 'client';
    document.getElementById('f-statut').value = 'actif';
    document.getElementById('f-vehicule').value = 'moto';
    document.getElementById('f-categorie').value = 'restaurant';
    majChampsRole();
}

document.getElementById('btn-nouveau').addEventListener('click', () => { reinitFormulaire(); ouvrirModal(false); });
document.getElementById('modal-fermer').addEventListener('click', fermerModal);
document.getElementById('f-annuler').addEventListener('click', fermerModal);
modal.addEventListener('click', (e) => { if (e.target === modal) fermerModal(); });

async function editerUtilisateur(id) {
    reinitFormulaire();
    try {
        const res = await Api.get('/api/admin/user_get.php?user_id=' + encodeURIComponent(id));
        const u = res.data.utilisateur;
        document.getElementById('f-user-id').value = u.id;
        document.getElementById('f-prenom').value = u.prenom;
        document.getElementById('f-nom').value = u.nom;
        document.getElementById('f-email').value = u.email;
        document.getElementById('f-telephone').value = u.telephone;
        document.getElementById('f-role').value = u.role;
        document.getElementById('f-statut').value = u.statut;
        if (u.livreur) { document.getElementById('f-vehicule').value = u.livreur.type_vehicule; }
        if (u.commercant) {
            document.getElementById('f-boutique').value = u.commercant.nom_boutique;
            document.getElementById('f-categorie').value = u.commercant.categorie;
        }
        majChampsRole();
        ouvrirModal(true);
    } catch (err) {
        alert(err.message);
    }
}

document.getElementById('f-submit').addEventListener('click', async () => {
    const payload = {
        prenom: document.getElementById('f-prenom').value.trim(),
        nom: document.getElementById('f-nom').value.trim(),
        email: document.getElementById('f-email').value.trim(),
        telephone: document.getElementById('f-telephone').value.trim(),
        role: document.getElementById('f-role').value,
        statut: document.getElementById('f-statut').value,
        type_vehicule: document.getElementById('f-vehicule').value,
        nom_boutique: document.getElementById('f-boutique').value.trim(),
        categorie: document.getElementById('f-categorie').value,
    };
    const password = document.getElementById('f-password').value;
    if (password) { payload.password = password; }

    try {
        if (modeEdition) {
            payload.user_id = document.getElementById('f-user-id').value;
            await Api.post('/api/admin/users_update.php', payload);
        } else {
            await Api.post('/api/admin/users_create.php', payload);
        }
        fermerModal();
        chargerUtilisateurs();
    } catch (err) {
        modalAlert.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

async function chargerUtilisateurs() {
    const params = new URLSearchParams({
        role: document.getElementById('filtre-role').value,
        statut: document.getElementById('filtre-statut').value,
        q: document.getElementById('filtre-q').value,
    });
    const tbody = document.getElementById('liste-utilisateurs');
    const res = await Api.get('/api/admin/users_list.php?' + params.toString());
    const utilisateurs = res.data.utilisateurs;
    tbody.innerHTML = utilisateurs.length ? utilisateurs.map(u => `
        <tr>
            <td>${escapeHtml(u.prenom)} ${escapeHtml(u.nom)}</td>
            <td><span class="tag tag-info">${escapeHtml(u.role)}</span></td>
            <td>${escapeHtml(u.email)}</td>
            <td>${escapeHtml(u.telephone)}</td>
            <td><span class="tag ${u.statut === 'actif' ? 'tag-succes' : u.statut === 'suspendu' ? 'tag-danger' : 'tag-attente'}">${escapeHtml(u.statut)}</span></td>
            <td><button class="btn btn-ghost btn-sm" data-edit="${u.id}">Editer</button></td>
        </tr>
    `).join('') : '<tr><td colspan="6" class="text-muted">Aucun utilisateur trouve.</td></tr>';

    tbody.querySelectorAll('button[data-edit]').forEach(btn => {
        btn.addEventListener('click', () => editerUtilisateur(btn.dataset.edit));
    });
}

['filtre-role', 'filtre-statut'].forEach(id => document.getElementById(id).addEventListener('change', chargerUtilisateurs));
document.getElementById('filtre-q').addEventListener('input', () => {
    clearTimeout(window._t);
    window._t = setTimeout(chargerUtilisateurs, 400);
});

chargerUtilisateurs();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
