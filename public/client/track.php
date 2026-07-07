<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client');

$reference = $_GET['ref'] ?? '';

$pageTitle = 'Suivi de commande';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<h1>Suivi de la commande <?= htmlspecialchars($reference) ?></h1>

<div class="grid grid-2">
    <div class="card">
        <div id="details">Chargement...</div>
        <div id="actions" class="mt-1"></div>
    </div>
    <div class="card">
        <h2>Position du livreur</h2>
        <div id="map"></div>
    </div>
</div>

<div id="modal-note" class="hidden">
    <div class="card mt-1">
        <h2>Evaluer votre livreur</h2>
        <div class="form-group">
            <label for="note">Note (1 a 5)</label>
            <select id="note"><option value="5">5 - Excellent</option><option value="4">4 - Bien</option><option value="3">3 - Correct</option><option value="2">2 - Moyen</option><option value="1">1 - Mauvais</option></select>
        </div>
        <div class="form-group">
            <label for="commentaire">Commentaire (optionnel)</label>
            <textarea id="commentaire"></textarea>
        </div>
        <button id="btn-envoyer-note" class="btn">Envoyer mon evaluation</button>
    </div>
</div>

<script>
const reference = <?= json_encode($reference) ?>;
let map, markerLivreur, markerDepart, markerArrivee;
let commandeId = null;

function initMap() {
    map = L.map('map').setView([7.6900, -5.0300], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);
}

function renderDetails(c) {
    commandeId = c.id;
    document.getElementById('details').innerHTML = `
        <p><strong>Type :</strong> ${escapeHtml(c.type_nom)}</p>
        <p><strong>Statut :</strong> ${badgeStatut(c.statut)}</p>
        <p><strong>De :</strong> ${escapeHtml(c.adresse_depart)}</p>
        <p><strong>Vers :</strong> ${escapeHtml(c.adresse_arrivee)}</p>
        <p><strong>Distance :</strong> ${c.distance_km} km</p>
        <p><strong>Montant :</strong> ${formatMontant(c.montant_estime)}</p>
        <p><strong>Paiement :</strong> ${escapeHtml(c.mode_paiement)} (${escapeHtml(c.statut_paiement)})</p>
        ${c.livreur_nom ? `<p><strong>Livreur :</strong> ${escapeHtml(c.livreur_prenom)} ${escapeHtml(c.livreur_nom)} - ${escapeHtml(c.livreur_telephone)}</p>` : '<p class="text-muted">En attente d\'un livreur...</p>'}
        ${(c.code_livraison && !['livree', 'annulee'].includes(c.statut)) ? `<p style="font-size:1.1rem;"><strong>Code de livraison :</strong> <span style="letter-spacing:3px;font-weight:800;color:var(--couleur-primaire);">${escapeHtml(c.code_livraison)}</span><br><span class="text-muted">Communiquez ce code au livreur uniquement a la remise de votre colis.</span></p>` : ''}
    `;

    const actions = document.getElementById('actions');
    actions.innerHTML = '';
    if (!['livree', 'annulee'].includes(c.statut)) {
        actions.innerHTML += '<button id="btn-annuler" class="btn btn-danger">Annuler la commande</button>';
    }
    if (c.statut === 'livree') {
        document.getElementById('modal-note').classList.remove('hidden');
    }

    if (!markerDepart) {
        markerDepart = L.marker([c.lat_depart, c.lng_depart]).addTo(map).bindPopup('Depart');
        markerArrivee = L.marker([c.lat_arrivee, c.lng_arrivee]).addTo(map).bindPopup('Arrivee');
        map.fitBounds([[c.lat_depart, c.lng_depart], [c.lat_arrivee, c.lng_arrivee]]);
    }

    if (c.livreur_lat && c.livreur_lng) {
        if (!markerLivreur) {
            markerLivreur = L.marker([c.livreur_lat, c.livreur_lng], {
                title: 'Livreur',
            }).addTo(map).bindPopup('Livreur');
        } else {
            markerLivreur.setLatLng([c.livreur_lat, c.livreur_lng]);
        }
    }

    document.getElementById('btn-annuler')?.addEventListener('click', async () => {
        if (!confirm('Confirmer l\'annulation de cette commande ?')) return;
        try {
            await Api.post('/api/client/orders_cancel.php', { commande_id: commandeId });
            charger();
        } catch (err) {
            alert(err.message);
        }
    });
}

async function charger() {
    try {
        const res = await Api.get(`/api/client/orders_track.php?reference=${encodeURIComponent(reference)}`);
        renderDetails(res.data.commande);
    } catch (err) {
        document.getElementById('details').innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
}

let sseSource = null;
let pollingInterval = null;

function demarrerSSE() {
    if (typeof EventSource === 'undefined') {
        // Repli : navigateurs sans support SSE -> polling classique toutes les 5 s.
        charger();
        pollingInterval = setInterval(charger, 5000);
        return;
    }

    const url = '/api/client/track_stream.php?reference=' + encodeURIComponent(reference);
    sseSource = new EventSource(url);

    sseSource.addEventListener('update', (e) => {
        try { renderDetails(JSON.parse(e.data).commande); } catch (err) {}
    });

    sseSource.addEventListener('final', (e) => {
        try { renderDetails(JSON.parse(e.data).commande); } catch (err) {}
        sseSource.close();
    });

    // A la fin de vie du flux (5 min), le serveur ferme : le navigateur se
    // reconnecte automatiquement. Aucune action necessaire ici.

    sseSource.onerror = () => {
        // EventSource tente de se reconnecter tout seul. Si le flux est ferme
        // definitivement (commande terminee), il ne se rouvrira pas.
    };
}

document.getElementById('btn-envoyer-note').addEventListener('click', async () => {
    try {
        await Api.post('/api/client/rate.php', {
            commande_id: commandeId,
            note: document.getElementById('note').value,
            commentaire: document.getElementById('commentaire').value.trim(),
        });
        document.getElementById('modal-note').innerHTML = '<div class="alert alert-succes">Merci pour votre evaluation !</div>';
    } catch (err) {
        alert(err.message);
    }
});

initMap();
charger();       // premier affichage immediat
demarrerSSE();   // puis mises a jour poussees par le serveur (SSE)
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
