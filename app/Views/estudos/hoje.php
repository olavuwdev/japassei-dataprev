<?php
/**
 * Página "Hoje" — foco total: tarefa, checklist, timer, revisões e ofensiva.
 *
 * @var array|null $mainTask
 * @var array      $otherTasks
 * @var array|null $nextTask
 * @var array|null $activeSession
 * @var array      $reviewsToday
 * @var array      $reviewsOverdue
 * @var array      $streak
 * @var array      $daily
 * @var string     $todayDate
 */
$nextTask = $nextTask ?? null;
$todayDate = $todayDate ?? date('Y-m-d');
$checklistData = array_map(static fn (array $item): array => [
    'id'                => (int) $item['id'],
    'title'             => (string) $item['title'],
    'estimated_minutes' => (int) $item['estimated_minutes'],
    'is_required'       => (bool) $item['is_required'],
    'is_completed'      => (bool) $item['is_completed'],
], $mainTask['checklist'] ?? []);

$sessionData = $activeSession !== null ? [
    'id'              => (int) $activeSession['id'],
    'status'          => (string) $activeSession['status'],
    'elapsed_seconds' => (int) $activeSession['elapsed_seconds'],
    'task_id'         => $activeSession['task_id'] !== null ? (int) $activeSession['task_id'] : null,
    'planned_minutes' => (int) $activeSession['planned_minutes'],
] : null;

$formatDate = static fn (string $date): string => date('d/m/Y', strtotime($date));
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Hoje<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Hoje<?= $this->endSection() ?>

<?= $this->section('head') ?>
<style>
    .icon-btn { background: none; border: none; cursor: pointer; font-size: .95rem; opacity: .55; padding: 2px 4px; }
    .icon-btn:hover { opacity: 1; }
    .checklist-item.is-current { border-color: var(--flame); box-shadow: 0 0 0 2px var(--flame-soft); }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Cabeçalho compacto: ofensiva + meta do dia -->
<div class="page-header">
    <div>
        <h1>Hoje</h1>
        <p class="subtitle"><?= esc($streak['message']) ?></p>
    </div>
    <div class="flex items-center gap-1 flex-wrap">
        <span class="chip chip-flame" title="Ofensiva atual">🔥 <span id="streak-count"><?= esc((string) $streak['current_streak']) ?></span> <?= (int) $streak['current_streak'] === 1 ? 'dia' : 'dias' ?></span>
        <?php if (! empty($streak['at_risk'])): ?>
            <span class="chip chip-danger" id="risk-chip">⚠ Em risco</span>
        <?php endif; ?>
        <span class="chip chip-primary" title="Meta do dia">⏱ <span id="daily-minutes"><?= esc((string) $daily['studied_minutes']) ?></span>/<?= esc((string) $daily['planned_minutes']) ?> min</span>
    </div>
</div>

<?php if ($mainTask === null && $activeSession === null): ?>
    <div class="card">
        <div class="empty-state">
            <span class="empty-state-icon" aria-hidden="true">🌤️</span>
            <h3>Nenhuma tarefa programada para hoje</h3>
            <p>
                A tela <strong>Hoje</strong> mostra apenas tarefas com data
                <strong><?= esc(date('d/m/Y', strtotime($todayDate))) ?></strong>
                (segunda a sexta no cronograma).
            </p>
            <?php if (! empty($nextTask)): ?>
                <div class="card mt-2 text-left" style="max-width: 480px; margin-left: auto; margin-right: auto;">
                    <p class="text-small text-muted mb-1">Próxima do cronograma</p>
                    <h3 class="mt-0"><?= esc($nextTask['title']) ?></h3>
                    <div class="flex items-center gap-1 flex-wrap mb-2">
                        <span class="chip">
                            <span class="chip-dot" style="background: <?= esc($nextTask['subject']['color'] ?? '#1B7A5E', 'attr') ?>"></span>
                            <?= esc($nextTask['subject']['name'] ?? 'Disciplina') ?>
                        </span>
                        <span class="chip">📅 <?= esc(date('d/m/Y', strtotime($nextTask['scheduled_date']))) ?></span>
                    </div>
                    <button type="button" class="btn btn-primary btn-block" id="btn-bring-today"
                            data-task-id="<?= esc((string) $nextTask['id'], 'attr') ?>">
                        Trazer para hoje e estudar
                    </button>
                </div>
            <?php else: ?>
                <div class="flex items-center gap-1" style="justify-content: center;">
                    <a href="<?= site_url('estudos/cronograma') ?>" class="btn btn-primary">Ver cronograma</a>
                    <a href="<?= site_url('estudos/revisoes') ?>" class="btn btn-ghost">Revisões</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($mainTask !== null): ?>
