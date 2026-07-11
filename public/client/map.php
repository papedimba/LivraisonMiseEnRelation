<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client', 'livreur', 'commercant');

$pageTitle = 'Carte collaborative';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
<script src="/assets/vendor/leaflet/leaflet.js"></script>

<div class="flex-between">
    <div>
        <h1>Carte collaborative</h1>
        <p class="subtitle">Ajoutez et confirmez les points de repere de votre quartier pour aider les livreurs.</p>
    </div>
</div>

<div class="card mb-1">
    <div class="flex-between">
        <div class="form-group" style="margin:0;min-width:200px;">
            <label for="filtre-cat">Categorie</label>
            <select id="filtre-cat"><option value="">Toutes</option></select>
        </div>
        <div style="align-self:flex-end;">
            <button id="btn-mode-ajout" class="btn btn-secondaire">📍 Ajouter un point</button>
        </div>
    </div>
    <div class="mt-1" style="display:flex;flex-direction:column;gap:0.35rem;">
        <label style="display:inline-flex;align-items:center;gap:0.4rem;">
            <input type="checkbox" id="toggle-routes" style="width:auto;display:inline-block;">
            🛣️ Afficher les routes parcourues par les livreurs
        </label>
        <label style="display:inline-flex;align-items:center;gap:0.4rem;">
            <input type="checkbox" id="toggle-heatmap" style="width:auto;display:inline-block;">
            🔥 Zones les plus desservies (heatmap)
        </label>
    </div>
    <div id="aide-ajout" class="alert alert-info hidden" style="margin-top:0.75rem;">
        Cliquez sur la carte a l'emplacement exact du point de repere.
    </div>
    <div id="alert-zone" class="mt-1"></div>
</div>

<div id="map" style="height:520px;"></div>
<p class="text-muted mt-1" id="legende" style="font-size:0.82rem;"></p>

<!-- Formulaire d'ajout (modale) -->
<div id="modal-point" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-head">
            <h2>Nouveau point de repere</h2>
            <button type="button" class="modal-close" id="pt-fermer">&times;</button>
        </div>
        <div id="pt-alert"></div>
        <div class="form-group">
            <label for="pt-nom">Nom du repere</label>
            <input type="text" id="pt-nom" maxlength="150" placeholder="Ex: Pharmacie du Rond-point, Ecole Kennedy...">
        </div>
        <div class="form-group">
            <label for="pt-cat">Categorie</label>
            <select id="pt-cat"></select>
        </div>
        <div class="form-group">
            <label for="pt-desc">Description (optionnel)</label>
            <textarea id="pt-desc" maxlength="500" placeholder="Precisions utiles pour retrouver le lieu"></textarea>
        </div>
        <div class="flex">
            <button type="button" id="pt-envoyer" class="btn">Envoyer</button>
            <button type="button" id="pt-annuler" class="btn btn-ghost">Annuler</button>
        </div>
    </div>
</div>

<script>
const CATS = {
    repere: { label: 'Repere', couleur: '#f26522' },
    commerce: { label: 'Commerce', couleur: '#2e9e4b' },
    carrefour: { label: 'Carrefour', couleur: '#d8551a' },
    quartier: { label: 'Quartier', couleur: '#7a5cff' },
    sante: { label: 'Sante', couleur: '#e5484d' },
    education: { label: 'Education', couleur: '#3b82c4' },
    service_public: { label: 'Service public', couleur: '#0e7c86' },
    autre: { label: 'Autre', couleur: '#6b6f76' },
};

let map, modeAjout = false, pointChoisi = null;
let marqueurs = {};
let routesLayer = null; // couche des routes parcourues par les livreurs
let heatLayer = null;   // couche heatmap des zones desservies

