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

<div id="offre-dispatch" class="card hidden mb-1" style="border:2px solid var(--couleur-primaire);"></div>

<div id="course-active" class="card hidden mb-1"></div>

<div class="card">
    <h2 style="margin-top:0;">Autres courses disponibles</h2>
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
let derniereSignatureCourse = null; // pour ne re-render que si l'etat change

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
        derniereSignatureCourse = null;
        return;
    }
    courseActiveId = active.id;

    // On ne reconstruit la carte que si l'etat change reellement, sinon le
    // rafraichissement automatique effacerait le code/la photo en cours de saisie.
    const signature = active.id + ':' + active.statut;
    if (signature === derniereSignatureCourse) {
        return;
    }
    derniereSignatureCourse = signature;

    const transitions = { acceptee: ['recuperee', 'Marquer comme recuperee'], recuperee: ['en_cours', 'Marquer en cours de livraison'] };
    const [prochainStatut, libelle] = transitions[active.statut] || [];
    zone.classList.remove('hidden');

    // A l'etape "en_cours", la confirmation de livraison exige une preuve.
    const blocLivraison = active.statut === 'en_cours' ? `
        <div class="mt-1" style="border-top:1px solid var(--couleur-bordure);padding-top:0.75rem;">
            <p><strong>Confirmer la livraison</strong> (preuve requise)</p>
            <div class="form-group">
                <label for="code-livraison">Code de livraison communique par le client</label>
                <input type="text" id="code-livraison" inputmode="numeric" maxlength="4" placeholder="4 chiffres">
            </div>
            <div class="form-group">
                <label for="photo-livraison">Ou joindre une photo (preuve)</label>
                <input type="file" id="photo-livraison" accept="image/*" capture="environment">
            </div>
            <button id="btn-livrer" class="btn">Confirmer la livraison</button>
        </div>` : '';

    zone.innerHTML = `
        <h2>Course en cours : ${escapeHtml(active.reference)}</h2>
        <p>${badgeStatut(active.statut)} - ${escapeHtml(active.adresse_depart)} &rarr; ${escapeHtml(active.adresse_arrivee)}</p>
        <p><strong>Montant :</strong> ${formatMontant(active.montant_estime)}</p>
        <div class="flex">
            <a class="btn btn-secondaire" href="/livreur/navigation.php?commande_id=${active.id}">🧭 Naviguer</a>
            ${prochainStatut ? `<button id="btn-avancer" class="btn">${libelle}</button>` : ''}
        </div>
        ${blocLivraison}
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

    document.getElementById('btn-livrer')?.addEventListener('click', async () => {
        const code = document.getElementById('code-livraison').value.trim();
        const photoInput = document.getElementById('photo-livraison');
        const photo = photoInput.files[0];
        if (!code && !photo) {
            alert('Saisissez le code du client ou joignez une photo.');
            return;
        }
        try {
            const form = new FormData();
            form.append('commande_id', active.id);
            if (code) { form.append('code_livraison', code); }
            if (photo) { form.append('photo', photo); }
            const res = await fetch('/api/livreur/confirm_delivery.php', { method: 'POST', credentials: 'same-origin', body: form });
            const body = await res.json();
            if (!res.ok || !body.success) { throw new Error(body.message || 'Erreur'); }
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

// --- Offre de dispatch (course proposee au livreur le plus proche) ----------
let offreCourante = null;

async function chargerOffre() {
    const zone = document.getElementById('offre-dispatch');
    try {
        const res = await Api.get('/api/livreur/offer_get.php');
        const o = res.data.offre;
        if (!o) {
            zone.classList.add('hidden');
            offreCourante = null;
            return;
        }
        offreCourante = o;
        const sec = res.data.secondes_restantes;
        const retrait = o.nom_boutique ? escapeHtml(o.nom_boutique) : escapeHtml(o.adresse_depart);
        zone.classList.remove('hidden');
        zone.innerHTML = `
            <h2 style="margin-top:0;">🛵 Course proposee <span style="color:var(--couleur-primaire)">(${sec}s)</span></h2>
            <p><strong>${escapeHtml(o.reference)}</strong> &middot; ${escapeHtml(o.type_nom)} &middot; a ${o.distance_km} km de vous</p>
            <p>Retrait : ${retrait} &rarr; ${escapeHtml(o.adresse_arrivee)}</p>
            <p><strong>Montant :</strong> ${formatMontant(o.montant_estime)} (${escapeHtml(o.mode_paiement)})</p>
            <div class="flex">
                <button id="btn-accepter-offre" class="btn">Accepter</button>
                <button id="btn-refuser-offre" class="btn btn-ghost">Refuser</button>
            </div>
        `;
        document.getElementById('btn-accepter-offre').addEventListener('click', () => repondreOffre('accepter'));
        document.getElementById('btn-refuser-offre').addEventListener('click', () => repondreOffre('refuser'));
    } catch (err) {
        zone.classList.add('hidden');
    }
}

async function repondreOffre(decision) {
    if (!offreCourante) { return; }
    try {
        await Api.post('/api/livreur/offer_respond.php', { offre_id: offreCourante.offre_id, decision: decision });
        offreCourante = null;
        chargerOffre();
        chargerCourseActive();
        chargerCommandes();
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
        chargerOffre();
    }
}

chargerOffre();
chargerCourseActive();
chargerCommandes();
setInterval(() => { chargerOffre(); chargerCourseActive(); chargerCommandes(); }, 5000);
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
