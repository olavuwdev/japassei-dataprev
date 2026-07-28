<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Kanban<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Kanban<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Kanban</h1>
        <p class="subtitle">Arraste os cards entre as colunas para organizar seus estudos.</p>
    </div>
</div>

<div class="card mb-2">
    <div class="flex flex-wrap items-center gap-1">
        <select id="filtro-disciplina" style="width:auto;min-width:170px" aria-label="Filtrar por disciplina">
            <option value="">Todas as disciplinas</option>
            <?php foreach ($filterOptions['subjects'] as $subject): ?>
                <option value="<?= esc($subject['id']) ?>"><?= esc($subject['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select id="filtro-semana" style="width:auto;min-width:140px" aria-label="Filtrar por semana">
            <option value="">Todas as semanas</option>
            <?php foreach ($filterOptions['weeks'] as $week): ?>
                <option value="<?= esc($week['id']) ?>">Semana <?= esc($week['week_number']) ?></option>
            <?php endforeach; ?>
        </select>

        <select id="filtro-tipo" style="width:auto;min-width:130px" aria-label="Filtrar por tipo">
            <option value="">Todos os tipos</option>
            <?php foreach ($filterOptions['task_types'] as $value => $label): ?>
                <option value="<?= esc($value) ?>"><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>

        <select id="filtro-prioridade" style="width:auto;min-width:140px" aria-label="Filtrar por prioridade">
            <option value="">Todas as prioridades</option>
            <?php foreach ($filterOptions['priorities'] as $value => $label): ?>
                <option value="<?= esc($value) ?>"><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>

        <select id="filtro-situacao" style="width:auto;min-width:140px" aria-label="Filtrar por situação">
            <option value="">Todas as situações</option>
            <?php foreach ($filterOptions['statuses'] as $value => $label): ?>
                <option value="<?= esc($value) ?>"><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="button" class="btn btn-ghost btn-sm" id="filtro-limpar">Limpar filtros</button>
    </div>
</div>

<div class="kanban-board" id="kanban-board" aria-label="Quadro Kanban"></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
(function () {
    'use strict';

    var INITIAL_COLUMNS = <?= json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var API_BASE  = '<?= site_url('estudos/api') ?>';
    var HOJE_URL  = '<?= site_url('estudos/hoje') ?>';

    var TYPE_LABELS = {
        theory: 'Teoria',
        questions: 'Questões',
        review: 'Revisão',
        practice: 'Prática',
        mock_exam: 'Simulado'
    };

    var board = document.getElementById('kanban-board');
    var dragging = false;

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatDate(iso) {
        if (!iso) { return ''; }
        var parts = String(iso).slice(0, 10).split('-');
        return parts.length === 3 ? parts[2] + '/' + parts[1] : '';
    }

    function priorityChip(priority) {
        var p = parseInt(priority, 10) || 3;
        if (p === 1) { return '<span class="chip chip-danger">Alta</span>'; }
        if (p <= 3)  { return '<span class="chip chip-gold">Média</span>'; }
        return '<span class="chip">Baixa</span>';
    }

    function cardHtml(card) {
        var meta = [];

        meta.push(
            '<span class="chip"><span class="chip-dot" style="background:' + esc(card.subject_color || '#9A8F7B') + '"></span>'
            + esc(card.subject_name) + '</span>'
        );

        if (card.week_number) {
            meta.push('<span class="chip">Sem ' + esc(card.week_number) + '</span>');
        }
        if (card.scheduled_date) {
            meta.push('<span class="chip">📅 ' + esc(formatDate(card.scheduled_date)) + '</span>');
        }

        meta.push('<span class="chip chip-info">' + esc(TYPE_LABELS[card.task_type] || card.task_type) + '</span>');

        if (parseInt(card.estimated_minutes, 10) > 0) {
            meta.push('<span class="chip">⏱ ' + esc(card.estimated_minutes) + ' min</span>');
        }

        meta.push(priorityChip(card.priority));

        if (parseInt(card.pending_reviews, 10) > 0) {
            meta.push('<span class="chip chip-flame">🔁 ' + esc(card.pending_reviews) + '</span>');
        }
        if (card.is_late) {
            meta.push('<span class="chip chip-danger">Atrasada</span>');
        }

        var checklist = '';
        var total = parseInt(card.checklist_total, 10) || 0;
        if (total > 0) {
            var done = parseInt(card.checklist_done, 10) || 0;
            var pct  = Math.round((done / total) * 100);
            checklist =
                '<div class="progress-label mt-1"><span>Checklist</span><span>' + done + '/' + total + '</span></div>' +
                '<div class="progress" style="height:6px"><div class="progress-bar" style="width:' + pct + '%"></div></div>';
        }

        return '<article class="kanban-card' + (card.is_late ? ' is-late' : '') + '" data-task-id="' + esc(card.id) + '">' +
            '<div class="kanban-card-title">' + esc(card.title) + '</div>' +
            '<div class="kanban-card-meta">' + meta.join('') + '</div>' +
            checklist +
            '</article>';
    }

    function renderBoard(columns) {
        board.innerHTML = columns.map(function (col) {
            return '<section class="kanban-column">' +
                '<header class="kanban-column-header" style="color:' + esc(col.color || '#2A2418') + '">' +
                '<span class="chip-dot" style="background:' + esc(col.color || '#9A8F7B') + '"></span>' +
                '<span>' + esc(col.title) + '</span>' +
                '<span class="kanban-count chip">' + col.cards.length + '</span>' +
                '</header>' +
                '<div class="kanban-cards" data-column-id="' + esc(col.id) + '">' +
                col.cards.map(cardHtml).join('') +
                '</div>' +
                '</section>';
        }).join('');

        board.querySelectorAll('.kanban-cards').forEach(function (el) {
            new Sortable(el, {
                group: 'kanban',
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onStart: function () { dragging = true; },
                onEnd: onCardDrop
            });
        });
    }

    function updateCounts() {
        board.querySelectorAll('.kanban-column').forEach(function (col) {
            var count = col.querySelectorAll('.kanban-card').length;
            var chip  = col.querySelector('.kanban-count');
            if (chip) { chip.textContent = count; }
        });
    }

    function currentFilters() {
        return {
            subject_id: document.getElementById('filtro-disciplina').value,
            week_id:    document.getElementById('filtro-semana').value,
            task_type:  document.getElementById('filtro-tipo').value,
            priority:   document.getElementById('filtro-prioridade').value,
            status:     document.getElementById('filtro-situacao').value
        };
    }

    async function loadBoard() {
        var filters = currentFilters();
        var params  = new URLSearchParams();
        Object.keys(filters).forEach(function (key) {
            if (filters[key] !== '') { params.append(key, filters[key]); }
        });

        var qs  = params.toString();
        var res = await JP.api(API_BASE + '/kanban/board' + (qs ? '?' + qs : ''));

        if (!res.ok) {
            JP.toast(res.message || 'Não foi possível carregar o quadro.', 'error');
            return;
        }
        renderBoard(res.data.columns || []);
    }

    async function onCardDrop(evt) {
        setTimeout(function () { dragging = false; }, 150);

        if (evt.to === evt.from && evt.newIndex === evt.oldIndex) {
            return;
        }

        var taskId     = parseInt(evt.item.dataset.taskId, 10);
        var toColumnId = parseInt(evt.to.dataset.columnId, 10);
        var position   = evt.newIndex + 1;

        var res = await JP.api(API_BASE + '/kanban/mover', {
            method: 'POST',
            body: { task_id: taskId, to_column_id: toColumnId, position: position }
        });

        if (!res.ok) {
            JP.toast(res.message || 'Não foi possível mover a tarefa.', 'error');
            loadBoard();
            return;
        }

        if (res.data && res.data.completed) {
            JP.toast('Tarefa concluída!', 'success');
            if (parseInt(res.data.xp_awarded, 10) > 0) {
                JP.xpPop(res.data.xp_awarded, evt.item);
            }
            if (res.data.goal_met_now) {
                JP.celebrate();
            }
            if (Array.isArray(res.data.new_badges) && res.data.new_badges.length > 0) {
                JP.toast('🏅 Nova conquista desbloqueada!', 'xp');
            }
            loadBoard();
            return;
        }

        updateCounts();
    }

    // ------------------------------------------------------------------
    // Modal de detalhes da tarefa
    // ------------------------------------------------------------------

    function checklistHtml(items) {
        if (!items || items.length === 0) {
            return '<p class="text-muted text-small">Sem itens de checklist.</p>';
        }
        return '<ul class="checklist" id="modal-checklist">' + items.map(function (item) {
            var done = String(item.is_completed) === '1' || item.is_completed === true;
            return '<li class="checklist-item' + (done ? ' is-done' : '') + '" data-item-id="' + esc(item.id) + '">' +
                '<button type="button" class="checklist-check' + (done ? ' is-checked' : '') + '" data-act="toggle-check" aria-label="Marcar item">' +
                '✓</button>' +
                '<span class="checklist-title">' + esc(item.title) + '</span>' +
                (parseInt(item.estimated_minutes, 10) > 0
                    ? '<span class="checklist-meta">' + esc(item.estimated_minutes) + ' min</span>'
                    : '') +
                '</li>';
        }).join('') + '</ul>';
    }

    async function openTaskModal(taskId) {
        var res = await JP.api(API_BASE + '/tarefas/' + taskId);

        if (!res.ok) {
            JP.toast(res.message || 'Não foi possível carregar a tarefa.', 'error');
            return;
        }

        var task = (res.data && res.data.task) ? res.data.task : res.data;
        var subjectName  = task.subject_name || (task.subject && task.subject.name) || '';
        var subjectColor = task.subject_color || (task.subject && task.subject.color) || '#9A8F7B';
        var isDone = task.status === 'done';

        var html =
            '<h3 class="modal-title">' + esc(task.title) + '</h3>' +
            '<div class="flex flex-wrap items-center gap-1 mb-2">' +
            '<span class="chip"><span class="chip-dot" style="background:' + esc(subjectColor) + '"></span>' + esc(subjectName) + '</span>' +
            '<span class="chip chip-info">' + esc(TYPE_LABELS[task.task_type] || task.task_type) + '</span>' +
            (task.scheduled_date ? '<span class="chip">📅 ' + esc(formatDate(task.scheduled_date)) + '</span>' : '') +
            (isDone ? '<span class="chip chip-primary">✓ Concluída</span>' : '') +
            '</div>' +
            (task.description
                ? '<p class="text-small">' + esc(task.description) + '</p>'
                : '<p class="text-muted text-small">Sem descrição.</p>') +
            '<h3 class="mt-2">Checklist</h3>' +
            checklistHtml(task.checklist) +
            '<div class="field mt-2">' +
            '<label for="jp-reagendar-data">Reagendar para</label>' +
            '<div class="form-row">' +
            '<input type="date" id="jp-reagendar-data" value="' + esc(String(task.scheduled_date || '').slice(0, 10)) + '">' +
            '<button type="button" class="btn btn-ghost" data-act="reagendar">Reagendar</button>' +
            '</div>' +
            '</div>' +
            '<div class="modal-actions">' +
            '<button type="button" class="btn btn-ghost" data-modal-close>Fechar</button>' +
            '<a class="btn btn-flame" href="' + HOJE_URL + '">⏱ Iniciar timer</a>' +
            (!isDone ? '<button type="button" class="btn btn-primary" data-act="concluir">✓ Concluir</button>' : '') +
            '</div>';

        var modal = JP.openModal(html);

        // Toggle checklist
        var checklistEl = modal.el.querySelector('#modal-checklist');
        if (checklistEl) {
            checklistEl.addEventListener('click', async function (ev) {
                var btn = ev.target.closest('[data-act="toggle-check"]');
                if (!btn) { return; }

                var li = btn.closest('.checklist-item');
                var itemId = li && li.dataset.itemId;
                if (!itemId) { return; }

                btn.disabled = true;
                var r = await JP.api(API_BASE + '/checklist/' + itemId + '/alternar', {
                    method: 'POST',
                    body: {}
                });
                btn.disabled = false;

                if (!r.ok) {
                    JP.toast(r.message || 'Não foi possível atualizar o item.', 'error');
                    return;
                }

                var item = r.data && r.data.item ? r.data.item : null;
                var done = item && (String(item.is_completed) === '1' || item.is_completed === true);
                li.classList.toggle('is-done', done);
                btn.classList.toggle('is-checked', done);

                if (r.data && r.data.suggest_complete) {
                    var confirmed = await JP.confirmDialog(
                        'Todos os itens obrigatórios foram concluídos. Deseja marcar a tarefa como concluída?',
                        { title: 'Concluir tarefa', okLabel: 'Concluir' }
                    );
                    if (confirmed) {
                        var cr = await JP.api(API_BASE + '/tarefas/' + taskId + '/concluir', { method: 'POST', body: {} });
                        if (cr.ok) {
                            JP.toast('Tarefa concluída!', 'success');
                            if (cr.data && parseInt(cr.data.xp_awarded, 10) > 0) {
                                JP.xpPop(cr.data.xp_awarded);
                            }
                            if (cr.data && cr.data.goal_met_now) {
                                JP.celebrate();
                            }
                            modal.close();
                            loadBoard();
                            return;
                        }
                        JP.toast(cr.message || 'Não foi possível concluir.', 'error');
                    }
                }

                // Atualiza contagem do card no board sem fechar o modal.
                loadBoard();
            });
        }

        var btnConcluir = modal.el.querySelector('[data-act="concluir"]');
        if (btnConcluir) {
            btnConcluir.addEventListener('click', async function () {
                var confirmed = await JP.confirmDialog('Deseja marcar esta tarefa como concluída?', {
                    title: 'Concluir tarefa',
                    okLabel: 'Concluir'
                });
                if (!confirmed) { return; }

                var r = await JP.api(API_BASE + '/tarefas/' + taskId + '/concluir', { method: 'POST', body: {} });
                if (!r.ok) {
                    JP.toast(r.message || 'Não foi possível concluir a tarefa.', 'error');
                    return;
                }

                JP.toast('Tarefa concluída!', 'success');
                if (r.data && parseInt(r.data.xp_awarded, 10) > 0) {
                    JP.xpPop(r.data.xp_awarded);
                }
                if (r.data && r.data.goal_met_now) {
                    JP.celebrate();
                }
                modal.close();
                loadBoard();
            });
        }

        modal.el.querySelector('[data-act="reagendar"]').addEventListener('click', async function () {
            var input = modal.el.querySelector('#jp-reagendar-data');
            if (!input.value) {
                JP.toast('Informe a nova data.', 'error');
                return;
            }

            var r = await JP.api(API_BASE + '/tarefas/' + taskId + '/reagendar', {
                method: 'POST',
                body: { date: input.value, new_date: input.value, scheduled_date: input.value }
            });
            if (!r.ok) {
                JP.toast(r.message || 'Não foi possível reagendar a tarefa.', 'error');
                return;
            }

            JP.toast('Tarefa reagendada!', 'success');
            modal.close();
            loadBoard();
        });
    }

    board.addEventListener('click', function (ev) {
        if (dragging) { return; }
        var card = ev.target.closest('.kanban-card');
        if (card) {
            openTaskModal(parseInt(card.dataset.taskId, 10));
        }
    });

    // ------------------------------------------------------------------
    // Filtros
    // ------------------------------------------------------------------

    ['filtro-disciplina', 'filtro-semana', 'filtro-tipo', 'filtro-prioridade', 'filtro-situacao'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', loadBoard);
    });

    document.getElementById('filtro-limpar').addEventListener('click', function () {
        ['filtro-disciplina', 'filtro-semana', 'filtro-tipo', 'filtro-prioridade', 'filtro-situacao'].forEach(function (id) {
            document.getElementById(id).value = '';
        });
        loadBoard();
    });

    // Primeiro paint com os dados iniciais renderizados pelo servidor.
    renderBoard(INITIAL_COLUMNS);
})();
</script>
<?= $this->endSection() ?>
