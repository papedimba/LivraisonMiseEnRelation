<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('commercant');

$pageTitle = 'Statistiques de ventes';
require __DIR__ . '/../includes/header.php';
?>
<h1>Statistiques de ventes</h1>

<div class="grid grid-4 mb-1">
    <div class="tile-hero vert">
        <div class="entete">
            <div class="entete-libelle"><span class="icone-rond">💵</span> Chiffre d'affaires</div>
        </div>
        <div class="montant" id="stat-ca">-</div>
        <div class="sous-texte">Toutes commandes livrees</div>
    </div>
    <div class="stat-tile"><div><div class="label">Total commandes</div><div class="valeur" id="stat-total">-</div></div><div class="stat-icone icone-vert">🧾</div></div>
    <div class="stat-tile"><div><div class="label">Livrees</div><div class="valeur" id="stat-livrees">-</div></div><div class="stat-icone icone-vert">📦</div></div>
    <div class="stat-tile"><div><div class="label">Annulees</div><div class="valeur" id="stat-annulees">-</div></div><div class="stat-icone icone-rouge">🚫</div></div>
</div>

<div class="card">
    <h2>Produits les plus vendus</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Produit</th><th>Ventes</th><th>Quantite vendue</th></tr></thead>
            <tbody id="top-produits"><tr><td colspan="3" class="text-muted">Chargement...</td></tr></tbody>
        </table>
    </div>
</div>

<script>
(async function () {
    const res = await Api.get('/api/commercant/stats.php');
    const g = res.data.global;
    document.getElementById('stat-total').textContent = g.total_commandes;
    document.getElementById('stat-livrees').textContent = g.commandes_livrees;
    document.getElementById('stat-annulees').textContent = g.commandes_annulees;
    document.getElementById('stat-ca').textContent = formatMontant(g.chiffre_affaires);

    const tbody = document.getElementById('top-produits');
    const top = res.data.top_produits;
    tbody.innerHTML = top.length
        ? top.map(p => `<tr><td>${escapeHtml(p.nom)}</td><td>${p.nb_ventes}</td><td>${p.quantite_vendue}</td></tr>`).join('')
        : '<tr><td colspan="3" class="text-muted">Aucune vente pour le moment.</td></tr>';
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
