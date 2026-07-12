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

<div class="grid grid-2 mb-1 hidden" id="carte-dette">
    <div class="card">
        <h2>Regler ma dette de commission</h2>
        <p class="text-muted">Paiement Mobile Money instantane : la dette est reduite des confirmation. Vous pouvez aussi la regler en especes aupres de votre agence.</p>
        <div id="dette-alert-zone"></div>
        <div id="dette-etat"></div>
        <form id="dette-form">
            <div class="form-group">
                <label for="dette-montant-form">Montant a regler (FCFA)</label>
                <input type="number" id="dette-montant-form" min="1" required>
            </div>
            <div class="form-group">
                <label for="dette-methode">Methode de paiement</label>
                <select id="dette-methode">
                    <option value="orange_money">Orange Money</option>
                    <option value="mtn_money">MTN Mobile Money</option>
                    <option value="moov_money">Moov Money</option>
                    <option value="wave">Wave</option>
                </select>
            </div>
            <div class="form-group">
                <label for="dette-numero">Numero de paiement</label>
                <input type="text" id="dette-numero" required placeholder="07 00 00 00 00">
            </div>
            <button type="submit" class="btn btn-block">Payer maintenant</button>
        </form>
    </div>
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
            Payez en Mobile Money ci-dessous, ou reglez aupres de votre agence CityHub.</div>`
        : '';

    document.getElementById('carte-dette').classList.toggle('hidden', d.dette_commission <= 0);
    if (d.dette_commission > 0) {
        document.getElementById('dette-montant-form').value = d.dette_commission;
        document.getElementById('dette-montant-form').max = d.dette_commission;
    }
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

// --- Reglement en libre-service de la dette de commission (Mobile Money) ----
document.getElementById('dette-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertZone = document.getElementById('dette-alert-zone');
    const etatZone = document.getElementById('dette-etat');
    alertZone.innerHTML = '';
    etatZone.innerHTML = '';
    try {
        const res = await Api.post('/api/livreur/dette_payer.php', {
            montant: document.getElementById('dette-montant-form').value,
            methode: document.getElementById('dette-methode').value,
            numero_paiement: document.getElementById('dette-numero').value.trim(),
        });
        const d = res.data;
        if (d.redirect_url) {
            // Orange Money / Wave : redirection vers la page de paiement operateur.
            window.location.href = d.redirect_url;
            return;
        }
        if (d.statut === 'reussi') {
            etatZone.innerHTML = '<div class="alert alert-succes">Paiement confirme, votre dette a ete mise a jour.</div>';
            document.getElementById('dette-form').reset();
            chargerGains();
            return;
        }
        if (d.statut === 'echec') {
            alertZone.innerHTML = '<div class="alert alert-erreur">Le paiement a echoue. Reessayez.</div>';
            return;
        }
        // En attente (MTN/Moov : validation par code USSD sur le telephone).
        etatZone.innerHTML = `<div class="alert alert-info">${escapeHtml(d.instructions || 'Paiement en attente de confirmation...')}</div>`;
        attendreConfirmationDette(d.reference);
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

// Poll (comme pour le paiement d'une commande) jusqu'a confirmation de
// l'operateur ou expiration (~1 min).
async function attendreConfirmationDette(reference, tentative = 0) {
    if (tentative >= 20) { return; }
    try {
        const res = await Api.get('/api/livreur/dette_status.php?reference=' + encodeURIComponent(reference));
        const statut = res.data.statut;
        const etatZone = document.getElementById('dette-etat');
        if (statut === 'reussi') {
            etatZone.innerHTML = '<div class="alert alert-succes">Paiement confirme, votre dette a ete mise a jour.</div>';
            document.getElementById('dette-form').reset();
            chargerGains();
            return;
        }
        if (statut === 'echec') {
            etatZone.innerHTML = '<div class="alert alert-erreur">Le paiement a echoue. Reessayez.</div>';
            return;
        }
    } catch (e) { /* on reessaie */ }
    setTimeout(() => attendreConfirmationDette(reference, tentative + 1), 3000);
}

chargerGains();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
