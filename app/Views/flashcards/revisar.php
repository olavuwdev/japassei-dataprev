<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Revisar flashcards<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Revisão<?= $this->endSection() ?>

<?= $this->section('body_class') ?>is-review<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_v('assets/css/flashcards.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$subjectName = '';

if (! empty($filters['subject_id'])) {
    foreach ($taxonomy['subjects'] as $subject) {
        if ((int) $subject['id'] === (int) $filters['subject_id']) {
            $subjectName = $subject['name'];
            break;
        }
    }
}

$topicName = '';

if (! empty($filters['topic_id'])) {
    foreach ($taxonomy['topics'] as $topic) {
        if ((int) $topic['id'] === (int) $filters['topic_id']) {
            $topicName = $topic['name'];
            break;
        }
    }
}

$config = [
    // A sessão só começa com um clique: o cronômetro e a fila são criados ali.
    'autoStart'     => false,
    'showIntervals' => (bool) $settings['show_intervals'],
    'showTimer'     => (bool) $settings['show_timer'],
    'shortcuts'     => (bool) $settings['keyboard_shortcuts'],
    'filters'       => [
        'subject_id' => $filters['subject_id'],
        'topic_id'   => $filters['topic_id'],
    ],
    'urls' => [
        'session'   => site_url('flashcards/api/sessao'),
        'next'      => site_url('flashcards/api/sessao/__UUID__/proximo'),
        'review'    => site_url('flashcards/api/sessao/__UUID__/avaliar'),
        'undo'      => site_url('flashcards/api/sessao/__UUID__/desfazer'),
        'finish'    => site_url('flashcards/api/sessao/__UUID__/encerrar'),
        'suspend'   => site_url('flashcards/api/cartoes/__ID__/suspender'),
        'cards'     => site_url('flashcards/cartoes'),
        'dashboard' => site_url('flashcards'),
    ],
];
?>

<div class="review-shell" id="review-root" data-config="<?= esc(json_encode($config), 'attr') ?>">

    <header class="review-topbar">
        <div class="review-context" id="review-context">
            <strong><?= $subjectName !== '' ? esc($subjectName) : 'Todas as disciplinas' ?></strong>
            <span><?= $topicName !== '' ? esc($topicName) : 'Sessão de revisão' ?></span>
        </div>

        <div class="review-tools">
            <span class="chip" id="review-timer" hidden aria-label="Tempo de sessão">00:00</span>

            <div class="review-tools" id="review-tools" hidden>
                <button type="button" class="btn btn-sm btn-ghost" id="btn-undo" aria-label="Desfazer última resposta (tecla Z)" title="Desfazer (Z)">↶ <span class="fc-btn-label">Desfazer</span></button>
                <button type="button" class="btn btn-sm btn-ghost" id="btn-suspend" aria-label="Suspender cartão (tecla S)" title="Suspender (S)">⏸ <span class="fc-btn-label">Suspender</span></button>
                <button type="button" class="btn btn-sm btn-ghost" id="btn-finish" aria-label="Encerrar sessão (tecla Esc)" title="Encerrar (Esc)">✕ <span class="fc-btn-label">Encerrar</span></button>
            </div>

            <div class="review-progress">
                <span class="review-progress-label" id="review-progress-label">0 de <?= (int) $counts['total'] ?></span>
                <div class="review-progress-track" role="progressbar" aria-label="Progresso da sessão">
                    <div class="review-progress-fill" id="review-progress-fill" style="width:0"></div>
                </div>
            </div>
        </div>
    </header>

    <div class="review-stage" id="review-stage">
        <?php if ($counts['total'] === 0): ?>
            <article class="flashcard">
                <div class="review-done">
                    <h2>Nada para revisar agora</h2>
                    <p class="subtitle">Você está em dia. Novos cartões e revisões aparecem conforme o agendamento do FSRS.</p>
                    <div class="flex gap-1 mt-3" style="justify-content:center">
                        <a class="btn btn-ghost" href="<?= site_url('flashcards') ?>">Voltar ao painel</a>
                        <?php if (user_can(\App\Services\Auth\Permissions::FLASHCARDS_IA)): ?>
                            <a class="btn btn-primary" href="<?= site_url('flashcards/gerar') ?>">Gerar novos cartões</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php else: ?>
            <article class="flashcard" id="review-intro">
                <div class="review-done">
                    <h2>Pronto para revisar?</h2>
                    <p class="subtitle">
                        <?= (int) $counts['total'] ?> cartão(ões) na fila —
                        <?= (int) $counts['new'] ?> novos, <?= (int) $counts['learning'] ?> em aprendizado
                        e <?= (int) $counts['review'] ?> revisões.
                    </p>
                    <div class="flex gap-1 mt-3" style="justify-content:center">
                        <button type="button" class="btn btn-primary btn-lg" id="review-start">Começar sessão</button>
                    </div>
                    <?php if ($settings['keyboard_shortcuts']): ?>
                        <p class="text-small text-muted mt-3">
                            Atalhos: <b>Espaço</b> revela a resposta · <b>1</b> Não lembrei ·
                            <b>2</b> Difícil · <b>3</b> Bom · <b>4</b> Fácil ·
                            <b>Z</b> desfazer · <b>S</b> suspender · <b>Esc</b> encerrar
                        </p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endif; ?>
    </div>

    <div class="review-counters" id="review-counters">
        <span class="review-counter counter-new">Novos: <b><?= (int) $counts['new'] ?></b></span>
        <span class="review-counter counter-learning">Aprendendo: <b><?= (int) $counts['learning'] ?></b></span>
        <span class="review-counter counter-review">Revisões: <b><?= (int) $counts['review'] ?></b></span>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_v('assets/js/flashcards-review.js') ?>"></script>
<?= $this->endSection() ?>
