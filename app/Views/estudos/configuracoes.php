<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Configurações<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Configurações<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$weekdayLabels = [
    1 => 'Segunda',
    2 => 'Terça',
    3 => 'Quarta',
    4 => 'Quinta',
    5 => 'Sexta',
    6 => 'Sábado',
    7 => 'Domingo',
];

$oldWeekdays = old('study_weekdays');
$checkedDays = $oldWeekdays !== null ? array_map('intval', (array) $oldWeekdays) : $weekdays;

$goalValue = old('daily_goal_minutes', $settings['daily_goal_minutes'] ?? 60);
$tzValue   = old('timezone', $settings['timezone'] ?? 'America/Fortaleza');

$autoComplete  = old('auto_complete_tasks') !== null
    ? true
    : (old('daily_goal_minutes') !== null ? false : ! empty($settings['auto_complete_tasks']));
$notifications = old('notifications_enabled') !== null
    ? true
    : (old('daily_goal_minutes') !== null ? false : (! isset($settings['notifications_enabled']) || ! empty($settings['notifications_enabled'])));
?>

<div class="page-header">
    <div>
        <h1>Configurações</h1>
        <p class="subtitle">Ajuste sua rotina de estudos do seu jeito.</p>
    </div>
</div>

<?php if ($plan !== null): ?>
    <div class="card">
        <div class="card-header"><h3>🗓️ Plano ativo</h3></div>
        <div class="grid grid-3">
            <div class="stat">
                <span class="stat-label">Plano</span>
                <span class="stat-value" style="font-size: 1.05rem;"><?= esc($plan['name']) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Início</span>
                <span class="stat-value" style="font-size: 1.05rem;"><?= esc(date('d/m/Y', strtotime($plan['start_date']))) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Término</span>
                <span class="stat-value" style="font-size: 1.05rem;">
                    <?= ! empty($plan['end_date']) ? esc(date('d/m/Y', strtotime($plan['end_date']))) : '—' ?>
                </span>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>⚙️ Preferências de estudo</h3></div>

    <form method="post" action="<?= site_url('estudos/configuracoes') ?>">
        <?= csrf_field() ?>

        <div class="field">
            <label for="c-goal">Meta diária (minutos)</label>
            <input type="number" id="c-goal" name="daily_goal_minutes" min="15" max="480"
                   value="<?= esc($goalValue, 'attr') ?>" required>
            <div class="text-small text-faint mt-1">Entre 15 e 480 minutos. O padrão do plano DATAPREV é 60 minutos.</div>
        </div>

        <div class="field">
            <label>Dias de estudo</label>
            <div class="flex flex-wrap gap-1">
                <?php foreach ($weekdayLabels as $number => $label): ?>
                    <label class="checkbox-row" style="border: 1px solid var(--border); border-radius: 10px; padding: 8px 12px;">
                        <input type="checkbox" name="study_weekdays[]" value="<?= esc($number, 'attr') ?>"
                            <?= in_array($number, $checkedDays, true) ? 'checked' : '' ?>>
                        <?= esc($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="text-small text-faint mt-1">Somente os dias marcados contam para a ofensiva. Os demais aceitam sessões extras.</div>
        </div>

        <div class="field">
            <label>Intervalos de revisão (dias)</label>
            <div class="form-row">
                <div class="field mb-0">
                    <label for="c-int1" class="text-small">1ª revisão</label>
                    <input type="number" id="c-int1" name="review_interval_1" min="1"
                           value="<?= esc(old('review_interval_1', $intervals[0]), 'attr') ?>" required>
                </div>
                <div class="field mb-0">
                    <label for="c-int2" class="text-small">2ª revisão</label>
                    <input type="number" id="c-int2" name="review_interval_2" min="1"
                           value="<?= esc(old('review_interval_2', $intervals[1]), 'attr') ?>" required>
                </div>
                <div class="field mb-0">
                    <label for="c-int3" class="text-small">3ª revisão</label>
                    <input type="number" id="c-int3" name="review_interval_3" min="1"
                           value="<?= esc(old('review_interval_3', $intervals[2]), 'attr') ?>" required>
                </div>
            </div>
            <div class="text-small text-faint mt-1">Os valores devem ser crescentes (ex.: 1, 7 e 30). Vale para as próximas revisões geradas.</div>
        </div>

        <div class="field">
            <label class="checkbox-row">
                <input type="checkbox" name="auto_complete_tasks" value="1" <?= $autoComplete ? 'checked' : '' ?>>
                Concluir tarefas automaticamente ao completar o checklist obrigatório
            </label>
        </div>

        <div class="field">
            <label class="checkbox-row">
                <input type="checkbox" name="notifications_enabled" value="1" <?= $notifications ? 'checked' : '' ?>>
                Ativar notificações e lembretes
            </label>
        </div>

        <div class="field">
            <label for="c-tz">Fuso horário</label>
            <select id="c-tz" name="timezone">
                <?php foreach ($timezones as $tz => $label): ?>
                    <option value="<?= esc($tz, 'attr') ?>" <?= $tzValue === $tz ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Salvar configurações</button>
    </form>
</div>
<?= $this->endSection() ?>
