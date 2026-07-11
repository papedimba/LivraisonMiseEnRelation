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
            <label for="type_livraison">Type de colis</label>
            <select id="type_livraison"></select>
        </div>

        <div class="form-group">
            <label for="moyen_transport">Moyen de transport</label>
            <select id="moyen_transport"></select>
        </div>

        <div class="form-group">
            <label>Point de depart <span class="text-muted">(cliquez sur la carte, marqueur orange)</span></label>
            <input type="text" id="adresse_depart" placeholder="Adresse de depart" required>
            <div class="fav-chips" id="fav-depart"></div>
            <div class="repere-search">
                <input type="text" id="rep-depart" placeholder="🔎 Rechercher un repere (ex: pharmacie, ecole...)">
                <div class="repere-suggestions hidden" id="sug-depart"></div>
            </div>
        </div>
        <div class="form-group">
            <label>Point d'arrivee <span class="text-muted">(cliquez sur la carte, marqueur vert)</span></label>
            <input type="text" id="adresse_arrivee" placeholder="Adresse d'arrivee" required>
            <div class="fav-chips" id="fav-arrivee"></div>
            <div class="repere-search">
                <input type="text" id="rep-arrivee" placeholder="🔎 Rechercher un repere (ex: marche, carrefour...)">
                <div class="repere-suggestions hidden" id="sug-arrivee"></div>
            </div>
            <div class="flex" style="margin-top:0.35rem;">
                <button type="button" class="btn btn-ghost btn-sm" id="btn-save-arrivee">💾 Enregistrer cette adresse</button>
                <a class="btn btn-ghost btn-sm" href="/client/addresses.php">Gerer mes adresses</a>
            </div>
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
        <div class="form-group">
            <label for="code_promo">Code promo (optionnel)</label>
            <div class="flex">
                <input type="text" id="code_promo" placeholder="Ex: BIENVENUE10" style="flex:1;">
                <button type="button" class="btn btn-ghost" onclick="verifierPromo()">Appliquer</button>
            </div>
            <div id="promo-message" style="font-size:0.85rem;margin-top:0.35rem;"></div>
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
            placerDepart(e.latlng.lat, e.latlng.lng);
        } else {
            placerArrivee(e.latlng.lat, e.latlng.lng);
        }
        estimer();
    });

    tenterGeolocalisation();
}

// Centre la carte sur la position du client (s'il l'autorise), pour que la
// recherche de reperes s'ancre sur sa zone reelle. Ne place aucun marqueur et
// n'ecrase pas un point deja saisi (ex: recommander une commande).
function tenterGeolocalisation() {
    if (!navigator.geolocation) { return; }
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            if (!coords.depart && !coords.arrivee) {
                map.setView([pos.coords.latitude, pos.coords.longitude], 15);
            }
        },
        () => { /* refus ou indisponible : on garde la vue par defaut (Bouake) */ },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 300000 }
    );
}

function placerDepart(lat, lng) {
    coords.depart = L.latLng(lat, lng);
    if (!markerDepart) {
        markerDepart = L.marker(coords.depart, { title: 'Depart' }).addTo(map).bindPopup('Depart');
    } else {
        markerDepart.setLatLng(coords.depart);
    }
}

function placerArrivee(lat, lng) {
    coords.arrivee = L.latLng(lat, lng);
    if (!markerArrivee) {
        markerArrivee = L.marker(coords.arrivee, { title: 'Arrivee' }).addTo(map).bindPopup('Arrivee');
    } else {
        markerArrivee.setLatLng(coords.arrivee);
    }
}

async function chargerTypes() {
    const res = await Api.get('/api/public/delivery_types.php');
    typesLivraison = res.data.types;
    const select = document.getElementById('type_livraison');
    select.innerHTML = typesLivraison.map(t => `<option value="${t.id}">${escapeHtml(t.nom)}</option>`).join('');
}

