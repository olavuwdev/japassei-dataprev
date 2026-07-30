<?php
/**
 * Navegação interna do módulo de flashcards.
 * O menu lateral do sistema não usa submenus, então as seções ficam em abas.
 */
$current = uri_string();
$items   = [
    ['url' => 'flashcards',               'label' => 'Visão geral',   'exact' => true],
    ['url' => 'flashcards/revisar',       'label' => 'Revisar agora'],
    ['url' => 'flashcards/cartoes',       'label' => 'Meus cartões'],
    ['url' => 'flashcards/gerar',         'label' => 'Gerar com IA'],
    ['url' => 'flashcards/fontes',        'label' => 'Fontes de estudo'],
    ['url' => 'flashcards/historico',     'label' => 'Histórico'],
    ['url' => 'flashcards/estatisticas',  'label' => 'Estatísticas'],
    ['url' => 'flashcards/integracoes',   'label' => 'Integrações e API'],
    ['url' => 'flashcards/configuracoes', 'label' => 'Configurações'],
];
?>
<div class="fc-tabs-scroll">
    <nav class="fc-tabs" aria-label="Seções de flashcards">
        <?php foreach ($items as $item): ?>
            <?php
            $active = ! empty($item['exact'])
                ? $current === $item['url']
                : str_starts_with($current, $item['url']);
            ?>
            <a class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-ghost' ?>"
               href="<?= site_url($item['url']) ?>"
               <?= $active ? 'aria-current="page"' : '' ?>><?= esc($item['label']) ?></a>
        <?php endforeach; ?>
    </nav>
</div>
