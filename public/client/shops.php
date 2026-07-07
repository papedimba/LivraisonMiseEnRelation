<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client');

$pageTitle = 'Boutiques';
require __DIR__ . '/../includes/header.php';
?>
<h1>Boutiques &amp; commerces</h1>
<p class="subtitle">Commandez directement aupres de nos restaurants, pharmacies, supermarches et boutiques partenaires.</p>

<div class="card mb-1">
    <div class="grid grid-2">
        <div class="form-group">
            <label for="filtre-categorie">Categorie</label>
            <select id="filtre-categorie">
                <option value="">Toutes</option>
                <option value="restaurant">Restaurant</option>
                <option value="maquis">Maquis</option>
                <option value="supermarche">Supermarche</option>
                <option value="pharmacie">Pharmacie</option>
                <option value="boutique">Boutique</option>
                <option value="fleuriste">Fleuriste</option>
                <option value="autre">Autre</option>
            </select>
        </div>
        <div class="form-group">
            <label for="recherche-produit">Rechercher un produit</label>
            <input type="text" id="recherche-produit" placeholder="Ex: pizza, paracetamol, riz...">
        </div>
    </div>
</div>

<div id="resultats-produits"></div>

<h2>Toutes les boutiques</h2>
<div id="liste-boutiques" class="grid grid-3">
    <p class="text-muted">Chargement...</p>
</div>

<script>
async function chargerBoutiques() {
    const categorie = document.getElementById('filtre-categorie').value;
    const zone = document.getElementById('liste-boutiques');
    try {
        const res = await Api.get('/api/public/stores.php?categorie=' + encodeURIComponent(categorie));
        const boutiques = res.data.boutiques;
        if (boutiques.length === 0) {
            zone.innerHTML = '<p class="text-muted">Aucune boutique disponible pour le moment.</p>';
            return;
        }
        zone.innerHTML = boutiques.map(b => `
            <a class="card" href="/client/shop.php?id=${b.user_id}">
                <h2>${escapeHtml(b.nom_boutique)} ${b.abonnement_premium == 1 ? '⭐' : ''}</h2>
                <p class="text-muted">${escapeHtml(b.categorie)} &middot; Note ${b.note_moyenne}/5</p>
                ${b.description ? `<p>${escapeHtml(b.description)}</p>` : ''}
            </a>
        `).join('');
    } catch (e) {
        zone.innerHTML = '<p class="text-muted">Erreur de chargement.</p>';
    }
}

async function rechercherProduits() {
    const q = document.getElementById('recherche-produit').value.trim();
    const zone = document.getElementById('resultats-produits');
    if (q === '') {
        zone.innerHTML = '';
        return;
    }
    try {
        const res = await Api.get('/api/public/search.php?q=' + encodeURIComponent(q));
        const produits = res.data.produits;
        if (produits.length === 0) {
            zone.innerHTML = '<div class="card mb-1"><p class="text-muted">Aucun produit trouve pour "' + escapeHtml(q) + '".</p></div>';
            return;
        }
        zone.innerHTML = '<div class="card mb-1"><h2>Resultats de recherche</h2><div class="table-wrap"><table>'
            + '<thead><tr><th>Produit</th><th>Boutique</th><th>Prix</th><th></th></tr></thead><tbody>'
            + produits.map(p => `
                <tr>
                    <td>${escapeHtml(p.nom)}</td>
                    <td>${escapeHtml(p.nom_boutique)}</td>
                    <td>${formatMontant(p.prix)}</td>
                    <td><a href="/client/shop.php?id=${p.commercant_id}">Voir la boutique</a></td>
                </tr>
            `).join('')
            + '</tbody></table></div></div>';
    } catch (e) {
        zone.innerHTML = '';
    }
}

document.getElementById('filtre-categorie').addEventListener('change', chargerBoutiques);
document.getElementById('recherche-produit').addEventListener('input', () => {
    clearTimeout(window._ts);
    window._ts = setTimeout(rechercherProduits, 400);
});

chargerBoutiques();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
