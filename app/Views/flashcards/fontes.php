<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Fontes de estudo<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Fontes de estudo<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_v('assets/css/flashcards.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$statusChips = [
    'pending'    => ['Pendente', 'chip-info'],
    'processing' => ['Processando', 'chip-gold'],
    'done'       => ['Concluído', 'chip-primary'],
    'warning'    => ['Concluído com alertas', 'chip-gold'],
    'error'      => ['Erro', 'chip-danger'],
    'cancelled'  => ['Cancelado', 'chip'],
];
$typeLabels = [
    'text'        => 'Texto',
    'url'         => 'Link',
    'external_ai' => 'IA externa',
];
?>

<div class="page-header">
    <div>
        <h1>Fontes de estudo</h1>
        <p class="subtitle">Materiais que já viraram flashcards, com o estado de cada processamento.</p>
    </div>
    <a class="btn btn-primary" href="<?= site_url('flashcards/gerar') ?>">✨ Nova geração</a>
</div>

<?= $this->include('flashcards/_tabs') ?>

<div class="card">
    <?php if ($sources === []): ?>
        <div class="empty-state">
            <span class="empty-state-icon">📄</span>
            <p>Nenhuma fonte processada ainda.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Origem</th>
                        <th>Disciplina</th>
                        <th>Cartões</th>
                        <th>Situação</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sources as $source): ?>
                    <?php $chip = $statusChips[$source['status']] ?? [$source['status'], 'chip']; ?>
                    <tr>
                        <td>
                            <?= esc($source['title']) ?>
                            <?php if ($source['url']): ?>
                                <br><a class="text-small" href="<?= esc($source['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">abrir original</a>
                            <?php endif; ?>
                            <?php if ($source['error_message']): ?>
                                <br><span class="text-small" style="color:var(--danger)"><?= esc(mb_strimwidth((string) $source['error_message'], 0, 140, '…')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="chip"><?= esc($typeLabels[$source['source_type']] ?? $source['source_type']) ?></span></td>
                        <td class="text-small"><?= esc($source['subject_name'] ?? '—') ?></td>
                        <td><?= (int) $source['cards_count'] ?></td>
                        <td><span class="chip <?= $chip[1] ?>"><?= esc($chip[0]) ?></span></td>
                        <td class="text-small text-muted">
                            <?= $source['created_at'] ? date('d/m/Y H:i', strtotime((string) $source['created_at'] . ' UTC')) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
