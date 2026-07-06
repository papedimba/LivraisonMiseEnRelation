<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Gestion des utilisateurs';
require __DIR__ . '/../includes/header.php';
?>
<h1>Gestion des utilisateurs</h1>

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

<script>
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
            <td>${escapeHtml(u.role)}</td>
            <td>${escapeHtml(u.email)}</td>
            <td>${escapeHtml(u.telephone)}</td>
            <td><span class="tag ${u.statut === 'actif' ? 'tag-succes' : u.statut === 'suspendu' ? 'tag-danger' : 'tag-attente'}">${escapeHtml(u.statut)}</span></td>
            <td>
                ${u.role !== 'admin' ? `
                    <select data-id="${u.id}" class="select-statut">
                        <option value="actif" ${u.statut === 'actif' ? 'selected' : ''}>Actif</option>
                        <option value="suspendu" ${u.statut === 'suspendu' ? 'selected' : ''}>Suspendu</option>
                        <option value="en_attente" ${u.statut === 'en_attente' ? 'selected' : ''}>En attente</option>
                    </select>
                ` : '-'}
            </td>
        </tr>
    `).join('') : '<tr><td colspan="6" class="text-muted">Aucun utilisateur trouve.</td></tr>';

    tbody.querySelectorAll('.select-statut').forEach(sel => {
        sel.addEventListener('change', async () => {
            try {
                await Api.post('/api/admin/users_update_status.php', { user_id: sel.dataset.id, statut: sel.value });
            } catch (err) {
                alert(err.message);
            }
        });
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
