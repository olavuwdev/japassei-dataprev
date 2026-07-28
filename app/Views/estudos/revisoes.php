<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Revisões<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Revisões<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$statusLabels = [
    'pending'     => 'Pendente',
    'available'   => 'Disponível',
    'overdue'     => 'Atrasada',
    'completed'   => 'Concluída',
    'skipped'     => 'Ignorada',
    'rescheduled' => 'Reagendada',
];
$statusChips = [
    'pending'     => 'chip-info',
    'available'   => 'chip-primary',
    'overdue'     => 'chip-danger',
    'completed'   => 'chip-primary',
    'skipped'     => 'chip-gold',
    'rescheduled' => 'chip-info',
];
$sections = [
    'today' => [
        'title' => '📌 Revisões de hoje',
        'empty' => 'Nenhuma revisão para hoje. Aproveite para avançar no conteúdo!',
    ],
    'overdue' => [
        'title' => '⏰ Revisões atrasadas',
        'empty' => 'Nenhuma revisão atrasada. Você está em dia!',
    ],
    'upcoming' => [
        'title' => '🔜 Próximas revisões',
        'empty' => 'Nenhuma revisão programada. Elas são criadas quando você conclui um conteúdo teórico.',
    ],
    'completed' => [
        'title' => '✅ Revisões concluídas',
        'empty' => 'Nenhuma revisão concluída até agora.',
    ],
];
$ordinals = [1 => '1ª', 2 => '2ª', 3 => '3ª'];
?>

<div class="page-header">
    <div>
        <h1>Revisões</h1>
        <p class="subtitle">Revisar no momento certo é o que fixa o conteúdo. Intervalos padrão: 1, 7 e 30 dias.</p>
    </div>
</div>

