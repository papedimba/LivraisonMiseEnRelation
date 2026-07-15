</main>
<footer class="footer">
    <p><strong><?= htmlspecialchars(APP_NOM) ?></strong> &middot; Connecter. Livrer. Simplifier.</p>
    <p>&copy; <?= date('Y') ?> - Livraison &amp; mise en relation a Bouake et en Cote d'Ivoire.</p>
</footer>
<?php if (!empty($items)): ?>
<nav class="bottomnav">
    <?php foreach ($items as [$href, $libelle, $icone]): ?>
        <a href="<?= $href ?>" class="<?= nav_actif($href, $courant ?? '') ? 'actif' : '' ?>">
            <span class="ic"><?= $icone ?></span>
            <span><?= htmlspecialchars($libelle) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
<script>
(function () {
    var menuToggle = document.getElementById('menu-toggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    function fermerSidebar() {
        if (sidebar) sidebar.classList.remove('ouvert');
        if (overlay) overlay.classList.remove('ouvert');
    }
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function () {
            sidebar.classList.toggle('ouvert');
            if (overlay) overlay.classList.toggle('ouvert');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', fermerSidebar);
    }

    var themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var actuel = document.documentElement.getAttribute('data-theme');
            var prefereSombre = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var estSombre = actuel ? actuel === 'dark' : prefereSombre;
            var nouveau = estSombre ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', nouveau);
            try { localStorage.setItem('theme', nouveau); } catch (e) {}
        });
    }
})();
</script>
<script src="<?= asset_url('/assets/js/pwa.js') ?>"></script>
<?php if (Auth::check()): ?>
<script src="<?= asset_url('/assets/js/push.js') ?>"></script>
<?php endif; ?>
</body>
</html>
