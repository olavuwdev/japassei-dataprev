# Módulo de Flashcards — documentação técnica

Implementação do PRD `docs/flashcards.plan`. Este documento registra o que foi
construído, onde está cada peça e como colocar em produção.

---

## 1. Decisões de adaptação ao sistema existente

O PRD sugere nomes e tecnologias genéricos. Foram adaptados ao padrão do repositório:

| PRD | Implementado | Motivo |
|-----|--------------|--------|
| Tabelas `est_flashcard_*` | `study_flashcard_*` / `study_flashcards` | O banco já usa prefixo `study_` e nomes em inglês. |
| Colunas `flc_*`, `not_*` | `id`, `user_id`, `front`, `back`… | Padrão do repositório: colunas sem prefixo, timestamps `created_at`/`updated_at`/`deleted_at`. |
| Frontend em Bootstrap | Design system próprio (`public/assets/css/app.css`) | O sistema não usa Bootstrap; o módulo estende a identidade existente em `flashcards.css`. |
| “Disciplina → Assunto” | `study_subjects` → `study_topics` | Cadastros já existentes, reaproveitados. |
| “Categoria” (API externa) | Tópico de primeiro nível (`study_topics.parent_id IS NULL`) | O sistema tem dois níveis; a categoria vira o nível intermediário. |

Tudo o mais segue o PRD literalmente, incluindo a proibição de calcular
intervalos em PHP.

---

## 2. Mapa dos arquivos

### Serviço FSRS (Node.js)

```
fsrs-service/
├── server.js            Servidor HTTP sem dependências, com token interno
├── src/fsrs.js          Camada sobre ts-fsrs (preview, review, rollback…)
├── test/smoke.test.js   10 testes (node:test)
├── package.json         ts-fsrs ^5.2, Node >= 20
└── README.md            Implantação na hospedagem Node da Hostinger
```

### Banco (migrations)

```
2026-07-29-120000  study_flashcard_sources          Fontes de estudo
2026-07-29-120001  study_flashcard_notes            Anotações (1 → N cartões)
2026-07-29-120002  study_flashcards                 Cartões
2026-07-29-120003  study_flashcard_states           Estado FSRS por cartão/usuário
2026-07-29-120004  study_flashcard_sessions         Sessões de revisão
2026-07-29-120005  study_flashcard_reviews          Log imutável das avaliações
2026-07-29-120006  study_flashcard_settings         Configurações do usuário
2026-07-29-120007  study_flashcard_ai_jobs          Jobs da OpenAI + consumo
2026-07-29-120008  study_flashcard_ai_suggestions   Sugestões antes da aprovação
2026-07-29-120009  study_flashcard_api_tokens       Tokens da API externa (hash)
2026-07-29-120010  study_flashcard_imports          Log das importações externas
2026-07-29-120011  study_flashcard_audit_logs       Eventos de auditoria
```

### Serviços (`app/Services/Flashcard/`)

| Classe | Responsabilidade |
|--------|------------------|
| `FlashcardService` | Criação, edição, suspensão, listagem, expansão de cloze/reverso |
| `FlashcardValidationService` | Validação, sanitização de HTML, parsing/render de cloze |
| `FlashcardDuplicateService` | Assinatura normalizada e detecção de duplicidades |
| `FlashcardQueueService` | Seleção e ordenação da fila (§18) |
| `FlashcardSessionService` | Sessão, avaliação idempotente, desfazer, resumo |
| `FsrsClientService` | Cliente do serviço Node — **único lugar que produz datas** |
| `ContentExtractorService` | Busca de URL com proteção SSRF, limpeza, divisão em blocos |
| `OpenAiFlashcardService` | Structured Outputs, prompt, tratamento de recusa/incompleto |
| `FlashcardAiService` | Orquestra fonte → job → sugestões → aprovação |
| `AiUsageService` | Limites diário e mensal de consumo |
| `FlashcardApiTokenService` | Emissão, verificação e revogação de tokens |
| `FlashcardApiImportService` | Importação externa com idempotência em 3 níveis |
| `FlashcardTaxonomyResolverService` | Resolve/cria disciplina, categoria e assunto |
| `FlashcardStatisticsService` | Dashboard, estatísticas, previsão, problemáticos |
| `FlashcardAuditService` | Registro dos eventos de auditoria |

### Telas

```
/flashcards                          Visão geral
/flashcards/revisar                  Sessão de revisão (tela principal)
/flashcards/cartoes                  Meus cartões (CRUD)
/flashcards/gerar                    Gerar com IA
/flashcards/fontes                   Fontes de estudo
/flashcards/historico                Histórico de revisões
/flashcards/estatisticas             Estatísticas e previsão de carga
/flashcards/integracoes              Tokens + instruções copiáveis
/flashcards/integracoes/documentacao Documentação da API
/flashcards/importacoes              Pendentes de aprovação
/flashcards/configuracoes            Configurações e diagnóstico
```

