<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client');

$pageTitle = 'Historique de mes commandes';
require __DIR__ . '/../includes/header.php';
?>
<h1>Historique de mes commandes</h1>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Reference</th><th>Type</th><th>Statut</th><th>Montant</th><th>Date</th><th></th></tr>
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
        const res = await Api.get('/api/client/orders_list.php');
        const commandes = res.data.commandes;
        if (commandes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-muted">Aucune commande pour le moment.</td></tr>';
            return;
        }
        tbody.innerHTML = commandes.map(c => `
            <tr>
                <td>${escapeHtml(c.reference)}</td>
                <td>${escapeHtml(c.type_nom)}</td>
                <td>${badgeStatut(c.statut)}</td>
                <td>${formatMontant(c.montant_estime)}</td>
                <td>${formatDate(c.created_at)}</td>
                <td><a href="/client/track.php?ref=${encodeURIComponent(c.reference)}">Details</a></td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-muted">Erreur de chargement.</td></tr>';
    }
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
