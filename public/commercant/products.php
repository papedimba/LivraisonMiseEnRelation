<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('commercant');

$pageTitle = 'Mes produits';
require __DIR__ . '/../includes/header.php';
?>
<div class="flex-between">
    <h1>Mes produits</h1>
    <button id="btn-nouveau" class="btn">+ Ajouter un produit</button>
</div>

<div id="alert-zone"></div>

<div id="form-produit" class="card hidden mb-1">
    <h2 id="form-titre">Nouveau produit</h2>
    <input type="hidden" id="produit_id">
    <div class="grid grid-2">
        <div class="form-group">
            <label for="nom">Nom</label>
            <input type="text" id="nom" required>
        </div>
        <div class="form-group">
            <label for="prix">Prix (FCFA)</label>
            <input type="number" id="prix" min="1" required>
        </div>
    </div>
    <div class="grid grid-2">
        <div class="form-group">
            <label for="categorie">Categorie</label>
            <input type="text" id="categorie" placeholder="Ex: Plats, Boissons...">
        </div>
        <div class="form-group">
            <label for="stock">Stock (optionnel)</label>
            <input type="number" id="stock" min="0">
        </div>
    </div>
    <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description"></textarea>
    </div>
    <div class="flex">
        <button id="btn-enregistrer" class="btn">Enregistrer</button>
        <button id="btn-annuler" class="btn btn-ghost">Annuler</button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Nom</th><th>Categorie</th><th>Prix</th><th>Stock</th><th>Disponible</th><th></th></tr>
            </thead>
            <tbody id="liste-produits">
                <tr><td colspan="6" class="text-muted">Chargement...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const formZone = document.getElementById('form-produit');

document.getElementById('btn-nouveau').addEventListener('click', () => {
    document.getElementById('form-titre').textContent = 'Nouveau produit';
    document.getElementById('produit_id').value = '';
    ['nom', 'prix', 'categorie', 'stock', 'description'].forEach(id => document.getElementById(id).value = '');
    formZone.classList.remove('hidden');
});
document.getElementById('btn-annuler').addEventListener('click', () => formZone.classList.add('hidden'));

document.getElementById('btn-enregistrer').addEventListener('click', async () => {
    const alertZone = document.getElementById('alert-zone');
    const id = document.getElementById('produit_id').value;
    const payload = {
        nom: document.getElementById('nom').value.trim(),
        prix: document.getElementById('prix').value,
        categorie: document.getElementById('categorie').value.trim(),
        stock: document.getElementById('stock').value || null,
        description: document.getElementById('description').value.trim(),
    };
    try {
        if (id) {
            payload.produit_id = id;
            await Api.post('/api/commercant/products_update.php', payload);
        } else {
            await Api.post('/api/commercant/products_create.php', payload);
        }
        formZone.classList.add('hidden');
        alertZone.innerHTML = '<div class="alert alert-succes">Produit enregistre.</div>';
        chargerProduits();
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});

async function chargerProduits() {
    const tbody = document.getElementById('liste-produits');
    const res = await Api.get('/api/commercant/products_list.php');
    const produits = res.data.produits;
    if (produits.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-muted">Aucun produit. Ajoutez-en un !</td></tr>';
        return;
    }
    tbody.innerHTML = produits.map(p => `
        <tr>
            <td>${escapeHtml(p.nom)}</td>
            <td>${escapeHtml(p.categorie || '-')}</td>
            <td>${formatMontant(p.prix)}</td>
            <td>${p.stock ?? '-'}</td>
            <td>${p.disponible == 1 ? '✅' : '❌'}</td>
            <td>
                <button class="btn btn-sm" data-action="edit" data-id="${p.id}">Modifier</button>
                <button class="btn btn-sm btn-danger" data-action="delete" data-id="${p.id}">Supprimer</button>
            </td>
        </tr>
    `).join('');

    tbody.querySelectorAll('button[data-action="edit"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const p = produits.find(x => x.id == btn.dataset.id);
            document.getElementById('form-titre').textContent = 'Modifier le produit';
            document.getElementById('produit_id').value = p.id;
            document.getElementById('nom').value = p.nom;
            document.getElementById('prix').value = p.prix;
            document.getElementById('categorie').value = p.categorie || '';
            document.getElementById('stock').value = p.stock ?? '';
            document.getElementById('description').value = p.description || '';
            formZone.classList.remove('hidden');
        });
    });
    tbody.querySelectorAll('button[data-action="delete"]').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Supprimer ce produit ?')) return;
            await Api.post('/api/commercant/products_delete.php', { produit_id: btn.dataset.id });
            chargerProduits();
        });
    });
}

chargerProduits();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
