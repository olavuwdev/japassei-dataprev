/* Tokens da API externa: criação, exibição única e revogação. */
(function () {
    'use strict';

    const scopesEl = document.getElementById('token-scopes');
    if (!scopesEl) { return; }

    const scopes = JSON.parse(scopesEl.textContent);

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    async function copyText(text, button) {
        try {
            await navigator.clipboard.writeText(text);
            JP.toast('Copiado!', 'success', 1600);
        } catch (e) {
            // Navegadores sem permissão de área de transferência.
            const area = document.createElement('textarea');
            area.value = text;
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
            JP.toast('Copiado!', 'success', 1600);
        }

        if (button) { button.blur(); }
    }

    // Botões genéricos de cópia.
    document.addEventListener('click', (ev) => {
        const button = ev.target.closest('[data-copy]');
        if (!button) { return; }

        const target = document.querySelector(button.dataset.copy);
        if (!target) { return; }

        copyText(target.value !== undefined ? target.value : target.textContent, button);
    });

    // ---------------------------------------------------------- novo token

    function tokenForm() {
        const options = Object.keys(scopes).map((key) =>
            '<label class="checkbox-row"><input type="checkbox" name="scope" value="' + key + '"' +
            (key === 'flashcards:import' ? ' checked' : '') + '> ' + esc(scopes[key]) +
            ' <span class="text-small text-muted">(' + esc(key) + ')</span></label>'
        ).join('');

        return '' +
            '<h3 class="modal-title">Novo token de integração</h3>' +
            '<div class="field">' +
            '  <label class="field-label" for="tk-name">Nome da integração</label>' +
            '  <input type="text" id="tk-name" placeholder="Ex.: ChatGPT pessoal" maxlength="100">' +
            '</div>' +
            '<fieldset class="field"><legend class="field-label">Permissões</legend>' + options + '</fieldset>' +
            '<div class="field">' +
            '  <label class="field-label" for="tk-approval">Comportamento padrão</label>' +
            '  <select id="tk-approval">' +
            '    <option value="1">Exigir aprovação antes de entrar na fila (recomendado)</option>' +
            '    <option value="0">Cadastrar automaticamente</option>' +
            '  </select>' +
            '</div>' +
            '<div class="field">' +
            '  <label class="field-label" for="tk-expires">Expira em (opcional)</label>' +
            '  <input type="date" id="tk-expires">' +
            '</div>' +
            '<div class="modal-actions">' +
            '  <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>' +
            '  <button type="button" class="btn btn-primary" id="tk-save">Gerar token</button>' +
            '</div>';
    }

    function showToken(token) {
        const modal = JP.openModal(
            '<h3 class="modal-title">Copie este token agora</h3>' +
            '<p>Por segurança, ele não será exibido novamente.</p>' +
            '<div class="field">' +
            '  <input type="text" id="tk-value" value="' + esc(token) + '" readonly>' +
            '</div>' +
            '<div class="modal-actions">' +
            '  <button type="button" class="btn btn-ghost" id="tk-copy">Copiar token</button>' +
            '  <button type="button" class="btn btn-primary" data-modal-close>Já copiei</button>' +
            '</div>',
            { onClose: () => window.location.reload() }
        );

        const input = modal.el.querySelector('#tk-value');
        input.select();

        modal.el.querySelector('#tk-copy').addEventListener('click', () => copyText(token));
    }

    const newBtn = document.getElementById('btn-new-token');

    if (newBtn) {
        newBtn.addEventListener('click', () => {
            const modal = JP.openModal(tokenForm());

            modal.el.querySelector('#tk-save').addEventListener('click', async () => {
                const selected = Array.from(modal.el.querySelectorAll('input[name="scope"]:checked')).map((i) => i.value);

                const payload = await JP.api('/flashcards/api/tokens', {
                    method: 'POST',
                    body: {
                        name: modal.el.querySelector('#tk-name').value.trim(),
                        scopes: selected,
                        requires_approval: modal.el.querySelector('#tk-approval').value === '1',
                        expires_at: modal.el.querySelector('#tk-expires').value || null
                    }
                });

                if (!payload.ok) {
                    JP.toast(payload.message || 'Erro ao gerar o token.', 'error');
                    return;
                }

                modal.close();
                showToken(payload.data.token);
            });

            modal.el.querySelector('#tk-name').focus();
        });
    }

    // ------------------------------------------------------------ revogar

    document.addEventListener('click', async (ev) => {
        const button = ev.target.closest('[data-revoke]');
        if (!button) { return; }

        const ok = await JP.confirmDialog(
            'Revogar este token? Integrações que o utilizam deixarão de funcionar imediatamente.',
            { title: 'Revogar token', okLabel: 'Revogar', danger: true }
        );

        if (!ok) { return; }

        const payload = await JP.api('/flashcards/api/tokens/' + button.dataset.revoke + '/revogar', {
            method: 'POST',
            body: {}
        });

        JP.toast(payload.message || '', payload.ok ? 'success' : 'error');

        if (payload.ok) { window.location.reload(); }
    });
})();
