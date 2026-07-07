<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client');

$pageTitle = 'Nouvelle commande';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<h1>Nouvelle commande</h1>
<p class="subtitle">Choisissez le type de livraison, le point de depart et le point d'arrivee.</p>

<div class="grid grid-2">
    <div class="card">
        <div class="form-group">
            <label for="type_livraison">Type de livraison</label>
            <select id="type_livraison"></select>
        </div>

        <div class="form-group">
            <label>Point de depart <span class="text-muted">(cliquez sur la carte, marqueur orange)</span></label>
            <input type="text" id="adresse_depart" placeholder="Adresse de depart" required>
        </div>
        <div class="form-group">
            <label>Point d'arrivee <span class="text-muted">(cliquez sur la carte, marqueur vert)</span></label>
            <input type="text" id="adresse_arrivee" placeholder="Adresse d'arrivee" required>
        </div>

        <div id="map"></div>
        <p class="text-muted mt-1">Astuce : un premier clic place le depart, le second l'arrivee, puis les clics suivants replacent l'arrivee.</p>

        <div class="form-group mt-1">
            <label><input type="checkbox" id="express" style="width:auto; display:inline-block;"> Livraison express (supplement)</label>
        </div>

        <div class="form-group">
            <label for="instructions">Instructions pour le livreur (optionnel)</label>
            <textarea id="instructions" placeholder="Ex: appartement 3B, appeler en arrivant"></textarea>
        </div>

        <div class="form-group">
            <label for="mode_paiement">Mode de paiement</label>
            <select id="mode_paiement">
                <option value="especes">Especes a la livraison</option>
                <option value="orange_money">Orange Money</option>
                <option value="mtn_money">MTN Mobile Money</option>
                <option value="moov_money">Moov Money</option>
                <option value="wave">Wave</option>
            </select>
        </div>
        <div class="form-group hidden" id="zone-numero-paiement">
            <label for="numero_paiement">Numero Mobile Money</label>
            <input type="text" id="numero_paiement" placeholder="07 00 00 00 00">
        </div>
    </div>

    <div class="card">
        <h2>Estimation</h2>
        <div id="alert-zone"></div>
        <div class="stat-tile mb-1">
            <div class="label">Distance</div>
            <div class="valeur" id="estim-distance">-</div>
        </div>
        <div class="stat-tile mb-1">
            <div class="label">Montant estime</div>
            <div class="valeur" id="estim-montant">-</div>
        </div>
        <button id="btn-commander" class="btn btn-block" disabled>Estimer d'abord</button>
    </div>
</div>

<script>
let map, markerDepart, markerArrivee;
let coords = { depart: null, arrivee: null };
let typesLivraison = [];

function initMap() {
    map = L.map('map').setView([7.6900, -5.0300], 13); // Bouake
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    map.on('click', (e) => {
        if (!coords.depart) {
            coords.depart = e.latlng;
            markerDepart = L.marker(e.latlng, { title: 'Depart' }).addTo(map).bindPopup('Depart').openPopup();
        } else if (!coords.arrivee) {
            coords.arrivee = e.latlng;
            markerArrivee = L.marker(e.latlng, { title: 'Arrivee' }).addTo(map).bindPopup('Arrivee').openPopup();
        } else {
            coords.arrivee = e.latlng;
            markerArrivee.setLatLng(e.latlng);
        }
        estimer();
    });
}

async function chargerTypes() {
    const res = await Api.get('/api/public/delivery_types.php');
    typesLivraison = res.data.types;
    const select = document.getElementById('type_livraison');
    select.innerHTML = typesLivraison.map(t => `<option value="${t.id}">${escapeHtml(t.nom)}</option>`).join('');
}

async function estimer() {
    const alertZone = document.getElementById('alert-zone');
    const btn = document.getElementById('btn-commander');
    if (!coords.depart || !coords.arrivee) {
        return;
    }
    try {
        const res = await Api.post('/api/client/estimation.php', {
            type_livraison_id: document.getElementById('type_livraison').value,
            lat_depart: coords.depart.lat,
            lng_depart: coords.depart.lng,
            lat_arrivee: coords.arrivee.lat,
            lng_arrivee: coords.arrivee.lng,
            express: document.getElementById('express').checked,
        });
        document.getElementById('estim-distance').textContent = res.data.distance_km + ' km';
        document.getElementById('estim-montant').textContent = formatMontant(res.data.montant_estime);
        btn.disabled = false;
        btn.textContent = 'Confirmer la commande';
        alertZone.innerHTML = '';
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
}

document.getElementById('type_livraison').addEventListener('change', estimer);
document.getElementById('express').addEventListener('change', estimer);
document.getElementById('mode_paiement').addEventListener('change', () => {
    const mm = ['orange_money', 'mtn_money', 'moov_money', 'wave'].includes(document.getElementById('mode_paiement').value);
    document.getElementById('zone-numero-paiement').classList.toggle('hidden', !mm);
});

document.getElementById('btn-commander').addEventListener('click', async () => {
    const alertZone = document.getElementById('alert-zone');
    const adresseDepart = document.getElementById('adresse_depart').value.trim();
    const adresseArrivee = document.getElementById('adresse_arrivee').value.trim();

    if (!adresseDepart || !adresseArrivee) {
        alertZone.innerHTML = '<div class="alert alert-erreur">Veuillez renseigner les adresses de depart et d\'arrivee.</div>';
        return;
    }

    const payload = {
        type_livraison_id: document.getElementById('type_livraison').value,
        adresse_depart: adresseDepart,
        lat_depart: coords.depart.lat,
        lng_depart: coords.depart.lng,
        adresse_arrivee: adresseArrivee,
        lat_arrivee: coords.arrivee.lat,
        lng_arrivee: coords.arrivee.lng,
        express: document.getElementById('express').checked,
        instructions: document.getElementById('instructions').value.trim(),
        mode_paiement: document.getElementById('mode_paiement').value,
        numero_paiement: document.getElementById('numero_paiement').value.trim(),
    };

    try {
        const res = await Api.post('/api/client/orders_create.php', payload);
        redirigerApresCommande(res.data);
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

// Redirige selon le mode de paiement : page de paiement web (Wave/Orange),
// instructions push (MTN/Moov) ou suivi direct (especes/simulation).
function redirigerApresCommande(data) {
    if (data.redirect_url) {
        window.location.href = data.redirect_url;
        return;
    }
    if (data.instructions && data.statut_paiement === 'en_attente' && document.getElementById('mode_paiement').value !== 'especes') {
        window.location.href = '/client/payment_return.php?ref=' + encodeURIComponent(data.reference);
        return;
    }
    window.location.href = '/client/track.php?ref=' + encodeURIComponent(data.reference);
}

initMap();
chargerTypes();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
