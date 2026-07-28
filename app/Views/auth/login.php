<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar · Já Passei DATAPREV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,700;12..96,800&family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-flame" aria-hidden="true">🔥</span>
            <h1>Já Passei</h1>
            <p>Sua rotina de estudos para a DATAPREV 2026</p>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="flash flash-error"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('warning')): ?>
            <div class="flash flash-warning"><?= esc(session()->getFlashdata('warning')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="flash flash-error">
                <?php foreach ((array) session()->getFlashdata('errors') as $error): ?>
                    <div><?= esc($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('login') ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?= esc(old('email') ?? '') ?>" required autofocus autocomplete="email">
            </div>
            <div class="field">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block">Entrar</button>
        </form>

        <p class="text-center text-small mt-3 mb-0">
            Ainda não tem conta? <a href="<?= site_url('registrar') ?>">Criar conta</a>
        </p>
    </div>
</div>
</body>
</html>
