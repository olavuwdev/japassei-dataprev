<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Documentação da API<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Documentação da API<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/flashcards.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="page-header">
    <div>
        <h1>API de importação de flashcards</h1>
        <p class="subtitle">Cadastre cartões gerados fora do sistema por qualquer cliente HTTP.</p>
    </div>
    <a class="btn btn-ghost" href="<?= site_url('flashcards/integracoes') ?>">← Voltar às integrações</a>
</div>

<div class="card">
    <div class="card-header"><h3>Endpoint principal</h3></div>
    <div class="code-block">
        <button type="button" class="btn btn-sm btn-ghost code-copy" data-copy="#doc-endpoint">Copiar</button>
        <pre id="doc-endpoint">POST <?= esc($endpoint) ?>

Authorization: Bearer SEU_TOKEN
Content-Type: application/json
Idempotency-Key: identificador-unico-do-lote</pre>
    </div>

    <p class="text-small text-muted mt-2">
        O cabeçalho <code>Idempotency-Key</code> evita que o mesmo lote seja cadastrado duas vezes caso a
        requisição seja repetida por falha de rede.
    </p>
</div>

<div class="card mt-3">
    <div class="card-header"><h3>Corpo completo</h3></div>
    <div class="code-block">
        <button type="button" class="btn btn-sm btn-ghost code-copy" data-copy="#doc-full">Copiar</button>
        <pre id="doc-full">{
  "external_id": "chatgpt-2026-07-29-direito-administrativo-01",
  "atomic": false,
  "source": {
    "type": "external_ai",
    "provider": "chatgpt",
    "title": "Princípios da Administração Pública",
    "url": "https://exemplo.com/material-estudado",
    "content_summary": "Resumo opcional do conteúdo analisado"
  },
  "discipline": { "name": "Direito Administrativo", "create_if_not_exists": true },
  "category":   { "name": "Administração Pública",  "create_if_not_exists": true },
  "subject":    { "name": "Princípios",             "create_if_not_exists": true },
  "settings": {
    "send_to_review": true,
    "requires_approval": false,
    "prevent_duplicates": true
  },
  "cards": [
    {
      "external_id": "card-001",
      "type": "basic",
      "question": "Qual princípio exige a divulgação dos atos administrativos?",
      "answer": "Princípio da publicidade.",
      "explanation": "Permite conhecimento e fiscalização dos atos públicos.",
      "source_excerpt": "A publicidade determina a divulgação oficial dos atos.",
      "difficulty": "medium",
      "tags": ["administracao-publica", "principios"]
    },
    {
      "external_id": "card-002",
      "type": "cloze",
      "text": "O princípio da {{c1::legalidade}} exige autorização legal para agir.",
      "difficulty": "medium"
    }
  ]
}</pre>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h3>Corpo simplificado</h3></div>
    <p class="text-small text-muted">Aceito para facilitar a configuração em assistentes com menos flexibilidade.</p>
    <div class="code-block">
        <button type="button" class="btn btn-sm btn-ghost code-copy" data-copy="#doc-simple">Copiar</button>
        <pre id="doc-simple">{
  "discipline": "Direito Administrativo",
  "category": "Administração Pública",
  "subject": "Princípios da Administração Pública",
  "create_missing_categories": true,
  "cards": [
    {
      "question": "Qual princípio exige a divulgação dos atos administrativos?",
      "answer": "Princípio da publicidade."
    }
  ]
}</pre>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h3>Exemplo com cURL</h3></div>
    <div class="code-block">
        <button type="button" class="btn btn-sm btn-ghost code-copy" data-copy="#doc-curl">Copiar</button>
        <pre id="doc-curl">curl --request POST \
  --url <?= esc($endpoint) ?> \
  --header "Authorization: Bearer SEU_TOKEN" \
  --header "Content-Type: application/json" \
  --header "Idempotency-Key: estudo-direito-administrativo-20260729" \
  --data '{
    "discipline": { "name": "Direito Administrativo", "create_if_not_exists": true },
    "subject": { "name": "Princípios", "create_if_not_exists": true },
    "settings": { "send_to_review": true, "prevent_duplicates": true },
    "cards": [
      {
        "external_id": "card-001",
        "type": "basic",
        "question": "Qual princípio exige a divulgação dos atos administrativos?",
        "answer": "Princípio da publicidade."
      }
    ]
  }'</pre>
    </div>
</div>

