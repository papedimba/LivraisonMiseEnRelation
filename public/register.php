<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    redirect_to_dashboard(Auth::role());
}

$roleInitial = $_GET['role'] ?? 'client';
if (!in_array($roleInitial, ['client', 'livreur', 'commercant'], true)) {
    $roleInitial = 'client';
}

$pageTitle = 'Inscription';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-tabs">
        <a href="/login.php">Connexion</a>
        <a href="/register.php" class="active">Inscription</a>
    </div>
    <div class="card">
        <h1>Creer un compte</h1>
        <p class="subtitle">Choisissez votre profil puis remplissez le formulaire.</p>

        <div class="form-group">
            <label for="role">Je suis</label>
            <select id="role">
                <option value="client">Client</option>
                <option value="livreur">Livreur</option>
                <option value="commercant">Commercant</option>
            </select>
        </div>

        <div id="alert-zone"></div>

        <form id="register-form">
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="prenom">Prenom</label>
                    <input type="text" id="prenom" required>
                </div>
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" required>
                </div>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label for="telephone">Telephone</label>
                    <input type="text" id="telephone" required placeholder="07 00 00 00 00">
                </div>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe (8 caracteres minimum)</label>
                <input type="password" id="password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="quartier_id">Quartier</label>
                <select id="quartier_id"><option value="">Selectionner...</option></select>
            </div>
            <div class="form-group">
                <label for="adresse">Adresse precise</label>
                <input type="text" id="adresse" placeholder="Ex: Rue 12, non loin de la pharmacie">
            </div>

            <div id="champs-livreur" class="hidden">
                <div class="form-group">
                    <label for="type_vehicule">Type de vehicule</label>
                    <select id="type_vehicule">
                        <option value="moto">Moto</option>
                        <option value="velo">Velo</option>
                        <option value="voiture">Voiture</option>
                        <option value="tricycle">Tricycle</option>
                        <option value="a_pied">A pied</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="numero_piece">Numero de piece d'identite</label>
                    <input type="text" id="numero_piece">
                </div>
                <p class="text-muted">Votre compte sera active apres verification de vos documents par un administrateur.</p>
            </div>

            <div id="champs-commercant" class="hidden">
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
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description"></textarea>
                </div>
                <p class="text-muted">Votre boutique sera visible apres validation par un administrateur.</p>
            </div>

            <button type="submit" class="btn btn-block">Creer mon compte</button>
        </form>
    </div>
</div>
<script>
const roleSelect = document.getElementById('role');
roleSelect.value = <?= json_encode($roleInitial) ?>;

function majChamps() {
    document.getElementById('champs-livreur').classList.toggle('hidden', roleSelect.value !== 'livreur');
    document.getElementById('champs-commercant').classList.toggle('hidden', roleSelect.value !== 'commercant');
}
roleSelect.addEventListener('change', majChamps);
majChamps();

(async function chargerQuartiers() {
    try {
        const res = await Api.get('/api/public/quartiers.php');
        const select = document.getElementById('quartier_id');
        res.data.quartiers.forEach(q => {
            const opt = document.createElement('option');
            opt.value = q.id;
            opt.textContent = `${q.nom} (${q.ville})`;
            select.appendChild(opt);
        });
    } catch (e) { /* liste optionnelle */ }
})();

document.getElementById('register-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertZone = document.getElementById('alert-zone');
    alertZone.innerHTML = '';

    const data = {
        role: roleSelect.value,
        prenom: document.getElementById('prenom').value.trim(),
        nom: document.getElementById('nom').value.trim(),
        email: document.getElementById('email').value.trim(),
        telephone: document.getElementById('telephone').value.trim(),
        password: document.getElementById('password').value,
        quartier_id: document.getElementById('quartier_id').value || null,
        adresse: document.getElementById('adresse').value.trim(),
    };

    if (roleSelect.value === 'livreur') {
        data.type_vehicule = document.getElementById('type_vehicule').value;
        data.numero_piece = document.getElementById('numero_piece').value.trim();
    }
    if (roleSelect.value === 'commercant') {
        data.nom_boutique = document.getElementById('nom_boutique').value.trim();
        data.categorie = document.getElementById('categorie').value;
        data.description = document.getElementById('description').value.trim();
    }

    try {
        const res = await Api.post('/api/auth/register.php', data);
        if (res.data.connecte) {
            window.location.href = '/client/dashboard.php';
        } else {
            alertZone.innerHTML = `<div class="alert alert-succes">${escapeHtml(res.message)}</div>`;
            document.getElementById('register-form').reset();
        }
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