async function chargerMoyensTransport() {
    const res = await Api.get('/api/public/transport_modes.php');
    const select = document.getElementById('moyen_transport');
    select.innerHTML = (res.data.moyens || []).map(m => {
        const coef = Number(m.multiplicateur);
        const suffixe = coef !== 1 ? ` (×${coef})` : '';
        return `<option value="${m.id}">${escapeHtml(m.nom)}${suffixe}</option>`;
    }).join('');
    select.addEventListener('change', estimer);
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
            moyen_transport_id: document.getElementById('moyen_transport').value,
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
        // Information de tarification dynamique (majoration eventuelle).
        alertZone.innerHTML = res.data.surge_actif
            ? `<div class="alert alert-info">Tarif majore &times;${res.data.surge_facteur}${res.data.surge_raison ? ' (' + escapeHtml(res.data.surge_raison) + ')' : ''} en raison de la demande actuelle.</div>`
            : '';
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
        zone.innerHTML = '<span style="color:var(--couleur-danger)">Estimez d\'abord la commande.</span>';
        return;
    }
    try {
        const res = await Api.post('/api/client/promo_check.php', { code: code, montant: montant });
        zone.innerHTML = '<span style="color:var(--couleur-succes)">Code applique : -'
            + formatMontant(res.data.reduction) + ' → ' + formatMontant(res.data.nouveau_montant) + '</span>';
    } catch (err) {
        zone.innerHTML = '<span style="color:var(--couleur-danger)">' + escapeHtml(err.message) + '</span>';
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
        moyen_transport_id: document.getElementById('moyen_transport').value,
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

// --- Adresses favorites -----------------------------------------------------
let favoris = [];

async function chargerFavoris() {
    try {
        const res = await Api.get('/api/client/addresses_list.php');
        favoris = res.data.adresses || [];
    } catch (e) {
        favoris = [];
    }
    rendreFavoris();
}

function rendreFavoris() {
    const zoneDep = document.getElementById('fav-depart');
    const zoneArr = document.getElementById('fav-arrivee');
    if (favoris.length === 0) {
        zoneDep.innerHTML = '';
        zoneArr.innerHTML = '';
        return;
    }
    const chips = (cible) => favoris.map(f =>
        `<button type="button" class="fav-chip" data-id="${f.id}" data-cible="${cible}">📍 ${escapeHtml(f.libelle)}</button>`
    ).join('');
    zoneDep.innerHTML = chips('depart');
    zoneArr.innerHTML = chips('arrivee');

    document.querySelectorAll('.fav-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            const fav = favoris.find(f => String(f.id) === chip.dataset.id);
            if (!fav) { return; }
            const lat = parseFloat(fav.latitude), lng = parseFloat(fav.longitude);
            if (chip.dataset.cible === 'depart') {
                placerDepart(lat, lng);
                document.getElementById('adresse_depart').value = fav.adresse;
            } else {
                placerArrivee(lat, lng);
                document.getElementById('adresse_arrivee').value = fav.adresse;
            }
            map.panTo([lat, lng]);
            estimer();
        });
    });
}

document.getElementById('btn-save-arrivee').addEventListener('click', async () => {
    if (!coords.arrivee) {
        alert('Placez d\'abord le point d\'arrivee sur la carte.');
        return;
    }
    const adresse = document.getElementById('adresse_arrivee').value.trim();
    if (!adresse) {
        alert('Renseignez l\'adresse d\'arrivee avant de l\'enregistrer.');
        return;
    }
    const libelle = prompt('Nom de cette adresse (ex: Maison, Bureau) :', '');
    if (libelle === null || libelle.trim() === '') { return; }
    try {
        await Api.post('/api/client/addresses_save.php', {
            libelle: libelle.trim(),
            adresse: adresse,
            latitude: coords.arrivee.lat,
            longitude: coords.arrivee.lng,
        });
        chargerFavoris();
    } catch (err) {
        alert(err.message);
    }
});

// --- Recherche de reperes issus de la cartographie collaborative -------------
function brancherRechercheRepere(inputId, listId, cible) {
    const input = document.getElementById(inputId);
    const liste = document.getElementById(listId);
    let minuteur = null;

    const fermer = () => { liste.classList.add('hidden'); liste.innerHTML = ''; };

    function choisir(lat, lng, nom) {
        if (cible === 'depart') {
            placerDepart(lat, lng);
            document.getElementById('adresse_depart').value = nom;
        } else {
            placerArrivee(lat, lng);
            document.getElementById('adresse_arrivee').value = nom;
        }
        input.value = '';
        fermer();
        map.panTo([lat, lng]);
        estimer();
    }

    input.addEventListener('input', () => {
        clearTimeout(minuteur);
        const q = input.value.trim();
        if (q.length < 3) { fermer(); return; }
        minuteur = setTimeout(async () => {
            // Ancrage sur la zone/ville affichee (centre de la carte) pour ne
            // retourner que les reperes et adresses proches du client.
            const centre = map.getCenter();
            const geo = '&lat=' + centre.lat + '&lng=' + centre.lng + '&rayon_km=40';
            // Deux sources en parallele : reperes collaboratifs + adresses OSM.
            const [reperesRes, geoRes] = await Promise.allSettled([
                Api.get('/api/public/map_points.php?q=' + encodeURIComponent(q) + geo),
                Api.get('/api/public/geocode.php?q=' + encodeURIComponent(q) + geo),
            ]);
            const reperes = reperesRes.status === 'fulfilled' ? (reperesRes.value.data.points || []).slice(0, 6) : [];
            const adresses = geoRes.status === 'fulfilled' ? (geoRes.value.data.resultats || []).slice(0, 6) : [];

            if (!reperes.length && !adresses.length) { fermer(); return; }

            let html = '';
            if (reperes.length) {
                html += '<div class="repere-head">Reperes CityHub</div>';
                html += reperes.map(p =>
                    `<div class="repere-item" data-lat="${p.latitude}" data-lng="${p.longitude}" data-nom="${escapeHtml(p.nom)}">
                        ${escapeHtml(p.nom)} <span class="cat">· ${escapeHtml(p.categorie)} · 👍 ${p.confirmations}</span>
                    </div>`).join('');
            }
            if (adresses.length) {
                html += '<div class="repere-head">Adresses (carte)</div>';
                html += adresses.map(a =>
                    `<div class="repere-item repere-osm" data-lat="${a.latitude}" data-lng="${a.longitude}" data-nom="${escapeHtml(a.nom)}" data-type="${escapeHtml(a.type || '')}">
                        <span>${escapeHtml(a.nom)} <span class="src">· 🗺️ OSM</span></span>
                        <button type="button" class="repere-save" title="Enregistrer comme repere collaboratif">＋ repere</button>
                    </div>`).join('');
            }
            liste.innerHTML = html;
            liste.classList.remove('hidden');
            liste.querySelectorAll('.repere-item').forEach(el => {
                el.addEventListener('click', () => {
                    choisir(parseFloat(el.dataset.lat), parseFloat(el.dataset.lng), el.dataset.nom);
                });
            });
            // Bouton "Enregistrer comme repere" : contribue le point OSM a la
            // cartographie collaborative (sans selectionner l'adresse).
            liste.querySelectorAll('.repere-save').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const el = btn.closest('.repere-item');
                    enregistrerRepereOSM(el.dataset.nom, parseFloat(el.dataset.lat), parseFloat(el.dataset.lng), el.dataset.type);
                });
            });
        }, 350);
    });

    // Ferme la liste si on clique ailleurs.
    document.addEventListener('click', (e) => {
        if (e.target !== input && !liste.contains(e.target)) { fermer(); }
    });
}

