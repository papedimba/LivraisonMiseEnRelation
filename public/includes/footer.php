</main>
<footer class="footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NOM) ?> - Livraison &amp; mise en relation a Bouake, Cote d'Ivoire.</p>
</footer>
<?php if (Auth::check()): ?>
<script src="/assets/js/push.js"></script>
<?php endif; ?>
</body>
</html>
