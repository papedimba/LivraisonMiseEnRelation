<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('commercant');

$pageTitle = 'Commandes recues';
require __DIR__ . '/../includes/header.php';
?>
<h1>Commandes recues</h1>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Reference</th><th>Client</th><th>Produits</th><th>Statut</th><th>Montant</th><th>Date</th></tr>
            </thead>
            <tbody id="liste-commandes">
                <tr><td colspan="6" class="text-muted">Chargement...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(async function () {
    const tbody = document.getElementById('liste-commandes');
    try {
        const res = await Api.get('/api/commercant/orders_list.php');
        const commandes = res.data.commandes;
        if (commandes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-muted">Aucune commande recue pour le moment.</td></tr>';
            return;
        }
        tbody.innerHTML = commandes.map(c => `
            <tr>
                <td>${escapeHtml(c.reference)}</td>
                <td>${escapeHtml(c.client_prenom)} ${escapeHtml(c.client_nom)}<br><span class="text-muted">${escapeHtml(c.client_telephone)}</span></td>
                <td>${c.produits.map(p => `${p.quantite}x ${escapeHtml(p.nom)}`).join('<br>') || '-'}</td>
                <td>${badgeStatut(c.statut)}</td>
                <td>${formatMontant(c.montant_estime)}</td>
                <td>${formatDate(c.created_at)}</td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-muted">Erreur de chargement.</td></tr>';
    }
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
