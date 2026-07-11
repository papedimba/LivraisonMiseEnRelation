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