<div class="grid grid-2 mt-3">
    <div class="card">
        <div class="card-header"><h3>Campos obrigatórios</h3></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tipo</th><th>Campos mínimos</th></tr></thead>
                <tbody>
                    <tr><td><code>basic</code></td><td><code>question</code>, <code>answer</code></td></tr>
                    <tr><td><code>typed_answer</code></td><td><code>question</code>, <code>answer</code></td></tr>
                    <tr><td><code>cloze</code></td><td><code>text</code> com ao menos uma <code>{{c1::resposta}}</code></td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-small text-muted mt-2">
            Cada lacuna numerada de um cloze vira um cartão independente.
        </p>
    </div>

    <div class="card">
        <div class="card-header"><h3>Limites</h3></div>
        <div class="table-wrap">
            <table class="table">
                <tbody>
                    <tr><th>Cartões por requisição</th><td><?= (int) $limits->apiMaxCardsPerRequest ?></td></tr>
                    <tr><th>Caracteres por pergunta</th><td><?= number_format($limits->apiMaxQuestionChars) ?></td></tr>
                    <tr><th>Caracteres por resposta</th><td><?= number_format($limits->apiMaxAnswerChars) ?></td></tr>
                    <tr><th>Tags por cartão</th><td><?= (int) $limits->apiMaxTagsPerCard ?></td></tr>
                    <tr><th>Requisições por minuto</th><td><?= (int) $limits->apiRequestsPerMinute ?></td></tr>
                    <tr><th>Importações por dia</th><td><?= (int) $limits->apiDailyImportLimit ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h3>Resposta de sucesso</h3></div>
    <div class="code-block">
        <pre>{
  "success": true,
  "message": "Importação concluída.",
  "import_id": "b3f1c9de-...",
  "discipline": { "id": 12, "name": "Direito Administrativo", "created": false },
  "category":   { "id": 28, "name": "Administração Pública",  "created": true },
  "subject":    { "id": 76, "name": "Princípios",             "created": true },
  "summary": {
    "received": 10, "created": 8, "duplicates": 2,
    "rejected": 0, "pending_approval": 0
  },
  "cards": [
    { "external_id": "card-001", "id": 845, "status": "created" }
  ]
}</pre>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h3>Códigos de resposta</h3></div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><td><code>200</code></td><td>Importação concluída</td></tr>
                <tr><td><code>201</code></td><td>Cartões criados</td></tr>
                <tr><td><code>207</code></td><td>Importação parcialmente concluída (veja <code>errors</code>)</td></tr>
                <tr><td><code>400</code></td><td>JSON inválido</td></tr>
                <tr><td><code>401</code></td><td>Token ausente, inválido, revogado ou expirado</td></tr>
                <tr><td><code>403</code></td><td>Token sem a permissão necessária</td></tr>
                <tr><td><code>404</code></td><td>Disciplina, categoria ou assunto não encontrado</td></tr>
                <tr><td><code>409</code></td><td>Importação duplicada (mesmo <code>Idempotency-Key</code> ou <code>external_id</code>)</td></tr>
                <tr><td><code>422</code></td><td>Erro de validação</td></tr>
                <tr><td><code>429</code></td><td>Limite de requisições excedido</td></tr>
                <tr><td><code>500</code></td><td>Erro interno</td></tr>
                <tr><td><code>503</code></td><td>Serviço temporariamente indisponível</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h3>Consultas auxiliares</h3></div>
    <div class="code-block">
        <pre>GET <?= esc($baseUrl) ?>/api/v1/disciplines
GET <?= esc($baseUrl) ?>/api/v1/categories?discipline_id=12
GET <?= esc($baseUrl) ?>/api/v1/subjects?discipline_id=12
GET <?= esc($baseUrl) ?>/api/v1/flashcards/imports/{import_id}</pre>
    </div>
    <p class="text-small text-muted mt-2">
        Use-as para reaproveitar nomes já cadastrados em vez de criar duplicatas.
        A comparação de nomes já ignora maiúsculas, acentuação e espaços repetidos.
    </p>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('click', async function (ev) {
    var button = ev.target.closest('[data-copy]');
    if (!button) { return; }

    var target = document.querySelector(button.dataset.copy);
    if (!target) { return; }

    try {
        await navigator.clipboard.writeText(target.textContent);
        JP.toast('Copiado!', 'success', 1600);
    } catch (e) {
        JP.toast('Selecione e copie manualmente.', 'error');
    }
});
</script>
<?= $this->endSection() ?>
