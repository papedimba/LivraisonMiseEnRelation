<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Tableau de bord';
require __DIR__ . '/../includes/header.php';
?>
<h1>Tableau de bord</h1>

<div class="grid grid-4 mb-1">
    <div class="stat-tile"><div class="label">Commandes totales</div><div class="valeur" id="stat-total">-</div></div>
    <div class="stat-tile"><div class="label">En cours</div><div class="valeur" id="stat-en-cours">-</div></div>
    <div class="stat-tile"><div class="label">Chiffre d'affaires</div><div class="valeur" id="stat-ca">-</div></div>
    <div class="stat-tile"><div class="label">Commissions percues</div><div class="valeur" id="stat-commissions">-</div></div>
</div>

<div class="grid grid-3 mb-1">
    <div class="stat-tile"><div class="label">Reclamations ouvertes</div><div class="valeur" id="stat-reclamations">-</div></div>
    <div class="stat-tile"><div class="label">Retraits en attente</div><div class="valeur" id="stat-retraits">-</div></div>
    <div class="stat-tile"><div class="label">Utilisateurs actifs</div><div class="valeur" id="stat-users">-</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Utilisateurs par role</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Role</th><th>Statut</th><th>Nombre</th></tr></thead>
                <tbody id="tbody-users"><tr><td colspan="3" class="text-muted">Chargement...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h2>Meilleurs livreurs</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Livreur</th><th>Courses</th><th>Note</th><th>Solde</th></tr></thead>
                <tbody id="tbody-livreurs"><tr><td colspan="4" class="text-muted">Chargement...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(async function () {
    const res = await Api.get('/api/admin/dashboard.php');
    const d = res.data;

    document.getElementById('stat-total').textContent = d.commandes.total ?? 0;
    document.getElementById('stat-en-cours').textContent = d.commandes.en_cours ?? 0;
    document.getElementById('stat-ca').textContent = formatMontant(d.commandes.chiffre_affaires_total ?? 0);
    document.getElementById('stat-commissions').textContent = formatMontant(d.commandes.commissions_totales ?? 0);
    document.getElementById('stat-reclamations').textContent = d.reclamations_ouvertes;
    document.getElementById('stat-retraits').textContent = `${d.retraits_en_attente.nombre} (${formatMontant(d.retraits_en_attente.montant)})`;

    const totalActifs = d.utilisateurs_par_role.filter(u => u.statut === 'actif').reduce((s, u) => s + Number(u.nb), 0);
    document.getElementById('stat-users').textContent = totalActifs;

    document.getElementById('tbody-users').innerHTML = d.utilisateurs_par_role.map(u => `
        <tr><td>${escapeHtml(u.role)}</td><td>${escapeHtml(u.statut)}</td><td>${u.nb}</td></tr>
    `).join('') || '<tr><td colspan="3" class="text-muted">Aucune donnee.</td></tr>';

    document.getElementById('tbody-livreurs').innerHTML = d.top_livreurs.map(l => `
        <tr><td>${escapeHtml(l.prenom)} ${escapeHtml(l.nom)}</td><td>${l.nombre_courses}</td><td>${l.note_moyenne}/5</td><td>${formatMontant(l.solde)}</td></tr>
    `).join('') || '<tr><td colspan="4" class="text-muted">Aucun livreur.</td></tr>';
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
