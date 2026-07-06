/**
 * Petit wrapper fetch pour dialoguer avec l'API PHP (JSON).
 */
const Api = {
    async post(url, data) {
        return Api._request(url, 'POST', data);
    },
    async get(url) {
        return Api._request(url, 'GET');
    },
    async _request(url, method, data) {
        const options = {
            method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
        };
        if (data !== undefined) {
            options.body = JSON.stringify(data);
        }
        const res = await fetch(url, options);
        let body;
        try {
            body = await res.json();
        } catch (e) {
            body = { success: false, message: 'Reponse invalide du serveur.' };
        }
        if (!res.ok) {
            const err = new Error(body.message || 'Erreur serveur');
            err.status = res.status;
            err.errors = body.errors || [];
            throw err;
        }
        return body;
    },
};
