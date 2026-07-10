<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Tableau de bord';
require __DIR__ . '/../includes/header.php';
?>
<div class="flex-between">
    <h1>Tableau de bord</h1>
    <div class="periode-btns" id="periode">
        <button class="btn btn-ghost btn-sm periode" data-j="7">7 j</button>
        <button class="btn btn-sm periode actif" data-j="30">30 j</button>
        <button class="btn btn-ghost btn-sm periode" data-j="90">90 j</button>
    </div>
</div>

<div class="grid grid-4 mb-1">
    <div class="stat-tile"><div class="label">Chiffre d'affaires</div><div class="valeur" id="a-ca">-</div></div>
    <div class="stat-tile"><div class="label">Commissions</div><div class="valeur" id="a-comm">-</div></div>
    <div class="stat-tile"><div class="label">Commandes livrees</div><div class="valeur" id="a-livrees">-</div></div>
    <div class="stat-tile"><div class="label">Panier moyen</div><div class="valeur" id="a-panier">-</div></div>
</div>
<div class="grid grid-4 mb-1">
    <div class="stat-tile"><div class="label">Taux de livraison</div><div class="valeur" id="a-tx-livr">-</div></div>
    <div class="stat-tile"><div class="label">Taux d'annulation</div><div class="valeur" id="a-tx-annul">-</div></div>
    <div class="stat-tile"><div class="label">Acceptation dispatch</div><div class="valeur" id="a-tx-accept">-</div></div>
    <div class="stat-tile"><div class="label">Delai livraison moyen</div><div class="valeur" id="a-delai">-</div></div>
</div>

<div class="grid grid-2 mb-1">
    <div class="card">
        <h2 style="margin-top:0;">Commandes par jour</h2>
        <div id="chart-commandes"></div>
    </div>
    <div class="card">
        <h2 style="margin-top:0;">Chiffre d'affaires par jour</h2>
        <div id="chart-ca"></div>
    </div>
</div>

<div class="grid grid-2 mb-1">
    <div class="card">
        <h2 style="margin-top:0;">Repartition par mode de paiement</h2>
        <div id="rep-paiement"><p class="text-muted">Chargement...</p></div>
    </div>
    <div class="card">
        <h2 style="margin-top:0;">Repartition par type de livraison</h2>
        <div id="rep-type"><p class="text-muted">Chargement...</p></div>
    </div>
</div>

<div class="grid grid-3 mb-1">
    <div class="stat-tile"><div class="label">Reclamations ouvertes</div><div class="valeur" id="stat-reclamations">-</div></div>
    <div class="stat-tile"><div class="label">Retraits en attente</div><div class="valeur" id="stat-retraits">-</div></div>
    <div class="stat-tile"><div class="label">Utilisateurs actifs</div><div class="valeur" id="stat-users">-</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 style="margin-top:0;">Meilleurs livreurs <span class="text-muted" id="lbl-periode" style="font-size:0.8rem;"></span></h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Livreur</th><th>Livrees</th><th>CA genere</th><th>Note</th></tr></thead>
                <tbody id="tbody-livreurs"><tr><td colspan="4" class="text-muted">Chargement...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h2 style="margin-top:0;">Utilisateurs par role</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Role</th><th>Statut</th><th>Nombre</th></tr></thead>
                <tbody id="tbody-users"><tr><td colspan="3" class="text-muted">Chargement...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<div class="chart-tip" id="chart-tip"></div>

<script>
let periodeJours = 30;
const tip = document.getElementById('chart-tip');

function montrerTip(html, evt) {
    tip.innerHTML = html;
    tip.style.opacity = '1';
    tip.style.left = (evt.clientX + 12) + 'px';
    tip.style.top = (evt.clientY - 10) + 'px';
}
function cacherTip() { tip.style.opacity = '0'; }

// Graphique en barres verticales (serie temporelle, une seule mesure/teinte).
function dessinerBarres(elId, serie, accesseur, formatValeur) {
    const el = document.getElementById(elId);
    const W = 720, H = 210, padB = 26, padL = 6, padT = 12;
    const n = serie.length;
    const max = Math.max(1, ...serie.map(accesseur));
    const plotH = H - padB - padT;
    const plotW = W - padL * 2;
    const pas = plotW / n;
    const largeurBarre = Math.max(2, pas - 2); // 2px de gouttiere entre barres

    // Lignes de grille horizontales (recessives) a 0/50/100 %.
    let grille = '';
    for (let k = 0; k <= 2; k++) {
        const y = padT + plotH * (k / 2);
        grille += `<line class="grille" x1="${padL}" y1="${y.toFixed(1)}" x2="${W - padL}" y2="${y.toFixed(1)}"/>`;
    }

    // Etiquettes d'axe X : environ 6 dates reparties.
    const pasLabel = Math.max(1, Math.round(n / 6));
    let labels = '';
    serie.forEach((d, i) => {
        if (i % pasLabel === 0 || i === n - 1) {
            const x = padL + pas * i + largeurBarre / 2;
            const [, m, j] = d.jour.split('-');
            labels += `<text class="axe-txt" x="${x.toFixed(1)}" y="${H - 8}" text-anchor="middle">${j}/${m}</text>`;
        }
    });

    let barres = '';
    serie.forEach((d, i) => {
        const v = accesseur(d);
        const h = (v / max) * plotH;
        const x = padL + pas * i + (pas - largeurBarre) / 2;
        const y = padT + plotH - h;
        barres += `<rect class="barre" x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${largeurBarre.toFixed(1)}" height="${Math.max(0, h).toFixed(1)}" rx="3"
            data-info="${d.jour} — ${formatValeur(v)}"></rect>`;
    });

    el.innerHTML = `<svg class="chart-svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="Graphique par jour">
        <text class="axe-txt" x="${padL}" y="${padT - 2}">max ${formatValeur(max)}</text>
        ${grille}${barres}${labels}
    </svg>`;

    el.querySelectorAll('.barre').forEach(r => {
        r.addEventListener('mousemove', (e) => montrerTip(r.dataset.info, e));
        r.addEventListener('mouseleave', cacherTip);
    });
}

