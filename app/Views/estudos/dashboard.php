<?php
/**
 * Visão geral dos estudos (dashboard).
 *
 * @var string      $greeting
 * @var string      $firstName
 * @var string      $currentDate
 * @var array|null  $mainTask
 * @var array       $daily
 * @var array       $overview
 * @var int         $reviewsPending
 * @var array       $streak
 * @var array       $level
 * @var int         $studiedToday
 * @var int         $plannedMinutes
 * @var int         $dailyPercent
 * @var int         $weekMinutes
 * @var int         $weeklyGoal
 * @var int         $weeklyPercent
 * @var int         $weekDaysDone
 * @var int         $weekStudyDays
 * @var int         $questionsToday
 * @var float|null  $accuracyToday
 * @var array|null  $nextTask
 * @var array       $lastSessions
 * @var array       $chartSubjects
 * @var array       $chartWeeks
 */
$formatDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '—';
    }

    return date('d/m/Y', strtotime($date));
};
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Visão geral<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Visão geral<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-header">
    <div>
        <h1><?= esc($greeting) ?><?= $firstName !== '' ? ', ' . esc($firstName) : '' ?>! 👋</h1>
        <p class="subtitle"><?= esc(ucfirst($currentDate)) ?></p>
    </div>
    <a href="<?= site_url('estudos/hoje') ?>" class="btn btn-flame">📌 Ir para hoje</a>
</div>

<!-- Card principal da ofensiva -->
<div class="card streak-card mb-2">
    <div class="flex items-center justify-between gap-2 flex-wrap">
        <div class="flex items-center gap-2">
            <span class="streak-flame<?= (int) $streak['current_streak'] === 0 ? ' is-cold' : '' ?>" aria-hidden="true">🔥</span>
            <div>
                <span class="streak-number"><?= esc((string) $streak['current_streak']) ?></span>
                <span class="fw-bold"><?= (int) $streak['current_streak'] === 1 ? 'dia de ofensiva' : 'dias de ofensiva' ?></span>
                <?php if (! empty($streak['at_risk'])): ?>
                    <span class="chip chip-danger">⚠ Em risco</span>
                <?php elseif (! empty($streak['today_qualified'])): ?>
                    <span class="chip chip-primary">✅ Meta de hoje cumprida</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-small text-muted">
            <div>🏆 Recorde pessoal: <strong><?= esc((string) $streak['best_streak']) ?> <?= (int) $streak['best_streak'] === 1 ? 'dia' : 'dias' ?></strong></div>
            <div>📅 Última atividade válida: <strong><?= esc($formatDate($streak['last_qualified_date'])) ?></strong></div>
            <div>🎯 Meta semanal: <strong><?= esc((string) $weekDaysDone) ?>/<?= esc((string) $weekStudyDays) ?> dias cumpridos</strong></div>
        </div>
    </div>

    <div class="streak-days" aria-label="Dias da semana">
        <?php foreach ($streak['week'] as $day): ?>
            <span class="streak-day<?= in_array($day['status'], ['done', 'today', 'missed'], true) ? ' is-' . esc($day['status'], 'attr') : '' ?>"
                  title="<?= esc($formatDate($day['date']), 'attr') ?>">
                <?= esc($day['label']) ?>
            </span>
        <?php endforeach; ?>
    </div>

    <p class="mt-1 mb-0 fw-bold"><?= esc($streak['message']) ?></p>
</div>

