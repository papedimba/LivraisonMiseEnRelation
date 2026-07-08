</main>
<footer class="footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NOM) ?> - Livraison &amp; mise en relation a Bouake, Cote d'Ivoire.</p>
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
<script src="/assets/js/pwa.js"></script>
<?php if (Auth::check()): ?>
<script src="/assets/js/push.js"></script>
<?php endif; ?>
</body>
</html>
