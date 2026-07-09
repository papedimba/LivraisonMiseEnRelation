<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client');

$pageTitle = 'Nouvelle commande';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="order-screen">
    <div id="map" class="order-map"></div>

    <div class="order-sheet">
        <div class="grip"></div>
        <h1>Envoyer un colis</h1>

        <div class="addr-row">
            <span class="addr-icon">📦</span>
            <input type="text" id="adresse_depart" placeholder="Point de depart (cliquez sur la carte)" required>
        </div>
        <div class="addr-row">
            <span class="addr-icon arrivee">🏁</span>
            <input type="text" id="adresse_arrivee" placeholder="Adresse de livraison (cliquez sur la carte)" required>
        </div>

        <div id="alert-zone" class="mt-1"></div>

        <div class="type-cards" id="type-cards"></div>

        <div class="form-group">
            <label><input type="checkbox" id="express" style="width:auto; display:inline-block;"> Livraison express (supplement)</label>
        </div>

        <div class="form-group">
            <label for="instructions">Instructions pour le livreur (optionnel)</label>
            <textarea id="instructions" placeholder="Ex: appartement 3B, appeler en arrivant"></textarea>
        </div>

        <div class="pay-row">
            <select id="mode_paiement">
                <option value="especes">💵 Especes a la livraison</option>
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

        <div class="form-group">
            <label for="code_promo">Code promo (optionnel)</label>
            <div class="flex">
                <input type="text" id="code_promo" placeholder="Ex: BIENVENUE10" style="flex:1;">
                <button type="button" class="btn btn-ghost" onclick="verifierPromo()">Appliquer</button>
            </div>
            <div id="promo-message" style="font-size:0.85rem;margin-top:0.35rem;"></div>
        </div>

        <div class="estim-line"><span>Distance</span><span id="estim-distance">-</span></div>
        <div class="estim-line"><span>Montant estime</span><strong id="estim-montant">-</strong></div>

        <button id="btn-commander" class="btn btn-block mt-1" disabled>Placez les points sur la carte</button>
    </div>
</div>

<script>
let map, markerDepart, markerArrivee;
let coords = { depart: null, arrivee: null };
let typesLivraison = [];
let typeSelectionneId = null;
let derniereDistanceKm = null;

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

// Prix indicatif d'un type de livraison ("a partir de") : tarif de base tant
// que la distance est inconnue, sinon estimation complete cote client.
function prixIndicatif(t) {
    const base = parseFloat(t.tarif_base) || 0;
    if (derniereDistanceKm === null) {
        return { label: 'a partir de', montant: base };
    }
    const km = parseFloat(t.tarif_km) || 0;
    const sup = document.getElementById('express').checked ? (parseFloat(t.supplement_express) || 0) : 0;
    return { label: '', montant: Math.round(base + km * derniereDistanceKm + sup) };
}

function rendreTypes() {
    const conteneur = document.getElementById('type-cards');
    conteneur.innerHTML = typesLivraison.map(t => {
        const p = prixIndicatif(t);
        const actif = String(t.id) === String(typeSelectionneId) ? ' actif' : '';
        const prefixe = p.label ? p.label + ' ' : '';
        return `<div class="type-card${actif}" data-id="${t.id}">
            <div class="tc-icon">${escapeHtml(t.icone || '🛵')}</div>
            <div class="tc-nom">${escapeHtml(t.nom)}</div>
            <div class="tc-prix">${prefixe}${formatMontant(p.montant)}</div>
        </div>`;
    }).join('');
    conteneur.querySelectorAll('.type-card').forEach(el => {
        el.addEventListener('click', () => {
            typeSelectionneId = el.dataset.id;
            rendreTypes();
            estimer();
        });
    });
}

async function chargerTypes() {
    const res = await Api.get('/api/public/delivery_types.php');
    typesLivraison = res.data.types;
    if (typesLivraison.length) {
        typeSelectionneId = typesLivraison[0].id;
    }
    rendreTypes();
}

async function estimer() {
    const alertZone = document.getElementById('alert-zone');
    const btn = document.getElementById('btn-commander');
    if (!coords.depart || !coords.arrivee || !typeSelectionneId) {
        return;
    }
    try {
        const res = await Api.post('/api/client/estimation.php', {
            type_livraison_id: typeSelectionneId,
            lat_depart: coords.depart.lat,
            lng_depart: coords.depart.lng,
            lat_arrivee: coords.arrivee.lat,
            lng_arrivee: coords.arrivee.lng,
            express: document.getElementById('express').checked,
        });
        derniereDistanceKm = parseFloat(res.data.distance_km);
        document.getElementById('estim-distance').textContent = res.data.distance_km + ' km';
        document.getElementById('estim-montant').textContent = formatMontant(res.data.montant_estime);
        btn.disabled = false;
        btn.textContent = 'Confirmer la commande';
        alertZone.innerHTML = '';
        rendreTypes(); // rafraichit les prix des cartes avec la distance connue
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
}

// Verifie et applique un code promo sur le montant estime courant.
async function verifierPromo() {
    const zone = document.getElementById('promo-message');
    const code = document.getElementById('code_promo').value.trim();
    if (!code) { zone.textContent = ''; return; }
    // Recupere le montant estime affiche (nombre).
    const txt = document.getElementById('estim-montant').textContent.replace(/[^\d]/g, '');
    const montant = parseInt(txt || '0', 10);
    if (!montant) {
        zone.innerHTML = '<span style="color:var(--danger)">Estimez d\'abord la commande.</span>';
        return;
    }
    try {
        const res = await Api.post('/api/client/promo_check.php', { code: code, montant: montant });
        zone.innerHTML = '<span style="color:var(--succes)">Code applique : -'
            + formatMontant(res.data.reduction) + ' → ' + formatMontant(res.data.nouveau_montant) + '</span>';
    } catch (err) {
        zone.innerHTML = '<span style="color:var(--danger)">' + escapeHtml(err.message) + '</span>';
    }
}

document.getElementById('express').addEventListener('change', () => { rendreTypes(); estimer(); });
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
        type_livraison_id: typeSelectionneId,
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
        code_promo: document.getElementById('code_promo').value.trim(),
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