<!-- Tarefa principal do dia -->
<div class="card mb-2" id="main-task-card">
    <div class="card-header">
        <h2>📌 <?= esc($mainTask['title']) ?></h2>
        <span id="task-status-chip">
            <?php if ($mainTask['status'] === 'done'): ?>
                <span class="chip chip-primary">✅ Concluída</span>
            <?php elseif ($mainTask['status'] === 'in_progress'): ?>
                <span class="chip chip-gold">⏳ Em estudo</span>
            <?php endif; ?>
        </span>
    </div>

    <div class="flex items-center gap-1 flex-wrap mb-1">
        <span class="chip">
            <span class="chip-dot" style="background: <?= esc($mainTask['subject']['color'] ?? '#1B7A5E', 'attr') ?>"></span>
            <?= esc($mainTask['subject']['name'] ?? 'Disciplina') ?>
        </span>
        <span class="chip chip-info">⏱ <?= esc((string) $mainTask['estimated_minutes']) ?> min previstos</span>
        <?php if (! empty($mainTask['topic'])): ?>
            <span class="chip"><?= esc($mainTask['topic']['name']) ?></span>
        <?php endif; ?>
    </div>

    <div class="progress-label">
        <span>Checklist</span>
        <span id="checklist-percent-label">—</span>
    </div>
    <div class="progress mb-2">
        <div class="progress-bar" id="checklist-progress-bar" style="width: 0%"></div>
    </div>

    <ul class="checklist" id="checklist" aria-label="Checklist da tarefa"></ul>

    <div class="mt-1">
        <button type="button" class="btn btn-ghost btn-sm" id="btn-add-item">＋ Adicionar item</button>
    </div>
</div>
<?php endif; ?>

<?php if ($mainTask !== null || $activeSession !== null): ?>
<!-- TIMER -->
<div class="card mb-2 text-center" id="timer-card">
    <div class="timer-display" id="timer-display">00:00</div>
    <div class="timer-stage" id="timer-stage"></div>
    <div class="timer-controls">
        <button type="button" class="btn btn-flame btn-lg" id="btn-start" hidden>▶ Iniciar estudo</button>
        <button type="button" class="btn btn-primary" id="btn-pause" hidden>⏸ Pausar</button>
        <button type="button" class="btn btn-primary" id="btn-resume" hidden>▶ Continuar</button>
        <button type="button" class="btn btn-primary" id="btn-finish" hidden>✅ Concluir</button>
        <button type="button" class="btn btn-ghost" id="btn-cancel" hidden>✖ Cancelar</button>
    </div>
</div>
<?php endif; ?>

<?php if ($otherTasks !== []): ?>
<!-- Demais tarefas do dia -->
<div class="card mb-2">
    <h3>Outras tarefas de hoje</h3>
    <ul class="checklist">
        <?php foreach ($otherTasks as $task): ?>
            <li class="checklist-item<?= $task['status'] === 'done' ? ' is-done' : '' ?>">
                <span class="checklist-check<?= $task['status'] === 'done' ? ' is-checked' : '' ?>" aria-hidden="true">✓</span>
                <span class="checklist-title"><?= esc($task['title']) ?></span>
                <span class="chip">
                    <span class="chip-dot" style="background: <?= esc($task['subject']['color'] ?? '#1B7A5E', 'attr') ?>"></span>
                    <?= esc($task['subject']['name'] ?? '') ?>
                </span>
                <span class="checklist-meta"><?= esc((string) $task['estimated_minutes']) ?> min</span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Revisões de hoje e atrasadas -->
