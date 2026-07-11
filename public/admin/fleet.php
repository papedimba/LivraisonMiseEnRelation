<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Suivi de la flotte';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
<script src="/assets/vendor/leaflet/leaflet.js"></script>

<div class="flex-between">
    <div>
        <h1>Suivi de la flotte</h1>
        <p class="subtitle">Position et statut en temps reel de vos livreurs actifs.</p>
    </div>
    <div class="text-muted" id="maj-info" style="font-size:0.82rem;"></div>
</div>

<div class="grid grid-4 mb-1">
    <div class="stat-tile"><div class="valeur" id="c-total">0</div><div class="label">Actifs</div></div>
    <div class="stat-tile"><div class="valeur" id="c-en_route" style="color:var(--primaire);">0</div><div class="label">🛵 En route</div></div>
    <div class="stat-tile"><div class="valeur" id="c-libre" style="color:var(--accent);">0</div><div class="label">🟢 Libres</div></div>
    <div class="stat-tile"><div class="valeur" id="c-pause" style="color:var(--attente);">0</div><div class="label">⏸️ En pause</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="flex-between mb-1">
            <h2 style="margin:0;">Carte</h2>
            <div class="flex" id="filtres" style="gap:0.35rem;">
                <button class="btn btn-sm filtre actif" data-f="tous">Tous</button>
                <button class="btn btn-ghost btn-sm filtre" data-f="en_route">En route</button>
                <button class="btn btn-ghost btn-sm filtre" data-f="libre">Libres</button>
                <button class="btn btn-ghost btn-sm filtre" data-f="pause">Pause</button>
            </div>
        </div>
        <div id="map" style="height:460px;"></div>
        <p class="text-muted mt-1" style="font-size:0.8rem;">
            <span style="color:var(--primaire);">●</span> En route &nbsp;
            <span style="color:var(--accent);">●</span> Libre &nbsp;
            <span style="color:var(--attente);">●</span> En pause &nbsp;
            (contour clair = position non actualisee)
        </p>
    </div>
    <div class="card">
        <h2 style="margin-top:0;">Livreurs</h2>
        <div id="liste-flotte" class="table-wrap"><p class="text-muted">Chargement...</p></div>
    </div>
</div>

<script>
const COULEURS = { en_route: '#f26522', libre: '#2e9e4b', pause: '#f79009' };
const LIBELLES = { en_route: 'En route', libre: 'Libre', pause: 'En pause' };
let map;
let marqueurs = {};      // id livreur -> circleMarker
let filtre = 'tous';
let derniereFlotte = [];

function initMap() {
    map = L.map('map').setView([7.6900, -5.0300], 13); // Bouake
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
    }).addTo(map);
}

function popupHtml(l) {
    return `<strong>${escapeHtml(l.nom_complet)}</strong><br>`
        + `<span class="tag tag-info">${LIBELLES[l.statut]}</span><br>`
        + `📞 ${escapeHtml(l.telephone)}<br>`
        + `🛵 ${escapeHtml(l.type_vehicule)} · ⭐ ${l.note_moyenne.toFixed(1)} · ${l.nombre_courses} courses`
        + (l.course_reference ? `<br>Course : <strong>${escapeHtml(l.course_reference)}</strong> → ${escapeHtml(l.course_arrivee || '')}` : '');
}

function dessinerMarqueurs() {
    const visibles = derniereFlotte.filter(l =>
        l.latitude !== null && l.longitude !== null && (filtre === 'tous' || l.statut === filtre));
    const idsVisibles = new Set(visibles.map(l => l.id));

    // Retire les marqueurs qui ne sont plus visibles.
    Object.keys(marqueurs).forEach(id => {
        if (!idsVisibles.has(Number(id))) {
            map.removeLayer(marqueurs[id]);
            delete marqueurs[id];
        }
    });

    const points = [];
    visibles.forEach(l => {
        points.push([l.latitude, l.longitude]);
        const couleur = COULEURS[l.statut];
        if (marqueurs[l.id]) {
            marqueurs[l.id].setLatLng([l.latitude, l.longitude]);
            marqueurs[l.id].setStyle({ color: l.position_fraiche ? couleur : '#ffffff', fillColor: couleur });
            marqueurs[l.id].getPopup()?.setContent(popupHtml(l));
        } else {
            marqueurs[l.id] = L.circleMarker([l.latitude, l.longitude], {
                radius: 9, weight: 3,
                color: l.position_fraiche ? couleur : '#ffffff',
                fillColor: couleur, fillOpacity: 0.9,
            }).addTo(map).bindPopup(popupHtml(l));
        }
    });

    // Cadrage automatique au premier rendu s'il y a des points.
    if (points.length && !map._flotteCadree) {
        map.fitBounds(points, { padding: [40, 40], maxZoom: 15 });
        map._flotteCadree = true;
    }
}

function rendreListe() {
    const zone = document.getElementById('liste-flotte');
    const liste = filtre === 'tous' ? derniereFlotte : derniereFlotte.filter(l => l.statut === filtre);
    if (liste.length === 0) {
        zone.innerHTML = '<p class="text-muted">Aucun livreur pour ce filtre.</p>';
        return;
    }
    zone.innerHTML = `<table><thead><tr><th>Livreur</th><th>Statut</th><th>Position</th></tr></thead><tbody>`
        + liste.map(l => `
            <tr${(l.latitude !== null ? ` style="cursor:pointer;" data-id="${l.id}"` : '')}>
                <td><strong>${escapeHtml(l.nom_complet)}</strong><br><span class="text-muted">${escapeHtml(l.telephone)}</span></td>
                <td><span class="tag" style="background:${COULEURS[l.statut]}22;color:${COULEURS[l.statut]};">${LIBELLES[l.statut]}</span>
                    ${l.course_reference ? `<br><span class="text-muted">${escapeHtml(l.course_reference)}</span>` : ''}</td>
                <td>${l.latitude === null ? '<span class="text-muted">inconnue</span>' : (l.position_fraiche ? 'a jour' : '<span class="text-muted">ancienne</span>')}</td>
            </tr>`).join('')
        + `</tbody></table>`;

    zone.querySelectorAll('tr[data-id]').forEach(tr => {
        tr.addEventListener('click', () => {
            const l = derniereFlotte.find(x => x.id === Number(tr.dataset.id));
            if (l && l.latitude !== null) {
                map.setView([l.latitude, l.longitude], 16);
                marqueurs[l.id]?.openPopup();
            }
        });
    });
}

async function charger() {
    try {
        const res = await Api.get('/api/admin/fleet.php');
        derniereFlotte = res.data.livreurs;
        document.getElementById('c-total').textContent = res.data.total;
        document.getElementById('c-en_route').textContent = res.data.compte.en_route;
        document.getElementById('c-libre').textContent = res.data.compte.libre;
        document.getElementById('c-pause').textContent = res.data.compte.pause;
        document.getElementById('maj-info').textContent = 'Mis a jour a ' + new Date().toLocaleTimeString('fr-FR');
        dessinerMarqueurs();
        rendreListe();
    } catch (err) {
        document.getElementById('maj-info').textContent = 'Erreur de chargement';
    }
}

document.querySelectorAll('.filtre').forEach(btn => {
    btn.addEventListener('click', () => {
        filtre = btn.dataset.f;
        document.querySelectorAll('.filtre').forEach(b => {
            b.classList.toggle('actif', b === btn);
            b.classList.toggle('btn-ghost', b !== btn);
        });
        dessinerMarqueurs();
        rendreListe();
    });
});

initMap();
charger();
setInterval(charger, 10000); // rafraichissement automatique
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
