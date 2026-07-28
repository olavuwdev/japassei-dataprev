/* Já Passei — utilitários globais (sem dependências) */
(function () {
    'use strict';

    const csrfHeader = document.querySelector('meta[name="csrf-header"]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]');

    /**
     * Requisição JSON para a API interna, já com CSRF e tratamento de erro.
     * Retorna o payload { ok, message, data } ou lança em erro de rede.
     */
    async function api(url, options = {}) {
        const headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }, options.headers || {});

        if (csrfHeader && csrfToken) {
            headers[csrfHeader.content] = csrfToken.content;
        }

        if (options.body && !(options.body instanceof FormData) && typeof options.body === 'object') {
            headers['Content-Type'] = 'application/json';
            options = Object.assign({}, options, { body: JSON.stringify(options.body) });
        }

        const response = await fetch(url, Object.assign({}, options, { headers }));

        if (response.status === 401) {
            window.location.href = '/login';
            return { ok: false, message: 'Sessão expirada.' };
        }

        let payload;
        try {
            payload = await response.json();
        } catch (e) {
            payload = { ok: false, message: 'Resposta inesperada do servidor.' };
        }

        if (!response.ok && payload.message === undefined) {
            payload = { ok: false, message: 'Erro ' + response.status };
        }

        return payload;
    }

    function toast(message, type = 'default', timeout = 3200) {
        const root = document.getElementById('toast-root');
        if (!root) { return; }

        const el = document.createElement('div');
        el.className = 'toast' + (type !== 'default' ? ' is-' + type : '');
        el.textContent = message;
        root.appendChild(el);

        setTimeout(() => {
            el.style.transition = 'opacity .3s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 320);
        }, timeout);
    }

    /** Modal de confirmação. Retorna Promise<boolean>. */
    function confirmDialog(message, { title = 'Confirmar', okLabel = 'Confirmar', danger = false } = {}) {
        return new Promise((resolve) => {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop';
            backdrop.innerHTML =
                '<div class="modal" role="dialog" aria-modal="true">' +
                '  <h3 class="modal-title"></h3>' +
                '  <p class="modal-message"></p>' +
                '  <div class="modal-actions">' +
                '    <button type="button" class="btn btn-ghost" data-act="cancel">Cancelar</button>' +
                '    <button type="button" class="btn ' + (danger ? 'btn-danger' : 'btn-primary') + '" data-act="ok"></button>' +
                '  </div>' +
                '</div>';

            backdrop.querySelector('.modal-title').textContent = title;
            backdrop.querySelector('.modal-message').textContent = message;
            backdrop.querySelector('[data-act="ok"]').textContent = okLabel;

            function close(result) {
                backdrop.remove();
                resolve(result);
            }

            backdrop.addEventListener('click', (ev) => {
                if (ev.target === backdrop) { close(false); }
            });
            backdrop.querySelector('[data-act="cancel"]').addEventListener('click', () => close(false));
            backdrop.querySelector('[data-act="ok"]').addEventListener('click', () => close(true));

            document.body.appendChild(backdrop);
        });
    }

    /** Abre um modal com conteúdo HTML arbitrário. Retorna { close }. */
    function openModal(html, { onClose } = {}) {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.setAttribute('role', 'dialog');
        modal.innerHTML = html;
        backdrop.appendChild(modal);

        function close() {
            backdrop.remove();
            if (onClose) { onClose(); }
        }

        backdrop.addEventListener('click', (ev) => {
            if (ev.target === backdrop) { close(); }
        });
        modal.querySelectorAll('[data-modal-close]').forEach((btn) => btn.addEventListener('click', close));

        document.body.appendChild(backdrop);
        return { close, el: modal };
    }

    function xpPop(amount, anchorEl) {
        const el = document.createElement('div');
        el.className = 'xp-pop';
        el.textContent = '+' + amount + ' XP';
        const rect = anchorEl ? anchorEl.getBoundingClientRect() : { left: window.innerWidth / 2, top: window.innerHeight / 2 };
        el.style.left = (rect.left + (rect.width || 0) / 2) + 'px';
        el.style.top = (rect.top - 8) + 'px';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 1300);
    }

    function celebrate() {
        const colors = ['#F4581C', '#1B7A5E', '#D99A06', '#2563EB', '#FF8B54'];
        for (let i = 0; i < 60; i++) {
            const piece = document.createElement('div');
            piece.className = 'confetti-piece';
            piece.style.left = Math.random() * 100 + 'vw';
            piece.style.background = colors[Math.floor(Math.random() * colors.length)];
            piece.style.animationDelay = (Math.random() * 0.9) + 's';
            piece.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
            document.body.appendChild(piece);
            setTimeout(() => piece.remove(), 3800);
        }
    }

    function formatSeconds(total) {
        total = Math.max(0, Math.floor(total));
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        const mm = String(m).padStart(2, '0');
        const ss = String(s).padStart(2, '0');
        return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
    }

    window.JP = { api, toast, confirmDialog, openModal, xpPop, celebrate, formatSeconds };
})();
