<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_page_role('client', 'livreur', 'commercant');

$pageTitle = 'Assistance';
require __DIR__ . '/../includes/header.php';
?>
<h1>Assistance</h1>
<p class="subtitle">Discutez avec notre assistant ou envoyez une reclamation a notre equipe.</p>

<div class="grid grid-2">
    <div class="card">
        <h2>💬 Assistant</h2>
        <div id="chat-messages" style="max-height:380px; overflow-y:auto; margin-bottom:1rem;"></div>
        <form id="chat-form" class="flex">
            <input type="text" id="chat-input" placeholder="Ecrivez votre question..." required style="flex:1;">
            <button type="submit" class="btn">Envoyer</button>
        </form>
    </div>

    <div class="card">
        <h2>📩 Envoyer une reclamation</h2>
        <div id="alert-zone"></div>
        <form id="complaint-form">
            <div class="form-group">
                <label for="sujet">Sujet</label>
                <input type="text" id="sujet" required>
            </div>
            <div class="form-group">
                <label for="commande_ref">Reference de commande (optionnel)</label>
                <input type="text" id="commande_ref">
            </div>
            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" required></textarea>
            </div>
            <button type="submit" class="btn btn-secondaire btn-block">Envoyer la reclamation</button>
        </form>
    </div>
</div>

<script>
let conversationId = null;
const chatMessages = document.getElementById('chat-messages');

function ajouterMessage(role, texte) {
    const div = document.createElement('div');
    div.style.marginBottom = '0.75rem';
    div.innerHTML = `<strong>${role === 'user' ? 'Vous' : 'Assistant'} :</strong> ${escapeHtml(texte)}`;
    chatMessages.appendChild(div);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

document.getElementById('chat-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    if (!message) return;
    ajouterMessage('user', message);
    input.value = '';

    try {
        const res = await Api.post('/api/support/chat.php', { message, conversation_id: conversationId });
        conversationId = res.data.conversation_id;
        ajouterMessage('assistant', res.data.reponse);
    } catch (err) {
        ajouterMessage('assistant', 'Erreur : ' + err.message);
    }
});

document.getElementById('complaint-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertZone = document.getElementById('alert-zone');
    try {
        await Api.post('/api/client/complaint.php', {
            sujet: document.getElementById('sujet').value.trim(),
            message: document.getElementById('message').value.trim(),
        });
        alertZone.innerHTML = '<div class="alert alert-succes">Reclamation envoyee. Nous vous repondrons sous peu.</div>';
        document.getElementById('complaint-form').reset();
    } catch (err) {
        alertZone.innerHTML = `<div class="alert alert-erreur">${escapeHtml(err.message)}</div>`;
    }
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