function initMap() {
    map = L.map('map').setView([7.6900, -5.0300], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
    }).addTo(map);
    routesLayer = L.layerGroup().addTo(map);
    heatLayer = L.layerGroup().addTo(map);
    ajouterBoutonMaPosition(map);
    map.on('moveend', () => { chargerPoints(); chargerRoutes(); chargerHeatmap(); });
    map.on('click', (e) => {
        if (!modeAjout) { return; }
        pointChoisi = e.latlng;
        ouvrirModal();
    });

    // Remplit les selecteurs de categorie.
    const opts = Object.entries(CATS).map(([k, v]) => `<option value="${k}">${v.label}</option>`).join('');
    document.getElementById('pt-cat').innerHTML = opts;
    document.getElementById('filtre-cat').innerHTML = '<option value="">Toutes</option>' + opts;
    document.getElementById('legende').innerHTML = Object.values(CATS)
        .map(v => `<span style="color:${v.couleur};">●</span> ${v.label}`).join(' &nbsp; ');
}

function popupHtml(p) {
    const c = CATS[p.categorie] || CATS.autre;
    return `<strong>${escapeHtml(p.nom)}</strong><br>`
        + `<span class="tag" style="background:${c.couleur}22;color:${c.couleur};">${c.label}</span><br>`
        + (p.description ? `${escapeHtml(p.description)}<br>` : '')
        + `<div style="margin-top:0.4rem;">👍 <span data-c="${p.id}">${p.confirmations}</span> &nbsp; 🚩 <span data-s="${p.id}">${p.signalements}</span></div>`
        + `<div class="flex" style="margin-top:0.4rem;gap:0.35rem;">`
        + `<button class="btn btn-sm" onclick="voter(${p.id}, 'confirme')">Confirmer</button>`
        + `<button class="btn btn-sm btn-ghost" onclick="voter(${p.id}, 'signale')">Signaler</button></div>`;
}

async function chargerPoints() {
    const b = map.getBounds();
    const bbox = [b.getSouth(), b.getWest(), b.getNorth(), b.getEast()].join(',');
    const cat = document.getElementById('filtre-cat').value;
    try {
        const res = await Api.get('/api/public/map_points.php?bbox=' + encodeURIComponent(bbox)
            + (cat ? '&categorie=' + encodeURIComponent(cat) : ''));
        const points = res.data.points || [];
        const ids = new Set(points.map(p => p.id));
        Object.keys(marqueurs).forEach(id => {
            if (!ids.has(Number(id))) { map.removeLayer(marqueurs[id]); delete marqueurs[id]; }
        });
        points.forEach(p => {
            const c = CATS[p.categorie] || CATS.autre;
            if (marqueurs[p.id]) {
                marqueurs[p.id].setLatLng([p.latitude, p.longitude]).getPopup()?.setContent(popupHtml(p));
            } else {
                marqueurs[p.id] = L.circleMarker([p.latitude, p.longitude], {
                    radius: 8, weight: 2, color: '#fff', fillColor: c.couleur, fillOpacity: 0.9,
                }).addTo(map).bindPopup(popupHtml(p));
            }
        });
    } catch (err) { /* silencieux */ }
}

// Routes parcourues par les livreurs (agregees depuis les livraisons terminees).
async function chargerRoutes() {
    if (!document.getElementById('toggle-routes').checked) { return; }
    const b = map.getBounds();
    const bbox = [b.getSouth(), b.getWest(), b.getNorth(), b.getEast()].join(',');
    try {
        const res = await Api.get('/api/public/routes.php?bbox=' + encodeURIComponent(bbox));
        routesLayer.clearLayers();
        (res.data.routes || []).forEach(rt => {
            // Trait plein = trace calee sur les rues (OSRM) ; pointille = trace GPS brute.
            const style = rt.cale
                ? { color: '#3b82c4', weight: 4, opacity: 0.65 }
                : { color: '#3b82c4', weight: 3, opacity: 0.45, dashArray: '5,6' };
            L.polyline(rt.points, style).addTo(routesLayer);
        });
    } catch (err) { /* silencieux */ }
}

