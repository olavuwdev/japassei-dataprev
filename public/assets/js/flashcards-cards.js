/* Cadastro, edição e manutenção dos cartões. */
(function () {
    'use strict';

    const dataEl = document.getElementById('taxonomy-data');
    if (!dataEl) { return; }

    const taxonomy = JSON.parse(dataEl.textContent);

    const urls = {
        create: '/flashcards/api/cartoes',
        show: (id) => '/flashcards/api/cartoes/' + id,
        update: (id) => '/flashcards/api/cartoes/' + id + '/editar',
        remove: (id) => '/flashcards/api/cartoes/' + id + '/excluir',
        suspend: (id) => '/flashcards/api/cartoes/' + id + '/suspender',
        improve: (id) => '/flashcards/api/cartoes/' + id + '/melhorar'
    };

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function optionsFor(list, selected, placeholder) {
        return '<option value="">' + placeholder + '</option>' + list.map((item) =>
            '<option value="' + item.id + '"' + (Number(selected) === item.id ? ' selected' : '') + '>' +
            esc(item.name) + '</option>'
        ).join('');
    }

    function typeOptions(selected) {
        return Object.keys(taxonomy.types).map((key) =>
            '<option value="' + key + '"' + (selected === key ? ' selected' : '') + '>' +
            esc(taxonomy.types[key]) + '</option>'
        ).join('');
    }

    /** Formulário compartilhado entre criação e edição. */
    function cardForm(card) {
        const c = card || {};

        return '' +
            '<h3 class="modal-title">' + (card ? 'Editar cartão' : 'Novo flashcard') + '</h3>' +
            '<div class="field">' +
            '  <label class="field-label" for="fc-type">Tipo</label>' +
            '  <select id="fc-type">' + typeOptions(c.card_type || 'basic') + '</select>' +
            '  <span class="text-small text-muted" id="fc-type-hint"></span>' +
            '</div>' +
            '<div class="field">' +
            '  <label class="field-label" for="fc-front">Pergunta</label>' +
            '  <textarea id="fc-front" rows="3" required>' + esc(stripTags(c.front)) + '</textarea>' +
            '</div>' +
            '<div class="field">' +
            '  <label class="field-label" for="fc-back">Resposta</label>' +
            '  <textarea id="fc-back" rows="3">' + esc(stripTags(c.back)) + '</textarea>' +
            '</div>' +
            '<div class="field">' +
            '  <label class="field-label" for="fc-explanation">Explicação (opcional)</label>' +
            '  <textarea id="fc-explanation" rows="2">' + esc(stripTags(c.explanation)) + '</textarea>' +
            '</div>' +
            '<div class="form-row">' +
            '  <div class="field">' +
            '    <label class="field-label" for="fc-subject">Disciplina</label>' +
            '    <select id="fc-subject">' + optionsFor(taxonomy.subjects, c.subject_id, 'Sem disciplina') + '</select>' +
            '  </div>' +
            '  <div class="field">' +
            '    <label class="field-label" for="fc-topic">Assunto</label>' +
            '    <select id="fc-topic">' + optionsFor(taxonomy.topics, c.topic_id, 'Sem assunto') + '</select>' +
            '  </div>' +
            '</div>' +
            (card ? '' :
            '<label class="checkbox-row"><input type="checkbox" id="fc-reverse"> ' +
            'Gerar também o cartão reverso (resposta → pergunta)</label>') +
            '<div class="modal-actions">' +
            '  <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>' +
            '  <button type="button" class="btn btn-primary" id="fc-save">Salvar</button>' +
            '</div>';
    }

    function stripTags(html) {
        if (!html) { return ''; }
        const div = document.createElement('div');
        div.innerHTML = html;
        return div.textContent || '';
    }

    function openForm(card) {
        const modal = JP.openModal(cardForm(card));
        const el = (id) => modal.el.querySelector('#' + id);

        const typeSelect = el('fc-type');
        const backField = el('fc-back').closest('.field');
        const hint = el('fc-type-hint');

        function syncType() {
            const isCloze = typeSelect.value === 'cloze';

            backField.hidden = isCloze;
            el('fc-front').previousElementSibling.textContent = isCloze ? 'Texto com lacunas' : 'Pergunta';
            hint.textContent = isCloze
                ? 'Marque as lacunas assim: A capital é {{c1::Brasília}}. Cada lacuna vira um cartão.'
                : (typeSelect.value === 'typed_answer' ? 'Você digitará a resposta antes de revelá-la.' : '');
        }

        typeSelect.addEventListener('change', syncType);
        syncType();

        // Filtra assuntos pela disciplina escolhida.
        const subjectSelect = el('fc-subject');
        const topicSelect = el('fc-topic');

        function syncTopics() {
            const subjectId = Number(subjectSelect.value);
            const current = topicSelect.value;
            const list = subjectId ? taxonomy.topics.filter((t) => t.subject_id === subjectId) : taxonomy.topics;

            topicSelect.innerHTML = optionsFor(list, current, 'Sem assunto');
        }

        subjectSelect.addEventListener('change', syncTopics);
        syncTopics();

        el('fc-save').addEventListener('click', async () => {
            const body = {
                card_type: typeSelect.value,
                question: el('fc-front').value.trim(),
                answer: el('fc-back').value.trim(),
                explanation: el('fc-explanation').value.trim(),
                subject_id: subjectSelect.value || null,
                topic_id: topicSelect.value || null
            };

            const reverseEl = el('fc-reverse');
            if (reverseEl) { body.reverse = reverseEl.checked; }

            const payload = await JP.api(card ? urls.update(card.id) : urls.create, { method: 'POST', body });

            JP.toast(payload.message || (payload.ok ? 'Salvo!' : 'Erro ao salvar.'), payload.ok ? 'success' : 'error');

            if (payload.ok) {
                modal.close();
                window.location.reload();
            }
        });

        el('fc-front').focus();
    }

    // ------------------------------------------------------------- eventos

    const newBtn = document.getElementById('btn-new-card');
    if (newBtn) { newBtn.addEventListener('click', () => openForm(null)); }

    const list = document.getElementById('card-list');
    if (!list) { return; }

    list.addEventListener('click', async (ev) => {
        const button = ev.target.closest('[data-act]');
        if (!button) { return; }

        const row = button.closest('[data-card-id]');
        const id = Number(row.dataset.cardId);
        const action = button.dataset.act;

        if (action === 'edit') {
            const payload = await JP.api(urls.show(id));

            if (!payload.ok) {
                JP.toast(payload.message || 'Cartão não encontrado.', 'error');
                return;
            }

            openForm(payload.data.card);
            return;
        }

        if (action === 'suspend') {
            const payload = await JP.api(urls.suspend(id), { method: 'POST', body: {} });
            JP.toast(payload.message || '', payload.ok ? 'success' : 'error');
            if (payload.ok) { window.location.reload(); }
            return;
        }

        if (action === 'improve') {
            button.disabled = true;
            JP.toast('Pedindo uma versão melhor à IA…', 'default');

            const payload = await JP.api(urls.improve(id), { method: 'POST', body: {} });
            button.disabled = false;

            JP.toast(payload.message || (payload.ok ? 'Sugestões geradas.' : 'Erro ao melhorar.'), payload.ok ? 'success' : 'error', 6000);

            if (payload.ok && payload.data.job) {
                window.location.href = '/flashcards/gerar?job=' + payload.data.job.uuid;
            }
            return;
        }

        if (action === 'delete') {
            const ok = await JP.confirmDialog('Excluir este cartão? O histórico de revisões será mantido.', {
                title: 'Excluir cartão',
                okLabel: 'Excluir',
                danger: true
            });

            if (!ok) { return; }

            const payload = await JP.api(urls.remove(id), { method: 'POST', body: {} });
            JP.toast(payload.message || '', payload.ok ? 'success' : 'error');
            if (payload.ok) { row.remove(); }
        }
    });
})();
