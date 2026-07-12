/**
 * Comportement partage : cloche de notifications, formatage.
 */
document.addEventListener('DOMContentLoaded', function () {
    const bell = document.getElementById('notif-bell');
    const panel = document.getElementById('notif-panel');
    const countBadge = document.getElementById('notif-count');

    if (!bell || !panel) {
        return;
    }

    let dernieresNotifsVues = null; // ids deja vus, pour detecter les nouvelles

    function notifierNavigateur(notifications) {
        // Notification navigateur in-app : seulement si l'onglet n'est pas au
        // premier plan, si la permission est accordee, et pour les nouvelles
        // notifications non lues uniquement.
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }
        const nonLues = notifications.filter(n => n.lu == 0);
        if (dernieresNotifsVues === null) {
            // Premier chargement : on memorise sans notifier (evite le spam au login).
            dernieresNotifsVues = new Set(nonLues.map(n => n.id));
            return;
        }
        nonLues.forEach(n => {
            if (!dernieresNotifsVues.has(n.id) && document.hidden) {
                try {
                    new Notification(n.titre, { body: n.message });
                } catch (e) {}
            }
            dernieresNotifsVues.add(n.id);
        });
    }

    async function chargerNotifications() {
        try {
            const res = await Api.get('/api/notifications/list.php');
            const { notifications, non_lues } = res.data;

            notifierNavigateur(notifications);

            if (non_lues > 0) {
                countBadge.textContent = non_lues;
                countBadge.classList.remove('hidden');
            } else {
                countBadge.classList.add('hidden');
            }

            panel.innerHTML = notifications.length
                ? notifications.map(n => `
                    <div class="notif-item ${n.lu == 0 ? 'non-lu' : ''}">
                        <div class="titre">${escapeHtml(n.titre)}</div>
                        <div>${escapeHtml(n.message)}</div>
                        <div class="date">${formatDate(n.created_at)}</div>
                    </div>
                `).join('')
                : '<div class="notif-item">Aucune notification pour le moment.</div>';
        } catch (e) {
            panel.innerHTML = '<div class="notif-item">Impossible de charger les notifications.</div>';
        }
    }

    bell.addEventListener('click', async () => {
        const estCache = panel.classList.contains('hidden');
        if (estCache) {
            await chargerNotifications();
            panel.classList.remove('hidden');
            await Api.post('/api/notifications/mark_read.php', {});
            countBadge.classList.add('hidden');
        } else {
            panel.classList.add('hidden');
        }
    });

    document.addEventListener('click', (e) => {
        if (!panel.contains(e.target) && e.target !== bell) {
            panel.classList.add('hidden');
        }
    });

    chargerNotifications();
    setInterval(chargerNotifications, 30000);
});

// Bandeau d'etat des notifications push ("Activer les notifications" /
// bloquees / actives). Rendu uniquement si la page contient un element
// #notif-statut (opt-in par page, ex. tableaux de bord client/livreur/
// commercant). S'appuie sur window.PushNotifications, expose par push.js
// (charge en fin de page) : on attend l'evenement 'load' pour etre sur qu'il
// est disponible.
function libelleNotifStatut(etat) {
    switch (etat) {
        case 'granted': return { icone: '🔔', texte: 'Notifications activees : vous serez alerte meme app fermee.', classe: 'alert-succes', bouton: null };
        case 'denied': return { icone: '🔕', texte: 'Notifications bloquees : vous ne serez alerte que si l\'application est ouverte a l\'ecran.', classe: 'alert-erreur', bouton: 'aide' };
        default: return { icone: '🔔', texte: 'Activez les notifications pour etre alerte en temps reel, meme app fermee.', classe: 'alert-info', bouton: 'activer' };
    }
}