<?php foreach ($sections as $key => $section): ?>
    <div class="card">
        <div class="card-header">
            <h3><?= $section['title'] ?></h3>
            <span class="chip"><?= count($grouped[$key]) ?></span>
        </div>

        <?php if ($grouped[$key] === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon">🌿</span>
                <p><?= esc($section['empty']) ?></p>
            </div>
        <?php else: ?>
            <div class="grid" style="gap: 10px;">
                <?php foreach ($grouped[$key] as $review): ?>
                    <?php
                    $content    = $review['topic_name'] ?: ($review['task_title'] ?: 'Conteúdo geral');
                    $reviewNum  = $ordinals[(int) $review['review_number']] ?? $review['review_number'] . 'ª';
                    $label      = $review['subject_name'] . ' — ' . $content;
                    $isPending  = ! in_array($review['status'], ['completed', 'skipped'], true);
                    ?>
                    <div class="flex flex-wrap items-center justify-between gap-1"
                         style="border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px;">
                        <div>
                            <div class="flex flex-wrap items-center gap-1 mb-1">
                                <span class="chip">
                                    <span class="chip-dot" style="background: <?= esc($review['subject_color'] ?: '#1B7A5E', 'attr') ?>;"></span>
                                    <?= esc($review['subject_name']) ?>
                                </span>
                                <span class="chip chip-info"><?= esc($reviewNum) ?> revisão · <?= esc($review['interval_days']) ?> dias</span>
                                <span class="chip <?= esc($statusChips[$review['status']] ?? '', 'attr') ?>">
                                    <?= esc($statusLabels[$review['status']] ?? $review['status']) ?>
                                </span>
                            </div>
                            <div class="fw-bold"><?= esc($content) ?></div>
                            <div class="text-small text-muted">
                                Prevista para <?= esc(date('d/m', strtotime($review['due_date']))) ?>
                                <?php if ($key === 'completed' && ! empty($review['completed_at'])): ?>
                                    · concluída em <?= esc(date('d/m', strtotime($review['completed_at']))) ?>
                                    <?php if ((int) $review['questions_total'] > 0): ?>
                                        · <?= esc($review['questions_correct']) ?>/<?= esc($review['questions_total']) ?> questões
                                        (<?= esc(round((int) $review['questions_correct'] / max(1, (int) $review['questions_total']) * 100)) ?>%)
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($isPending): ?>
                            <div class="flex flex-wrap items-center gap-1">
                                <button type="button" class="btn btn-primary btn-sm"
                                        data-review-complete="<?= esc($review['id'], 'attr') ?>"
                                        data-label="<?= esc($label, 'attr') ?>">Concluir</button>
                                <button type="button" class="btn btn-ghost btn-sm"
                                        data-review-reschedule="<?= esc($review['id'], 'attr') ?>"
                                        data-label="<?= esc($label, 'attr') ?>">Reagendar</button>
                                <button type="button" class="btn btn-danger btn-sm"
                                        data-review-skip="<?= esc($review['id'], 'attr') ?>">Ignorar</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    'use strict';

    const API = '<?= site_url('estudos/api/revisoes') ?>';

    function reloadSoon() {
        setTimeout(() => window.location.reload(), 900);
    }

    document.querySelectorAll('[data-review-complete]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.reviewComplete;
            const modal = JP.openModal(
                '<h3 class="modal-title">Concluir revisão</h3>' +
                '<p class="text-small text-muted" data-review-label></p>' +
                '<form data-complete-form>' +
                '  <div class="form-row">' +
                '    <div class="field"><label>Total de questões</label>' +
                '      <input type="number" name="questions_total" min="0" value="0" required></div>' +
                '    <div class="field"><label>Acertos</label>' +
                '      <input type="number" name="questions_correct" min="0" value="0" required></div>' +
                '  </div>' +
                '  <div class="field"><label>Dificuldade percebida</label>' +
                '    <select name="difficulty">' +
                '      <option value="1">1 — Fácil</option>' +
                '      <option value="2" selected>2 — Média</option>' +
                '      <option value="3">3 — Difícil</option>' +
                '    </select></div>' +
                '  <div class="field"><label>Observação (opcional)</label>' +
                '    <textarea name="notes" rows="3" placeholder="O que ainda precisa reforçar?"></textarea></div>' +
                '  <div class="modal-actions">' +
                '    <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>' +
                '    <button type="submit" class="btn btn-primary">Concluir revisão</button>' +
                '  </div>' +
                '</form>'
            );

            modal.el.querySelector('[data-review-label]').textContent = btn.dataset.label || '';

            modal.el.querySelector('[data-complete-form]').addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const form = ev.target;
                const res = await JP.api(API + '/' + id + '/concluir', {
                    method: 'POST',
                    body: {
                        questions_total: parseInt(form.questions_total.value || '0', 10),
                        questions_correct: parseInt(form.questions_correct.value || '0', 10),
                        difficulty: parseInt(form.difficulty.value, 10),
                        notes: form.notes.value.trim() || null
                    }
                });

                if (!res.ok) {
                    JP.toast(res.message || 'Não foi possível concluir a revisão.', 'error');
                    return;
                }

                JP.toast(res.message, 'success');
                if (res.data && res.data.xp_awarded > 0) {
                    JP.xpPop(res.data.xp_awarded, btn);
                }
                if (res.data && res.data.new_badges && res.data.new_badges.length > 0) {
                    JP.celebrate();
                }
                modal.close();
                reloadSoon();
            });
        });
    });

    document.querySelectorAll('[data-review-reschedule]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.reviewReschedule;
            const today = new Date().toISOString().slice(0, 10);
            const modal = JP.openModal(
                '<h3 class="modal-title">Reagendar revisão</h3>' +
                '<p class="text-small text-muted" data-review-label></p>' +
                '<form data-reschedule-form>' +
                '  <div class="field"><label>Nova data</label>' +
                '    <input type="date" name="due_date" required></div>' +
                '  <div class="modal-actions">' +
                '    <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>' +
                '    <button type="submit" class="btn btn-primary">Reagendar</button>' +
                '  </div>' +
                '</form>'
            );

            modal.el.querySelector('[data-review-label]').textContent = btn.dataset.label || '';
            const dateInput = modal.el.querySelector('input[name="due_date"]');
            dateInput.min = today;
            dateInput.value = today;

            modal.el.querySelector('[data-reschedule-form]').addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const res = await JP.api(API + '/' + id + '/reagendar', {
                    method: 'POST',
                    body: { due_date: dateInput.value }
                });

                if (!res.ok) {
                    JP.toast(res.message || 'Não foi possível reagendar.', 'error');
                    return;
                }

                JP.toast(res.message, 'success');
                modal.close();
                reloadSoon();
            });
        });
    });

    document.querySelectorAll('[data-review-skip]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const confirmed = await JP.confirmDialog(
                'Ignorar esta revisão? Ela não aparecerá mais como pendente.',
                { danger: true, okLabel: 'Ignorar' }
            );
            if (!confirmed) { return; }

            const res = await JP.api(API + '/' + btn.dataset.reviewSkip + '/ignorar', { method: 'POST' });

            if (!res.ok) {
                JP.toast(res.message || 'Não foi possível ignorar.', 'error');
                return;
            }

            JP.toast(res.message, 'success');
            reloadSoon();
        });
    });
})();
</script>
<?= $this->endSection() ?>
