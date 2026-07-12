<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('livreur');

$pageTitle = 'Courses disponibles';
require __DIR__ . '/../includes/header.php';
?>
<script src="<?= asset_url('/assets/js/chat.js') ?>"></script>
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
            <option value="pause">En pause</option>
        </select>
    </div>
</div>

<div id="alert-zone"></div>

<div id="notif-statut" class="hidden mb-1"></div>

<div id="offre-dispatch" class="card hidden mb-1" style="border:2px solid var(--couleur-primaire);"></div>

<div id="course-active" class="card hidden mb-1"></div>

<div id="chat-course" class="card hidden mb-1"></div>

<div id="notation-client" class="card hidden mb-1" style="border:2px solid var(--accent);"></div>

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
let chatCourseId = null; // commande dont le chat est actuellement affiche

document.getElementById('disponibilite').addEventListener('change', async (e) => {
    try {
        await Api.post('/api/livreur/toggle_availability.php', { disponibilite: e.target.value });
        // On garde le suivi de position en ligne ET en pause (l'admin voit la
        // flotte), on ne l'arrete qu'une fois hors ligne.
        if (e.target.value === 'en_ligne' || e.target.value === 'pause') {
            demarrerSuiviPosition();
        } else if (watchId) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
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

// Reflete la disponibilite reelle du livreur au chargement (evite d'afficher
// "Hors ligne" alors que le livreur est en ligne cote serveur).
async function chargerDisponibilite() {
    try {
        const res = await Api.get('/api/livreur/profile.php');
        const dispo = res.data.disponibilite || 'hors_ligne';
        document.getElementById('disponibilite').value = dispo;
        if (dispo === 'en_ligne' || dispo === 'pause') {
            demarrerSuiviPosition();
        }
    } catch (err) { /* on garde la valeur par defaut */ }
}

async function chargerCourseActive() {
    const zone = document.getElementById('course-active');
    const chatCard = document.getElementById('chat-course');

    let res;
    try {
        res = await Api.get('/api/livreur/orders_history.php');
    } catch (err) {
        // Ne jamais faire "disparaitre" la course en silence : on informe.
        zone.classList.remove('hidden');
        zone.innerHTML = `<div class="alert alert-erreur">Impossible de charger votre course en cours : ${escapeHtml(err.message)}</div>`;
        return;
    }

    const active = res.data.commandes.find(c => ['acceptee', 'recuperee', 'en_cours'].includes(c.statut));
    if (!active) {
        zone.classList.add('hidden');
        chatCard.classList.add('hidden');
        if (typeof Chat !== 'undefined') { Chat.stop(); }
        chatCourseId = null;
        courseActiveId = null;
        derniereSignatureCourse = null;
        return;
    }
    courseActiveId = active.id;

    // Messagerie avec le client : optionnelle, ne doit jamais empecher
    // l'affichage de la course si le module de chat echoue.
    if (typeof Chat !== 'undefined' && chatCourseId !== active.id) {
        try {
            chatCard.classList.remove('hidden');
            Chat.init(chatCard, active.id);
            chatCourseId = active.id;
        } catch (e) { /* le chat est secondaire */ }
    }

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

    const noteClient = active.client_note ? ` · ⭐ ${Number(active.client_note).toFixed(1)}` : '';
    zone.innerHTML = `
        <h2>Course en cours : ${escapeHtml(active.reference)}</h2>
        <p>${badgeStatut(active.statut)} - ${escapeHtml(active.adresse_depart)} &rarr; ${escapeHtml(active.adresse_arrivee)}</p>
        <p><strong>Client :</strong> ${escapeHtml(active.client_prenom || '')} ${escapeHtml(active.client_nom || '')}${noteClient}</p>
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

// --- Notation du client par le livreur (notation double sens) ---------------
let notationCommandeId = null; // commande dont le formulaire est affiche

async function chargerNotationClient() {
    const zone = document.getElementById('notation-client');
    try {
        const res = await Api.get('/api/livreur/pending_rating.php');
        const c = res.data.a_noter;
        if (!c) {
            zone.classList.add('hidden');
            notationCommandeId = null;
            return;
        }
        // Ne re-render pas si le formulaire de cette commande est deja affiche
        // (sinon la saisie en cours serait effacee a chaque rafraichissement).
        if (notationCommandeId === c.id) { return; }
        notationCommandeId = c.id;

        const noteActuelle = c.nombre_evaluations_client > 0
            ? `note actuelle ⭐ ${Number(c.note_client).toFixed(1)} (${c.nombre_evaluations_client})`
            : 'client pas encore note';
        zone.classList.remove('hidden');
        zone.innerHTML = `
            <h2 style="margin-top:0;">Noter le client — ${escapeHtml(c.reference)}</h2>
            <p>Comment s'est passee la livraison avec <strong>${escapeHtml(c.client_prenom)} ${escapeHtml(c.client_nom)}</strong> ? <span class="text-muted">(${noteActuelle})</span></p>
            <div class="form-group">
                <label for="note-client">Note</label>
                <select id="note-client">
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Bien</option>
                    <option value="3">3 - Correct</option>
                    <option value="2">2 - Moyen</option>
                    <option value="1">1 - Mauvais</option>
                </select>
            </div>
            <div class="form-group">
                <label for="commentaire-client">Commentaire (optionnel)</label>
                <textarea id="commentaire-client" placeholder="Ex: adresse facile a trouver, client ponctuel..."></textarea>
            </div>
            <div class="flex">
                <button id="btn-noter-client" class="btn btn-secondaire">Envoyer</button>
                <button id="btn-ignorer-note" class="btn btn-ghost">Plus tard</button>
            </div>`;

        document.getElementById('btn-noter-client').addEventListener('click', async () => {
            try {
                await Api.post('/api/livreur/rate_client.php', {
                    commande_id: c.id,
                    note: document.getElementById('note-client').value,
                    commentaire: document.getElementById('commentaire-client').value.trim(),
                });
                notationCommandeId = null;
                chargerNotationClient();
            } catch (err) {
                alert(err.message);
            }
        });
        document.getElementById('btn-ignorer-note').addEventListener('click', () => {
            zone.classList.add('hidden'); // reapparaitra au prochain chargement de page
        });
    } catch (err) {
        zone.classList.add('hidden');
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

chargerDisponibilite();
chargerOffre();
chargerCourseActive();
chargerCommandes();
chargerNotationClient();
setInterval(() => { chargerOffre(); chargerCourseActive(); chargerCommandes(); chargerNotationClient(); }, 5000);
// Le bandeau #notif-statut (activer/bloquees/actives) est rendu par app.js.
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
