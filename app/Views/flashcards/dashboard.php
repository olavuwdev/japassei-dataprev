<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Flashcards<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Flashcards<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_v('assets/css/flashcards.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$hasCards = (int) $summary['total_cards'] > 0;
?>

<div class="page-header">
    <div>
        <h1>Flashcards</h1>
        <p class="subtitle">Transforme o que você estuda em memórias revisáveis. O agendamento é calculado pelo FSRS a partir das suas respostas.</p>
    </div>
    <div class="flex gap-1 flex-wrap">
        <a class="btn btn-flame" href="<?= site_url('flashcards/revisar') ?>">🔥 Revisar agora</a>
        <a class="btn btn-primary" href="<?= site_url('flashcards/cartoes') ?>">➕ Criar flashcard</a>
        <?php if (user_can(\App\Services\Auth\Permissions::FLASHCARDS_IA)): ?>
            <a class="btn btn-ghost" href="<?= site_url('flashcards/gerar') ?>">✨ Gerar com IA</a>
        <?php endif; ?>
    </div>
</div>

<?= $this->include('flashcards/_tabs') ?>

<?php if (! $hasCards): ?>
    <div class="card">
        <div class="empty-state">
            <span class="empty-state-icon">🃏</span>
            <h3>Transforme o que você estuda em conhecimento duradouro.</h3>
            <p>Crie seu primeiro flashcard manualmente ou utilize a inteligência artificial para gerar cartões a partir de um conteúdo.</p>
            <div class="flex gap-1 mt-2" style="justify-content:center">
                <a class="btn btn-primary" href="<?= site_url('flashcards/cartoes') ?>">Criar flashcard</a>
                <?php if (user_can(\App\Services\Auth\Permissions::FLASHCARDS_IA)): ?>
                    <a class="btn btn-ghost" href="<?= site_url('flashcards/gerar') ?>">Gerar com IA</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>

    <div class="grid grid-3 fc-stats-grid">
        <div class="stat">
            <span class="stat-value"><?= (int) $summary['due_reviews'] ?></span>
            <span class="stat-label">Revisões pendentes</span>
        </div>
        <div class="stat">
            <span class="stat-value"><?= (int) $summary['new_available'] ?></span>
            <span class="stat-label">Cartões novos disponíveis</span>
        </div>
        <div class="stat">
            <span class="stat-value"><?= (int) $summary['learning'] ?></span>
            <span class="stat-label">Em aprendizado</span>
        </div>
        <div class="stat">
            <span class="stat-value"><?= (int) $summary['reviewed_today'] ?></span>
            <span class="stat-label">Revisados hoje</span>
        </div>
        <div class="stat">
            <span class="stat-value"><?= (int) $summary['streak_days'] ?></span>
            <span class="stat-label">Dias seguidos</span>
        </div>
        <div class="stat">
            <span class="stat-value"><?= $summary['recall_rate'] === null ? '—' : $summary['recall_rate'] . '%' ?></span>
            <span class="stat-label">Lembrança (30 dias)</span>
            <span class="stat-hint">Respostas diferentes de “Não lembrei”</span>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h3>📚 Disciplinas</h3>
            <span class="chip"><?= count($subjects) ?></span>
        </div>

        <?php if ($subjects === []): ?>
            <div class="empty-state"><p>Nenhum cartão vinculado a disciplinas ainda.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Disciplina</th>
                            <th>Cartões</th>
                            <th>Pendentes</th>
                            <th class="hide-mobile">Novos</th>
                            <th class="hide-mobile">Retenção</th>
                            <th class="hide-mobile">Última revisão</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <td><?= esc($subject['subject_name']) ?></td>
                            <td><?= (int) $subject['total_cards'] ?></td>
                            <td><?= (int) $subject['due_cards'] ?></td>
                            <td class="hide-mobile"><?= (int) $subject['new_cards'] ?></td>
                            <td class="hide-mobile"><?= $subject['retention'] === null ? '—' : $subject['retention'] . '%' ?></td>
                            <td class="hide-mobile text-small text-muted">
                                <?= $subject['last_review'] ? date('d/m/Y', strtotime((string) $subject['last_review'] . ' UTC')) : '—' ?>
                            </td>
                            <td>
                                <?php if ((int) $subject['subject_id'] > 0): ?>
                                    <a class="btn btn-sm btn-ghost" href="<?= site_url('flashcards/revisar?disciplina=' . (int) $subject['subject_id']) ?>">Revisar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-2 mt-3">
        <div class="card">
            <div class="card-header"><h3>🕘 Atividade recente</h3></div>

            <?php if ($activity['cards'] === []): ?>
                <div class="empty-state"><p>Nenhum cartão criado recentemente.</p></div>
            <?php else: ?>
                <div class="card-list">
                    <?php foreach ($activity['cards'] as $card): ?>
                        <div class="card-row">
                            <div class="card-row-body">
                                <div class="card-row-front"><?= esc(mb_strimwidth(strip_tags((string) $card['front']), 0, 90, '…')) ?></div>
                                <div class="card-row-meta">
                                    <span class="chip chip-info"><?= esc(\App\Models\FlashcardModel::TYPE_LABELS[$card['card_type']] ?? $card['card_type']) ?></span>
                                    <?php if ($card['subject_name']): ?>
                                        <span class="chip"><?= esc($card['subject_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><h3>⚠️ Cartões que mais causam esquecimento</h3></div>

            <?php if ($activity['forgotten'] === []): ?>
                <div class="empty-state"><p>Nenhum cartão problemático identificado. Ótimo sinal!</p></div>
            <?php else: ?>
                <div class="card-list">
                    <?php foreach ($activity['forgotten'] as $card): ?>
                        <div class="card-row">
                            <div class="card-row-body">
                                <div class="card-row-front"><?= esc(mb_strimwidth(strip_tags((string) $card['front']), 0, 90, '…')) ?></div>
                                <div class="card-row-meta">
                                    <span class="chip chip-danger"><?= (int) $card['lapses'] ?> esquecimento(s)</span>
                                    <?php if ((int) $card['hard_count'] > 0): ?>
                                        <span class="chip chip-gold"><?= (int) $card['hard_count'] ?>× difícil</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a class="btn btn-ghost btn-sm mt-2" href="<?= site_url('flashcards/estatisticas') ?>">Ver todos</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><h3>📈 Revisões dos últimos 7 dias</h3></div>
        <div class="flex gap-2 flex-wrap">
            <?php foreach ($activity['week'] as $day): ?>
                <div class="stat" style="min-width:88px">
                    <span class="stat-value"><?= (int) $day['total'] ?></span>
                    <span class="stat-label"><?= date('d/m', strtotime($day['day'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php endif; ?>
<?= $this->endSection() ?>
