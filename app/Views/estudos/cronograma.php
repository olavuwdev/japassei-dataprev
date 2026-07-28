<?php
$diasSemana = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
$tipos      = ['theory' => 'Teoria', 'questions' => 'Questões', 'review' => 'Revisão', 'practice' => 'Prática', 'mock_exam' => 'Simulado'];
$hoje       = date('Y-m-d');
$formatar   = static fn (?string $data): string => $data !== null && $data !== '' ? date('d/m/Y', strtotime($data)) : '—';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Cronograma<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Cronograma<?= $this->endSection() ?>

<?= $this->section('head') ?>
<style>
    .week-accordion { margin-bottom: 12px; padding: 0; overflow: hidden; }
    .week-accordion summary {
        list-style: none;
        cursor: pointer;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .week-accordion summary::-webkit-details-marker { display: none; }
    .week-accordion summary::before { content: '▸'; color: var(--ink-faint); transition: transform .15s; }
    .week-accordion[open] summary::before { transform: rotate(90deg); }
    .week-accordion summary:hover { background: var(--bg-soft); }
    .week-accordion .week-body { padding: 0 20px 18px; }
    .week-title { font-family: var(--font-display); font-weight: 800; }
    .week-dates { color: var(--ink-soft); font-size: .84rem; font-weight: 700; }
    .week-progress { margin-left: auto; display: flex; align-items: center; gap: 10px; min-width: 180px; }
    .week-progress .progress { flex: 1; }
    @media (max-width: 560px) { .week-progress { min-width: 100%; } }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if ($plan === null): ?>
    <div class="card">
        <div class="empty-state">
            <span class="empty-state-icon" aria-hidden="true">🗓️</span>
            <h2>Nenhum plano de estudos ativo</h2>
            <p>Você ainda não possui um cronograma. Assim que seu plano for criado, as 24 semanas de estudo aparecerão aqui.</p>
        </div>
    </div>
<?php else: ?>
    <div class="page-header">
        <div>
            <h1><?= esc($plan['name']) ?></h1>
            <p class="subtitle">Período: <?= esc($formatar($plan['start_date'])) ?> — <?= esc($formatar($plan['end_date'] ?? null)) ?></p>
        </div>
    </div>

    <div class="card mb-2">
        <div class="progress-label">
            <span>Adesão geral ao cronograma</span>
            <span><?= esc($adherence['done']) ?>/<?= esc($adherence['due']) ?> tarefas · <?= esc($adherence['percent']) ?>%</span>
        </div>
        <div class="progress">
            <div class="progress-bar<?= $adherence['percent'] < 50 ? ' is-flame' : '' ?>" style="width: <?= esc($adherence['percent']) ?>%"></div>
        </div>
    </div>

    <?php if ($weeks === []): ?>
        <div class="card">
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true">📭</span>
                <h2>Sem semanas cadastradas</h2>
                <p>O plano ainda não possui semanas de estudo cadastradas.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($weeks as $week): ?>
        <details class="card week-accordion"<?= $week['is_current'] ? ' open id="semana-atual"' : '' ?>>
            <summary>
                <span class="week-title">Semana <?= esc($week['week_number']) ?></span>
                <span class="week-dates"><?= esc($formatar($week['start_date'])) ?> — <?= esc($formatar($week['end_date'])) ?></span>
                <?php if ($week['is_current']): ?>
                    <span class="chip chip-flame">Semana atual</span>
                <?php endif; ?>
                <span class="week-progress">
                    <span class="progress"><span class="progress-bar" style="display:block;width:<?= esc($week['percent']) ?>%"></span></span>
                    <span class="text-small fw-bold"><?= esc($week['done']) ?>/<?= esc($week['total']) ?> · <?= esc($week['percent']) ?>%</span>
                </span>
            </summary>
            <div class="week-body">
                <?php if (! empty($week['title'])): ?>
                    <p class="text-muted text-small mb-1"><?= esc($week['title']) ?></p>
                <?php endif; ?>

                <?php if ($week['tasks'] === []): ?>
                    <p class="text-muted text-small mb-0">Nenhuma tarefa nesta semana.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Dia</th>
                                    <th>Tarefa</th>
                                    <th>Disciplina</th>
                                    <th>Tipo</th>
                                    <th>Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($week['tasks'] as $task): ?>
                                    <?php
                                    $dia     = $task['scheduled_date'] !== null
                                        ? $diasSemana[(int) date('N', strtotime($task['scheduled_date']))]
                                        : '—';
                                    $done    = $task['status'] === 'done';
                                    $atrasada = ! $done && $task['scheduled_date'] !== null && $task['scheduled_date'] < $hoje;
                                    ?>
                                    <tr>
                                        <td><?= esc($dia) ?></td>
                                        <td class="fw-bold"><?= esc($task['title']) ?></td>
                                        <td>
                                            <span class="chip">
                                                <span class="chip-dot" style="background: <?= esc($task['subject_color'] ?: '#9A8F7B') ?>"></span>
                                                <?= esc($task['subject_name']) ?>
                                            </span>
                                        </td>
                                        <td><?= esc($tipos[$task['task_type']] ?? $task['task_type']) ?></td>
                                        <td>
                                            <?php if ($done): ?>
                                                <span class="chip chip-primary">✓ Concluída</span>
                                            <?php elseif ($atrasada): ?>
                                                <span class="chip chip-danger">Atrasada</span>
                                            <?php else: ?>
                                                <span class="chip">Pendente</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </details>
    <?php endforeach; ?>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    'use strict';
    var atual = document.getElementById('semana-atual');
    if (atual) {
        setTimeout(function () {
            atual.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 150);
    }
})();
</script>
<?= $this->endSection() ?>