### Comandos

```bash
php spark flashcards:self-test            # verificação ponta a ponta (49 checagens)
php spark flashcards:token --user 1        # gera token da API externa
php spark flashcards:process-ai-jobs       # processa gerações pendentes
php spark flashcards:rebuild-fsrs --user 1 # reconstrói estados pelo histórico
php spark flashcards:detect-problematic    # sinaliza cartões problemáticos
php spark flashcards:cleanup-jobs          # limpa sugestões antigas
```

---

## 3. Implantação

### 3.1 Banco

```bash
php spark migrate
```

### 3.2 Serviço FSRS na hospedagem Node da Hostinger

1. Envie a pasta `fsrs-service/` para o servidor.
2. No painel **Node.js**: *application root* = `fsrs-service`,
   *startup file* = `server.js`, versão 20+.
3. Cadastre `FSRS_SERVICE_TOKEN` com um valor aleatório longo.
4. `npm install` e inicie a aplicação.

### 3.3 `.env` do CodeIgniter

```env
FSRS_SERVICE_URL = 'https://seu-app-node.hostingersite.com'
FSRS_SERVICE_TOKEN = 'o-mesmo-token-do-passo-anterior'

OPENAI_API_KEY = 'sk-proj-...'
OPENAI_MODEL_FLASHCARDS = 'gpt-4.1-mini'
```

Sem `OPENAI_API_KEY` o módulo funciona normalmente — apenas a geração interna
por IA fica desabilitada, com aviso na tela. A criação manual e a API externa
continuam disponíveis.

### 3.4 Verificação

```bash
php spark flashcards:self-test
```

Confere serviço FSRS, criação de cartões, duplicidades, cloze, sanitização,
sessão, idempotência, bloqueio otimista, desfazer, SSRF, tokens, importação e
estatísticas. Cria e remove os próprios dados.

---

## 4. Pontos de arquitetura que importam

### Nenhum intervalo é calculado em PHP

`FsrsClientService` é o único caminho para uma data de revisão. Quando o serviço
Node não responde, `FsrsUnavailableException` sobe até o controller, que devolve
**503** e **não grava nada**:

> Não foi possível calcular a próxima revisão. Sua resposta não foi registrada. Tente novamente.

A prévia dos intervalos é a única exceção: se ela falhar, os botões continuam
funcionando sem o rótulo — a revisão em si permanece bloqueada.

### Idempotência das avaliações

Cada avaliação envia um `request_uuid` (UUID v4 gerado no navegador), gravado com
índice único em `study_flashcard_reviews.uuid`. Duplo clique ou reenvio por falha
de rede retornam `duplicate: true` sem gravar uma segunda revisão.

### Concorrência

`study_flashcard_states.version` implementa bloqueio otimista. O `UPDATE` só
ocorre se a versão lida ainda for a atual; caso contrário a operação é recusada
com “Este cartão foi atualizado em outra aba”.

### Desfazer

Usa `rollback` do `ts-fsrs` com o log original guardado em `fsrs_log`. O registro
não é apagado — recebe `undone = 1`, preservando o histórico.

### Cloze

Uma anotação com `{{c1::…}} {{c2::…}}` gera dois cartões independentes. Como
ambos compartilham o mesmo texto, o índice da lacuna entra na assinatura de
duplicidade — sem isso o segundo cartão seria descartado.

### Segurança da IA

- A chave existe apenas no servidor; a tela administrativa mostra `sk-proj-••••••••••••A1B2`.
- Todo material importado é delimitado por `<<<CONTEUDO>>>` e tratado como dado
  não confiável — instruções embutidas na fonte não são obedecidas.
- A OpenAI nunca recebe apenas a URL: o backend busca, valida e limpa a página.
- SSRF: bloqueio de `localhost`, `127.0.0.0/8`, faixas privadas, link-local
  (que cobre `169.254.169.254`), somente HTTP/HTTPS, com revalidação a **cada**
  redirecionamento.
- Structured Outputs com `strict: true` e `additionalProperties: false`; ainda
  assim o backend revalida tudo e trata recusa e saída incompleta.

### API externa

Duplicidade controlada em três níveis: `Idempotency-Key`/`external_id` do lote,
`external_id` de cada cartão e assinatura normalizada de conteúdo. Cartões
importados entram como **Novo**, sem data fixa, respeitando o limite diário.
O `requires_approval` do token tem precedência sobre o pedido do cliente.

---

## 5. Fases posteriores (fora do MVP, conforme §5.2 do PRD)

PDF, imagens, oclusão de imagem, áudio, transcrição de aula, pacotes do Anki,
compartilhamento de baralhos, revisão colaborativa, otimização personalizada dos
parâmetros FSRS, simulados, app nativo, push e revisão offline.
