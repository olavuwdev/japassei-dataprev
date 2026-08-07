<?= $this->extend('layouts/main') ?>

<?php
$isEdit = $user !== null;
$title  = $isEdit ? 'Editar usuário' : 'Novo usuário';
$action = $isEdit
    ? site_url('estudos/usuarios/' . (int) $user['id'])
    : site_url('estudos/usuarios/novo');

$oldPermissions = old('permissions');
$checked        = $oldPermissions !== null ? array_map('strval', (array) $oldPermissions) : $granted;

$isActive = old('active') !== null
    ? true
    : (old('name') !== null ? false : ($isEdit ? ! empty($user['active']) : true));

$errors = session()->getFlashdata('errors') ?? [];
?>

<?= $this->section('title') ?><?= esc($title) ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?><?= esc($title) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-header">
    <div>
        <h1><?= esc($title) ?></h1>
        <p class="subtitle">
            <?= $isEdit
                ? 'Ajuste os dados de acesso e as permissões deste usuário.'
                : 'Preencha os dados de acesso e marque a que ele terá acesso.' ?>
        </p>
    </div>
    <a class="btn btn-ghost" href="<?= site_url('estudos/usuarios') ?>">Voltar</a>
</div>

<?php if ($errors !== []): ?>
    <div class="flash flash-error" role="alert">
        <?php foreach ($errors as $message): ?>
            <div><?= esc($message) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= esc($action, 'attr') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header"><h3>👤 Dados de acesso</h3></div>

        <div class="field">
            <label for="u-name">Nome</label>
            <input type="text" id="u-name" name="name" maxlength="120" required
                   value="<?= esc(old('name', $user['name'] ?? ''), 'attr') ?>">
        </div>

        <div class="field">
            <label for="u-email">E-mail</label>
            <input type="email" id="u-email" name="email" maxlength="190" required
                   value="<?= esc(old('email', $user['email'] ?? ''), 'attr') ?>">
        </div>

        <div class="form-row">
            <div class="field">
                <label for="u-password">Senha</label>
                <input type="password" id="u-password" name="password" autocomplete="new-password"
                       <?= $isEdit ? '' : 'required' ?>>
                <div class="text-small text-faint mt-1">
                    <?= $isEdit ? 'Deixe em branco para manter a senha atual.' : 'Mínimo de 6 caracteres.' ?>
                </div>
            </div>

            <div class="field">
                <label for="u-password-confirm">Confirmar senha</label>
                <input type="password" id="u-password-confirm" name="password_confirm" autocomplete="new-password"
                       <?= $isEdit ? '' : 'required' ?>>
            </div>
        </div>

        <div class="field">
            <label class="checkbox-row">
                <input type="checkbox" name="active" value="1" <?= $isActive ? 'checked' : '' ?>>
                Conta ativa
            </label>
            <div class="text-small text-faint mt-1">Uma conta inativa não consegue entrar no sistema.</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>🔐 Permissões</h3></div>

        <?php foreach ($catalog as $code => $info): ?>
            <div class="field">
                <label class="checkbox-row">
                    <input type="checkbox" name="permissions[]" value="<?= esc($code, 'attr') ?>"
                        <?= in_array((string) $code, $checked, true) ? 'checked' : '' ?>>
                    <?= esc($info['label']) ?>
                </label>
                <div class="text-small text-faint mt-1"><?= esc($info['description']) ?></div>
            </div>
        <?php endforeach; ?>

        <div class="text-small text-muted mt-1">
            Sem a permissão, o item some do menu e o endereço direto é bloqueado.
        </div>
    </div>

    <div class="flex gap-1">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Salvar alterações' : 'Criar usuário' ?></button>
        <a class="btn btn-ghost" href="<?= site_url('estudos/usuarios') ?>">Cancelar</a>
    </div>
</form>

<?= $this->endSection() ?>