// Barres horizontales pour une repartition (magnitude, teinte unique).
function dessinerRepartition(elId, items, labelFn) {
    const el = document.getElementById(elId);
    if (!items.length) { el.innerHTML = '<p class="text-muted">Aucune donnee sur la periode.</p>'; return; }
    const max = Math.max(1, ...items.map(i => Number(i.nb)));
    const total = items.reduce((s, i) => s + Number(i.nb), 0);
    el.innerHTML = items.map(i => {
        const pct = (Number(i.nb) / max) * 100;
        const part = total ? Math.round(Number(i.nb) / total * 100) : 0;
        return `<div class="hbar-row">
            <div class="hbar-label" title="${escapeHtml(labelFn(i.cle))}">${escapeHtml(labelFn(i.cle))}</div>
            <div class="hbar-track"><div class="hbar-fill" style="width:${pct.toFixed(1)}%"></div></div>
            <div class="hbar-val">${i.nb} <span class="text-muted" style="font-weight:400;">(${part}%)</span></div>
        </div>`;
    }).join('');
}

const LABELS_PAIEMENT = {
    especes: 'Especes', orange_money: 'Orange Money', mtn_money: 'MTN Money',
    moov_money: 'Moov Money', wave: 'Wave',
};

async function chargerAnalytics() {
    const res = await Api.get('/api/admin/analytics.php?jours=' + periodeJours);
    const d = res.data;
    const r = d.resume;

    document.getElementById('a-ca').textContent = formatMontant(r.chiffre_affaires);
    document.getElementById('a-comm').textContent = formatMontant(r.commissions);
    document.getElementById('a-livrees').textContent = r.livrees + ' / ' + r.total;
    document.getElementById('a-panier').textContent = formatMontant(r.panier_moyen);
    document.getElementById('a-tx-livr').textContent = r.taux_livraison + ' %';
    document.getElementById('a-tx-annul').textContent = r.taux_annulation + ' %';
    document.getElementById('a-tx-accept').textContent = r.taux_acceptation + ' %';
    document.getElementById('a-delai').textContent = r.delai_livraison_min + ' min';
    document.getElementById('lbl-periode').textContent = '(' + d.jours + ' derniers jours)';

    dessinerBarres('chart-commandes', d.serie, x => x.nb, v => v + ' cmd');
    dessinerBarres('chart-ca', d.serie, x => Number(x.ca), v => formatMontant(v));
    dessinerRepartition('rep-paiement', d.par_paiement, c => LABELS_PAIEMENT[c] || c);
    dessinerRepartition('rep-type', d.par_type, c => c);

    document.getElementById('tbody-livreurs').innerHTML = d.top_livreurs.length ? d.top_livreurs.map(l => `
        <tr><td>${escapeHtml(l.prenom)} ${escapeHtml(l.nom)}</td><td>${l.livrees}</td><td>${formatMontant(l.ca)}</td><td>${l.note_moyenne ? Number(l.note_moyenne).toFixed(1) + '/5' : '-'}</td></tr>
    `).join('') : '<tr><td colspan="4" class="text-muted">Aucune course livree sur la periode.</td></tr>';
}

async function chargerOperationnel() {
    const res = await Api.get('/api/admin/dashboard.php');
    const d = res.data;
    document.getElementById('stat-reclamations').textContent = d.reclamations_ouvertes;
    document.getElementById('stat-retraits').textContent = `${d.retraits_en_attente.nombre} (${formatMontant(d.retraits_en_attente.montant)})`;
    const totalActifs = d.utilisateurs_par_role.filter(u => u.statut === 'actif').reduce((s, u) => s + Number(u.nb), 0);
    document.getElementById('stat-users').textContent = totalActifs;
    document.getElementById('tbody-users').innerHTML = d.utilisateurs_par_role.map(u => `
        <tr><td>${escapeHtml(u.role)}</td><td>${escapeHtml(u.statut)}</td><td>${u.nb}</td></tr>
    `).join('') || '<tr><td colspan="3" class="text-muted">Aucune donnee.</td></tr>';
}

document.querySelectorAll('.periode').forEach(btn => {
    btn.addEventListener('click', () => {
        periodeJours = Number(btn.dataset.j);
        document.querySelectorAll('.periode').forEach(b => {
            b.classList.toggle('actif', b === btn);
            b.classList.toggle('btn-ghost', b !== btn);
        });
        chargerAnalytics();
    });
});

chargerAnalytics();
chargerOperationnel();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