async function afficherStatutNotif() {
    const zone = document.getElementById('notif-statut');
    if (!zone) { return; } // page qui n'affiche pas ce bandeau
    if (!window.PushNotifications) { zone.classList.add('hidden'); return; }

    const etat = window.PushNotifications.statutPermission();
    if (etat === 'non_supporte') { zone.classList.add('hidden'); return; }

    // Fonctionnalite non activee cote serveur (pas de cles VAPID) : rien a proposer.
    const publicKey = await window.PushNotifications.configServeur();
    if (!publicKey) { zone.classList.add('hidden'); return; }

    const info = libelleNotifStatut(etat);
    let html = `<div class="alert ${info.classe}" style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;">
        <span>${info.icone} ${info.texte}</span>`;
    if (info.bouton === 'activer') {
        html += `<button type="button" class="btn btn-sm" id="btn-activer-notifs">Activer les notifications</button>`;
    } else if (info.bouton === 'aide') {
        html += `<button type="button" class="btn btn-sm btn-ghost" id="btn-notifs-aide">Comment debloquer ?</button>`;
    }
    html += `</div><p id="notif-aide" class="text-muted hidden" style="font-size:0.85rem;margin:0.5rem 0 0;"></p>`;
    zone.innerHTML = html;
    zone.classList.remove('hidden');

    document.getElementById('btn-activer-notifs')?.addEventListener('click', async (e) => {
        e.target.disabled = true;
        e.target.textContent = 'Activation...';
        await window.PushNotifications.demanderActivation();
        afficherStatutNotif();
    });
    document.getElementById('btn-notifs-aide')?.addEventListener('click', () => {
        const aide = document.getElementById('notif-aide');
        aide.classList.toggle('hidden');
        aide.textContent = 'Notifications bloquees dans votre navigateur. Pour les reactiver : cliquez sur l\'icone 🔒 ou ⓘ a cote de l\'adresse du site, ouvrez "Autorisations" ou "Notifications", choisissez "Autoriser", puis rechargez cette page. Sur mobile : Parametres du navigateur > Parametres de site > cet appli > Notifications > Autoriser.';
    });
}

window.addEventListener('load', afficherStatutNotif);

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function formatDate(iso) {
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatMontant(montant) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(montant)) + ' FCFA';
}

const STATUT_LABELS = {
    en_attente: { label: 'En attente', classe: 'tag-attente' },
    acceptee: { label: 'Acceptee', classe: 'tag-info' },
    recuperee: { label: 'Recuperee', classe: 'tag-info' },
    en_cours: { label: 'En cours', classe: 'tag-info' },
    livree: { label: 'Livree', classe: 'tag-succes' },
    annulee: { label: 'Annulee', classe: 'tag-danger' },
};

function badgeStatut(statut) {
    const info = STATUT_LABELS[statut] || { label: statut, classe: 'tag-info' };
    return `<span class="tag ${info.classe}">${info.label}</span>`;
}

// Epingle Leaflet coloree (SVG en ligne, sans image externe). A appeler quand
// Leaflet (L) est charge sur la page.
function pinIcon(couleur) {
    const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="40" viewBox="0 0 28 40">'
        + '<path d="M14 0C6.3 0 0 6.3 0 14c0 10.5 14 26 14 26s14-15.5 14-26C28 6.3 21.7 0 14 0z" fill="' + couleur + '"/>'
        + '<circle cx="14" cy="14" r="5.5" fill="#ffffff"/></svg>';
    return L.divIcon({
        html: svg,
        className: 'pin-icon',
        iconSize: [28, 40],
        iconAnchor: [14, 40],
        popupAnchor: [0, -36],
    });
}

// Couleurs de reference des points sur la carte.
const PIN_ROUGE = '#e5484d'; // point de depart
const PIN_VERT = '#2e9e4b';  // point d'arrivee

