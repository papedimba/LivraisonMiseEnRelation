<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Commandes';
require __DIR__ . '/../includes/header.php';
?>
<h1>Commandes</h1>

<div class="card mb-1">
    <div class="grid grid-2">
        <div class="form-group">
            <label for="filtre-statut">Statut</label>
            <select id="filtre-statut">
                <option value="">Tous</option>
                <option value="en_attente">En attente</option>
                <option value="acceptee">Acceptee</option>
                <option value="recuperee">Recuperee</option>
                <option value="en_cours">En cours</option>
                <option value="livree">Livree</option>
                <option value="annulee">Annulee</option>
            </select>
        </div>
        <div class="form-group">
            <label for="filtre-reference">Reference</label>
            <input type="text" id="filtre-reference" placeholder="CMD-...">
        </div>
    </div>
</div>

<div class="card mb-1">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>Client</th><th>Livreur</th><th>Boutique</th><th>Statut</th><th>Montant</th><th>Date</th></tr></thead>
            <tbody id="liste-commandes"><tr><td colspan="7" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Retraits en attente de traitement</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Livreur</th><th>Montant</th><th>Methode</th><th>Numero</th><th></th></tr></thead>
            <tbody id="liste-retraits"><tr><td colspan="5" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
</div>

<script>
async function chargerCommandes() {
    const params = new URLSearchParams({
        statut: document.getElementById('filtre-statut').value,
        reference: document.getElementById('filtre-reference').value,
    });
    const tbody = document.getElementById('liste-commandes');
    const res = await Api.get('/api/admin/orders_list.php?' + params.toString());
    const commandes = res.data.commandes;
    tbody.innerHTML = commandes.length ? commandes.map(c => `
        <tr>
            <td>${escapeHtml(c.reference)}</td>
            <td>${escapeHtml(c.client_prenom)} ${escapeHtml(c.client_nom)}</td>
            <td>${c.livreur_nom ? escapeHtml(c.livreur_prenom) + ' ' + escapeHtml(c.livreur_nom) : '-'}</td>
            <td>${escapeHtml(c.nom_boutique || '-')}</td>
            <td>${badgeStatut(c.statut)}</td>
            <td>${formatMontant(c.montant_estime)}</td>
            <td>${formatDate(c.created_at)}</td>
        </tr>
    `).join('') : '<tr><td colspan="7" class="text-muted">Aucune commande trouvee.</td></tr>';
}

async function chargerRetraits() {
    const tbody = document.getElementById('liste-retraits');
    const res = await Api.get('/api/admin/withdrawals_list.php?statut=en_attente');
    const retraits = res.data.retraits;
    tbody.innerHTML = retraits.length ? retraits.map(r => `
        <tr>
            <td>${escapeHtml(r.prenom)} ${escapeHtml(r.nom)}</td>
            <td>${formatMontant(r.montant)}</td>
            <td>${escapeHtml(r.methode)}</td>
            <td>${escapeHtml(r.numero_reception)}</td>
            <td>
                <button class="btn btn-sm" data-id="${r.id}" data-decision="traite">Traiter</button>
                <button class="btn btn-sm btn-danger" data-id="${r.id}" data-decision="rejete">Rejeter</button>
            </td>
        </tr>
    `).join('') : '<tr><td colspan="5" class="text-muted">Aucun retrait en attente.</td></tr>';

    tbody.querySelectorAll('button[data-decision]').forEach(btn => {
        btn.addEventListener('click', async () => {
            let reference = '';
            if (btn.dataset.decision === 'traite') {
                reference = prompt('Reference de la transaction (optionnel) :') || '';
            }
            try {
                await Api.post('/api/admin/withdrawals_process.php', { retrait_id: btn.dataset.id, decision: btn.dataset.decision, reference });
                chargerRetraits();
            } catch (err) {
                alert(err.message);
            }
        });
    });
}

document.getElementById('filtre-statut').addEventListener('change', chargerCommandes);
document.getElementById('filtre-reference').addEventListener('input', () => {
    clearTimeout(window._t);
    window._t = setTimeout(chargerCommandes, 400);
});

chargerCommandes();
chargerRetraits();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
