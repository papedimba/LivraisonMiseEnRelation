<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client');

$commercantId = (int) ($_GET['id'] ?? 0);

$pageTitle = 'Boutique';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
<script src="/assets/vendor/leaflet/leaflet.js"></script>
<script>window.L||document.write('<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><scr'+'ipt src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"><\/scr'+'ipt>');</script>

<a href="/client/shops.php" class="text-muted">&larr; Toutes les boutiques</a>
<div id="entete-boutique"><h1>Chargement...</h1></div>

<div class="grid grid-2">
    <div class="card">
        <h2>Produits</h2>
        <div id="liste-produits"><p class="text-muted">Chargement...</p></div>
    </div>

    <div class="card">
        <h2>Mon panier</h2>
        <div id="panier"><p class="text-muted">Votre panier est vide.</p></div>
        <hr>
        <div id="alert-zone"></div>

        <div class="form-group">
            <label for="type_livraison">Type de livraison</label>
            <select id="type_livraison"></select>
        </div>
        <div class="form-group">
            <label>Adresse de livraison <span class="text-muted">(marqueur vert)</span></label>
            <div class="repere-search">
                <input type="text" id="adresse_arrivee" autocomplete="off" placeholder="🔎 Repere, adresse, ou cliquez sur la carte">
                <div class="repere-suggestions hidden" id="sug-arrivee"></div>
            </div>
        </div>
        <div id="map" style="height:220px;"></div>
        <p class="text-muted mt-1" id="hint-depart"></p>

        <div class="form-group mt-1">
            <label for="mode_paiement">Mode de paiement</label>
            <select id="mode_paiement">
                <option value="especes">Especes a la livraison</option>
                <option value="orange_money">Orange Money</option>
                <option value="mtn_money">MTN Mobile Money</option>
                <option value="moov_money">Moov Money</option>
                <option value="wave">Wave</option>
            </select>
        </div>
        <div class="form-group hidden" id="zone-numero">
            <label for="numero_paiement">Numero Mobile Money</label>
            <input type="text" id="numero_paiement" placeholder="07 00 00 00 00">
        </div>

        <button type="button" class="btn btn-block" onclick="commander()">Commander</button>
    </div>
</div>

<script>
var COMMERCANT_ID = <?= json_encode($commercantId) ?>;
var panier = {};       // produit_id -> { produit, quantite }
var produitsMap = {};  // produit_id -> produit
var map, markerDepart, markerArrivee;
var coordDepart = null, coordArrivee = null;
var departFixe = false;