<!-- Tarefa principal do dia -->
<div class="card mb-2">
    <?php if ($mainTask !== null): ?>
        <div class="card-header">
            <h2>📌 Tarefa de hoje</h2>
            <?php if ($mainTask['status'] === 'done'): ?>
                <span class="chip chip-primary">✅ Concluída</span>
            <?php elseif ($mainTask['status'] === 'in_progress'): ?>
                <span class="chip chip-gold">⏳ Em estudo</span>
            <?php endif; ?>
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
        <h3 class="mb-1"><?= esc($mainTask['title']) ?></h3>
        <?php $cp = $mainTask['checklist_progress']; ?>
        <div class="progress-label">
            <span>Checklist</span>
            <span><?= esc((string) $cp['done']) ?>/<?= esc((string) $cp['total']) ?> · <?= esc((string) $cp['percent']) ?>%</span>
        </div>
        <div class="progress mb-2">
            <div class="progress-bar" style="width: <?= esc((string) $cp['percent'], 'attr') ?>%"></div>
        </div>
        <?php if ($mainTask['status'] !== 'done'): ?>
            <a href="<?= site_url('estudos/hoje') ?>" class="btn btn-flame btn-lg btn-block">▶ Iniciar estudo</a>
        <?php else: ?>
            <a href="<?= site_url('estudos/hoje') ?>" class="btn btn-ghost btn-block">Ver a página de hoje</a>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <span class="empty-state-icon" aria-hidden="true">🗓️</span>
            <h3>Nenhuma tarefa programada para hoje</h3>
            <p>Aproveite para revisar conteúdos ou adiantar o cronograma.</p>
            <a href="<?= site_url('estudos/cronograma') ?>" class="btn btn-primary">Ver cronograma</a>
        </div>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="grid grid-4 mb-2">
    <div class="stat">
        <div class="stat-label">Minutos hoje</div>
        <div class="stat-value"><?= esc((string) $studiedToday) ?></div>
        <div class="stat-hint">meta: <?= esc((string) $plannedMinutes) ?> min</div>
    </div>
    <div class="stat">
        <div class="stat-label">Questões hoje</div>
        <div class="stat-value"><?= esc((string) $questionsToday) ?></div>
        <div class="stat-hint"><?= esc((string) $overview['questions_total']) ?> no total</div>
    </div>
    <div class="stat">
        <div class="stat-label">% de acertos</div>
        <div class="stat-value"><?= $accuracyToday !== null ? esc((string) $accuracyToday) . '%' : '—' ?></div>
        <div class="stat-hint">geral: <?= esc((string) $overview['accuracy']) ?>%</div>
    </div>
    <div class="stat">
        <div class="stat-label">Revisões pendentes</div>
        <div class="stat-value"><?= esc((string) $reviewsPending) ?></div>
        <div class="stat-hint"><a href="<?= site_url('estudos/revisoes') ?>">ver revisões</a></div>
    </div>
</div>

<!-- Progresso diário / semanal + XP -->
<div class="grid grid-2 mb-2">
    <div class="card">
        <h3>Progresso</h3>
        <div class="progress-label">
            <span>Hoje</span>
            <span><?= esc((string) $studiedToday) ?>/<?= esc((string) $plannedMinutes) ?> min</span>
        </div>
        <div class="progress mb-1">
            <div class="progress-bar is-flame" style="width: <?= esc((string) $dailyPercent, 'attr') ?>%"></div>
        </div>
        <div class="progress-label">
            <span>Semana</span>
            <span><?= esc((string) $weekMinutes) ?>/<?= esc((string) $weeklyGoal) ?> min</span>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width: <?= esc((string) $weeklyPercent, 'attr') ?>%"></div>
        </div>

        <?php if ($nextTask !== null): ?>
            <div class="mt-2 text-small text-muted">
                <strong>Próximo conteúdo:</strong>
                <?= esc($nextTask['title']) ?>
                <span class="chip">
                    <span class="chip-dot" style="background: <?= esc($nextTask['subject_color'] ?? '#1B7A5E', 'attr') ?>"></span>
                    <?= esc($nextTask['subject_name']) ?>
                </span>
                em <?= esc($formatDate($nextTask['scheduled_date'])) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>⭐ Nível <?= esc((string) $level['level']) ?></h3>
            <span class="chip chip-gold"><?= esc((string) $level['total_xp']) ?> XP no total</span>
        </div>
        <div class="progress-label">
            <span>Rumo ao nível <?= esc((string) ($level['level'] + 1)) ?></span>
            <span><?= esc((string) $level['xp_into_level']) ?>/<?= esc((string) $level['xp_for_next']) ?> XP</span>
        </div>
        <div class="progress">
            <div class="progress-bar is-gold" style="width: <?= esc((string) $level['percent'], 'attr') ?>%"></div>
        </div>
        <p class="mt-1 mb-0 text-small text-muted">
            Você ganha XP estudando, concluindo metas, revisando e acertando questões.
        </p>
    </div>
