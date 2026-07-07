/**
 * Service worker : reçoit les push de reveil et affiche la notification en
 * allant chercher son contenu aupres du serveur (modele "reveil sans payload").
 */
self.addEventListener('push', function (event) {
    event.waitUntil(
        fetch('/api/notifications/unread.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var liste = (res && res.data && res.data.notifications) || [];
                if (liste.length === 0) {
                    return self.registration.showNotification('LivraisonCI', {
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
                return self.registration.showNotification('LivraisonCI', {
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
