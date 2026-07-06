<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client');

$pageTitle = 'Mon espace client';
require __DIR__ . '/../includes/header.php';
?>
<h1>Bonjour <?= htmlspecialchars(explode(' ', $_SESSION['nom_complet'])[0]) ?> 👋</h1>
<p class="subtitle">Que souhaitez-vous faire aujourd'hui ?</p>

<div class="grid grid-3 mb-1">
    <a class="card" href="/client/new-order.php">
        <h2>➕ Nouvelle commande</h2>
        <p class="text-muted">Repas, courses, colis, medicaments, documents...</p>
    </a>
    <a class="card" href="/client/history.php">
        <h2>🕒 Historique</h2>
        <p class="text-muted">Consultez et suivez vos commandes passees.</p>
    </a>
    <a class="card" href="/client/support.php">
        <h2>💬 Assistance</h2>
        <p class="text-muted">Discutez avec notre assistant ou envoyez une reclamation.</p>
    </a>
</div>

<div class="card">
    <div class="flex-between">
        <h2>Commandes en cours</h2>
        <a href="/client/history.php" class="text-muted">Voir tout &rarr;</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Reference</th><th>Type</th><th>Statut</th><th>Montant</th><th></th></tr>
            </thead>
            <tbody id="commandes-en-cours">
                <tr><td colspan="5" class="text-muted">Chargement...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(async function () {
    const tbody = document.getElementById('commandes-en-cours');
    try {
        const res = await Api.get('/api/client/orders_list.php');
        const enCours = res.data.commandes.filter(c => !['livree', 'annulee'].includes(c.statut));
        if (enCours.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Aucune commande en cours.</td></tr>';
            return;
        }
        tbody.innerHTML = enCours.map(c => `
            <tr>
                <td>${escapeHtml(c.reference)}</td>
                <td>${escapeHtml(c.type_nom)}</td>
                <td>${badgeStatut(c.statut)}</td>
                <td>${formatMontant(c.montant_estime)}</td>
                <td><a href="/client/track.php?ref=${encodeURIComponent(c.reference)}">Suivre</a></td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Erreur de chargement.</td></tr>';
    }
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
