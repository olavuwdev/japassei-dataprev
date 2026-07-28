<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Desempenho<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Desempenho<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Desempenho</h1>
        <p class="subtitle">Seus números, do total de horas até a evolução nas questões.</p>
    </div>
</div>

<!-- Filtros -->
<div class="card">
    <form method="get" action="<?= site_url('estudos/desempenho') ?>">
        <div class="form-row">
            <div class="field mb-0">
                <label for="f-from">De</label>
                <input type="date" id="f-from" name="date_from" value="<?= esc($filters['date_from'] ?? '', 'attr') ?>">
            </div>
            <div class="field mb-0">
                <label for="f-to">Até</label>
                <input type="date" id="f-to" name="date_to" value="<?= esc($filters['date_to'] ?? '', 'attr') ?>">
            </div>
            <div class="field mb-0">
                <label for="f-subject">Disciplina</label>
                <select id="f-subject" name="subject_id">
                    <option value="">Todas</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= esc($subject['id'], 'attr') ?>"
                            <?= (int) ($filters['subject_id'] ?? 0) === (int) $subject['id'] ? 'selected' : '' ?>>
                            <?= esc($subject['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="flex gap-1 mt-1">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <a class="btn btn-ghost btn-sm" href="<?= site_url('estudos/desempenho') ?>">Limpar</a>
        </div>
    </form>
</div>

<!-- Indicadores -->
<div class="grid grid-4 mt-2">
    <div class="stat">
        <span class="stat-label">Total de horas</span>
        <span class="stat-value"><?= esc($overview['total_hours']) ?>h</span>
    </div>
    <div class="stat">
        <span class="stat-label">Sessões</span>
        <span class="stat-value"><?= esc($overview['total_sessions']) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Média diária</span>
        <span class="stat-value"><?= esc($overview['daily_average']) ?> min</span>
        <span class="stat-hint">nos dias em que estudou</span>
    </div>
    <div class="stat">
        <span class="stat-label">Média semanal</span>
        <span class="stat-value"><?= esc($overview['weekly_average']) ?> min</span>
    </div>
    <div class="stat">
        <span class="stat-label">Questões respondidas</span>
        <span class="stat-value"><?= esc($overview['questions_total']) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">% geral de acertos</span>
        <span class="stat-value"><?= esc($overview['accuracy']) ?>%</span>
        <span class="stat-hint">meta: 80%</span>
    </div>
    <div class="stat">
        <span class="stat-label">Melhor disciplina</span>
        <span class="stat-value" style="font-size: 1.05rem;"><?= esc($overview['best_subject']['name'] ?? '—') ?></span>
        <?php if ($overview['best_subject'] !== null): ?>
            <span class="stat-hint"><?= esc($overview['best_subject']['accuracy']) ?>% em <?= esc($overview['best_subject']['total']) ?> questões</span>
        <?php endif; ?>
    </div>
    <div class="stat">
        <span class="stat-label">Pior disciplina</span>
        <span class="stat-value" style="font-size: 1.05rem;"><?= esc($overview['worst_subject']['name'] ?? '—') ?></span>
        <?php if ($overview['worst_subject'] !== null): ?>
            <span class="stat-hint"><?= esc($overview['worst_subject']['accuracy']) ?>% em <?= esc($overview['worst_subject']['total']) ?> questões</span>
        <?php endif; ?>
    </div>
    <div class="stat">
        <span class="stat-label">Revisões concluídas</span>
        <span class="stat-value"><?= esc($overview['reviews_completed']) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Revisões atrasadas</span>
        <span class="stat-value"><?= esc($overview['reviews_overdue']) ?></span>
    </div>
    <div class="stat">
        <span class="stat-label">Cumprimento do cronograma</span>
        <span class="stat-value"><?= esc($adherence['percent']) ?>%</span>
        <span class="stat-hint"><?= esc($adherence['done']) ?> de <?= esc($adherence['due']) ?> tarefas previstas</span>
    </div>
    <div class="stat">
        <span class="stat-label">Ofensiva 🔥</span>
        <span class="stat-value"><?= esc($streak['current_streak']) ?> dias</span>
        <span class="stat-hint">recorde: <?= esc($streak['best_streak']) ?> dias · nível <?= esc($level['level']) ?> (<?= esc($level['total_xp']) ?> XP)</span>
    </div>
</div>

<!-- Conteúdos -->
<div class="grid grid-2 mt-2">
    <div class="card mt-0">
        <div class="card-header"><h3>📚 Conteúdos mais estudados</h3></div>
        <?php if ($overview['top_contents'] === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon">📚</span>
                <p>Conclua sessões de estudo para ver este ranking.</p>
            </div>
        <?php else: ?>
            <?php foreach ($overview['top_contents'] as $content): ?>
                <div class="flex items-center justify-between mb-1">
                    <span class="fw-bold text-small"><?= esc($content['name']) ?></span>
                    <span class="chip chip-primary"><?= esc(round((int) $content['seconds'] / 60)) ?> min</span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card mt-0">
        <div class="card-header"><h3>🎯 Conteúdos com mais erros</h3></div>
        <?php if ($overview['error_contents'] === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon">🎯</span>
                <p>Nenhum erro registrado. Continue assim!</p>
            </div>
        <?php else: ?>
            <?php foreach ($overview['error_contents'] as $content): ?>
                <div class="flex items-center justify-between mb-1">
                    <span class="fw-bold text-small"><?= esc($content['name']) ?></span>
                    <span class="chip chip-danger"><?= esc($content['wrong']) ?> erros</span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Conquistas -->
<div class="card">
    <div class="card-header"><h3>🏅 Conquistas</h3></div>
    <?php if ($badges === []): ?>
        <div class="empty-state">
            <span class="empty-state-icon">🏅</span>
            <p>Nenhuma conquista disponível ainda.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-4">
            <?php foreach ($badges as $badge): ?>
                <?php $earned = ! empty($badge['earned_at']); ?>
                <div class="stat text-center" style="<?= $earned ? '' : 'opacity: .4; filter: grayscale(1);' ?>">
                    <span style="font-size: 1.8rem;"><?= esc($badge['icon'] ?? '🏅') ?></span>
                    <span class="fw-bold text-small"><?= esc($badge['name']) ?></span>
                    <span class="stat-hint"><?= esc($badge['description'] ?? '') ?></span>
                    <?php if ($earned): ?>
                        <span class="chip chip-gold mt-1">Conquistada em <?= esc(date('d/m/Y', strtotime($badge['earned_at']))) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Gráficos -->
<div class="grid grid-2 mt-2">
    <div class="card mt-0">
        <div class="card-header"><h3>⏱️ Minutos por semana</h3></div>
        <div class="chart-box"><canvas id="chart-minutes"></canvas></div>
    </div>
    <div class="card mt-0">
        <div class="card-header"><h3>✅ Acertos por disciplina</h3></div>
        <div class="chart-box"><canvas id="chart-accuracy-subject"></canvas></div>
    </div>
    <div class="card mt-0">
        <div class="card-header"><h3>📈 Evolução de acertos</h3></div>
        <div class="chart-box"><canvas id="chart-accuracy-evolution"></canvas></div>
    </div>
    <div class="card mt-0">
        <div class="card-header"><h3>🥧 Distribuição de tempo</h3></div>
        <div class="chart-box"><canvas id="chart-time"></canvas></div>
    </div>
    <div class="card mt-0">
        <div class="card-header"><h3>📌 Tarefas concluídas por semana</h3></div>
        <div class="chart-box"><canvas id="chart-tasks"></canvas></div>
    </div>
    <div class="card mt-0">
        <div class="card-header"><h3>⚖️ Planejado × realizado (min)</h3></div>
        <div class="chart-box"><canvas id="chart-planned"></canvas></div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
window.JPD = <?= json_encode($charts, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
(function () {
    'use strict';

    if (typeof Chart === 'undefined') { return; }

    const data = window.JPD;
    const green = '#1B7A5E';
    const flame = '#F4581C';
    const gold = '#D99A06';

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    };

    new Chart(document.getElementById('chart-minutes'), {
        type: 'bar',
        data: {
            labels: data.minutes_per_week.labels,
            datasets: [{ label: 'Minutos', data: data.minutes_per_week.values, backgroundColor: green, borderRadius: 6 }]
        },
        options: baseOptions
    });

    new Chart(document.getElementById('chart-accuracy-subject'), {
        type: 'bar',
        data: {
            labels: data.accuracy_by_subject.labels,
            datasets: [{ label: '% de acertos', data: data.accuracy_by_subject.values, backgroundColor: data.accuracy_by_subject.colors, borderRadius: 6 }]
        },
        options: Object.assign({}, baseOptions, {
            indexAxis: 'y',
            scales: { x: { min: 0, max: 100 } }
        })
    });

    new Chart(document.getElementById('chart-accuracy-evolution'), {
        type: 'line',
        data: {
            labels: data.accuracy_evolution.labels,
            datasets: [{
                label: '% de acertos',
                data: data.accuracy_evolution.values,
                borderColor: flame,
                backgroundColor: 'rgba(244, 88, 28, .12)',
                fill: true,
                tension: .3
            }]
        },
        options: Object.assign({}, baseOptions, { scales: { y: { min: 0, max: 100 } } })
    });

    new Chart(document.getElementById('chart-time'), {
        type: 'doughnut',
        data: {
            labels: data.time_distribution.labels,
            datasets: [{ data: data.time_distribution.values, backgroundColor: data.time_distribution.colors }]
        },
        options: Object.assign({}, baseOptions, { plugins: { legend: { display: true, position: 'bottom' } } })
    });

    new Chart(document.getElementById('chart-tasks'), {
        type: 'bar',
        data: {
            labels: data.tasks_per_week.labels,
            datasets: [{ label: 'Tarefas', data: data.tasks_per_week.values, backgroundColor: gold, borderRadius: 6 }]
        },
        options: baseOptions
    });

    new Chart(document.getElementById('chart-planned'), {
        type: 'bar',
        data: {
            labels: data.planned_vs_done.labels,
            datasets: [
                { label: 'Planejado', data: data.planned_vs_done.planned, backgroundColor: 'rgba(27, 122, 94, .35)', borderRadius: 6 },
                { label: 'Realizado', data: data.planned_vs_done.studied, backgroundColor: green, borderRadius: 6 }
            ]
        },
        options: Object.assign({}, baseOptions, { plugins: { legend: { display: true, position: 'bottom' } } })
    });
})();
</script>
<?= $this->endSection() ?>
