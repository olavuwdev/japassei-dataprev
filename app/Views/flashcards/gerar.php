<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Gerar com IA<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Gerar flashcards com IA<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/flashcards.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>Gerar com IA</h1>
        <p class="subtitle">A IA sugere os cartões; você aprova. Nada entra na fila de estudos sem sua validação.</p>
    </div>
</div>

<?= $this->include('flashcards/_tabs') ?>

<?php if (! $enabled): ?>
    <div class="card">
        <div class="empty-state">
            <span class="empty-state-icon">🔒</span>
            <h3>Integração com a OpenAI não configurada</h3>
            <p>Defina <code>OPENAI_API_KEY</code> no arquivo <code>.env</code> do servidor para habilitar a geração automática.</p>
            <p class="text-small text-muted">Você ainda pode criar cartões manualmente ou importá-los pela API externa.</p>
            <div class="flex gap-1 mt-2" style="justify-content:center">
                <a class="btn btn-primary" href="<?= site_url('flashcards/cartoes') ?>">Criar manualmente</a>
                <a class="btn btn-ghost" href="<?= site_url('flashcards/integracoes') ?>">Usar a API externa</a>
            </div>
        </div>
    </div>
<?php else: ?>

<div class="grid grid-2" id="ai-root"
     data-urls="<?= esc(json_encode([
         'create'  => site_url('flashcards/api/gerar'),
         'process' => site_url('flashcards/api/gerar/__UUID__/processar'),
         'result'  => site_url('flashcards/api/gerar/__UUID__/resultado'),
         'approve' => site_url('flashcards/api/gerar/__UUID__/aprovar'),
         'reject'  => site_url('flashcards/api/gerar/__UUID__/descartar'),
         'retry'   => site_url('flashcards/api/gerar/__UUID__/reprocessar'),
         'cards'   => site_url('flashcards/cartoes'),
     ]), 'attr') ?>"
     data-job="<?= esc($_GET['job'] ?? '') ?>">

    <div class="card" id="ai-form-card">
        <div class="card-header">
            <h3>1. Origem do conteúdo</h3>
            <span class="chip"><?= (int) $remaining ?> gerações hoje</span>
        </div>

        <div class="field">
            <label class="field-label" for="ai-source-type">Como você quer enviar o conteúdo?</label>
            <select id="ai-source-type">
                <option value="text">Colar texto ou escrever resumo</option>
                <option value="url">Informar um link público</option>
            </select>
        </div>

        <div class="field" id="ai-url-field" hidden>
            <label class="field-label" for="ai-url">Endereço da página</label>
            <input type="url" id="ai-url" placeholder="https://exemplo.com/material">
            <span class="text-small text-muted">O sistema busca e limpa o conteúdo antes de enviá-lo à IA. Endereços internos são bloqueados.</span>
        </div>

        <div class="field" id="ai-content-field">
            <label class="field-label" for="ai-content">Conteúdo</label>
            <textarea id="ai-content" rows="10" placeholder="Cole aqui o texto estudado…"></textarea>
            <span class="text-small text-muted">Limite de <?= number_format($maxCards) ?> cartões por geração.</span>
        </div>

        <div class="field">
            <label class="field-label" for="ai-title">Título da fonte</label>
            <input type="text" id="ai-title" placeholder="Ex.: Princípios da Administração Pública">
        </div>

        <div class="form-row">
            <div class="field">
                <label class="field-label" for="ai-subject">Disciplina</label>
                <select id="ai-subject">
                    <option value="">Sem disciplina</option>
                    <?php foreach ($taxonomy['subjects'] as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>"><?= esc($subject['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="ai-topic">Assunto</label>
                <select id="ai-topic">
                    <option value="">Sem assunto</option>
                    <?php foreach ($taxonomy['topics'] as $topic): ?>
                        <option value="<?= (int) $topic['id'] ?>" data-subject="<?= (int) $topic['subject_id'] ?>">
                            <?= esc($topic['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="field">
                <label class="field-label" for="ai-quantity">Quantidade</label>
                <select id="ai-quantity">
                    <option value="">Automática</option>
                    <option value="5">5 cartões</option>
                    <option value="10">10 cartões</option>
                    <option value="15">15 cartões</option>
                    <option value="20">20 cartões</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="ai-depth">Profundidade</label>
                <select id="ai-depth">
                    <option value="essential">Essencial — só o fundamental</option>
                    <option value="balanced" selected>Equilibrada — conceitos, relações e exceções</option>
                    <option value="detailed">Detalhada — inclui classificações e condições</option>
                </select>
            </div>
        </div>

        <fieldset class="field">
            <legend class="field-label">Tipos de cartão desejados</legend>
            <label class="checkbox-row"><input type="checkbox" name="ai-type" value="basic" checked> Básico</label>
            <label class="checkbox-row"><input type="checkbox" name="ai-type" value="cloze" checked> Completar lacuna</label>
            <label class="checkbox-row"><input type="checkbox" name="ai-type" value="typed_answer"> Resposta digitada</label>
        </fieldset>

        <button type="button" class="btn btn-primary btn-block" id="ai-generate">✨ Gerar flashcards</button>
    </div>

    <div class="card" id="ai-status-card">
        <div class="card-header"><h3>2. Processamento</h3></div>

        <div class="ai-steps" id="ai-steps">
            <div class="ai-step" data-step="fetch"><span class="ai-step-icon">1</span> Obtendo o conteúdo</div>
            <div class="ai-step" data-step="clean"><span class="ai-step-icon">2</span> Limpando o texto</div>
            <div class="ai-step" data-step="concepts"><span class="ai-step-icon">3</span> Identificando os conceitos</div>
            <div class="ai-step" data-step="generating"><span class="ai-step-icon">4</span> Gerando os flashcards</div>
            <div class="ai-step" data-step="validating"><span class="ai-step-icon">5</span> Validando o resultado</div>
        </div>

        <div id="ai-status-message" class="text-muted text-small">Preencha o formulário e clique em “Gerar flashcards”.</div>
    </div>
</div>

<div class="card mt-3" id="ai-result-card" hidden>
    <div class="card-header">
        <h3>3. Revisar e aprovar</h3>
        <span class="chip" id="ai-result-count">0</span>
    </div>

    <div class="flex gap-1 flex-wrap mb-2">
        <button type="button" class="btn btn-sm btn-ghost" id="ai-select-all">Selecionar todos</button>
        <button type="button" class="btn btn-sm btn-ghost" id="ai-select-none">Limpar seleção</button>
        <button type="button" class="btn btn-sm btn-ghost" id="ai-regenerate">Gerar novamente</button>
    </div>

    <div id="ai-warnings"></div>
    <div class="card-list" id="ai-suggestions"></div>

    <div class="flex gap-1 mt-3 flex-wrap">
        <button type="button" class="btn btn-primary" id="ai-approve">Salvar cartões aprovados</button>
        <button type="button" class="btn btn-ghost" id="ai-discard">Descartar selecionados</button>
        <a class="btn btn-ghost" href="<?= site_url('flashcards/cartoes') ?>">Cancelar</a>
    </div>
</div>

<script type="application/json" id="taxonomy-data">
    <?= json_encode([
        'subjects' => array_map(static fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name']], $taxonomy['subjects']),
        'topics'   => array_map(static fn ($t) => ['id' => (int) $t['id'], 'subject_id' => (int) $t['subject_id'], 'name' => $t['name']], $taxonomy['topics']),
        'types'    => \App\Models\FlashcardModel::TYPE_LABELS,
    ], JSON_UNESCAPED_UNICODE) ?>
</script>

<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($enabled): ?>
<script src="<?= base_url('assets/js/flashcards-ai.js') ?>"></script>
<?php endif; ?>
<?= $this->endSection() ?>
