<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Estatísticas dos flashcards<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Estatísticas<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/flashcards.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$totalRatings = array_sum($ratings);
$ratingMeta   = [
    'again' => ['Não lembrei', 'var(--danger)'],
    'hard'  => ['Difícil', 'var(--gold)'],
    'good'  => ['Bom', 'var(--primary)'],
    'easy'  => ['Fácil', 'var(--info)'],
];
$maxWeek = max(1, max(array_column($week, 'total')));
?>

<div class="page-header">
    <div>
        <h1>Estatísticas</h1>
        <p class="subtitle">Como sua memorização está evoluindo e quanto trabalho vem pela frente.</p>
    </div>
</div>

<?= $this->include('flashcards/_tabs') ?>

<div class="grid grid-4">
    <div class="stat">
        <span class="stat-value"><?= (int) $summary['total_cards'] ?></span>
        <span class="stat-label">Cartões ativos</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $summary['reviewed_today'] ?></span>
        <span class="stat-label">Revisados hoje</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= $summary['recall_rate'] === null ? '—' : $summary['recall_rate'] . '%' ?></span>
        <span class="stat-label">Taxa de lembrança (30 dias)</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $summary['streak_days'] ?></span>
        <span class="stat-label">Dias seguidos</span>
    </div>
</div>

<div class="grid grid-2 mt-3">
    <div class="card">
        <div class="card-header"><h3>🎯 Histórico de avaliações (30 dias)</h3></div>

        <?php if ($totalRatings === 0): ?>
            <div class="empty-state"><p>Nenhuma avaliação registrada no período.</p></div>
        <?php else: ?>
            <?php foreach ($ratingMeta as $key => $meta): ?>
                <?php $percent = round(($ratings[$key] / $totalRatings) * 100, 1); ?>
                <div class="progress-label">
                    <span><?= esc($meta[0]) ?></span>
                    <span><?= (int) $ratings[$key] ?> · <?= $percent ?>%</span>
                </div>
                <div class="progress" role="img" aria-label="<?= esc($meta[0]) ?>: <?= $percent ?>%">
                    <div class="progress-bar" style="width: <?= $percent ?>%; background: <?= $meta[1] ?>"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><h3>📅 Previsão de carga</h3></div>

        <div class="grid grid-3">
            <div class="stat">
                <span class="stat-value"><?= (int) $forecast['tomorrow'] ?></span>
                <span class="stat-label">Amanhã</span>
            </div>
            <div class="stat">
                <span class="stat-value"><?= (int) $forecast['week'] ?></span>
                <span class="stat-label">Próximos 7 dias</span>
            </div>
            <div class="stat">
                <span class="stat-value"><?= (int) $forecast['month'] ?></span>
                <span class="stat-label">Próximos 30 dias</span>
            </div>
        </div>

        <p class="text-small text-muted mt-2">
            A previsão é informativa: cada nova resposta recalcula o agendamento pelo FSRS.
        </p>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h3>📈 Revisões por dia (14 dias)</h3></div>
    <div class="flex gap-1 flex-wrap items-center" style="align-items:flex-end; min-height:120px">
        <?php foreach ($week as $day): ?>
            <div style="display:flex; flex-direction:column; align-items:center; gap:4px; min-width:44px">
                <span class="text-small fw-bold"><?= (int) $day['total'] ?></span>
                <div style="width:26px; border-radius:6px 6px 0 0; background:var(--primary);
                            height: <?= max(4, (int) round(($day['total'] / $maxWeek) * 90)) ?>px"
                     role="img" aria-label="<?= date('d/m', strtotime($day['day'])) ?>: <?= (int) $day['total'] ?> revisões"></div>
                <span class="text-small text-muted"><?= date('d/m', strtotime($day['day'])) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>📚 Desempenho por disciplina</h3>
        <span class="chip"><?= count($subjects) ?></span>
    </div>

    <?php if ($subjects === []): ?>
        <div class="empty-state"><p>Sem dados por disciplina ainda.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Disciplina</th>
                        <th>Cartões</th>
                        <th>Novos</th>
                        <th>Pendentes</th>
                        <th>Suspensos</th>
                        <th>Problemáticos</th>
                        <th>Taxa de acerto</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($subjects as $subject): ?>
                    <tr>
                        <td><?= esc($subject['subject_name']) ?></td>
                        <td><?= (int) $subject['total_cards'] ?></td>
                        <td><?= (int) $subject['new_cards'] ?></td>
                        <td><?= (int) $subject['due_cards'] ?></td>
                        <td><?= (int) $subject['suspended_cards'] ?></td>
                        <td><?= (int) $subject['flagged_cards'] ?></td>
                        <td><?= $subject['retention'] === null ? '—' : $subject['retention'] . '%' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>⚠️ Cartões problemáticos</h3>
        <span class="chip"><?= count($problematic) ?></span>
    </div>

    <?php if ($problematic === []): ?>
        <div class="empty-state"><p>Nenhum cartão problemático identificado.</p></div>
    <?php else: ?>
        <p class="text-small text-muted">
            Cartões esquecidos com frequência, com resposta longa demais, muitas edições ou baixa confiança na geração.
            Use “✨ Melhorar” na tela de cartões para pedir uma reescrita à IA.
        </p>
        <div class="card-list mt-2">
            <?php foreach ($problematic as $card): ?>
                <div class="card-row">
                    <div class="card-row-body">
                        <div class="card-row-front"><?= esc(mb_strimwidth(strip_tags((string) $card['front']), 0, 130, '…')) ?></div>
                        <div class="card-row-meta">
                            <?php if ((int) $card['lapses'] > 0): ?>
                                <span class="chip chip-danger"><?= (int) $card['lapses'] ?> esquecimento(s)</span>
                            <?php endif; ?>
                            <?php if ((int) $card['hard_count'] > 2): ?>
                                <span class="chip chip-gold"><?= (int) $card['hard_count'] ?>× difícil</span>
                            <?php endif; ?>
                            <?php if ((int) $card['answer_length'] >= 1200): ?>
                                <span class="chip chip-gold">Resposta longa</span>
                            <?php endif; ?>
                            <?php if ((int) $card['edit_count'] >= 5): ?>
                                <span class="chip">Muitas edições</span>
                            <?php endif; ?>
                            <?php if ($card['ai_confidence'] !== null && (float) $card['ai_confidence'] < 0.6): ?>
                                <span class="chip chip-gold">Baixa confiança da IA</span>
                            <?php endif; ?>
                            <?php if ($card['subject_name']): ?>
                                <span class="chip"><?= esc($card['subject_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-row-actions">
                        <a class="btn btn-sm btn-ghost" href="<?= site_url('flashcards/cartoes?busca=' . urlencode(mb_substr(strip_tags((string) $card['front']), 0, 40))) ?>">Abrir</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card mt-3">
    <div class="card-header"><h3>🤖 Consumo da IA (30 dias)</h3></div>
    <div class="grid grid-4">
        <div class="stat">
            <span class="stat-value"><?= (int) $aiUsage['requests'] ?></span>
            <span class="stat-label">Gerações</span>
        </div>
        <div class="stat">
            <span class="stat-value"><?= number_format($aiUsage['input_tokens'] + $aiUsage['output_tokens']) ?></span>
            <span class="stat-label">Tokens consumidos</span>
        </div>
        <div class="stat">
            <span class="stat-value">US$ <?= number_format($aiUsage['cost'], 4) ?></span>
            <span class="stat-label">Custo estimado</span>
        </div>
        <div class="stat">
            <span class="stat-value"><?= (int) $aiUsage['failures'] ?></span>
            <span class="stat-label">Falhas</span>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
