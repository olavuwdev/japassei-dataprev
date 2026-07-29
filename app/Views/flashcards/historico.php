<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Histórico de revisões<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Histórico<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/flashcards.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
use App\Models\FlashcardReviewLogModel;
use App\Models\FlashcardStateModel;

$ratingChips = [
    1 => 'chip-danger',
    2 => 'chip-gold',
    3 => 'chip-primary',
    4 => 'chip-info',
];
?>

<div class="page-header">
    <div>
        <h1>Histórico</h1>
        <p class="subtitle"><?= (int) $total ?> revisão(ões) registradas. Nada é apagado — respostas desfeitas ficam marcadas.</p>
    </div>
</div>

<?= $this->include('flashcards/_tabs') ?>

<div class="card">
    <?php if ($items === []): ?>
        <div class="empty-state">
            <span class="empty-state-icon">🕘</span>
            <p>Nenhuma revisão registrada ainda.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Quando</th>
                        <th>Cartão</th>
                        <th>Disciplina</th>
                        <th>Avaliação</th>
                        <th>Estado</th>
                        <th>Próxima revisão</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr<?= $item['undone'] ? ' style="opacity:.55"' : '' ?>>
                        <td class="text-small text-muted">
                            <?= date('d/m/Y H:i', strtotime((string) $item['reviewed_at'] . ' UTC')) ?>
                        </td>
                        <td><?= esc(mb_strimwidth(strip_tags((string) $item['front']), 0, 90, '…')) ?></td>
                        <td class="text-small"><?= esc($item['subject_name'] ?? '—') ?></td>
                        <td>
                            <span class="chip <?= $ratingChips[(int) $item['rating']] ?? '' ?>">
                                <?= esc(FlashcardReviewLogModel::RATING_LABELS[(int) $item['rating']] ?? '') ?>
                            </span>
                            <?php if ($item['undone']): ?>
                                <span class="chip">desfeita</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-small">
                            <?= esc(FlashcardStateModel::STATE_LABELS[(int) $item['state_before']] ?? '') ?>
                            →
                            <?= esc(FlashcardStateModel::STATE_LABELS[(int) $item['state_after']] ?? '') ?>
                        </td>
                        <td class="text-small text-muted">
                            <?= $item['due_after'] ? date('d/m/Y H:i', strtotime((string) $item['due_after'] . ' UTC')) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <div class="flex gap-1 mt-2 flex-wrap" role="navigation" aria-label="Paginação">
                <?php for ($p = max(1, $page - 4); $p <= min($pages, $page + 4); $p++): ?>
                    <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"
                       href="<?= site_url('flashcards/historico?pagina=' . $p) ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