// Ajoute un bouton "Ma position" sur la carte (facon Google Maps / Yango).
// Au clic : geolocalise, centre la carte et affiche un point bleu avec le
// cercle de precision. Rappel optionnel onLocate(lat, lng, precision).
function ajouterBoutonMaPosition(map, onLocate) {
    let posMarker = null, posCercle = null;

    function localiser(btn) {
        if (!navigator.geolocation) {
            alert('La geolocalisation n\'est pas disponible sur cet appareil.');
            return;
        }
        if (btn) { btn.classList.add('charge'); }
        navigator.geolocation.getCurrentPosition((pos) => {
            const lat = pos.coords.latitude, lng = pos.coords.longitude;
            const precision = pos.coords.accuracy || 0;
            const ll = [lat, lng];
            if (!posMarker) {
                posCercle = L.circle(ll, { radius: precision, color: '#1a73e8', weight: 1, fillColor: '#1a73e8', fillOpacity: 0.12 }).addTo(map);
                posMarker = L.circleMarker(ll, { radius: 7, color: '#ffffff', weight: 3, fillColor: '#1a73e8', fillOpacity: 1 }).addTo(map).bindPopup('Vous etes ici');
            } else {
                posMarker.setLatLng(ll);
                posCercle.setLatLng(ll).setRadius(precision);
            }
            map.setView(ll, 16);
            if (btn) { btn.classList.remove('charge'); }
            if (typeof onLocate === 'function') { onLocate(lat, lng, precision); }
        }, () => {
            if (btn) { btn.classList.remove('charge'); }
            alert('Impossible d\'obtenir votre position. Autorisez la localisation dans votre navigateur.');
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
    }

    const Bouton = L.Control.extend({
        options: { position: 'topright' },
        onAdd: function () {
            const btn = L.DomUtil.create('button', 'btn-ma-position');
            btn.type = 'button';
            btn.innerHTML = '📍';
            btn.title = 'Ma position';
            L.DomEvent.disableClickPropagation(btn);
            L.DomEvent.on(btn, 'click', function (e) {
                L.DomEvent.stop(e);
                localiser(btn);
            });
            return btn;
        },
    });
    new Bouton().addTo(map);
    return { localiser };
}

// Geocodage inverse (coordonnees -> libelle d'adresse), best-effort. En cas
// d'echec reseau, renvoie un libelle base sur les coordonnees pour que le
// livreur ait toujours une reference exploitable.
async function reverseGeocode(lat, lng) {
    try {
        const res = await Api.get('/api/public/geocode_reverse.php?lat=' + lat + '&lng=' + lng);
        if (res.data && res.data.adresse) {
            return res.data.adresse;
        }
    } catch (e) { /* repli ci-dessous */ }
    return 'Point sur la carte (' + Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5) + ')';
}

// Champ d'adresse fusionne (recherche + saisie) : autocomplete a deux sources,
// les reperes collaboratifs CityHub et les adresses OSM (geocodage). Generique,
// reutilisable sur toutes les pages de commande.
//   input : l'element <input> du champ adresse
//   liste : l'element conteneur des suggestions (.repere-suggestions)
//   opts.getCenter()          -> {lat,lng} pour ancrer la recherche sur la zone (optionnel)
//   opts.onSelect(lat,lng,nom) -> appele quand l'utilisateur choisit un resultat
//   opts.onSaveRepere(nom,lat,lng,type) -> si fourni, affiche un bouton "+ repere" sur les resultats OSM
function brancherRechercheAdresse(input, liste, opts) {
    opts = opts || {};
    let minuteur = null;
    const fermer = () => { liste.classList.add('hidden'); liste.innerHTML = ''; };

    function choisir(lat, lng, nom) {
        input.value = nom;
        fermer();
        if (typeof opts.onSelect === 'function') { opts.onSelect(lat, lng, nom); }
    }

    input.addEventListener('input', () => {
        clearTimeout(minuteur);
        const q = input.value.trim();
        if (q.length < 3) { fermer(); return; }
        minuteur = setTimeout(async () => {
            // Ancrage sur la zone/ville affichee pour ne retourner que les
            // reperes et adresses proches du client.
            let geo = '';
            if (typeof opts.getCenter === 'function') {
                const centre = opts.getCenter();
                if (centre) { geo = '&lat=' + centre.lat + '&lng=' + centre.lng + '&rayon_km=40'; }
            }
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
                        ${opts.onSaveRepere ? '<button type="button" class="repere-save" title="Enregistrer comme repere collaboratif">＋ repere</button>' : ''}
                    </div>`).join('');
            }
            liste.innerHTML = html;
            liste.classList.remove('hidden');
            liste.querySelectorAll('.repere-item').forEach(el => {
                el.addEventListener('click', () => {
                    choisir(parseFloat(el.dataset.lat), parseFloat(el.dataset.lng), el.dataset.nom);
                });
            });
            if (typeof opts.onSaveRepere === 'function') {
                liste.querySelectorAll('.repere-save').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const el = btn.closest('.repere-item');
                        opts.onSaveRepere(el.dataset.nom, parseFloat(el.dataset.lat), parseFloat(el.dataset.lng), el.dataset.type);
                    });
                });
            }
        }, 350);
    });

    // Ferme la liste si on clique ailleurs.
    document.addEventListener('click', (e) => {
        if (e.target !== input && !liste.contains(e.target)) { fermer(); }
    });
}
