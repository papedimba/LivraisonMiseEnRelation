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
<script src="<?= asset_url('/assets/js/pwa.js') ?>"></script>
<?php if (Auth::check()): ?>
<script src="<?= asset_url('/assets/js/push.js') ?>"></script>
<?php endif; ?>
</body>
</html>
