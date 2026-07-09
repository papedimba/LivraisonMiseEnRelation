/**
 * Widget de messagerie in-app client <-> livreur, rattache a une commande.
 * Utilise sur la page de suivi client et le tableau de bord livreur.
 *
 * Usage : Chat.init(elementConteneur, commandeId); Chat.stop();
 * Depend de escapeHtml() et formatDate() (app.js).
 */
const Chat = {
    commandeId: null,
    container: null,
    listeEl: null,
    inputEl: null,
    dernierId: 0,
    timer: null,
    moi: null,
    envoiEnCours: false,

    init(container, commandeId) {
        if (!container || !commandeId) { return; }
        // Reinitialise si on change de commande.
        if (this.commandeId !== commandeId) {
            this.stop();
            this.dernierId = 0;
        }
        this.commandeId = commandeId;
        this.container = container;

        if (!container.dataset.chatMonte) {
            container.innerHTML = `
                <h2 style="margin-top:0;">💬 Messagerie</h2>
                <div class="chat-messages" aria-live="polite"></div>
                <div class="chat-input-row">
                    <input type="text" class="chat-input" maxlength="1000" placeholder="Écrire un message...">
                    <button type="button" class="btn chat-send">Envoyer</button>
                </div>`;
            container.dataset.chatMonte = '1';
            this.listeEl = container.querySelector('.chat-messages');
            this.inputEl = container.querySelector('.chat-input');
            const btn = container.querySelector('.chat-send');
            btn.addEventListener('click', () => this._envoyer());
            this.inputEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); this._envoyer(); }
            });
        } else {
            this.listeEl = container.querySelector('.chat-messages');
            this.inputEl = container.querySelector('.chat-input');
        }

        this._charger();
        this.timer = setInterval(() => this._charger(), 4000);
    },

    stop() {
        if (this.timer) { clearInterval(this.timer); this.timer = null; }
    },

    async _charger() {
        try {
            const res = await Api.get(`/api/chat/messages.php?commande_id=${encodeURIComponent(this.commandeId)}&after=${this.dernierId}`);
            this.moi = res.data.moi;
            (res.data.messages || []).forEach(m => this._ajouter(m));
            if (this.inputEl) {
                this.inputEl.disabled = !res.data.disponible;
                const btn = this.container.querySelector('.chat-send');
                if (btn) { btn.disabled = !res.data.disponible; }
            }
        } catch (e) { /* silencieux : la prochaine iteration reessaiera */ }
    },

    _ajouter(m) {
        if (m.id <= this.dernierId) { return; }
        this.dernierId = m.id;
        const estMoi = m.expediteur_role === this.moi;
        const div = document.createElement('div');
        div.className = 'chat-msg' + (estMoi ? ' moi' : '');
        div.innerHTML = `<div class="chat-bulle">${escapeHtml(m.message)}</div>`
            + `<div class="chat-heure">${formatDate(m.created_at)}</div>`;
        this.listeEl.appendChild(div);
        this.listeEl.scrollTop = this.listeEl.scrollHeight;
    },

    async _envoyer() {
        if (this.envoiEnCours || !this.inputEl) { return; }
        const texte = this.inputEl.value.trim();
        if (!texte) { return; }
        this.envoiEnCours = true;
        this.inputEl.value = '';
        try {
            const res = await Api.post('/api/chat/send.php', { commande_id: this.commandeId, message: texte });
            this._ajouter({
                id: res.data.id,
                expediteur_role: res.data.expediteur_role,
                message: res.data.message,
                created_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
            });
        } catch (err) {
            this.inputEl.value = texte; // on ne perd pas le message en cas d'echec
            alert(err.message);
        } finally {
            this.envoiEnCours = false;
        }
    },
};
