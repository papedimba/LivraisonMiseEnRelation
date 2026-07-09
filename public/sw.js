/**
 * Service worker : reçoit les push de reveil et affiche la notification en
 * allant chercher son contenu aupres du serveur (modele "reveil sans payload").
 */
// --- Cache (PWA installable + chargement rapide des ressources statiques) ---
var CACHE = 'cityhub225-v2';
var ASSETS = [
    '/assets/css/style.css',
    '/assets/js/api.js',
    '/assets/js/app.js',
    '/assets/js/pwa.js',
    '/assets/icons/icon-192.png',
    '/assets/icons/icon-512.png',
    '/manifest.webmanifest'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE).then(function (c) { return c.addAll(ASSETS); }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (cles) {
            return Promise.all(cles.filter(function (k) { return k !== CACHE; })
                .map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') { return; }

    var url = new URL(req.url);
    if (url.origin !== self.location.origin) { return; }

    // Ressources statiques : cache d'abord, puis reseau (mise a jour du cache).
    if (url.pathname.startsWith('/assets/') || url.pathname === '/manifest.webmanifest') {
        event.respondWith(
            caches.match(req).then(function (cache) {
                return cache || fetch(req).then(function (res) {
                    var copie = res.clone();
                    caches.open(CACHE).then(function (c) { c.put(req, copie); });
                    return res;
                });
            })
        );
        return;
    }

    // Navigation (pages) : reseau d'abord, message hors-ligne en dernier recours.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(function () {
                return new Response(
                    '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                    + '<div style="font-family:sans-serif;text-align:center;padding:3rem 1rem;color:#1a1a2e">'
                    + '<h1>Hors ligne</h1><p>Verifiez votre connexion Internet puis reessayez.</p></div>',
                    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                );
            })
        );
        return;
    }
    // Le reste (API...) passe directement au reseau.
});

self.addEventListener('push', function (event) {
    event.waitUntil(
        fetch('/api/notifications/unread.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var liste = (res && res.data && res.data.notifications) || [];
                if (liste.length === 0) {
                    return self.registration.showNotification('CityHub 225', {
                        body: 'Vous avez une nouvelle notification.',
                    });
                }
                var n = liste[0];
                return self.registration.showNotification(n.titre, {
                    body: n.message,
                    tag: 'notif-' + n.id,
                    data: { lien: n.lien || '/' },
                });
            })
            .catch(function () {
                return self.registration.showNotification('CityHub 225', {
                    body: 'Nouvelle notification.'
                });
            })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var lien = (event.notification.data && event.notification.data.lien) || '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientsList) {
            for (var i = 0; i < clientsList.length; i++) {
                if (clientsList[i].url.indexOf(lien) !== -1 && 'focus' in clientsList[i]) {
                    return clientsList[i].focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(lien);
            }
        })
    );
});