<div class="card">
    <div class="card-header">
        <h3>🔁 Revisões</h3>
        <a href="<?= site_url('estudos/revisoes') ?>" class="btn btn-ghost btn-sm">Ver todas</a>
    </div>
    <?php if ($reviewsToday === [] && $reviewsOverdue === []): ?>
        <div class="empty-state">
            <span class="empty-state-icon" aria-hidden="true">🧘</span>
            <p class="mb-0">Nenhuma revisão pendente para hoje. Tudo em dia!</p>
        </div>
    <?php else: ?>
        <ul class="checklist">
            <?php foreach ($reviewsOverdue as $review): ?>
                <li class="checklist-item">
                    <span class="chip chip-danger">Atrasada</span>
                    <span class="checklist-title">
                        <?= esc($review['topic_name'] ?? $review['task_title'] ?? $review['subject_name']) ?>
                        <span class="text-faint text-small">· <?= esc($review['subject_name']) ?></span>
                    </span>
                    <span class="checklist-meta"><?= esc($formatDate($review['due_date'])) ?></span>
                </li>
            <?php endforeach; ?>
            <?php foreach ($reviewsToday as $review): ?>
                <li class="checklist-item">
                    <span class="chip chip-gold">Hoje</span>
                    <span class="checklist-title">
                        <?= esc($review['topic_name'] ?? $review['task_title'] ?? $review['subject_name']) ?>
                        <span class="text-faint text-small">· <?= esc($review['subject_name']) ?></span>
                    </span>
                    <span class="checklist-meta"><?= esc((string) $review['interval_days']) ?>º dia</span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="mt-1 mb-0 text-small text-muted">Conclua suas revisões na página <a href="<?= site_url('estudos/revisoes') ?>">Revisões</a>.</p>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    'use strict';

    // ------------------------------------------------------------------
    // Dados vindos do backend
    // ------------------------------------------------------------------
    var API       = <?= json_encode(site_url('estudos/api')) ?>;
    var TASK_ID   = <?= json_encode($mainTask !== null ? (int) $mainTask['id'] : null) ?>;
    var checklist = <?= json_encode($checklistData) ?>;
    var taskStatus = <?= json_encode($mainTask['status'] ?? null) ?>;

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------
    function escHtml(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function normalizeItem(raw) {
        return {
            id: Number(raw.id),
            title: String(raw.title),
            estimated_minutes: Number(raw.estimated_minutes || 0),
            is_required: Number(raw.is_required) > 0 || raw.is_required === true,
            is_completed: Number(raw.is_completed) > 0 || raw.is_completed === true
        };
    }

    function byId(id) { return document.getElementById(id); }

    // ==================================================================
    // TIMER — estado persistido no backend; aqui só a exibição local
    // ==================================================================
    var timer = {
        session: <?= json_encode($sessionData) ?>,
        baseElapsed: 0,
        runningSince: null,
        intervalId: null
    };

    function elapsedSeconds() {
        var extra = timer.runningSince !== null ? (Date.now() - timer.runningSince) / 1000 : 0;
        return Math.floor(timer.baseElapsed + extra);
    }

    function setSession(session) {
        timer.session = session ? {
            id: Number(session.id),
            status: String(session.status),
            task_id: session.task_id !== null && session.task_id !== undefined ? Number(session.task_id) : null,
            planned_minutes: Number(session.planned_minutes || 60)
        } : null;

        if (session) {
            timer.baseElapsed  = Number(session.elapsed_seconds || 0);
            timer.runningSince = session.status === 'running' ? Date.now() : null;
        } else {
            timer.baseElapsed  = 0;
            timer.runningSince = null;
        }

        if (timer.session && timer.session.status === 'running') {
            startTicker();
        } else {
            stopTicker();
        }

        renderTimer();
        updateControls();
    }

    function startTicker() {
        stopTicker();
        timer.intervalId = setInterval(renderTimer, 1000);
    }

    function stopTicker() {
        if (timer.intervalId !== null) {
            clearInterval(timer.intervalId);
            timer.intervalId = null;
        }
    }

    function renderTimer() {
        var display = byId('timer-display');
        if (!display) { return; }
        display.textContent = JP.formatSeconds(elapsedSeconds());
        renderStage();
    }

    // Etapas derivadas do checklist (soma dos minutos estimados)
    function currentStage() {
        var minutes = elapsedSeconds() / 60;
        var cumulative = 0;
        var stages = checklist.filter(function (item) { return item.estimated_minutes > 0; });

        for (var i = 0; i < stages.length; i++) {
            cumulative += stages[i].estimated_minutes;
            if (minutes < cumulative) { return stages[i]; }
        }

        return null;
    }

    function renderStage() {
        var stageEl = byId('timer-stage');
        if (!stageEl) { return; }

        if (!timer.session) {
            stageEl.textContent = TASK_ID ? 'Pronto para começar' : '';
            highlightStage(null);
            return;
        }

        var stage = currentStage();
        if (stage) {
            stageEl.textContent = 'Etapa atual: ' + stage.title;
            highlightStage(stage.id);
        } else if (checklist.length > 0) {
            stageEl.textContent = 'Todas as etapas percorridas — conclua a sessão!';
            highlightStage(null);
        } else {
            stageEl.textContent = 'Sessão em andamento';
            highlightStage(null);
        }
    }

    function highlightStage(itemId) {
        document.querySelectorAll('#checklist .checklist-item').forEach(function (li) {
            li.classList.toggle('is-current', itemId !== null && Number(li.dataset.id) === itemId && timer.session !== null);
        });
    }

    function updateControls() {
        var hasSession = timer.session !== null;
        var running    = hasSession && timer.session.status === 'running';
        var paused     = hasSession && timer.session.status === 'paused';

        toggleBtn('btn-start',  !hasSession && TASK_ID !== null);
        toggleBtn('btn-pause',  running);
        toggleBtn('btn-resume', paused);
        toggleBtn('btn-finish', hasSession);
        toggleBtn('btn-cancel', hasSession);
    }

    function toggleBtn(id, visible) {
        var btn = byId(id);
        if (btn) { btn.hidden = !visible; }
    }

    // ------------------------------------------------------------------
    // Ações do timer (persistência sempre no backend)
    // ------------------------------------------------------------------
    async function startSession() {
        var res = await JP.api(API + '/sessao/iniciar', { method: 'POST', body: { task_id: TASK_ID } });
        if (!res.ok) { JP.toast(res.message || 'Não foi possível iniciar.', 'error'); return; }
        setSession(res.data.session);
        JP.toast(res.message || 'Sessão iniciada. Bons estudos!', 'success');
    }

    async function pauseSession() {
        var res = await JP.api(API + '/sessao/' + timer.session.id + '/pausar', { method: 'POST', body: {} });
        if (!res.ok) { JP.toast(res.message || 'Não foi possível pausar.', 'error'); return; }
        setSession(res.data.session);
        JP.toast('Sessão pausada.', 'success');
    }

    async function resumeSession() {
        var res = await JP.api(API + '/sessao/' + timer.session.id + '/retomar', { method: 'POST', body: {} });
        if (!res.ok) { JP.toast(res.message || 'Não foi possível retomar.', 'error'); return; }
        setSession(res.data.session);
        JP.toast('Sessão retomada.', 'success');
    }

    async function finishSession() {
        var res = await JP.api(API + '/sessao/' + timer.session.id + '/concluir', { method: 'POST', body: {} });
        if (!res.ok) { JP.toast(res.message || 'Não foi possível concluir.', 'error'); return; }

        var data = res.data;
        setSession(null);
        applyProgressFeedback(data);
        showSessionSummary(data);
    }

    async function cancelSession() {
        var confirmed = await JP.confirmDialog(
            'O tempo desta sessão será descartado e não contará para sua meta. Deseja realmente cancelar?',
            { title: 'Cancelar sessão', okLabel: 'Cancelar sessão', danger: true }
        );
        if (!confirmed) { return; }

        var res = await JP.api(API + '/sessao/' + timer.session.id + '/cancelar', { method: 'POST', body: {} });
        if (!res.ok) { JP.toast(res.message || 'Não foi possível cancelar.', 'error'); return; }
        setSession(null);
        JP.toast('Sessão cancelada.', 'success');
    }

    // Atualiza chips do cabeçalho e dispara comemorações
    function applyProgressFeedback(data) {
        if (data.streak && data.streak.current_streak !== undefined) {
            var streakEl = byId('streak-count');
            if (streakEl) { streakEl.textContent = data.streak.current_streak; }
            var risk = byId('risk-chip');
            if (risk && data.streak.at_risk === false) { risk.remove(); }
        }

        if (data.daily && data.daily.studied_minutes !== undefined) {
            var minutesEl = byId('daily-minutes');
            if (minutesEl) { minutesEl.textContent = data.daily.studied_minutes; }
        }

        var badges = data.new_badges || [];

        if (Number(data.xp_awarded) > 0) {
            JP.xpPop(Number(data.xp_awarded), byId('timer-display') || document.body);
            JP.toast('+' + data.xp_awarded + ' XP', 'xp');
        }

        if (data.goal_met_now || badges.length > 0) {
            JP.celebrate();
        }

        badges.forEach(function (badge) {
            JP.toast('🏅 Conquista desbloqueada: ' + (badge.name || badge.title || badge.code), 'xp', 4200);
        });
    }

    function showSessionSummary(data) {
        var badges = data.new_badges || [];

        var html =
            '<h3 class="modal-title">Sessão concluída! 🎉</h3>' +
            '<div class="grid grid-2">' +
            '  <div class="stat"><div class="stat-label">Duração</div><div class="stat-value">' + escHtml(JP.formatSeconds(Number(data.duration_seconds || 0))) + '</div></div>' +
            '  <div class="stat"><div class="stat-label">XP ganho</div><div class="stat-value">+' + escHtml(String(Number(data.xp_awarded || 0))) + '</div></div>' +
            '  <div class="stat"><div class="stat-label">Ofensiva</div><div class="stat-value">🔥 ' + escHtml(String(data.streak ? data.streak.current_streak : 0)) + '</div></div>' +
            '  <div class="stat"><div class="stat-label">Meta do dia</div><div class="stat-value">' + (data.goal_met_now || (data.daily && Number(data.daily.goal_met) > 0) ? '✅' : '⏳') + '</div></div>' +
            '</div>';

        if (badges.length > 0) {
            html += '<p class="mt-2 fw-bold">Novas conquistas:</p><div class="flex flex-wrap gap-1">';
            badges.forEach(function (badge) {
                html += '<span class="chip chip-gold">🏅 ' + escHtml(badge.name || badge.title || badge.code) + '</span>';
            });
            html += '</div>';
        }

        if (data.goal_met_now) {
            html += '<p class="mt-2 mb-0 fw-bold" style="color: var(--primary-dark);">Você concluiu sua meta de hoje. 👏</p>';
        }

        html += '<div class="modal-actions"><button type="button" class="btn btn-primary" data-modal-close>Continuar</button></div>';

        JP.openModal(html);
    }

    // ==================================================================
    // CHECKLIST
    // ==================================================================
    function renderChecklist() {
        var ul = byId('checklist');
        if (!ul) { return; }

        ul.innerHTML = '';

        if (checklist.length === 0) {
            var empty = document.createElement('li');
            empty.className = 'text-muted text-small';
            empty.textContent = 'Nenhum item no checklist. Adicione o primeiro!';
            ul.appendChild(empty);
        }

        checklist.forEach(function (item) {
            var li = document.createElement('li');
            li.className = 'checklist-item' + (item.is_completed ? ' is-done' : '');
            li.dataset.id = item.id;

            var check = document.createElement('button');
            check.type = 'button';
            check.className = 'checklist-check' + (item.is_completed ? ' is-checked' : '');
            check.dataset.action = 'toggle';
            check.setAttribute('aria-label', (item.is_completed ? 'Desmarcar' : 'Marcar') + ' item: ' + item.title);
            check.textContent = '✓';

            var title = document.createElement('span');
            title.className = 'checklist-title';
            title.textContent = item.title;

            if (item.is_required) {
                var star = document.createElement('span');
                star.className = 'text-faint';
                star.title = 'Item obrigatório';
                star.textContent = ' ★';
                title.appendChild(star);
            }

            var meta = document.createElement('span');
            meta.className = 'checklist-meta';
            meta.textContent = item.estimated_minutes > 0 ? item.estimated_minutes + ' min' : '';

            var edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'icon-btn';
            edit.dataset.action = 'edit';
            edit.setAttribute('aria-label', 'Editar item');
            edit.textContent = '✏️';

            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'icon-btn';
            del.dataset.action = 'delete';
            del.setAttribute('aria-label', 'Excluir item');
            del.textContent = '🗑️';

            li.append(check, title, meta, edit, del);
            ul.appendChild(li);
        });

        updateChecklistBar();
        renderStage();
    }

    function updateChecklistBar() {
        var total = checklist.length;
        var done  = checklist.filter(function (item) { return item.is_completed; }).length;
        var percent = total > 0 ? Math.round(done / total * 100) : 0;

        var bar = byId('checklist-progress-bar');
        if (bar) { bar.style.width = percent + '%'; }

        var label = byId('checklist-percent-label');
        if (label) { label.textContent = done + '/' + total + ' · ' + percent + '%'; }
    }

    async function toggleItem(itemId, buttonEl) {
        buttonEl.disabled = true;
        var res = await JP.api(API + '/checklist/' + itemId + '/alternar', { method: 'POST', body: {} });
        buttonEl.disabled = false;

        if (!res.ok) { JP.toast(res.message || 'Não foi possível atualizar o item.', 'error'); return; }

        var updated = normalizeItem(res.data.item);
        checklist = checklist.map(function (item) { return item.id === updated.id ? updated : item; });
        renderChecklist();

        if (res.data.auto_completed) {
            taskStatus = 'done';
            setTaskStatusChip('done');
            JP.toast('Tarefa concluída automaticamente! 🎉', 'success');
            JP.celebrate();
            return;
        }

        if (res.data.suggest_complete && taskStatus !== 'done') {
            var confirmed = await JP.confirmDialog(
                'Todos os itens obrigatórios foram concluídos. Deseja marcar a tarefa como concluída?',
                { title: 'Concluir tarefa', okLabel: 'Concluir tarefa' }
            );
            if (confirmed) { completeTask(); }
        }
    }

    async function completeTask() {
        var res = await JP.api(API + '/tarefas/' + TASK_ID + '/concluir', { method: 'POST', body: {} });
        if (!res.ok) { JP.toast(res.message || 'Não foi possível concluir a tarefa.', 'error'); return; }

        var data = res.data;
        taskStatus = 'done';
        setTaskStatusChip('done');
        applyProgressFeedback(data);
        JP.toast('Tarefa concluída! 🎉', 'success');

        var created = data.reviews_created || [];
        if (created.length > 0) {
            JP.toast('🔁 ' + created.length + ' revisões programadas (1, 7 e 30 dias).', 'success', 4200);
        }
    }

    function setTaskStatusChip(status) {
        var holder = byId('task-status-chip');
        if (!holder) { return; }
        if (status === 'done') {
            holder.innerHTML = '<span class="chip chip-primary">✅ Concluída</span>';
        } else if (status === 'in_progress') {
            holder.innerHTML = '<span class="chip chip-gold">⏳ Em estudo</span>';
        } else {
            holder.innerHTML = '';
        }
    }

    // Recarrega tarefa + checklist do backend (após criar/editar/excluir item)
    async function refreshChecklist() {
        var res = await JP.api(API + '/tarefas/' + TASK_ID);
        if (!res.ok || !res.data.task) { return; }
        checklist  = (res.data.task.checklist || []).map(normalizeItem);
        taskStatus = res.data.task.status;
        setTaskStatusChip(taskStatus);
        renderChecklist();
    }

    // ------------------------------------------------------------------
    // Modal de criação / edição de item
    // ------------------------------------------------------------------
    function openItemModal(item) {
        var isEdit = !!item;
        var html =
            '<h3 class="modal-title">' + (isEdit ? 'Editar item' : 'Novo item do checklist') + '</h3>' +
            '<form id="item-form">' +
            '  <div class="field">' +
            '    <label for="item-title">Título</label>' +
            '    <input type="text" id="item-title" maxlength="255" required value="' + (isEdit ? escHtml(item.title) : '') + '">' +
            '  </div>' +
            '  <div class="field">' +
            '    <label for="item-minutes">Minutos estimados</label>' +
            '    <input type="number" id="item-minutes" min="0" max="600" value="' + (isEdit ? item.estimated_minutes : 10) + '">' +
            '  </div>' +
            '  <div class="field checkbox-row">' +
            '    <input type="checkbox" id="item-required"' + (isEdit && item.is_required ? ' checked' : '') + '>' +
            '    <label for="item-required" style="margin: 0;">Item obrigatório</label>' +
            '  </div>' +
            '  <div class="modal-actions">' +
            '    <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>' +
            '    <button type="submit" class="btn btn-primary">' + (isEdit ? 'Salvar' : 'Adicionar') + '</button>' +
            '  </div>' +
            '</form>';

        var modal = JP.openModal(html);

        modal.el.querySelector('#item-form').addEventListener('submit', async function (ev) {
            ev.preventDefault();

            var payload = {
                title: modal.el.querySelector('#item-title').value.trim(),
                estimated_minutes: Number(modal.el.querySelector('#item-minutes').value || 0),
                is_required: modal.el.querySelector('#item-required').checked
            };

            if (payload.title === '') {
                JP.toast('Informe o título do item.', 'error');
                return;
            }

            var res;
            if (isEdit) {
                res = await JP.api(API + '/checklist/' + item.id + '/editar', { method: 'POST', body: payload });
            } else {
                payload.task_id = TASK_ID;
                res = await JP.api(API + '/checklist', { method: 'POST', body: payload });
            }

            if (!res.ok) { JP.toast(res.message || 'Não foi possível salvar.', 'error'); return; }

            modal.close();
            JP.toast(isEdit ? 'Item atualizado.' : 'Item adicionado.', 'success');
            refreshChecklist();
        });
    }

    async function deleteItem(item) {
        var confirmed = await JP.confirmDialog(
            'Excluir o item "' + item.title + '"?',
            { title: 'Excluir item', okLabel: 'Excluir', danger: true }
        );
        if (!confirmed) { return; }

        var res = await JP.api(API + '/checklist/' + item.id + '/excluir', { method: 'POST', body: {} });
        if (!res.ok) { JP.toast(res.message || 'Não foi possível excluir.', 'error'); return; }

        JP.toast('Item excluído.', 'success');
        refreshChecklist();
    }

    // ==================================================================
    // Eventos
    // ==================================================================
    var checklistEl = byId('checklist');
    if (checklistEl) {
        checklistEl.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-action]');
            if (!btn) { return; }

            var li   = btn.closest('.checklist-item');
            var item = checklist.find(function (candidate) { return candidate.id === Number(li.dataset.id); });
            if (!item) { return; }

            if (btn.dataset.action === 'toggle') { toggleItem(item.id, btn); }
            if (btn.dataset.action === 'edit')   { openItemModal(item); }
            if (btn.dataset.action === 'delete') { deleteItem(item); }
        });
    }

    var addBtn = byId('btn-add-item');
    if (addBtn) { addBtn.addEventListener('click', function () { openItemModal(null); }); }

    var bringBtn = byId('btn-bring-today');
    if (bringBtn) {
        bringBtn.addEventListener('click', async function () {
            var taskId = Number(bringBtn.dataset.taskId);
            if (!taskId) { return; }

            bringBtn.disabled = true;
            var res = await JP.api(API + '/tarefas/' + taskId + '/reagendar', {
                method: 'POST',
                body: { bring_to_today: true }
            });

            if (!res.ok) {
                bringBtn.disabled = false;
                JP.toast(res.message || 'Não foi possível trazer a tarefa.', 'error');
                return;
            }

            JP.toast('Tarefa trazida para hoje!', 'success');
            window.location.reload();
        });
    }

    var bindings = {
        'btn-start': startSession,
        'btn-pause': pauseSession,
        'btn-resume': resumeSession,
        'btn-finish': finishSession,
        'btn-cancel': cancelSession
    };
    Object.keys(bindings).forEach(function (id) {
        var btn = byId(id);
        if (btn) { btn.addEventListener('click', bindings[id]); }
    });

    // Impede perda acidental de sessão em execução
    window.addEventListener('beforeunload', function (ev) {
        if (timer.session && timer.session.status === 'running') {
            ev.preventDefault();
            ev.returnValue = '';
        }
    });

    // ------------------------------------------------------------------
    // Estado inicial (restaurado do backend)
    // ------------------------------------------------------------------
    renderChecklist();
    setSession(timer.session);
})();
</script>
<?= $this->endSection() ?>
