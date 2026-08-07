<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar · Já Passei DATAPREV</title>
    <link rel="icon" href="<?= asset_v('favicon.ico') ?>" sizes="any">
    <link rel="icon" href="<?= asset_v('favicon.svg') ?>" type="image/svg+xml">
    <link rel="icon" href="<?= asset_v('favicon-32.png') ?>" type="image/png" sizes="32x32">
    <link rel="icon" href="<?= asset_v('favicon-16.png') ?>" type="image/png" sizes="16x16">
    <link rel="apple-touch-icon" href="<?= asset_v('apple-touch-icon.png') ?>" sizes="180x180">
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

            <div class="auth-options">
                <label class="checkbox-row" for="remember_email">
                    <input type="checkbox" id="remember_email" name="remember_email" value="1">
                    <span>Lembrar meu e-mail neste dispositivo</span>
                </label>
                <label class="checkbox-row" for="remember_me">
                    <input type="checkbox" id="remember_me" name="remember_me" value="1" <?= old('remember_me') ? 'checked' : '' ?>>
                    <span>Manter-me conectado por 30 dias</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block">Entrar</button>
        </form>

        <p class="text-center text-small mt-3 mb-0">
            Precisa de acesso? Peça a conta a quem administra o sistema.
        </p>
    </div>
</div>

<script>
// "Lembrar meu e-mail": guarda apenas o e-mail no localStorage, para poupar
// digitação. A senha nunca é armazenada — quem cuida disso é o gerenciador de
// senhas do navegador, via autocomplete="current-password".
(function () {
    var KEY = 'japassei:login:email';
    var form = document.querySelector('.auth-card form');
    var email = document.getElementById('email');
    var check = document.getElementById('remember_email');
    var password = document.getElementById('password');

    if (!form || !email || !check) {
        return;
    }

    var saved = null;

    try {
        saved = localStorage.getItem(KEY);
    } catch (e) {
        return; // localStorage bloqueado (modo privado / cookies desativados)
    }

    if (saved && !email.value) {
        email.value = saved;
        check.checked = true;

        if (password) {
            password.focus();
        }
    }

    form.addEventListener('submit', function () {
        try {
            if (check.checked && email.value.trim()) {
                localStorage.setItem(KEY, email.value.trim());
            } else {
                localStorage.removeItem(KEY);
            }
        } catch (e) {
            // Sem persistência disponível: segue o login normalmente.
        }
    });
})();
</script>
</body>
</html>
