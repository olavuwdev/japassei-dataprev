<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Configurações dos flashcards<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Configurações<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_v('assets/css/flashcards.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$learning   = json_decode((string) $settings['learning_steps'], true) ?: ['1m', '10m'];
$relearning = json_decode((string) $settings['relearning_steps'], true) ?: ['10m'];
?>

<div class="page-header">
    <div>
        <h1>Configurações</h1>
        <p class="subtitle">Ajuste o ritmo das suas revisões. O FSRS usa esses parâmetros para calcular cada intervalo.</p>
    </div>
</div>

<?= $this->include('flashcards/_tabs') ?>

<form method="post" action="<?= site_url('flashcards/configuracoes') ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header"><h3>⚙️ Gerais</h3></div>

        <div class="form-row">
            <div class="field">
                <label class="field-label" for="new_per_day">Cartões novos por dia</label>
                <input type="number" id="new_per_day" name="new_per_day" min="0" max="9999"
                       value="<?= (int) $settings['new_per_day'] ?>">
            </div>
            <div class="field">
                <label class="field-label" for="reviews_per_day">Máximo de revisões por dia</label>
                <input type="number" id="reviews_per_day" name="reviews_per_day" min="1" max="9999"
                       value="<?= (int) $settings['reviews_per_day'] ?>">
            </div>
            <div class="field">
                <label class="field-label" for="request_retention">Retenção desejada</label>
                <input type="number" id="request_retention" name="request_retention" step="0.01" min="0.80" max="0.95"
                       value="<?= number_format((float) $settings['request_retention'], 2, '.', '') ?>">
                <span class="text-small text-muted">
                    Entre 0,80 e 0,95. O padrão 0,90 equilibra retenção e carga —
                    valores maiores encurtam os intervalos e aumentam muito o número de revisões.
                </span>
            </div>
        </div>

        <label class="checkbox-row">
            <input type="checkbox" name="show_intervals" value="1" <?= $settings['show_intervals'] ? 'checked' : '' ?>>
            Mostrar os intervalos previstos nos botões de avaliação
        </label>
        <label class="checkbox-row">
            <input type="checkbox" name="show_timer" value="1" <?= $settings['show_timer'] ? 'checked' : '' ?>>
            Mostrar cronômetro durante a sessão
        </label>
        <label class="checkbox-row">
            <input type="checkbox" name="keyboard_shortcuts" value="1" <?= $settings['keyboard_shortcuts'] ? 'checked' : '' ?>>
            Ativar atalhos de teclado (Espaço, 1–4, Z, S, Esc)
        </label>
        <label class="checkbox-row">
            <input type="checkbox" name="shuffle_cards" value="1" <?= $settings['shuffle_cards'] ? 'checked' : '' ?>>
            Embaralhar cartões equivalentes
        </label>
        <label class="checkbox-row">
            <input type="checkbox" name="flip_animation" value="1" <?= $settings['flip_animation'] ? 'checked' : '' ?>>
            Animação de virar o cartão (desativada por padrão)
        </label>
    </div>

    <div class="card mt-2">
        <div class="card-header"><h3>🔬 Avançadas</h3></div>

        <div class="form-row">
            <div class="field">
                <label class="field-label" for="learning_steps">Etapas de aprendizado</label>
                <input type="text" id="learning_steps" name="learning_steps" value="<?= esc(implode(', ', $learning)) ?>">
                <span class="text-small text-muted">Ex.: 1m, 10m. Use m (minutos), h (horas) ou d (dias).</span>
            </div>
            <div class="field">
                <label class="field-label" for="relearning_steps">Etapas de reaprendizado</label>
                <input type="text" id="relearning_steps" name="relearning_steps" value="<?= esc(implode(', ', $relearning)) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="maximum_interval">Intervalo máximo (dias)</label>
                <input type="number" id="maximum_interval" name="maximum_interval" min="1" max="36500"
                       value="<?= (int) $settings['maximum_interval'] ?>">
            </div>
            <div class="field">
                <label class="field-label" for="backlog_threshold">Pausar novos com acúmulo de</label>
                <input type="number" id="backlog_threshold" name="backlog_threshold" min="0" max="9999"
                       value="<?= (int) $settings['backlog_threshold'] ?>">
                <span class="text-small text-muted">Revisões atrasadas acima disso suspendem a entrada de cartões novos. 0 desativa.</span>
            </div>
        </div>

        <label class="checkbox-row">
            <input type="checkbox" name="bury_siblings" value="1" <?= $settings['bury_siblings'] ? 'checked' : '' ?>>
            Evitar cartões irmãos (da mesma anotação) em sequência
        </label>
        <label class="checkbox-row">
            <input type="checkbox" name="enable_fuzz" value="1" <?= $settings['enable_fuzz'] ? 'checked' : '' ?>>
            Variação aleatória nos intervalos (evita acúmulos no mesmo dia)
        </label>
        <label class="checkbox-row">
            <input type="checkbox" name="enable_short_term" value="1" <?= $settings['enable_short_term'] ? 'checked' : '' ?>>
            Agendamento de curto prazo (etapas em minutos)
        </label>
    </div>

    <div class="flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary">Salvar configurações</button>
    </div>
</form>

<div class="card mt-3">
    <div class="card-header">
        <h3>🧠 Serviço FSRS</h3>
        <span class="chip <?= $fsrs['online'] ? 'chip-primary' : 'chip-danger' ?>">
            <?= $fsrs['online'] ? 'Online' : 'Indisponível' ?>
        </span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><th>Endereço interno</th><td><code><?= esc($fsrs['url']) ?></code></td></tr>
                <tr><th>Token interno</th><td><?= $fsrs['token_set'] ? 'Configurado' : '<span style="color:var(--danger)">Não configurado</span>' ?></td></tr>
                <tr><th>Versão da biblioteca</th><td><?= esc($fsrs['version'] ?? '—') ?></td></tr>
                <?php if (! $fsrs['online']): ?>
                    <tr><th>Diagnóstico</th><td style="color:var(--danger)"><?= esc($fsrs['message'] ?? 'Sem resposta.') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <button type="button" class="btn btn-ghost btn-sm mt-2" id="btn-test-fsrs">Testar conexão</button>

    <p class="text-small text-muted mt-2">
        Sem este serviço as avaliações não podem ser registradas: o sistema nunca calcula intervalos por conta própria.
        As instruções de implantação estão em <code>fsrs-service/README.md</code>.
    </p>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>🤖 OpenAI</h3>
        <span class="chip <?= $openai['enabled'] ? 'chip-primary' : 'chip' ?>">
            <?= $openai['enabled'] ? 'Ativa' : 'Desativada' ?>
        </span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><th>Chave configurada</th><td><code><?= esc($openai['key']) ?></code></td></tr>
                <tr><th>Modelo principal</th><td><?= esc($openai['model']) ?></td></tr>
                <tr><th>Modelo alternativo</th><td><?= esc($openai['fallback']) ?></td></tr>
                <tr><th>Timeout / tentativas</th><td><?= (int) $openai['timeout'] ?>s · <?= (int) $openai['attempts'] ?> tentativas</td></tr>
                <tr><th>Limite por geração</th><td><?= (int) $openai['maxCards'] ?> cartões · <?= number_format($openai['maxChars']) ?> caracteres</td></tr>
                <tr><th>Limite diário por usuário</th><td><?= (int) $openai['daily'] ?> gerações</td></tr>
                <tr><th>Custo do mês</th>
                    <td>
                        US$ <?= number_format($openai['spent'], 4) ?>
                        <?= $openai['monthly'] > 0 ? ' de US$ ' . number_format($openai['monthly'], 2) : ' (sem teto definido)' ?>
                    </td>
                </tr>
                <tr><th>Versão do prompt / schema</th><td><?= esc($openai['prompt']) ?> / <?= esc($openai['schema']) ?></td></tr>
            </tbody>
        </table>
    </div>

    <p class="text-small text-muted mt-2">
        A chave existe apenas no servidor, em variável de ambiente, e nunca é enviada ao navegador nem gravada em log.
        Para alterá-la, edite <code>OPENAI_API_KEY</code> no <code>.env</code>.
    </p>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var button = document.getElementById('btn-test-fsrs');
    if (!button) { return; }

    button.addEventListener('click', async function () {
        button.disabled = true;
        var payload = await JP.api('<?= site_url('flashcards/api/fsrs/testar') ?>', { method: 'POST', body: {} });
        button.disabled = false;

        JP.toast(payload.message || 'Sem resposta.', payload.ok ? 'success' : 'error', 6000);
    });
})();
</script>
<?= $this->endSection() ?>
