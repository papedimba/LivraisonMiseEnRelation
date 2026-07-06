<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('commercant');

$pageTitle = 'Ma boutique';
require __DIR__ . '/../includes/header.php';
?>
<h1>Ma boutique</h1>
<div id="alert-zone"></div>

<div class="card">
    <form id="store-form">
        <div class="grid grid-2">
            <div class="form-group">
                <label for="nom_boutique">Nom de la boutique</label>
                <input type="text" id="nom_boutique">
            </div>
            <div class="form-group">
                <label for="categorie">Categorie</label>
                <select id="categorie">
                    <option value="restaurant">Restaurant</option>
                    <option value="maquis">Maquis</option>
                    <option value="supermarche">Supermarche</option>
                    <option value="pharmacie">Pharmacie</option>
                    <option value="boutique">Boutique</option>
                    <option value="fleuriste">Fleuriste</option>
                    <option value="autre">Autre</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="adresse">Adresse</label>
            <input type="text" id="adresse">
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description"></textarea>
        </div>
        <div class="flex">
            <span id="statut-badge"></span>
        </div>
        <button type="submit" class="btn mt-1">Enregistrer</button>
    </form>
</div>

<script>
async function chargerBoutique() {
    const res = await Api.get('/api/commercant/store_profile.php');
    const b = res.data.boutique;
    document.getElementById('nom_boutique').value = b.nom_boutique || '';
    document.getElementById('categorie').value = b.categorie || 'boutique';
    document.getElementById('adresse').value = b.adresse || '';
    document.getElementById('description').value = b.description || '';
    const statuts = { valide: ['Boutique validee et visible', 'tag-succes'], en_attente: ['En attente de validation', 'tag-attente'], rejete: ['Rejetee - contactez le support', 'tag-danger'] };
    const [label, classe] = statuts[b.statut_validation] || ['-', 'tag-info'];
    document.getElementById('statut-badge').innerHTML = `<span class="tag ${classe}">${label}</span>`;
}

document.getElementById('store-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertZone = document.getElementById('alert-zone');
    try {
        await Api.post('/api/commercant/store_update.php', {
            nom_boutique: document.getElementById('nom_boutique').value.trim(),
            categorie: document.getElementById('categorie').value,
            adresse: document.getElementById('adresse').value.trim(),
            description: document.getElementById('description').value.trim(),
        });
        alertZone.innerHTML = '<div class="alert alert-succes">Boutique mise a jour.</div>';
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

chargerBoutique();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
