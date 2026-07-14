<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('admin');

$pageTitle = 'Journal systeme';
require __DIR__ . '/../includes/header.php';
?>
<h1>Journal systeme</h1>
<p class="subtitle">Derniers evenements techniques (echecs de paiement, exceptions...), sans avoir besoin d'un acces FTP/SSH au serveur.</p>

<div class="card mb-1">
    <h2 style="margin-top:0;">Diagnostic</h2>
    <div id="diagnostic"><p class="text-muted">Chargement...</p></div>
    <div id="test-alert" class="mt-1"></div>
    <button type="button" class="btn btn-sm btn-ghost mt-1" id="btn-test-ecriture">Ecrire une ligne de test</button>
</div>

<div class="card">
    <div class="flex-between">
        <h2 style="margin-top:0;">Dernieres lignes (storage/logs/app.log)</h2>
        <button type="button" class="btn btn-sm btn-ghost" id="btn-rafraichir">Rafraichir</button>
    </div>
    <pre id="log-contenu" style="max-height:520px;overflow:auto;background:var(--fond-alt,#12131a);color:#d7d9e0;padding:0.85rem;border-radius:8px;font-size:0.8rem;line-height:1.4;white-space:pre-wrap;word-break:break-word;"></pre>
</div>

<script>
function libelleBool(val) {
    return val ? '<span class="tag tag-succes">Oui</span>' : '<span class="tag tag-danger">Non</span>';
}

async function chargerJournal() {
    try {
        const res = await Api.get('/api/admin/logs_view.php');
        const { infos, lignes } = res.data;

        const cheminActifDiffere = infos.php_error_log_actif && infos.log_path
            && !infos.php_error_log_actif.includes('app.log');

        document.getElementById('diagnostic').innerHTML = `
            <table>
                <tbody>
                    <tr><td>Dossier storage/logs</td><td>${libelleBool(infos.dossier_existe)}</td></tr>
                    <tr><td>Dossier inscriptible</td><td>${libelleBool(infos.dossier_inscriptible)}</td></tr>
                    <tr><td>Fichier app.log present</td><td>${libelleBool(infos.fichier_existe)}</td></tr>
                    <tr><td>Taille</td><td>${infos.fichier_taille_octets !== null ? infos.fichier_taille_octets + ' octets' : '-'}</td></tr>
                    <tr><td>Journal PHP reellement actif</td><td><code>${escapeHtml(infos.php_error_log_actif)}</code></td></tr>
                </tbody>
            </table>
            ${cheminActifDiffere ? `<div class="alert alert-erreur mt-1">
                Votre hebergeur ignore la configuration demandee par l'application : PHP ecrit ailleurs
                que <code>storage/logs/app.log</code> (chemin actif ci-dessus). Les erreurs existent bien,
                mais dans ce fichier-la - cherchez-le via le panneau d'administration de votre hebergeur,
                ou demandez-lui confirmation de son emplacement.
            </div>` : ''}
        `;

        const zone = document.getElementById('log-contenu');
        zone.textContent = lignes.length ? lignes.join('\n') : 'Aucune ligne pour le moment.';
        zone.scrollTop = zone.scrollHeight;
    } catch (err) {
        document.getElementById('diagnostic').innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
}

document.getElementById('btn-rafraichir').addEventListener('click', chargerJournal);

document.getElementById('btn-test-ecriture').addEventListener('click', async () => {
    const alertZone = document.getElementById('test-alert');
    alertZone.innerHTML = '<p class="text-muted">Ecriture en cours...</p>';
    try {
        const res = await Api.post('/api/admin/logs_view.php', { action: 'test_ecriture' });
        const classe = res.data.trouve_dans_log_path ? 'alert-succes' : 'alert-erreur';
        alertZone.innerHTML = `<div class="alert ${classe}">${escapeHtml(res.message)}</div>`;
        chargerJournal();
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

chargerJournal();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