function initMap(latShop, lngShop) {
    map = L.map('map').setView([latShop || 7.69, lngShop || -5.03], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    if (latShop && lngShop) {
        coordDepart = { lat: latShop, lng: lngShop };
        markerDepart = L.marker([latShop, lngShop], { icon: pinIcon(PIN_ROUGE) }).addTo(map).bindPopup('Boutique (depart)');
        departFixe = true;
        document.getElementById('hint-depart').textContent = 'Depart : la boutique (marqueur rouge). Cliquez sur la carte, recherchez ou glissez le marqueur vert pour definir le lieu de livraison.';
    } else {
        document.getElementById('hint-depart').textContent = 'La boutique n\'a pas de position enregistree : 1er clic = depart (rouge), 2e clic = livraison (vert).';
    }

    map.on('click', function (e) {
        if (!departFixe && !coordDepart) {
            coordDepart = e.latlng;
            markerDepart = L.marker(e.latlng, { icon: pinIcon(PIN_ROUGE) }).addTo(map).bindPopup('Depart');
        } else {
            placerArrivee(e.latlng.lat, e.latlng.lng);
            remplirAdresseArrivee(e.latlng.lat, e.latlng.lng);
        }
    });

    ajouterBoutonMaPosition(map);
}

// Place / deplace le marqueur de livraison (vert, draggable). Au relachement,
// on renseigne l'adresse par geocodage inverse.
function placerArrivee(lat, lng) {
    coordArrivee = L.latLng(lat, lng);
    if (!markerArrivee) {
        markerArrivee = L.marker(coordArrivee, { draggable: true, icon: pinIcon(PIN_VERT) }).addTo(map).bindPopup('Livraison (glissez pour ajuster)');
        markerArrivee.on('dragend', function () {
            var ll = markerArrivee.getLatLng();
            coordArrivee = ll;
            remplirAdresseArrivee(ll.lat, ll.lng);
        });
    } else {
        markerArrivee.setLatLng(coordArrivee);
    }
}

// Geocodage inverse : renseigne le champ adresse de livraison a partir des
// coordonnees (best-effort, avec repli sur les coordonnees).
async function remplirAdresseArrivee(lat, lng) {
    document.getElementById('adresse_arrivee').value = await reverseGeocode(lat, lng);
}

async function chargerBoutique() {
    try {
        const res = await Api.get('/api/public/shop.php?id=' + COMMERCANT_ID);
        const b = res.data.boutique;
        document.getElementById('entete-boutique').innerHTML =
            '<h1>' + escapeHtml(b.nom_boutique) + '</h1>' +
            '<p class="subtitle">' + escapeHtml(b.categorie) + ' &middot; Note ' + b.note_moyenne + '/5' +
            (b.adresse ? ' &middot; ' + escapeHtml(b.adresse) : '') + '</p>';

        const zone = document.getElementById('liste-produits');
        const produits = res.data.produits;
        if (produits.length === 0) {
            zone.innerHTML = '<p class="text-muted">Cette boutique n\'a pas encore de produit.</p>';
        } else {
            zone.innerHTML = produits.map(function (p) {
                produitsMap[p.id] = p;
                return '<div class="flex-between" style="border-bottom:1px solid var(--bordure);padding:0.5rem 0;">'
                    + '<div><strong>' + escapeHtml(p.nom) + '</strong><br>'
                    + '<span class="text-muted">' + (p.description ? escapeHtml(p.description) + ' &middot; ' : '') + formatMontant(p.prix) + '</span></div>'
                    + '<button class="btn btn-sm" onclick="ajouterAuPanier(' + p.id + ')">Ajouter</button>'
                    + '</div>';
            }).join('');
        }

        // La carte est isolee : meme si Leaflet echoue a charger, la boutique et
        // le panier restent utilisables.
        try {
            initMap(b.latitude ? parseFloat(b.latitude) : null, b.longitude ? parseFloat(b.longitude) : null);
            brancherRechercheAdresse(
                document.getElementById('adresse_arrivee'),
                document.getElementById('sug-arrivee'),
                {
                    getCenter: function () { return map ? map.getCenter() : null; },
                    onSelect: function (lat, lng) { placerArrivee(lat, lng); map.panTo([lat, lng]); },
                }
            );
        } catch (e) {
            document.getElementById('hint-depart').textContent =
                'La carte n\'a pas pu se charger. Verifiez votre connexion et rechargez la page.';
        }
    } catch (err) {
        document.getElementById('entete-boutique').innerHTML = '<h1>Boutique indisponible</h1><p class="text-muted">' + escapeHtml(err.message) + '</p>';
    }
}

function ajouterAuPanier(id) {
    if (!panier[id]) {
        panier[id] = { produit: produitsMap[id], quantite: 0 };
    }
    panier[id].quantite++;
    afficherPanier();
}

function retirerDuPanier(id) {
    if (panier[id]) {
        panier[id].quantite--;
        if (panier[id].quantite <= 0) {
            delete panier[id];
        }
    }
    afficherPanier();
}

function afficherPanier() {
    var zone = document.getElementById('panier');
    var ids = Object.keys(panier);
    if (ids.length === 0) {
        zone.innerHTML = '<p class="text-muted">Votre panier est vide.</p>';
        return;
    }
    var total = 0;
    var html = '';
    ids.forEach(function (id) {
        var ligne = panier[id];
        var sousTotal = ligne.produit.prix * ligne.quantite;
        total += parseFloat(sousTotal);
        html += '<div class="flex-between" style="padding:0.3rem 0;">'
            + '<span>' + escapeHtml(ligne.produit.nom) + '</span>'
            + '<span>'
            + '<button class="btn btn-sm btn-ghost" onclick="retirerDuPanier(' + id + ')">-</button> '
            + ligne.quantite + ' '
            + '<button class="btn btn-sm btn-ghost" onclick="ajouterAuPanier(' + id + ')">+</button> '
            + formatMontant(sousTotal) + '</span></div>';
    });
    html += '<div class="flex-between mt-1"><strong>Total produits</strong><strong>' + formatMontant(total) + '</strong></div>';
    html += '<p class="text-muted">+ frais de livraison selon la distance</p>';
    zone.innerHTML = html;
}

async function chargerTypes() {
    const res = await Api.get('/api/public/delivery_types.php');
    const select = document.getElementById('type_livraison');
    select.innerHTML = res.data.types.map(function (t) {
        return '<option value="' + t.id + '">' + escapeHtml(t.nom) + '</option>';
    }).join('');
}

document.getElementById('mode_paiement').addEventListener('change', function () {
    var mm = ['orange_money', 'mtn_money', 'moov_money', 'wave'].indexOf(this.value) !== -1;
    document.getElementById('zone-numero').classList.toggle('hidden', !mm);
});

async function commander() {
    var alertZone = document.getElementById('alert-zone');
    function erreur(msg) {
        alertZone.innerHTML = '<div class="alert alert-erreur">' + escapeHtml(msg) + '</div>';
    }

    var ids = Object.keys(panier);
    if (ids.length === 0) { erreur('Votre panier est vide.'); return; }
    if (!coordDepart) { erreur('Veuillez definir le point de depart sur la carte.'); return; }
    if (!coordArrivee) { erreur('Veuillez definir le lieu de livraison (recherche ou clic sur la carte).'); return; }
    // Le libelle d'adresse est un complement : les coordonnees suffisent. Si le
    // champ est vide, on genere un libelle base sur les coordonnees.
    var adresseArrivee = document.getElementById('adresse_arrivee').value.trim()
        || ('Point sur la carte (' + coordArrivee.lat.toFixed(5) + ', ' + coordArrivee.lng.toFixed(5) + ')');

    var produits = ids.map(function (id) {
        return { produit_id: parseInt(id, 10), quantite: panier[id].quantite };
    });

    var payload = {
        type_livraison_id: document.getElementById('type_livraison').value,
        commercant_id: COMMERCANT_ID,
        produits: produits,
        adresse_depart: 'Boutique',
        lat_depart: coordDepart.lat,
        lng_depart: coordDepart.lng,
        adresse_arrivee: adresseArrivee,
        lat_arrivee: coordArrivee.lat,
        lng_arrivee: coordArrivee.lng,
        mode_paiement: document.getElementById('mode_paiement').value,
        numero_paiement: document.getElementById('numero_paiement').value.trim()
    };

    try {
        const res = await Api.post('/api/client/orders_create.php', payload);
        var data = res.data;
        if (data.redirect_url) {
            window.location.href = data.redirect_url;
        } else if (data.instructions && data.statut_paiement === 'en_attente' && payload.mode_paiement !== 'especes') {
            window.location.href = '/client/payment_return.php?ref=' + encodeURIComponent(data.reference);
        } else {
            window.location.href = '/client/track.php?ref=' + encodeURIComponent(data.reference);
        }
    } catch (err) {
        erreur(err.message);
    }
}

chargerTypes();
chargerBoutique();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
