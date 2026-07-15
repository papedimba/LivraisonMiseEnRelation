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

<div class="card mb-1">
    <h2 style="margin-top:0;">Deploiement (fichiers a jour ?)</h2>
    <p class="text-muted">Sur hebergement mutualise, un cache d'opcode (OPcache) ou une synchronisation partielle peut laisser un fichier a une ancienne version meme apres un deploiement "complet". Verifie directement si le code recent est bien present, fichier par fichier.</p>
    <div id="deploiement"><p class="text-muted">Chargement...</p></div>
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

function rendreDeploiement(deploiement) {
    const zone = document.getElementById('deploiement');
    if (!deploiement || !deploiement.length) { zone.innerHTML = '<p class="text-muted">Indisponible.</p>'; return; }

    let toutAJour = true;
    const lignes = deploiement.map(f => {
        const verifsHtml = f.verifications.map(v => {
            if (!v.present) toutAJour = false;
            return `<div>${libelleBool(v.present)} ${escapeHtml(v.libelle)}</div>`;
        }).join('');
        return `<tr>
            <td><code>${escapeHtml(f.fichier)}</code>${f.modifie_le ? `<br><span class="text-muted" style="font-size:0.78rem;">modifie le ${escapeHtml(f.modifie_le)}</span>` : ''}</td>
            <td>${f.existe ? verifsHtml : '<span class="tag tag-danger">Fichier introuvable</span>'}</td>
        </tr>`;
    }).join('');

    zone.innerHTML = `
        <table><tbody>${lignes}</tbody></table>
        ${!toutAJour ? `<div class="alert alert-erreur mt-1">
            Au moins un fichier n'a pas la derniere version du code (voir "Non" ci-dessus). Re-uploadez ce
            fichier specifiquement, puis si possible redemarrez PHP / videz le cache OPcache depuis le
            panneau de votre hebergeur (ex. cPanel : Selecteur PHP).
        </div>` : `<div class="alert alert-succes mt-1">Tous les fichiers verifies sont a jour.</div>`}
    `;
}

async function chargerJournal() {
    try {
        const res = await Api.get('/api/admin/logs_view.php');
        const { infos, lignes, deploiement } = res.data;
        rendreDeploiement(deploiement);

        const cheminActifDiffere = infos.php_error_log_actif && infos.log_path
            && !infos.php_error_log_actif.includes('app.log');

        document.getElementById('diagnostic').innerHTML = `
            <table>
                <tbody>
                    <tr><td>Dossier storage/logs</td><td>${libelleBool(infos.dossier_existe)}</td></tr>
                    <tr><td>Dossier inscriptible</td><td>${libelleBool(infos.dossier_inscriptible)}</td></tr>
                    <tr><td>Fichier app.log present</td><td>${libelleBool(infos.fichier_existe)}</td></tr>
                    <tr><td>Taille</td><td>${infos.fichier_taille_octets !== null ? infos.fichier_taille_octets + ' octets' : '-'}</td></tr>
                    <tr><td>Journal PHP natif (error_log ini)</td><td><code>${escapeHtml(infos.php_error_log_actif)}</code></td></tr>
                </tbody>
            </table>
            ${cheminActifDiffere ? `<div class="alert alert-info mt-1">
                Information : votre hebergeur ignore la directive PHP <code>error_log</code> demandee par
                l'application (il ecrit ailleurs, chemin ci-dessus). Sans impact sur les evenements de
                l'app ci-dessous (commandes, paiements...), qui sont ecrits directement dans
                <code>storage/logs/app.log</code> sans dependre de ce reglage. Seules les erreurs internes
                du moteur PHP lui-meme (rares, hors du controle de l'application) resteraient a chercher
                a cet autre emplacement, aupres du panneau d'administration de votre hebergeur.
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
