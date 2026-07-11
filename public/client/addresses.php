<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client');

$pageTitle = 'Mes adresses';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
<script src="/assets/vendor/leaflet/leaflet.js"></script>

<h1>Mes adresses enregistrees</h1>
<p class="subtitle">Enregistrez vos lieux habituels (Maison, Bureau...) pour commander plus vite.</p>

<div class="grid grid-2">
    <div class="card">
        <h2 style="margin-top:0;">Ajouter une adresse</h2>
        <div id="alert-zone"></div>
        <div class="form-group">
            <label for="libelle">Nom</label>
            <input type="text" id="libelle" maxlength="80" placeholder="Ex: Maison, Bureau">
        </div>
        <div class="form-group">
            <label for="adresse">Adresse <span class="text-muted">(cliquez sur la carte pour situer)</span></label>
            <input type="text" id="adresse" placeholder="Ex: Belleville, rue des Jardins">
        </div>
        <div id="map"></div>
        <button id="btn-ajouter" class="btn btn-block mt-1" disabled>Cliquez sur la carte d'abord</button>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Mes adresses</h2>
        <div id="liste-adresses"><p class="text-muted">Chargement...</p></div>
    </div>
</div>

<script>
let map, marker, point = null;

function initMap() {
    map = L.map('map').setView([7.6900, -5.0300], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
    }).addTo(map);
    map.on('click', (e) => {
        point = e.latlng;
        if (!marker) {
            marker = L.marker(e.latlng).addTo(map);
        } else {
            marker.setLatLng(e.latlng);
        }
        document.getElementById('btn-ajouter').disabled = false;
        document.getElementById('btn-ajouter').textContent = 'Enregistrer cette adresse';
    });
}

async function chargerListe() {
    const zone = document.getElementById('liste-adresses');
    try {
        const res = await Api.get('/api/client/addresses_list.php');
        const adresses = res.data.adresses || [];
        if (adresses.length === 0) {
            zone.innerHTML = '<p class="text-muted">Aucune adresse enregistree pour le moment.</p>';
            return;
        }
        zone.innerHTML = adresses.map(a => `
            <div class="flex-between" style="padding:0.6rem 0;border-bottom:1px solid var(--bordure);">
                <div>
                    <strong>📍 ${escapeHtml(a.libelle)}</strong><br>
                    <span class="text-muted">${escapeHtml(a.adresse)}</span>
                </div>
                <button class="btn btn-ghost btn-sm" data-id="${a.id}">Supprimer</button>
            </div>
        `).join('');
        zone.querySelectorAll('button[data-id]').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Supprimer cette adresse ?')) { return; }
                try {
                    await Api.post('/api/client/addresses_delete.php', { id: btn.dataset.id });
                    chargerListe();
                } catch (err) { alert(err.message); }
            });
        });
    } catch (err) {
        zone.innerHTML = '<p class="text-muted">Erreur de chargement.</p>';
    }
}

document.getElementById('btn-ajouter').addEventListener('click', async () => {
    const alertZone = document.getElementById('alert-zone');
    const libelle = document.getElementById('libelle').value.trim();
    const adresse = document.getElementById('adresse').value.trim();
    if (!libelle || !adresse || !point) {
        alertZone.innerHTML = '<div class="alert alert-erreur">Renseignez le nom, l\'adresse et un point sur la carte.</div>';
        return;
    }
    try {
        await Api.post('/api/client/addresses_save.php', {
            libelle: libelle, adresse: adresse,
            latitude: point.lat, longitude: point.lng,
        });
        alertZone.innerHTML = '<div class="alert alert-succes">Adresse enregistree.</div>';
        document.getElementById('libelle').value = '';
        document.getElementById('adresse').value = '';
        chargerListe();
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

initMap();
chargerListe();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
