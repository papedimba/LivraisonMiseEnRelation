/**
 * Progressive Web App : enregistrement du service worker et invite
 * d'installation ("Installer l'application").
 */
(function () {
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function (e) {
                console.warn('SW non enregistre :', e);
            });
        });
    }

    // Invite d'installation personnalisee.
    var promptDiffere = null;

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        promptDiffere = e;
        afficherBoutonInstall();
    });

    function afficherBoutonInstall() {
        if (document.getElementById('pwa-install')) { return; }
        var btn = document.createElement('button');
        btn.id = 'pwa-install';
        btn.type = 'button';
        btn.textContent = '⬇️ Installer l\'application';
        btn.style.cssText =
            'position:fixed;left:50%;transform:translateX(-50%);bottom:calc(72px + env(safe-area-inset-bottom,0px));'
            + 'z-index:200;background:#6c4dff;color:#fff;border:none;border-radius:999px;'
            + 'padding:0.7rem 1.2rem;font-weight:700;font-size:0.9rem;box-shadow:0 8px 24px rgba(28,27,51,.25);cursor:pointer;';
        btn.addEventListener('click', function () {
            if (!promptDiffere) { return; }
            promptDiffere.prompt();
            promptDiffere.userChoice.finally(function () {
                promptDiffere = null;
                btn.remove();
            });
        });
        document.body.appendChild(btn);
    }

    // Une fois installee, on retire le bouton.
    window.addEventListener('appinstalled', function () {
        var b = document.getElementById('pwa-install');
        if (b) { b.remove(); }
    });
})();
