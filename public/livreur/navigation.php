<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('livreur');

$commandeId = (int) ($_GET['commande_id'] ?? 0);

$pageTitle = 'Navigation';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
<script src="/assets/vendor/leaflet/leaflet.js"></script>

<a href="/livreur/dashboard.php" class="text-muted">&larr; Retour</a>
<h1>Navigation</h1>

<div class="grid grid-2">
    <div class="card">
        <div id="infos">Chargement...</div>
        <div id="actions" class="mt-1"></div>
    </div>
    <div class="card">
        <div id="map" style="height:360px;"></div>
        <p class="text-muted mt-1" id="etat-itineraire"></p>
    </div>
</div>

<script>
const COMMANDE_ID = <?= json_encode($commandeId) ?>;
let map, marqueurMoi, marqueurRetrait, marqueurLivraison, traceItineraire;
let maPosition = null;
let commande = null;
let cibleActuelle = null; // {lat,lng} vers laquelle on trace l'itineraire

function initMap() {
    map = L.map('map').setView([7.69, -5.03], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);
}

function icone(couleur) {
    return L.divIcon({
        html: '<div style="background:' + couleur + ';width:16px;height:16px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 4px rgba(0,0,0,.5)"></div>',
        className: '', iconSize: [16, 16], iconAnchor: [8, 8]
    });
}

async function charger() {
    try {
        const res = await Api.get('/api/livreur/order_detail.php?commande_id=' + COMMANDE_ID);
        commande = res.data.commande;
        const c = commande;

        const retrait = c.nom_boutique ? ('Retrait : ' + escapeHtml(c.nom_boutique)) : ('Retrait : ' + escapeHtml(c.adresse_depart));
        document.getElementById('infos').innerHTML =
            '<p><strong>' + escapeHtml(c.reference) + '</strong> &middot; ' + badgeStatut(c.statut) + '</p>'
            + '<p>' + retrait + '</p>'
            + '<p>Livraison : ' + escapeHtml(c.adresse_arrivee) + '</p>'
            + '<p>Client : ' + escapeHtml(c.client_prenom) + ' ' + escapeHtml(c.client_nom)
            + ' &middot; <a href="tel:' + escapeHtml(c.client_telephone) + '">' + escapeHtml(c.client_telephone) + '</a></p>'
            + '<p><strong>Montant :</strong> ' + formatMontant(c.montant_estime) + ' (' + escapeHtml(c.mode_paiement) + ')</p>'
            + (c.instructions ? '<p class="text-muted">Instructions : ' + escapeHtml(c.instructions) + '</p>' : '');

        // Cible : le point de retrait tant que non recupere, sinon la livraison.
        const versLivraison = (c.statut === 'recuperee' || c.statut === 'en_cours');
        cibleActuelle = versLivraison
            ? { lat: parseFloat(c.lat_arrivee), lng: parseFloat(c.lng_arrivee) }
            : { lat: parseFloat(c.lat_depart), lng: parseFloat(c.lng_depart) };

        marqueurRetrait = L.marker([c.lat_depart, c.lng_depart], { icon: icone('#ea6a12') }).addTo(map).bindPopup('Retrait');
        marqueurLivraison = L.marker([c.lat_arrivee, c.lng_arrivee], { icon: icone('#12805c') }).addTo(map).bindPopup('Livraison');
        map.fitBounds([[c.lat_depart, c.lng_depart], [c.lat_arrivee, c.lng_arrivee]], { padding: [40, 40] });

        const dest = versLivraison
            ? c.lat_arrivee + ',' + c.lng_arrivee
            : c.lat_depart + ',' + c.lng_depart;
        document.getElementById('actions').innerHTML =
            '<a class="btn btn-block" target="_blank" rel="noopener" '
            + 'href="https://www.google.com/maps/dir/?api=1&destination=' + dest + '&travelmode=driving">'
            + '🧭 Ouvrir dans l\'application de navigation</a>'
            + '<p class="text-muted mt-1">Ouvre Google Maps / Plans pour la navigation vocale en temps reel.</p>';

        demarrerGeoloc();
    } catch (err) {
        document.getElementById('infos').innerHTML = '<div class="alert alert-erreur">' + escapeHtml(err.message) + '</div>';
    }
}

function demarrerGeoloc() {
    if (!navigator.geolocation) {
        document.getElementById('etat-itineraire').textContent = 'Geolocalisation non disponible.';
        return;
    }
    navigator.geolocation.watchPosition(function (pos) {
        maPosition = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        if (!marqueurMoi) {
            marqueurMoi = L.marker([maPosition.lat, maPosition.lng], { icon: icone('#2563eb') }).addTo(map).bindPopup('Moi');
        } else {
            marqueurMoi.setLatLng([maPosition.lat, maPosition.lng]);
        }
        // Envoi de la position au serveur (suivi cote client).
        Api.post('/api/livreur/update_position.php', {
            latitude: maPosition.lat, longitude: maPosition.lng, commande_id: COMMANDE_ID
        }).catch(function () {});
        tracerItineraire();
    }, function () {
        document.getElementById('etat-itineraire').textContent = 'Autorisez la geolocalisation pour afficher l\'itineraire.';
    }, { enableHighAccuracy: true, maximumAge: 5000 });
}

function tracerItineraire() {
    if (!maPosition || !cibleActuelle) { return; }
    const url = 'https://router.project-osrm.org/route/v1/driving/'
        + maPosition.lng + ',' + maPosition.lat + ';' + cibleActuelle.lng + ',' + cibleActuelle.lat
        + '?overview=full&geometries=geojson';

    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
        if (!data.routes || !data.routes.length) { throw new Error('no route'); }
        const route = data.routes[0];
        const coords = route.geometry.coordinates.map(function (p) { return [p[1], p[0]]; });
        if (traceItineraire) { map.removeLayer(traceItineraire); }
        traceItineraire = L.polyline(coords, { color: '#2563eb', weight: 5, opacity: 0.7 }).addTo(map);
        const km = (route.distance / 1000).toFixed(1);
        const min = Math.round(route.duration / 60);
        document.getElementById('etat-itineraire').textContent =
            'Itineraire : ' + km + ' km, environ ' + min + ' min.';
    }).catch(function () {
        // Repli : trait direct si le service de routage est indisponible.
        if (traceItineraire) { map.removeLayer(traceItineraire); }
        traceItineraire = L.polyline([[maPosition.lat, maPosition.lng], [cibleActuelle.lat, cibleActuelle.lng]],
            { color: '#2563eb', weight: 3, dashArray: '6' }).addTo(map);
        document.getElementById('etat-itineraire').textContent = 'Trace direct (service d\'itineraire indisponible).';
    });
}

initMap();
charger();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