</div>

<!-- Gráficos -->
<div class="grid grid-2 mb-2">
    <div class="card">
        <h3>Acertos por disciplina</h3>
        <?php if ($chartSubjects['labels'] === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true">📊</span>
                <p class="mb-0">Registre questões para acompanhar seu desempenho por disciplina.</p>
            </div>
        <?php else: ?>
            <div class="chart-box"><canvas id="chart-subjects" aria-label="Gráfico de acertos por disciplina"></canvas></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h3>Minutos estudados por semana</h3>
        <?php if ($chartWeeks['labels'] === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true">📈</span>
                <p class="mb-0">Conclua sessões de estudo para ver sua evolução semanal.</p>
            </div>
        <?php else: ?>
            <div class="chart-box"><canvas id="chart-weeks" aria-label="Gráfico de minutos por semana"></canvas></div>
        <?php endif; ?>
    </div>
</div>

<!-- Últimas sessões -->
<div class="card">
    <div class="card-header">
        <h3>Últimas sessões</h3>
        <a href="<?= site_url('estudos/historico') ?>" class="btn btn-ghost btn-sm">Ver histórico</a>
    </div>
    <?php if ($lastSessions === []): ?>
        <div class="empty-state">
            <span class="empty-state-icon" aria-hidden="true">⏱️</span>
            <p class="mb-0">Nenhuma sessão concluída ainda. Comece agora na página <a href="<?= site_url('estudos/hoje') ?>">Hoje</a>!</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Disciplina</th>
                        <th>Duração</th>
                        <th>Planejado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lastSessions as $sessionRow): ?>
                        <tr>
                            <td><?= esc(date('d/m/Y H:i', strtotime($sessionRow['started_at']))) ?></td>
                            <td>
                                <span class="chip">
                                    <span class="chip-dot" style="background: <?= esc($sessionRow['subject_color'] ?? '#1B7A5E', 'attr') ?>"></span>
                                    <?= esc($sessionRow['subject_name']) ?>
                                </span>
                            </td>
                            <td class="fw-bold"><?= esc((string) (int) round(((int) $sessionRow['duration_seconds']) / 60)) ?> min</td>
                            <td class="text-muted"><?= esc((string) $sessionRow['planned_minutes']) ?> min</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($chartSubjects['labels'] !== [] || $chartWeeks['labels'] !== []): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
(function () {
    'use strict';

    var subjectsData = <?= json_encode($chartSubjects) ?>;
    var weeksData    = <?= json_encode($chartWeeks) ?>;

    var subjectsCanvas = document.getElementById('chart-subjects');
    if (subjectsCanvas && subjectsData.labels.length > 0) {
        new Chart(subjectsCanvas, {
            type: 'bar',
            data: {
                labels: subjectsData.labels,
                datasets: [{
                    label: '% de acertos',
                    data: subjectsData.values,
                    backgroundColor: subjectsData.colors,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + '%'; } } }
                }
            }
        });
    }

    var weeksCanvas = document.getElementById('chart-weeks');
    if (weeksCanvas && weeksData.labels.length > 0) {
        new Chart(weeksCanvas, {
            type: 'line',
            data: {
                labels: weeksData.labels,
                datasets: [{
                    label: 'Minutos estudados',
                    data: weeksData.values,
                    borderColor: '#1B7A5E',
                    backgroundColor: 'rgba(27, 122, 94, .12)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#1B7A5E'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
})();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
