<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Validations';
require __DIR__ . '/../includes/header.php';
?>
<h1>Validations en attente</h1>

<div class="card mb-1">
    <h2>Livreurs</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Contact</th><th>Vehicule</th><th>Documents</th><th></th></tr></thead>
            <tbody id="liste-livreurs"><tr><td colspan="5" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Commercants</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Boutique</th><th>Categorie</th><th>Contact</th><th></th></tr></thead>
            <tbody id="liste-commercants"><tr><td colspan="4" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
</div>

<script>
async function chargerLivreurs() {
    const tbody = document.getElementById('liste-livreurs');
    const res = await Api.get('/api/admin/livreurs_pending.php');
    const livreurs = res.data.livreurs;
    function lienDoc(id, type, present, libelle) {
        if (!present) {
            return `<span class="text-muted">${libelle} : —</span>`;
        }
        return `<a href="/api/admin/livreur_document.php?livreur_id=${id}&type=${type}" target="_blank">${libelle}</a>`;
    }

    tbody.innerHTML = livreurs.length ? livreurs.map(l => `
        <tr>
            <td>${escapeHtml(l.prenom)} ${escapeHtml(l.nom)}</td>
            <td>${escapeHtml(l.email)}<br>${escapeHtml(l.telephone)}</td>
            <td>${escapeHtml(l.type_vehicule)}</td>
            <td style="font-size:0.8rem;">
                ${lienDoc(l.id, 'piece_identite', l.a_piece_identite, 'Piece')}<br>
                ${lienDoc(l.id, 'permis', l.a_permis, 'Permis')}<br>
                ${lienDoc(l.id, 'carte_grise', l.a_carte_grise, 'Carte grise')}
            </td>
            <td>
                <button class="btn btn-sm" data-id="${l.id}" data-decision="valide">Valider</button>
                <button class="btn btn-sm btn-danger" data-id="${l.id}" data-decision="rejete">Rejeter</button>
            </td>
        </tr>
    `).join('') : '<tr><td colspan="5" class="text-muted">Aucun livreur en attente.</td></tr>';

    tbody.querySelectorAll('button[data-decision]').forEach(btn => {
        btn.addEventListener('click', async () => {
            let motif = '';
            if (btn.dataset.decision === 'rejete') {
                motif = prompt('Motif du rejet :') || '';
            }
            try {
                await Api.post('/api/admin/livreurs_validate.php', { user_id: btn.dataset.id, decision: btn.dataset.decision, motif });
                chargerLivreurs();
            } catch (err) {
                alert(err.message);
            }
        });
    });
}

async function chargerCommercants() {
    const tbody = document.getElementById('liste-commercants');
    const res = await Api.get('/api/admin/commercants_pending.php');
    const commercants = res.data.commercants;
    tbody.innerHTML = commercants.length ? commercants.map(c => `
        <tr>
            <td>${escapeHtml(c.nom_boutique)}</td>
            <td>${escapeHtml(c.categorie)}</td>
            <td>${escapeHtml(c.email)}<br>${escapeHtml(c.telephone)}</td>
            <td>
                <button class="btn btn-sm" data-id="${c.id}" data-decision="valide">Valider</button>
                <button class="btn btn-sm btn-danger" data-id="${c.id}" data-decision="rejete">Rejeter</button>
            </td>
        </tr>
    `).join('') : '<tr><td colspan="4" class="text-muted">Aucun commercant en attente.</td></tr>';

    tbody.querySelectorAll('button[data-decision]').forEach(btn => {
        btn.addEventListener('click', async () => {
            try {
                await Api.post('/api/admin/commercants_validate.php', { user_id: btn.dataset.id, decision: btn.dataset.decision });
                chargerCommercants();
            } catch (err) {
                alert(err.message);
            }
        });
    });
}

chargerLivreurs();
chargerCommercants();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
