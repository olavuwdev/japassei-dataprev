/* Sessão de revisão de flashcards — recordação ativa + FSRS.
   Depende de window.JP (api, toast, confirmDialog) definido em app.js. */
(function () {
    'use strict';

    const root = document.getElementById('review-root');
    if (!root) { return; }

    const cfg = JSON.parse(root.dataset.config || '{}');

    const el = {
        stage: document.getElementById('review-stage'),
        counters: document.getElementById('review-counters'),
        progressFill: document.getElementById('review-progress-fill'),
        progressLabel: document.getElementById('review-progress-label'),
        context: document.getElementById('review-context'),
        timer: document.getElementById('review-timer'),
        start: document.getElementById('review-start'),
        intro: document.getElementById('review-intro'),
        tools: document.getElementById('review-tools'),
    };

    const RATINGS = [
        { value: 1, label: 'Não lembrei', key: '1', cls: 'rating-again' },
        { value: 2, label: 'Difícil', key: '2', cls: 'rating-hard' },
        { value: 3, label: 'Bom', key: '3', cls: 'rating-good' },
        { value: 4, label: 'Fácil', key: '4', cls: 'rating-easy' }
    ];

    const state = {
        session: null,
        card: null,
        revealed: false,
        sending: false,
        shownAt: 0,
        revealedAt: 0,
        answered: 0,
        canUndo: false,
        timerHandle: null,
        startedAt: 0,
        countdown: null
    };

    // ------------------------------------------------------------- utilidades

    function uuid() {
        if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
        });
    }

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function isTyping() {
        const active = document.activeElement;
        if (!active) { return false; }
        const tag = active.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || active.isContentEditable;
    }

    // ------------------------------------------------------------ ciclo da sessão

    async function startSession() {
        const payload = await JP.api(cfg.urls.session, {
            method: 'POST',
            body: { subject_id: cfg.filters.subject_id, topic_id: cfg.filters.topic_id }
        });

        if (!payload.ok) {
            JP.toast(payload.message || 'Não foi possível iniciar a sessão.', 'error');
            return;
        }

        state.session = payload.data.session;
        state.startedAt = Date.now();
        state.answered = 0;
        state.canUndo = false;

        if (el.intro) { el.intro.hidden = true; }
        if (el.tools) { el.tools.hidden = false; }

        startTimer();
        await loadNext();
    }

    async function loadNext() {
        clearCountdown();

        const url = cfg.urls.next.replace('__UUID__', state.session.uuid);
        const payload = await JP.api(url);

        if (!payload.ok) {
            JP.toast(payload.message || 'Erro ao carregar o próximo cartão.', 'error');
            return;
        }

        renderCounters(payload.data.counts);
        renderProgress(payload.data.progress);

        if (!payload.data.card) {
            if (payload.data.next_due_in_seconds != null) {
                renderWaiting(payload.data.next_due_in_seconds);
            } else {
                finishSession();
            }
            return;
        }

        state.card = payload.data.card;
        state.revealed = false;
        state.shownAt = Date.now();
        renderCard();
    }

    async function finishSession() {
        clearTimer();
        clearCountdown();

        const url = cfg.urls.finish.replace('__UUID__', state.session.uuid);
        const payload = await JP.api(url, { method: 'POST' });

        if (!payload.ok) {
            JP.toast(payload.message || 'Erro ao encerrar a sessão.', 'error');
            return;
        }

        renderSummary(payload.data);

        if (payload.data.reviewed > 0 && window.JP.celebrate) { JP.celebrate(); }
    }

    // --------------------------------------------------------------- render

    function renderCard() {
        const card = state.card;
        const typed = card.card_type === 'typed_answer';

        el.stage.innerHTML =
            '<article class="flashcard" aria-live="polite">' +
            '  <div class="flashcard-scroll">' +
            '    <div class="flashcard-question" id="flashcard-question">' + card.front + '</div>' +
            (typed ? '<div class="typed-answer"><input type="text" id="typed-input" autocomplete="off" ' +
                     'aria-label="Digite sua resposta" placeholder="Digite sua resposta e pressione Enter"></div>' : '') +
            '  </div>' +
            '  <div class="flashcard-actions">' +
            '    <button type="button" class="btn btn-primary btn-lg btn-reveal" id="btn-reveal">Mostrar resposta</button>' +
            '  </div>' +
            '</article>';

        const reveal = document.getElementById('btn-reveal');
        reveal.addEventListener('click', revealAnswer);

        const typedInput = document.getElementById('typed-input');
        if (typedInput) {
            typedInput.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    revealAnswer();
                }
            });
            typedInput.focus();
        } else {
            reveal.focus();
        }
    }

    function revealAnswer() {
        if (state.revealed) { return; }

        state.revealed = true;
        state.revealedAt = Date.now();

        const card = state.card;
        const typedInput = document.getElementById('typed-input');
        const userAnswer = typedInput ? typedInput.value.trim() : null;

        const parts = [
            '<hr class="flashcard-divider">',
            '<div class="flashcard-answer flashcard-reveal">' + card.back + '</div>'
        ];

        if (userAnswer !== null) {
            parts.push(renderTypedComparison(userAnswer, card.back));
        }

        if (card.explanation) {
            parts.push('<div class="flashcard-explanation flashcard-reveal">' + card.explanation + '</div>');
        }

        if (card.example) {
            parts.push('<div class="flashcard-explanation flashcard-reveal">' + card.example + '</div>');
        }

        if (card.source_excerpt) {
            parts.push('<div class="flashcard-excerpt flashcard-reveal">' + card.source_excerpt + '</div>');
        }

        const scroll = el.stage.querySelector('.flashcard-scroll');
        scroll.insertAdjacentHTML('beforeend', parts.join(''));

        if (typedInput) { typedInput.disabled = true; }

        el.stage.querySelector('.flashcard-actions').outerHTML = renderRatings();

        el.stage.querySelectorAll('[data-rating]').forEach((btn) => {
            btn.addEventListener('click', () => submitRating(Number(btn.dataset.rating), btn));
        });

        const good = el.stage.querySelector('[data-rating="3"]');
        if (good) { good.focus(); }
    }

    /** Compara a resposta digitada com a esperada. Não decide a avaliação. */
    function renderTypedComparison(userAnswer, expectedHtml) {
        const expected = normalize(stripHtml(expectedHtml));
        const given = normalize(userAnswer);

        if (given === '') {
            return '<p class="typed-diff flashcard-reveal">Você não digitou uma resposta.</p>';
        }

        const match = given === expected;

        return '<p class="typed-diff flashcard-reveal">Sua resposta: <span class="' +
            (match ? 'is-ok' : 'is-off') + '">' + esc(userAnswer) + '</span>' +
            (match ? ' — idêntica à esperada.' : ' — diferente da esperada. Avalie você mesmo o quanto lembrou.') +
            '</p>';
    }

    function stripHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html;
        return div.textContent || '';
    }

    function normalize(text) {
        return text.toLowerCase()
            .normalize('NFD').replace(/\p{Diacritic}/gu, '')
            .replace(/[^a-z0-9\s]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function renderRatings() {
        const intervals = state.card.intervals || {};
        const showIntervals = cfg.showIntervals;

        const buttons = RATINGS.map((r) => {
            const label = showIntervals && intervals[r.value] ? intervals[r.value] : '';

            return '<button type="button" class="rating-btn ' + r.cls + '" data-rating="' + r.value + '"' +
                ' aria-label="' + r.label + (label ? ', próxima revisão em ' + label : '') + ' (tecla ' + r.key + ')">' +
                '<span class="rating-key">' + r.key + '</span>' +
                '<span>' + r.label + '</span>' +
                (label ? '<span class="rating-interval">' + esc(label) + '</span>' : '') +
                '</button>';
        }).join('');

        return '<div class="rating-grid" role="group" aria-label="Como foi sua lembrança?">' + buttons + '</div>';
    }

    async function submitRating(rating, button) {
        if (state.sending || !state.revealed) { return; }

        state.sending = true;
        el.stage.querySelectorAll('[data-rating]').forEach((b) => { b.disabled = true; });

        const url = cfg.urls.review.replace('__UUID__', state.session.uuid);

        // O identificador torna a requisição idempotente: duplo clique ou
        // reenvio por falha de rede não gera duas revisões.
        const payload = await JP.api(url, {
            method: 'POST',
            body: {
                card_id: state.card.id,
                rating: rating,
                request_uuid: uuid(),
                state_version: state.card.state_version,
                question_ms: state.revealedAt - state.shownAt,
                answer_ms: Date.now() - state.revealedAt
            }
        });

        state.sending = false;

        if (!payload.ok) {
            el.stage.querySelectorAll('[data-rating]').forEach((b) => { b.disabled = false; });
            JP.toast(payload.message || 'Não foi possível registrar a avaliação.', 'error', 6000);
            return;
        }

        if (!payload.data.duplicate) {
            state.answered++;
            state.canUndo = true;

            if (window.JP.xpPop && rating >= 3 && button) { JP.xpPop(1, button); }
        }

        await loadNext();
    }

    function renderCounters(counts) {
        if (!el.counters) { return; }

        el.counters.innerHTML =
            '<span class="review-counter counter-new">Novos: <b>' + counts.new + '</b></span>' +
            '<span class="review-counter counter-learning">Aprendendo: <b>' + counts.learning + '</b></span>' +
            '<span class="review-counter counter-review">Revisões: <b>' + counts.review + '</b></span>';
    }

    function renderProgress(progress) {
        if (!el.progressFill) { return; }

        const planned = Math.max(progress.planned, 1);
        const done = Math.min(progress.reviewed, planned);

        el.progressFill.style.width = ((done / planned) * 100).toFixed(1) + '%';
        el.progressLabel.textContent = done + ' de ' + planned;
    }

    function renderWaiting(seconds) {
        el.stage.innerHTML =
            '<article class="flashcard">' +
            '  <div class="review-done">' +
            '    <h2>Quase lá</h2>' +
            '    <p class="subtitle">Os próximos cartões de aprendizado voltam em <b id="review-countdown">' +
                 JP.formatSeconds(seconds) + '</b>.</p>' +
            '    <div class="flex gap-2 justify-center mt-2" style="justify-content:center">' +
            '      <button type="button" class="btn btn-ghost" id="btn-wait-refresh">Verificar agora</button>' +
            '      <button type="button" class="btn btn-primary" id="btn-wait-finish">Encerrar sessão</button>' +
            '    </div>' +
            '  </div>' +
            '</article>';

        document.getElementById('btn-wait-refresh').addEventListener('click', loadNext);
        document.getElementById('btn-wait-finish').addEventListener('click', finishSession);

        let remaining = seconds;
        state.countdown = setInterval(() => {
            remaining--;
            const target = document.getElementById('review-countdown');

            if (remaining <= 0) {
                clearCountdown();
                loadNext();
                return;
            }

            if (target) { target.textContent = JP.formatSeconds(remaining); }
        }, 1000);
    }

    function renderSummary(data) {
        const minutes = Math.round((data.duration || 0) / 60);

        el.stage.innerHTML =
            '<article class="flashcard">' +
            '  <div class="review-done">' +
            '    <h2>Sessão concluída</h2>' +
            '    <p class="subtitle">' + data.reviewed + ' cartão(ões) revisado(s) em ' +
                 (minutes >= 1 ? minutes + ' min' : Math.max(1, data.duration) + ' s') + '.</p>' +
            '    <div class="grid grid-4">' +
            statTile('Não lembrei', data.again) +
            statTile('Difícil', data.hard) +
            statTile('Bom', data.good) +
            statTile('Fácil', data.easy) +
            '    </div>' +
            '    <div class="grid grid-2 mt-2">' +
            statTile('Tempo médio', (data.average_ms ? Math.round(data.average_ms / 100) / 10 : 0) + ' s') +
            statTile('Ainda pendentes', data.remaining.total) +
            '    </div>' +
            (data.hardest ? '<p class="text-muted mt-2">Disciplina com mais esquecimentos: <b>' + esc(data.hardest) + '</b></p>' : '') +
            '    <div class="flex gap-2 mt-3" style="justify-content:center">' +
            '      <a class="btn btn-ghost" href="' + cfg.urls.dashboard + '">Voltar ao painel</a>' +
            (data.remaining.total > 0 ? '<button type="button" class="btn btn-primary" id="btn-restart">Continuar revisando</button>' : '') +
            '    </div>' +
            '  </div>' +
            '</article>';

        const restart = document.getElementById('btn-restart');
        if (restart) { restart.addEventListener('click', startSession); }

        if (el.tools) { el.tools.hidden = true; }
    }

    function statTile(label, value) {
        return '<div class="stat"><span class="stat-value">' + esc(value) + '</span>' +
            '<span class="stat-label">' + esc(label) + '</span></div>';
    }

    // ---------------------------------------------------------------- ações

    async function undoLast() {
        if (!state.session || !state.canUndo) {
            JP.toast('Não há resposta para desfazer.', 'default');
            return;
        }

        const url = cfg.urls.undo.replace('__UUID__', state.session.uuid);
        const payload = await JP.api(url, { method: 'POST' });

        if (!payload.ok) {
            JP.toast(payload.message || 'Não foi possível desfazer.', 'error');
            return;
        }

        state.canUndo = false;
        state.answered = Math.max(0, state.answered - 1);
        JP.toast('Última resposta desfeita.', 'success');
        await loadNext();
    }

    async function suspendCard() {
        if (!state.card) { return; }

        const ok = await JP.confirmDialog('Suspender este cartão? Ele deixará de aparecer nas revisões até ser reativado.', {
            title: 'Suspender cartão',
            okLabel: 'Suspender'
        });

        if (!ok) { return; }

        const payload = await JP.api(cfg.urls.suspend.replace('__ID__', state.card.id), {
            method: 'POST',
            body: { suspended: true }
        });

        JP.toast(payload.message || (payload.ok ? 'Cartão suspenso.' : 'Erro ao suspender.'), payload.ok ? 'success' : 'error');

        if (payload.ok) { await loadNext(); }
    }

    function editCard() {
        if (!state.card) { return; }
        window.open(cfg.urls.cards + '?busca=', '_blank');
    }

    // --------------------------------------------------------------- timer

    function startTimer() {
        if (!cfg.showTimer || !el.timer) { return; }

        el.timer.hidden = false;
        clearTimer();

        state.timerHandle = setInterval(() => {
            el.timer.textContent = JP.formatSeconds((Date.now() - state.startedAt) / 1000);
        }, 1000);
    }

    function clearTimer() {
        if (state.timerHandle) { clearInterval(state.timerHandle); state.timerHandle = null; }
    }

    function clearCountdown() {
        if (state.countdown) { clearInterval(state.countdown); state.countdown = null; }
    }

    // ------------------------------------------------------------- teclado

    function onKeyDown(ev) {
        if (!cfg.shortcuts || isTyping() || !state.session) { return; }
        if (ev.ctrlKey || ev.metaKey || ev.altKey) { return; }

        const key = ev.key;

        if ((key === ' ' || key === 'Enter') && !state.revealed && state.card) {
            ev.preventDefault();
            revealAnswer();
            return;
        }

        if (['1', '2', '3', '4'].includes(key) && state.revealed) {
            ev.preventDefault();
            const button = el.stage.querySelector('[data-rating="' + key + '"]');
            if (button && !button.disabled) { submitRating(Number(key), button); }
            return;
        }

        const lower = key.toLowerCase();

        if (lower === 'z') { ev.preventDefault(); undoLast(); }
        else if (lower === 's') { ev.preventDefault(); suspendCard(); }
        else if (lower === 'e') { ev.preventDefault(); editCard(); }
        else if (key === 'Escape') { ev.preventDefault(); finishSession(); }
    }

    // ------------------------------------------------------------- ligação

    document.addEventListener('keydown', onKeyDown);

    if (el.start) { el.start.addEventListener('click', startSession); }

    const undoBtn = document.getElementById('btn-undo');
    if (undoBtn) { undoBtn.addEventListener('click', undoLast); }

    const suspendBtn = document.getElementById('btn-suspend');
    if (suspendBtn) { suspendBtn.addEventListener('click', suspendCard); }

    const finishBtn = document.getElementById('btn-finish');
    if (finishBtn) { finishBtn.addEventListener('click', finishSession); }

    if (cfg.autoStart) { startSession(); }
})();
