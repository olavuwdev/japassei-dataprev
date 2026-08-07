<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Usuários<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Usuários<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-header">
    <div>
        <h1>Usuários</h1>
        <p class="subtitle">Cadastre quem usa o sistema e escolha a que cada um tem acesso.</p>
    </div>
    <a class="btn btn-primary" href="<?= site_url('estudos/usuarios/novo') ?>">+ Novo usuário</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Situação</th>
                    <th>Permissões</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users === []): ?>
                    <tr>
                        <td colspan="5" class="text-muted text-small">Nenhum usuário cadastrado.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($users as $item): ?>
                    <?php
                    $id      = (int) $item['id'];
                    $granted = $permissions[$id] ?? [];
                    ?>
                    <tr>
                        <td>
                            <?= esc($item['name']) ?>
                            <?php if ($id === $currentId): ?>
                                <span class="chip chip-primary">você</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-small"><?= esc($item['email']) ?></td>
                        <td>
                            <?php if (! empty($item['active'])): ?>
                                <span class="chip chip-primary">Ativo</span>
                            <?php else: ?>
                                <span class="chip chip-danger">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($granted === []): ?>
                                <span class="text-faint text-small">Nenhuma</span>
                            <?php else: ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach ($granted as $code): ?>
                                        <span class="chip chip-info"><?= esc($catalog[$code]['label'] ?? $code) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a class="btn btn-sm btn-ghost" href="<?= site_url('estudos/usuarios/' . $id) ?>">Editar</a>
                            <?php if ($id !== $currentId): ?>
                                <form method="post" action="<?= site_url('estudos/usuarios/' . $id . '/excluir') ?>"
                                      style="display: inline;"
                                      data-confirm="Excluir o usuário <?= esc($item['name'], 'attr') ?>? Os dados de estudo e flashcards dele deixam de ser acessíveis.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>🔐 O que cada permissão libera</h3></div>
    <?php foreach ($catalog as $code => $info): ?>
        <p class="mb-1">
            <strong><?= esc($info['label']) ?></strong>
            <span class="text-small text-muted">— <?= esc($info['description']) ?></span>
        </p>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', async function (ev) {
        if (form.dataset.confirmed === '1') { return; }

        ev.preventDefault();

        var ok = await JP.confirmDialog(form.dataset.confirm, {
            title: 'Excluir usuário',
            okLabel: 'Excluir',
            danger: true
        });

        if (ok) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });
});
</script>
<?= $this->endSection() ?>
