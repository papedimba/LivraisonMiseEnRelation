/**
 * Enregistrement du service worker et abonnement Web Push.
 * Necessite un contexte securise (HTTPS, ou localhost en developpement).
 */
(function () {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        return; // navigateur ou contexte non compatible (ex: http hors localhost)
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(base64);
        var arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) {
            arr[i] = raw.charCodeAt(i);
        }
        return arr;
    }

    function envoyerAbonnement(sub) {
        var data = sub.toJSON();
        fetch('/api/notifications/subscribe.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ endpoint: data.endpoint, keys: data.keys })
        }).catch(function () {});
    }

    // Le service worker est enregistre par pwa.js ; on attend qu'il soit pret.
    navigator.serviceWorker.ready.then(function (reg) {
        fetch('/api/notifications/vapid_public_key.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !res.data.active || !res.data.public_key) {
                    return; // Web Push non configure cote serveur
                }
                if (Notification.permission === 'denied') {
                    return;
                }

                function abonner() {
                    reg.pushManager.getSubscription().then(function (sub) {
                        if (sub) { envoyerAbonnement(sub); return; }
                        reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlBase64ToUint8Array(res.data.public_key)
                        }).then(envoyerAbonnement).catch(function (e) {
                            console.warn('Abonnement push echoue :', e);
                        });
                    });
                }

                if (Notification.permission === 'granted') {
                    abonner();
                } else if (Notification.permission === 'default') {
                    Notification.requestPermission().then(function (perm) {
                        if (perm === 'granted') { abonner(); }
                    });
                }
            })
            .catch(function () {});
    }).catch(function () {});
})();
