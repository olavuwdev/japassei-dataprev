/* Geração de flashcards por IA: envio, acompanhamento e aprovação. */
(function () {
    'use strict';

    const root = document.getElementById('ai-root');
    if (!root) { return; }

    const urls = JSON.parse(root.dataset.urls);
    const taxonomy = JSON.parse(document.getElementById('taxonomy-data').textContent);

    const el = (id) => document.getElementById(id);

    const state = { job: null, suggestions: [] };

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function url(template, uuid) {
        return template.replace('__UUID__', uuid);
    }

    // -------------------------------------------------------------- etapas

    function setStep(step, status) {
        const order = ['fetch', 'clean', 'concepts', 'generating', 'validating'];
        const index = order.indexOf(step);

        document.querySelectorAll('.ai-step').forEach((node, i) => {
            node.classList.remove('is-active', 'is-done', 'is-error');

            if (status === 'error' && i === Math.max(index, 0)) {
                node.classList.add('is-error');
            } else if (i < index) {
                node.classList.add('is-done');
            } else if (i === index) {
                node.classList.add(status === 'done' ? 'is-done' : 'is-active');
            }
        });
    }

    function message(text, tone) {
        const node = el('ai-status-message');
        node.textContent = text;
        node.className = 'text-small ' + (tone === 'error' ? 'text-danger' : 'text-muted');
    }

    // ------------------------------------------------------------- geração

    async function generate() {
        const type = el('ai-source-type').value;

        const body = {
            source_type: type,
            url: el('ai-url').value.trim(),
            content: el('ai-content').value.trim(),
            title: el('ai-title').value.trim(),
            subject_id: el('ai-subject').value || null,
            topic_id: el('ai-topic').value || null,
            quantity: el('ai-quantity').value || null,
            depth: el('ai-depth').value,
            types: Array.from(document.querySelectorAll('input[name="ai-type"]:checked')).map((i) => i.value)
        };

        if (type === 'url' && body.url === '') {
            JP.toast('Informe o endereço da página.', 'error');
            return;
        }

        if (type === 'text' && body.content === '') {
            JP.toast('Cole o conteúdo que deve virar flashcards.', 'error');
            return;
        }

        const button = el('ai-generate');
        button.disabled = true;

        setStep('fetch', 'active');
        message('Enviando o conteúdo…');

        const created = await JP.api(urls.create, { method: 'POST', body });

        if (!created.ok) {
            button.disabled = false;
            setStep('fetch', 'error');
            message(created.message || 'Não foi possível iniciar a geração.', 'error');
            JP.toast(created.message || 'Erro ao iniciar a geração.', 'error', 6000);
            return;
        }

        state.job = created.data.job;

        setStep('concepts', 'active');
        message('A IA está analisando o conteúdo. Isso pode levar alguns segundos…');

        await processJob();
        button.disabled = false;
    }

    async function processJob() {
        setStep('generating', 'active');

        const payload = await JP.api(url(urls.process, state.job.uuid), { method: 'POST', body: {} });

        if (!payload.ok) {
            setStep('generating', 'error');
            message(payload.message || 'A geração falhou. O conteúdo foi salvo e pode ser reprocessado.', 'error');
            JP.toast(payload.message || 'A geração falhou.', 'error', 7000);
            return;
        }

        state.job = payload.data.job;

        setStep('validating', 'done');
        message('Geração concluída: ' + state.job.cards + ' cartão(ões) sugerido(s).');

        await loadResult();
    }

    async function loadResult() {
        const payload = await JP.api(url(urls.result, state.job.uuid));

        if (!payload.ok) {
            JP.toast(payload.message || 'Erro ao carregar as sugestões.', 'error');
            return;
        }

        state.job = payload.data.job;
        state.suggestions = payload.data.suggestions;

        renderSuggestions();
    }

    // ---------------------------------------------------------- sugestões

    function renderSuggestions() {
        const card = el('ai-result-card');
        const list = el('ai-suggestions');

        card.hidden = false;
        el('ai-result-count').textContent = state.suggestions.length;

        const warnings = el('ai-warnings');
        warnings.innerHTML = (state.job.warnings || []).map((w) =>
            '<div class="flash flash-warning">' + esc(w) + '</div>'
        ).join('');

        if (state.suggestions.length === 0) {
            list.innerHTML = '<div class="empty-state"><p>Nenhuma sugestão pendente para esta geração.</p></div>';
            return;
        }

        list.innerHTML = state.suggestions.map(renderSuggestion).join('');

        list.querySelectorAll('[data-act="remove"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                btn.closest('.suggestion').classList.toggle('is-rejected');
                const checkbox = btn.closest('.suggestion').querySelector('input[type="checkbox"]');
                checkbox.checked = false;
            });
        });

        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderSuggestion(item) {
        const tags = item.tags ? (JSON.parse(item.tags) || []) : [];
        const typeLabel = taxonomy.types[item.card_type] || item.card_type;

        return '' +
            '<div class="suggestion' + (item.duplicate_of ? ' is-duplicate' : '') + '" data-id="' + item.id + '">' +
            '  <div class="suggestion-head">' +
            '    <label class="checkbox-row"><input type="checkbox" checked> Aprovar</label>' +
            '    <span class="chip chip-info">' + esc(typeLabel) + '</span>' +
            (item.difficulty ? '<span class="chip">' + esc(item.difficulty) + '</span>' : '') +
            (item.confidence ? '<span class="chip">confiança ' + Math.round(item.confidence * 100) + '%</span>' : '') +
            (item.duplicate_of ? '<span class="chip chip-gold">Possível duplicidade</span>' : '') +
            '    <button type="button" class="btn btn-sm btn-ghost" data-act="remove" style="margin-left:auto">Descartar</button>' +
            '  </div>' +
            '  <label class="field-label" for="q-' + item.id + '">Pergunta</label>' +
            '  <textarea id="q-' + item.id + '" data-field="question" rows="2">' + esc(stripTags(item.question)) + '</textarea>' +
            '  <label class="field-label" for="a-' + item.id + '">Resposta</label>' +
            '  <textarea id="a-' + item.id + '" data-field="answer" rows="2">' + esc(stripTags(item.answer)) + '</textarea>' +
            (item.explanation ?
            '  <label class="field-label" for="e-' + item.id + '">Explicação</label>' +
            '  <textarea id="e-' + item.id + '" data-field="explanation" rows="2">' + esc(stripTags(item.explanation)) + '</textarea>' : '') +
            (item.source_excerpt ?
            '  <p class="text-small text-muted">Trecho da fonte: “' + esc(stripTags(item.source_excerpt)) + '”</p>' : '') +
            (tags.length ? '  <div class="flex gap-1 flex-wrap">' + tags.map((t) => '<span class="chip">' + esc(t) + '</span>').join('') + '</div>' : '') +
            '</div>';
    }

    function stripTags(html) {
        if (!html) { return ''; }
        const div = document.createElement('div');
        div.innerHTML = html;
        return div.textContent || '';
    }

    function selected() {
        return Array.from(document.querySelectorAll('.suggestion'))
            .filter((node) => node.querySelector('input[type="checkbox"]').checked);
    }

    // ---------------------------------------------------------- aprovação

    async function approve() {
        const nodes = selected();

        if (nodes.length === 0) {
            JP.toast('Selecione ao menos um cartão.', 'error');
            return;
        }

        const ids = [];
        const edits = {};

        nodes.forEach((node) => {
            const id = Number(node.dataset.id);
            ids.push(id);

            const edit = {};
            node.querySelectorAll('[data-field]').forEach((field) => {
                edit[field.dataset.field] = field.value.trim();
            });

            edits[id] = edit;
        });

        const button = el('ai-approve');
        button.disabled = true;

        const payload = await JP.api(url(urls.approve, state.job.uuid), { method: 'POST', body: { ids, edits } });
        button.disabled = false;

        JP.toast(payload.message || (payload.ok ? 'Cartões salvos!' : 'Erro ao salvar.'), payload.ok ? 'success' : 'error', 6000);

        if (payload.ok) {
            if (window.JP.celebrate) { JP.celebrate(); }
            setTimeout(() => { window.location.href = urls.cards; }, 900);
        }
    }

    async function discard() {
        const nodes = selected();

        if (nodes.length === 0) {
            JP.toast('Selecione as sugestões que deseja descartar.', 'error');
            return;
        }

        const ids = nodes.map((node) => Number(node.dataset.id));
        const payload = await JP.api(url(urls.reject, state.job.uuid), { method: 'POST', body: { ids } });

        JP.toast(payload.message || '', payload.ok ? 'success' : 'error');

        if (payload.ok) { await loadResult(); }
    }

    async function regenerate() {
        if (!state.job) { return; }

        const ok = await JP.confirmDialog('Descartar as sugestões atuais e pedir uma nova geração?', {
            title: 'Gerar novamente',
            okLabel: 'Gerar novamente'
        });

        if (!ok) { return; }

        message('Refazendo a geração…');
        setStep('generating', 'active');

        const payload = await JP.api(url(urls.retry, state.job.uuid), { method: 'POST', body: {} });

        if (!payload.ok) {
            setStep('generating', 'error');
            message(payload.message || 'Não foi possível refazer a geração.', 'error');
            return;
        }

        state.job = payload.data.job;
        setStep('validating', 'done');
        await loadResult();
    }

    // ------------------------------------------------------------- eventos

    el('ai-source-type').addEventListener('change', (ev) => {
        const isUrl = ev.target.value === 'url';
        el('ai-url-field').hidden = !isUrl;
        el('ai-content-field').hidden = isUrl;
    });

    el('ai-subject').addEventListener('change', (ev) => {
        const subjectId = Number(ev.target.value);

        Array.from(el('ai-topic').options).forEach((option) => {
            if (!option.value) { return; }
            option.hidden = subjectId > 0 && Number(option.dataset.subject) !== subjectId;
        });

        el('ai-topic').value = '';
    });

    el('ai-generate').addEventListener('click', generate);
    el('ai-approve').addEventListener('click', approve);
    el('ai-discard').addEventListener('click', discard);
    el('ai-regenerate').addEventListener('click', regenerate);

    el('ai-select-all').addEventListener('click', () => {
        document.querySelectorAll('.suggestion input[type="checkbox"]').forEach((c) => { c.checked = true; });
    });

    el('ai-select-none').addEventListener('click', () => {
        document.querySelectorAll('.suggestion input[type="checkbox"]').forEach((c) => { c.checked = false; });
    });

    // Abertura direta de uma geração existente (ex.: "Melhorar com IA").
    if (root.dataset.job) {
        state.job = { uuid: root.dataset.job, warnings: [] };
        loadResult();
    }
})();
