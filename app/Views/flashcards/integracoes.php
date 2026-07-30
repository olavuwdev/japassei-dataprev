<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Integrações e API<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Integrações e API<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_v('assets/css/flashcards.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$statusChips = [
    'processing' => ['Processando', 'chip-gold'],
    'done'       => ['Concluída', 'chip-primary'],
    'partial'    => ['Concluída com alertas', 'chip-gold'],
    'error'      => ['Erro', 'chip-danger'],
];
?>

<div class="page-header">
    <div>
        <h1>Integrações e API</h1>
        <p class="subtitle">Gere um token e peça ao ChatGPT, Gemini ou outra IA que envie os flashcards direto para cá.</p>
    </div>
    <div class="flex gap-1">
        <button type="button" class="btn btn-primary" id="btn-new-token">🔑 Gerar novo token</button>
        <a class="btn btn-ghost" href="<?= site_url('flashcards/integracoes/documentacao') ?>">📖 Documentação</a>
    </div>
</div>

<?= $this->include('flashcards/_tabs') ?>

<div class="card">
    <div class="card-header">
        <h3>🔑 Tokens de integração</h3>
        <span class="chip"><?= count($tokens) ?></span>
    </div>

    <div class="field">
        <label class="field-label" for="api-endpoint">Endereço da API</label>
        <div class="flex gap-1">
            <input type="text" id="api-endpoint" value="<?= esc($endpoint) ?>" readonly style="flex:1">
            <button type="button" class="btn btn-ghost" data-copy="#api-endpoint">Copiar</button>
        </div>
    </div>

    <?php if ($tokens === []): ?>
        <div class="empty-state">
            <span class="empty-state-icon">🔐</span>
            <p>Nenhum token criado. Gere um para permitir que uma IA externa cadastre flashcards na sua conta.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Token</th>
                        <th>Permissões</th>
                        <th>Aprovação</th>
                        <th>Requisições</th>
                        <th>Último uso</th>
                        <th>Situação</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tokens as $token): ?>
                    <tr>
                        <td><?= esc($token['name']) ?></td>
                        <td><span class="token-value"><?= esc($token['masked']) ?></span></td>
                        <td class="text-small">
                            <?php foreach ($token['scopes'] as $scope): ?>
                                <span class="chip"><?= esc($scopes[$scope] ?? $scope) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="text-small"><?= $token['requires_approval'] ? 'Exige aprovação' : 'Cadastro direto' ?></td>
                        <td><?= (int) $token['request_count'] ?></td>
                        <td class="text-small text-muted">
                            <?= $token['last_used_at'] ? date('d/m/Y H:i', strtotime((string) $token['last_used_at'] . ' UTC')) : 'nunca' ?>
                        </td>
                        <td>
                            <span class="chip <?= $token['active'] ? 'chip-primary' : 'chip-danger' ?>">
                                <?= $token['active'] ? 'Ativo' : 'Revogado' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($token['active']): ?>
                                <button type="button" class="btn btn-sm btn-danger" data-revoke="<?= esc($token['uuid']) ?>">Revogar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card mt-3">
    <div class="card-header"><h3>📋 Instruções prontas para colar na IA</h3></div>

    <p class="text-small text-muted">
        Copie o bloco abaixo, substitua <code>SEU_TOKEN</code> pelo token gerado e envie ao ChatGPT, Gemini
        ou a qualquer assistente capaz de fazer requisições HTTP.
    </p>

    <div class="code-block">
        <button type="button" class="btn btn-sm btn-ghost code-copy" data-copy="#copy-instructions">Copiar</button>
        <pre id="copy-instructions">Ao gerar os flashcards, envie-os para:

POST <?= esc($endpoint) . "\n" ?>

Autenticação:
Authorization: Bearer SEU_TOKEN
Content-Type: application/json
Idempotency-Key: um-identificador-unico-para-este-lote

Formato do corpo:
{
  "external_id": "identificador-unico-do-lote",
  "source": { "type": "external_ai", "provider": "chatgpt", "title": "Tema estudado" },
  "discipline": { "name": "Direito Administrativo", "create_if_not_exists": true },
  "category":   { "name": "Administração Pública", "create_if_not_exists": true },
  "subject":    { "name": "Princípios", "create_if_not_exists": true },
  "settings":   { "send_to_review": true, "requires_approval": false, "prevent_duplicates": true },
  "cards": [
    {
      "external_id": "card-001",
      "type": "basic",
      "question": "Qual princípio exige a divulgação dos atos administrativos?",
      "answer": "Princípio da publicidade.",
      "explanation": "Permite conhecimento e fiscalização dos atos públicos.",
      "source_excerpt": "Trecho do material que fundamenta o cartão.",
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
}

Regras ao montar os cartões:
- Cada cartão deve testar apenas uma informação principal.
- Não invente fatos ausentes no material estudado.
- Preserve números, datas, exceções e termos técnicos.
- Use cloze somente quando a frase continuar compreensível.
- Máximo de <?= (int) $limits->apiMaxCardsPerRequest ?> cartões por requisição.
- Caso a disciplina, categoria ou assunto ainda não exista, envie create_if_not_exists como true.</pre>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>📥 Importações recebidas</h3>
        <a class="btn btn-sm btn-ghost" href="<?= site_url('flashcards/importacoes') ?>">Ver pendentes de aprovação</a>
    </div>

    <?php if ($imports === []): ?>
        <div class="empty-state"><p>Nenhuma importação recebida ainda.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Quando</th>
                        <th>Origem</th>
                        <th>Recebidos</th>
                        <th>Criados</th>
                        <th>Duplicados</th>
                        <th>Rejeitados</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($imports as $import): ?>
                    <?php $chip = $statusChips[$import['status']] ?? [$import['status'], 'chip']; ?>
                    <tr>
                        <td class="text-small text-muted">
                            <?= date('d/m/Y H:i', strtotime((string) $import['created_at'] . ' UTC')) ?>
                        </td>
                        <td><span class="chip"><?= esc($import['provider'] ?? '—') ?></span></td>
                        <td><?= (int) $import['received_count'] ?></td>
                        <td><?= (int) $import['created_count'] ?></td>
                        <td><?= (int) $import['duplicate_count'] ?></td>
                        <td><?= (int) $import['rejected_count'] ?></td>
                        <td>
                            <span class="chip <?= $chip[1] ?>"><?= esc($chip[0]) ?></span>
                            <?php if ($import['error_message']): ?>
                                <br><span class="text-small" style="color:var(--danger)"><?= esc(mb_strimwidth((string) $import['error_message'], 0, 120, '…')) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script type="application/json" id="token-scopes"><?= json_encode($scopes, JSON_UNESCAPED_UNICODE) ?></script>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_v('assets/js/flashcards-integracoes.js') ?>"></script>
<?= $this->endSection() ?>
