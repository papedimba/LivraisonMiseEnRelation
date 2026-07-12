<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('commercant');

$pageTitle = 'Ma boutique';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
<script src="/assets/vendor/leaflet/leaflet.js"></script>
<script>window.L||document.write('<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><scr'+'ipt src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"><\/scr'+'ipt>');</script>

<h1>Ma boutique</h1>
<div id="notif-statut" class="hidden mb-1"></div>
<div id="alert-zone"></div>

<div class="card">
    <form id="store-form">
        <div class="grid grid-2">
            <div class="form-group">
                <label for="nom_boutique">Nom de la boutique</label>
                <input type="text" id="nom_boutique">
            </div>
            <div class="form-group">
                <label for="categorie">Categorie</label>
                <select id="categorie">
                    <option value="restaurant">Restaurant</option>
                    <option value="maquis">Maquis</option>
                    <option value="supermarche">Supermarche</option>
                    <option value="pharmacie">Pharmacie</option>
                    <option value="boutique">Boutique</option>
                    <option value="fleuriste">Fleuriste</option>
                    <option value="autre">Autre</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="adresse">Adresse</label>
            <input type="text" id="adresse">
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description"></textarea>
        </div>

        <div class="form-group">
            <label>Position de la boutique
                <span class="text-muted">(cliquez sur la carte ou utilisez votre position actuelle)</span>
            </label>
            <div class="flex mb-1">
                <button type="button" class="btn btn-sm btn-ghost" onclick="maPosition()">📍 Ma position actuelle</button>
                <span id="coords-affichage" class="text-muted"></span>
            </div>
            <div id="map" style="height:280px;"></div>
        </div>

        <div class="flex">
            <span id="statut-badge"></span>
        </div>
        <button type="submit" class="btn mt-1">Enregistrer</button>
    </form>
</div>

<script>
let map, marker;
let position = { lat: null, lng: null };

function initMap(lat, lng) {
    const centre = (lat && lng) ? [lat, lng] : [7.6900, -5.0300];
    map = L.map('map').setView(centre, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    if (lat && lng) {
        placerMarqueur(lat, lng);
    }

    map.on('click', (e) => placerMarqueur(e.latlng.lat, e.latlng.lng));
}

function placerMarqueur(lat, lng) {
    position = { lat: lat, lng: lng };
    if (!marker) {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', () => {
            const p = marker.getLatLng();
            position = { lat: p.lat, lng: p.lng };
            afficherCoords();
        });
    } else {
        marker.setLatLng([lat, lng]);
    }
    afficherCoords();
}

function afficherCoords() {
    document.getElementById('coords-affichage').textContent =
        position.lat ? ('Position : ' + position.lat.toFixed(5) + ', ' + position.lng.toFixed(5)) : '';
}

function maPosition() {
    if (!navigator.geolocation) {
        alert('La geolocalisation n\'est pas disponible sur cet appareil.');
        return;
    }
    navigator.geolocation.getCurrentPosition(function (pos) {
        placerMarqueur(pos.coords.latitude, pos.coords.longitude);
        map.setView([pos.coords.latitude, pos.coords.longitude], 16);
    }, function () {
        alert('Impossible de recuperer votre position. Autorisez la geolocalisation.');
    });
}

async function chargerBoutique() {
    const res = await Api.get('/api/commercant/store_profile.php');
    const b = res.data.boutique;
    document.getElementById('nom_boutique').value = b.nom_boutique || '';
    document.getElementById('categorie').value = b.categorie || 'boutique';
    document.getElementById('adresse').value = b.adresse || '';
    document.getElementById('description').value = b.description || '';
    const statuts = { valide: ['Boutique validee et visible', 'tag-succes'], en_attente: ['En attente de validation', 'tag-attente'], rejete: ['Rejetee - contactez le support', 'tag-danger'] };
    const [label, classe] = statuts[b.statut_validation] || ['-', 'tag-info'];
    document.getElementById('statut-badge').innerHTML = `<span class="tag ${classe}">${label}</span>`;

    const lat = b.latitude ? parseFloat(b.latitude) : null;
    const lng = b.longitude ? parseFloat(b.longitude) : null;
    initMap(lat, lng);
}

document.getElementById('store-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertZone = document.getElementById('alert-zone');
    const payload = {
        nom_boutique: document.getElementById('nom_boutique').value.trim(),
        categorie: document.getElementById('categorie').value,
        adresse: document.getElementById('adresse').value.trim(),
        description: document.getElementById('description').value.trim(),
    };
    if (position.lat && position.lng) {
        payload.latitude = position.lat;
        payload.longitude = position.lng;
    }
    try {
        await Api.post('/api/commercant/store_update.php', payload);
        alertZone.innerHTML = '<div class="alert alert-succes">Boutique mise a jour.</div>';
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

chargerBoutique();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
