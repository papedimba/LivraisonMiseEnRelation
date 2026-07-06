<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Reclamations';
require __DIR__ . '/../includes/header.php';
?>
<h1>Reclamations</h1>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Utilisateur</th><th>Sujet</th><th>Message</th><th>Statut</th><th></th></tr></thead>
            <tbody id="liste-reclamations"><tr><td colspan="5" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
</div>

<script>
async function chargerReclamations() {
    const tbody = document.getElementById('liste-reclamations');
    const res = await Api.get('/api/admin/complaints_list.php');
    const reclamations = res.data.reclamations;
    tbody.innerHTML = reclamations.length ? reclamations.map(r => `
        <tr>
            <td>${escapeHtml(r.prenom)} ${escapeHtml(r.nom)} (${escapeHtml(r.role)})</td>
            <td>${escapeHtml(r.sujet)}</td>
            <td>${escapeHtml(r.message)}${r.reponse ? `<br><em class="text-muted">Reponse : ${escapeHtml(r.reponse)}</em>` : ''}</td>
            <td>${escapeHtml(r.statut)}</td>
            <td>${r.statut !== 'resolue' && r.statut !== 'fermee' ? `<button class="btn btn-sm" data-id="${r.id}">Repondre</button>` : ''}</td>
        </tr>
    `).join('') : '<tr><td colspan="5" class="text-muted">Aucune reclamation.</td></tr>';

    tbody.querySelectorAll('button[data-id]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const reponse = prompt('Votre reponse :');
            if (!reponse) return;
            try {
                await Api.post('/api/admin/complaints_respond.php', { reclamation_id: btn.dataset.id, reponse, statut: 'resolue' });
                chargerReclamations();
            } catch (err) {
                alert(err.message);
            }
        });
    });
}

chargerReclamations();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
