/**
 * Notifications Web Push : enregistrement du service worker et abonnement.
 * Necessite un contexte securise (HTTPS, ou localhost en developpement).
 *
 * Expose window.PushNotifications pour permettre a une page (ex. tableau de
 * bord livreur) d'afficher l'etat courant et de proposer un bouton
 * "Activer les notifications" : la plupart des navigateurs (notamment Safari)
 * exigent un geste utilisateur direct pour Notification.requestPermission(),
 * une demande automatique au chargement de la page echoue silencieusement.
 */
(function () {
    var supporte = ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);

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
        return fetch('/api/notifications/subscribe.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ endpoint: data.endpoint, keys: data.keys })
        }).catch(function () {});
    }

    // Verifie que le serveur a des cles VAPID configurees (fonctionnalite
    // activee cote hebergement). Retourne la cle publique ou null.
    function configServeur() {
        return fetch('/api/notifications/vapid_public_key.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !res.data.active || !res.data.public_key) { return null; }
                return res.data.public_key;
            })
            .catch(function () { return null; });
    }

    function abonner(reg, publicKey) {
        return reg.pushManager.getSubscription().then(function (sub) {
            if (sub) {
                return envoyerAbonnement(sub).then(function () { return true; });
            }
            return reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey)
            }).then(function (sub2) {
                return envoyerAbonnement(sub2).then(function () { return true; });
            }).catch(function (e) {
                console.warn('Abonnement push echoue :', e);
                return false;
            });
        });
    }

    // Au chargement : si la permission est deja accordee (visite precedente),
    // on (re)confirme silencieusement l'abonnement aupres du serveur. On ne
    // demande JAMAIS la permission automatiquement ici (cf. commentaire d'en-tete).
    function autoConfirmerSiAutorise() {
        if (!supporte || Notification.permission !== 'granted') { return; }
        navigator.serviceWorker.ready.then(function (reg) {
            configServeur().then(function (publicKey) {
                if (publicKey) { abonner(reg, publicKey); }
            });
        }).catch(function () {});
    }

    // Etat courant, pour affichage ("Activer les notifications" / bloque / etc).
    function statutPermission() {
        if (!supporte) { return 'non_supporte'; }
        return Notification.permission; // 'granted' | 'denied' | 'default'
    }

    // Demande explicite (a appeler depuis le gestionnaire de clic d'un bouton).
    // Retourne { ok: bool, raison: 'actif'|'bloque'|'ignore'|'echec'|'non_supporte'|'non_configure' }.
    function demanderActivation() {
        if (!supporte) {
            return Promise.resolve({ ok: false, raison: 'non_supporte' });
        }
        if (Notification.permission === 'denied') {
            return Promise.resolve({ ok: false, raison: 'bloque' });
        }
        return navigator.serviceWorker.ready.then(function (reg) {
            return configServeur().then(function (publicKey) {
                if (!publicKey) { return { ok: false, raison: 'non_configure' }; }

                function finaliser() {
                    return abonner(reg, publicKey).then(function (ok) {
                        return { ok: ok, raison: ok ? 'actif' : 'echec' };
                    });
                }

                if (Notification.permission === 'granted') {
                    return finaliser();
                }
                return Notification.requestPermission().then(function (perm) {
                    if (perm !== 'granted') {
                        return { ok: false, raison: perm === 'denied' ? 'bloque' : 'ignore' };
                    }
                    return finaliser();
                });
            });
        });
    }

    window.PushNotifications = {
        statutPermission: statutPermission,
        demanderActivation: demanderActivation,
        configServeur: configServeur,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoConfirmerSiAutorise);
    } else {
        autoConfirmerSiAutorise();
    }
})();