// Correspondance type OSM -> categorie de repere collaboratif.
const OSM_CATEGORIE = {
    pharmacy: 'sante', hospital: 'sante', clinic: 'sante', doctors: 'sante',
    school: 'education', university: 'education', college: 'education', kindergarten: 'education',
    marketplace: 'commerce', supermarket: 'commerce', bakery: 'commerce', mall: 'commerce', convenience: 'commerce', shop: 'commerce',
    townhall: 'service_public', police: 'service_public', post_office: 'service_public', bank: 'service_public', courthouse: 'service_public',
    neighbourhood: 'quartier', suburb: 'quartier', quarter: 'quartier',
};

async function enregistrerRepereOSM(nom, lat, lng, type) {
    const categorie = OSM_CATEGORIE[type] || 'repere';
    try {
        const res = await Api.post('/api/map/point_add.php', {
            nom: nom, categorie: categorie, latitude: lat, longitude: lng,
        });
        document.getElementById('alert-zone').innerHTML = `<div class="alert alert-succes">${escapeHtml(res.message)}</div>`;
    } catch (err) {
        document.getElementById('alert-zone').innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
}

// --- Recommander une commande (pre-remplissage depuis une commande passee) ---
async function prechargerReorder() {
    const params = new URLSearchParams(window.location.search);
    const ref = params.get('reorder');
    if (!ref) { return; }
    try {
        const res = await Api.get('/api/client/orders_track.php?reference=' + encodeURIComponent(ref));
        const c = res.data.commande;
        document.getElementById('adresse_depart').value = c.adresse_depart || '';
        document.getElementById('adresse_arrivee').value = c.adresse_arrivee || '';
        placerDepart(parseFloat(c.lat_depart), parseFloat(c.lng_depart));
        placerArrivee(parseFloat(c.lat_arrivee), parseFloat(c.lng_arrivee));
        const selType = document.getElementById('type_livraison');
        if ([...selType.options].some(o => o.value === String(c.type_livraison_id))) {
            selType.value = String(c.type_livraison_id);
        }
        document.getElementById('express').checked = (c.est_express == 1);
        if (c.instructions) { document.getElementById('instructions').value = c.instructions; }
        const modes = ['especes', 'orange_money', 'mtn_money', 'moov_money', 'wave'];
        if (modes.includes(c.mode_paiement)) {
            document.getElementById('mode_paiement').value = c.mode_paiement;
            document.getElementById('mode_paiement').dispatchEvent(new Event('change'));
        }
        map.fitBounds([[c.lat_depart, c.lng_depart], [c.lat_arrivee, c.lng_arrivee]]);
        estimer();
    } catch (err) {
        document.getElementById('alert-zone').innerHTML =
            `<div class="alert alert-erreur">Impossible de recharger la commande : ${escapeHtml(err.message)}</div>`;
    }
}

initMap();
chargerTypes().then(prechargerReorder);
chargerMoyensTransport();
chargerFavoris();
brancherRechercheRepere('rep-depart', 'sug-depart', 'depart');
brancherRechercheRepere('rep-arrivee', 'sug-arrivee', 'arrivee');
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
