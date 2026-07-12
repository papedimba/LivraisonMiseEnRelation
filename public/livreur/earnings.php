<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('livreur');

$pageTitle = 'Mes gains';
require __DIR__ . '/../includes/header.php';
?>
<h1>Mes gains</h1>

<div id="alerte-dette"></div>

<div class="grid grid-4 mb-1">
    <div class="stat-tile"><div class="label">Solde disponible</div><div class="valeur" id="stat-solde">-</div></div>
    <div class="stat-tile"><div class="label">Gains du jour</div><div class="valeur" id="stat-jour">-</div></div>
    <div class="stat-tile"><div class="label">Gains de la semaine</div><div class="valeur" id="stat-semaine">-</div></div>
    <div class="stat-tile"><div class="label">Gains du mois</div><div class="valeur" id="stat-mois">-</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Note moyenne</h2>
        <div class="valeur" id="stat-note" style="font-size:1.8rem;">-</div>
        <p class="text-muted" id="stat-courses"></p>
    </div>
    <div class="card">
        <h2>Demander un retrait</h2>
        <div id="alert-zone"></div>
        <form id="withdraw-form">
            <div class="form-group">
                <label for="montant">Montant (FCFA)</label>
                <input type="number" id="montant" min="1" required>
            </div>
            <div class="form-group">
                <label for="methode">Methode de reception</label>
                <select id="methode">
                    <option value="orange_money">Orange Money</option>
                    <option value="mtn_money">MTN Mobile Money</option>
                    <option value="moov_money">Moov Money</option>
                    <option value="wave">Wave</option>
                </select>
            </div>
            <div class="form-group">
                <label for="numero_reception">Numero de reception</label>
                <input type="text" id="numero_reception" required placeholder="07 00 00 00 00">
            </div>
            <button type="submit" class="btn btn-block">Demander le retrait</button>
        </form>
    </div>
</div>

<script>
async function chargerGains() {
    const res = await Api.get('/api/livreur/earnings.php');
    const d = res.data;
    document.getElementById('stat-solde').textContent = formatMontant(d.solde_disponible);
    document.getElementById('stat-jour').textContent = formatMontant(d.gains_jour);
    document.getElementById('stat-semaine').textContent = formatMontant(d.gains_semaine);
    document.getElementById('stat-mois').textContent = formatMontant(d.gains_mois);
    document.getElementById('stat-note').textContent = d.note_moyenne + ' / 5';
    document.getElementById('stat-courses').textContent = d.nombre_courses + ' course(s) effectuee(s)';

    // Dette de commission sur les courses payees en especes (encaissees en
    // main propre) : reduit le solde retirable tant qu'elle n'est pas reglee.
    const alerte = document.getElementById('alerte-dette');
    alerte.innerHTML = d.dette_commission > 0
        ? `<div class="alert alert-info mb-1">Vous devez <strong>${formatMontant(d.dette_commission)}</strong> de commission sur vos courses payees en especes.
            Reglez ce montant aupres de votre agence CityHub : il reduit votre solde retirable jusqu'a son reglement.</div>`
        : '';
}

document.getElementById('withdraw-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertZone = document.getElementById('alert-zone');
    try {
        await Api.post('/api/livreur/withdraw.php', {
            montant: document.getElementById('montant').value,
            methode: document.getElementById('methode').value,
            numero_reception: document.getElementById('numero_reception').value.trim(),
        });
        alertZone.innerHTML = '<div class="alert alert-succes">Demande de retrait envoyee.</div>';
        document.getElementById('withdraw-form').reset();
        chargerGains();
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

chargerGains();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
