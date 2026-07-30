<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Importações externas<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Importações externas<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_v('assets/css/flashcards.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php use App\Models\FlashcardModel; ?>

<div class="page-header">
    <div>
        <h1>Pendentes de aprovação</h1>
        <p class="subtitle">Cartões recebidos pela API externa que aguardam sua validação antes de entrar na fila.</p>
    </div>
    <a class="btn btn-ghost" href="<?= site_url('flashcards/integracoes') ?>">← Integrações</a>
</div>

<?= $this->include('flashcards/_tabs') ?>

<div class="card">
    <?php if ($cards === []): ?>
        <div class="empty-state">
            <span class="empty-state-icon">✅</span>
            <p>Nenhum cartão aguardando aprovação.</p>
        </div>
    <?php else: ?>
        <div class="flex gap-1 mb-2 flex-wrap">
            <button type="button" class="btn btn-sm btn-ghost" id="sel-all">Selecionar todos</button>
            <button type="button" class="btn btn-sm btn-ghost" id="sel-none">Limpar seleção</button>
            <button type="button" class="btn btn-primary btn-sm" id="btn-approve">Aprovar selecionados</button>
        </div>

        <div class="card-list" id="pending-list">
            <?php foreach ($cards as $card): ?>
                <div class="card-row" data-card-id="<?= (int) $card['id'] ?>">
                    <label class="checkbox-row" style="margin:0">
                        <input type="checkbox" checked aria-label="Aprovar este cartão">
                    </label>
                    <div class="card-row-body">
                        <div class="card-row-front"><?= esc(mb_strimwidth(strip_tags((string) $card['front']), 0, 160, '…')) ?></div>
                        <div class="card-row-back"><?= esc(mb_strimwidth(strip_tags((string) $card['back']), 0, 200, '…')) ?></div>
                        <div class="card-row-meta">
                            <span class="chip chip-info"><?= esc(FlashcardModel::TYPE_LABELS[$card['card_type']] ?? $card['card_type']) ?></span>
                            <?php if ($card['subject_name']): ?>
                                <span class="chip"><?= esc($card['subject_name']) ?></span>
                            <?php endif; ?>
                            <?php if ($card['topic_name']): ?>
                                <span class="chip"><?= esc($card['topic_name']) ?></span>
                            <?php endif; ?>
                            <?php if ($card['external_id']): ?>
                                <span class="chip text-small"><?= esc($card['external_id']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-row-actions">
                        <button type="button" class="btn btn-sm btn-danger" data-act="delete">Excluir</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <div class="flex gap-1 mt-2 flex-wrap">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?>"
                       href="<?= site_url('flashcards/importacoes?pagina=' . $p) ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const list = document.getElementById('pending-list');
    if (!list) { return; }

    function selectedIds() {
        return Array.from(list.querySelectorAll('.card-row'))
            .filter((row) => row.querySelector('input[type="checkbox"]').checked)
            .map((row) => Number(row.dataset.cardId));
    }

    document.getElementById('sel-all').addEventListener('click', () => {
        list.querySelectorAll('input[type="checkbox"]').forEach((c) => { c.checked = true; });
    });

    document.getElementById('sel-none').addEventListener('click', () => {
        list.querySelectorAll('input[type="checkbox"]').forEach((c) => { c.checked = false; });
    });

    document.getElementById('btn-approve').addEventListener('click', async () => {
        const ids = selectedIds();

        if (ids.length === 0) {
            JP.toast('Selecione ao menos um cartão.', 'error');
            return;
        }

        const payload = await JP.api('<?= site_url('flashcards/api/cartoes/aprovar') ?>', {
            method: 'POST',
            body: { ids }
        });

        JP.toast(payload.message || '', payload.ok ? 'success' : 'error');

        if (payload.ok) { setTimeout(() => window.location.reload(), 700); }
    });

    list.addEventListener('click', async (ev) => {
        const button = ev.target.closest('[data-act="delete"]');
        if (!button) { return; }

        const row = button.closest('.card-row');

        const ok = await JP.confirmDialog('Excluir este cartão importado?', {
            title: 'Excluir cartão', okLabel: 'Excluir', danger: true
        });

        if (!ok) { return; }

        const payload = await JP.api('/flashcards/api/cartoes/' + row.dataset.cardId + '/excluir', {
            method: 'POST', body: {}
        });

        JP.toast(payload.message || '', payload.ok ? 'success' : 'error');
        if (payload.ok) { row.remove(); }
    });
})();
</script>
<?= $this->endSection() ?>
