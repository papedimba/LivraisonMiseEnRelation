<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client');

$reference = $_GET['ref'] ?? '';

$pageTitle = 'Paiement';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
    <div class="card" style="text-align:center;">
        <h1>Paiement de la commande</h1>
        <p class="subtitle"><?= htmlspecialchars($reference) ?></p>
        <div id="etat">
            <p>Verification du paiement en cours...</p>
        </div>
        <a class="btn mt-1" href="/client/track.php?ref=<?= urlencode($reference) ?>">Suivre ma commande</a>
    </div>
</div>
<script>
const reference = <?= json_encode($reference) ?>;
let tentatives = 0;

function libelleStatut(sp) {
    if (sp === 'paye') { return ['✅ Paiement confirme', 'Votre paiement a bien ete recu.']; }
    if (sp === 'echec') { return ['❌ Paiement echoue', 'Le paiement n\'a pas abouti. Vous pouvez reessayer depuis votre commande.']; }
    return ['⏳ En attente de confirmation', 'Nous attendons la confirmation de l\'operateur. Cela peut prendre quelques instants.'];
}

async function verifier() {
    tentatives++;
    try {
        const res = await Api.get('/api/client/payment_status.php?reference=' + encodeURIComponent(reference));
        const sp = res.data.statut_paiement;
        const [titre, texte] = libelleStatut(sp);
        document.getElementById('etat').innerHTML = '<h2>' + titre + '</h2><p class="text-muted">' + texte + '</p>';
        if (sp === 'paye' || sp === 'echec') { return; } // statut final atteint
    } catch (e) {}
    if (tentatives < 20) { setTimeout(verifier, 3000); } // poll ~1 min
}

verifier();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
