<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Histórico<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Histórico<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$sessionTypeLabels = [
    'theory'    => 'Teoria',
    'questions' => 'Questões',
    'review'    => 'Revisão',
    'practice'  => 'Prática',
    'mock_exam' => 'Simulado',
    'extra'     => 'Extra',
];
$sessionStatusLabels = [
    'completed' => 'Concluída',
    'cancelled' => 'Cancelada',
];
$eventLabels = [
    'started'      => 'Início',
    'increased'    => 'Aumentou',
    'maintained'   => 'Mantida',
    'broken'       => 'Quebrada',
    'recalculated' => 'Recalculada',
    'record'       => 'Recorde',
];
$eventChips = [
    'started'      => 'chip-info',
    'increased'    => 'chip-primary',
    'maintained'   => 'chip-primary',
    'broken'       => 'chip-danger',
    'recalculated' => 'chip-info',
    'record'       => 'chip-gold',
];
$scoreChip = static function (float $score): string {
    if ($score >= 80) {
        return 'chip-primary';
    }

    return $score >= 60 ? 'chip-gold' : 'chip-danger';
};
?>

<div class="page-header">
    <div>
        <h1>Histórico</h1>
        <p class="subtitle">Tudo o que você já fez: sessões, questões e a trajetória da sua ofensiva.</p>
    </div>
</div>

<div class="flex flex-wrap gap-1 mb-2" role="tablist" aria-label="Tipos de histórico">
    <button type="button" class="btn btn-sm btn-primary" data-tab-btn="sessoes" role="tab" aria-selected="true">
        Sessões (<?= esc($sessionsTotal) ?>)
    </button>
    <button type="button" class="btn btn-sm btn-ghost" data-tab-btn="questoes" role="tab" aria-selected="false">
        Questões (<?= esc($attemptsTotal) ?>)
    </button>
    <button type="button" class="btn btn-sm btn-ghost" data-tab-btn="ofensiva" role="tab" aria-selected="false">
        Ofensiva (<?= esc($streakTotal) ?>)
    </button>
</div>

<!-- Aba: Sessões -->
<div data-tab-panel="sessoes">
    <div class="card">
        <?php if ($sessions === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon">🕘</span>
                <p>Nenhuma sessão registrada ainda. Comece pelo timer na página "Hoje".</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Disciplina</th>
                            <th>Duração</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><?= esc(date('d/m/Y H:i', strtotime($session['started_at']))) ?></td>
                                <td>
                                    <span class="chip">
                                        <span class="chip-dot" style="background: <?= esc($session['subject_color'] ?: '#1B7A5E', 'attr') ?>;"></span>
                                        <?= esc($session['subject_name']) ?>
                                    </span>
                                    <?php if (! empty($session['topic_name'])): ?>
                                        <div class="text-small text-faint"><?= esc($session['topic_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc(round((int) $session['duration_seconds'] / 60)) ?> min</td>
                                <td><?= esc($sessionTypeLabels[$session['session_type']] ?? $session['session_type']) ?></td>
                                <td>
                                    <span class="chip <?= $session['status'] === 'completed' ? 'chip-primary' : 'chip-danger' ?>">
                                        <?= esc($sessionStatusLabels[$session['status']] ?? $session['status']) ?>
                                    </span>
                                </td>
                                <td class="text-small text-muted"><?= esc($session['notes'] ? mb_strimwidth($session['notes'], 0, 80, '…') : '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Aba: Questões -->
<div data-tab-panel="questoes" hidden>
    <div class="card">
        <?php if ($attempts === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon">✍️</span>
                <p>Nenhum registro de questões ainda.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Disciplina</th>
                            <th>Total</th>
                            <th>Acertos</th>
                            <th>%</th>
                            <th>Fonte</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $attempt): ?>
                            <?php $score = (float) ($attempt['score_percentage'] ?? 0); ?>
                            <tr>
                                <td><?= esc(date('d/m/Y', strtotime($attempt['attempt_date']))) ?></td>
                                <td>
                                    <span class="chip">
                                        <span class="chip-dot" style="background: <?= esc($attempt['subject_color'] ?: '#1B7A5E', 'attr') ?>;"></span>
                                        <?= esc($attempt['subject_name']) ?>
                                    </span>
                                    <?php if (! empty($attempt['topic_name'])): ?>
                                        <div class="text-small text-faint"><?= esc($attempt['topic_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc($attempt['questions_total']) ?></td>
                                <td><?= esc($attempt['questions_correct']) ?></td>
                                <td><span class="chip <?= esc($scoreChip($score), 'attr') ?>"><?= esc(round($score, 1)) ?>%</span></td>
                                <td><?= esc($attempt['source'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Aba: Ofensiva -->
<div data-tab-panel="ofensiva" hidden>
    <div class="card">
        <?php if ($streakRows === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon">🔥</span>
                <p>Sua ofensiva ainda não tem histórico. Cumpra a meta de hoje para começar!</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Evento</th>
                            <th>Sequência</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($streakRows as $row): ?>
                            <tr>
                                <td><?= esc(date('d/m/Y', strtotime($row['reference_date']))) ?></td>
                                <td>
                                    <span class="chip <?= esc($eventChips[$row['event_type']] ?? '', 'attr') ?>">
                                        <?= esc($eventLabels[$row['event_type']] ?? $row['event_type']) ?>
                                    </span>
                                </td>
                                <td><?= esc($row['previous_streak']) ?> → <?= esc($row['new_streak']) ?> dias</td>
                                <td class="text-small text-muted"><?= esc($row['description'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Paginação -->
<?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between mt-2">
        <?php if ($page > 1): ?>
            <a class="btn btn-ghost btn-sm" href="<?= site_url('estudos/historico') ?>?page=<?= esc($page - 1, 'attr') ?>">← Anterior</a>
        <?php else: ?>
            <span></span>
        <?php endif; ?>

        <span class="text-small text-muted">Página <?= esc($page) ?> de <?= esc($totalPages) ?></span>

        <?php if ($page < $totalPages): ?>
            <a class="btn btn-ghost btn-sm" href="<?= site_url('estudos/historico') ?>?page=<?= esc($page + 1, 'attr') ?>">Próxima →</a>
        <?php else: ?>
            <span></span>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    'use strict';

    const tabButtons = document.querySelectorAll('[data-tab-btn]');
    tabButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            tabButtons.forEach((b) => {
                const active = b === btn;
                b.classList.toggle('btn-primary', active);
                b.classList.toggle('btn-ghost', !active);
                b.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.tabPanel !== btn.dataset.tabBtn;
            });
        });
    });
})();
</script>
<?= $this->endSection() ?>