document.getElementById('toggle-routes').addEventListener('change', (e) => {
    if (e.target.checked) {
        chargerRoutes();
    } else if (routesLayer) {
        routesLayer.clearLayers();
    }
});

// Heatmap des zones les plus desservies : cellules coloriees par intensite
// (teinte unique, opacite et rayon croissant avec le nombre de livraisons).
async function chargerHeatmap() {
    if (!document.getElementById('toggle-heatmap').checked) { return; }
    try {
        const res = await Api.get('/api/public/heatmap.php');
        heatLayer.clearLayers();
        const max = res.data.poids_max || 1;
        (res.data.cellules || []).forEach(c => {
            const intensite = c.poids / max;           // 0..1
            const rayon = 14 + intensite * 26;          // px
            L.circleMarker([c.lat, c.lng], {
                radius: rayon,
                stroke: false,
                fillColor: '#f26522',
                fillOpacity: 0.15 + intensite * 0.5,
            }).addTo(heatLayer).bindPopup(c.poids + ' livraison(s) dans cette zone');
        });
    } catch (err) { /* silencieux */ }
}

document.getElementById('toggle-heatmap').addEventListener('change', (e) => {
    if (e.target.checked) {
        chargerHeatmap();
    } else if (heatLayer) {
        heatLayer.clearLayers();
    }
});

async function voter(pointId, type) {
    try {
        const res = await Api.post('/api/map/point_vote.php', { point_id: pointId, type: type });
        const cSpan = document.querySelector(`[data-c="${pointId}"]`);
        const sSpan = document.querySelector(`[data-s="${pointId}"]`);
        if (cSpan) { cSpan.textContent = res.data.confirmations; }
        if (sSpan) { sSpan.textContent = res.data.signalements; }
        if (res.data.remis_en_moderation && marqueurs[pointId]) {
            map.removeLayer(marqueurs[pointId]); delete marqueurs[pointId];
        }
    } catch (err) { alert(err.message); }
}

function ouvrirModal() { document.getElementById('modal-point').classList.remove('hidden'); }
function fermerModal() { document.getElementById('modal-point').classList.add('hidden'); }

document.getElementById('btn-mode-ajout').addEventListener('click', () => {
    modeAjout = !modeAjout;
    document.getElementById('aide-ajout').classList.toggle('hidden', !modeAjout);
    document.getElementById('btn-mode-ajout').classList.toggle('btn-secondaire', !modeAjout);
    document.getElementById('map').style.cursor = modeAjout ? 'crosshair' : '';
});
document.getElementById('pt-fermer').addEventListener('click', fermerModal);
document.getElementById('pt-annuler').addEventListener('click', fermerModal);
document.getElementById('filtre-cat').addEventListener('change', chargerPoints);

document.getElementById('pt-envoyer').addEventListener('click', async () => {
    const zone = document.getElementById('pt-alert');
    const nom = document.getElementById('pt-nom').value.trim();
    if (!nom || !pointChoisi) {
        zone.innerHTML = '<div class="alert alert-erreur">Indiquez un nom et un emplacement.</div>';
        return;
    }
    try {
        const res = await Api.post('/api/map/point_add.php', {
            nom: nom,
            categorie: document.getElementById('pt-cat').value,
            description: document.getElementById('pt-desc').value.trim(),
            latitude: pointChoisi.lat,
            longitude: pointChoisi.lng,
        });
        fermerModal();
        document.getElementById('pt-nom').value = '';
        document.getElementById('pt-desc').value = '';
        modeAjout = false;
        document.getElementById('aide-ajout').classList.add('hidden');
        document.getElementById('map').style.cursor = '';
        document.getElementById('alert-zone').innerHTML = `<div class="alert alert-succes">${escapeHtml(res.message)}</div>`;
        chargerPoints();
    } catch (err) {
        zone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

initMap();
chargerPoints();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
