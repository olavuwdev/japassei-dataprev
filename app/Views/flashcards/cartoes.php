<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Meus cartões<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Meus cartões<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/flashcards.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
use App\Models\FlashcardModel;
use App\Models\FlashcardStateModel;

$typeLabels  = FlashcardModel::TYPE_LABELS;
$stateLabels = FlashcardStateModel::STATE_LABELS;
$stateChips  = [0 => 'chip-info', 1 => 'chip-flame', 2 => 'chip-primary', 3 => 'chip-danger'];
?>

<div class="page-header">
    <div>
        <h1>Meus cartões</h1>
        <p class="subtitle"><?= (int) $total ?> cartão(ões) encontrados.</p>
    </div>
    <button type="button" class="btn btn-primary" id="btn-new-card">➕ Criar flashcard</button>
</div>

<?= $this->include('flashcards/_tabs') ?>

<form class="card" method="get" action="<?= site_url('flashcards/cartoes') ?>">
    <div class="form-row">
        <div class="field">
            <label class="field-label" for="f-busca">Buscar</label>
            <input type="search" id="f-busca" name="busca" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Pergunta ou resposta">
        </div>
        <div class="field">
            <label class="field-label" for="f-disciplina">Disciplina</label>
            <select id="f-disciplina" name="disciplina">
                <option value="">Todas</option>
                <?php foreach ($taxonomy['subjects'] as $subject): ?>
                    <option value="<?= (int) $subject['id'] ?>" <?= (int) ($filters['subject_id'] ?? 0) === (int) $subject['id'] ? 'selected' : '' ?>>
                        <?= esc($subject['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field-label" for="f-tipo">Tipo</label>
            <select id="f-tipo" name="tipo">
                <option value="">Todos</option>
                <?php foreach ($typeLabels as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($filters['card_type'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field-label" for="f-suspensos">Situação</label>
            <select id="f-suspensos" name="suspensos">
                <option value="">Todas</option>
                <option value="0" <?= ($filters['suspended'] ?? '') === '0' ? 'selected' : '' ?>>Ativos</option>
                <option value="1" <?= ($filters['suspended'] ?? '') === '1' ? 'selected' : '' ?>>Suspensos</option>
            </select>
        </div>
        <div class="field" style="align-self:flex-end">
            <button type="submit" class="btn btn-ghost">Filtrar</button>
        </div>
    </div>
</form>

<div class="card mt-2">
    <?php if ($cards === []): ?>
        <div class="empty-state">
            <span class="empty-state-icon">🔍</span>
            <p>Nenhum cartão encontrado com esses filtros.</p>
        </div>
    <?php else: ?>
        <div class="card-list" id="card-list">
            <?php foreach ($cards as $card): ?>
                <?php $state = $card['state'] === null ? 0 : (int) $card['state']; ?>
                <div class="card-row<?= $card['suspended'] ? ' is-suspended' : '' ?>" data-card-id="<?= (int) $card['id'] ?>">
                    <div class="card-row-body">
                        <div class="card-row-front"><?= esc(mb_strimwidth(strip_tags((string) $card['front']), 0, 160, '…')) ?></div>
                        <div class="card-row-back"><?= esc(mb_strimwidth(strip_tags((string) $card['back']), 0, 200, '…')) ?></div>
                        <div class="card-row-meta">
                            <span class="chip chip-info"><?= esc($typeLabels[$card['card_type']] ?? $card['card_type']) ?></span>
                            <span class="chip <?= $stateChips[$state] ?? '' ?>"><?= esc($stateLabels[$state] ?? '') ?></span>
                            <?php if ($card['subject_name']): ?>
                                <span class="chip"><?= esc($card['subject_name']) ?></span>
                            <?php endif; ?>
                            <?php if ($card['topic_name']): ?>
                                <span class="chip"><?= esc($card['topic_name']) ?></span>
                            <?php endif; ?>
                            <?php if ((int) $card['lapses'] > 0): ?>
                                <span class="chip chip-danger"><?= (int) $card['lapses'] ?> esquecimento(s)</span>
                            <?php endif; ?>
                            <?php if ($card['suspended']): ?>
                                <span class="chip chip-gold">Suspenso</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-row-actions">
                        <button type="button" class="btn btn-sm btn-ghost" data-act="edit">Editar</button>
                        <button type="button" class="btn btn-sm btn-ghost" data-act="suspend">
                            <?= $card['suspended'] ? 'Reativar' : 'Suspender' ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-ghost" data-act="improve" title="Pedir uma versão melhor à IA">✨ Melhorar</button>
                        <button type="button" class="btn btn-sm btn-danger" data-act="delete">Excluir</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <div class="flex gap-1 mt-2 flex-wrap" role="navigation" aria-label="Paginação">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <?php
                    $query = array_filter([
                        'busca'     => $filters['search'] ?? null,
                        'disciplina' => $filters['subject_id'] ?? null,
                        'tipo'      => $filters['card_type'] ?? null,
                        'suspensos' => $filters['suspended'] ?? null,
                        'pagina'    => $p,
                    ], static fn ($v) => $v !== null && $v !== '');
                    ?>
                    <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"
                       href="<?= site_url('flashcards/cartoes?' . http_build_query($query)) ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script type="application/json" id="taxonomy-data">
    <?= json_encode([
        'subjects' => array_map(static fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name']], $taxonomy['subjects']),
        'topics'   => array_map(static fn ($t) => ['id' => (int) $t['id'], 'subject_id' => (int) $t['subject_id'], 'name' => $t['name']], $taxonomy['topics']),
        'types'    => $typeLabels,
    ], JSON_UNESCAPED_UNICODE) ?>
</script>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/flashcards-cards.js?v=2') ?>"></script>
<?= $this->endSection() ?>
