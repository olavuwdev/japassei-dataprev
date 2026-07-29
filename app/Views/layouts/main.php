<?php
$user      = session()->get('user');
$currentUrl = uri_string();
$menu = [
    ['url' => 'estudos',               'label' => 'Visão geral',   'icon' => '🏠', 'exact' => true],
    ['url' => 'estudos/hoje',          'label' => 'Hoje',          'icon' => '📌'],
    ['url' => 'estudos/cronograma',    'label' => 'Cronograma',    'icon' => '🗓️'],
    ['url' => 'estudos/kanban',        'label' => 'Kanban',        'icon' => '🗂️'],
    ['url' => 'estudos/revisoes',      'label' => 'Revisões',      'icon' => '🔁'],
    ['url' => 'flashcards',            'label' => 'Flashcards',    'icon' => '🃏'],
    ['url' => 'estudos/questoes',      'label' => 'Questões',      'icon' => '✍️'],
    ['url' => 'estudos/provas',        'label' => 'Provas antigas','icon' => '📄'],
    ['url' => 'estudos/desempenho',    'label' => 'Desempenho',    'icon' => '📈'],
    ['url' => 'estudos/historico',     'label' => 'Histórico',     'icon' => '🕘'],
    ['url' => 'estudos/configuracoes', 'label' => 'Configurações', 'icon' => '⚙️'],
];
$isActive = static function (array $item) use ($currentUrl): bool {
    if (! empty($item['exact'])) {
        return $currentUrl === $item['url'];
    }

    return str_starts_with($currentUrl, $item['url']);
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-header" content="<?= csrf_header() ?>">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <meta name="theme-color" content="#FAF5EC">
    <title><?= esc($this->renderSection('title', true) ?: 'Estudos') ?> · Já Passei DATAPREV</title>
    <link rel="icon" href="<?= base_url('favicon.svg') ?>" type="image/svg+xml">
    <link rel="icon" href="<?= base_url('favicon-32.png') ?>" type="image/png" sizes="32x32">
    <link rel="icon" href="<?= base_url('favicon-16.png') ?>" type="image/png" sizes="16x16">
    <link rel="shortcut icon" href="<?= base_url('favicon.ico') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('apple-touch-icon.png') ?>" sizes="180x180">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700;12..96,800&family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="<?= site_url('estudos') ?>">
            <span class="brand-flame" aria-hidden="true">🔥</span>
            <span class="brand-text">Já&nbsp;Passei<em>DATAPREV 2026</em></span>
        </a>
        <nav class="sidebar-nav" aria-label="Menu principal">
            <?php foreach ($menu as $item): ?>
                <a href="<?= site_url($item['url']) ?>" class="nav-item<?= $isActive($item) ? ' is-active' : '' ?>">
                    <span class="nav-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                    <span><?= esc($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <span class="avatar" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($user['name'] ?? '?', 0, 1))) ?></span>
                <span class="sidebar-user-name"><?= esc($user['name'] ?? '') ?></span>
            </div>
            <a class="nav-item nav-logout" href="<?= site_url('logout') ?>">🚪 <span>Sair</span></a>
        </div>
    </aside>

    <div class="main-column">
        <header class="topbar">
            <a class="brand brand-mobile" href="<?= site_url('estudos') ?>">
                <span class="brand-flame" aria-hidden="true">🔥</span>
                <span class="brand-text">Já&nbsp;Passei</span>
            </a>
            <div class="topbar-title"><?= esc($this->renderSection('page_title', true) ?: '') ?></div>
            <a class="topbar-user" href="<?= site_url('estudos/configuracoes') ?>" title="<?= esc($user['name'] ?? '') ?>">
                <span class="avatar"><?= esc(mb_strtoupper(mb_substr($user['name'] ?? '?', 0, 1))) ?></span>
            </a>
        </header>

        <main class="content" id="content">
            <?php if (session()->getFlashdata('success')): ?>
                <div class="flash flash-success" role="status"><?= esc(session()->getFlashdata('success')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('error')): ?>
                <div class="flash flash-error" role="alert"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('warning')): ?>
                <div class="flash flash-warning" role="alert"><?= esc(session()->getFlashdata('warning')) ?></div>
            <?php endif; ?>

            <?= $this->renderSection('content') ?>
        </main>

        <nav class="bottom-nav" aria-label="Menu inferior">
            <?php
            // Selecionados por URL para não quebrar quando o menu muda de ordem.
            $byUrl       = array_column($menu, null, 'url');
            $bottomItems = array_values(array_filter([
                $byUrl['estudos/hoje']       ?? null,
                $byUrl['estudos/kanban']     ?? null,
                $byUrl['estudos']            ?? null,
                $byUrl['flashcards']         ?? null,
                $byUrl['estudos/desempenho'] ?? null,
            ]));
            foreach ($bottomItems as $item): ?>
                <a href="<?= site_url($item['url']) ?>" class="bottom-item<?= $isActive($item) ? ' is-active' : '' ?>">
                    <span class="bottom-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                    <span class="bottom-label"><?= esc($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<div id="toast-root" class="toast-root" aria-live="polite"></div>

<script src="<?= base_url('assets/js/flashcards-config.js') ?>"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
