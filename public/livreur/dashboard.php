<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('livreur');

$pageTitle = 'Courses disponibles';
require __DIR__ . '/../includes/header.php';
?>
<div class="flex-between">
    <div>
        <h1>Courses disponibles</h1>
        <p class="subtitle">Acceptez une course pour commencer.</p>
    </div>
    <div class="flex">
        <label class="text-muted">Disponibilite</label>
        <select id="disponibilite">
            <option value="hors_ligne">Hors ligne</option>
            <option value="en_ligne">En ligne</option>
        </select>
    </div>
</div>

<div id="alert-zone"></div>

<div id="course-active" class="card hidden mb-1"></div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Reference</th><th>Type</th><th>Depart</th><th>Arrivee</th><th>Distance</th><th>Montant</th><th></th></tr>
            </thead>
            <tbody id="liste-commandes">
                <tr><td colspan="7" class="text-muted">Chargement...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const alertZone = document.getElementById('alert-zone');
let watchId = null;
let courseActiveId = null;

document.getElementById('disponibilite').addEventListener('change', async (e) => {
    try {
        await Api.post('/api/livreur/toggle_availability.php', { disponibilite: e.target.value });
        if (e.target.value === 'en_ligne') {
            demarrerSuiviPosition();
        } else if (watchId) {
            navigator.geolocation.clearWatch(watchId);
        }
        chargerCommandes();
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
        e.target.value = 'hors_ligne';
    }
});

function demarrerSuiviPosition() {
    if (!navigator.geolocation) return;
    watchId = navigator.geolocation.watchPosition((pos) => {
        Api.post('/api/livreur/update_position.php', {
            latitude: pos.coords.latitude,
            longitude: pos.coords.longitude,
            commande_id: courseActiveId,
        }).catch(() => {});
    }, () => {}, { enableHighAccuracy: true, maximumAge: 10000 });
}

async function chargerCourseActive() {
    const res = await Api.get('/api/livreur/orders_history.php');
    const active = res.data.commandes.find(c => ['acceptee', 'recuperee', 'en_cours'].includes(c.statut));
    const zone = document.getElementById('course-active');
    if (!active) {
        zone.classList.add('hidden');
        courseActiveId = null;
        return;
    }
    courseActiveId = active.id;
    const transitions = { acceptee: ['recuperee', 'Marquer comme recuperee'], recuperee: ['en_cours', 'Marquer en cours de livraison'], en_cours: ['livree', 'Marquer comme livree'] };
    const [prochainStatut, libelle] = transitions[active.statut] || [];
    zone.classList.remove('hidden');
    zone.innerHTML = `
        <h2>Course en cours : ${escapeHtml(active.reference)}</h2>
        <p>${badgeStatut(active.statut)} - ${escapeHtml(active.adresse_depart)} &rarr; ${escapeHtml(active.adresse_arrivee)}</p>
        <p><strong>Montant :</strong> ${formatMontant(active.montant_estime)}</p>
        <div class="flex">
            <a class="btn btn-secondaire" href="/livreur/navigation.php?commande_id=${active.id}">🧭 Naviguer</a>
            ${prochainStatut ? `<button id="btn-avancer" class="btn">${libelle}</button>` : ''}
        </div>
    `;
    document.getElementById('btn-avancer')?.addEventListener('click', async () => {
        try {
            await Api.post('/api/livreur/orders_update_status.php', { commande_id: active.id, statut: prochainStatut });
            chargerCourseActive();
            chargerCommandes();
        } catch (err) {
            alert(err.message);
        }
    });
}

async function chargerCommandes() {
    const tbody = document.getElementById('liste-commandes');
    try {
        const res = await Api.get('/api/livreur/orders_available.php');
        const commandes = res.data.commandes;
        if (commandes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-muted">Aucune commande disponible pour le moment.</td></tr>';
            return;
        }
        tbody.innerHTML = commandes.map(c => `
            <tr>
                <td>${escapeHtml(c.reference)}</td>
                <td>${escapeHtml(c.type_nom)}</td>
                <td>${escapeHtml(c.adresse_depart)}</td>
                <td>${escapeHtml(c.adresse_arrivee)}</td>
                <td>${c.distance_km} km</td>
                <td>${formatMontant(c.montant_estime)}</td>
                <td><button class="btn btn-sm" data-id="${c.id}">Accepter</button></td>
            </tr>
        `).join('');
        tbody.querySelectorAll('button[data-id]').forEach(btn => {
            btn.addEventListener('click', async () => {
                try {
                    await Api.post('/api/livreur/orders_accept.php', { commande_id: btn.dataset.id });
                    chargerCourseActive();
                    chargerCommandes();
                } catch (err) {
                    alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
                }
            });
        });
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-muted">${escapeHtml(err.message)}</td></tr>`;
    }
}

chargerCourseActive();
chargerCommandes();
setInterval(() => { chargerCourseActive(); chargerCommandes(); }, 10000);
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
