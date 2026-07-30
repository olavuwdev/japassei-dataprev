<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar conta · Já Passei DATAPREV</title>
    <link rel="icon" href="<?= base_url('favicon.svg') ?>" type="image/svg+xml">
    <link rel="icon" href="<?= base_url('favicon-32.png') ?>" type="image/png" sizes="32x32">
    <link rel="shortcut icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('apple-touch-icon.png') ?>" sizes="180x180">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,700;12..96,800&family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_v('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-flame" aria-hidden="true">🔥</span>
            <h1>Criar conta</h1>
            <p>Comece hoje sua preparação</p>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="flash flash-error"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="flash flash-error">
                <?php foreach ((array) session()->getFlashdata('errors') as $error): ?>
                    <div><?= esc($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('registrar') ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="name">Nome</label>
                <input type="text" id="name" name="name" value="<?= esc(old('name') ?? '') ?>" required autofocus autocomplete="name">
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?= esc(old('email') ?? '') ?>" required autocomplete="email">
            </div>
            <div class="field">
                <label for="password">Senha (mínimo 6 caracteres)</label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
            </div>
            <div class="field">
                <label for="password_confirm">Confirmar senha</label>
                <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block">Criar conta</button>
        </form>

        <p class="text-center text-small mt-3 mb-0">
            Já tem conta? <a href="<?= site_url('login') ?>">Entrar</a>
        </p>
    </div>
</div>
</body>
</html>
